<?php
// procesar_contacto.php
header('Content-Type: application/json; charset=utf-8');

// Incluir la configuración de email
require_once 'config_email.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Obtener y sanitizar datos del formulario
$nombre = isset($_POST['nombre']) ? trim(strip_tags($_POST['nombre'])) : '';
$email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
$telefono = isset($_POST['telefono']) ? trim(strip_tags($_POST['telefono'])) : '';
$asunto = isset($_POST['asunto']) ? trim(strip_tags($_POST['asunto'])) : '';
$mensaje = isset($_POST['mensaje']) ? trim(strip_tags($_POST['mensaje'])) : '';

// Validaciones
$errores = [];

if (empty($nombre)) {
    $errores[] = 'El nombre es requerido';
}

if (empty($email)) {
    $errores[] = 'El correo electrónico es requerido';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo electrónico no es válido';
}

if (empty($mensaje)) {
    $errores[] = 'El mensaje es requerido';
}

// Manejo del archivo adjunto
$archivoSubido = null;
$nombreArchivo = '';
$rutaArchivo = '';

if (isset($_FILES['documento']) && $_FILES['documento']['error'] !== UPLOAD_ERR_NO_FILE) {
    $archivo = $_FILES['documento'];
    
    // Verificar si hubo error en la subida
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error al subir el archivo';
    } else {
        // Validar tipo de archivo
        $extensionesPermitidas = ['pdf', 'doc', 'docx'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensionesPermitidas)) {
            $errores[] = 'Solo se permiten archivos PDF, DOC o DOCX';
        }
        
        // Validar tamaño (máximo 5MB)
        $tamanoMaximo = 5 * 1024 * 1024; // 5MB en bytes
        if ($archivo['size'] > $tamanoMaximo) {
            $errores[] = 'El archivo no debe superar los 5MB';
        }
        
        // Si no hay errores, procesar el archivo
        if (empty($errores)) {
            // Crear directorio si no existe
            $directorioUploads = 'uploads/solicitudes/';
            if (!file_exists($directorioUploads)) {
                mkdir($directorioUploads, 0755, true);
            }
            
            // Generar nombre único para el archivo
            $nombreArchivo = date('YmdHis') . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $archivo['name']);
            $rutaArchivo = $directorioUploads . $nombreArchivo;
            
            // Mover archivo
            if (move_uploaded_file($archivo['tmp_name'], $rutaArchivo)) {
                $archivoSubido = $rutaArchivo;
            } else {
                $errores[] = 'Error al guardar el archivo';
            }
        }
    }
}

// Si hay errores, retornar
if (!empty($errores)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errores)
    ]);
    exit;
}

// Preparar información del archivo para el correo
$infoArchivo = '';
if ($archivoSubido) {
    $tamanoMB = round($_FILES['documento']['size'] / 1024 / 1024, 2);
    $infoArchivo = "
    <div class='field'>
        <div class='label'>📎 Documento adjunto:</div>
        <div class='value'>
            <strong>Nombre:</strong> " . htmlspecialchars($_FILES['documento']['name']) . "<br>
            <strong>Tamaño:</strong> " . $tamanoMB . " MB<br>
            <strong>Tipo:</strong> " . strtoupper(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION)) . "
        </div>
    </div>";
}

// Preparar el contenido del correo
$asuntoCorreo = !empty($asunto) ? "Contacto Web: $asunto" : "Nuevo mensaje de contacto web";

