<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conexion.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Incluir la clase EmailHandler
require_once 'EmailHandler.php';

// Función para enviar correo a participante de evento
function enviarCorreoParticipante($email, $nombre, $numeroParticipante, $tallPlayera, $evento) {
    $asunto = "✅ ¡Inscripción Confirmada! - " . $evento['Nombre_Evento'];
    
    $mensaje_html = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Inscripción Confirmada</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #004b87 0%, #0066b3 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .info-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #004b87; border-radius: 5px; }
            .number-box { background: #004b87; color: white; padding: 20px; text-align: center; border-radius: 10px; margin: 20px 0; }
            .number-box h3 { margin: 0 0 10px 0; }
            .number-box .number { font-size: 2.5em; font-weight: bold; }
            .highlight { color: #004b87; font-weight: bold; }
            .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
            .detail-row:last-child { border-bottom: none; }
            .label { font-weight: 600; color: #004b87; }
            .value { color: #333; text-align: right; }
            .footer { text-align: center; margin-top: 30px; padding: 20px; color: #666; border-top: 2px solid #ddd; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 ¡Inscripción Confirmada!</h1>
                <p>" . htmlspecialchars($evento['Nombre_Evento']) . "</p>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
                <p>¡Nos complace informarte que tu inscripción ha sido <strong>APROBADA</strong>!</p>
                
                <div class='number-box'>
                    <h3>Tu Número de Participante</h3>
                    <div class='number'>" . htmlspecialchars($numeroParticipante) . "</div>
                </div>
                
                <div class='info-box'>
                    <h3>📋 Tu Información</h3>
                    <div class='detail-row'>
                        <span class='label'>Talla de Playera:</span>
                        <span class='value'>" . htmlspecialchars($tallPlayera) . "</span>
                    </div>
                </div>
                
                <div class='info-box'>
                    <h3>📅 Información del Evento</h3>
                    <div class='detail-row'>
                        <span class='label'>Evento:</span>
                        <span class='value'>" . htmlspecialchars($evento['Nombre_Evento']) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Fecha:</span>
                        <span class='value'>" . date('d/m/Y', strtotime($evento['Fecha_Evento'])) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Hora de Salida:</span>
                        <span class='value'>" . htmlspecialchars($evento['Hora_Inicio']) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='label'>Lugar de Salida:</span>
                        <span class='value'>" . htmlspecialchars($evento['Lugar_Salida']) . "</span>
                    </div>
                    " . (!empty($evento['Hora_Salida']) ? "
                    <div class='detail-row'>
                        <span class='label'>Hora Estimada de Llegada:</span>
                        <span class='value'>" . htmlspecialchars($evento['Hora_Salida']) . "</span>
                    </div>
                    " : "") . "
                </div>
                
                <div class='info-box' style='background: #e8f4f8; border-left-color: #0066b3;'>
                    <h3 style='color: #004b87;'>📝 Información Importante</h3>
                    <ul style='margin: 15px 0; padding-left: 20px;'>
                        <li>Presenta este correo en el evento como confirmación</li>
                        <li>Tu número de participante: <strong>" . htmlspecialchars($numeroParticipante) . "</strong></li>
                        <li>Llega con al menos 30 minutos de anticipación</li>
                        <li>Trae tu cédula de identidad (DPI)</li>
                        <li>Mantente hidratado durante el evento</li>
                    </ul>
                </div>
                
                <p style='margin-top: 30px; text-align: center; font-size: 16px; color: #004b87;'>
                    <strong>¡Gracias por participar en nuestro evento!</strong>
                </p>
            </div>
            <div class='footer'>
                <p><strong>Club Rotario Coatepeque - Colomba</strong></p>
                <p>Costa Cuca</p>
                <p style='margin-top: 10px;'>Transformando vidas a través de la acción rotaria</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Enviar usando mail() de PHP
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@clubrotariocoatepeque.org\r\n";
    $headers .= "Reply-To: inscripciones@clubrotariocoatepeque.org\r\n";
    
    return mail($email, $asunto, $mensaje_html, $headers);
}

// Procesar acciones de autorización o negación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inscripcion_id = $_POST['inscripcion_id'] ?? null;
    $accion = $_POST['accion'] ?? null;
    $comentario = $_POST['comentario'] ?? '';
    
    if ($inscripcion_id && $accion) {
        // Obtener datos de la inscripción con detalles del evento
        $stmt = $pdo->prepare("
            SELECT ie.*, 
                   e.Nombre_Evento, 
                   e.Fecha_Evento,
                   e.Hora_Inicio,
                   e.Hora_Salida,
                   e.Lugar_Salida,
                   bpe.Id_Boleta, 
                   bpe.Ruta_Archivo
            FROM Inscripciones_Evento ie
            INNER JOIN Eventos e ON ie.Id_Evento = e.Id_Evento
            LEFT JOIN Boletas_Pago_Evento bpe ON ie.Id_Inscripcion = bpe.Inscripcion_Id
            WHERE ie.Id_Inscripcion = ?
        ");
        $stmt->execute([$inscripcion_id]);
        $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inscripcion) {
            if ($accion === 'autorizar') {
                // Actualizar inscripción
                $stmt_upd = $pdo->prepare("
                    UPDATE Inscripciones_Evento 
                    SET Estado_Pago = 'Aprobado', 
                        Estado_Inscripcion = 'Confirmado',
                        Observaciones = ?
                    WHERE Id_Inscripcion = ?
                ");
                $stmt_upd->execute([$comentario, $inscripcion_id]);
                
                // Actualizar boleta
                $stmt_boleta = $pdo->prepare("
                    UPDATE Boletas_Pago_Evento 
                    SET Estado_Verificacion = 'Aprobado',
                        Fecha_Verificacion = NOW(),
                        Usuario_Verificador = 1
                    WHERE Inscripcion_Id = ?
                ");
                $stmt_boleta->execute([$inscripcion_id]);
                
                // Enviar correo al participante
                $evento = [
                    'Nombre_Evento' => $inscripcion['Nombre_Evento'],
                    'Fecha_Evento' => $inscripcion['Fecha_Evento'],
                    'Hora_Inicio' => $inscripcion['Hora_Inicio'],
                    'Hora_Salida' => $inscripcion['Hora_Salida'],
                    'Lugar_Salida' => $inscripcion['Lugar_Salida']
                ];
                
                $correoEnviado = enviarCorreoParticipante(
                    $inscripcion['Email'],
                    $inscripcion['Nombre_Completo'],
                    $inscripcion['Numero_Participante'],
                    $inscripcion['Talla_Playera'],
                    $evento
                );
                
                if ($correoEnviado) {
                    $alerta = ['tipo' => 'success', 'mensaje' => '✓ Inscripción autorizada y correo enviado al participante'];
                } else {
                    $alerta = ['tipo' => 'success', 'mensaje' => '✓ Inscripción autorizada (Nota: El correo no pudo enviarse)'];
                }
                
            } elseif ($accion === 'negar') {
                // Actualizar inscripción
                $stmt_upd = $pdo->prepare("
                    UPDATE Inscripciones_Evento 
                    SET Estado_Pago = 'Rechazado', 
                        Estado_Inscripcion = 'Cancelado',
                        Observaciones = ?
                    WHERE Id_Inscripcion = ?
                ");
                $stmt_upd->execute([$comentario, $inscripcion_id]);
                
                // Actualizar boleta
                $stmt_boleta = $pdo->prepare("
                    UPDATE Boletas_Pago_Evento 
                    SET Estado_Verificacion = 'Rechazado',
                        Comentario_Rechazo = ?,
                        Fecha_Verificacion = NOW(),
                        Usuario_Verificador = 1
                    WHERE Inscripcion_Id = ?
                ");
                $stmt_boleta->execute([$comentario, $inscripcion_id]);
                
                $alerta = ['tipo' => 'error', 'mensaje' => '✗ Inscripción negada correctamente'];
            }
        }
    }
}

// Obtener inscripciones pendientes con información completa
$query = "
    SELECT 
        ie.Id_Inscripcion,
        ie.Numero_Participante,
        ie.Nombre_Completo,
        ie.Email,
        ie.Telefono,
        ie.Edad,
        ie.Genero,
        ie.DPI,
        ie.Direccion,
        ie.Talla_Playera,
        ie.Contacto_Emergencia,
        ie.Telefono_Emergencia,
        ie.Fecha_Inscripcion,
        ie.Estado_Pago,
        ie.Estado_Inscripcion,
        ie.Monto_Pagado,
        e.Nombre_Evento,
        e.Fecha_Evento,
        c.Nombre_Categoria,
        co.Tipo_Inscripcion,
        bpe.Id_Boleta,
        bpe.Ruta_Archivo,
        bpe.Tipo_Archivo,
        bpe.Fecha_Subida,
        bpe.Estado_Verificacion
    FROM Inscripciones_Evento ie
    INNER JOIN Eventos e ON ie.Id_Evento = e.Id_Evento
    INNER JOIN Categorias_Evento c ON ie.Id_Categoria = c.Id_Categoria
    INNER JOIN Costos_Inscripcion co ON ie.Id_Costo = co.Id_Costo
    LEFT JOIN Boletas_Pago_Evento bpe ON ie.Id_Inscripcion = bpe.Inscripcion_Id
    WHERE ie.Estado_Inscripcion IN ('Pendiente', 'Confirmado')
    ORDER BY ie.Fecha_Inscripcion DESC
";

$stmt = $pdo->query($query);
$inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Estado_Inscripcion = 'Confirmado' THEN 1 ELSE 0 END) as confirmados,
        SUM(CASE WHEN Estado_Inscripcion = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN Estado_Inscripcion = 'Cancelado' THEN 1 ELSE 0 END) as cancelados,
        SUM(CASE WHEN Estado_Pago = 'Aprobado' THEN 1 ELSE 0 END) as pagos_aprobados,
        SUM(CASE WHEN Estado_Pago = 'Pendiente' THEN 1 ELSE 0 END) as pagos_pendientes
    FROM Inscripciones_Evento
";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisión de Inscripciones - Admin Eventos</title>
   <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background-color: #f5f5f5;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #004b87 0%, #003d6e 100%);
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 0 20px;
        }

        .logo {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .club-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .club-location {
            font-size: 14px;
            opacity: 0.9;
        }

        .menu {
            list-style: none;
        }

        .menu-item {
            transition: background 0.3s;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #ffa500;
        }

        .menu-icon {
            font-size: 18px;
        }

        .logout-section {
            margin-top: 30px;
            padding: 0 30px;
        }

        .logout-btn {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 69, 0, 0.8);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 69, 0, 1);
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header h1 {
            color: #004b87;
            font-size: 32px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #004b87, #0066b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            color: #004b87;
            font-weight: 600;
            font-size: 15px;
        }

        .user-role {
            color: #666;
            font-size: 13px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .alert.success {
            background-color: white;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert.error {
            background-color: white;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            font-size: 36px;
            color: #004b87;
            margin-bottom: 8px;
        }

        .stat-card p {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .table-section h3 {
            color: #004b87;
            font-size: 22px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        thead {
            background-color: #004b87;
            color: white;
        }

        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 18px 12px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-badge.pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.confirmado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-badge.aprobado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rechazado {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            margin: 2px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-view {
            background-color: #004b87;
            color: white;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            width: 90%;
            max-width: 900px;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, #004b87 0%, #0066b3 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0;
        }

        .modal-header h2 {
            font-size: 24px;
        }

        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .close:hover {
            transform: scale(1.1);
        }

        .modal-body {
            padding: 30px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #004b87;
        }

        .detail-label {
            font-weight: 600;
            color: #004b87;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .detail-value {
            color: #333;
            font-size: 15px;
        }

        .image-container {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }

        .image-container img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            margin-top: 10px;
            font-size: 14px;
        }

        .textarea:focus {
            outline: none;
            border-color: #004b87;
            box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding: 25px 30px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }

        .btn-large {
            padding: 12px 30px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; padding: 20px; }
            .detail-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 24px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo-section">
                <div class="logo">📋</div>
                <div class="club-name">Club Rotario</div>
                <div class="club-location">Coatepeque - Colomba</div>
            </div>
            <ul class="menu">
    <li class="menu-item">
                    <a href="dashboard.php">
                        <span class="menu-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="aplicaciones.php">
                        <span class="menu-icon">📋</span>
                        <span>Aplicaciones</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="nueva_evaluacion.php">
                        <span class="menu-icon">📝</span>
                        <span>Nueva Evaluación</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="estudiantes.php">
                        <span class="menu-icon">👥</span>
                        <span>Estudiantes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="ad21k.php">
                        <span class="menu-icon">🏃</span>
                        <span>Carrera 21K</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="Crear_Evento.php">
                        <span class="menu-icon">📅</span>
                        <span>Crear Eventos</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reportes.php">
                        <span class="menu-icon">📊</span>
                        <span>Reportes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="configuracion.php">
                        <span class="menu-icon">⚙️</span>
                        <span>Configuración</span>
                    </a>
                </li>
            </ul>
                        <div class="logout-section">
                <button class="logout-btn" onclick="cerrarSesion()">
                    🚪 Cerrar Sesión
                </button>
            </div>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1>Revisión de Inscripciones a Eventos</h1>
            </div>

            <?php if (isset($alerta)): ?>
            <div class="alert <?= $alerta['tipo'] ?>">
                <span><?= $alerta['tipo'] === 'success' ? '✓' : '⚠️' ?></span>
                <span><?= $alerta['mensaje'] ?></span>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Inscripciones</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['confirmados'] ?></h3>
                    <p>Confirmadas</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['pendientes'] ?></h3>
                    <p>Pendientes</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['pagos_aprobados'] ?></h3>
                    <p>Pagos Aprobados</p>
                </div>
            </div>

            <div class="table-section">
                <h3>Inscripciones Registradas</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Nombre</th>
                                <th>Evento</th>
                                <th>Categoría</th>
                                <th>Correo</th>
                                <th>Estado Inscripción</th>
                                <th>Estado Pago</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscripciones as $insc): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($insc['Numero_Participante']) ?></strong></td>
                                <td><?= htmlspecialchars($insc['Nombre_Completo']) ?></td>
                                <td><?= htmlspecialchars($insc['Nombre_Evento']) ?></td>
                                <td><?= htmlspecialchars($insc['Nombre_Categoria']) ?></td>
                                <td><?= htmlspecialchars($insc['Email']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($insc['Estado_Inscripcion']) ?>">
                                        <?= ucfirst($insc['Estado_Inscripcion']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= strtolower($insc['Estado_Pago']) ?>">
                                        <?= ucfirst($insc['Estado_Pago']) ?>
                                    </span>
                                </td>
                                <td>Q<?= number_format($insc['Monto_Pagado'], 2) ?></td>
                                <td>
                                    <button class="btn btn-view" onclick="verDetalle(<?= $insc['Id_Inscripcion'] ?>)">
                                        Ver
                                    </button>
                                    <?php if ($insc['Estado_Inscripcion'] === 'Pendiente'): ?>
                                    <button class="btn btn-approve" onclick="openApproveForm(<?= $insc['Id_Inscripcion'] ?>)">
                                        Aprobar
                                    </button>
                                    <button class="btn btn-reject" onclick="openRejectForm(<?= $insc['Id_Inscripcion'] ?>)">
                                        Negar
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Detalle -->
    <div id="modalDetalle" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalle de Inscripción</h2>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <!-- Modal Acción -->
    <div id="modalAccion" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalAccionTitle">Acción</h2>
                <span class="close" onclick="cerrarModalAccion()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formAccion" method="POST">
                    <input type="hidden" name="inscripcion_id" id="inscripcion_id">
                    <input type="hidden" name="accion" id="accion">
                    
                    <label for="comentario" style="display: block; margin-bottom: 10px; font-weight: 600; color: #004b87;">
                        Comentario (opcional):
                    </label>
                    <textarea name="comentario" id="comentario" class="textarea" placeholder="Ingresa un comentario sobre esta acción..."></textarea>
                    
                    <div class="modal-actions">
                        <button type="submit" class="btn btn-large" id="btnSubmit" style="background-color: #004b87; color: white;">
                            Confirmar
                        </button>
                        <button type="button" class="btn btn-large" onclick="cerrarModalAccion()" style="background-color: #999; color: white;">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const inscripcionesData = <?= json_encode($inscripciones) ?>;

        function verDetalle(id) {
            const insc = inscripcionesData.find(i => i.Id_Inscripcion == id);
            if (!insc) return;

            const modalBody = document.getElementById('modalBody');
            
            let html = `
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Número de Participante</div>
                        <div class="detail-value">${insc.Numero_Participante}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Nombre Completo</div>
                        <div class="detail-value">${insc.Nombre_Completo}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Evento</div>
                        <div class="detail-value">${insc.Nombre_Evento}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Categoría</div>
                        <div class="detail-value">${insc.Nombre_Categoria}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Edad</div>
                        <div class="detail-value">${insc.Edad} años</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Género</div>
                        <div class="detail-value">${insc.Genero}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">DPI</div>
                        <div class="detail-value">${insc.DPI}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Talla de Playera</div>
                        <div class="detail-value">${insc.Talla_Playera}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Teléfono</div>
                        <div class="detail-value">${insc.Telefono}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">${insc.Email}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Dirección</div>
                        <div class="detail-value">${insc.Direccion}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Contacto de Emergencia</div>
                        <div class="detail-value">${insc.Contacto_Emergencia} (${insc.Telefono_Emergencia})</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Monto Pagado</div>
                        <div class="detail-value">Q${parseFloat(insc.Monto_Pagado).toFixed(2)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Fecha de Inscripción</div>
                        <div class="detail-value">${new Date(insc.Fecha_Inscripcion).toLocaleDateString('es-ES')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Estado Pago</div>
                        <div class="detail-value">
                            <span class="status-badge ${insc.Estado_Pago.toLowerCase()}">
                                ${insc.Estado_Pago.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Estado Inscripción</div>
                        <div class="detail-value">
                            <span class="status-badge ${insc.Estado_Inscripcion.toLowerCase()}">
                                ${insc.Estado_Inscripcion.toUpperCase()}
                            </span>
                        </div>
                    </div>
                </div>
            `;

            if (insc.Ruta_Archivo) {
                html += `
                    <div class="image-container">
                        <h3 style="color: #004b87; margin-bottom: 15px;">Boleta de Pago</h3>
                        <p style="margin-bottom: 10px; color: #666;">Archivo: ${insc.Nombre_Archivo}</p>
                        <p style="margin-bottom: 20px; color: #666;">Fecha de subida: ${new Date(insc.Fecha_Subida).toLocaleDateString('es-ES')}</p>
                        ${getImagePreview(insc.Ruta_Archivo, insc.Tipo_Archivo)}
                    </div>
                `;
            }

            modalBody.innerHTML = html;
            document.getElementById('modalDetalle').style.display = 'block';
        }

        function getImagePreview(ruta, tipo) {
            const extension = tipo.toLowerCase();
            if (extension.includes('image') || extension.includes('jpg') || extension.includes('jpeg') || extension.includes('png')) {
                return `<img src="${ruta}" alt="Boleta" style="max-width: 400px; border-radius: 8px;">`;
            } else if (extension.includes('pdf')) {
                return `<embed src="${ruta}" type="application/pdf" width="100%" height="500px" style="border-radius: 8px;">`;
            } else {
                return `<p><a href="${ruta}" target="_blank" class="btn btn-view">Descargar archivo</a></p>`;
            }
        }

        function openApproveForm(id) {
            document.getElementById('inscripcion_id').value = id;
            document.getElementById('accion').value = 'autorizar';
            document.getElementById('modalAccionTitle').textContent = 'Autorizar Inscripción';
            document.getElementById('btnSubmit').textContent = 'Autorizar';
            document.getElementById('btnSubmit').style.backgroundColor = '#28a745';
            document.getElementById('comentario').value = '';
            document.getElementById('modalAccion').style.display = 'block';
        }

        function openRejectForm(id) {
            document.getElementById('inscripcion_id').value = id;
            document.getElementById('accion').value = 'negar';
            document.getElementById('modalAccionTitle').textContent = 'Negar Inscripción';
            document.getElementById('btnSubmit').textContent = 'Negar';
            document.getElementById('btnSubmit').style.backgroundColor = '#dc3545';
            document.getElementById('comentario').value = '';
            document.getElementById('modalAccion').style.display = 'block';
        }

        function cerrarModal() {
            document.getElementById('modalDetalle').style.display = 'none';
        }

        function cerrarModalAccion() {
            document.getElementById('modalAccion').style.display = 'none';
        }

        window.onclick = function(event) {
            const modalDetalle = document.getElementById('modalDetalle');
            const modalAccion = document.getElementById('modalAccion');
            
            if (event.target == modalDetalle) cerrarModal();
            if (event.target == modalAccion) cerrarModalAccion();
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModal();
                cerrarModalAccion();
            }
        });

        document.getElementById('formAccion').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
    </script>
</body>
</html>