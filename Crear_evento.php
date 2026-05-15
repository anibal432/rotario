<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

$sql_tipos = "SELECT * FROM Tipos_Evento WHERE Estado = 'Activo' ORDER BY Nombre";
$stmt_tipos = $pdo->prepare($sql_tipos);
$stmt_tipos->execute();
$tipos_evento = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Evento - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/evento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Crear Nuevo Evento</h1>
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

            <form id="formEvento" enctype="multipart/form-data">
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Información General
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width required">
                            <label for="nombre_evento">Nombre del Evento</label>
                            <input type="text" id="nombre_evento" name="nombre_evento" required>
                        </div>

                        <div class="form-group required">
                            <label for="id_tipo_evento">Tipo de Evento</label>
                            <select id="id_tipo_evento" name="id_tipo_evento" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($tipos_evento as $tipo): ?>
                                <option value="<?= $tipo['Id_Tipo_Evento'] ?>">
                                    <?= htmlspecialchars($tipo['Nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group required">
                            <label for="fecha_evento">Fecha del Evento</label>
                            <input type="date" id="fecha_evento" name="fecha_evento" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group required">
                            <label for="hora_inicio">Hora de Inicio</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" required>
                        </div>

                        <div class="form-group">
                            <label for="hora_salida">Hora de Salida (opcional)</label>
                            <input type="time" id="hora_salida" name="hora_salida">
                        </div>

                        <div class="form-group">
                            <label for="cupo_maximo">Cupo Máximo (opcional)</label>
                            <input type="number" id="cupo_maximo" name="cupo_maximo" min="1">
                        </div>

                        <div class="form-group">
                            <label for="distancia_km">Distancia (km)</label>
                            <input type="number" id="distancia_km" name="distancia_km" step="0.01" min="0">
                        </div>

                        <div class="form-group full-width required">
                            <label for="lugar_salida">Lugar de Salida</label>
                            <textarea id="lugar_salida" name="lugar_salida" rows="3" required></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="descripcion">Descripción del Evento</label>
                            <textarea id="descripcion" name="descripcion" rows="4"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="recorrido">Detalles del Recorrido</label>
                            <textarea id="recorrido" name="recorrido" rows="3"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="causa_beneficiada">Causa Beneficiada</label>
                            <textarea id="causa_beneficiada" name="causa_beneficiada" rows="2"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="imagen_banner">Imagen Banner del Evento</label>
                            <input type="file" id="imagen_banner" name="imagen_banner" accept="image/*" class="file-input">
                            <small>JPG, PNG. Tamaño recomendado: 1200x400px</small>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-medal"></i> Categorías del Evento
                    </h3>
                    <div class="categorias-container" id="categoriasContainer">
                        <div class="item-row" data-index="0">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'categoria')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Nombre de Categoría</label>
                                    <input type="text" name="categorias[0][nombre]" required>
                                </div>
                                <div class="form-group required">
                                    <label>Género</label>
                                    <select name="categorias[0][genero]" required>
                                        <option value="">Selecciona...</option>
                                        <option value="Masculino">Masculino</option>
                                        <option value="Femenino">Femenino</option>
                                        <option value="Mixto">Mixto</option>
                                        <option value="Todos">Todos</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Edad Mínima</label>
                                    <input type="number" name="categorias[0][edad_min]" min="0">
                                </div>
                                <div class="form-group">
                                    <label>Edad Máxima</label>
                                    <input type="number" name="categorias[0][edad_max]" min="0">
                                </div>
                                <div class="form-group full-width">
                                    <label>Descripción</label>
                                    <input type="text" name="categorias[0][descripcion]">
                                </div>
                            </div>
                        </div>
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
                        <div class="item-row" data-index="0">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'costo')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Tipo de Inscripción</label>
                                    <input type="text" name="costos[0][tipo]" placeholder="Ej: Inscripción Temprana" required>
                                </div>
                                <div class="form-group required">
                                    <label>Costo (Q)</label>
                                    <input type="number" name="costos[0][costo]" step="0.01" min="0" required>
                                </div>
                                <div class="form-group required">
                                    <label>Válido Desde</label>
                                    <input type="date" name="costos[0][fecha_inicio]" required>
                                </div>
                                <div class="form-group required">
                                    <label>Válido Hasta</label>
                                    <input type="date" name="costos[0][fecha_fin]" required>
                                </div>
                                <div class="form-group full-width">
                                    <label>Descripción</label>
                                    <input type="text" name="costos[0][descripcion]" placeholder="Ej: Aplica hasta el 30 de octubre">
                                </div>
                            </div>
                        </div>
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
                        <div class="item-row" data-index="0">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'cuenta')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Banco</label>
                                    <input type="text" name="cuentas[0][banco]" placeholder="Ej: Banco Industrial" required>
                                </div>
                                <div class="form-group required">
                                    <label>Número de Cuenta</label>
                                    <input type="text" name="cuentas[0][numero]" required>
                                </div>
                                <div class="form-group required">
                                    <label>Nombre de la Cuenta</label>
                                    <input type="text" name="cuentas[0][nombre]" placeholder="Ej: Club Rotario Coatepeque" required>
                                </div>
                                <div class="form-group required">
                                    <label>Tipo de Cuenta</label>
                                    <select name="cuentas[0][tipo]" required>
                                        <option value="">Selecciona...</option>
                                        <option value="Ahorro">Ahorro</option>
                                        <option value="Monetaria">Monetaria</option>
                                        <option value="Cheques">Cheques</option>
                                        <option value="Empresarial">Empresarial</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Moneda</label>
                                    <select name="cuentas[0][moneda]">
                                        <option value="GTQ">Quetzales (GTQ)</option>
                                        <option value="USD">Dólares (USD)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCuenta()">
                        <i class="fas fa-plus"></i> Agregar Cuenta Bancaria
                    </button>
                </div>

                <div class="form-card">
                    <div class="action-buttons">
                        <button type="button" class="btn btn-cancel" onclick="confirmCancel()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnSubmit">
                            <i class="fas fa-save"></i> Crear Evento
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        let categoriaIndex = 1;
        let costoIndex = 1;
        let cuentaIndex = 1;

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
                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: 'Debe haber al menos un elemento de este tipo',
                    confirmButtonColor: '#004b87'
                });
            }
        }

        function confirmCancel() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Los cambios no guardados se perderán',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004b87',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'Continuar editando'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'admin.php';
                }
            });
        }

        document.getElementById('formEvento').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btnSubmit = document.getElementById('btnSubmit');
            
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando evento...';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('procesar_crear_evento.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.id_evento) {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            html: `
                                ${data.message}<br><br>
                                <strong>Enlace al evento:</strong><br>
                                <a href="../evento.php?id=${data.id_evento}" target="_blank" style="color: #155724; text-decoration: underline;">
                                    evento.php?id=${data.id_evento}
                                </a>
                            `,
                            confirmButtonColor: '#004b87',
                            confirmButtonText: 'Ver Evento'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = '../evento.php?id=' + data.id_evento;
                            } else {
                                window.location.href = 'admin.php';
                            }
                        });
                    } else {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.message,
                            confirmButtonColor: '#004b87'
                        });
                        window.location.href = 'admin.php';
                    }
                } else {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message,
                        confirmButtonColor: '#004b87'
                    });
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-save"></i> Crear Evento';
                }
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al crear el evento. Intenta nuevamente.',
                    confirmButtonColor: '#004b87'
                });
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-save"></i> Crear Evento';
            }
        });
    </script>
</body>
</html>