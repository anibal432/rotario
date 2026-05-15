<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <div class="club-info">
                <div class="club-name">Club Rotario</div>
                <div class="club-location">Coatepeque - Colomba</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">
            <div class="nav-title">Principal</div>
            <ul class="nav-menu">
                <li class="nav-item <?= $current_page == 'admin.php' ? 'active' : '' ?>">
                    <a href="admin.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item <?= $current_page == 'gestionar_usuario.php' ? 'active' : '' ?>">
                    <a href="gestionar_usuario.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <span class="nav-text">Gestionar Usuarios</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Solicitudes -->
        <div class="nav-section">
            <div class="nav-title">Solicitudes</div>
            <ul class="nav-menu">
                <li class="nav-item <?= $current_page == 'lista_solicitudes.php' ? 'active' : '' ?>">
                    <a href="lista_solicitudes.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <span class="nav-text">Ver Solicitudes</span>
                    </a>
                </li>
                <li class="nav-item <?= $current_page == 'gestionar_preguntas.php' ? 'active' : '' ?>">
                    <a href="gestionar_preguntas.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <span class="nav-text">Gestionar Preguntas</span>
                    </a>
                </li>
                <li class="nav-item <?= ($current_page == 'gestionar_reglamento.php' || $current_page == 'agregar_clausula.php' || $current_page == 'editar_clausula.php') ? 'active' : '' ?>">
                    <a href="gestionar_reglamento.php" class="nav-link">
                    <div class="nav-icon">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                   <span class="nav-text">Gestionar Reglamento</span>
                </a>
                 </li>
            </ul>
        </div>

        <!-- Becados -->
        <div class="nav-section">
            <div class="nav-title">Becados</div>
            <ul class="nav-menu">
                <li class="nav-item <?= ($current_page == 'estudiantes_becados.php' || $current_page == 'registrar_pago.php' || $current_page == 'subir_boleta.php' ) ? 'active' : '' ?>">
                    <a href="estudiantes_becados.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="nav-text">Ver Becados</span>
                    </a>
                </li>
                <li class="nav-item <?= $current_page == 'reactivar.php' ? 'active' : '' ?>">
                    <a href="reactivar.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-redo"></i>
                        </div>
                        <span class="nav-text">Reactivar Solicitudes</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Evaluaciones -->
        <div class="nav-section">
            <div class="nav-title">Evaluaciones</div>
            <ul class="nav-menu">
                <li class="nav-item <?= $current_page == 'evaluacion_anual.php' ? 'active' : '' ?>">
                    <a href="evaluacion_anual.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <span class="nav-text">Evaluación Anual</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Eventos -->
        <div class="nav-section">
            <div class="nav-title">Eventos</div>
            <ul class="nav-menu">
                <li class="nav-item <?= $current_page == 'Crear_evento.php' ? 'active' : '' ?>">
                    <a href="Crear_evento.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <span class="nav-text">Crear Eventos</span>
                    </a>
                </li>
                <li class="nav-item <?= $current_page == 'ver_eventos.php' ? 'active' : '' ?>">
                    <a href="ver_eventos.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <span class="nav-text">Ver Eventos</span>
                    </a>
                </li>
                <li class="nav-item <?= $current_page == 'revisar_inscrpciones.php' ? 'active' : '' ?>">
                    <a href="revisar_inscrpciones.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <span class="nav-text">Revisar Inscripciones</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Reportes -->
        <div class="nav-section">
            <div class="nav-title">Reportes</div>
            <ul class="nav-menu">
                <li class="nav-item <?= $current_page == 'reportes.php' ? 'active' : '' ?>">
                    <a href="reportes.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Público -->
        <div class="nav-section">
            <div class="nav-title">Público</div>
            <ul class="nav-menu">
                <li class="nav-item <?= $current_page == 'testimonios.php' ? 'active' : '' ?>">
                    <a href="testimonios.php" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-quote-right"></i>
                        </div>
                        <span class="nav-text">Testimonios</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php
                function getInitials($name) {
                    $words = explode(' ', $name);
                    if (count($words) >= 2) {
                        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                    }
                    return strtoupper(substr($name, 0, 2));
                }
                echo getInitials($_SESSION['username'] ?? 'Usuario');
                ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></div>
                <div class="user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'Usuario') ?></div>
            </div>
        </div>
        <button class="logout-btn" onclick="cerrarSesion()">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const sidebar = document.querySelector('.sidebar');

if (sidebar) {
    const savedScroll = localStorage.getItem('sidebarScroll');
    if (savedScroll) {
        sidebar.scrollTop = savedScroll;
    }

    sidebar.addEventListener('scroll', () => {
        localStorage.setItem('sidebarScroll', sidebar.scrollTop);
    });
}

function cerrarSesion() {
    Swal.fire({
        title: '¿Está seguro?',
        text: "Se cerrará la sesión actual",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, cerrar sesión',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            localStorage.removeItem('sidebarScroll');
            window.location.href = 'logout.php';
        }
    });
}
</script>
