<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Obtener los mismos filtros que en la página principal
$filtro_estado = $_GET['estado'] ?? 'Todas';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_año = $_GET['año'] ?? date('Y');

// Consulta SQL igual que en estudiantes_becados.php
$sql = "SELECT 
            e.Id_Estudiante,
            e.Numero_Expediente,
            e.Nombres_Apellidos,
            e.Email,
            e.Telefono,
            e.Edad,
            e.Direccion_Domiciliar,
            e.Nombre_Encargado,
            e.Telefono_Encargado,
            e.Grado_Obtenido_Anterior,
            e.Escuela_Anterior,
            e.Estado_Estudiante,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Fecha_Inicio,
            b.Fecha_Fin,
            b.Promedio_Minimo,
            b.Promedio_Actual,
            (SELECT COUNT(*) FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca 
             AND YEAR(Fecha_Pago) = ?) as Pagos_Realizados,
            (SELECT SUM(Monto) FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca 
             AND YEAR(Fecha_Pago) = ?) as Total_Pagado_Año,
            (SELECT MAX(Fecha_Pago) FROM Pagos_Becas 
             WHERE Id_Beca = b.Id_Beca) as Ultimo_Pago,
            (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante) as Ultima_Boleta,
            (SELECT Promedio FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante 
             ORDER BY Fecha_Subida DESC LIMIT 1) as Ultimo_Promedio,
            (SELECT AVG(Promedio) FROM Boletas_Calificaciones 
             WHERE Id_Estudiante = e.Id_Estudiante) as Promedio_General
        FROM Estudiantes e
        INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE 1=1";

$params = [$filtro_año, $filtro_año];

if ($filtro_estado !== 'Todas') {
    $sql .= " AND b.Estado_Beca = ?";
    $params[] = $filtro_estado;
}

