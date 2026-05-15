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
    $_SESSION['mensaje'] = 'ID de estudiante no especificado';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: lista_solicitudes.php');
    exit;
}

$id_estudiante = intval($_GET['id']);

// Obtener información del estudiante
$sql = "SELECT e.*, ev.Id_Evaluacion, ev.Estado_Evaluacion
        FROM Estudiantes e
        LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
        WHERE e.Id_Estudiante = ?
        ORDER BY ev.Fecha_Evaluacion DESC
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    $_SESSION['mensaje'] = 'Estudiante no encontrado';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: lista_solicitudes.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo_rechazo = trim($_POST['motivo_rechazo']);
    
    if (empty($motivo_rechazo)) {
        $mensaje = 'Debe especificar el motivo del rechazo';
        $tipo_mensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Actualizar evaluación si existe
            if ($estudiante['Id_Evaluacion']) {
                $sql_eval = "UPDATE Evaluaciones_Socioeconomicas 
                            SET Estado_Evaluacion = 'Rechazado',
                                Motivo_Rechazo = ?,
                                Fecha_Decision = NOW(),
                                Id_Usuario_Evaluador = ?
                            WHERE Id_Evaluacion = ?";
                
                $stmt = $pdo->prepare($sql_eval);
                $stmt->execute([$motivo_rechazo, $_SESSION['user_id'], $estudiante['Id_Evaluacion']]);
            }
            
            // Actualizar estado del estudiante (opcional, según tu lógica)
            // Puedes mantener el estado como Activo pero con evaluación rechazada
            // o cambiar a un estado específico
            
            // Registrar en bitácora
            $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                            VALUES (?, ?, CURDATE(), ?)";
            $stmt = $pdo->prepare($sql_bitacora);
            $stmt->execute([
                $_SESSION['user_id'],
                "Rechazó solicitud de: " . $estudiante['Nombres_Apellidos'] . " (ID: $id_estudiante) - Motivo: " . $motivo_rechazo,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = 'Solicitud rechazada exitosamente';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: admin_detalle.php?id=' . $id_estudiante);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = 'Error al rechazar la solicitud: ' . $e->getMessage();
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
    <title>Rechazar Solicitud - <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .modal-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: #f8d7da;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .modal-icon i {
            font-size: 2.5em;
            color: #dc3545;
        }

        .modal-header h1 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .modal-header p {
            color: #666;
            font-size: 1em;
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

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
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
            font-weight: 600;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .warning-box h3 {
            color: #856404;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-box ul {
            color: #856404;
            padding-left: 30px;
            line-height: 1.8;
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
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .form-group .hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .form-actions {
            display: flex;
            gap: 15px;
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

        .btn-danger {
            background: #dc3545;
            color: white;
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

        .ejemplo-motivos {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .ejemplo-motivos h4 {
            color: #004085;
            margin-bottom: 10px;
            font-size: 0.9em;
        }

        .ejemplo-motivos ul {
            color: #004085;
            font-size: 0.85em;
            padding-left: 20px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .modal-container {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Rechazar Solicitud</h1>
            <p>Esta acción marcará la solicitud como rechazada</p>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?= $mensaje ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <div class="info-item">
                <span class="info-label">Estudiante:</span>
                <span class="info-value"><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Expediente:</span>
                <span class="info-value"><?= htmlspecialchars($estudiante['Numero_Expediente']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Edad:</span>
                <span class="info-value"><?= $estudiante['Edad'] ?> años</span>
            </div>
            <div class="info-item">
                <span class="info-label">Estado Actual:</span>
                <span class="info-value"><?= $estudiante['Estado_Estudiante'] ?></span>
            </div>
        </div>

        <div class="warning-box">
            <h3>
                <i class="fas fa-exclamation-triangle"></i>
                Consecuencias del rechazo:
            </h3>
            <ul>
                <li>La solicitud será marcada como rechazada en el sistema</li>
                <li>El estudiante no será elegible para recibir la beca</li>
                <li>Se notificará al estudiante sobre la decisión</li>
                <li>El registro permanecerá en el sistema para referencia</li>
                <li>Esta acción puede ser revertida posteriormente si es necesario</li>
            </ul>
        </div>

        <form method="POST" action="">
            <div class="ejemplo-motivos">
                <h4><i class="fas fa-lightbulb"></i> Ejemplos de motivos comunes:</h4>
                <ul>
                    <li>No cumple con los requisitos académicos mínimos</li>
                    <li>Situación económica no corresponde con los criterios de necesidad</li>
                    <li>Documentación incompleta o inconsistente</li>
                    <li>No asistió a la entrevista programada</li>
                    <li>Perfil no alineado con los objetivos del programa</li>
                </ul>
            </div>

            <div class="form-group">
                <label>
                    Motivo del Rechazo <span class="required">*</span>
                </label>
                <textarea name="motivo_rechazo" 
                          placeholder="Explique de manera clara y profesional el motivo del rechazo. Esta información será registrada en el expediente del estudiante."
                          required></textarea>
                <div class="hint">
                    Sea específico y profesional. Este motivo quedará registrado permanentemente.
                </div>
            </div>

            <div class="form-actions">
                <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está completamente seguro de rechazar esta solicitud?')">
                    <i class="fas fa-times-circle"></i> Confirmar Rechazo
                </button>
            </div>
        </form>
    </div>
</body>
</html>