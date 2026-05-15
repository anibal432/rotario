<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Validar datos requeridos
    if (empty($_POST['nombre_evento']) || empty($_POST['fecha_evento'])) {
        throw new Exception('Faltan datos requeridos');
    }
    
    $id_evento_origen = $_POST['id_evento_origen'];
    
    // Procesar campos opcionales correctamente
    $cupo_maximo = !empty($_POST['cupo_maximo']) ? (int)$_POST['cupo_maximo'] : null;
    $distancia_km = !empty($_POST['distancia_km']) ? (float)$_POST['distancia_km'] : null;
    $hora_salida = !empty($_POST['hora_salida']) ? $_POST['hora_salida'] : null;
    $fecha_inicio_inscripciones = !empty($_POST['fecha_inicio_inscripciones']) ? $_POST['fecha_inicio_inscripciones'] : null;
    $fecha_fin_inscripciones = !empty($_POST['fecha_fin_inscripciones']) ? $_POST['fecha_fin_inscripciones'] : null;
    
    // Procesar imagen banner
    $imagen_banner = null;
    if (isset($_FILES['imagen_banner']) && $_FILES['imagen_banner']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/eventos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extension = strtolower(pathinfo($_FILES['imagen_banner']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($extension, $allowed)) {
            throw new Exception('Formato de imagen no permitido');
        }
        
        $imagen_banner = 'evento_' . time() . '_' . uniqid() . '.' . $extension;
        $ruta_completa = $upload_dir . $imagen_banner;
        
        if (!move_uploaded_file($_FILES['imagen_banner']['tmp_name'], $ruta_completa)) {
            throw new Exception('Error al subir la imagen');
        }
        
        $imagen_banner = $ruta_completa;
    } elseif (!empty($_POST['imagen_banner_original'])) {
        // Usar imagen del evento original
        $imagen_banner = $_POST['imagen_banner_original'];
    }
    
    // Insertar nuevo evento
    $sql_evento = "INSERT INTO Eventos (
        Id_Tipo_Evento,
        Nombre_Evento,
        Descripcion,
        Fecha_Evento,
        Hora_Inicio,
        Hora_Salida,
        Lugar_Salida,
        Recorrido,
        Distancia_KM,
        Causa_Beneficiada,
        Fecha_Inicio_Inscripciones,
        Fecha_Fin_Inscripciones,
        Estado_Evento,
        Cupo_Maximo,
        Imagen_Banner
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Planificado', ?, ?)";
    
    $stmt = $pdo->prepare($sql_evento);
    $stmt->execute([
        $_POST['id_tipo_evento'],
        $_POST['nombre_evento'],
        $_POST['descripcion'] ?? null,
        $_POST['fecha_evento'],
        $_POST['hora_inicio'],
        $hora_salida,
        $_POST['lugar_salida'],
        $_POST['recorrido'] ?? null,
        $distancia_km,
        $_POST['causa_beneficiada'] ?? null,
        $fecha_inicio_inscripciones,
        $fecha_fin_inscripciones,
        $cupo_maximo,
        $imagen_banner
    ]);
    
    $nuevo_id_evento = $pdo->lastInsertId();
    
    // Insertar categorías
    if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
        $sql_categoria = "INSERT INTO Categorias_Evento (
            Id_Evento, Nombre_Categoria, Genero, Edad_Minima, Edad_Maxima, Descripcion
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt_cat = $pdo->prepare($sql_categoria);
        
        foreach ($_POST['categorias'] as $cat) {
            if (empty($cat['nombre']) || empty($cat['genero'])) continue;
            
            $edad_min = !empty($cat['edad_min']) ? (int)$cat['edad_min'] : null;
            $edad_max = !empty($cat['edad_max']) ? (int)$cat['edad_max'] : null;
            
            $stmt_cat->execute([
                $nuevo_id_evento,
                $cat['nombre'],
                $cat['genero'],
                $edad_min,
                $edad_max,
                $cat['descripcion'] ?? null
            ]);
        }
    }
    
    // Insertar costos
    if (isset($_POST['costos']) && is_array($_POST['costos'])) {
        $sql_costo = "INSERT INTO Costos_Inscripcion (
            Id_Evento, Tipo_Inscripcion, Costo, Fecha_Inicio, Fecha_Fin, Descripcion
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt_cost = $pdo->prepare($sql_costo);
        
        foreach ($_POST['costos'] as $cost) {
            if (empty($cost['tipo']) || empty($cost['costo']) || 
                empty($cost['fecha_inicio']) || empty($cost['fecha_fin'])) continue;
            
            $stmt_cost->execute([
                $nuevo_id_evento,
                $cost['tipo'],
                (float)$cost['costo'],
                $cost['fecha_inicio'],
                $cost['fecha_fin'],
                $cost['descripcion'] ?? null
            ]);
        }
    }
    
    // Insertar cuentas bancarias
    if (isset($_POST['cuentas']) && is_array($_POST['cuentas'])) {
        $sql_cuenta = "INSERT INTO Cuentas_Bancarias (
            Id_Evento, Nombre_Banco, Numero_Cuenta, Nombre_Cuenta, Tipo_Cuenta, Moneda
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt_cta = $pdo->prepare($sql_cuenta);
        
        foreach ($_POST['cuentas'] as $cta) {
            if (empty($cta['banco']) || empty($cta['numero']) || 
                empty($cta['nombre']) || empty($cta['tipo'])) continue;
            
            $stmt_cta->execute([
                $nuevo_id_evento,
                $cta['banco'],
                $cta['numero'],
                $cta['nombre'],
                $cta['tipo'],
                $cta['moneda'] ?? 'GTQ'
            ]);
        }
    }
    
    // Registrar en bitácora
    $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                     VALUES (?, ?, CURDATE(), ?)";
    $stmt_bit = $pdo->prepare($sql_bitacora);
    $stmt_bit->execute([
        $_SESSION['user_id'],
        "Reactivó evento: " . $_POST['nombre_evento'] . " (ID origen: $id_evento_origen)",
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Evento reactivado exitosamente',
        'id_evento' => $nuevo_id_evento
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error al reactivar el evento: ' . $e->getMessage()
    ]);
}
?>