<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar que se recibió el ID de la evaluación
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: aplicaciones.php');
    exit;
}

$id_evaluacion = $_GET['id'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';

// Obtener información completa de la solicitud
$sql = "SELECT 
            e.Id_Estudiante,
            e.Numero_Expediente,
            e.Nombres_Apellidos,
            e.Edad,
            e.Telefono,
            e.Email,
            e.Direccion_Domiciliar,
            e.Nombre_Madre,
            e.Nombre_Padre,
            e.Nombre_Encargado,
            e.Telefono_Encargado,
            e.Grado_Obtenido_Anterior,
            e.Escuela_Anterior,
            e.Fecha_Registro,
            ev.*,
            c.Fecha_Cita,
            c.Hora_Cita,
            c.Estado_Cita,
            cs.Contenido_Carta
        FROM Evaluaciones_Socioeconomicas ev
        INNER JOIN Estudiantes e ON ev.Id_Estudiante = e.Id_Estudiante
        LEFT JOIN Citas_Entrevista c ON ev.Id_Evaluacion = c.Id_Evaluacion
        LEFT JOIN Cartas_Solicitud cs ON e.Id_Estudiante = cs.Id_Estudiante
        WHERE ev.Id_Evaluacion = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_evaluacion]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    header('Location: aplicaciones.php');
    exit;
}

// Obtener respuestas del cuestionario
$sql_respuestas = "SELECT 
                    p.Pregunta,
                    p.Orden,
                    r.Respuesta
                FROM Respuestas_Cuestionario r
                INNER JOIN Preguntas_Cuestionario p ON r.Id_Pregunta = p.Id_Pregunta
                WHERE r.Id_Estudiante = ?
                ORDER BY p.Orden";

$stmt_resp = $pdo->prepare($sql_respuestas);
$stmt_resp->execute([$solicitud['Id_Estudiante']]);
$respuestas = $stmt_resp->fetchAll(PDO::FETCH_ASSOC);

// Organizar respuestas por sección
$secciones_respuestas = [
    'Sobre tu Perfil' => [],
    'Sobre tus Objetivos y Motivación' => [],
    'Sobre tu Experiencia y Comunidad' => [],
    'Preguntas de Cierre' => []
];

foreach ($respuestas as $respuesta) {
    $orden = $respuesta['Orden'];
    if ($orden >= 1 && $orden <= 5) {
        $secciones_respuestas['Sobre tu Perfil'][] = $respuesta;
    } elseif ($orden >= 6 && $orden <= 9) {
        $secciones_respuestas['Sobre tus Objetivos y Motivación'][] = $respuesta;
    } elseif ($orden >= 10 && $orden <= 14) {
        $secciones_respuestas['Sobre tu Experiencia y Comunidad'][] = $respuesta;
    } else {
        $secciones_respuestas['Preguntas de Cierre'][] = $respuesta;
    }
}

// Obtener composición familiar si existe
$sql_familia = "SELECT * FROM Composicion_Familiar WHERE Id_Evaluacion = ? ORDER BY Edad DESC";
$stmt_familia = $pdo->prepare($sql_familia);
$stmt_familia->execute([$id_evaluacion]);
$familia = $stmt_familia->fetchAll(PDO::FETCH_ASSOC);

