<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$tipo_mensaje = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: lista_solicitudes.php');
    exit;
}

$id_estudiante = intval($_GET['id']);
$username = $_SESSION['username'] ?? 'Usuario';
$user_id = $_SESSION['user_id'];

// Obtener información del estudiante y evaluación
try {
    $sql = "SELECT e.*, ev.Id_Evaluacion 
            FROM Estudiantes e
            LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
            WHERE e.Id_Estudiante = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$estudiante) {
        header('Location: lista_solicitudes.php');
        exit;
    }
    
    // Verificar si ya tiene beca
    $sql = "SELECT * FROM Becas_Otorgadas WHERE Id_Estudiante = ? AND Estado_Beca = 'Activa'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_estudiante]);
    $beca_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($beca_existente) {
        header('Location: admin_detalle.php?id=' . $id_estudiante . '&error=Ya tiene una beca activa');
        exit;
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Procesar la aprobación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $tipo_beca = $_POST['tipo_beca'];
        $monto_mensual = floatval($_POST['monto_mensual']);
        $promedio_minimo = floatval($_POST['promedio_minimo']);
        $fecha_inicio = $_POST['fecha_inicio'];
        
        // Actualizar estado del estudiante
        $sql = "UPDATE Estudiantes 
                SET Estado_Estudiante = 'Activo', 
                    Estado_Beca = 'Activo',
                    Fecha_Inicio_Beca = ?
                WHERE Id_Estudiante = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fecha_inicio, $id_estudiante]);
        
        // Actualizar evaluación
        if ($estudiante['Id_Evaluacion']) {
            $sql = "UPDATE Evaluaciones_Socioeconomicas 
                    SET Estado_Evaluacion = 'Aprobado',
                        Fecha_Decision = NOW()
                    WHERE Id_Evaluacion = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$estudiante['Id_Evaluacion']]);
        }
        
        // Crear beca
        $sql = "INSERT INTO Becas_Otorgadas 
                (Id_Estudiante, Id_Evaluacion, Tipo_Beca, Monto_Mensual, Fecha_Inicio, Estado_Beca, Promedio_Minimo)
                VALUES (?, ?, ?, ?, ?, 'Activa', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id_estudiante,
            $estudiante['Id_Evaluacion'],
            $tipo_beca,
            $monto_mensual,
            $fecha_inicio,
            $promedio_minimo
        ]);
        
        $id_beca = $pdo->lastInsertId();
        
        // Registrar en bitácora
        $sql = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                VALUES (?, ?, CURDATE(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            "Aprobó beca para {$estudiante['Nombres_Apellidos']} (ID: $id_estudiante). Monto: Q$monto_mensual",
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
        
        // Enviar correo (opcional)
        if (isset($_POST['enviar_correo']) && $_POST['enviar_correo'] === '1' && file_exists(__DIR__ . '/config_email.php')) {
            require_once __DIR__ . '/config_email.php';
            
            $mensaje_html = generarCorreoAprobacion(
                $estudiante['Nombres_Apellidos'],
                $estudiante['Numero_Expediente'],
                $tipo_beca,
                $monto_mensual,
                $fecha_inicio
            );
            
            enviarCorreo(
                $estudiante['Email'],
                "¡Felicidades! Tu Beca ha sido Aprobada",
                $mensaje_html,
                $estudiante['Nombres_Apellidos']
            );
        }
        
        $pdo->commit();
        
        // Redirigir a imprimir carta de compromiso
        header("Location: imprimir_carta_compromiso.php?id=$id_estudiante&id_beca=$id_beca");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al aprobar beca: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

function generarCorreoAprobacion($nombre, $expediente, $tipo_beca, $monto, $fecha_inicio) {
    $monto_format = number_format($monto, 2);
    $fecha_format = date('d/m/Y', strtotime($fecha_inicio));
    
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 ¡Felicidades!</h1>
            <p>Tu beca ha sido aprobada</p>
        </div>
        <div class="content">
            <p>Estimado/a <strong>$nombre</strong>,</p>
            <p>Nos complace informarte que tu solicitud de beca ha sido <strong>APROBADA</strong>.</p>
            <p><strong>Detalles de tu beca:</strong></p>
            <ul>
                <li>Expediente: <strong>$expediente</strong></li>
                <li>Tipo de Beca: <strong>$tipo_beca</strong></li>
                <li>Monto Mensual: <strong>Q $monto_format</strong></li>
                <li>Fecha de Inicio: <strong>$fecha_format</strong></li>
            </ul>
            <p>Próximamente te contactaremos para la firma del compromiso de beca.</p>
            <p>¡Felicidades y mucho éxito en tus estudios!</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Club Rotario Coatepeque Colomba</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobar Beca - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px 30px; }
        .header-content { max-width: 800px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .info-box { background: #d4edda; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #28a745; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 15px; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; }
        .btn { padding: 12px 30px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 15px; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .help-text { font-size: 13px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-check-circle"></i> Aprobar Beca</h1>
            <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="info-box">
                <h3 style="margin-bottom: 10px;"><i class="fas fa-user-graduate"></i> Estudiante</h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></p>
                <p><strong>Expediente:</strong> <?= htmlspecialchars($estudiante['Numero_Expediente']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($estudiante['Email']) ?></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Tipo de Beca *</label>
                    <input type="text" name="tipo_beca" value="Beca Educativa Completa" required>
                    <div class="help-text">Ejemplo: Beca Educativa Completa, Beca Parcial, etc.</div>
                </div>
                
                <div class="form-group">
                    <label>Monto Mensual (Q) *</label>
                    <input type="number" name="monto_mensual" step="0.01" value="500.00" required>
                    <div class="help-text">Monto que se pagará mensualmente</div>
                </div>
                
                <div class="form-group">
                    <label>Promedio Mínimo Requerido *</label>
                    <input type="number" name="promedio_minimo" step="0.01" value="75.00" required>
                    <div class="help-text">Promedio mínimo que debe mantener el estudiante</div>
                </div>
                
                <div class="form-group">
                    <label>Fecha de Inicio *</label>
                    <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="enviar_correo" value="1" id="enviar_correo" checked>
                    <label for="enviar_correo" style="margin: 0; font-weight: normal;">
                        Enviar notificación por correo electrónico
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Aprobar y Generar Carta de Compromiso
                    </button>
                    <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>