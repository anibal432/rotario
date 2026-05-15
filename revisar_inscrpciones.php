<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'conexion.php';

// ============================================
// CARGAR SISTEMA DE CORREOS
// ============================================
$emailConfigExists = false;
if (file_exists(__DIR__ . '/config_email.php')) {
    try {
        require_once __DIR__ . '/config_email.php';
        $emailConfigExists = function_exists('enviarCorreo');
        if ($emailConfigExists) {
            error_log("✓ Sistema de correos cargado correctamente");
        }
    } catch (Exception $e) {
        error_log("⚠ Error cargando config_email.php: " . $e->getMessage());
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

// ============================================
// FUNCIONES DE FORMATEO
// ============================================

/**
 * Formatear fecha en texto legible en español
 */
function formatearFechaTexto($fecha) {
    $dias_semana = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
    ];
    
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    
    $fecha_obj = new DateTime($fecha);
    $dia_semana = $dias_semana[$fecha_obj->format('w')];
    $dia = $fecha_obj->format('d');
    $mes = $meses[intval($fecha_obj->format('m'))];
    $anio = $fecha_obj->format('Y');
    
    return "$dia_semana, $dia de $mes de $anio";
}

/**
 * Formatear hora en formato 12 horas con AM/PM
 */
function formatearHoraTexto($hora) {
    if (empty($hora)) return 'No especificada';
    $hora_obj = new DateTime($hora);
    return $hora_obj->format('g:i A');
}

/**
 * Generar HTML del correo de confirmación de inscripción
 */
function generarCorreoInscripcionAprobada($datos_participante, $datos_evento) {
    $nombre = htmlspecialchars($datos_participante['nombre']);
    $numero_participante = htmlspecialchars($datos_participante['numero_participante']);
    $categoria = htmlspecialchars($datos_participante['categoria']);
    $monto = number_format($datos_participante['monto'], 2);
    
    $nombre_evento = htmlspecialchars($datos_evento['nombre']);
    $fecha_evento = formatearFechaTexto($datos_evento['fecha']);
    $hora_inicio = formatearHoraTexto($datos_evento['hora_inicio']);
    $hora_salida = formatearHoraTexto($datos_evento['hora_salida']);
    $lugar_salida = htmlspecialchars($datos_evento['lugar_salida'] ?? 'Por confirmar');
    $descripcion = htmlspecialchars($datos_evento['descripcion'] ?? '');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .header { background: linear-gradient(135deg, #1a3a5f 0%, #0f2a47 100%); color: white; padding: 40px 30px; text-align: center; }
        .header h1 { margin: 0 0 10px 0; font-size: 28px; }
        .header p { margin: 0; font-size: 16px; opacity: 0.9; }
        .content { padding: 30px; }
        .success-icon { text-align: center; margin: 20px 0; font-size: 64px; }
        .info-box { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #28a745; }
        .evento-box { background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%); padding: 25px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #d4af37; }
        .detail-row { margin: 12px 0; display: flex; align-items: flex-start; }
        .detail-icon { margin-right: 10px; font-size: 18px; color: #667eea; }
        .detail-label { font-weight: 600; color: #1a3a5f; margin-right: 8px; }
        .detail-value { color: #555; }
        .highlight-box { background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #ffc107; }
        .participant-badge { display: inline-block; background: #d4af37; color: white; padding: 10px 20px; border-radius: 25px; font-size: 20px; font-weight: bold; margin: 20px 0; }
        .tips-section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tips-section h3 { color: #1a3a5f; font-size: 18px; margin-top: 0; margin-bottom: 15px; }
        .tips-section ul { margin: 0; padding-left: 20px; }
        .tips-section li { margin: 10px 0; color: #555; }
        .important-note { background: #ffe7e7; padding: 15px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #dc3545; color: #721c24; }
        .footer { text-align: center; padding: 25px; background: #f1f1f1; color: #666; font-size: 13px; }
        .footer p { margin: 5px 0; }
        .contact-info { margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 8px; text-align: center; }
        .social-icons { margin-top: 15px; }
        .social-icons a { display: inline-block; margin: 0 8px; color: #667eea; text-decoration: none; font-size: 20px; }
        .btn-primary { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 ¡Inscripción Aprobada!</h1>
            <p>Tu participación ha sido confirmada</p>
        </div>
        
        <div class="content">
            <div class="success-icon">✅</div>
            
            <p style="font-size: 16px; text-align: center;">Estimado/a <strong>$nombre</strong>,</p>
            
            <div class="info-box">
                <p style="margin: 0; font-size: 15px;">
                    ¡Felicidades! Tu inscripción ha sido <strong style="color: #28a745;">APROBADA</strong> 
                    y tu pago ha sido verificado exitosamente. Estamos emocionados de tenerte como parte de este evento.
                </p>
            </div>
            
            <div style="text-align: center; margin: 25px 0;">
                <span class="participant-badge"># $numero_participante</span>
                <p style="margin-top: 10px; color: #666; font-size: 14px;">Tu número de corredor</p>
            </div>

            <div class="evento-box">
                <h2 style="color: #1a3a5f; margin-top: 0; margin-bottom: 20px;">📅 Detalles del Evento</h2>
                
                <div class="detail-row">
                    <span class="detail-icon">🏃</span>
                    <div>
                        <span class="detail-label">Evento:</span>
                        <span class="detail-value">$nombre_evento</span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-icon">📆</span>
                    <div>
                        <span class="detail-label">Fecha:</span>
                        <span class="detail-value">$fecha_evento</span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-icon">🕐</span>
                    <div>
                        <span class="detail-label">Hora de Inicio:</span>
                        <span class="detail-value">$hora_inicio</span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-icon">🚀</span>
                    <div>
                        <span class="detail-label">Hora de Salida:</span>
                        <span class="detail-value">$hora_salida</span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-icon">📍</span>
                    <div>
                        <span class="detail-label">Punto de Salida:</span>
                        <span class="detail-value">$lugar_salida</span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-icon">🏆</span>
                    <div>
                        <span class="detail-label">Categoría:</span>
                        <span class="detail-value">$categoria</span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <span class="detail-icon">💰</span>
                    <div>
                        <span class="detail-label">Monto Pagado:</span>
                        <span class="detail-value">Q $monto</span>
                    </div>
                </div>
            </div>

            <div class="tips-section">
                <h3>📋 Información Importante</h3>
                <ul>
                    <li><strong>Llega temprano:</strong> Te recomendamos llegar al menos 30 minutos antes de la hora de salida</li>
                    <li><strong>Tu número de corredor:</strong> Se te entregará el día del evento junto con tu playera</li>
                    <li><strong>Hidratación:</strong> Mantente bien hidratado antes y durante el evento</li>
                    <li><strong>Documentos:</strong> Lleva tu DPI o documento de identificación</li>
                    <li><strong>Calentamiento:</strong> Realiza ejercicios de calentamiento antes de comenzar</li>
                </ul>
            </div>

            <div class="highlight-box">
                <h3 style="margin-top: 0; color: #856404;">⚠️ Recomendaciones del Día</h3>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Usa ropa y calzado cómodo y apropiado para correr</li>
                    <li>No olvides aplicar protector solar</li>
                    <li>Lleva una botella de agua</li>
                    <li>Sigue las indicaciones del personal del evento</li>
                </ul>
            </div>

            <div class="important-note">
                <strong>📢 Nota importante:</strong> Conserva este correo como comprobante de tu inscripción aprobada. 
                Si tienes alguna pregunta o necesitas realizar algún cambio, contáctanos lo antes posible.
            </div>

            <div class="contact-info">
                <h3 style="color: #1a3a5f; margin-top: 0;">📞 ¿Necesitas ayuda?</h3>
                <p><strong>Email:</strong> eventos@rotariocoatepeque.org</p>
                <p><strong>Teléfono:</strong> (502) 1234-5678</p>
                <p><strong>WhatsApp:</strong> +502 1234-5678</p>
                <p style="margin-top: 15px; font-size: 13px; color: #666;">
                    Horario de atención: Lunes a Viernes, 8:00 AM - 5:00 PM
                </p>
            </div>
            
            <p style="text-align: center; margin-top: 30px; font-size: 16px; color: #1a3a5f;">
                <strong>¡Te deseamos mucha suerte y éxito en el evento! 🏆</strong>
            </p>
        </div>
        
        <div class="footer">
            <p style="font-weight: bold; font-size: 14px; color: #1a3a5f;">&copy; 2025 Club Rotario Coatepeque Colomba</p>
            <p>Comprometidos con el desarrollo de nuestra comunidad</p>
            <p style="font-size: 11px; margin-top: 10px;">
                Este es un correo automático, por favor no respondas directamente a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

// ============================================
// PROCESAR ACCIONES DE AUTORIZACIÓN O NEGACIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inscripcion_id = $_POST['inscripcion_id'] ?? null;
    $accion = $_POST['accion'] ?? null;
    $comentario = $_POST['comentario'] ?? '';
    
    if ($inscripcion_id && $accion) {
        // Obtener datos completos de la inscripción y el evento
        $stmt = $pdo->prepare("
            SELECT ie.*, 
                   e.Nombre_Evento, 
                   e.Fecha_Evento,
                   e.Hora_Inicio,
                   e.Hora_Salida,
                   e.Lugar_Salida,
                   e.Descripcion,
                   c.Nombre_Categoria
            FROM Inscripciones_Evento ie
            INNER JOIN Eventos e ON ie.Id_Evento = e.Id_Evento
            INNER JOIN Categorias_Evento c ON ie.Id_Categoria = c.Id_Categoria
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
                        Usuario_Verificador = ?
                    WHERE Inscripcion_Id = ?
                ");
                $stmt_boleta->execute([$_SESSION['user_id'], $inscripcion_id]);
                
                // ============================================
                // ENVIAR CORREO DE CONFIRMACIÓN
                // ============================================
                $correo_enviado = false;
                $mensaje_correo = '';
                
                if ($emailConfigExists && !empty($inscripcion['Email'])) {
                    try {
                        error_log("→ Enviando correo de confirmación a: " . $inscripcion['Email']);
                        
                        // Preparar datos del participante
                        $datos_participante = [
                            'nombre' => $inscripcion['Nombre_Completo'],
                            'numero_participante' => $inscripcion['Numero_Participante'],
                            'categoria' => $inscripcion['Nombre_Categoria'],
                            'monto' => $inscripcion['Monto_Pagado']
                        ];
                        
                        // Preparar datos del evento
                        $datos_evento = [
                            'nombre' => $inscripcion['Nombre_Evento'],
                            'fecha' => $inscripcion['Fecha_Evento'],
                            'hora_inicio' => $inscripcion['Hora_Inicio'],
                            'hora_salida' => $inscripcion['Hora_Salida'],
                            'lugar_salida' => $inscripcion['Lugar_Salida'],
                            'descripcion' => $inscripcion['Descripcion']
                        ];
                        
                        // Generar HTML del correo
                        $mensaje_html = generarCorreoInscripcionAprobada($datos_participante, $datos_evento);
                        
                        // Enviar correo
                        $resultado_correo = enviarCorreo(
                            $inscripcion['Email'],
                            "¡Inscripción Aprobada! - " . $inscripcion['Nombre_Evento'],
                            $mensaje_html,
                            $inscripcion['Nombre_Completo']
                        );
                        
                        $correo_enviado = $resultado_correo['success'] ?? false;
                        $mensaje_correo = $resultado_correo['message'] ?? '';
                        
                        if ($correo_enviado) {
                            error_log("✓ Correo de confirmación enviado exitosamente");
                        } else {
                            error_log("⚠ No se pudo enviar el correo: $mensaje_correo");
                        }
                        
                    } catch (Exception $e) {
                        error_log("⚠ Error al enviar correo: " . $e->getMessage());
                        $mensaje_correo = $e->getMessage();
                    }
                }
                
                $mensaje_alerta = '✓ Inscripción autorizada correctamente';
                if ($correo_enviado) {
                    $mensaje_alerta .= ' y correo de confirmación enviado';
                } else if ($emailConfigExists) {
                    $mensaje_alerta .= ' (correo no pudo ser enviado)';
                }
                
                $alerta = ['tipo' => 'success', 'mensaje' => $mensaje_alerta];
                
            } elseif ($accion === 'negar') {
                $stmt_upd = $pdo->prepare("
                    UPDATE Inscripciones_Evento 
                    SET Estado_Pago = 'Rechazado', 
                        Estado_Inscripcion = 'Cancelado',
                        Observaciones = ?
                    WHERE Id_Inscripcion = ?
                ");
                $stmt_upd->execute([$comentario, $inscripcion_id]);
                
                $stmt_boleta = $pdo->prepare("
                    UPDATE Boletas_Pago_Evento 
                    SET Estado_Verificacion = 'Rechazado',
                        Comentario_Rechazo = ?,
                        Fecha_Verificacion = NOW(),
                        Usuario_Verificador = ?
                    WHERE Inscripcion_Id = ?
                ");
                $stmt_boleta->execute([$comentario, $_SESSION['user_id'], $inscripcion_id]);
                
                $alerta = ['tipo' => 'error', 'mensaje' => '✗ Inscripción negada correctamente'];
            }
        }
    }
}

// Filtros
$filtro_evento = $_GET['evento'] ?? null;
$filtro_estado = $_GET['estado'] ?? 'Pendiente';
$busqueda = $_GET['busqueda'] ?? '';

// Obtener eventos activos con estadísticas
$sql_eventos = "
    SELECT 
        e.Id_Evento,
        e.Nombre_Evento,
        e.Fecha_Evento,
        e.Estado_Evento,
        te.Nombre as Tipo_Evento_Nombre,
        YEAR(e.Fecha_Evento) as Año_Evento,
        MONTH(e.Fecha_Evento) as Mes_Evento,
        COUNT(ie.Id_Inscripcion) as total_inscripciones,
        SUM(CASE WHEN ie.Estado_Inscripcion = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN ie.Estado_Inscripcion = 'Confirmado' THEN 1 ELSE 0 END) as confirmados,
        SUM(CASE WHEN ie.Estado_Inscripcion = 'Cancelado' THEN 1 ELSE 0 END) as cancelados
    FROM Eventos e
    LEFT JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
    LEFT JOIN Inscripciones_Evento ie ON e.Id_Evento = ie.Id_Evento
    WHERE e.Estado_Evento IN ('Planificado', 'Inscripciones Abiertas', 'Inscripciones Cerradas')
    GROUP BY e.Id_Evento
    ORDER BY e.Fecha_Evento DESC, e.Nombre_Evento ASC
";
$stmt_eventos = $pdo->query($sql_eventos);
$todos_eventos = $stmt_eventos->fetchAll(PDO::FETCH_ASSOC);

// Agrupar eventos por año y mes
$eventos_por_periodo = [];
foreach ($todos_eventos as $evt) {
    $año = $evt['Año_Evento'];
    $mes = $evt['Mes_Evento'];
    
    if (!isset($eventos_por_periodo[$año])) {
        $eventos_por_periodo[$año] = [];
    }
    if (!isset($eventos_por_periodo[$año][$mes])) {
        $eventos_por_periodo[$año][$mes] = [];
    }
    
    $eventos_por_periodo[$año][$mes][] = $evt;
}

// Ordenar años descendente
krsort($eventos_por_periodo);

// Si hay eventos y no se ha seleccionado uno, seleccionar el primero con pendientes
if (empty($filtro_evento) && !empty($todos_eventos)) {
    foreach ($todos_eventos as $evt) {
        if ($evt['pendientes'] > 0) {
            $filtro_evento = $evt['Id_Evento'];
            break;
        }
    }
    if (empty($filtro_evento)) {
        $filtro_evento = $todos_eventos[0]['Id_Evento'];
    }
}

// Obtener inscripciones del evento seleccionado
$inscripciones = [];
$evento_seleccionado = null;
if ($filtro_evento) {
    foreach ($todos_eventos as $evt) {
        if ($evt['Id_Evento'] == $filtro_evento) {
            $evento_seleccionado = $evt;
            break;
        }
    }

    $sql = "SELECT 
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
            WHERE ie.Id_Evento = ?";

    $params = [$filtro_evento];

    if ($filtro_estado !== 'Todos') {
        $sql .= " AND ie.Estado_Inscripcion = ?";
        $params[] = $filtro_estado;
    }

    if (!empty($busqueda)) {
        $sql .= " AND (ie.Nombre_Completo LIKE ? OR ie.Email LIKE ? OR ie.Numero_Participante LIKE ?)";
        $busqueda_param = "%{$busqueda}%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }

    $sql .= " ORDER BY ie.Fecha_Inscripcion DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener estadísticas del evento seleccionado
$stats = ['total' => 0, 'confirmados' => 0, 'pendientes' => 0, 'cancelados' => 0];
if ($evento_seleccionado) {
    $stats['total'] = $evento_seleccionado['total_inscripciones'];
    $stats['pendientes'] = $evento_seleccionado['pendientes'];
    $stats['confirmados'] = $evento_seleccionado['confirmados'];
    $stats['cancelados'] = $evento_seleccionado['cancelados'];
}

// Función helper para nombre de mes
function nombreMes($num) {
    $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $meses[$num] ?? '';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Inscripciones - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/inscripciones.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Revisar Inscripciones</h1>
                <div class="user-info">
                    <div class="user-avatar-wrapper">
                        <?php
                            $iconClass = '';
                            switch ($role) {
                                case 'Administrador':
                                    $iconClass = 'fa-solid fa-crown';
                                    break;
                                case 'Coordinador':
                                    $iconClass = 'fa-solid fa-user-tie';
                                    break;
                                default:
                                    $iconClass = 'fa-solid fa-user';
                                    break;
                            }
                        ?>
                        <i class="<?= $iconClass ?> user-role-icon"></i>
                        <div class="user-avatar-main"><?= getInitials($username) ?></div>
                    </div>
                    <div class="user-details-main">
                        <div class="user-name-main"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role-main"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <?php if (isset($alerta)): ?>
            <div class="alert <?= $alerta['tipo'] ?>">
                <span><?= $alerta['tipo'] === 'success' ? '✓' : '⚠️' ?></span>
                <span><?= $alerta['mensaje'] ?></span>
            </div>
            <?php endif; ?>

            <!-- Timeline de Eventos por Año y Mes -->
            <?php if (count($eventos_por_periodo) > 0): ?>
            <div class="eventos-timeline">
                <?php foreach ($eventos_por_periodo as $año => $meses): ?>
                <div class="periodo-año">
                    <div class="año-header">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?= $año ?></span>
                        </div>
                        <div class="año-stats">
                            <?php
                                $total_año = 0;
                                $pendientes_año = 0;
                                foreach ($meses as $eventos_mes) {
                                    foreach ($eventos_mes as $evt) {
                                        $total_año += $evt['total_inscripciones'];
                                        $pendientes_año += $evt['pendientes'];
                                    }
                                }
                            ?>
                            <?= count($meses) ?> mes<?= count($meses) != 1 ? 'es' : '' ?> · 
                            <?php
                                $total_eventos_año = 0;
                                foreach ($meses as $eventos_mes) {
                                    $total_eventos_año += count($eventos_mes);
                                }
                            ?>
                            <?= $total_eventos_año ?> evento<?= $total_eventos_año != 1 ? 's' : '' ?> · 
                            <?= $total_año ?> inscripciones
                            <?php if ($pendientes_año > 0): ?>
                             · <i class="fas fa-exclamation-circle"></i> <?= $pendientes_año ?> pendiente<?= $pendientes_año != 1 ? 's' : '' ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php foreach ($meses as $mes => $eventos): ?>
                    <div class="mes-section">
                        <div class="mes-header">
                            <i class="fas fa-calendar-day"></i>
                            <?= nombreMes($mes) ?>
                            <span style="font-weight: 400; color: #666; font-size: 14px; margin-left: 10px;">
                                (<?= count($eventos) ?> evento<?= count($eventos) != 1 ? 's' : '' ?>)
                            </span>
                        </div>
                        
                        <div class="eventos-mes">
                            <?php foreach ($eventos as $evt): ?>
                            <?php
                                $estado_clase = strtolower(str_replace(' ', '_', $evt['Estado_Evento']));
                            ?>
                            <div class="evento-card <?= $filtro_evento == $evt['Id_Evento'] ? 'active' : '' ?>" 
                                 onclick="seleccionarEvento(<?= $evt['Id_Evento'] ?>)">
                                <?php if ($evt['pendientes'] > 0): ?>
                                <span class="badge-pendientes">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= $evt['pendientes'] ?>
                                </span>
                                <?php endif; ?>
                                
                                <h3>
                                    <i class="fas fa-running"></i>
                                    <?= htmlspecialchars($evt['Nombre_Evento']) ?>
                                </h3>
                                
                                <div class="evento-fecha-completa">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($evt['Fecha_Evento'])) ?>
                                </div>
                                
                                <?php if ($evt['Tipo_Evento_Nombre']): ?>
                                <div class="evento-tipo">
                                    <i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($evt['Tipo_Evento_Nombre']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="evento-stats-inline">
                                    <div class="stat-inline total">
                                        <span class="number"><?= $evt['total_inscripciones'] ?></span>
                                        <span class="label">total</span>
                                    </div>
                                    <div class="stat-inline pendiente">
                                        <span class="number"><?= $evt['pendientes'] ?></span>
                                        <span class="label">pendientes</span>
                                    </div>
                                    <div class="stat-inline confirmado">
                                        <span class="number"><?= $evt['confirmados'] ?></span>
                                        <span class="label">confirmados</span>
                                    </div>
                                </div>

                                <span class="badge-estado-evento badge-<?= $estado_clase ?>">
                                    <?= $evt['Estado_Evento'] ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-eventos">
                <i class="fas fa-calendar-times"></i>
                <h3>No hay eventos activos</h3>
                <p>No se encontraron eventos con inscripciones disponibles</p>
            </div>
            <?php endif; ?>

            <?php if ($filtro_evento && $evento_seleccionado): ?>
            <!-- Estadísticas del evento seleccionado -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Inscripciones</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['confirmados'] ?></h3>
                    <p>Confirmados</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['pendientes'] ?></h3>
                    <p>Pendientes</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['cancelados'] ?></h3>
                    <p>Cancelados</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filtros">
                <form method="GET" action="">
                    <input type="hidden" name="evento" value="<?= $filtro_evento ?>">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label><i class="fas fa-search"></i> Buscar Participante</label>
                            <input type="text" name="busqueda" placeholder="Nombre, email o número..." value="<?= htmlspecialchars($busqueda) ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Estado</label>
                            <select name="estado">
                                <option value="Todos" <?= $filtro_estado === 'Todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                <option value="Confirmado" <?= $filtro_estado === 'Confirmado' ? 'selected' : '' ?>>Confirmados</option>
                                <option value="Cancelado" <?= $filtro_estado === 'Cancelado' ? 'selected' : '' ?>>Cancelados</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>

                        <?php if (!empty($busqueda) || $filtro_estado !== 'Pendiente'): ?>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="?evento=<?= $filtro_evento ?>" class="btn" style="background: #6c757d; color: white; text-decoration: none;">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabla de Inscripciones -->
            <div class="table-section">
                <h3>
                    <?= htmlspecialchars($evento_seleccionado['Nombre_Evento']) ?> - Inscripciones 
                    (<?= count($inscripciones) ?>)
                </h3>
                
                <?php if (count($inscripciones) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Nombre</th>
                                <th>Categoría</th>
                                <th>Correo</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscripciones as $insc): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($insc['Numero_Participante']) ?></strong></td>
                                <td><?= htmlspecialchars($insc['Nombre_Completo']) ?></td>
                                <td><?= htmlspecialchars($insc['Nombre_Categoria']) ?></td>
                                <td><?= htmlspecialchars($insc['Email']) ?></td>
                                <td><?= htmlspecialchars($insc['Telefono']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($insc['Estado_Inscripcion']) ?>">
                                        <?= ucfirst($insc['Estado_Inscripcion']) ?>
                                    </span>
                                </td>
                                <td>Q<?= number_format($insc['Monto_Pagado'], 2) ?></td>
                                <td>
                                    <button class="btn btn-view" onclick="verDetalle(<?= $insc['Id_Inscripcion'] ?>)" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($insc['Estado_Inscripcion'] === 'Pendiente'): ?>
                                    <button class="btn btn-approve" onclick="openApproveForm(<?= $insc['Id_Inscripcion'] ?>)" title="Aprobar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-reject" onclick="openRejectForm(<?= $insc['Id_Inscripcion'] ?>)" title="Rechazar">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay inscripciones</h3>
                    <p>
                        <?php if (!empty($busqueda)): ?>
                            No se encontraron resultados para "<?= htmlspecialchars($busqueda) ?>"
                        <?php else: ?>
                            No hay inscripciones con el estado seleccionado
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif (count($todos_eventos) > 0): ?>
            <!-- Sin evento seleccionado pero hay eventos -->
            <div class="no-evento-seleccionado">
                <i class="fas fa-hand-pointer"></i>
                <h3>Selecciona un evento</h3>
                <p>Haz clic en uno de los eventos arriba para revisar sus inscripciones</p>
            </div>
            <?php endif; ?>
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
                        <button type="submit" class="btn btn-large" id="btnSubmit">
                            Confirmar
                        </button>
                        <button type="button" class="btn btn-large" onclick="cerrarModalAccion()" style="background-color: #6c757d; color: white;">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const inscripcionesData = <?= json_encode($inscripciones) ?>;

        function seleccionarEvento(eventoId) {
            window.location.href = `?evento=${eventoId}&estado=Pendiente`;
        }

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
                        <div class="detail-label">Estado</div>
                        <div class="detail-value">
                            <span class="status-badge ${insc.Estado_Inscripcion.toLowerCase()}">
                                ${insc.Estado_Inscripcion.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tipo de Inscripción</div>
                        <div class="detail-value">${insc.Tipo_Inscripcion}</div>
                    </div>
                </div>
            `;

            if (insc.Ruta_Archivo) {
                html += `
                    <div class="image-container">
                        <h3 style="color: #004b87; margin-bottom: 15px;">Boleta de Pago</h3>
                        <p style="margin-bottom: 20px; color: #666;">Fecha de subida: ${new Date(insc.Fecha_Subida).toLocaleDateString('es-ES')}</p>
                        <img src="${insc.Ruta_Archivo}" alt="Boleta" style="max-width: 500px;">
                    </div>
                `;
            } else {
                html += `
                    <div class="image-container">
                        <h3 style="color: #dc3545; margin-bottom: 15px;">Sin Boleta de Pago</h3>
                        <p style="color: #666;">El participante no ha subido una boleta de pago</p>
                    </div>
                `;
            }

            modalBody.innerHTML = html;
            document.getElementById('modalDetalle').style.display = 'block';
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

        // SweetAlert2 para mostrar alertas bonitas
        <?php if (isset($alerta)): ?>
            Swal.fire({
                icon: '<?= $alerta['tipo'] === 'success' ? 'success' : 'error' ?>',
                title: '<?= $alerta['tipo'] === 'success' ? '¡Éxito!' : '¡Atención!' ?>',
                text: '<?= $alerta['mensaje'] ?>',
                confirmButtonColor: '#004b87',
                timer: 3000,
                showConfirmButton: true
            });
        <?php endif; ?>

        // Confirmación para acciones de autorización/negación
        document.getElementById('formAccion').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const accion = document.getElementById('accion').value;
            const comentario = document.getElementById('comentario').value;
            
            Swal.fire({
                title: `¿Estás seguro de ${accion === 'autorizar' ? 'autorizar' : 'negar'} esta inscripción?`,
                text: accion === 'autorizar' ? 
                    'La inscripción será confirmada y el participante podrá asistir al evento.' :
                    'La inscripción será cancelada y no podrá asistir al evento.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004b87',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Sí, ${accion === 'autorizar' ? 'autorizar' : 'negar'}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html>