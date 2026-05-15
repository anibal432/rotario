<?php
/**
 * correo_reprogramacion.php
 * Maneja el envío de correos para reprogramación de citas
 */

require_once 'EmailHandler.php';

function enviarCorreoReprogramacion($pdo, $estudiante, $cita_anterior, $nueva_fecha, $nueva_hora, $motivo) {
    try {
        $emailHandler = new EmailHandler($pdo);
        
        // Formatear fechas
        $meses = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo',
            '04' => 'abril', '05' => 'mayo', '06' => 'junio',
            '07' => 'julio', '08' => 'agosto', '09' => 'septiembre',
            '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'
        ];
        
        $fecha_anterior_obj = new DateTime($cita_anterior['Fecha_Cita']);
        $dia_anterior = $fecha_anterior_obj->format('d');
        $mes_anterior = $meses[$fecha_anterior_obj->format('m')];
        $anio_anterior = $fecha_anterior_obj->format('Y');
        $fecha_anterior_formateada = "$dia_anterior de $mes_anterior de $anio_anterior";
        
        $fecha_nueva_obj = new DateTime($nueva_fecha);
        $dia_nueva = $fecha_nueva_obj->format('d');
        $mes_nueva = $meses[$fecha_nueva_obj->format('m')];
        $anio_nueva = $fecha_nueva_obj->format('Y');
        $fecha_nueva_formateada = "$dia_nueva de $mes_nueva de $anio_nueva";
        
        $hora_anterior_obj = new DateTime($cita_anterior['Hora_Cita']);
        $hora_anterior_formateada = $hora_anterior_obj->format('g:i A');
        
        $hora_nueva_obj = new DateTime($nueva_hora);
        $hora_nueva_formateada = $hora_nueva_obj->format('g:i A');
        
        $asunto = "Reprogramación de Cita de Entrevista - Club Rotario";
        
        $mensaje = <<<HTML
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
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .warning-box { background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #ffc107; }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
        h1 { margin: 0; font-size: 24px; }
        h2 { color: #667eea; font-size: 20px; margin-top: 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
        .change-details { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Reprogramación de Cita</h1>
            <p style="margin: 5px 0 0 0;">Club Rotario Coatepeque Colomba</p>
        </div>
        
        <div class="content">
            <p>Estimado/a <strong>{$estudiante['Nombres_Apellidos']}</strong>,</p>
            
            <p>Le informamos que su cita de entrevista ha sido reprogramada. A continuación encontrará los detalles actualizados:</p>
            
            <div class="change-details">
                <h3>📅 Cambios en la Cita:</h3>
                <p><strong>Fecha Anterior:</strong> {$fecha_anterior_formateada} a las {$hora_anterior_formateada}</p>
                <p><strong>Nueva Fecha:</strong> {$fecha_nueva_formateada} a las {$hora_nueva_formateada}</p>
                <p><strong>Lugar:</strong> {$cita_anterior['Lugar_Entrevista']}</p>
            </div>
            
            <div class="info-box">
                <h3>📝 Motivo de la Reprogramación:</h3>
                <p>{$motivo}</p>
            </div>
            
            <div class="warning-box">
                <h3>⚠️ Recordatorio Importante:</h3>
                <ul>
                    <li>Llega <strong>10 minutos antes</strong> de la hora programada</li>
                    <li>Trae todos los documentos requeridos (originales y copias)</li>
                    <li>La duración estimada es de <strong>30-45 minutos</strong></li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>📋 Documentos Requeridos:</h3>
                <ul>
                    <li>Boleta de calificaciones reciente</li>
                    <li>Certificado de nacimiento</li>
                    <li>DPI estudiante y encargado</li>
                    <li>Comprobante de ingresos</li>
                    <li>Recibos de servicios básicos</li>
                </ul>
            </div>
            
            <p>Si tiene alguna pregunta o necesita realizar algún ajuste adicional, no dude en contactarnos.</p>
            
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

        $resultado = $emailHandler->enviarCorreo(
            $estudiante['Email'],
            $asunto,
            $mensaje,
            true,
            $estudiante['Nombres_Apellidos']
        );
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error en enviarCorreoReprogramacion: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>