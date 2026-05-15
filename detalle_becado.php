<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar ID del estudiante
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: estudiantes_becados.php');
    exit;
}

$id_estudiante = $_GET['id'];

// Obtener información completa del estudiante
$sql = "SELECT 
            e.*,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Fecha_Inicio as Fecha_Inicio_Beca_Actual,
            b.Fecha_Fin,
            b.Promedio_Minimo,
            ev.Meta_Profesional,
            ev.Estado_Evaluacion
        FROM Estudiantes e
        LEFT JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
        WHERE e.Id_Estudiante = ?
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: estudiantes_becados.php');
    exit;
}

// Obtener historial de pagos
$sql_pagos = "SELECT * FROM Pagos_Becas 
              WHERE Id_Beca = ? 
              ORDER BY Fecha_Pago DESC";
$stmt_pagos = $pdo->prepare($sql_pagos);
$stmt_pagos->execute([$estudiante['Id_Beca']]);
$pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

// Calcular total pagado
$total_pagado = array_sum(array_column($pagos, 'Monto'));

// Obtener boletas de calificaciones
$sql_boletas = "SELECT * FROM Boletas_Calificaciones 
                WHERE Id_Estudiante = ? 
                ORDER BY Fecha_Subida DESC";
$stmt_boletas = $pdo->prepare($sql_boletas);
$stmt_boletas->execute([$id_estudiante]);
$boletas = $stmt_boletas->fetchAll(PDO::FETCH_ASSOC);

// Calcular promedio actual (último promedio de las boletas)
$promedio_actual = null;
if (count($boletas) > 0) {
    $promedio_actual = $boletas[0]['Promedio'];
}

// Obtener testimonios del estudiante
$sql_testimonios = "SELECT * FROM Testimonios 
                    WHERE Id_Estudiante = ? 
                    ORDER BY Fecha_Registro DESC";
