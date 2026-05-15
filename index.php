<?php
require_once 'conexion.php';

function obtenerEventosProximos($pdo, $limite = 6) {
    $sql = "SELECT 
                e.Id_Evento,
                e.Nombre_Evento,
                e.Descripcion,
                e.Fecha_Evento,
                e.Hora_Inicio,
                e.Hora_Salida,
                e.Lugar_Salida,
                e.Estado_Evento,
                e.Cupo_Maximo,
                e.Imagen_Banner,
                te.Nombre AS Tipo_Evento,
                COUNT(DISTINCT ie.Id_Inscripcion) AS Total_Inscritos,
                (e.Cupo_Maximo - COUNT(DISTINCT ie.Id_Inscripcion)) AS Cupo_Disponible
            FROM Eventos e
            INNER JOIN Tipos_Evento te ON e.Id_Tipo_Evento = te.Id_Tipo_Evento
            LEFT JOIN Inscripciones_Evento ie 
                ON e.Id_Evento = ie.Id_Evento 
                AND ie.Estado_Inscripcion != 'Cancelado'
            WHERE e.Fecha_Evento >= CURDATE()
            AND e.Estado_Evento IN ('Planificado', 'Inscripciones Abiertas')
            GROUP BY e.Id_Evento
            ORDER BY e.Fecha_Evento ASC
            LIMIT :limite";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatearFechaEvento($fecha) {
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = date('M', $timestamp);
    $anio = date('Y', $timestamp);
    
    $meses = [
        'Jan' => 'ENE', 'Feb' => 'FEB', 'Mar' => 'MAR', 'Apr' => 'ABR',
        'May' => 'MAY', 'Jun' => 'JUN', 'Jul' => 'JUL', 'Aug' => 'AGO',
        'Sep' => 'SEP', 'Oct' => 'OCT', 'Nov' => 'NOV', 'Dec' => 'DIC'
    ];
    
    $mes = $meses[$mes] ?? $mes;
    
    return "$dia $mes $anio";
}

function formatearHora($hora) {
    return date('g:i A', strtotime($hora));
}

function obtenerEstadoBadge($estado) {
    $badges = [
        'Inscripciones Abiertas' => '<span class="badge badge-success">Inscripciones Abiertas</span>',
        'Planificado' => '<span class="badge badge-info">Próximamente</span>',
        'Inscripciones Cerradas' => '<span class="badge badge-warning">Inscripciones Cerradas</span>',
        'En Curso' => '<span class="badge badge-primary">En Curso</span>',
        'Finalizado' => '<span class="badge badge-secondary">Finalizado</span>'
    ];
    
    return $badges[$estado] ?? '';
}

$eventos = obtenerEventosProximos($pdo, 6);

function obtenerTestimonios($pdo, $limite = 6, $soloActivos = true) {
    try {
        $sql = "SELECT 
                    Id_Testimonio,
                    Nombre_Estudiante,
                    Testimonio,
                    Foto,
                    Orden
                FROM Testimonios
                WHERE Activo = :activo
                ORDER BY Orden ASC, Fecha_Registro DESC
                LIMIT :limite";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':activo', $soloActivos ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Error al obtener testimonios: " . $e->getMessage());
        return [];
    }
}

