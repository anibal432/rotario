<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}


require_once 'conexion.php';

// Verificar ID de la beca
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: estudiantes_becados.php');
    exit;
}

$id_beca = $_GET['id'];

// Obtener información de la beca
$sql = "SELECT b.*, e.Nombres_Apellidos, e.Id_Estudiante
        FROM Becas_Otorgadas b
        INNER JOIN Estudiantes e ON b.Id_Estudiante = e.Id_Estudiante
        WHERE b.Id_Beca = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_beca]);
$beca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$beca) {
    header('Location: estudiantes_becados.php');
    exit;
}

// Procesar el formulario
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fecha_reactivacion = $_POST['fecha_reactivacion'] ?? date('Y-m-d');
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar estado de la beca
        $sql_update = "UPDATE Becas_Otorgadas 
                      SET Estado_Beca = 'Activa'
                      WHERE Id_Beca = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$id_beca]);
        
        // Registrar en bitácora
        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP)
                        VALUES (?, ?, CURDATE(), ?)";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $actividad = "Reactivó la beca del estudiante {$beca['Nombres_Apellidos']}";
        if (!empty($observaciones)) {
            $actividad .= ". Observaciones: $observaciones";
        }
        $stmt_bitacora->execute([$_SESSION['user_id'], $actividad, $_SERVER['REMOTE_ADDR']]);
        
        $pdo->commit();
        
        // Redirigir con mensaje de éxito
        header("Location: detalle_becado.php?id={$beca['Id_Estudiante']}&msg=reactivated");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error al reactivar la beca: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivar Beca - <?= htmlspecialchars($beca['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .breadcrumb {
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #666;
        }

        .breadcrumb a {
            color: #004b87;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header i {
            font-size: 4em;
            color: #28a745;
            margin-bottom: 15px;
        }

        .card-header h1 {
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .card-header p {
            color: #666;
            font-size: 1.1em;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            color: #004b87;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 1em;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #004b87;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .success-list {
            background: #d4edda;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .success-list h4 {
            color: #155724;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-list ul {
            list-style: none;
            padding-left: 0;
        }

        .success-list li {
            padding: 8px 0;
            color: #155724;
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .success-list li:before {
            content: "✓";
            flex-shrink: 0;
            font-weight: 700;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
            <a href="estudiantes_becados.php">Estudiantes Becados</a> /
            <a href="detalle_becado.php?id=<?= $beca['Id_Estudiante'] ?>"><?= htmlspecialchars($beca['Nombres_Apellidos']) ?></a> /
            Reactivar Beca
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-play-circle"></i>
                <h1>Reactivar Beca</h1>
                <p>Esta acción restaurará los beneficios de la beca</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Información de la beca -->
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Información de la Beca</h3>
                <div class="info-item">
                    <span class="info-label">Estudiante:</span>
                    <span class="info-value"><?= htmlspecialchars($beca['Nombres_Apellidos']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo de Beca:</span>
                    <span class="info-value"><?= htmlspecialchars($beca['Tipo_Beca']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Monto Mensual:</span>
                    <span class="info-value">Q<?= number_format($beca['Monto_Mensual'], 2) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado Actual:</span>
                    <span class="info-value" style="color: #ffc107; font-weight: 600;"><?= $beca['Estado_Beca'] ?></span>
                </div>
            </div>

            <!-- Información positiva -->
            <div class="success-list">
                <h4><i class="fas fa-check-circle"></i> Al reactivar esta beca:</h4>
                <ul>
                    <li>Los pagos mensuales se reanudarán</li>
                    <li>El estudiante recuperará todos los beneficios</li>
                    <li>Se registrará la reactivación en el historial</li>
                    <li>El estudiante será notificado sobre la reactivación</li>
                </ul>
            </div>

            <!-- Formulario -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="fecha_reactivacion">
                        Fecha de Reactivación
                    </label>
                    <input type="date" 
                           id="fecha_reactivacion" 
                           name="fecha_reactivacion" 
                           value="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="observaciones">
                        Observaciones (Opcional)
                    </label>
                    <textarea id="observaciones" 
                              name="observaciones" 
                              placeholder="Agrega cualquier observación relevante sobre la reactivación..."></textarea>
                </div>

                <div class="alert alert-success">
                    <i class="fas fa-info-circle"></i>
                    El estudiante podrá continuar con su beca normalmente.
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Estás seguro de reactivar esta beca?')">
                        <i class="fas fa-play-circle"></i>
                        Reactivar Beca
                    </button>
                    <a href="detalle_becado.php?id=<?= $beca['Id_Estudiante'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>