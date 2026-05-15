<?php
require_once 'conexion.php';

// Obtener las preguntas del cuestionario
$sql_preguntas = "SELECT * FROM Preguntas_Cuestionario WHERE Estado = 'Activa' ORDER BY Orden";
$stmt_preguntas = $pdo->query($sql_preguntas);
$preguntas = $stmt_preguntas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Beca - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.95;
        }

        .progress-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 30px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #e0e0e0;
            z-index: 1;
        }

        .progress-line {
            position: absolute;
            top: 25px;
            left: 0;
            height: 3px;
            background: linear-gradient(to right, #667eea, #764ba2);
            z-index: 2;
            transition: width 0.3s ease;
        }

        .step {
            position: relative;
            text-align: center;
            flex: 1;
            z-index: 3;
        }

        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e0e0e0;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #999;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            transform: scale(1.1);
        }

        .step.completed .step-circle {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }

        .step-label {
            font-size: 0.9em;
            color: #666;
            font-weight: 500;
        }

        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #666;
            font-size: 1em;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95em;
        }

        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group.col-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.85em;
        }

        .radio-group,
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-group label,
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }

        .radio-group input,
        .checkbox-group input {
            width: auto;
            margin: 0;
        }

        .question-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .question-number {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        .alert i {
            font-size: 1.3em;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            flex: 1;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-back {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-back:hover {
            background: #f8f9fa;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .summary-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .summary-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .summary-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .summary-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        .summary-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .summary-value {
            color: #666;
        }

        .error {
            color: #dc3545;
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
        }

        .form-group.error input,
        .form-group.error select,
        .form-group.error textarea {
            border-color: #dc3545;
        }

        .form-group.error .error {
            display: block;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 25px;
            }

            .form-group.col-2 {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-graduation-cap"></i> Solicitud de Beca</h1>
            <p>Club Rotario Coatepeque Colomba</p>
        </div>

        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line" id="progressLine" style="width: 0%"></div>
                <div class="step active" data-step="1">
                    <div class="step-circle"><i class="fas fa-file-alt"></i></div>
                    <div class="step-label">Carta de Solicitud</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle"><i class="fas fa-question-circle"></i></div>
                    <div class="step-label">Cuestionario</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle"><i class="fas fa-users"></i></div>
                    <div class="step-label">Evaluación Socioeconómica</div>
                </div>
            </div>
        </div>

        <form id="solicitudForm" class="form-container">
            <!-- PASO 1: CARTA DE SOLICITUD -->
            <div class="form-step active" data-step="1">
                <div class="form-header">
                    <h2>Carta de Solicitud de Beca</h2>
                    <p>Por favor completa la información solicitada y redacta tu carta de solicitud.</p>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Importante:</strong> Toda la información proporcionada será verificada. Asegúrate de ser honesto y preciso en tus respuestas.
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Nombres y Apellidos Completos <span class="required">*</span></label>
                        <input type="text" name="nombres_apellidos" required>
                    </div>
                    <div>
                        <label>Edad <span class="required">*</span></label>
                        <input type="number" name="edad" min="5" max="99" required>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Teléfono <span class="required">*</span></label>
                        <input type="tel" name="telefono" placeholder="1234-5678" required>
                    </div>
                    <div>
                        <label>Correo Electrónico <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="correo@ejemplo.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Dirección Completa <span class="required">*</span></label>
                    <textarea name="direccion" rows="2" required></textarea>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Nombre de la Madre</label>
                        <input type="text" name="nombre_madre">
                    </div>
                    <div>
                        <label>Nombre del Padre</label>
                        <input type="text" name="nombre_padre">
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Nombre del Encargado <span class="required">*</span></label>
                        <input type="text" name="nombre_encargado" required>
                    </div>
                    <div>
                        <label>Teléfono del Encargado <span class="required">*</span></label>
                        <input type="tel" name="telefono_encargado" required>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Último Grado Obtenido <span class="required">*</span></label>
                        <input type="text" name="grado_obtenido" placeholder="Ej: 3ro Básico" required>
                    </div>
                    <div>
                        <label>Escuela/Colegio Anterior <span class="required">*</span></label>
                        <input type="text" name="escuela_anterior" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Carta de Solicitud <span class="required">*</span></label>
                    <textarea name="carta_solicitud" rows="10" required 
                              placeholder="Estimados miembros del Club Rotario:

Me dirijo a ustedes con el fin de solicitar su apoyo mediante una beca educativa. Deseo expresar mi interés en...

(Escribe aquí tu carta de solicitud explicando tu situación familiar, tus metas académicas, por qué necesitas la beca y cómo esta te ayudará a alcanzar tus objetivos)"></textarea>
                    <small>Mínimo 200 palabras. Sé claro y honesto sobre tu situación y tus metas.</small>
                </div>

                <div class="button-group">
                    <a href="index.php" class="btn btn-back">
                        <i class="fas fa-arrow-left"></i> Volver al Inicio
                    </a>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 2: CUESTIONARIO -->
            <div class="form-step" data-step="2">
                <div class="form-header">
                    <h2>Cuestionario de Evaluación</h2>
                    <p>Responde las siguientes preguntas con sinceridad y detalle.</p>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Nota:</strong> Tus respuestas nos ayudarán a conocerte mejor y evaluar tu candidatura de manera integral.
                    </div>
                </div>

                <?php 
                // Organizar preguntas por sección
                $secciones = [
                    'Sobre tu Perfil' => [1, 2, 3, 4, 5],
                    'Sobre tus Objetivos y Motivación' => [6, 7, 8, 9],
                    'Sobre tu Experiencia y Comunidad' => [10, 11, 12, 13, 14],
                    'Preguntas de Cierre' => [15, 16]
                ];
                
                $pregunta_count = 0;
                foreach ($secciones as $titulo_seccion => $ordenes):
                ?>
                    <h3 style="color: #667eea; margin: 30px 0 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                        <i class="fas fa-list-ul"></i> <?= $titulo_seccion ?>
                    </h3>
                    
                    <?php 
                    foreach ($preguntas as $index => $pregunta): 
                        if (in_array($pregunta['Orden'], $ordenes)):
                            $pregunta_count++;
                    ?>
                    <div class="question-item">
                        <span class="question-number">Pregunta <?= $pregunta_count ?></span>
                        <div class="form-group">
                            <label><?= htmlspecialchars($pregunta['Pregunta']) ?> <span class="required">*</span></label>
                            
                            <?php if ($pregunta['Tipo_Respuesta'] === 'texto_corto'): ?>
                                <input type="text" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" required>
                            
                            <?php elseif ($pregunta['Tipo_Respuesta'] === 'texto_largo'): ?>
                                <textarea name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" rows="4" required 
                                          placeholder="Escribe tu respuesta aquí de forma detallada..."></textarea>
                            
                            <?php elseif ($pregunta['Tipo_Respuesta'] === 'si_no'): ?>
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" value="SI" required> Sí
                                    </label>
                                    <label>
                                        <input type="radio" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" value="NO" required> No
                                    </label>
                                </div>
                            
                            <?php elseif ($pregunta['Tipo_Respuesta'] === 'opcion_multiple' && $pregunta['Opciones']): ?>
                                <?php $opciones = json_decode($pregunta['Opciones'], true); ?>
                                <select name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" required>
                                    <option value="">Selecciona una opción</option>
                                    <?php foreach ($opciones as $opcion): ?>
                                        <option value="<?= htmlspecialchars($opcion) ?>"><?= htmlspecialchars($opcion) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                <?php endforeach; ?>

                <div class="button-group">
                    <button type="button" class="btn btn-back" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 3: EVALUACIÓN SOCIOECONÓMICA -->
            <div class="form-step" data-step="3">
                <div class="form-header">
                    <h2>Evaluación Socioeconómica</h2>
                    <p>Información sobre tu situación familiar y económica.</p>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Confidencialidad:</strong> Toda la información proporcionada será tratada de manera confidencial y solo será utilizada para evaluar tu solicitud.
                    </div>
                </div>

                <div class="form-group">
                    <label>¿Cuál es tu meta profesional? <span class="required">*</span></label>
                    <textarea name="meta_profesional" rows="3" required 
                              placeholder="Describe qué carrera o profesión deseas estudiar y por qué"></textarea>
                </div>

                <div class="form-group">
                    <label>¿Tienes otra beca actualmente? <span class="required">*</span></label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="otra_beca" value="SI" required onclick="toggleOtraBeca(true)"> Sí
                        </label>
                        <label>
                            <input type="radio" name="otra_beca" value="NO" required onclick="toggleOtraBeca(false)"> No
                        </label>
                    </div>
                </div>

                <div id="otraBecaFields" style="display: none;">
                    <div class="form-group col-2">
                        <div>
                            <label>Institución que otorga la beca <span class="required">*</span></label>
                            <input type="text" name="institucion_beca">
                        </div>
                        <div>
                            <label>Contacto de la institución</label>
                            <input type="text" name="contacto_institucion">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Estado civil de los padres <span class="required">*</span></label>
                    <select name="estado_civil_padres" required>
                        <option value="">Selecciona una opción</option>
                        <option value="Casados">Casados</option>
                        <option value="Divorciados">Divorciados</option>
                        <option value="Viudo">Viudo/a</option>
                    </select>
                </div>

                <h3 style="color: #667eea; margin: 30px 0 20px;">Información de la Madre</h3>
                
                <div class="form-group col-2">
                    <div>
                        <label>¿Sabe leer y escribir? <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="madre_leer" value="SI" required> Sí</label>
                            <label><input type="radio" name="madre_leer" value="NO" required> No</label>
                        </div>
                    </div>
                    <div>
                        <label>Último grado educativo <span class="required">*</span></label>
                        <input type="text" name="madre_grado_educacion" required>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Profesión u ocupación</label>
                        <input type="text" name="profesion_madre">
                    </div>
                    <div>
                        <label>Lugar de trabajo</label>
                        <input type="text" name="lugar_trabajo_madre">
                    </div>
                </div>

                <h3 style="color: #667eea; margin: 30px 0 20px;">Información del Padre</h3>
                
                <div class="form-group col-2">
                    <div>
                        <label>¿Sabe leer y escribir? <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="padre_leer" value="SI" required> Sí</label>
                            <label><input type="radio" name="padre_leer" value="NO" required> No</label>
                        </div>
                    </div>
                    <div>
                        <label>Último grado educativo <span class="required">*</span></label>
                        <input type="text" name="padre_grado_educacion" required>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Profesión u ocupación</label>
                        <input type="text" name="profesion_padre">
                    </div>
                    <div>
                        <label>Lugar de trabajo</label>
                        <input type="text" name="lugar_trabajo_padre">
                    </div>
                </div>

                <h3 style="color: #667eea; margin: 30px 0 20px;">Información de Vivienda</h3>

                <div class="form-group col-2">
                    <div>
                        <label>Tipo de vivienda <span class="required">*</span></label>
                        <select name="tipo_vivienda" required>
                            <option value="">Selecciona</option>
                            <option value="Casa">Casa</option>
                            <option value="Apartamento">Apartamento</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label>Condiciones de la vivienda <span class="required">*</span></label>
                        <select name="condiciones_vivienda" required>
                            <option value="">Selecciona</option>
                            <option value="Excelente">Excelente</option>
                            <option value="Buena">Buena</option>
                            <option value="Regular">Regular</option>
                            <option value="Mala">Mala</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Material de construcción <span class="required">*</span></label>
                    <select name="material_vivienda" required>
                        <option value="">Selecciona</option>
                        <option value="Ladrillo">Ladrillo</option>
                        <option value="Block">Block</option>
                        <option value="Adobe">Adobe</option>
                        <option value="Madera">Madera</option>
                        <option value="Mixto">Mixto</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Servicios básicos con los que cuenta <span class="required">*</span></label>
                    <div class="checkbox-group" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                        <label><input type="checkbox" name="servicios[]" value="agua"> Agua potable</label>
                        <label><input type="checkbox" name="servicios[]" value="luz"> Energía eléctrica</label>
                        <label><input type="checkbox" name="servicios[]" value="drenaje"> Drenaje</label>
                        <label><input type="checkbox" name="servicios[]" value="internet"> Internet</label>
                        <label><input type="checkbox" name="servicios[]" value="telefono"> Teléfono</label>
                        <label><input type="checkbox" name="servicios[]" value="cable"> TV por cable</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>¿Cómo te enteraste del programa de becas? <span class="required">*</span></label>
                    <textarea name="como_se_entero" rows="2" required></textarea>
                </div>

                <div class="form-group">
                    <label>Ensayo personal</label>
                    <textarea name="ensayo_personal" rows="5" 
                              placeholder="Cuéntanos más sobre ti, tu familia, tus sueños y aspiraciones (opcional)"></textarea>
                </div>

                <div class="form-group">
                    <label>¿Conoces algún socio del Club Rotario?</label>
                    <input type="text" name="nombre_socio_rotario" placeholder="Nombre del socio (si aplica)">
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-back" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Enviar Solicitud
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div class="loading" id="loadingState">
                <div class="spinner"></div>
                <h3>Enviando tu solicitud...</h3>
                <p>Por favor espera, esto puede tomar unos momentos.</p>
            </div>
        </form>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;

        function updateProgress() {
            // Actualizar círculos y labels
            document.querySelectorAll('.step').forEach(step => {
                const stepNum = parseInt(step.dataset.step);
                if (stepNum < currentStep) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (stepNum === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });

            // Actualizar barra de progreso
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progressLine').style.width = progress + '%';

            // Mostrar/ocultar pasos
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active');
            });
            document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');

            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function nextStep() {
            if (validateStep(currentStep)) {
                currentStep++;
                updateProgress();
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateProgress();
            }
        }

        function validateStep(step) {
            const currentStepEl = document.querySelector(`.form-step[data-step="${step}"]`);
            const requiredFields = currentStepEl.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                const formGroup = field.closest('.form-group');
                
                if (field.type === 'radio') {
                    const radioGroup = currentStepEl.querySelectorAll(`[name="${field.name}"]`);
                    const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                    
                    if (!isChecked) {
                        isValid = false;
                        if (formGroup) {
                            formGroup.classList.add('error');
                            let error = formGroup.querySelector('.error');
                            if (!error) {
                                error = document.createElement('div');
                                error.className = 'error';
                                error.textContent = 'Por favor selecciona una opción';
                                formGroup.appendChild(error);
                            }
                        }
                    } else {
                        if (formGroup) formGroup.classList.remove('error');
                    }
                } else if (field.type === 'checkbox') {
                    const checkboxGroup = currentStepEl.querySelectorAll(`[name="${field.name}"]`);
                    const isChecked = Array.from(checkboxGroup).some(checkbox => checkbox.checked);
                    
                    if (!isChecked) {
                        isValid = false;
                        if (formGroup) {
                            formGroup.classList.add('error');
                            let error = formGroup.querySelector('.error');
                            if (!error) {
                                error = document.createElement('div');
                                error.className = 'error';
                                error.textContent = 'Selecciona al menos una opción';
                                formGroup.appendChild(error);
                            }
                        }
                    } else {
                        if (formGroup) formGroup.classList.remove('error');
                    }
                } else if (!field.value.trim()) {
                    isValid = false;
                    if (formGroup) {
                        formGroup.classList.add('error');
                        let error = formGroup.querySelector('.error');
                        if (!error) {
                            error = document.createElement('div');
                            error.className = 'error';
                            error.textContent = 'Este campo es obligatorio';
                            formGroup.appendChild(error);
                        }
                    }
                } else {
                    if (formGroup) formGroup.classList.remove('error');
                }
            });

            // Validación especial para la carta (mínimo 200 palabras)
            if (step === 1) {
                const carta = currentStepEl.querySelector('[name="carta_solicitud"]');
                const palabras = carta.value.trim().split(/\s+/).length;
                if (palabras < 200) {
                    isValid = false;
                    const formGroup = carta.closest('.form-group');
                    formGroup.classList.add('error');
                    let error = formGroup.querySelector('.error');
                    if (!error) {
                        error = document.createElement('div');
                        error.className = 'error';
                        formGroup.appendChild(error);
                    }
                    error.textContent = `La carta debe tener al menos 200 palabras. Actualmente tiene ${palabras} palabras.`;
                }
            }

            if (!isValid) {
                alert('Por favor completa todos los campos obligatorios antes de continuar.');
            }

            return isValid;
        }

        function toggleOtraBeca(show) {
            const fields = document.getElementById('otraBecaFields');
            const inputs = fields.querySelectorAll('input');
            
            if (show) {
                fields.style.display = 'block';
                inputs.forEach(input => {
                    if (input.name === 'institucion_beca') {
                        input.required = true;
                    }
                });
            } else {
                fields.style.display = 'none';
                inputs.forEach(input => {
                    input.required = false;
                    input.value = '';
                });
            }
        }

        // Manejo del envío del formulario
        document.getElementById('solicitudForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!validateStep(3)) {
                return;
            }

            if (!confirm('¿Estás seguro de enviar tu solicitud? Verifica que toda la información sea correcta.')) {
                return;
            }

            // Mostrar loading
            document.querySelector('.form-step.active').style.display = 'none';
            document.getElementById('loadingState').classList.add('active');

            // Recopilar datos del formulario
            const formData = new FormData(this);

            try {
                const response = await fetch('procesar_solicitud_beca.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Redirigir a página de confirmación
                    window.location.href = 'confirmacion_solicitud.php?cita=' + result.id_cita;
                } else {
                    alert('Error: ' + result.message);
                    document.getElementById('loadingState').classList.remove('active');
                    document.querySelector('.form-step[data-step="3"]').style.display = 'block';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Ocurrió un error al enviar la solicitud. Por favor intenta nuevamente.');
                document.getElementById('loadingState').classList.remove('active');
                document.querySelector('.form-step[data-step="3"]').style.display = 'block';
            }
        });

        // Remover errores al escribir
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', function() {
                const formGroup = this.closest('.form-group');
                if (formGroup) {
                    formGroup.classList.remove('error');
                }
            });
        });
    </script>
</body>
</html>