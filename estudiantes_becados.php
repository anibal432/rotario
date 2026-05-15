<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Obtener información del usuario para el header
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

$user_id = $_SESSION['user_id'];

$filtro_estado = $_GET['estado'] ?? 'Activa';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_año = $_GET['año'] ?? date('Y');

$sql = "SELECT 
            e.Id_Estudiante,
            e.Numero_Expediente,
            e.Nombres_Apellidos,
            e.Email,
            e.Telefono,
            e.Grado_Obtenido_Anterior,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Fecha_Inicio,
            b.Fecha_Fin,
            b.Promedio_Minimo,
            b.Promedio_Actual,
            (SELECT COUNT(*) FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca 
             AND YEAR(Fecha_Pago) = ?) as Pagos_Realizados,
            (SELECT MAX(Fecha_Pago) FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca) as Ultimo_Pago,
            (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante) as Ultima_Boleta,
            (SELECT Promedio FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante 
             ORDER BY Fecha_Subida DESC LIMIT 1) as Ultimo_Promedio
        FROM Estudiantes e
        INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE 1=1";

$params = [$filtro_año];

if ($filtro_estado !== 'Todas') {
    $sql .= " AND b.Estado_Beca = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_busqueda)) {
    $sql .= " AND (e.Nombres_Apellidos LIKE ? OR e.Numero_Expediente LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$sql .= " ORDER BY e.Nombres_Apellidos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$becados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_stats = "SELECT 
                COUNT(*) as Total_Becados,
                COUNT(CASE WHEN Estado_Beca = 'Activa' THEN 1 END) as Becas_Activas,
                COUNT(CASE WHEN Estado_Beca = 'Suspendida' THEN 1 END) as Becas_Suspendidas,
                COUNT(CASE WHEN Estado_Beca = 'Finalizada' THEN 1 END) as Becas_Finalizadas,
                SUM(CASE WHEN Estado_Beca = 'Activa' THEN Monto_Mensual ELSE 0 END) as Monto_Mensual_Total
              FROM Becas_Otorgadas";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$sql_alertas = "SELECT 
                    e.Id_Estudiante,
                    e.Nombres_Apellidos,
                    b.Promedio_Minimo,
                    b.Promedio_Actual,
                    CASE 
                        WHEN (SELECT MAX(Fecha_Pago) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca) IS NULL
                        THEN DATEDIFF(NOW(), b.Fecha_Inicio)
                        ELSE DATEDIFF(NOW(), (SELECT MAX(Fecha_Pago) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca))
                    END as Dias_Sin_Pago,
                    CASE 
                        WHEN (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones WHERE Id_Estudiante = e.Id_Estudiante) IS NULL
                        THEN DATEDIFF(NOW(), b.Fecha_Inicio)
                        ELSE DATEDIFF(NOW(), (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones WHERE Id_Estudiante = e.Id_Estudiante))
                    END as Dias_Sin_Boleta
                FROM Estudiantes e
                INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
                WHERE b.Estado_Beca = 'Activa'
                HAVING 
                    (b.Promedio_Actual IS NOT NULL AND b.Promedio_Actual < b.Promedio_Minimo)
                    OR Dias_Sin_Pago > 45
                    OR Dias_Sin_Boleta > 90";

$stmt_alertas = $pdo->prepare($sql_alertas);
$stmt_alertas->execute();
$alertas = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes Becados - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/becados.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Estudiantes Becados</h1>
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

            <!-- Stats Grid Compacto -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['Total_Becados'] ?></h3>
                        <p>Total Estudiantes</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['Becas_Activas'] ?></h3>
                        <p>Becas Activas</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['Becas_Suspendidas'] ?></h3>
                        <p>Becas Suspendidas</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Q<?= number_format($stats['Monto_Mensual_Total'], 0) ?></h3>
                        <p>Monto Mensual Total</p>
                    </div>
                </div>
            </div>

            <!-- Alertas Section -->
            <?php if (count($alertas) > 0): ?>
            <div class="alertas-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Alertas y Pendientes</h2>
                <?php foreach ($alertas as $alerta): ?>
                    <?php
                    $es_critico = false;
                    $mensaje = [];
                    
                    if (isset($alerta['Promedio_Actual']) && $alerta['Promedio_Actual'] < $alerta['Promedio_Minimo']) {
                        $mensaje[] = "Promedio bajo: {$alerta['Promedio_Actual']} (mínimo: {$alerta['Promedio_Minimo']})";
                        $es_critico = true;
                    }
                    
                    if ($alerta['Dias_Sin_Pago'] > 45) {
                        $mensaje[] = "Sin pago hace {$alerta['Dias_Sin_Pago']} días";
                        if ($alerta['Dias_Sin_Pago'] > 60) $es_critico = true;
                    }
                    
                    if ($alerta['Dias_Sin_Boleta'] > 90) {
                        $mensaje[] = "Sin boleta hace {$alerta['Dias_Sin_Boleta']} días";
                        if ($alerta['Dias_Sin_Boleta'] > 120) $es_critico = true;
                    }
                    ?>
                    <div class="alerta-item <?= $es_critico ? 'critico' : '' ?>">
                        <div>
                            <strong><?= htmlspecialchars($alerta['Nombres_Apellidos']) ?></strong>
                            <div style="font-size: 0.85em; margin-top: 4px;">
                                <?= implode(' • ', $mensaje) ?>
                            </div>
                        </div>
                        <span class="badge <?= $es_critico ? 'badge-danger' : 'badge-warning' ?>">
                            <?= $es_critico ? 'URGENTE' : 'Atención' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alertas-section">
                <div class="no-alertas">
                    <i class="fas fa-check-circle"></i>
                    <p><strong>¡Todo en orden!</strong> No hay alertas pendientes.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="filtros">
                <form method="GET" action="">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label><i class="fas fa-search"></i> Buscar Estudiante</label>
                            <input type="text" 
                                   name="busqueda" 
                                   placeholder="Nombre o número de expediente..." 
                                   value="<?= htmlspecialchars($filtro_busqueda) ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Estado de Beca</label>
                            <select name="estado">
                                <option value="Todas" <?= $filtro_estado === 'Todas' ? 'selected' : '' ?>>Todas</option>
                                <option value="Activa" <?= $filtro_estado === 'Activa' ? 'selected' : '' ?>>Activas</option>
                                <option value="Suspendida" <?= $filtro_estado === 'Suspendida' ? 'selected' : '' ?>>Suspendidas</option>
                                <option value="Finalizada" <?= $filtro_estado === 'Finalizada' ? 'selected' : '' ?>>Finalizadas</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Año</label>
                            <select name="año">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $filtro_año == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla -->
            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-table"></i>
                        Lista de Estudiantes (<?= count($becados) ?>)
                    </h2>
                    <a href="exportar_becados.php" class="btn btn-secondary" onclick="return confirmExport()">
                        <i class="fas fa-file-excel"></i> Exportar
                    </a>
                </div>

                <?php if (count($becados) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Tipo de Beca</th>
                            <th>Monto Mensual</th>
                            <th>Estado</th>
                            <th>Pagos <?= $filtro_año ?></th>
                            <th>Promedio</th>
                            <th>Última Actividad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($becados as $estudiante): ?>
                        <tr>
                            <td>
                                <div class="estudiante-info">
                                    <span class="estudiante-nombre">
                                        <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>
                                    </span>
                                    <span class="estudiante-detalles">
                                        Exp: <?= htmlspecialchars($estudiante['Numero_Expediente']) ?> | 
                                        <?= htmlspecialchars($estudiante['Grado_Obtenido_Anterior']) ?>
                                    </span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($estudiante['Tipo_Beca']) ?></td>
                            <td><strong>Q<?= number_format($estudiante['Monto_Mensual'], 2) ?></strong></td>
                            <td>
                                <span class="badge badge-<?= $estudiante['Estado_Beca'] === 'Activa' ? 'success' : ($estudiante['Estado_Beca'] === 'Suspendida' ? 'warning' : 'info') ?>">
                                    <?= $estudiante['Estado_Beca'] ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $estudiante['Pagos_Realizados'] ?></strong> / 12 meses
                            </td>
                            <td>
                                <?php if ($estudiante['Ultimo_Promedio']): ?>
                                    <?php
                                    $clase_promedio = 'bueno';
                                    if ($estudiante['Ultimo_Promedio'] < $estudiante['Promedio_Minimo']) {
                                        $clase_promedio = 'malo';
                                    } elseif ($estudiante['Ultimo_Promedio'] < 80) {
                                        $clase_promedio = 'regular';
                                    }
                                    ?>
                                    <span class="promedio <?= $clase_promedio ?>">
                                        <?= number_format($estudiante['Ultimo_Promedio'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #bdc3c7;">Sin datos</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.8em;">
                                    <?php if ($estudiante['Ultimo_Pago']): ?>
                                        <div>💰 <?= date('d/m/Y', strtotime($estudiante['Ultimo_Pago'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($estudiante['Ultima_Boleta']): ?>
                                        <div>📄 <?= date('d/m/Y', strtotime($estudiante['Ultima_Boleta'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a href="detalle_becado.php?id=<?= $estudiante['Id_Estudiante'] ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="registrar_pago.php?id_beca=<?= $estudiante['Id_Beca'] ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-dollar-sign"></i>
                                    </a>
                                    <a href="subir_boleta.php?id=<?= $estudiante['Id_Estudiante'] ?>" 
                                       class="btn btn-warning">
                                        <i class="fas fa-upload"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No se encontraron estudiantes</h3>
                    <p>Intenta cambiar los filtros de búsqueda</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function confirmExport() {
            event.preventDefault();
            const url = event.target.href;
            
            Swal.fire({
                title: '¿Exportar datos?',
                text: "Se generará un archivo Excel con la información de los estudiantes becados.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, exportar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            
            return false;
        }

        // Mostrar SweetAlert si hay parámetros de filtro
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('busqueda') || urlParams.has('estado') || urlParams.has('año')) {
                Swal.fire({
                    title: 'Filtros aplicados',
                    text: 'Se han aplicado los filtros de búsqueda a la lista de estudiantes.',
                    icon: 'info',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
    </script>
</body>
</html>