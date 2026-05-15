<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'agregar') {
            $sql = "INSERT INTO Preguntas_Cuestionario (Pregunta, Tipo_Respuesta, Opciones, Orden, Categoria, Estado) 
                    VALUES (:pregunta, :tipo, :opciones, :orden, :categoria, 'Activa')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pregunta' => $_POST['pregunta'],
                ':tipo' => $_POST['tipo_respuesta'],
                ':opciones' => $_POST['opciones'] ?? null,
                ':orden' => $_POST['orden'],
                ':categoria' => $_POST['categoria']
            ]);
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Pregunta agregada exitosamente'];
            
        } elseif ($accion === 'editar') {
            $sql = "UPDATE Preguntas_Cuestionario 
                    SET Pregunta = :pregunta, Tipo_Respuesta = :tipo, Opciones = :opciones, 
                        Orden = :orden, Categoria = :categoria 
                    WHERE Id_Pregunta = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pregunta' => $_POST['pregunta'],
                ':tipo' => $_POST['tipo_respuesta'],
                ':opciones' => $_POST['opciones'] ?? null,
                ':orden' => $_POST['orden'],
                ':categoria' => $_POST['categoria'],
                ':id' => $_POST['id_pregunta']
            ]);
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Pregunta actualizada exitosamente'];
            
        } elseif ($accion === 'eliminar') {
            $sql = "UPDATE Preguntas_Cuestionario SET Estado = 'Inactiva' WHERE Id_Pregunta = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $_POST['id_pregunta']]);
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Pregunta desactivada exitosamente'];
            
        } elseif ($accion === 'activar') {
            $sql = "UPDATE Preguntas_Cuestionario SET Estado = 'Activa' WHERE Id_Pregunta = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $_POST['id_pregunta']]);
            $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Pregunta activada exitosamente'];
        }
        
        header('Location: gestionar_preguntas.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
    }
}

// Obtener todas las preguntas agrupadas por categoría
$sql = "SELECT * FROM Preguntas_Cuestionario ORDER BY Categoria, Orden, Id_Pregunta";
$stmt = $pdo->query($sql);
$todasPreguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por categoría
$preguntasPorCategoria = [
    'perfil' => [],
    'objetivos' => [],
    'experiencia' => [],
    'cierre' => []
];

foreach ($todasPreguntas as $pregunta) {
    $cat = $pregunta['Categoria'] ?? 'perfil';
    if (isset($preguntasPorCategoria[$cat])) {
        $preguntasPorCategoria[$cat][] = $pregunta;
    }
}

// Obtener el siguiente número de orden por categoría
function obtenerSiguienteOrden($pdo, $categoria) {
    $sql = "SELECT COALESCE(MAX(Orden), 0) + 1 as siguiente FROM Preguntas_Cuestionario WHERE Categoria = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoria]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['siguiente'];
}

