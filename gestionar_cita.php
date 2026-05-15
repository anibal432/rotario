<?php
/**
 * gestionar_cita.php
 * Permite programar, reprogramar, completar o cancelar citas de entrevista
 * Incluye envío de correos electrónicos para reprogramación y cancelación
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Verificar ID de estudiante
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensaje'] = 'ID de estudiante no especificado';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: lista_solicitudes.php');
    exit;
}

$id_estudiante = intval($_GET['id']);

// Obtener información del estudiante
$sql_estudiante = "SELECT * FROM Estudiantes WHERE Id_Estudiante = ? LIMIT 1";
$stmt = $pdo->prepare($sql_estudiante);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    $_SESSION['mensaje'] = 'Estudiante no encontrado';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: lista_solicitudes.php');
    exit;
}

// Obtener la cita más reciente del estudiante (si existe)
$sql_cita = "SELECT * FROM Citas_Entrevista 
             WHERE Id_Estudiante = ? 
             ORDER BY Fecha_Cita DESC, Id_Cita DESC 
             LIMIT 1";
$stmt = $pdo->prepare($sql_cita);
$stmt->execute([$id_estudiante]);
$cita_existente = $stmt->fetch(PDO::FETCH_ASSOC);

$es_nueva_cita = !$cita_existente;
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        switch ($accion) {
            case 'programar':
                // Crear una nueva cita
                $fecha = $_POST['fecha_cita'];
                $hora = $_POST['hora_cita'];
                $lugar = trim($_POST['lugar_cita']);
                $observaciones = trim($_POST['observaciones'] ?? '');
                
                if (empty($fecha) || empty($hora) || empty($lugar)) {
                    throw new Exception('Fecha, hora y lugar son requeridos');
                }
                
                // Validar que la fecha no sea anterior a hoy
                if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
                    throw new Exception('No se puede programar una cita en el pasado');
                }
                
                $sql_insert = "INSERT INTO Citas_Entrevista 
                              (Id_Estudiante, Fecha_Cita, Hora_Cita, Lugar_Entrevista, 
                               Estado_Cita, Observaciones, Fecha_Creacion) 
                              VALUES (?, ?, ?, ?, 'Programada', ?, NOW())";
                
                $stmt = $pdo->prepare($sql_insert);
                $stmt->execute([
                    $id_estudiante,
                    $fecha,
                    $hora,
                    $lugar,
                    $observaciones
                ]);
                
                $actividad = "Programó cita para {$estudiante['Nombres_Apellidos']} el {$fecha} a las {$hora}";
                $mensaje_sesion = 'Cita programada exitosamente';
                break;
                
            case 'reprogramar':
                if (!$cita_existente) {
                    throw new Exception('No hay una cita existente para reprogramar');
                }
                
                $nueva_fecha = $_POST['nueva_fecha'];
                $nueva_hora = $_POST['nueva_hora'];
                $motivo_reprogramacion = trim($_POST['motivo_reprogramacion']);
                
                if (empty($nueva_fecha) || empty($nueva_hora)) {
                    throw new Exception('Fecha y hora son requeridas');
                }
                
                // Validar que la fecha no sea anterior a hoy
                if (strtotime($nueva_fecha) < strtotime(date('Y-m-d'))) {
                    throw new Exception('No se puede programar una cita en el pasado');
                }
                
                // Guardar información de la cita anterior
                $fecha_anterior = $cita_existente['Fecha_Cita'];
                $hora_anterior = $cita_existente['Hora_Cita'];
                
                $sql_update = "UPDATE Citas_Entrevista 
                              SET Fecha_Cita = ?,
                                  Hora_Cita = ?,
                                  Estado_Cita = 'Reprogramada',
                                  Observaciones = CONCAT(
                                      COALESCE(Observaciones, ''),
                                      '\n\n--- REPROGRAMACIÓN ---\n',
                                      'Fecha anterior: ', ?, ' a las ', ?, '\n',
                                      'Nueva fecha: ', ?, ' a las ', ?, '\n',
                                      'Motivo: ', ?, '\n',
                                      'Reprogramado por: ', ?, '\n',
                                      'Fecha de cambio: ', NOW()
                                  ),
                                  Fecha_Modificacion = NOW()
                              WHERE Id_Cita = ?";
                
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute([
                    $nueva_fecha,
                    $nueva_hora,
                    $fecha_anterior,
                    $hora_anterior,
                    $nueva_fecha,
                    $nueva_hora,
                    $motivo_reprogramacion,
                    $_SESSION['username'] ?? 'Usuario',
                    $cita_existente['Id_Cita']
                ]);
                
                $actividad = "Reprogramó cita de {$estudiante['Nombres_Apellidos']} del {$fecha_anterior} {$hora_anterior} al {$nueva_fecha} {$nueva_hora}";
                
                // ENVÍO DE CORREO - REPROGRAMACIÓN
                $resultado_correo = enviarCorreoReprogramacion($estudiante, $cita_existente, $nueva_fecha, $nueva_hora, $motivo_reprogramacion);
                
                if ($resultado_correo['success']) {
                    $mensaje_sesion = 'Cita reprogramada exitosamente y correo de notificación enviado';
                } else {
                    $mensaje_sesion = 'Cita reprogramada exitosamente, pero el correo no pudo enviarse: ' . $resultado_correo['error'];
                }
                break;
                
            case 'completar':
                if (!$cita_existente) {
                    throw new Exception('No hay una cita existente para completar');
                }
                
                $observaciones = trim($_POST['observaciones_completar']);
                
                $sql_update = "UPDATE Citas_Entrevista 
                              SET Estado_Cita = 'Completada',
                                  Observaciones = CONCAT(
                                      COALESCE(Observaciones, ''),
                                      '\n\n--- CITA COMPLETADA ---\n',
                                      'Fecha de realización: ', NOW(), '\n',
                                      'Observaciones: ', ?, '\n',
                                      'Completado por: ', ?
                                  ),
                                  Fecha_Modificacion = NOW()
                              WHERE Id_Cita = ?";
                
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute([
                    $observaciones,
                    $_SESSION['username'] ?? 'Usuario',
                    $cita_existente['Id_Cita']
                ]);
                
                $actividad = "Completó cita de {$estudiante['Nombres_Apellidos']}";
                $mensaje_sesion = 'Cita marcada como completada';
                break;
                
            case 'cancelar':
                if (!$cita_existente) {
                    throw new Exception('No hay una cita existente para cancelar');
                }
                
                $motivo_cancelacion = trim($_POST['motivo_cancelacion']);
                
                if (empty($motivo_cancelacion)) {
                    throw new Exception('Debe especificar el motivo de cancelación');
                }
                
                $sql_update = "UPDATE Citas_Entrevista 
                              SET Estado_Cita = 'Cancelada',
                                  Observaciones = CONCAT(
                                      COALESCE(Observaciones, ''),
                                      '\n\n--- CITA CANCELADA ---\n',
                                      'Fecha de cancelación: ', NOW(), '\n',
                                      'Motivo: ', ?, '\n',
                                      'Cancelado por: ', ?
                                  ),
                                  Fecha_Modificacion = NOW()
                              WHERE Id_Cita = ?";
                
                $stmt = $pdo->prepare($sql_update);
                $stmt->execute([
                    $motivo_cancelacion,
                    $_SESSION['username'] ?? 'Usuario',
                    $cita_existente['Id_Cita']
                ]);
                
                $actividad = "Canceló cita de {$estudiante['Nombres_Apellidos']} - Motivo: {$motivo_cancelacion}";
                
                // ENVÍO DE CORREO - CANCELACIÓN
                $resultado_correo = enviarCorreoCancelacion($estudiante, $cita_existente, $motivo_cancelacion);
                
                if ($resultado_correo['success']) {
                    $mensaje_sesion = 'Cita cancelada exitosamente y correo de notificación enviado';
                } else {
                    $mensaje_sesion = 'Cita cancelada exitosamente, pero el correo no pudo enviarse: ' . $resultado_correo['error'];
                }
                break;
                
            default:
                throw new Exception('Acción no válida');
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
        header('Location: admin_detalle.php?id=' . $id_estudiante);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['mensaje'] = 'Error: ' . $e->getMessage();
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: gestionar_cita.php?id=' . $id_estudiante);
        exit;
    }
}

/**
 * FUNCIONES DE ENVÍO DE CORREO INTEGRADAS
 */

