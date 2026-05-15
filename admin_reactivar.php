<?php
session_start();

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

$id_estudiante = $_GET['id'] ?? null;

if (!$id_estudiante) {
    $_SESSION['error'] = 'ID de estudiante no especificado';
    header('Location: reactivar.php');
    exit;
}

// Obtener información del estudiante
try {
    $sql_estudiante = "SELECT 
                          e.*,
                          ev.Id_Evaluacion,
                          ev.Estado_Evaluacion,
                          ev.Motivo_Rechazo,
                          ev.Fecha_Evaluacion,
                          b.Id_Beca,
                          b.Estado_Beca,
                          b.Motivo_Suspension,
                          b.Fecha_Suspension
                       FROM Estudiantes e
                       LEFT JOIN Evaluaciones_Socioeconomicas ev ON e.Id_Estudiante = ev.Id_Estudiante
                       LEFT JOIN Becas_Otorgadas b ON e.Id_Estudiante = b.Id_Estudiante
                       WHERE e.Id_Estudiante = ?";
    
    $stmt = $pdo->prepare($sql_estudiante);
    $stmt->execute([$id_estudiante]);
    $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$estudiante) {
        $_SESSION['error'] = 'Estudiante no encontrado';
        header('Location: reactivar.php');
        exit;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error al cargar datos: ' . $e->getMessage();
    header('Location: reactivar.php');
    exit;
}

