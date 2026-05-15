<?php
// procesar_inscripcion.php - Procesa inscripciones de eventos
header('Content-Type: application/json');
require_once 'conexion.php';

try {
    // Validar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Validar campos requeridos
    $campos_requeridos = [
        'id_evento', 'nombres', 'apellidos', 'edad', 'genero',
        'telefono', 'email', 'dpi', 'departamento', 'municipio',
        'zona', 'direccion_detallada', 'playera',
        'contacto_emergencia_nombres', 'contacto_emergencia_apellidos',
        'telefono_emergencia'
    ];

    foreach ($campos_requeridos as $campo) {
        if (!isset($_POST[$campo]) || empty(trim($_POST[$campo]))) {
            throw new Exception("El campo $campo es requerido");
        }
    }

    // Validar categoría y costo (pueden ser opcionales según el evento)
    $id_categoria = !empty($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
    $id_costo = !empty($_POST['id_costo']) ? (int)$_POST['id_costo'] : null;

    // Validar archivo de boleta
    if (!isset($_FILES['boleta_pago']) || $_FILES['boleta_pago']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Debe subir la boleta de pago');
    }

    $archivo = $_FILES['boleta_pago'];
    $tamaño_maximo = 5 * 1024 * 1024; // 5MB

    if ($archivo['size'] > $tamaño_maximo) {
        throw new Exception('El archivo no debe superar los 5MB');
    }

    // Validar tipo de archivo
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipo_archivo = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($tipo_archivo, $tipos_permitidos)) {
        throw new Exception('Solo se permiten archivos JPG, PNG o PDF');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    $id_evento = (int)$_POST['id_evento'];

    // Verificar que el evento existe y está abierto
    $stmt_evento = $pdo->prepare("SELECT * FROM Eventos WHERE Id_Evento = ?");
    $stmt_evento->execute([$id_evento]);
    $evento = $stmt_evento->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        throw new Exception('El evento no existe');
    }

    if ($evento['Estado_Evento'] !== 'Inscripciones Abiertas') {
        throw new Exception('Las inscripciones no están abiertas para este evento');
    }

    // Verificar cupos disponibles
    if ($evento['Cupo_Maximo'] > 0) {
        $stmt_count = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM Inscripciones_Evento 
            WHERE Id_Evento = ? AND Estado_Inscripcion != 'Cancelado'
        ");
        $stmt_count->execute([$id_evento]);
        $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);

        if ($count_result['total'] >= $evento['Cupo_Maximo']) {
            throw new Exception('No hay cupos disponibles para este evento');
        }
    }

    // Verificar si ya existe una inscripción con el mismo DPI
    $stmt_check = $pdo->prepare("
        SELECT * FROM Inscripciones_Evento 
        WHERE Id_Evento = ? AND DPI = ?
    ");
    $stmt_check->execute([$id_evento, trim($_POST['dpi'])]);

    if ($stmt_check->rowCount() > 0) {
        throw new Exception('Ya existe una inscripción con este DPI para este evento');
    }

    // Obtener información del costo si existe
    $monto_pagado = 0;
    if ($id_costo) {
        $stmt_costo = $pdo->prepare("SELECT Costo FROM Costos_Inscripcion WHERE Id_Costo = ?");
        $stmt_costo->execute([$id_costo]);
        $costo_info = $stmt_costo->fetch(PDO::FETCH_ASSOC);

        if ($costo_info) {
            $monto_pagado = $costo_info['Costo'];
        }
    }

    // Generar número de participante único
    $año_actual = date('Y');
    $prefijo = 'EVT' . substr($año_actual, -2);

    $stmt_numero = $pdo->prepare("
        SELECT Numero_Participante 
        FROM Inscripciones_Evento 
        WHERE Numero_Participante LIKE ? 
        ORDER BY Id_Inscripcion DESC 
        LIMIT 1
    ");
    $stmt_numero->execute([$prefijo . '%']);
    $ultimo = $stmt_numero->fetch(PDO::FETCH_ASSOC);

    if ($ultimo) {
        $ultimo_numero = intval(substr($ultimo['Numero_Participante'], -4));
        $nuevo_numero = $ultimo_numero + 1;
    } else {
        $nuevo_numero = 1;
    }

    $numero_participante = $prefijo . str_pad($nuevo_numero, 4, '0', STR_PAD_LEFT);

    // Construir datos
    $nombre_completo = trim($_POST['nombres']) . ' ' . trim($_POST['apellidos']);
    $direccion_completa = trim($_POST['direccion_detallada']) . ', Zona ' . trim($_POST['zona']) .
                         ', ' . trim($_POST['municipio']) . ', ' . trim($_POST['departamento']);
    $contacto_emergencia = trim($_POST['contacto_emergencia_nombres']) . ' ' .
                          trim($_POST['contacto_emergencia_apellidos']);

    // Insertar inscripción (SIN columna Boleta_Pago)
    $sql_inscripcion = "
        INSERT INTO Inscripciones_Evento (
            Id_Evento,
            Id_Categoria,
            Id_Costo,
            Numero_Participante,
            Nombre_Completo,
            Edad,
            Genero,
            Telefono,
            Email,
            DPI,
            Direccion,
            Talla_Playera,
            Contacto_Emergencia,
            Telefono_Emergencia,
            Fecha_Inscripcion,
            Hora_Inscripcion,
            Estado_Pago,
            Monto_Pagado,
            Estado_Inscripcion
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'Pendiente', ?, 'Pendiente'
        )
    ";

    $stmt_inscripcion = $pdo->prepare($sql_inscripcion);
    $stmt_inscripcion->execute([
        $id_evento,
        $id_categoria,
        $id_costo,
        $numero_participante,
        $nombre_completo,
        (int)$_POST['edad'],
        $_POST['genero'],
        trim($_POST['telefono']),
        trim($_POST['email']),
        trim($_POST['dpi']),
        $direccion_completa,
        $_POST['playera'],
        $contacto_emergencia,
        trim($_POST['telefono_emergencia']),
        $monto_pagado
    ]);

    $id_inscripcion = $pdo->lastInsertId();

    // Guardar archivo de boleta
    $directorio_boletas = 'uploads/boletas/';
    if (!file_exists($directorio_boletas)) {
        mkdir($directorio_boletas, 0777, true);
    }

    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_archivo = $numero_participante . '_' . time() . '.' . $extension;
    $ruta_completa = $directorio_boletas . $nombre_archivo;

    if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        throw new Exception('Error al guardar el archivo');
    }

    // Insertar en tabla de boletas
    $sql_boleta = "
        INSERT INTO Boletas_Pago_Evento (
            Inscripcion_Id,
            Nombre_Archivo,
            Ruta_Archivo,
            Tipo_Archivo,
            Tamaño_Archivo,
            Fecha_Subida,
            Estado_Verificacion
        ) VALUES (?, ?, ?, ?, ?, NOW(), 'Pendiente')
    ";

    $stmt_boleta = $pdo->prepare($sql_boleta);
    $stmt_boleta->execute([
        $id_inscripcion,
        $archivo['name'],
        $ruta_completa,
        $tipo_archivo,
        $archivo['size']
    ]);

    // Confirmar transacción
    $pdo->commit();

    // Enviar respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Inscripción registrada exitosamente',
        'numero_participante' => $numero_participante,
        'id_inscripcion' => $id_inscripcion
    ]);

} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Eliminar archivo si se subió
    if (isset($ruta_completa) && file_exists($ruta_completa)) {
        unlink($ruta_completa);
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Eliminar archivo si se subió
    if (isset($ruta_completa) && file_exists($ruta_completa)) {
        unlink($ruta_completa);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>