$mensajeHTML = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #c9a961 0%, #8b7035 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .field { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
        .field:last-child { border-bottom: none; }
        .label { font-weight: bold; color: #c9a961; margin-bottom: 5px; }
        .value { color: #333; }
        .footer { background: #333; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; }
        .archivo-alert { background: #fff3cd; padding: 15px; border-left: 4px solid #f2a900; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>📧 Nuevo Mensaje de Contacto</h2>
            <p>Club Rotario Coatepeque-Colomba</p>
        </div>
        
        <div class='content'>
            <div class='field'>
                <div class='label'>👤 Nombre:</div>
                <div class='value'>" . htmlspecialchars($nombre) . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>📧 Correo electrónico:</div>
                <div class='value'>" . htmlspecialchars($email) . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>📱 Teléfono:</div>
                <div class='value'>" . (!empty($telefono) ? htmlspecialchars($telefono) : 'No proporcionado') . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>📋 Asunto:</div>
                <div class='value'>" . (!empty($asunto) ? htmlspecialchars($asunto) : 'Consulta general') . "</div>
            </div>
            
            <div class='field'>
                <div class='label'>💬 Mensaje:</div>
                <div class='value'>" . nl2br(htmlspecialchars($mensaje)) . "</div>
            </div>
            
            " . $infoArchivo . "
            
            <div style='margin-top: 20px; padding: 15px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;'>
                <strong>📅 Fecha y hora:</strong> " . date('d/m/Y H:i:s') . "
            </div>
        </div>
        
        <div class='footer'>
            <p>Este mensaje fue enviado desde el formulario de contacto del sitio web</p>
            <p>Club Rotario Coatepeque-Colomba © " . date('Y') . "</p>
        </div>
    </div>
</body>
</html>
";

// Usar la función avanzada para enviar con adjunto
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host       = ConfigEmail::SMTP_HOST;
    $mail->SMTPAuth   = ConfigEmail::SMTP_AUTH;
    $mail->Username   = ConfigEmail::SMTP_USERNAME;
    $mail->Password   = ConfigEmail::SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = ConfigEmail::SMTP_PORT;
    
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->Timeout = 30;
    $mail->setFrom(ConfigEmail::FROM_EMAIL, ConfigEmail::FROM_NAME);
    $mail->addAddress(ConfigEmail::FROM_EMAIL, 'Administrador');
    
    // Adjuntar archivo si existe
    if ($archivoSubido && file_exists($archivoSubido)) {
        $mail->addAttachment($archivoSubido, $_FILES['documento']['name']);
    }
    
    $mail->isHTML(true);
    $mail->CharSet = ConfigEmail::CHARSET;
    $mail->Subject = $asuntoCorreo;
    $mail->Body    = $mensajeHTML;
    $mail->AltBody = strip_tags($mensajeHTML);
    
    $mail->send();
    $resultadoAdmin = ['success' => true];
    
} catch (Exception $e) {
    error_log("Error al enviar correo: {$mail->ErrorInfo}");
    $resultadoAdmin = ['success' => false, 'message' => $mail->ErrorInfo];
}

// Correo de confirmación al usuario (sin adjunto)
$mensajeConfirmacion = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #005daa 0%, #003b76 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #333; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; }
        .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #f2a900; margin: 20px 0; border-radius: 4px; }
        .contact-info { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .contact-item { margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✅ ¡Mensaje Recibido!</h1>
            <p>Gracias por contactarnos</p>
        </div>
        
        <div class='content'>
            <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
            
            <p>Hemos recibido tu mensaje correctamente" . ($archivoSubido ? " junto con tu documento adjunto" : "") . ". Nuestro equipo lo revisará y te responderá a la brevedad posible.</p>
            
            <div class='highlight'>
                <strong>📝 Resumen de tu mensaje:</strong><br><br>
                <strong>Asunto:</strong> " . (!empty($asunto) ? htmlspecialchars($asunto) : 'Consulta general') . "<br>
                <strong>Mensaje:</strong> " . htmlspecialchars(substr($mensaje, 0, 100)) . (strlen($mensaje) > 100 ? '...' : '') . 
                ($archivoSubido ? "<br><strong>Documento:</strong> " . htmlspecialchars($_FILES['documento']['name']) : "") . "
            </div>
            
            <p>Normalmente respondemos en un plazo de <strong>24 a 48 horas hábiles</strong>.</p>
            
            <div class='contact-info'>
                <h3 style='color: #005daa; margin-top: 0;'>📞 Información de Contacto</h3>
                <div class='contact-item'>📍 <strong>Dirección:</strong> 5 Calle 4-56 Zona 1, Coatepeque, Guatemala</div>
                <div class='contact-item'>📱 <strong>Teléfono:</strong> 7775 5248</div>
                <div class='contact-item'>📧 <strong>Email:</strong> rotarios_coatepequecolomba@yahoo.com.mx</div>
                <div class='contact-item'>🕐 <strong>Horario:</strong> Lunes a Viernes: 9:00 AM - 5:00 PM</div>
            </div>
            
            <p style='margin-top: 20px;'>Si tu consulta es urgente, no dudes en llamarnos directamente.</p>
            
            <p>Saludos cordiales,<br>
            <strong>Club Rotario Coatepeque-Colomba</strong></p>
        </div>
        
        <div class='footer'>
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>Club Rotario Coatepeque-Colomba © " . date('Y') . "</p>
        </div>
    </div>
</body>
</html>
";

$resultadoUsuario = enviarCorreo(
    $email,
    "Confirmación de contacto - Club Rotario Coatepeque-Colomba",
    $mensajeConfirmacion,
    $nombre
);


if ($resultadoAdmin['success']) {
    echo json_encode([
        'success' => true,
        'message' => '¡Mensaje enviado exitosamente!' . ($archivoSubido ? ' Tu documento ha sido recibido.' : '') . ' Te responderemos pronto.'
    ]);
} else {

    if ($archivoSubido && file_exists($archivoSubido)) {
        unlink($archivoSubido);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Hubo un problema al enviar el mensaje. Por favor, intenta nuevamente o contáctanos directamente.'
    ]);
}
?>