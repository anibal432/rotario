<?php
/**
 * EmailHandler.php
 * Manejo de correos electrónicos con PHPMailer (sin Composer)
 * Club Rotario Coatepeque Colomba
 */

// OPCIÓN 1: Si PHPMailer está directamente en la raíz del proyecto
// require_once __DIR__ . '/PHPMailer/PHPMailer.php';
// require_once __DIR__ . '/PHPMailer/SMTP.php';
// require_once __DIR__ . '/PHPMailer/Exception.php';

// OPCIÓN 2: Si PHPMailer está en PHPMailer-master/src/
// require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
// require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
// require_once __DIR__ . '/PHPMailer-master/src/Exception.php';

// OPCIÓN 3: Si PHPMailer está en subcarpeta src/
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

// DESCOMENTA LA OPCIÓN CORRECTA Y COMENTA LAS OTRAS

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHandler {
    private $pdo;
    private $smtp_config;
    
    /**
     * Constructor
     * @param PDO|null $pdo Conexión PDO
     */
    public function __construct($pdo = null) {
        if ($pdo !== null && $pdo instanceof PDO) {
            $this->pdo = $pdo;
        } else {
            // Intentar cargar desde conexion.php
            if (file_exists(__DIR__ . '/conexion.php')) {
                require_once __DIR__ . '/conexion.php';
                if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                    $this->pdo = $GLOBALS['pdo'];
                }
            }
        }
        
        // Configuración SMTP - Gmail
        $this->smtp_config = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls', // o 'ssl' si usas puerto 465
            'username' => 'cmvitalis01@gmail.com',
            'password' => 'wcce ight jiig mmfy',
            'from_email' => 'cmvitalis01@gmail.com',
            'from_name' => 'Club Rotario Coatepeque Colomba'
        ];
        
        $this->cargarConfiguracion();
    }
    
    /**
     * Carga configuración desde BD (opcional)
     */
    private function cargarConfiguracion() {
        try {
            if ($this->pdo !== null) {
                $stmt = $this->pdo->query("SELECT * FROM Configuracion_Email LIMIT 1");
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($config) {
                    $this->smtp_config['from_email'] = $config['Email_Remitente'] ?? $this->smtp_config['from_email'];
                    $this->smtp_config['from_name'] = $config['Nombre_Remitente'] ?? $this->smtp_config['from_name'];
                }
            }
        } catch (Exception $e) {
            error_log("EmailHandler: No se pudo cargar configuración - " . $e->getMessage());
        }
    }
    
    /**
     * Envía un correo usando PHPMailer
     * @param string $to Destinatario
     * @param string $subject Asunto
     * @param string $message Mensaje
     * @param bool $is_html Si es HTML
     * @param string $nombre_destinatario Nombre del destinatario (opcional)
     * @return array
     */
    public function enviarCorreo($to, $subject, $message, $is_html = true, $nombre_destinatario = '') {
        try {
            // Validar email
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Email inválido: $to");
            }
            
            // Crear instancia de PHPMailer
            $mail = new PHPMailer(true);
            
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host       = $this->smtp_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_config['username'];
            $mail->Password   = $this->smtp_config['password'];
            $mail->SMTPSecure = $this->smtp_config['encryption'];
            $mail->Port       = $this->smtp_config['port'];
            $mail->CharSet    = 'UTF-8';
            
            // Configuración de depuración (comentar en producción)
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
            // Remitente
            $mail->setFrom($this->smtp_config['from_email'], $this->smtp_config['from_name']);
            
            // Destinatario
            $mail->addAddress($to, $nombre_destinatario);
            
            // Contenido
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            
            // Versión texto plano (opcional pero recomendado)
            if ($is_html) {
                $mail->AltBody = strip_tags($message);
            }
            
            // Enviar
            $mail->send();
            
            error_log("EmailHandler: Correo enviado exitosamente a $to");
            $this->registrarEnvio($to, $subject, 'Enviado', '');
            
            return [
                'success' => true,
                'message' => 'Correo enviado exitosamente'
            ];
            
        } catch (Exception $e) {
            $error_msg = "Error al enviar correo: {$mail->ErrorInfo}";
            error_log("EmailHandler: $error_msg");
            $this->registrarEnvio($to, $subject, 'Fallido', $error_msg);
            
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }
    }
    
    /**
     * Registra el envío en BD
     */
    private function registrarEnvio($to, $subject, $estado, $error = '') {
        try {
            if ($this->pdo !== null) {
                // Crear tabla si no existe
                $sql_create = "CREATE TABLE IF NOT EXISTS Historial_Emails (
                    ID INT AUTO_INCREMENT PRIMARY KEY,
                    Email_Destinatario VARCHAR(255) NOT NULL,
                    Asunto VARCHAR(255),
                    Estado VARCHAR(50),
                    Error_Mensaje TEXT,
                    Fecha_Envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_destinatario (Email_Destinatario),
                    INDEX idx_fecha (Fecha_Envio)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $this->pdo->exec($sql_create);
                
                // Insertar registro
                $sql = "INSERT INTO Historial_Emails 
                        (Email_Destinatario, Asunto, Estado, Error_Mensaje, Fecha_Envio) 
                        VALUES (?, ?, ?, ?, NOW())";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$to, $subject, $estado, $error]);
            }
        } catch (Exception $e) {
            error_log("EmailHandler: No se pudo registrar envío - " . $e->getMessage());
        }
    }
    
    /**
     * Envía confirmación de solicitud de beca
     * @param array $datos Datos del estudiante
     * @return array
     */
    public function enviarConfirmacionSolicitud($datos) {
        $to = $datos['email'];
        $nombre = $datos['nombre'];
        $fecha_cita = $datos['fecha_cita'];
        $hora_cita = $datos['hora_cita'];
        $id_estudiante = $datos['id_estudiante'];
        
        // Formatear fecha
        $meses = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo',
            '04' => 'abril', '05' => 'mayo', '06' => 'junio',
            '07' => 'julio', '08' => 'agosto', '09' => 'septiembre',
            '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
        ];
        
        $fecha_obj = new DateTime($fecha_cita);
        $dia = $fecha_obj->format('d');
        $mes = $meses[$fecha_obj->format('m')];
        $anio = $fecha_obj->format('Y');
        $fecha_formateada = "$dia de $mes de $anio";
        
        $hora_obj = new DateTime($hora_cita);
        $hora_formateada = $hora_obj->format('g:i A');
        
        $numero_ref = "BECA-" . str_pad($id_estudiante, 6, '0', STR_PAD_LEFT);
        
        $asunto = "Solicitud de Beca Recibida - Club Rotario";
        
        $mensaje = $this->generarPlantillaHTML(
            $nombre, 
            $fecha_formateada, 
            $hora_formateada, 
            $numero_ref
        );
        
        return $this->enviarCorreo($to, $asunto, $mensaje, true, $nombre);
    }
    
    /**
     * Envía confirmación de registro para carrera 21K
     * @param int $corredor_id ID del corredor
     * @return array
     */
    public function enviarConfirmacionRegistro($corredor_id) {
        try {
            // Obtener datos del corredor
            $stmt = $this->pdo->prepare("
                SELECT c.*, COUNT(c2.id) as total_registros
                FROM corredores c
                CROSS JOIN corredores c2 
                WHERE c2.fecha_registro <= c.fecha_registro AND c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$corredor_id]);
            $corredor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$corredor) {
                throw new Exception("Corredor no encontrado");
            }
            
            // Generar contenido del email
            $asunto = "Confirmación de Registro - Carrera 21K 'Por la Educación'";
            $mensaje_html = $this->generarMensajeConfirmacionCarrera($corredor);
            
            // Enviar email
            $resultado = $this->enviarCorreo(
                $corredor['email'],
                $asunto,
                $mensaje_html,
                true,
                $corredor['nombre']
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Error enviando confirmación de registro: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera HTML del correo de confirmación de beca
     */
    private function generarPlantillaHTML($nombre, $fecha, $hora, $referencia) {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .cita-box { background: #e7f3ff; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #667eea; }
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
        h1 { margin: 0; font-size: 24px; }
        h2 { color: #667eea; font-size: 20px; margin-top: 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
        .reference { text-align: center; font-size: 24px; font-weight: bold; color: #667eea; padding: 15px; background: #fff3cd; border-radius: 5px; margin: 20px 0; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 ¡Solicitud Recibida!</h1>
            <p style="margin: 5px 0 0 0;">Club Rotario Coatepeque Colomba</p>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>$nombre</strong>,</p>
            
            <p>Hemos recibido tu solicitud de beca correctamente.</p>
            
            <div class="cita-box">
                <h2>📅 Tu Cita de Entrevista</h2>
                <p style="margin: 10px 0;">📆 <strong>Fecha:</strong> $fecha</p>
                <p style="margin: 10px 0;">🕐 <strong>Hora:</strong> $hora</p>
                <p style="margin: 10px 0;">📍 <strong>Lugar:</strong> Oficinas Club Rotario Coatepeque-Colomba</p>
            </div>
            
            <div class="info-box">
                <h3>📝 Documentos requeridos:</h3>
                <ul>
                    <li>Boleta de calificaciones reciente</li>
                    <li>Certificado de nacimiento</li>
                    <li>DPI estudiante y encargado</li>
                    <li>Comprobante de ingresos</li>
                    <li>Recibos de servicios</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>⚠️ Recomendaciones:</h3>
                <p style="margin: 5px 0;">• Llega <strong>10 minutos antes</strong></p>
                <p style="margin: 5px 0;">• Duración: <strong>30-45 minutos</strong></p>
                <p style="margin: 5px 0;">• Trae documentos originales y copias</p>
            </div>
            
            <div class="reference">
                <strong>Número de Referencia:</strong><br>
                $referencia
            </div>
            
            <p style="text-align: center;">
                <strong>¿Preguntas?</strong><br>
                📧 becas@rotariocoatepeque.org<br>
                📱 1234-5678
            </p>
        </div>
        
        <div class="footer">
            <p style="margin: 5px 0;">&copy; 2025 Club Rotario Coatepeque Colomba</p>
            <p style="font-size: 12px; margin: 5px 0;">Correo automático, no responder.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Genera HTML del correo de confirmación de carrera
     */
    private function generarMensajeConfirmacionCarrera($corredor) {
        $fecha_evento = "5 de Noviembre 2024";
        $hora_evento = "7:00 AM";
        $lugar_evento = "Centro Histórico Coatepeque";
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .number-box { background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; padding: 20px; color: #666; font-size: 14px; background: #f1f1f1; }
        h1, h2 { margin: 10px 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏃‍♂️ ¡Registro Exitoso!</h1>
            <h2>Carrera 21K 'Por la Educación'</h2>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>{$corredor['nombre']}</strong>,</p>
            
            <p>¡Felicitaciones! Tu registro para la Carrera 21K 'Por la Educación' ha sido procesado exitosamente.</p>
            
            <div class="number-box">
                <h3 style="color: white; margin: 0 0 10px 0;">Tu Número de Corredor</h3>
                <div style="font-size: 2.5em; font-weight: bold;">{$corredor['numero_corredor']}</div>
            </div>
            
            <div class="info-box">
                <h3>📋 Detalles de tu Registro:</h3>
                <ul>
                    <li><strong>Categoría:</strong> {$corredor['categoria']}</li>
                    <li><strong>Talla de Playera:</strong> {$corredor['talla_playera']}</li>
                    <li><strong>Fecha de Registro:</strong> {$corredor['fecha_registro']}</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>🏁 Información del Evento:</h3>
                <ul>
                    <li><strong>📅 Fecha:</strong> $fecha_evento</li>
                    <li><strong>🕕 Hora:</strong> $hora_evento</li>
                    <li><strong>📍 Lugar de Salida:</strong> $lugar_evento</li>
                    <li><strong>🏃 Distancia:</strong> 21 Kilómetros</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>📝 Instrucciones Importantes:</h3>
                <ul>
                    <li>Llega al menos 30 minutos antes del evento</li>
                    <li>Trae tu identificación (DPI)</li>
                    <li>Recuerda mantenerte hidratado</li>
                    <li>Tu playera técnica y medalla te serán entregadas el día del evento</li>
                </ul>
            </div>
            
            <p><strong>¡Gracias por apoyar la educación con tu participación!</strong></p>
            <p>Nos vemos en la línea de salida.</p>
        </div>
        
        <div class="footer">
            <p><strong>Club Rotario Coatepeque</strong><br>
            Carrera 21K 'Por la Educación'<br>
            <em>Corriendo por las becas estudiantiles</em></p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Método de prueba para verificar configuración SMTP
     * @return array
     */
    public function testConexion() {
        try {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host       = $this->smtp_config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtp_config['username'];
            $mail->Password   = $this->smtp_config['password'];
            $mail->SMTPSecure = $this->smtp_config['encryption'];
            $mail->Port       = $this->smtp_config['port'];
            
            // Intentar conectar
            $mail->smtpConnect();
            $mail->smtpClose();
            
            return [
                'success' => true,
                'message' => 'Conexión SMTP exitosa'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}