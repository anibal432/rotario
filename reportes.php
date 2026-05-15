<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Usar la conexión del nuevo archivo que tiene correcciones
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Usuario';
$user_id = $_SESSION['user_id'];

include 'conexion.php';

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// ESTADÍSTICAS PRINCIPALES
$stmt = $pdo->query("SELECT COUNT(*) as total FROM Estudiantes WHERE Estado_Estudiante = 'Activo'");
$total_becados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Estado_Estudiante = 'Graduado' THEN 1 ELSE 0 END) as graduados
    FROM Estudiantes
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$tasa_graduacion = $stats['total'] > 0 ? round(($stats['graduados'] / $stats['total']) * 100) : 0;

$inversion_por_estudiante = 1500;
$inversion_total = $total_becados * $inversion_por_estudiante;

$stmt = $pdo->query("
    SELECT 
        Grado_Obtenido_Anterior as grado, 
        COUNT(*) as cantidad 
    FROM Estudiantes 
    WHERE Estado_Estudiante = 'Activo'
    GROUP BY Grado_Obtenido_Anterior
    ORDER BY cantidad DESC
    LIMIT 10
");
$distribucion_grados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        c.Nombre_Categoria as categoria,
        COUNT(i.Id_Inscripcion) as participantes,
        SUM(i.Monto_Pagado) as total_ingreso,
        AVG(i.Monto_Pagado) as precio_promedio
    FROM Inscripciones_Evento i
    INNER JOIN Categorias_Evento c ON i.Id_Categoria = c.Id_Categoria
    INNER JOIN Eventos e ON i.Id_Evento = e.Id_Evento
    WHERE YEAR(i.Fecha_Inscripcion) = :year
    AND i.Estado_Pago = 'Aprobado'
    GROUP BY c.Id_Categoria, c.Nombre_Categoria
    ORDER BY total_ingreso DESC
");
$stmt->execute([':year' => $year]);
$ingresos_eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_ingresos_eventos = 0;
$total_participantes_eventos = 0;
foreach ($ingresos_eventos as $evento) {
    $total_ingresos_eventos += $evento['total_ingreso'] ?? 0;
    $total_participantes_eventos += $evento['participantes'];
}

$stmt = $pdo->query("
    SELECT DISTINCT YEAR(Fecha_Inscripcion) as anio 
    FROM Inscripciones_Evento 
    ORDER BY anio DESC
");
$years_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reportes.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Reportes y Estadísticas</h1>
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
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_becados ?></h3>
                        <p>Becados Activos</p>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $tasa_graduacion ?>%</h3>
                        <p>Tasa de Graduación</p>
                    </div>
                </div>

                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Q<?= number_format($inversion_total, 0) ?></h3>
                        <p>Inversión Total <?= $year ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($distribucion_grados)): ?>
            <div class="chart-container">
                <h2 class="chart-header">
                    <i class="fas fa-chart-bar"></i>
                    Distribución por Grado Académico
                </h2>
                <div class="bar-chart">
                    <?php 
                    $colors = ['blue', 'yellow', 'green', 'orange', 'red'];
                    $max_cantidad = max(array_column($distribucion_grados, 'cantidad'));
                    $color_index = 0;
                    
                    foreach ($distribucion_grados as $grado): 
                        $porcentaje = ($grado['cantidad'] / $max_cantidad) * 100;
                    ?>
                        <div class="bar-item">
                            <div class="bar-label"><?= htmlspecialchars($grado['grado']) ?></div>
                            <div class="bar-wrapper">
                                <div class="bar <?= $colors[$color_index % 5] ?>" style="width: <?= $porcentaje ?>%">
                                    <?= $grado['cantidad'] ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        $color_index++;
                    endforeach; 
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="ingresos-section">
                <div class="ingresos-header">
                    <h2>
                        <i class="fas fa-running"></i>
                        Ingresos por Eventos
                    </h2>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <select class="year-selector" onchange="cambiarYear(this.value)">
                            <?php foreach ($years_disponibles as $y): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="total-badge">
                            <i class="fas fa-money-bill-wave"></i>
                            Total: Q<?= number_format($total_ingresos_eventos, 2) ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($ingresos_eventos)): ?>
                    <div class="ingresos-grid">
                        <?php foreach ($ingresos_eventos as $evento): ?>
                            <div class="ingreso-card">
                                <div class="ingreso-categoria">
                                    <i class="fas fa-medal"></i>
                                    <?= htmlspecialchars($evento['categoria']) ?>
                                </div>
                                <div class="ingreso-stats">
                                    <div class="ingreso-stat">
                                        <span class="ingreso-stat-label">Participantes:</span>
                                        <span class="ingreso-stat-value"><?= $evento['participantes'] ?></span>
                                    </div>
                                    <div class="ingreso-stat">
                                        <span class="ingreso-stat-label">Precio promedio:</span>
                                        <span class="ingreso-stat-value">Q<?= number_format($evento['precio_promedio'], 2) ?></span>
                                    </div>
                                </div>
                                <div class="ingreso-total">
                                    Q<?= number_format($evento['total_ingreso'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 25px; padding: 20px; background: #d4edda; border-radius: 8px; text-align: center;">
                        <div style="font-size: 14px; color: #155724; margin-bottom: 5px;">
                            <i class="fas fa-users"></i>
                            Total de Participantes: <strong><?= $total_participantes_eventos ?></strong>
                        </div>
                        <div style="font-size: 18px; color: #155724; font-weight: 700;">
                            <i class="fas fa-chart-line"></i>
                            Ingreso Total Eventos: Q<?= number_format($total_ingresos_eventos, 2) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No hay datos de eventos para el año <?= $year ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="report-section">
                <h2><i class="fas fa-file-download"></i> Generar Reportes</h2>
                <p>Exportar datos e informes del programa de becas</p>

                <form method="GET" action="generar_reporte.php" target="_blank" id="reportForm">
                    <div class="report-controls">
                        <div class="control-group">
                            <label>Tipo de Reporte</label>
                            <select name="tipo" required>
                                <option value="general">Reporte General de Becados</option>
                                <option value="evaluaciones">Evaluaciones Socioeconómicas</option>
                                <option value="grados">Distribución por Grados</option>
                                <option value="eventos">Ingresos Eventos</option>
                            </select>
                        </div>

                        <div class="control-group">
                            <label>Período</label>
                            <select name="periodo">
                                <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                                <option value="<?= date('Y') - 1 ?>"><?= date('Y') - 1 ?></option>
                                <option value="<?= date('Y') - 2 ?>"><?= date('Y') - 2 ?></option>
                                <option value="todos">Todos los años</option>
                            </select>
                        </div>

                        <button type="submit" name="formato" value="pdf" class="btn btn-pdf">
                            <i class="fas fa-file-pdf"></i>
                            Generar PDF
                        </button>
                        <button type="submit" name="formato" value="excel" class="btn btn-excel">
                            <i class="fas fa-file-excel"></i>
                            Exportar Excel
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function cambiarYear(year) {
            window.location.href = 'reportes.php?year=' + year;
        }

        // SweetAlert2 para mostrar alertas bonitas
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

        // Confirmación para generar reportes
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const formato = e.submitter.value;
            const tipo = document.querySelector('select[name="tipo"]').value;
            
            Swal.fire({
                title: 'Generando Reporte',
                html: `Preparando reporte <strong>${tipo}</strong> en formato <strong>${formato.toUpperCase()}</strong>...`,
                timer: 2000,
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        // Mostrar notificación cuando se cambia el año
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('year')) {
                Swal.fire({
                    title: 'Filtro aplicado',
                    text: `Mostrando datos del año ${urlParams.get('year')}`,
                    icon: 'info',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        });
    </script>
</body>
</html>