<?php
require_once 'conexion.php';

if (!isset($_GET['cita']) || empty($_GET['cita'])) {
    header('Location: index.php');
    exit;
}

$id_cita = intval($_GET['cita']);

try {
    $sql = "SELECT 
                c.Fecha_Cita,
                c.Hora_Cita,
                c.Lugar_Entrevista,
                c.Observaciones,
                e.Nombres_Apellidos,
                e.Email,
                e.Telefono,
                e.Id_Estudiante,
                ev.Id_Evaluacion
            FROM Citas_Entrevista c
            INNER JOIN Estudiantes e ON c.Id_Estudiante = e.Id_Estudiante
            INNER JOIN Evaluaciones_Socioeconomicas ev ON c.Id_Evaluacion = ev.Id_Evaluacion
            WHERE c.Id_Cita = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_cita]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        throw new Exception('Cita no encontrada');
    }

    // Formatear fecha y hora
    $meses = [
        'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 
        'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
        'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
        'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
    ];
    
    $dias = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 
        'Sunday' => 'Domingo'
    ];

    $fecha_obj = new DateTime($cita['Fecha_Cita']);
    $dia_semana = $dias[$fecha_obj->format('l')];
    $dia = $fecha_obj->format('d');
    $mes = $meses[$fecha_obj->format('F')];
    $anio = $fecha_obj->format('Y');
    $fecha_formateada = "$dia_semana, $dia de $mes de $anio";

    $hora_obj = new DateTime($cita['Hora_Cita']);
    $hora_formateada = $hora_obj->format('g:i A');
    
    $numero_referencia = "BECA-" . str_pad($cita['Id_Estudiante'], 6, '0', STR_PAD_LEFT);
    
} catch (Exception $e) {
    error_log("Error en confirmación: " . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud Confirmada - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1a3a5f 0%, #0f2a47 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 750px;
            width: 100%;
        }

        /* Animación del checkmark */
        .success-animation {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .checkmark-container {
            display: inline-block;
            position: relative;
        }

        .checkmark {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: block;
            stroke-width: 3;
            stroke: #fff;
            stroke-miterlimit: 10;
            box-shadow: inset 0 0 0 #7ac142;
            animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
        }

        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 3;
            stroke-miterlimit: 10;
            stroke: #7ac142;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }

        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }

        @keyframes fill {
            100% { box-shadow: inset 0 0 0 60px #7ac142; }
        }

        /* Card principal */
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-header {
            background: linear-gradient(135deg, #1a3a5f 0%, #0f2a47 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .card-header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .card-header p {
            font-size: 1.1em;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .card-body {
            padding: 40px 30px;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section h2 {
            color: #333;
            font-size: 1.4em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-section h2 i {
            color: #d4af37;
            font-size: 1.2em;
        }

        /* Box de la cita destacado */
        .cita-box {
            background: linear-gradient(135deg, #e7f3ff 0%, #fff7e6 100%);
            padding: 30px;
            border-radius: 15px;
            margin: 25px 0;
            border: 2px solid #d4af37;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
            position: relative;
            overflow: hidden;
        }

        .cita-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: #d4af37;
        }

        .cita-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 18px;
            font-size: 1.1em;
            padding: 12px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .cita-item:hover {
            transform: translateX(5px);
        }

        .cita-item:last-child {
            margin-bottom: 0;
        }

        .cita-item i {
            font-size: 1.5em;
            color: #1a3a5f;
            width: 35px;
            text-align: center;
        }

        .cita-item strong {
            color: #333;
            min-width: 90px;
        }

        .cita-item span {
            color: #555;
            flex: 1;
        }

        /* Número de referencia destacado */
        .reference-box {
            background: linear-gradient(135deg, #d4af37 0%, #e6c86e 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 25px 0;
            border: 2px dashed #b8941f;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }

        .reference-box h3 {
            color: #1a3a5f;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .reference-number {
            font-size: 1.8em;
            font-weight: 700;
            color: white;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Lista de documentos mejorada */
        .documents-list {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-top: 15px;
        }

        .documents-list ul {
            list-style: none;
            padding: 0;
        }

        .documents-list li {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.2s ease;
        }

        .documents-list li:hover {
            background: rgba(26, 58, 95, 0.05);
            padding-left: 10px;
        }

        .documents-list li:last-child {
            border-bottom: none;
        }

        .documents-list li i {
            color: #1a3a5f;
            font-size: 1.2em;
        }

        /* Alertas mejoradas */
        .alert {
            padding: 18px 22px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .alert-warning {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            color: #856404;
        }

        .alert-info {
            background: #e3f2fd;
            border-left: 4px solid #1a3a5f;
            color: #0d47a1;
        }

        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }

        .alert i {
            font-size: 1.5em;
            margin-top: 2px;
        }

        .alert ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }

        .alert li {
            margin: 8px 0;
        }

        /* Contact box */
        .contact-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-top: 30px;
            border: 1px solid #dee2e6;
        }

        .contact-box h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .contact-item {
            margin: 12px 0;
            color: #555;
            font-size: 1.05em;
        }

        .contact-item i {
            color: #1a3a5f;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .contact-item a {
            color: #1a3a5f;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .contact-item a:hover {
            color: #d4af37;
            text-decoration: underline;
        }

        /* Botones mejorados */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 16px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a3a5f 0%, #0f2a47 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(26, 58, 95, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #1a3a5f;
            border: 2px solid #1a3a5f;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .print-btn {
            background: linear-gradient(135deg, #d4af37 0%, #b8941f 100%);
            color: white;
        }

        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .button-group,
            .no-print {
                display: none !important;
            }

            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .success-animation {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .card-header h1 {
                font-size: 1.8em;
            }

            .card-body {
                padding: 25px 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .cita-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .cita-item i {
                font-size: 1.3em;
            }

            .reference-number {
                font-size: 1.4em;
            }
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-animation no-print">
            <div class="checkmark-container">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                    <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-check-circle"></i> ¡Solicitud Enviada Exitosamente!</h1>
                <p>Tu solicitud ha sido registrada en nuestro sistema</p>
            </div>

            <div class="card-body">
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Estimado/a <?= htmlspecialchars($cita['Nombres_Apellidos']) ?>,</strong>
                        <p style="margin-top: 8px;">Hemos recibido tu solicitud de beca exitosamente. Se ha enviado un correo de confirmación a <strong><?= htmlspecialchars($cita['Email']) ?></strong> con todos los detalles de tu cita.</p>
                    </div>
                </div>

                <div class="reference-box fade-in">
                    <h3><i class="fas fa-barcode"></i> Número de Referencia</h3>
                    <div class="reference-number"><?= $numero_referencia ?></div>
                    <p style="margin-top: 10px; color: #1a3a5f; font-size: 0.9em; font-weight: 600;">Guarda este número para futuras consultas</p>
                </div>

                <div class="alert alert-info fade-in">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <strong>📅 Sistema de Citas Automáticas</strong>
                        <p style="margin-top: 8px;">Tu cita fue asignada automáticamente en el próximo horario disponible. Las citas se programan:</p>
                        <ul>
                            <li><strong>De Lunes a Viernes</strong> (sin atención los fines de semana)</li>
                            <li>Horario: <strong>9:00 AM - 1:00 PM</strong></li>
                            <li>Duración de cada entrevista: <strong>40 minutos</strong></li>
                            <li>Intervalos entre citas: <strong>40 minutos</strong></li>
                        </ul>
                    </div>
                </div>

                <div class="info-section fade-in">
                    <h2><i class="fas fa-calendar-alt"></i> Tu Cita de Entrevista</h2>
                    <div class="cita-box">
                        <div class="cita-item">
                            <i class="fas fa-calendar-day"></i>
                            <strong>Fecha:</strong>
                            <span><?= $fecha_formateada ?></span>
                        </div>
                        <div class="cita-item">
                            <i class="fas fa-clock"></i>
                            <strong>Hora:</strong>
                            <span><?= $hora_formateada ?></span>
                        </div>
                        <div class="cita-item">
                            <i class="fas fa-hourglass-half"></i>
                            <strong>Duración:</strong>
                            <span>40 minutos aproximadamente</span>
                        </div>
                        <div class="cita-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <strong>Lugar:</strong>
                            <span><?= htmlspecialchars($cita['Lugar_Entrevista']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning fade-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>¡Importante! Recomendaciones para tu entrevista:</strong>
                        <ul>
                            <li>Llega <strong>10 minutos antes</strong> de tu cita</li>
                            <li>Si no puedes asistir, <strong>comunícate con anticipación</strong></li>
                            <li>La entrevista dura aproximadamente <strong>40 minutos</strong></li>
                            <li>Trae todos los documentos <strong>originales y copias</strong></li>
                            <li>Viste de manera <strong>formal y presentable</strong></li>
                            <li>Asiste acompañado de tu <strong>padre, madre o encargado</strong></li>
                        </ul>
                    </div>
                </div>

                <div class="info-section fade-in">
                    <h2><i class="fas fa-folder-open"></i> Documentos Requeridos</h2>
                    <p style="margin-bottom: 15px; color: #666;">Por favor lleva los siguientes documentos el día de tu entrevista:</p>
                    <div class="documents-list">
                        <ul>
                            <li><i class="fas fa-file-alt"></i> Boleta de calificaciones más reciente</li>
                            <li><i class="fas fa-certificate"></i> Certificado de nacimiento (original y copia)</li>
                            <li><i class="fas fa-id-card"></i> DPI del estudiante y encargado (original y copia)</li>
                            <li><i class="fas fa-money-bill-wave"></i> Comprobante de ingresos familiar</li>
                            <li><i class="fas fa-receipt"></i> Recibos de servicios básicos (agua, luz)</li>
                            <li><i class="fas fa-home"></i> Constancia de residencia (si es posible)</li>
                            <li><i class="fas fa-file-pdf"></i> Cotizacion e informacion de costo de centro estudiantil</li>
                            <li><i class="fas fa-file-pdf"></i> Cualquier otro documento que respalde tu solicitud</li>
                        </ul>
                    </div>
                </div>

                <div class="contact-box fade-in">
                    <h3><i class="fas fa-question-circle"></i> ¿Tienes Preguntas o Dudas?</h3>
                    <p style="margin-bottom: 15px; color: #666;">No dudes en contactarnos</p>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        Email: <a href="mailto:becas@rotariocoatepeque.org">becas@rotariocoatepeque.org</a>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        Teléfono: <a href="tel:12345678">1234-5678</a>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        Dirección: Oficinas Club Rotario Coatepeque-Colomba
                    </div>
                    <div class="contact-item" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <i class="fas fa-clock"></i>
                        Horario de atención: <strong>Lunes a Viernes, 9:00 AM - 1:00 PM</strong>
                    </div>
                </div>

                <div class="button-group no-print">
                    <button onclick="window.print()" class="btn print-btn">
                        <i class="fas fa-print"></i> Imprimir Confirmación
                    </button>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animación de entrada para elementos con clase fade-in
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(20px)';
                    element.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 150);
            });

            // Copiar número de referencia al hacer clic
            const referenceNumber = document.querySelector('.reference-number');
            if (referenceNumber) {
                referenceNumber.style.cursor = 'pointer';
                referenceNumber.title = 'Haz clic para copiar';
                
                referenceNumber.addEventListener('click', function() {
                    const text = this.textContent;
                    navigator.clipboard.writeText(text).then(function() {
                        const originalText = referenceNumber.textContent;
                        referenceNumber.textContent = '✓ Copiado!';
                        referenceNumber.style.color = '#28a745';
                        
                        setTimeout(() => {
                            referenceNumber.textContent = originalText;
                            referenceNumber.style.color = 'white';
                        }, 2000);
                    }).catch(function() {
                        alert('Número de referencia: ' + text);
                    });
                });
            }
        });
    </script>
</body>
</html>