// Procesar formulario de reactivación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $motivo_reactivacion = $_POST['motivo_reactivacion'] ?? '';
        $nueva_evaluacion = $_POST['crear_evaluacion'] ?? 'no';
        $programar_cita = $_POST['programar_cita'] ?? 'no';
        
        if (empty($motivo_reactivacion)) {
            throw new Exception('El motivo de reactivación es obligatorio');
        }
        
        // 1. Actualizar estado del estudiante
        if ($estudiante['Estado_Evaluacion'] === 'Rechazado') {
            // Si fue rechazado, cambiar evaluación a pendiente
            $sql_update_eval = "UPDATE Evaluaciones_Socioeconomicas 
                               SET Estado_Evaluacion = 'Pendiente',
                                   Motivo_Rechazo = NULL
                               WHERE Id_Evaluacion = ?";
            $stmt_eval = $pdo->prepare($sql_update_eval);
            $stmt_eval->execute([$estudiante['Id_Evaluacion']]);
        }
        
        // Actualizar estado del estudiante a Activo
        $sql_update_est = "UPDATE Estudiantes 
                          SET Estado_Estudiante = 'Activo'
                          WHERE Id_Estudiante = ?";
        $stmt_est = $pdo->prepare($sql_update_est);
        $stmt_est->execute([$id_estudiante]);
        
        // 2. Si tiene beca suspendida, reactivarla
        if ($estudiante['Id_Beca'] && $estudiante['Estado_Beca'] === 'Suspendida') {
            $sql_update_beca = "UPDATE Becas_Otorgadas 
                               SET Estado_Beca = 'Activa',
                                   Motivo_Suspension = NULL,
                                   Fecha_Suspension = NULL
                               WHERE Id_Beca = ?";
            $stmt_beca = $pdo->prepare($sql_update_beca);
            $stmt_beca->execute([$estudiante['Id_Beca']]);
        }
        
        // 3. Crear nueva evaluación si se solicitó
        if ($nueva_evaluacion === 'si') {
            $sql_nueva_eval = "INSERT INTO Evaluaciones_Socioeconomicas 
                              (Id_Estudiante, Fecha_Evaluacion, Estado_Evaluacion, Id_Usuario_Evaluador)
                              VALUES (?, CURDATE(), 'Pendiente', ?)";
            $stmt_nueva = $pdo->prepare($sql_nueva_eval);
            $stmt_nueva->execute([$id_estudiante, $user_id]);
            
            $nueva_eval_id = $pdo->lastInsertId();
            
            // 4. Programar cita si se solicitó
            if ($programar_cita === 'si') {
                // Obtener siguiente fecha/hora disponible
                $sql_cita = "CALL sp_obtener_siguiente_cita_disponible(@fecha, @hora)";
                $pdo->query($sql_cita);
                
                $result = $pdo->query("SELECT @fecha as fecha, @hora as hora")->fetch();
                
                $sql_insert_cita = "INSERT INTO Citas_Entrevista 
                                   (Id_Evaluacion, Id_Estudiante, Fecha_Cita, Hora_Cita, Estado_Cita)
                                   VALUES (?, ?, ?, ?, 'Programada')";
                $stmt_cita = $pdo->prepare($sql_insert_cita);
                $stmt_cita->execute([
                    $nueva_eval_id,
                    $id_estudiante,
                    $result['fecha'],
                    $result['hora']
                ]);
            }
        }
        
        // 5. Registrar en bitácora
        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                        VALUES (?, ?, CURDATE())";
        $actividad = "Reactivó estudiante: {$estudiante['Nombres_Apellidos']} (ID: {$id_estudiante}). Motivo: {$motivo_reactivacion}";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $stmt_bitacora->execute([$user_id, $actividad]);
        
        $pdo->commit();
        
        $_SESSION['success'] = '¡Estudiante reactivado exitosamente!';
        header('Location: reactivar.php?vista=historial');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = 'Error de base de datos: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Función para obtener iniciales del usuario
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivar Estudiante - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content { 
            flex: 1; 
            min-height: 100vh;
            margin-left: 0;
        }
        
        .header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 { 
            font-size: 28px; 
            color: #2c3e50; 
            font-weight: 600; 
        }
        
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        
        .user-avatar-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .user-role-icon {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: #ffc107;
            color: #000;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
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
        
        .user-details-main { 
            text-align: right; 
        }
        
        .user-name-main { 
            font-weight: 600; 
            color: #2c3e50; 
            font-size: 14px; 
        }
        
        .user-role-main { 
            font-size: 12px; 
            color: #7f8c8d; 
        }
        
        .content-container { 
            padding: 30px 40px; 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        
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

        .breadcrumb a:hover { color: #2980b9; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .info-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 15px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.rechazado { background: #f8d7da; color: #721c24; }
        .status-badge.suspendido { background: #fff3cd; color: #856404; }
        .status-badge.retirado { background: #e2e3e5; color: #383d41; }
        
        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .alert-box h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-box p {
            color: #856404;
            line-height: 1.6;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-section:last-child { border-bottom: none; }
        
        .form-section h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        .form-group textarea,
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
            color: #2c3e50;
        }
        
        .form-group textarea:focus,
        .form-group input:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }
        
        .form-group .hint {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
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
            color: #2c3e50;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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
        }
        
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .content-container { padding: 20px; }
            .header { padding: 15px 20px; flex-direction: column; gap: 15px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Reactivar Estudiante</h1>
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
            
            <div class="content-container">
                <div class="breadcrumb">
                    <a href="admin.php"><i class="fas fa-home"></i> Inicio</a> /
                    <a href="reactivar.php">Reactivaciones</a> /
                    Reactivar
                </div>

                <!-- Información del Estudiante -->
                <div class="info-card">
                    <h2><i class="fas fa-user"></i> Información del Estudiante</h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Expediente</span>
                            <span class="info-value"><?= htmlspecialchars($estudiante['Numero_Expediente'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nombre Completo</span>
                            <span class="info-value"><?= htmlspecialchars($estudiante['Nombres_Apellidos']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($estudiante['Email']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Teléfono</span>
                            <span class="info-value"><?= htmlspecialchars($estudiante['Telefono']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Estado Actual</span>
                            <span class="info-value">
                                <span class="status-badge <?= strtolower($estudiante['Estado_Estudiante']) ?>">
                                    <?= htmlspecialchars($estudiante['Estado_Estudiante']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fecha de Registro</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($estudiante['Fecha_Registro'])) ?></span>
                        </div>
                    </div>

                    <?php if ($estudiante['Estado_Evaluacion'] === 'Rechazado' && $estudiante['Motivo_Rechazo']): ?>
                    <div class="alert-box">
                        <h3><i class="fas fa-info-circle"></i> Motivo del Rechazo</h3>
                        <p><?= nl2br(htmlspecialchars($estudiante['Motivo_Rechazo'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($estudiante['Estado_Beca'] === 'Suspendida' && $estudiante['Motivo_Suspension']): ?>
                    <div class="alert-box">
                        <h3><i class="fas fa-info-circle"></i> Motivo de la Suspensión</h3>
                        <p><?= nl2br(htmlspecialchars($estudiante['Motivo_Suspension'])) ?></p>
                        <p style="margin-top: 10px;"><strong>Fecha de suspensión:</strong> <?= date('d/m/Y', strtotime($estudiante['Fecha_Suspension'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Formulario de Reactivación -->
                <div class="form-card">
                    <form method="POST" action="" id="reactivarForm">
                        <div class="form-section">
                            <h3><i class="fas fa-edit"></i> Información de Reactivación</h3>
                            
                            <div class="form-group">
                                <label>
                                    Motivo de la Reactivación <span class="required">*</span>
                                </label>
                                <textarea name="motivo_reactivacion" 
                                          placeholder="Explica detalladamente por qué se está reactivando este estudiante..."
                                          required></textarea>
                                <div class="hint">
                                    Este motivo quedará registrado en la bitácora del sistema
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3><i class="fas fa-cog"></i> Opciones de Reactivación</h3>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" 
                                       id="crear_evaluacion" 
                                       name="crear_evaluacion" 
                                       value="si">
                                <label for="crear_evaluacion">
                                    <strong>Crear nueva evaluación socioeconómica</strong>
                                    <div class="hint" style="margin-top: 3px;">Genera una nueva evaluación pendiente para este estudiante</div>
                                </label>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" 
                                       id="programar_cita" 
                                       name="programar_cita" 
                                       value="si">
                                <label for="programar_cita">
                                    <strong>Programar cita de entrevista automáticamente</strong>
                                    <div class="hint" style="margin-top: 3px;">Se asignará la siguiente fecha/hora disponible</div>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success" id="btnReactivar">
                                <i class="fas fa-check"></i>
                                Reactivar Estudiante
                            </button>
                            <a href="reactivar.php" class="btn btn-secondary" id="btnCancelar">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('crear_evaluacion').addEventListener('change', function() {
            const programarCita = document.getElementById('programar_cita');
            if (!this.checked) {
                programarCita.checked = false;
                programarCita.disabled = true;
            } else {
                programarCita.disabled = false;
            }
        });

        // SweetAlert para confirmación de reactivación
        document.getElementById('reactivarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const motivo = document.querySelector('textarea[name="motivo_reactivacion"]').value;
            if (!motivo.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: 'Por favor ingresa el motivo de la reactivación',
                    confirmButtonColor: '#004b87'
                });
                return;
            }
            
            Swal.fire({
                title: '¿Confirmar reactivación?',
                html: `Estás a punto de reactivar al estudiante:<br><strong>${'<?= htmlspecialchars($estudiante["Nombres_Apellidos"]) ?>'}</strong>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, reactivar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    const btn = document.getElementById('btnReactivar');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    
                    // Enviar formulario
                    e.target.submit();
                }
            });
        });

        // SweetAlert para cancelar
        document.getElementById('btnCancelar').addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.href;
            
            Swal.fire({
                title: '¿Cancelar reactivación?',
                text: 'Los cambios no guardados se perderán',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004b87',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'Continuar editando'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });

        // Mostrar SweetAlert si hay mensajes de PHP
        <?php if ($mensaje): ?>
            Swal.fire({
                icon: '<?= $tipo_mensaje === 'error' ? 'error' : 'success' ?>',
                title: '<?= $tipo_mensaje === 'error' ? 'Error' : 'Éxito' ?>',
                text: '<?= addslashes($mensaje) ?>',
                confirmButtonColor: '#004b87'
            });
        <?php endif; ?>

        // Mostrar notificación si hay parámetros de éxito/error en la URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: urlParams.get('success'),
                    timer: 3000,
                    showConfirmButton: false
                });
            }
            if (urlParams.has('error')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: urlParams.get('error'),
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    </script>
</body>
</html>