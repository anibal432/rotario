<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

require_once 'actualizar_estados_eventos.php';
$eventos_actualizados = actualizarEstadosEventos($pdo);

$mensaje_actualizacion = null;
if ($eventos_actualizados > 0) {
    $mensaje_actualizacion = "Se actualizaron automáticamente {$eventos_actualizados} evento(s)";
}

$filtro_estado = $_GET['estado'] ?? 'Todos';
$filtro_año = $_GET['año'] ?? date('Y');
$vista = $_GET['vista'] ?? 'activos';

$sql = "SELECT 
            e.*,
            te.Nombre as Tipo_Evento_Nombre,
            (SELECT COUNT(*) FROM Inscripciones_Evento WHERE Id_Evento = e.Id_Evento) as Total_Inscritos,
            (SELECT COUNT(*) FROM Inscripciones_Evento WHERE Id_Evento = e.Id_Evento AND Estado_Inscripcion = 'Confirmado') as Inscritos_Confirmados,
            (SELECT COUNT(*) FROM Inscripciones_Evento WHERE Id_Evento = e.Id_Evento AND Estado_Inscripcion = 'Pendiente') as Inscritos_Pendientes,
            (SELECT COUNT(*) FROM Categorias_Evento WHERE Id_Evento = e.Id_Evento) as Total_Categorias,
            (SELECT SUM(Monto_Pagado) FROM Inscripciones_Evento WHERE Id_Evento = e.Id_Evento AND Estado_Pago = 'Aprobado') as Total_Recaudado,
            e_nuevo.Nombre_Evento as Evento_Reactivado_Nombre,
            e_nuevo.Id_Evento as Evento_Reactivado_Id,
            e_nuevo.Fecha_Evento as Evento_Reactivado_Fecha
        FROM Eventos e
        LEFT JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
        LEFT JOIN Eventos e_nuevo ON e_nuevo.Id_Evento_Origen = e.Id_Evento
        WHERE 1=1";

$params = [];

if ($vista === 'historial') {
    $sql .= " AND e.Archivado = 1";
} else {
    $sql .= " AND e.Archivado = 0";
}

if ($filtro_estado !== 'Todos') {
    $sql .= " AND e.Estado_Evento = ?";
    $params[] = $filtro_estado;
}

if ($filtro_año !== 'Todos') {
    $sql .= " AND YEAR(e.Fecha_Evento) = ?";
    $params[] = $filtro_año;
}

$sql .= " ORDER BY e.Fecha_Evento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_stats = "SELECT 
                COUNT(*) as Total_Eventos,
                COUNT(CASE WHEN Estado_Evento IN ('Planificado', 'Inscripciones Abiertas') THEN 1 END) as Eventos_Activos,
                COUNT(CASE WHEN Estado_Evento = 'Finalizado' THEN 1 END) as Eventos_Finalizados,
                COUNT(CASE WHEN Fecha_Evento > CURDATE() THEN 1 END) as Eventos_Proximos
              FROM Eventos
              WHERE Archivado = 0";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$sql_archivados = "SELECT COUNT(*) as Total_Archivados FROM Eventos WHERE Archivado = 1";
$stmt_archivados = $pdo->prepare($sql_archivados);
$stmt_archivados->execute();
$total_archivados = $stmt_archivados->fetchColumn();

