<?php
session_start();
require_once 'conexion.php';

// Obtener cláusulas activas
$sql = "SELECT * FROM Reglamento_Becas WHERE Estado = 'Activo' ORDER BY Orden ASC, Numero_Clausula ASC";
$stmt = $pdo->query($sql);
$clausulas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Previa - Carta de Compromiso</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background: #e5e5e5;
        }
        
        .controls {
            max-width: 850px;
            margin: 0 auto 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .alert {
            background: #d1ecf1;
            padding: 15px 20px;
            border-left: 4px solid #17a2b8;
            border-radius: 8px;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Contenedor que simula una hoja A4 */
        .pdf-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 50px 70px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            min-height: 1100px;
        }
        
        /* Segunda página */
        .page-two {
            max-width: 850px;
            margin: 30px auto 0;
            background: white;
            padding: 50px 70px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            min-height: 1100px;
        }
        
        /* Estilos EXACTOS del PDF original */
        h1 {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            color: #000;
            font-family: 'Helvetica', sans-serif;
        }
        
        h2 {
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            color: #000;
            font-family: 'Helvetica', sans-serif;
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
            font-size: 10pt;
            line-height: 1.6;
        }
        
        .clausula strong {
            font-weight: bold;
        }
        
        ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        li {
            margin-bottom: 3px;
            font-size: 10pt;
        }
        
        li strong {
            font-weight: bold;
        }
        
        .firma-text {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 30px;
            font-weight: bold;
            font-size: 10pt;
        }
        
        /* Sección de firmas (página 2) */
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
        
        .firma-nombre {
            font-size: 9pt;
            margin-top: 5px;
        }
        
        .sello-box {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            border: 2px dashed #000;
        }
        
        .sello-text {
            font-size: 12pt;
            font-weight: bold;
            color: #666;
        }
        
        .sello-subtitle {
            font-size: 8pt;
            margin-top: 10px;
            color: #999;
        }
        
        /* Responsive */
        @media print {
            body { background: white; padding: 0; }
            .controls { display: none; }
            .pdf-container, .page-two { 
                box-shadow: none; 
                padding: 40px;
                page-break-after: always; 
            }
        }
        
        @media (max-width: 768px) {
            .pdf-container, .page-two {
                padding: 30px 20px;
            }
            .controls {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="controls">        
        <div class="alert">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Vista Previa:</strong> Así se verá el reglamento en las Cartas de Compromiso. 
                Solo se muestran las cláusulas activas.
            </div>
        </div>
    </div>
    
    <div class="pdf-container">
        <h1>COMPROMISO ENTRE BECADOS Y CLUB ROTARIO COATEPEQUE-COLOMBA</h1>
        <h2>PARA EL AÑO ESCOLAR <?= date('Y') ?></h2>
        
        <div class="info-box">
            <div class="info-line">
                <span class="label">NOMBRE DEL ESTUDIANTE:</span> [NOMBRE DEL ESTUDIANTE]
            </div>
            <div class="info-line">
                <span class="label">NOMBRE DEL PADRE O ENCARGADO:</span> [NOMBRE DEL ENCARGADO]
            </div>
            <div class="info-line">
                <span class="label">No. de Teléfono:</span> [TELÉFONO] 
                <span style="margin-left: 20px;"><span class="label">Dirección Domiciliar:</span> [DIRECCIÓN]</span>
            </div>
            <div class="info-line">
                <span class="label">NIVEL ACADÉMICO:</span> [NIVEL] 
                <span style="margin-left: 20px;"><span class="label">GRADO:</span> [GRADO]</span>
            </div>
        </div>
        
        <div class="reglamento-title">REGLAMENTO GENERAL:</div>
        
        <div class="reglamento">
            <?php if (count($clausulas) > 0): ?>
                <?php foreach ($clausulas as $clausula): ?>
                    <div class="clausula">
                        <strong><?= $clausula['Numero_Clausula'] ?>.</strong> 
                        <?= $clausula['Contenido_Clausula'] ?>
                        
                        <?php if ($clausula['Tiene_Subcausulas']): ?>
                            <?php
                            $sql_sub = "SELECT * FROM Sub_Clausulas_Reglamento 
                                        WHERE Id_Clausula = ? ORDER BY Orden";
                            $stmt_sub = $pdo->prepare($sql_sub);
                            $stmt_sub->execute([$clausula['Id_Clausula']]);
                            $subcausulas = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php if (count($subcausulas) > 0): ?>
                                <ul>
                                    <?php foreach ($subcausulas as $sub): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($sub['Numero_Sub_Clausula']) ?>:</strong> 
                                            <?= htmlspecialchars($sub['Contenido']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">
                    No hay cláusulas activas para mostrar.
                </p>
            <?php endif; ?>
        </div>
        
        <div class="firma-text">
            Después de haber leído y entendido por completo este compromiso, firmo.
        </div>
        
        <div class="firmas-section">
            <div class="firma-box">
                <div class="firma-line"></div>
                <div class="firma-label">Firma del Estudiante</div>
                <div class="firma-nombre">[Nombre del Estudiante]</div>
            </div>
            
            <div class="firma-box">
                <div class="firma-line"></div>
                <div class="firma-label">Firma del Padre o Encargado</div>
                <div class="firma-nombre">[Nombre del Encargado]</div>
            </div>
            
            <div class="firma-box">
                <div class="firma-line"></div>
                <div class="firma-label">Firma del Representante Legal del Club</div>
                <div class="firma-nombre">Club Rotario Coatepeque-Colomba</div>
            </div>
            
            <div class="firma-box">
                <div class="firma-line"></div>
                <div class="firma-label">Fecha que se oficializó el compromiso de beca</div>
                <div class="firma-nombre"><?= date('d/m/Y') ?></div>
            </div>
            
            <div class="sello-box">
                <div class="sello-text">SELLO DEL CLUB</div>
                <div class="sello-subtitle">(Colocar sello oficial aquí)</div>
            </div>
        </div>
    </div>
</body>
</html>