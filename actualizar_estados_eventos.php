<?php
/**
 * Actualización Automática de Estados de Eventos
 * Este archivo actualiza el estado de los eventos según las fechas
 */

require_once 'conexion.php';

function actualizarEstadosEventos($pdo) {
    $fecha_actual = date('Y-m-d');
    $eventos_actualizados = 0;
    
    try {
        // 1. Eventos que ya pasaron → Finalizado
        $sql_finalizar = "
            UPDATE Eventos 
            SET Estado_Evento = 'Finalizado'
            WHERE Fecha_Evento < ?
            AND Estado_Evento NOT IN ('Finalizado', 'Cancelado')
        ";
        $stmt = $pdo->prepare($sql_finalizar);
        $stmt->execute([$fecha_actual]);
        $eventos_actualizados += $stmt->rowCount();
        
        // 2. Eventos con inscripciones abiertas que ya cerraron → Inscripciones Cerradas
        $sql_cerrar_inscripciones = "
            UPDATE Eventos 
            SET Estado_Evento = 'Inscripciones Cerradas'
            WHERE Fecha_Fin_Inscripciones IS NOT NULL
            AND Fecha_Fin_Inscripciones < NOW()
            AND Estado_Evento = 'Inscripciones Abiertas'
            AND Fecha_Evento >= ?
        ";
        $stmt = $pdo->prepare($sql_cerrar_inscripciones);
        $stmt->execute([$fecha_actual]);
        $eventos_actualizados += $stmt->rowCount();
        
        // 3. Eventos planificados que deben abrir inscripciones → Inscripciones Abiertas
        $sql_abrir_inscripciones = "
            UPDATE Eventos 
            SET Estado_Evento = 'Inscripciones Abiertas'
            WHERE Fecha_Inicio_Inscripciones IS NOT NULL
            AND Fecha_Inicio_Inscripciones <= NOW()
            AND Estado_Evento = 'Planificado'
            AND Fecha_Evento >= ?
        ";
        $stmt = $pdo->prepare($sql_abrir_inscripciones);
        $stmt->execute([$fecha_actual]);
        $eventos_actualizados += $stmt->rowCount();
        
        // 4. Eventos que son HOY → En Curso
        $sql_en_curso = "
            UPDATE Eventos 
            SET Estado_Evento = 'En Curso'
            WHERE Fecha_Evento = ?
            AND Estado_Evento NOT IN ('Cancelado', 'Finalizado')
        ";
        $stmt = $pdo->prepare($sql_en_curso);
        $stmt->execute([$fecha_actual]);
        $eventos_actualizados += $stmt->rowCount();
        
        return $eventos_actualizados;
        
    } catch (Exception $e) {
        error_log("Error actualizando estados de eventos: " . $e->getMessage());
        return 0;
    }
}

// Si se ejecuta directamente (por cron o manualmente)
if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $eventos_actualizados = actualizarEstadosEventos($pdo);
    echo "✓ Estados actualizados: {$eventos_actualizados} evento(s)\n";
    echo "Fecha de ejecución: " . date('Y-m-d H:i:s') . "\n";
}
?>