$testimonios = obtenerTestimonios($pdo, 6, true);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Rotario Coatepeque Colomba - Transformando Vidas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/inicios.css">
</head>
<body>
    <header id="header">
        <div class="container nav-container">
            <div class="logo">
                <img src="logo/logo.jpg" alt="Club Rotario" class="logo-icon">
                <h1>Club Rotario Coatepeque Colomba</h1>
            </div>
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav>
                <ul id="navMenu">
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#becas">Becas</a></li>
                    <li><a href="solicitud_beca.php">Solicitar Beca</a></li>
                    <li><a href="eventos.php">Eventos</a></li>
                    <li><a href="#aplicar">Cómo Aplicar</a></li>
                    <li><a href="#testimonios">Testimonios</a></li>
                    <li><a href="#contacto">Contacto</a></li>
                    <li class="login-mobile"><a href="login.php">Iniciar Sesión</a></li>
                </ul>
            </nav>

            <div class="nav-cta">
                <a href="login.php" class="btn-dorado">
                    <i class="fas fa-door-closed door-icon"></i>
                    Iniciar Sesión
                </a>
            </div>
        </div>
    </header>

    <section class="hero" id="inicio">
        <div class="hero-slider">
            <div class="slide active">
                <img src="https://plus.unsplash.com/premium_photo-1683887034146-c79058dbdcb1?ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&q=80&w=869" alt="Estudiantes universitarios">
            </div>
            <div class="slide">
                <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80" alt="Educación y aprendizaje">
            </div>
            <div class="slide">
                <img src="https://images.unsplash.com/photo-1560472354-b33ff0c44a43?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80" alt="Comunidad y servicio">
            </div>
            <div class="slide">
                <img src="https://images.unsplash.com/photo-1758270703286-6600f25efdbc?ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&q=80&w=1031" alt="Futuro y oportunidades">
            </div>
        </div>
        <div class="hero-content">
            <div class="hero-badge">Transformando Vidas desde 1985</div>
            <h2>Educación que Transforma Futuros</h2>
            <p>En Rotary, creemos que cada joven merece la oportunidad de alcanzar sus sueños. Ofrecemos becas completas y programas de desarrollo para estudiantes talentosos.</p>
            <div class="hero-buttons">
                <a href="#becas" class="btn-azul">
                    <i class="fas fa-graduation-cap"></i>
                    Descubrir Becas
                </a>
                <a href="eventos.php" class="btn-dorado">
                    <i class="fas fa-calendar-alt"></i>
                    Ver Eventos
                </a>
                <a href="conoce.php" class="btn-azul">
                    <i class="fas fa-users"></i>
                    Conoce Rotary
                </a>
            </div>
        </div>
    </section>

    <!-- About Section Premium -->
    <section class="about" id="nosotros">
        <div class="container">
            <div class="section-title">
                <h2>Nuestra Misión</h2>
                <p>Comprometidos con el desarrollo educativo y el crecimiento de nuestra comunidad</p>
            </div>
            <div class="about-content">
                <div class="about-text">
                    <p>El Club Rotario Coatepeque Colomba es una organización sin fines de lucro que desde 1985 ha trabajado incansablemente para apoyar la educación de jóvenes talentosos en nuestra región. Creemos firmemente que la falta de recursos económicos no debe ser un impedimento para acceder a una educación de calidad.</p>
                    
                    <div class="about-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Compromiso Social</h4>
                                <p>Trabajamos por el bienestar de nuestra comunidad a través de la educación.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-hand-holding-heart"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Apoyo Integral</h4>
                                <p>Ofrecemos acompañamiento académico y personal a nuestros becarios.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-award"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Excelencia Académica</h4>
                                <p>Promovemos el alto rendimiento y la superación personal.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Trabajo en Equipo</h4>
                                <p>Colaboramos con instituciones educativas y la comunidad.</p>
                            </div>
                        </div>
                    </div>
                    
                    <p>Nuestro programa de becas no solo cubre aspectos económicos como matrícula, libros y gastos de manutención, sino que también incluye mentoría, talleres de desarrollo personal y oportunidades de networking para asegurar el éxito integral de nuestros estudiantes.</p>
                    
                    <a href="conoce.php" class="btn-dorado">
                        <i class="fas fa-arrow-right"></i> Conoce más sobre nosotros
                    </a>
                </div>
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1523580494863-6f3031224c94?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Estudiantes graduados">
                </div>
            </div>
        </div>
    </section>

    <!-- Programs Section Premium -->
    <section class="programs" id="becas">
        <div class="container">
            <div class="section-title">
                <h2>Programas de Becas</h2>
                <p>Ofrecemos diferentes tipos de becas diseñadas para apoyar a estudiantes con talento y compromiso</p>
            </div>
            <div class="programs-grid">
                <div class="program-card fade-in">
                    <div class="program-image">
                        <img src="https://images.unsplash.com/photo-1560785496-3c9d27877182?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Beca Excelencia Académica">
                    </div>
                    <div class="program-content">
                        <h3>Beca Excelencia Académica</h3>
                        <p>Dirigida a estudiantes con promedios sobresalientes (mínimo 9.0) que demuestren compromiso con su desarrollo educativo y potencial de liderazgo.</p>
                        <div class="program-details">
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Cobertura:</strong> 100% de matrícula y libros</span>
                            </div>
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Duración:</strong> Hasta 5 años</span>
                            </div>
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Requisito:</strong> Promedio mínimo de 9.0</span>
                            </div>
                        </div>
                        <a href="#aplicar" class="btn-dorado">Más información</a>
                    </div>
                </div>
                
                <div class="program-card fade-in">
                    <div class="program-image">
                        <img src="https://images.unsplash.com/photo-1571260899304-425eee4c7efc?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Beca Liderazgo Juvenil">
                    </div>
                    <div class="program-content">
                        <h3>Beca Liderazgo Juvenil</h3>
                        <p>Para jóvenes que han demostrado capacidad de liderazgo en sus comunidades, centros educativos o mediante proyectos de impacto social.</p>
                        <div class="program-details">
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Cobertura:</strong> 80% de matrícula y manutención</span>
                            </div>
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Duración:</strong> Hasta 4 años</span>
                            </div>
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Requisito:</strong> Experiencia en liderazgo comprobable</span>
                            </div>
                        </div>
                        <a href="#aplicar" class="btn-dorado">Más información</a>
                    </div>
                </div>
                
                <div class="program-card fade-in">
                    <div class="program-image">
                        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Beca Vocación de Servicio">
                    </div>
                    <div class="program-content">
                        <h3>Beca Vocación de Servicio</h3>
                        <p>Destinada a estudiantes que han realizado trabajo voluntario y servicio comunitario significativo, demostrando compromiso con el bienestar social.</p>
                        <div class="program-details">
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Cobertura:</strong> 70% de matrícula y seguro médico</span>
                            </div>
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Duración:</strong> Hasta 3 años</span>
                            </div>
                            <div class="program-detail">
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Requisito:</strong> Experiencia en voluntariado</span>
                            </div>
                        </div>
                        <a href="#aplicar" class="btn-dorado">Más información</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Apply Section Premium -->
    <section class="apply" id="aplicar">
        <div class="container">
            <div class="section-title">
                <h2>Cómo Aplicar a Becas</h2>
                <p>Sigue estos simples pasos para solicitar una de nuestras becas y transformar tu futuro</p>
            </div>
            
            <div class="apply-steps">
                <div class="step-card fade-in">
                    <div class="step-number">1</div>
                    <h3>Revisa los requisitos</h3>
                    <p>Asegúrate de cumplir con todos los requisitos específicos para la beca a la que deseas aplicar. Consulta los detalles de cada programa.</p>
                </div>
                
                <div class="step-card fade-in">
                    <div class="step-number">2</div>
                    <h3>Prepara tu documentación</h3>
                    <p>Reúne todos los documentos necesarios para tu aplicación. Te recomendamos organizarlos con anticipación.</p>
                </div>
                
                <div class="step-card fade-in">
                    <div class="step-number">3</div>
                    <h3>Completa el formulario</h3>
                    <p>Llena cuidadosamente el formulario de aplicación en línea o presencial. Asegúrate de que toda la información sea correcta.</p>
                </div>
                
                <div class="step-card fade-in">
                    <div class="step-number">4</div>
                    <h3>Envía tu aplicación</h3>
                    <p>Envía tu aplicación completa antes de la fecha límite. Recibirás una confirmación de recepción y seguimiento del proceso.</p>
                </div>
            </div>
            
            <div class="requirements fade-in">
                <h3>Documentos Requeridos para Becas</h3>
                <ul class="requirements-list">
                    <li>Solicitud de beca completamente llena y firmada</li>
                    <li>Copia de identificación oficial (DPI o pasaporte)</li>
                    <li>Comprobante de domicilio reciente</li>
                    <li>Constancia de estudios con promedio oficial</li>
                    <li>Historial académico completo</li>
                    <li>Carta de motivos (máximo 2 páginas)</li>
                    <li>Carta de recomendación de un profesor o mentor</li>
                    <li>Comprobante de ingresos familiares</li>
                    <li>Currículum vitae actualizado (en caso de posgrado)</li>
                    <li>Comprobante de situación socioeconómica</li>
                    <li>Fotografías tamaño credencial (2)</li>
                    <li>Comprobante de actividades extracurriculares (opcional)</li>
                </ul>
            </div>
            
            <div class="apply-cta">
                <a href="solicitud_beca.php" class="btn-azul pulse">
                    <i class="fas fa-edit"></i> Comenzar Solicitud de Beca
                </a>
            </div>
        </div>
    </section>

    <!-- Events Section Premium -->
    <section class="events" id="eventos">
        <div class="container">
            <div class="section-title">
                <h2>Próximos Eventos</h2>
                <p>Únete a nuestras actividades y forma parte del cambio en nuestra comunidad</p>
            </div>
            
            <?php if (count($eventos) > 0): ?>
                <div class="events-grid">
                    <?php foreach ($eventos as $evento): ?>
                        <div class="event-card fade-in" data-evento-id="<?php echo $evento['Id_Evento']; ?>">
                            <div class="event-date">
                                <?php echo formatearFechaEvento($evento['Fecha_Evento']); ?>
                            </div>
                            
                            <?php if ($evento['Imagen_Banner']): ?>
                                <div class="event-image">
                                    <img src="<?php echo htmlspecialchars($evento['Imagen_Banner']); ?>" 
                                         alt="<?php echo htmlspecialchars($evento['Nombre_Evento']); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="event-content">
                                <div class="event-header">
                                    <h3><?php echo htmlspecialchars($evento['Nombre_Evento']); ?></h3>
                                    <?php echo obtenerEstadoBadge($evento['Estado_Evento']); ?>
                                </div>
                                
                                <div class="event-info">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($evento['Tipo_Evento']); ?></span>
                                </div>
                                
                                <div class="event-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($evento['Lugar_Salida']); ?></span>
                                </div>
                                
                                <div class="event-info">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?php 
                                        echo formatearHora($evento['Hora_Inicio']);
                                        if ($evento['Hora_Salida']) {
                                            echo ' - ' . formatearHora($evento['Hora_Salida']);
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <?php if ($evento['Cupo_Maximo']): ?>
                                    <div class="event-capacity">
                                        <div class="capacity-bar">
                                            <?php 
                                            $porcentaje = ($evento['Total_Inscritos'] / $evento['Cupo_Maximo']) * 100;
                                            ?>
                                            <div class="capacity-fill" style="width: <?php echo $porcentaje; ?>%"></div>
                                        </div>
                                        <small>
                                            <?php echo $evento['Total_Inscritos']; ?> / <?php echo $evento['Cupo_Maximo']; ?> inscritos
                                            <?php if ($evento['Cupo_Disponible'] <= 10 && $evento['Cupo_Disponible'] > 0): ?>
                                                <span class="text-warning">¡Últimos cupos!</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <p><?php echo nl2br(htmlspecialchars(substr($evento['Descripcion'], 0, 150))); ?>
                                   <?php if (strlen($evento['Descripcion']) > 150) echo '...'; ?></p>
                                
                                <div class="event-actions">
                                    <a href="detalle_evento.php?id=<?php echo $evento['Id_Evento']; ?>" class="btn btn-azul">
                                        <i class="fas fa-info-circle"></i> Más información
                                    </a>
                                    
                                    <?php if ($evento['Estado_Evento'] === 'Inscripciones Abiertas' && 
                                             $evento['Cupo_Disponible'] > 0): ?>
                                        <a href="inscripcion.php?evento=<?php echo $evento['Id_Evento']; ?>" 
                                           class="btn btn-success">
                                            <i class="fas fa-user-plus"></i> Inscribirse
                                        </a>
                                    <?php elseif ($evento['Cupo_Disponible'] <= 0): ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-times"></i> Cupo Lleno
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-events fade-in">
                    <div class="no-events-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3>Próximamente más eventos</h3>
                    <p>Actualmente no hay eventos programados. Mantente atento a nuestras redes sociales para conocer las próximas actividades del Club Rotario.</p>
                    <div class="social-links-eventos">
                        <a href="https://www.facebook.com/Club.Rotario.Coatepeque.Colomba/?locale=es_LA" class="social-btn">
                            <i class="fab fa-facebook"></i> Facebook
                        </a>
                        <a href="https://www.instagram.com/rotarios_coatepequecolomba?igsh=MTg5Z3BhcG1nbHJxeQ==" class="social-btn">
                            <i class="fab fa-instagram"></i> Instagram
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 50px;">
                <a href="eventos.php" class="btn-dorado">
                    <i class="fas fa-calendar-alt"></i> Ver Todos los Eventos
                </a>
            </div>
        </div>
    </section>

    <!-- Testimonials Section Premium -->
    <section class="testimonials" id="testimonios">
        <div class="container">
            <div class="section-title">
                <h2>Testimonios</h2>
                <p>Historias de éxito de nuestros becarios que han transformado sus vidas a través de la educación</p>
            </div>
            
            <?php if (count($testimonios) > 0): ?>
                <div class="testimonials-grid">
                    <?php foreach ($testimonios as $testimonio): ?>
                        <div class="testimonial-card fade-in" data-testimonio-id="<?php echo $testimonio['Id_Testimonio']; ?>">
                            <div class="testimonial-text">
                                <p><?php echo nl2br(htmlspecialchars($testimonio['Testimonio'])); ?></p>
                            </div>
                            
                            <div class="testimonial-author">
                                <div class="author-image" 
                                     <?php if (empty($testimonio['Foto'])): ?>
                                        style="background-color: <?php 
                                            $colores = ['#005daa', '#f2a900', '#003b76', '#28a745', '#dc3545', '#6f42c1'];
                                            echo $colores[array_rand($colores)]; 
                                        ?>;"
                                     <?php endif; ?>>
                                    <?php if (!empty($testimonio['Foto'])): ?>
                                        <img src="<?php echo htmlspecialchars($testimonio['Foto']); ?>" 
                                             alt="<?php echo htmlspecialchars($testimonio['Nombre_Estudiante']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php 
                                        $nombres = explode(' ', $testimonio['Nombre_Estudiante']);
                                        $iniciales = strtoupper(substr($nombres[0], 0, 1));
                                        if (isset($nombres[1])) {
                                            $iniciales .= strtoupper(substr($nombres[1], 0, 1));
                                        }
                                        echo $iniciales;
                                        ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="author-info">
                                    <h4><?php echo htmlspecialchars($testimonio['Nombre_Estudiante']); ?></h4>
                                    <p>Becario Rotary</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-testimonios fade-in">
                    <div class="no-testimonios-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Próximamente más testimonios</h3>
                    <p>Estamos recopilando las historias de éxito de nuestros becarios para compartirlas contigo.</p>
                </div>
            <?php endif; ?>
            
            <?php if (count($testimonios) >= 6): ?>
                <div class="testimonios-cta">
                    <a href="testimonios.php" class="btn-dorado">
                        <i class="fas fa-heart"></i> Ver más historias de éxito
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section Premium -->
    <section class="contact" id="contacto">
        <div class="container">
            <div class="section-title">
                <h2>Contacto</h2>
                <p>Estamos aquí para ayudarte. Contáctanos para más información sobre nuestros programas</p>
            </div>
            
            <div class="contact-grid">
                <div class="contact-info">
                    <h3>Información de Contacto</h3>
                    
                    <div class="contact-details">
                        <div class="contact-item fade-in">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Dirección</h4>
                                <p>5 Calle 4-56 Zona 1, Coatepeque, Guatemala</p>
                            </div>
                        </div>
                        
                        <div class="contact-item fade-in">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Teléfono</h4>
                                <p>7775 5248</p>
                            </div>
                        </div>
                        
                        <div class="contact-item fade-in">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Correo Electrónico</h4>
                                <p>rotarios_coatepequecolomba@yahoo.com.mx</p>
                            </div>
                        </div>
                        
                        <div class="contact-item fade-in">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Horario de Atención</h4>
                                <p>Lunes a Viernes: 9:00 AM - 5:00 PM</p>
                                <p>Sábados: 9:00 AM - 12:00 PM</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="map fade-in">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.914739125936!2d-91.86160316061815!3d14.7034630175249!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x858e5f2d2a3f6f31%3A0x0!2s5%20Calle%204-56%2C%20Zona%201%2C%20Coatepeque%2C%20Guatemala!5e0!3m2!1ses!2sgt!4v1756578500000!5m2!1ses!2sgt"
                            width="100%"
                            height="100%"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
                
                <div class="contact-form fade-in">
                    <h3>Envíanos un mensaje</h3>
                    <p style="color: #666; margin-bottom: 20px; font-size: 0.95rem;">
                        <i class="fas fa-info-circle" style="color: var(--azul-rotary);"></i> 
                        Completa el formulario y adjunta tu carta de solicitud si lo deseas
                    </p>
                    
                    <form id="contactForm" method="POST" enctype="multipart/form-data">
                        <input type="text" name="nombre" placeholder="Nombre completo" required>
                        <input type="email" name="email" placeholder="Correo electrónico" required>
                        <input type="tel" name="telefono" placeholder="Teléfono (opcional)">
                        <input type="text" name="asunto" placeholder="Asunto (opcional)">
                        <textarea name="mensaje" placeholder="Tu mensaje" required></textarea>
                        
                        <div class="file-upload-wrapper">
                            <label for="documento" class="file-upload-label">
                                <i class="fas fa-paperclip"></i>
                                <span id="fileName">Adjuntar carta de solicitud (PDF, DOC, DOCX - Máx. 5MB)</span>
                            </label>
                            <input 
                                type="file" 
                                id="documento" 
                                name="documento" 
                                accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                style="display: none;">
                            <button type="button" id="clearFile" class="clear-file-btn" style="display: none;">
                                <i class="fas fa-times"></i> Quitar archivo
                            </button>
                        </div>
                        
                        <button type="submit" class="btn-dorado">
                            <i class="fas fa-paper-plane"></i> Enviar mensaje
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Premium -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>Rotary</h3>
                    <p>Organización sin fines de lucro dedicada a apoyar la educación de jóvenes talentosos mediante programas de becas y desarrollo comunitario.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/Club.Rotario.Coatepeque.Colomba/?locale=es_LA"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/rotarios_coatepequecolomba?igsh=MTg5Z3BhcG1nbHJxeQ=="><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Enlaces rápidos</h3>
                    <ul>
                        <li><a href="#inicio"><i class="fas fa-chevron-right"></i> Inicio</a></li>
                        <li><a href="#becas"><i class="fas fa-chevron-right"></i> Becas</a></li>
                        <li><a href="solicitud_beca.php"><i class="fas fa-chevron-right"></i> Solicitar Beca</a></li>
                        <li><a href="eventos.php"><i class="fas fa-chevron-right"></i> Eventos</a></li>
                        <li><a href="#aplicar"><i class="fas fa-chevron-right"></i> Cómo aplicar</a></li>
                        <li><a href="#testimonios"><i class="fas fa-chevron-right"></i> Testimonios</a></li>
                        <li><a href="#contacto"><i class="fas fa-chevron-right"></i> Contacto</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contacto</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 5 Calle 4-56 Zona 1, Coatepeque, Guatemala</li>
                        <li><i class="fas fa-phone"></i> Tel: 7775 5248</li>
                        <li><i class="fas fa-envelope"></i> rotarios_coatepequecolomba@yahoo.com.mx</li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Lugar de Reunión</h3>
                    <ul>
                        <li><i class="fas fa-coffee"></i> Maragos Caffé</li>
                        <li>
                            <a href="https://maps.app.goo.gl/ZXW1moMALn2otkcP6" target="_blank">
                                <i class="fas fa-map-pin"></i> 06 Calle zona 1, Barrio La Esperanza, Coatepeque, Quetzaltenango, 09020
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; 2025 Club Rotario Coatepeque-Colomba Costa Cuca. Asociación de Becas. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('header');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 100) {
                    header.classList.add('header-scrolled');
                } else {
                    header.classList.remove('header-scrolled');
                }
            });

            const fadeElements = document.querySelectorAll('.fade-in');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            fadeElements.forEach(element => {
                observer.observe(element);
            });

            const fileInput = document.getElementById('documento');
            const fileName = document.getElementById('fileName');
            const clearFileBtn = document.getElementById('clearFile');

            if (fileInput && fileName && clearFileBtn) {
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        fileName.textContent = this.files[0].name;
                        clearFileBtn.style.display = 'inline-block';
                    } else {
                        fileName.textContent = 'Adjuntar carta de solicitud (PDF, DOC, DOCX - Máx. 5MB)';
                        clearFileBtn.style.display = 'none';
                    }
                });

                clearFileBtn.addEventListener('click', function() {
                    fileInput.value = '';
                    fileName.textContent = 'Adjuntar carta de solicitud (PDF, DOC, DOCX - Máx. 5MB)';
                    clearFileBtn.style.display = 'none';
                });
            }

            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        const headerHeight = document.querySelector('header').offsetHeight;
                        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                const hero = document.querySelector('.hero-slider');
                if (hero) {
                    hero.style.transform = `translateY(${scrolled * 0.5}px)`;
                }
            });

            const statNumbers = document.querySelectorAll('.stat-number');
            const observerStats = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const stat = entry.target;
                        const target = parseInt(stat.textContent);
                        let current = 0;
                        const increment = target / 50;
                        
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= target) {
                                stat.textContent = target + (stat.textContent.includes('+') ? '+' : '');
                                clearInterval(timer);
                            } else {
                                stat.textContent = Math.floor(current) + (stat.textContent.includes('+') ? '+' : '');
                            }
                        }, 30);
                        
                        observerStats.unobserve(stat);
                    }
                });
            }, { threshold: 0.5 });

            statNumbers.forEach(stat => {
                observerStats.observe(stat);
            });

            const cards = document.querySelectorAll('.program-card, .event-card, .testimonial-card');
            cards.forEach(card => {
                card.addEventListener('mousemove', function(e) {
                    const cardRect = this.getBoundingClientRect();
                    const cardWidth = cardRect.width;
                    const cardHeight = cardRect.height;
                    const centerX = cardRect.left + cardWidth / 2;
                    const centerY = cardRect.top + cardHeight / 2;
                    const mouseX = e.clientX - centerX;
                    const mouseY = e.clientY - centerY;
                    const rotateX = (mouseY / cardHeight) * 10;
                    const rotateY = (mouseX / cardWidth) * -10;
                    
                    this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-15px)`;
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(-15px)';
                });
            });

            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (mobileMenuToggle && navMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('show');
                });
            }

            const slides = document.querySelectorAll('.slide');
            let currentSlide = 0;
            
            function showSlide(index) {
                slides.forEach(slide => slide.classList.remove('active'));
                slides[index].classList.add('active');
            }
            
            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }
            
            setInterval(nextSlide, 5000);
        });
    </script>
</body>
</html>