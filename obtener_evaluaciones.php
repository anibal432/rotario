<?php
// obtener_evaluaciones.php - Obtener evaluaciones según usuario y rol
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método no permitido');
}

try {
    $usuarioId = $_GET['usuario_id'] ?? null;
    $rol = $_GET['rol'] ?? null;
    
    if (!$usuarioId || !$rol) {
        jsonResponse(false, 'Faltan parámetros');
    }
    
    $pdo = getDBConnection();
    
    // Construir consulta según el rol
    $query = "
        SELECT 
            ev.Id_Evaluacion as id,
            ev.Fecha_Evaluacion as fecha_registro,
            ev.Estado_Evaluacion as estado,
            ev.Meta_Profesional,
            ev.Otra_Beca,
            ev.Institucion_Beca,
            ev.Estado_Civil_Padres,
            ev.Madre_Leer,
            ev.Madre_Grado_Educacion,
            ev.Padre_Leer,
            ev.Padre_Grado_Educacion,
            ev.Profesion_Madre,
            ev.Profesion_Padre,
            ev.Lugar_Trabajo_Madre,
            ev.Lugar_Trabajo_Padre,
            ev.Como_Se_Entero,
            ev.Tipo_Vivienda,
            ev.Condiciones_Vivienda,
            ev.Material_Vivienda,
            ev.Servicios_Basicos,
            ev.Ensayo_Personal,
            ev.Nombre_Socio_Rotario,
            ev.Firma_Socio,
            ev.Motivo_Rechazo,
            e.Nombres_Apellidos,
            e.Edad,
            e.Telefono,
            e.Email,
            e.Nombre_Madre,
            e.Nombre_Padre,
            e.Direccion_Domiciliar,
            e.Nombre_Encargado,
            e.Telefono_Encargado,
            e.Grado_Obtenido_Anterior,
            e.Escuela_Anterior,
            u.Nombre as evaluador_nombre
        FROM Evaluaciones_Socioeconomicas ev
        INNER JOIN Estudiantes e ON ev.Id_Estudiante = e.Id_Estudiante
        LEFT JOIN Usuario u ON ev.Id_Usuario_Evaluador = u.Id_Usuario
    ";
    
    // Filtrar según el rol
    if ($rol === 'evaluador') {
        // El evaluador solo ve las evaluaciones que él mismo hizo
        $query .= " WHERE ev.Id_Usuario_Evaluador = ?";
        $stmt = $pdo->prepare($query . " ORDER BY ev.Fecha_Evaluacion DESC");
        $stmt->execute([$usuarioId]);
    } else {
        // Los revisores ven todas las evaluaciones pendientes o según permisos
        $query .= " WHERE 1=1"; // Mostrar todas
        $stmt = $pdo->prepare($query . " ORDER BY ev.Fecha_Evaluacion DESC");
        $stmt->execute();
    }
    
    $evaluaciones = $stmt->fetchAll();
    
    // Procesar datos para incluir composición familiar y servicios
    $result = [];
    foreach ($evaluaciones as $ev) {
        // Obtener composición familiar
        $stmtFamilia = $pdo->prepare("
            SELECT * FROM Composicion_Familiar 
            WHERE Id_Evaluacion = ?
        ");
        $stmtFamilia->execute([$ev['id']]);
        $familia = $stmtFamilia->fetchAll();
        
        // Decodificar servicios
        $servicios = json_decode($ev['Servicios_Basicos'] ?? '[]', true);
        
        // Preparar objeto de datos
        $datos = [
            'nombres' => $ev['Nombres_Apellidos'],
            'edad' => $ev['Edad'],
            'telefono' => $ev['Telefono'],
            'email' => $ev['Email'],
            'madre' => $ev['Nombre_Madre'],
            'padre' => $ev['Nombre_Padre'],
            'direccion' => $ev['Direccion_Domiciliar'],
            'encargado' => $ev['Nombre_Encargado'],
            'tel_encargado' => $ev['Telefono_Encargado'],
            'grado_anterior' => $ev['Grado_Obtenido_Anterior'],
            'escuela_anterior' => $ev['Escuela_Anterior'],
            'meta_profesional' => $ev['Meta_Profesional'],
            'otra_beca' => $ev['Otra_Beca'],
            'institucion_beca' => $ev['Institucion_Beca'],
            'estado_padres' => $ev['Estado_Civil_Padres'],
            'madre_leer' => $ev['Madre_Leer'],
            'madre_educacion' => $ev['Madre_Grado_Educacion'],
            'padre_leer' => $ev['Padre_Leer'],
            'padre_educacion' => $ev['Padre_Grado_Educacion'],
            'profesion_madre' => $ev['Profesion_Madre'],
            'profesion_padre' => $ev['Profesion_Padre'],
            'trabajo_madre' => $ev['Lugar_Trabajo_Madre'],
            'trabajo_padre' => $ev['Lugar_Trabajo_Padre'],
            'como_enterado' => $ev['Como_Se_Entero'],
            'tipo_vivienda' => $ev['Tipo_Vivienda'],
            'condiciones_vivienda' => $ev['Condiciones_Vivienda'],
            'material_vivienda' => $ev['Material_Vivienda'],
            'ensayo_personal' => $ev['Ensayo_Personal'],
            'socio_rotario' => $ev['Nombre_Socio_Rotario'],
            'firma_socio' => $ev['Firma_Socio']
        ];
        
        $result[] = [
            'id' => $ev['id'],
            'fecha_registro' => $ev['fecha_registro'],
            'estado' => $ev['Estado_Evaluacion'],
            'evaluador_nombre' => $ev['evaluador_nombre'],
            'motivo_rechazo' => $ev['Motivo_Rechazo'],
            'datos' => $datos,
            'familia' => $familia,
            'servicios' => $servicios
        ];
    }
    
    jsonResponse(true, 'Evaluaciones obtenidas', $result);
    
} catch (Exception $e) {
    error_log("Error al obtener evaluaciones: " . $e->getMessage());
    jsonResponse(false, 'Error al obtener evaluaciones: ' . $e->getMessage());
}
?>