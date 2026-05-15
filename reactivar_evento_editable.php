<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';

// Obtener ID del evento a reactivar
$id_evento_origen = $_GET['id'] ?? null;

if (!$id_evento_origen) {
    header('Location: ver_eventos.php');
    exit;
}

// Obtener datos completos del evento original
$stmt = $pdo->prepare("SELECT * FROM Eventos WHERE Id_Evento = ?");
$stmt->execute([$id_evento_origen]);
$evento_original = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento_original) {
    $_SESSION['mensaje_error'] = "Evento no encontrado";
    header('Location: ver_eventos.php');
    exit;
}

// Obtener categorías del evento
$stmt_cat = $pdo->prepare("SELECT * FROM Categorias_Evento WHERE Id_Evento = ? ORDER BY Id_Categoria");
$stmt_cat->execute([$id_evento_origen]);
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Obtener costos del evento
$stmt_cost = $pdo->prepare("SELECT * FROM Costos_Inscripcion WHERE Id_Evento = ? ORDER BY Fecha_Inicio");
$stmt_cost->execute([$id_evento_origen]);
$costos = $stmt_cost->fetchAll(PDO::FETCH_ASSOC);

// Obtener cuentas bancarias del evento
$stmt_cta = $pdo->prepare("SELECT * FROM Cuentas_Bancarias WHERE Id_Evento = ? ORDER BY Id_Cuenta");
$stmt_cta->execute([$id_evento_origen]);
$cuentas = $stmt_cta->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de evento
$sql_tipos = "SELECT * FROM Tipos_Evento WHERE Estado = 'Activo' ORDER BY Nombre";
$stmt_tipos = $pdo->prepare($sql_tipos);
$stmt_tipos->execute();
$tipos_evento = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

