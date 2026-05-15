<?php

require_once 'conexion.php';


$id_evento = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_evento === 0) {
    header('Location: eventos.php');
    exit;
}


$sql_evento = "
    SELECT 
        e.*,
        te.Nombre as Tipo_Evento_Nombre,
        te.Descripcion as Tipo_Evento_Descripcion
    FROM Eventos e
    INNER JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
    WHERE e.Id_Evento = ?
";

$stmt = $conn->prepare($sql_evento);
$stmt->bind_param("i", $id_evento);
$stmt->execute();
$resultado = $stmt->get_result();
$evento = $resultado->fetch_assoc();

if (!$evento) {
    header('Location: eventos.php');
    exit;
}

// Verificar si el evento ya pasó o está cerrado
$fecha_evento = new DateTime($evento['Fecha_Evento']);
$fecha_actual = new DateTime();
$evento_pasado = $fecha_actual > $fecha_evento;
$inscripciones_cerradas = $evento['Estado_Evento'] === 'Inscripciones Cerradas' || 
                          $evento['Estado_Evento'] === 'Finalizado' || 
                          $evento['Estado_Evento'] === 'Cancelado';

// Obtener categorías del evento
$sql_categorias = "SELECT * FROM Categorias_Evento WHERE Id_Evento = ? AND Estado = 'Activa' ORDER BY Nombre_Categoria";
$stmt_cat = $conn->prepare($sql_categorias);
$stmt_cat->bind_param("i", $id_evento);
$stmt_cat->execute();
$categorias = $stmt_cat->get_result();

// Obtener costos de inscripción activos
$sql_costos = "
    SELECT * FROM Costos_Inscripcion 
    WHERE Id_Evento = ? 
    AND Estado = 'Activo'
    AND CURDATE() BETWEEN Fecha_Inicio AND Fecha_Fin
    ORDER BY Costo ASC
";
$stmt_costos = $conn->prepare($sql_costos);
$stmt_costos->bind_param("i", $id_evento);
$stmt_costos->execute();
$costos = $stmt_costos->get_result();

// Obtener cuentas bancarias
$sql_cuentas = "
    SELECT * FROM Cuentas_Bancarias 
    WHERE Id_Evento = ? AND Estado = 'Activa'
    ORDER BY Orden_Prioridad ASC
