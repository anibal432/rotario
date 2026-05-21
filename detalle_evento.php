<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();


require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';
$id_evento = $_GET['id'] ?? null;

if (!$id_evento) {
    header('Location: ver_eventos.php');
    exit;
}

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
            }
        }
        return substr($initials, 0, 2);
    }
}

// Obtener información completa del evento
$sql = "SELECT e.*, te.Nombre as Tipo_Evento_Nombre
        FROM Eventos e
        LEFT JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
        WHERE e.Id_Evento = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_evento]);
$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    header('Location: ver_eventos.php');
    exit;
}

// Obtener categorías del evento
$sql_categorias = "SELECT * FROM Categorias_Evento WHERE Id_Evento = ? ORDER BY Nombre_Categoria";
$stmt_cat = $pdo->prepare($sql_categorias);
$stmt_cat->execute([$id_evento]);
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Obtener costos de inscripción
$sql_costos = "SELECT * FROM Costos_Inscripcion WHERE Id_Evento = ? ORDER BY Fecha_Inicio";
$stmt_cos = $pdo->prepare($sql_costos);
$stmt_cos->execute([$id_evento]);
$costos = $stmt_cos->fetchAll(PDO::FETCH_ASSOC);

// Obtener cuentas bancarias
$sql_cuentas = "SELECT * FROM Cuentas_Bancarias WHERE Id_Evento = ?";
$stmt_cue = $pdo->prepare($sql_cuentas);
$stmt_cue->execute([$id_evento]);
$cuentas = $stmt_cue->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del evento
$sql_stats = "SELECT 
                COUNT(*) as Total_Inscritos,
                COUNT(CASE WHEN Estado_Inscripcion = 'Confirmado' THEN 1 END) as Confirmados,
                COUNT(CASE WHEN Estado_Inscripcion = 'Pendiente' THEN 1 END) as Pendientes,
                COUNT(CASE WHEN Estado_Inscripcion = 'Cancelado' THEN 1 END) as Cancelados,
                SUM(CASE WHEN Estado_Pago = 'Aprobado' THEN Monto_Pagado ELSE 0 END) as Total_Recaudado,
                SUM(CASE WHEN Estado_Pago = 'Pendiente' THEN Monto_Pagado ELSE 0 END) as Monto_Pendiente
              FROM Inscripciones_Evento WHERE Id_Evento = ?";
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute([$id_evento]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Filtros para inscripciones
$filtro_categoria = $_GET['categoria'] ?? 'Todas';
$filtro_estado = $_GET['estado'] ?? 'Todos';
$busqueda = $_GET['busqueda'] ?? '';

// Obtener TODAS las inscripciones con filtros
$sql_inscritos = "SELECT 
                    ie.Id_Inscripcion,
                    ie.Numero_Participante,
                    ie.Nombre_Completo,
                    ie.Email,
                    ie.Telefono,
                    ie.Edad,
                    ie.Genero,
                    ie.DPI,
                    ie.Direccion,
                    ie.Talla_Playera,
                    ie.Contacto_Emergencia,
                    ie.Telefono_Emergencia,
                    ie.Fecha_Inscripcion,
                    ie.Estado_Pago,
                    ie.Estado_Inscripcion,
                    ie.Monto_Pagado,
                    c.Nombre_Categoria,
                    co.Tipo_Inscripcion
                  FROM Inscripciones_Evento ie
                  LEFT JOIN Categorias_Evento c ON ie.Id_Categoria = c.Id_Categoria
                  LEFT JOIN Costos_Inscripcion co ON ie.Id_Costo = co.Id_Costo
                  WHERE ie.Id_Evento = ?";

$params = [$id_evento];

if ($filtro_categoria !== 'Todas') {
    $sql_inscritos .= " AND c.Nombre_Categoria = ?";
    $params[] = $filtro_categoria;
}

if ($filtro_estado !== 'Todos') {
    $sql_inscritos .= " AND ie.Estado_Inscripcion = ?";
    $params[] = $filtro_estado;
}

if (!empty($busqueda)) {
    $sql_inscritos .= " AND (ie.Nombre_Completo LIKE ? OR ie.Email LIKE ? OR ie.Numero_Participante LIKE ?)";
    $busqueda_param = "%{$busqueda}%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$sql_inscritos .= " ORDER BY ie.Fecha_Inscripcion DESC";

$stmt_inscritos = $pdo->prepare($sql_inscritos);
$stmt_inscritos->execute($params);
$todos_inscritos = $stmt_inscritos->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas por categoría
$sql_stats_cat = "SELECT 
                    c.Nombre_Categoria,
                    COUNT(*) as Total,
                    COUNT(CASE WHEN ie.Estado_Inscripcion = 'Confirmado' THEN 1 END) as Confirmados,
                    COUNT(CASE WHEN ie.Estado_Inscripcion = 'Pendiente' THEN 1 END) as Pendientes
                  FROM Inscripciones_Evento ie
                  LEFT JOIN Categorias_Evento c ON ie.Id_Categoria = c.Id_Categoria
                  WHERE ie.Id_Evento = ?
                  GROUP BY c.Id_Categoria, c.Nombre_Categoria
                  ORDER BY c.Nombre_Categoria";
$stmt_stats_cat = $pdo->prepare($sql_stats_cat);
$stmt_stats_cat->execute([$id_evento]);
$stats_categorias = $stmt_stats_cat->fetchAll(PDO::FETCH_ASSOC);

// Inscripciones recientes
$sql_recientes = "SELECT ie.*, c.Nombre_Categoria
                  FROM Inscripciones_Evento ie
                  LEFT JOIN Categorias_Evento c ON ie.Id_Categoria = c.Id_Categoria
                  WHERE ie.Id_Evento = ?
                  ORDER BY ie.Fecha_Inscripcion DESC
                  LIMIT 10";
$stmt_rec = $pdo->prepare($sql_recientes);
$stmt_rec->execute([$id_evento]);
$inscripciones_recientes = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($evento['Nombre_Evento']) ?> - Detalle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/detalle_evento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .main-content { 
    min-height: 100vh;
    margin-left: 280px; /* o el ancho que tenga tu sidebar */
    transition: margin-left 0.3s ease;
}
        
        .top-bar {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .breadcrumb a { color: #667eea; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .user-info { display: flex; align-items: center; gap: 15px; }
        
        .user-avatar-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-role-icon {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
            border: 2px solid white;
        }
        
        .user-avatar-main {
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
        
        .user-details-main { text-align: left; }
        .user-name-main { font-weight: 600; color: #2c3e50; font-size: 14px; }
        .user-role-main { font-size: 12px; color: #7f8c8d; }
        
        .container { 
            padding: 30px 40px;
            max-width: 100%;
            margin: 0 auto;
        }
        
        .event-banner {
            width: 100%;
            max-width: 100%;
            height: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .event-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-banner-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 5em;
            background: linear-gradient(135deg, #1a3a5f 0%, #0f2a47 50%, #003d82 100%);
        }
        
        .event-header {
            background: white;
            padding: 35px 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .event-title {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }
        
        .event-title-text {
            flex: 1;
            min-width: 250px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            color: #7f8c8d;
            font-size: 0.95em;
            margin-top: 15px;
            width: 100%;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #667eea;
            min-width: 0;
            overflow: hidden;
        }
        
        .event-meta-item span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .event-meta-item i { 
            color: #667eea; 
            font-size: 1.2em;
            flex-shrink: 0;
        }
        
        .badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.85em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        
        .badge-planificado { 
            background: linear-gradient(135deg, #42a5f5 0%, #1976d2 100%);
            color: white;
        }
        .badge-inscripciones_abiertas { 
            background: linear-gradient(135deg, #66bb6a 0%, #388e3c 100%);
            color: white;
        }
        .badge-inscripciones_cerradas { 
            background: linear-gradient(135deg, #ffa726 0%, #f57c00 100%);
            color: white;
        }
        .badge-en_curso { 
            background: linear-gradient(135deg, #26c6da 0%, #0097a7 100%);
            color: white;
        }
        .badge-finalizado { 
            background: linear-gradient(135deg, #ef5350 0%, #c62828 100%);
            color: white;
        }
        .badge-cancelado { 
            background: #6c757d;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }
        
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid #667eea;
            transition: all 0.3s ease;
            min-width: 0;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.2);
        }
        
        .stat-card h3 {
            font-size: 2.8em;
            color: #667eea;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .stat-card p { 
            color: #7f8c8d; 
            font-size: 0.95em; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }
        
        .tab-header {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            background: white;
            overflow-x: auto;
            scrollbar-width: thin;
            width: 100%;
        }
        
        .tab-header::-webkit-scrollbar {
            height: 4px;
        }
        
        .tab-header::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        
        .tab-button {
            padding: 18px 28px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            color: #7f8c8d;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-button i {
            font-size: 1.1em;
        }
        
        .tab-button:hover { 
            color: #667eea; 
            background: #f8f9fa;
        }
        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: linear-gradient(to bottom, #f8f9fa 0%, white 100%);
        }
        
        .tab-content {
            display: none;
            padding: 30px;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }
        
        .tab-content.active { display: block; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-weight: 600;
            color: #667eea;
            font-size: 0.85em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .filtros-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
            width: 100%;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.9em;
            white-space: nowrap;
        }
        
        .form-group select,
        .form-group input {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            width: 100%;
            min-width: 0;
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .stats-categorias {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .stat-categoria-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .stat-categoria-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .stat-categoria-card h4 {
            font-size: 1.3em;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 10px;
        }
        
        .stat-categoria-detail {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 0.9em;
            opacity: 0.95;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .table-container {
            overflow-x: auto;
            width: 100%;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        thead {
            background: #667eea;
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
            white-space: nowrap;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
        }
        
        tbody tr:hover { background: #f8f9fa; }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
        }
        
        .status-pendiente { background: #fff3cd; color: #856404; }
        .status-confirmado { background: #d4edda; color: #155724; }
        .status-cancelado { background: #f8d7da; color: #721c24; }
        
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
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; transform: translateY(-2px); }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; transform: translateY(-2px); }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #bdc3c7;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .total-inscritos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-top: 2px solid #e9ecef;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .total-inscritos-header h3 {
            color: #2c3e50;
            font-size: 1.5em;
            margin: 0;
            flex: 1;
            min-width: 250px;
        }
        
        .resultado-count {
            color: #667eea;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        @media (max-width: 1024px) {
            .filtros-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
            .event-meta { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        }
        
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .event-meta { grid-template-columns: 1fr; }
            .tab-header { overflow-x: auto; }
            .tab-button { padding: 15px 20px; font-size: 0.9em; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .event-title { font-size: 1.5em; }
            .event-banner { height: 200px; }
            .filtros-grid { grid-template-columns: 1fr; }
            .total-inscritos-header { flex-direction: column; align-items: flex-start; }
            .top-bar { padding: 15px 20px; }
        }
        
        @media (max-width: 480px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .event-banner { height: 150px; border-radius: 8px; }
            .tab-content { padding: 15px; }
            .stat-card h3 { font-size: 2em; }
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="breadcrumb">
                <a href="ver_eventos.php"><i class="fas fa-calendar-alt"></i> Eventos</a>
                <span>/</span>
                <span><?= htmlspecialchars($evento['Nombre_Evento']) ?></span>
            </div>
            <div class="user-info">
                <div class="user-avatar-wrapper">
                    <?php
                        $iconClass = '';
                        switch ($role) {
                            case 'Administrador': $iconClass = 'fa-solid fa-crown'; break;
                            case 'Coordinador':   $iconClass = 'fa-solid fa-user-tie'; break;
                            default:              $iconClass = 'fa-solid fa-user'; break;
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
        
        <div class="container">
            <div class="event-banner">
                <?php if ($evento['Imagen_Banner']): ?>
                    <img src="<?= htmlspecialchars($evento['Imagen_Banner']) ?>" alt="<?= htmlspecialchars($evento['Nombre_Evento']) ?>">
                <?php else: ?>
                    <div class="event-banner-placeholder" style="width:100%;height:100%;">
                        <i class="fas fa-running"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="event-header">
                <?php $estado_clase = strtolower(str_replace(' ', '_', $evento['Estado_Evento'])); ?>
                <div class="event-title">
                    <div class="event-title-text"><?= htmlspecialchars($evento['Nombre_Evento']) ?></div>
                    <span class="badge badge-<?= $estado_clase ?>"><?= $evento['Estado_Evento'] ?></span>
                    <a href="editar_evento.php?id=<?= $id_evento ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Editar Evento
    </a>
                </div>
                <div class="event-meta">
                    <div class="event-meta-item">
                        <i class="fas fa-tag"></i>
                        <span><strong>Tipo:</strong> <?= htmlspecialchars($evento['Tipo_Evento_Nombre']) ?></span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-calendar-day"></i>
                        <span><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($evento['Fecha_Evento'])) ?></span>
                    </div>
                    <div class="event-meta-item">
                        <i class="fas fa-clock"></i>
                        <span><strong>Hora:</strong> <?= date('H:i', strtotime($evento['Hora_Inicio'])) ?></span>
                    </div>
                    <?php if ($evento['Distancia_KM']): ?>
                    <div class="event-meta-item">
                        <i class="fas fa-road"></i>
                        <span><strong>Distancia:</strong> <?= $evento['Distancia_KM'] ?> km</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $stats['Total_Inscritos'] ?></h3>
                    <p>Total Inscritos</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['Confirmados'] ?></h3>
                    <p>Confirmados</p>
                </div>
                <div class="stat-card">
                    <h3><?= $stats['Pendientes'] ?></h3>
                    <p>Pendientes</p>
                </div>
                <div class="stat-card">
                    <h3>Q<?= number_format($stats['Total_Recaudado'] ?? 0, 2) ?></h3>
                    <p>Recaudado</p>
                </div>
            </div>
            
            <div class="tabs">
                <div class="tab-header">
                    <button class="tab-button active" onclick="openTab(event, 'info')">
                        <i class="fas fa-info-circle"></i> Información General
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'categorias')">
                        <i class="fas fa-medal"></i> Categorías
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'costos')">
                        <i class="fas fa-dollar-sign"></i> Costos
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'cuentas')">
                        <i class="fas fa-university"></i> Cuentas Bancarias
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'todos-inscritos')">
                        <i class="fas fa-list-ul"></i> Todos los Inscritos (<?= count($todos_inscritos) ?>)
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'inscritos')">
                        <i class="fas fa-users"></i> Últimas Inscripciones
                    </button>
                </div>
                
                <div id="info" class="tab-content active">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Lugar de Salida</div>
                            <div class="info-value"><?= htmlspecialchars($evento['Lugar_Salida']) ?></div>
                        </div>
                        <?php if ($evento['Cupo_Maximo']): ?>
                        <div class="info-item">
                            <div class="info-label">Cupo Máximo</div>
                            <div class="info-value"><?= $evento['Cupo_Maximo'] ?> participantes</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($evento['Hora_Inicio']): ?>
                        <div class="info-item">
                            <div class="info-label">Hora de Inicio</div>
                            <div class="info-value"><?= date('H:i', strtotime($evento['Hora_Inicio'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($evento['Descripcion']): ?>
                    <div class="info-item" style="margin-top: 20px;">
                        <div class="info-label">Descripción</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($evento['Descripcion'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evento['Recorrido']): ?>
                    <div class="info-item" style="margin-top: 20px;">
                        <div class="info-label">Recorrido</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($evento['Recorrido'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evento['Causa_Beneficiada']): ?>
                    <div class="info-item" style="margin-top: 20px;">
                        <div class="info-label">Causa Beneficiada</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($evento['Causa_Beneficiada'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="categorias" class="tab-content">
                    <?php if (count($categorias) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Género</th>
                                    <th>Edad Mínima</th>
                                    <th>Edad Máxima</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cat['Nombre_Categoria']) ?></strong></td>
                                    <td><?= htmlspecialchars($cat['Genero']) ?></td>
                                    <td><?= $cat['Edad_Minima'] ?? 'Sin límite' ?></td>
                                    <td><?= $cat['Edad_Maxima'] ?? 'Sin límite' ?></td>
                                    <td><?= htmlspecialchars($cat['Descripcion'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-medal"></i>
                        <p>No hay categorías registradas</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="costos" class="tab-content">
                    <?php if (count($costos) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tipo de Inscripción</th>
                                    <th>Costo</th>
                                    <th>Válido Desde</th>
                                    <th>Válido Hasta</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($costos as $costo): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($costo['Tipo_Inscripcion']) ?></strong></td>
                                    <td><strong>Q<?= number_format($costo['Costo'], 2) ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($costo['Fecha_Inicio'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($costo['Fecha_Fin'])) ?></td>
                                    <td><?= htmlspecialchars($costo['Descripcion'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-dollar-sign"></i>
                        <p>No hay costos registrados</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="cuentas" class="tab-content">
                    <?php if (count($cuentas) > 0): ?>
                    <div class="info-grid">
                        <?php foreach ($cuentas as $cuenta): ?>
                        <div class="info-item">
                            <div class="info-label"><?= htmlspecialchars($cuenta['Nombre_Banco']) ?></div>
                            <div class="info-value">
                                <strong>Cuenta:</strong> <?= htmlspecialchars($cuenta['Numero_Cuenta']) ?><br>
                                <strong>Nombre:</strong> <?= htmlspecialchars($cuenta['Nombre_Cuenta']) ?><br>
                                <strong>Tipo:</strong> <?= htmlspecialchars($cuenta['Tipo_Cuenta']) ?><br>
                                <strong>Moneda:</strong> <?= htmlspecialchars($cuenta['Moneda']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-university"></i>
                        <p>No hay cuentas bancarias registradas</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="todos-inscritos" class="tab-content">
                    <?php if (count($stats_categorias) > 0): ?>
                    <div class="stats-categorias">
                        <?php foreach ($stats_categorias as $stat_cat): ?>
                        <div class="stat-categoria-card">
                            <h4><?= htmlspecialchars($stat_cat['Nombre_Categoria']) ?></h4>
                            <div style="font-size: 2em; font-weight: bold;"><?= $stat_cat['Total'] ?></div>
                            <div class="stat-categoria-detail">
                                <span>✓ <?= $stat_cat['Confirmados'] ?> confirmados</span>
                                <span>⏳ <?= $stat_cat['Pendientes'] ?> pendientes</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filtros-section">
                        <form method="GET" action="">
                            <input type="hidden" name="id" value="<?= $id_evento ?>">
                            <div class="filtros-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-search"></i> Buscar</label>
                                    <input type="text" name="busqueda" placeholder="Nombre, email o número..." value="<?= htmlspecialchars($busqueda) ?>">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-medal"></i> Categoría</label>
                                    <select name="categoria">
                                        <option value="Todas" <?= $filtro_categoria === 'Todas' ? 'selected' : '' ?>>Todas</option>
                                        <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['Nombre_Categoria']) ?>" 
                                                <?= $filtro_categoria === $cat['Nombre_Categoria'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['Nombre_Categoria']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-filter"></i> Estado</label>
                                    <select name="estado">
                                        <option value="Todos" <?= $filtro_estado === 'Todos' ? 'selected' : '' ?>>Todos</option>
                                        <option value="Confirmado" <?= $filtro_estado === 'Confirmado' ? 'selected' : '' ?>>Confirmados</option>
                                        <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                        <option value="Cancelado" <?= $filtro_estado === 'Cancelado' ? 'selected' : '' ?>>Cancelados</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                </div>
                                <?php if (!empty($busqueda) || $filtro_categoria !== 'Todas' || $filtro_estado !== 'Todos'): ?>
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <a href="?id=<?= $id_evento ?>" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (count($todos_inscritos) > 0): ?>
                    <div class="total-inscritos-header">
                        <h3>
                            <span class="resultado-count"><?= count($todos_inscritos) ?></span> 
                            <?= count($todos_inscritos) === 1 ? 'Inscrito' : 'Inscritos' ?>
                            <?php if ($filtro_categoria !== 'Todas' || $filtro_estado !== 'Todos' || !empty($busqueda)): ?>
                                (Filtrado)
                            <?php endif; ?>
                        </h3>
                        <a href="exportar_inscritos.php?id_evento=<?= $id_evento ?>&categoria=<?= urlencode($filtro_categoria) ?>&estado=<?= urlencode($filtro_estado) ?>&busqueda=<?= urlencode($busqueda) ?>" 
                           class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Exportar a Excel
                        </a>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Edad</th>
                                    <th>Género</th>
                                    <th>Talla</th>
                                    <th>Estado</th>
                                    <th>Tipo Inscripción</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todos_inscritos as $inscrito): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($inscrito['Numero_Participante']) ?></strong></td>
                                    <td><?= htmlspecialchars($inscrito['Nombre_Completo']) ?></td>
                                    <td><?= htmlspecialchars($inscrito['Nombre_Categoria']) ?></td>
                                    <td><?= htmlspecialchars($inscrito['Email']) ?></td>
                                    <td><?= htmlspecialchars($inscrito['Telefono']) ?></td>
                                    <td><?= $inscrito['Edad'] ?></td>
                                    <td><?= htmlspecialchars($inscrito['Genero']) ?></td>
                                    <td><?= htmlspecialchars($inscrito['Talla_Playera']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($inscrito['Estado_Inscripcion']) ?>">
                                            <?= $inscrito['Estado_Inscripcion'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($inscrito['Tipo_Inscripcion']) ?></td>
                                    <td>Q<?= number_format($inscrito['Monto_Pagado'], 2) ?></td>
                                    <td><?= date('d/m/Y', strtotime($inscrito['Fecha_Inscripcion'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No se encontraron inscritos</h3>
                        <?php if (!empty($busqueda) || $filtro_categoria !== 'Todas' || $filtro_estado !== 'Todos'): ?>
                        <p>No hay resultados para los filtros aplicados</p>
                        <a href="?id=<?= $id_evento ?>" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-times"></i> Limpiar Filtros
                        </a>
                        <?php else: ?>
                        <p>No hay inscripciones aún para este evento</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="inscritos" class="tab-content">
                    <?php if (count($inscripciones_recientes) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Fecha Inscripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inscripciones_recientes as $insc): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($insc['Numero_Participante']) ?></strong></td>
                                    <td><?= htmlspecialchars($insc['Nombre_Completo']) ?></td>
                                    <td><?= htmlspecialchars($insc['Nombre_Categoria']) ?></td>
                                    <td><?= htmlspecialchars($insc['Email']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($insc['Estado_Inscripcion']) ?>">
                                            <?= $insc['Estado_Inscripcion'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($insc['Fecha_Inscripcion'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="revisar_inscripciones.php?evento=<?= $id_evento ?>" class="btn btn-primary">
                            <i class="fas fa-clipboard-check"></i> Revisar Todas las Inscripciones
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No hay inscripciones aún</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openTab(event, tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            const buttons = document.querySelectorAll('.tab-button');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.tab-button').classList.add('active');
        }

        function confirmAction(message, url) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '<?= htmlspecialchars($_GET['success']) ?>',
                confirmButtonColor: '#667eea',
                timer: 3000
            });
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($_GET['error']) ?>',
                confirmButtonColor: '#667eea'
            });
        <?php endif; ?>
    </script>
</body>
</html>