// Preparar nombre sugerido con año actual
$nombre_sugerido = $evento_original['Nombre_Evento'] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivar Evento - Sistema de Becas</title>
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
        
        .main-content { margin-left: 260px; flex: 1; min-height: 100vh; padding-bottom: 80px; }
        
        .top-bar {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            padding: 25px 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 { font-size: 28px; color: #000; font-weight: 700; }
        .page-title p { font-size: 14px; color: rgba(0,0,0,0.7); margin-top: 5px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffc107;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-details { text-align: right; }
        .user-name { font-weight: 600; color: #000; font-size: 14px; }
        .user-role { font-size: 12px; color: rgba(0,0,0,0.7); }
        
        .container { padding: 30px 40px; }
        
        .alert-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            color: #1976d2;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .alert-info i {
            font-size: 24px;
            margin-top: 2px;
        }

        .alert-info-content h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #1976d2;
        }

        .alert-info-content p {
            font-size: 14px;
            line-height: 1.6;
            color: #424242;
        }

        .alert-info-content ul {
            margin-top: 10px;
            margin-left: 20px;
            color: #424242;
        }

        .alert-info-content li {
            margin: 5px 0;
        }
        
        .success-message, .error-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .success-message { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error-message { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        
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
        
        .section-title i { font-size: 22px; color: #ffc107; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-group.required label::after { content: ' *'; color: #dc3545; }
        
        input, select, textarea {
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
        
        textarea { resize: vertical; min-height: 100px; }
        input[type="file"] { padding: 10px; border: 2px dashed #e9ecef; }
        small { color: #7f8c8d; font-size: 12px; margin-top: 5px; display: block; }
        
        .categorias-container, .costos-container, .cuentas-container {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            background: #f8f9fa;
        }
        
        .item-row {
            background: white;
            padding: 20px;
            padding-top: 35px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            border: 1px solid #e9ecef;
        }
        
        .remove-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success { background: #ffc107; color: #000; font-weight: 700; }
        .btn-success:hover:not(:disabled) {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-danger:hover { background: #c82333; transform: translateY(-2px); }
        
        .btn-add {
            background: white;
            color: #ffc107;
            border: 2px solid #ffc107;
            margin-top: 15px;
        }
        
        .btn-add:hover {
            background: #ffc107;
            color: #000;
            transform: translateY(-2px);
        }
        
        .btn-cancel { background: #6c757d; color: white; }
        .btn-cancel:hover { background: #5a6268; transform: translateY(-2px); }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.1); }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 3px; }
        
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 0; transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="https://i.postimg.cc/QtyYkbxp/Club-Rotary-Coatepeque-Colomba.png" alt="Club Rotario Logo">
            </div>
            <h2>Coatepeque Colomba</h2>
            <p>SISTEMA DE BECAS</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">
                <a href="admin.php" class="menu-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">EVENTOS</div>
                <a href="crear_eventos.php" class="menu-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Crear Eventos</span>
                </a>
                <a href="ver_eventos.php" class="menu-item active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Ver Eventos</span>
                </a>
                <a href="revisar_inscripciones.php" class="menu-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Revisar Inscripciones</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1><i class="fas fa-redo"></i> Reactivar Evento</h1>
                <p>Edita y personaliza el evento antes de reactivarlo</p>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($username, 0, 2)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($username) ?></div>
                    <div class="user-role">Administrador</div>
                </div>
            </div>
        </div>
        
        <div class="container">
            <div class="alert-info">
                <i class="fas fa-info-circle"></i>
                <div class="alert-info-content">
                    <h3>Reactivando: <?= htmlspecialchars($evento_original['Nombre_Evento']) ?></h3>
                    <p>
                        Estás creando una nueva edición de este evento. Todos los campos están pre-cargados con la información 
                        del evento original, pero puedes modificar lo que necesites:
                    </p>
                    <ul>
                        <li><strong>Fechas y horarios</strong> - Actualiza para la nueva edición</li>
                        <li><strong>Costos</strong> - Modifica precios y periodos</li>
                        <li><strong>Categorías</strong> - Agrega, elimina o edita categorías</li>
                        <li><strong>Información general</strong> - Actualiza descripción, lugar, etc.</li>
                        <li><strong>Cuentas bancarias</strong> - Verifica que sigan vigentes</li>
                    </ul>
                    <p style="margin-top: 10px;">
                        <strong>Nota:</strong> Las inscripciones del evento original permanecerán intactas en su historial.
                    </p>
                </div>
            </div>

            <div class="success-message" id="successMessage">
                <i class="fas fa-check-circle"></i> <span id="successText"></span>
            </div>
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-triangle"></i> <span id="errorText"></span>
            </div>

            <form id="formEvento" enctype="multipart/form-data">
                <input type="hidden" name="id_evento_origen" value="<?= $id_evento_origen ?>">
                
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Información General
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width required">
                            <label for="nombre_evento">Nombre del Evento</label>
                            <input type="text" id="nombre_evento" name="nombre_evento" 
                                   value="<?= htmlspecialchars($nombre_sugerido) ?>" required>
                        </div>

                        <div class="form-group required">
                            <label for="id_tipo_evento">Tipo de Evento</label>
                            <select id="id_tipo_evento" name="id_tipo_evento" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($tipos_evento as $tipo): ?>
                                <option value="<?= $tipo['Id_Tipo_Evento'] ?>" 
                                        <?= $tipo['Id_Tipo_Evento'] == $evento_original['Id_Tipo_Evento'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['Nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group required">
                            <label for="fecha_evento">Fecha del Evento</label>
                            <input type="date" id="fecha_evento" name="fecha_evento" 
                                   min="<?= date('Y-m-d') ?>" required>
                            <small>Fecha original: <?= date('d/m/Y', strtotime($evento_original['Fecha_Evento'])) ?></small>
                        </div>

                        <div class="form-group required">
                            <label for="hora_inicio">Hora de Inicio</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" 
                                   value="<?= $evento_original['Hora_Inicio'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="hora_salida">Hora de Salida (opcional)</label>
                            <input type="time" id="hora_salida" name="hora_salida" 
                                   value="<?= $evento_original['Hora_Salida'] ?>">
                        </div>

                        <div class="form-group">
                            <label for="cupo_maximo">Cupo Máximo (opcional)</label>
                            <input type="number" id="cupo_maximo" name="cupo_maximo" min="1" 
                                   value="<?= $evento_original['Cupo_Maximo'] ?>">
                        </div>

                        <div class="form-group">
                            <label for="distancia_km">Distancia (km)</label>
                            <input type="number" id="distancia_km" name="distancia_km" step="0.01" min="0" 
                                   value="<?= $evento_original['Distancia_KM'] ?>">
                        </div>

                        <div class="form-group">
                            <label for="fecha_inicio_inscripciones">Inicio de Inscripciones</label>
                            <input type="datetime-local" id="fecha_inicio_inscripciones" name="fecha_inicio_inscripciones">
                        </div>

                        <div class="form-group">
                            <label for="fecha_fin_inscripciones">Cierre de Inscripciones</label>
                            <input type="datetime-local" id="fecha_fin_inscripciones" name="fecha_fin_inscripciones">
                        </div>

                        <div class="form-group full-width required">
                            <label for="lugar_salida">Lugar de Salida</label>
                            <textarea id="lugar_salida" name="lugar_salida" rows="3" required><?= htmlspecialchars($evento_original['Lugar_Salida']) ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="descripcion">Descripción del Evento</label>
                            <textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($evento_original['Descripcion']) ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="recorrido">Detalles del Recorrido</label>
                            <textarea id="recorrido" name="recorrido" rows="3"><?= htmlspecialchars($evento_original['Recorrido']) ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="causa_beneficiada">Causa Beneficiada</label>
                            <textarea id="causa_beneficiada" name="causa_beneficiada" rows="2"><?= htmlspecialchars($evento_original['Causa_Beneficiada']) ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="imagen_banner">Imagen Banner del Evento</label>
                            <input type="file" id="imagen_banner" name="imagen_banner" accept="image/*">
                            <small>JPG, PNG. Tamaño recomendado: 1200x400px. Si no subes una nueva, se usará la imagen original.</small>
                            <?php if ($evento_original['Imagen_Banner']): ?>
                            <input type="hidden" name="imagen_banner_original" value="<?= $evento_original['Imagen_Banner'] ?>">
                            <small style="color: #28a745;">✓ El evento original tiene una imagen banner</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-medal"></i> Categorías del Evento
                    </h3>
                    <div class="categorias-container" id="categoriasContainer">
                        <?php foreach ($categorias as $index => $cat): ?>
                        <div class="item-row" data-index="<?= $index ?>">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'categoria')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Nombre de Categoría</label>
                                    <input type="text" name="categorias[<?= $index ?>][nombre]" 
                                           value="<?= htmlspecialchars($cat['Nombre_Categoria']) ?>" required>
                                </div>
                                <div class="form-group required">
                                    <label>Género</label>
                                    <select name="categorias[<?= $index ?>][genero]" required>
                                        <option value="">Selecciona...</option>
                                        <option value="Masculino" <?= $cat['Genero'] == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="Femenino" <?= $cat['Genero'] == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                                        <option value="Mixto" <?= $cat['Genero'] == 'Mixto' ? 'selected' : '' ?>>Mixto</option>
                                        <option value="Todos" <?= $cat['Genero'] == 'Todos' ? 'selected' : '' ?>>Todos</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Edad Mínima</label>
                                    <input type="number" name="categorias[<?= $index ?>][edad_min]" min="0" 
                                           value="<?= $cat['Edad_Minima'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Edad Máxima</label>
                                    <input type="number" name="categorias[<?= $index ?>][edad_max]" min="0" 
                                           value="<?= $cat['Edad_Maxima'] ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label>Descripción</label>
                                    <input type="text" name="categorias[<?= $index ?>][descripcion]" 
                                           value="<?= htmlspecialchars($cat['Descripcion']) ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCategoria()">
                        <i class="fas fa-plus"></i> Agregar Categoría
                    </button>
                </div>

                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i> Costos de Inscripción
                    </h3>
                    <div class="costos-container" id="costosContainer">
                        <?php foreach ($costos as $index => $cost): ?>
                        <div class="item-row" data-index="<?= $index ?>">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'costo')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Tipo de Inscripción</label>
                                    <input type="text" name="costos[<?= $index ?>][tipo]" 
                                           value="<?= htmlspecialchars($cost['Tipo_Inscripcion']) ?>" required>
                                </div>
                                <div class="form-group required">
                                    <label>Costo (Q)</label>
                                    <input type="number" name="costos[<?= $index ?>][costo]" step="0.01" min="0" 
                                           value="<?= $cost['Costo'] ?>" required>
                                </div>
                                <div class="form-group required">
                                    <label>Válido Desde</label>
                                    <input type="date" name="costos[<?= $index ?>][fecha_inicio]" required>
                                    <small>Original: <?= date('d/m/Y', strtotime($cost['Fecha_Inicio'])) ?></small>
                                </div>
                                <div class="form-group required">
                                    <label>Válido Hasta</label>
                                    <input type="date" name="costos[<?= $index ?>][fecha_fin]" required>
                                    <small>Original: <?= date('d/m/Y', strtotime($cost['Fecha_Fin'])) ?></small>
                                </div>
                                <div class="form-group full-width">
                                    <label>Descripción</label>
                                    <input type="text" name="costos[<?= $index ?>][descripcion]" 
                                           value="<?= htmlspecialchars($cost['Descripcion']) ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCosto()">
                        <i class="fas fa-plus"></i> Agregar Costo
                    </button>
                </div>

                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-university"></i> Cuentas Bancarias para Pago
                    </h3>
                    <div class="cuentas-container" id="cuentasContainer">
                        <?php foreach ($cuentas as $index => $cta): ?>
                        <div class="item-row" data-index="<?= $index ?>">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'cuenta')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Banco</label>
                                    <input type="text" name="cuentas[<?= $index ?>][banco]" 
                                           value="<?= htmlspecialchars($cta['Nombre_Banco']) ?>" required>
                                </div>
                                <div class="form-group required">
                                    <label>Número de Cuenta</label>
                                    <input type="text" name="cuentas[<?= $index ?>][numero]" 
                                           value="<?= htmlspecialchars($cta['Numero_Cuenta']) ?>" required>
                                </div>
                                <div class="form-group required">
                                    <label>Nombre de la Cuenta</label>
                                    <input type="text" name="cuentas[<?= $index ?>][nombre]" 
                                           value="<?= htmlspecialchars($cta['Nombre_Cuenta']) ?>" required>
                                </div>
                                <div class="form-group required">
                                    <label>Tipo de Cuenta</label>
                                    <select name="cuentas[<?= $index ?>][tipo]" required>
                                        <option value="">Selecciona...</option>
                                        <option value="Ahorro" <?= $cta['Tipo_Cuenta'] == 'Ahorro' ? 'selected' : '' ?>>Ahorro</option>
                                        <option value="Monetaria" <?= $cta['Tipo_Cuenta'] == 'Monetaria' ? 'selected' : '' ?>>Monetaria</option>
                                        <option value="Cheques" <?= $cta['Tipo_Cuenta'] == 'Cheques' ? 'selected' : '' ?>>Cheques</option>
                                        <option value="Empresarial" <?= $cta['Tipo_Cuenta'] == 'Empresarial' ? 'selected' : '' ?>>Empresarial</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Moneda</label>
                                    <select name="cuentas[<?= $index ?>][moneda]">
                                        <option value="GTQ" <?= $cta['Moneda'] == 'GTQ' ? 'selected' : '' ?>>Quetzales (GTQ)</option>
                                        <option value="USD" <?= $cta['Moneda'] == 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCuenta()">
                        <i class="fas fa-plus"></i> Agregar Cuenta Bancaria
                    </button>
                </div>

                <div class="form-card">
                    <div class="action-buttons">
                        <button type="button" class="btn btn-cancel" onclick="window.location.href='ver_eventos.php'">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnSubmit">
                            <i class="fas fa-redo"></i> Reactivar Evento
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let categoriaIndex = <?= count($categorias) ?>;
        let costoIndex = <?= count($costos) ?>;
        let cuentaIndex = <?= count($cuentas) ?>;

        function addCategoria() {
            const container = document.getElementById('categoriasContainer');
            const html = `
                <div class="item-row" data-index="${categoriaIndex}">
                    <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'categoria')">
                        <i class="fas fa-trash"></i>
                    </button>
                    <div class="form-grid">
                        <div class="form-group required">
                            <label>Nombre de Categoría</label>
                            <input type="text" name="categorias[${categoriaIndex}][nombre]" required>
                        </div>
                        <div class="form-group required">
                            <label>Género</label>
                            <select name="categorias[${categoriaIndex}][genero]" required>
                                <option value="">Selecciona...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                                <option value="Mixto">Mixto</option>
                                <option value="Todos">Todos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Edad Mínima</label>
                            <input type="number" name="categorias[${categoriaIndex}][edad_min]" min="0">
                        </div>
                        <div class="form-group">
                            <label>Edad Máxima</label>
                            <input type="number" name="categorias[${categoriaIndex}][edad_max]" min="0">
                        </div>
                        <div class="form-group full-width">
                            <label>Descripción</label>
                            <input type="text" name="categorias[${categoriaIndex}][descripcion]">
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            categoriaIndex++;
        }

        function addCosto() {
            const container = document.getElementById('costosContainer');
            const html = `
                <div class="item-row" data-index="${costoIndex}">
                    <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'costo')">
                        <i class="fas fa-trash"></i>
                    </button>
                    <div class="form-grid">
                        <div class="form-group required">
                            <label>Tipo de Inscripción</label>
                            <input type="text" name="costos[${costoIndex}][tipo]" required>
                        </div>
                        <div class="form-group required">
                            <label>Costo (Q)</label>
                            <input type="number" name="costos[${costoIndex}][costo]" step="0.01" min="0" required>
                        </div>
                        <div class="form-group required">
                            <label>Válido Desde</label>
                            <input type="date" name="costos[${costoIndex}][fecha_inicio]" required>
                        </div>
                        <div class="form-group required">
                            <label>Válido Hasta</label>
                            <input type="date" name="costos[${costoIndex}][fecha_fin]" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Descripción</label>
                            <input type="text" name="costos[${costoIndex}][descripcion]">
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            costoIndex++;
        }

        function addCuenta() {
            const container = document.getElementById('cuentasContainer');
            const html = `
                <div class="item-row" data-index="${cuentaIndex}">
                    <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'cuenta')">
                        <i class="fas fa-trash"></i>
                    </button>
                    <div class="form-grid">
                        <div class="form-group required">
                            <label>Banco</label>
                            <input type="text" name="cuentas[${cuentaIndex}][banco]" required>
                        </div>
                        <div class="form-group required">
                            <label>Número de Cuenta</label>
                            <input type="text" name="cuentas[${cuentaIndex}][numero]" required>
                        </div>
                        <div class="form-group required">
                            <label>Nombre de la Cuenta</label>
                            <input type="text" name="cuentas[${cuentaIndex}][nombre]" required>
                        </div>
                        <div class="form-group required">
                            <label>Tipo de Cuenta</label>
                            <select name="cuentas[${cuentaIndex}][tipo]" required>
                                <option value="">Selecciona...</option>
                                <option value="Ahorro">Ahorro</option>
                                <option value="Monetaria">Monetaria</option>
                                <option value="Cheques">Cheques</option>
                                <option value="Empresarial">Empresarial</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Moneda</label>
                            <select name="cuentas[${cuentaIndex}][moneda]">
                                <option value="GTQ">Quetzales (GTQ)</option>
                                <option value="USD">Dólares (USD)</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            cuentaIndex++;
        }

        function removeItem(button, type) {
            const container = button.closest('.item-row').parentElement;
            const items = container.querySelectorAll('.item-row');
            
            if (items.length > 1) {
                button.closest('.item-row').remove();
            } else {
                alert('Debe haber al menos un elemento de este tipo');
            }
        }

        document.getElementById('formEvento').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btnSubmit = document.getElementById('btnSubmit');
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reactivando evento...';
            successMsg.style.display = 'none';
            errorMsg.style.display = 'none';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('procesar_reactivar_evento.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('successText').textContent = data.message;
                    successMsg.style.display = 'block';
                    successMsg.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    setTimeout(() => {
                        window.location.href = 'ver_eventos.php';
                    }, 2000);
                } else {
                    document.getElementById('errorText').textContent = data.message;
                    errorMsg.style.display = 'block';
                    errorMsg.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-redo"></i> Reactivar Evento';
                }
            } catch (error) {
                document.getElementById('errorText').textContent = 'Error al reactivar el evento. Intenta nuevamente.';
                errorMsg.style.display = 'block';
                errorMsg.scrollIntoView({ behavior: 'smooth', block: 'start' });
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-redo"></i> Reactivar Evento';
            }
        });
    </script>
</body>
</html>