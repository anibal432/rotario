<?php
/**
 * Header de Eventos - Incluir en todas las páginas de gestión de eventos
 * Actualiza automáticamente los estados de eventos según fechas
 */

// Solo ejecutar si hay sesión activa
if (isset($_SESSION['user_id'])) {
    // Verificar si ya se actualizó en esta sesión (para no hacerlo en cada página)
    $ultima_actualizacion = $_SESSION['ultima_actualizacion_eventos'] ?? 0;
    $tiempo_actual = time();
    
    // Actualizar cada 5 minutos como máximo
    if (($tiempo_actual - $ultima_actualizacion) > 300) {
        require_once __DIR__ . '/actualizar_estados_eventos.php';
        actualizarEstadosEventos($pdo);
        $_SESSION['ultima_actualizacion_eventos'] = $tiempo_actual;
    }
}

// Función helper para obtener la clase CSS del badge según el estado
function getEstadoBadgeClass($estado) {
    $estado_normalizado = strtolower(str_replace(' ', '_', $estado));
    return 'badge-' . $estado_normalizado;
}

// Función helper para determinar si un evento puede reactivarse
function puedeReactivarse($estado) {
    return in_array($estado, ['Finalizado', 'Cancelado']);
}

// Función helper para obtener el color del estado
function getEstadoColor($estado) {
    $colores = [
        'Planificado' => '#2196f3',
        'Inscripciones Abiertas' => '#4caf50',
        'Inscripciones Cerradas' => '#ff9800',
        'En Curso' => '#03a9f4',
        'Finalizado' => '#f44336',
        'Cancelado' => '#9e9e9e'
    ];
    
    return $colores[$estado] ?? '#757575';
}
?>