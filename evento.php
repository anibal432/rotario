<?php
// evento_completo.php - Detalles de evento con formulario de inscripción integrado
require_once 'conexion.php';

// Verificar que se recibió un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: eventos.php');
    exit;
}

$id_evento = (int)$_GET['id'];

// Obtener información completa del evento
$sql_evento = "
    SELECT 
        e.*,
        te.Nombre as Tipo_Evento_Nombre,
        te.Descripcion as Tipo_Evento_Desc,
        COUNT(DISTINCT ie.Id_Inscripcion) as Total_Inscritos
    FROM Eventos e
    INNER JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
    LEFT JOIN Inscripciones_Evento ie ON e.Id_Evento = ie.Id_Evento 
        AND ie.Estado_Inscripcion != 'Cancelado'
    WHERE e.Id_Evento = :id_evento
    GROUP BY e.Id_Evento
";

try {
    $stmt_evento = $pdo->prepare($sql_evento);
    $stmt_evento->bindParam(':id_evento', $id_evento, PDO::PARAM_INT);
    $stmt_evento->execute();
    $evento = $stmt_evento->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        header('Location: eventos.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Obtener costos activos del evento
$sql_costos = "
    SELECT * FROM Costos_Inscripcion 
    WHERE Id_Evento = :id_evento 
    AND Estado = 'Activo'
    AND (
        CURDATE() BETWEEN Fecha_Inicio AND Fecha_Fin
        OR Fecha_Fin >= CURDATE()
    )
    ORDER BY Costo ASC
";

try {
    $stmt_costos = $pdo->prepare($sql_costos);
    $stmt_costos->bindParam(':id_evento', $id_evento, PDO::PARAM_INT);
    $stmt_costos->execute();
    $costos = $stmt_costos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $costos = [];
}

// Obtener categorías del evento
$sql_categorias = "
    SELECT * FROM Categorias_Evento 
    WHERE Id_Evento = :id_evento 
    AND Estado = 'Activa'
    ORDER BY Nombre_Categoria
";

try {
    $stmt_categorias = $pdo->prepare($sql_categorias);
    $stmt_categorias->bindParam(':id_evento', $id_evento, PDO::PARAM_INT);
    $stmt_categorias->execute();
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
}

// Obtener cuentas bancarias
$sql_cuentas = "
    SELECT * FROM Cuentas_Bancarias 
    WHERE Id_Evento = :id_evento AND Estado = 'Activa'
    ORDER BY Orden_Prioridad ASC
";

try {
    $stmt_cuentas = $pdo->prepare($sql_cuentas);
    $stmt_cuentas->bindParam(':id_evento', $id_evento, PDO::PARAM_INT);
    $stmt_cuentas->execute();
    $cuentas = $stmt_cuentas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cuentas = [];
}

// Calcular información de fechas
$fecha_evento = new DateTime($evento['Fecha_Evento']);
$fecha_actual = new DateTime();
$dias_restantes = $fecha_actual->diff($fecha_evento)->days;
$evento_pasado = $fecha_evento < $fecha_actual;

// Calcular disponibilidad de cupos
$porcentaje_cupo = 0;
$cupos_disponibles = 0;

if ($evento['Cupo_Maximo'] > 0) {
    $porcentaje_cupo = ($evento['Total_Inscritos'] / $evento['Cupo_Maximo']) * 100;
    $cupos_disponibles = $evento['Cupo_Maximo'] - $evento['Total_Inscritos'];
}

// Determinar si se puede inscribir
$puede_inscribirse = true;
$mensaje_boton = 'Inscribirme Ahora';

if ($evento_pasado) {
    $puede_inscribirse = false;
    $mensaje_boton = 'Evento Finalizado';
} elseif ($evento['Estado_Evento'] === 'Inscripciones Cerradas') {
    $puede_inscribirse = false;
    $mensaje_boton = 'Inscripciones Cerradas';
} elseif ($cupos_disponibles <= 0 && $evento['Cupo_Maximo'] > 0) {
    $puede_inscribirse = false;
    $mensaje_boton = 'Sin Cupos Disponibles';
} elseif ($evento['Estado_Evento'] === 'Planificado') {
    $puede_inscribirse = false;
    $mensaje_boton = 'Próximamente';
}

// Meses en español
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$dias_semana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
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
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --background: #f8fafc;
            --white: #ffffff;
            --border-radius: 12px;
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

        .header-banner {
            position: relative;
            width: 100%;
            height: 400px;
            overflow: hidden;
        }

        .header-banner-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-banner-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 5rem;
        }

        .header-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.6));
            z-index: 1;
        }

        .header-content {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            z-index: 2;
            color: white;
            padding: 0 40px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .btn-volver {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 3;
            background: rgba(255, 255, 255, 0.95);
            color: var(--primary-color);
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .btn-volver:hover {
            background: white;
            transform: translateY(-2px);
        }

        .evento-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .badge-abierto {
            background: var(--success-color);
        }

        .badge-cerrado {
            background: var(--error-color);
        }

        .badge-pronto {
            background: var(--warning-color);
        }

        .header-content h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header-content .tipo-evento {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .contenido-principal {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .seccion {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .seccion h2 {
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item i {
            color: var(--primary-light);
            font-size: 1.2rem;
            width: 30px;
            text-align: center;
            margin-top: 3px;
        }

        .info-item-content h3 {
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item-content p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .descripcion {
            color: var(--text-secondary);
            font-size: 1.05rem;
            line-height: 1.8;
        }

        .fecha-destacada {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 20px;
        }

        .fecha-destacada .dia {
            font-size: 3.5rem;
            font-weight: bold;
            line-height: 1;
        }

        .fecha-destacada .mes-año {
            font-size: 1.2rem;
            opacity: 0.95;
            margin-top: 10px;
        }

        .dias-restantes {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }

        .cupo-section {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }

        .cupo-section h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cupo-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .cupo-stat {
            text-align: center;
        }

        .cupo-stat .numero {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .cupo-stat .label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-top: 5px;
        }

        .cupo-barra {
            width: 100%;
            height: 12px;
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .cupo-progreso {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-light));
            transition: width 0.3s ease;
        }

        .costos-grid {
            display: grid;
            gap: 15px;
        }

        .costo-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            padding: 20px;
            transition: all 0.3s ease;
        }

        .costo-card:hover {
            border-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .costo-card h3 {
            color: var(--text-primary);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .costo-precio {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .costo-descripcion {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .costo-periodo {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 0.85rem;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
        }

        .categorias-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .categoria-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-light);
            font-size: 0.9rem;
        }

        .categoria-item strong {
            color: var(--text-primary);
        }

        .btn-inscribirse {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin-top: 20px;
        }

        .btn-inscribirse:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 64, 175, 0.4);
        }

        .btn-inscribirse:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .alerta {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alerta i {
            font-size: 1.3rem;
        }

        .alerta-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning-color);
        }

        .alerta-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--error-color);
        }

        .alerta-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--primary-light);
        }

        /* ESTILOS DEL FORMULARIO */
        #formularioInscripcion {
            display: none;
        }

        #formularioInscripcion.activo {
            display: block;
        }

        .form-seccion {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 30px;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin: -30px -30px 30px -30px;
            text-align: center;
        }

        .form-header h3 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
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

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .file-upload-label:hover {
            border-color: var(--primary-light);
            background: #f0f9ff;
        }

        .file-upload-label i {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 10px;
        }

        .file-upload-label span {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        input[type="file"] {
            display: none;
        }

        .bank-info-section {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin: 20px 0;
        }

        .bank-details {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }

        .bank-details p {
            margin: 8px 0;
            font-size: 0.95rem;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-top: 30px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .success-message,
        .error-message {
            display: none;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid var(--success-color);
        }

        .success-message i {
            font-size: 3rem;
            margin-bottom: 10px;
            display: block;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid var(--error-color);
        }

        @media (max-width: 968px) {
            .contenido-principal {
                grid-template-columns: 1fr;
            }

            .header-content h1 {
                font-size: 2rem;
            }

            .header-banner {
                height: 300px;
            }

            .header-content {
                padding: 0 20px;
            }

            .cupo-stats {
                flex-wrap: wrap;
                gap: 15px;
            }

            .categorias-list {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="eventos.php" class="btn-volver">
        <i class="fas fa-arrow-left"></i> Volver a Eventos
    </a>

    <div class="header-banner">
        <?php if (!empty($evento['Imagen_Banner'])): ?>
        <img src="<?php echo htmlspecialchars($evento['Imagen_Banner']); ?>" 
             alt="<?php echo htmlspecialchars($evento['Nombre_Evento']); ?>" 
             class="header-banner-img">
        <?php else: ?>
        <div class="header-banner-placeholder">
            <i class="fas fa-calendar-star"></i>
        </div>
        <?php endif; ?>
        
        <div class="header-content">
            <?php 
            $clase_badge = 'evento-badge badge-abierto';
            $texto_badge = 'Inscripciones Abiertas';
            
            if ($evento['Estado_Evento'] === 'Inscripciones Cerradas' || $evento_pasado) {
                $clase_badge = 'evento-badge badge-cerrado';
                $texto_badge = 'Cerrado';
            } elseif ($evento['Estado_Evento'] === 'Planificado') {
                $clase_badge = 'evento-badge badge-pronto';
                $texto_badge = 'Próximamente';
            }
            ?>
            <span class="<?php echo $clase_badge; ?>">
                <?php echo $texto_badge; ?>
            </span>
            <h1><?php echo htmlspecialchars($evento['Nombre_Evento']); ?></h1>
            <div class="tipo-evento">
                <i class="fas fa-tag"></i> 
                <?php echo htmlspecialchars($evento['Tipo_Evento_Nombre']); ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="contenido-principal">
            <!-- COLUMNA IZQUIERDA: INFORMACIÓN -->
            <div>
                <div class="seccion">
                    <h2><i class="fas fa-info-circle"></i> Descripción del Evento</h2>
                    <div class="descripcion">
                        <?php echo nl2br(htmlspecialchars($evento['Descripcion'])); ?>
                    </div>
                </div>

                <div class="seccion">
                    <h2><i class="fas fa-list-ul"></i> Detalles del Evento</h2>
                    
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="info-item-content">
                            <h3>Fecha</h3>
                            <p><?php 
                            $dia_semana = $dias_semana[(int)$fecha_evento->format('w')];
                            $dia = $fecha_evento->format('d');
                            $mes = $meses[(int)$fecha_evento->format('n')];
                            $año = $fecha_evento->format('Y');
                            echo "$dia_semana, $dia de $mes de $año"; 
                            ?></p>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div class="info-item-content">
                            <h3>Horario</h3>
                            <p>
                                Inicio: <?php echo date('h:i A', strtotime($evento['Hora_Inicio'])); ?>
                                
                            </p>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="info-item-content">
                            <h3>Lugar de Salida</h3>
                            <p><?php echo htmlspecialchars($evento['Lugar_Salida']); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($evento['Recorrido'])): ?>
                    <div class="info-item">
                        <i class="fas fa-route"></i>
                        <div class="info-item-content">
                            <h3>Recorrido</h3>
                            <p><?php echo htmlspecialchars($evento['Recorrido']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($evento['Distancia_KM']): ?>
                    <div class="info-item">
                        <i class="fas fa-road"></i>
                        <div class="info-item-content">
                            <h3>Distancia</h3>
                            <p><?php echo $evento['Distancia_KM']; ?> kilómetros</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($evento['Causa_Beneficiada'])): ?>
                    <div class="info-item">
                        <i class="fas fa-heart"></i>
                        <div class="info-item-content">
                            <h3>Causa Beneficiada</h3>
                            <p><?php echo htmlspecialchars($evento['Causa_Beneficiada']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($evento['Personas_Beneficiadas'])): ?>
                    <div class="info-item">
                        <i class="fas fa-users-between-lines"></i>
                        <div class="info-item-content">
                            <h3>Personas Beneficiadas</h3>
                            <p><?php echo htmlspecialchars($evento['Personas_Beneficiadas']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (count($categorias) > 0): ?>
                <div class="seccion">
                    <h2><i class="fas fa-trophy"></i> Categorías</h2>
                    <div class="categorias-list">
                        <?php foreach ($categorias as $cat): ?>
                        <div class="categoria-item">
                            <strong><?php echo htmlspecialchars($cat['Nombre_Categoria']); ?></strong><br>
                            <small>
                                <?php echo htmlspecialchars($cat['Genero']); ?>
                                <?php if ($cat['Edad_Minima'] || $cat['Edad_Maxima']): ?>
                                | <?php echo $cat['Edad_Minima'] ?: '0'; ?> - <?php echo $cat['Edad_Maxima'] ?: '∞'; ?> años
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- COLUMNA DERECHA: INSCRIPCIÓN -->
            <div>
                <div class="seccion">
                    <div class="fecha-destacada">
                        <div class="dia"><?php echo $fecha_evento->format('d'); ?></div>
                        <div class="mes-año">
                            <?php echo $meses[(int)$fecha_evento->format('n')] . ' ' . $fecha_evento->format('Y'); ?>
                        </div>
                        <?php if (!$evento_pasado): ?>
                        <div class="dias-restantes">
                            <?php if ($dias_restantes == 0): ?>
                            ¡Es hoy!
                            <?php elseif ($dias_restantes == 1): ?>
                            ¡Es mañana!
                            <?php else: ?>
                            Faltan <?php echo $dias_restantes; ?> días
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="dias-restantes">Evento finalizado</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($evento['Cupo_Maximo'] > 0): ?>
                    <div class="cupo-section">
                        <h3><i class="fas fa-users"></i> Disponibilidad</h3>
                        <div class="cupo-stats">
                            <div class="cupo-stat">
                                <div class="numero"><?php echo $evento['Total_Inscritos']; ?></div>
                                <div class="label">Inscritos</div>
                            </div>
                            <div class="cupo-stat">
                                <div class="numero"><?php echo max(0, $cupos_disponibles); ?></div>
                                <div class="label">Disponibles</div>
                            </div>
                            <div class="cupo-stat">
                                <div class="numero"><?php echo $evento['Cupo_Maximo']; ?></div>
                                <div class="label">Cupo Total</div>
                            </div>
                        </div>
                        <div class="cupo-barra">
                            <div class="cupo-progreso" style="width: <?php echo min($porcentaje_cupo, 100); ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ALERTAS -->
                    <?php if ($dias_restantes <= 7 && !$evento_pasado && $dias_restantes > 0): ?>
                    <div class="alerta alerta-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><strong>¡Atención!</strong> El evento es en <?php echo $dias_restantes; ?> día<?php echo $dias_restantes > 1 ? 's' : ''; ?>.</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($cupos_disponibles > 0 && $cupos_disponibles <= 10 && $evento['Cupo_Maximo'] > 0): ?>
                    <div class="alerta alerta-warning">
                        <i class="fas fa-users"></i>
                        <span><strong>¡Últimos cupos!</strong> Solo quedan <?php echo $cupos_disponibles; ?> lugares disponibles.</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($cupos_disponibles <= 0 && $evento['Cupo_Maximo'] > 0): ?>
                    <div class="alerta alerta-error">
                        <i class="fas fa-times-circle"></i>
                        <span><strong>Cupo lleno.</strong> No hay lugares disponibles.</span>
                    </div>
                    <?php endif; ?>

                    <!-- COSTOS -->
                    <h2><i class="fas fa-dollar-sign"></i> Costos de Inscripción</h2>

                    <?php if (count($costos) > 0): ?>
                    <div class="costos-grid">
                        <?php foreach ($costos as $costo): ?>
                        <div class="costo-card">
                            <h3><?php echo htmlspecialchars($costo['Tipo_Inscripcion']); ?></h3>
                            <div class="costo-precio">
                                Q<?php echo number_format($costo['Costo'], 2); ?>
                            </div>
                            <?php if (!empty($costo['Descripcion'])): ?>
                            <div class="costo-descripcion">
                                <?php echo htmlspecialchars($costo['Descripcion']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="costo-periodo">
                                <i class="fas fa-calendar-check"></i>
                                <?php 
                                $fecha_inicio = new DateTime($costo['Fecha_Inicio']);
                                $fecha_fin = new DateTime($costo['Fecha_Fin']);
                                echo 'Válido: ' . $fecha_inicio->format('d/m/Y') . ' - ' . $fecha_fin->format('d/m/Y');
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alerta alerta-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Los costos se anunciarán próximamente.</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($puede_inscribirse): ?>
                    <button onclick="mostrarFormulario()" class="btn-inscribirse" id="btnMostrarFormulario">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $mensaje_boton; ?>
                    </button>
                    <?php else: ?>
                    <button class="btn-inscribirse" disabled>
                        <i class="fas fa-ban"></i>
                        <?php echo $mensaje_boton; ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FORMULARIO DE INSCRIPCIÓN -->
        <?php if ($puede_inscribirse): ?>
        <div id="formularioInscripcion">
            <div class="form-seccion">
                <div class="form-header">
                    <h3><i class="fas fa-user-plus"></i> Formulario de Inscripción</h3>
                    <p>Completa todos los campos marcados con (*) para completar tu inscripción</p>
                </div>

                <?php if (count($cuentas) > 0): ?>
                <div class="bank-info-section">
                    <h4 style="margin-bottom: 15px;"><i class="fas fa-university"></i> Información de Pago</h4>
                    <p style="margin-bottom: 15px;">Realiza tu pago a cualquiera de las siguientes cuentas antes de completar tu inscripción:</p>
                    <?php foreach ($cuentas as $cuenta): ?>
                    <div class="bank-details">
                        <p><strong>Banco:</strong> <?php echo htmlspecialchars($cuenta['Nombre_Banco']); ?></p>
                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cuenta['Nombre_Cuenta']); ?></p>
                        <p><strong>No. Cuenta:</strong> <?php echo htmlspecialchars($cuenta['Numero_Cuenta']); ?></p>
                        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($cuenta['Tipo_Cuenta']); ?></p>
                        <p><strong>Moneda:</strong> <?php echo $cuenta['Moneda']; ?></p>
                    </div>
                    <?php endforeach; ?>
                    <p style="margin-top: 15px; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-circle"></i> 
                        Luego de realizar tu pago, sube la boleta en el formulario
                    </p>
                </div>
                <?php endif; ?>

                <form id="registrationForm" enctype="multipart/form-data">
                    <input type="hidden" name="id_evento" value="<?php echo $id_evento; ?>">
                    
                    <div class="form-grid">
                        <?php if (count($categorias) > 0): ?>
                        <div class="form-group full-width required">
                            <label for="id_categoria">Categoría</label>
                            <select id="id_categoria" name="id_categoria" required>
                                <option value="">Selecciona tu categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['Id_Categoria']; ?>">
                                    <?php echo htmlspecialchars($cat['Nombre_Categoria']); ?>
                                    <?php if ($cat['Edad_Minima'] || $cat['Edad_Maxima']): ?>
                                    (<?php echo $cat['Edad_Minima'] ?: '0'; ?>-<?php echo $cat['Edad_Maxima'] ?: '∞'; ?> años - <?php echo $cat['Genero']; ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (count($costos) > 0): ?>
                        <div class="form-group full-width required">
                            <label for="id_costo">Tipo de Inscripción</label>
                            <select id="id_costo" name="id_costo" required>
                                <option value="">Selecciona el tipo de inscripción...</option>
                                <?php foreach ($costos as $costo): ?>
                                <option value="<?php echo $costo['Id_Costo']; ?>" data-costo="<?php echo $costo['Costo']; ?>">
                                    <?php echo htmlspecialchars($costo['Tipo_Inscripcion']); ?> - Q<?php echo number_format($costo['Costo'], 2); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- Mostrar costo total -->
                            <div id="costoTotal" style="display: none; margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; text-align: center;">
                                <p style="font-size: 0.9rem; margin-bottom: 5px;">Total a pagar:</p>
                                <p style="font-size: 2rem; font-weight: bold; margin: 0;">Q<span id="montoTotal">0.00</span></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group required">
                            <label for="nombres">Nombres</label>
                            <input type="text" id="nombres" name="nombres" required placeholder="Ej: Juan Carlos">
                        </div>

                        <div class="form-group required">
                            <label for="apellidos">Apellidos</label>
                            <input type="text" id="apellidos" name="apellidos" required placeholder="Ej: Pérez García">
                        </div>

                        <div class="form-group required">
                            <label for="edad">Edad</label>
                            <input type="number" id="edad" name="edad" min="5" max="99" required placeholder="Ej: 25">
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
                            <input type="tel" id="telefono" name="telefono" required placeholder="Ej: 5555-5555">
                        </div>

                        <div class="form-group required">
                            <label for="email">Correo Electrónico</label>
                            <input type="email" id="email" name="email" required placeholder="Ej: correo@ejemplo.com">
                        </div>

                        <div class="form-group required">
                            <label for="dpi">Número de DPI</label>
                            <input type="text" id="dpi" name="dpi" required placeholder="Ej: 1234567890101">
                        </div>

                        <div class="form-group full-width required">
                            <label for="departamento">Departamento</label>
                            <select id="departamento" name="departamento" required>
                                <option value="">Selecciona un departamento...</option>
                            </select>
                        </div>

                        <div class="form-group full-width required">
                            <label for="municipio">Municipio</label>
                            <select id="municipio" name="municipio" required disabled>
                                <option value="">Primero selecciona un departamento...</option>
                            </select>
                        </div>

                        <div class="form-group required">
                            <label for="zona">Zona</label>
                            <input type="text" id="zona" name="zona" required placeholder="Ej: Zona 10">
                        </div>

                        <div class="form-group full-width required">
                            <label for="direccion_detallada">Dirección Detallada</label>
                            <textarea id="direccion_detallada" name="direccion_detallada" rows="3" required placeholder="Ej: 5ta Avenida 10-45, Colonia Los Álamos"></textarea>
                        </div>

                        <div class="form-group required">
                            <label for="playera">Talla de Playera</label>
                            <select id="playera" name="playera" required>
                                <option value="">Selecciona tu talla...</option>
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
                            <label for="contacto_emergencia_nombres">Nombres del Contacto de Emergencia</label>
                            <input type="text" id="contacto_emergencia_nombres" name="contacto_emergencia_nombres" required placeholder="Nombres completos">
                        </div>

                        <div class="form-group required">
                            <label for="contacto_emergencia_apellidos">Apellidos del Contacto de Emergencia</label>
                            <input type="text" id="contacto_emergencia_apellidos" name="contacto_emergencia_apellidos" required placeholder="Apellidos completos">
                        </div>

                        <div class="form-group required">
                            <label for="telefono_emergencia">Teléfono de Emergencia</label>
                            <input type="tel" id="telefono_emergencia" name="telefono_emergencia" required placeholder="Ej: 5555-5555">
                        </div>

                        <div class="form-group full-width required">
                            <label for="boleta_pago">Boleta de Pago</label>
                            <input type="file" id="boleta_pago" name="boleta_pago" accept="image/*,.pdf" required>
                            <label for="boleta_pago" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span id="file-name">Haz clic para subir tu boleta de pago</span>
                            </label>
                            <small style="color: var(--text-secondary); margin-top: 5px; display: block;">Formatos: JPG, PNG, PDF. Máximo 5MB</small>
                            
                            <!-- Vista previa de la imagen -->
                            <div id="imagePreview" style="display: none; margin-top: 15px;">
                                <p style="font-weight: 600; margin-bottom: 10px;">Vista previa:</p>
                                <img id="previewImg" src="" alt="Vista previa" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 2px solid #e5e7eb;">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="terminos" required style="width: auto; margin: 0;">
                                <span>Acepto los términos y condiciones del evento *</span>
                            </label>
                        </div>
                    </div>

                    <div class="success-message" id="successMessage">
                        <i class="fas fa-check-circle"></i>
                        <h3>¡Inscripción Recibida!</h3>
                        <p>Tu número de participante es: <strong id="numeroParticipante"></strong></p>
                        <p>Recibirás un correo cuando tu inscripción sea confirmada.</p>
                    </div>

                    <div class="error-message" id="errorMessage"></div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Inscripción
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Datos de departamentos y municipios de Guatemala
        const departamentosMunicipios = {
            "Alta Verapaz": ["Cobán", "Santa Cruz Verapaz", "San Cristóbal Verapaz", "Tactic", "Tamahú", "San Miguel Tucurú", "Panzós", "Senahú", "San Pedro Carchá", "San Juan Chamelco", "San Agustín Lanquín", "Santa María Cahabón", "Chisec", "Chahal", "Fray Bartolomé de las Casas", "La Tinta", "Raxruhá"],
            "Baja Verapaz": ["Salamá", "San Miguel Chicaj", "Rabinal", "Cubulco", "Granados", "Santa Cruz el Chol", "San Jerónimo", "Purulhá"],
            "Chimaltenango": ["Chimaltenango", "San José Poaquil", "San Martín Jilotepeque", "San Juan Comalapa", "Santa Apolonia", "Tecpán Guatemala", "Patzún", "San Miguel Pochuta", "Patzicía", "Santa Cruz Balanyá", "Acatenango", "San Pedro Yepocapa", "San Andrés Itzapa", "Parramos", "Zaragoza", "El Tejar"],
            "Chiquimula": ["Chiquimula", "Jocotán", "Esquipulas", "Camotán", "Quezaltepeque", "Olopa", "Ipala", "San Juan Ermita", "Concepción Las Minas", "San Jacinto", "San José la Arada"],
            "El Progreso": ["Guastatoya", "Morazán", "San Agustín Acasaguastlán", "San Cristóbal Acasaguastlán", "El Jícaro", "Sansare", "Sanarate", "San Antonio La Paz"],
            "Escuintla": ["Escuintla", "Santa Lucía Cotzumalguapa", "La Democracia", "Siquinalá", "Masagua", "Tiquisate", "La Gomera", "Guanagazapa", "San José", "Iztapa", "Palín", "San Vicente Pacaya", "Nueva Concepción"],
            "Guatemala": ["Guatemala", "Santa Catarina Pinula", "San José Pinula", "San José del Golfo", "Palencia", "Chinautla", "San Pedro Ayampuc", "Mixco", "San Pedro Sacatepéquez", "San Juan Sacatepéquez", "San Raymundo", "Chuarrancho", "Fraijanes", "Amatitlán", "Villa Nueva", "Villa Canales", "Petapa"],
            "Huehuetenango": ["Huehuetenango", "Chiantla", "Malacatancito", "Cuilco", "Nentón", "San Pedro Necta", "Jacaltenango", "San Pedro Soloma", "San Ildefonso Ixtahuacán", "Santa Bárbara", "La Libertad", "La Democracia", "San Miguel Acatán", "San Rafael La Independencia", "Todos Santos Cuchumatán", "San Juan Atitán", "Santa Eulalia", "San Mateo Ixtatán", "Colotenango", "San Sebastián Huehuetenango", "Tectitán", "Concepción Huista", "San Juan Ixcoy", "San Antonio Huista", "Santa Cruz Barillas", "San Sebastián Coatán", "Aguacatán", "San Rafael Petzal", "San Gaspar Ixchil", "Santiago Chimaltenango", "Santa Ana Huista"],
            "Izabal": ["Puerto Barrios", "Livingston", "El Estor", "Morales", "Los Amates"],
            "Jalapa": ["Jalapa", "San Pedro Pinula", "San Luis Jilotepeque", "San Manuel Chaparrón", "San Carlos Alzatate", "Monjas", "Mataquescuintla"],
            "Jutiapa": ["Jutiapa", "El Progreso", "Santa Catarina Mita", "Agua Blanca", "Asunción Mita", "Yupiltepeque", "Atescatempa", "Jerez", "El Adelanto", "Zapotitlán", "Comapa", "Jalpatagua", "Conguaco", "Moyuta", "Pasaco", "San José Acatempa", "Quesada"],
            "Petén": ["Flores", "San José", "San Benito", "San Andrés", "La Libertad", "San Francisco", "Santa Ana", "Dolores", "San Luis", "Sayaxché", "Melchor de Mencos", "Poptún", "Las Cruces", "El Chal"],
            "Quetzaltenango": ["Quetzaltenango", "Salcajá", "Olintepeque", "San Carlos Sija", "Sibilia", "Cabricán", "Cajolá", "San Miguel Sigüilá", "San Juan Ostuncalco", "San Mateo", "Concepción Chiquirichapa", "San Martín Sacatepéquez", "Almolonga", "Cantel", "Huitán", "Zunil", "Colomba Costa Cuca", "San Francisco La Unión", "El Palmar", "Coatepeque", "Génova", "Flores Costa Cuca", "La Esperanza", "Palestina de Los Altos"],
            "Quiché": ["Santa Cruz del Quiché", "Chiché", "Chinique", "Zacualpa", "Chajul", "Santo Tomás Chichicastenango", "Patzité", "San Antonio Ilotenango", "San Pedro Jocopilas", "Cunén", "San Juan Cotzal", "Joyabaj", "Nebaj", "San Andrés Sajcabajá", "San Miguel Uspantán", "Sacapulas", "San Bartolomé Jocotenango", "Canillá", "Chicamán", "Ixcán", "Pachalum"],
            "Retalhuleu": ["Retalhuleu", "San Sebastián", "Santa Cruz Muluá", "San Martín Zapotitlán", "San Felipe", "San Andrés Villa Seca", "Champerico", "Nuevo San Carlos", "El Asintal"],
            "Sacatepéquez": ["Antigua Guatemala", "Jocotenango", "Pastores", "Sumpango", "Santo Domingo Xenacoj", "Santiago Sacatepéquez", "San Bartolomé Milpas Altas", "San Lucas Sacatepéquez", "Santa Lucía Milpas Altas", "Magdalena Milpas Altas", "Santa María de Jesús", "Ciudad Vieja", "San Miguel Dueñas", "Alotenango", "San Antonio Aguas Calientes", "Santa Catarina Barahona"],
            "San Marcos": ["San Marcos", "San Pedro Sacatepéquez", "San Antonio Sacatepéquez", "Comitancillo", "San Miguel Ixtahuacán", "Concepción Tutuapa", "Tacaná", "Sibinal", "Tajumulco", "Tejutla", "San Rafael Pie de la Cuesta", "Nuevo Progreso", "El Tumbador", "San José El Rodeo", "Malacatán", "Catarina", "Ayutla", "Ocós", "San Pablo", "El Quetzal", "La Reforma", "Pajapita", "Ixchiguán", "San José Ojetenam", "San Cristóbal Cucho", "Sipacapa", "Esquipulas Palo Gordo", "Río Blanco", "San Lorenzo"],
            "Santa Rosa": ["Cuilapa", "Barberena", "Santa Rosa de Lima", "Casillas", "San Rafael Las Flores", "Oratorio", "San Juan Tecuaco", "Chiquimulilla", "Taxisco", "Santa María Ixhuatán", "Guazacapán", "Santa Cruz Naranjo", "Pueblo Nuevo Viñas", "Nueva Santa Rosa"],
            "Sololá": ["Sololá", "San José Chacayá", "Santa María Visitación", "Santa Lucía Utatlán", "Nahualá", "Santa Catarina Ixtahuacán", "Santa Clara La Laguna", "Concepción", "San Andrés Semetabaj", "Panajachel", "Santa Catarina Palopó", "San Antonio Palopó", "San Lucas Tolimán", "Santa Cruz La Laguna", "San Pablo La Laguna", "San Marcos La Laguna", "San Juan La Laguna", "San Pedro La Laguna", "Santiago Atitlán"],
            "Suchitepéquez": ["Mazatenango", "Cuyotenango", "San Francisco Zapotitlán", "San Bernardino", "San José El Ídolo", "Santo Domingo Suchitepéquez", "San Lorenzo", "Samayac", "San Pablo Jocopilas", "San Antonio Suchitepéquez", "San Miguel Panán", "San Gabriel", "Chicacao", "Patulul", "Santa Bárbara", "San Juan Bautista", "Santo Tomás La Unión", "Zunilito", "Pueblo Nuevo", "Río Bravo"],
            "Totonicapán": ["Totonicapán", "San Cristóbal Totonicapán", "San Francisco El Alto", "San Andrés Xecul", "Momostenango", "Santa María Chiquimula", "Santa Lucía La Reforma", "San Bartolo"],
            "Zacapa": ["Zacapa", "Estanzuela", "Río Hondo", "Gualán", "Teculután", "Usumatlán", "Cabañas", "San Diego", "La Unión", "Huité", "San Jorge"]
        };

        // Cargar departamentos al cargar la página
        window.addEventListener('DOMContentLoaded', function() {
            const departamentoSelect = document.getElementById('departamento');
            const municipioSelect = document.getElementById('municipio');
            
            // Llenar select de departamentos
            Object.keys(departamentosMunicipios).sort().forEach(depto => {
                const option = document.createElement('option');
                option.value = depto;
                option.textContent = depto;
                departamentoSelect.appendChild(option);
            });
            
            // Escuchar cambios en departamento
            departamentoSelect.addEventListener('change', function() {
                municipioSelect.disabled = false;
                municipioSelect.innerHTML = '<option value="">Selecciona un municipio...</option>';
                
                const departamento = this.value;
                if (departamento && departamentosMunicipios[departamento]) {
                    departamentosMunicipios[departamento].forEach(muni => {
                        const option = document.createElement('option');
                        option.value = muni;
                        option.textContent = muni;
                        municipioSelect.appendChild(option);
                    });
                } else {
                    municipioSelect.disabled = true;
                    municipioSelect.innerHTML = '<option value="">Primero selecciona un departamento...</option>';
                }
            });
        });

        function mostrarFormulario() {
            const formulario = document.getElementById('formularioInscripcion');
            const boton = document.getElementById('btnMostrarFormulario');
            
            formulario.classList.add('activo');
            formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Ocultar el botón después de hacer clic
            boton.style.display = 'none';
        }

        // Mostrar costo total cuando se selecciona tipo de inscripción
        document.getElementById('id_costo').addEventListener('change', function() {
            const costoTotal = document.getElementById('costoTotal');
            const montoTotal = document.getElementById('montoTotal');
            const selectedOption = this.options[this.selectedIndex];
            
            if (this.value) {
                const costo = selectedOption.getAttribute('data-costo');
                montoTotal.textContent = parseFloat(costo).toFixed(2);
                costoTotal.style.display = 'block';
            } else {
                costoTotal.style.display = 'none';
            }
        });

        // Mostrar nombre del archivo seleccionado y vista previa
        document.getElementById('boleta_pago').addEventListener('change', function(e) {
            const fileName = document.getElementById('file-name');
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (e.target.files[0]) {
                const file = e.target.files[0];
                fileName.textContent = file.name;
                fileName.parentElement.style.borderColor = 'var(--success-color)';
                
                // Mostrar vista previa si es imagen
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        previewImg.src = event.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.style.display = 'none';
                }
            } else {
                fileName.textContent = 'Haz clic para subir tu boleta de pago';
                imagePreview.style.display = 'none';
            }
        });

        // Procesar formulario
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            // Deshabilitar botón y ocultar mensajes
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
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
                    
                    // Resetear formulario después de 5 segundos
                    setTimeout(() => {
                        this.reset();
                        document.getElementById('file-name').textContent = 'Haz clic para subir tu boleta de pago';
                        document.getElementById('imagePreview').style.display = 'none';
                        document.getElementById('costoTotal').style.display = 'none';
                        document.getElementById('municipio').disabled = true;
                        document.getElementById('municipio').innerHTML = '<option value="">Primero selecciona un departamento...</option>';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Inscripción';
                    }, 5000);
                } else {
                    errorMsg.textContent = '❌ ' + (data.message || 'Error al procesar la inscripción');
                    errorMsg.style.display = 'block';
                    errorMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Inscripción';
                }
            } catch (error) {
                console.error('Error:', error);
                errorMsg.textContent = '❌ Error al procesar la inscripción. Intenta nuevamente.';
                errorMsg.style.display = 'block';
                errorMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Inscripción';
            }
        });
    </script>
</body>
</html>