<?php
/**
 * config_email.php
 * Configuración de correo electrónico con PHPMailer
 * Club Rotario Coatepeque Colomba
 */

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Configuración de correo electrónico
 */
class ConfigEmail {
    // Configuración SMTP
    const SMTP_HOST = 'smtp.gmail.com';  
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_AUTH = true;
    
    // Credenciales - ACTUALIZADAS
    const SMTP_USERNAME = 'cmvitalis01@gmail.com';
    const SMTP_PASSWORD = 'gcly petu tsfz ppdj';   // ← CONTRASEÑA NUEVA
    
    // Información del remitente
    const FROM_EMAIL = 'cmvitalis01@gmail.com';
    const FROM_NAME = 'Club Rotario Coatepeque Colomba';
    
    // Configuración adicional
    const CHARSET = 'UTF-8';
    const DEBUG_MODE = false; // Cambiar a true para debug
}

/**
 * Función para enviar correos usando PHPMailer
 */
function enviarCorreo($destinatario, $asunto, $mensaje, $nombreDestinatario = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        if (ConfigEmail::DEBUG_MODE) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
        }
        
        $mail->isSMTP();
        $mail->Host       = ConfigEmail::SMTP_HOST;
        $mail->SMTPAuth   = ConfigEmail::SMTP_AUTH;
        $mail->Username   = ConfigEmail::SMTP_USERNAME;
        $mail->Password   = ConfigEmail::SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = ConfigEmail::SMTP_PORT;
        
        // Opciones adicionales para Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->Timeout = 30;
        
        // Configuración del remitente
        $mail->setFrom(ConfigEmail::FROM_EMAIL, ConfigEmail::FROM_NAME);
        
        // Destinatario
        if ($nombreDestinatario) {
            $mail->addAddress($destinatario, $nombreDestinatario);
        } else {
            $mail->addAddress($destinatario);
        }
        
        // Configuración del contenido
        $mail->isHTML(true);
        $mail->CharSet = ConfigEmail::CHARSET;
        $mail->Subject = $asunto;
        $mail->Body    = $mensaje;
        $mail->AltBody = strip_tags($mensaje);
        
        // Enviar
        $mail->send();
        
        // Log de éxito
        error_log("✓ Correo enviado exitosamente a: $destinatario");
        
        return [
            'success' => true,
            'message' => 'Correo enviado exitosamente'
        ];
        
    } catch (Exception $e) {
        // Log de error
        error_log("✗ Error al enviar correo a $destinatario");
        error_log("Detalle del error: {$mail->ErrorInfo}");
        
        return [
            'success' => false,
            'message' => "Error: {$mail->ErrorInfo}"
        ];
    }
}
?>