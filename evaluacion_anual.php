<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

$filtro_año = $_GET['año'] ?? date('Y');
$filtro_estado = $_GET['estado'] ?? 'Pendiente';

$sql = "SELECT 
            e.Id_Estudiante,
            e.Numero_Expediente,
            e.Nombres_Apellidos,
            e.Email,
            e.Telefono,
            e.Fecha_Inicio_Beca,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Promedio_Minimo,
            b.Promedio_Actual,
            b.Fecha_Inicio as Fecha_Inicio_Beca_Actual,
            (SELECT AVG(Promedio) 
             FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante 
             AND YEAR(Fecha_Subida) = ?) as Promedio_Anual,
            (SELECT COUNT(*) 
             FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca 
             AND YEAR(Fecha_Pago) = ?) as Pagos_Año,
            (SELECT COUNT(*) 
             FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante 
             AND YEAR(Fecha_Subida) = ?) as Boletas_Año,
            (SELECT Estado_Evaluacion 
             FROM Evaluaciones_Anuales 
             WHERE Id_Beca = b.Id_Beca 
             AND Año_Evaluacion = ?
             ORDER BY Fecha_Evaluacion DESC 
             LIMIT 1) as Estado_Evaluacion_Anual,
            (SELECT Fecha_Evaluacion 
             FROM Evaluaciones_Anuales 
             WHERE Id_Beca = b.Id_Beca 
             AND Año_Evaluacion = ?
             ORDER BY Fecha_Evaluacion DESC 
             LIMIT 1) as Fecha_Ultima_Evaluacion,
            TIMESTAMPDIFF(YEAR, b.Fecha_Inicio, NOW()) as Años_Beca
        FROM Estudiantes e
        INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE b.Estado_Beca IN ('Activa', 'Suspendida')
        AND YEAR(b.Fecha_Inicio) < ?";

$params = [$filtro_año, $filtro_año, $filtro_año, $filtro_año, $filtro_año, $filtro_año];

if ($filtro_estado !== 'Todos') {
    if ($filtro_estado === 'Pendiente') {
        $sql .= " HAVING (Estado_Evaluacion_Anual IS NULL OR Estado_Evaluacion_Anual = 'Pendiente')";
    } else {
        $sql .= " HAVING Estado_Evaluacion_Anual = ?";
        $params[] = $filtro_estado;
    }
}

$sql .= " ORDER BY 
            CASE 
                WHEN Estado_Evaluacion_Anual IS NULL THEN 0
                WHEN Estado_Evaluacion_Anual = 'Pendiente' THEN 1
                ELSE 2
            END,
            e.Nombres_Apellidos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_estudiantes = count($estudiantes);
$pendientes = 0;
$renovados = 0;
$finalizados = 0;