// Decodificar servicios básicos
$servicios = json_decode($solicitud['Servicios_Basicos'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrevista - <?= htmlspecialchars($solicitud['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #004b87 0%, #0068b8 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .header-right {
            text-align: right;
        }

        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .status-pendiente {
            background: #ffc107;
            color: #000;
        }

        .status-aprobado {
            background: #28a745;
            color: white;
        }

        .status-rechazado {
            background: #dc3545;
            color: white;
        }

        .content {
            background: white;
            padding: 0;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
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
        }

        .tab:hover {
            background: #e9ecef;
        }

        .tab.active {
            background: white;
            color: #004b87;
            border-bottom-color: #004b87;
        }

        .tab-content {
            display: none;
            padding: 40px;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #004b87;
        }

        .info-card h3 {
            color: #004b87;
            margin-bottom: 15px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item {
            margin-bottom: 12px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.85em;
            display: block;
            margin-bottom: 4px;
        }

        .info-value {
            color: #333;
            font-size: 1em;
        }

        .carta-solicitud {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            border-left: 5px solid #004b87;
            white-space: pre-wrap;
            line-height: 1.8;
            font-size: 1em;
        }

        .seccion {
            margin-bottom: 35px;
        }

        .seccion h3 {
            color: #004b87;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pregunta-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #004b87;
        }

        .pregunta-item .pregunta {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 1.05em;
        }

        .pregunta-item .respuesta {
            color: #555;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .decision-section {
            background: #fff3cd;
            padding: 30px;
            border-radius: 15px;
            border: 2px solid #ffc107;
            margin-top: 30px;
        }

        .decision-section h3 {
            color: #856404;
            margin-bottom: 20px;
            font-size: 1.4em;
        }

        .decision-buttons {
            display: flex;
            gap: 20px;
            margin-top: 25px;
        }

        .btn {
            padding: 15px 35px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            text-decoration: none;
        }

        .btn-success {
            background: #28a745;
            color: white;
            flex: 1;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            flex: 1;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1em;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #004b87;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h2 {
            color: #333;
            font-size: 1.5em;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #666;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert i {
            font-size: 1.3em;
            margin-top: 2px;
        }

        .servicios-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .servicio-badge {
            background: #004b87;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-buttons {
            display: flex;
            justify-content: space-between;
            padding: 20px 40px;
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
        }

        @media print {
            .header-right, .tabs, .decision-section, .nav-buttons, .btn {
                display: none !important;
            }

            .tab-content {
                display: block !important;
            }
        }

        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .decision-buttons {
                flex-direction: column;
            }

            .tab-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1><i class="fas fa-clipboard-check"></i> Entrevista de Solicitud de Beca</h1>
                <p><?= htmlspecialchars($solicitud['Nombres_Apellidos']) ?></p>
            </div>
            <div class="header-right">
                <div class="status-badge status-<?= strtolower($solicitud['Estado_Evaluacion']) ?>">
                    <?= htmlspecialchars($solicitud['Estado_Evaluacion']) ?>
                </div>
                <div style="font-size: 0.9em; opacity: 0.9;">
                    <i class="fas fa-calendar"></i> Cita: 
                    <?= $solicitud['Fecha_Cita'] ? date('d/m/Y', strtotime($solicitud['Fecha_Cita'])) . ' - ' . date('H:i', strtotime($solicitud['Hora_Cita'])) : 'Sin asignar' ?>
                </div>
                <div style="font-size: 0.9em; opacity: 0.9;">
                    <i class="fas fa-hashtag"></i> Expediente: <?= htmlspecialchars($solicitud['Numero_Expediente']) ?>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- Pestañas -->
            <div class="tabs">
                <div class="tab active" onclick="openTab('info-personal')">
                    <i class="fas fa-user"></i> Información Personal
                </div>
                <div class="tab" onclick="openTab('carta')">
                    <i class="fas fa-file-alt"></i> Carta de Solicitud
                </div>
                <div class="tab" onclick="openTab('cuestionario')">
                    <i class="fas fa-question-circle"></i> Cuestionario
                </div>
                <div class="tab" onclick="openTab('socioeconomico')">
                    <i class="fas fa-home"></i> Evaluación Socioeconómica
                </div>
                <div class="tab" onclick="openTab('decision')">
                    <i class="fas fa-gavel"></i> Decisión
                </div>
            </div>

            <!-- TAB 1: INFORMACIÓN PERSONAL -->
            <div id="info-personal" class="tab-content active">
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-user"></i> Datos del Estudiante</h3>
                        <div class="info-item">
                            <span class="info-label">Nombre Completo</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Nombres_Apellidos']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Edad</span>
                            <span class="info-value"><?= $solicitud['Edad'] ?> años</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Teléfono</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Telefono']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Dirección</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Direccion_Domiciliar']) ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-users"></i> Información Familiar</h3>
                        <div class="info-item">
                            <span class="info-label">Nombre de la Madre</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Nombre_Madre'] ?: 'No especificado') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nombre del Padre</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Nombre_Padre'] ?: 'No especificado') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Encargado</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Nombre_Encargado']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Teléfono del Encargado</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Telefono_Encargado']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Estado Civil de Padres</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Estado_Civil_Padres']) ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                        <div class="info-item">
                            <span class="info-label">Último Grado Obtenido</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Grado_Obtenido_Anterior']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Escuela Anterior</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Escuela_Anterior']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Meta Profesional</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Meta_Profesional']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">¿Tiene otra beca?</span>
                            <span class="info-value"><?= $solicitud['Otra_Beca'] ?></span>
                        </div>
                        <?php if ($solicitud['Otra_Beca'] === 'SI'): ?>
                        <div class="info-item">
                            <span class="info-label">Institución que otorga la beca</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Institucion_Beca']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-calendar"></i> Información de Solicitud</h3>
                        <div class="info-item">
                            <span class="info-label">Fecha de Registro</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($solicitud['Fecha_Registro'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fecha de Evaluación</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($solicitud['Fecha_Evaluacion'])) ?></span>
                        </div>
                        <?php if ($solicitud['Fecha_Cita']): ?>
                        <div class="info-item">
                            <span class="info-label">Fecha de Cita</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($solicitud['Fecha_Cita'] . ' ' . $solicitud['Hora_Cita'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Estado de Cita</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Estado_Cita']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">¿Cómo se enteró?</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Como_Se_Entero']) ?></span>
                        </div>
                        <?php if ($solicitud['Nombre_Socio_Rotario']): ?>
                        <div class="info-item">
                            <span class="info-label">Socio Rotario de Referencia</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Nombre_Socio_Rotario']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CARTA DE SOLICITUD -->
            <div id="carta" class="tab-content">
                <div class="seccion">
                    <h3><i class="fas fa-file-alt"></i> Carta de Solicitud de Beca</h3>
                    <div class="carta-solicitud">
                        <?= htmlspecialchars($solicitud['Contenido_Carta'] ?? 'No se encontró la carta de solicitud.') ?>
                    </div>
                </div>
            </div>

            <!-- TAB 3: CUESTIONARIO -->
            <div id="cuestionario" class="tab-content">
                <?php foreach ($secciones_respuestas as $titulo_seccion => $preguntas_seccion): ?>
                    <?php if (!empty($preguntas_seccion)): ?>
                    <div class="seccion">
                        <h3><i class="fas fa-list"></i> <?= $titulo_seccion ?></h3>
                        <?php foreach ($preguntas_seccion as $item): ?>
                        <div class="pregunta-item">
                            <div class="pregunta"><?= htmlspecialchars($item['Pregunta']) ?></div>
                            <div class="respuesta"><?= htmlspecialchars($item['Respuesta']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- TAB 4: EVALUACIÓN SOCIOECONÓMICA -->
            <div id="socioeconomico" class="tab-content">
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-female"></i> Información de la Madre</h3>
                        <div class="info-item">
                            <span class="info-label">¿Sabe leer y escribir?</span>
                            <span class="info-value"><?= $solicitud['Madre_Leer'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Grado de Educación</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Madre_Grado_Educacion']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Profesión u Ocupación</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Profesion_Madre'] ?: 'No especificado') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Lugar de Trabajo</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Lugar_Trabajo_Madre'] ?: 'No especificado') ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-male"></i> Información del Padre</h3>
                        <div class="info-item">
                            <span class="info-label">¿Sabe leer y escribir?</span>
                            <span class="info-value"><?= $solicitud['Padre_Leer'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Grado de Educación</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Padre_Grado_Educacion']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Profesión u Ocupación</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Profesion_Padre'] ?: 'No especificado') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Lugar de Trabajo</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Lugar_Trabajo_Padre'] ?: 'No especificado') ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-home"></i> Información de Vivienda</h3>
                        <div class="info-item">
                            <span class="info-label">Tipo de Vivienda</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Tipo_Vivienda']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Condiciones</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Condiciones_Vivienda']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Material de Construcción</span>
                            <span class="info-value"><?= htmlspecialchars($solicitud['Material_Vivienda']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Servicios Básicos</span>
                            <div class="servicios-list">
                                <?php if (empty($servicios)): ?>
                                    <span class="info-value">No especificado</span>
                                <?php else: ?>
                                    <?php 
                                    $iconos_servicios = [
                                        'agua' => 'tint',
                                        'luz' => 'bolt',
                                        'drenaje' => 'toilet',
                                        'internet' => 'wifi',
                                        'telefono' => 'phone',
                                        'cable' => 'tv'
                                    ];
                                    foreach ($servicios as $servicio): 
                                        $icono = $iconos_servicios[$servicio] ?? 'check';
                                    ?>
                                        <span class="servicio-badge">
                                            <i class="fas fa-<?= $icono ?>"></i>
                                            <?= ucfirst($servicio) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($solicitud['Ensayo_Personal']): ?>
                <div class="seccion">
                    <h3><i class="fas fa-pen"></i> Ensayo Personal</h3>
                    <div class="carta-solicitud">
                        <?= htmlspecialchars($solicitud['Ensayo_Personal']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB 5: DECISIÓN -->
            <div id="decision" class="tab-content">
                <?php if ($solicitud['Estado_Evaluacion'] === 'Pendiente'): ?>
                    <div class="decision-section">
                        <h3><i class="fas fa-gavel"></i> Tomar Decisión sobre la Solicitud</h3>
                        <p style="margin-bottom: 20px; color: #666;">
                            Después de revisar toda la información del solicitante, debes tomar una decisión.
                            Esta acción quedará registrada junto con tu usuario y comentarios.
                        </p>

                        <div class="form-group">
                            <label for="comentarios">
                                Comentarios de la Evaluación 
                                <span style="color: #999;">(Opcional pero recomendado)</span>
                            </label>
                            <textarea id="comentarios" placeholder="Escribe aquí tus observaciones sobre el candidato, razones de tu decisión, recomendaciones, etc."></textarea>
                        </div>

                        <div class="decision-buttons">
                            <button class="btn btn-success" onclick="tomarDecision('Aprobado')">
                                <i class="fas fa-check-circle"></i>
                                Aprobar Solicitud
                            </button>
                            <button class="btn btn-danger" onclick="tomarDecision('Rechazado')">
                                <i class="fas fa-times-circle"></i>
                                Rechazar Solicitud
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-<?= $solicitud['Estado_Evaluacion'] === 'Aprobado' ? 'success' : 'danger' ?>">
                        <i class="fas fa-<?= $solicitud['Estado_Evaluacion'] === 'Aprobado' ? 'check-circle' : 'times-circle' ?>"></i>
                        <div>
                            <strong>Solicitud <?= $solicitud['Estado_Evaluacion'] === 'Aprobado' ? 'Aprobada' : 'Rechazada' ?></strong>
                            <p>Esta solicitud fue <?= strtolower($solicitud['Estado_Evaluacion']) ?> el 
                            <?= $solicitud['Fecha_Decision'] ? date('d/m/Y', strtotime($solicitud['Fecha_Decision'])) : 'fecha no registrada' ?>.</p>
                            
                            <?php if ($solicitud['Comentarios_Evaluacion']): ?>
                                <p style="margin-top: 15px;"><strong>Comentarios:</strong></p>
                                <p style="white-space: pre-wrap;"><?= htmlspecialchars($solicitud['Comentarios_Evaluacion']) ?></p>
                            <?php endif; ?>

                            <?php if ($solicitud['Estado_Evaluacion'] === 'Rechazado' && $solicitud['Motivo_Rechazo']): ?>
                                <p style="margin-top: 15px;"><strong>Motivo del Rechazo:</strong></p>
                                <p style="white-space: pre-wrap;"><?= htmlspecialchars($solicitud['Motivo_Rechazo']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($solicitud['Estado_Evaluacion'] === 'Aprobado'): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <h3><i class="fas fa-tasks"></i> Próximos Pasos</h3>
                                <div style="line-height: 2;">
                                    <p><i class="fas fa-check" style="color: #28a745;"></i> 1. Crear registro de beca</p>
                                    <p><i class="fas fa-check" style="color: #28a745;"></i> 2. Generar carta de compromiso</p>
                                    <p><i class="fas fa-check" style="color: #28a745;"></i> 3. Obtener firmas del estudiante y encargado</p>
                                    <p><i class="fas fa-check" style="color: #28a745;"></i> 4. Archivar documentación</p>
                                </div>
                                <div style="margin-top: 20px;">
                                    <a href="carta_compromiso.php?id=<?= $solicitud['Id_Estudiante'] ?>" class="btn btn-success">
                                        <i class="fas fa-file-signature"></i>
                                        Generar Carta de Compromiso
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Botones de Navegación -->
            <div class="nav-buttons">
                <a href="aplicaciones.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Solicitudes
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Ocultar todos los tabs
            const tabContents = document.getElementsByClassName('tab-content');
            for (let content of tabContents) {
                content.classList.remove('active');
            }

            // Remover active de todos los tabs
            const tabs = document.getElementsByClassName('tab');
            for (let tab of tabs) {
                tab.classList.remove('active');
            }

            // Mostrar el tab seleccionado
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        async function tomarDecision(decision) {
            const comentarios = document.getElementById('comentarios').value;

            if (!confirm(`¿Estás seguro de ${decision === 'Aprobado' ? 'APROBAR' : 'RECHAZAR'} esta solicitud?\n\nEsta acción quedará registrada en el sistema.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id_evaluacion', '<?= $id_evaluacion ?>');
                formData.append('decision', decision);
                formData.append('comentarios', comentarios);
                formData.append('user_id', '<?= $user_id ?>');

                const response = await fetch('procesar_decision.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Ocurrió un error al procesar la decisión. Por favor intenta nuevamente.');
            }
        }
    </script>
</body>
</html>