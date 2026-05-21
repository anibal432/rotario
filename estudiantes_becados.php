<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// =============================================
// PROCESAMIENTO AJAX — Registrar nuevo becado
// (Usa el stored procedure RegistrarBecadoRapido)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'registrar_becado_rapido') {

    header('Content-Type: application/json');

    try {

        // Campos obligatorios
        $required = [
            'nombres_apellidos',
            'edad',
            'telefono',
            'direccion',
            'nombre_encargado',
            'telefono_encargado',
            'grado_anterior',
            'escuela_anterior',
            'tipo_beca',
            'monto_mensual',
            'fecha_inicio',
        ];

        foreach ($required as $campo) {
            if (empty(trim($_POST[$campo] ?? ''))) {
                throw new Exception("El campo '{$campo}' es requerido.");
            }
        }

        // Foto opcional
        $foto_nombre = null;

        if (!empty($_FILES['foto_becado']['name'])) {

            $upload_dir = 'uploads/fotos/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = strtolower(
                pathinfo($_FILES['foto_becado']['name'], PATHINFO_EXTENSION)
            );

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                throw new Exception("Formato de imagen no permitido.");
            }

            if ($_FILES['foto_becado']['size'] > 5 * 1024 * 1024) {
                throw new Exception("La imagen no debe superar 5MB.");
            }

            $foto_nombre = 'TEMP_' . time() . '.' . $ext;

            move_uploaded_file(
                $_FILES['foto_becado']['tmp_name'],
                $upload_dir . $foto_nombre
            );
        }

        // Llamar al stored procedure
        $stmt = $pdo->prepare("CALL RegistrarBecadoRapido(
            :nombres, :edad, :telefono, :email,
            :madre, :padre, :direccion,
            :encargado, :tel_encargado,
            :grado, :escuela, :foto,
            :id_usuario,
            :tipo_beca, :monto, :fecha_inicio, :fecha_fin,
            :promedio_min, :socio_rotario,
            @expediente, @id_est, @id_beca, @mensaje
        )");

        $stmt->execute([
            ':nombres'       => trim($_POST['nombres_apellidos']),
            ':edad'          => (int) $_POST['edad'],
            ':telefono'      => trim($_POST['telefono']),
            ':email'         => trim($_POST['email'] ?? ''),
            ':madre'         => trim($_POST['nombre_madre'] ?? ''),
            ':padre'         => trim($_POST['nombre_padre'] ?? ''),
            ':direccion'     => trim($_POST['direccion']),
            ':encargado'     => trim($_POST['nombre_encargado']),
            ':tel_encargado' => trim($_POST['telefono_encargado']),
            ':grado'         => trim($_POST['grado_anterior']),
            ':escuela'       => trim($_POST['escuela_anterior']),
            ':foto'          => $foto_nombre,
            ':id_usuario'    => (int) $_SESSION['user_id'],
            ':tipo_beca'     => trim($_POST['tipo_beca']),
            ':monto'         => (float) $_POST['monto_mensual'],
            ':fecha_inicio'  => $_POST['fecha_inicio'],
            ':fecha_fin'     => !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null,
            ':promedio_min'  => (float) ($_POST['promedio_minimo'] ?? 70),
            ':socio_rotario' => trim($_POST['socio_rotario'] ?? ''),
        ]);

        // Leer variables de salida del procedure
        $out = $pdo
            ->query("SELECT @expediente AS exp,
                            @id_est     AS id_est,
                            @id_beca    AS id_beca,
                            @mensaje    AS msg")
            ->fetch(PDO::FETCH_ASSOC);

        // Renombrar foto con expediente real
        if ($foto_nombre && !empty($out['exp'])) {
            $ext_foto = pathinfo($foto_nombre, PATHINFO_EXTENSION);
            $foto_final = $out['exp'] . '_' . time() . '.' . $ext_foto;
            rename(
                'uploads/fotos/' . $foto_nombre,
                'uploads/fotos/' . $foto_final
            );
            // Actualizar nombre de foto en la BD
            $pdo->prepare("UPDATE Estudiantes SET Foto_Becado = ? WHERE Id_Estudiante = ?")
                ->execute([$foto_final, $out['id_est']]);
        }

        if ($out['id_est'] === null) {
            throw new Exception($out['msg'] ?? 'Error desconocido en el procedimiento.');
        }
        // ======================================================
// INSERCIÓN AUTOMÁTICA DE PAGOS HISTÓRICOS (MESES ANTERIORES)
// ======================================================
if (!empty($out['id_beca'])) {
    $fecha_inicio = $_POST['fecha_inicio'];
    $monto        = (float) $_POST['monto_mensual'];
    $id_beca      = $out['id_beca'];
    $id_usuario   = (int) $_SESSION['user_id'];

    $inicio = new DateTime($fecha_inicio);
    $hoy    = new DateTime();
    // Último mes completo: primer día del mes actual - 1 día = último día del mes anterior
    $ultimoMesCompleto = (new DateTime('first day of this month'))->modify('-1 day');

    // Solo si la fecha de inicio es anterior al último mes completo
    if ($inicio <= $ultimoMesCompleto) {
        // Clonamos la fecha de inicio y la llevamos al primer día de su mes
        $mesActual = clone $inicio;
        $mesActual->modify('first day of this month');

        while ($mesActual <= $ultimoMesCompleto) {
            $periodo   = $mesActual->format('Y-m');
            $fechaPago = clone $mesActual;
            $fechaPago->modify('last day of this month');

            // Verificar si ya existe un pago para este período (evitar duplicados)
            $stmtCheck = $pdo->prepare("SELECT 1 FROM Pagos_Becas WHERE Id_Beca = ? AND Periodo = ?");
            $stmtCheck->execute([$id_beca, $periodo]);
            if (!$stmtCheck->fetch()) {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO Pagos_Becas 
                        (Id_Beca, Fecha_Pago, Monto, Periodo, Metodo_Pago, Notas, Id_Usuario_Registro)
                    VALUES 
                        (?, ?, ?, ?, 'Automático', 'Pago histórico generado automáticamente al registrar becado', ?)
                ");
                $stmtInsert->execute([$id_beca, $fechaPago->format('Y-m-d'), $monto, $periodo, $id_usuario]);
            }

            // Avanzar al siguiente mes
            $mesActual->modify('first day of next month');
        }
    }
}

        echo json_encode([
            'success'    => true,
            'message'    => $out['msg'],
            'expediente' => $out['exp'],
            'id'         => $out['id_est'],
            'id_beca'    => $out['id_beca'],
        ]);

    } catch (Exception $e) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