$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Preguntas - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/preguntas.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Gestionar Preguntas del Cuestionario</h1>
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
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: '<?= $mensaje['tipo'] === 'success' ? 'success' : 'error' ?>',
                            title: '<?= $mensaje['tipo'] === 'success' ? 'Éxito' : 'Error' ?>',
                            text: '<?= $mensaje['texto'] ?>',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    });
                </script>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-plus-circle"></i> Gestión de Preguntas</h2>
                    <button onclick="abrirModalAgregar()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Pregunta
                    </button>
                </div>
                
                <div class="tabs">
                    <button class="tab active" onclick="cambiarTab('perfil')">
                        <i class="fas fa-user"></i> Sobre tu Perfil
                    </button>
                    <button class="tab" onclick="cambiarTab('objetivos')">
                        <i class="fas fa-bullseye"></i> Objetivos y Motivación
                    </button>
                    <button class="tab" onclick="cambiarTab('experiencia')">
                        <i class="fas fa-handshake"></i> Experiencia y Comunidad
                    </button>
                    <button class="tab" onclick="cambiarTab('cierre')">
                        <i class="fas fa-check-circle"></i> Preguntas de Cierre
                    </button>
                </div>
                
                <?php 
                $categorias = [
                    'perfil' => ['nombre' => 'Sobre tu Perfil', 'icono' => 'user', 'color' => 'perfil'],
                    'objetivos' => ['nombre' => 'Objetivos y Motivación', 'icono' => 'bullseye', 'color' => 'objetivos'],
                    'experiencia' => ['nombre' => 'Experiencia y Comunidad', 'icono' => 'handshake', 'color' => 'experiencia'],
                    'cierre' => ['nombre' => 'Preguntas de Cierre', 'icono' => 'check-circle', 'color' => 'cierre']
                ];
                
                foreach ($categorias as $cat_key => $cat_info): 
                    $preguntas = $preguntasPorCategoria[$cat_key];
                ?>
                <div id="tab-<?= $cat_key ?>" class="tab-content <?= $cat_key === 'perfil' ? 'active' : '' ?>">
                    <?php if (empty($preguntas)): ?>
                        <div class="empty-state">
                            <i class="fas fa-<?= $cat_info['icono'] ?>"></i>
                            <h4>No hay preguntas en esta categoría</h4>
                            <p>Agrega la primera pregunta haciendo clic en "Nueva Pregunta"</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Pregunta</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preguntas as $pregunta): ?>
                                    <tr>
                                        <td><strong><?= $pregunta['Orden'] ?></strong></td>
                                        <td>
                                            <div class="question-item">
                                                <?= htmlspecialchars($pregunta['Pregunta']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $tipos = [
                                                'texto_corto' => 'Texto Corto',
                                                'texto_largo' => 'Texto Largo',
                                                'si_no' => 'Sí / No',
                                                'opcion_multiple' => 'Opción Múltiple'
                                            ];
                                            ?>
                                            <span class="badge badge-info"><?= $tipos[$pregunta['Tipo_Respuesta']] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($pregunta['Estado'] === 'Activa'): ?>
                                                <span class="badge badge-success">Activa</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editarPregunta(<?= json_encode($pregunta) ?>)' 
                                                        class="btn btn-warning btn-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($pregunta['Estado'] === 'Activa'): ?>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirmarDesactivacion(event)">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id_pregunta" value="<?= $pregunta['Id_Pregunta'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="Desactivar">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="accion" value="activar">
                                                        <input type="hidden" name="id_pregunta" value="<?= $pregunta['Id_Pregunta'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" title="Activar">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Modal Agregar -->
    <div id="modalAgregar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Agregar Nueva Pregunta</h3>
                <button class="close-modal" onclick="cerrarModal('modalAgregar')">&times;</button>
            </div>
            <form method="POST" id="formAgregar">
                <input type="hidden" name="accion" value="agregar">
                
                <div class="form-group">
                    <label>Categoría <span style="color: #dc3545;">*</span></label>
                    <select name="categoria" id="categoriaSelect" required onchange="actualizarOrden()">
                        <option value="">Selecciona una categoría</option>
                        <option value="perfil">Sobre tu Perfil</option>
                        <option value="objetivos">Objetivos y Motivación</option>
                        <option value="experiencia">Experiencia y Comunidad</option>
                        <option value="cierre">Preguntas de Cierre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Texto de la Pregunta <span style="color: #dc3545;">*</span></label>
                    <textarea name="pregunta" required placeholder="Escribe la pregunta aquí..." style="min-height: 80px;"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Respuesta <span style="color: #dc3545;">*</span></label>
                        <select name="tipo_respuesta" id="tipoRespuesta" required onchange="toggleOpciones()">
                            <option value="">Selecciona un tipo</option>
                            <option value="texto_corto">Texto Corto</option>
                            <option value="texto_largo">Texto Largo</option>
                            <option value="si_no">Sí / No</option>
                            <option value="opcion_multiple">Opción Múltiple</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Orden <span style="color: #dc3545;">*</span></label>
                        <input type="number" name="orden" id="ordenInput" value="1" min="1" required>
                    </div>
                </div>

                <div id="opcionesContainer" class="form-group">
                    <label>Opciones (una por línea)</label>
                    <textarea name="opciones" id="opcionesTextarea" rows="5" placeholder="Opción 1&#10;Opción 2&#10;Opción 3"></textarea>
                    <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Escribe cada opción en una línea diferente
                    </small>
                </div>

                <div class="btn-group" style="justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalAgregar')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Agregar Pregunta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Pregunta</h3>
                <button class="close-modal" onclick="cerrarModal('modalEditar')">&times;</button>
            </div>
            <form method="POST" id="formEditar">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_pregunta" id="edit_id_pregunta">
                
                <div class="form-group">
                    <label>Categoría <span style="color: #dc3545;">*</span></label>
                    <select name="categoria" id="edit_categoria" required>
                        <option value="perfil">Sobre tu Perfil</option>
                        <option value="objetivos">Objetivos y Motivación</option>
                        <option value="experiencia">Experiencia y Comunidad</option>
                        <option value="cierre">Preguntas de Cierre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Texto de la Pregunta <span style="color: #dc3545;">*</span></label>
                    <textarea name="pregunta" id="edit_pregunta" required style="min-height: 80px;"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Respuesta <span style="color: #dc3545;">*</span></label>
                        <select name="tipo_respuesta" id="edit_tipo" required onchange="toggleOpcionesEditar()">
                            <option value="texto_corto">Texto Corto</option>
                            <option value="texto_largo">Texto Largo</option>
                            <option value="si_no">Sí / No</option>
                            <option value="opcion_multiple">Opción Múltiple</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Orden <span style="color: #dc3545;">*</span></label>
                        <input type="number" name="orden" id="edit_orden" min="1" required>
                    </div>
                </div>

                <div id="opcionesEditarContainer" class="form-group">
                    <label>Opciones (una por línea)</label>
                    <textarea name="opciones" id="edit_opciones" rows="5"></textarea>
                </div>

                <div class="btn-group" style="justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEditar')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const ordenesPorCategoria = <?= json_encode([
            'perfil' => obtenerSiguienteOrden($pdo, 'perfil'),
            'objetivos' => obtenerSiguienteOrden($pdo, 'objetivos'),
            'experiencia' => obtenerSiguienteOrden($pdo, 'experiencia'),
            'cierre' => obtenerSiguienteOrden($pdo, 'cierre')
        ]) ?>;
        
        function cambiarTab(categoria) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.closest('.tab').classList.add('active');
            document.getElementById('tab-' + categoria).classList.add('active');
        }
        
        function abrirModalAgregar() {
            document.getElementById('modalAgregar').classList.add('active');
        }
        
        function cerrarModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function actualizarOrden() {
            const categoria = document.getElementById('categoriaSelect').value;
            if (categoria && ordenesPorCategoria[categoria]) {
                document.getElementById('ordenInput').value = ordenesPorCategoria[categoria];
            }
        }
        
        function toggleOpciones() {
            const tipo = document.getElementById('tipoRespuesta').value;
            const container = document.getElementById('opcionesContainer');
            
            if (tipo === 'opcion_multiple') {
                container.style.display = 'block';
                document.getElementById('opcionesTextarea').required = true;
            } else {
                container.style.display = 'none';
                document.getElementById('opcionesTextarea').required = false;
                document.getElementById('opcionesTextarea').value = '';
            }
        }

        function toggleOpcionesEditar() {
            const tipo = document.getElementById('edit_tipo').value;
            const container = document.getElementById('opcionesEditarContainer');
            
            if (tipo === 'opcion_multiple') {
                container.style.display = 'block';
                document.getElementById('edit_opciones').required = true;
            } else {
                container.style.display = 'none';
                document.getElementById('edit_opciones').required = false;
            }
        }

        function editarPregunta(pregunta) {
            document.getElementById('edit_id_pregunta').value = pregunta.Id_Pregunta;
            document.getElementById('edit_pregunta').value = pregunta.Pregunta;
            document.getElementById('edit_tipo').value = pregunta.Tipo_Respuesta;
            document.getElementById('edit_orden').value = pregunta.Orden;
            document.getElementById('edit_categoria').value = pregunta.Categoria || 'perfil';
            
            if (pregunta.Tipo_Respuesta === 'opcion_multiple' && pregunta.Opciones) {
                const opciones = JSON.parse(pregunta.Opciones);
                document.getElementById('edit_opciones').value = opciones.join('\n');
            } else {
                document.getElementById('edit_opciones').value = '';
            }
            
            toggleOpcionesEditar();
            document.getElementById('modalEditar').classList.add('active');
        }

        function confirmarDesactivacion(event) {
            event.preventDefault();
            Swal.fire({
                title: '¿Desactivar pregunta?',
                text: "La pregunta se marcará como inactiva pero no se eliminará del sistema.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    event.target.submit();
                }
            });
        }

        document.getElementById('formAgregar').addEventListener('submit', function(e) {
            const tipo = document.getElementById('tipoRespuesta').value;
            if (tipo === 'opcion_multiple') {
                const textarea = document.getElementById('opcionesTextarea');
                const opciones = textarea.value.split('\n').filter(o => o.trim() !== '');
                if (opciones.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debe ingresar al menos una opción para el tipo de respuesta múltiple.'
                    });
                    return;
                }
                textarea.value = JSON.stringify(opciones);
            }
        });

        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const tipo = document.getElementById('edit_tipo').value;
            if (tipo === 'opcion_multiple') {
                const textarea = document.getElementById('edit_opciones');
                const opciones = textarea.value.split('\n').filter(o => o.trim() !== '');
                if (opciones.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debe ingresar al menos una opción para el tipo de respuesta múltiple.'
                    });
                    return;
                }
                textarea.value = JSON.stringify(opciones);
            }
        });

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModal(this.id);
                }
            });
        });
    </script>
</body>
</html>