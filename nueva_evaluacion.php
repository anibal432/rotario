<?php
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener información del usuario
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Usuario';
$user_id = $_SESSION['user_id'];

// Definir permisos por rol
$puede_crear = true; // Todos pueden crear evaluaciones
$puede_aprobar = in_array($role, ['Administrador', 'Coordinador']); // Solo Admin y Coordinador pueden aprobar

include 'conexion.php';

function getInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Evaluación - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background-color: #f5f5f5;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Mismo estilo del dashboard */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #004b87 0%, #003d6e 100%);
            color: white;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 0 20px;
        }

        .logo {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .club-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .club-location {
            font-size: 14px;
            opacity: 0.9;
        }

        .menu {
            list-style: none;
        }

        .menu-item {
            transition: background 0.3s;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #ffa500;
        }

        .logout-section {
            margin-top: 30px;
            padding: 0 30px;
        }

        .logout-btn {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 69, 0, 0.8);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 69, 0, 1);
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header h1 {
            color: #004b87;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
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
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            color: #004b87;
            font-weight: 600;
            font-size: 15px;
        }

        .user-role {
            color: #666;
            font-size: 13px;
        }

        /* Progress Bar */
        .progress-container {
            background: #e9ecef;
            height: 8px;
            border-radius: 10px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #004b87, #0066b3);
            transition: width 0.5s;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            background: linear-gradient(135deg, #004b87, #003d6e);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .section-header h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .section-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #004b87;
        }

        .required {
            color: #dc3545;
        }

        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus, 
        .form-group textarea:focus, 
        .form-group select:focus {
            outline: none;
            border-color: #004b87;
            box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
        }

        .date-inputs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .radio-group label, 
        .checkbox-group label {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            font-weight: normal;
            cursor: pointer;
        }

        .radio-group input, 
        .checkbox-group input {
            width: auto;
            margin-right: 8px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .data-table th, 
        .data-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .data-table th {
            background: #004b87;
            color: white;
        }

        .table-input {
            width: 100%;
            border: none;
            background: transparent;
            padding: 5px;
        }

        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #004b87;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #003d6e;
            transform: translateY(-2px);
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

        .btn-secondary:hover:not(:disabled) {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 99999;
            text-align: center;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #004b87;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
            animation: slideDown 0.3s;
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
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px;
            }
            
            .date-inputs {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-section">
                <div class="logo">🎓</div>
                <div class="club-name">Club Rotario</div>
                <div class="club-location">Coatepeque - Colomba</div>
            </div>
            <ul class="menu">
                <li class="menu-item">
                    <a href="dashboard.php">
                        <span>📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="aplicaciones.php">
                        <span>📋</span>
                        <span>Aplicaciones</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="nueva_evaluacion.php">
                        <span>📝</span>
                        <span>Nueva Evaluación</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="estudiantes.php">
                        <span>👥</span>
                        <span>Estudiantes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="ad21k.php">
                        <span>🏃</span>
                        <span>Carrera 21K</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reportes.php">
                        <span>📊</span>
                        <span>Reportes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="configuracion.php">
                        <span>⚙️</span>
                        <span>Configuración</span>
                    </a>
                </li>
            </ul>
            
            <div class="logout-section">
                <button class="logout-btn" onclick="cerrarSesion()">
                    🚪 Cerrar Sesión
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>
                    <i class="fas fa-file-medical"></i>
                    Nueva Evaluación Socioeconómica
                </h1>
                <div class="user-info">
                    <div class="user-avatar"><?= getInitials($username) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <div class="alert alert-success" id="alert-success"></div>
            <div class="alert alert-error" id="alert-error"></div>

            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar" id="progress-bar" style="width: 25%"></div>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <form id="evaluacion-form">
                    <!-- SECCIÓN 1: DATOS DE IDENTIFICACIÓN -->
                    <div class="form-section active" id="section-1">
                        <div class="section-header">
                            <h3>I. DATOS DE IDENTIFICACIÓN</h3>
                            <p>Información básica del estudiante</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Fecha <span class="required">*</span></label>
                            <div class="date-inputs">
                                <input type="number" name="dia" placeholder="Día" min="1" max="31" required>
                                <input type="number" name="mes" placeholder="Mes" min="1" max="12" required>
                                <input type="number" name="anio" placeholder="Año" value="<?= date('Y') ?>" min="2024" max="2030" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombres y Apellidos <span class="required">*</span></label>
                            <input type="text" name="nombres" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Edad <span class="required">*</span></label>
                            <input type="number" name="edad" min="5" max="30" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Teléfono <span class="required">*</span></label>
                            <input type="tel" name="telefono" placeholder="1234-5678" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="estudiante@ejemplo.com">
                        </div>
                        
                        <div class="form-group">
                            <label>Nombre de la Madre <span class="required">*</span></label>
                            <input type="text" name="madre" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombre del Padre <span class="required">*</span></label>
                            <input type="text" name="padre" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Dirección Domiciliar <span class="required">*</span></label>
                            <textarea name="direccion" rows="3" placeholder="Dirección completa" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombre del Encargado(a) <span class="required">*</span></label>
                            <input type="text" name="encargado" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Teléfono del Encargado(a) <span class="required">*</span></label>
                            <input type="tel" name="tel_encargado" placeholder="1234-5678" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Grado Obtenido el Año Anterior <span class="required">*</span></label>
                            <input type="text" name="grado_anterior" placeholder="Ej: 3ro Básico" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Escuela del Año Anterior <span class="required">*</span></label>
                            <input type="text" name="escuela_anterior" required>
                        </div>
                        
                        <div class="nav-buttons">
                            <div></div>
                            <button type="button" class="btn btn-primary" onclick="siguienteSeccion(2)">
                                Siguiente <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN 2: INFORMACIÓN PERSONAL -->
                    <div class="form-section" id="section-2">
                        <div class="section-header">
                            <h3>II. INFORMACIÓN PERSONAL</h3>
                            <p>Aspiraciones y situación actual del estudiante</p>
                        </div>
                        
                        <div class="form-group">
                            <label>¿Qué desea ser después de graduarse?</label>
                            <textarea name="meta_profesional" rows="3" placeholder="Describa sus metas profesionales"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>¿Está becado por otra institución? <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="otra_beca" value="SI" required> Sí</label>
                                <label><input type="radio" name="otra_beca" value="NO"> No</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nombre de la Institución (si aplica)</label>
                            <input type="text" name="institucion_beca">
                        </div>
                        
                        <div class="form-group">
                            <label>Contacto Institución</label>
                            <input type="text" name="contacto_institucion" placeholder="Teléfono o email">
                        </div>
                        
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" onclick="anteriorSeccion(1)">
                                <i class="fas fa-arrow-left"></i> Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="siguienteSeccion(3)">
                                Siguiente <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN 3: ASPECTO FAMILIAR -->
                    <div class="form-section" id="section-3">
                        <div class="section-header">
                            <h3>III. ASPECTO FAMILIAR</h3>
                            <p>Información sobre la familia del estudiante</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Estado Civil de los Padres <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="estado_padres" value="Casados" required> Casados</label>
                                <label><input type="radio" name="estado_padres" value="Divorciados"> Divorciados</label>
                                <label><input type="radio" name="estado_padres" value="Viudo"> Viudo(a)</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>¿Su mamá sabe leer y escribir? <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="madre_leer" value="SI" required> Sí</label>
                                <label><input type="radio" name="madre_leer" value="NO"> No</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Grado Educativo de la Madre</label>
                            <input type="text" name="madre_educacion" placeholder="Ej: Primaria completa">
                        </div>
                        
                        <div class="form-group">
                            <label>¿Su papá sabe leer y escribir? <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="padre_leer" value="SI" required> Sí</label>
                                <label><input type="radio" name="padre_leer" value="NO"> No</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Grado Educativo del Padre</label>
                            <input type="text" name="padre_educacion" placeholder="Ej: Básicos completos">
                        </div>
                        
                        <div class="form-group">
                            <label>Profesión/Oficio de la Madre</label>
                            <input type="text" name="profesion_madre">
                        </div>
                        
                        <div class="form-group">
                            <label>Profesión/Oficio del Padre</label>
                            <input type="text" name="profesion_padre">
                        </div>
                        
                        <div class="form-group">
                            <label>Lugar de Trabajo de la Madre</label>
                            <input type="text" name="trabajo_madre">
                        </div>
                        
                        <div class="form-group">
                            <label>Lugar de Trabajo del Padre</label>
                            <input type="text" name="trabajo_padre">
                        </div>
                        
                        <div class="form-group">
                            <label>¿Cómo se enteró del programa de becas?</label>
                            <textarea name="como_enterado" rows="3" placeholder="Radio, televisión, familiar, amigo, etc."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Composición Familiar</label>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Edad</th>
                                        <th>Parentesco</th>
                                        <th>Educación</th>
                                        <th>Estado Civil</th>
                                        <th>Ocupación</th>
                                    </tr>
                                </thead>
                                <tbody id="familia-tbody">
                                    <tr>
                                        <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                                        <td><input type="number" name="fam_edad[]" class="table-input"></td>
                                        <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                                        <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                                        <td><input type="text" name="fam_civil[]" class="table-input"></td>
                                        <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                                        <td><input type="number" name="fam_edad[]" class="table-input"></td>
                                        <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                                        <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                                        <td><input type="text" name="fam_civil[]" class="table-input"></td>
                                        <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                                    </tr>
                                    <tr>
                                        <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                                        <td><input type="number" name="fam_edad[]" class="table-input"></td>
                                        <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                                        <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                                        <td><input type="text" name="fam_civil[]" class="table-input"></td>
                                        <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" onclick="anteriorSeccion(2)">
                                <i class="fas fa-arrow-left"></i> Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="siguienteSeccion(4)">
                                Siguiente <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN 4: ASPECTO SOCIOECONÓMICO -->
                    <div class="form-section" id="section-4">
                        <div class="section-header">
                            <h3>IV. ASPECTO SOCIOECONÓMICO</h3>
                            <p>Condiciones de vivienda y situación económica</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Tipo de Vivienda <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="tipo_vivienda" value="Casa" required> Casa</label>
                                <label><input type="radio" name="tipo_vivienda" value="Apartamento"> Apartamento</label>
                                <label><input type="radio" name="tipo_vivienda" value="Otro"> Otro</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Condiciones de la Vivienda <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="condiciones_vivienda" value="Excelente" required> Excelente</label>
                                <label><input type="radio" name="condiciones_vivienda" value="Buena"> Buena</label>
                                <label><input type="radio" name="condiciones_vivienda" value="Regular"> Regular</label>
                                <label><input type="radio" name="condiciones_vivienda" value="Mala"> Mala</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Material de la Vivienda <span class="required">*</span></label>
                            <div class="radio-group">
                                <label><input type="radio" name="material_vivienda" value="Ladrillo" required> Ladrillo</label>
                                <label><input type="radio" name="material_vivienda" value="Block"> Block</label>
                                <label><input type="radio" name="material_vivienda" value="Adobe"> Adobe</label>
                                <label><input type="radio" name="material_vivienda" value="Madera"> Madera</label>
                                <label><input type="radio" name="material_vivienda" value="Mixto"> Mixto</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Servicios Básicos</label>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="servicios[]" value="Agua"> Agua</label>
                                <label><input type="checkbox" name="servicios[]" value="Luz"> Luz</label>
                                <label><input type="checkbox" name="servicios[]" value="Drenaje"> Drenaje</label>
                                <label><input type="checkbox" name="servicios[]" value="Internet"> Internet</label>
                                <label><input type="checkbox" name="servicios[]" value="Telefono"> Teléfono</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>¿Por qué necesita la beca? <span class="required">*</span></label>
                            <textarea name="ensayo_personal" rows="6" placeholder="Explique su situación económica y por qué necesita apoyo para continuar sus estudios" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Socio Rotario que realiza la entrevista <span class="required">*</span></label>
                            <input type="text" name="socio_rotario" value="<?= htmlspecialchars($username) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Firma del Socio <span class="required">*</span></label>
                            <input type="text" name="firma_socio" placeholder="Nombre completo para firma digital" required>
                        </div>
                        
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" onclick="anteriorSeccion(3)">
                                <i class="fas fa-arrow-left"></i> Anterior
                            </button>
                            <button type="submit" class="btn btn-success" id="btn-submit">
                                <i class="fas fa-save"></i> Guardar Evaluación
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <p>Procesando evaluación...</p>
    </div>

    <script>
        let currentSection = 1;
        const totalSections = 4;

        // Actualizar barra de progreso
        function updateProgress() {
            const progress = (currentSection / totalSections) * 100;
            document.getElementById('progress-bar').style.width = progress + '%';
        }

        // Cambiar a la siguiente sección
        function siguienteSeccion(seccion) {
            // Validar campos requeridos de la sección actual
            const currentForm = document.getElementById('section-' + currentSection);
            const inputs = currentForm.querySelectorAll('[required]');
            let valid = true;

            inputs.forEach(input => {
                if (input.type === 'radio') {
                    const radioGroup = currentForm.querySelectorAll(`[name="${input.name}"]`);
                    const checked = Array.from(radioGroup).some(radio => radio.checked);
                    if (!checked) valid = false;
                } else if (!input.value.trim()) {
                    valid = false;
                    input.style.borderColor = '#dc3545';
                } else {
                    input.style.borderColor = '#ddd';
                }
            });

            if (!valid) {
                showAlert('error', 'Por favor complete todos los campos requeridos antes de continuar');
                return;
            }

            document.getElementById('section-' + currentSection).classList.remove('active');
            document.getElementById('section-' + seccion).classList.add('active');
            currentSection = seccion;
            updateProgress();
            window.scrollTo(0, 0);
        }

        // Volver a sección anterior
        function anteriorSeccion(seccion) {
            document.getElementById('section-' + currentSection).classList.remove('active');
            document.getElementById('section-' + seccion).classList.add('active');
            currentSection = seccion;
            updateProgress();
            window.scrollTo(0, 0);
        }

        // Mostrar alertas
        function showAlert(type, message) {
            const alertElement = document.getElementById('alert-' + type);
            alertElement.textContent = message;
            alertElement.classList.add('show');
            
            setTimeout(() => {
                alertElement.classList.remove('show');
            }, 5000);
        }

        // Cerrar sesión
        function cerrarSesion() {
            if (confirm('¿Está seguro que desea cerrar sesión?')) {
                const formData = new FormData();
                formData.append('action', 'logout');
                
                fetch('auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    window.location.href = 'login.php';
                })
                .catch(error => {
                    console.error('Error:', error);
                    window.location.href = 'login.php';
                });
            }
        }

        // Enviar formulario
        document.getElementById('evaluacion-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Mostrar loading
            document.getElementById('loading').classList.add('active');
            document.getElementById('btn-submit').disabled = true;
            
            // Recopilar datos del formulario
            const formData = new FormData(this);
            formData.append('action', 'guardar_evaluacion');
            formData.append('user_id', '<?= $user_id ?>');
            
            // Enviar al servidor
            fetch('procesar_evaluacion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                document.getElementById('btn-submit').disabled = false;
                
                if (data.success) {
                    showAlert('success', data.message);
                    
                    // Redirigir después de 2 segundos
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('active');
                document.getElementById('btn-submit').disabled = false;
                showAlert('error', 'Error al procesar la evaluación: ' + error.message);
                console.error('Error:', error);
            });
        });

        // Validación en tiempo real
        document.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#ddd';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            });
        });

        console.log('Sistema de Evaluación - Club Rotario');
        console.log('Usuario: <?= $username ?>');
        console.log('Rol: <?= $role ?>');
        console.log('Puede aprobar: <?= $puede_aprobar ? "Sí" : "No" ?>');
    </script>
</body>
</html>