// =============================================
// LÓGICA NORMAL DE LA PÁGINA
// =============================================
$username = $_SESSION['username'] ?? 'Usuario';
$role     = $_SESSION['role']     ?? 'Administrador';
$user_id  = $_SESSION['user_id'];

$filtro_estado   = $_GET['estado']   ?? 'Todas';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_año      = $_GET['año']      ?? date('Y');

$sql = "SELECT
            e.Id_Estudiante, e.Numero_Expediente, e.Nombres_Apellidos,
            e.Email, e.Telefono, e.Grado_Obtenido_Anterior,
            b.Id_Beca, b.Tipo_Beca, b.Monto_Mensual, b.Estado_Beca,
            b.Fecha_Inicio, b.Fecha_Fin, b.Promedio_Minimo, b.Promedio_Actual,
            (SELECT COUNT(*) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca AND YEAR(Fecha_Pago) = ?) as Pagos_Realizados,
            (SELECT MAX(Fecha_Pago) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca) as Ultimo_Pago,
            (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones WHERE Id_Estudiante = e.Id_Estudiante) as Ultima_Boleta,
            (SELECT Promedio FROM Boletas_Calificaciones WHERE Id_Estudiante = e.Id_Estudiante ORDER BY Fecha_Subida DESC LIMIT 1) as Ultimo_Promedio
        FROM Estudiantes e
        INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
        WHERE 1=1";

$params = [$filtro_año];

