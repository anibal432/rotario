<?php
/**
 * correo_cancelacion.php
 * Maneja el envío de correos para cancelación de citas
 */

require_once 'EmailHandler.php';

function enviarCorreoCancelacion($pdo, $estudiante, $cita, $motivo) {
    try {
        $emailHandler = new EmailHandler($pdo);
        
        // Formatear fecha
        $meses = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo',
            '04' => 'abril', '05' => 'mayo', '06' => 'junio',
            '07' => 'julio', '08' => 'agosto', '09' => 'septiembre',
            '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
        ];
        
        $fecha_obj = new DateTime($cita['Fecha_Cita']);
        $dia = $fecha_obj->format('d');
        $mes = $meses[$fecha_obj->format('m')];
        $anio = $fecha_obj->format('Y');
        $fecha_formateada = "$dia de $mes de $anio";
        
        $hora_obj = new DateTime($cita['Hora_Cita']);
        $hora_formateada = $hora_obj->format('g:i A');
        
        $asunto = "Cancelación de Cita de Entrevista - Club Rotario";
        
        $mensaje = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .cancel-box { background: #f8d7da; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #dc3545; }
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
        h1 { margin: 0; font-size: 24px; }
        h2 { color: #dc3545; font-size: 20px; margin-top: 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ Cancelación de Cita</h1>
            <p style="margin: 5px 0 0 0;">Club Rotario Coatepeque Colomba</p>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>{$estudiante['Nombres_Apellidos']}</strong>,</p>
            
            <p>Lamentamos informarle que su cita de entrevista programada ha sido cancelada.</p>
            
            <div class="cancel-box">
                <h2>📅 Cita Cancelada</h2>
                <p><strong>Fecha:</strong> {$fecha_formateada}</p>
                <p><strong>Hora:</strong> {$hora_formateada}</p>
                <p><strong>Lugar:</strong> {$cita['Lugar_Entrevista']}</p>
            </div>
            
            <div class="info-box">
                <h3>📝 Motivo de Cancelación:</h3>
                <p>{$motivo}</p>
            </div>
            
            <div class="info-box">
                <h3>ℹ️ Información Adicional:</h3>
                <p>Si desea programar una nueva cita de entrevista o tiene alguna pregunta sobre el proceso de solicitud de beca, por favor póngase en contacto con nosotros.</p>
                
                <p style="text-align: center; margin-top: 15px;">
                    <strong>Contacto:</strong><br>
                    📧 becas@rotariocoatepeque.org<br>
                    📱 1234-5678
                </p>
            </div>
            
            <p>Agradecemos su comprensión y esperamos poder asistirle en el futuro.</p>
            
            <p>Atentamente,<br>
            <strong>Equipo del Club Rotario Coatepeque Colomba</strong></p>
        </div>
        
        <div class="footer">
            <p style="margin: 5px 0;">&copy; 2025 Club Rotario Coatepeque Colomba</p>
            <p style="font-size: 12px; margin: 5px 0;">Correo automático, no responder.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $resultado = $emailHandler->enviarCorreo(
            $estudiante['Email'],
            $asunto,
            $mensaje,
            true,
            $estudiante['Nombres_Apellidos']
        );
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error en enviarCorreoCancelacion: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>