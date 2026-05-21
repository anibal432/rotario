<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ver_becados.php');
    exit;
}

$id_estudiante = $_GET['id'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';
$requiere_confirmacion = false;
$datos_formulario = [];

$sql = "SELECT 
            e.*,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Promedio_Minimo,
            b.Promedio_Actual
        FROM Estudiantes e
        LEFT JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE e.Id_Estudiante = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: ver_becados.php');
    exit;
}

$sql_boletas = "SELECT * FROM Boletas_Calificaciones 
                WHERE Id_Estudiante = ? 
                ORDER BY Fecha_Subida DESC";
$stmt_boletas = $pdo->prepare($sql_boletas);
$stmt_boletas->execute([$id_estudiante]);
$boletas_anteriores = $stmt_boletas->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $promedio = $_POST['promedio'];
        $periodo = $_POST['periodo'];
        $fecha_subida = $_POST['fecha_subida'];
        $observaciones = $_POST['observaciones'] ?? '';
        $confirmar_suspension = $_POST['confirmar_suspension'] ?? 'no';
        
        $datos_formulario = [
            'promedio' => $promedio,
            'periodo' => $periodo,
            'fecha_subida' => $fecha_subida,
            'observaciones' => $observaciones
        ];
        
        if (empty($promedio) || empty($periodo) || empty($fecha_subida)) {
            throw new Exception('Por favor completa todos los campos obligatorios');
        }

        if ($promedio < 0 || $promedio > 100) {
            throw new Exception('El promedio debe estar entre 0 y 100');
        }

        if ($promedio < 75 && $confirmar_suspension !== 'si') {
            $requiere_confirmacion = true;
            throw new Exception('CONFIRMACIÓN_REQUERIDA');
        }

        // ─────────────────────────────────────────────────────────────────────
        // CORRECCIÓN PRINCIPAL: guardar ruta RELATIVA, no absoluta
        // Ruta relativa desde la raíz del sitio: uploads/boletas/AÑO/archivo.ext
        // Así la URL quedará: https://dominio.com/uploads/boletas/2026/archivo.ext
        // ─────────────────────────────────────────────────────────────────────
        $ruta_archivo = null;
        if (isset($_FILES['archivo_boleta']) && $_FILES['archivo_boleta']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo_boleta'];
            
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $tipos_permitidos = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($extension, $tipos_permitidos)) {
                throw new Exception('Solo se permiten archivos PDF, JPG, JPEG o PNG');
            }

            if ($archivo['size'] > 5 * 1024 * 1024) {
                throw new Exception('El archivo no debe superar los 5MB');
            }

            // Carpeta relativa (desde raíz del sitio)
            $carpeta_relativa = 'uploads/boletas/' . date('Y');
            // Ruta absoluta en el servidor para crear el directorio y mover el archivo
            $directorio_absoluto = $_SERVER['DOCUMENT_ROOT'] . '/' . $carpeta_relativa;

            if (!file_exists($directorio_absoluto)) {
                mkdir($directorio_absoluto, 0755, true);
            }

            $nombre_archivo = 'boleta_' . $id_estudiante . '_' . time() . '.' . $extension;
            $ruta_completa_absoluta = $directorio_absoluto . '/' . $nombre_archivo;

            if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa_absoluta)) {
                throw new Exception('Error al subir el archivo. Verifica los permisos del directorio.');
            }

            // Solo guardamos la ruta RELATIVA en la base de datos
            $ruta_archivo = $carpeta_relativa . '/' . $nombre_archivo;
        }

        $pdo->beginTransaction();

        $sql_insert = "INSERT INTO Boletas_Calificaciones 
                       (Id_Estudiante, Promedio, Archivo_Boleta, Periodo, Fecha_Subida, 
                        Observaciones, Id_Usuario_Registro)
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $id_estudiante,
            $promedio,
            $ruta_archivo,
            $periodo,
            $fecha_subida,
            $observaciones,
            $user_id
        ]);

        if ($estudiante['Id_Beca']) {
            $sql_update_promedio = "UPDATE Becas_Otorgadas 
                                    SET Promedio_Actual = ?
                                    WHERE Id_Beca = ?";
            $stmt_update = $pdo->prepare($sql_update_promedio);
            $stmt_update->execute([$promedio, $estudiante['Id_Beca']]);
            
            if ($promedio < 75) {
                $sql_suspender = "UPDATE Becas_Otorgadas 
                                 SET Estado_Beca = 'Suspendida',
                                     Fecha_Suspension = NOW(),
                                     Motivo_Suspension = ?
                                 WHERE Id_Beca = ?";
                $motivo = "Suspensión automática por promedio bajo ({$promedio} puntos). Mínimo requerido: 75 puntos.";
                $stmt_suspender = $pdo->prepare($sql_suspender);
                $stmt_suspender->execute([$motivo, $estudiante['Id_Beca']]);
                
                $sql_suspender_est = "UPDATE Estudiantes 
                                     SET Estado_Beca = 'Suspendido'
                                     WHERE Id_Estudiante = ?";
                $stmt_suspender_est = $pdo->prepare($sql_suspender_est);
                $stmt_suspender_est->execute([$id_estudiante]);
                
                $sql_bitacora_suspension = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                                           VALUES (?, ?, CURDATE())";
                $actividad_suspension = "🚫 BECA SUSPENDIDA AUTOMÁTICAMENTE - {$estudiante['Nombres_Apellidos']} - Promedio: {$promedio} puntos (Mínimo: 75)";
                $stmt_bitacora_susp = $pdo->prepare($sql_bitacora_suspension);
                $stmt_bitacora_susp->execute([$user_id, $actividad_suspension]);
            }
        }

        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                         VALUES (?, ?, CURDATE())";
        $actividad = "Registró boleta de calificaciones para {$estudiante['Nombres_Apellidos']} - Promedio: {$promedio}";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $stmt_bitacora->execute([$user_id, $actividad]);

        if ($estudiante['Promedio_Minimo'] && $promedio < $estudiante['Promedio_Minimo']) {
            $sql_alerta = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                          VALUES (?, ?, CURDATE())";
            $actividad_alerta = "⚠️ ALERTA: {$estudiante['Nombres_Apellidos']} tiene promedio BAJO ({$promedio}) - Mínimo requerido: {$estudiante['Promedio_Minimo']}";
            $stmt_alerta = $pdo->prepare($sql_alerta);
            $stmt_alerta->execute([$user_id, $actividad_alerta]);
        }

        $pdo->commit();

        if ($promedio < 75) {
            $mensaje = '⚠️ BECA SUSPENDIDA AUTOMÁTICAMENTE<br><br>';
            $mensaje .= 'La boleta se registró correctamente, pero debido a que el promedio (' . $promedio . ' puntos) está por debajo del mínimo requerido (75 puntos), <strong>la beca ha sido suspendida automáticamente</strong>.<br><br>';
            $mensaje .= '<strong>El estudiante deberá:</strong><br>';
            $mensaje .= '• Mejorar su rendimiento académico<br>';
            $mensaje .= '• Alcanzar un promedio mínimo de 75 puntos<br>';
            $mensaje .= '• Solicitar reactivación de la beca<br>';
            $tipo_mensaje = 'error';
        } else {
            $mensaje = '✓ Boleta registrada exitosamente';
            $tipo_mensaje = 'success';
            
            if ($estudiante['Promedio_Minimo'] && $promedio < $estudiante['Promedio_Minimo']) {
                $mensaje .= '<br><br><strong>⚠️ ADVERTENCIA:</strong> El promedio registrado (' . $promedio . ') está por debajo del mínimo requerido (' . $estudiante['Promedio_Minimo'] . '), pero aún no alcanza el umbral de suspensión (75 puntos).';
                $tipo_mensaje = 'warning';
            }
        }

        $stmt_boletas->execute([$id_estudiante]);
        $boletas_anteriores = $stmt_boletas->fetchAll(PDO::FETCH_ASSOC);
        $stmt->execute([$id_estudiante]);
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $datos_formulario = [];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = 'Error al registrar la boleta: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getMessage() !== 'CONFIRMACIÓN_REQUERIDA') {
            $mensaje = $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}

