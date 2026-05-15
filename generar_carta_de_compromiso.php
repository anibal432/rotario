<?php
require_once 'conexion.php';
require_once 'TCPDF/tcpdf.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID de estudiante no proporcionado');
}

$id_estudiante = $_GET['id'];

$sql = "SELECT 
            e.Id_Estudiante,
            e.Nombres_Apellidos,
            e.Numero_Expediente,
            e.Telefono,
            e.Email,
            e.Direccion_Domiciliar,
            e.Nombre_Encargado,
            e.Telefono_Encargado,
            e.Grado_Obtenido_Anterior,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Fecha_Inicio,
            b.Promedio_Minimo
        FROM Estudiantes e
        LEFT JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE e.Id_Estudiante = ? AND b.Estado_Beca = 'Activa'
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    die('Estudiante no encontrado o no tiene beca activa');
}

$sql_clausulas = "SELECT * FROM Reglamento_Becas 
                  WHERE Estado = 'Activo' 
                  ORDER BY Orden ASC, Numero_Clausula ASC";
$stmt_clausulas = $pdo->query($sql_clausulas);
$clausulas = $stmt_clausulas->fetchAll(PDO::FETCH_ASSOC);

$nivel_academico = "UNIVERSIDAD";
$grado = $estudiante['Grado_Obtenido_Anterior'];

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('Club Rotario Coatepeque-Colomba');
$pdf->SetAuthor('Club Rotario Coatepeque-Colomba');
$pdf->SetTitle('Carta de Compromiso - ' . $estudiante['Nombres_Apellidos']);
$pdf->SetSubject('Compromiso entre Becados y Club Rotario');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(20, 15, 20);
$pdf->SetAutoPageBreak(TRUE, 15);

$pdf->AddPage();

$pdf->SetFont('helvetica', '', 10);

$anio_actual = date('Y');
$html_reglamento = '';

foreach ($clausulas as $clausula) {
    $html_reglamento .= '<div class="clausula">';
    $html_reglamento .= '<strong>' . $clausula['Numero_Clausula'] . '.</strong> ';
    $html_reglamento .= $clausula['Contenido_Clausula'];
    
    if ($clausula['Tiene_Subcausulas']) {
        $sql_sub = "SELECT * FROM Sub_Clausulas_Reglamento 
                    WHERE Id_Clausula = ? 
                    ORDER BY Orden ASC";
        $stmt_sub = $pdo->prepare($sql_sub);
        $stmt_sub->execute([$clausula['Id_Clausula']]);
        $subcausulas = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($subcausulas) > 0) {
            $html_reglamento .= '<ul style="margin-left: 20px; margin-top: 5px;">';
            foreach ($subcausulas as $sub) {
                $html_reglamento .= '<li><strong>' . htmlspecialchars($sub['Numero_Sub_Clausula']) . ':</strong> ';
                $html_reglamento .= htmlspecialchars($sub['Contenido']) . '</li>';
            }
            $html_reglamento .= '</ul>';
        }
    }
    
    $html_reglamento .= '</div>';
}

$html = '
<style>
    h1 { 
        font-size: 14pt; 
        font-weight: bold; 
        text-align: center; 
        margin-bottom: 5px;
    }
    h2 { 
        font-size: 12pt; 
        font-weight: bold; 
        text-align: center; 
        margin-bottom: 20px;
    }
    .info-box {
        border: 1px solid #000;
        padding: 10px;
        margin-bottom: 15px;
        background-color: #f5f5f5;
    }
    .info-line {
        margin-bottom: 8px;
        font-size: 10pt;
    }
    .label {
        font-weight: bold;
    }
    .reglamento {
        text-align: justify;
        line-height: 1.6;
    }
    .reglamento-title {
        font-size: 12pt;
        font-weight: bold;
        margin-top: 15px;
        margin-bottom: 10px;
        text-decoration: underline;
    }
    .clausula {
        margin-bottom: 10px;
        text-align: justify;
    }
    ul {
        margin-left: 20px;
        margin-top: 5px;
    }
    li {
        margin-bottom: 5px;
    }
    .firma-section {
        margin-top: 30px;
        page-break-inside: avoid;
    }
    .firma-line {
        border-bottom: 1px solid #000;
        width: 200px;
        display: inline-block;
        margin-top: 40px;
    }
    .firma-label {
        font-size: 9pt;
        font-weight: bold;
        margin-top: 5px;
    }
</style>

<h1>COMPROMISO ENTRE BECADOS Y CLUB ROTARIO COATEPEQUE-COLOMBA</h1>
<h2>PARA EL AÑO ESCOLAR ' . $anio_actual . '</h2>

