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
    header('Location: estudiantes_becados.php');
    exit;
}

$id_estudiante = $_GET['id'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener información del estudiante
$sql = "SELECT 
            e.*,
            b.Id_Beca,
            b.Tipo_Beca,
            b.Monto_Mensual,
            b.Estado_Beca,
            b.Fecha_Inicio as Fecha_Inicio_Beca,
            b.Fecha_Fin,
            b.Promedio_Minimo
        FROM Estudiantes e
        LEFT JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE e.Id_Estudiante = ?
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_estudiante]);
$estudiante = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$estudiante) {
    header('Location: estudiantes_becados.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Procesar la imagen si se subió una nueva
        $foto_nombre = $estudiante['Foto_Becado']; // Mantener la foto actual por defecto
        
        if (isset($_FILES['foto_becado']) && $_FILES['foto_becado']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['foto_becado'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
            
            // Validar extensión
            if (!in_array($extension, $extensiones_permitidas)) {
                throw new Exception('Solo se permiten archivos de imagen (JPG, JPEG, PNG, GIF)');
            }
            
            // Validar tamaño (máximo 5MB)
            if ($archivo['size'] > 5 * 1024 * 1024) {
                throw new Exception('La imagen no debe superar los 5MB');
            }
            
            // Crear directorio si no existe
            $directorio_fotos = 'uploads/fotos_becados/';
            if (!file_exists($directorio_fotos)) {
                mkdir($directorio_fotos, 0755, true);
            }
            
            // Generar nombre único para la foto
            $foto_nombre = 'becado_' . $id_estudiante . '_' . time() . '.' . $extension;
            $ruta_destino = $directorio_fotos . $foto_nombre;
            
            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                throw new Exception('Error al subir la imagen');
            }
            
            // Eliminar foto anterior si existe
            if (!empty($estudiante['Foto_Becado']) && file_exists($directorio_fotos . $estudiante['Foto_Becado'])) {
                unlink($directorio_fotos . $estudiante['Foto_Becado']);
            }
        }
        
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
            Foto_Becado = ?
            WHERE Id_Estudiante = ?";
        
        $stmt = $pdo->prepare($sql_estudiante);
        $stmt->execute([
            $_POST['nombres_apellidos'],
            $_POST['edad'],
            $_POST['telefono'],
            $_POST['email'],
            $_POST['nombre_madre'],
            $_POST['nombre_padre'],
            $_POST['direccion'],
            $_POST['nombre_encargado'],
            $_POST['telefono_encargado'],
            $_POST['grado_obtenido'],
            $_POST['escuela_anterior'],
            $foto_nombre,
            $id_estudiante
        ]);
        
        // Actualizar datos de la beca si existe
        if ($estudiante['Id_Beca']) {
            $sql_beca = "UPDATE Becas_Otorgadas SET
                Tipo_Beca = ?,
                Monto_Mensual = ?,
                Promedio_Minimo = ?
                WHERE Id_Beca = ?";
            
            $stmt = $pdo->prepare($sql_beca);
            $stmt->execute([
                $_POST['tipo_beca'],
                $_POST['monto_mensual'],
                $_POST['promedio_minimo'],
                $estudiante['Id_Beca']
            ]);
        }
        
        // Registrar en bitácora
        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                         VALUES (?, ?, CURDATE(), ?)";
        $stmt = $pdo->prepare($sql_bitacora);
        $stmt->execute([
            $_SESSION['user_id'],
            "Editó información del estudiante: " . $_POST['nombres_apellidos'],
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
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

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-header {
            border-bottom: 3px solid #004b87;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .form-header h1 {
            color: #004b87;
            font-size: 2em;
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
            color: #004b87;
            font-size: 1.4em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
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
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #004b87;
            box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Estilos para la sección de foto */
        .photo-upload-section {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .photo-preview {
            flex-shrink: 0;
        }

        .photo-preview-container {
            width: 200px;
            height: 250px;
            border: 3px dashed #004b87;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8f9fa;
            position: relative;
        }

        .photo-preview-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-container .no-photo {
            text-align: center;
            color: #6c757d;
            padding: 20px;
        }

        .photo-preview-container .no-photo i {
            font-size: 3em;
            margin-bottom: 10px;
            display: block;
        }

        .photo-upload-controls {
            flex: 1;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: #004b87;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .file-input-label:hover {
            background: #003866;
            transform: translateY(-2px);
        }

        .file-input-label i {
            font-size: 1.2em;
        }

        .file-name-display {
            margin-top: 10px;
            padding: 10px;
            background: #e7f3ff;
            border-radius: 6px;
            font-size: 0.9em;
            color: #004b87;
            display: none;
        }

        .file-name-display.active {
            display: block;
        }

        .photo-requirements {
            margin-top: 15px;
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 6px;
            font-size: 0.85em;
        }

        .photo-requirements ul {
            margin: 10px 0 0 20px;
        }

        .photo-requirements li {
            margin: 5px 0;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
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
            background: #004b87;
            color: white;
        }

        .btn-primary:hover {
            background: #003866;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 75, 135, 0.3);
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
            border-left: 4px solid #004b87;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #004b87;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .photo-upload-section {
                flex-direction: column;
            }

            .photo-preview-container {
                width: 100%;
                max-width: 250px;
                margin: 0 auto;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
            <a href="estudiantes_becados.php">Estudiantes Becados</a> /
            <a href="detalle_becado.php?id=<?= $id_estudiante ?>">Detalle</a> /
            Editar
        </div>

        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-edit"></i> Editar Información del Estudiante</h1>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= $mensaje ?>
            </div>
            <?php endif; ?>

            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                Los campos marcados con <span class="required">*</span> son obligatorios
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Sección: Fotografía del Becado -->
                <div class="form-section">
                    <h2><i class="fas fa-camera"></i> Fotografía del Becado</h2>
                    <div class="photo-upload-section">
                        <div class="photo-preview">
                            <div class="photo-preview-container" id="photoPreview">
                                <?php if (!empty($estudiante['Foto_Becado']) && file_exists('uploads/fotos_becados/' . $estudiante['Foto_Becado'])): ?>
                                    <img src="uploads/fotos_becados/<?= htmlspecialchars($estudiante['Foto_Becado']) ?>" 
                                         alt="Foto del becado" id="previewImage">
                                <?php else: ?>
                                    <div class="no-photo">
                                        <i class="fas fa-user-circle"></i>
                                        <div>Sin fotografía</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="photo-upload-controls">
                            <div class="form-group">
                                <label>Subir nueva fotografía</label>
                                <div class="file-input-wrapper">
                                    <input type="file" name="foto_becado" id="fotoBecado" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    <label for="fotoBecado" class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        Seleccionar imagen
                                    </label>
                                </div>
                                <div class="file-name-display" id="fileNameDisplay">
                                    <i class="fas fa-file-image"></i> <span id="fileName"></span>
                                </div>
                            </div>
                            
                            <div class="photo-requirements">
                                <strong><i class="fas fa-exclamation-circle"></i> Requisitos de la imagen:</strong>
                                <ul>
                                    <li>Formatos permitidos: JPG, JPEG, PNG, GIF</li>
                                    <li>Tamaño máximo: 5 MB</li>
                                    <li>Se recomienda foto tipo carnet</li>
                                    <li>Fondo claro y fotografía reciente</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección: Datos Personales -->
                <div class="form-section">
                    <h2><i class="fas fa-user"></i> Datos Personales</h2>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombres y Apellidos <span class="required">*</span></label>
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
                                   value="<?= htmlspecialchars($estudiante['Telefono']) ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" 
                                   value="<?= htmlspecialchars($estudiante['Email']) ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Dirección <span class="required">*</span></label>
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
                                   value="<?= htmlspecialchars($estudiante['Nombre_Madre'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Nombre del Padre</label>
                            <input type="text" name="nombre_padre" 
                                   value="<?= htmlspecialchars($estudiante['Nombre_Padre'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Nombre del Encargado <span class="required">*</span></label>
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
                                   value="<?= htmlspecialchars($estudiante['Grado_Obtenido_Anterior']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Escuela Anterior <span class="required">*</span></label>
                            <input type="text" name="escuela_anterior" 
                                   value="<?= htmlspecialchars($estudiante['Escuela_Anterior']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Sección: Información de Beca -->
                <?php if ($estudiante['Id_Beca']): ?>
                <div class="form-section">
                    <h2><i class="fas fa-award"></i> Información de Beca</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de Beca <span class="required">*</span></label>
                            <select name="tipo_beca" required>
                                <option value="Completa" <?= $estudiante['Tipo_Beca'] === 'Completa' ? 'selected' : '' ?>>Completa</option>
                                <option value="Parcial" <?= $estudiante['Tipo_Beca'] === 'Parcial' ? 'selected' : '' ?>>Parcial</option>
                                <option value="Especial" <?= $estudiante['Tipo_Beca'] === 'Especial' ? 'selected' : '' ?>>Especial</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Monto Mensual (Q) <span class="required">*</span></label>
                            <input type="number" name="monto_mensual" step="0.01" min="0"
                                   value="<?= $estudiante['Monto_Mensual'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Promedio Mínimo <span class="required">*</span></label>
                            <input type="number" name="promedio_minimo" step="0.01" min="0" max="100"
                                   value="<?= $estudiante['Promedio_Minimo'] ?>" required>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <a href="detalle_becado.php?id=<?= $id_estudiante ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Preview de la imagen antes de subir
        document.getElementById('fotoBecado').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const fileName = document.getElementById('fileName');
            const previewContainer = document.getElementById('photoPreview');
            
            if (file) {
                // Mostrar nombre del archivo
                fileName.textContent = file.name;
                fileNameDisplay.classList.add('active');
                
                // Crear preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewContainer.innerHTML = '<img src="' + event.target.result + '" alt="Preview" id="previewImage">';
                };
                reader.readAsDataURL(file);
            } else {
                fileNameDisplay.classList.remove('active');
            }
        });
    </script>
</body>
</html>