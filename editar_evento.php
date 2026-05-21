<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ver_eventos.php');
    exit;
}

$id_evento = (int)$_GET['id'];
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';

// Obtener datos del evento
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

// Obtener tipos de evento
$sql_tipos = "SELECT * FROM Tipos_Evento WHERE Estado = 'Activo' ORDER BY Nombre";
$stmt_tipos = $pdo->prepare($sql_tipos);
$stmt_tipos->execute();
$tipos_evento = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías actuales
$sql_categorias = "SELECT * FROM Categorias_Evento WHERE Id_Evento = ? ORDER BY Nombre_Categoria";
$stmt_cat = $pdo->prepare($sql_categorias);
$stmt_cat->execute([$id_evento]);
$categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

// Obtener costos actuales
$sql_costos = "SELECT * FROM Costos_Inscripcion WHERE Id_Evento = ? ORDER BY Fecha_Inicio";
$stmt_cos = $pdo->prepare($sql_costos);
$stmt_cos->execute([$id_evento]);
$costos = $stmt_cos->fetchAll(PDO::FETCH_ASSOC);

// Obtener cuentas actuales
$sql_cuentas = "SELECT * FROM Cuentas_Bancarias WHERE Id_Evento = ? ORDER BY Orden_Prioridad";
$stmt_cue = $pdo->prepare($sql_cuentas);
$stmt_cue->execute([$id_evento]);
$cuentas = $stmt_cue->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Evento - <?= htmlspecialchars($evento['Nombre_Evento']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/evento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <main class="main-content">
            <div class="header">
                <h1>Editar Evento</h1>
                <div class="user-info">
                    <div class="user-avatar-wrapper">
                        <?php
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

            <form id="formEditarEvento" enctype="multipart/form-data">
                <input type="hidden" name="id_evento" value="<?= $id_evento ?>">

                <!-- INFORMACIÓN GENERAL -->
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Información General
                    </h3>
                    <div class="form-grid">
                        <div class="form-group full-width required">
                            <label for="nombre_evento">Nombre del Evento</label>
                            <input type="text" id="nombre_evento" name="nombre_evento" required
                                   value="<?= htmlspecialchars($evento['Nombre_Evento']) ?>">
                        </div>

                        <div class="form-group required">
                            <label for="id_tipo_evento">Tipo de Evento</label>
                            <select id="id_tipo_evento" name="id_tipo_evento" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($tipos_evento as $tipo): ?>
                                <option value="<?= $tipo['Id_Tipo_Evento'] ?>"
                                    <?= $tipo['Id_Tipo_Evento'] == $evento['Id_Tipo_Evento'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['Nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group required">
                            <label for="fecha_evento">Fecha del Evento</label>
                            <input type="date" id="fecha_evento" name="fecha_evento" required
                                   value="<?= $evento['Fecha_Evento'] ?>">
                        </div>

                        <div class="form-group required">
                            <label for="hora_inicio">Hora de Inicio</label>
                            <input type="time" id="hora_inicio" name="hora_inicio" required
                                   value="<?= substr($evento['Hora_Inicio'], 0, 5) ?>">
                        </div>

                        <div class="form-group">
                            <label for="estado_evento">Estado del Evento</label>
                            <select id="estado_evento" name="estado_evento">
                                <?php
                                $estados = ['Planificado', 'Inscripciones Abiertas', 'Inscripciones Cerradas', 'En Curso', 'Finalizado', 'Cancelado'];
                                foreach ($estados as $estado):
                                ?>
                                <option value="<?= $estado ?>"
                                    <?= $evento['Estado_Evento'] === $estado ? 'selected' : '' ?>>
                                    <?= $estado ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cupo_maximo">Cupo Máximo (opcional)</label>
                            <input type="number" id="cupo_maximo" name="cupo_maximo" min="1"
                                   value="<?= $evento['Cupo_Maximo'] ?>">
                        </div>

                        <div class="form-group">
                            <label for="distancia_km">Distancia (km)</label>
                            <input type="number" id="distancia_km" name="distancia_km" step="0.01" min="0"
                                   value="<?= $evento['Distancia_KM'] ?>">
                        </div>

                        <div class="form-group full-width required">
                            <label for="lugar_salida">Lugar de Salida</label>
                            <textarea id="lugar_salida" name="lugar_salida" rows="3" required><?= htmlspecialchars($evento['Lugar_Salida']) ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="descripcion">Descripción del Evento</label>
                            <textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($evento['Descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="recorrido">Detalles del Recorrido</label>
                            <textarea id="recorrido" name="recorrido" rows="3"><?= htmlspecialchars($evento['Recorrido'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="causa_beneficiada">Causa Beneficiada</label>
                            <textarea id="causa_beneficiada" name="causa_beneficiada" rows="2"><?= htmlspecialchars($evento['Causa_Beneficiada'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="imagen_banner">Imagen Banner (dejar vacío para mantener la actual)</label>
                            <?php if ($evento['Imagen_Banner']): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="<?= htmlspecialchars($evento['Imagen_Banner']) ?>"
                                     alt="Banner actual"
                                     style="max-height: 120px; border-radius: 8px; border: 2px solid #e0e0e0;">
                                <p style="font-size: 0.85em; color: #666; margin-top: 5px;">
                                    <i class="fas fa-image"></i> Banner actual
                                </p>
                            </div>
                            <?php endif; ?>
                            <input type="file" id="imagen_banner" name="imagen_banner" accept="image/*" class="file-input">
                            <small>JPG, PNG. Tamaño recomendado: 1200x400px</small>
                        </div>
                    </div>
                </div>

                <!-- CATEGORÍAS -->
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-medal"></i> Categorías del Evento
                    </h3>
                    <div class="categorias-container" id="categoriasContainer">
                        <?php foreach ($categorias as $i => $cat): ?>
                        <div class="item-row" data-index="<?= $i ?>">
                            <input type="hidden" name="categorias[<?= $i ?>][id]" value="<?= $cat['Id_Categoria'] ?>">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'categoria')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Nombre de Categoría</label>
                                    <input type="text" name="categorias[<?= $i ?>][nombre]" required
                                           value="<?= htmlspecialchars($cat['Nombre_Categoria']) ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Género</label>
                                    <select name="categorias[<?= $i ?>][genero]" required>
                                        <?php foreach (['Masculino','Femenino','Mixto','Todos'] as $g): ?>
                                        <option value="<?= $g ?>" <?= $cat['Genero'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Edad Mínima</label>
                                    <input type="number" name="categorias[<?= $i ?>][edad_min]" min="0"
                                           value="<?= $cat['Edad_Minima'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Edad Máxima</label>
                                    <input type="number" name="categorias[<?= $i ?>][edad_max]" min="0"
                                           value="<?= $cat['Edad_Maxima'] ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label>Descripción</label>
                                    <input type="text" name="categorias[<?= $i ?>][descripcion]"
                                           value="<?= htmlspecialchars($cat['Descripcion'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($categorias)): ?>
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
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCategoria()">
                        <i class="fas fa-plus"></i> Agregar Categoría
                    </button>
                </div>

                <!-- COSTOS -->
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i> Costos de Inscripción
                    </h3>
                    <div class="costos-container" id="costosContainer">
                        <?php foreach ($costos as $i => $costo): ?>
                        <div class="item-row" data-index="<?= $i ?>">
                            <input type="hidden" name="costos[<?= $i ?>][id]" value="<?= $costo['Id_Costo'] ?>">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'costo')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Tipo de Inscripción</label>
                                    <input type="text" name="costos[<?= $i ?>][tipo]" required
                                           value="<?= htmlspecialchars($costo['Tipo_Inscripcion']) ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Costo (Q)</label>
                                    <input type="number" name="costos[<?= $i ?>][costo]" step="0.01" min="0" required
                                           value="<?= $costo['Costo'] ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Válido Desde</label>
                                    <input type="date" name="costos[<?= $i ?>][fecha_inicio]" required
                                           value="<?= $costo['Fecha_Inicio'] ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Válido Hasta</label>
                                    <input type="date" name="costos[<?= $i ?>][fecha_fin]" required
                                           value="<?= $costo['Fecha_Fin'] ?>">
                                </div>
                                <div class="form-group full-width">
                                    <label>Descripción</label>
                                    <input type="text" name="costos[<?= $i ?>][descripcion]"
                                           value="<?= htmlspecialchars($costo['Descripcion'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($costos)): ?>
                        <div class="item-row" data-index="0">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'costo')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Tipo de Inscripción</label>
                                    <input type="text" name="costos[0][tipo]" required>
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
                                    <input type="text" name="costos[0][descripcion]">
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCosto()">
                        <i class="fas fa-plus"></i> Agregar Costo
                    </button>
                </div>

                <!-- CUENTAS BANCARIAS -->
                <div class="form-card">
                    <h3 class="section-title">
                        <i class="fas fa-university"></i> Cuentas Bancarias para Pago
                    </h3>
                    <div class="cuentas-container" id="cuentasContainer">
                        <?php foreach ($cuentas as $i => $cuenta): ?>
                        <div class="item-row" data-index="<?= $i ?>">
                            <input type="hidden" name="cuentas[<?= $i ?>][id]" value="<?= $cuenta['Id_Cuenta'] ?>">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'cuenta')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Banco</label>
                                    <input type="text" name="cuentas[<?= $i ?>][banco]" required
                                           value="<?= htmlspecialchars($cuenta['Nombre_Banco']) ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Número de Cuenta</label>
                                    <input type="text" name="cuentas[<?= $i ?>][numero]" required
                                           value="<?= htmlspecialchars($cuenta['Numero_Cuenta']) ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Nombre de la Cuenta</label>
                                    <input type="text" name="cuentas[<?= $i ?>][nombre]" required
                                           value="<?= htmlspecialchars($cuenta['Nombre_Cuenta']) ?>">
                                </div>
                                <div class="form-group required">
                                    <label>Tipo de Cuenta</label>
                                    <select name="cuentas[<?= $i ?>][tipo]" required>
                                        <?php foreach (['Ahorro','Monetaria','Cheques','Empresarial'] as $tipo): ?>
                                        <option value="<?= $tipo ?>" <?= $cuenta['Tipo_Cuenta'] === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Moneda</label>
                                    <select name="cuentas[<?= $i ?>][moneda]">
                                        <option value="GTQ" <?= $cuenta['Moneda'] === 'GTQ' ? 'selected' : '' ?>>Quetzales (GTQ)</option>
                                        <option value="USD" <?= $cuenta['Moneda'] === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($cuentas)): ?>
                        <div class="item-row" data-index="0">
                            <button type="button" class="btn btn-danger remove-btn" onclick="removeItem(this, 'cuenta')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <div class="form-grid">
                                <div class="form-group required">
                                    <label>Banco</label>
                                    <input type="text" name="cuentas[0][banco]" required>
                                </div>
                                <div class="form-group required">
                                    <label>Número de Cuenta</label>
                                    <input type="text" name="cuentas[0][numero]" required>
                                </div>
                                <div class="form-group required">
                                    <label>Nombre de la Cuenta</label>
                                    <input type="text" name="cuentas[0][nombre]" required>
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
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addCuenta()">
                        <i class="fas fa-plus"></i> Agregar Cuenta Bancaria
                    </button>
                </div>

                <!-- ACCIONES -->
                <div class="form-card">
                    <div class="action-buttons">
                        <button type="button" class="btn btn-cancel" onclick="confirmCancel()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btnSubmit">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        let categoriaIndex = <?= max(count($categorias), 1) ?>;
        let costoIndex     = <?= max(count($costos), 1) ?>;
        let cuentaIndex    = <?= max(count($cuentas), 1) ?>;

        function addCategoria() {
            const container = document.getElementById('categoriasContainer');
            container.insertAdjacentHTML('beforeend', `
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
                </div>`);
            categoriaIndex++;
        }

        function addCosto() {
            const container = document.getElementById('costosContainer');
            container.insertAdjacentHTML('beforeend', `
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
                </div>`);
            costoIndex++;
        }

        function addCuenta() {
            const container = document.getElementById('cuentasContainer');
            container.insertAdjacentHTML('beforeend', `
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
                </div>`);
            cuentaIndex++;
        }

        function removeItem(button, type) {
            const container = button.closest('.item-row').parentElement;
            if (container.querySelectorAll('.item-row').length > 1) {
                button.closest('.item-row').remove();
            } else {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe haber al menos un elemento', confirmButtonColor: '#004b87' });
            }
        }

        function confirmCancel() {
            Swal.fire({
                title: '¿Descartar cambios?',
                text: 'Los cambios no guardados se perderán',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004b87',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, descartar',
                cancelButtonText: 'Seguir editando'
            }).then(result => {
                if (result.isConfirmed)
                    window.location.href = 'detalle_evento.php?id=<?= $id_evento ?>';
            });
        }

        let enviando = false;

        document.getElementById('formEditarEvento').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (enviando) return;
            enviando = true;

            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                const response = await fetch('procesar_editar_evento.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
                const data = await response.json();

                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: data.message,
                        confirmButtonColor: '#004b87'
                    });
                    window.location.href = 'detalle_evento.php?id=<?= $id_evento ?>';
                } else {
                    await Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#004b87' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
                    enviando = false;
                }
            } catch (error) {
                await Swal.fire({ icon: 'error', title: 'Error', text: 'Error inesperado. Intenta nuevamente.', confirmButtonColor: '#004b87' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
                enviando = false;
            }
        });
    </script>
</body>
</html>