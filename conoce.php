<?php
require_once 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conócenos - Club Rotario Coatepeque Colomba</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/conoce.css">
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
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="index.php#becas">Becas</a></li>
                    <li><a href="eventos.php">Inscripción Eventos</a></li>
                    <li><a href="index.php#aplicar">Cómo Aplicar</a></li>
                    <li><a href="index.php#eventos">Eventos</a></li>
                    <li><a href="index.php#testimonios">Testimonios</a></li>
                    <li><a href="index.php#contacto">Contacto</a></li>
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

    <section class="conocenos-hero">
        <div class="container">
            <h1>Conócenos</h1>
            <p>Descubre quiénes somos, qué nos impulsa y cómo trabajamos para transformar vidas a través de la educación y el servicio a la comunidad</p>
        </div>
    </section>

    <section class="mision-vision">
        <div class="container">
            <div class="section-title">
                <h2>Nuestra Esencia</h2>
            </div>
            <div class="mision-vision-grid">
                <div class="mision-card fade-in">
                    <h3>
                        <i class="fas fa-bullseye"></i>
                        Misión
                    </h3>
                    <p>
                        Somos una organización dedicada a transformar vidas a través de la educación, proporcionando becas y oportunidades de desarrollo a jóvenes talentosos con necesidades económicas. Nos comprometemos a fomentar la excelencia académica, el liderazgo y los valores rotarios, creando un impacto positivo y duradero en nuestra comunidad.
                    </p>
                    <p>
                        Trabajamos incansablemente para eliminar las barreras económicas que impiden el acceso a la educación de calidad, creyendo firmemente que cada estudiante merece la oportunidad de alcanzar su máximo potencial.
                    </p>
                </div>

                <div class="vision-card fade-in">
                    <h3>
                        <i class="fas fa-eye"></i>
                        Visión
                    </h3>
                    <p>
                        Ser la organización líder en la región en el otorgamiento de becas educativas, reconocidos por nuestro compromiso inquebrantable con la excelencia académica y el desarrollo integral de los estudiantes. Aspiramos a crear una red sólida de profesionales exitosos que retribuyan a sus comunidades y perpetúen el ciclo del servicio.
                    </p>
                    <p>
                        Soñamos con un futuro donde ningún estudiante talentoso y dedicado vea truncados sus sueños por falta de recursos económicos, construyendo así una sociedad más equitativa y próspera.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="valores-section">
        <div class="container">
            <div class="section-title">
                <h2>Nuestros Valores</h2>
            </div>
            <div class="valores-grid">
                <div class="valor-card fade-in">
                    <i class="fas fa-hands-helping"></i>
                    <h3>Servicio</h3>
                    <p>Dedicamos nuestro tiempo y recursos para servir a la comunidad y cambiar vidas</p>
                </div>
                <div class="valor-card fade-in">
                    <i class="fas fa-heart"></i>
                    <h3>Compañerismo</h3>
                    <p>Fomentamos relaciones duraderas basadas en el respeto y la solidaridad</p>
                </div>
                <div class="valor-card fade-in">
                    <i class="fas fa-balance-scale"></i>
                    <h3>Integridad</h3>
                    <p>Actuamos con honestidad y transparencia en todas nuestras acciones</p>
                </div>
                <div class="valor-card fade-in">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Liderazgo</h3>
                    <p>Inspiramos y formamos líderes comprometidos con el cambio positivo</p>
                </div>
            </div>
        </div>
    </section>

    <section class="eventos-realizados">
        <div class="container">
            <div class="section-title">
                <h2>Eventos Realizados</h2>
                <p>Conoce las actividades que hemos organizado para recaudar fondos y fortalecer nuestra comunidad</p>
            </div>
            <div class="eventos-grid">
                <div class="evento-card fade-in">
                    <div class="evento-imagen">
                        <img src="https://images.unsplash.com/photo-1452626038306-9aae5e071dd3?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Carrera 21K">
                        <div class="evento-badge">21K</div>
                    </div>
                    <div class="evento-contenido">
                        <h3>Carrera 21K Rotaria</h3>
                        <p>Nuestra carrera insignia que reúne a corredores de toda la región en un desafío de medio maratón. Un evento que combina deporte, solidaridad y compromiso con la educación.</p>
                        <div class="evento-detalles">
                            <div class="detalle-item">
                                <i class="fas fa-running"></i>
                                <span>21 kilómetros</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-users"></i>
                                <span>500+ participantes</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-trophy"></i>
                                <span>Anual</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="evento-card fade-in">
                    <div class="evento-imagen">
                        <img src="https://images.unsplash.com/photo-1486218119243-13883505764c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Carrera 10K">
                        <div class="evento-badge">10K</div>
                    </div>
                    <div class="evento-contenido">
                        <h3>Carrera 10K por la Educación</h3>
                        <p>Una distancia perfecta para corredores de todos los niveles. Esta carrera ha permitido financiar becas para decenas de estudiantes talentosos de nuestra comunidad.</p>
                        <div class="evento-detalles">
                            <div class="detalle-item">
                                <i class="fas fa-running"></i>
                                <span>10 kilómetros</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-users"></i>
                                <span>300+ participantes</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-heart"></i>
                                <span>Familiar</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="evento-card fade-in">
                    <div class="evento-imagen">
                        <img src="https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Carrera 5K">
                        <div class="evento-badge">5K</div>
                    </div>
                    <div class="evento-contenido">
                        <h3>Carrera 5K Familiar</h3>
                        <p>Un evento inclusivo diseñado para toda la familia. Los más pequeños y principiantes pueden disfrutar del deporte mientras apoyan una noble causa.</p>
                        <div class="evento-detalles">
                            <div class="detalle-item">
                                <i class="fas fa-running"></i>
                                <span>5 kilómetros</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-users"></i>
                                <span>400+ participantes</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-child"></i>
                                <span>Todas las edades</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="evento-card fade-in">
                    <div class="evento-imagen">
                        <img src="https://images.unsplash.com/photo-1541625602330-2277a4c46182?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" alt="Cicletón">
                        <div class="evento-badge">Cicletón</div>
                    </div>
                    <div class="evento-contenido">
                        <h3>Cicletón Rotario</h3>
                        <p>Un emocionante recorrido en bicicleta que promueve el deporte, la salud y la unión comunitaria. Ciclistas de todos los niveles se unen por una causa común: la educación.</p>
                        <div class="evento-detalles">
                            <div class="detalle-item">
                                <i class="fas fa-biking"></i>
                                <span>Ruta panorámica</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-users"></i>
                                <span>250+ ciclistas</span>
                            </div>
                            <div class="detalle-item">
                                <i class="fas fa-mountain"></i>
                                <span>Todos los niveles</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="asambleas-section">
        <div class="container">
            <div class="section-title">
                <h2>Encuentros con Nuestros Becados</h2>
            </div>
            <div class="asambleas-content">
                <div class="asambleas-texto fade-in">
                    <h3>
                        <i class="fas fa-users"></i>
                        Asambleas en Maragos
                    </h3>
                    <p>
                        Regularmente nos reunimos con nuestros becados en el hermoso lugar de Maragos, un espacio que nos permite crear un ambiente de confianza, aprendizaje y crecimiento mutuo. Estas asambleas son fundamentales para mantener una comunicación cercana con nuestros estudiantes y conocer de primera mano sus experiencias, retos y logros.
                    </p>
                    <p>
                        Durante estos encuentros, compartimos momentos de reflexión, celebramos los éxitos académicos, abordamos inquietudes y reforzamos nuestro compromiso con su desarrollo integral. Es una oportunidad invaluable para que los becados se conozcan entre sí, creando una red de apoyo que trasciende el ámbito académico.
                    </p>
                    
                    <ul class="asambleas-lista">
                        <li>
                            <i class="fas fa-calendar-check"></i>
                            Reuniones trimestrales con todos los becados
                        </li>
                        <li>
                            <i class="fas fa-comments"></i>
                            Espacios de diálogo abierto y retroalimentación
                        </li>
                        <li>
                            <i class="fas fa-graduation-cap"></i>
                            Seguimiento personalizado del rendimiento académico
                        </li>
                        <li>
                            <i class="fas fa-handshake"></i>
                            Fortalecimiento de la comunidad rotaria
                        </li>
                        <li>
                            <i class="fas fa-award"></i>
                            Reconocimiento de logros y excelencia
                        </li>
                    </ul>
                </div>
                <div class="asambleas-imagen fade-in">
                    <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&q=80&w=870" alt="Asambleas en Maragos">
                </div>
            </div>
        </div>
    </section>

    <section class="join-section">
        <div class="container">
            <div class="section-title">
                <h2>Únete a Nuestra Causa</h2>
            </div>
            <div class="join-content">
                <p>
                    Cada evento que realizamos, cada asamblea que organizamos y cada beca que otorgamos es posible gracias al compromiso y generosidad de personas como tú. Te invitamos a ser parte de esta transformación.
                </p>
                <div class="hero-buttons">
                    <a href="index.php#contacto" class="btn-dorado">Contáctanos</a>
                    <a href="index.php#becas" class="btn-azul">Ver Becas</a>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-columns">
                <div class="footer-column">
                    <h3>Acerca de nosotros</h3>
                    <p>Club Rotario Coatepeque-Colomba Costa Cuca es una organización dedicada a apoyar la educación de jóvenes talentosos mediante becas y programas de desarrollo.</p>
                </div>
                
                <div class="footer-column">
                    <h3>Enlaces rápidos</h3>
                    <ul>
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="index.php#becas">Becas</a></li>
                        <li><a href="eventos.php">Inscripción Eventos</a></li>
                        <li><a href="index.php#aplicar">Cómo aplicar</a></li>
                        <li><a href="index.php#eventos">Eventos</a></li>
                        <li><a href="index.php#testimonios">Testimonios</a></li>
                        <li><a href="index.php#contacto">Contacto</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contacto</h3>
                    <ul>
                        <li>5 Calle 4-56 Zona 1, Coatepeque, Guatemala</li>
                        <li>Tel: 7775 5248</li>
                        <li>Correo: rotarios_coatepequecolomba@yahoo.com.mx</li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Síguenos</h3>
                    <div class="social-links">
                        <a href="https://www.facebook.com/Club.Rotario.Coatepeque.Colomba/?locale=es_LA"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/rotarios_coatepequecolomba?igsh=MTg5Z3BhcG1nbHJxeQ=="><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; 2025 Club Rotario Coatepeque-Colomba Costa Cuca. Asociación de Becas. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Efecto de header al hacer scroll
            const header = document.getElementById('header');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 100) {
                    header.classList.add('header-scrolled');
                } else {
                    header.classList.remove('header-scrolled');
                }
            });

            // Animación de elementos al hacer scroll
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

            // Navegación suave para enlaces internos
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

            // Funcionalidad del menú móvil
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (mobileMenuToggle && navMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('show');
                });
            }

            // Efecto de tilt en tarjetas
            const cards = document.querySelectorAll('.evento-card, .mision-card, .vision-card, .valor-card');
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
                    
                    this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-10px)`;
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(-10px)';
                });
            });
        });
    </script>
</body>
</html>