$periodos_escolares = [
    'Primer Bimestre',
    'Segundo Bimestre',
    'Tercer Bimestre',
    'Cuarto Bimestre',
    'Primer Trimestre',
    'Segundo Trimestre',
    'Tercer Trimestre',
    'Primer Semestre',
    'Segundo Semestre',
    'Anual'
];

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(mb_substr($word, 0, 1));
            }
        }
        return mb_substr($initials, 0, 2);
    }
}

/**
 * Convierte cualquier ruta almacenada en BD a una URL web válida.
 * Maneja tanto rutas absolutas antiguas (/var/www/html/uploads/...)
 * como rutas relativas nuevas (uploads/boletas/...).
 */
function buildFileUrl($ruta) {
    if (empty($ruta)) return null;
    // Si la ruta ya es relativa (no empieza con /), úsala directamente
    if (!str_starts_with($ruta, '/')) {
        return '/' . $ruta;
    }
    // Si es una ruta absoluta del servidor, extrae la parte relativa
    // Elimina el prefijo /var/www/html o similar hasta llegar a /uploads
    if (preg_match('#(/uploads/.+)$#', $ruta, $m)) {
        return $m[1];
    }
    // Fallback: devolver tal cual (podría fallar, pero al menos no rompe)
    return $ruta;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Boleta - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            width: 100%;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px 40px;
            width: calc(100% - 280px);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #004b87, #ffa500);
        }

        .header h1 {
            color: #004b87;
            font-size: 28px;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .user-info:hover {
            transform: translateY(-2px);
        }

        .user-avatar-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-role-icon {
            font-size: 1.2em;
            color: #004b87;
        }

        .user-avatar-main {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #004b87, #0066b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 8px rgba(0, 75, 135, 0.3);
        }

        .user-details-main {
            display: flex;
            flex-direction: column;
        }

        .user-name-main {
            color: #004b87;
            font-weight: 600;
            font-size: 15px;
        }

        .user-role-main {
            color: #666;
            font-size: 13px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .alert-success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745; 
        }
        
        .alert-warning { 
            background: #fff3cd; 
            color: #856404; 
            border-left: 4px solid #ffc107; 
        }
        
        .alert-error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545; 
        }
        
        .alert-info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-left: 4px solid #17a2b8; 
        }

        .alert-promedio-bajo {
            background: #fff3cd;
            border: 3px solid #ffc107;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        .alert-promedio-bajo h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-promedio-bajo ul {
            margin-left: 25px;
            margin-top: 15px;
            color: #856404;
        }
        
        .alert-promedio-bajo li {
            margin: 8px 0;
            line-height: 1.5;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .form-section, .historial-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i { 
            font-size: 22px; 
            color: #ffc107; 
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #004b87;
        }
        
        .info-box h3 { 
            color: #004b87; 
            margin-bottom: 15px; 
            font-size: 1.1em; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 10px; 
        }
        
        .info-label { 
            color: #7f8c8d; 
            font-weight: 600; 
        }
        
        .info-value { 
            color: #2c3e50; 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group label .required { 
            color: #dc3545; 
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }
        
        .form-group textarea { 
            resize: vertical; 
            min-height: 80px; 
        }
        
        .form-group .hint { 
            font-size: 0.85em; 
            color: #7f8c8d; 
            margin-top: 5px; 
        }
        
        .file-upload { 
            position: relative; 
            display: block; 
        }
        
        .file-upload input[type="file"] { 
            display: none; 
        }
        
        .file-upload-button {
            display: block;
            width: 100%;
            padding: 40px 20px;
            border: 3px dashed #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .file-upload-button:hover { 
            border-color: #ffc107; 
            background: #fffbf0; 
        }
        
        .file-upload-button i { 
            font-size: 3em; 
            color: #004b87; 
            margin-bottom: 15px; 
            display: block; 
        }
        
        .file-name {
            margin-top: 10px;
            padding: 10px;
            background: #d4edda;
            border-radius: 5px;
            color: #155724;
            display: none;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #004b87;
            color: white;
            width: 100%;
            justify-content: center;
        }
        
        .btn-primary:hover { 
            background: #003366; 
            transform: translateY(-2px); 
        }
        
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        
        .btn-secondary:hover { 
            background: #5a6268; 
            transform: translateY(-2px); 
        }
        
        .btn-small { 
            padding: 6px 12px; 
            font-size: 0.85em; 
        }
        
        .boletas-list { 
            max-height: 500px; 
            overflow-y: auto; 
        }
        
        .boleta-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 5px solid #28a745;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s ease;
        }
        
        .boleta-item:hover { 
            transform: translateX(5px); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        }
        
        .boleta-item.promedio-bajo-item { 
            border-left-color: #dc3545; 
        }
        
        .boleta-info { 
            flex: 1; 
        }
        
        .boleta-periodo { 
            font-weight: 600; 
            color: #2c3e50; 
            font-size: 1.1em; 
            margin-bottom: 5px; 
        }
        
        .boleta-fecha { 
            font-size: 0.85em; 
            color: #7f8c8d; 
            margin-bottom: 5px; 
        }
        
        .boleta-observaciones { 
            font-size: 0.9em; 
            color: #495057; 
            font-style: italic; 
        }
        
        .boleta-promedio { 
            text-align: center; 
            min-width: 100px; 
        }
        
        .boleta-promedio-numero { 
            font-size: 2.5em; 
            font-weight: 700; 
            display: block; 
        }
        
        .boleta-promedio-numero.bueno { 
            color: #28a745; 
        }
        
        .boleta-promedio-numero.regular { 
            color: #ffc107; 
        }
        
        .boleta-promedio-numero.malo { 
            color: #dc3545; 
        }
        
        .boleta-acciones { 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
            margin-left: 15px; 
        }
        
        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #bdc3c7; 
        }
        
        .empty-state i { 
            font-size: 4em; 
            margin-bottom: 20px; 
            opacity: 0.3; 
        }
        
        .empty-state h3 { 
            font-size: 1.2em; 
            margin-bottom: 10px; 
            color: #7f8c8d; 
        }

        .promedio-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.2em;
            font-weight: 700;
        }
        
        .promedio-bueno { 
            background: #d4edda; 
            color: #155724; 
        }
        
        .promedio-regular { 
            background: #fff3cd; 
            color: #856404; 
        }
        
        .promedio-bajo { 
            background: #f8d7da; 
            color: #721c24; 
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            background: #dc3545;
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .modal-header i { 
            font-size: 2em; 
        }
        
        .modal-header h2 { 
            font-size: 24px; 
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .modal-body h3 {
            color: #dc3545;
            margin-bottom: 20px;
            font-size: 1.4em;
        }
        
        .modal-body p {
            line-height: 1.8;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .modal-body ul {
            margin-left: 25px;
            margin-top: 15px;
            color: #2c3e50;
        }
        
        .modal-body li {
            margin: 10px 0;
            line-height: 1.6;
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            padding: 25px 30px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 12px 30px;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        @media (max-width: 968px) {
            .main-content { 
                margin-left: 0; 
                padding: 20px; 
            }
            
            .content-grid { 
                grid-template-columns: 1fr; 
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <div class="header">
                <h1>Subir Boleta de Calificaciones</h1>
                <div class="user-info">
                    <?php if ($estudiante['Promedio_Actual']): ?>
                        <?php
                        $clase = 'promedio-bueno';
                        if ($estudiante['Promedio_Actual'] < 75) {
                            $clase = 'promedio-bajo';
                        } elseif ($estudiante['Promedio_Actual'] < 80) {
                            $clase = 'promedio-regular';
                        }
                        ?>
                        <div style="margin-right: 20px;">
                            <div style="font-size: 0.85em; color: #7f8c8d; margin-bottom: 5px; text-align: center;">Promedio Actual</div>
                            <div class="promedio-badge <?= $clase ?>">
                                <?= number_format($estudiante['Promedio_Actual'], 1) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="user-avatar-wrapper">
                        <?php
                            $iconClass = '';
                            switch ($role) {
                                case 'Administrador':
                                    $iconClass = 'fa-solid fa-crown';
                                    break;
                                case 'Coordinador':
                                    $iconClass = 'fa-solid fa-user-tie';
                                    break;
                                default:
                                    $iconClass = 'fa-solid fa-user';
                                    break;
                            }
                        ?>
                        <i class="<?= $iconClass ?> user-role-icon"></i>
                        <div class="user-avatar-main"><?= getInitials($username) ?></div>
                    </div>
                    <div class="user-details-main">
                        <div class="user-name-main"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role-main"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                <div><?= $mensaje ?></div>
            </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Información del Estudiante:</strong> 
                    <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?> - 
                    Expediente: <?= htmlspecialchars($estudiante['Numero_Expediente']) ?>
                </div>
            </div>

            <div class="content-grid">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-plus-circle"></i> Nueva Boleta
                    </h3>

                    <div class="info-box">
                        <h3><i class="fas fa-info-circle"></i> Información Importante</h3>
                        <div class="info-item">
                            <span class="info-label">Promedio Mínimo:</span>
                            <span class="info-value"><strong><?= $estudiante['Promedio_Minimo'] ?? 'No especificado' ?> puntos</strong></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Umbral de Suspensión:</span>
                            <span class="info-value"><strong style="color: #dc3545;">75 puntos</strong></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Formatos Permitidos:</span>
                            <span class="info-value">PDF, JPG, PNG</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tamaño Máximo:</span>
                            <span class="info-value">5 MB</span>
                        </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" id="formBoleta">
                        <input type="hidden" name="confirmar_suspension" id="confirmar_suspension" value="no">
                        
                        <div class="form-group">
                            <label>Archivo de Boleta</label>
                            <div class="file-upload">
                                <input type="file" 
                                       id="archivo_boleta" 
                                       name="archivo_boleta" 
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       onchange="mostrarNombreArchivo(this)">
                                <label for="archivo_boleta" class="file-upload-button">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div><strong>Click para seleccionar archivo</strong></div>
                                    <div style="font-size: 0.9em; margin-top: 10px; color: #7f8c8d;">
                                        o arrastra y suelta aquí
                                    </div>
                                </label>
                                <div class="file-name" id="fileName"></div>
                            </div>
                            <div class="hint">Opcional - Sube una copia de la boleta en PDF o imagen</div>
                        </div>

                        <div class="form-group">
                            <label>Promedio <span class="required">*</span></label>
                            <input type="number" 
                                   name="promedio" 
                                   id="promedio"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   placeholder="Ej: 85.5"
                                   value="<?= htmlspecialchars($datos_formulario['promedio'] ?? '') ?>"
                                   onchange="verificarPromedio(this.value)"
                                   required>
                            <div class="hint">Promedio de calificaciones (0-100 puntos)</div>
                            <div id="alerta-promedio" style="display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label>Período <span class="required">*</span></label>
                            <select name="periodo" required>
                                <option value="">Selecciona el período...</option>
                                <?php foreach ($periodos_escolares as $per): ?>
                                    <option value="<?= $per ?> <?= date('Y') ?>" 
                                            <?= ($datos_formulario['periodo'] ?? '') === ($per . ' ' . date('Y')) ? 'selected' : '' ?>>
                                        <?= $per ?> <?= date('Y') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="hint">Período académico al que corresponde esta boleta</div>
                        </div>

                        <div class="form-group">
                            <label>Fecha de la Boleta <span class="required">*</span></label>
                            <input type="date" 
                                   name="fecha_subida" 
                                   value="<?= htmlspecialchars($datos_formulario['fecha_subida'] ?? date('Y-m-d')) ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   required>
                            <div class="hint">Fecha de emisión de la boleta</div>
                        </div>

                        <div class="form-group">
                            <label>Observaciones</label>
                            <textarea name="observaciones" 
                                      placeholder="Comentarios adicionales sobre el rendimiento académico..."><?= htmlspecialchars($datos_formulario['observaciones'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Registrar Boleta
                        </button>
                    </form>

                    <div style="margin-top: 20px;">
                        <a href="estudiantes_becados.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Becados
                        </a>
                    </div>
                </div>

                <div class="historial-section">
                    <h3 class="section-title">
                        <i class="fas fa-history"></i> Historial de Boletas
                    </h3>

                    <?php if (count($boletas_anteriores) > 0): ?>
                    <div class="boletas-list">
                        <?php foreach ($boletas_anteriores as $boleta): 
                            $clase_promedio = 'bueno';
                            if ($boleta['Promedio'] < 75) {
                                $clase_promedio = 'malo';
                            } elseif ($boleta['Promedio'] < 80) {
                                $clase_promedio = 'regular';
                            }
                            // ── CORRECCIÓN: construir URL correcta desde la ruta guardada ──
                            $url_archivo = buildFileUrl($boleta['Archivo_Boleta'] ?? null);
                        ?>
                        <div class="boleta-item <?= $clase_promedio === 'malo' ? 'promedio-bajo-item' : '' ?>">
                            <div class="boleta-info">
                                <div class="boleta-periodo"><?= htmlspecialchars($boleta['Periodo']) ?></div>
                                <div class="boleta-fecha">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($boleta['Fecha_Subida'])) ?>
                                </div>
                                <?php if ($boleta['Observaciones']): ?>
                                <div class="boleta-observaciones">
                                    <?= htmlspecialchars($boleta['Observaciones']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="boleta-promedio">
                                <span class="boleta-promedio-numero <?= $clase_promedio ?>">
                                    <?= number_format($boleta['Promedio'], 1) ?>
                                </span>
                                <span style="font-size: 0.85em; color: #7f8c8d;">puntos</span>
                            </div>
                            <?php if ($url_archivo): ?>
                            <div class="boleta-acciones">
                                <a href="<?= htmlspecialchars($url_archivo) ?>" 
                                   target="_blank" 
                                   class="btn btn-primary btn-small">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>Sin boletas registradas</h3>
                        <p>Esta será la primera boleta para este estudiante</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Confirmación -->
    <div id="modalConfirmacion" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>⚠️ ADVERTENCIA: SUSPENSIÓN DE BECA</h2>
            </div>
            <div class="modal-body">
                <h3>El promedio ingresado (<span id="promedioModal"></span> puntos) es menor a 75 puntos</h3>
                <p><strong>Esto causará la SUSPENSIÓN AUTOMÁTICA de la beca.</strong></p>
                <p>Al confirmar esta acción:</p>
                <ul>
                    <li><strong>La beca será suspendida inmediatamente</strong></li>
                    <li>El estado de la beca cambiará a "Suspendida"</li>
                    <li>Se registrará en el sistema el motivo de la suspensión</li>
                    <li>El estudiante deberá mejorar su promedio a 75+ puntos</li>
                    <li>Se requerirá una solicitud de reactivación posterior</li>
                </ul>
                <p style="margin-top: 20px; font-weight: 600; color: #dc3545;">
                    ¿Está seguro de que desea continuar?
                </p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmarSuspension()">
                    <i class="fas fa-check"></i> Sí, Confirmar Suspensión
                </button>
            </div>
        </div>
    </div>

    <script>
        function verificarPromedio(valor) {
            const alerta = document.getElementById('alerta-promedio');
            const promedio = parseFloat(valor);
            
            if (isNaN(promedio)) {
                alerta.style.display = 'none';
                return;
            }
            
            if (promedio < 75) {
                alerta.innerHTML = `
                    <div class="alert-promedio-bajo" style="margin-top: 15px;">
                        <h3><i class="fas fa-exclamation-triangle"></i> ¡ATENCIÓN!</h3>
                        <p style="color: #856404; font-weight: 600; margin-top: 10px;">
                            El promedio ingresado (${promedio} puntos) está por debajo del umbral de 75 puntos.
                        </p>
                        <p style="color: #856404; margin-top: 10px;"><strong>Consecuencias:</strong></p>
                        <ul>
                            <li>La beca será <strong>SUSPENDIDA AUTOMÁTICAMENTE</strong></li>
                            <li>El estudiante no recibirá más pagos</li>
                            <li>Deberá mejorar su rendimiento académico</li>
                            <li>Requerirá solicitud de reactivación</li>
                        </ul>
                    </div>
                `;
                alerta.style.display = 'block';
            } else if (promedio < 80) {
                alerta.innerHTML = `
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <p style="color: #856404; margin: 0;">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Advertencia:</strong> El promedio está por debajo de 80 puntos. Se recomienda seguimiento académico.
                        </p>
                    </div>
                `;
                alerta.style.display = 'block';
            } else {
                alerta.style.display = 'none';
            }
        }

        const form = document.getElementById('formBoleta');
        form.addEventListener('submit', function(e) {
            const promedio = parseFloat(document.getElementById('promedio').value);
            const confirmarSuspensionInput = document.getElementById('confirmar_suspension');
            
            if (promedio < 75 && confirmarSuspensionInput.value !== 'si') {
                e.preventDefault();
                document.getElementById('promedioModal').textContent = promedio.toFixed(2);
                document.getElementById('modalConfirmacion').style.display = 'block';
            }
        });

        function confirmarSuspension() {
            document.getElementById('confirmar_suspension').value = 'si';
            document.getElementById('modalConfirmacion').style.display = 'none';
            document.getElementById('formBoleta').submit();
        }

        function cerrarModal() {
            document.getElementById('modalConfirmacion').style.display = 'none';
            document.getElementById('confirmar_suspension').value = 'no';
        }

        function mostrarNombreArchivo(input) {
            const fileName = document.getElementById('fileName');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const size = (file.size / 1024 / 1024).toFixed(2);
                fileName.innerHTML = `<i class="fas fa-file"></i> ${file.name} (${size} MB)`;
                fileName.style.display = 'block';
            }
        }

        const fileUploadButton = document.querySelector('.file-upload-button');
        const fileInput = document.getElementById('archivo_boleta');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadButton.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadButton.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadButton.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            fileUploadButton.style.borderColor = '#ffc107';
            fileUploadButton.style.background = '#fffbf0';
        }

        function unhighlight() {
            fileUploadButton.style.borderColor = '#e9ecef';
            fileUploadButton.style.background = '#f8f9fa';
        }

        fileUploadButton.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            mostrarNombreArchivo(fileInput);
        }
        
        <?php if (!empty($datos_formulario['promedio'])): ?>
        window.addEventListener('load', function() {
            verificarPromedio(<?= $datos_formulario['promedio'] ?>);
        });
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($_SESSION['error']) ?>',
                confirmButtonColor: '#dc3545'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '<?= addslashes($_SESSION['success']) ?>',
                confirmButtonColor: '#28a745',
                timer: 3000
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </script>
</body>
</html>