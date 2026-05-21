<?php
/**
 * admin_detalle.php
 * Ver toda la información detallada de un solicitante
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_solicitudes.php');
    exit;
}

$id_estudiante = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM Estudiantes WHERE Id_Estudiante = ?");
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$estudiante) {
        header('Location: admin_solicitudes.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM Evaluaciones_Socioeconomicas WHERE Id_Estudiante = ? ORDER BY Fecha_Evaluacion DESC LIMIT 1");
    $stmt->execute([$id_estudiante]);
    $evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

    $composicion_familiar = [];
    if ($evaluacion) {
        $stmt = $pdo->prepare("SELECT * FROM Composicion_Familiar WHERE Id_Evaluacion = ? ORDER BY Edad DESC");
        $stmt->execute([$evaluacion['Id_Evaluacion']]);
        $composicion_familiar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("SELECT * FROM Cartas_Solicitud WHERE Id_Estudiante = ? ORDER BY Fecha_Creacion DESC LIMIT 1");
    $stmt->execute([$id_estudiante]);
    $carta = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM Citas_Entrevista WHERE Id_Estudiante = ? ORDER BY Fecha_Creacion DESC LIMIT 1");
    $stmt->execute([$id_estudiante]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT rc.*, p.Pregunta 
        FROM Respuestas_Cuestionario rc 
        LEFT JOIN Preguntas_Cuestionario p ON rc.Id_Pregunta = p.Id_Pregunta
        WHERE rc.Id_Estudiante = ?
        ORDER BY p.Orden");
    $stmt->execute([$id_estudiante]);
    $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM Documentos_Solicitud WHERE Id_Estudiante = ? ORDER BY Fecha_Subida DESC");
    $stmt->execute([$id_estudiante]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM Becas_Otorgadas WHERE Id_Estudiante = ? ORDER BY Fecha_Inicio DESC LIMIT 1");
    $stmt->execute([$id_estudiante]);
    $beca = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Helper: evita el error de null en htmlspecialchars (PHP 8.1+)
function h(?string $value, string $fallback = '—'): string {
    return htmlspecialchars($value ?? $fallback, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Solicitud - <?= h($estudiante['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { font-size: 24px; }

        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover { background: rgba(255,255,255,0.3); }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .status-banner {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }
        .status-activo     { background: #d4edda; color: #155724; }
        .status-rechazado  { background: #f8d7da; color: #721c24; }
        .status-graduado   { background: #cce5ff; color: #004085; }
        .status-suspendido { background: #fff3cd; color: #856404; }
        .status-retirado   { background: #e2e3e5; color: #383d41; }

        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .section-header i  { font-size: 24px; color: #667eea; }
        .section-header h2 { font-size: 20px; color: #333; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        .info-item label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 13px;
            text-transform: uppercase;
        }
        .info-item .value { font-size: 16px; color: #333; }

        /* Valor vacío — gris claro para que se note que no hay dato */
        .info-item .value.empty { color: #aaa; font-style: italic; font-size: 14px; }

        .carta-content {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            white-space: pre-wrap;
            line-height: 1.8;
            font-size: 15px;
        }

        .respuestas-list { display: flex; flex-direction: column; gap: 15px; }
        .respuesta-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        .respuesta-item .pregunta  { font-weight: 600; color: #555; margin-bottom: 8px; }
        .respuesta-item .respuesta { color: #333; font-size: 15px; }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding: 30px 0;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; transform: translateY(-2px); }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; transform: translateY(-2px); }
        .btn-danger  { background: #dc3545; color: white; }
        .btn-danger:hover  { background: #c82333; transform: translateY(-2px); }

        .cita-info {
            background: linear-gradient(135deg, #e7f3ff 0%, #f3e7ff 100%);
            padding: 25px;
            border-radius: 10px;
            border: 2px solid #667eea;
        }
        .cita-info .fecha {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .tabla-familiar { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabla-familiar th { background: #667eea; color: white; padding: 12px; text-align: left; }
        .tabla-familiar td { padding: 12px; border-bottom: 1px solid #ddd; }
        .tabla-familiar tr:hover { background: #f8f9fa; }

        .documentos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .documento-card {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .documento-card:hover { border-color: #667eea; box-shadow: 0 2px 8px rgba(102,126,234,0.2); }
        .documento-tipo  { font-weight: 600; color: #667eea; margin-bottom: 5px; }
        .documento-nombre { font-size: 14px; color: #666; }

        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }

        @media print {
            .header, .actions, .btn-back { display: none; }
            .section { page-break-inside: avoid; }
        }

        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1><i class="fas fa-user-graduate"></i> Detalle de Solicitud — <?= h($estudiante['Nombres_Apellidos']) ?></h1>
        <a href="lista_solicitudes.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="container">

    <!-- Estado -->
    <div class="status-banner status-<?= strtolower(h($estudiante['Estado_Estudiante'], 'activo')) ?>">
        <i class="fas fa-info-circle"></i>
        Estado: <strong><?= h($estudiante['Estado_Estudiante']) ?></strong>
        <?php if (!empty($estudiante['Estado_Beca'])): ?>
            | Estado Beca: <strong><?= h($estudiante['Estado_Beca']) ?></strong>
        <?php endif; ?>
    </div>

    <!-- ── Información Personal ── -->
    <div class="section">
        <div class="section-header">
            <i class="fas fa-user"></i>
            <h2>Información Personal</h2>
        </div>

        <div class="info-grid">
            <?php
            $campos_personales = [
                'Número de Expediente'  => $estudiante['Numero_Expediente'],
                'Nombre Completo'       => $estudiante['Nombres_Apellidos'],
                'Edad'                  => ($estudiante['Edad'] ? $estudiante['Edad'] . ' años' : null),
                'Email'                 => $estudiante['Email'],
                'Teléfono'              => $estudiante['Telefono'],
                'Dirección'             => $estudiante['Direccion_Domiciliar'],
                'Fecha de Registro'     => ($estudiante['Fecha_Registro'] ? date('d/m/Y', strtotime($estudiante['Fecha_Registro'])) : null),
                'Nombre de la Madre'    => $estudiante['Nombre_Madre'],
                'Nombre del Padre'      => $estudiante['Nombre_Padre'],
                'Nombre del Encargado'  => $estudiante['Nombre_Encargado'],
                'Teléfono del Encargado'=> $estudiante['Telefono_Encargado'],
            ];
            foreach ($campos_personales as $label => $val):
                if ($val === null || $val === '') continue; // ocultar campos vacíos
            ?>
            <div class="info-item">
                <label><?= htmlspecialchars($label) ?></label>
                <div class="value"><?= htmlspecialchars($val) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Información Académica ── -->
    <div class="section">
        <div class="section-header">
            <i class="fas fa-graduation-cap"></i>
            <h2>Información Académica</h2>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <label>Grado Obtenido Anterior</label>
                <div class="value <?= empty($estudiante['Grado_Obtenido_Anterior']) ? 'empty' : '' ?>">
                    <?= h($estudiante['Grado_Obtenido_Anterior'], 'Sin información') ?>
                </div>
            </div>
            <div class="info-item">
                <label>Escuela Anterior</label>
                <div class="value <?= empty($estudiante['Escuela_Anterior']) ? 'empty' : '' ?>">
                    <?= h($estudiante['Escuela_Anterior'], 'Sin información') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Evaluación Socioeconómica ── -->
    <?php if ($evaluacion): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-chart-line"></i>
            <h2>Evaluación Socioeconómica</h2>
        </div>

        <div class="info-grid">

            <?php
            // Todos los campos de evaluación con su etiqueta
            // Usando el helper h() para evitar el error de null en PHP 8.1+
            $campos_eval = [
                'Fecha de Evaluación'         => $evaluacion['Fecha_Evaluacion']
                                                    ? date('d/m/Y', strtotime($evaluacion['Fecha_Evaluacion']))
                                                    : null,
                'Estado de Evaluación'        => $evaluacion['Estado_Evaluacion'],
                'Meta Profesional'            => $evaluacion['Meta_Profesional'],
                '¿Tiene otra beca?'           => $evaluacion['Otra_Beca'],
                'Institución de la Beca'      => ($evaluacion['Otra_Beca'] === 'SI') ? ($evaluacion['Institucion_Beca'] ?? null) : null,
                'Contacto de la Institución'  => ($evaluacion['Otra_Beca'] === 'SI') ? ($evaluacion['Contacto_Institucion'] ?? null) : null,
                'Estado Civil de los Padres'  => $evaluacion['Estado_Civil_Padres'],
                '¿Madre sabe leer?'           => $evaluacion['Madre_Leer'],
                'Educación de la Madre'       => $evaluacion['Madre_Grado_Educacion'],
                '¿Padre sabe leer?'           => $evaluacion['Padre_Leer'],
                'Educación del Padre'         => $evaluacion['Padre_Grado_Educacion'],
                'Profesión de la Madre'       => $evaluacion['Profesion_Madre'] ?? null,
                'Lugar de Trabajo — Madre'    => $evaluacion['Lugar_Trabajo_Madre'] ?? null,
                'Profesión del Padre'         => $evaluacion['Profesion_Padre'] ?? null,
                'Lugar de Trabajo — Padre'    => $evaluacion['Lugar_Trabajo_Padre'] ?? null,
                'Tipo de Vivienda'            => $evaluacion['Tipo_Vivienda'],
                'Condiciones de la Vivienda'  => $evaluacion['Condiciones_Vivienda'],
                'Material de la Vivienda'     => $evaluacion['Material_Vivienda'],
                'Servicios Básicos'           => $evaluacion['Servicios_Basicos'],
                'Cómo se Enteró'              => $evaluacion['Como_Se_Entero'],
                'Socio Rotario'               => $evaluacion['Nombre_Socio_Rotario'] ?? null,
            ];

            foreach ($campos_eval as $label => $val):
                // Ocultar campos que son null o vacíos
                if ($val === null || $val === '') continue;
            ?>
            <div class="info-item">
                <label><?= htmlspecialchars($label) ?></label>
                <div class="value"><?= htmlspecialchars((string) $val) ?></div>
            </div>
            <?php endforeach; ?>

        </div>

        <?php if (!empty($evaluacion['Ensayo_Personal'])): ?>
        <div style="margin-top:25px;">
            <label style="display:block;font-weight:600;margin-bottom:10px;color:#555;font-size:14px;">
                <i class="fas fa-file-alt"></i> ENSAYO PERSONAL:
            </label>
            <div class="carta-content"><?= nl2br(h($evaluacion['Ensayo_Personal'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($evaluacion['Motivo_Rechazo'])): ?>
        <div style="margin-top:25px;padding:20px;background:#fff3cd;border-left:4px solid #ffc107;border-radius:8px;">
            <label style="display:block;font-weight:600;margin-bottom:10px;color:#856404;">
                <i class="fas fa-exclamation-triangle"></i> MOTIVO DE RECHAZO:
            </label>
            <div style="color:#856404;"><?= nl2br(h($evaluacion['Motivo_Rechazo'])) ?></div>
            <?php if (!empty($evaluacion['Fecha_Decision'])): ?>
            <div style="margin-top:10px;font-size:13px;color:#666;">
                Fecha de decisión: <?= date('d/m/Y', strtotime($evaluacion['Fecha_Decision'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Composición Familiar ── -->
    <?php if (count($composicion_familiar) > 0): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-users"></i>
            <h2>Composición Familiar</h2>
        </div>

        <table class="tabla-familiar">
            <thead>
                <tr>
                    <th>Nombre y Apellidos</th>
                    <th>Edad</th>
                    <th>Parentesco</th>
                    <th>Nivel Educativo</th>
                    <th>Estado Civil</th>
                    <th>Ocupación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($composicion_familiar as $familiar): ?>
                <tr>
                    <td><?= h($familiar['Nombre_Apellidos']) ?></td>
                    <td><?= h((string)($familiar['Edad'] ?? '')) ?></td>
                    <td><?= h($familiar['Parentesco']) ?></td>
                    <td><?= h($familiar['Nivel_Educativo']) ?></td>
                    <td><?= h($familiar['Estado_Civil']) ?></td>
                    <td><?= h($familiar['Ocupacion']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; /* fin if ($evaluacion) */ ?>

    <!-- ── Carta de Solicitud ── -->
    <?php if ($carta): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-envelope"></i>
            <h2>Carta de Solicitud</h2>
        </div>
        <div class="carta-content"><?= nl2br(h($carta['Contenido_Carta'])) ?></div>
        <div style="margin-top:10px;font-size:13px;color:#666;">
            Fecha de creación: <?= date('d/m/Y H:i', strtotime($carta['Fecha_Creacion'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Respuestas del Cuestionario ── -->
    <?php if (count($respuestas) > 0): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-question-circle"></i>
            <h2>Respuestas del Cuestionario</h2>
        </div>

        <div class="respuestas-list">
            <?php foreach ($respuestas as $index => $resp): ?>
            <div class="respuesta-item">
                <div class="pregunta">
                    <?= ($index + 1) ?>. <?= h($resp['Pregunta'] ?? "Pregunta #{$resp['Id_Pregunta']}") ?>
                </div>
                <div class="respuesta"><?= nl2br(h($resp['Respuesta'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Documentos Adjuntos ── -->
    <?php if (count($documentos) > 0): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-file-pdf"></i>
            <h2>Documentos Adjuntos</h2>
        </div>

        <div class="documentos-grid">
            <?php foreach ($documentos as $doc): ?>
            <div class="documento-card">
                <div class="documento-tipo">
                    <i class="fas fa-file"></i> <?= str_replace('_', ' ', h($doc['Tipo_Documento'])) ?>
                </div>
                <div class="documento-nombre"><?= h($doc['Nombre_Archivo']) ?></div>
                <div style="margin-top:10px;font-size:12px;color:#999;">
                    <?= date('d/m/Y', strtotime($doc['Fecha_Subida'])) ?>
                </div>
                <div style="margin-top:8px;">
                    <span style="padding:4px 8px;border-radius:4px;font-size:11px;
                                 background:<?= ($doc['Estado_Documento'] == 'Aprobado') ? '#d4edda' : '#fff3cd' ?>;
                                 color:<?= ($doc['Estado_Documento'] == 'Aprobado') ? '#155724' : '#856404' ?>;">
                        <?= h($doc['Estado_Documento']) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Cita ── -->
    <?php if ($cita): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-calendar-check"></i>
            <h2>Información de la Cita</h2>
        </div>

        <div class="cita-info">
            <div class="fecha">
                <i class="fas fa-calendar-alt"></i>
                <?= date('d/m/Y', strtotime($cita['Fecha_Cita'])) ?>
                a las <?= date('H:i', strtotime($cita['Hora_Cita'])) ?>
            </div>
            <p><strong>Lugar:</strong> <?= h($cita['Lugar_Entrevista']) ?></p>
            <p><strong>Estado:</strong>
                <span style="padding:5px 12px;border-radius:20px;font-weight:600;font-size:13px;
                      background:<?= ($cita['Estado_Cita'] == 'Completada') ? '#d4edda' : '#fff3cd' ?>;
                      color:<?= ($cita['Estado_Cita'] == 'Completada') ? '#155724' : '#856404' ?>;">
                    <?= h($cita['Estado_Cita']) ?>
                </span>
            </p>
            <?php if (!empty($cita['Observaciones'])): ?>
            <p style="margin-top:15px;"><strong>Observaciones:</strong></p>
            <p style="margin-top:5px;"><?= nl2br(h($cita['Observaciones'])) ?></p>
            <?php endif; ?>
            <p style="margin-top:10px;font-size:13px;color:#666;">
                Fecha de creación: <?= date('d/m/Y H:i', strtotime($cita['Fecha_Creacion'])) ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Beca otorgada ── -->
    <?php if ($beca): ?>
    <div class="section">
        <div class="section-header">
            <i class="fas fa-award"></i>
            <h2>Información de la Beca Otorgada</h2>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <label>Tipo de Beca</label>
                <div class="value"><?= h($beca['Tipo_Beca']) ?></div>
            </div>
            <div class="info-item">
                <label>Monto Mensual</label>
                <div class="value">Q <?= number_format($beca['Monto_Mensual'], 2) ?></div>
            </div>
            <div class="info-item">
                <label>Fecha de Inicio</label>
                <div class="value"><?= date('d/m/Y', strtotime($beca['Fecha_Inicio'])) ?></div>
            </div>
            <?php if (!empty($beca['Fecha_Fin'])): ?>
            <div class="info-item">
                <label>Fecha de Fin</label>
                <div class="value"><?= date('d/m/Y', strtotime($beca['Fecha_Fin'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <label>Estado de la Beca</label>
                <div class="value">
                    <span style="padding:5px 12px;border-radius:20px;font-weight:600;
                          background:<?= ($beca['Estado_Beca'] == 'Activa') ? '#d4edda' : '#f8d7da' ?>;
                          color:<?= ($beca['Estado_Beca'] == 'Activa') ? '#155724' : '#721c24' ?>;">
                        <?= h($beca['Estado_Beca']) ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <label>Promedio Mínimo Requerido</label>
                <div class="value"><?= number_format($beca['Promedio_Minimo'], 2) ?></div>
            </div>
            <?php if (!empty($beca['Promedio_Actual'])): ?>
            <div class="info-item">
                <label>Promedio Actual</label>
                <div class="value" style="color:<?= ($beca['Promedio_Actual'] >= $beca['Promedio_Minimo']) ? '#28a745' : '#dc3545' ?>;font-weight:bold;">
                    <?= number_format($beca['Promedio_Actual'], 2) ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($beca['Motivo_Suspension'])): ?>
            <div class="info-item" style="grid-column:1/-1;border-left-color:#dc3545;">
                <label style="color:#dc3545;">Motivo de Suspensión</label>
                <div class="value"><?= nl2br(h($beca['Motivo_Suspension'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($beca['Fecha_Finalizacion'])): ?>
            <div class="info-item">
                <label>Fecha de Finalización</label>
                <div class="value"><?= date('d/m/Y', strtotime($beca['Fecha_Finalizacion'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Acciones ── -->
    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimir
        </button>

        <?php if ($estudiante['Estado_Estudiante'] === 'Activo' && !$beca): ?>
        <a href="aprobar_beca.php?id=<?= $id_estudiante ?>" class="btn btn-success">
            <i class="fas fa-check-circle"></i> Aprobar Beca
        </a>
        <?php endif; ?>

        <?php if (in_array($estudiante['Estado_Estudiante'], ['Rechazado', 'Suspendido', 'Retirado'])): ?>
        <a href="reactivar.php?id=<?= $id_estudiante ?>" class="btn btn-warning">
            <i class="fas fa-redo"></i> Reactivar Solicitud
        </a>
        <?php endif; ?>

        <?php if ($estudiante['Estado_Estudiante'] === 'Activo' && !$evaluacion): ?>
        <a href="crear_evaluacion.php?id=<?= $id_estudiante ?>" class="btn btn-primary">
            <i class="fas fa-clipboard-check"></i> Crear Evaluación
        </a>
        <?php endif; ?>

        <?php if (!$cita && $evaluacion): ?>
        <a href="programar_cita.php?id=<?= $id_estudiante ?>" class="btn btn-primary">
            <i class="fas fa-calendar-plus"></i> Programar Cita
        </a>
        <?php endif; ?>

        <?php if ($cita && in_array($cita['Estado_Cita'], ['Programada', 'Reprogramada'])): ?>
        <a href="gestionar_cita.php?id=<?= $cita['Id_Cita'] ?>" class="btn btn-warning">
            <i class="fa-solid fa-calendar-week"></i> Gestionar Cita
        </a>
        <?php endif; ?>

        <a href="editar_estudiante.php?id=<?= $id_estudiante ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar Información
        </a>

        <?php if ($estudiante['Estado_Estudiante'] !== 'Rechazado'): ?>
        <a href="rechazar_solicitud.php?id=<?= $id_estudiante ?>" class="btn btn-danger"
           onclick="return confirm('¿Está seguro de rechazar esta solicitud?')">
            <i class="fas fa-times-circle"></i> Rechazar Solicitud
        </a>
        <?php endif; ?>

        <a href="lista_solicitudes.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Volver al Listado
        </a>
    </div>

</div><!-- /container -->

<script>
    document.querySelectorAll('.btn-danger').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de realizar esta acción?')) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>