$sql_años = "SELECT DISTINCT YEAR(Fecha_Evento) as anio FROM Eventos ORDER BY anio DESC";
$stmt_años = $pdo->prepare($sql_años);
$stmt_años->execute();
$años_disponibles = $stmt_años->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Eventos - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/ver_eventos.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
            <style>
        .view-tabs {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            margin-bottom: -1px;
            overflow-x: auto;
        }
        
        .view-tab {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            color: #7f8c8d;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .view-tab:hover { 
            color: #667eea; 
            background: #f8f9fa;
        }
        
        .view-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: white;
        }
        
        .view-tab .badge-count {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        
        .badge-archivado {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #6c757d;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .badge-reactivado {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .info-reactivacion {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 12px 15px;
            margin: 15px 0;
            border-radius: 6px;
            font-size: 0.9em;
        }

        .info-reactivacion strong {
            color: #1976d2;
        }

        .info-reactivacion a {
            color: #1976d2;
            text-decoration: underline;
            font-weight: 600;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-icon.gray { background: linear-gradient(135deg, #a3a3a3 0%, #555555 100%); }
    </style>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <div class="header">
                <h1>Gestión de Eventos</h1>
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

            <?php if ($mensaje_actualizacion): ?>
            <div class="alert-info">
                <i class="fas fa-sync-alt"></i>
                <span><?= $mensaje_actualizacion ?></span>
            </div>
            <?php endif; ?>

            <div class="view-tabs">
                <a href="?vista=activos&estado=<?= $filtro_estado ?>&año=<?= $filtro_año ?>" 
                   class="view-tab <?= $vista === 'activos' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Eventos Activos</span>
                    <span class="badge-count"><?= $stats['Total_Eventos'] ?></span>
                </a>
                <a href="?vista=historial&estado=<?= $filtro_estado ?>&año=<?= $filtro_año ?>" 
                   class="view-tab <?= $vista === 'historial' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Historial de Eventos</span>
                    <span class="badge-count"><?= $total_archivados ?></span>
                </a>
            </div>

            <br>

            <?php if ($vista === 'activos'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['Total_Eventos'] ?></h3>
                        <p>Total Eventos Activos</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green" style="flex-shrink: 0;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['Eventos_Activos'] ?></h3>
                        <p>Con inscripciones abiertas</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['Eventos_Proximos'] ?></h3>
                        <p>Próximos Eventos</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon gray">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $total_archivados ?></h3>
                        <p>Eventos Archivados</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="filtros">
                <form method="GET" action="">
                    <input type="hidden" name="vista" value="<?= $vista ?>">
                    <div class="filtros-grid">
                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Estado</label>
                            <select name="estado">
                                <option value="Todos" <?= $filtro_estado === 'Todos' ? 'selected' : '' ?>>Todos los Estados</option>
                                <option value="Planificado" <?= $filtro_estado === 'Planificado' ? 'selected' : '' ?>>Planificado</option>
                                <option value="Inscripciones Abiertas" <?= $filtro_estado === 'Inscripciones Abiertas' ? 'selected' : '' ?>>Inscripciones Abiertas</option>
                                <option value="Inscripciones Cerradas" <?= $filtro_estado === 'Inscripciones Cerradas' ? 'selected' : '' ?>>Inscripciones Cerradas</option>
                                <option value="En Curso" <?= $filtro_estado === 'En Curso' ? 'selected' : '' ?>>En Curso</option>
                                <option value="Finalizado" <?= $filtro_estado === 'Finalizado' ? 'selected' : '' ?>>Finalizados</option>
                                <option value="Cancelado" <?= $filtro_estado === 'Cancelado' ? 'selected' : '' ?>>Cancelados</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Año</label>
                            <select name="año">
                                <option value="Todos">Todos</option>
                                <?php foreach ($años_disponibles as $año): ?>
                                    <option value="<?= $año ?>" <?= $filtro_año == $año ? 'selected' : '' ?>><?= $año ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>

                        <a href="Crear_evento.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nuevo Evento
                        </a>
                    </div>
                </form>
            </div>

            <?php if (count($eventos) > 0): ?>
            <div class="eventos-grid">
                <?php foreach ($eventos as $evento): ?>
                <?php
                    $estado_clase = strtolower(str_replace(' ', '_', $evento['Estado_Evento']));
                    $puede_reactivar = in_array($evento['Estado_Evento'], ['Finalizado', 'Cancelado']) && $vista === 'activos';
                    $esta_archivado = $evento['Archivado'] == 1;
                    $fue_reactivado = !empty($evento['Evento_Reactivado_Id']);
                ?>
                <div class="evento-card">
                    <?php if ($esta_archivado): ?>
                    <span class="badge-archivado">
                        <i class="fas fa-archive"></i> Archivado
                    </span>
                    <?php endif; ?>

                    <?php if ($fue_reactivado && !$esta_archivado): ?>
                    <span class="badge-reactivado">
                        <i class="fas fa-check-circle"></i> Reactivado
                    </span>
                    <?php elseif ($puede_reactivar && !$fue_reactivado): ?>
                    <span class="badge-reactivable" title="Evento puede ser reactivado">
                        <i class="fas fa-redo"></i> Reactivable
                    </span>
                    <?php endif; ?>

                    <?php if ($evento['Imagen_Banner']): ?>
                        <img src="<?= htmlspecialchars($evento['Imagen_Banner']) ?>" alt="<?= htmlspecialchars($evento['Nombre_Evento']) ?>" class="evento-banner" style="font-size: 1em;">
                    <?php else: ?>
                        <div class="evento-banner">
                            <i class="fas fa-running"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="evento-body">
                        <div class="evento-header">
                            <div>
                                <div class="evento-titulo"><?= htmlspecialchars($evento['Nombre_Evento']) ?></div>
                                <div class="evento-tipo"><?= htmlspecialchars($evento['Tipo_Evento_Nombre']) ?></div>
                            </div>
                            <span class="badge badge-<?= $estado_clase ?>">
                                <?= $evento['Estado_Evento'] ?>
                            </span>
                        </div>
                        
                        <div class="evento-fecha">
                            <i class="fas fa-calendar-day"></i>
                            <?= date('d/m/Y', strtotime($evento['Fecha_Evento'])) ?>
                            <i class="fas fa-clock" style="margin-left: 10px;"></i>
                            <?= date('H:i', strtotime($evento['Hora_Inicio'])) ?>
                        </div>

                        <?php if ($fue_reactivado): ?>
                        <div class="info-reactivacion">
                            <i class="fas fa-info-circle"></i>
                            <strong>Este evento fue reactivado como:</strong><br>
                            <a href="detalle_evento.php?id=<?= $evento['Evento_Reactivado_Id'] ?>">
                                <?= htmlspecialchars($evento['Evento_Reactivado_Nombre']) ?> 
                                (<?= date('d/m/Y', strtotime($evento['Evento_Reactivado_Fecha'])) ?>)
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="evento-stats">
                            <div class="stat-item">
                                <span class="number"><?= $evento['Total_Inscritos'] ?></span>
                                <span class="label">Inscritos</span>
                            </div>
                            <div class="stat-item">
                                <span class="number"><?= $evento['Inscritos_Pendientes'] ?></span>
                                <span class="label">Pendientes</span>
                            </div>
                            <div class="stat-item">
                                <span class="number">Q<?= number_format($evento['Total_Recaudado'] ?? 0, 0) ?></span>
                                <span class="label">Recaudado</span>
                            </div>
                        </div>
                        
                        <div class="evento-footer">
                            <a href="detalle_evento.php?id=<?= $evento['Id_Evento'] ?>" class="btn btn-primary btn-small">
                                <i class="fas fa-eye"></i> Ver Detalle
                            </a>
                            <?php if (!$esta_archivado): ?>
                            <a href="revisar_inscrpciones.php?evento=<?= $evento['Id_Evento'] ?>" class="btn btn-success btn-small">
                                <i class="fas fa-clipboard-check"></i> Inscripciones
                            </a>
                            <?php endif; ?>
                            <?php if ($puede_reactivar && !$fue_reactivado): ?>
                            <a href="reactivar_evento_editable.php?id=<?= $evento['Id_Evento'] ?>" class="btn btn-warning btn-small" 
                               onclick="return confirmReactivarEvento(event, '<?= htmlspecialchars($evento['Nombre_Evento']) ?>')">
                                <i class="fas fa-redo"></i> Reactivar
                            </a>
                            <?php endif; ?>
                            <?php if ($esta_archivado): ?>
                            <a href="restaurar_evento.php?id=<?= $evento['Id_Evento'] ?>" class="btn btn-info btn-small" 
                               onclick="return confirmRestaurarEvento(event, '<?= htmlspecialchars($evento['Nombre_Evento']) ?>')">
                                <i class="fas fa-undo"></i> Restaurar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No hay eventos en <?= $vista === 'historial' ? 'el historial' : 'esta sección' ?></h3>
                <p>No se encontraron eventos con los filtros seleccionados</p>
                <?php if ($vista === 'activos'): ?>
                <div style="margin-top: 30px;">
                    <a href="crear_evento.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Crear Primer Evento
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal de Reactivación (del archivo antiguo) -->
    <div id="modalReactivar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-redo"></i> Reactivar Evento</h2>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <form action="reactivar_evento.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_evento_origen" id="id_evento_origen">
                    
                    <div class="info-box">
                        <h4><i class="fas fa-info-circle"></i> ¿Qué significa reactivar un evento?</h4>
                        <p>
                            Se creará una nueva edición del evento con toda su configuración (categorías, costos, cuentas bancarias). 
                            Solo necesitas actualizar las fechas y límites. Las inscripciones anteriores permanecerán en el evento original.
                        </p>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label><i class="fas fa-tag"></i> Nombre del Evento</label>
                        <input type="text" name="nombre_evento" id="nombre_evento" required readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Nueva Fecha del Evento *</label>
                            <input type="date" name="fecha_evento" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Hora de Inicio *</label>
                            <input type="time" name="hora_inicio" id="hora_inicio" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Hora de Salida/Llegada</label>
                            <input type="time" name="hora_salida" id="hora_salida">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Nuevo Cupo Máximo</label>
                            <input type="number" name="cupo_maximo" id="cupo_maximo" min="1">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-check"></i> Inicio de Inscripciones</label>
                            <input type="datetime-local" name="fecha_inicio_inscripciones">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-times"></i> Cierre de Inscripciones</label>
                            <input type="datetime-local" name="fecha_fin_inscripciones">
                        </div>
                    </div>

                    <div class="form-group form-grid-full">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="actualizar_costos" id="actualizar_costos">
                            <span>Actualizar fechas de los costos de inscripción automáticamente</span>
                        </label>
                        <p style="font-size: 12px; color: #666; margin-top: 5px; margin-left: 28px;">
                            Las fechas de costos se ajustarán proporcionalmente a la nueva fecha del evento
                        </p>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-large" onclick="cerrarModal()" style="background-color: #6c757d; color: white;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-warning btn-large">
                        <i class="fas fa-redo"></i> Reactivar Evento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // SweetAlert para confirmaciones (del archivo antiguo)
        function confirmReactivarEvento(event, nombreEvento) {
            event.preventDefault();
            const url = event.target.closest('a').href;
            
            Swal.fire({
                title: '¿Reactivar evento?',
                html: `¿Estás seguro de que deseas reactivar el evento <strong>"${nombreEvento}"</strong>?<br><br>
                      Se creará una nueva edición del evento.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, reactivar',
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(url)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la reactivación');
                            }
                            return response.text();
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Error: ${error}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: '¡Reactivado!',
                        text: 'El evento ha sido reactivado exitosamente',
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
            
            return false;
        }

        function confirmRestaurarEvento(event, nombreEvento) {
            event.preventDefault();
            const url = event.target.closest('a').href;
            
            Swal.fire({
                title: '¿Restaurar evento?',
                html: `¿Estás seguro de que deseas restaurar el evento <strong>"${nombreEvento}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            
            return false;
        }

        // Funciones del modal (del archivo antiguo)
        function abrirModalReactivar(evento) {
            document.getElementById('id_evento_origen').value = evento.Id_Evento;
            document.getElementById('nombre_evento').value = evento.Nombre_Evento + ' ' + (new Date().getFullYear());
            document.getElementById('hora_inicio').value = evento.Hora_Inicio;
            document.getElementById('hora_salida').value = evento.Hora_Salida || '';
            document.getElementById('cupo_maximo').value = evento.Cupo_Maximo || '';
            
            document.getElementById('modalReactivar').style.display = 'block';
        }

        function cerrarModal() {
            document.getElementById('modalReactivar').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalReactivar');
            if (event.target == modal) {
                cerrarModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModal();
            }
        });

        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '<?= htmlspecialchars($_GET['success']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($_GET['error']) ?>',
                confirmButtonText: 'Aceptar'
            });
        <?php endif; ?>
    </script>
</body>
</html>