foreach ($estudiantes as $est) {
    if (empty($est['Estado_Evaluacion_Anual']) || $est['Estado_Evaluacion_Anual'] === 'Pendiente') {
        $pendientes++;
    } elseif ($est['Estado_Evaluacion_Anual'] === 'Renovado') {
        $renovados++;
    } elseif ($est['Estado_Evaluacion_Anual'] === 'Finalizado') {
        $finalizados++;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación Anual de Becas - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/evaluacion.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Evaluación Anual de Becas</h1>
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

            <div class="alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Proceso de Evaluación Anual:</strong>
                    <p style="margin-top: 8px;">
                        Al final de cada año académico, se debe evaluar a cada estudiante becado para determinar 
                        si cumple con los requisitos de continuidad. Se revisa el promedio anual, asistencia, 
                        conducta y situación socioeconómica actual.
                    </p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_estudiantes ?></h3>
                        <p>Total para Evaluar</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $pendientes ?></h3>
                        <p>Evaluaciones Pendientes</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $renovados ?></h3>
                        <p>Becas Renovadas</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $finalizados ?></h3>
                        <p>Becas Finalizadas</p>
                    </div>
                </div>
            </div>

            <div class="filtros">
                <form method="GET" action="">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Año Académico</label>
                            <select name="año">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $filtro_año == $y ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Estado de Evaluación</label>
                            <select name="estado">
                                <option value="Todos" <?= $filtro_estado === 'Todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                <option value="Renovado" <?= $filtro_estado === 'Renovado' ? 'selected' : '' ?>>Renovados</option>
                                <option value="Finalizado" <?= $filtro_estado === 'Finalizado' ? 'selected' : '' ?>>Finalizados</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-table"></i>
                        Estudiantes (<?= count($estudiantes) ?>)
                    </h2>
                </div>

                <?php if (count($estudiantes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Promedio Anual</th>
                            <th>Indicadores</th>
                            <th>Años con Beca</th>
                            <th>Estado Evaluación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $est): ?>
                        <tr>
                            <td>
                                <div class="estudiante-info">
                                    <span class="estudiante-nombre">
                                        <?= htmlspecialchars($est['Nombres_Apellidos']) ?>
                                    </span>
                                    <span class="estudiante-detalles">
                                        Exp: <?= htmlspecialchars($est['Numero_Expediente']) ?> | 
                                        <?= htmlspecialchars($est['Tipo_Beca']) ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($est['Promedio_Anual']): 
                                    $promedio = $est['Promedio_Anual'];
                                    $clase_promedio = 'bajo';
                                    if ($promedio >= 90) $clase_promedio = 'excelente';
                                    elseif ($promedio >= 85) $clase_promedio = 'bueno';
                                    elseif ($promedio >= $est['Promedio_Minimo']) $clase_promedio = 'regular';
                                ?>
                                    <span class="promedio <?= $clase_promedio ?>">
                                        <?= number_format($promedio, 1) ?>
                                    </span>
                                    <div style="font-size: 0.85em; color: #666; margin-top: 3px;">
                                        Mín: <?= $est['Promedio_Minimo'] ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #bdc3c7;">Sin datos</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="indicadores">
                                    <div class="indicador" title="Pagos recibidos">
                                        <i class="fas fa-dollar-sign" style="color: #27ae60;"></i>
                                        <?= $est['Pagos_Año'] ?>/12
                                    </div>
                                    <div class="indicador" title="Boletas subidas">
                                        <i class="fas fa-file-alt" style="color: #3498db;"></i>
                                        <?= $est['Boletas_Año'] ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?= $est['Años_Beca'] ?></strong> año(s)
                                <div style="font-size: 0.85em; color: #666;">
                                    Desde <?= date('Y', strtotime($est['Fecha_Inicio_Beca_Actual'])) ?>
                                </div>
                            </td>
                            <td>
                                <?php if (empty($est['Estado_Evaluacion_Anual']) || $est['Estado_Evaluacion_Anual'] === 'Pendiente'): ?>
                                    <span class="badge badge-pendiente">
                                        <i class="fas fa-clock"></i> Pendiente
                                    </span>
                                <?php elseif ($est['Estado_Evaluacion_Anual'] === 'Renovado'): ?>
                                    <span class="badge badge-renovado">
                                        <i class="fas fa-check-circle"></i> Renovado
                                    </span>
                                    <?php if ($est['Fecha_Ultima_Evaluacion']): ?>
                                    <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                                        <?= date('d/m/Y', strtotime($est['Fecha_Ultima_Evaluacion'])) ?>
                                    </div>
                                    <?php endif; ?>
                                <?php elseif ($est['Estado_Evaluacion_Anual'] === 'Finalizado'): ?>
                                    <span class="badge badge-finalizado">
                                        <i class="fas fa-times-circle"></i> Finalizado
                                    </span>
                                    <?php if ($est['Fecha_Ultima_Evaluacion']): ?>
                                    <div style="font-size: 0.85em; color: #666; margin-top: 5px;">
                                        <?= date('d/m/Y', strtotime($est['Fecha_Ultima_Evaluacion'])) ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="evaluar_estudiante.php?id=<?= $est['Id_Estudiante'] ?>&año=<?= $filtro_año ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-clipboard-check"></i>
                                        <?= (empty($est['Estado_Evaluacion_Anual']) || $est['Estado_Evaluacion_Anual'] === 'Pendiente') ? 'Evaluar' : 'Ver' ?>
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
                    <h3>No hay estudiantes para evaluar</h3>
                    <p>No se encontraron estudiantes con los filtros seleccionados</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '<?= htmlspecialchars($_GET['success']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($_GET['error']) ?>',
                confirmButtonText: 'Aceptar'
            });
        <?php endif; ?>

        function confirmAction(message, url) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004b87',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }
    </script>
</body>
</html>