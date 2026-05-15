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

// Verificar permisos
$puede_aprobar = in_array($role, ['Administrador', 'Coordinador']);

// Obtener ID de evaluación
$id_evaluacion = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_evaluacion == 0) {
    header('Location: admin.php');
    exit;
}

include 'conexion.php';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener datos completos de la evaluación
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        est.Numero_Expediente,
        est.Nombres_Apellidos,
        est.Edad,
        est.Telefono,
        est.Email,
        est.Nombre_Madre,
        est.Nombre_Padre,
        est.Direccion_Domiciliar,
        est.Nombre_Encargado,
        est.Telefono_Encargado,
        est.Grado_Obtenido_Anterior,
        est.Escuela_Anterior,
        u.Nombre as Evaluador
    FROM Evaluaciones_Socioeconomicas e
    INNER JOIN Estudiantes est ON e.Id_Estudiante = est.Id_Estudiante
    LEFT JOIN Usuario u ON e.Id_Usuario_Evaluador = u.Id_Usuario
    WHERE e.Id_Evaluacion = :id_evaluacion
");

$stmt->execute([':id_evaluacion' => $id_evaluacion]);
$evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluacion) {
    header('Location: admin.php');
    exit;
}

// Obtener composición familiar
$stmt = $pdo->prepare("
    SELECT * FROM Composicion_Familiar 
    WHERE Id_Evaluacion = :id_evaluacion
    ORDER BY Id_Familiar
");
$stmt->execute([':id_evaluacion' => $id_evaluacion]);
$familia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Decodificar servicios básicos
$servicios = json_decode($evaluacion['Servicios_Basicos'], true) ?? [];

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
    <title>Ver Evaluación - Club Rotario</title>
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

        /* Sidebar */
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

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            transition: background 0.3s;
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
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
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header h1 {
            color: #004b87;
            font-size: 28px;
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

        /* Status Badge */
        .status-container {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 30px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pendiente {
            background-color: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .status-aprobado {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }

        .status-rechazado {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .expediente-badge {
            background: linear-gradient(135deg, #004b87, #0066b3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .card-title {
            color: #004b87;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            color: #666;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: #333;
            font-size: 16px;
        }

        .info-value.highlight {
            color: #004b87;
            font-weight: 600;
        }

        /* Tabla */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .data-table th {
            background-color: #f8f9fa;
            color: #004b87;
            font-weight: 600;
            font-size: 14px;
        }

        .data-table td {
            color: #333;
        }

        /* Servicios */
        .servicios-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .servicio-badge {
            background-color: #e3f2fd;
            color: #004b87;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Ensayo */
        .ensayo-content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #004b87;
            line-height: 1.8;
            color: #333;
        }

        /* Botones de acción */
        .action-section {
            background: linear-gradient(135deg, #004b87, #0066b3);
            border-radius: 12px;
            padding: 30px;
            color: white;
            margin-top: 20px;
        }

        .action-section h3 {
            margin-bottom: 20px;
            font-size: 20px;
        }

        .action-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 14px;
        }

        .form-group textarea {
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            resize: vertical;
            min-height: 100px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover:not(:disabled) {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background-color: white;
            color: #004b87;
        }

        .btn-secondary:hover {
            background-color: #f8f9fa;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            animation: slideDown 0.3s;
        }

        .alert.show {
            display: block;
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

        .no-permisos {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <li class="menu-item">
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
                    <i class="fas fa-file-alt"></i>
                    Evaluación Socioeconómica
                </h1>
                <div class="user-info">
                    <div class="user-avatar"><?= getInitials($username) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <div class="alert alert-success" id="alert-success"></div>
            <div class="alert alert-error" id="alert-error"></div>

            <!-- Status Container -->
            <div class="status-container">
                <div class="status-badge status-<?= strtolower($evaluacion['Estado_Evaluacion']) ?>">
                    <i class="fas fa-<?= $evaluacion['Estado_Evaluacion'] == 'Pendiente' ? 'clock' : ($evaluacion['Estado_Evaluacion'] == 'Aprobado' ? 'check-circle' : 'times-circle') ?>"></i>
                    <?= strtoupper($evaluacion['Estado_Evaluacion']) ?>
                </div>
                <?php if (!empty($evaluacion['Numero_Expediente'])): ?>
                    <div class="expediente-badge">
                        <i class="fas fa-folder"></i>
                        Expediente: <?= htmlspecialchars($evaluacion['Numero_Expediente']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECCIÓN 1: DATOS PERSONALES -->
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-user"></i>
                    I. Datos de Identificación
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre Completo</div>
                        <div class="info-value highlight"><?= htmlspecialchars($evaluacion['Nombres_Apellidos']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Edad</div>
                        <div class="info-value"><?= $evaluacion['Edad'] ?> años</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Telefono']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Email'] ?? 'No proporcionado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Madre</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Nombre_Madre']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Padre</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Nombre_Padre']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Dirección</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Direccion_Domiciliar']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Encargado(a)</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Nombre_Encargado']) ?> - <?= htmlspecialchars($evaluacion['Telefono_Encargado']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Grado Anterior</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Grado_Obtenido_Anterior']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Escuela Anterior</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Escuela_Anterior']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Fecha de Evaluación</div>
                        <div class="info-value"><?= date('d/m/Y', strtotime($evaluacion['Fecha_Evaluacion'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Evaluado por</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Evaluador'] ?? 'No especificado') ?></div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: INFORMACIÓN PERSONAL -->
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-graduation-cap"></i>
                    II. Información Personal
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Meta Profesional</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Meta_Profesional'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">¿Tiene otra beca?</div>
                        <div class="info-value"><?= $evaluacion['Otra_Beca'] ?></div>
                    </div>
                    <?php if ($evaluacion['Otra_Beca'] == 'SI'): ?>
                        <div class="info-item">
                            <div class="info-label">Institución</div>
                            <div class="info-value"><?= htmlspecialchars($evaluacion['Institucion_Beca'] ?? 'No especificado') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contacto</div>
                            <div class="info-value"><?= htmlspecialchars($evaluacion['Contacto_Institucion'] ?? 'No especificado') ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SECCIÓN 3: ASPECTO FAMILIAR -->
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-users"></i>
                    III. Aspecto Familiar
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Estado Civil Padres</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Estado_Civil_Padres']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Madre sabe leer</div>
                        <div class="info-value"><?= $evaluacion['Madre_Leer'] ?> - <?= htmlspecialchars($evaluacion['Madre_Grado_Educacion'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Padre sabe leer</div>
                        <div class="info-value"><?= $evaluacion['Padre_Leer'] ?> - <?= htmlspecialchars($evaluacion['Padre_Grado_Educacion'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Profesión Madre</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Profesion_Madre'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Profesión Padre</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Profesion_Padre'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Trabajo Madre</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Lugar_Trabajo_Madre'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Trabajo Padre</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Lugar_Trabajo_Padre'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">¿Cómo se enteró?</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Como_Se_Entero'] ?? 'No especificado') ?></div>
                    </div>
                </div>

                <?php if (!empty($familia)): ?>
                    <h3 style="color: #004b87; margin-top: 30px; margin-bottom: 15px;">
                        <i class="fas fa-home"></i> Composición Familiar
                    </h3>
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
                        <tbody>
                            <?php foreach ($familia as $fam): ?>
                                <tr>
                                    <td><?= htmlspecialchars($fam['Nombre_Apellidos']) ?></td>
                                    <td><?= $fam['Edad'] ?? '-' ?></td>
                                    <td><?= htmlspecialchars($fam['Parentesco'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($fam['Nivel_Educativo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($fam['Estado_Civil'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($fam['Ocupacion'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- SECCIÓN 4: ASPECTO SOCIOECONÓMICO -->
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-home"></i>
                    IV. Aspecto Socioeconómico
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Tipo de Vivienda</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Tipo_Vivienda']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Condiciones</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Condiciones_Vivienda']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Material</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Material_Vivienda']) ?></div>
                    </div>
                </div>

                <h3 style="color: #004b87; margin-top: 20px; margin-bottom: 10px;">
                    <i class="fas fa-plug"></i> Servicios Básicos
                </h3>
                <div class="servicios-list">
                    <?php if (!empty($servicios)): ?>
                        <?php foreach ($servicios as $servicio): ?>
                            <span class="servicio-badge">
                                <i class="fas fa-check-circle"></i>
                                <?= htmlspecialchars($servicio) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color: #666;">No se registraron servicios</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SECCIÓN 5: ENSAYO PERSONAL -->
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-pen"></i>
                    V. ¿Por qué necesita la beca?
                </h2>
                <div class="ensayo-content">
                    <?= nl2br(htmlspecialchars($evaluacion['Ensayo_Personal'] ?? 'No proporcionado')) ?>
                </div>
            </div>

            <!-- SECCIÓN 6: INFORMACIÓN DE LA ENTREVISTA -->
            <div class="content-card">
                <h2 class="card-title">
                    <i class="fas fa-clipboard-check"></i>
                    VI. Información de la Entrevista
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Socio Rotario</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Socio_Rotario'] ?? 'No especificado') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Firma del Socio</div>
                        <div class="info-value"><?= htmlspecialchars($evaluacion['Firma_Socio'] ?? 'No especificado') ?></div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN DE REVISIÓN Y APROBACIÓN -->
            <?php if ($evaluacion['Estado_Evaluacion'] == 'Pendiente' && $puede_aprobar): ?>
                <div class="action-section">
                    <h3>
                        <i class="fas fa-tasks"></i>
                        Revisión y Decisión
                    </h3>
                    <form id="form-decision">
                        <input type="hidden" name="id_evaluacion" value="<?= $id_evaluacion ?>">
                        
                        <div class="form-group">
                            <label>Comentarios u Observaciones</label>
                            <textarea name="comentarios" placeholder="Agregue sus comentarios sobre esta evaluación..."></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="button" class="btn btn-success" onclick="procesarDecision('Aprobado')">
                                <i class="fas fa-check"></i>
                                Aprobar
                            </button>
                            <button type="button" class="btn btn-danger" onclick="procesarDecision('Rechazado')">
                                <i class="fas fa-times"></i>
                                Rechazar
                            </button>
                            <a href="admin.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Volver
                            </a>
                        </div>
                    </form>
                </div>
            <?php elseif ($evaluacion['Estado_Evaluacion'] != 'Pendiente'): ?>
                <div class="content-card">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Estado de la Evaluación
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Estado</div>
                            <div class="info-value highlight"><?= $evaluacion['Estado_Evaluacion'] ?></div>
                        </div>
                        <?php if (!empty($evaluacion['Comentarios_Evaluacion'])): ?>
                            <div class="info-item" style="grid-column: 1 / -1;">
                                <div class="info-label">Comentarios</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($evaluacion['Comentarios_Evaluacion'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <a href="admin.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Volver al Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-permisos">
                    <i class="fas fa-lock"></i>
                    <p>No tiene permisos para aprobar o rechazar esta evaluación.</p>
                    <p>Solo los Administradores y Coordinadores pueden tomar esta decisión.</p>
                    <a href="admin.php" class="btn btn-secondary" style="margin-top: 15px;">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
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

        function showAlert(type, message) {
            const alertElement = document.getElementById('alert-' + type);
            alertElement.textContent = message;
            alertElement.classList.add('show');
            
            setTimeout(() => {
                alertElement.classList.remove('show');
            }, 5000);
        }

        function procesarDecision(decision) {
            const comentarios = document.querySelector('[name="comentarios"]').value;
            const idEvaluacion = document.querySelector('[name="id_evaluacion"]').value;
            
            const mensaje = decision === 'Aprobado' 
                ? '¿Está seguro que desea APROBAR esta evaluación?' 
                : '¿Está seguro que desea RECHAZAR esta evaluación?';
            
            if (!confirm(mensaje)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'procesar_decision');
            formData.append('id_evaluacion', idEvaluacion);
            formData.append('decision', decision);
            formData.append('comentarios', comentarios);
            
            // Deshabilitar botones mientras se procesa
            document.querySelectorAll('.action-buttons button').forEach(btn => {
                btn.disabled = true;
            });
            
            fetch('procesar_decision.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert('error', data.message);
                    document.querySelectorAll('.action-buttons button').forEach(btn => {
                        btn.disabled = false;
                    });
                }
            })
            .catch(error => {
                showAlert('error', 'Error al procesar la decisión: ' + error.message);
                console.error('Error:', error);
                document.querySelectorAll('.action-buttons button').forEach(btn => {
                    btn.disabled = false;
                });
            });
        }

        console.log('Ver Evaluación - Club Rotario');
        console.log('Usuario: <?= $username ?>');
        console.log('Rol: <?= $role ?>');
        console.log('Puede aprobar: <?= $puede_aprobar ? "Sí" : "No" ?>');
    </script>
</body>
</html>