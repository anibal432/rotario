<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $id_evento = (int)($_POST['id_evento'] ?? 0);
    if (!$id_evento) throw new Exception('ID de evento inválido');

    $campos = ['nombre_evento', 'id_tipo_evento', 'fecha_evento', 'hora_inicio', 'lugar_salida'];
    foreach ($campos as $campo) {
        if (empty($_POST[$campo])) throw new Exception("El campo $campo es requerido");
    }

    // Verificar que el evento existe
    $stmt = $pdo->prepare("SELECT Id_Evento, Imagen_Banner FROM Eventos WHERE Id_Evento = ?");
    $stmt->execute([$id_evento]);
    $evento_actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$evento_actual) throw new Exception('Evento no encontrado');

    $pdo->beginTransaction();

    // Procesar imagen si se subió una nueva
    $imagen_banner = $evento_actual['Imagen_Banner'];
    if (isset($_FILES['imagen_banner']) && $_FILES['imagen_banner']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '/var/www/html/uploads/eventos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $extension = pathinfo($_FILES['imagen_banner']['name'], PATHINFO_EXTENSION);
        $filename  = 'banner_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath  = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['imagen_banner']['tmp_name'], $filepath)) {
            throw new Exception('Error al subir la imagen');
        }

        // Eliminar banner anterior usando ruta absoluta
        if ($imagen_banner && file_exists('/var/www/html' . $imagen_banner)) {
            unlink('/var/www/html' . $imagen_banner);
        }

        // Guardar ruta relativa para el navegador
        $imagen_banner = '/uploads/eventos/' . $filename;
    }

    // UPDATE evento
    $sql_evento = "UPDATE Eventos SET
        Id_Tipo_Evento        = :id_tipo_evento,
        Nombre_Evento         = :nombre_evento,
        Descripcion           = :descripcion,
        Fecha_Evento          = :fecha_evento,
        Hora_Inicio           = :hora_inicio,
        Lugar_Salida          = :lugar_salida,
        Recorrido             = :recorrido,
        Distancia_KM          = :distancia_km,
        Causa_Beneficiada     = :causa_beneficiada,
        Personas_Beneficiadas = :personas_beneficiadas,
        Estado_Evento         = :estado_evento,
        Cupo_Maximo           = :cupo_maximo,
        Imagen_Banner         = :imagen_banner
        WHERE Id_Evento       = :id_evento";

    $pdo->prepare($sql_evento)->execute([
        ':id_tipo_evento'        => $_POST['id_tipo_evento'],
        ':nombre_evento'         => $_POST['nombre_evento'],
        ':descripcion'           => $_POST['descripcion'] ?? null,
        ':fecha_evento'          => $_POST['fecha_evento'],
        ':hora_inicio'           => $_POST['hora_inicio'],
        ':lugar_salida'          => $_POST['lugar_salida'],
        ':recorrido'             => $_POST['recorrido'] ?? null,
        ':distancia_km'          => !empty($_POST['distancia_km']) ? $_POST['distancia_km'] : null,
        ':causa_beneficiada'     => $_POST['causa_beneficiada'] ?? null,
        ':personas_beneficiadas' => $_POST['personas_beneficiadas'] ?? null,
        ':estado_evento'         => $_POST['estado_evento'],
        ':cupo_maximo'           => !empty($_POST['cupo_maximo']) ? $_POST['cupo_maximo'] : null,
        ':imagen_banner'         => $imagen_banner,
        ':id_evento'             => $id_evento
    ]);

    // CATEGORÍAS: actualizar existentes, insertar nuevas
    if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
        $stmt_update_cat = $pdo->prepare("UPDATE Categorias_Evento SET
            Nombre_Categoria = :nombre,
            Genero           = :genero,
            Edad_Minima      = :edad_min,
            Edad_Maxima      = :edad_max,
            Descripcion      = :descripcion
            WHERE Id_Categoria = :id AND Id_Evento = :id_evento");

        $stmt_insert_cat = $pdo->prepare("INSERT INTO Categorias_Evento
            (Id_Evento, Nombre_Categoria, Genero, Edad_Minima, Edad_Maxima, Descripcion, Estado)
            VALUES (:id_evento, :nombre, :genero, :edad_min, :edad_max, :descripcion, 'Activa')");

        foreach ($_POST['categorias'] as $cat) {
            if (empty($cat['nombre']) || empty($cat['genero'])) continue;

            if (!empty($cat['id'])) {
                // Actualizar existente
                $stmt_update_cat->execute([
                    ':nombre'      => $cat['nombre'],
                    ':genero'      => $cat['genero'],
                    ':edad_min'    => !empty($cat['edad_min']) ? $cat['edad_min'] : null,
                    ':edad_max'    => !empty($cat['edad_max']) ? $cat['edad_max'] : null,
                    ':descripcion' => $cat['descripcion'] ?? null,
                    ':id'          => $cat['id'],
                    ':id_evento'   => $id_evento
                ]);
            } else {
                // Insertar nueva
                $stmt_insert_cat->execute([
                    ':id_evento'   => $id_evento,
                    ':nombre'      => $cat['nombre'],
                    ':genero'      => $cat['genero'],
                    ':edad_min'    => !empty($cat['edad_min']) ? $cat['edad_min'] : null,
                    ':edad_max'    => !empty($cat['edad_max']) ? $cat['edad_max'] : null,
                    ':descripcion' => $cat['descripcion'] ?? null
                ]);
            }
        }
    }

    // COSTOS: actualizar existentes, insertar nuevos
    if (isset($_POST['costos']) && is_array($_POST['costos'])) {
        $stmt_update_cos = $pdo->prepare("UPDATE Costos_Inscripcion SET
            Tipo_Inscripcion = :tipo,
            Descripcion      = :descripcion,
            Costo            = :costo,
            Fecha_Inicio     = :fecha_inicio,
            Fecha_Fin        = :fecha_fin
            WHERE Id_Costo = :id AND Id_Evento = :id_evento");

        $stmt_insert_cos = $pdo->prepare("INSERT INTO Costos_Inscripcion
            (Id_Evento, Tipo_Inscripcion, Descripcion, Costo, Fecha_Inicio, Fecha_Fin, Estado)
            VALUES (:id_evento, :tipo, :descripcion, :costo, :fecha_inicio, :fecha_fin, 'Activo')");

        foreach ($_POST['costos'] as $costo) {
            if (empty($costo['tipo']) || empty($costo['costo'])) continue;

            if (!empty($costo['id'])) {
                // Actualizar existente
                $stmt_update_cos->execute([
                    ':tipo'         => $costo['tipo'],
                    ':descripcion'  => $costo['descripcion'] ?? null,
                    ':costo'        => $costo['costo'],
                    ':fecha_inicio' => $costo['fecha_inicio'],
                    ':fecha_fin'    => $costo['fecha_fin'],
                    ':id'           => $costo['id'],
                    ':id_evento'    => $id_evento
                ]);
            } else {
                // Insertar nuevo
                $stmt_insert_cos->execute([
                    ':id_evento'    => $id_evento,
                    ':tipo'         => $costo['tipo'],
                    ':descripcion'  => $costo['descripcion'] ?? null,
                    ':costo'        => $costo['costo'],
                    ':fecha_inicio' => $costo['fecha_inicio'],
                    ':fecha_fin'    => $costo['fecha_fin']
                ]);
            }
        }
    }

    // CUENTAS: delete + reinsert (no tienen FK con inscripciones, es seguro)
    $pdo->prepare("DELETE FROM Cuentas_Bancarias WHERE Id_Evento = ?")->execute([$id_evento]);
    if (isset($_POST['cuentas']) && is_array($_POST['cuentas'])) {
        $stmt_cue = $pdo->prepare("INSERT INTO Cuentas_Bancarias
            (Id_Evento, Numero_Cuenta, Nombre_Cuenta, Nombre_Banco, Tipo_Cuenta, Moneda, Estado, Orden_Prioridad)
            VALUES (:id_evento, :numero, :nombre, :banco, :tipo, :moneda, 'Activa', :orden)");
        $orden = 1;
        foreach ($_POST['cuentas'] as $cuenta) {
            if (!empty($cuenta['banco']) && !empty($cuenta['numero'])) {
                $stmt_cue->execute([
                    ':id_evento' => $id_evento,
                    ':numero'    => $cuenta['numero'],
                    ':nombre'    => $cuenta['nombre'],
                    ':banco'     => $cuenta['banco'],
                    ':tipo'      => $cuenta['tipo'],
                    ':moneda'    => $cuenta['moneda'] ?? 'GTQ',
                    ':orden'     => $orden++
                ]);
            }
        }
    }

    // Bitácora
    $pdo->prepare("INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha) VALUES (?, ?, CURDATE())")
        ->execute([$_SESSION['user_id'], "Editó evento: " . $_POST['nombre_evento']]);

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Evento actualizado exitosamente';

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($filepath) && file_exists($filepath)) unlink($filepath);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>