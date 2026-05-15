<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar parámetros
if (!isset($_GET['id']) || !isset($_GET['año'])) {
    header('Location: evaluacion_anual.php');
    exit;
}

$id_estudiante = $_GET['id'];
$año_evaluacion = $_GET['año'];
$user_id = $_SESSION['user_id'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener información del estudiante y su beca
$sql = "SELECT 
            e.*,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Promedio_Minimo,
            b.Promedio_Actual,
            b.Fecha_Inicio as Fecha_Inicio_Beca,
            TIMESTAMPDIFF(YEAR, b.Fecha_Inicio, NOW()) as Años_Beca,
            -- Promedio anual
            (SELECT AVG(Promedio) 
             FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante 
             AND YEAR(Fecha_Subida) = ?) as Promedio_Anual,
            -- Total de pagos
            (SELECT COUNT(*) 
             FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca 
             AND YEAR(Fecha_Pago) = ?) as Pagos_Realizados,
            -- Total de boletas
            (SELECT COUNT(*) 
             FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante 
             AND YEAR(Fecha_Subida) = ?) as Boletas_Subidas
        FROM Estudiantes e
        INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE e.Id_Estudiante = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$año_evaluacion, $año_evaluacion, $año_evaluacion, $id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: evaluacion_anual.php');
    exit;
}

// Verificar si ya existe una evaluación para este año
$sql_eval = "SELECT * FROM Evaluaciones_Anuales 
             WHERE Id_Beca = ? AND Año_Evaluacion = ?
             ORDER BY Fecha_Evaluacion DESC LIMIT 1";
$stmt_eval = $pdo->prepare($sql_eval);
$stmt_eval->execute([$estudiante['Id_Beca'], $año_evaluacion]);
$evaluacion_existente = $stmt_eval->fetch(PDO::FETCH_ASSOC);

// Obtener boletas del año
$sql_boletas = "SELECT * FROM Boletas_Calificaciones 
                WHERE Id_Estudiante = ? AND YEAR(Fecha_Subida) = ?
                ORDER BY Fecha_Subida DESC";
