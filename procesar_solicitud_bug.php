<?php
// Archivo de depuración - guardar como procesar_solicitud_beca_debug.php

// Configurar reporte de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log de inicio
error_log("=== INICIO DE PROCESAMIENTO ===");

header('Content-Type: application/json; charset=utf-8');

try {
    error_log("Paso 1: Verificando conexión");
    
    // Incluir conexión
    if (!file_exists('conexion.php')) {
        throw new Exception('Archivo conexion.php no encontrado');
    }
    
    require_once 'conexion.php';
    error_log("Conexión incluida correctamente");
    
    // Verificar si $pdo existe
    if (!isset($pdo)) {
        throw new Exception('Variable $pdo no está definida después de incluir conexion.php');
    }
    
    error_log("Paso 2: Verificando datos POST");
    
    if (empty($_POST)) {
        throw new Exception('No se recibieron datos POST');
    }
    
    error_log("Total de campos POST: " . count($_POST));
    error_log("Campos recibidos: " . implode(', ', array_keys($_POST)));
    
    // Verificar campos obligatorios
    $campos_requeridos = ['nombres_apellidos', 'edad', 'telefono', 'email'];
    foreach ($campos_requeridos as $campo) {
        if (!isset($_POST[$campo])) {
            throw new Exception("Campo faltante: $campo");
        }
    }
    
    error_log("Paso 3: Iniciando transacción");
    $pdo->beginTransaction();
    
    // INSERTAR ESTUDIANTE
    error_log("Paso 4: Insertando estudiante");
    $sql = "INSERT INTO Estudiantes (
        Nombres_Apellidos, Edad, Telefono, Email, 
        Nombre_Madre, Nombre_Padre, Direccion_Domiciliar,
        Nombre_Encargado, Telefono_Encargado,
        Grado_Obtenido_Anterior, Escuela_Anterior,
        Fecha_Registro, Estado_Estudiante
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Activo')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['nombres_apellidos'],
        $_POST['edad'],
        $_POST['telefono'],
        $_POST['email'],
        $_POST['nombre_madre'] ?? null,
        $_POST['nombre_padre'] ?? null,
        $_POST['direccion'] ?? 'Sin dirección',
        $_POST['nombre_encargado'] ?? 'Sin encargado',
        $_POST['telefono_encargado'] ?? '0000-0000',
        $_POST['grado_obtenido'] ?? 'No especificado',
        $_POST['escuela_anterior'] ?? 'No especificado'
    ]);
    
    $id_estudiante = $pdo->lastInsertId();
    error_log("Estudiante insertado con ID: $id_estudiante");
    
    // INSERTAR CARTA
    if (!empty($_POST['carta_solicitud'])) {
        error_log("Paso 5: Insertando carta");
        $sql = "INSERT INTO Cartas_Solicitud (Id_Estudiante, Contenido_Carta) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_estudiante, $_POST['carta_solicitud']]);
        error_log("Carta insertada");
    }
    
    // INSERTAR RESPUESTAS
    error_log("Paso 6: Insertando respuestas del cuestionario");
    $respuestas_count = 0;
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'pregunta_') === 0 && !empty($value)) {
            $id_pregunta = str_replace('pregunta_', '', $key);
            $sql = "INSERT INTO Respuestas_Cuestionario (Id_Estudiante, Id_Pregunta, Respuesta) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_estudiante, $id_pregunta, $value]);
            $respuestas_count++;
        }
    }
    error_log("Respuestas insertadas: $respuestas_count");
    
    // INSERTAR EVALUACIÓN
    error_log("Paso 7: Insertando evaluación socioeconómica");
    $servicios = isset($_POST['servicios']) ? json_encode($_POST['servicios']) : '[]';
    
    $sql = "INSERT INTO Evaluaciones_Socioeconomicas (
        Id_Estudiante, Fecha_Evaluacion, Meta_Profesional, Otra_Beca,
        Estado_Civil_Padres, Madre_Leer, Madre_Grado_Educacion,
        Padre_Leer, Padre_Grado_Educacion, Como_Se_Entero,
        Tipo_Vivienda, Condiciones_Vivienda, Material_Vivienda,
        Servicios_Basicos, Estado_Evaluacion
    ) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $id_estudiante,
        $_POST['meta_profesional'] ?? '',
        $_POST['otra_beca'] ?? 'NO',
        $_POST['estado_civil_padres'] ?? 'Casados',
        $_POST['madre_leer'] ?? 'NO',
        $_POST['madre_grado_educacion'] ?? 'No especificado',
        $_POST['padre_leer'] ?? 'NO',
        $_POST['padre_grado_educacion'] ?? 'No especificado',
        $_POST['como_se_entero'] ?? 'No especificado',
        $_POST['tipo_vivienda'] ?? 'Casa',
        $_POST['condiciones_vivienda'] ?? 'Buena',
        $_POST['material_vivienda'] ?? 'Block',
        $servicios
    ]);
    
    $id_evaluacion = $pdo->lastInsertId();
    error_log("Evaluación insertada con ID: $id_evaluacion");
    
    // INSERTAR CITA
    error_log("Paso 8: Insertando cita");
    $fecha_cita = date('Y-m-d', strtotime('+1 weekday'));
    $hora_cita = '09:00:00';
    
    $sql = "INSERT INTO Citas_Entrevista (
        Id_Evaluacion, Id_Estudiante, Fecha_Cita, Hora_Cita,
        Estado_Cita, Lugar_Entrevista, Observaciones
    ) VALUES (?, ?, ?, ?, 'Programada', 'Oficinas Club Rotario', 'Cita automática')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_evaluacion, $id_estudiante, $fecha_cita, $hora_cita]);
    $id_cita = $pdo->lastInsertId();
    error_log("Cita insertada con ID: $id_cita");
    
    // COMMIT
    error_log("Paso 9: Confirmando transacción");
    $pdo->commit();
    
    error_log("=== PROCESAMIENTO EXITOSO ===");
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud procesada correctamente',
        'id_estudiante' => $id_estudiante,
        'id_evaluacion' => $id_evaluacion,
        'id_cita' => $id_cita,
        'fecha_cita' => $fecha_cita,
        'hora_cita' => $hora_cita
    ]);
    
} catch (PDOException $e) {
    error_log("ERROR PDO: " . $e->getMessage());
    error_log("Código: " . $e->getCode());
    error_log("Trace: " . $e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos',
        'error_detalle' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    
} catch (Exception $e) {
    error_log("ERROR GENERAL: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

error_log("=== FIN DE PROCESAMIENTO ===");
?>