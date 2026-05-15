<?php
// ============================================
// CONFIGURACIÓN INICIAL
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Limpiar cualquier salida previa
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    // ============================================
    // VERIFICACIONES BÁSICAS
    // ============================================
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido. Use POST.');
    }

    if (!file_exists(__DIR__ . '/conexion.php')) {
        throw new Exception('Archivo conexion.php no encontrado');
    }
    
    require_once __DIR__ . '/conexion.php';
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Conexión PDO no disponible');
    }

    // ============================================
    // FUNCIONES DEL SISTEMA DE CITAS AUTOMÁTICAS
    // Horario: Lunes a Viernes, 9:00 AM - 1:00 PM
    // Intervalos: 40 minutos
    // ============================================
    
    /**
     * Obtener la próxima cita disponible
     */
    function obtenerProximaCitaDisponible($pdo) {
        // Horarios disponibles del día (última cita: 12:20 para terminar a la 1:00 PM)
        $horarios_del_dia = [
            '09:00:00',
            '09:40:00',
            '10:20:00',
            '11:00:00',
            '11:40:00',
            '12:20:00'
        ];
        
        // Obtener la última cita registrada (excluyendo canceladas)
        $sql = "SELECT Fecha_Cita, Hora_Cita 
                FROM Citas_Entrevista 
                WHERE Estado_Cita != 'Cancelada'
                ORDER BY Fecha_Cita DESC, Hora_Cita DESC 
                LIMIT 1";
        
        $stmt = $pdo->query($sql);
        $ultima_cita = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no hay citas previas, asignar la primera fecha laboral disponible
        if (!$ultima_cita) {
            $fecha_inicio = obtenerSiguienteDiaLaboral(date('Y-m-d'));
            return [
                'fecha' => $fecha_inicio,
                'hora' => $horarios_del_dia[0],
                'fecha_formateada' => formatearFechaTexto($fecha_inicio),
                'hora_formateada' => formatearHoraTexto($horarios_del_dia[0])
            ];
        }
        
        $fecha_actual = $ultima_cita['Fecha_Cita'];
        $hora_actual = $ultima_cita['Hora_Cita'];
        
        // Buscar el índice del horario actual
        $index_actual = array_search($hora_actual, $horarios_del_dia);
        
        // Si hay espacio en el mismo día, usar el siguiente horario
        if ($index_actual !== false && $index_actual < count($horarios_del_dia) - 1) {
            $nueva_hora = $horarios_del_dia[$index_actual + 1];
            return [
                'fecha' => $fecha_actual,
                'hora' => $nueva_hora,
                'fecha_formateada' => formatearFechaTexto($fecha_actual),
                'hora_formateada' => formatearHoraTexto($nueva_hora)
            ];
        }
        
        // Si el día está lleno, pasar al siguiente día laboral
        $siguiente_dia = obtenerSiguienteDiaLaboral($fecha_actual);
        
        return [
            'fecha' => $siguiente_dia,
            'hora' => $horarios_del_dia[0],
            'fecha_formateada' => formatearFechaTexto($siguiente_dia),
            'hora_formateada' => formatearHoraTexto($horarios_del_dia[0])
        ];
    }
    
    /**
     * Obtener el siguiente día laboral (Lunes a Viernes)
     */
    function obtenerSiguienteDiaLaboral($fecha_actual) {
        $fecha = new DateTime($fecha_actual);
        $fecha->modify('+1 day');
        
        // Obtener el día de la semana (1 = Lunes, 7 = Domingo)
        $dia_semana = $fecha->format('N');
        
        // Si es sábado (6), avanzar a lunes
        if ($dia_semana == 6) {
            $fecha->modify('+2 days');
        }
        // Si es domingo (7), avanzar a lunes
        elseif ($dia_semana == 7) {
            $fecha->modify('+1 day');
        }
        
        return $fecha->format('Y-m-d');
    }
    
    /**
     * Formatear fecha en texto legible en español
     */
    function formatearFechaTexto($fecha) {
        $dias_semana = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes'
        ];
        
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        
        $fecha_obj = new DateTime($fecha);
        $dia_semana = $dias_semana[$fecha_obj->format('N')];
        $dia = $fecha_obj->format('d');
        $mes = $meses[intval($fecha_obj->format('m'))];
        $anio = $fecha_obj->format('Y');
        
        return "$dia_semana, $dia de $mes de $anio";
    }
    
    /**
     * Formatear hora en formato 12 horas con AM/PM
     */
    function formatearHoraTexto($hora) {
        $hora_obj = new DateTime($hora);
        return $hora_obj->format('g:i A');
    }

    // ============================================
    // CARGAR CONFIG_EMAIL (USA TU ARCHIVO EXISTENTE)
    // ============================================
    $emailConfigExists = false;
    
    if (file_exists(__DIR__ . '/config_email.php')) {
        try {
            require_once __DIR__ . '/config_email.php';
            $emailConfigExists = function_exists('enviarCorreo');
            if ($emailConfigExists) {
                error_log("✓ config_email.php cargado correctamente");
            }
        } catch (Exception $e) {
            error_log("⚠ Error cargando config_email.php: " . $e->getMessage());
        }
    } else {
        error_log("⚠ config_email.php no encontrado - Los correos no se enviarán");
    }

    // ============================================
    // FUNCIONES DE VALIDACIÓN
    // ============================================
    
    function validarCampo($campo, $nombre) {
        if (!isset($_POST[$campo])) {
            throw new Exception("Campo '$nombre' no encontrado");
        }
        
        $valor = trim($_POST[$campo]);
        
        if ($valor === '') {
            throw new Exception("El campo '$nombre' es obligatorio");
        }
        
        return $valor;
    }
    
    function validarEmail($email) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El correo '$email' no es válido");
        }
        return $email;
    }
    
    function sanitizar($texto) {
        if ($texto === null) return null;
        return htmlspecialchars(strip_tags(trim($texto)), ENT_QUOTES, 'UTF-8');
    }

    // ============================================
    // VALIDAR DATOS DEL FORMULARIO
    // ============================================
    
    error_log("=== INICIANDO PROCESO DE SOLICITUD ===");
    
    $nombres_apellidos = sanitizar(validarCampo('nombres_apellidos', 'Nombres y Apellidos'));
    $edad = intval(validarCampo('edad', 'Edad'));
    $telefono = sanitizar(validarCampo('telefono', 'Teléfono'));
    $email = validarEmail(validarCampo('email', 'Correo'));
    $direccion = sanitizar(validarCampo('direccion', 'Dirección'));
    $nombre_encargado = sanitizar(validarCampo('nombre_encargado', 'Encargado'));
    $telefono_encargado = sanitizar(validarCampo('telefono_encargado', 'Teléfono Encargado'));
    $grado_actual = sanitizar(validarCampo('grado_actual', 'Grado Actual'));
    $carrera = sanitizar(validarCampo('carrera', 'Carrera'));
    $establecimiento = sanitizar(validarCampo('establecimiento', 'Establecimiento'));
    $promedio = floatval(validarCampo('promedio', 'Promedio'));
    $grado_obtenido = sanitizar(validarCampo('grado_obtenido', 'Grado Obtenido'));
    $escuela_anterior = sanitizar(validarCampo('escuela_anterior', 'Escuela Anterior'));
    $carta_solicitud = validarCampo('carta_solicitud', 'Carta');
    
    // Validaciones adicionales
    if ($edad < 5 || $edad > 99) {
        throw new Exception('Edad debe estar entre 5 y 99 años');
    }
    
    if ($promedio < 0 || $promedio > 100) {
        throw new Exception('Promedio debe estar entre 0 y 100');
    }
    
    if (strlen($carta_solicitud) < 100) {
        throw new Exception('La carta debe tener al menos 100 caracteres');
    }
    
    // Campos opcionales
    $nombre_madre = isset($_POST['nombre_madre']) ? sanitizar($_POST['nombre_madre']) : null;
    $nombre_padre = isset($_POST['nombre_padre']) ? sanitizar($_POST['nombre_padre']) : null;
    $dpi = isset($_POST['dpi']) ? sanitizar($_POST['dpi']) : null;

    error_log("✓ Validación completada para: $nombres_apellidos ($email)");

    // ============================================
    // INICIAR TRANSACCIÓN
    // ============================================
    
    error_log("→ Iniciando transacción de base de datos");
    $pdo->beginTransaction();

    // ============================================
    // 1. INSERTAR ESTUDIANTE
    // ============================================
    
    error_log("→ Insertando estudiante...");
    
    $sql = "INSERT INTO Estudiantes (
        Nombres_Apellidos, Edad, Telefono, Email, 
        Nombre_Madre, Nombre_Padre, Direccion_Domiciliar,
        Nombre_Encargado, Telefono_Encargado,
        Grado_Obtenido_Anterior, Escuela_Anterior,
        Fecha_Registro, Estado_Estudiante
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Activo')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $nombres_apellidos, $edad, $telefono, $email,
        $nombre_madre, $nombre_padre, $direccion,
        $nombre_encargado, $telefono_encargado,
        $grado_obtenido, $escuela_anterior
    ]);

    $id_estudiante = $pdo->lastInsertId();
    
    if (!$id_estudiante) {
        throw new Exception('No se pudo registrar el estudiante');
    }
    
    error_log("✓ Estudiante registrado con ID: $id_estudiante");

    // ============================================
    // 2. GUARDAR CARTA
    // ============================================
    
    error_log("→ Guardando carta de solicitud...");
    
    $sql = "INSERT INTO Cartas_Solicitud (Id_Estudiante, Contenido_Carta, Fecha_Creacion) 
            VALUES (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_estudiante, $carta_solicitud]);
    
    error_log("✓ Carta guardada");

    // ============================================
    // 3. GUARDAR RESPUESTAS CUESTIONARIO
    // ============================================
    
    error_log("→ Guardando respuestas del cuestionario...");
    
    $sql = "INSERT INTO Respuestas_Cuestionario 
            (Id_Estudiante, Id_Pregunta, Respuesta, Fecha_Respuesta) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);

    $respuestas_guardadas = 0;
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'pregunta_') === 0 && !empty($value)) {
            $id_pregunta = intval(str_replace('pregunta_', '', $key));
            if ($id_pregunta > 0) {
                $stmt->execute([$id_estudiante, $id_pregunta, sanitizar($value)]);
                $respuestas_guardadas++;
            }
        }
    }
    
    error_log("✓ Respuestas guardadas: $respuestas_guardadas");

    // ============================================
    // 4. GUARDAR EVALUACIÓN SOCIOECONÓMICA
    // ============================================
    
    error_log("→ Guardando evaluación socioeconómica...");
    
    $meta_profesional = sanitizar(validarCampo('meta_profesional', 'Meta Profesional'));
    $otra_beca = validarCampo('otra_beca', 'Otra Beca');
    $estado_civil = validarCampo('estado_civil_padres', 'Estado Civil');
    $madre_leer = validarCampo('madre_leer', 'Madre Leer');
    $madre_grado = sanitizar(validarCampo('madre_grado_educacion', 'Grado Madre'));
    $padre_leer = validarCampo('padre_leer', 'Padre Leer');
    $padre_grado = sanitizar(validarCampo('padre_grado_educacion', 'Grado Padre'));
    $tipo_vivienda = validarCampo('tipo_vivienda', 'Tipo Vivienda');
    $condiciones = validarCampo('condiciones_vivienda', 'Condiciones Vivienda');
    $material = validarCampo('material_vivienda', 'Material Vivienda');
    $como_entero = sanitizar(validarCampo('como_se_entero', 'Cómo se enteró'));
    
    // Servicios
    $servicios = '[]';
    if (isset($_POST['servicios']) && is_array($_POST['servicios']) && count($_POST['servicios']) > 0) {
        $servicios = json_encode(array_map('sanitizar', $_POST['servicios']));
    } else {
        throw new Exception('Selecciona al menos un servicio básico');
    }

    $sql = "INSERT INTO Evaluaciones_Socioeconomicas (
        Id_Estudiante, Fecha_Evaluacion, Meta_Profesional, Otra_Beca,
        Institucion_Beca, Contacto_Institucion, Estado_Civil_Padres,
        Madre_Leer, Madre_Grado_Educacion, Padre_Leer, Padre_Grado_Educacion,
        Profesion_Madre, Profesion_Padre, Lugar_Trabajo_Madre, Lugar_Trabajo_Padre,
        Como_Se_Entero, Tipo_Vivienda, Condiciones_Vivienda, Material_Vivienda,
        Servicios_Basicos, Ensayo_Personal, Nombre_Socio_Rotario, Estado_Evaluacion
    ) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $id_estudiante, $meta_profesional, $otra_beca,
        isset($_POST['institucion_beca']) ? sanitizar($_POST['institucion_beca']) : null,
        isset($_POST['contacto_institucion']) ? sanitizar($_POST['contacto_institucion']) : null,
        $estado_civil, $madre_leer, $madre_grado, $padre_leer, $padre_grado,
        isset($_POST['profesion_madre']) ? sanitizar($_POST['profesion_madre']) : null,
        isset($_POST['profesion_padre']) ? sanitizar($_POST['profesion_padre']) : null,
        isset($_POST['lugar_trabajo_madre']) ? sanitizar($_POST['lugar_trabajo_madre']) : null,
        isset($_POST['lugar_trabajo_padre']) ? sanitizar($_POST['lugar_trabajo_padre']) : null,
        $como_entero, $tipo_vivienda, $condiciones, $material, $servicios,
        isset($_POST['ensayo_personal']) ? sanitizar($_POST['ensayo_personal']) : null,
        isset($_POST['nombre_socio_rotario']) ? sanitizar($_POST['nombre_socio_rotario']) : null
    ]);

    $id_evaluacion = $pdo->lastInsertId();
    
    if (!$id_evaluacion) {
        throw new Exception('No se pudo guardar la evaluación');
    }
    
    error_log("✓ Evaluación guardada con ID: $id_evaluacion");

    // ============================================
    // 5. ASIGNAR CITA AUTOMÁTICA CON SISTEMA DE INTERVALOS
    // ============================================
    
    error_log("→ Calculando próxima cita disponible...");
    
    // Obtener la próxima cita disponible usando el sistema automático
    $cita_info = obtenerProximaCitaDisponible($pdo);
    
    $fecha_cita = $cita_info['fecha'];
    $hora_cita = $cita_info['hora'];
    $fecha_formateada = $cita_info['fecha_formateada'];
    $hora_formateada = $cita_info['hora_formateada'];
    
    error_log("→ Cita calculada: $fecha_formateada a las $hora_formateada");
    error_log("→ Insertando cita en base de datos...");

    $sql = "INSERT INTO Citas_Entrevista (
        Id_Evaluacion, Id_Estudiante, Fecha_Cita, Hora_Cita,
        Estado_Cita, Lugar_Entrevista, Observaciones, Fecha_Creacion
    ) VALUES (?, ?, ?, ?, 'Programada', 'Oficinas Club Rotario Coatepeque-Colomba',
              'Cita asignada automáticamente - Sistema de intervalos de 40 minutos', NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_evaluacion, $id_estudiante, $fecha_cita, $hora_cita]);
    
    $id_cita = $pdo->lastInsertId();
    
    if (!$id_cita) {
        throw new Exception('No se pudo asignar la cita');
    }
    
    error_log("✓ Cita asignada con ID: $id_cita para $fecha_cita $hora_cita");

    // ============================================
    // 6. ENVIAR CORREO DE CONFIRMACIÓN
    // ============================================
    
    $correo_enviado = false;
    $mensaje_correo = '';
    
    if ($emailConfigExists) {
        try {
            error_log("→ Intentando enviar correo de confirmación...");
            
            $numero_ref = "BECA-" . str_pad($id_estudiante, 6, '0', STR_PAD_LEFT);
            
            // Generar HTML del correo con la cita formateada
            $mensaje_html = generarCorreoConfirmacion(
                $nombres_apellidos, 
                $fecha_formateada, 
                $hora_formateada, 
                $numero_ref
            );
            
            // Enviar usando tu función de config_email.php
            $resultado_correo = enviarCorreo(
                $email,
                "Solicitud de Beca Recibida - Club Rotario",
                $mensaje_html,
                $nombres_apellidos
            );
            
            $correo_enviado = $resultado_correo['success'] ?? false;
            $mensaje_correo = $resultado_correo['message'] ?? '';
            
            if ($correo_enviado) {
                error_log("✓ Correo enviado exitosamente a: $email");
            } else {
                error_log("⚠ No se pudo enviar el correo: $mensaje_correo");
            }
            
        } catch (Exception $e) {
            error_log("⚠ Error al enviar correo: " . $e->getMessage());
            $mensaje_correo = $e->getMessage();
            // No lanzar excepción - el registro ya está completo
        }
    } else {
        error_log("⚠ config_email.php no disponible - correo no enviado");
        $mensaje_correo = 'Sistema de correo no configurado';
    }

    // ============================================
    // CONFIRMAR TRANSACCIÓN
    // ============================================
    
    $pdo->commit();
    error_log("=== ✓ TRANSACCIÓN COMPLETADA EXITOSAMENTE ===");
    error_log("=== Cita asignada: $fecha_formateada a las $hora_formateada ===");

    // Respuesta exitosa
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud registrada exitosamente',
        'id_estudiante' => $id_estudiante,
        'id_evaluacion' => $id_evaluacion,
        'id_cita' => $id_cita,
        'fecha_cita' => $fecha_cita,
        'hora_cita' => $hora_cita,
        'fecha_cita_formateada' => $fecha_formateada,
        'hora_cita_formateada' => $hora_formateada,
        'respuestas_guardadas' => $respuestas_guardadas,
        'correo_enviado' => $correo_enviado,
        'mensaje_correo' => $mensaje_correo
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();
    error_log("❌ ERROR PDO: $error");
    
    $mensaje = 'Error al procesar la solicitud';
    
    if (strpos($error, 'Duplicate entry') !== false) {
        if (strpos($error, 'Email') !== false) {
            $mensaje = 'Este correo ya está registrado';
        } else {
            $mensaje = 'Ya existe un registro con estos datos';
        }
    }
    
    ob_end_clean();
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'message' => $mensaje,
        'error_detalle' => $error,
        'tipo_error' => 'database'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();
    error_log("❌ ERROR: $error");
    
    ob_end_clean();
    http_response_code(400);
    
    echo json_encode([
        'success' => false,
        'message' => $error,
        'tipo_error' => 'validation'
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================
// FUNCIÓN AUXILIAR: GENERAR HTML DEL CORREO
// ============================================
function generarCorreoConfirmacion($nombre, $fecha, $hora, $referencia) {
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #1a3a5f 0%, #0f2a47 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .cita-box { background: #e7f3ff; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #d4af37; }
        .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .footer { text-align: center; padding: 20px; color: #666; background: #f1f1f1; }
        h1 { margin: 0; font-size: 24px; }
        h2 { color: #1a3a5f; font-size: 20px; margin-top: 0; }
        h3 { color: #333; font-size: 16px; margin-top: 0; }
        .reference { text-align: center; font-size: 24px; font-weight: bold; color: #d4af37; padding: 15px; background: #fff3cd; border-radius: 5px; margin: 20px 0; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
        .highlight { background: #d4af37; color: white; padding: 5px 10px; border-radius: 4px; font-weight: bold; }
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
            
            <p>Hemos recibido tu solicitud de beca correctamente. Tu entrevista ha sido programada automáticamente.</p>
            
            <div class="cita-box">
                <h2>📅 Tu Cita de Entrevista</h2>
                <p style="margin: 10px 0;">📆 <strong>Fecha:</strong> <span class="highlight">$fecha</span></p>
                <p style="margin: 10px 0;">🕐 <strong>Hora:</strong> <span class="highlight">$hora</span></p>
                <p style="margin: 10px 0;">📍 <strong>Lugar:</strong> Oficinas Club Rotario Coatepeque-Colomba</p>
                <p style="margin: 10px 0; font-size: 14px; color: #666;">⏱️ <strong>Duración estimada:</strong> 40 minutos</p>
            </div>
            
            <div class="info-box">
                <h3>📝 Documentos requeridos:</h3>
                <ul>
                    <li>Boleta de calificaciones reciente</li>
                    <li>Certificado de nacimiento</li>
                    <li>DPI estudiante y encargado</li>
                    <li>Comprobante de ingresos</li>
                    <li>Recibos de servicios básicos (luz, agua)</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h3>⚠️ Recomendaciones importantes:</h3>
                <p style="margin: 5px 0;">• Llega <strong>10 minutos antes</strong> de tu hora programada</p>
                <p style="margin: 5px 0;">• Duración aproximada: <strong>40 minutos</strong></p>
                <p style="margin: 5px 0;">• Trae documentos originales y copias</p>
                <p style="margin: 5px 0;">• Asiste con tu encargado o padre/madre</p>
                <p style="margin: 5px 0; color: #e74c3c;"><strong>⚠️ Las citas se asignan solo de Lunes a Viernes</strong></p>
            </div>
            
            <div class="reference">
                <strong>Número de Referencia:</strong><br>
                $referencia
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <strong>¿Necesitas reprogramar o tienes preguntas?</strong><br>
                📧 rotarios_coatepequecolomba@yahoo.com.mx<br>
                📱 7775 5248<br>
                <small style="color: #666;">Horario de atención: Lunes a Viernes, 9:00 AM - 1:00 PM</small>
            </p>
        </div>
        
        <div class="footer">
            <p style="margin: 5px 0;">&copy; 2025 Club Rotario Coatepeque Colomba</p>
            <p style="font-size: 12px; margin: 5px 0;">Correo automático, no responder directamente a este mensaje.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

exit;