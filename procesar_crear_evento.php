<?php
// admin/procesar_crear_evento.php - Procesar creación de evento
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

// Verificar autenticación (adaptar según tu sistema)
// if (!isset($_SESSION['usuario_id'])) {
//     echo json_encode(['success' => false, 'message' => 'No autorizado']);
//     exit;
// }

$response = ['success' => false, 'message' => ''];

try {
    // Validar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Validar campos requeridos del evento
    $camposRequeridos = ['nombre_evento', 'id_tipo_evento', 'fecha_evento', 'hora_inicio', 'lugar_salida'];
    foreach ($camposRequeridos as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("El campo $campo es requerido");
        }
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. INSERTAR EVENTO
    $sql_evento = "
        INSERT INTO Eventos (
            Id_Tipo_Evento, Nombre_Evento, Descripcion, Fecha_Evento, 
            Hora_Inicio, Hora_Salida, Lugar_Salida, Recorrido, 
            Distancia_KM, Causa_Beneficiada, Personas_Beneficiadas,
            Estado_Evento, Cupo_Maximo, Imagen_Banner
        ) VALUES (
            :id_tipo_evento, :nombre_evento, :descripcion, :fecha_evento,
            :hora_inicio, :hora_salida, :lugar_salida, :recorrido,
            :distancia_km, :causa_beneficiada, :personas_beneficiadas,
            'Inscripciones Abiertas', :cupo_maximo, :imagen_banner
        )
    ";

    // Procesar imagen banner si se subió
    $imagen_banner = null;
    if (isset($_FILES['imagen_banner']) && $_FILES['imagen_banner']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/banners/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extension = pathinfo($_FILES['imagen_banner']['name'], PATHINFO_EXTENSION);
        $filename = 'banner_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['imagen_banner']['tmp_name'], $filepath)) {
            $imagen_banner = $filepath;
        }
    }

    $stmt_evento = $pdo->prepare($sql_evento);
    $stmt_evento->execute([
        ':id_tipo_evento' => $_POST['id_tipo_evento'],
        ':nombre_evento' => $_POST['nombre_evento'],
        ':descripcion' => $_POST['descripcion'] ?? null,
        ':fecha_evento' => $_POST['fecha_evento'],
        ':hora_inicio' => $_POST['hora_inicio'],
        ':hora_salida' => $_POST['hora_salida'] ?? null,
        ':lugar_salida' => $_POST['lugar_salida'],
        ':recorrido' => $_POST['recorrido'] ?? null,
        ':distancia_km' => !empty($_POST['distancia_km']) ? $_POST['distancia_km'] : null,
        ':causa_beneficiada' => $_POST['causa_beneficiada'] ?? null,
        ':personas_beneficiadas' => $_POST['personas_beneficiadas'] ?? null,
        ':cupo_maximo' => !empty($_POST['cupo_maximo']) ? $_POST['cupo_maximo'] : null,
        ':imagen_banner' => $imagen_banner
    ]);

    $id_evento = $pdo->lastInsertId();

    // 2. INSERTAR CATEGORÍAS
    if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
        $sql_categoria = "
            INSERT INTO Categorias_Evento (
                Id_Evento, Nombre_Categoria, Genero, 
                Edad_Minima, Edad_Maxima, Descripcion, Estado
            ) VALUES (
                :id_evento, :nombre, :genero, 
                :edad_min, :edad_max, :descripcion, 'Activa'
            )
        ";
        $stmt_categoria = $pdo->prepare($sql_categoria);

        foreach ($_POST['categorias'] as $categoria) {
            if (!empty($categoria['nombre']) && !empty($categoria['genero'])) {
                $stmt_categoria->execute([
                    ':id_evento' => $id_evento,
                    ':nombre' => $categoria['nombre'],
                    ':genero' => $categoria['genero'],
                    ':edad_min' => !empty($categoria['edad_min']) ? $categoria['edad_min'] : null,
                    ':edad_max' => !empty($categoria['edad_max']) ? $categoria['edad_max'] : null,
                    ':descripcion' => $categoria['descripcion'] ?? null
                ]);
            }
        }
    }

    // 3. INSERTAR COSTOS DE INSCRIPCIÓN
    if (isset($_POST['costos']) && is_array($_POST['costos'])) {
        $sql_costo = "
            INSERT INTO Costos_Inscripcion (
                Id_Evento, Tipo_Inscripcion, Descripcion, Costo,
                Fecha_Inicio, Fecha_Fin, Estado
            ) VALUES (
                :id_evento, :tipo, :descripcion, :costo,
                :fecha_inicio, :fecha_fin, 'Activo'
            )
        ";
        $stmt_costo = $pdo->prepare($sql_costo);

        foreach ($_POST['costos'] as $costo) {
            if (!empty($costo['tipo']) && !empty($costo['costo']) && 
                !empty($costo['fecha_inicio']) && !empty($costo['fecha_fin'])) {
                
                $stmt_costo->execute([
                    ':id_evento' => $id_evento,
                    ':tipo' => $costo['tipo'],
                    ':descripcion' => $costo['descripcion'] ?? null,
                    ':costo' => $costo['costo'],
                    ':fecha_inicio' => $costo['fecha_inicio'],
                    ':fecha_fin' => $costo['fecha_fin']
                ]);
            }
        }
    }

    // 4. INSERTAR CUENTAS BANCARIAS
    if (isset($_POST['cuentas']) && is_array($_POST['cuentas'])) {
        $sql_cuenta = "
            INSERT INTO Cuentas_Bancarias (
                Id_Evento, Numero_Cuenta, Nombre_Cuenta, Nombre_Banco,
                Tipo_Cuenta, Moneda, Estado, Orden_Prioridad
            ) VALUES (
                :id_evento, :numero, :nombre, :banco,
                :tipo, :moneda, 'Activa', :orden
            )
        ";
        $stmt_cuenta = $pdo->prepare($sql_cuenta);

        $orden = 1;
        foreach ($_POST['cuentas'] as $cuenta) {
            if (!empty($cuenta['banco']) && !empty($cuenta['numero']) && !empty($cuenta['nombre'])) {
                $stmt_cuenta->execute([
                    ':id_evento' => $id_evento,
                    ':numero' => $cuenta['numero'],
                    ':nombre' => $cuenta['nombre'],
                    ':banco' => $cuenta['banco'],
                    ':tipo' => $cuenta['tipo'],
                    ':moneda' => $cuenta['moneda'] ?? 'GTQ',
                    ':orden' => $orden++
                ]);
            }
        }
    }

    // Confirmar transacción
    $pdo->commit();

//    Registrar en bitácora (opcional)
     $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha) VALUES (?, ?, CURDATE())";
     $stmt_bitacora = $pdo->prepare($sql_bitacora);
     $stmt_bitacora->execute([$_SESSION['usuario_id'], "Creó evento: " . $_POST['nombre_evento']]);

    $response['success'] = true;
    $response['message'] = 'Evento creado exitosamente';
    $response['id_evento'] = $id_evento;

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Eliminar imagen si se subió
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>