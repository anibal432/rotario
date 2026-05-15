<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar ID de evaluación
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensaje'] = 'ID de evaluación no especificado';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: lista_solicitudes.php');
    exit;
}

$id_evaluacion = intval($_GET['id']);

// Obtener información de la evaluación y estudiante
$sql = "SELECT 
            ev.*,
            e.Id_Estudiante,
            e.Nombres_Apellidos,
            e.Numero_Expediente,
            e.Edad,
            e.Email,
            e.Telefono
        FROM Evaluaciones_Socioeconomicas ev
        INNER JOIN Estudiantes e ON ev.Id_Estudiante = e.Id_Estudiante
        WHERE ev.Id_Evaluacion = ?
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_evaluacion]);
$evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluacion) {
    $_SESSION['mensaje'] = 'Evaluación no encontrada';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: lista_solicitudes.php');
    exit;
}

// Verificar que esté pendiente
if ($evaluacion['Estado_Evaluacion'] !== 'Pendiente') {
    $_SESSION['mensaje'] = 'Esta evaluación ya fue procesada';
    $_SESSION['tipo_mensaje'] = 'warning';
    header('Location: admin_detalle.php?id=' . $evaluacion['Id_Estudiante']);
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision'];
    $comentarios = trim($_POST['comentarios_evaluacion']);
    
    if (empty($comentarios) && $decision === 'Rechazado') {
        $mensaje = 'Debe proporcionar comentarios al rechazar una evaluación';
        $tipo_mensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($decision === 'Aprobado') {
                // Aprobar evaluación
                $sql_eval = "UPDATE Evaluaciones_Socioeconomicas 
                            SET Estado_Evaluacion = 'Aprobado',
                                Comentarios_Evaluacion = ?,
                                Fecha_Revision = NOW(),
                                Id_Usuario_Revisor = ?
                            WHERE Id_Evaluacion = ?";
                
                $stmt = $pdo->prepare($sql_eval);
                $stmt->execute([$comentarios, $_SESSION['user_id'], $id_evaluacion]);
                
                $actividad = "Aprobó evaluación de: " . $evaluacion['Nombres_Apellidos'];
                $mensaje_sesion = 'Evaluación aprobada exitosamente. Ahora puede proceder a otorgar la beca.';
                
            } else {
                // Rechazar evaluación
                $sql_eval = "UPDATE Evaluaciones_Socioeconomicas 
                            SET Estado_Evaluacion = 'Rechazado',
                                Motivo_Rechazo = ?,
                                Comentarios_Evaluacion = ?,
                                Fecha_Decision = NOW(),
                                Fecha_Revision = NOW(),
                                Id_Usuario_Revisor = ?,
                                Id_Usuario_Evaluador = ?
                            WHERE Id_Evaluacion = ?";
                
                $stmt = $pdo->prepare($sql_eval);
                $stmt->execute([
                    $comentarios,
                    $comentarios,
                    $_SESSION['user_id'],
                    $_SESSION['user_id'],
                    $id_evaluacion
                ]);
                
                $actividad = "Rechazó evaluación de: " . $evaluacion['Nombres_Apellidos'] . " - Motivo: " . substr($comentarios, 0, 100);
                $mensaje_sesion = 'Evaluación rechazada. Se ha notificado la decisión.';
            }
            
            // Registrar en bitácora
            $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                            VALUES (?, ?, CURDATE(), ?)";
            $stmt = $pdo->prepare($sql_bitacora);
            $stmt->execute([
                $_SESSION['user_id'],
                $actividad,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = $mensaje_sesion;
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: admin_detalle.php?id=' . $evaluacion['Id_Estudiante']);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = 'Error al procesar la evaluación: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Evaluación - <?= htmlspecialchars($evaluacion['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .breadcrumb {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .info-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }

        .info-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            padding: 10px 0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.85em;
            display: block;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
            font-size: 1em;
        }

        .decision-section {
            margin-bottom: 30px;
        }

        .decision-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .decision-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .decision-card {
            position: relative;
        }

        .decision-card input[type="radio"] {
            display: none;
        }

        .decision-label {
            display: block;
            padding: 25px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .decision-card input[type="radio"]:checked + .decision-label {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .decision-card.aprobar input[type="radio"]:checked + .decision-label {
            border-color: #28a745;
            background: #d4edda;
        }

        .decision-card.rechazar input[type="radio"]:checked + .decision-label {
            border-color: #dc3545;
            background: #f8d7da;
        }

        .decision-label:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .decision-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .aprobar .decision-icon {
            color: #28a745;
        }

        .rechazar .decision-icon {
            color: #dc3545;
        }

        .decision-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .decision-description {
            font-size: 0.9em;
            color: #666;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 1em;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            resize: vertical;
            min-height: 150px;
            transition: all 0.3s ease;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group .hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .warning-box h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-box p {
            color: #856404;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #e0e0e0;
            justify-content: center;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }

            .decision-options {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
                <a href="lista_solicitudes.php">Solicitudes</a> /
                <a href="admin_detalle.php?id=<?= $evaluacion['Id_Estudiante'] ?>">Detalle</a> /
                Procesar Evaluación
            </div>
            <h1><i class="fas fa-clipboard-check"></i> Procesar Evaluación Socioeconómica</h1>
        </div>

        <div class="form-container">
            <?php if ($mensaje): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $mensaje ?>
            </div>
            <?php endif; ?>

            <!-- Información del Estudiante -->
            <div class="info-section">
                <h3><i class="fas fa-user"></i> Información del Estudiante</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nombre Completo</span>
                        <span class="info-value"><?= htmlspecialchars($evaluacion['Nombres_Apellidos']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Expediente</span>
                        <span class="info-value"><?= htmlspecialchars($evaluacion['Numero_Expediente']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Edad</span>
                        <span class="info-value"><?= $evaluacion['Edad'] ?> años</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Meta Profesional</span>
                        <span class="info-value"><?= htmlspecialchars($evaluacion['Meta_Profesional'] ?? 'No especificada') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Evaluación</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($evaluacion['Fecha_Evaluacion'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="warning-box">
                <h4><i class="fas fa-info-circle"></i> Importante</h4>
                <p>
                    Revise cuidadosamente toda la información de la evaluación socioeconómica antes de tomar una decisión.
                    Esta acción quedará registrada en el sistema y afectará la elegibilidad del estudiante para la beca.
                </p>
            </div>

            <form method="POST" action="" onsubmit="return confirmarDecision()">
                <!-- Sección de Decisión -->
                <div class="decision-section">
                    <h3>Decisión de la Evaluación</h3>
                    
                    <div class="decision-options">
                        <div class="decision-card aprobar">
                            <input type="radio" name="decision" id="aprobar" value="Aprobado" required>
                            <label for="aprobar" class="decision-label">
                                <div class="decision-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="decision-title">Aprobar</div>
                                <div class="decision-description">
                                    El estudiante cumple con los requisitos y puede proceder a recibir la beca
                                </div>
                            </label>
                        </div>

                        <div class="decision-card rechazar">
                            <input type="radio" name="decision" id="rechazar" value="Rechazado" required>
                            <label for="rechazar" class="decision-label">
                                <div class="decision-icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="decision-title">Rechazar</div>
                                <div class="decision-description">
                                    El estudiante no cumple con los criterios establecidos
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Comentarios -->
                <div class="form-group">
                    <label>
                        Comentarios y Observaciones <span class="required">*</span>
                    </label>
                    <textarea name="comentarios_evaluacion" 
                              placeholder="Ingrese sus observaciones, comentarios sobre la evaluación y justificación de la decisión..."
                              required></textarea>
                    <div class="hint">
                        <strong>Si aprueba:</strong> Puede incluir recomendaciones o condiciones.<br>
                        <strong>Si rechaza:</strong> Debe explicar claramente los motivos del rechazo.
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="form-actions">
                    <a href="admin_detalle.php?id=<?= $evaluacion['Id_Estudiante'] ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Procesar Evaluación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmarDecision() {
            const decision = document.querySelector('input[name="decision"]:checked').value;
            const comentarios = document.querySelector('textarea[name="comentarios_evaluacion"]').value.trim();
            
            if (!comentarios) {
                alert('Debe proporcionar comentarios sobre su decisión.');
                return false;
            }
            
            if (decision === 'Rechazado') {
                return confirm('¿Está seguro de RECHAZAR esta evaluación?\n\nEsta acción marcará al estudiante como no elegible para la beca.');
            } else {
                return confirm('¿Está seguro de APROBAR esta evaluación?\n\nEl estudiante quedará elegible para recibir la beca.');
            }
        }
        
        // Actualizar el placeholder del textarea según la decisión
        document.querySelectorAll('input[name="decision"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const textarea = document.querySelector('textarea[name="comentarios_evaluacion"]');
                if (this.value === 'Aprobado') {
                    textarea.placeholder = 'Ejemplo: El estudiante cumple con todos los requisitos. Se recomienda seguimiento académico mensual...';
                } else {
                    textarea.placeholder = 'Ejemplo: No cumple con el requisito de promedio mínimo. La situación económica familiar no corresponde con los criterios establecidos...';
                }
            });
        });
    </script>
</body>
</html>