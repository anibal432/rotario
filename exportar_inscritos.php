<?php
/**
 * exportar_inscritos.php
 * Exporta el listado de inscritos a Excel
 * Respeta los filtros aplicados (categoría, estado, búsqueda)
 */

session_start();
require_once 'conexion.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    die('Acceso no autorizado');
}

$id_evento = $_GET['id_evento'] ?? null;

if (!$id_evento) {
    die('ID de evento no especificado');
}

// Obtener información del evento
$sql_evento = "SELECT Nombre_Evento, Fecha_Evento FROM Eventos WHERE Id_Evento = ?";
$stmt_evento = $pdo->prepare($sql_evento);
$stmt_evento->execute([$id_evento]);
$evento = $stmt_evento->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    die('Evento no encontrado');
}

// Obtener filtros
$filtro_categoria = $_GET['categoria'] ?? 'Todas';
$filtro_estado = $_GET['estado'] ?? 'Todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta SQL con filtros
$sql = "SELECT 
            ie.Numero_Participante,
            ie.Nombre_Completo,
            ie.Email,
            ie.Telefono,
            ie.Edad,
            ie.Genero,
            ie.DPI,
            ie.Direccion,
            ie.Talla_Playera,
            ie.Contacto_Emergencia,
            ie.Telefono_Emergencia,
            ie.Fecha_Inscripcion,
            ie.Estado_Pago,
            ie.Estado_Inscripcion,
            ie.Monto_Pagado,
            c.Nombre_Categoria,
            co.Tipo_Inscripcion
        FROM Inscripciones_Evento ie
        LEFT JOIN Categorias_Evento c ON ie.Id_Categoria = c.Id_Categoria
        LEFT JOIN Costos_Inscripcion co ON ie.Id_Costo = co.Id_Costo
        WHERE ie.Id_Evento = ?";

$params = [$id_evento];

// Aplicar filtros
if ($filtro_categoria !== 'Todas') {
    $sql .= " AND c.Nombre_Categoria = ?";
    $params[] = $filtro_categoria;
}

if ($filtro_estado !== 'Todos') {
    $sql .= " AND ie.Estado_Inscripcion = ?";
    $params[] = $filtro_estado;
}

if (!empty($busqueda)) {
    $sql .= " AND (ie.Nombre_Completo LIKE ? OR ie.Email LIKE ? OR ie.Numero_Participante LIKE ?)";
    $busqueda_param = "%{$busqueda}%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$sql .= " ORDER BY ie.Numero_Participante ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// GENERAR ARCHIVO EXCEL
// ============================================

// Configurar headers para descarga de Excel
$fecha_export = date('Y-m-d_H-i-s');
$nombre_evento_limpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $evento['Nombre_Evento']);
$nombre_archivo = "Inscritos_{$nombre_evento_limpio}_{$fecha_export}.xls";

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header("Pragma: no-cache");
header("Expires: 0");

// Iniciar salida con BOM UTF-8 para correcta visualización de caracteres especiales
echo "\xEF\xBB\xBF";