/**
 * Envía correo de notificación de reprogramación
 */
function enviarCorreoReprogramacion($estudiante, $cita_anterior, $nueva_fecha, $nueva_hora, $motivo) {
    // Configuración SMTP - Gmail
    $smtp_config = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls', // o 'ssl' si usas puerto 465
            'username' => 'cmvitalis01@gmail.com',
            'password' => 'gcly petu tsfz ppdj',
            'from_email' => 'cmvitalis01@gmail.com',
            'from_name' => 'Club Rotario Coatepeque Colomba'
        ];
        
    
    $to = $estudiante['Email'];
    $nombre = $estudiante['Nombres_Apellidos'];
    
    // Formatear fechas
    $fecha_anterior_formateada = date('d/m/Y', strtotime($cita_anterior['Fecha_Cita']));
    $hora_anterior_formateada = date('g:i A', strtotime($cita_anterior['Hora_Cita']));
    $fecha_nueva_formateada = date('d/m/Y', strtotime($nueva_fecha));
    $hora_nueva_formateada = date('g:i A', strtotime($nueva_hora));
    
    $asunto = "Reprogramación de Cita - Club Rotario";
    
    // Generar mensaje HTML
    $mensaje = generarPlantillaReprogramacion(
        $nombre, 
        $fecha_anterior_formateada, 
        $hora_anterior_formateada,
        $fecha_nueva_formateada,
        $hora_nueva_formateada,
        $motivo,
        $cita_anterior['Lugar_Entrevista']
    );
    
    // Enviar correo
    return enviarCorreoSMTP($smtp_config, $to, $asunto, $mensaje, $nombre);
}

