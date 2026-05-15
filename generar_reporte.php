<?php
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    die('Acceso no autorizado');
}

// Obtener parámetros
$tipo = $_GET['tipo'] ?? 'general';
$periodo = $_GET['periodo'] ?? date('Y');
$formato = $_GET['formato'] ?? 'pdf';

include 'conexion.php';

function generarExcel($datos, $titulo, $columnas) {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $titulo . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    
    echo "<table border='1'>";
    echo "<tr style='background-color: #004b87; color: white; font-weight: bold;'>";
    foreach ($columnas as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    
    foreach ($datos as $fila) {
        echo "<tr>";
        foreach ($fila as $valor) {
            echo "<td>" . htmlspecialchars($valor ?? '') . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
}

// Función para generar PDF (HTML que se puede imprimir como PDF)
function generarPDF($datos, $titulo, $columnas) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($titulo) ?></title>
        <style>
            @media print {
                @page { margin: 2cm; }
                body { margin: 0; }
            }
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #004b87;
                padding-bottom: 15px;
            }
            .header h1 {
                color: #004b87;
                margin: 0;
                font-size: 24px;
            }
            .header p {
                color: #666;
                margin: 5px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th {
                background-color: #004b87;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }
            tr:hover {
                background-color: #f5f5f5;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                color: #666;
                font-size: 12px;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            .btn-print {
                background: #004b87;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                margin: 20px 0;
                font-size: 16px;
            }
            .btn-print:hover {
                background: #003d6e;
            }
            @media print {
                .btn-print { display: none; }
            }
        </style>
    </head>
    <body>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
        
        <div class="header">
            <h1>Club Rotario Coatepeque - Colomba</h1>
            <h2><?= htmlspecialchars($titulo) ?></h2>
            <p>Fecha de generación: <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <?php foreach ($columnas as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datos as $fila): ?>
                    <tr>
                        <?php foreach ($fila as $valor): ?>
                            <td><?= htmlspecialchars($valor ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Total de registros: <?= count($datos) ?></p>
            <p>&copy; <?= date('Y') ?> Club Rotario Coatepeque - Colomba</p>
        </div>
    </body>
    </html>
    <?php
}

// Procesar según el tipo de reporte
switch ($tipo) {
    case 'general':
        // Reporte General de Becados
        $where = $periodo !== 'todos' ? "WHERE YEAR(e.Fecha_Registro) = :periodo" : "";
        $stmt = $pdo->prepare("
            SELECT 
                e.Numero_Expediente,
                e.Nombres_Apellidos,
                e.Edad,
                e.Telefono,
                e.Grado_Obtenido_Anterior,
                e.Estado_Estudiante,
                e.Fecha_Registro
            FROM Estudiantes e
            $where
            ORDER BY e.Fecha_Registro DESC
        ");
        
        if ($periodo !== 'todos') {
            $stmt->execute([':periodo' => $periodo]);
        } else {
            $stmt->execute();
        }
        
        $datos = $stmt->fetchAll(PDO::FETCH_NUM);
        $columnas = ['Expediente', 'Nombre Completo', 'Edad', 'Teléfono', 'Grado', 'Estado', 'Fecha Registro'];
        $titulo = 'Reporte General de Becados ' . ($periodo !== 'todos' ? $periodo : '');
        break;
        
    case 'evaluaciones':
        // Reporte de Evaluaciones Socioeconómicas
        $where = $periodo !== 'todos' ? "WHERE YEAR(ev.Fecha_Evaluacion) = :periodo" : "";
        $stmt = $pdo->prepare("
            SELECT 
                e.Numero_Expediente,
                e.Nombres_Apellidos,
                ev.Fecha_Evaluacion,
                ev.Estado_Evaluacion,
                ev.Nombre_Socio_Rotario,
                CASE 
                    WHEN ev.Comentarios_Evaluacion IS NOT NULL THEN 'Sí'
                    ELSE 'No'
                END as Tiene_Comentarios
            FROM Evaluaciones_Socioeconomicas ev
            INNER JOIN Estudiantes e ON ev.Id_Estudiante = e.Id_Estudiante
            $where
            ORDER BY ev.Fecha_Evaluacion DESC
        ");
        
        if ($periodo !== 'todos') {
            $stmt->execute([':periodo' => $periodo]);
        } else {
            $stmt->execute();
        }
        
        $datos = $stmt->fetchAll(PDO::FETCH_NUM);
        $columnas = ['Expediente', 'Estudiante', 'Fecha Evaluación', 'Estado', 'Evaluador', 'Comentarios'];
        $titulo = 'Reporte de Evaluaciones ' . ($periodo !== 'todos' ? $periodo : '');
        break;
        
    case 'grados':
        // Distribución por Grados
        $where = $periodo !== 'todos' ? "WHERE YEAR(Fecha_Registro) = :periodo" : "";
        $stmt = $pdo->prepare("
            SELECT 
                Grado_Obtenido_Anterior,
                COUNT(*) as Cantidad,
                GROUP_CONCAT(Nombres_Apellidos SEPARATOR ', ') as Estudiantes
            FROM Estudiantes
            $where
            GROUP BY Grado_Obtenido_Anterior
            ORDER BY Cantidad DESC
        ");
        
        if ($periodo !== 'todos') {
            $stmt->execute([':periodo' => $periodo]);
        } else {
            $stmt->execute();
        }
        
        $datos = $stmt->fetchAll(PDO::FETCH_NUM);
        $columnas = ['Grado', 'Cantidad', 'Estudiantes'];
        $titulo = 'Distribución por Grados ' . ($periodo !== 'todos' ? $periodo : '');
        break;
        
    case 'eventos':
        // Ingresos por Eventos
        $where = $periodo !== 'todos' ? "WHERE YEAR(i.Fecha_Inscripcion) = :periodo" : "";
        $stmt = $pdo->prepare("
            SELECT 
                e.Nombre_Evento,
                c.Nombre_Categoria,
                COUNT(i.Id_Inscripcion) as Participantes,
                CONCAT('Q', FORMAT(AVG(i.Monto_Pagado), 2)) as Precio_Promedio,
                CONCAT('Q', FORMAT(SUM(i.Monto_Pagado), 2)) as Total_Ingresos
            FROM Inscripciones_Evento i
            INNER JOIN Eventos e ON i.Id_Evento = e.Id_Evento
            INNER JOIN Categorias_Evento c ON i.Id_Categoria = c.Id_Categoria
            $where
            AND i.Estado_Pago = 'Aprobado'
            GROUP BY e.Id_Evento, e.Nombre_Evento, c.Id_Categoria, c.Nombre_Categoria
            ORDER BY SUM(i.Monto_Pagado) DESC
        ");
        
        if ($periodo !== 'todos') {
            $stmt->execute([':periodo' => $periodo]);
        } else {
            $stmt->execute();
        }
        
        $datos = $stmt->fetchAll(PDO::FETCH_NUM);
        $columnas = ['Evento', 'Categoría', 'Participantes', 'Precio Promedio', 'Total Ingresos'];
        $titulo = 'Reporte de Ingresos por Eventos ' . ($periodo !== 'todos' ? $periodo : '');
        break;
        
    default:
        die('Tipo de reporte no válido');
}

// Generar reporte según formato
if ($formato === 'excel') {
    generarExcel($datos, $titulo, $columnas);
} else {
    generarPDF($datos, $titulo, $columnas);
}
?>