<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver_eventos.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    $id_evento_origen = $_POST['id_evento_origen'];
    $nombre_evento = $_POST['nombre_evento'];
    $fecha_evento = $_POST['fecha_evento'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_salida = $_POST['hora_salida'] ?? null;
    $cupo_maximo = $_POST['cupo_maximo'] ?? null;
    $fecha_inicio_inscripciones = $_POST['fecha_inicio_inscripciones'] ?? null;
    $fecha_fin_inscripciones = $_POST['fecha_fin_inscripciones'] ?? null;
    $actualizar_costos = isset($_POST['actualizar_costos']);
    
    // 1. Obtener todos los datos del evento original
    $stmt = $pdo->prepare("SELECT * FROM Eventos WHERE Id_Evento = ?");
    $stmt->execute([$id_evento_origen]);
    $evento_original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento_original) {
        throw new Exception("Evento original no encontrado");
    }
    
    // 2. Crear el nuevo evento (copia del original con nuevos datos)
    $sql_nuevo_evento = "INSERT INTO Eventos (
        Nombre_Evento, Id_Tipo_Evento, Fecha_Evento, Hora_Inicio, Hora_Salida,
        Lugar_Salida, Distancia_KM, Cupo_Maximo, Descripcion, Recorrido,
        Causa_Beneficiada, Imagen_Banner, Estado_Evento, Fecha_Inicio_Inscripciones,
        Fecha_Fin_Inscripciones, Fecha_Creacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt_nuevo = $pdo->prepare($sql_nuevo_evento);
    $stmt_nuevo->execute([
        $nombre_evento,
        $evento_original['Id_Tipo_Evento'],
        $fecha_evento,
        $hora_inicio,
        $hora_salida,
        $evento_original['Lugar_Salida'],
        $evento_original['Distancia_KM'],
        $cupo_maximo ?: $evento_original['Cupo_Maximo'],
        $evento_original['Descripcion'],
        $evento_original['Recorrido'],
        $evento_original['Causa_Beneficiada'],
        $evento_original['Imagen_Banner'],
        'Planificado', // Estado inicial
        $fecha_inicio_inscripciones,
        $fecha_fin_inscripciones
    ]);
    
    $nuevo_evento_id = $pdo->lastInsertId();
    
    // 3. Copiar categorías del evento original
    $stmt_categorias = $pdo->prepare("
        SELECT * FROM Categorias_Evento WHERE Id_Evento = ?
    ");
    $stmt_categorias->execute([$id_evento_origen]);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($categorias as $categoria) {
        $sql_cat = "INSERT INTO Categorias_Evento (
            Id_Evento, Nombre_Categoria, Genero, Edad_Minima, Edad_Maxima, Descripcion
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt_cat = $pdo->prepare($sql_cat);
        $stmt_cat->execute([
            $nuevo_evento_id,
            $categoria['Nombre_Categoria'],
            $categoria['Genero'],
            $categoria['Edad_Minima'],
            $categoria['Edad_Maxima'],
            $categoria['Descripcion']
        ]);
    }
    
    // 4. Copiar costos de inscripción (con fechas actualizadas si se solicitó)
    $stmt_costos = $pdo->prepare("
        SELECT * FROM Costos_Inscripcion WHERE Id_Evento = ?
    ");
    $stmt_costos->execute([$id_evento_origen]);
    $costos = $stmt_costos->fetchAll(PDO::FETCH_ASSOC);
    
    if ($actualizar_costos && count($costos) > 0) {
        // Calcular diferencia en días entre evento original y nuevo
        $fecha_original = new DateTime($evento_original['Fecha_Evento']);
        $fecha_nueva = new DateTime($fecha_evento);
        $diff_dias = $fecha_original->diff($fecha_nueva)->days;
        if ($fecha_nueva < $fecha_original) {
            $diff_dias = -$diff_dias;
        }
        
        foreach ($costos as $costo) {
            $fecha_inicio = new DateTime($costo['Fecha_Inicio']);
            $fecha_fin = new DateTime($costo['Fecha_Fin']);
            
            // Ajustar fechas
            if ($diff_dias > 0) {
                $fecha_inicio->add(new DateInterval('P' . abs($diff_dias) . 'D'));
                $fecha_fin->add(new DateInterval('P' . abs($diff_dias) . 'D'));
            } else if ($diff_dias < 0) {
                $fecha_inicio->sub(new DateInterval('P' . abs($diff_dias) . 'D'));
                $fecha_fin->sub(new DateInterval('P' . abs($diff_dias) . 'D'));
            }
            
            $sql_costo = "INSERT INTO Costos_Inscripcion (
                Id_Evento, Tipo_Inscripcion, Costo, Fecha_Inicio, Fecha_Fin, Descripcion
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt_costo = $pdo->prepare($sql_costo);
            $stmt_costo->execute([
                $nuevo_evento_id,
                $costo['Tipo_Inscripcion'],
                $costo['Costo'],
                $fecha_inicio->format('Y-m-d'),
                $fecha_fin->format('Y-m-d'),
                $costo['Descripcion']
            ]);
        }
    } else {
        // Copiar costos con fechas originales
        foreach ($costos as $costo) {
            $sql_costo = "INSERT INTO Costos_Inscripcion (
                Id_Evento, Tipo_Inscripcion, Costo, Fecha_Inicio, Fecha_Fin, Descripcion
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt_costo = $pdo->prepare($sql_costo);
            $stmt_costo->execute([
                $nuevo_evento_id,
                $costo['Tipo_Inscripcion'],
                $costo['Costo'],
                $costo['Fecha_Inicio'],
                $costo['Fecha_Fin'],
                $costo['Descripcion']
            ]);
        }
    }
    
    // 5. Copiar cuentas bancarias
    $stmt_cuentas = $pdo->prepare("
        SELECT * FROM Cuentas_Bancarias WHERE Id_Evento = ?
    ");
    $stmt_cuentas->execute([$id_evento_origen]);
    $cuentas = $stmt_cuentas->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cuentas as $cuenta) {
        $sql_cuenta = "INSERT INTO Cuentas_Bancarias (
            Id_Evento, Nombre_Banco, Numero_Cuenta, Nombre_Cuenta, Tipo_Cuenta, Moneda
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt_cuenta = $pdo->prepare($sql_cuenta);
        $stmt_cuenta->execute([
            $nuevo_evento_id,
            $cuenta['Nombre_Banco'],
            $cuenta['Numero_Cuenta'],
            $cuenta['Nombre_Cuenta'],
            $cuenta['Tipo_Cuenta'],
            $cuenta['Moneda']
        ]);
    }
    
    $pdo->commit();
    
    // Redirigir al detalle del nuevo evento
    $_SESSION['mensaje_exito'] = "Evento reactivado exitosamente. Revisa la configuración y ajusta lo necesario.";
    header('Location: detalle_evento.php?id=' . $nuevo_evento_id);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['mensaje_error'] = "Error al reactivar el evento: " . $e->getMessage();
    header('Location: ver_eventos.php');
    exit;
}
?>