";
$stmt_cuentas = $conn->prepare($sql_cuentas);
$stmt_cuentas->bind_param("i", $id_evento);
$stmt_cuentas->execute();
$cuentas = $stmt_cuentas->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($evento['Nombre_Evento']); ?> - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary-color: #dbeafe;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --background: #f8fafc;
            --white: #ffffff;
            --border-color: #d1d5db;
            --border-radius: 8px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: var(--background);
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
        }

        .back-button:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(-3px);
        }

        .evento-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 80px 0 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        <?php if ($evento['Imagen_Banner']): ?>
        .evento-banner {
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.9), rgba(59, 130, 246, 0.9)),
                        url('<?php echo htmlspecialchars($evento['Imagen_Banner']); ?>');
            background-size: cover;
            background-position: center;
        }
        <?php endif; ?>

        .evento-banner h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .evento-badge {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .evento-cerrado {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            padding: 40px 20px;
            text-align: center;
            margin: 40px auto;
            max-width: 800px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
        }

        .evento-cerrado i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .evento-cerrado h2 {
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .evento-cerrado p {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .info-card {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .info-card h3 {
            color: var(--primary-color);
            font-size: 1.4rem;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
        }

        .info-card ul {
            list-style: none;
        }

        .info-card li {
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
        }

        .info-card li:last-child {
            border-bottom: none;
        }

        .info-card li i {
            color: var(--primary-light);
            margin-right: 12px;
            width: 24px;
        }

        .bank-info {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-top: 20px;
        }

        .bank-details {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }

        .bank-details p {
            margin: 8px 0;
        }

        .registro-form {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-top: 40px;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .form-container {
            padding: 40px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group.required label::after {
            content: ' *';
            color: var(--error-color);
        }

        label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        input, select, textarea {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            border-color: var(--primary-light);
            background: var(--secondary-color);
        }

        .success-message, .error-message {
            display: none;
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            text-align: center;
        }

        .success-message {
            background: #fef3c7;
            color: #92400e;
            border: 2px solid #fbbf24;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #fca5a5;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .evento-banner h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <a href="eventos.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span>Ver Todos los Eventos</span>
    </a>

    <div class="evento-banner">
        <div class="container">
            <h1><i class="fas fa-running"></i> <?php echo htmlspecialchars($evento['Nombre_Evento']); ?></h1>
            <p style="font-size: 1.2rem;"><?php echo nl2br(htmlspecialchars($evento['Descripcion'])); ?></p>
            <span class="evento-badge">
                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($evento['Tipo_Evento_Nombre']); ?>
            </span>
        </div>
    </div>

    <div class="container">
        <?php if ($evento_pasado || $inscripciones_cerradas): ?>
            <div class="evento-cerrado">
                <i class="fas fa-calendar-times"></i>
                <h2>
                    <?php if ($evento['Estado_Evento'] === 'Finalizado'): ?>
                        ¡Este evento ya finalizó!
                    <?php elseif ($evento['Estado_Evento'] === 'Cancelado'): ?>
                        Este evento ha sido cancelado
                    <?php else: ?>
                        Las inscripciones están cerradas
                    <?php endif; ?>
                </h2>
                <p>Fecha del evento: <?php echo date('d/m/Y', strtotime($evento['Fecha_Evento'])); ?></p>
                <p style="margin-top: 20px; font-size: 1.1rem;">
                    <i class="fas fa-info-circle"></i> 
                    ¡Pronto habrá nuevos eventos! Mantente atento a nuestras redes sociales.
                </p>
                <div style="margin-top: 30px;">
                    <a href="eventos.php" class="btn-primary" style="display: inline-flex; text-decoration: none;">
                        <i class="fas fa-calendar-alt"></i> Ver Próximos Eventos
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Información General</h3>
                    <ul>
                        <li><i class="fas fa-calendar"></i> Fecha: <?php echo date('d/m/Y', strtotime($evento['Fecha_Evento'])); ?></li>
                        <li><i class="fas fa-clock"></i> Hora: <?php echo date('h:i A', strtotime($evento['Hora_Inicio'])); ?></li>
                        <?php if ($evento['Hora_Salida']): ?>
                        <li><i class="fas fa-flag"></i> Salida: <?php echo date('h:i A', strtotime($evento['Hora_Salida'])); ?></li>
                        <?php endif; ?>
                        <li><i class="fas fa-map-marker-alt"></i> <?php echo nl2br(htmlspecialchars($evento['Lugar_Salida'])); ?></li>
                        <?php if ($evento['Distancia_KM']): ?>
                        <li><i class="fas fa-route"></i> Distancia: <?php echo $evento['Distancia_KM']; ?> km</li>
                        <?php endif; ?>
                        <?php if ($evento['Cupo_Maximo']): ?>
                        <li><i class="fas fa-users"></i> Cupo: <?php echo $evento['Cupo_Maximo']; ?> participantes</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <?php if ($costos->num_rows > 0): ?>
                <div class="info-card">
                    <h3><i class="fas fa-dollar-sign"></i> Costos de Inscripción</h3>
                    <ul>
                        <?php while ($costo = $costos->fetch_assoc()): ?>
                        <li>
                            <i class="fas fa-tag"></i> 
                            <strong><?php echo htmlspecialchars($costo['Tipo_Inscripcion']); ?>:</strong> 
                            Q<?php echo number_format($costo['Costo'], 2); ?>
                            <?php if ($costo['Descripcion']): ?>
                            <br><small style="margin-left: 36px; color: var(--text-secondary);">
                                <?php echo htmlspecialchars($costo['Descripcion']); ?>
                            </small>
                            <?php endif; ?>
                        </li>
                        <?php endwhile; ?>
                    </ul>

                    <?php 
                    $costos->data_seek(0);
                    if ($cuentas->num_rows > 0): 
                    ?>
                    <div class="bank-info">
                        <h4><i class="fas fa-university"></i> Información de Pago</h4>
                        <?php while ($cuenta = $cuentas->fetch_assoc()): ?>
                        <div class="bank-details">
                            <p><strong>Banco:</strong> <?php echo htmlspecialchars($cuenta['Nombre_Banco']); ?></p>
                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cuenta['Nombre_Cuenta']); ?></p>
                            <p><strong>No. Cuenta:</strong> <?php echo htmlspecialchars($cuenta['Numero_Cuenta']); ?></p>
                            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($cuenta['Tipo_Cuenta']); ?></p>
                            <p><strong>Moneda:</strong> <?php echo $cuenta['Moneda']; ?></p>
                        </div>
                        <?php endwhile; ?>
                        <p style="margin-top: 10px; font-size: 0.9rem;">
                            <i class="fas fa-exclamation-circle"></i> 
                            Realiza tu pago y sube la boleta en el formulario
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($categorias->num_rows > 0): ?>
                <div class="info-card">
                    <h3><i class="fas fa-list-check"></i> Categorías</h3>
                    <ul>
                        <?php while ($categoria = $categorias->fetch_assoc()): ?>
                        <li>
                            <i class="fas fa-medal"></i> 
                            <?php echo htmlspecialchars($categoria['Nombre_Categoria']); ?>
                            <?php if ($categoria['Edad_Minima'] || $categoria['Edad_Maxima']): ?>
                            <br><small style="margin-left: 36px; color: var(--text-secondary);">
                                <?php 
                                if ($categoria['Edad_Minima'] && $categoria['Edad_Maxima']) {
                                    echo $categoria['Edad_Minima'] . "-" . $categoria['Edad_Maxima'] . " años";
                                } elseif ($categoria['Edad_Minima']) {
                                    echo $categoria['Edad_Minima'] . "+ años";
                                } elseif ($categoria['Edad_Maxima']) {
                                    echo "Hasta " . $categoria['Edad_Maxima'] . " años";
                                }
                                echo " - " . $categoria['Genero'];
                                ?>
                            </small>
                            <?php endif; ?>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <div class="registro-form">
                <div class="form-header">
                    <h3><i class="fas fa-user-plus"></i> Formulario de Inscripción</h3>
                    <p>Completa todos los campos para inscribirte</p>
                </div>

                <div class="form-container">
                    <form id="registrationForm" enctype="multipart/form-data">
                        <input type="hidden" name="id_evento" value="<?php echo $id_evento; ?>">
                        
                        <div class="form-grid">
                            <?php if ($categorias->num_rows > 0): ?>
                            <div class="form-group full-width required">
                                <label for="id_categoria">Selecciona tu Categoría</label>
                                <select id="id_categoria" name="id_categoria" required>
                                    <option value="">Selecciona...</option>
                                    <?php 
                                    $categorias->data_seek(0);
                                    while ($categoria = $categorias->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $categoria['Id_Categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['Nombre_Categoria']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <?php if ($costos->num_rows > 0): ?>
                            <div class="form-group full-width required">
                                <label for="id_costo">Tipo de Inscripción</label>
                                <select id="id_costo" name="id_costo" required>
                                    <option value="">Selecciona...</option>
                                    <?php 
                                    $costos->data_seek(0);
                                    while ($costo = $costos->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $costo['Id_Costo']; ?>">
                                        <?php echo htmlspecialchars($costo['Tipo_Inscripcion']); ?> - Q<?php echo number_format($costo['Costo'], 2); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-group required">
                                <label for="nombre">Nombre Completo</label>
                                <input type="text" id="nombre" name="nombre" required>
                            </div>

                            <div class="form-group required">
                                <label for="edad">Edad</label>
                                <input type="number" id="edad" name="edad" min="5" max="99" required>
                            </div>

                            <div class="form-group required">
                                <label for="genero">Género</label>
                                <select id="genero" name="genero" required>
                                    <option value="">Selecciona...</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Femenino">Femenino</option>
                                </select>
                            </div>

                            <div class="form-group required">
                                <label for="telefono">Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" required>
                            </div>

                            <div class="form-group required">
                                <label for="email">Correo Electrónico</label>
                                <input type="email" id="email" name="email" required>
                            </div>

                            <div class="form-group required">
                                <label for="dpi">Número de DPI</label>
                                <input type="text" id="dpi" name="dpi" required>
                            </div>

                            <div class="form-group full-width required">
                                <label for="direccion">Dirección Completa</label>
                                <textarea id="direccion" name="direccion" rows="3" required></textarea>
                            </div>

                            <div class="form-group required">
                                <label for="playera">Talla de Playera</label>
                                <select id="playera" name="playera" required>
                                    <option value="">Selecciona...</option>
                                    <option value="XS">Extra Small (XS)</option>
                                    <option value="S">Small (S)</option>
                                    <option value="M">Medium (M)</option>
                                    <option value="L">Large (L)</option>
                                    <option value="XL">Extra Large (XL)</option>
                                    <option value="XXL">XXL</option>
                                    <option value="3XL">3XL</option>
                                </select>
                            </div>

                            <div class="form-group required">
                                <label for="contacto_emergencia">Contacto de Emergencia</label>
                                <input type="text" id="contacto_emergencia" name="contacto_emergencia" required>
                            </div>

                            <div class="form-group required">
                                <label for="telefono_emergencia">Teléfono de Emergencia</label>
                                <input type="tel" id="telefono_emergencia" name="telefono_emergencia" required>
                            </div>

                            <div class="form-group full-width required">
                                <label for="boleta_pago">Boleta de Pago</label>
                                <input type="file" id="boleta_pago" name="boleta_pago" accept="image/*,.pdf" style="display: none;" required>
                                <label for="boleta_pago" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary-light);"></i>
                                    <span>Haz clic para subir tu boleta de pago</span>
                                </label>
                                <small>Formatos: JPG, PNG, PDF. Máximo 5MB</small>
                            </div>

                            <div class="form-group full-width">
                                <label>
                                    <input type="checkbox" id="terminos" required>
                                    Acepto los términos y condiciones del evento
                                </label>
                            </div>
                        </div>

                        <div class="success-message" id="successMessage">
                            <i class="fas fa-check-circle" style="font-size: 3rem;"></i>
                            <h3>¡Inscripción Recibida!</h3>
                            <p>Tu número de participante es: <strong id="numeroParticipante"></strong></p>
                            <p>Recibirás un correo cuando tu inscripción sea confirmada.</p>
                        </div>

                        <div class="error-message" id="errorMessage"></div>

                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane"></i> Inscribirme al Evento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            submitBtn.disabled = true;
            successMsg.style.display = 'none';
            errorMsg.style.display = 'none';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('procesar_inscripcion.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('numeroParticipante').textContent = data.numero_participante;
                    successMsg.style.display = 'block';
                    successMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    setTimeout(() => {
                        this.reset();
                        submitBtn.disabled = false;
                        successMsg.style.display = 'none';
                    }, 8000);
                } else {
                    errorMsg.textContent = data.message || 'Error al procesar la inscripción';
                    errorMsg.style.display = 'block';
                    submitBtn.disabled = false;
                }
            } catch (error) {
                errorMsg.textContent = 'Error al procesar la inscripción. Intenta nuevamente.';
                errorMsg.style.display = 'block';
                submitBtn.disabled = false;
            }
        });

        document.getElementById('boleta_pago').addEventListener('change', function(e) {
            const label = document.querySelector('.file-upload-label span');
            if (e.target.files[0]) {
                label.textContent = e.target.files[0].name;
            }
        });
    </script>
</body>
</html>