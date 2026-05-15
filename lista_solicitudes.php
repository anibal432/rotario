<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'conexion.php';

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

// Parámetros de filtrado (del nuevo archivo)
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'activas';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'urgencia';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$where_conditions = [];
$params = [];

// Determinar qué solicitudes mostrar según la vista (del nuevo archivo)
if ($vista === 'activas') {
    // SOLO solicitudes en proceso, sin aprobar y sin vencer
    $where_conditions[] = "(
        e.Estado_Estudiante = 'Activo'
        AND (
            (c.Estado_Cita IN ('Programada', 'Reprogramada') AND c.Fecha_Cita >= CURDATE())
            OR c.Fecha_Cita IS NULL
            OR (ev.Estado_Evaluacion = 'Pendiente')
        )
        AND (ev.Estado_Evaluacion IS NULL OR ev.Estado_Evaluacion = 'Pendiente')
    )";
} else {
    // Historial: Todo lo que YA se procesó, venció o completó
    $where_conditions[] = "(
        e.Estado_Estudiante IN ('Rechazado', 'Graduado', 'Retirado', 'Suspendido')
        OR ev.Estado_Evaluacion IN ('Aprobado', 'Rechazado')
        OR c.Estado_Cita IN ('Cancelada', 'Completada')
        OR (c.Fecha_Cita < CURDATE() AND c.Estado_Cita IN ('Programada', 'Reprogramada'))
    )";
}

