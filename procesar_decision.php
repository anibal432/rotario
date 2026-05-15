<?php
session_start();
header('Content-Type: application/json');

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión no válida. Por favor inicie sesión nuevamente.'
    ]);
    exit;
}

// Verificar permisos
$role = $_SESSION['role'] ?? '';
$puede_aprobar = in_array($role, ['Administrador', 'Coordinador']);

if (!$puede_aprobar) {
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para realizar esta acción.'
    ]);
    exit;
}

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método de solicitud no válido.'
    ]);
    exit;
}

// Obtener datos del formulario
$action = $_POST['action'] ?? '';
$id_evaluacion = isset($_POST['id_evaluacion']) ? intval($_POST['id_evaluacion']) : 0;
$decision = $_POST['decision'] ?? '';
$comentarios = trim($_POST['comentarios'] ?? '');
$user_id = $_SESSION['user_id'];

// Validar datos
if ($action !== 'procesar_decision') {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida.'
    ]);
    exit;
}

if ($id_evaluacion <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de evaluación no válido.'
    ]);
    exit;
}

if (!in_array($decision, ['Aprobado', 'Rechazado'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Decisión no válida.'
    ]);
    exit;
}

include 'conexion.php';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Verificar que la evaluación existe y está pendiente
    $stmt = $pdo->prepare("
        SELECT Estado_Evaluacion, Id_Estudiante 
        FROM Evaluaciones_Socioeconomicas 
        WHERE Id_Evaluacion = :id_evaluacion
    ");
    $stmt->execute([':id_evaluacion' => $id_evaluacion]);
    $evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evaluacion) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'La evaluación no existe.'
        ]);
        exit;
    }
    
    if ($evaluacion['Estado_Evaluacion'] !== 'Pendiente') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Esta evaluación ya ha sido procesada anteriormente.'
        ]);
        exit;
    }
    
    // Actualizar estado de la evaluación
    $stmt = $pdo->prepare("
        UPDATE Evaluaciones_Socioeconomicas 
        SET Estado_Evaluacion = :decision,
            Comentarios_Evaluacion = :comentarios,
            Fecha_Revision = NOW(),
            Id_Usuario_Revisor = :user_id
        WHERE Id_Evaluacion = :id_evaluacion
    ");
    
    $stmt->execute([
        ':decision' => $decision,
        ':comentarios' => $comentarios,
        ':user_id' => $user_id,
        ':id_evaluacion' => $id_evaluacion
    ]);
    
    // Si fue aprobado, actualizar estado del estudiante
    if ($decision === 'Aprobado') {
        $stmt = $pdo->prepare("
            UPDATE Estudiantes 
            SET Estado_Beca = 'Activo',
                Fecha_Inicio_Beca = NOW()
            WHERE Id_Estudiante = :id_estudiante
        ");
        $stmt->execute([':id_estudiante' => $evaluacion['Id_Estudiante']]);
    } else {
        // Si fue rechazado, marcar como inactivo
        $stmt = $pdo->prepare("
            UPDATE Estudiantes 
            SET Estado_Beca = 'Inactivo'
            WHERE Id_Estudiante = :id_estudiante
        ");
        $stmt->execute([':id_estudiante' => $evaluacion['Id_Estudiante']]);
    }
    
    // Registrar en el log de actividades (opcional)
    $stmt = $pdo->prepare("
        INSERT INTO Log_Actividades 
        (Id_Usuario, Accion, Descripcion, Fecha_Hora) 
        VALUES 
        (:user_id, :accion, :descripcion, NOW())
    ");
    
    $accion = $decision === 'Aprobado' ? 'Aprobar Evaluación' : 'Rechazar Evaluación';
    $descripcion = "Evaluación ID: $id_evaluacion - Decisión: $decision";
    if (!empty($comentarios)) {
        $descripcion .= " - Comentarios: $comentarios";
    }
    
    try {
        $stmt->execute([
            ':user_id' => $user_id,
            ':accion' => $accion,
            ':descripcion' => $descripcion
        ]);
    } catch (PDOException $e) {
        // Si la tabla Log_Actividades no existe, ignorar el error
        // pero continuar con la transacción principal
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    $mensaje = $decision === 'Aprobado' 
        ? 'Evaluación aprobada exitosamente. El estudiante ha sido activado en el sistema.'
        : 'Evaluación rechazada. Se ha notificado el rechazo.';
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'decision' => $decision,
        'id_evaluacion' => $id_evaluacion
    ]);
    
} catch(PDOException $e) {
    // Revertir transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la decisión: ' . $e->getMessage()
    ]);
    
    // Log del error para debugging (opcional)
    error_log("Error en procesar_decision.php: " . $e->getMessage());
} catch(Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error inesperado: ' . $e->getMessage()
    ]);
    
    error_log("Error inesperado en procesar_decision.php: " . $e->getMessage());
}
?>