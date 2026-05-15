<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';
$id_testimonio = $_GET['id'] ?? null;

if (!$id_testimonio) {
    header('Location: testimonios.php');
    exit;
}

// Obtener datos del testimonio
$sql_testimonio = "SELECT 
                      t.*,
                      e.Nombres_Apellidos,
                      e.Numero_Expediente
                   FROM Testimonios t
                   LEFT JOIN Estudiantes e ON t.Id_Estudiante = e.Id_Estudiante
                   WHERE t.Id_Testimonio = ?";
$stmt_testimonio = $pdo->prepare($sql_testimonio);
$stmt_testimonio->execute([$id_testimonio]);
$testimonio = $stmt_testimonio->fetch(PDO::FETCH_ASSOC);

if (!$testimonio) {
    header('Location: testimonios.php');
    exit;
}

// Obtener lista de estudiantes becados
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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre_estudiante = $_POST['nombre_estudiante'];
        $id_estudiante = $_POST['id_estudiante'] ?: null;
        $testimonio_texto = $_POST['testimonio'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Validaciones
        if (empty($nombre_estudiante) || empty($testimonio_texto)) {
            throw new Exception('Por favor completa todos los campos obligatorios');
        }

        if (strlen($testimonio_texto) < 50) {
            throw new Exception('El testimonio debe tener al menos 50 caracteres');
        }

        $ruta_foto = $testimonio['Foto']; // Mantener foto actual por defecto

        // Procesar nueva foto si se subió
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['foto'];
            
            // Validar tipo
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $tipos_permitidos = ['jpg', 'jpeg', 'png'];
            
            if (!in_array($extension, $tipos_permitidos)) {
                throw new Exception('Solo se permiten archivos JPG, JPEG o PNG');
            }

            // Validar tamaño (máximo 2MB)
            if ($archivo['size'] > 2 * 1024 * 1024) {
                throw new Exception('La foto no debe superar los 2MB');
            }

            // Crear directorio si no existe
            $directorio = 'uploads/testimonios';
            if (!file_exists($directorio)) {
                mkdir($directorio, 0755, true);
            }

            // Generar nombre único
            $nombre_foto = 'testimonio_' . time() . '.' . $extension;
            $ruta_completa = $directorio . '/' . $nombre_foto;

            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                throw new Exception('Error al subir la foto');
            }

            // Eliminar foto anterior si existe
            if ($testimonio['Foto'] && file_exists($testimonio['Foto'])) {
                unlink($testimonio['Foto']);
            }

            $ruta_foto = $ruta_completa;
        }

        // Verificar si se debe eliminar la foto
        if (isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1') {
            if ($testimonio['Foto'] && file_exists($testimonio['Foto'])) {
                unlink($testimonio['Foto']);
            }
            $ruta_foto = null;
        }

        // Actualizar testimonio
        $sql = "UPDATE Testimonios 
                SET Id_Estudiante = ?,
                    Nombre_Estudiante = ?,
                    Testimonio = ?,
                    Foto = ?,
                    Activo = ?
                WHERE Id_Testimonio = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id_estudiante,
            $nombre_estudiante,
            $testimonio_texto,
            $ruta_foto,
            $activo,
            $id_testimonio
        ]);

        // Registrar en bitácora
        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                         VALUES (?, ?, CURDATE())";
        $actividad = "Editó testimonio de {$nombre_estudiante}";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $stmt_bitacora->execute([$user_id, $actividad]);

        $mensaje = '¡Testimonio actualizado exitosamente!';
        $tipo_mensaje = 'success';

        // Recargar datos del testimonio
        $stmt_testimonio->execute([$id_testimonio]);
        $testimonio = $stmt_testimonio->fetch(PDO::FETCH_ASSOC);

        // Redireccionar después de 2 segundos
        header('refresh:2;url=testimonios.php');

    } catch (PDOException $e) {
        $mensaje = 'Error al actualizar el testimonio: ' . $e->getMessage();
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
    <title>Editar Testimonio - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #003d82 0%, #002855 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo-container { margin-bottom: 15px; }
        .logo-container img { width: 180px; height: auto; }
        .sidebar-header h2 { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
        .sidebar-header p { font-size: 11px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; }
        
        .sidebar-menu { padding: 10px 0; }
        .menu-section { margin-bottom: 25px; }
        .menu-section-title { padding: 15px 20px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.6; font-weight: 600; }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 25px; }
        .menu-item.active { background: rgba(255,255,255,0.15); color: white; border-left: 4px solid #ffc107; }
        .menu-item i { width: 20px; margin-right: 12px; font-size: 16px; }
        
        .main-content { margin-left: 260px; flex: 1; min-height: 100vh; }
        
        .top-bar {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 { font-size: 28px; color: #2c3e50; font-weight: 600; }
        .page-title p { font-size: 14px; color: #7f8c8d; margin-top: 5px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-details { text-align: right; }
        .user-name { font-weight: 600; color: #2c3e50; font-size: 14px; }
        .user-role { font-size: 12px; color: #7f8c8d; }
        
        .container { padding: 30px 40px; }
        
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: #7f8c8d;
        }

        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: #2980b9;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .form-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-section {
            margin-bottom: 35px;
            padding-bottom: 35px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .form-section h2 {
            color: #2c3e50;
            font-size: 1.3em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
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
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
            color: #2c3e50;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
            line-height: 1.6;
        }

        .form-group .hint {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .char-counter {
            text-align: right;
            font-size: 13px;
            color: #7f8c8d;
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
            border-color: #3498db;
            background: #f0f8ff;
        }

        .file-upload-button i {
            font-size: 3em;
            color: #3498db;
            margin-bottom: 15px;
            display: block;
        }

        .current-photo {
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .current-photo img {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
            display: block;
            margin-bottom: 15px;
        }

        .current-photo .photo-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .file-preview {
            margin-top: 15px;
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .file-preview img {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
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
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .info-box {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .info-box strong {
            color: #0c5460;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .sidebar { width: 0; transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="logo.jpg" alt="Club Rotario Logo">
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">
                <a href="admin.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">SOLICITUDES</div>
                <a href="lista_solicitudes.php" class="menu-item">
                    <i class="fas fa-list"></i>
                    <span>Ver Solicitudes</span>
                </a>
                <a href="gestionar_preguntas.php" class="menu-item">
                    <i class="fas fa-question-circle"></i>
                    <span>Gestionar Preguntas</span>
                </a>
                <a href="gestionar_reglamento.php" class="menu-item">
                    <i class="fas fa-book"></i>
                    <span>Gestionar Reglamento</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">BECADOS</div>
                <a href="estudiantes_becados.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Ver Becados</span>
                </a>
                <a href="reactivar.php" class="menu-item">
                    <i class="fas fa-redo"></i>
                    <span>Reactivar Solicitudes</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">EVALUACIONES</div>
                <a href="evaluacion_anual.php" class="menu-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Evaluación Anual</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">EVENTOS</div>
                <a href="crear_evento.php" class="menu-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Crear Eventos</span>
                </a>
                <a href="ver_eventos.php" class="menu-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Ver Eventos</span>
                </a>
                <a href="revisar_inscrpciones.php" class="menu-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Revisar Inscripciones</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">REPORTES</div>
                <a href="reportes.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">PÚBLICO</div>
                <a href="testimonios.php" class="menu-item active">
                    <i class="fas fa-quote-right"></i>
                    <span>Testimonios</span>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Editar Testimonio</h1>
                <p>Actualiza la información del testimonio</p>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($username, 0, 2)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($username) ?></div>
                    <div class="user-role"><?= htmlspecialchars($role) ?></div>
                </div>
            </div>
        </div>
        
        <div class="container">
            <div class="breadcrumb">
                <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
                <a href="testimonios.php">Testimonios</a> /
                Editar
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <div><?= $mensaje ?></div>
            </div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" action="" enctype="multipart/form-data" id="formEditar">
                    <!-- Información del Estudiante -->
                    <div class="form-section">
                        <h2><i class="fas fa-user"></i> Información del Estudiante</h2>

                        <div class="info-box">
                            <strong>Nota:</strong> Puedes cambiar el estudiante seleccionado o modificar el nombre manualmente.
                        </div>

                        <div class="form-group">
                            <label>
                                Seleccionar Estudiante Becado
                            </label>
                            <select name="id_estudiante" id="selectEstudiante" onchange="llenarNombre()">
                                <option value="">-- Seleccionar de la lista (opcional) --</option>
                                <?php foreach ($estudiantes as $est): ?>
                                    <option value="<?= $est['Id_Estudiante'] ?>" 
                                            data-nombre="<?= htmlspecialchars($est['Nombres_Apellidos']) ?>"
                                            <?= $testimonio['Id_Estudiante'] == $est['Id_Estudiante'] ? 'selected' : '' ?>>
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
                                   value="<?= htmlspecialchars($testimonio['Nombre_Estudiante']) ?>"
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
                                      oninput="contarCaracteres()"><?= htmlspecialchars($testimonio['Testimonio']) ?></textarea>
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

                        <?php if ($testimonio['Foto'] && file_exists($testimonio['Foto'])): ?>
                        <div class="current-photo">
                            <strong style="display: block; margin-bottom: 10px; color: #2c3e50;">
                                <i class="fas fa-image"></i> Foto Actual:
                            </strong>
                            <img src="<?= htmlspecialchars($testimonio['Foto']) ?>" 
                                 alt="<?= htmlspecialchars($testimonio['Nombre_Estudiante']) ?>">
                            <div class="photo-actions">
                                <label class="checkbox-group" style="display: inline-flex; padding: 10px;">
                                    <input type="checkbox" 
                                           name="eliminar_foto" 
                                           value="1"
                                           id="eliminarFoto"
                                           onchange="toggleEliminarFoto()">
                                    <span>Eliminar foto actual</span>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>
                                <?= $testimonio['Foto'] ? 'Cambiar Foto (Opcional)' : 'Foto del Estudiante (Opcional)' ?>
                            </label>
                            <div class="file-upload">
                                <input type="file" 
                                       id="foto" 
                                       name="foto" 
                                       accept=".jpg,.jpeg,.png"
                                       onchange="previsualizarImagen(this)">
                                <label for="foto" class="file-upload-button" id="uploadButton">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div><strong>Click para seleccionar nueva foto</strong></div>
                                    <div style="font-size: 13px; margin-top: 10px; color: #7f8c8d;">
                                        JPG, JPEG o PNG - Máximo 2MB
                                    </div>
                                </label>
                            </div>
                            <div class="file-preview" id="preview">
                                <strong style="display: block; margin-bottom: 10px; color: #2c3e50;">
                                    <i class="fas fa-eye"></i> Vista Previa:
                                </strong>
                                <img id="previewImage" src="" alt="Preview">
                            </div>
                            <div class="hint">
                                <?= $testimonio['Foto'] ? 'Sube una nueva foto para reemplazar la actual' : 'Una foto profesional o académica del estudiante' ?>
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
                                       <?= $testimonio['Activo'] ? 'checked' : '' ?>>
                                <label for="activo">
                                    <strong>Publicar en el sitio web</strong> 
                                    (marcar para que sea visible públicamente)
                                </label>
                            </div>
                            <div class="hint">
                                Si no marcas esta opción, el testimonio se guardará como inactivo y no será visible públicamente
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Guardar Cambios
                        </button>
                        <a href="testimonios.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
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
            const eliminarCheckbox = document.getElementById('eliminarFoto');
            if (eliminarCheckbox) {
                eliminarCheckbox.checked = false;
            }

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('preview').style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        function toggleEliminarFoto() {
            const checkbox = document.getElementById('eliminarFoto');
            const fileInput = document.getElementById('foto');
            const uploadButton = document.getElementById('uploadButton');
            
            if (checkbox.checked) {
                fileInput.value = '';
                document.getElementById('preview').style.display = 'none';
                uploadButton.style.opacity = '0.5';
                uploadButton.style.pointerEvents = 'none';
            } else {
                uploadButton.style.opacity = '1';
                uploadButton.style.pointerEvents = 'auto';
            }
        }

        // Inicializar contador de caracteres al cargar
        document.addEventListener('DOMContentLoaded', function() {
            contarCaracteres();
        });
    </script>
</body>
</html>