if ($filtro_estado !== 'todos') {
    $where_conditions[] = "e.Estado_Estudiante = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_busqueda)) {
    $where_conditions[] = "(e.Nombres_Apellidos LIKE ? OR e.Email LIKE ? OR e.Telefono LIKE ? OR e.Numero_Expediente LIKE ?)";
    $busqueda_param = "%$filtro_busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Orden por urgencia: los más próximos primero (del nuevo archivo)
$order_by = match($orden) {
    'urgencia' => 'CASE 
                    WHEN MAX(c.Fecha_Cita) IS NULL THEN 3
                    WHEN MAX(c.Fecha_Cita) = CURDATE() THEN 0
                    WHEN MAX(c.Fecha_Cita) > CURDATE() THEN 1
                    ELSE 2
                   END ASC,
                   MAX(c.Fecha_Cita) ASC,
                   e.Fecha_Registro ASC',
    'antiguo' => 'e.Fecha_Registro ASC',
    'reciente' => 'e.Fecha_Registro DESC',
    'nombre' => 'e.Nombres_Apellidos ASC',
    'edad' => 'e.Edad DESC',
    default => 'CASE 
                    WHEN MAX(c.Fecha_Cita) IS NULL THEN 3
                    WHEN MAX(c.Fecha_Cita) = CURDATE() THEN 0
                    WHEN MAX(c.Fecha_Cita) > CURDATE() THEN 1
                    ELSE 2
                   END ASC,
                   MAX(c.Fecha_Cita) ASC,
                   e.Fecha_Registro ASC'
};

try {
    $sql_count = "SELECT COUNT(DISTINCT e.Id_Estudiante) as total 
                  FROM Estudiantes e 
                  LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
                  LEFT JOIN Citas_Entrevista c ON e.Id_Estudiante = c.Id_Estudiante
                  $where_sql";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($params);
    $total_registros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $por_pagina);

    $sql = "SELECT 
                e.Id_Estudiante,
                e.Numero_Expediente,
                e.Nombres_Apellidos,
                e.Edad,
                e.Email,
                e.Telefono,
                e.Estado_Estudiante,
                e.Fecha_Registro,
                MAX(ev.Id_Evaluacion) as Id_Evaluacion,
                MAX(ev.Estado_Evaluacion) as Estado_Evaluacion,
                MAX(ev.Fecha_Evaluacion) as Fecha_Evaluacion,
                MAX(ev.Motivo_Rechazo) as Motivo_Rechazo,
                MAX(c.Id_Cita) as Id_Cita,
                MAX(c.Fecha_Cita) as Fecha_Cita,
                MAX(c.Hora_Cita) as Hora_Cita,
                MAX(c.Estado_Cita) as Estado_Cita,
                CASE 
                    WHEN MAX(c.Fecha_Cita) IS NULL THEN 'sin_cita'
                    WHEN MAX(c.Estado_Cita) = 'Cancelada' THEN 'cancelada'
                    WHEN MAX(c.Estado_Cita) = 'Completada' THEN 'completada'
                    WHEN MAX(c.Estado_Cita) = 'Reprogramada' AND MAX(c.Fecha_Cita) = CURDATE() THEN 'hoy'
                    WHEN MAX(c.Estado_Cita) = 'Reprogramada' AND MAX(c.Fecha_Cita) > CURDATE() THEN 'reprogramada'
                    WHEN MAX(c.Estado_Cita) = 'Reprogramada' AND MAX(c.Fecha_Cita) < CURDATE() THEN 'vencida'
                    WHEN MAX(c.Fecha_Cita) < CURDATE() 
                         AND MAX(c.Estado_Cita) = 'Programada' 
                         AND MAX(ev.Estado_Evaluacion) IN ('Aprobado', 'Rechazado') THEN 'completada'
                    WHEN MAX(c.Fecha_Cita) < CURDATE() 
                         AND MAX(c.Estado_Cita) = 'Programada' 
                         AND (MAX(ev.Estado_Evaluacion) IS NULL OR MAX(ev.Estado_Evaluacion) = 'Pendiente') THEN 'vencida'
                    WHEN MAX(c.Fecha_Cita) = CURDATE() AND MAX(c.Estado_Cita) = 'Programada' THEN 'hoy'
                    WHEN MAX(c.Fecha_Cita) > CURDATE() AND MAX(c.Estado_Cita) = 'Programada' THEN 'programada'
                    ELSE 'pendiente'
                END as Estado_Cita_Real,
                DATEDIFF(MAX(c.Fecha_Cita), CURDATE()) as Dias_Restantes
            FROM Estudiantes e
            LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
            LEFT JOIN Citas_Entrevista c ON e.Id_Estudiante = c.Id_Estudiante
            $where_sql
            GROUP BY e.Id_Estudiante, e.Numero_Expediente, e.Nombres_Apellidos, e.Edad, 
                     e.Email, e.Telefono, e.Estado_Estudiante, e.Fecha_Registro
            ORDER BY $order_by
            LIMIT ? OFFSET ?";
    
    $params[] = $por_pagina;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas separadas para activas e historial (del nuevo archivo)
    if ($vista === 'activas') {
        $sql_stats = "SELECT 
                        COUNT(DISTINCT e.Id_Estudiante) as total,
                        SUM(CASE WHEN e.Estado_Estudiante = 'Activo' THEN 1 ELSE 0 END) as activos,
                        SUM(CASE WHEN c.Estado_Cita IN ('Programada', 'Reprogramada') AND c.Fecha_Cita = CURDATE() THEN 1 ELSE 0 END) as citas_hoy,
                        SUM(CASE WHEN c.Estado_Cita IN ('Programada', 'Reprogramada') AND c.Fecha_Cita > CURDATE() AND c.Fecha_Cita <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as citas_semana,
                        SUM(CASE WHEN c.Fecha_Cita IS NULL THEN 1 ELSE 0 END) as sin_cita,
                        SUM(CASE WHEN ev.Estado_Evaluacion = 'Pendiente' OR ev.Estado_Evaluacion IS NULL THEN 1 ELSE 0 END) as pendiente_evaluacion
                      FROM Estudiantes e
                      LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
                      LEFT JOIN Citas_Entrevista c ON e.Id_Estudiante = c.Id_Estudiante
                      WHERE e.Estado_Estudiante = 'Activo'
                        AND (
                            (c.Estado_Cita IN ('Programada', 'Reprogramada') AND c.Fecha_Cita >= CURDATE())
                            OR c.Fecha_Cita IS NULL
                            OR (ev.Estado_Evaluacion = 'Pendiente')
                        )
                        AND (ev.Estado_Evaluacion IS NULL OR ev.Estado_Evaluacion = 'Pendiente')";
    } else {
        $sql_stats = "SELECT 
                        COUNT(DISTINCT e.Id_Estudiante) as total,
                        SUM(CASE WHEN ev.Estado_Evaluacion = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
                        SUM(CASE WHEN ev.Estado_Evaluacion = 'Rechazado' OR e.Estado_Estudiante = 'Rechazado' THEN 1 ELSE 0 END) as rechazados,
                        SUM(CASE WHEN c.Estado_Cita = 'Completada' THEN 1 ELSE 0 END) as completadas,
                        SUM(CASE WHEN c.Estado_Cita = 'Cancelada' THEN 1 ELSE 0 END) as canceladas,
                        SUM(CASE WHEN c.Fecha_Cita < CURDATE() AND c.Estado_Cita IN ('Programada', 'Reprogramada') THEN 1 ELSE 0 END) as vencidas
                      FROM Estudiantes e
                      LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
                      LEFT JOIN Citas_Entrevista c ON e.Id_Estudiante = c.Id_Estudiante
                      WHERE (
                            e.Estado_Estudiante IN ('Rechazado', 'Graduado', 'Retirado', 'Suspendido')
                            OR ev.Estado_Evaluacion IN ('Aprobado', 'Rechazado')
                            OR c.Estado_Cita IN ('Cancelada', 'Completada')
                            OR (c.Fecha_Cita < CURDATE() AND c.Estado_Cita IN ('Programada', 'Reprogramada'))
                        )";
    }
    
    $stmt = $pdo->query($sql_stats);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function obtenerBadgeCita($solicitud) {
    $estado = $solicitud['Estado_Cita_Real'];
    $fecha = $solicitud['Fecha_Cita'];
    $hora = $solicitud['Hora_Cita'];
    $estado_evaluacion = $solicitud['Estado_Evaluacion'];
    $estado_cita_db = $solicitud['Estado_Cita'];
    $dias_restantes = $solicitud['Dias_Restantes'];
    
    switch ($estado) {
        case 'sin_cita':
            return '<span class="badge badge-pendiente"><i class="fas fa-clock"></i> Sin cita programada</span>';
        
        case 'programada':
            $texto = date('d/m/Y', strtotime($fecha));
            if ($hora) $texto .= ' ' . date('H:i', strtotime($hora));
            
            // Mostrar días restantes si es relevante
            if ($dias_restantes !== null && $dias_restantes > 0 && $dias_restantes <= 7) {
                $texto .= " <small>(en {$dias_restantes} " . ($dias_restantes == 1 ? 'día' : 'días') . ")</small>";
            }
            
            return '<span class="badge badge-programada"><i class="fas fa-calendar-check"></i> ' . $texto . '</span>';
        
        case 'reprogramada':
            $texto = date('d/m/Y', strtotime($fecha));
            if ($hora) $texto .= ' a las ' . date('H:i', strtotime($hora));
            
            // Mostrar días restantes
            if ($dias_restantes !== null && $dias_restantes > 0 && $dias_restantes <= 7) {
                $texto .= " <small>(en {$dias_restantes} " . ($dias_restantes == 1 ? 'día' : 'días') . ")</small>";
            }
            
            return '<span class="badge badge-reprogramada"><i class="fas fa-calendar-alt"></i> Reprogramada: ' . $texto . '</span>';
        
        case 'hoy':
            $texto = 'HOY';
            if ($hora) $texto .= ' a las ' . date('H:i', strtotime($hora));
            
            if ($estado_cita_db === 'Reprogramada') {
                return '<span class="badge badge-hoy-reprogramada"><i class="fas fa-exclamation-circle"></i> ' . $texto . ' (Reprogramada)</span>';
            }
            return '<span class="badge badge-hoy"><i class="fas fa-exclamation-circle"></i> ' . $texto . '</span>';
        
        case 'vencida':
            $texto = date('d/m/Y', strtotime($fecha));
            if ($estado_cita_db === 'Reprogramada') {
                return '<span class="badge badge-vencida"><i class="fas fa-calendar-times"></i> Vencida (Reprogramada): ' . $texto . '</span>';
            }
            return '<span class="badge badge-vencida"><i class="fas fa-calendar-times"></i> Vencida: ' . $texto . '</span>';
        
        case 'completada':
            $texto = date('d/m/Y', strtotime($fecha));
            if ($estado_evaluacion === 'Aprobado') {
                return '<span class="badge badge-completada"><i class="fas fa-check-circle"></i> Completada: ' . $texto . '</span>';
            } else if ($estado_evaluacion === 'Rechazado') {
                return '<span class="badge badge-completada-rechazado"><i class="fas fa-times-circle"></i> Completada: ' . $texto . '</span>';
            }
            return '<span class="badge badge-completada"><i class="fas fa-check-circle"></i> Completada: ' . $texto . '</span>';
        
        case 'cancelada':
            return '<span class="badge badge-cancelada"><i class="fas fa-times-circle"></i> Cancelada</span>';
        
        default:
            return '<span class="badge badge-pendiente"><i class="fas fa-question-circle"></i> Pendiente</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Becas - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/solicitudes.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header (del archivo antiguo) -->
            <div class="header">
                <h1>Administración de Solicitudes</h1>
                <div class="user-info">
                    <div class="user-avatar-wrapper">
                        <?php
                            $iconClass = '';
                            switch ($role) {
                                case 'Administrador':
                                    $iconClass = 'fa-solid fa-crown';
                                    break;
                                case 'Coordinador':
                                    $iconClass = 'fa-solid fa-user-tie';
                                    break;
                                default:
                                    $iconClass = 'fa-solid fa-user';
                                    break;
                            }
                        ?>
                        <i class="<?= $iconClass ?> user-role-icon"></i>
                        <div class="user-avatar-main"><?= getInitials($username) ?></div>
                    </div>
                    <div class="user-details-main">
                        <div class="user-name-main"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role-main"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <div class="tabs-container">
                <a href="?vista=activas" class="tab <?= $vista === 'activas' ? 'active' : '' ?>">
                    <i class="fas fa-list-check"></i> Solicitudes Activas
                </a>
                <a href="?vista=historial" class="tab <?= $vista === 'historial' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i> Historial
                </a>
            </div>

            <!-- Alert informativo (del archivo nuevo) -->
            <?php if ($vista === 'activas'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div><strong>Vista de Solicitudes Activas:</strong> Aquí se muestran todas las solicitudes en proceso que están pendientes de evaluación o tienen citas programadas. Las citas más urgentes aparecen primero.</div>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div><strong>Vista de Historial:</strong> Aquí se muestran todas las solicitudes que ya fueron procesadas: aprobadas, rechazadas, con citas completadas, canceladas o vencidas.</div>
            </div>
            <?php endif; ?>

            <!-- Stats Grid (combinado) -->
            <div class="stats-grid">
                <?php if ($vista === 'activas'): ?>
                    <div class="stat-box">
                        <h3><i class="fas fa-clipboard-list"></i> Total Activas</h3>
                        <div class="number"><?= number_format($stats['total']) ?></div>
                    </div>
                    <div class="stat-box activos">
                        <h3><i class="fas fa-calendar-day"></i> Citas Hoy</h3>
                        <div class="number"><?= number_format($stats['citas_hoy']) ?></div>
                    </div>
                    <div class="stat-box aprobados">
                        <h3><i class="fas fa-calendar-week"></i> Esta Semana</h3>
                        <div class="number"><?= number_format($stats['citas_semana']) ?></div>
                    </div>
                    <div class="stat-box citas">
                        <h3><i class="fas fa-clock"></i> Sin Cita</h3>
                        <div class="number"><?= number_format($stats['sin_cita']) ?></div>
                    </div>
                <?php else: ?>
                    <div class="stat-box">
                        <h3><i class="fas fa-archive"></i> Total Historial</h3>
                        <div class="number"><?= number_format($stats['total']) ?></div>
                    </div>
                    <div class="stat-box aprobados">
                        <h3><i class="fas fa-check-circle"></i> Aprobados</h3>
                        <div class="number"><?= number_format($stats['aprobados']) ?></div>
                    </div>
                    <div class="stat-box rechazados">
                        <h3><i class="fas fa-times-circle"></i> Rechazados</h3>
                        <div class="number"><?= number_format($stats['rechazados']) ?></div>
                    </div>
                    <div class="stat-box citas">
                        <h3><i class="fas fa-check-double"></i> Completadas</h3>
                        <div class="number"><?= number_format($stats['completadas']) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filtros (combinado) -->
            <div class="filters">
                <form method="GET" action="">
                    <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">
                    
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Buscar</label>
                        <input type="text" name="buscar" placeholder="Nombre, email, teléfono o expediente..." value="<?= htmlspecialchars($filtro_busqueda) ?>">
                    </div>
                    
                    <?php if ($vista === 'historial'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Estado</label>
                        <select name="estado">
                            <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos los estados</option>
                            <option value="Aprobado" <?= $filtro_estado === 'Aprobado' ? 'selected' : '' ?>>Aprobados</option>
                            <option value="Rechazado" <?= $filtro_estado === 'Rechazado' ? 'selected' : '' ?>>Rechazados</option>
                            <option value="Graduado" <?= $filtro_estado === 'Graduado' ? 'selected' : '' ?>>Graduados</option>
                            <option value="Suspendido" <?= $filtro_estado === 'Suspendido' ? 'selected' : '' ?>>Suspendidos</option>
                            <option value="Retirado" <?= $filtro_estado === 'Retirado' ? 'selected' : '' ?>>Retirados</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Estado</label>
                        <select name="estado">
                            <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="Activo" <?= $filtro_estado === 'Activo' ? 'selected' : '' ?>>Activos</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-sort"></i> Ordenar</label>
                        <select name="orden">
                            <?php if ($vista === 'activas'): ?>
                                <option value="urgencia" <?= $orden === 'urgencia' ? 'selected' : '' ?>>Más urgentes primero</option>
                                <option value="reciente" <?= $orden === 'reciente' ? 'selected' : '' ?>>Más recientes</option>
                                <option value="antiguo" <?= $orden === 'antiguo' ? 'selected' : '' ?>>Más antiguos</option>
                            <?php else: ?>
                                <option value="reciente" <?= $orden === 'reciente' ? 'selected' : '' ?>>Más recientes</option>
                                <option value="antiguo" <?= $orden === 'antiguo' ? 'selected' : '' ?>>Más antiguos</option>
                            <?php endif; ?>
                            <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre A-Z</option>
                            <option value="edad" <?= $orden === 'edad' ? 'selected' : '' ?>>Edad</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    
                    <a href="?vista=<?= htmlspecialchars($vista) ?>" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </form>
            </div>

            <!-- Tabla (combinada) -->
            <div class="table-container">
                <?php if (count($solicitudes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Expediente</th>
                            <th>Nombre</th>
                            <th>Edad</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <?php if ($vista === 'historial'): ?>
                            <th>Evaluación</th>
                            <?php endif; ?>
                            <th>Registro</th>
                            <th>Cita</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $sol): 
                            $row_class = '';
                            if ($vista === 'activas') {
                                if ($sol['Estado_Cita_Real'] === 'hoy') {
                                    $row_class = 'urgente-hoy';
                                } elseif ($sol['Dias_Restantes'] !== null && $sol['Dias_Restantes'] > 0 && $sol['Dias_Restantes'] <= 7) {
                                    $row_class = 'urgente-semana';
                                }
                            }
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><strong><?= htmlspecialchars($sol['Numero_Expediente'] ?? 'N/A') ?></strong></td>
                            <td><?= htmlspecialchars($sol['Nombres_Apellidos']) ?></td>
                            <td><?= $sol['Edad'] ?> años</td>
                            <td>
                                <div style="font-size: 12px;"><?= htmlspecialchars($sol['Email']) ?></div>
                                <small style="color: #7f8c8d; font-size: 11px;"><?= htmlspecialchars($sol['Telefono']) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?= strtolower($sol['Estado_Estudiante']) ?>">
                                    <?= $sol['Estado_Estudiante'] ?>
                                </span>
                            </td>
                            <?php if ($vista === 'historial'): ?>
                            <td>
                                <?php if ($sol['Estado_Evaluacion']): ?>
                                    <span class="badge badge-<?= strtolower($sol['Estado_Evaluacion']) ?>">
                                        <?= $sol['Estado_Evaluacion'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-pendiente">Sin evaluar</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><?= date('d/m/Y', strtotime($sol['Fecha_Registro'])) ?></td>
                            <td style="max-width:150px; overflow-x:auto; white-space:nowrap;">
  <?= obtenerBadgeCita($sol) ?>
</td>

                            <td>
                                <div class="actions">
                                    <a href="admin_detalle.php?id=<?= $sol['Id_Estudiante'] ?>" class="btn-icon btn-view" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($vista === 'activas' && in_array($sol['Estado_Cita_Real'], ['sin_cita', 'programada', 'reprogramada', 'hoy'])): ?>
                                    <a href="gestionar_cita.php?id=<?= $sol['Id_Estudiante'] ?>" class="btn-icon btn-calendar" title="<?= $sol['Estado_Cita_Real'] === 'sin_cita' ? 'Programar cita' : 'Gestionar cita' ?>">
                                        <i class="fa-solid fa-calendar-days <?= $sol['Estado_Cita_Real'] === 'sin_cita' ? 'fa-plus' : 'fa-edit' ?>"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($vista === 'historial' && $sol['Estado_Estudiante'] === 'Rechazado'): ?>
                                    <a href="admin_reactivar.php?id=<?= $sol['Id_Estudiante'] ?>" class="btn-icon btn-reactivar" title="Reactivar solicitud" 
                                       onclick="return confirmReactivar(event)">
                                        <i class="fa-solid fa-rotate-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina > 1): ?>
                        <a href="?vista=<?= $vista ?>&pagina=<?= $pagina - 1 ?>&estado=<?= $filtro_estado ?>&buscar=<?= urlencode($filtro_busqueda) ?>&orden=<?= $orden ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $rango_inicio = max(1, $pagina - 2);
                    $rango_fin = min($total_paginas, $pagina + 2);
                    
                    if ($rango_inicio > 1): ?>
                        <a href="?vista=<?= $vista ?>&pagina=1&estado=<?= $filtro_estado ?>&buscar=<?= urlencode($filtro_busqueda) ?>&orden=<?= $orden ?>">1</a>
                        <?php if ($rango_inicio > 2): ?><span>...</span><?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $rango_inicio; $i <= $rango_fin; $i++): ?>
                        <?php if ($i === $pagina): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?vista=<?= $vista ?>&pagina=<?= $i ?>&estado=<?= $filtro_estado ?>&buscar=<?= urlencode($filtro_busqueda) ?>&orden=<?= $orden ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($rango_fin < $total_paginas): ?>
                        <?php if ($rango_fin < $total_paginas - 1): ?><span>...</span><?php endif; ?>
                        <a href="?vista=<?= $vista ?>&pagina=<?= $total_paginas ?>&estado=<?= $filtro_estado ?>&buscar=<?= urlencode($filtro_busqueda) ?>&orden=<?= $orden ?>"><?= $total_paginas ?></a>
                    <?php endif; ?>
                    
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?vista=<?= $vista ?>&pagina=<?= $pagina + 1 ?>&estado=<?= $filtro_estado ?>&buscar=<?= urlencode($filtro_busqueda) ?>&orden=<?= $orden ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No se encontraron resultados</h3>
                    <p>
                        <?php if ($vista === 'activas'): ?>
                            No hay solicitudes activas que coincidan con los filtros de búsqueda.
                        <?php else: ?>
                            No hay registros en el historial que coincidan con los filtros de búsqueda.
                        <?php endif; ?>
                    </p>
                    <a href="?vista=<?= htmlspecialchars($vista) ?>" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-redo"></i> Limpiar filtros
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function confirmReactivar(event) {
            event.preventDefault();
            const url = event.target.closest('a').href;
            
            Swal.fire({
                title: '¿Reactivar solicitud?',
                text: "Esta acción reactivará la solicitud rechazada.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, reactivar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        setTimeout(function() { 
            location.reload(); 
        }, 300000);
    </script>
</body>
</html>