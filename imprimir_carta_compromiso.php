<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

if (!isset($_GET['id']) || !isset($_GET['id_beca'])) {
    header('Location: lista_solicitudes.php');
    exit;
}

$id_estudiante = intval($_GET['id']);
$id_beca = intval($_GET['id_beca']);

try {
    // Obtener información completa
    $sql = "SELECT 
                e.*,
                b.Tipo_Beca,
                b.Monto_Mensual,
                b.Fecha_Inicio,
                b.Promedio_Minimo
            FROM Estudiantes e
            INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
            WHERE e.Id_Estudiante = ? AND b.Id_Beca = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_estudiante, $id_beca]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$datos) {
        die("No se encontró la información de la beca.");
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$fecha_actual = date('d/m/Y');
$anio_actual = date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta de Compromiso - <?= htmlspecialchars($datos['Nombres_Apellidos']) ?></title>
    <style>
        @page {
            size: letter;
            margin: 2cm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            background: #fff;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .no-print button {
            padding: 12px 24px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .no-print button:hover {
            background: #0056b3;
        }
        
        .no-print .btn-close {
            background: #6c757d;
        }
        
        .no-print .btn-close:hover {
            background: #5a6268;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #000;
            padding-bottom: 20px;
        }
        
        .logo {
            width: 150px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 10px 0;
            text-transform: uppercase;
        }
        
        .header .subtitle {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .content {
            margin-top: 30px;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-row {
            margin-bottom: 12px;
            display: flex;
        }
        
        .info-row label {
            font-weight: bold;
            min-width: 250px;
            text-transform: uppercase;
        }
        
        .info-row .value {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 20px;
            padding-left: 10px;
        }
        
        .section-title {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 15px;
            text-transform: uppercase;
            text-align: center;
        }
        
        .rules-list {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        
        .rules-list li {
            margin-bottom: 10px;
            text-align: justify;
        }
        
        .signatures {
            margin-top: 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 2px solid #000;
            margin-bottom: 5px;
            margin-top: 80px;
        }
        
        .signature-label {
            font-size: 11pt;
            font-weight: bold;
        }
        
        .date-section {
            margin-top: 40px;
            text-align: center;
        }
        
        .date-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 300px;
            margin-left: 10px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10pt;
        }
        
        .seal-box {
            margin-top: 30px;
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="window.location.href='admin_detalle.php?id=<?= $id_estudiante ?>'" class="btn-close">
            Cerrar
        </button>
    </div>
    
    <div class="container">
        <div class="header">
            <div class="logo-section">
                <!-- Si tienes un logo, descomentar esto y agregar la ruta -->
                <!-- <img src="img/logo-rotario.png" alt="Logo Rotario" class="logo"> -->
                <div style="font-size: 40px;">⚙️</div>
            </div>
            <h1>Compromiso entre Becados y Club Rotario Coatepeque-Colomba</h1>
            <div class="subtitle">Para el año escolar <?= $anio_actual ?></div>
        </div>
        
        <div class="content">
            <div class="info-section">
                <div class="info-row">
                    <label>Nombre del Estudiante:</label>
                    <div class="value"><?= htmlspecialchars($datos['Nombres_Apellidos']) ?></div>
                </div>
                
                <div class="info-row">
                    <label>Nombre del Padre o Encargado:</label>
                    <div class="value"><?= htmlspecialchars($datos['Nombre_Encargado']) ?></div>
                </div>
                
                <div class="info-row">
                    <label>No. de Teléfono:</label>
                    <div class="value"><?= htmlspecialchars($datos['Telefono_Encargado']) ?></div>
                </div>
                
                <div class="info-row">
                    <label>Dirección Domiciliar:</label>
                    <div class="value"><?= htmlspecialchars($datos['Direccion_Domiciliar']) ?></div>
                </div>
                
                <div class="info-row">
                    <label>Nivel Académico:</label>
                    <div class="value"><?= htmlspecialchars($datos['Grado_Obtenido_Anterior']) ?></div>
                </div>
                
                <div class="info-row">
                    <label>Tipo de Beca:</label>
                    <div class="value"><?= htmlspecialchars($datos['Tipo_Beca']) ?></div>
                </div>
                
                <div class="info-row">
                    <label>Monto Mensual:</label>
                    <div class="value">Q <?= number_format($datos['Monto_Mensual'], 2) ?></div>
                </div>
            </div>
            
            <div class="section-title">Reglamento General</div>
            
            <ol class="rules-list">
                <li>Las becas se asignarán entre alumnos que hayan obtenido un promedio mínimo de <strong><?= number_format($datos['Promedio_Minimo'], 0) ?> puntos</strong> para optar por una beca, no interfiriendo el sexo del alumno.</li>
                
                <li>Los candidatos deben aprobar todas las materias, aun cuando su promedio de notas sea superior a <?= number_format($datos['Promedio_Minimo'], 0) ?> puntos.</li>
                
                <li>Los becados de reingreso deberán de presentar al encargado de las becas, original y fotocopias de las notas aprobadas del ciclo anterior, estas deberán estar selladas y firmadas por el director del establecimiento.</li>
                
                <li>La beca escolar contempla el pago de Inscripción del estudiante y 10 pagos de mensualidades correspondientes de febrero a noviembre, según sea el precio a cancelar en la Universidad donde el alumno estudie, previa autorización del comité de becas.</li>
                
                <li>Se asignará una bolsa escolar anual por alumno, que comprende la cantidad de Q.500.00 como máximo.</li>
                
                <li>La fecha límite de entrega de la bolsa escolar será el 30 de febrero.</li>
                
                <li>El estudiante se compromete a brindar su usuario y clave de plataforma virtual para poder ver sus notas y poder cancelarle sus pagos de mensualidades.</li>
                
                <li>Las becas no cubren otros gastos, como derecho a exámenes, pago por graduaciones y otros que no sean colegiatura y los mencionados anteriormente.</li>
                
                <li>Los pagos de inscripción y mensualidades los hará el Club Rotario Coatepeque Colomba directamente a la Universidad.</li>
                
                <li>Se hará un estudio socioeconómico de la familia del candidato.</li>
                
                <li>Se hará un contrato de beca con el padre de familia o encargado directo del becado, para que haya un responsable de la beca.</li>
                
                <li>La adjudicación de una beca estará condicionada a estudiantes de recursos económicos bajos.</li>
                
                <li>El becado deberá mantener un promedio mínimo de <strong><?= number_format($datos['Promedio_Minimo'], 0) ?> puntos</strong> (promedio de todas las asignaturas), durante todas sus evaluaciones.</li>
                
                <li>Si en caso el becado no llega a <?= number_format($datos['Promedio_Minimo'], 0) ?> puntos de promedio al finalizar una unidad por primera vez se le hará una llamada de atención por escrito, por segunda vez se le hará una llamada de atención por escrito y suspensión de pago hasta que recupere el promedio permitido, por tercera vez se le hará la cancelación definitiva de la beca y los padres se comprometen en devolver lo que se le ha pagado por parte del Club Rotario Coatepeque-Colomba, durante el período que haya cursado, en un plazo no mayor de treinta días y de inmediato perderá la beca por bajo rendimiento académico.</li>
                
                <li>Si en caso el becado reprueba un curso al finalizar una unidad se le hará por primera vez una llamada de atención por escrito y suspensión de pago hasta que apruebe todos sus cursos, por segunda vez se le hará una llamada de atención por escrito y suspensión de pago hasta que apruebe todos sus cursos, por tercera vez significará la cancelación definitiva de la beca y los padres se comprometen en devolver lo que se le ha pagado por parte del Club Rotario Coatepeque-Colomba, durante el período que haya cursado, en un plazo no mayor de treinta días y de inmediato perderá la beca por bajo rendimiento académico.</li>
                
                <li>El alumno deberá comportarse y tratar con respeto y decoro a sus compañeros y catedráticos, de lo contrario el estudiante perderá la beca de inmediato.</li>
                
                <li>El estudiante deberá de mantener principios de honradez, honestidad y no alterar ninguna información de sus notas.</li>
                
                <li>El alumno becado se compromete a colaborar en las actividades que realiza el Club Rotario Coatepeque-Colomba durante el año y a formar parte de un comité de exbecados.</li>
                
                <li>Después de haber leído y entendido por completo este compromiso, firmo.</li>
            </ol>
            
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Firma del Estudiante</div>
                    <div style="font-size: 10pt; margin-top: 5px;"><?= htmlspecialchars($datos['Nombres_Apellidos']) ?></div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Firma del Padre o Encargado</div>
                    <div style="font-size: 10pt; margin-top: 5px;"><?= htmlspecialchars($datos['Nombre_Encargado']) ?></div>
                </div>
            </div>
            
            <div class="signature-box" style="margin-top: 40px;">
                <div class="signature-line"></div>
                <div class="signature-label">Firma del Representante Legal del Club</div>
                <div style="font-size: 10pt; margin-top: 5px;">Club Rotario Coatepeque-Colomba</div>
            </div>
            
            <div class="date-section">
                <strong>Fecha que se oficializó el compromiso de beca:</strong>
                <span class="date-line"><?= $fecha_actual ?></span>
            </div>
            
            <div class="seal-box">
                <div style="margin-top: 40px; padding: 20px; border: 2px solid #000; display: inline-block;">
                    SELLO DEL CLUB
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Club Rotario Coatepeque-Colomba</strong></p>
            <p>Compromiso de Beca Educativa - <?= $anio_actual ?></p>
            <p>Expediente: <?= htmlspecialchars($datos['Numero_Expediente']) ?></p>
        </div>
    </div>
    
    <script>
        // Imprimir automáticamente al cargar (opcional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>