// ============================================
// ENCABEZADO DEL DOCUMENTO
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #003d82; color: white; font-weight: bold; }
        .header { background-color: #f0f0f0; font-weight: bold; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; color: #003d82; }
        .subtitle { font-size: 14px; color: #666; }
    </style>
</head>
<body>

<!-- INFORMACIÓN DEL REPORTE -->
<div class="header">
    <div class="title">LISTADO DE INSCRITOS</div>
    <div class="subtitle">Evento: <?= htmlspecialchars($evento['Nombre_Evento']) ?></div>
    <div class="subtitle">Fecha del Evento: <?= date('d/m/Y', strtotime($evento['Fecha_Evento'])) ?></div>
    <div class="subtitle">Fecha de Exportación: <?= date('d/m/Y H:i:s') ?></div>
    <?php if ($filtro_categoria !== 'Todas'): ?>
    <div class="subtitle">Categoría Filtrada: <?= htmlspecialchars($filtro_categoria) ?></div>
    <?php endif; ?>
    <?php if ($filtro_estado !== 'Todos'): ?>
    <div class="subtitle">Estado Filtrado: <?= htmlspecialchars($filtro_estado) ?></div>
    <?php endif; ?>
    <?php if (!empty($busqueda)): ?>
    <div class="subtitle">Búsqueda: <?= htmlspecialchars($busqueda) ?></div>
    <?php endif; ?>
    <div class="subtitle">Total de Registros: <?= count($inscritos) ?></div>
</div>

<br><br>

<!-- TABLA DE DATOS -->
<table>
    <thead>
        <tr>
            <th>Número</th>
            <th>Nombre Completo</th>
            <th>Categoría</th>
            <th>Tipo Inscripción</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Edad</th>
            <th>Género</th>
            <th>DPI</th>
            <th>Dirección</th>
            <th>Talla Playera</th>
            <th>Contacto Emergencia</th>
            <th>Teléfono Emergencia</th>
            <th>Estado Inscripción</th>
            <th>Estado Pago</th>
            <th>Monto Pagado</th>
            <th>Fecha Inscripción</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($inscritos) > 0): ?>
            <?php foreach ($inscritos as $inscrito): ?>
            <tr>
                <td><?= htmlspecialchars($inscrito['Numero_Participante']) ?></td>
                <td><?= htmlspecialchars($inscrito['Nombre_Completo']) ?></td>
                <td><?= htmlspecialchars($inscrito['Nombre_Categoria']) ?></td>
                <td><?= htmlspecialchars($inscrito['Tipo_Inscripcion']) ?></td>
                <td><?= htmlspecialchars($inscrito['Email']) ?></td>
                <td><?= htmlspecialchars($inscrito['Telefono']) ?></td>
                <td><?= $inscrito['Edad'] ?></td>
                <td><?= htmlspecialchars($inscrito['Genero']) ?></td>
                <td><?= htmlspecialchars($inscrito['DPI']) ?></td>
                <td><?= htmlspecialchars($inscrito['Direccion']) ?></td>
                <td><?= htmlspecialchars($inscrito['Talla_Playera']) ?></td>
                <td><?= htmlspecialchars($inscrito['Contacto_Emergencia']) ?></td>
                <td><?= htmlspecialchars($inscrito['Telefono_Emergencia']) ?></td>
                <td><?= htmlspecialchars($inscrito['Estado_Inscripcion']) ?></td>
                <td><?= htmlspecialchars($inscrito['Estado_Pago']) ?></td>
                <td>Q<?= number_format($inscrito['Monto_Pagado'], 2) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($inscrito['Fecha_Inscripcion'])) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="17" style="text-align: center;">No hay inscritos para exportar</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<br><br>

<!-- RESUMEN ESTADÍSTICO -->
<?php if (count($inscritos) > 0): ?>
<table style="width: 400px;">
    <tr>
        <th colspan="2" style="text-align: center;">RESUMEN ESTADÍSTICO</th>
    </tr>
    <tr>
        <td><strong>Total de Inscritos:</strong></td>
        <td><?= count($inscritos) ?></td>
    </tr>
    <?php
    // Calcular estadísticas
    $total_confirmados = 0;
    $total_pendientes = 0;
    $total_cancelados = 0;
    $total_recaudado = 0;
    $total_masculino = 0;
    $total_femenino = 0;
    
    foreach ($inscritos as $inscrito) {
        if ($inscrito['Estado_Inscripcion'] === 'Confirmado') $total_confirmados++;
        if ($inscrito['Estado_Inscripcion'] === 'Pendiente') $total_pendientes++;
        if ($inscrito['Estado_Inscripcion'] === 'Cancelado') $total_cancelados++;
        
        if ($inscrito['Estado_Pago'] === 'Aprobado') {
            $total_recaudado += $inscrito['Monto_Pagado'];
        }
        
        if ($inscrito['Genero'] === 'Masculino') $total_masculino++;
        if ($inscrito['Genero'] === 'Femenino') $total_femenino++;
    }
    ?>
    <tr>
        <td><strong>Confirmados:</strong></td>
        <td><?= $total_confirmados ?></td>
    </tr>
    <tr>
        <td><strong>Pendientes:</strong></td>
        <td><?= $total_pendientes ?></td>
    </tr>
    <tr>
        <td><strong>Cancelados:</strong></td>
        <td><?= $total_cancelados ?></td>
    </tr>
    <tr>
        <td><strong>Masculino:</strong></td>
        <td><?= $total_masculino ?></td>
    </tr>
    <tr>
        <td><strong>Femenino:</strong></td>
        <td><?= $total_femenino ?></td>
    </tr>
    <tr>
        <td><strong>Total Recaudado:</strong></td>
        <td>Q<?= number_format($total_recaudado, 2) ?></td>
    </tr>
</table>
<?php endif; ?>

<br><br>

<!-- PIE DE PÁGINA -->
<div style="text-align: center; font-size: 10px; color: #666; margin-top: 30px;">
    <p>Club Rotario Coatepeque Colomba</p>
    <p>Sistema de Gestión de Eventos Deportivos</p>
    <p>Reporte generado el <?= date('d/m/Y') ?> a las <?= date('H:i:s') ?></p>
</div>

</body>
</html>
<?php
// Log de exportación
error_log("Exportación de inscritos - Evento: {$evento['Nombre_Evento']} - Total registros: " . count($inscritos) . " - Usuario: " . $_SESSION['username']);
exit;
?>