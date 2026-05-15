<?php

include 'conexion.php';

$mensaje = '';
$tipo_mensaje = '';
$nombre = '';
$id_rol = '';
$email = '';

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener roles de la base de datos
try {
    $stmt = $pdo->query("SELECT Id_Rol, Nombre, Descripcion FROM Rol WHERE 1=1 ORDER BY Nombre");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error al obtener roles: " . $e->getMessage());
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $id_rol = $_POST['id_rol'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    $errores = [];
    
    // Validaciones
    if (empty($nombre)) {
        $errores[] = 'El nombre es requerido';
    } elseif (strlen($nombre) < 3) {
        $errores[] = 'El nombre debe tener al menos 3 caracteres';
    }
    
    if (empty($id_rol)) {
        $errores[] = 'Debe seleccionar un rol';
    } else {
        // Verificar que el rol existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Rol WHERE Id_Rol = :id_rol");
        $stmt->execute([':id_rol' => $id_rol]);
        if ($stmt->fetchColumn() == 0) {
            $errores[] = 'El rol seleccionado no existe';
        }
    }
    
    // Validar contraseña
    if (empty($password_input)) {
        $errores[] = 'La contraseña es requerida';
    } elseif (strlen($password_input) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres';
    }
    
    if ($password_input !== $password_confirm) {
        $errores[] = 'Las contraseñas no coinciden';
    }
    
    // Validar email si se proporciona
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del email no es válido';
    }
    
    // Verificar que el nombre no exista ya
    if (!empty($nombre)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuario WHERE Nombre = :nombre");
        $stmt->execute([':nombre' => $nombre]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ya existe un usuario con ese nombre';
        }
    }
    
    // Verificar que el email no exista ya
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuario WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ya existe un usuario con ese email';
        }
    }
    
    // Si no hay errores, insertar en la base de datos
    if (empty($errores)) {
        try {
            // Generar hash de la contraseña
            $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO Usuario (Id_Rol, Nombre, Password, Email, Estado) VALUES (:id_rol, :nombre, :password, :email, 'Activo')");
            $stmt->execute([
                ':id_rol' => $id_rol,
                ':nombre' => $nombre,
                ':password' => $password_hash,
                ':email' => $email
            ]);
            
            $nuevo_id = $pdo->lastInsertId();
            $mensaje = "✓ Usuario registrado exitosamente con ID: " . $nuevo_id . "<br>Ahora puede iniciar sesión con sus credenciales.";
            $tipo_mensaje = 'success';
            
            // Limpiar el formulario
            $nombre = '';
            $id_rol = '';
            $email = '';
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "Error: El nombre de usuario o email ya existe en el sistema";
            } else {
                $mensaje = "Error al registrar usuario: " . $e->getMessage();
            }
            $tipo_mensaje = 'error';
        }
    } else {
        $mensaje = implode('<br>', $errores);
        $tipo_mensaje = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario - RotarioDB</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .form-content {
            padding: 40px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select {
            cursor: pointer;
        }
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .password-strength-text {
            font-size: 11px;
            margin-top: 5px;
            font-weight: 600;
        }
        
        .strength-weak {
            background: #e74c3c;
            color: #e74c3c;
        }
        
        .strength-medium {
            background: #f39c12;
            color: #f39c12;
        }
        
        .strength-strong {
            background: #27ae60;
            color: #27ae60;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        button {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .icon {
            width: 20px;
            height: 20px;
        }
        
        .role-description {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
            display: none;
        }
        
        .role-description.active {
            display: block;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 20px;
            user-select: none;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .back-login {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .back-login a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .back-login a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 600px) {
            .form-content {
                padding: 25px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Registro de Usuario</h1>
            <p>Sistema de Gestión Rotario</p>
        </div>
        
        <div class="form-content">
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="formRegistro">
                <div class="form-group">
                    <label for="nombre">
                        Nombre de Usuario <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="nombre" 
                        name="nombre" 
                        value="<?php echo htmlspecialchars($nombre); ?>"
                        placeholder="Ingrese el nombre de usuario"
                        required
                        autocomplete="off"
                    >
                    <div class="help-text">Mínimo 3 caracteres, debe ser único</div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        Contraseña <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Ingrese la contraseña"
                            required
                            autocomplete="new-password"
                        >
                        <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="password-strength-text" id="strengthText"></div>
                    <div class="help-text">Mínimo 6 caracteres</div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">
                        Confirmar Contraseña <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Confirme la contraseña"
                            required
                            autocomplete="new-password"
                        >
                        <span class="password-toggle" onclick="togglePassword('password_confirm')">👁️</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        Correo Electrónico
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($email); ?>"
                        placeholder="usuario@ejemplo.com"
                        autocomplete="off"
                    >
                    <div class="help-text">Opcional, pero debe ser válido y único</div>
                </div>
                
                <div class="form-group">
                    <label for="id_rol">
                        Rol <span class="required">*</span>
                    </label>
                    <select id="id_rol" name="id_rol" required>
                        <option value="">Seleccione un rol</option>
                        <?php foreach ($roles as $rol): ?>
                            <option 
                                value="<?php echo $rol['Id_Rol']; ?>"
                                data-description="<?php echo htmlspecialchars($rol['Descripcion'] ?? ''); ?>"
                                <?php echo ($id_rol == $rol['Id_Rol']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['Nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="role-description" id="roleDescription"></div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-primary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Registrar Usuario
                    </button>
                    <button type="reset" class="btn-secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Limpiar
                    </button>
                </div>
            </form>
            
            <div class="back-login">
                <a href="login.php">← Volver al Login</a>
            </div>
        </div>
    </div>

    <script>
        // Mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
        
        // Verificar fortaleza de contraseña
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.length >= 10) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            if (/\d/.test(password)) strength += 15;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 10;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 40) {
                strengthBar.className = 'password-strength-bar strength-weak';
                strengthText.className = 'password-strength-text strength-weak';
                strengthText.textContent = 'Débil';
            } else if (strength < 70) {
                strengthBar.className = 'password-strength-bar strength-medium';
                strengthText.className = 'password-strength-text strength-medium';
                strengthText.textContent = 'Media';
            } else {
                strengthBar.className = 'password-strength-bar strength-strong';
                strengthText.className = 'password-strength-text strength-strong';
                strengthText.textContent = 'Fuerte';
            }
        });
        
        // Verificar que las contraseñas coincidan
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#667eea';
            }
        });
        
        // Mostrar descripción del rol
        const selectRol = document.getElementById('id_rol');
        const roleDescription = document.getElementById('roleDescription');
        
        selectRol.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const description = selectedOption.getAttribute('data-description');
            
            if (description && this.value) {
                roleDescription.textContent = description;
                roleDescription.classList.add('active');
            } else {
                roleDescription.classList.remove('active');
            }
        });
        
        // Confirmación antes de limpiar
        const btnLimpiar = document.querySelector('.btn-secondary');
        btnLimpiar.addEventListener('click', function(e) {
            const nombre = document.getElementById('nombre').value;
            const password = document.getElementById('password').value;
            
            if ((nombre || password) && !confirm('¿Está seguro que desea limpiar el formulario?')) {
                e.preventDefault();
            } else {
                roleDescription.classList.remove('active');
                document.getElementById('strengthBar').style.width = '0%';
                document.getElementById('strengthText').textContent = '';
            }
        });
    </script>
</body>
</html>