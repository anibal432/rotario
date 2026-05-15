<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'cambiar_estado':
                    $id = $_POST['id_clausula'];
                    $nuevo_estado = $_POST['nuevo_estado'];
                    $stmt = $pdo->prepare("UPDATE Reglamento_Becas SET Estado = ? WHERE Id_Clausula = ?");
                    $stmt->execute([$nuevo_estado, $id]);
                    $_SESSION['success'] = "Estado actualizado correctamente";
                    break;
                    
                case 'eliminar':
                    $id = $_POST['id_clausula'];
                    $stmt = $pdo->prepare("DELETE FROM Reglamento_Becas WHERE Id_Clausula = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success'] = "Cláusula eliminada correctamente";
                    break;
                    
                case 'reordenar':
                    $orden_clausulas = json_decode($_POST['orden'], true);
                    foreach ($orden_clausulas as $index => $id) {
                        $stmt = $pdo->prepare("UPDATE Reglamento_Becas SET Orden = ? WHERE Id_Clausula = ?");
                        $stmt->execute([$index + 1, $id]);
                    }
                    echo json_encode(['success' => true, 'message' => 'Orden actualizado']);
                    exit;
            }
            header('Location: gestionar_reglamento.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// Obtener todas las cláusulas
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM Sub_Clausulas_Reglamento WHERE Id_Clausula = r.Id_Clausula) as Num_Subcausulas
        FROM Reglamento_Becas r
        ORDER BY r.Orden ASC, r.Numero_Clausula ASC";
$stmt = $pdo->query($sql);
$clausulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$sql_stats = "SELECT 
                COUNT(*) as Total,
                SUM(CASE WHEN Estado = 'Activo' THEN 1 ELSE 0 END) as Activas,
                SUM(CASE WHEN Estado = 'Inactivo' THEN 1 ELSE 0 END) as Inactivas,
                SUM(CASE WHEN Tiene_Subcausulas = 1 THEN 1 ELSE 0 END) as Con_Subcausulas
              FROM Reglamento_Becas";
