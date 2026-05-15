<?php
// admin/aplicaciones.php - Gestión de aplicaciones de becas
session_start();
require_once 'conexion.php';

// Verificar autenticación
// if (!isset($_SESSION['usuario_id'])) {
//     header('Location: login.php');
//     exit;
// }

// Obtener información del usuario
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';
$user_id = $_SESSION['user_id'] ?? 1;

// Función para obtener las iniciales del usuario
function getInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Procesar búsqueda
$search_nombre = $_GET['search_nombre'] ?? '';
$search_estado = $_GET['search_estado'] ?? '';
$search_anio = $_GET['search_anio'] ?? date('Y');

// Construir consulta con filtros
$sql = "
    SELECT 
        e.Id_Evaluacion,
        e.Fecha_Evaluacion,
        est.Nombres_Apellidos,
        est.Edad,
        est.Telefono,
        est.Email,
        est.Grado_Obtenido_Anterior,
        e.Estado_Evaluacion,
        e.Fecha_Decision
    FROM Evaluaciones_Socioeconomicas e
    INNER JOIN Estudiantes est ON e.Id_Estudiante = est.Id_Estudiante
    WHERE 1=1
";

$params = [];

if (!empty($search_nombre)) {
    $sql .= " AND est.Nombres_Apellidos LIKE ?";
    $params[] = "%$search_nombre%";
}

if (!empty($search_estado)) {
    $sql .= " AND e.Estado_Evaluacion = ?";
    $params[] = $search_estado;
}

if (!empty($search_anio)) {
    $sql .= " AND YEAR(e.Fecha_Evaluacion) = ?";
    $params[] = $search_anio;
}