$stmt_testimonios = $pdo->prepare($sql_testimonios);
$stmt_testimonios->execute([$id_estudiante]);
$testimonios = $stmt_testimonios->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?> - Detalle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .breadcrumb {
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #666;
        }

        .breadcrumb a {
            color: #004b87;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Header del estudiante con foto */
        .estudiante-header {
            background: linear-gradient(135deg, #004b87 0%, #0068b8 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .foto-estudiante-container {
            flex-shrink: 0;
        }

        .foto-estudiante {
            width: 180px;
            height: 220px;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            border: 5px solid rgba(255,255,255,0.2);
        }

        .foto-estudiante img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .foto-estudiante .no-foto {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
            color: #6c757d;
        }

        .foto-estudiante .no-foto i {
            font-size: 4em;
            margin-bottom: 10px;
        }

        .foto-estudiante .no-foto span {
            font-size: 0.85em;
            text-align: center;
            padding: 0 10px;
        }

        .estudiante-info-header {
            flex: 1;
        }

        .estudiante-info-header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
        }

        .estudiante-info-header .detalles {
            display: flex;
            gap: 30px;
            margin-top: 15px;
            font-size: 1.05em;
            opacity: 0.95;
            flex-wrap: wrap;
        }

        .estado-beca-header {
            text-align: right;
            flex-shrink: 0;
        }

        .estado-badge-grande {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 30px;
            font-size: 1.2em;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 15px;
        }

        .estado-activa {
            background: #28a745;
            color: white;
        }

        .estado-suspendida {
            background: #ffc107;
            color: #000;
        }

        .estado-finalizada {
            background: #6c757d;
            color: white;
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 15px 15px 0 0;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }

        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            color: #666;
            background: white;
        }

        .tab:hover {
            background: #f8f9fa;
        }

        .tab.active {
            color: #004b87;
            border-bottom-color: #004b87;
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 40px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Grid de información */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid #004b87;
        }

        .info-card h3 {
            color: #004b87;
            margin-bottom: 20px;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
            display: block;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
            font-size: 1em;
        }

        /* Tarjeta de foto en info general */
        .foto-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid #004b87;
            text-align: center;
        }

        .foto-card h3 {
            color: #004b87;
            margin-bottom: 20px;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .foto-display {
            width: 100%;
            max-width: 250px;
            height: 300px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .foto-display img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .foto-display .no-foto-display {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
            color: #6c757d;
        }

        .foto-display .no-foto-display i {
            font-size: 5em;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .foto-actions {
            margin-top: 15px;
        }

        /* Estadísticas de pagos */
        .stats-pagos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-box.green {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .stat-box.blue {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }

        .stat-box h4 {
            font-size: 0.9em;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-box .numero {
            font-size: 2.5em;
            font-weight: 700;
        }

        /* Tabla de historial */
        .historial-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .historial-table thead {
            background: #f8f9fa;
        }

        .historial-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }

        .historial-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .historial-table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Boletas */
        .boletas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .boleta-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid #28a745;
            transition: transform 0.3s ease;
        }

        .boleta-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .boleta-card.promedio-bajo {
            border-left-color: #dc3545;
        }

        .boleta-promedio {
            font-size: 2.5em;
            font-weight: 700;
            margin: 15px 0;
        }

        .boleta-promedio.bueno { color: #28a745; }
        .boleta-promedio.regular { color: #ffc107; }
        .boleta-promedio.malo { color: #dc3545; }

        /* Botones */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #004b87;
            color: white;
        }

        .btn-primary:hover {
            background: #003866;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 75, 135, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        .acciones-principales {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 30px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -35px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #004b87;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #004b87;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
        }

        .timeline-date {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }

        /* Modal para ver foto en grande */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #bbb;
        }

        @media (max-width: 768px) {
            .estudiante-header {
                flex-direction: column;
                text-align: center;
            }

            .foto-estudiante {
                margin: 0 auto;
            }

            .estado-beca-header {
                text-align: center;
                margin-top: 20px;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-content {
                padding: 20px;
            }

            .estudiante-info-header .detalles {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
            <a href="estudiantes_becados.php">Estudiantes Becados</a> /
            <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>
        </div>

        <!-- Header del estudiante con foto -->
        <div class="estudiante-header">
            <div class="foto-estudiante-container">
                <div class="foto-estudiante">
                    <?php if (!empty($estudiante['Foto_Becado']) && file_exists('uploads/fotos_becados/' . $estudiante['Foto_Becado'])): ?>
                        <img src="uploads/fotos_becados/<?= htmlspecialchars($estudiante['Foto_Becado']) ?>" 
                             alt="<?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>"
                             onclick="openModal(this.src)"
                             style="cursor: pointer;"
                             title="Click para ampliar">
                    <?php else: ?>
                        <div class="no-foto">
                            <i class="fas fa-user-circle"></i>
                            <span>Sin fotografía</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="estudiante-info-header">
                <h1><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></h1>
                <div class="detalles">
                    <div><i class="fas fa-id-card"></i> Exp: <?= htmlspecialchars($estudiante['Numero_Expediente']) ?></div>
                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($estudiante['Email']) ?></div>
                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($estudiante['Telefono']) ?></div>
                </div>
            </div>

            <div class="estado-beca-header">
                <div class="estado-badge-grande estado-<?= strtolower($estudiante['Estado_Beca']) ?>">
                    <?= $estudiante['Estado_Beca'] ?>
                </div>
                <div style="font-size: 1.1em;">
                    <strong><?= htmlspecialchars($estudiante['Tipo_Beca']) ?></strong>
                </div>
                <div style="font-size: 1.3em; margin-top: 5px;">
                    <strong>Q<?= number_format($estudiante['Monto_Mensual'], 2) ?></strong> / mes
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="openTab(event, 'info-general')">
                <i class="fas fa-user"></i> Información General
            </div>
            <div class="tab" onclick="openTab(event, 'pagos')">
                <i class="fas fa-dollar-sign"></i> Pagos
            </div>
            <div class="tab" onclick="openTab(event, 'boletas')">
                <i class="fas fa-file-alt"></i> Boletas
            </div>
            <div class="tab" onclick="openTab(event, 'historial')">
                <i class="fas fa-history"></i> Historial
            </div>
        </div>

        <!-- TAB: Información General -->
        <div id="info-general" class="tab-content active">
            <div class="info-grid">
                <!-- Tarjeta de Foto -->
                <div class="foto-card">
                    <h3><i class="fas fa-camera"></i> Fotografía</h3>
                    <div class="foto-display">
                        <?php if (!empty($estudiante['Foto_Becado']) && file_exists('uploads/fotos_becados/' . $estudiante['Foto_Becado'])): ?>
                            <img src="uploads/fotos_becados/<?= htmlspecialchars($estudiante['Foto_Becado']) ?>" 
                                 alt="<?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>"
                                 onclick="openModal(this.src)"
                                 style="cursor: pointer;"
                                 title="Click para ampliar">
                        <?php else: ?>
                            <div class="no-foto-display">
                                <i class="fas fa-user-circle"></i>
                                <span>Sin fotografía</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="foto-actions">
                        <a href="editar_becado.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-primary btn-small">
                            <i class="fas fa-edit"></i> Actualizar Foto
                        </a>
                    </div>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Datos Personales</h3>
                    <div class="info-item">
                        <span class="info-label">Nombre Completo</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Edad</span>
                        <span class="info-value"><?= $estudiante['Edad'] ?> años</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Telefono']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Dirección</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Direccion_Domiciliar']) ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-users"></i> Información Familiar</h3>
                    <div class="info-item">
                        <span class="info-label">Encargado</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Nombre_Encargado']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono del Encargado</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Telefono_Encargado']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Madre</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Nombre_Madre'] ?: 'No especificado') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Padre</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Nombre_Padre'] ?: 'No especificado') ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                    <div class="info-item">
                        <span class="info-label">Último Grado Obtenido</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Grado_Obtenido_Anterior']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Escuela Anterior</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Escuela_Anterior']) ?></span>
                    </div>
                    <?php if (!empty($estudiante['Meta_Profesional'])): ?>
                    <div class="info-item">
                        <span class="info-label">Meta Profesional</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Meta_Profesional']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Promedio Mínimo Requerido</span>
                        <span class="info-value"><?= $estudiante['Promedio_Minimo'] ?> puntos</span>
                    </div>
                    <?php if ($promedio_actual): ?>
                    <div class="info-item">
                        <span class="info-label">Promedio Actual</span>
                        <span class="info-value">
                            <strong style="color: <?= $promedio_actual >= $estudiante['Promedio_Minimo'] ? '#28a745' : '#dc3545' ?>">
                                <?= number_format($promedio_actual, 1) ?> puntos
                            </strong>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-award"></i> Información de Beca</h3>
                    <div class="info-item">
                        <span class="info-label">Tipo de Beca</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Tipo_Beca']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Monto Mensual</span>
                        <span class="info-value"><strong>Q<?= number_format($estudiante['Monto_Mensual'], 2) ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Inicio</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($estudiante['Fecha_Inicio_Beca_Actual'])) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado</span>
                        <span class="badge badge-<?= $estudiante['Estado_Beca'] === 'Activa' ? 'success' : ($estudiante['Estado_Beca'] === 'Suspendida' ? 'warning' : 'info') ?>">
                            <?= $estudiante['Estado_Beca'] ?>
                        </span>
                    </div>
                    <?php if ($estudiante['Fecha_Fin']): ?>
                    <div class="info-item">
                        <span class="info-label">Fecha de Finalización</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($estudiante['Fecha_Fin'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="acciones-principales">
                <a href="registrar_pago.php?id_beca=<?= $estudiante['Id_Beca'] ?>" class="btn btn-success">
                    <i class="fas fa-dollar-sign"></i> Registrar Pago
                </a>
                <a href="subir_boleta.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-warning">
                    <i class="fas fa-upload"></i> Subir Boleta
                </a>
                <a href="editar_becado.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Editar Información
                </a>
                <?php if ($estudiante['Estado_Beca'] === 'Activa'): ?>
                <a href="suspender_beca.php?id=<?= $estudiante['Id_Beca'] ?>" class="btn btn-danger" 
                   onclick="return confirm('¿Estás seguro de suspender esta beca?')">
                    <i class="fas fa-pause-circle"></i> Suspender Beca
                </a>
                <?php elseif ($estudiante['Estado_Beca'] === 'Suspendida'): ?>
                <a href="reactivar_beca.php?id=<?= $estudiante['Id_Beca'] ?>" class="btn btn-success">
                    <i class="fas fa-play-circle"></i> Reactivar Beca
                </a>
                <?php endif; ?>
                <a href="estudiantes_becados.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <!-- TAB: Pagos -->
        <div id="pagos" class="tab-content">
            <div class="stats-pagos">
                <div class="stat-box">
                    <h4>Pagos Realizados</h4>
                    <div class="numero"><?= count($pagos) ?></div>
                </div>
                <div class="stat-box green">
                    <h4>Total Pagado</h4>
                    <div class="numero">Q<?= number_format($total_pagado, 0) ?></div>
                </div>
                <div class="stat-box blue">
                    <h4>Monto Mensual</h4>
                    <div class="numero">Q<?= number_format($estudiante['Monto_Mensual'], 0) ?></div>
                </div>
            </div>

            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-history"></i> Historial de Pagos
            </h3>

            <?php if (count($pagos) > 0): ?>
            <table class="historial-table">
                <thead>
                    <tr>
                        <th>Fecha de Pago</th>
                        <th>Monto</th>
                        <th>Mes/Período</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($pago['Fecha_Pago'])) ?></td>
                        <td><strong>Q<?= number_format($pago['Monto'], 2) ?></strong></td>
                        <td><?= htmlspecialchars($pago['Periodo'] ?? 'No especificado') ?></td>
                        <td><?= htmlspecialchars($pago['Metodo_Pago'] ?? 'Efectivo') ?></td>
                        <td><?= htmlspecialchars($pago['Referencia'] ?? '-') ?></td>
                        <td>
                            <span class="badge badge-success">Completado</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>No hay pagos registrados</h3>
                <p>Aún no se han registrado pagos para este estudiante</p>
                <a href="registrar_pago.php?id_beca=<?= $estudiante['Id_Beca'] ?>" class="btn btn-success" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Registrar Primer Pago
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Boletas -->
        <div id="boletas" class="tab-content">
            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-file-alt"></i> Boletas de Calificaciones
            </h3>

            <?php if (count($boletas) > 0): ?>
            <div class="boletas-grid">
                <?php foreach ($boletas as $boleta): 
                    $clase_promedio = 'bueno';
                    if ($boleta['Promedio'] < $estudiante['Promedio_Minimo']) {
                        $clase_promedio = 'malo';
                    } elseif ($boleta['Promedio'] < 80) {
                        $clase_promedio = 'regular';
                    }
                ?>
                <div class="boleta-card <?= $clase_promedio === 'malo' ? 'promedio-bajo' : '' ?>">
                    <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                        <?= date('F Y', strtotime($boleta['Fecha_Subida'])) ?>
                    </div>
                    <div class="boleta-promedio <?= $clase_promedio ?>">
                        <?= number_format($boleta['Promedio'], 1) ?>
                    </div>
                    <div style="font-size: 0.9em; color: #666;">
                        Subida: <?= date('d/m/Y', strtotime($boleta['Fecha_Subida'])) ?>
                    </div>
                    <?php if ($boleta['Archivo_Boleta']): ?>
                    <a href="<?= $boleta['Archivo_Boleta'] ?>" target="_blank" class="btn btn-primary" style="margin-top: 15px; width: 100%; justify-content: center;">
                        <i class="fas fa-download"></i> Ver Boleta
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No hay boletas registradas</h3>
                <p>Aún no se han subido boletas de calificaciones</p>
                <a href="subir_boleta.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-warning" style="margin-top: 20px;">
                    <i class="fas fa-upload"></i> Subir Primera Boleta
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Historial -->
        <div id="historial" class="tab-content">
            <h3 style="margin-bottom: 20px;">
                <i class="fas fa-history"></i> Línea de Tiempo
            </h3>

            <div class="timeline">
                <!-- Registro inicial -->
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date"><?= date('d/m/Y', strtotime($estudiante['Fecha_Registro'])) ?></div>
                        <div class="timeline-title">Registro de Solicitud</div>
                        <div>Se registró la solicitud de beca del estudiante</div>
                    </div>
                </div>

                <!-- Inicio de beca -->
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date"><?= date('d/m/Y', strtotime($estudiante['Fecha_Inicio_Beca_Actual'])) ?></div>
                        <div class="timeline-title">Beca Otorgada</div>
                        <div>Se aprobó y activó la beca para el estudiante</div>
                    </div>
                </div>

                <!-- Pagos recientes -->
                <?php foreach (array_slice($pagos, 0, 5) as $pago): ?>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date"><?= date('d/m/Y', strtotime($pago['Fecha_Pago'])) ?></div>
                        <div class="timeline-title">Pago Registrado</div>
                        <div>Pago de Q<?= number_format($pago['Monto'], 2) ?> - <?= htmlspecialchars($pago['Periodo'] ?? 'Sin período') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Boletas recientes -->
                <?php foreach (array_slice($boletas, 0, 3) as $boleta): ?>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date"><?= date('d/m/Y', strtotime($boleta['Fecha_Subida'])) ?></div>
                        <div class="timeline-title">Boleta Subida</div>
                        <div>Promedio: <?= number_format($boleta['Promedio'], 1) ?> puntos</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal para ver foto en grande -->
    <div id="fotoModal" class="modal" onclick="closeModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="imgModal">
    </div>

    <script>
        function openTab(evt, tabName) {
            const tabContents = document.getElementsByClassName('tab-content');
            for (let content of tabContents) {
                content.classList.remove('active');
            }

            const tabs = document.getElementsByClassName('tab');
            for (let tab of tabs) {
                tab.classList.remove('active');
            }

            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        // Funciones para el modal de foto
        function openModal(src) {
            const modal = document.getElementById('fotoModal');
            const modalImg = document.getElementById('imgModal');
            modal.style.display = 'block';
            modalImg.src = src;
        }

        function closeModal() {
            document.getElementById('fotoModal').style.display = 'none';
        }

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>