$stmt_boletas = $pdo->prepare($sql_boletas);
$stmt_boletas->execute([$id_estudiante, $año_evaluacion]);
$boletas = $stmt_boletas->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$evaluacion_existente) {
    try {
        // Recibir datos
        $promedio_anual = $_POST['promedio_anual'];
        $asistencia = $_POST['asistencia'];
        $conducta = $_POST['conducta'];
        $cumplimiento_compromisos = $_POST['cumplimiento_compromisos'];
        $situacion_economica = $_POST['situacion_economica'];
        $observaciones_generales = $_POST['observaciones_generales'];
        $decision = $_POST['decision']; // Renovar o Finalizar
        $motivo_decision = $_POST['motivo_decision'];
        $monto_nuevo = $_POST['monto_nuevo'] ?? $estudiante['Monto_Mensual'];
        
        // Validaciones
        if (empty($decision)) {
            throw new Exception('Debes seleccionar una decisión');
        }

        if ($decision === 'Finalizado' && empty($motivo_decision)) {
            throw new Exception('Debes especificar el motivo de finalización');
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        // Insertar evaluación anual
        $sql_insert = "INSERT INTO Evaluaciones_Anuales 
                       (Id_Beca, Año_Evaluacion, Promedio_Anual, Asistencia, Conducta,
                        Cumplimiento_Compromisos, Situacion_Economica, Observaciones_Generales,
                        Estado_Evaluacion, Motivo_Decision, Monto_Anterior, Monto_Nuevo,
                        Fecha_Evaluacion, Id_Usuario_Evaluador)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $estudiante['Id_Beca'],
            $año_evaluacion,
            $promedio_anual,
            $asistencia,
            $conducta,
            $cumplimiento_compromisos,
            $situacion_economica,
            $observaciones_generales,
            $decision,
            $motivo_decision,
            $estudiante['Monto_Mensual'],
            $monto_nuevo
        ]);

        // Actualizar la beca según la decisión
        if ($decision === 'Renovado') {
            // Actualizar monto si cambió
            if ($monto_nuevo != $estudiante['Monto_Mensual']) {
                $sql_update = "UPDATE Becas_Otorgadas 
                              SET Monto_Mensual = ?
                              WHERE Id_Beca = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$monto_nuevo, $estudiante['Id_Beca']]);
            }
            
            $mensaje_beca = "Renovó la beca de {$estudiante['Nombres_Apellidos']} para el año " . ($año_evaluacion + 1);
        } else {
            // Finalizar la beca
            $sql_update = "UPDATE Becas_Otorgadas 
                          SET Estado_Beca = 'Finalizada',
                              Fecha_Finalizacion = NOW(),
                              Motivo_Suspension = ?
                          WHERE Id_Beca = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$motivo_decision, $estudiante['Id_Beca']]);

            // Actualizar estado del estudiante
            $sql_est = "UPDATE Estudiantes 
                       SET Estado_Beca = 'Finalizado'
                       WHERE Id_Estudiante = ?";
            $stmt_est = $pdo->prepare($sql_est);
            $stmt_est->execute([$id_estudiante]);
            
            $mensaje_beca = "Finalizó la beca de {$estudiante['Nombres_Apellidos']}";
        }

        // Registrar en bitácora
        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                         VALUES (?, ?, CURDATE())";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $stmt_bitacora->execute([$user_id, $mensaje_beca]);

        // Commit
        $pdo->commit();

        $mensaje = '¡Evaluación registrada exitosamente!';
        $tipo_mensaje = 'success';

        // Recargar evaluación
        $stmt_eval->execute([$estudiante['Id_Beca'], $año_evaluacion]);
        $evaluacion_existente = $stmt_eval->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = 'Error al registrar la evaluación: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluar - <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></title>
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
            color: #e74c3c;
            text-decoration: none;
        }

        .header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .header-right {
            text-align: right;
        }

        .year-badge {
            display: inline-block;
            padding: 15px 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 30px;
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 10px;
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

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
            text-align: right;
        }

        .promedio-grande {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }

        .promedio-numero {
            font-size: 4em;
            font-weight: 700;
            display: block;
            margin-bottom: 10px;
        }

        .promedio-numero.excelente { color: #27ae60; }
        .promedio-numero.bueno { color: #3498db; }
        .promedio-numero.regular { color: #f39c12; }
        .promedio-numero.bajo { color: #e74c3c; }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e74c3c;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-option:hover {
            border-color: #e74c3c;
            background: #fff5f5;
        }

        .radio-option input {
            width: auto;
            margin: 0;
        }

        .decision-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 25px;
            border-radius: 10px;
            margin: 30px 0;
        }

        .decision-box h3 {
            color: #856404;
            margin-bottom: 20px;
        }

        .btn {
            padding: 12px 30px;
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

        .btn-success {
            background: #27ae60;
            color: white;
            width: 100%;
            justify-content: center;
            font-size: 1.1em;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .evaluacion-existente {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            padding: 30px;
            border-radius: 15px;
        }

        .evaluacion-existente h3 {
            color: #2e7d32;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .estado-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1.1em;
            margin-bottom: 15px;
        }

        .estado-renovado {
            background: #27ae60;
            color: white;
        }

        .estado-finalizado {
            background: #e74c3c;
            color: white;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
            <a href="evaluacion_anual.php">Evaluación Anual</a> /
            <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Evaluación Anual</h1>
                <p style="font-size: 1.2em; margin-top: 10px;">
                    <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>
                </p>
            </div>
            <div class="header-right">
                <div class="year-badge"><?= $año_evaluacion ?></div>
                <div style="font-size: 0.9em; opacity: 0.95;">
                    Año de Evaluación
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_mensaje ?>">
            <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $mensaje ?></div>
        </div>
        <?php endif; ?>

        <?php if ($evaluacion_existente): ?>
        <!-- Evaluación ya realizada -->
        <div class="evaluacion-existente">
            <h3><i class="fas fa-check-circle"></i> Evaluación Completada</h3>
            
            <span class="estado-badge estado-<?= strtolower($evaluacion_existente['Estado_Evaluacion']) ?>">
                <?= $evaluacion_existente['Estado_Evaluacion'] ?>
            </span>
            
            <div style="margin-top: 20px;">
                <div class="info-item">
                    <span class="info-label">Fecha de Evaluación:</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($evaluacion_existente['Fecha_Evaluacion'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Promedio Anual Evaluado:</span>
                    <span class="info-value"><strong><?= number_format($evaluacion_existente['Promedio_Anual'], 1) ?></strong></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Asistencia:</span>
                    <span class="info-value"><?= htmlspecialchars($evaluacion_existente['Asistencia']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Conducta:</span>
                    <span class="info-value"><?= htmlspecialchars($evaluacion_existente['Conducta']) ?></span>
                </div>
                <?php if ($evaluacion_existente['Estado_Evaluacion'] === 'Renovado' && $evaluacion_existente['Monto_Nuevo'] != $evaluacion_existente['Monto_Anterior']): ?>
                <div class="info-item">
                    <span class="info-label">Ajuste de Monto:</span>
                    <span class="info-value">
                        Q<?= number_format($evaluacion_existente['Monto_Anterior'], 2) ?> → 
                        Q<?= number_format($evaluacion_existente['Monto_Nuevo'], 2) ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($evaluacion_existente['Motivo_Decision']): ?>
                <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 8px;">
                    <strong>Motivo/Observaciones:</strong>
                    <p style="margin-top: 10px; white-space: pre-wrap;"><?= htmlspecialchars($evaluacion_existente['Motivo_Decision']) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 25px;">
                <a href="evaluacion_anual.php?año=<?= $año_evaluacion ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a la Lista
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- Formulario de evaluación -->
        <div class="content-grid">
            <!-- Panel izquierdo: Información del estudiante -->
            <div>
                <div class="card">
                    <h2><i class="fas fa-user"></i> Información del Estudiante</h2>
                    
                    <div class="info-item">
                        <span class="info-label">Expediente:</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Numero_Expediente']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tipo de Beca:</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Tipo_Beca']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Monto Mensual:</span>
                        <span class="info-value"><strong>Q<?= number_format($estudiante['Monto_Mensual'], 2) ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Años con Beca:</span>
                        <span class="info-value"><?= $estudiante['Años_Beca'] ?> año(s)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono:</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Telefono']) ?></span>
                    </div>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2><i class="fas fa-chart-line"></i> Rendimiento Año <?= $año_evaluacion ?></h2>
                    
                    <?php if ($estudiante['Promedio_Anual']): 
                        $promedio = $estudiante['Promedio_Anual'];
                        $clase = 'bajo';
                        if ($promedio >= 90) $clase = 'excelente';
                        elseif ($promedio >= 85) $clase = 'bueno';
                        elseif ($promedio >= $estudiante['Promedio_Minimo']) $clase = 'regular';
                    ?>
                    <div class="promedio-grande">
                        <span class="promedio-numero <?= $clase ?>">
                            <?= number_format($promedio, 1) ?>
                        </span>
                        <div style="font-size: 1.1em; color: #666;">
                            Promedio Anual
                        </div>
                        <div style="font-size: 0.9em; color: #999; margin-top: 5px;">
                            Mínimo requerido: <?= $estudiante['Promedio_Minimo'] ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 30px;">
                        Sin boletas registradas en <?= $año_evaluacion ?>
                    </p>
                    <?php endif; ?>

                    <div class="info-item">
                        <span class="info-label">Pagos Recibidos:</span>
                        <span class="info-value"><?= $estudiante['Pagos_Realizados'] ?> / 12 meses</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Boletas Subidas:</span>
                        <span class="info-value"><?= $estudiante['Boletas_Subidas'] ?> boleta(s)</span>
                    </div>
                </div>
            </div>

            <!-- Panel derecho: Formulario de evaluación -->
            <div class="card">
                <h2><i class="fas fa-clipboard-check"></i> Formulario de Evaluación</h2>

                <form method="POST" action="">
                    <!-- Promedio Anual -->
                    <div class="form-section">
                        <h3><i class="fas fa-graduation-cap"></i> Rendimiento Académico</h3>
                        
                        <div class="form-group">
                            <label>
                                Promedio Anual <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   name="promedio_anual" 
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   value="<?= $estudiante['Promedio_Anual'] ?? '' ?>"
                                   required>
                            <div class="hint">Promedio general del año <?= $año_evaluacion ?></div>
                        </div>

                        <div class="form-group">
                            <label>
                                Asistencia <span class="required">*</span>
                            </label>
                            <select name="asistencia" required>
                                <option value="">Selecciona...</option>
                                <option value="Excelente">Excelente (95-100%)</option>
                                <option value="Muy Buena">Muy Buena (90-94%)</option>
                                <option value="Buena">Buena (85-89%)</option>
                                <option value="Regular">Regular (80-84%)</option>
                                <option value="Deficiente">Deficiente (<80%)</option>
                            </select>
                            <div class="hint">Porcentaje de asistencia a clases</div>
                        </div>

                        <div class="form-group">
                            <label>
                                Conducta <span class="required">*</span>
                            </label>
                            <select name="conducta" required>
                                <option value="">Selecciona...</option>
                                <option value="Excelente">Excelente</option>
                                <option value="Muy Buena">Muy Buena</option>
                                <option value="Buena">Buena</option>
                                <option value="Regular">Regular</option>
                                <option value="Deficiente">Deficiente</option>
                            </select>
                            <div class="hint">Comportamiento general del estudiante</div>
                        </div>
                    </div>

                    <!-- Cumplimiento -->
                    <div class="form-section">
                        <h3><i class="fas fa-tasks"></i> Cumplimiento de Compromisos</h3>
                        
                        <div class="form-group">
                            <label>
                                Evaluación de Cumplimiento <span class="required">*</span>
                            </label>
                            <select name="cumplimiento_compromisos" required>
                                <option value="">Selecciona...</option>
                                <option value="Cumple Totalmente">Cumple Totalmente</option>
                                <option value="Cumple Satisfactoriamente">Cumple Satisfactoriamente</option>
                                <option value="Cumple Parcialmente">Cumple Parcialmente</option>
                                <option value="No Cumple">No Cumple</option>
                            </select>
                            <div class="hint">Entrega de boletas, asistencia a reuniones, participación, etc.</div>
                        </div>
                    </div>

                    <!-- Situación Económica -->
                    <div class="form-section">
                        <h3><i class="fas fa-home"></i> Situación Socioeconómica</h3>
                        
                        <div class="form-group">
                            <label>
                                Estado Actual <span class="required">*</span>
                            </label>
                            <select name="situacion_economica" required>
                                <option value="">Selecciona...</option>
                                <option value="Sin Cambios">Sin cambios significativos</option>
                                <option value="Ha Mejorado">Ha mejorado</option>
                                <option value="Ha Empeorado">Ha empeorado</option>
                            </select>
                            <div class="hint">Cambios en la situación familiar desde la última evaluación</div>
                        </div>

                        <div class="form-group">
                            <label>Observaciones Generales</label>
                            <textarea name="observaciones_generales" 
                                      placeholder="Comentarios adicionales sobre el desempeño y situación del estudiante..."></textarea>
                        </div>
                    </div>

                    <!-- Decisión -->
                    <div class="decision-box">
                        <h3><i class="fas fa-gavel"></i> Decisión de Renovación</h3>
                        
                        <div class="form-group">
                            <label>
                                Decisión <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="decision" value="Renovado" required>
                                    <span><i class="fas fa-check-circle" style="color: #27ae60;"></i> Renovar Beca</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="decision" value="Finalizado" required>
                                    <span><i class="fas fa-times-circle" style="color: #e74c3c;"></i> Finalizar Beca</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Monto Mensual para Próximo Año</label>
                            <input type="number" 
                                   name="monto_nuevo" 
                                   step="0.01"
                                   min="0"
                                   value="<?= $estudiante['Monto_Mensual'] ?>"
                                   placeholder="<?= $estudiante['Monto_Mensual'] ?>">
                            <div class="hint">Ajustar solo si hay cambio en el monto</div>
                        </div>

                        <div class="form-group">
                            <label>
                                Justificación de la Decisión <span class="required">*</span>
                            </label>
                            <textarea name="motivo_decision" 
                                      placeholder="Explica los motivos de tu decisión..."
                                      required></textarea>
                            <div class="hint">Este campo es obligatorio</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success" 
                            onclick="return confirm('¿Estás seguro de registrar esta evaluación? Esta acción no se puede deshacer.')">
                        <i class="fas fa-check"></i>
                        Registrar Evaluación
                    </button>
                </form>

                <div style="margin-top: 20px;">
                    <a href="evaluacion_anual.php?año=<?= $año_evaluacion ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>