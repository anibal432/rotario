<?php
// Suprimir warnings para evitar problemas con JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Configuración de archivos
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

class RegistroCarrera {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = getDBConnection();
        } catch (Exception $e) {
            throw new Exception("Error inicializando el sistema: " . $e->getMessage());
        }
        
        // Crear directorio de uploads si no existe
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
    }
    
    public function procesarRegistro($datos, $archivo) {
        try {
            $this->pdo->beginTransaction();
            
            // 1. Validar datos
            $this->validarDatos($datos);
            
            // 2. Verificar si el email ya existe
            $this->verificarEmailUnico($datos['email']);
            
            // 3. Generar número de corredor
            $numero_corredor = $this->generarNumeroCorredor();
            
            // 4. Determinar categoría
            $categoria = $this->determinarCategoria($datos['edad'], $datos['genero']);
            
            // 5. Procesar archivo de boleta
            $archivo_info = $this->procesarArchivo($archivo);
            
            // 6. Insertar datos del corredor con estado PENDIENTE
            $corredor_id = $this->insertarCorredor($datos, $numero_corredor, $categoria);
            
            // 7. Insertar información del archivo
            $this->insertarBoleta($corredor_id, $archivo_info);
            
            $this->pdo->commit();
            
            // NO ENVIAR EMAIL - esperar autorización del administrador
            return [
                'success' => true,
                'message' => '¡Registro recibido exitosamente! Estás a un paso de participar. Tu inscripción será revisada y recibirás un correo de confirmación una vez sea aprobada. Recuerda revisar tu correo constantemente.',
                'data' => [
                    'numero_corredor' => $numero_corredor,
                    'categoria' => $categoria,
                    'corredor_id' => $corredor_id,
                    'estado' => 'Pendiente de autorización'
                ]
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Limpiar archivo subido si hay error
            if (isset($archivo_info['ruta']) && file_exists($archivo_info['ruta'])) {
                unlink($archivo_info['ruta']);
            }
            
            throw $e;
        }
    }
    
    private function validarDatos($datos) {
        $campos_requeridos = [
            'nombre' => 'Nombre completo',
            'edad' => 'Edad',
            'genero' => 'Género',
            'telefono' => 'Teléfono',
            'email' => 'Email',
            'dpi' => 'DPI',
            'direccion' => 'Dirección',
            'playera' => 'Talla de playera'
        ];
        
        foreach ($campos_requeridos as $campo => $etiqueta) {
            if (empty($datos[$campo])) {
                throw new Exception("El campo '$etiqueta' es requerido");
            }
        }
        
        // Validaciones específicas
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido");
        }
        
        if ($datos['edad'] < 18 || $datos['edad'] > 99) {
            throw new Exception("La edad debe estar entre 18 y 99 años");
        }
        
        if (!in_array($datos['genero'], ['Masculino', 'Femenino'])) {
            throw new Exception("Género inválido");
        }
        
        if (!in_array($datos['playera'], ['XS', 'S', 'M', 'L', 'XL', 'XXL'])) {
            throw new Exception("Talla de playera inválida");
        }
        
        // Validar formato de DPI (13 dígitos)
        $dpi_limpio = preg_replace('/\D/', '', $datos['dpi']);
        if (strlen($dpi_limpio) !== 13) {
            throw new Exception("El DPI debe tener 13 dígitos");
        }
        
        // Validar formato de teléfono (8 dígitos)
        $telefono_limpio = preg_replace('/\D/', '', $datos['telefono']);
        if (strlen($telefono_limpio) !== 8) {
            throw new Exception("El teléfono debe tener 8 dígitos");
        }
    }
    
    private function verificarEmailUnico($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM corredores WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            throw new Exception("Este email ya está registrado");
        }
    }
    
    private function generarNumeroCorredor() {
        $anio_actual = date('Y');
        
        // Obtener o crear configuración
        $stmt = $this->pdo->prepare("SELECT ultimo_numero, prefijo, anio FROM configuracion_numeros WHERE anio = ? LIMIT 1");
        $stmt->execute([$anio_actual]);
        $config = $stmt->fetch();
        
        if (!$config) {
            // Crear configuración para el año actual
            $stmt = $this->pdo->prepare("INSERT INTO configuracion_numeros (ultimo_numero, prefijo, anio) VALUES (1000, 'RC', ?)");
            $stmt->execute([$anio_actual]);
            $ultimo_numero = 1001;
            $prefijo = 'RC';
        } else {
            $ultimo_numero = $config['ultimo_numero'] + 1;
            $prefijo = $config['prefijo'];
        }
        
        // Actualizar último número
        $stmt = $this->pdo->prepare("UPDATE configuracion_numeros SET ultimo_numero = ? WHERE anio = ?");
        $stmt->execute([$ultimo_numero, $anio_actual]);
        
        return $prefijo . $anio_actual . str_pad($ultimo_numero, 4, '0', STR_PAD_LEFT);
    }
    
    private function determinarCategoria($edad, $genero) {
        if ($edad >= 18 && $edad <= 29) {
            return "Libre $genero";
        } elseif ($edad >= 30 && $edad <= 39) {
            return "Master A $genero";
        } elseif ($edad >= 40 && $edad <= 49) {
            return "Master B $genero";
        } elseif ($edad >= 50) {
            return "Master C $genero";
        }
        
        throw new Exception("No se pudo determinar la categoría para la edad $edad");
    }
    
    private function procesarArchivo($archivo) {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo: " . $this->getUploadError($archivo['error']));
        }
        
        // Validar tamaño
        if ($archivo['size'] > MAX_FILE_SIZE) {
            throw new Exception("El archivo es demasiado grande. Máximo 5MB");
        }
        
        // Validar tipo de archivo
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        if (!in_array($archivo['type'], $tipos_permitidos)) {
            throw new Exception("Tipo de archivo no permitido. Solo JPG, PNG y PDF");
        }
        
        // Generar nombre único
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre_archivo = 'boleta_' . uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $extension;
        $ruta_completa = UPLOAD_DIR . $nombre_archivo;
        
        // Mover archivo
        if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
            throw new Exception("Error al guardar el archivo");
        }
        
        return [
            'nombre_original' => $archivo['name'],
            'nombre_archivo' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'tipo' => $archivo['type'],
            'tamaño' => $archivo['size']
        ];
    }
    
    private function insertarCorredor($datos, $numero_corredor, $categoria) {
        $sql = "INSERT INTO corredores (
            numero_corredor, nombre, edad, genero, categoria, telefono, 
            email, dpi, direccion, talla_playera, contacto_emergencia, 
            fecha_registro, estado_inscripcion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Pendiente')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $numero_corredor,
            $datos['nombre'],
            $datos['edad'],
            $datos['genero'],
            $categoria,
            $datos['telefono'],
            $datos['email'],
            $datos['dpi'],
            $datos['direccion'],
            $datos['playera'],
            $datos['contacto_emergencia'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function insertarBoleta($corredor_id, $archivo_info) {
        $sql = "INSERT INTO boletas_pago (
            corredor_id, nombre_archivo, ruta_archivo, tipo_archivo, tamaño_archivo, estado_verificacion
        ) VALUES (?, ?, ?, ?, ?, 'Pendiente')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $corredor_id,
            $archivo_info['nombre_archivo'],
            $archivo_info['ruta'],
            $archivo_info['tipo'],
            $archivo_info['tamaño']
        ]);
    }
    
    private function getUploadError($error_code) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo es demasiado grande',
            UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir archivo',
            UPLOAD_ERR_EXTENSION => 'Extensión no permitida'
        ];
        
        return $errores[$error_code] ?? 'Error desconocido';
    }
}

// Procesar la petición
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }
    
    // Verificar que se enviaron datos
    if (empty($_POST)) {
        throw new Exception("No se recibieron datos");
    }
    
    // Verificar que se subió un archivo
    if (!isset($_FILES['boleta_pago']) || $_FILES['boleta_pago']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("La boleta de pago es requerida");
    }
    
    $registro = new RegistroCarrera();
    $resultado = $registro->procesarRegistro($_POST, $_FILES['boleta_pago']);
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    http_response_code(400);
    
    // Log del error para depuración
    error_log("Error en registro: " . $e->getMessage());
    error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'post_data' => $_POST,
            'files_data' => array_map(function($file) {
                return [
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'error' => $file['error']
                ];
            }, $_FILES)
        ]
    ]);
}
?>