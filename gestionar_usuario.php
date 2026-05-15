<?php
session_start();
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener información del usuario de la sesión
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

// Obtener el role_id desde la base de datos
try {
    $stmt = $pdo->prepare("SELECT Id_Rol FROM Usuario WHERE Id_Usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    $role_id = $user_data['Id_Rol'] ?? 0;
} catch (PDOException $e) {
    header('Location: login.php');
    exit;
}

$roles_permitidos = [1, 2];
if (!in_array($role_id, $roles_permitidos)) {
    $_SESSION['error'] = 'No tienes permisos para acceder a esta sección';
    header('Location: admin.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $id_rol = intval($_POST['id_rol']);
        $estado = $_POST['estado'];
        
        if (empty($nombre) || empty($email) || empty($password)) {
            $mensaje = "Todos los campos son obligatorios";
            $tipo_mensaje = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "Email inválido";
            $tipo_mensaje = "error";
        } elseif (strlen($password) < 6) {
            $mensaje = "La contraseña debe tener al menos 6 caracteres";
            $tipo_mensaje = "error";
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO Usuario (Id_Rol, Nombre, Email, Password, Estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_rol, $nombre, $email, $password_hash, $estado]);
                
                $mensaje = "Usuario creado exitosamente";
                $tipo_mensaje = "success";
                
                $id_usuario_nuevo = $pdo->lastInsertId();
                $actividad = "Creó usuario: $nombre (ID: $id_usuario_nuevo)";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $stmt_bitacora = $pdo->prepare("CALL sp_registrar_actividad(?, ?, ?)");
                $stmt_bitacora->execute([$_SESSION['user_id'], $actividad, $ip]);
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensaje = "El nombre de usuario o email ya existe";
                } else {
                    $mensaje = "Error al crear usuario: " . $e->getMessage();
                }
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'editar') {
        $id_usuario = intval($_POST['id_usuario']);
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $id_rol = intval($_POST['id_rol']);
        $estado = $_POST['estado'];
        $password = $_POST['password'] ?? '';
        
        if (empty($nombre) || empty($email)) {
            $mensaje = "Nombre y email son obligatorios";
            $tipo_mensaje = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "Email inválido";
            $tipo_mensaje = "error";
        } else {
            try {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $mensaje = "La contraseña debe tener al menos 6 caracteres";
                        $tipo_mensaje = "error";
                    } else {
                        $password_hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE Usuario SET Id_Rol = ?, Nombre = ?, Email = ?, Password = ?, Estado = ? WHERE Id_Usuario = ?");
                        $stmt->execute([$id_rol, $nombre, $email, $password_hash, $estado, $id_usuario]);
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE Usuario SET Id_Rol = ?, Nombre = ?, Email = ?, Estado = ? WHERE Id_Usuario = ?");
                    $stmt->execute([$id_rol, $nombre, $email, $estado, $id_usuario]);
                }
                
                if (!isset($mensaje)) {
                    $mensaje = "Usuario actualizado exitosamente";
                    $tipo_mensaje = "success";
                    
                    $actividad = "Actualizó usuario: $nombre (ID: $id_usuario)";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    
                    $stmt_bitacora = $pdo->prepare("CALL sp_registrar_actividad(?, ?, ?)");
                    $stmt_bitacora->execute([$_SESSION['user_id'], $actividad, $ip]);
                }
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar usuario: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'cambiar_estado') {
        $id_usuario = intval($_POST['id_usuario']);
        $nuevo_estado = $_POST['nuevo_estado'];
        
        if ($id_usuario == $_SESSION['user_id']) {
            $mensaje = "No puedes desactivar tu propio usuario";
            $tipo_mensaje = "error";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT Nombre FROM Usuario WHERE Id_Usuario = ?");
                $stmt->execute([$id_usuario]);
                $usuario_data = $stmt->fetch();
                $nombre_usuario = $usuario_data['Nombre'] ?? 'Desconocido';
                
                $stmt = $pdo->prepare("UPDATE Usuario SET Estado = ? WHERE Id_Usuario = ?");
                $stmt->execute([$nuevo_estado, $id_usuario]);
                
                $accion_texto = $nuevo_estado === 'Activo' ? 'activado' : 'desactivado';
                $mensaje = "Usuario $accion_texto exitosamente";
                $tipo_mensaje = "success";
                
                $actividad = ucfirst($accion_texto) . " usuario: $nombre_usuario (ID: $id_usuario)";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $stmt_bitacora = $pdo->prepare("CALL sp_registrar_actividad(?, ?, ?)");
                $stmt_bitacora->execute([$_SESSION['user_id'], $actividad, $ip]);
                
            } catch (PDOException $e) {
                $mensaje = "Error al cambiar estado del usuario: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

try {
    $stmt_roles = $pdo->query("SELECT Id_Rol, Nombre, Descripcion FROM Rol ORDER BY Nombre");
    $roles = $stmt_roles->fetchAll();
} catch (PDOException $e) {
    die("Error al obtener roles: " . $e->getMessage());
}

try {
    $stmt_usuarios = $pdo->query("
        SELECT u.Id_Usuario, u.Nombre, u.Email, u.Estado, u.Fecha_Creacion, u.Ultimo_Acceso,
               r.Nombre as Rol_Nombre, u.Id_Rol
        FROM Usuario u
        INNER JOIN Rol r ON u.Id_Rol = r.Id_Rol
        ORDER BY u.Fecha_Creacion DESC
    ");
    $usuarios = $stmt_usuarios->fetchAll();
} catch (PDOException $e) {
    die("Error al obtener usuarios: " . $e->getMessage());
}

// Contar estadísticas
$total_usuarios = count($usuarios);
$usuarios_activos = count(array_filter($usuarios, fn($u) => $u['Estado'] === 'Activo'));
$usuarios_inactivos = $total_usuarios - $usuarios_activos;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/users.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Gestión de Usuarios</h1>
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

            <!-- Mensajes -->
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?= $tipo_mensaje === 'success' ? 'success' : 'error' ?>">
                    <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <div><?= htmlspecialchars($mensaje) ?></div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?= $total_usuarios ?></h3>
                        <p>Total Usuarios</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                    <div class="stat-info">
                        <h3><?= $usuarios_activos ?></h3>
                        <p>Usuarios Activos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fas fa-user-times"></i></div>
                    <div class="stat-info">
                        <h3><?= $usuarios_inactivos ?></h3>
                        <p>Usuarios Inactivos</p>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="table-card">
                <div class="table-card-header">
                    <h2><i class="fas fa-table"></i> Lista de Usuarios</h2>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Buscar usuarios...">
                        </div>
                        <button class="btn-primary-custom" onclick="openModal('modalCrearUsuario')">
                            <i class="fas fa-user-plus"></i> Nuevo Usuario
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><strong>#<?= $usuario['Id_Usuario'] ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-user-circle" style="font-size: 24px; color: #004b87;"></i>
                                            <strong><?= htmlspecialchars($usuario['Nombre']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['Email']) ?></td>
                                    <td>
                                        <span class="badge-custom badge-info">
                                            <?= htmlspecialchars($usuario['Rol_Nombre']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-custom badge-<?= $usuario['Estado'] === 'Activo' ? 'success' : 'danger' ?>">
                                            <?= $usuario['Estado'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo $usuario['Ultimo_Acceso'] 
                                            ? date('d/m/Y H:i', strtotime($usuario['Ultimo_Acceso'])) 
                                            : '<span style="color: #9ca3af;">Nunca</span>'; 
                                        ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn-action btn-edit" title="editar informacion de usuario"
                                                onclick='editarUsuario(<?= json_encode($usuario, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($usuario['Id_Usuario'] != $_SESSION['user_id']): ?>
                                            <?php if ($usuario['Estado'] === 'Activo'): ?>
                                                <button class="btn-action btn-delete cambiar-estado-btn" title="Desactivar Usuario"
                                                        data-id="<?= $usuario['Id_Usuario'] ?>" 
                                                        data-nombre="<?= htmlspecialchars($usuario['Nombre']) ?>" 
                                                        data-estado="Inactivo">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-activate cambiar-estado-btn" title="Activar Usuario"
                                                        data-id="<?= $usuario['Id_Usuario'] ?>" 
                                                        data-nombre="<?= htmlspecialchars($usuario['Nombre']) ?>" 
                                                        data-estado="Activo">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Crear Usuario -->
    <div id="modalCrearUsuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h3>
                <button class="close-modal" onclick="closeModal('modalCrearUsuario')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Nombre *
                            </label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="form-group full-width password-wrapper">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Contraseña *
                            </label>
                            <input type="password" class="form-control" name="password" id="crear_password" required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('crear_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="text-muted">Mínimo 6 caracteres</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-shield-alt"></i> Rol *
                            </label>
                            <select class="form-select" name="id_rol" required>
                                <option value="">Seleccione un rol</option>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= $rol['Id_Rol'] ?>">
                                        <?= htmlspecialchars($rol['Nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-toggle-on"></i> Estado *
                            </label>
                            <select class="form-select" name="estado" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalCrearUsuario')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Usuario -->
    <div id="modalEditarUsuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Usuario</h3>
                <button class="close-modal" onclick="closeModal('modalEditarUsuario')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_usuario" id="edit_id_usuario">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Nombre *
                            </label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        
                        <div class="form-group full-width password-wrapper">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Nueva Contraseña
                            </label>
                            <input type="password" class="form-control" name="password" id="edit_password" minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('edit_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="text-muted">Dejar en blanco para mantener la contraseña actual</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-shield-alt"></i> Rol *
                            </label>
                            <select class="form-select" name="id_rol" id="edit_id_rol" required>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= $rol['Id_Rol'] ?>">
                                        <?= htmlspecialchars($rol['Nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-toggle-on"></i> Estado *
                            </label>
                            <select class="form-select" name="estado" id="edit_estado" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditarUsuario')">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulario oculto para cambiar estado -->
    <form method="POST" id="formCambiarEstado" style="display: none;">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id_usuario" id="cambiar_estado_id_usuario">
        <input type="hidden" name="nuevo_estado" id="cambiar_estado_nuevo_estado">
    </form>

    <script>
        // Funciones para modales
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const cambiarEstadoButtons = document.querySelectorAll('.cambiar-estado-btn');
            
            cambiarEstadoButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const idUsuario = this.getAttribute('data-id');
                    const nombreUsuario = this.getAttribute('data-nombre');
                    const nuevoEstado = this.getAttribute('data-estado');
                    const accion = nuevoEstado === 'Activo' ? 'activar' : 'desactivar';
                    
                    Swal.fire({
                        title: '¿Estás seguro?',
                        html: `Vas a ${accion} al usuario <strong>${nombreUsuario}</strong>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: nuevoEstado === 'Activo' ? '#28a745' : '#e74c3c',
                        cancelButtonColor: '#95a5a6',
                        confirmButtonText: nuevoEstado === 'Activo' ? 'Sí, activar' : 'Sí, desactivar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.getElementById('cambiar_estado_id_usuario').value = idUsuario;
                            document.getElementById('cambiar_estado_nuevo_estado').value = nuevoEstado;
                            document.getElementById('formCambiarEstado').submit();
                        }
                    });
                });
            });

            <?php if (!empty($mensaje)): ?>
                Swal.fire({
                    icon: '<?= $tipo_mensaje === 'success' ? 'success' : 'error' ?>',
                    title: '<?= $tipo_mensaje === 'success' ? 'Éxito' : 'Error' ?>',
                    text: '<?= $mensaje ?>',
                    confirmButtonColor: '#004b87'
                });
            <?php endif; ?>
        });

        // Toggle Password
        function togglePassword(fieldId, button) {
            const field = document.getElementById(fieldId);
            const icon = button.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function editarUsuario(usuario) {
            document.getElementById('edit_id_usuario').value = usuario.Id_Usuario;
            document.getElementById('edit_nombre').value = usuario.Nombre;
            document.getElementById('edit_email').value = usuario.Email;
            document.getElementById('edit_id_rol').value = usuario.Id_Rol;
            document.getElementById('edit_estado').value = usuario.Estado;
            document.getElementById('edit_password').value = '';
            
            openModal('modalEditarUsuario');
        }
        
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#userTableBody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>