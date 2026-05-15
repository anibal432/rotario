<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gestionar_reglamento.php');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo->beginTransaction();
    
    if ($action === 'agregar') {
        // ====================================
        // AGREGAR NUEVA CLÁUSULA
        // ====================================
        
        $numero_clausula = intval($_POST['numero_clausula']);
        $titulo_clausula = !empty($_POST['titulo_clausula']) ? trim($_POST['titulo_clausula']) : null;
        $contenido_clausula = trim($_POST['contenido_clausula']);
        $tipo_clausula = $_POST['tipo_clausula'];
        $estado = $_POST['estado'] ?? 'Activo';
        $tiene_subcausulas = isset($_POST['tiene_subcausulas']) ? 1 : 0;
        
        // Validaciones
        if (empty($contenido_clausula)) {
            throw new Exception('El contenido de la cláusula es obligatorio');
        }
        
        // Verificar que el número no esté duplicado
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Reglamento_Becas WHERE Numero_Clausula = ?");
        $stmt_check->execute([$numero_clausula]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception('Ya existe una cláusula con ese número. Por favor elige otro número.');
        }
        
        // Obtener el próximo orden
        $stmt_orden = $pdo->query("SELECT COALESCE(MAX(Orden), 0) + 1 as ProximoOrden FROM Reglamento_Becas");
        $orden = $stmt_orden->fetch(PDO::FETCH_ASSOC)['ProximoOrden'];
        
        // Insertar la cláusula
        $sql = "INSERT INTO Reglamento_Becas (
                    Numero_Clausula, 
                    Titulo_Clausula, 
                    Contenido_Clausula, 
                    Tiene_Subcausulas, 
                    Tipo_Clausula, 
                    Estado, 
                    Orden
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $numero_clausula,
            $titulo_clausula,
            $contenido_clausula,
            $tiene_subcausulas,
            $tipo_clausula,
            $estado,
            $orden
        ]);
        
        $id_clausula = $pdo->lastInsertId();
        
        // Si tiene subcláusulas, insertarlas
        if ($tiene_subcausulas && isset($_POST['subcausulas']) && is_array($_POST['subcausulas'])) {
            foreach ($_POST['subcausulas'] as $index => $sub) {
                if (!empty($sub['numero']) && !empty($sub['contenido'])) {
                    $sql_sub = "INSERT INTO Sub_Clausulas_Reglamento (
                                    Id_Clausula, 
                                    Numero_Sub_Clausula, 
                                    Contenido, 
                                    Orden
                                ) VALUES (?, ?, ?, ?)";
                    
                    $stmt_sub = $pdo->prepare($sql_sub);
                    $stmt_sub->execute([
                        $id_clausula,
                        trim($sub['numero']),
                        trim($sub['contenido']),
                        $sub['orden'] ?? ($index + 1)
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Cláusula agregada exitosamente";
        header('Location: gestionar_reglamento.php');
        exit;
        
    } elseif ($action === 'editar') {
        // ====================================
        // EDITAR CLÁUSULA EXISTENTE
        // ====================================
        
        $id_clausula = intval($_POST['id_clausula']);
        $numero_clausula = intval($_POST['numero_clausula']);
        $titulo_clausula = !empty($_POST['titulo_clausula']) ? trim($_POST['titulo_clausula']) : null;
        $contenido_clausula = trim($_POST['contenido_clausula']);
        $tipo_clausula = $_POST['tipo_clausula'];
        $estado = $_POST['estado'] ?? 'Activo';
        $tiene_subcausulas = isset($_POST['tiene_subcausulas']) ? 1 : 0;
        
        // Validaciones
        if (empty($contenido_clausula)) {
            throw new Exception('El contenido de la cláusula es obligatorio');
        }
        
        // Verificar que el número no esté duplicado (excepto para esta misma cláusula)
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Reglamento_Becas 
                                      WHERE Numero_Clausula = ? AND Id_Clausula != ?");
        $stmt_check->execute([$numero_clausula, $id_clausula]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception('Ya existe otra cláusula con ese número. Por favor elige otro número.');
        }
        
        // Actualizar la cláusula
        $sql = "UPDATE Reglamento_Becas SET
                    Numero_Clausula = ?,
                    Titulo_Clausula = ?,
                    Contenido_Clausula = ?,
                    Tiene_Subcausulas = ?,
                    Tipo_Clausula = ?,
                    Estado = ?
                WHERE Id_Clausula = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $numero_clausula,
            $titulo_clausula,
            $contenido_clausula,
            $tiene_subcausulas,
            $tipo_clausula,
            $estado,
            $id_clausula
        ]);
        
        // Eliminar subcláusulas anteriores
        $stmt_delete = $pdo->prepare("DELETE FROM Sub_Clausulas_Reglamento WHERE Id_Clausula = ?");
        $stmt_delete->execute([$id_clausula]);
        
        // Si tiene subcláusulas, insertarlas nuevamente
        if ($tiene_subcausulas && isset($_POST['subcausulas']) && is_array($_POST['subcausulas'])) {
            foreach ($_POST['subcausulas'] as $index => $sub) {
                if (!empty($sub['numero']) && !empty($sub['contenido'])) {
                    $sql_sub = "INSERT INTO Sub_Clausulas_Reglamento (
                                    Id_Clausula, 
                                    Numero_Sub_Clausula, 
                                    Contenido, 
                                    Orden
                                ) VALUES (?, ?, ?, ?)";
                    
                    $stmt_sub = $pdo->prepare($sql_sub);
                    $stmt_sub->execute([
                        $id_clausula,
                        trim($sub['numero']),
                        trim($sub['contenido']),
                        $sub['orden'] ?? ($index + 1)
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Cláusula actualizada exitosamente";
        header('Location: gestionar_reglamento.php');
        exit;
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = $e->getMessage();
    
    // Redirigir según la acción
    if ($action === 'agregar') {
        header('Location: agregar_clausula.php');
    } elseif ($action === 'editar' && isset($_POST['id_clausula'])) {
        header('Location: editar_clausula.php?id=' . $_POST['id_clausula']);
    } else {
        header('Location: gestionar_reglamento.php');
    }
    exit;
}
?>