if ($filtro_estado !== 'Todas') {
    $sql .= " AND b.Estado_Beca = ?";
    $params[] = $filtro_estado;
}
if (!empty($filtro_busqueda)) {
    $sql .= " AND (e.Nombres_Apellidos LIKE ? OR e.Numero_Expediente LIKE ?)";
    $params[] = "%$filtro_busqueda%";
    $params[] = "%$filtro_busqueda%";
}
$sql .= " ORDER BY e.Nombres_Apellidos ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$becados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_stats = $pdo->prepare("SELECT
    COUNT(*) as Total_Becados,
    COUNT(CASE WHEN Estado_Beca = 'Activa' THEN 1 END) as Becas_Activas,
    COUNT(CASE WHEN Estado_Beca = 'Suspendida' THEN 1 END) as Becas_Suspendidas,
    COUNT(CASE WHEN Estado_Beca = 'Finalizada' THEN 1 END) as Becas_Finalizadas,
    SUM(CASE WHEN Estado_Beca = 'Activa' THEN Monto_Mensual ELSE 0 END) as Monto_Mensual_Total
  FROM Becas_Otorgadas");
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
/*
$stmt_alertas = $pdo->prepare("SELECT
    e.Id_Estudiante, e.Nombres_Apellidos, b.Promedio_Minimo, b.Promedio_Actual,
    CASE WHEN (SELECT MAX(Fecha_Pago) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca) IS NULL
         THEN DATEDIFF(NOW(), b.Fecha_Inicio)
         ELSE DATEDIFF(NOW(), (SELECT MAX(Fecha_Pago) FROM Pagos_Becas WHERE Id_Beca = b.Id_Beca))
    END as Dias_Sin_Pago,
    CASE WHEN (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones WHERE Id_Estudiante = e.Id_Estudiante) IS NULL
         THEN DATEDIFF(NOW(), b.Fecha_Inicio)
         ELSE DATEDIFF(NOW(), (SELECT MAX(Fecha_Subida) FROM Boletas_Calificaciones WHERE Id_Estudiante = e.Id_Estudiante))
    END as Dias_Sin_Boleta
  FROM Estudiantes e
  INNER JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
  WHERE b.Estado_Beca = 'Activa'
  HAVING (b.Promedio_Actual IS NOT NULL AND b.Promedio_Actual < b.Promedio_Minimo)
      OR Dias_Sin_Pago > 45 OR Dias_Sin_Boleta > 90");
$stmt_alertas->execute();
$alertas = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC); */

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(mb_substr($part, 0, 1));
    }
    return $initials ?: '?';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes Becados - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/becados.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    /* ============================================================
       MODAL — OVERLAY (estilos completos)
    ============================================================ */
    #modalNuevoBecado {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(10, 25, 50, .75);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 99999;
        overflow-y: auto;
        align-items: flex-start;
        justify-content: center;
        padding: 1.5rem 1rem;
        box-sizing: border-box;
    }
    #modalNuevoBecado.open { display: flex; }

    /* ============================================================
       PANEL
    ============================================================ */
    .modal-panel {
        background: #fff;
        border-radius: 16px;
        width: 100%;
        max-width: 860px;
        max-height: calc(200vh - 3rem);
        height: auto;
        display: flex;
        flex-direction: column;
        box-shadow: 0 24px 80px rgba(0, 0, 0, .4);
        animation: slideUp .28s ease;
        overflow: hidden;
        position: relative;
        margin: auto;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(32px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Header ── */
    .modal-header {
        background: linear-gradient(135deg, #1a4b8c 0%, #2563c7 100%);
        color: #fff;
        padding: 1.25rem 1.6rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        flex-shrink: 0;
    }
    .modal-header h3 { margin: 0; font-size: 1.15rem; font-weight: 700; }

    .modal-close {
        margin-left: auto;
        background: rgba(255,255,255,.18);
        border: none; color: #fff;
        width: 34px; height: 34px;
        border-radius: 50%;
        cursor: pointer; font-size: 1.1rem;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s;
    }
    .modal-close:hover { background: rgba(255,255,255,.32); }

    /* ── Tabs ── */
    .modal-tabs {
        display: flex;
        border-bottom: 2px solid #e8eef6;
        background: #f4f7fc;
        flex-shrink: 0;
        overflow-x: auto;
    }
    .modal-tab {
        padding: .75rem 1.4rem;
        font-size: .85rem; font-weight: 600; color: #7a8fa6;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all .2s;
        display: flex; align-items: center; gap: .4rem;
        background: none;
        border-top: none; border-left: none; border-right: none;
        white-space: nowrap;
    }
    .modal-tab.active { color: #1a4b8c; border-bottom-color: #2563c7; }
    .modal-tab:hover:not(.active) { color: #2563c7; background: #eaf0fb; }

    /* ── Body con scroll ── */
    .modal-body {
        padding: 1.5rem 1.6rem;
        overflow-y: auto;
        flex: 1 1 0;
        min-height: 180px;
    }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── Footer fijo ── */
    .modal-footer {
        padding: 1rem 1.6rem;
        border-top: 1.5px solid #e8eef6;
        display: flex; align-items: center; gap: .75rem; justify-content: flex-end;
        flex-shrink: 0;
        background: #f9fbff;
    }

    /* ── Grid ── */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.4rem; }
    .col-span-2 { grid-column: span 2; }

    /* ── Campos ── */
    .form-group { display: flex; flex-direction: column; gap: .3rem; }
    .form-group label {
        font-size: .78rem; font-weight: 700; color: #4a607a;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .form-group label .req { color: #e53e3e; margin-left: 2px; }
    .form-group input,
    .form-group select,
    .form-group textarea {
        border: 1.5px solid #d2dce8;
        border-radius: 8px;
        padding: .6rem .85rem;
        font-size: .9rem; color: #1e2d3d;
        transition: border-color .2s, box-shadow .2s;
        background: #fff;
        width: 100%; box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #2563c7;
        box-shadow: 0 0 0 3px rgba(37,99,199,.12);
        outline: none;
    }
    .form-group textarea { resize: vertical; min-height: 72px; }
    .field-hint { font-size: .74rem; color: #8fa3bb; margin-top: 1px; }

    /* ── Foto ── */
    .foto-upload-area {
        border: 2px dashed #b0c4de;
        border-radius: 10px;
        padding: .6rem 1rem;
        cursor: pointer;
        transition: all .2s;
        background: #f8fbff;
        position: relative;
        display: flex;
        align-items: center;
        gap: .9rem;
    }
    .foto-upload-area:hover { border-color: #2563c7; background: #eef4ff; }
    .foto-upload-area input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer;
        width: 100%; height: 100%;
    }
    .foto-preview {
        width: 52px; height: 52px; border-radius: 50%; object-fit: cover;
        border: 2px solid #2563c7; flex-shrink: 0; display: none;
    }
    .foto-upload-icon { font-size: 1.5rem; color: #90aac8; flex-shrink: 0; }
    .foto-upload-area p { margin: 0; font-size: .8rem; color: #7a8fa6; line-height: 1.4; }

    /* ── Títulos de sección ── */
    .section-title {
        font-size: .8rem; font-weight: 800; color: #2563c7;
        text-transform: uppercase; letter-spacing: .06em;
        margin: 1.2rem 0 .8rem;
        padding-bottom: .4rem;
        border-bottom: 2px solid #e0eaf8;
        display: flex; align-items: center; gap: .5rem;
    }
    .section-title:first-child { margin-top: 0; }

    /* ── Botones del footer ── */
    .btn-modal-cancel {
        background: #fff; border: 1.5px solid #d2dce8; color: #4a607a;
        padding: .6rem 1.4rem; border-radius: 8px; font-weight: 600;
        cursor: pointer; font-size: .9rem; transition: all .2s;
    }
    .btn-modal-cancel:hover { background: #f0f4fa; }

    .btn-modal-save {
        background: linear-gradient(135deg, #1a4b8c, #2563c7);
        color: #fff; border: none; padding: .6rem 1.7rem; border-radius: 8px;
        font-weight: 700; font-size: .9rem; cursor: pointer;
        display: flex; align-items: center; gap: .5rem;
        transition: opacity .2s, transform .15s;
    }
    .btn-modal-save:hover   { opacity: .9; transform: translateY(-1px); }
    .btn-modal-save:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    /* ── Validación ── */
    .form-group input.invalid,
    .form-group select.invalid,
    .form-group textarea.invalid { border-color: #ef4444 !important; }
    .error-msg { font-size: .75rem; color: #ef4444; display: none; margin-top: 2px; }
    .form-group.has-error .error-msg { display: block; }

    /* ── Info box ── */
    .info-box {
        background: #f0f6ff;
        border-radius: 10px;
        padding: 1rem 1.2rem;
        margin-top: 1rem;
        border: 1px solid #c8daf8;
        font-size: .84rem;
        color: #2a508c;
        display: flex;
        align-items: flex-start;
        gap: .5rem;
    }
    .info-box i { margin-top: 2px; flex-shrink: 0; }

    /* ── Toast ── */
    #toastBecado {
        position: fixed; bottom: 1.5rem; right: 1.5rem;
        background: #fff; border-radius: 12px; padding: 1rem 1.4rem;
        box-shadow: 0 8px 32px rgba(0,0,0,.18);
        display: flex; align-items: center; gap: .75rem;
        z-index: 999999; min-width: 280px; max-width: 400px;
        transform: translateY(120%); transition: transform .3s ease;
        border-left: 4px solid #2563c7;
    }
    #toastBecado.show    { transform: translateY(0); }
    #toastBecado.success { border-left-color: #22c55e; }
    #toastBecado.error   { border-left-color: #ef4444; }
    .toast-msg { font-size: .88rem; color: #2d3f55; font-weight: 500; flex: 1; }

    /* ── Responsive ── */
    @media (max-width: 640px) {
        #modalNuevoBecado { padding: .5rem; }
        .modal-panel { max-height: calc(100vh - 1rem); border-radius: 10px; margin: 0; }
        .form-grid { grid-template-columns: 1fr; }
        .col-span-2 { grid-column: span 1; }
        .modal-body { padding: 1rem; }
        .modal-footer { flex-wrap: wrap; gap: .5rem; }
        .btn-modal-cancel,
        .btn-modal-save { flex: 1; justify-content: center; }
    }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <main class="main-content">

            <!-- Header -->
            <div class="header">
                <h1>Estudiantes Becados</h1>
                <div class="user-info">
                    <div class="user-avatar-wrapper">
                        <?php
                            $iconClass = match($role) {
                                'Administrador' => 'fa-solid fa-crown',
                                'Coordinador'   => 'fa-solid fa-user-tie',
                                default         => 'fa-solid fa-user',
                            };
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

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Total_Becados'] ?></h3>
                        <p>Total Estudiantes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Becas_Activas'] ?></h3>
                        <p>Becas Activas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-pause-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Becas_Suspendidas'] ?></h3>
                        <p>Becas Suspendidas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-info">
                        <h3>Q<?= number_format($stats['Monto_Mensual_Total'], 0) ?></h3>
                        <p>Monto Mensual Total</p>
                    </div>
                </div>
            </div>
 
            <!-- Alertas -->
           

            <!-- Filtros -->
            <div class="filtros">
                <form method="GET" action="">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label><i class="fas fa-search"></i> Buscar Estudiante</label>
                            <input type="text" name="busqueda"
                                   placeholder="Nombre o número de expediente..."
                                   value="<?= htmlspecialchars($filtro_busqueda) ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Estado de Beca</label>
                            <select name="estado">
                                <option value="Todas"      <?= $filtro_estado==='Todas'     ?'selected':'' ?>>Todas</option>
                                <option value="Activa"     <?= $filtro_estado==='Activa'    ?'selected':'' ?>>Activas</option>
                                <option value="Suspendida" <?= $filtro_estado==='Suspendida'?'selected':'' ?>>Suspendidas</option>
                                <option value="Finalizada" <?= $filtro_estado==='Finalizada'?'selected':'' ?>>Finalizadas</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Año</label>
                            <select name="año">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $filtro_año==$y?'selected':'' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla -->
            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-table"></i>
                        Lista de Estudiantes (<?= count($becados) ?>)
                    </h2>
                    <div style="display:flex;gap:.6rem;align-items:center;">
                        <a href="exportar_becados.php" class="btn btn-secondary" onclick="return confirmExport()">
                            <i class="fas fa-file-excel"></i> Exportar
                        </a>
                        <button type="button" class="btn btn-primary" id="btnNuevoBecado">
                            <i class="fas fa-user-plus"></i> Nuevo Becado
                        </button>
                    </div>
                </div>

                <?php if (count($becados) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Tipo de Beca</th>
                            <th>Monto Mensual</th>
                            <th>Estado</th>
                            <th>Pagos <?= $filtro_año ?></th>
                            <th>Promedio</th>
                            <th>Última Actividad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($becados as $estudiante): ?>
                        <tr>
                            <td>
                                <div class="estudiante-info">
                                    <span class="estudiante-nombre">
                                        <?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?>
                                    </span>
                                    <span class="estudiante-detalles">
                                        Exp: <?= htmlspecialchars($estudiante['Numero_Expediente']) ?> |
                                        <?= htmlspecialchars($estudiante['Grado_Obtenido_Anterior']) ?>
                                    </span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($estudiante['Tipo_Beca'] ?? 'Sin asignar') ?></td>
                            <td><strong>Q<?= number_format($estudiante['Monto_Mensual'], 2) ?></strong></td>
                            <td>
                                <span class="badge badge-<?= $estudiante['Estado_Beca']==='Activa' ? 'success' : ($estudiante['Estado_Beca']==='Suspendida' ? 'warning' : 'info') ?>">
                                    <?= $estudiante['Estado_Beca'] ?? 'Pendiente' ?>
                                </span>
                            </td>
                            <td><strong><?= $estudiante['Pagos_Realizados'] ?></strong> / 12 meses</td>
                            <td>
                                <?php if ($estudiante['Ultimo_Promedio']): ?>
                                    <?php
                                    $clase_promedio = 'bueno';
                                    if ($estudiante['Ultimo_Promedio'] < $estudiante['Promedio_Minimo']) $clase_promedio = 'malo';
                                    elseif ($estudiante['Ultimo_Promedio'] < 80) $clase_promedio = 'regular';
                                    ?>
                                    <span class="promedio <?= $clase_promedio ?>">
                                        <?= number_format($estudiante['Ultimo_Promedio'], 1) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#bdc3c7;">Sin datos</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:.8em;">
                                    <?php if ($estudiante['Ultimo_Pago']): ?>
                                        <div>💰 <?= date('d/m/Y', strtotime($estudiante['Ultimo_Pago'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($estudiante['Ultima_Boleta']): ?>
                                        <div>📄 <?= date('d/m/Y', strtotime($estudiante['Ultima_Boleta'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a href="detalle_becado.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="registrar_pago.php?id_beca=<?= $estudiante['Id_Beca'] ?>" class="btn btn-success">
                                        <i class="fas fa-dollar-sign"></i>
                                    </a>
                                    <a href="subir_boleta.php?id=<?= $estudiante['Id_Estudiante'] ?>" class="btn btn-warning">
                                        <i class="fas fa-upload"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No se encontraron estudiantes</h3>
                    <p>Intenta cambiar los filtros de búsqueda</p>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div><!-- /container -->

    <!-- =================================================================
         MODAL — fuera del .container para evitar conflictos con transform
         ================================================================= -->
    <div id="modalNuevoBecado" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-panel">

            <div class="modal-header">
                <i class="fas fa-user-plus" style="font-size:1.2rem;"></i>
                <h3 id="modalTitle">Registrar Nuevo Becado</h3>
                <button class="modal-close" id="btnCerrarModal" title="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- 4 tabs: Personal · Familiar · Académico · Beca -->
            <div class="modal-tabs">
                <button class="modal-tab active" data-tab="personal">
                    <i class="fas fa-user"></i> Datos Personales
                </button>
                <button class="modal-tab" data-tab="familiar">
                    <i class="fas fa-users"></i> Familiar
                </button>
                <button class="modal-tab" data-tab="academico">
                    <i class="fas fa-graduation-cap"></i> Académico
                </button>
                <button class="modal-tab" data-tab="beca">
                    <i class="fas fa-hand-holding-heart"></i> Beca
                </button>
            </div>

            <form id="formNuevoBecado" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="registrar_becado_rapido">

                <!-- modal-body: único con scroll -->
                <div class="modal-body">

                    <!-- ===== TAB 1: PERSONAL ===== -->
                    <div class="tab-panel active" id="tab-personal">

                        <p class="section-title"><i class="fas fa-camera"></i> Fotografía</p>
                        <div class="foto-upload-area" id="fotoUploadArea">
                            <input type="file" name="foto_becado" id="inputFoto" accept="image/jpeg,image/png,image/webp">
                            <img src="" alt="Preview" class="foto-preview" id="fotoPreview">
                            <div id="fotoPlaceholder" class="foto-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div>
                                <p><strong>Haz clic o arrastra una foto</strong></p>
                                <p>JPG, PNG o WEBP · Máximo 5MB · Opcional</p>
                            </div>
                        </div>

                        <p class="section-title"><i class="fas fa-id-card"></i> Información Personal</p>
                        <div class="form-grid">
                            <div class="form-group col-span-2">
                                <label>Nombres y Apellidos <span class="req">*</span></label>
                                <input type="text" name="nombres_apellidos" id="f_nombres"
                                       placeholder="Ej. Juan Carlos Pérez López" maxlength="150">
                                <span class="error-msg">Ingrese al menos 3 caracteres.</span>
                            </div>
                            <div class="form-group">
                                <label>Edad <span class="req">*</span></label>
                                <input type="number" name="edad" id="f_edad" min="5" max="99" placeholder="Ej. 18">
                                <span class="error-msg">Ingrese una edad válida (5–99).</span>
                            </div>
                            <div class="form-group">
                                <label>Teléfono <span class="req">*</span></label>
                                <input type="tel" name="telefono" id="f_telefono"
                                       placeholder="Ej. 55551234" maxlength="20">
                                <span class="error-msg">Ingrese un teléfono válido.</span>
                            </div>
                            <div class="form-group col-span-2">
                                <label>Correo Electrónico</label>
                                <input type="email" name="email"
                                       placeholder="estudiante@ejemplo.com" maxlength="100">
                                <span class="field-hint">Opcional</span>
                            </div>
                            <div class="form-group col-span-2">
                                <label>Dirección Domiciliar <span class="req">*</span></label>
                                <textarea name="direccion" id="f_direccion"
                                          placeholder="Colonia, calle, número de casa, municipio..."></textarea>
                                <span class="error-msg">La dirección es requerida.</span>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB 2: FAMILIAR ===== -->
                    <div class="tab-panel" id="tab-familiar">

                        <p class="section-title"><i class="fas fa-user-shield"></i> Encargado Principal</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombre del Encargado <span class="req">*</span></label>
                                <input type="text" name="nombre_encargado" id="f_encargado"
                                       placeholder="Nombre completo" maxlength="150">
                                <span class="error-msg">Este campo es requerido.</span>
                            </div>
                            <div class="form-group">
                                <label>Teléfono del Encargado <span class="req">*</span></label>
                                <input type="tel" name="telefono_encargado" id="f_tel_encargado"
                                       placeholder="Ej. 55551234" maxlength="20">
                                <span class="error-msg">Ingrese el teléfono del encargado.</span>
                            </div>
                        </div>

                        <p class="section-title"><i class="fas fa-heart"></i> Padres</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Nombre de la Madre</label>
                                <input type="text" name="nombre_madre"
                                       placeholder="Nombre completo" maxlength="150">
                                <span class="field-hint">Opcional</span>
                            </div>
                            <div class="form-group">
                                <label>Nombre del Padre</label>
                                <input type="text" name="nombre_padre"
                                       placeholder="Nombre completo" maxlength="150">
                                <span class="field-hint">Opcional</span>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB 3: ACADÉMICO ===== -->
                    <div class="tab-panel" id="tab-academico">

                        <p class="section-title"><i class="fas fa-school"></i> Historial Académico</p>
                        <div class="form-grid">
                            <div class="form-group col-span-2">
                                <label>Grado Obtenido Anteriormente <span class="req">*</span></label>
                                <input type="text" name="grado_anterior" id="f_grado"
                                       placeholder="Ej. Tercero Básico, Bachillerato en Computación..."
                                       maxlength="100">
                                <span class="error-msg">Este campo es requerido.</span>
                            </div>
                            <div class="form-group col-span-2">
                                <label>Escuela / Instituto Anterior <span class="req">*</span></label>
                                <input type="text" name="escuela_anterior" id="f_escuela"
                                       placeholder="Nombre completo del establecimiento" maxlength="200">
                                <span class="error-msg">Este campo es requerido.</span>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB 4: BECA ===== -->
                    <div class="tab-panel" id="tab-beca">

                        <p class="section-title"><i class="fas fa-hand-holding-heart"></i> Datos de la Beca</p>
                        <div class="form-grid">
                            <div class="form-group col-span-2">
                                <label>Tipo de Beca <span class="req">*</span></label>
                                <select name="tipo_beca" id="f_tipo_beca">
                                    <option value="">— Seleccione un tipo —</option>
                                    <option value="Completa">Beca Completa</option>
                                    <option value="Parcial">Beca Parcial</option>
                                    <option value="Especial">Beca Especial</option>
                                </select>
                                <span class="error-msg">Seleccione un tipo de beca.</span>
                            </div>

                            <div class="form-group">
                                <label>Monto Mensual (Q) <span class="req">*</span></label>
                                <input type="number" name="monto_mensual" id="f_monto"
                                       min="1" step="0.01" placeholder="Ej. 500.00">
                                <span class="error-msg">Ingrese un monto válido mayor a 0.</span>
                            </div>
                            <div class="form-group">
                                <label>Promedio Mínimo Requerido</label>
                                <input type="number" name="promedio_minimo"
                                       value="70" min="0" max="100" step="0.01">
                                <span class="field-hint">Por defecto: 70</span>
                            </div>
                            <div class="form-group">
                                <label>Fecha de Inicio <span class="req">*</span></label>
                                <input type="date" name="fecha_inicio" id="f_fecha_inicio">
                                <span class="error-msg">La fecha de inicio es requerida.</span>
                            </div>
                            <div class="form-group">
                                <label>Fecha de Fin</label>
                                <input type="date" name="fecha_fin">
                                <span class="field-hint">Opcional — dejar en blanco si es indefinida</span>
                            </div>
                            <div class="form-group col-span-2">
                                <label>Socio Rotario Avalador</label>
                                <input type="text" name="socio_rotario"
                                       placeholder="Nombre del socio que avala" maxlength="150">
                                <span class="field-hint">Opcional</span>
                            </div>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <span>
                                Al guardar, el sistema creará automáticamente la
                                <strong>evaluación socioeconómica</strong> en estado
                                <strong>Aprobado</strong> y la <strong>beca activa</strong>.
                                No necesitas pasar por el flujo normal de solicitud.
                            </span>
                        </div>
                    </div>

                </div><!-- /modal-body -->

                <!-- Footer siempre visible -->
                <div class="modal-footer">
                    <span id="tabIndicator" style="font-size:.82rem;color:#7a8fa6;margin-right:auto;">
                        Paso 1 de 4
                    </span>
                    <button type="button" class="btn-modal-cancel"
                            id="btnAnteriorTab" style="display:none;">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn-modal-cancel" id="btnCancelarModal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modal-save" id="btnSiguienteTab">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn-modal-save"
                            id="btnGuardar" style="display:none;">
                        <i class="fas fa-save"></i> Guardar Becado
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Toast -->
    <div id="toastBecado">
        <span class="toast-msg" id="toastMsg"></span>
    </div>

    <!-- =================================================================
         JAVASCRIPT
         ================================================================= -->
    <script>
    function confirmExport() {
        event.preventDefault();
        const url = event.target.href;
        Swal.fire({
            title: '¿Exportar datos?',
            text: 'Se generará un archivo Excel con la información de los estudiantes becados.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, exportar',
            cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) window.location.href = url; });
        return false;
    }

    document.addEventListener('DOMContentLoaded', function () {

        // Notificación de filtros aplicados
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('busqueda') || urlParams.has('estado') || urlParams.has('año')) {
            Swal.fire({
                title: 'Filtros aplicados',
                text: 'Se han aplicado los filtros de búsqueda.',
                icon: 'info', timer: 3000, showConfirmButton: false
            });
        }

        /* ── Referencias al DOM ── */
        const TOTAL_TABS   = 4;
        const modal        = document.getElementById('modalNuevoBecado');
        const form         = document.getElementById('formNuevoBecado');
        const tabs         = document.querySelectorAll('.modal-tab');
        const panels       = document.querySelectorAll('.tab-panel');
        const btnAbrir     = document.getElementById('btnNuevoBecado');
        const btnCerrar    = document.getElementById('btnCerrarModal');
        const btnCancelar  = document.getElementById('btnCancelarModal');
        const btnSiguiente = document.getElementById('btnSiguienteTab');
        const btnAnterior  = document.getElementById('btnAnteriorTab');
        const btnGuardar   = document.getElementById('btnGuardar');
        const tabIndicator = document.getElementById('tabIndicator');

        let currentTab = 0;

        /* ── Abrir modal ── */
        btnAbrir.addEventListener('click', () => {
            modal.classList.add('open');
            resetForm();
            goToTab(0);
            document.body.style.overflow = 'hidden';
            modal.scrollTop = 0;
        });

        /* ── Cerrar modal ── */
        [btnCerrar, btnCancelar].forEach(b => b.addEventListener('click', cerrarModal));
        modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });

        function cerrarModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        /* ── Navegación entre tabs ── */
        function goToTab(idx) {
            currentTab = idx;
            tabs.forEach((t, i)   => t.classList.toggle('active', i === idx));
            panels.forEach((p, i) => p.classList.toggle('active', i === idx));
            tabIndicator.textContent   = `Paso ${idx + 1} de ${TOTAL_TABS}`;
            btnAnterior.style.display  = idx > 0              ? 'inline-flex' : 'none';
            btnSiguiente.style.display = idx < TOTAL_TABS - 1 ? 'inline-flex' : 'none';
            btnGuardar.style.display   = idx === TOTAL_TABS - 1 ? 'inline-flex' : 'none';
            const body = document.querySelector('.modal-body');
            if (body) body.scrollTop = 0;
        }

        // Clic en tab solo permite retroceder
        tabs.forEach((tab, idx) => {
            tab.addEventListener('click', () => { if (idx < currentTab) goToTab(idx); });
        });

        btnSiguiente.addEventListener('click', () => {
            if (validateTab(currentTab)) goToTab(currentTab + 1);
        });
        btnAnterior.addEventListener('click', () => {
            if (currentTab > 0) goToTab(currentTab - 1);
        });

        /* ── Validación por tab ── */
        function validateTab(idx) {
            let ok = true;

            // TAB 0: Personal
            if (idx === 0) {
                ok = chk('f_nombres',   v => v.trim().length >= 3)       && ok;
                ok = chk('f_edad',      v => +v >= 5 && +v <= 99)        && ok;
                ok = chk('f_telefono',  v => v.trim().length >= 7)       && ok;
                ok = chk('f_direccion', v => v.trim().length >= 5)       && ok;
            }

            // TAB 1: Familiar
            if (idx === 1) {
                ok = chk('f_encargado',    v => v.trim().length >= 3)    && ok;
                ok = chk('f_tel_encargado',v => v.trim().length >= 7)    && ok;
            }

            // TAB 2: Académico
            if (idx === 2) {
                ok = chk('f_grado',   v => v.trim().length >= 3)         && ok;
                ok = chk('f_escuela', v => v.trim().length >= 3)         && ok;
            }

            // TAB 3: Beca
            if (idx === 3) {
                ok = chk('f_tipo_beca',   v => v.trim().length >= 2)     && ok;
                ok = chk('f_monto',       v => parseFloat(v) > 0)        && ok;
                ok = chk('f_fecha_inicio',v => v !== '')                  && ok;
            }

            return ok;
        }

        function chk(id, fn) {
            const el = document.getElementById(id);
            if (!el) return true;
            const valid = fn(el.value);
            el.classList.toggle('invalid', !valid);
            el.closest('.form-group').classList.toggle('has-error', !valid);
            return valid;
        }

        // Limpiar error al escribir
        form.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', () => {
                el.classList.remove('invalid');
                el.closest('.form-group')?.classList.remove('has-error');
            });
        });

        /* ── Preview de foto ── */
        const inputFoto   = document.getElementById('inputFoto');
        const fotoPreview = document.getElementById('fotoPreview');
        const fotoPH      = document.getElementById('fotoPlaceholder');

        inputFoto.addEventListener('change', () => {
            const file = inputFoto.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                fotoPreview.src = e.target.result;
                fotoPreview.style.display = 'block';
                fotoPH.style.display = 'none';
            };
            reader.readAsDataURL(file);
        });

        const area = document.getElementById('fotoUploadArea');
        area.addEventListener('dragover',  e => { e.preventDefault(); area.style.borderColor = '#2563c7'; });
        area.addEventListener('dragleave', ()  => { area.style.borderColor = '#b0c4de'; });
        area.addEventListener('drop', e => {
            e.preventDefault();
            area.style.borderColor = '#b0c4de';
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                const dt = new DataTransfer();
                dt.items.add(file);
                inputFoto.files = dt.files;
                inputFoto.dispatchEvent(new Event('change'));
            }
        });

        /* ── Envío AJAX ── */
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Validar todos los tabs antes de enviar
            let allOk = true;
            for (let i = 0; i < TOTAL_TABS; i++) {
                if (!validateTab(i)) {
                    allOk = false;
                    goToTab(i);
                    break;
                }
            }
            if (!allOk) return;

            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                const resp = await fetch(window.location.href, {
                    method: 'POST',
                    body: new FormData(form)
                });
                const json = await resp.json();

                if (json.success) {
                    showToast('success', '✅ ' + json.message);
                    cerrarModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast('error', '❌ ' + json.message);
                }
            } catch {
                showToast('error', '❌ Error de conexión. Intente de nuevo.');
            } finally {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Becado';
            }
        });

        /* ── Toast ── */
        let toastTimer = null;
        function showToast(type, msg) {
            const t = document.getElementById('toastBecado');
            t.className = 'show ' + type;
            document.getElementById('toastMsg').textContent = msg;
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { t.className = ''; }, 4500);
        }

        /* ── Reset del formulario ── */
        function resetForm() {
            form.reset();
            fotoPreview.style.display = 'none';
            fotoPreview.src = '';
            fotoPH.style.display = 'block';
            form.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
            form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
        }
    });
    </script>
</body>
</html>