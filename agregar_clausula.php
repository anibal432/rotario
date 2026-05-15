<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

// Obtener el próximo número de cláusula disponible
$stmt = $pdo->query("SELECT COALESCE(MAX(Numero_Clausula), 0) + 1 as ProximoNumero FROM Reglamento_Becas");
$proximo_numero = $stmt->fetch(PDO::FETCH_ASSOC)['ProximoNumero'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Cláusula - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px 40px;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            height: 60px;
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

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .form-card h3 {
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 1.4em;
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
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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
            background: white;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #004b87;
            box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
        }
        
        textarea { 
            resize: vertical; 
            min-height: 120px; 
            line-height: 1.5;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }
        
        .subcausulas-container {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 25px;
            margin-top: 20px;
            background: #f8f9fa;
            display: none;
            transition: all 0.3s ease;
        }
        
        .subcausulas-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .subcausula-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            position: relative;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .subcausula-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        
        .btn-remove-sub {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
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
            font-family: inherit;
        }
        
        .btn-success { 
            background: #28a745; 
            color: white; 
        }
        .btn-success:hover { 
            background: #218838; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        .btn-secondary:hover { 
            background: #5a6268; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        .btn-primary { 
            background: #004b87; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #0066b3; 
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 75, 135, 0.3);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }
        
        small {
            color: #6c757d;
            font-size: 12px;
            display: block;
            margin-top: 6px;
            font-style: italic;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        .alert-info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-left: 4px solid #17a2b8; 
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 250px;
                padding: 25px 30px;
            }
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
                height: auto;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .form-card {
                padding: 20px;
            }

            .subcausulas-container {
                padding: 15px;
            }

            .subcausula-item {
                padding: 15px;
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
                <h1>Agregar Nueva Cláusula</h1>
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

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Información:</strong> Completa el formulario para agregar una nueva cláusula al reglamento de becas. 
                    Los campos marcados con * son obligatorios.
                </div>
            </div>

            <form action="procesar_clausula.php" method="POST" id="formClausula">
                <input type="hidden" name="action" value="agregar">
                
                <div class="form-card">
                    <h3>
                        <i class="fas fa-file-alt"></i> Información de la Cláusula
                    </h3>
                    
                    <div class="form-group required">
                        <label for="numero_clausula">
                            <i class="fas fa-hashtag"></i> Número de Cláusula
                        </label>
                        <input type="number" id="numero_clausula" name="numero_clausula" 
                               value="<?= $proximo_numero ?>" min="1" required>
                        <small>Se sugiere el próximo número disponible</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="titulo_clausula">
                            <i class="fas fa-heading"></i> Título de la Cláusula (Opcional)
                        </label>
                        <input type="text" id="titulo_clausula" name="titulo_clausula" 
                               placeholder="Ej: Requisitos de Promedio">
                    </div>
                    
                    <div class="form-group required">
                        <label for="contenido_clausula">
                            <i class="fas fa-align-left"></i> Contenido de la Cláusula
                        </label>
                        <textarea id="contenido_clausula" name="contenido_clausula" required
                                  placeholder="Escribe el contenido completo de la cláusula..."></textarea>
                        <small>Puedes usar &lt;strong&gt;texto&lt;/strong&gt; para resaltar</small>
                    </div>
                    
                    <div class="form-group required">
                        <label for="tipo_clausula">
                            <i class="fas fa-tag"></i> Tipo de Cláusula
                        </label>
                        <select id="tipo_clausula" name="tipo_clausula" required>
                            <option value="">Selecciona un tipo...</option>
                            <option value="General">General</option>
                            <option value="Promedio">Promedio</option>
                            <option value="Pago">Pago</option>
                            <option value="Comportamiento">Comportamiento</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">
                            <i class="fas fa-toggle-on"></i> Estado
                        </label>
                        <select id="estado" name="estado">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="tiene_subcausulas" name="tiene_subcausulas" value="1"
                               onchange="toggleSubcausulas()">
                        <label for="tiene_subcausulas">
                            <i class="fas fa-list-ul"></i> Esta cláusula tiene subcláusulas (Ej: Primera vez, Segunda vez...)
                        </label>
                    </div>
                </div>
                
                <div class="subcausulas-container" id="subcausulasContainer">
                    <h4 style="margin-bottom: 20px; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list-ul"></i> Subcláusulas
                    </h4>
                    <div id="subcausulasLista"></div>
                    <button type="button" class="btn btn-primary" onclick="agregarSubcausula()">
                        <i class="fas fa-plus"></i> Agregar Subcláusula
                    </button>
                </div>
                
                <div class="form-actions">
                    <a href="gestionar_reglamento.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Cláusula
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        let subcausulaIndex = 0;
        
        function toggleSubcausulas() {
            const checkbox = document.getElementById('tiene_subcausulas');
            const container = document.getElementById('subcausulasContainer');
            
            if (checkbox.checked) {
                container.classList.add('active');
                if (subcausulaIndex === 0) {
                    agregarSubcausula();
                }
            } else {
                container.classList.remove('active');
                // Limpiar subcláusulas existentes
                document.getElementById('subcausulasLista').innerHTML = '';
                subcausulaIndex = 0;
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
                        <label><i class="fas fa-hashtag"></i> Número/Título de Subcláusula</label>
                        <input type="text" name="subcausulas[${index}][numero]" 
                               placeholder="Ej: Primera vez, Segunda vez, etc." required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Contenido de la Subcláusula</label>
                        <textarea name="subcausulas[${index}][contenido]" 
                                  placeholder="Contenido de la subcláusula..." required></textarea>
                    </div>
                    <input type="hidden" name="subcausulas[${index}][orden]" value="${index + 1}">
                </div>
            `;
            
            lista.insertAdjacentHTML('beforeend', html);
        }
        
        function eliminarSubcausula(index) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: '¿Quieres eliminar esta subcláusula?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const elemento = document.getElementById('subcausula_' + index);
                    if (elemento) {
                        elemento.remove();
                        // Reindexar las subcláusulas restantes si es necesario
                        const subcausulas = document.querySelectorAll('.subcausula-item');
                        if (subcausulas.length === 0) {
                            subcausulaIndex = 0;
                        }
                    }
                }
            });
        }
        
        // Validación del formulario
        document.getElementById('formClausula').addEventListener('submit', function(e) {
            const tieneSubcausulas = document.getElementById('tiene_subcausulas').checked;
            const numSubcausulas = document.querySelectorAll('.subcausula-item').length;
            
            if (tieneSubcausulas && numSubcausulas === 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error de validación',
                    text: 'Debes agregar al menos una subcláusula o desmarcar la opción "Tiene subcláusulas"',
                    icon: 'error',
                    confirmButtonColor: '#004b87'
                });
                return false;
            }
        });

        // Auto-cerrar alerta después de 5 segundos
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>