$stmt_stats = $pdo->query($sql_stats);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Reglamento - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reglamento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Gestionar Reglamento de Becas</h1>
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

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= $_SESSION['success'] ?></span>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= $_SESSION['error'] ?></span>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Importante:</strong> Los cambios aquí realizados se reflejarán automáticamente en las Cartas de Compromiso que se generen. 
                    Puedes arrastrar las cláusulas para cambiar su orden.
                </div>
            </div>

            <!-- Stats Cards con icono a la izquierda -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-list"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Total'] ?></h3>
                        <p>Total Cláusulas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Activas'] ?></h3>
                        <p>Activas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Inactivas'] ?></h3>
                        <p>Inactivas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-list-ul"></i></div>
                    <div class="stat-info">
                        <h3><?= $stats['Con_Subcausulas'] ?></h3>
                        <p>Con Subcláusulas</p>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div class="actions-info">
                    <h3><i class="fas fa-list-ol"></i> Cláusulas del Reglamento</h3>
                    <p>Arrastra para reordenar</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="agregar_clausula.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nueva Cláusula
                    </a>
                    <a href="vista_previa_carta.php" class="btn btn-info" target="_blank">
                        <i class="fas fa-eye"></i> Vista Previa
                    </a>
                </div>
            </div>

            <?php if (count($clausulas) > 0): ?>
            <div class="clausulas-container">
                <div id="clausulasList">
                    <?php foreach ($clausulas as $clausula): ?>
                    <div class="clausula-item" data-id="<?= $clausula['Id_Clausula'] ?>">
                        <div class="clausula-header">
                            <div class="clausula-numero"><?= $clausula['Numero_Clausula'] ?></div>
                            
                            <div class="clausula-content">
                                <div class="clausula-texto"><?= $clausula['Contenido_Clausula'] ?></div>
                                
                                <?php if ($clausula['Tiene_Subcausulas']): ?>
                                <div class="subcausulas-list">
                                    <?php
                                    $sql_sub = "SELECT * FROM Sub_Clausulas_Reglamento WHERE Id_Clausula = ? ORDER BY Orden";
                                    $stmt_sub = $pdo->prepare($sql_sub);
                                    $stmt_sub->execute([$clausula['Id_Clausula']]);
                                    $subcausulas = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php foreach ($subcausulas as $sub): ?>
                                    <div class="subcausula-item">
                                        <strong><?= htmlspecialchars($sub['Numero_Sub_Clausula']) ?>:</strong> 
                                        <?= htmlspecialchars($sub['Contenido']) ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="clausula-meta">
                                    <span class="badge badge-<?= strtolower($clausula['Estado']) ?>">
                                        <?= $clausula['Estado'] ?>
                                    </span>
                                    <span class="badge badge-<?= strtolower($clausula['Tipo_Clausula']) ?>">
                                        <?= $clausula['Tipo_Clausula'] ?>
                                    </span>
                                    <?php if ($clausula['Tiene_Subcausulas']): ?>
                                    <span class="badge badge-subcausulas">
                                        <i class="fas fa-list-ul"></i> <?= $clausula['Num_Subcausulas'] ?> subcláusulas
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="clausula-actions">
                                <button class="btn-icon btn-drag" title="Arrastrar para reordenar">
                                    <i class="fas fa-grip-vertical"></i>
                                </button>
                                <a href="editar_clausula.php?id=<?= $clausula['Id_Clausula'] ?>" class="btn-icon btn-edit" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" class="toggle-form">
                                    <input type="hidden" name="action" value="cambiar_estado">
                                    <input type="hidden" name="id_clausula" value="<?= $clausula['Id_Clausula'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?= $clausula['Estado'] === 'Activo' ? 'Inactivo' : 'Activo' ?>">
                                    <button type="submit" class="btn-icon btn-toggle" title="<?= $clausula['Estado'] === 'Activo' ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fas fa-<?= $clausula['Estado'] === 'Activo' ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" class="delete-form">
                                    <input type="hidden" name="action" value="eliminar">
                                    <input type="hidden" name="id_clausula" value="<?= $clausula['Id_Clausula'] ?>">
                                    <button type="submit" class="btn-icon btn-delete eliminar-clausula" title="Eliminar" data-numero="<?= $clausula['Numero_Clausula'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-contract"></i>
                <h3>No hay cláusulas registradas</h3>
                <p>Comienza agregando la primera cláusula del reglamento</p>
                <a href="agregar_clausula.php" class="btn btn-success" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Agregar Primera Cláusula
                </a>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.eliminar-clausula');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const numero = this.getAttribute('data-numero');
                    const form = this.closest('form');
                    
                    Swal.fire({
                        title: '¿Estás seguro?',
                        html: `Vas a eliminar la cláusula <strong>${numero}</strong>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e74c3c',
                        cancelButtonColor: '#95a5a6',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            const toggleForms = document.querySelectorAll('.toggle-form');
            
            toggleForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: '¿Cambiar estado?',
                        text: '¿Estás seguro de cambiar el estado de esta cláusula?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#95a5a6',
                        confirmButtonText: 'Sí, cambiar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });

            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: '<?= $_SESSION['success'] ?>',
                    confirmButtonColor: '#004b87'
                });
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= $_SESSION['error'] ?>',
                    confirmButtonColor: '#004b87'
                });
            <?php unset($_SESSION['error']); endif; ?>
        });

        const el = document.getElementById('clausulasList');
        if (el) {
            const sortable = Sortable.create(el, {
                animation: 150,
                handle: '.btn-drag',
                ghostClass: 'sortable-ghost',
                onEnd: function(evt) {
                    const orden = [];
                    document.querySelectorAll('.clausula-item').forEach(item => {
                        orden.push(item.dataset.id);
                    });
                    
                    fetch('gestionar_reglamento.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=reordenar&orden=' + encodeURIComponent(JSON.stringify(orden))
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Orden actualizado',
                                text: data.message,
                                confirmButtonColor: '#004b87',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    });
                }
            });
        }
        
        function cerrarSesion() {
            Swal.fire({
                title: '¿Está seguro que desea cerrar sesión?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#004b87',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Sí, cerrar sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>