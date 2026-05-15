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

if (isset($_GET['accion'])) {
    $accion = $_GET['accion'];
    $id = $_GET['id'] ?? null;

    try {
        if ($accion === 'eliminar' && $id) {
            $sql_foto = "SELECT Foto FROM Testimonios WHERE Id_Testimonio = ?";
            $stmt_foto = $pdo->prepare($sql_foto);
            $stmt_foto->execute([$id]);
            $testimonio = $stmt_foto->fetch(PDO::FETCH_ASSOC);

            $sql = "DELETE FROM Testimonios WHERE Id_Testimonio = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            if ($testimonio && $testimonio['Foto'] && file_exists($testimonio['Foto'])) {
                unlink($testimonio['Foto']);
            }

            $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                            VALUES (?, 'Eliminó un testimonio', CURDATE())";
            $stmt_bitacora = $pdo->prepare($sql_bitacora);
            $stmt_bitacora->execute([$user_id]);

            $mensaje = 'Testimonio eliminado exitosamente';
            $tipo_mensaje = 'success';

        } elseif ($accion === 'toggle' && $id) {
            $sql = "UPDATE Testimonios 
                    SET Activo = NOT Activo 
                    WHERE Id_Testimonio = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            $mensaje = 'Estado del testimonio actualizado';
            $tipo_mensaje = 'success';

        } elseif ($accion === 'orden' && $id) {
            $direccion = $_GET['direccion'] ?? 'up';
            
            $sql_actual = "SELECT Orden FROM Testimonios WHERE Id_Testimonio = ?";
            $stmt_actual = $pdo->prepare($sql_actual);
            $stmt_actual->execute([$id]);
            $orden_actual = $stmt_actual->fetchColumn();

            if ($direccion === 'up') {
                $sql = "UPDATE Testimonios SET Orden = Orden + 1 WHERE Orden = ? - 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$orden_actual]);

                $sql2 = "UPDATE Testimonios SET Orden = Orden - 1 WHERE Id_Testimonio = ?";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([$id]);
            } else {
                $sql = "UPDATE Testimonios SET Orden = Orden - 1 WHERE Orden = ? + 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$orden_actual]);

                $sql2 = "UPDATE Testimonios SET Orden = Orden + 1 WHERE Id_Testimonio = ?";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([$id]);
            }

            $mensaje = 'Orden actualizado';
            $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

$sql = "SELECT 
            t.*,
            e.Nombres_Apellidos,
            e.Numero_Expediente
        FROM Testimonios t
        LEFT JOIN Estudiantes e ON t.Id_Estudiante = e.Id_Estudiante
        ORDER BY t.Orden ASC, t.Fecha_Registro DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$testimonios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_testimonios = count($testimonios);
$testimonios_activos = count(array_filter($testimonios, function($t) {
    return $t['Activo'] == 1;
}));
$testimonios_inactivos = $total_testimonios - $testimonios_activos;
$con_foto = count(array_filter($testimonios, function($t) {
    return !empty($t['Foto']);
}));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Testimonios - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/testimonios.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header (del archivo antiguo) -->
            <div class="header">
                <h1>Gestión de Testimonios</h1>
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

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <div><?= $mensaje ?></div>
            </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Sobre los Testimonios:</strong>
                    <p style="margin-top: 5px;">
                        Los testimonios visibles en la página pública son solo aquellos marcados como "Activos". 
                        Puedes ordenarlos usando las flechas para controlar en qué orden aparecen.
                    </p>
                </div>
            </div>

            <!-- Stats Cards con icono a la izquierda (del archivo antiguo) -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-quote-right"></i></div>
                    <div class="stat-info">
                        <h3><?= $total_testimonios ?></h3>
                        <p>Total de Testimonios</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?= $testimonios_activos ?></h3>
                        <p>Activos (Públicos)</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-eye-slash"></i></div>
                    <div class="stat-info">
                        <h3><?= $testimonios_inactivos ?></h3>
                        <p>Inactivos (Ocultos)</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-image"></i></div>
                    <div class="stat-info">
                        <h3><?= $con_foto ?></h3>
                        <p>Con Fotografía</p>
                    </div>
                </div>
            </div>

            <!-- Testimonios Section (combinado) -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i>
                        Todos los Testimonios (<?= $total_testimonios ?>)
                    </h2>
                    <div class="header-actions">
                        <a href="agregar_testimonio.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nuevo Testimonio
                        </a>
                    </div>
                </div>

                <?php if (count($testimonios) > 0): ?>
                <div class="testimonios-grid">
                    <?php foreach ($testimonios as $test): ?>
                    <div class="testimonio-card <?= $test['Activo'] ? '' : 'inactivo' ?>">
                        <div class="testimonio-header">
                            <?php if ($test['Foto'] && file_exists($test['Foto'])): ?>
                                <img src="<?= htmlspecialchars($test['Foto']) ?>" 
                                     alt="<?= htmlspecialchars($test['Nombre_Estudiante']) ?>"
                                     class="testimonio-foto">
                            <?php else: ?>
                                <div class="testimonio-foto placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>

                            <div class="testimonio-info">
                                <div class="testimonio-nombre">
                                    <?= htmlspecialchars($test['Nombre_Estudiante']) ?>
                                </div>
                                <?php if ($test['Id_Estudiante'] && $test['Nombres_Apellidos']): ?>
                                <div class="testimonio-estudiante">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?= htmlspecialchars($test['Nombres_Apellidos']) ?>
                                </div>
                                <?php endif; ?>
                                <div class="testimonio-fecha">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($test['Fecha_Registro'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="testimonio-badges">
                            <span class="badge badge-<?= $test['Activo'] ? 'activo' : 'inactivo' ?>">
                                <i class="fas fa-<?= $test['Activo'] ? 'check' : 'times' ?>"></i>
                                <?= $test['Activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                            <span class="badge badge-orden">
                                <i class="fas fa-sort"></i>
                                Orden: <?= $test['Orden'] ?>
                            </span>
                        </div>

                        <div class="testimonio-texto">
                            "<?= htmlspecialchars($test['Testimonio']) ?>"
                        </div>

                        <div class="testimonio-acciones">
                            <a href="?accion=orden&id=<?= $test['Id_Testimonio'] ?>&direccion=up" 
                               class="btn btn-secondary"
                               title="Mover arriba">
                                <i class="fas fa-arrow-up"></i>
                            </a>
                            <a href="?accion=orden&id=<?= $test['Id_Testimonio'] ?>&direccion=down" 
                               class="btn btn-secondary"
                               title="Mover abajo">
                                <i class="fas fa-arrow-down"></i>
                            </a>

                            <a href="editar_testimonio.php?id=<?= $test['Id_Testimonio'] ?>" 
                               class="btn btn-warning">
                                <i class="fas fa-edit"></i> Editar
                            </a>

                            <a href="?accion=toggle&id=<?= $test['Id_Testimonio'] ?>" 
                               class="btn btn-<?= $test['Activo'] ? 'secondary' : 'success' ?>">
                                <i class="fas fa-<?= $test['Activo'] ? 'eye-slash' : 'eye' ?>"></i>
                                <?= $test['Activo'] ? 'Desactivar' : 'Activar' ?>
                            </a>

                            <a href="?accion=eliminar&id=<?= $test['Id_Testimonio'] ?>" 
                               class="btn btn-danger eliminar-testimonio"
                               data-nombre="<?= htmlspecialchars($test['Nombre_Estudiante']) ?>">
                                <i class="fas fa-trash"></i> Eliminar
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-quote-right"></i>
                    <h3>No hay testimonios aún</h3>
                    <p>Comienza agregando el primer testimonio de un estudiante becado</p>
                    <div style="margin-top: 30px;">
                        <a href="agregar_testimonio.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Agregar Primer Testimonio
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.eliminar-testimonio');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const nombre = this.getAttribute('data-nombre');
                    const url = this.getAttribute('href');
                    
                    Swal.fire({
                        title: '¿Estás seguro?',
                        html: `Vas a eliminar el testimonio de <strong>${nombre}</strong>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e74c3c',
                        cancelButtonColor: '#95a5a6',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = url;
                        }
                    });
                });
            });

            const toggleButtons = document.querySelectorAll('.btn-secondary, .btn-success');
            toggleButtons.forEach(button => {
                if (button.textContent.includes('Desactivar') || button.textContent.includes('Activar')) {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = this.getAttribute('href');
                        const accion = this.textContent.includes('Desactivar') ? 'desactivar' : 'activar';
                        const nombre = this.closest('.testimonio-card').querySelector('.testimonio-nombre').textContent;
                        
                        Swal.fire({
                            title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} testimonio?`,
                            html: `Vas a ${accion} el testimonio de <strong>${nombre}</strong>`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: accion === 'activar' ? '#28a745' : '#6c757d',
                            cancelButtonColor: '#95a5a6',
                            confirmButtonText: `Sí, ${accion}`,
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = url;
                            }
                        });
                    });
                }
            });

            <?php if ($mensaje): ?>
                Swal.fire({
                    icon: '<?= $tipo_mensaje === 'success' ? 'success' : 'error' ?>',
                    title: '<?= $tipo_mensaje === 'success' ? 'Éxito' : 'Error' ?>',
                    text: '<?= $mensaje ?>',
                    confirmButtonColor: '#004b87',
                    timer: <?= $tipo_mensaje === 'success' ? '3000' : '5000' ?>,
                    showConfirmButton: <?= $tipo_mensaje === 'success' ? 'false' : 'true' ?>
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>