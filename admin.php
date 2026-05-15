<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

require_once 'actualizar_estados_eventos.php';
actualizarEstadosEventos($pdo);

$sql_stats = "
    SELECT 
        (SELECT COUNT(*) FROM Solicitudes_Beca) as total_solicitudes,
        (SELECT COUNT(*) FROM Solicitudes_Beca WHERE Estado_Solicitud = 'Aprobado') as aprobadas,
        (SELECT COUNT(*) FROM Solicitudes_Beca WHERE Estado_Solicitud = 'Pendiente') as pendientes,
        (SELECT COUNT(*) FROM Solicitudes_Beca WHERE Estado_Solicitud = 'Rechazado') as rechazadas,
        (SELECT COUNT(*) FROM Becas_Otorgadas WHERE Estado_Beca = 'Activa') as becas_activas,
        (SELECT COUNT(*) FROM Estudiantes WHERE Estado_Beca = 'Activo') as estudiantes_activos,
        (SELECT COUNT(*) FROM Pagos_Becas 
         WHERE MONTH(Fecha_Pago) = MONTH(CURDATE()) 
         AND YEAR(Fecha_Pago) = YEAR(CURDATE())) as pagos_mes_actual,
        (SELECT COUNT(*) FROM Boletas_Calificaciones 
         WHERE MONTH(Fecha_Subida) = MONTH(CURDATE()) 
         AND YEAR(Fecha_Subida) = YEAR(CURDATE())) as boletas_mes_actual,
        (SELECT COUNT(*) FROM Testimonios WHERE Activo = 1) as testimonios_activos,
        (SELECT COUNT(*) FROM Estudiantes e
         INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
         WHERE b.Estado_Beca = 'Activa'
         AND NOT EXISTS (
            SELECT 1 FROM Evaluaciones_Anuales ea
            WHERE ea.Id_Beca = b.Id_Beca
            AND ea.Año_Evaluacion = YEAR(CURDATE())
         )) as evaluaciones_pendientes,
        (SELECT COUNT(*) FROM Eventos WHERE Estado_Evento IN ('Planificado', 'Inscripciones Abiertas')) as eventos_activos,
        (SELECT COUNT(*) FROM Inscripciones_Evento WHERE Estado_Inscripcion = 'Pendiente') as inscripciones_pendientes
";
$stmt = $pdo->query($sql_stats);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$sql_meses = "
    SELECT 
        MONTH(Fecha_Solicitud) as mes,
        COUNT(*) as cantidad
    FROM Solicitudes_Beca
    WHERE YEAR(Fecha_Solicitud) = YEAR(CURDATE())
    GROUP BY MONTH(Fecha_Solicitud)
    ORDER BY mes
";
$stmt_meses = $pdo->query($sql_meses);
$datos_meses = $stmt_meses->fetchAll(PDO::FETCH_ASSOC);

$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$cantidades = array_fill(0, 12, 0);

foreach ($datos_meses as $dato) {
    $cantidades[$dato['mes'] - 1] = $dato['cantidad'];
}

$max_cantidad = max($cantidades);
$alturas = array_map(function($cant) use ($max_cantidad) {
    return $max_cantidad > 0 ? ($cant / $max_cantidad) * 200 : 0;
}, $cantidades);

// Obtener solicitudes recientes
$sql_recientes = "
    SELECT 
        s.Id_Solicitud,
        s.Fecha_Solicitud,
        s.Nombres_Apellidos,
        s.Email,
        s.Telefono,
        s.Nivel_Educativo,
        s.Estado_Solicitud,
        s.Numero_Expediente
    FROM Solicitudes_Beca s
    ORDER BY s.Fecha_Solicitud DESC
    LIMIT 10
";
$stmt_recientes = $pdo->query($sql_recientes);
$recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener alertas activas
$sql_alertas = "
    SELECT 
        e.Id_Estudiante,
        e.Nombres_Apellidos,
        b.Id_Beca,
        b.Promedio_Minimo,
        b.Promedio_Actual,
        CASE 
            WHEN b.Promedio_Actual < b.Promedio_Minimo THEN 1
            ELSE 0
        END as alerta_promedio,
        CASE 
            WHEN DATEDIFF(CURDATE(), 
                (SELECT MAX(Fecha_Pago) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca)) > 25 
            THEN 1
            ELSE 0
        END as alerta_pago,
        CASE 
            WHEN NOT EXISTS (
                SELECT 1 FROM Boletas_Calificaciones 
                WHERE Id_Estudiante = e.Id_Estudiante 
                AND Fecha_Subida >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            ) THEN 1
            ELSE 0
        END as alerta_boleta
    FROM Estudiantes e
    INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
    WHERE b.Estado_Beca = 'Activa'
    HAVING alerta_promedio = 1 OR alerta_pago = 1 OR alerta_boleta = 1
    LIMIT 5
";
$stmt_alertas = $pdo->query($sql_alertas);
$alertas = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>

    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Dashboard</h1>
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
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['total_solicitudes'] ?? 0 ?></h3>
                        <p>Total Solicitudes</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['becas_activas'] ?? 0 ?></h3>
                        <p>Becas Activas</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['pendientes'] ?? 0 ?></h3>
                        <p>Solicitudes Pendientes</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['eventos_activos'] ?? 0 ?></h3>
                        <p>Eventos Activos</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['inscripciones_pendientes'] ?? 0 ?></h3>
                        <p>Inscripciones Pendientes</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon teal">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['evaluaciones_pendientes'] ?? 0 ?></h3>
                        <p>Evaluaciones Pendientes</p>
                    </div>
                </div>
            </div>

            <?php if (count($alertas) > 0): ?>
            <div class="alerts-section">
                <h3><i class="fas fa-exclamation-triangle"></i> Alertas Activas</h3>
                <?php foreach ($alertas as $alerta): ?>
                <div class="alert-item">
                    <div class="alert-content">
                        <div class="alert-title">
                            <?= htmlspecialchars($alerta['Nombres_Apellidos']) ?>
                        </div>
                        <div class="alert-description">
                            <?php if ($alerta['alerta_promedio']): ?>
                                <i class="fas fa-exclamation-circle"></i> Promedio bajo: <?= $alerta['Promedio_Actual'] ?> (mínimo: <?= $alerta['Promedio_Minimo'] ?>)
                            <?php elseif ($alerta['alerta_pago']): ?>
                                <i class="fas fa-dollar-sign"></i> Pago próximo a vencer
                            <?php elseif ($alerta['alerta_boleta']): ?>
                                <i class="fas fa-file-alt"></i> Sin boleta reciente (más de 60 días)
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="detalle_becado.php?id=<?= $alerta['Id_Estudiante'] ?>" class="alert-action">
                        Ver Detalles
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>