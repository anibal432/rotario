<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar que se recibió el ID del estudiante
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: aplicaciones.php');
    exit;
}

$id_estudiante = $_GET['id'];

// Obtener información del estudiante
$sql = "SELECT 
            e.*,
            ev.Estado_Evaluacion,
            ev.Fecha_Evaluacion,
            b.Id_Beca,
            b.Estado_Beca,
            b.Fecha_Inicio
        FROM Estudiantes e
        LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
        LEFT JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE e.Id_Estudiante = ?
        ORDER BY ev.Fecha_Evaluacion DESC
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: aplicaciones.php');
    exit;
}

// Verificar si ya existe una carta de compromiso
$sql_carta = "SELECT * FROM Documentos_Solicitud 
              WHERE Id_Estudiante = ? AND Tipo_Documento = 'Carta_Compromiso'
              ORDER BY Fecha_Subida DESC LIMIT 1";
$stmt_carta = $pdo->prepare($sql_carta);
$stmt_carta->execute([$id_estudiante]);
$carta_existente = $stmt_carta->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta de Compromiso - <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #004b87 0%, #0068b8 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1em;
        }

        .content {
            background: white;
            padding: 40px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .student-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #004b87;
        }

        .student-info h2 {
            color: #004b87;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.85em;
            text-transform: uppercase;
        }

        .info-value {
            color: #333;
            font-size: 1em;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-aprobado {
            background: #d4edda;
            color: #155724;
        }

        .status-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .status-activo {
            background: #cce5ff;
            color: #004085;
        }

        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: start;
            gap: 15px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        .alert i {
            font-size: 1.5em;
            margin-top: 2px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #004b87 0%, #0068b8 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 75, 135, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .card h3 {
            color: #004b87;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .document-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .document-icon {
            font-size: 2em;
            color: #dc3545;
        }

        .document-details h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .document-details p {
            color: #666;
            font-size: 0.9em;
        }

        .instructions {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #004b87;
        }

        .instructions h3 {
            color: #004b87;
            margin-bottom: 15px;
        }

        .instructions ul {
            margin-left: 20px;
        }

        .instructions li {
            margin-bottom: 10px;
            color: #333;
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-signature"></i> Carta de Compromiso</h1>
            <p>Generación de documento oficial de compromiso</p>
        </div>

        <div class="content">
            <!-- Información del Estudiante -->
            <div class="student-info">
                <h2><i class="fas fa-user"></i> Información del Estudiante</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nombre Completo</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">No. Expediente</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Numero_Expediente']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado de Evaluación</span>
                        <span class="status-badge status-<?= strtolower($estudiante['Estado_Evaluacion']) ?>">
                            <?= htmlspecialchars($estudiante['Estado_Evaluacion']) ?>
                        </span>
                    </div>
                    <?php if ($estudiante['Estado_Beca']): ?>
                    <div class="info-item">
                        <span class="info-label">Estado de Beca</span>
                        <span class="status-badge status-activo">
                            <?= htmlspecialchars($estudiante['Estado_Beca']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Encargado</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Nombre_Encargado']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono Encargado</span>
                        <span class="info-value"><?= htmlspecialchars($estudiante['Telefono_Encargado']) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($estudiante['Estado_Evaluacion'] !== 'Aprobado'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Atención:</strong> Este estudiante aún no ha sido aprobado. 
                        Debes aprobar la solicitud antes de generar la carta de compromiso.
                        <br><br>
                        <a href="entrevista_estudiante.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-primary" style="margin-top: 10px;">
                            <i class="fas fa-clipboard-check"></i> Ir a Entrevista
                        </a>
                    </div>
                </div>
            <?php elseif (!$estudiante['Id_Beca']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Atención:</strong> Este estudiante fue aprobado pero aún no tiene una beca asignada.
                        Debes crear el registro de beca antes de generar la carta de compromiso.
                    </div>
                </div>
            <?php else: ?>
                <?php if ($carta_existente): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Carta de Compromiso Generada</strong>
                            <p>Ya existe una carta de compromiso para este estudiante generada el 
                            <?= date('d/m/Y', strtotime($carta_existente['Fecha_Subida'])) ?>.</p>
                        </div>
                    </div>

                    <div class="card">
                        <h3><i class="fas fa-file-pdf"></i> Documento Actual</h3>
                        <div class="document-item">
                            <div class="document-info">
                                <i class="fas fa-file-pdf document-icon"></i>
                                <div class="document-details">
                                    <h4><?= htmlspecialchars($carta_existente['Nombre_Archivo']) ?></h4>
                                    <p>Generado el <?= date('d/m/Y H:i', strtotime($carta_existente['Fecha_Subida'])) ?></p>
                                </div>
                            </div>
                            <div>
                                <a href="<?= htmlspecialchars($carta_existente['Ruta_Archivo']) ?>" 
                                   target="_blank" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> Ver PDF
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Listo para Generar</strong>
                            <p>El estudiante ha sido aprobado y tiene una beca asignada. 
                            Puedes generar la carta de compromiso ahora.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="instructions">
                    <h3><i class="fas fa-list-check"></i> Instrucciones</h3>
                    <ul>
                        <li>La carta de compromiso se generará en formato PDF con todos los datos del estudiante</li>
                        <li>El documento incluye el reglamento completo del programa de becas</li>
                        <li>Debes imprimir el documento para que sea firmado por:
                            <ul style="margin-top: 5px;">
                                <li>El estudiante</li>
                                <li>El padre o encargado</li>
                                <li>El representante legal del Club Rotario</li>
                            </ul>
                        </li>
                        <li>Una vez firmado, debes colocar el sello oficial del Club</li>
                        <li>Entrega una copia al estudiante y conserva el original en los archivos del Club</li>
                    </ul>
                </div>

                <div class="button-group">
                    <a href="generar_carta_compromiso.php?id=<?= $id_estudiante ?>" 
                       target="_blank" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> 
                        <?= $carta_existente ? 'Regenerar' : 'Generar' ?> Carta de Compromiso
                    </a>
                    
                    <a href="estudiantes_becados.php" class="btn btn-primary">
                        <i class="fas fa-users"></i> Ver Estudiantes Becados
                    </a>

                    <a href="aplicaciones.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Solicitudes
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>