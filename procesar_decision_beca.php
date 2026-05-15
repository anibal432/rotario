<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once 'conexion.php';
require_once 'EmailHandler.php';

header('Content-Type: application/json');

try {
    // Validar datos recibidos
    if (!isset($_POST['id_evaluacion']) || !isset($_POST['decision'])) {
        throw new Exception('Datos incompletos');
    }

    $id_evaluacion = $_POST['id_evaluacion'];
    $decision = $_POST['decision']; // 'Aprobado' o 'Rechazado'
    $comentarios = $_POST['comentarios'] ?? '';
    $user_id = $_POST['user_id'];

    // Validar que la decisión sea válida
    if (!in_array($decision, ['Aprobado', 'Rechazado'])) {
        throw new Exception('Decisión no válida');
    }

    // Obtener información del estudiante y la evaluación
    $sql = "SELECT 
                e.Id_Estudiante,
                e.Nombres_Apellidos,
                e.Email,
                ev.Estado_Evaluacion
            FROM Evaluaciones_Socioeconomicas ev
            INNER JOIN Estudiantes e ON ev.Id_Estudiante = e.Id_Estudiante
            WHERE ev.Id_Evaluacion = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_evaluacion]);
    $evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluacion) {
        throw new Exception('Evaluación no encontrada');
    }

    // Verificar que esté pendiente
    if ($evaluacion['Estado_Evaluacion'] !== 'Pendiente') {
        throw new Exception('Esta evaluación ya fue procesada anteriormente');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Actualizar el estado de la evaluación
    $sql_update = "UPDATE Evaluaciones_Socioeconomicas 
                   SET Estado_Evaluacion = ?,
                       Comentarios_Evaluacion = ?,
                       Fecha_Revision = NOW(),
                       Id_Usuario_Revisor = ?,
                       Fecha_Decision = CURDATE()";

    // Si es rechazado, agregar el motivo
    if ($decision === 'Rechazado') {
        $sql_update .= ", Motivo_Rechazo = ?";
    }
    
    $sql_update .= " WHERE Id_Evaluacion = ?";

    $stmt = $pdo->prepare($sql_update);
    
    if ($decision === 'Rechazado') {
        $motivo = $comentarios ?: 'No especificado';
        $stmt->execute([$decision, $comentarios, $user_id, $motivo, $id_evaluacion]);
    } else {
        $stmt->execute([$decision, $comentarios, $user_id, $id_evaluacion]);
    }

    // Si es aprobado, crear el registro de beca
    if ($decision === 'Aprobado') {
        // Determinar el tipo de beca y monto (puedes ajustar estos valores)
        $tipo_beca = 'Beca Académica';
        $monto_mensual = 500.00; // Monto por defecto en quetzales
        
        $sql_beca = "INSERT INTO Becas_Otorgadas 
                     (Id_Estudiante, Id_Evaluacion, Tipo_Beca, Monto_Mensual, 
                      Fecha_Inicio, Estado_Beca, Promedio_Minimo)
                     VALUES (?, ?, ?, ?, CURDATE(), 'Activa', 75.00)";
        
        $stmt = $pdo->prepare($sql_beca);
        $stmt->execute([
            $evaluacion['Id_Estudiante'],
            $id_evaluacion,
            $tipo_beca,
            $monto_mensual
        ]);

        $id_beca = $pdo->lastInsertId();

        // Actualizar el estado del estudiante
        $sql_estudiante = "UPDATE Estudiantes 
                          SET Estado_Beca = 'Activo',
                              Fecha_Inicio_Beca = CURDATE()
                          WHERE Id_Estudiante = ?";
        $stmt = $pdo->prepare($sql_estudiante);
        $stmt->execute([$evaluacion['Id_Estudiante']]);
    }

    // Actualizar el estado de la cita a 'Completada'
    $sql_cita = "UPDATE Citas_Entrevista 
                 SET Estado_Cita = 'Completada'
                 WHERE Id_Evaluacion = ?";
    $stmt = $pdo->prepare($sql_cita);
    $stmt->execute([$id_evaluacion]);

    // Registrar en bitácora
    $actividad = $decision === 'Aprobado' 
        ? "Aprobó la solicitud de beca de {$evaluacion['Nombres_Apellidos']}"
        : "Rechazó la solicitud de beca de {$evaluacion['Nombres_Apellidos']}";
    
    $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                     VALUES (?, ?, CURDATE())";
    $stmt = $pdo->prepare($sql_bitacora);
    $stmt->execute([$user_id, $actividad]);

    // Commit de la transacción
    $pdo->commit();

    // Enviar correo de notificación al estudiante
    try {
        $emailHandler = new EmailHandler();
        
        if ($decision === 'Aprobado') {
            $asunto = "¡Felicitaciones! Tu solicitud de beca ha sido aprobada";
            $mensaje = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                             color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .success-icon { font-size: 60px; margin-bottom: 20px; }
                    .info-box { background: white; padding: 20px; margin: 20px 0; 
                               border-left: 4px solid #28a745; border-radius: 5px; }
                    .btn { display: inline-block; padding: 12px 30px; background: #28a745; 
                          color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='success-icon'>✓</div>
                        <h1>¡Felicitaciones!</h1>
                        <p>Tu Solicitud ha sido Aprobada</p>
                    </div>
                    <div class='content'>
                        <p>Estimado/a <strong>{$evaluacion['Nombres_Apellidos']}</strong>,</p>
                        
                        <p>Nos complace informarte que tu solicitud de beca ha sido <strong>APROBADA</strong> 
                        por el comité evaluador del Club Rotario Coatepeque-Colomba.</p>
                        
                        <div class='info-box'>
                            <h3>📋 Próximos Pasos:</h3>
                            <ol>
                                <li>Deberás presentarte a nuestras oficinas para firmar la <strong>Carta de Compromiso</strong></li>
                                <li>Trae contigo los siguientes documentos:
                                    <ul>
                                        <li>Boleta de calificaciones original</li>
                                        <li>Certificado de nacimiento (original y copia)</li>
                                        <li>DPI del estudiante y encargado</li>
                                        <li>Comprobante de ingresos familiar</li>
                                    </ul>
                                </li>
                                <li>Una vez firmada la carta, iniciaremos el proceso de pago de tu beca</li>
                            </ol>
                        </div>
                        
                        <div class='info-box'>
                            <h3>💰 Detalles de tu Beca:</h3>
                            <p><strong>Tipo:</strong> {$tipo_beca}</p>
                            <p><strong>Monto Mensual:</strong> Q. " . number_format($monto_mensual, 2) . "</p>
                            <p><strong>Inicio:</strong> " . date('d/m/Y') . "</p>
                        </div>
                        
                        <p><strong>Recuerda:</strong> Debes mantener un promedio mínimo de 75 puntos y 
                        aprobar todas tus materias para continuar recibiendo la beca.</p>
                        
                        <p style='text-align: center; margin-top: 30px;'>
                            <strong>¿Tienes preguntas?</strong><br>
                            Contáctanos: becas@rotariocoatepeque.org<br>
                            Teléfono: 1234-5678
                        </p>
                        
                        <p style='text-align: center; margin-top: 30px; color: #666;'>
                            <em>¡Felicitaciones nuevamente y mucho éxito en tus estudios!</em>
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            $asunto = "Resultado de tu Solicitud de Beca";
            $mensaje = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); 
                             color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                    .info-box { background: white; padding: 20px; margin: 20px 0; 
                               border-left: 4px solid #6c757d; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Resultado de tu Solicitud</h1>
                    </div>
                    <div class='content'>
                        <p>Estimado/a <strong>{$evaluacion['Nombres_Apellidos']}</strong>,</p>
                        
                        <p>Lamentamos informarte que en esta ocasión tu solicitud de beca no ha sido aprobada 
                        por el comité evaluador del Club Rotario Coatepeque-Colomba.</p>
                        
                        <div class='info-box'>
                            <h3>📋 Información:</h3>
                            <p>Esta decisión se tomó después de una cuidadosa revisión de tu solicitud 
                            y entrevista. Te animamos a seguir trabajando en tu desarrollo académico.</p>
                            
                            <p>Podrás aplicar nuevamente en el próximo período de solicitudes.</p>
                        </div>
                        
                        <p style='text-align: center; margin-top: 30px;'>
                            <strong>¿Tienes preguntas?</strong><br>
                            Contáctanos: becas@rotariocoatepeque.org<br>
                            Teléfono: 1234-5678
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";
        }

        $emailHandler->enviarCorreo($evaluacion['Email'], $asunto, $mensaje);
    } catch (Exception $e) {
        // Si falla el correo, registrar pero no detener el proceso
        error_log("Error al enviar correo de decisión: " . $e->getMessage());
    }

    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => $decision === 'Aprobado' 
            ? 'Solicitud aprobada exitosamente. Se ha creado el registro de beca y se envió notificación al estudiante.'
            : 'Solicitud rechazada. Se ha enviado notificación al estudiante.',
        'decision' => $decision
    ];

    if ($decision === 'Aprobado') {
        $response['id_beca'] = $id_beca;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error en procesar_decision.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la decisión: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Revertir transacción en caso de error general
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error general en procesar_decision.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>