/**
 * Envía correo de notificación de cancelación
 */
function enviarCorreoCancelacion($estudiante, $cita_cancelada, $motivo) {
    // Configuración SMTP - Gmail
    $smtp_config = [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'cmvitalis01@gmail.com',
        'password' => 'gcly petu tsfz ppdj',
        'from_email' => 'cmvitalis01@gmail.com',
        'from_name' => 'Club Rotario Coatepeque Colomba'
    ];
    
    // Preparar datos para el correo
    $to = $estudiante['Email'];
    $nombre = $estudiante['Nombres_Apellidos'];
    
    // Formatear fecha y hora de la cita cancelada
    $fecha_cancelada = date('d/m/Y', strtotime($cita_cancelada['Fecha_Cita']));
    $hora_cancelada = date('g:i A', strtotime($cita_cancelada['Hora_Cita']));
    
    $asunto = "Cancelación de Cita - Club Rotario";
    
    // Generar mensaje HTML
    $mensaje = generarPlantillaCancelacion($nombre, $fecha_cancelada, $hora_cancelada, $motivo);
    
    // Enviar correo
    return enviarCorreoSMTP($smtp_config, $to, $asunto, $mensaje, $nombre);
}

/**
 * Función para enviar correo usando SMTP
 */
function enviarCorreoSMTP($config, $to, $subject, $message, $nombre_destinatario = '') {
    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido: $to");
        }
        
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $nombre_destinatario);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        
        return ['success' => true, 'message' => 'Correo enviado exitosamente'];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => "Error al enviar correo: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Genera plantilla HTML para reprogramación de cita
 */
