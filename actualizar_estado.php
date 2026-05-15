<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Método no permitido');
}

try {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);
    
    if (!$input) {
        jsonResponse(false, 'Datos inválidos');
    }
    
    $idEvaluacion = $input['id_evaluacion'] ?? null;
    $revisorId = $input['revisor_id'] ?? null;
    $aprobado = $input['aprobado'] ?? null;
    $comentarios = $input['comentarios'] ?? '';
    
    if (!$idEvaluacion || !$revisorId || $aprobado === null) {
        jsonResponse(false, 'Faltan datos obligatorios');
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $nuevoEstado = $aprobado ? 'Aprobado' : 'Rechazado';
    $fechaDecision = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        UPDATE Evaluaciones_Socioeconomicas 
        SET Estado_Evaluacion = ?,
            Fecha_Decision = ?,
            Motivo_Rechazo = ?
        WHERE Id_Evaluacion = ?
    ");
    
    $stmt->execute([
        $nuevoEstado,
        $fechaDecision,
        $aprobado ? null : $comentarios,
        $idEvaluacion
    ]);
    
    // Si fue aprobado, crear registro de beca
    if ($aprobado) {
        // Obtener datos de la evaluación
        $stmtEval = $pdo->prepare("
            SELECT Id_Estudiante, Fecha_Evaluacion 
            FROM Evaluaciones_Socioeconomicas 
            WHERE Id_Evaluacion = ?
        ");
        $stmtEval->execute([$idEvaluacion]);
        $evaluacion = $stmtEval->fetch();
        
        if ($evaluacion) {
            // Obtener monto de beca desde configuración
            $stmtConfig = $pdo->prepare("
                SELECT Valor FROM Configuracion_Sistema 
                WHERE Clave = 'monto_beca_mensual'
            ");
            $stmtConfig->execute();
            $montoConfig = $stmtConfig->fetch();
            $montoBeca = $montoConfig ? $montoConfig['Valor'] : 500;
            
            // Crear beca
            $fechaInicio = date('Y-m-d');
            $stmtBeca = $pdo->prepare("
                INSERT INTO Becas_Otorgadas (
                    Id_Estudiante, Id_Evaluacion, Tipo_Beca, 
                    Monto_Mensual, Fecha_Inicio, Estado_Beca
                ) VALUES (?, ?, ?, ?, ?, 'Activa')
            ");
            
            $stmtBeca->execute([
                $evaluacion['Id_Estudiante'],
                $idEvaluacion,
                'Educación Básica',
                $montoBeca,
                $fechaInicio
            ]);
        }
    }
    
    // Registrar en bitácora
    $actividad = $aprobado 
        ? "Evaluación ID $idEvaluacion APROBADA" 
        : "Evaluación ID $idEvaluacion RECHAZADA: $comentarios";
    
    registrarBitacora($pdo, $revisorId, $actividad);
    
    $pdo->commit();
    
    jsonResponse(true, 'Evaluación actualizada exitosamente', [
        'estado' => $nuevoEstado
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error al actualizar estado: " . $e->getMessage());
    jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage());
}
?>