<div class="info-box">
    <div class="info-line">
        <span class="label">NOMBRE DEL ESTUDIANTE:</span> ' . strtoupper(htmlspecialchars($estudiante['Nombres_Apellidos'])) . '
    </div>
    <div class="info-line">
        <span class="label">NOMBRE DEL PADRE O ENCARGADO:</span> ' . strtoupper(htmlspecialchars($estudiante['Nombre_Encargado'])) . '
    </div>
    <div class="info-line">
        <span class="label">No. de Teléfono:</span> ' . htmlspecialchars($estudiante['Telefono_Encargado']) . ' 
        <span style="margin-left: 20px;"><span class="label">Dirección Domiciliar:</span> ' . htmlspecialchars($estudiante['Direccion_Domiciliar']) . '</span>
    </div>
    <div class="info-line">
        <span class="label">NIVEL ACADÉMICO:</span> ' . $nivel_academico . ' 
        <span style="margin-left: 20px;"><span class="label">GRADO:</span> ' . htmlspecialchars($grado) . '</span>
    </div>
</div>

<div class="reglamento-title">REGLAMENTO GENERAL:</div>

<div class="reglamento">
' . $html_reglamento . '
</div>

<div style="text-align: center; margin-top: 20px; margin-bottom: 30px; font-weight: bold;">
Después de haber leído y entendido por completo este compromiso, firmo.
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->AddPage();

$html_firmas = '
<style>
    .firma-container {
        margin-top: 50px;
    }
    .firma-box {
        margin-bottom: 60px;
    }
    .firma-line {
        border-bottom: 1px solid #000;
        width: 100%;
        height: 1px;
        margin-top: 50px;
        margin-bottom: 5px;
    }
    .firma-label {
        font-size: 10pt;
        font-weight: bold;
    }
    .sello-box {
        text-align: center;
        margin-top: 40px;
        padding: 20px;
        border: 2px dashed #000;
    }
</style>

<div class="firma-container">
    <div class="firma-box">
        <div class="firma-line"></div>
        <div class="firma-label">Firma del Estudiante</div>
        <div style="font-size: 9pt; margin-top: 5px;">' . htmlspecialchars($estudiante['Nombres_Apellidos']) . '</div>
    </div>

    <div class="firma-box">
        <div class="firma-line"></div>
        <div class="firma-label">Firma del Padre o Encargado</div>
        <div style="font-size: 9pt; margin-top: 5px;">' . htmlspecialchars($estudiante['Nombre_Encargado']) . '</div>
    </div>

    <div class="firma-box">
        <div class="firma-line"></div>
        <div class="firma-label">Firma del Representante Legal del Club</div>
        <div style="font-size: 9pt; margin-top: 5px;">Club Rotario Coatepeque-Colomba</div>
    </div>

    <div class="firma-box">
        <div class="firma-line"></div>
        <div class="firma-label">Fecha que se oficializó el compromiso de beca</div>
        <div style="font-size: 9pt; margin-top: 5px;">' . date('d/m/Y') . '</div>
    </div>

    <div class="sello-box">
        <div style="font-size: 12pt; font-weight: bold; color: #666;">
            SELLO DEL CLUB
        </div>
        <div style="font-size: 8pt; margin-top: 10px; color: #999;">
            (Colocar sello oficial aquí)
        </div>
    </div>
</div>
';

$pdf->writeHTML($html_firmas, true, false, true, false, '');

$nombre_archivo = 'Carta_Compromiso_' . str_replace(' ', '_', $estudiante['Nombres_Apellidos']) . '_' . date('Y') . '.pdf';
$ruta_guardado = 'uploads/cartas_compromiso/' . $nombre_archivo;

if (!file_exists('uploads/cartas_compromiso')) {
    mkdir('uploads/cartas_compromiso', 0755, true);
}

$pdf->Output($ruta_guardado, 'F');

$sql_documento = "INSERT INTO Documentos_Solicitud 
                  (Id_Estudiante, Tipo_Documento, Nombre_Archivo, Ruta_Archivo, Tamaño_Archivo, Estado_Documento)
                  VALUES (?, 'Carta_Compromiso', ?, ?, ?, 'Aprobado')";

$tamaño = filesize($ruta_guardado);
$stmt = $pdo->prepare($sql_documento);
$stmt->execute([$id_estudiante, $nombre_archivo, $ruta_guardado, $tamaño]);

$pdf->Output($nombre_archivo, 'I');
?>