<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Rotario - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

    <div class="login-container">
        <div class="logo-section">
            <div class="rotary-wheel">
                <i class="fas fa-cog"></i>
            </div>
            <h1 class="club-title">Club Rotario</h1>
            <p class="club-subtitle">Sistema de Becas</p>
        </div>

        <div class="alert alert-error" id="alertError"></div>
        <div class="alert alert-success" id="alertSuccess"></div>
        <div class="alert alert-warning" id="alertWarning"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Ingrese su usuario" required autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" required autocomplete="current-password">
                </div>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Recordar mi sesión</label>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i>
                <span id="btnText">Iniciar Sesión</span>
            </button>
        </form>

        <div class="forgot-password">
            <a href="#" onclick="handleForgotPassword(); return false;">
                <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
            </a>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            handleLogin();
        });

        function handleLogin() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');

            hideAlerts();

            if (!username || !password) {
                showAlert('error', 'Por favor complete todos los campos');
                return;
            }

            // Deshabilitar botón y mostrar loading
            loginBtn.disabled = true;
            btnText.innerHTML = '<span class="loading"></span> Verificando...';

            // Enviar datos al servidor
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);

            fetch('auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar warning si existe
                    if (data.warning) {
                        showAlert('warning', data.warning);
                    }
                    
                    showAlert('success', '¡Login exitoso! Redirigiendo...');
                    btnText.textContent = '✓ Acceso Concedido';
                    loginBtn.style.background = 'linear-gradient(135deg, #10b981, #34d399)';
                    
                    setTimeout(() => {
                        window.location.href = 'admin.php';
                    }, 1500);
                } else {
                    showAlert('error', data.message);
                    loginBtn.disabled = false;
                    btnText.textContent = 'Iniciar Sesión';
                }
            })
            .catch(error => {
                showAlert('error', 'Error de conexión. Verifique su conexión a internet.');
                console.error('Error:', error);
                loginBtn.disabled = false;
                btnText.textContent = 'Iniciar Sesión';
            });
        }

        function setupDatabase() {
            if (!confirm('¿Desea configurar la base de datos con los usuarios iniciales?\n\nEsto creará:\n• admin / rotario2025\n• coordinador / becas123\n• usuario / usuario123')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'setup');

            fetch('auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message + '\n\n✓ Usuarios creados:\n• admin / rotario2025 (Administrador)\n• coordinador / becas123 (Coordinador)\n• usuario / usuario123 (Usuario)');
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                showAlert('error', 'Error al configurar: ' + error.message);
            });
        }

        function showAlert(type, message) {
            hideAlerts();
            let alertElement;
            
            if (type === 'error') {
                alertElement = document.getElementById('alertError');
            } else if (type === 'warning') {
                alertElement = document.getElementById('alertWarning');
            } else {
                alertElement = document.getElementById('alertSuccess');
            }
            
            alertElement.textContent = message;
            alertElement.style.display = 'block';
        }

        function hideAlerts() {
            document.getElementById('alertError').style.display = 'none';
            document.getElementById('alertSuccess').style.display = 'none';
            document.getElementById('alertWarning').style.display = 'none';
        }

        function handleForgotPassword() {
            alert('Para recuperar su contraseña, contacte al administrador del sistema.\n\nAdministrador: admin@rotario.org\nTeléfono: (502) 2345-6789');
        }

        // Enfocar en el campo usuario al cargar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Enter en username pasa a password
        document.getElementById('username').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>