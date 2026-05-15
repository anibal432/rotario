<?php
// Configuración de base de datos
include 'conexion.php';
// Incluir configuración de email
require_once 'config_email.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar datos requeridos
    $required_fields = ['distancia', 'nombre', 'edad', 'genero', 'telefono', 'email', 'dpi', 
                       'zona', 'colonia', 'calle', 'numero_casa', 'departamento', 'municipio',
                       'playera', 'nombre_emergencia', 'telefono_emergencia'];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "El campo $field es requerido"]);
            exit;
        }
    }
    
    // Validar edad
    $edad = intval($_POST['edad']);
    if ($edad < 15 || $edad > 99) {
        echo json_encode(['success' => false, 'message' => 'La edad debe estar entre 15 y 99 años']);
        exit;
    }
    
    // Validar archivo
    if (!isset($_FILES['boleta_pago']) || $_FILES['boleta_pago']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Debe subir la boleta de pago']);
        exit;
    }
    
    $file = $_FILES['boleta_pago'];
    $file_size = $file['size'];
    $file_type = $file['type'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validar tamaño (5MB máximo)
    if ($file_size > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'El archivo no debe exceder 5MB']);
        exit;
    }
    
    // Validar tipo de archivo
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    if (!in_array($file_ext, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Formato de archivo no permitido']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generar número de corredor único
        $stmt = $pdo->prepare("SELECT ultimo_numero, prefijo FROM configuracion_numeros WHERE anio = YEAR(CURDATE()) LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            // Crear configuración si no existe
            $stmt = $pdo->prepare("INSERT INTO configuracion_numeros (ultimo_numero, prefijo, anio) VALUES (1000, 'RC', YEAR(CURDATE()))");
            $stmt->execute();
            $numero_corredor = 'RC1001';
            $nuevo_numero = 1001;
        } else {
            $nuevo_numero = $config['ultimo_numero'] + 1;
            $numero_corredor = $config['prefijo'] . $nuevo_numero;
        }
        
        // Actualizar configuración
        $stmt = $pdo->prepare("UPDATE configuracion_numeros SET ultimo_numero = ? WHERE anio = YEAR(CURDATE())");
        $stmt->execute([$nuevo_numero]);
        
        // Construir dirección completa
        $direccion = sprintf(
            "%s, %s, %s, Zona %s, %s, %s",
            $_POST['calle'],
            $_POST['numero_casa'],
            $_POST['colonia'],
            $_POST['zona'],
            $_POST['municipio'],
            $_POST['departamento']
        );
        
        // Contacto de emergencia
        $contacto_emergencia = $_POST['nombre_emergencia'] . ' - Tel: ' . $_POST['telefono_emergencia'];
        
        // Insertar corredor
        $stmt = $pdo->prepare("
            INSERT INTO corredores (
                numero_corredor, nombre, edad, genero, categoria, telefono, email, dpi, 
                direccion, talla_playera, contacto_emergencia, fecha_registro, estado_inscripcion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Pendiente')
        ");
        
        $stmt->execute([
            $numero_corredor,
            $_POST['nombre'],
            $edad,
            $_POST['genero'],
            $_POST['distancia'],
            $_POST['telefono'],
            $_POST['email'],
            $_POST['dpi'],
            $direccion,
            $_POST['playera'],
            $contacto_emergencia
        ]);
        
        $corredor_id = $pdo->lastInsertId();
        
        // Guardar archivo
        $upload_dir = 'uploads/boletas/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_filename = $corredor_id . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Error al guardar el archivo');
        }
        
        // Insertar boleta
        $stmt = $pdo->prepare("
            INSERT INTO boletas_pago (
                corredor_id, nombre_archivo, ruta_archivo, tipo_archivo, 
                tamaño_archivo, estado_verificacion
            ) VALUES (?, ?, ?, ?, ?, 'Pendiente')
        ");
        
        $stmt->execute([
            $corredor_id,
            $file['name'],
            $upload_path,
            $file_type,
            $file_size
        ]);
        
        // Registrar en bitácora
        $stmt = $pdo->prepare("INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) VALUES (1, ?, CURDATE(), ?)");
        $stmt->execute([
            "Nuevo registro 21K/10K: {$_POST['nombre']} - {$_POST['distancia']} - Número: $numero_corredor",
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $pdo->commit();
        
        // Enviar correo de confirmación
        $mensaje_correo = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .info-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #1e40af; border-radius: 5px; }
                .highlight { color: #1e40af; font-weight: bold; font-size: 24px; }
                .footer { text-align: center; margin-top: 30px; padding: 20px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏃 ¡Registro Recibido!</h1>
                    <p>Carrera {$_POST['distancia']} - Club Rotario Coatepeque-Colomba</p>
                </div>
                <div class='content'>
                    <p>Estimado/a <strong>{$_POST['nombre']}</strong>,</p>
                    <p>Hemos recibido tu registro para la Carrera {$_POST['distancia']} \"Por la Educación\".</p>
                    
                    <div class='info-box'>
                        <h3>📋 Información de tu registro:</h3>
                        <p><strong>Número de Corredor:</strong> <span class='highlight'>$numero_corredor</span></p>
                        <p><strong>Categoría:</strong> {$_POST['distancia']}</p>
                        <p><strong>Talla de Playera:</strong> {$_POST['playera']}</p>
                        <p><strong>Estado:</strong> Pendiente de aprobación</p>
                    </div>
                    
                    <div class='info-box' style='border-left-color: #f59e0b;'>
                        <h3>⏳ Próximos pasos:</h3>
                        <p>Tu inscripción está <strong>pendiente de autorización</strong>.</p>
                        <p>Nuestro equipo verificará tu boleta de pago y te enviaremos un correo cuando tu inscripción sea aprobada.</p>
                        <p><strong>Recuerda revisar tu bandeja de entrada y spam.</strong></p>
                    </div>
                    
                    <div class='info-box'>
                        <h3>📅 Información del Evento:</h3>
                        <p><strong>Fecha:</strong> 5 de Noviembre, 2024</p>
                        <p><strong>Hora de Inicio:</strong> 6:00 AM</p>
                        <p><strong>Lugar de Salida:</strong> Parque Central, Coatepeque</p>
                    </div>
                    
                    <p style='margin-top: 30px;'>¡Nos vemos en la carrera! 🏃‍♂️💪</p>
                </div>
                <div class='footer'>
                    <p><strong>Club Rotario Coatepeque-Colomba</strong></p>
                    <p>📞 +502 7775-1234 | 📧 info@rotario.org</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        enviarCorreo($_POST['email'], "🏃 Registro Recibido - Carrera {$_POST['distancia']}", $mensaje_correo, $_POST['nombre']);
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso',
            'numero_corredor' => $numero_corredor
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error al procesar el registro: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>