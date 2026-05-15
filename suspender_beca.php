<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar ID de la beca
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensaje'] = 'ID de beca no especificado';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: estudiantes_becados.php');
    exit;
}

$id_beca = $_GET['id'];

// Obtener información de la beca
$sql = "SELECT 
            b.*,
            e.Id_Estudiante,
            e.Nombres_Apellidos,
            e.Numero_Expediente
        FROM Becas_Otorgadas b
        INNER JOIN Estudiantes e ON b.Id_Estudiante = e.Id_Estudiante
        WHERE b.Id_Beca = ?
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_beca]);
$beca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$beca) {
    $_SESSION['mensaje'] = 'Beca no encontrada';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: estudiantes_becados.php');
    exit;
}

// Verificar que la beca esté activa
if ($beca['Estado_Beca'] !== 'Activa') {
    $_SESSION['mensaje'] = 'Solo se pueden suspender becas activas';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: detalle_becado.php?id=' . $beca['Id_Estudiante']);
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de suspensión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = trim($_POST['motivo_suspension']);
    
    if (empty($motivo)) {
        $mensaje = 'Debe especificar el motivo de suspensión';
        $tipo_mensaje = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Suspender la beca
            $sql_suspender = "UPDATE Becas_Otorgadas 
                             SET Estado_Beca = 'Suspendida',
                                 Motivo_Suspension = ?
                             WHERE Id_Beca = ?";
            
            $stmt = $pdo->prepare($sql_suspender);
            $stmt->execute([$motivo, $id_beca]);
            
            // Registrar en bitácora
            $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                            VALUES (?, ?, CURDATE(), ?)";
            $stmt = $pdo->prepare($sql_bitacora);
            $stmt->execute([
                $_SESSION['user_id'],
                "Suspendió beca de: " . $beca['Nombres_Apellidos'] . " - Motivo: " . $motivo,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = 'Beca suspendida exitosamente';
            $_SESSION['tipo_mensaje'] = 'success';
            header('Location: detalle_becado.php?id=' . $beca['Id_Estudiante']);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = 'Error al suspender la beca: ' . $e->getMessage();
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
    <title>Suspender Beca - <?= htmlspecialchars($beca['Nombres_Apellidos']) ?></title>
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
        }

        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: #fff3cd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .modal-icon i {
            font-size: 2.5em;
            color: #ffc107;
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
            border-left: 4px solid #004b87;
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
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
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
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-box ul {
            color: #856404;
            padding-left: 30px;
            line-height: 1.8;
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
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>Suspender Beca</h1>
            <p>¿Está seguro de suspender esta beca?</p>
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
                <span class="info-value"><?= htmlspecialchars($beca['Nombres_Apellidos']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Expediente:</span>
                <span class="info-value"><?= htmlspecialchars($beca['Numero_Expediente']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tipo de Beca:</span>
                <span class="info-value"><?= htmlspecialchars($beca['Tipo_Beca']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Monto Mensual:</span>
                <span class="info-value">Q<?= number_format($beca['Monto_Mensual'], 2) ?></span>
            </div>
        </div>

        <div class="warning-box">
            <h3>
                <i class="fas fa-info-circle"></i>
                Consecuencias de la suspensión:
            </h3>
            <ul>
                <li>Los pagos mensuales se detendrán temporalmente</li>
                <li>El estudiante conserva su registro en el sistema</li>
                <li>La beca puede ser reactivada posteriormente</li>
                <li>Se mantendrá el historial académico y de pagos</li>
            </ul>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label>
                    Motivo de Suspensión <span class="required">*</span>
                </label>
                <textarea name="motivo_suspension" 
                          placeholder="Ejemplo: Promedio bajo, incumplimiento de compromisos, cambio de situación económica, etc."
                          required></textarea>
            </div>

            <div class="form-actions">
                <a href="detalle_becado.php?id=<?= $beca['Id_Estudiante'] ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-pause-circle"></i> Suspender Beca
                </button>
            </div>
        </form>
    </div>
</body>
</html>