<?php
session_start();
require_once 'config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // 1. Insertar estudiante
    $sql_estudiante = "INSERT INTO Estudiantes (
        Nombres_Apellidos, Edad, Telefono, Email, 
        Nombre_Madre, Nombre_Padre, Direccion_Domiciliar,
        Nombre_Encargado, Telefono_Encargado,
        Grado_Obtenido_Anterior, Escuela_Anterior, Fecha_Registro
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql_estudiante);
    
    $fecha_registro = $_POST['anio'] . '-' . str_pad($_POST['mes'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($_POST['dia'], 2, '0', STR_PAD_LEFT);
    
    $stmt->bind_param("sissssssssss",
        $_POST['nombres'],
        $_POST['edad'],
        $_POST['telefono'],
        $_POST['email'],
        $_POST['madre'],
        $_POST['padre'],
        $_POST['direccion'],
        $_POST['encargado'],
        $_POST['tel_encargado'],
        $_POST['grado_anterior'],
        $_POST['escuela_anterior'],
        $fecha_registro
    );
    
    $stmt->execute();
    $id_estudiante = $conn->insert_id;
    
    // 2. Preparar servicios básicos como JSON
    $servicios = isset($_POST['servicios']) ? json_encode($_POST['servicios']) : json_encode([]);
    
    // 3. Insertar evaluación socioeconómica
    $sql_evaluacion = "INSERT INTO Evaluaciones_Socioeconomicas (
        Id_Estudiante, Id_Usuario_Evaluador, Fecha_Evaluacion,
        Meta_Profesional, Otra_Beca, Institucion_Beca, Contacto_Institucion,
        Estado_Civil_Padres, Madre_Leer, Madre_Grado_Educacion,
        Padre_Leer, Padre_Grado_Educacion,
        Profesion_Madre, Profesion_Padre,
        Lugar_Trabajo_Madre, Lugar_Trabajo_Padre,
        Como_Se_Entero, Tipo_Vivienda, Condiciones_Vivienda,
        Material_Vivienda, Servicios_Basicos, Ensayo_Personal,
        Nombre_Socio_Rotario, Firma_Socio, Estado_Evaluacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
    
    $stmt_eval = $conn->prepare($sql_evaluacion);
    
    $stmt_eval->bind_param("iisssssssssssssssssssss",
        $id_estudiante,
        $_SESSION['usuario_id'],
        $fecha_registro,
        $_POST['meta_profesional'],
        $_POST['otra_beca'],
        $_POST['institucion_beca'],
        $_POST['contacto_institucion'],
        $_POST['estado_padres'],
        $_POST['madre_leer'],
        $_POST['madre_educacion'],
        $_POST['padre_leer'],
        $_POST['padre_educacion'],
        $_POST['profesion_madre'],
        $_POST['profesion_padre'],
        $_POST['trabajo_madre'],
        $_POST['trabajo_padre'],
        $_POST['como_enterado'],
        $_POST['tipo_vivienda'],
        $_POST['condiciones_vivienda'],
        $_POST['material_vivienda'],
        $servicios,
        $_POST['ensayo_personal'],
        $_POST['socio_rotario'],
        $_POST['firma_socio']
    );
    
    $stmt_eval->execute();
    $id_evaluacion = $conn->insert_id;
    
    // 4. Insertar composición familiar
    if (isset($_POST['fam_nombre']) && is_array($_POST['fam_nombre'])) {
        $sql_familiar = "INSERT INTO Composicion_Familiar (
            Id_Evaluacion, Nombre_Apellidos, Edad, Parentesco,
            Nivel_Educativo, Estado_Civil, Ocupacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_fam = $conn->prepare($sql_familiar);
        
        for ($i = 0; $i < count($_POST['fam_nombre']); $i++) {
            // Solo insertar si hay nombre
            if (!empty($_POST['fam_nombre'][$i])) {
                $edad_fam = !empty($_POST['fam_edad'][$i]) ? $_POST['fam_edad'][$i] : null;
                
                $stmt_fam->bind_param("isisss",
                    $id_evaluacion,
                    $_POST['fam_nombre'][$i],
                    $edad_fam,
                    $_POST['fam_parentesco'][$i],
                    $_POST['fam_educacion'][$i],
                    $_POST['fam_civil'][$i],
                    $_POST['fam_ocupacion'][$i]
                );
                
                $stmt_fam->execute();
            }
        }
    }
    
    // 5. Registrar en bitácora
    $actividad = "Registró nueva evaluación socioeconómica para " . $_POST['nombres'];
    $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) VALUES (?, ?, CURDATE(), ?)";
    $stmt_bit = $conn->prepare($sql_bitacora);
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt_bit->bind_param("iss", $_SESSION['usuario_id'], $actividad, $ip);
    $stmt_bit->execute();
    
    // Commit de la transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluación guardada exitosamente',
        'id_evaluacion' => $id_evaluacion
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la evaluación: ' . $e->getMessage()
    ]);
}

$conn->close();
?>