if (!empty($filtro_busqueda)) {
    $sql .= " AND (e.Nombres_Apellidos LIKE ? OR e.Numero_Expediente LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}

$sql .= " ORDER BY e.Nombres_Apellidos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$becados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configurar headers para descarga de Excel
$filename = "becados_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Agregar BOM para UTF-8
echo "\xEF\xBB\xBF";

// Función para limpiar datos
function cleanData($str) {
    // Convertir a string y eliminar saltos de línea
    $str = str_replace(["\r", "\n"], ' ', (string)$str);
    // Escapar comillas dobles
    $str = str_replace('"', '""', $str);
    return $str;
}

// Crear encabezado del reporte
echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<style>';
echo 'table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11pt; }';
echo 'th { background-color: #003d82; color: white; font-weight: bold; padding: 10px; border: 1px solid #000; text-align: center; }';
echo 'td { padding: 8px; border: 1px solid #ccc; }';
echo '.header { background-color: #f0f0f0; font-weight: bold; text-align: center; padding: 15px; margin-bottom: 10px; }';
echo '.numero { text-align: center; }';
echo '.monto { text-align: right; }';
echo '.promedio { text-align: center; font-weight: bold; }';
echo '.bueno { color: green; }';
echo '.regular { color: orange; }';
echo '.malo { color: red; }';
echo '</style>';
echo '</head>';
echo '<body>';

// Título del reporte
echo '<div class="header">';
echo '<h1>SISTEMA DE BECAS - CLUB ROTARIO COATEPEQUE-COLOMBA</h1>';
echo '<h2>Reporte de Estudiantes Becados</h2>';
echo '<p>Generado el: ' . date('d/m/Y H:i:s') . '</p>';
echo '<p>Año: ' . $filtro_año . ' | Estado: ' . $filtro_estado . '</p>';
if (!empty($filtro_busqueda)) {
    echo '<p>Búsqueda: ' . htmlspecialchars($filtro_busqueda) . '</p>';
}
echo '<p>Total de registros: ' . count($becados) . '</p>';
echo '</div>';

// Iniciar tabla
echo '<table border="1">';

// Encabezados de columnas
echo '<thead>';
echo '<tr>';
echo '<th>No.</th>';
echo '<th>Expediente</th>';
echo '<th>Nombre Completo</th>';
echo '<th>Email</th>';
echo '<th>Teléfono</th>';
echo '<th>Edad</th>';
echo '<th>Dirección</th>';
echo '<th>Encargado</th>';
echo '<th>Tel. Encargado</th>';
echo '<th>Grado/Carrera</th>';
echo '<th>Establecimiento</th>';
echo '<th>Estado Estudiante</th>';
echo '<th>Tipo de Beca</th>';
echo '<th>Monto Mensual</th>';
echo '<th>Estado Beca</th>';
echo '<th>Fecha Inicio</th>';
echo '<th>Fecha Fin</th>';
echo '<th>Promedio Mínimo</th>';
echo '<th>Promedio Actual</th>';
echo '<th>Promedio General</th>';
echo '<th>Pagos ' . $filtro_año . '</th>';
echo '<th>Total Pagado ' . $filtro_año . '</th>';
echo '<th>Último Pago</th>';
echo '<th>Última Boleta</th>';
echo '</tr>';
echo '</thead>';

// Cuerpo de la tabla
echo '<tbody>';

$contador = 1;
$total_monto_mensual = 0;
$total_pagado = 0;

foreach ($becados as $estudiante) {
    echo '<tr>';
    
    // No.
    echo '<td class="numero">' . $contador++ . '</td>';
    
    // Expediente
    echo '<td class="numero">' . htmlspecialchars($estudiante['Numero_Expediente']) . '</td>';
    
    // Nombre
    echo '<td>' . htmlspecialchars($estudiante['Nombres_Apellidos']) . '</td>';
    
    // Email
    echo '<td>' . htmlspecialchars($estudiante['Email']) . '</td>';
    
    // Teléfono
    echo '<td class="numero">' . htmlspecialchars($estudiante['Telefono']) . '</td>';
    
    // Edad
    echo '<td class="numero">' . htmlspecialchars($estudiante['Edad']) . '</td>';
    
    // Dirección
    echo '<td>' . htmlspecialchars($estudiante['Direccion_Domiciliar']) . '</td>';
    
    // Encargado
    echo '<td>' . htmlspecialchars($estudiante['Nombre_Encargado']) . '</td>';
    
    // Tel. Encargado
    echo '<td class="numero">' . htmlspecialchars($estudiante['Telefono_Encargado']) . '</td>';
    
    // Grado/Carrera
    echo '<td>' . htmlspecialchars($estudiante['Grado_Obtenido_Anterior']) . '</td>';
    
    // Establecimiento
    echo '<td>' . htmlspecialchars($estudiante['Escuela_Anterior']) . '</td>';
    
    // Estado Estudiante
    echo '<td class="numero">' . htmlspecialchars($estudiante['Estado_Estudiante']) . '</td>';
    
    // Tipo de Beca
    echo '<td>' . htmlspecialchars($estudiante['Tipo_Beca']) . '</td>';
    
    // Monto Mensual
    $monto_mensual = $estudiante['Monto_Mensual'];
    echo '<td class="monto">Q ' . number_format($monto_mensual, 2) . '</td>';
    $total_monto_mensual += $monto_mensual;
    
    // Estado Beca
    echo '<td class="numero">' . htmlspecialchars($estudiante['Estado_Beca']) . '</td>';
    
    // Fecha Inicio
    echo '<td class="numero">' . ($estudiante['Fecha_Inicio'] ? date('d/m/Y', strtotime($estudiante['Fecha_Inicio'])) : 'N/A') . '</td>';
    
    // Fecha Fin
    echo '<td class="numero">' . ($estudiante['Fecha_Fin'] ? date('d/m/Y', strtotime($estudiante['Fecha_Fin'])) : 'N/A') . '</td>';
    
    // Promedio Mínimo
    echo '<td class="promedio">' . number_format($estudiante['Promedio_Minimo'], 1) . '</td>';
    
    // Promedio Actual
    if ($estudiante['Promedio_Actual']) {
        $clase_promedio = 'bueno';
        if ($estudiante['Promedio_Actual'] < $estudiante['Promedio_Minimo']) {
            $clase_promedio = 'malo';
        } elseif ($estudiante['Promedio_Actual'] < 80) {
            $clase_promedio = 'regular';
        }
        echo '<td class="promedio ' . $clase_promedio . '">' . number_format($estudiante['Promedio_Actual'], 1) . '</td>';
    } else {
        echo '<td class="promedio">N/A</td>';
    }
    
    // Promedio General
    if ($estudiante['Promedio_General']) {
        echo '<td class="promedio">' . number_format($estudiante['Promedio_General'], 1) . '</td>';
    } else {
        echo '<td class="promedio">N/A</td>';
    }
    
    // Pagos realizados
    echo '<td class="numero">' . $estudiante['Pagos_Realizados'] . ' / 12</td>';
    
    // Total pagado en el año
    $total_pagado_año = $estudiante['Total_Pagado_Año'] ?? 0;
    echo '<td class="monto">Q ' . number_format($total_pagado_año, 2) . '</td>';
    $total_pagado += $total_pagado_año;
    
    // Último pago
    echo '<td class="numero">' . ($estudiante['Ultimo_Pago'] ? date('d/m/Y', strtotime($estudiante['Ultimo_Pago'])) : 'Sin pagos') . '</td>';
    
    // Última boleta
    echo '<td class="numero">' . ($estudiante['Ultima_Boleta'] ? date('d/m/Y', strtotime($estudiante['Ultima_Boleta'])) : 'Sin boletas') . '</td>';
    
    echo '</tr>';
}

// Fila de totales
echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
echo '<td colspan="13" style="text-align: right; padding-right: 10px;">TOTALES:</td>';
echo '<td class="monto">Q ' . number_format($total_monto_mensual, 2) . '</td>';
echo '<td colspan="7"></td>';
echo '<td class="monto">Q ' . number_format($total_pagado, 2) . '</td>';
echo '<td colspan="2"></td>';
echo '</tr>';

echo '</tbody>';
echo '</table>';

// Pie de página con estadísticas adicionales
echo '<br><br>';
echo '<div class="header">';
echo '<h3>Estadísticas Generales</h3>';

// Calcular estadísticas
$becas_activas = count(array_filter($becados, fn($e) => $e['Estado_Beca'] === 'Activa'));
$becas_suspendidas = count(array_filter($becados, fn($e) => $e['Estado_Beca'] === 'Suspendida'));
$becas_finalizadas = count(array_filter($becados, fn($e) => $e['Estado_Beca'] === 'Finalizada'));

echo '<table border="1" style="width: 50%; margin: 0 auto;">';
echo '<tr><td><strong>Total de Estudiantes:</strong></td><td class="numero">' . count($becados) . '</td></tr>';
echo '<tr><td><strong>Becas Activas:</strong></td><td class="numero">' . $becas_activas . '</td></tr>';
echo '<tr><td><strong>Becas Suspendidas:</strong></td><td class="numero">' . $becas_suspendidas . '</td></tr>';
echo '<tr><td><strong>Becas Finalizadas:</strong></td><td class="numero">' . $becas_finalizadas . '</td></tr>';
echo '<tr><td><strong>Inversión Mensual Total:</strong></td><td class="monto">Q ' . number_format($total_monto_mensual, 2) . '</td></tr>';
echo '<tr><td><strong>Total Pagado en ' . $filtro_año . ':</strong></td><td class="monto">Q ' . number_format($total_pagado, 2) . '</td></tr>';
echo '<tr><td><strong>Proyección Anual (12 meses):</strong></td><td class="monto">Q ' . number_format($total_monto_mensual * 12, 2) . '</td></tr>';
echo '</table>';

echo '</div>';

echo '</body>';
echo '</html>';

// Registrar en bitácora
try {
    $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                     VALUES (?, ?, CURDATE(), ?)";
    $stmt_bitacora = $pdo->prepare($sql_bitacora);
    $stmt_bitacora->execute([
        $_SESSION['user_id'],
        "Exportó reporte de becados (Total: " . count($becados) . ", Estado: $filtro_estado, Año: $filtro_año)",
        $_SERVER['REMOTE_ADDR']
    ]);
} catch (PDOException $e) {
    // Error silencioso en bitácora
}

exit;
?>