<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_clausula = $_GET['id'] ?? null;

if (!$id_clausula) {
    $_SESSION['error'] = "ID de cláusula no especificado";
    header('Location: gestionar_reglamento.php');
    exit;
}

// Obtener la cláusula
$stmt = $pdo->prepare("SELECT * FROM Reglamento_Becas WHERE Id_Clausula = ?");
$stmt->execute([$id_clausula]);
$clausula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$clausula) {
    $_SESSION['error'] = "Cláusula no encontrada";
    header('Location: gestionar_reglamento.php');
    exit;
}

// Obtener subcláusulas si existen
$subcausulas = [];
if ($clausula['Tiene_Subcausulas']) {
    $stmt_sub = $pdo->prepare("SELECT * FROM Sub_Clausulas_Reglamento WHERE Id_Clausula = ? ORDER BY Orden");
    $stmt_sub->execute([$id_clausula]);
    $subcausulas = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cláusula - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reglamento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            width: 100%;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px 40px;
            width: calc(100% - 280px);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #004b87, #ffa500);
        }

        .header h1 {
            color: #004b87;
            font-size: 28px;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .user-info:hover {
            transform: translateY(-2px);
        }

        .user-avatar-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-role-icon {
            font-size: 1.2em;
            color: #004b87;
        }

        .user-avatar-main {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #004b87, #0066b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 8px rgba(0, 75, 135, 0.3);
        }

        .user-details-main {
            display: flex;
            flex-direction: column;
        }

        .user-name-main {
            color: #004b87;
            font-weight: 600;
            font-size: 15px;
        }

        .user-role-main {
            color: #666;
            font-size: 13px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-left: 4px solid #17a2b8; 
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i { 
            font-size: 22px; 
            color: #ffc107; 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-group.required label::after { 
            content: ' *'; 
            color: #dc3545; 
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }
        
        textarea { 
            resize: vertical; 
            min-height: 120px; 
        }
        
        small { 
            color: #7f8c8d; 
            font-size: 12px; 
            margin-top: 5px; 
            display: block; 
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] { 
            width: auto; 
            cursor: pointer; 
        }
        
        .subcausulas-container {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            background: #f8f9fa;
            display: none;
        }
        
        .subcausulas-container.active { 
            display: block; 
        }
        
        .subcausula-item {
            background: white;
            padding: 20px;
            padding-top: 35px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            border: 1px solid #e9ecef;
        }
        
        .btn-remove-sub {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-remove-sub:hover { 
            background: #c82333; 
            transform: scale(1.05); 
        }
        
        .btn {
            padding: 12px 24px;
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
        
        .btn:disabled { 
            opacity: 0.6; 
            cursor: not-allowed; 
        }
        
        .btn-success { 
            background: #28a745; 
            color: white; 
        }
        
        .btn-success:hover:not(:disabled) { 
            background: #218838; 
            transform: translateY(-2px); 
        }
        
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        
        .btn-secondary:hover { 
            background: #5a6268; 
            transform: translateY(-2px); 
        }
        
        .btn-add {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            margin-top: 15px;
        }
        
        .btn-add:hover {
            background: #667eea;
            color: white;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .main-content { 
                margin-left: 0; 
                padding: 20px; 
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Editar Cláusula #<?= $clausula['Numero_Clausula'] ?></h1>
                <div class="user-info">
                    <div class="user-avatar-wrapper">
                        <?php
                            $iconClass = '';
                            switch ($role) {
                                case 'Administrador':
                                    $iconClass = 'fa-solid fa-crown';
                                    break;
                                case 'Coordinador':
                                    $iconClass = 'fa-solid fa-user-tie';
                                    break;
                                default:
                                    $iconClass = 'fa-solid fa-user';
                                    break;
                            }
                        ?>
                        <i class="<?= $iconClass ?> user-role-icon"></i>
                        <div class="user-avatar-main"><?= getInitials($username) ?></div>
                    </div>
                    <div class="user-details-main">
                        <div class="user-name-main"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role-main"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= $_SESSION['success'] ?></span>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Editando Cláusula:</strong> Los cambios se guardarán y se reflejarán automáticamente en las Cartas de Compromiso.
                </div>
            </div>

            <form action="procesar_clausula.php" method="POST" id="formClausula">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id_clausula" value="<?= $id_clausula ?>">
                
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-file-alt"></i> Información de la Cláusula
                    </h3>
                    
                    <div class="form-group required">
                        <label for="numero_clausula">Número de Cláusula</label>
                        <input type="number" id="numero_clausula" name="numero_clausula" 
                               value="<?= $clausula['Numero_Clausula'] ?>" min="1" required>
                        <small>El número de orden de esta cláusula en el reglamento</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="titulo_clausula">Título de la Cláusula (Opcional)</label>
                        <input type="text" id="titulo_clausula" name="titulo_clausula" 
                               value="<?= htmlspecialchars($clausula['Titulo_Clausula'] ?? '') ?>"
                               placeholder="Ej: Requisitos de Promedio">
                    </div>
                    
                    <div class="form-group required">
                        <label for="contenido_clausula">Contenido de la Cláusula</label>
                        <textarea id="contenido_clausula" name="contenido_clausula" required
                                  placeholder="Escribe el contenido completo de la cláusula..."><?= htmlspecialchars($clausula['Contenido_Clausula']) ?></textarea>
                        <small>Puedes usar &lt;strong&gt;texto&lt;/strong&gt; para resaltar</small>
                    </div>
                    
                    <div class="form-group required">
                        <label for="tipo_clausula">Tipo de Cláusula</label>
                        <select id="tipo_clausula" name="tipo_clausula" required>
                            <option value="">Selecciona un tipo...</option>
                            <option value="General" <?= $clausula['Tipo_Clausula'] == 'General' ? 'selected' : '' ?>>General</option>
                            <option value="Promedio" <?= $clausula['Tipo_Clausula'] == 'Promedio' ? 'selected' : '' ?>>Promedio</option>
                            <option value="Pago" <?= $clausula['Tipo_Clausula'] == 'Pago' ? 'selected' : '' ?>>Pago</option>
                            <option value="Comportamiento" <?= $clausula['Tipo_Clausula'] == 'Comportamiento' ? 'selected' : '' ?>>Comportamiento</option>
                            <option value="Otro" <?= $clausula['Tipo_Clausula'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="Activo" <?= $clausula['Estado'] == 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Inactivo" <?= $clausula['Estado'] == 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                        <small>Solo las cláusulas activas aparecen en la Carta de Compromiso</small>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="tiene_subcausulas" name="tiene_subcausulas" value="1"
                               <?= $clausula['Tiene_Subcausulas'] ? 'checked' : '' ?> onchange="toggleSubcausulas()">
                        <label for="tiene_subcausulas" style="margin: 0; font-weight: normal;">
                            Esta cláusula tiene subcláusulas (Ej: Primera vez, Segunda vez...)
                        </label>
                    </div>
                </div>
                
                <div class="subcausulas-container <?= $clausula['Tiene_Subcausulas'] ? 'active' : '' ?>" id="subcausulasContainer">
                    <h4 style="margin-bottom: 15px; color: #2c3e50;">
                        <i class="fas fa-list-ul"></i> Subcláusulas
                    </h4>
                    <div id="subcausulasLista">
                        <?php foreach ($subcausulas as $index => $sub): ?>
                        <div class="subcausula-item" id="subcausula_<?= $index ?>">
                            <button type="button" class="btn-remove-sub" onclick="eliminarSubcausula(<?= $index ?>)">
                                <i class="fas fa-times"></i> Eliminar
                            </button>
                            <div class="form-group">
                                <label>Número/Título de Subcláusula</label>
                                <input type="text" name="subcausulas[<?= $index ?>][numero]" 
                                       value="<?= htmlspecialchars($sub['Numero_Sub_Clausula']) ?>" 
                                       placeholder="Ej: Primera vez, Segunda vez, etc." required>
                            </div>
                            <div class="form-group">
                                <label>Contenido de la Subcláusula</label>
                                <textarea name="subcausulas[<?= $index ?>][contenido]" 
                                          placeholder="Contenido de la subcláusula..." required><?= htmlspecialchars($sub['Contenido']) ?></textarea>
                            </div>
                            <input type="hidden" name="subcausulas[<?= $index ?>][orden]" value="<?= $sub['Orden'] ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="agregarSubcausula()">
                        <i class="fas fa-plus"></i> Agregar Subcláusula
                    </button>
                </div>
                
                <div class="form-actions">
                    <a href="gestionar_reglamento.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        let subcausulaIndex = <?= count($subcausulas) ?>;
        
        function toggleSubcausulas() {
            const container = document.getElementById('subcausulasContainer');
            const checkbox = document.getElementById('tiene_subcausulas');
            if (checkbox.checked) {
                container.classList.add('active');
            } else {
                container.classList.remove('active');
            }
        }
        
        function agregarSubcausula() {
            const lista = document.getElementById('subcausulasLista');
            const index = subcausulaIndex++;
            const html = `
                <div class="subcausula-item" id="subcausula_${index}">
                    <button type="button" class="btn-remove-sub" onclick="eliminarSubcausula(${index})">
                        <i class="fas fa-times"></i> Eliminar
                    </button>
                    <div class="form-group">
                        <label>Número/Título de Subcláusula</label>
                        <input type="text" name="subcausulas[${index}][numero]" 
                               placeholder="Ej: Primera vez, Segunda vez, etc." required>
                    </div>
                    <div class="form-group">
                        <label>Contenido de la Subcláusula</label>
                        <textarea name="subcausulas[${index}][contenido]" 
                                  placeholder="Contenido de la subcláusula..." required></textarea>
                    </div>
                    <input type="hidden" name="subcausulas[${index}][orden]" value="${index + 1}">
                </div>
            `;
            lista.insertAdjacentHTML('beforeend', html);
        }
        
        function eliminarSubcausula(index) {
            const elemento = document.getElementById('subcausula_' + index);
            if (elemento) {
                elemento.remove();
            }
        }
        
        // Validación del formulario con SweetAlert
        document.getElementById('formClausula').addEventListener('submit', function(e) {
            const tieneSubcausulas = document.getElementById('tiene_subcausulas').checked;
            const numSubcausulas = document.querySelectorAll('.subcausula-item').length;
            
            if (tieneSubcausulas && numSubcausulas === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Subcláusulas requeridas',
                    text: 'Debes agregar al menos una subcláusula o desmarcar la opción "Tiene subcláusulas"',
                    confirmButtonColor: '#ffc107',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
        });

        // Mostrar SweetAlert si hay mensajes de sesión
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($_SESSION['error']) ?>',
                confirmButtonColor: '#dc3545'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '<?= addslashes($_SESSION['success']) ?>',
                confirmButtonColor: '#28a745',
                timer: 3000
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </script>
</body>
</html>