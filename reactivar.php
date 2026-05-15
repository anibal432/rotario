<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

$vista = isset($_GET['vista']) ? $_GET['vista'] : 'reactivar';

try {
    if ($vista === 'reactivar') {
        $sql = "SELECT 
                    e.Id_Estudiante,
                    e.Numero_Expediente,
                    e.Nombres_Apellidos,
                    e.Email,
                    e.Telefono,
                    e.Estado_Estudiante,
                    e.Fecha_Registro,
                    ev.Id_Evaluacion,
                    ev.Estado_Evaluacion,
                    ev.Fecha_Evaluacion,
                    ev.Motivo_Rechazo,
                    ev.Fecha_Decision,
                    CASE 
                        WHEN ev.Estado_Evaluacion = 'Rechazado' THEN 'Rechazado'
                        ELSE e.Estado_Estudiante
                    END as Estado_Real
                FROM Estudiantes e
                LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
                WHERE ev.Estado_Evaluacion = 'Rechazado' 
                   OR e.Estado_Estudiante IN ('Suspendido', 'Retirado')
                ORDER BY e.Fecha_Registro DESC";
    } else {
        // Vista de historial - SIN GROUP BY (del nuevo archivo)
        $sql = "SELECT 
                    e.Id_Estudiante,
                    e.Numero_Expediente,
                    e.Nombres_Apellidos,
                    e.Email,
                    e.Telefono,
                    e.Estado_Estudiante,
                    e.Fecha_Registro,
                    ev.Estado_Evaluacion,
                    ev.Fecha_Evaluacion,
                    b.Actividades as Accion_Reactivacion,
                    b.Fecha as Fecha_Reactivacion,
                    b.Hora as Hora_Reactivacion,
                    c.Fecha_Cita,
                    c.Estado_Cita
                FROM Estudiantes e
                LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
                LEFT JOIN Citas_Entrevista c ON e.Id_Estudiante = c.Id_Estudiante
                LEFT JOIN (
                    SELECT Id_Estudiante, Actividades, Fecha, Hora
                    FROM (
                        SELECT 
                            SUBSTRING_INDEX(SUBSTRING_INDEX(Actividades, '(ID: ', -1), ')', 1) COLLATE utf8mb4_general_ci as Id_Estudiante,
                            Actividades,
                            Fecha,
                            Hora,
                            ROW_NUMBER() OVER (PARTITION BY SUBSTRING_INDEX(SUBSTRING_INDEX(Actividades, '(ID: ', -1), ')', 1) ORDER BY Fecha DESC, Hora DESC) as rn
                        FROM Bitacora
                        WHERE Actividades LIKE '%Reactivó%' OR Actividades LIKE '%reactivó%'
                    ) as ranked
                    WHERE rn = 1
                ) b ON CAST(e.Id_Estudiante AS CHAR CHARSET utf8mb4) COLLATE utf8mb4_general_ci = b.Id_Estudiante
                WHERE (
                    e.Estado_Estudiante = 'Graduado'
                    OR (e.Estado_Estudiante = 'Activo' AND b.Actividades IS NOT NULL)
                    OR c.Estado_Cita IN ('Completada', 'Cancelada')
                )
                ORDER BY COALESCE(b.Fecha, e.Fecha_Registro) DESC";
    }
    
    $stmt = $pdo->query($sql);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar datos: " . $e->getMessage();
    $estudiantes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reactivaciones - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reactivar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header (del archivo antiguo) -->
            <div class="header">
                <h1>Gestión de Reactivaciones</h1>
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

            <!-- Tabs (del archivo antiguo) -->
            <div class="tabs-container">
                <a href="?vista=reactivar" class="tab <?= $vista === 'reactivar' ? 'active' : '' ?>">
                    <i class="fas fa-redo"></i> Pendientes de Reactivar
                </a>
                <a href="?vista=historial" class="tab <?= $vista === 'historial' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i> Historial de Reactivaciones
                </a>
            </div>

            <div class="container">
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?= htmlspecialchars($_SESSION['error']) ?></div>
                </div>
                <?php unset($_SESSION['error']); endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= htmlspecialchars($_SESSION['success']) ?></div>
                </div>
                <?php unset($_SESSION['success']); endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <?php if ($vista === 'reactivar'): ?>
                            <h2>Estudiantes Pendientes de Reactivación</h2>
                            <p>Selecciona el estudiante que deseas reactivar y volver a estado activo</p>
                        <?php else: ?>
                            <h2>Historial de Reactivaciones y Casos Cerrados</h2>
                            <p>Estudiantes que fueron reactivados, graduados o con citas completadas/canceladas</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($estudiantes) && $vista === 'reactivar'): ?>
                    <div class="stats">
                        <div class="stat-box">
                            <h4>Total Disponibles</h4>
                            <div class="number"><?= count($estudiantes) ?></div>
                        </div>
                        <div class="stat-box" style="border-left-color: #dc3545;">
                            <h4>Rechazados</h4>
                            <div class="number"><?= count(array_filter($estudiantes, fn($e) => $e['Estado_Real'] === 'Rechazado')) ?></div>
                        </div>
                        <div class="stat-box" style="border-left-color: #ffc107;">
                            <h4>Suspendidos</h4>
                            <div class="number"><?= count(array_filter($estudiantes, fn($e) => $e['Estado_Estudiante'] === 'Suspendido')) ?></div>
                        </div>
                        <div class="stat-box" style="border-left-color: #6c757d;">
                            <h4>Retirados</h4>
                            <div class="number"><?= count(array_filter($estudiantes, fn($e) => $e['Estado_Estudiante'] === 'Retirado')) ?></div>
                        </div>
                    </div>
                    <?php elseif (!empty($estudiantes) && $vista === 'historial'): ?>
                    <div class="stats">
                        <div class="stat-box">
                            <h4>Total en Historial</h4>
                            <div class="number"><?= count($estudiantes) ?></div>
                        </div>
                        <div class="stat-box" style="border-left-color: #28a745;">
                            <h4>Graduados</h4>
                            <div class="number"><?= count(array_filter($estudiantes, fn($e) => $e['Estado_Estudiante'] === 'Graduado')) ?></div>
                        </div>
                        <div class="stat-box" style="border-left-color: #17a2b8;">
                            <h4>Activos (Reactivados)</h4>
                            <div class="number"><?= count(array_filter($estudiantes, fn($e) => $e['Estado_Estudiante'] === 'Activo')) ?></div>
                        </div>
                        <div class="stat-box" style="border-left-color: #667eea;">
                            <h4>Citas Completadas</h4>
                            <div class="number"><?= count(array_filter($estudiantes, fn($e) => isset($e['Estado_Cita']) && $e['Estado_Cita'] === 'Completada')) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($estudiantes)): ?>
                        <div class="no-data">
                            <i class="fas fa-inbox"></i>
                            <?php if ($vista === 'reactivar'): ?>
                                <h3>No hay estudiantes pendientes de reactivación</h3>
                                <p>En este momento no hay solicitudes rechazadas, suspendidas o retiradas.</p>
                            <?php else: ?>
                                <h3>No hay registros en el historial</h3>
                                <p>Aún no se han realizado reactivaciones o no hay casos finalizados.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php if ($vista === 'reactivar'): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Expediente</th>
                                        <th>Nombre</th>
                                        <th>Email / Teléfono</th>
                                        <th>Estado</th>
                                        <th>Fecha Registro</th>
                                        <th>Motivo Rechazo</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $est): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($est['Numero_Expediente'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($est['Nombres_Apellidos']) ?></td>
                                        <td>
                                            <div style="font-size: 12px;"><?= htmlspecialchars($est['Email']) ?></div>
                                            <small style="color: #7f8c8d; font-size: 11px;"><?= htmlspecialchars($est['Telefono']) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= strtolower($est['Estado_Real']) ?>">
                                                <?= htmlspecialchars($est['Estado_Real']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($est['Fecha_Registro'])) ?></td>
                                        <td>
                                            <?php if ($est['Motivo_Rechazo']): ?>
                                                <div class="motivo-rechazo" title="<?= htmlspecialchars($est['Motivo_Rechazo']) ?>">
                                                    <?= htmlspecialchars($est['Motivo_Rechazo']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #bdc3c7; font-size: 12px;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="admin_reactivar.php?id=<?= $est['Id_Estudiante'] ?>" class="btn-reactivar" onclick="return confirmReactivar(event)">
                                                <i class="fas fa-redo"></i> Reactivar
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Expediente</th>
                                        <th>Nombre</th>
                                        <th>Email / Teléfono</th>
                                        <th>Estado Actual</th>
                                        <th>Estado Evaluación</th>
                                        <th>Fecha Reactivación</th>
                                        <th>Estado Cita</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estudiantes as $est): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($est['Numero_Expediente'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($est['Nombres_Apellidos']) ?></td>
                                        <td>
                                            <div style="font-size: 12px;"><?= htmlspecialchars($est['Email']) ?></div>
                                            <small style="color: #7f8c8d; font-size: 11px;"><?= htmlspecialchars($est['Telefono']) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= strtolower($est['Estado_Estudiante']) ?>">
                                                <?= htmlspecialchars($est['Estado_Estudiante']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($est['Estado_Evaluacion']): ?>
                                                <span class="status-badge <?= strtolower($est['Estado_Evaluacion']) ?>">
                                                    <?= htmlspecialchars($est['Estado_Evaluacion']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #bdc3c7; font-size: 12px;">Sin evaluación</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($est['Fecha_Reactivacion']) && $est['Fecha_Reactivacion']): ?>
                                                <div style="font-size: 12px;"><?= date('d/m/Y', strtotime($est['Fecha_Reactivacion'])) ?></div>
                                                <?php if ($est['Hora_Reactivacion']): ?>
                                                    <small style="color: #7f8c8d; font-size: 11px;"><?= date('H:i', strtotime($est['Hora_Reactivacion'])) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #bdc3c7; font-size: 12px;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($est['Estado_Cita']) && $est['Estado_Cita']): ?>
                                                <span class="status-badge <?= strtolower($est['Estado_Cita']) ?>">
                                                    <?= htmlspecialchars($est['Estado_Cita']) ?>
                                                </span>
                                                <?php if ($est['Fecha_Cita']): ?>
                                                    <div style="font-size: 11px; color: #7f8c8d; margin-top: 2px;"><?= date('d/m/Y', strtotime($est['Fecha_Cita'])) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #bdc3c7; font-size: 12px;">Sin cita</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="admin_detalle.php?id=<?= $est['Id_Estudiante'] ?>" class="btn-reactivar btn-view">
                                                <i class="fas fa-eye"></i> Ver Detalle
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function confirmReactivar(event) {
            event.preventDefault();
            const url = event.target.href;
            
            Swal.fire({
                title: '¿Reactivar estudiante?',
                text: "Esta acción cambiará el estado del estudiante a 'Activo'.",
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
            
            return false;
        }
    </script>
</body>
</html>