function generarPlantillaReprogramacion($nombre, $fecha_anterior, $hora_anterior, $fecha_nueva, $hora_nueva, $motivo, $lugar) {
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f7fa; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .change-box { background: #ffeaa7; padding: 25px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #fdcb6e; }
        .new-date-box { background: #d4edda; padding: 25px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #28a745; text-align: center; }
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
        h1 { margin: 0; font-size: 24px; }
        h2 { color: #e17055; font-size: 20px; margin-top: 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
        .date-change { display: flex; justify-content: space-between; margin: 15px 0; }
        .date-old, .date-new { flex: 1; text-align: center; padding: 15px; }
        .date-old { background: #f8d7da; border-radius: 5px; }
        .date-new { background: #d4edda; border-radius: 5px; }
        .arrow { flex: 0 0 50px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Cita Reprogramada</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Club Rotario Coatepeque Colomba</p>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>$nombre</strong>,</p>
            
            <p>Queremos informarle que su cita de entrevista para la solicitud de beca ha sido reprogramada.</p>
            
            <div class="change-box">
                <h2>📅 Cambio de Fecha y Hora</h2>
                
                <div class="date-change">
                    <div class="date-old">
                        <strong>Fecha Anterior</strong><br>
                        $fecha_anterior<br>
                        $hora_anterior
                    </div>
                    <div class="arrow">→</div>
                    <div class="date-new">
                        <strong>Nueva Fecha</strong><br>
                        $fecha_nueva<br>
                        $hora_nueva
                    </div>
                </div>
                
                <p><strong>Motivo del cambio:</strong><br>
                $motivo</p>
            </div>
            
            <div class="new-date-box">
                <h2>✅ Su Nueva Cita</h2>
                <p style="font-size: 18px; margin: 10px 0;">
                    <strong>📅 $fecha_nueva</strong><br>
                    <strong>🕐 $hora_nueva</strong>
                </p>
                <p style="margin: 10px 0;">
                    <strong>📍 $lugar</strong>
                </p>
            </div>
            
            <div class="info-box">
                <h3>📝 Recordatorio de Documentos:</h3>
                <ul>
                    <li>Boleta de calificaciones reciente (original y copia)</li>
                    <li>Certificado de nacimiento (original y copia)</li>
                    <li>DPI estudiante y encargado (original y copia)</li>
                    <li>Comprobante de ingresos familiares</li>
                    <li>Recibos de servicios básicos (luz, agua)</li>
                    <li>2 fotografías tamaño cédula</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 25px 0;">
                <p><strong>¿Tienes preguntas o necesitas ajustar la nueva fecha?</strong></p>
                <p style="margin: 5px 0;">📧 becas@rotariocoatepeque.org</p>
                <p style="margin: 5px 0;">📱 +502 1234-5678</p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">
                    Horario de atención: Lunes a Viernes 8:00 AM - 5:00 PM
                </p>
            </div>
            
            <p>Lamentamos las molestias y esperamos verle en la nueva fecha asignada.</p>
        </div>
        
        <div class="footer">
            <p style="margin: 5px 0;">&copy; 2025 Club Rotario Coatepeque Colomba</p>
            <p style="font-size: 12px; margin: 5px 0; color: #999;">
                Este es un correo automático, por favor no responder directamente a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Genera plantilla HTML para cancelación de cita
 */
function generarPlantillaCancelacion($nombre, $fecha_cancelada, $hora_cancelada, $motivo) {
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f7fa; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .cancellation-box { background: #f8d7da; padding: 25px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #dc3545; text-align: center; }
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .next-steps { background: #d1ecf1; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #17a2b8; }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
        h1 { margin: 0; font-size: 24px; }
        h2 { color: #dc3545; font-size: 20px; margin-top: 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
        .contact-info { text-align: center; margin: 25px 0; padding: 20px; background: white; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ Cita Cancelada</h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Club Rotario Coatepeque Colomba</p>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>$nombre</strong>,</p>
            
            <p>Lamentamos informarle que su cita de entrevista para la solicitud de beca ha sido cancelada.</p>
            
            <div class="cancellation-box">
                <h2>🗓️ Cita Cancelada</h2>
                <p style="font-size: 18px; margin: 10px 0;">
                    <strong>📅 $fecha_cancelada</strong><br>
                    <strong>🕐 $hora_cancelada</strong>
                </p>
                <p style="margin: 15px 0;">
                    <strong>Motivo de cancelación:</strong><br>
                    $motivo
                </p>
            </div>
            
            <div class="next-steps">
                <h3>🔄 ¿Qué sigue?</h3>
                <p>Si desea volver a programar una cita o tiene preguntas sobre el estado de su solicitud, por favor contáctenos:</p>
                
                <div class="contact-info">
                    <p><strong>📧 becas@rotariocoatepeque.org</strong></p>
                    <p><strong>📱 +502 1234-5678</strong></p>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        Horario de atención: Lunes a Viernes 8:00 AM - 5:00 PM
                    </p>
                </div>
            </div>
            
            <div class="info-box">
                <h3>💡 Información Adicional</h3>
                <p>Su solicitud de beca permanece en nuestro sistema. Si la cancelación fue por un motivo que no afecta su elegibilidad, puede solicitar una nueva cita cuando las circunstancias lo permitan.</p>
                
                <p>Si tiene dudas sobre los motivos de la cancelación, no dude en contactarnos para obtener más información.</p>
            </div>
            
            <p>Agradecemos su comprensión y le deseamos éxito en sus estudios.</p>
            
            <p>Atentamente,<br>
            <strong>Comité de Becas</strong><br>
            Club Rotario Coatepeque Colomba</p>
        </div>
        
        <div class="footer">
            <p style="margin: 5px 0;">&copy; 2025 Club Rotario Coatepeque Colomba</p>
            <p style="font-size: 12px; margin: 5px 0; color: #999;">
                Este es un correo automático, por favor no responder directamente a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

// Fecha mínima para programación (hoy)
$fecha_minima = date('Y-m-d');

// Verificar si hay mensajes de sesión para mostrar con SweetAlert2
$mostrar_alerta = false;
$alerta_tipo = '';
$alerta_mensaje = '';

if (isset($_SESSION['mensaje']) && !empty($_SESSION['mensaje'])) {
    $mostrar_alerta = true;
    $alerta_tipo = $_SESSION['tipo_mensaje'] ?? 'info';
    $alerta_mensaje = $_SESSION['mensaje'];
    
    // Limpiar la sesión después de obtener los valores
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}
?>

<!-- EL RESTO DEL CÓDIGO HTML PERMANECE IGUAL -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $es_nueva_cita ? 'Programar' : 'Gestionar' ?> Cita - <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Los estilos CSS permanecen exactamente iguales */
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

        .info-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .info-box h3 {
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

        .cita-actual {
            background: linear-gradient(135deg, #e7f3ff 0%, #f3e7ff 100%);
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #667eea;
            margin-bottom: 25px;
        }

        .cita-actual .fecha {
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .badge-estado {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
        }

        .badge-programada {
            background: #cce5ff;
            color: #004085;
        }

        .badge-reprogramada {
            background: #ffeaa7;
            color: #d63031;
        }

        .badge-completada {
            background: #d4edda;
            color: #155724;
        }

        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
        }

        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }

        .tab {
            flex: 1;
            padding: 18px;
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
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 1em;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .warning-note {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #856404;
        }

        .success-note {
            background: #d1ecf1;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #0c5460;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
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
                <a href="admin_detalle.php?id=<?= $id_estudiante ?>">Detalle</a> /
                <?= $es_nueva_cita ? 'Programar' : 'Gestionar' ?> Cita
            </div>
            <h1>
                <i class="fas fa-calendar-alt"></i> 
                <?= $es_nueva_cita ? 'Programar Nueva Cita' : 'Gestionar Cita de Entrevista' ?>
            </h1>
        </div>

        <!-- Información del Estudiante -->
        <div class="info-box">
            <h3><i class="fas fa-user"></i> Información del Estudiante</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nombre Completo</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Expediente</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['Numero_Expediente'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['Email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?= htmlspecialchars($estudiante['Telefono']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($cita_existente): ?>
        <!-- Cita Actual (si existe) -->
        <div class="cita-actual">
            <div class="fecha">
                <i class="fas fa-calendar-alt"></i> 
                <?= date('d/m/Y', strtotime($cita_existente['Fecha_Cita'])) ?> 
                a las <?= date('H:i', strtotime($cita_existente['Hora_Cita'])) ?>
            </div>
            <p><strong>Lugar:</strong> <?= htmlspecialchars($cita_existente['Lugar_Entrevista']) ?></p>
            <p>
                <strong>Estado:</strong> 
                <span class="badge-estado badge-<?= strtolower($cita_existente['Estado_Cita']) ?>">
                    <?= htmlspecialchars($cita_existente['Estado_Cita']) ?>
                </span>
            </p>
            <?php if ($cita_existente['Observaciones']): ?>
            <p style="margin-top: 15px;"><strong>Historial:</strong></p>
            <p style="margin-top: 5px; white-space: pre-wrap; font-size: 0.9em; background: white; padding: 15px; border-radius: 8px;">
                <?= htmlspecialchars($cita_existente['Observaciones']) ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($es_nueva_cita): ?>
        <!-- Formulario para NUEVA CITA -->
        <div class="info-box">
            <form method="POST" action="" id="formProgramar">
                <input type="hidden" name="accion" value="programar">
                
                <div class="success-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nueva Cita:</strong> Complete los datos para programar la primera entrevista con este estudiante.
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de la Cita <span class="required">*</span></label>
                        <input type="date" name="fecha_cita" min="<?= $fecha_minima ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hora <span class="required">*</span></label>
                        <input type="time" name="hora_cita" min="08:00" max="17:00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Lugar de la Entrevista <span class="required">*</span></label>
                    <input type="text" name="lugar_cita" 
                           placeholder="Ej: Oficina Club Rotario, Salón Principal, etc." 
                           required>
                </div>

                <div class="form-group">
                    <label>Observaciones Iniciales</label>
                    <textarea name="observaciones" 
                              placeholder="Información adicional sobre la cita (opcional)"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Programar Cita
                    </button>
                    <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- Tabs de Acciones para CITA EXISTENTE -->
        <div class="tabs">
            <div class="tab active" onclick="openTab(event, 'reprogramar')">
                <i class="fas fa-calendar-day"></i> Reprogramar
            </div>
            <div class="tab" onclick="openTab(event, 'completar')">
                <i class="fas fa-check-circle"></i> Completar
            </div>
            <div class="tab" onclick="openTab(event, 'cancelar')">
                <i class="fas fa-times-circle"></i> Cancelar
            </div>
        </div>

        <!-- TAB: Reprogramar -->
        <div id="reprogramar" class="tab-content active">
            <form method="POST" action="" id="formReprogramar">
                <input type="hidden" name="accion" value="reprogramar">
                
                <div class="warning-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Al reprogramar, se mantendrá un historial completo de cambios en las observaciones de la cita.
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nueva Fecha <span class="required">*</span></label>
                        <input type="date" name="nueva_fecha" min="<?= $fecha_minima ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nueva Hora <span class="required">*</span></label>
                        <input type="time" name="nueva_hora" min="08:00" max="17:00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Motivo de Reprogramación <span class="required">*</span></label>
                    <textarea name="motivo_reprogramacion" 
                              placeholder="Ej: Solicitud del estudiante, conflicto de agenda, etc." 
                              required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Reprogramar Cita
                    </button>
                    <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <!-- TAB: Completar -->
        <div id="completar" class="tab-content">
            <form method="POST" action="" id="formCompletar">
                <input type="hidden" name="accion" value="completar">
                
                <div class="warning-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Marcar como completada indica que la entrevista se realizó exitosamente.
                </div>

                <div class="form-group">
                    <label>Observaciones de la Entrevista <span class="required">*</span></label>
                    <textarea name="observaciones_completar" 
                              placeholder="Resumen de la entrevista, impresiones, recomendaciones, etc." 
                              required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Marcar como Completada
                    </button>
                    <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <!-- TAB: Cancelar -->
        <div id="cancelar" class="tab-content">
            <form method="POST" action="" id="formCancelar">
                <input type="hidden" name="accion" value="cancelar">
                
                <div class="warning-note" style="background: #f8d7da; border-left-color: #dc3545; color: #721c24;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Advertencia:</strong> Cancelar una cita es una acción que debe usarse solo cuando sea estrictamente necesario.
                </div>

                <div class="form-group">
                    <label>Motivo de Cancelación <span class="required">*</span></label>
                    <textarea name="motivo_cancelacion" 
                              placeholder="Explique claramente el motivo de la cancelación (Ej: Estudiante no se presentó, cambio de estado de solicitud, etc.)" 
                              required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Cancelar Cita
                    </button>
                    <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        // Función para cambiar pestañas
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

        // Mostrar alertas con SweetAlert2
        <?php if ($mostrar_alerta): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const tipo = '<?= $alerta_tipo ?>';
            const mensaje = '<?= addslashes($alerta_mensaje) ?>';
            
            let icono = 'info';
            let titulo = 'Información';
            
            switch(tipo) {
                case 'success':
                    icono = 'success';
                    titulo = 'Éxito';
                    break;
                case 'error':
                    icono = 'error';
                    titulo = 'Error';
                    break;
                case 'warning':
                    icono = 'warning';
                    titulo = 'Advertencia';
                    break;
            }
            
            Swal.fire({
                icon: icono,
                title: titulo,
                text: mensaje,
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#667eea'
            });
        });
        <?php endif; ?>

        // Confirmaciones para formularios
        document.addEventListener('DOMContentLoaded', function() {
            // Formulario de programar
            const formProgramar = document.getElementById('formProgramar');
            if (formProgramar) {
                formProgramar.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '¿Confirmar programación?',
                        text: '¿Está seguro de programar esta cita?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, programar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#667eea'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formProgramar.submit();
                        }
                    });
                });
            }

            // Formulario de reprogramar
            const formReprogramar = document.getElementById('formReprogramar');
            if (formReprogramar) {
                formReprogramar.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '¿Confirmar reprogramación?',
                        text: '¿Está seguro de reprogramar esta cita?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, reprogramar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#667eea'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formReprogramar.submit();
                        }
                    });
                });
            }

            // Formulario de completar
            const formCompletar = document.getElementById('formCompletar');
            if (formCompletar) {
                formCompletar.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '¿Marcar como completada?',
                        text: '¿Confirma que la cita fue completada?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, completar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#28a745'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formCompletar.submit();
                        }
                    });
                });
            }

            // Formulario de cancelar
            const formCancelar = document.getElementById('formCancelar');
            if (formCancelar) {
                formCancelar.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '¿Cancelar cita?',
                        text: '¿Está seguro de CANCELAR esta cita? Esta acción no se puede deshacer fácilmente.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, cancelar',
                        cancelButtonText: 'Volver',
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formCancelar.submit();
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>