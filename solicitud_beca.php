<?php
require_once 'conexion.php';

try {
    $sql_preguntas = "SELECT * FROM Preguntas_Cuestionario WHERE Estado = 'Activa' ORDER BY Orden";
    $stmt_preguntas = $pdo->query($sql_preguntas);
    $preguntas = $stmt_preguntas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar preguntas: " . $e->getMessage());
}

$meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$fecha_actual = [
    'ciudad' => 'Coatepeque',
    'dia' => date('d'),
    'mes' => $meses[date('n')],
    'anio' => date('Y')
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Beca - Club Rotario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1a3a5f;
            --dark-blue: #0f2a47;
            --light-blue: #2a5a8c;
            --accent-blue: #3a7ab8;
            --gold: #d4af37;
            --light-gold: #e6c86e;
            --dark-gold: #b8941f;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --dark-gray: #6c757d;
            --text-dark: #343a40;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            --shadow-heavy: 0 15px 40px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: var(--white);
            margin-bottom: 40px;
            position: relative;
            padding: 20px 0;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 3px;
            background: linear-gradient(to right, var(--gold), var(--light-gold), var(--gold));
            border-radius: 3px;
        }

        .header h1 {
            font-size: 2.8em;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
            font-weight: 300;
        }

        .header-icon {
            display: inline-block;
            background: var(--gold);
            color: var(--primary-blue);
            width: 70px;
            height: 70px;
            border-radius: 50%;
            line-height: 70px;
            margin-bottom: 20px;
            font-size: 1.8em;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .progress-container {
            background: var(--white);
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-heavy);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .progress-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--gold), var(--light-gold));
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
            height: 4px;
            background: var(--medium-gray);
            z-index: 1;
            border-radius: 2px;
        }

        .progress-line {
            position: absolute;
            top: 25px;
            left: 0;
            height: 4px;
            background: linear-gradient(to right, var(--accent-blue), var(--light-blue));
            z-index: 2;
            transition: var(--transition);
            border-radius: 2px;
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
            background: var(--white);
            border: 3px solid var(--medium-gray);
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--dark-gray);
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }

        .step.active .step-circle {
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
            border-color: var(--accent-blue);
            color: var(--white);
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(58, 122, 184, 0.4);
        }

        .step.completed .step-circle {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--white);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }

        .step-label {
            font-size: 0.9em;
            color: var(--dark-gray);
            font-weight: 500;
            transition: var(--transition);
        }

        .step.active .step-label {
            color: var(--accent-blue);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: var(--gold);
        }

        .form-container {
            background: var(--white);
            padding: 40px;
            border-radius: 15px;
            box-shadow: var(--shadow-heavy);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--gold), var(--light-gold));
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
            margin-bottom: 35px;
            text-align: center;
            position: relative;
            padding-bottom: 20px;
        }

        .form-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(to right, var(--gold), var(--light-gold));
            border-radius: 3px;
        }

        .form-header h2 {
            color: var(--primary-blue);
            font-size: 2em;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .form-header p {
            color: var(--dark-gray);
            font-size: 1.1em;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 0.95em;
        }

        .form-group label .required {
            color: #e74c3c;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--medium-gray);
            border-radius: 8px;
            font-size: 1em;
            transition: var(--transition);
            font-family: inherit;
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(58, 122, 184, 0.15);
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

        .form-group.col-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--dark-gray);
            font-size: 0.85em;
        }

        .radio-group,
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .radio-group label,
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
            transition: var(--transition);
            padding: 8px 12px;
            border-radius: 6px;
        }

        .radio-group label:hover,
        .checkbox-group label:hover {
            background: rgba(58, 122, 184, 0.05);
        }

        .radio-group input,
        .checkbox-group input {
            width: auto;
            margin: 0;
        }

        /* NUEVA SECCIÓN: Carta Editable */
        .carta-editable {
            background: var(--white);
            padding: 40px;
            border-radius: 10px;
            border: 2px solid var(--accent-blue);
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .carta-editable::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--gold);
        }

        .carta-editable .edit-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            color: #856404;
        }

        .carta-editable .edit-notice i {
            color: #ffc107;
            font-size: 1.2em;
        }

        .carta-editor {
            font-family: 'Times New Roman', serif;
            line-height: 2;
            font-size: 1.05em;
            color: var(--text-dark);
            min-height: 600px;
            padding: 20px;
            border: 2px dashed var(--medium-gray);
            border-radius: 8px;
            background: #fafafa;
            white-space: pre-wrap;
        }

        .carta-editor:focus {
            outline: none;
            border-color: var(--accent-blue);
            background: var(--white);
        }

        .carta-editor[contenteditable="true"]:empty:before {
            content: "Escribe tu carta aquí o usa el botón 'Generar Carta Automática'...";
            color: var(--dark-gray);
            font-style: italic;
        }

        .carta-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn-carta {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-generar {
            background: var(--accent-blue);
            color: var(--white);
        }

        .btn-generar:hover {
            background: var(--light-blue);
            transform: translateY(-2px);
        }

        .btn-limpiar {
            background: var(--dark-gray);
            color: var(--white);
        }

        .btn-limpiar:hover {
            background: #5a6268;
        }

        .btn-copiar {
            background: var(--gold);
            color: var(--white);
        }

        .btn-copiar:hover {
            background: var(--dark-gold);
        }

        .question-item {
            background: var(--light-gray);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid var(--accent-blue);
            position: relative;
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .question-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .question-number {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin-bottom: 15px;
            box-shadow: 0 3px 8px rgba(58, 122, 184, 0.3);
        }

        .alert {
            padding: 18px 22px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            border-left: 4px solid;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .alert-info {
            background: #e3f2fd;
            border-color: var(--accent-blue);
            color: #0d47a1;
        }

        .alert-warning {
            background: #fff8e1;
            border-color: var(--gold);
            color: #ff8f00;
        }

        .alert-success {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }

        .alert i {
            font-size: 1.4em;
            margin-top: 2px;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            gap: 15px;
        }

        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(58, 122, 184, 0.4);
        }

        .btn-secondary {
            background: var(--dark-gray);
            color: var(--white);
            flex: 1;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-back {
            background: var(--white);
            color: var(--accent-blue);
            border: 2px solid var(--accent-blue);
        }

        .btn-back:hover {
            background: rgba(58, 122, 184, 0.05);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .error {
            color: #e74c3c;
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
        }

        .form-group.error input,
        .form-group.error select,
        .form-group.error textarea {
            border-color: #e74c3c;
        }

        .form-group.error .error {
            display: block;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 50px 20px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid rgba(58, 122, 184, 0.2);
            border-top: 4px solid var(--accent-blue);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 25px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .admin-link {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: var(--white);
            color: var(--accent-blue);
            padding: 14px 22px;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: var(--shadow-heavy);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: var(--transition);
            z-index: 1000;
        }

        .admin-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
            color: var(--primary-blue);
        }

        .section-title {
            color: var(--primary-blue);
            margin: 35px 0 20px;
            padding-top: 25px;
            border-top: 1px solid var(--medium-gray);
            font-size: 1.4em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--gold);
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 25px;
            }

            .carta-editable {
                padding: 20px;
            }

            .form-group.col-2,
            .form-group.col-3 {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 2em;
            }

            .button-group {
                flex-direction: column;
            }

            .admin-link {
                bottom: 15px;
                right: 15px;
                font-size: 0.9em;
                padding: 12px 18px;
            }

            .progress-steps {
                flex-direction: column;
                gap: 20px;
            }

            .progress-steps::before,
            .progress-line {
                display: none;
            }

            .carta-actions {
                flex-direction: column;
            }

            .btn-carta {
                width: 100%;
                justify-content: center;
            }
        }

        .char-count {
            text-align: right;
            font-size: 0.85em;
            color: var(--dark-gray);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1>Solicitud de Beca</h1>
            <p>Club Rotario Coatepeque Colomba</p>
        </div>

        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line" id="progressLine" style="width: 0%"></div>
                <div class="step active" data-step="1">
                    <div class="step-circle"><i class="fas fa-user"></i></div>
                    <div class="step-label">Datos Personales</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle"><i class="fas fa-file-alt"></i></div>
                    <div class="step-label">Carta de Solicitud</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle"><i class="fas fa-question-circle"></i></div>
                    <div class="step-label">Cuestionario</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-circle"><i class="fas fa-users"></i></div>
                    <div class="step-label">Evaluación Socioeconómica</div>
                </div>
            </div>
        </div>

        <form id="solicitudForm" class="form-container">
            <!-- PASO 1: DATOS PERSONALES -->
            <div class="form-step active" data-step="1">
                <div class="form-header">
                    <h2>Datos Personales</h2>
                    <p>Por favor completa tu información personal básica</p>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Importante:</strong> Asegúrate de que todos los datos sean correctos. Esta información será utilizada para tu proceso de evaluación.
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Nombres y Apellidos Completos <span class="required">*</span></label>
                        <input type="text" name="nombres_apellidos" id="nombres_apellidos" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
                    <div>
                        <label>Edad <span class="required">*</span></label>
                        <input type="number" name="edad" id="edad" min="5" max="99" required>
                        <div class="error">Ingresa una edad válida</div>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Teléfono <span class="required">*</span></label>
                        <input type="tel" name="telefono" id="telefono" placeholder="1234-5678" required>
                        <div class="error">Ingresa un teléfono válido</div>
                    </div>
                    <div>
                        <label>Correo Electrónico <span class="required">*</span></label>
                        <input type="email" name="email" id="email" placeholder="correo@ejemplo.com" required>
                        <div class="error">Ingresa un correo válido</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>DPI o Número de Identificación <span class="required">*</span></label>
                    <input type="text" name="dpi" id="dpi" placeholder="1234 56789 0101" required>
                    <div class="error">Este campo es obligatorio</div>
                </div>

                <div class="form-group">
                    <label>Dirección Completa <span class="required">*</span></label>
                    <textarea name="direccion" id="direccion" rows="2" required></textarea>
                    <div class="error">Ingresa tu dirección completa</div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Nombre de la Madre</label>
                        <input type="text" name="nombre_madre" id="nombre_madre">
                    </div>
                    <div>
                        <label>Nombre del Padre</label>
                        <input type="text" name="nombre_padre" id="nombre_padre">
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Nombre del Encargado <span class="required">*</span></label>
                        <input type="text" name="nombre_encargado" id="nombre_encargado" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
                    <div>
                        <label>Teléfono del Encargado <span class="required">*</span></label>
                        <input type="tel" name="telefono_encargado" id="telefono_encargado" required>
                        <div class="error">Ingresa un teléfono válido</div>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Grado/Año Actual <span class="required">*</span></label>
                        <input type="text" name="grado_actual" id="grado_actual" placeholder="Ej: 4to Bachillerato" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
                    <div>
                        <label>Carrera o Programa <span class="required">*</span></label>
                        <input type="text" name="carrera" id="carrera" placeholder="Ej: Perito Contador" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Establecimiento Educativo <span class="required">*</span></label>
                        <input type="text" name="establecimiento" id="establecimiento" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
                    <div>
                        <label>Promedio General <span class="required">*</span></label>
                        <input type="number" name="promedio" id="promedio" min="0" max="100" step="0.01" placeholder="Ej: 85.5" required>
                        <div class="error">Ingresa tu promedio</div>
                    </div>
                </div>

                <div class="form-group col-2">
                    <div>
                        <label>Grado Obtenido Anteriormente <span class="required">*</span></label>
                        <input type="text" name="grado_obtenido" id="grado_obtenido" placeholder="Ej: 3ro Básico" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
                    <div>
                        <label>Escuela/Colegio Anterior <span class="required">*</span></label>
                        <input type="text" name="escuela_anterior" id="escuela_anterior" required>
                        <div class="error">Este campo es obligatorio</div>
                    </div>
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

            <!-- PASO 2: CARTA DE SOLICITUD EDITABLE -->
            <div class="form-step" data-step="2">
                <div class="form-header">
                    <h2>Carta de Solicitud de Beca</h2>
                    <p>Puedes escribir tu propia carta o generar una automáticamente</p>
                </div>

                <div class="alert alert-success">
                    <i class="fas fa-lightbulb"></i>
                    <div>
                        <strong>¡Nuevo!</strong> Ahora puedes escribir tu carta personalizada directamente o usar el botón para generar una carta automática con tus datos. Puedes editarla libremente.
                    </div>
                </div>

                <div class="carta-editable">
                    <div class="edit-notice">
                        <i class="fas fa-edit"></i>
                        <span><strong>Carta Editable:</strong> Haz clic en el área de texto para escribir o editar tu carta libremente</span>
                    </div>

                    <div 
                        id="cartaEditor" 
                        class="carta-editor" 
                        contenteditable="true"
                        data-placeholder="Escribe tu carta aquí o usa el botón 'Generar Carta Automática'..."
                    ></div>

                    <div class="char-count">
                        <span id="charCount">0</span> caracteres
                    </div>

                    <div class="carta-actions">
                        <button type="button" class="btn-carta btn-generar" onclick="generarCartaAutomatica()">
                            <i class="fas fa-magic"></i> Generar Carta Automática
                        </button>
                        <button type="button" class="btn-carta btn-limpiar" onclick="limpiarCarta()">
                            <i class="fas fa-eraser"></i> Limpiar Carta
                        </button>
                        <button type="button" class="btn-carta btn-copiar" onclick="copiarCarta()">
                            <i class="fas fa-copy"></i> Copiar al Portapapeles
                        </button>
                    </div>
                </div>

                <input type="hidden" name="carta_solicitud" id="carta_solicitud">

                <div class="form-group col-3" style="margin-top: 30px;">
                    <div>
                        <label>Ciudad/Municipio</label>
                        <input type="text" name="ciudad_carta" id="ciudad_carta" value="Coatepeque">
                    </div>
                    <div>
                        <label>Día</label>
                        <input type="number" name="dia_carta" id="dia_carta" value="<?= $fecha_actual['dia'] ?>" min="1" max="31">
                    </div>
                    <div>
                        <label>Mes</label>
                        <select name="mes_carta" id="mes_carta">
                            <?php foreach ($meses as $num => $mes): ?>
                                <option value="<?= $mes ?>" <?= ($num == date('n')) ? 'selected' : '' ?>><?= ucfirst($mes) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Año</label>
                    <input type="number" name="anio_carta" id="anio_carta" value="<?= $fecha_actual['anio'] ?>" min="2024" max="2030">
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-back" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div class="form-step" data-step="3">
                <div class="form-header">
                    <h2>Cuestionario de Evaluación</h2>
                    <p>Responde las siguientes preguntas con sinceridad y detalle</p>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Nota:</strong> Tus respuestas nos ayudarán a conocerte mejor y evaluar tu candidatura de manera integral.
                    </div>
                </div>

                <?php 
                // Configuración de categorías con sus nombres e iconos
                $categorias_config = [
                    'perfil' => [
                        'nombre' => 'Sobre tu Perfil',
                        'icono' => 'fa-user'
                    ],
                    'objetivos' => [
                        'nombre' => 'Sobre tus Objetivos y Motivación',
                        'icono' => 'fa-bullseye'
                    ],
                    'experiencia' => [
                        'nombre' => 'Sobre tu Experiencia y Comunidad',
                        'icono' => 'fa-handshake'
                    ],
                    'cierre' => [
                        'nombre' => 'Preguntas de Cierre',
                        'icono' => 'fa-check-circle'
                    ]
                ];
                
                // Agrupar preguntas por categoría
                $preguntas_por_categoria = [];
                foreach ($preguntas as $pregunta) {
                    $categoria = $pregunta['Categoria'] ?? 'perfil'; // Default a 'perfil' si no tiene categoría
                    if (!isset($preguntas_por_categoria[$categoria])) {
                        $preguntas_por_categoria[$categoria] = [];
                    }
                    $preguntas_por_categoria[$categoria][] = $pregunta;
                }
                
                // Mostrar preguntas organizadas por categoría
                $pregunta_count = 0;
                
                // Iterar sobre las categorías en el orden definido
                foreach ($categorias_config as $cat_key => $cat_info):
                    // Verificar si hay preguntas en esta categoría
                    if (empty($preguntas_por_categoria[$cat_key])) {
                        continue; // Saltar categorías vacías
                    }
                ?>
                    <h3 class="section-title">
                        <i class="fas <?= $cat_info['icono'] ?>"></i> <?= $cat_info['nombre'] ?>
                    </h3>
                    
                    <?php 
                    // Mostrar todas las preguntas de esta categoría
                    foreach ($preguntas_por_categoria[$cat_key] as $pregunta): 
                        $pregunta_count++;
                    ?>
                    <div class="question-item">
                        <span class="question-number">Pregunta <?= $pregunta_count ?></span>
                        <div class="form-group">
                            <label><?= htmlspecialchars($pregunta['Pregunta']) ?> <span class="required">*</span></label>
                            
                            <?php if ($pregunta['Tipo_Respuesta'] === 'texto_corto'): ?>
                                <input type="text" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" required>
                                <div class="error">Esta pregunta es obligatoria</div>
                            
                            <?php elseif ($pregunta['Tipo_Respuesta'] === 'texto_largo'): ?>
                                <textarea name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" rows="4" required 
                                          placeholder="Escribe tu respuesta aquí de forma detallada..."></textarea>
                                <div class="error">Esta pregunta es obligatoria</div>
                            
                            <?php elseif ($pregunta['Tipo_Respuesta'] === 'si_no'): ?>
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" value="SI" required> Sí
                                    </label>
                                    <label>
                                        <input type="radio" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" value="NO" required> No
                                    </label>
                                </div>
                                <div class="error">Selecciona una opción</div>
                            
                            <?php elseif ($pregunta['Tipo_Respuesta'] === 'opcion_multiple' && $pregunta['Opciones']): ?>
                                <?php $opciones = json_decode($pregunta['Opciones'], true); ?>
                                <?php if (is_array($opciones)): ?>
                                    <select name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" required>
                                        <option value="">Selecciona una opción</option>
                                        <?php foreach ($opciones as $opcion): ?>
                                            <option value="<?= htmlspecialchars($opcion) ?>"><?= htmlspecialchars($opcion) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="error">Selecciona una opción</div>
                                <?php else: ?>
                                    <input type="text" name="pregunta_<?= $pregunta['Id_Pregunta'] ?>" required>
                                    <div class="error">Esta pregunta es obligatoria</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                    endforeach; // Fin del foreach de preguntas de la categoría
                    ?>
                <?php 
                endforeach; // Fin del foreach de categorías
                ?>

                <div class="button-group">
                    <button type="button" class="btn btn-back" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>


            <!-- PASO 4: EVALUACIÓN SOCIOECONÓMICA -->
            <div class="form-step" data-step="4">
                <div class="form-header">
                    <h2>Evaluación Socioeconómica</h2>
                    <p>Información sobre tu situación familiar y económica</p>
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
                    <div class="error">Este campo es obligatorio</div>
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
                    <div class="error">Selecciona una opción</div>
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
                        <option value="Solteros">Solteros</option>
                        <option value="Unión Libre">Unión Libre</option>
                    </select>
                    <div class="error">Selecciona una opción</div>
                </div>

                <h3 class="section-title">
                    <i class="fas fa-female"></i> Información de la Madre
                </h3>
                
                <div class="form-group col-2">
                    <div>
                        <label>¿Sabe leer y escribir? <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="madre_leer" value="SI" required> Sí</label>
                            <label><input type="radio" name="madre_leer" value="NO" required> No</label>
                        </div>
                        <div class="error">Selecciona una opción</div>
                    </div>
                    <div>
                        <label>Último grado educativo <span class="required">*</span></label>
                        <input type="text" name="madre_grado_educacion" required>
                        <div class="error">Este campo es obligatorio</div>
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

                <h3 class="section-title">
                    <i class="fas fa-male"></i> Información del Padre
                </h3>
                
                <div class="form-group col-2">
                    <div>
                        <label>¿Sabe leer y escribir? <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="padre_leer" value="SI" required> Sí</label>
                            <label><input type="radio" name="padre_leer" value="NO" required> No</label>
                        </div>
                        <div class="error">Selecciona una opción</div>
                    </div>
                    <div>
                        <label>Último grado educativo <span class="required">*</span></label>
                        <input type="text" name="padre_grado_educacion" required>
                        <div class="error">Este campo es obligatorio</div>
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

                <h3 class="section-title">
                    <i class="fas fa-home"></i> Información de Vivienda
                </h3>

                <div class="form-group col-2">
                    <div>
                        <label>Tipo de vivienda <span class="required">*</span></label>
                        <select name="tipo_vivienda" required>
                            <option value="">Selecciona</option>
                            <option value="Casa">Casa</option>
                            <option value="Apartamento">Apartamento</option>
                            <option value="Cuarto">Cuarto</option>
                            <option value="Otro">Otro</option>
                        </select>
                        <div class="error">Selecciona una opción</div>
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
                        <div class="error">Selecciona una opción</div>
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
                        <option value="Lámina">Lámina</option>
                        <option value="Mixto">Mixto</option>
                    </select>
                    <div class="error">Selecciona una opción</div>
                </div>

                <div class="form-group">
                    <label>Servicios básicos con los que cuenta <span class="required">*</span></label>
                    <div class="checkbox-group" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
                        <label><input type="checkbox" name="servicios[]" value="agua"> Agua potable</label>
                        <label><input type="checkbox" name="servicios[]" value="luz"> Energía eléctrica</label>
                        <label><input type="checkbox" name="servicios[]" value="drenaje"> Drenaje</label>
                        <label><input type="checkbox" name="servicios[]" value="internet"> Internet</label>
                        <label><input type="checkbox" name="servicios[]" value="telefono"> Teléfono</label>
                        <label><input type="checkbox" name="servicios[]" value="cable"> TV por cable</label>
                    </div>
                    <div class="error">Selecciona al menos un servicio</div>
                </div>

                <div class="form-group">
                    <label>¿Cómo te enteraste del programa de becas? <span class="required">*</span></label>
                    <textarea name="como_se_entero" rows="2" required></textarea>
                    <div class="error">Este campo es obligatorio</div>
                </div>

                <div class="form-group">
                    <label>Ensayo personal (Opcional)</label>
                    <textarea name="ensayo_personal" rows="5" 
                              placeholder="Cuéntanos más sobre ti, tu familia, tus sueños y aspiraciones"></textarea>
                    <small>Este campo es opcional pero nos ayuda a conocerte mejor</small>
                </div>

                <div class="form-group">
                    <label>¿Conoces algún socio del Club Rotario? (Opcional)</label>
                    <input type="text" name="nombre_socio_rotario" placeholder="Nombre del socio (si aplica)">
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-back" onclick="prevStep()">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
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

    <a href="index.php" class="admin-link">
        <i class=""></i> Volver al inicio
    </a>

    <script>
let currentStep = 1;
const totalSteps = 4;


document.addEventListener('DOMContentLoaded', function() {
    const editor = document.getElementById('cartaEditor');
    const charCount = document.getElementById('charCount');
    
    if (editor) {
        editor.addEventListener('input', function() {
            const text = this.innerText || this.textContent || '';
            charCount.textContent = text.length;
        });
    }
});

function generarCartaAutomatica() {
    // Obtener datos del paso 1
    const nombre = document.getElementById('nombres_apellidos').value;
    const establecimiento = document.getElementById('establecimiento').value;
    const carrera = document.getElementById('carrera').value;
    const grado = document.getElementById('grado_actual').value;
    const promedio = document.getElementById('promedio').value;
    const dpi = document.getElementById('dpi').value;
    const telefono = document.getElementById('telefono').value;
    const email = document.getElementById('email').value;
    
    // Validar que se hayan completado los datos básicos
    if (!nombre || !establecimiento || !carrera || !grado) {
        alert('Por favor completa los datos personales del Paso 1 antes de generar la carta automática.');
        return;
    }
    
    // Obtener fecha
    const ciudad = document.getElementById('ciudad_carta').value || 'Coatepeque';
    const dia = document.getElementById('dia_carta').value || '<?= $fecha_actual['dia'] ?>';
    const mes = document.getElementById('mes_carta').value || '<?= $fecha_actual['mes'] ?>';
    const anio = document.getElementById('anio_carta').value || '<?= $fecha_actual['anio'] ?>';
    
    // Generar la carta
    const carta = `${ciudad}, ${dia} de ${mes} de ${anio}

Señores:
Club Rotario Coatepeque Colomba
Presente.

Asunto: Solicitud de beca estudiantil

Estimados miembros del comité:

Por medio de la presente, me dirijo a ustedes de manera atenta para solicitar una beca de estudios, con el fin de continuar mi formación académica en ${establecimiento}, en la carrera de ${carrera}.

Soy ${nombre}, estudiante de ${grado}. Durante mi trayectoria académica he demostrado compromiso y responsabilidad, manteniendo un buen rendimiento escolar${promedio ? ' con un promedio general de ' + promedio : ''}.

Lamentablemente, mi situación económica actual dificulta cubrir los gastos relacionados con mi educación, por lo que esta beca representa una valiosa oportunidad para continuar con mis estudios sin interrupciones.

Me comprometo a mantener mi desempeño académico y a cumplir con los requisitos que el programa de becas establezca. Estoy dispuesto/a a brindar cualquier información adicional que consideren necesaria para evaluar mi solicitud.

Agradezco de antemano la atención prestada a esta solicitud y quedo a disposición para proporcionar cualquier documentación o información adicional que sea necesaria.

Sin otro particular, les saludo cordialmente y espero una respuesta favorable a mi petición.

Atentamente,

${nombre}
${dpi ? 'DPI: ' + dpi : ''}
${telefono ? 'Teléfono: ' + telefono : ''}
${email ? 'Correo: ' + email : ''}`;

    // Insertar en el editor
    document.getElementById('cartaEditor').innerText = carta;
    
    // Actualizar contador
    document.getElementById('charCount').textContent = carta.length;
    
    // Mostrar mensaje de éxito
    mostrarMensajeTemporal('¡Carta generada! Puedes editarla libremente.', 'success');
}

// Limpiar la carta
function limpiarCarta() {
    if (confirm('¿Estás seguro de que deseas limpiar la carta? Se perderá todo el contenido.')) {
        document.getElementById('cartaEditor').innerText = '';
        document.getElementById('charCount').textContent = '0';
        mostrarMensajeTemporal('Carta limpiada', 'info');
    }
}

// Copiar carta al portapapeles
function copiarCarta() {
    const editor = document.getElementById('cartaEditor');
    const text = editor.innerText || editor.textContent;
    
    if (!text.trim()) {
        alert('No hay contenido para copiar. Escribe o genera una carta primero.');
        return;
    }
    
    navigator.clipboard.writeText(text).then(function() {
        mostrarMensajeTemporal('¡Carta copiada al portapapeles!', 'success');
    }).catch(function() {
        // Fallback para navegadores antiguos
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        mostrarMensajeTemporal('¡Carta copiada al portapapeles!', 'success');
    });
}

// Mostrar mensaje temporal
function mostrarMensajeTemporal(mensaje, tipo = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo}`;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '10000';
    alert.style.minWidth = '300px';
    alert.style.animation = 'slideInRight 0.3s ease';
    
    const icon = tipo === 'success' ? 'check-circle' : tipo === 'warning' ? 'exclamation-triangle' : 'info-circle';
    
    alert.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <div>${mensaje}</div>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(alert);
        }, 300);
    }, 3000);
}

// ============================================
// NAVEGACIÓN ENTRE PASOS
// ============================================

function updateProgress() {
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

    const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
    document.getElementById('progressLine').style.width = progress + '%';

    document.querySelectorAll('.form-step').forEach(step => {
        step.classList.remove('active');
    });
    document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep() {
    if (validateStep(currentStep)) {
        // Si estamos en el paso 2 (carta), guardar el contenido
        if (currentStep === 2) {
            const cartaTexto = document.getElementById('cartaEditor').innerText || 
                              document.getElementById('cartaEditor').textContent || '';
            
            if (cartaTexto.trim().length < 100) {
                alert('Por favor escribe o genera una carta de solicitud antes de continuar. La carta debe tener al menos 100 caracteres.');
                return;
            }
            
            document.getElementById('carta_solicitud').value = cartaTexto;
        }
        
        if (currentStep < totalSteps) {
            currentStep++;
            updateProgress();
        }
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateProgress();
    }
}

// ============================================
// VALIDACIÓN DE CAMPOS
// ============================================

function validateStep(step) {
    const currentStepEl = document.querySelector(`.form-step[data-step="${step}"]`);
    const requiredFields = currentStepEl.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        const formGroup = field.closest('.form-group');
        
        if (field.type === 'radio') {
            const radioGroup = currentStepEl.querySelectorAll(`[name="${field.name}"]`);
            const isChecked = Array.from(radioGroup).some(radio => radio.checked);
            
            if (!isChecked) {
                isValid = false;
                if (formGroup && !firstInvalidField) {
                    formGroup.classList.add('error');
                    firstInvalidField = formGroup;
                }
            } else {
                if (formGroup) formGroup.classList.remove('error');
            }
        } else if (field.type === 'checkbox') {
            const checkboxGroup = currentStepEl.querySelectorAll(`[name="${field.name}"]`);
            const isChecked = Array.from(checkboxGroup).some(checkbox => checkbox.checked);
            
            if (!isChecked) {
                isValid = false;
                if (formGroup && !firstInvalidField) {
                    formGroup.classList.add('error');
                    firstInvalidField = formGroup;
                }
            } else {
                if (formGroup) formGroup.classList.remove('error');
            }
        } else if (field.type === 'email') {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!field.value.trim() || !emailPattern.test(field.value)) {
                isValid = false;
                if (formGroup && !firstInvalidField) {
                    formGroup.classList.add('error');
                    firstInvalidField = formGroup;
                }
            } else {
                if (formGroup) formGroup.classList.remove('error');
            }
        } else if (!field.value.trim()) {
            isValid = false;
            if (formGroup && !firstInvalidField) {
                formGroup.classList.add('error');
                firstInvalidField = formGroup;
            }
        } else {
            if (formGroup) formGroup.classList.remove('error');
        }
    });

    if (!isValid) {
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        mostrarMensajeTemporal('Por favor completa todos los campos obligatorios', 'warning');
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

// ============================================
// ENVÍO DEL FORMULARIO
// ============================================

document.getElementById('solicitudForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    if (!validateStep(4)) {
        return;
    }

    // Validar servicios básicos
    const servicios = document.querySelectorAll('input[name="servicios[]"]:checked');
    if (servicios.length === 0) {
        alert('Por favor selecciona al menos un servicio básico.');
        return;
    }

    // Confirmar envío
    if (!confirm('¿Estás seguro de enviar tu solicitud? Por favor verifica que toda la información sea correcta antes de continuar.')) {
        return;
    }

    // Deshabilitar botón de envío
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    // Mostrar loading
    document.querySelector('.form-step.active').style.display = 'none';
    document.getElementById('loadingState').classList.add('active');

    const formData = new FormData(this);

    try {
        const response = await fetch('procesar_solicitud_beca.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Respuesta del servidor:', text);
        
        let result;
        
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            console.error('Error al parsear JSON:', parseError);
            console.error('Texto recibido:', text);
            throw new Error('La respuesta del servidor no es válida. Por favor contacta al administrador.');
        }

        if (result.success) {
            // Redirigir a página de confirmación
            window.location.href = 'confirmacion_solicitud.php?cita=' + result.id_cita;
        } else {
            throw new Error(result.message || 'Error desconocido al procesar la solicitud');
        }
    } catch (error) {
        console.error('Error completo:', error);
        
        // Ocultar loading
        document.getElementById('loadingState').classList.remove('active');
        document.querySelector('.form-step[data-step="4"]').style.display = 'block';
        
        // Rehabilitar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Solicitud';
        
        // Mostrar error
        alert('Error al enviar la solicitud:\n\n' + error.message + '\n\nPor favor verifica tu conexión e intenta nuevamente. Si el problema persiste, contacta al administrador.');
    }
});

// Remover clase error al escribir
document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('input', function() {
        const formGroup = this.closest('.form-group');
        if (formGroup) {
            formGroup.classList.remove('error');
        }
    });
    
    field.addEventListener('change', function() {
        const formGroup = this.closest('.form-group');
        if (formGroup) {
            formGroup.classList.remove('error');
        }
    });
});

// Animación para slideIn/slideOut
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
    </script>
</body>
</html>