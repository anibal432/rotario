<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$user_id = $_SESSION['user_id'];
$mensaje = '';
$tipo_mensaje = '';

$sql_estudiantes = "SELECT 
                        e.Id_Estudiante,
                        e.Nombres_Apellidos,
                        e.Numero_Expediente,
                        b.Tipo_Beca
                    FROM Estudiantes e
                    INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
                    WHERE b.Estado_Beca IN ('Activa', 'Finalizada')
                    ORDER BY e.Nombres_Apellidos ASC";
$stmt_estudiantes = $pdo->prepare($sql_estudiantes);
$stmt_estudiantes->execute();
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre_estudiante = $_POST['nombre_estudiante'];
        $id_estudiante = $_POST['id_estudiante'] ?: null;
        $testimonio = $_POST['testimonio'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if (empty($nombre_estudiante) || empty($testimonio)) {
            throw new Exception('Por favor completa todos los campos obligatorios');
        }

        if (strlen($testimonio) < 50) {
            throw new Exception('El testimonio debe tener al menos 50 caracteres');
        }

        $sql_max_orden = "SELECT COALESCE(MAX(Orden), 0) + 1 as Siguiente_Orden FROM Testimonios";
        $stmt_orden = $pdo->prepare($sql_max_orden);
        $stmt_orden->execute();
        $siguiente_orden = $stmt_orden->fetchColumn();

        $ruta_foto = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['foto'];
            
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $tipos_permitidos = ['jpg', 'jpeg', 'png'];
            
            if (!in_array($extension, $tipos_permitidos)) {
                throw new Exception('Solo se permiten archivos JPG, JPEG o PNG');
            }

            if ($archivo['size'] > 2 * 1024 * 1024) {
                throw new Exception('La foto no debe superar los 2MB');
            }

            $directorio = 'uploads/testimonios';
            if (!file_exists($directorio)) {
                mkdir($directorio, 0755, true);
            }

            $nombre_foto = 'testimonio_' . time() . '.' . $extension;
            $ruta_completa = $directorio . '/' . $nombre_foto;

            if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                throw new Exception('Error al subir la foto');
            }

            $ruta_foto = $ruta_completa;
        }
        $sql = "INSERT INTO Testimonios 
                (Id_Estudiante, Nombre_Estudiante, Testimonio, Foto, Activo, Orden, Fecha_Registro)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id_estudiante,
            $nombre_estudiante,
            $testimonio,
            $ruta_foto,
            $activo,
            $siguiente_orden
        ]);

        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                         VALUES (?, ?, CURDATE())";
        $actividad = "Agregó nuevo testimonio de {$nombre_estudiante}";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $stmt_bitacora->execute([$user_id, $actividad]);

        $mensaje = '¡Testimonio agregado exitosamente!';
        $tipo_mensaje = 'success';

        header('refresh:2;url=testimonios.php');

    } catch (PDOException $e) {
        $mensaje = 'Error al guardar el testimonio: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Testimonio - Club Rotario</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .breadcrumb {
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #666;
        }

        .breadcrumb a {
            color: #9b59b6;
            text-decoration: none;
        }

        .header {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h2 {
            color: #9b59b6;
            font-size: 1.3em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
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
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #9b59b6;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
            line-height: 1.6;
        }

        .form-group .hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        .char-counter {
            text-align: right;
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }

        .file-upload {
            position: relative;
            display: block;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload-button {
            display: block;
            width: 100%;
            padding: 40px 20px;
            border: 3px dashed #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .file-upload-button:hover {
            border-color: #9b59b6;
            background: #f5f0ff;
        }

        .file-upload-button i {
            font-size: 3em;
            color: #9b59b6;
            margin-bottom: 15px;
            display: block;
        }

        .file-preview {
            margin-top: 15px;
            display: none;
        }

        .file-preview img {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .info-box strong {
            color: #2980b9;
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
            background: #27ae60;
            color: white;
            width: 100%;
            justify-content: center;
            font-size: 1.1em;
        }

        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
            <a href="testimonios.php">Testimonios</a> /
            Agregar Nuevo
        </div>

        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-plus-circle"></i>
                Agregar Nuevo Testimonio
            </h1>
            <p style="margin-top: 10px; font-size: 1.1em; opacity: 0.95;">
                Comparte la experiencia de un estudiante becado
            </p>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_mensaje ?>">
            <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <div><?= $mensaje ?></div>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="form-card">
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Información del Estudiante -->
                <div class="form-section">
                    <h2><i class="fas fa-user"></i> Información del Estudiante</h2>

                    <div class="info-box">
                        <strong>Tip:</strong> Puedes seleccionar un estudiante de la lista o escribir un nombre manualmente 
                        si el testimonio es de alguien que ya no está becado.
                    </div>

                    <div class="form-group">
                        <label>
                            Seleccionar Estudiante Becado
                        </label>
                        <select name="id_estudiante" id="selectEstudiante" onchange="llenarNombre()">
                            <option value="">-- Seleccionar de la lista (opcional) --</option>
                            <?php foreach ($estudiantes as $est): ?>
                                <option value="<?= $est['Id_Estudiante'] ?>" 
                                        data-nombre="<?= htmlspecialchars($est['Nombres_Apellidos']) ?>">
                                    <?= htmlspecialchars($est['Nombres_Apellidos']) ?> 
                                    (<?= htmlspecialchars($est['Numero_Expediente']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">Si seleccionas un estudiante, su nombre se llenará automáticamente</div>
                    </div>

                    <div class="form-group">
                        <label>
                            Nombre del Estudiante <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="nombre_estudiante" 
                               id="nombreEstudiante"
                               placeholder="Ej: María José López García"
                               required>
                        <div class="hint">Nombre completo tal como aparecerá en el sitio web</div>
                    </div>
                </div>

                <!-- Testimonio -->
                <div class="form-section">
                    <h2><i class="fas fa-quote-right"></i> Testimonio</h2>

                    <div class="form-group">
                        <label>
                            Texto del Testimonio <span class="required">*</span>
                        </label>
                        <textarea name="testimonio" 
                                  id="testimonioTexto"
                                  placeholder="Escribe aquí el testimonio del estudiante..."
                                  required
                                  oninput="contarCaracteres()"></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span> caracteres (mínimo 50)
                        </div>
                        <div class="hint">
                            Escribe un testimonio sincero y motivador sobre cómo la beca ha impactado la vida del estudiante
                        </div>
                    </div>
                </div>

                <!-- Foto -->
                <div class="form-section">
                    <h2><i class="fas fa-camera"></i> Fotografía</h2>

                    <div class="form-group">
                        <label>Foto del Estudiante (Opcional)</label>
                        <div class="file-upload">
                            <input type="file" 
                                   id="foto" 
                                   name="foto" 
                                   accept=".jpg,.jpeg,.png"
                                   onchange="previsualizarImagen(this)">
                            <label for="foto" class="file-upload-button">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div><strong>Click para seleccionar foto</strong></div>
                                <div style="font-size: 0.9em; margin-top: 10px; color: #666;">
                                    JPG, JPEG o PNG - Máximo 2MB
                                </div>
                            </label>
                        </div>
                        <div class="file-preview" id="preview">
                            <img id="previewImage" src="" alt="Preview">
                        </div>
                        <div class="hint">
                            Una foto profesional o académica del estudiante (opcional pero recomendado)
                        </div>
                    </div>
                </div>

                <!-- Configuración -->
                <div class="form-section">
                    <h2><i class="fas fa-cog"></i> Configuración</h2>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" 
                                   id="activo" 
                                   name="activo" 
                                   checked>
                            <label for="activo">
                                <strong>Publicar inmediatamente</strong> 
                                (marcar para que aparezca en el sitio web)
                            </label>
                        </div>
                        <div class="hint">
                            Si no marcas esta opción, el testimonio se guardará como inactivo y no será visible públicamente
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i>
                        Guardar Testimonio
                    </button>
                    <a href="testimonios.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function llenarNombre() {
            const select = document.getElementById('selectEstudiante');
            const input = document.getElementById('nombreEstudiante');
            const option = select.options[select.selectedIndex];
            
            if (option.value) {
                input.value = option.getAttribute('data-nombre');
            }
        }

        function contarCaracteres() {
            const texto = document.getElementById('testimonioTexto').value;
            const contador = document.getElementById('charCount');
            contador.textContent = texto.length;
            
            if (texto.length < 50) {
                contador.style.color = '#e74c3c';
            } else {
                contador.style.color = '#27ae60';
            }
        }

        function previsualizarImagen(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('preview').style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>