$sql .= " ORDER BY e.Fecha_Evaluacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$aplicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener años disponibles para el filtro
$sql_anios = "SELECT DISTINCT YEAR(Fecha_Evaluacion) as anio FROM Evaluaciones_Socioeconomicas ORDER BY anio DESC";
$stmt_anios = $pdo->query($sql_anios);
$anios = $stmt_anios->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplicaciones - Club Rotario</title>
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

        .menu-icon {
            font-size: 18px;
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

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .search-title {
            font-size: 18px;
            color: #004b87;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #004b87;
            box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, #004b87, #0066b3);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 75, 135, 0.3);
        }

        .btn-clear {
            background: #6b7280;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-clear:hover {
            background: #4b5563;
        }

        /* Applications Section */
        .applications-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            margin-bottom: 25px;
        }

        .section-header h2 {
            font-size: 24px;
            color: #004b87;
            margin-bottom: 5px;
        }

        .section-header p {
            color: #666;
            font-size: 14px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-mini {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 3px solid #004b87;
        }

        .stat-mini .number {
            font-size: 24px;
            font-weight: 700;
            color: #004b87;
            margin-bottom: 5px;
        }

        .stat-mini .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        thead {
            background-color: #004b87;
            color: white;
        }

        th {
            padding: 15px 18px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 18px;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            background: white;
            transition: background 0.2s;
        }

        tbody tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-badge.pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.aprobado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rechazado {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 15px;
            transition: all 0.2s;
            font-weight: bold;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-view {
            background-color: #004b87;
        }

        .btn-view:hover {
            background-color: #003d6e;
        }

        .btn-approve {
            background-color: #28a745;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-reject {
            background-color: #dc3545;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }

        .btn-edit {
            background-color: #ff9800;
        }

        .btn-edit:hover {
            background-color: #e68900;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .no-data p {
            font-size: 16px;
        }

        @media (max-width: 1024px) {
            .search-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
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
                    <a href="admin.php">
                        <span class="menu-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="aplicaciones.php">
                        <span class="menu-icon">📋</span>
                        <span>Aplicaciones</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="nueva_evaluacion.php">
                        <span class="menu-icon">📝</span>
                        <span>Nueva Evaluación</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="estudiantes.php">
                        <span class="menu-icon">👥</span>
                        <span>Estudiantes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="ad21k.php">
                        <span class="menu-icon">🏃</span>
                        <span>Carrera 21K</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="Crear_Evento.php">
                        <span class="menu-icon">📅</span>
                        <span>Crear Eventos</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reportes.php">
                        <span class="menu-icon">📊</span>
                        <span>Reportes</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="configuracion.php">
                        <span class="menu-icon">⚙️</span>
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
                <h1>Aplicaciones de Becas</h1>
                <div class="user-info">
                    <div class="user-avatar"><?= getInitials($username) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <div class="search-title">🔍 Buscar Solicitudes</div>
                <form class="search-form" method="GET" action="">
                    <div class="form-group">
                        <label>Nombre del Estudiante</label>
                        <input type="text" name="search_nombre" 
                               placeholder="Buscar por nombre..." 
                               value="<?= htmlspecialchars($search_nombre) ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="search_estado">
                            <option value="">Todos</option>
                            <option value="Pendiente" <?= $search_estado == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="Aprobado" <?= $search_estado == 'Aprobado' ? 'selected' : '' ?>>Aprobado</option>
                            <option value="Rechazado" <?= $search_estado == 'Rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Año</label>
                        <select name="search_anio">
                            <?php foreach ($anios as $anio): ?>
                                <option value="<?= $anio ?>" <?= $search_anio == $anio ? 'selected' : '' ?>>
                                    <?= $anio ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-search">
                        🔍 Buscar
                    </button>
                    <a href="aplicaciones.php" class="btn-clear">Limpiar</a>
                </form>
            </div>

            <!-- Applications Section -->
            <div class="applications-section">
                <div class="section-header">
                    <h2>Todas las Solicitudes</h2>
                    <p>Gestión completa de evaluaciones socioeconómicas</p>
                </div>

                <!-- Mini Stats -->
                <?php
                $total = count($aplicaciones);
                $pendientes = count(array_filter($aplicaciones, fn($a) => $a['Estado_Evaluacion'] == 'Pendiente'));
                $aprobados = count(array_filter($aplicaciones, fn($a) => $a['Estado_Evaluacion'] == 'Aprobado'));
                $rechazados = count(array_filter($aplicaciones, fn($a) => $a['Estado_Evaluacion'] == 'Rechazado'));
                ?>
                <div class="stats-row">
                    <div class="stat-mini">
                        <div class="number"><?= $total ?></div>
                        <div class="label">Total</div>
                    </div>
                    <div class="stat-mini">
                        <div class="number"><?= $pendientes ?></div>
                        <div class="label">Pendientes</div>
                    </div>
                    <div class="stat-mini">
                        <div class="number"><?= $aprobados ?></div>
                        <div class="label">Aprobados</div>
                    </div>
                    <div class="stat-mini">
                        <div class="number"><?= $rechazados ?></div>
                        <div class="label">Rechazados</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <?php if (empty($aplicaciones)): ?>
                        <div class="no-data">
                            <div style="font-size: 48px; margin-bottom: 15px;">📋</div>
                            <p>No se encontraron solicitudes con los criterios de búsqueda</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Estudiante</th>
                                    <th>Edad</th>
                                    <th>Teléfono</th>
                                    <th>Grado Anterior</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aplicaciones as $app): ?>
                                    <tr>
                                        <td>#<?= str_pad($app['Id_Evaluacion'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= date('d/m/Y', strtotime($app['Fecha_Evaluacion'])) ?></td>
                                        <td><?= htmlspecialchars($app['Nombres_Apellidos']) ?></td>
                                        <td><?= $app['Edad'] ?> años</td>
                                        <td><?= htmlspecialchars($app['Telefono']) ?></td>
                                        <td><?= htmlspecialchars($app['Grado_Obtenido_Anterior']) ?></td>
                                        <td>
                                            <span class="status-badge <?= strtolower($app['Estado_Evaluacion']) ?>">
                                                <?= strtoupper($app['Estado_Evaluacion']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" 
                                                        onclick="verDetalle(<?= $app['Id_Evaluacion'] ?>)" 
                                                        title="Ver detalles">👁</button>
                                                <?php if ($app['Estado_Evaluacion'] == 'Pendiente'): ?>
                                                    <button class="btn-action btn-approve" 
                                                            onclick="aprobarSolicitud(<?= $app['Id_Evaluacion'] ?>, '<?= htmlspecialchars($app['Nombres_Apellidos']) ?>')" 
                                                            title="Aprobar">✓</button>
                                                    <button class="btn-action btn-reject" 
                                                            onclick="rechazarSolicitud(<?= $app['Id_Evaluacion'] ?>, '<?= htmlspecialchars($app['Nombres_Apellidos']) ?>')" 
                                                            title="Rechazar">✕</button>
                                                <?php else: ?>
                                                    <button class="btn-action btn-edit" 
                                                            onclick="editarSolicitud(<?= $app['Id_Evaluacion'] ?>)" 
                                                            title="Editar">✏️</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function verDetalle(id) {
            window.location.href = 'ver_evaluacion.php?id=' + id;
        }

        function aprobarSolicitud(id, nombre) {
            if (confirm('¿Está seguro de aprobar la solicitud de ' + nombre + '?')) {
                window.location.href = 'aprobar_evaluacion.php?id=' + id;
            }
        }

        function rechazarSolicitud(id, nombre) {
            const motivo = prompt('Ingrese el motivo del rechazo para ' + nombre + ':');
            if (motivo && motivo.trim()) {
                window.location.href = 'rechazar_evaluacion.php?id=' + id + '&motivo=' + encodeURIComponent(motivo);
            }
        }

        function editarSolicitud(id) {
            window.location.href = 'editar_evaluacion.php?id=' + id;
        }

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

        // Mensaje informativo
        console.log('Sistema de Aplicaciones cargado');
        console.log('Total de solicitudes: <?= $total ?>');
    </script>
</body>
</html>