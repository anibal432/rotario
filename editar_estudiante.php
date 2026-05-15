<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Verificar ID del estudiante
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: lista_solicitudes.php');
    exit;
}

$id_estudiante = intval($_GET['id']);
$mensaje = '';
$tipo_mensaje = '';

// Obtener información del estudiante
$sql = "SELECT * FROM Estudiantes WHERE Id_Estudiante = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: lista_solicitudes.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Actualizar datos del estudiante
        $sql_estudiante = "UPDATE Estudiantes SET
            Nombres_Apellidos = ?,
            Edad = ?,
            Telefono = ?,
            Email = ?,
            Nombre_Madre = ?,
            Nombre_Padre = ?,
            Direccion_Domiciliar = ?,
            Nombre_Encargado = ?,
            Telefono_Encargado = ?,
            Grado_Obtenido_Anterior = ?,
            Escuela_Anterior = ?,
            Estado_Estudiante = ?
            WHERE Id_Estudiante = ?";
        
        $stmt = $pdo->prepare($sql_estudiante);
        $stmt->execute([
            $_POST['nombres_apellidos'],
            $_POST['edad'],
            $_POST['telefono'],
            $_POST['email'],
            $_POST['nombre_madre'] ?: null,
            $_POST['nombre_padre'] ?: null,
            $_POST['direccion'],
            $_POST['nombre_encargado'],
            $_POST['telefono_encargado'],
            $_POST['grado_obtenido'],
            $_POST['escuela_anterior'],
            $_POST['estado_estudiante'],
            $id_estudiante
        ]);
        
        // Registrar en bitácora
        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                         VALUES (?, ?, CURDATE(), ?)";
        $stmt = $pdo->prepare($sql_bitacora);
        $stmt->execute([
            $_SESSION['user_id'],
            "Editó información del estudiante: " . $_POST['nombres_apellidos'] . " (ID: $id_estudiante)",
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $pdo->commit();
        
        $mensaje = 'Información actualizada exitosamente';
        $tipo_mensaje = 'success';
        
        // Recargar datos actualizados
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_estudiante]);
        $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = 'Error al actualizar: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Estudiante - <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .breadcrumb {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .form-section {
            margin-bottom: 35px;
        }

        .form-section h2 {
            color: #667eea;
            font-size: 1.4em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.95em;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 30px;
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
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .info-note {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #004085;
        }

        .estado-info {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }

        .estado-info strong {
            color: #856404;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
                <a href="lista_solicitudes.php">Solicitudes</a> /
                <a href="admin_detalle.php?id=<?= $id_estudiante ?>">Detalle</a> /
                Editar
            </div>
            <h1><i class="fas fa-edit"></i> Editar Información del Estudiante</h1>
        </div>

        <div class="form-container">
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= $mensaje ?>
            </div>
            <?php endif; ?>

            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                Los campos marcados con <span class="required">*</span> son obligatorios. Esta información se utiliza para la evaluación de la solicitud de beca.
            </div>

            <div class="estado-info">
                <strong><i class="fas fa-flag"></i> Estado Actual:</strong> <?= $estudiante['Estado_Estudiante'] ?>
            </div>

            <form method="POST" action="">
                <!-- Sección: Datos Personales -->
                <div class="form-section">
                    <h2><i class="fas fa-user"></i> Datos Personales</h2>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombres y Apellidos Completos <span class="required">*</span></label>
                            <input type="text" name="nombres_apellidos" 
                                   value="<?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Edad <span class="required">*</span></label>
                            <input type="number" name="edad" 
                                   value="<?= $estudiante['Edad'] ?>" min="5" max="30" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono <span class="required">*</span></label>
                            <input type="tel" name="telefono" 
                                   value="<?= htmlspecialchars($estudiante['Telefono']) ?>" 
                                   placeholder="Ej: 1234-5678" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Correo Electrónico <span class="required">*</span></label>
                            <input type="email" name="email" 
                                   value="<?= htmlspecialchars($estudiante['Email']) ?>" 
                                   placeholder="ejemplo@correo.com" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Dirección Completa <span class="required">*</span></label>
                            <textarea name="direccion" required><?= htmlspecialchars($estudiante['Direccion_Domiciliar']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sección: Información Familiar -->
                <div class="form-section">
                    <h2><i class="fas fa-users"></i> Información Familiar</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre de la Madre</label>
                            <input type="text" name="nombre_madre" 
                                   value="<?= htmlspecialchars($estudiante['Nombre_Madre'] ?? '') ?>"
                                   placeholder="Opcional">
                        </div>
                        <div class="form-group">
                            <label>Nombre del Padre</label>
                            <input type="text" name="nombre_padre" 
                                   value="<?= htmlspecialchars($estudiante['Nombre_Padre'] ?? '') ?>"
                                   placeholder="Opcional">
                        </div>
                        <div class="form-group">
                            <label>Nombre del Encargado/Tutor <span class="required">*</span></label>
                            <input type="text" name="nombre_encargado" 
                                   value="<?= htmlspecialchars($estudiante['Nombre_Encargado']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Teléfono del Encargado <span class="required">*</span></label>
                            <input type="tel" name="telefono_encargado" 
                                   value="<?= htmlspecialchars($estudiante['Telefono_Encargado']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Sección: Información Académica -->
                <div class="form-section">
                    <h2><i class="fas fa-graduation-cap"></i> Información Académica</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Último Grado Obtenido <span class="required">*</span></label>
                            <input type="text" name="grado_obtenido" 
                                   value="<?= htmlspecialchars($estudiante['Grado_Obtenido_Anterior']) ?>" 
                                   placeholder="Ej: Tercero Básico" required>
                        </div>
                        <div class="form-group">
                            <label>Escuela/Colegio Anterior <span class="required">*</span></label>
                            <input type="text" name="escuela_anterior" 
                                   value="<?= htmlspecialchars($estudiante['Escuela_Anterior']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Sección: Estado del Estudiante -->
                <div class="form-section">
                    <h2><i class="fas fa-flag"></i> Estado de la Solicitud</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Estado del Estudiante <span class="required">*</span></label>
                            <select name="estado_estudiante" required>
                                <option value="Activo" <?= $estudiante['Estado_Estudiante'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                                <option value="Graduado" <?= $estudiante['Estado_Estudiante'] === 'Graduado' ? 'selected' : '' ?>>Graduado</option>
                                <option value="Suspendido" <?= $estudiante['Estado_Estudiante'] === 'Suspendido' ? 'selected' : '' ?>>Suspendido</option>
                                <option value="Retirado" <?= $estudiante['Estado_Estudiante'] === 'Retirado' ? 'selected' : '' ?>>Retirado</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <div class="info-note" style="margin: 0;">
                                <i class="fas fa-info-circle"></i>
                                <strong>Nota:</strong> Cambiar el estado a "Suspendido" o "Retirado" afectará la elegibilidad del estudiante para recibir becas.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <a href="admin_detalle.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>