<?php
// eventos.php - Lista de eventos disponibles
require_once 'conexion.php';

// Obtener todos los eventos próximos
$sql_eventos = "
    SELECT 
        e.*,
        te.Nombre as Tipo_Evento_Nombre,
        COUNT(DISTINCT ie.Id_Inscripcion) as Total_Inscritos,
        MIN(ci.Costo) as Precio_Desde
    FROM Eventos e
    INNER JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
    LEFT JOIN Inscripciones_Evento ie ON e.Id_Evento = ie.Id_Evento 
        AND ie.Estado_Inscripcion != 'Cancelado'
    LEFT JOIN Costos_Inscripcion ci ON e.Id_Evento = ci.Id_Evento 
        AND ci.Estado = 'Activo'
        AND CURDATE() BETWEEN ci.Fecha_Inicio AND ci.Fecha_Fin
    WHERE e.Fecha_Evento >= CURDATE()
    GROUP BY e.Id_Evento
    ORDER BY e.Fecha_Evento ASC
";

try {
    $stmt_eventos = $pdo->prepare($sql_eventos);
    $stmt_eventos->execute();
    $resultado_eventos = $stmt_eventos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Obtener tipos de evento para el filtro
try {
    $sql_tipos = "SELECT * FROM Tipos_Evento WHERE Estado = 'Activo' ORDER BY Nombre";
    $stmt_tipos = $pdo->prepare($sql_tipos);
    $stmt_tipos->execute();
    $tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tipos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - Club Rotario Coatepeque</title>
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

        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .header-section h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .header-section p {
            font-size: 1.2rem;
            opacity: 0.95;
        }

        .container {
            max-width: 1200px;
            margin: -40px auto 40px;
            padding: 0 20px;
        }

        .filtros-eventos {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filtros-eventos label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .filtros-eventos select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .filtros-eventos select:focus {
            outline: none;
            border-color: var(--primary-light);
        }

        .eventos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .evento-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .evento-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .evento-imagen {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .evento-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.95);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: var(--shadow);
        }

        .badge-abierto {
            background: var(--success-color) !important;
        }

        .badge-cerrado {
            background: var(--error-color) !important;
        }

        .badge-pronto {
            background: var(--warning-color) !important;
        }

        .evento-content {
            padding: 25px;
        }

        .evento-titulo {
            font-size: 1.4rem;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .evento-tipo {
            display: inline-block;
            background: var(--primary-light);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 15px;
        }

        .evento-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0;
        }

        .evento-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .evento-info-item i {
            color: var(--primary-light);
            width: 20px;
            text-align: center;
        }

        .cupo-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 10px;
        }

        .cupo-barra {
            flex: 1;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }

        .cupo-progreso {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-light));
            transition: width 0.3s ease;
        }

        .evento-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 2px solid #f3f4f6;
            margin-top: 15px;
        }

        .evento-precio {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .evento-precio small {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: normal;
        }

        .btn-ver-detalles {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-ver-detalles:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .no-eventos {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .no-eventos i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .no-eventos h2 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .no-eventos p {
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .header-section h1 {
                font-size: 2rem;
            }
            
            .eventos-grid {
                grid-template-columns: 1fr;
            }
            
            .filtros-eventos {
                flex-direction: column;
            }
            
            .filtros-eventos select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header-section">
        <h1><i class="fas fa-calendar-alt"></i> Eventos Club Rotario</h1>
        <p>Únete a nuestros eventos y apoya causas benéficas</p>
    </div>

    <div class="container">
        <?php if (count($resultado_eventos) > 0): ?>
            <div class="filtros-eventos">
                <i class="fas fa-filter" style="color: var(--primary-color);"></i>
                <label>Filtrar por:</label>
                <select id="filtroTipo">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tipos as $tipo): ?>
                    <option value="<?php echo htmlspecialchars($tipo['Nombre']); ?>">
                        <?php echo htmlspecialchars($tipo['Nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="filtroEstado">
                    <option value="">Todos los estados</option>
                    <option value="Inscripciones Abiertas">Inscripciones Abiertas</option>
                    <option value="Planificado">Próximamente</option>
                    <option value="Inscripciones Cerradas">Cerrados</option>
                </select>
            </div>

            <div class="eventos-grid" id="eventosGrid">
                <?php foreach ($resultado_eventos as $evento): 
                    $fecha_evento = new DateTime($evento['Fecha_Evento']);
                    $fecha_actual = new DateTime();
                    $dias_restantes = $fecha_actual->diff($fecha_evento)->days;
                    
                    // Calcular porcentaje de cupo
                    $porcentaje_cupo = 0;
                    if ($evento['Cupo_Maximo'] > 0) {
                        $porcentaje_cupo = ($evento['Total_Inscritos'] / $evento['Cupo_Maximo']) * 100;
                    }
                    
                    // Determinar clase de estado
                    $clase_estado = 'badge-abierto';
                    $texto_estado = 'Inscripciones Abiertas';
                    
                    if ($evento['Estado_Evento'] === 'Inscripciones Cerradas') {
                        $clase_estado = 'badge-cerrado';
                        $texto_estado = 'Cerrado';
                    } elseif ($evento['Estado_Evento'] === 'Planificado') {
                        $clase_estado = 'badge-pronto';
                        $texto_estado = 'Próximamente';
                    }
                    
                    // Formatear fecha en español
                    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                    $mes = $meses[(int)$fecha_evento->format('n')];
                ?>
                <div class="evento-card" 
                     data-tipo="<?php echo htmlspecialchars($evento['Tipo_Evento_Nombre']); ?>"
                     data-estado="<?php echo htmlspecialchars($evento['Estado_Evento']); ?>"
                     onclick="window.location.href='evento.php?id=<?php echo $evento['Id_Evento']; ?>'">
                    
                    <?php if (!empty($evento['Imagen_Banner'])): ?>
                    <img src="<?php echo htmlspecialchars($evento['Imagen_Banner']); ?>" 
                         alt="<?php echo htmlspecialchars($evento['Nombre_Evento']); ?>" 
                         class="evento-imagen">
                    <?php else: ?>
                    <div class="evento-imagen">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <?php endif; ?>
                    
                    <span class="evento-badge <?php echo $clase_estado; ?>">
                        <?php echo $texto_estado; ?>
                    </span>
                    
                    <div class="evento-content">
                        <h3 class="evento-titulo">
                            <?php echo htmlspecialchars($evento['Nombre_Evento']); ?>
                        </h3>
                        
                        <span class="evento-tipo">
                            <i class="fas fa-tag"></i> 
                            <?php echo htmlspecialchars($evento['Tipo_Evento_Nombre']); ?>
                        </span>
                        
                        <div class="evento-info">
                            <div class="evento-info-item">
                                <i class="fas fa-calendar"></i>
                                <span>
                                    <?php echo $fecha_evento->format('d') . ' de ' . $mes . ' de ' . $fecha_evento->format('Y'); ?>
                                    <?php if ($dias_restantes <= 7 && $fecha_evento > $fecha_actual): ?>
                                    <strong style="color: var(--error-color);">
                                        (¡<?php echo $dias_restantes; ?> días!)
                                    </strong>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="evento-info-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('h:i A', strtotime($evento['Hora_Inicio'])); ?></span>
                            </div>
                            
                            <div class="evento-info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php 
                                $lugar = $evento['Lugar_Salida'];
                                echo htmlspecialchars(strlen($lugar) > 50 ? substr($lugar, 0, 50) . '...' : $lugar); 
                                ?></span>
                            </div>
                            
                            <?php if ($evento['Distancia_KM']): ?>
                            <div class="evento-info-item">
                                <i class="fas fa-route"></i>
                                <span><?php echo $evento['Distancia_KM']; ?> km</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($evento['Cupo_Maximo']): ?>
                            <div class="cupo-info">
                                <i class="fas fa-users"></i>
                                <span><?php echo $evento['Total_Inscritos']; ?> / <?php echo $evento['Cupo_Maximo']; ?></span>
                                <div class="cupo-barra">
                                    <div class="cupo-progreso" style="width: <?php echo min($porcentaje_cupo, 100); ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="evento-footer">
                            <?php if ($evento['Precio_Desde']): ?>
                            <div class="evento-precio">
                                Q<?php echo number_format($evento['Precio_Desde'], 2); ?>
                                <small>desde</small>
                            </div>
                            <?php else: ?>
                            <div class="evento-precio">
                                <small>Consultar precio</small>
                            </div>
                            <?php endif; ?>
                            
                            <a href="evento.php?id=<?php echo $evento['Id_Evento']; ?>" 
                               class="btn-ver-detalles"
                               onclick="event.stopPropagation();">
                                Ver Detalles <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-eventos">
                <i class="fas fa-calendar-times"></i>
                <h2>No hay eventos disponibles</h2>
                <p>Pronto habrá nuevos eventos. ¡Mantente atento!</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Sistema de filtros
        const filtroTipo = document.getElementById('filtroTipo');
        const filtroEstado = document.getElementById('filtroEstado');
        const eventosGrid = document.getElementById('eventosGrid');
        
        function filtrarEventos() {
            const tipoSeleccionado = filtroTipo.value.toLowerCase();
            const estadoSeleccionado = filtroEstado.value.toLowerCase();
            const cards = eventosGrid.querySelectorAll('.evento-card');
            
            let visibles = 0;
            
            cards.forEach(card => {
                const tipo = card.dataset.tipo.toLowerCase();
                const estado = card.dataset.estado.toLowerCase();
                
                const cumpleTipo = !tipoSeleccionado || tipo.includes(tipoSeleccionado);
                const cumpleEstado = !estadoSeleccionado || estado === estadoSeleccionado;
                
                if (cumpleTipo && cumpleEstado) {
                    card.style.display = 'block';
                    visibles++;
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        if (filtroTipo) filtroTipo.addEventListener('change', filtrarEventos);
        if (filtroEstado) filtroEstado.addEventListener('change', filtrarEventos);
    </script>
</body>
</html>