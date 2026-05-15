<?php
// auth.php - Sistema de Autenticación
session_start();

// Verificar que el archivo de conexión existe
if (!file_exists('conexion.php')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error: archivo conexion.php no encontrado'
    ]);
    exit;
}

require_once "conexion.php";

// Verificar que la conexión existe
if (!isset($pdo)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: No hay conexión a la base de datos'
    ]);
    exit;
}

class AuthSystem {
    private $db;
    
    public function __construct() {
        global $pdo; 
        $this->db = $pdo;
    }
    
    public function login($username, $password) {
        try {
            // Buscar usuario en la base de datos
            $stmt = $this->db->prepare("
                SELECT u.Id_Usuario, u.Nombre, u.Password, u.Estado, r.Nombre as rol_nombre 
                FROM Usuario u 
                INNER JOIN Rol r ON u.Id_Rol = r.Id_Rol 
                WHERE u.Nombre = :username
            ");
            
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Verificar que el usuario esté activo
                if ($user['Estado'] !== 'Activo') {
                    return [
                        'success' => false,
                        'message' => 'Usuario inactivo. Contacte al administrador.'
                    ];
                }
                
                // Verificar contraseña con hash
                if (!empty($user['Password']) && password_verify($password, $user['Password'])) {
                    // Registrar en bitácora
                    $this->logActivity($user['Id_Usuario'], 'Inicio de sesión exitoso');
                    
                    // Crear sesión
                    $_SESSION['user_id'] = $user['Id_Usuario'];
                    $_SESSION['username'] = $user['Nombre'];
                    $_SESSION['role'] = $user['rol_nombre'];
                    $_SESSION['login_time'] = date('Y-m-d H:i:s');
                    
                    return [
                        'success' => true,
                        'user' => [
                            'id' => $user['Id_Usuario'],
                            'nombre' => $user['Nombre'],
                            'rol_nombre' => $user['rol_nombre']
                        ],
                        'message' => 'Login exitoso'
                    ];
                }
                
                // Si no hay password en BD, verificar contraseñas hardcodeadas (solo para compatibilidad)
                if (empty($user['Password'])) {
                    $valid_passwords = [
                        'admin' => 'rotario2025',
                        'coordinador' => 'becas123',
                        'usuario' => 'usuario123'
                    ];
                    
                    if (isset($valid_passwords[$username]) && $valid_passwords[$username] === $password) {
                        $this->logActivity($user['Id_Usuario'], 'Inicio de sesión exitoso (sin hash)');
                        
                        $_SESSION['user_id'] = $user['Id_Usuario'];
                        $_SESSION['username'] = $user['Nombre'];
                        $_SESSION['role'] = $user['rol_nombre'];
                        $_SESSION['login_time'] = date('Y-m-d H:i:s');
                        
                        return [
                            'success' => true,
                            'user' => [
                                'id' => $user['Id_Usuario'],
                                'nombre' => $user['Nombre'],
                                'rol_nombre' => $user['rol_nombre']
                            ],
                            'message' => 'Login exitoso',
                            'warning' => 'Advertencia: Este usuario debe actualizar su contraseña'
                        ];
                    }
                }
            }
            
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
            
        } catch (PDOException $e) {
            error_log("Error SQL en login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error en la base de datos: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Error general en login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error en el sistema: ' . $e->getMessage()
            ];
        }
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'Cierre de sesión');
        }
        
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Sesión cerrada correctamente'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'login_time' => $_SESSION['login_time']
            ];
        }
        return null;
    }
    
    private function logActivity($userId, $activity) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
            
            $stmt = $this->db->prepare("
                INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha, Direccion_IP) 
                VALUES (:user_id, :activity, :fecha, :ip)
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'activity' => $activity,
                'fecha' => date('Y-m-d'),
                'ip' => $ip
            ]);
        } catch (Exception $e) {
            error_log("Error registrando actividad: " . $e->getMessage());
        }
    }
    
    public function setupInitialData() {
        try {
            $mensajes = [];
            
            // 1. Verificar y crear tabla Rol
            $stmt = $this->db->query("SHOW TABLES LIKE 'Rol'");
            if ($stmt->rowCount() == 0) {
                $this->db->exec("
                    CREATE TABLE Rol (
                        Id_Rol INT AUTO_INCREMENT PRIMARY KEY,
                        Nombre VARCHAR(50) NOT NULL UNIQUE,
                        Descripcion TEXT,
                        Fecha_Creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $mensajes[] = "✓ Tabla Rol creada";
            }
            
            // 2. Verificar y crear tabla Usuario
            $stmt = $this->db->query("SHOW TABLES LIKE 'Usuario'");
            if ($stmt->rowCount() == 0) {
                $this->db->exec("
                    CREATE TABLE Usuario (
                        Id_Usuario INT AUTO_INCREMENT PRIMARY KEY,
                        Id_Rol INT NOT NULL,
                        Nombre VARCHAR(100) NOT NULL UNIQUE,
                        Password VARCHAR(255),
                        Email VARCHAR(100),
                        Telefono VARCHAR(20),
                        Estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
                        Fecha_Creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        Ultimo_Acceso TIMESTAMP NULL,
                        FOREIGN KEY (Id_Rol) REFERENCES Rol(Id_Rol)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $mensajes[] = "✓ Tabla Usuario creada";
            }
            
            // 3. Verificar y crear tabla Bitacora
            $stmt = $this->db->query("SHOW TABLES LIKE 'Bitacora'");
            if ($stmt->rowCount() == 0) {
                $this->db->exec("
                    CREATE TABLE Bitacora (
                        Id_Bitacora INT AUTO_INCREMENT PRIMARY KEY,
                        Id_Usuario INT,
                        Actividades TEXT NOT NULL,
                        Fecha DATE NOT NULL,
                        Hora TIME DEFAULT NULL,
                        Direccion_IP VARCHAR(45),
                        FOREIGN KEY (Id_Usuario) REFERENCES Usuario(Id_Usuario)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $mensajes[] = "✓ Tabla Bitacora creada";
            }
            
            // 4. Verificar si ya existen roles
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM Rol");
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $roles = [
                    ['Administrador', 'Acceso total al sistema'],
                    ['Coordinador', 'Gestión de becas y seguimiento'], 
                    ['Usuario', 'Consulta y registro básico']
                ];
                
                $stmt = $this->db->prepare("INSERT INTO Rol (Nombre, Descripcion) VALUES (?, ?)");
                foreach ($roles as $role) {
                    $stmt->execute($role);
                }
                $mensajes[] = "✓ Roles creados (Administrador, Coordinador, Usuario)";
            }
            
            // 5. Verificar si ya existen usuarios
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM Usuario");
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                // Generar hashes para las contraseñas
                $usuarios = [
                    [1, 'admin', password_hash('rotario2025', PASSWORD_DEFAULT), 'admin@rotario.org'],
                    [2, 'coordinador', password_hash('becas123', PASSWORD_DEFAULT), 'coordinador@rotario.org'],
                    [3, 'usuario', password_hash('usuario123', PASSWORD_DEFAULT), 'usuario@rotario.org']
                ];
                
                $stmt = $this->db->prepare("
                    INSERT INTO Usuario (Id_Rol, Nombre, Password, Email, Estado) 
                    VALUES (?, ?, ?, ?, 'Activo')
                ");
                
                foreach ($usuarios as $usuario) {
                    $stmt->execute($usuario);
                }
                $mensajes[] = "✓ Usuarios creados:";
                $mensajes[] = "  • admin / rotario2025 (Administrador)";
                $mensajes[] = "  • coordinador / becas123 (Coordinador)";
                $mensajes[] = "  • usuario / usuario123 (Usuario)";
            } else {
                $mensajes[] = "⚠ Los usuarios ya existen en la base de datos";
            }
            
            return [
                'success' => true, 
                'message' => implode("\n", $mensajes)
            ];
            
        } catch (PDOException $e) {
            error_log("Error en setup: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Error configurando datos: ' . $e->getMessage()
            ];
        }
    }
}

// Manejo de solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $auth = new AuthSystem();
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'login':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Usuario y contraseña son requeridos'
                    ]);
                    exit;
                }
                
                $result = $auth->login($username, $password);
                echo json_encode($result);
                break;
                
            case 'logout':
                $result = $auth->logout();
                echo json_encode($result);
                break;
                
            case 'setup':
                $result = $auth->setupInitialData();
                echo json_encode($result);
                break;
                
            case 'check_session':
                if ($auth->isLoggedIn()) {
                    echo json_encode([
                        'success' => true,
                        'logged_in' => true,
                        'user' => $auth->getCurrentUser()
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'logged_in' => false
                    ]);
                }
                break;
                
            default:
                echo json_encode([
                    'success' => false, 
                    'message' => 'Acción no válida: ' . $action
                ]);
        }
        
    } catch (Exception $e) {
        error_log("Error en auth.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }
    
    exit;
}
?>