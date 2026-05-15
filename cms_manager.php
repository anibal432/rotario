<?php
// cms_manager.php - Sistema de Gestión de Contenidos

require_once 'config.php';
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

class CMSManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // ============================================
    // GESTIÓN DE PROGRAMAS DE BECAS
    // ============================================
    
    public function getProgramasBecas($activo = null) {
        $sql = "SELECT * FROM Programas_Becas";
        if ($activo !== null) {
            $sql .= " WHERE Activo = :activo";
        }
        $sql .= " ORDER BY Orden ASC";
        
        $stmt = $this->pdo->prepare($sql);
        if ($activo !== null) {
            $stmt->execute(['activo' => $activo]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    public function crearProgramaBeca($datos) {
        $sql = "INSERT INTO Programas_Becas (Titulo, Descripcion, Cobertura, Duracion, Imagen_URL, Orden, Activo) 
                VALUES (:titulo, :descripcion, :cobertura, :duracion, :imagen_url, :orden, :activo)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'cobertura' => $datos['cobertura'],
            'duracion' => $datos['duracion'],
            'imagen_url' => $datos['imagen_url'] ?? null,
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function actualizarProgramaBeca($id, $datos) {
        $sql = "UPDATE Programas_Becas 
                SET Titulo = :titulo, Descripcion = :descripcion, Cobertura = :cobertura, 
                    Duracion = :duracion, Imagen_URL = :imagen_url, Orden = :orden, Activo = :activo
                WHERE Id_Programa = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'cobertura' => $datos['cobertura'],
            'duracion' => $datos['duracion'],
            'imagen_url' => $datos['imagen_url'] ?? null,
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function eliminarProgramaBeca($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Programas_Becas WHERE Id_Programa = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    // ============================================
    // GESTIÓN DE PASOS DE APLICACIÓN
    // ============================================
    
    public function getPasosAplicacion($activo = null) {
        $sql = "SELECT * FROM Pasos_Aplicacion";
        if ($activo !== null) {
            $sql .= " WHERE Activo = :activo";
        }
        $sql .= " ORDER BY Numero_Paso ASC";
        
        $stmt = $this->pdo->prepare($sql);
        if ($activo !== null) {
            $stmt->execute(['activo' => $activo]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    public function crearPasoAplicacion($datos) {
        $sql = "INSERT INTO Pasos_Aplicacion (Numero_Paso, Titulo, Descripcion, Icono, Activo) 
                VALUES (:numero, :titulo, :descripcion, :icono, :activo)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'numero' => $datos['numero_paso'],
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'icono' => $datos['icono'] ?? 'fas fa-check',
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function actualizarPasoAplicacion($id, $datos) {
        $sql = "UPDATE Pasos_Aplicacion 
                SET Numero_Paso = :numero, Titulo = :titulo, Descripcion = :descripcion, 
                    Icono = :icono, Activo = :activo
                WHERE Id_Paso = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'numero' => $datos['numero_paso'],
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'icono' => $datos['icono'] ?? 'fas fa-check',
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function eliminarPasoAplicacion($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Pasos_Aplicacion WHERE Id_Paso = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    // ============================================
    // GESTIÓN DE REQUISITOS DE DOCUMENTOS
    // ============================================
    
    public function getRequisitosDocumentos($activo = null) {
        $sql = "SELECT * FROM Requisitos_Documentos";
        if ($activo !== null) {
            $sql .= " WHERE Activo = :activo";
        }
        $sql .= " ORDER BY Orden ASC";
        
        $stmt = $this->pdo->prepare($sql);
        if ($activo !== null) {
            $stmt->execute(['activo' => $activo]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    public function crearRequisitoDocumento($datos) {
        $sql = "INSERT INTO Requisitos_Documentos (Nombre_Documento, Obligatorio, Orden, Activo) 
                VALUES (:nombre, :obligatorio, :orden, :activo)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'nombre' => $datos['nombre_documento'],
            'obligatorio' => $datos['obligatorio'] ?? 1,
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function actualizarRequisitoDocumento($id, $datos) {
        $sql = "UPDATE Requisitos_Documentos 
                SET Nombre_Documento = :nombre, Obligatorio = :obligatorio, 
                    Orden = :orden, Activo = :activo
                WHERE Id_Requisito = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'nombre' => $datos['nombre_documento'],
            'obligatorio' => $datos['obligatorio'] ?? 1,
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function eliminarRequisitoDocumento($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Requisitos_Documentos WHERE Id_Requisito = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    // ============================================
    // GESTIÓN DE EVENTOS
    // ============================================
    
    public function getEventos($activo = null, $estado = null) {
        $sql = "SELECT * FROM Eventos WHERE 1=1";
        $params = [];
        
        if ($activo !== null) {
            $sql .= " AND Activo = :activo";
            $params['activo'] = $activo;
        }
        
        if ($estado !== null) {
            $sql .= " AND Estado = :estado";
            $params['estado'] = $estado;
        }
        
        $sql .= " ORDER BY Fecha_Evento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function crearEvento($datos) {
        $sql = "INSERT INTO Eventos (Titulo, Descripcion, Fecha_Evento, Hora_Inicio, Hora_Fin, 
                Ubicacion, Imagen_URL, Estado, Destacado, Activo) 
                VALUES (:titulo, :descripcion, :fecha, :hora_inicio, :hora_fin, 
                :ubicacion, :imagen_url, :estado, :destacado, :activo)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'fecha' => $datos['fecha_evento'],
            'hora_inicio' => $datos['hora_inicio'] ?? null,
            'hora_fin' => $datos['hora_fin'] ?? null,
            'ubicacion' => $datos['ubicacion'] ?? null,
            'imagen_url' => $datos['imagen_url'] ?? null,
            'estado' => $datos['estado'] ?? 'Próximo',
            'destacado' => $datos['destacado'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function actualizarEvento($id, $datos) {
        $sql = "UPDATE Eventos 
                SET Titulo = :titulo, Descripcion = :descripcion, Fecha_Evento = :fecha, 
                    Hora_Inicio = :hora_inicio, Hora_Fin = :hora_fin, Ubicacion = :ubicacion, 
                    Imagen_URL = :imagen_url, Estado = :estado, Destacado = :destacado, Activo = :activo
                WHERE Id_Evento = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'fecha' => $datos['fecha_evento'],
            'hora_inicio' => $datos['hora_inicio'] ?? null,
            'hora_fin' => $datos['hora_fin'] ?? null,
            'ubicacion' => $datos['ubicacion'] ?? null,
            'imagen_url' => $datos['imagen_url'] ?? null,
            'estado' => $datos['estado'] ?? 'Próximo',
            'destacado' => $datos['destacado'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function eliminarEvento($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Eventos WHERE Id_Evento = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    // ============================================
    // GESTIÓN DE TESTIMONIOS
    // ============================================
    
    public function getTestimonios($aprobado = null, $activo = null) {
        $sql = "SELECT * FROM Testimonios WHERE 1=1";
        $params = [];
        
        if ($aprobado !== null) {
            $sql .= " AND Aprobado = :aprobado";
            $params['aprobado'] = $aprobado;
        }
        
        if ($activo !== null) {
            $sql .= " AND Activo = :activo";
            $params['activo'] = $activo;
        }
        
        $sql .= " ORDER BY Orden ASC, Fecha_Creacion DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function crearTestimonio($datos) {
        // Generar iniciales automáticamente si no se proporcionan
        $iniciales = $datos['iniciales'] ?? $this->generarIniciales($datos['nombre_completo']);
        
        $sql = "INSERT INTO Testimonios (Nombre_Completo, Profesion, Testimonio, Foto_URL, 
                Iniciales, Año_Beca, Aprobado, Orden, Activo) 
                VALUES (:nombre, :profesion, :testimonio, :foto_url, :iniciales, 
                :anio_beca, :aprobado, :orden, :activo)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'nombre' => $datos['nombre_completo'],
            'profesion' => $datos['profesion'] ?? null,
            'testimonio' => $datos['testimonio'],
            'foto_url' => $datos['foto_url'] ?? null,
            'iniciales' => $iniciales,
            'anio_beca' => $datos['anio_beca'] ?? null,
            'aprobado' => $datos['aprobado'] ?? 0,
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function actualizarTestimonio($id, $datos) {
        $iniciales = $datos['iniciales'] ?? $this->generarIniciales($datos['nombre_completo']);
        
        $sql = "UPDATE Testimonios 
                SET Nombre_Completo = :nombre, Profesion = :profesion, Testimonio = :testimonio, 
                    Foto_URL = :foto_url, Iniciales = :iniciales, Año_Beca = :anio_beca, 
                    Aprobado = :aprobado, Orden = :orden, Activo = :activo
                WHERE Id_Testimonio = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'nombre' => $datos['nombre_completo'],
            'profesion' => $datos['profesion'] ?? null,
            'testimonio' => $datos['testimonio'],
            'foto_url' => $datos['foto_url'] ?? null,
            'iniciales' => $iniciales,
            'anio_beca' => $datos['anio_beca'] ?? null,
            'aprobado' => $datos['aprobado'] ?? 0,
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    public function aprobarTestimonio($id) {
        $stmt = $this->pdo->prepare("UPDATE Testimonios SET Aprobado = 1 WHERE Id_Testimonio = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    public function eliminarTestimonio($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Testimonios WHERE Id_Testimonio = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    private function generarIniciales($nombreCompleto) {
        $palabras = explode(' ', $nombreCompleto);
        $iniciales = '';
        foreach ($palabras as $palabra) {
            if (!empty($palabra)) {
                $iniciales .= strtoupper(substr($palabra, 0, 1));
                if (strlen($iniciales) >= 2) break;
            }
        }
        return substr($iniciales, 0, 3);
    }
    
    // ============================================
    // GESTIÓN DE SECCIONES WEB
    // ============================================
    
    public function getSeccionWeb($nombre) {
        $stmt = $this->pdo->prepare("SELECT * FROM Secciones_Web WHERE Nombre_Seccion = :nombre");
        $stmt->execute(['nombre' => $nombre]);
        return $stmt->fetch();
    }
    
    public function actualizarSeccionWeb($nombre, $datos) {
        $sql = "UPDATE Secciones_Web 
                SET Titulo = :titulo, Subtitulo = :subtitulo, Contenido = :contenido, 
                    Imagen_URL = :imagen_url, Activo = :activo
                WHERE Nombre_Seccion = :nombre";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'nombre' => $nombre,
            'titulo' => $datos['titulo'] ?? null,
            'subtitulo' => $datos['subtitulo'] ?? null,
            'contenido' => $datos['contenido'] ?? null,
            'imagen_url' => $datos['imagen_url'] ?? null,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
    
    // ============================================
    // GESTIÓN DE INFORMACIÓN DE CONTACTO
    // ============================================
    
    public function getInformacionContacto($tipo = null) {
        $sql = "SELECT * FROM Informacion_Contacto";
        if ($tipo !== null) {
            $sql .= " WHERE Tipo = :tipo";
        }
        $sql .= " ORDER BY Orden ASC";
        
        $stmt = $this->pdo->prepare($sql);
        if ($tipo !== null) {
            $stmt->execute(['tipo' => $tipo]);
        } else {
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    public function actualizarInformacionContacto($id, $datos) {
        $sql = "UPDATE Informacion_Contacto 
                SET Tipo = :tipo, Icono = :icono, Titulo = :titulo, 
                    Valor = :valor, Orden = :orden, Activo = :activo
                WHERE Id_Contacto = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'tipo' => $datos['tipo'],
            'icono' => $datos['icono'] ?? null,
            'titulo' => $datos['titulo'] ?? null,
            'valor' => $datos['valor'],
            'orden' => $datos['orden'] ?? 0,
            'activo' => $datos['activo'] ?? 1
        ]);
    }
}

// ============================================
// API PARA MANEJO DE SOLICITUDES
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $cms = new CMSManager();
    $action = $_POST['action'] ?? '';
    $module = $_POST['module'] ?? '';
    
    try {
        switch ($module) {
            case 'programas':
                switch ($action) {
                    case 'get':
                        $result = $cms->getProgramasBecas();
                        echo json_encode(['success' => true, 'data' => $result]);
                        break;
                    
                    case 'create':
                        $result = $cms->crearProgramaBeca($_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Programa creado' : 'Error al crear']);
                        break;
                    
                    case 'update':
                        $result = $cms->actualizarProgramaBeca($_POST['id'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Programa actualizado' : 'Error al actualizar']);
                        break;
                    
                    case 'delete':
                        $result = $cms->eliminarProgramaBeca($_POST['id']);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Programa eliminado' : 'Error al eliminar']);
                        break;
                }
                break;
            
            case 'pasos':
                switch ($action) {
                    case 'get':
                        $result = $cms->getPasosAplicacion();
                        echo json_encode(['success' => true, 'data' => $result]);
                        break;
                    
                    case 'create':
                        $result = $cms->crearPasoAplicacion($_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Paso creado' : 'Error al crear']);
                        break;
                    
                    case 'update':
                        $result = $cms->actualizarPasoAplicacion($_POST['id'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Paso actualizado' : 'Error al actualizar']);
                        break;
                    
                    case 'delete':
                        $result = $cms->eliminarPasoAplicacion($_POST['id']);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Paso eliminado' : 'Error al eliminar']);
                        break;
                }
                break;
            
            case 'requisitos':
                switch ($action) {
                    case 'get':
                        $result = $cms->getRequisitosDocumentos();
                        echo json_encode(['success' => true, 'data' => $result]);
                        break;
                    
                    case 'create':
                        $result = $cms->crearRequisitoDocumento($_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Requisito creado' : 'Error al crear']);
                        break;
                    
                    case 'update':
                        $result = $cms->actualizarRequisitoDocumento($_POST['id'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Requisito actualizado' : 'Error al actualizar']);
                        break;
                    
                    case 'delete':
                        $result = $cms->eliminarRequisitoDocumento($_POST['id']);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Requisito eliminado' : 'Error al eliminar']);
                        break;
                }
                break;
            
            case 'eventos':
                switch ($action) {
                    case 'get':
                        $result = $cms->getEventos();
                        echo json_encode(['success' => true, 'data' => $result]);
                        break;
                    
                    case 'create':
                        $result = $cms->crearEvento($_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Evento creado' : 'Error al crear']);
                        break;
                    
                    case 'update':
                        $result = $cms->actualizarEvento($_POST['id'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Evento actualizado' : 'Error al actualizar']);
                        break;
                    
                    case 'delete':
                        $result = $cms->eliminarEvento($_POST['id']);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Evento eliminado' : 'Error al eliminar']);
                        break;
                }
                break;
            
            case 'testimonios':
                switch ($action) {
                    case 'get':
                        $result = $cms->getTestimonios();
                        echo json_encode(['success' => true, 'data' => $result]);
                        break;
                    
                    case 'create':
                        $result = $cms->crearTestimonio($_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Testimonio creado' : 'Error al crear']);
                        break;
                    
                    case 'update':
                        $result = $cms->actualizarTestimonio($_POST['id'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Testimonio actualizado' : 'Error al actualizar']);
                        break;
                    
                    case 'aprobar':
                        $result = $cms->aprobarTestimonio($_POST['id']);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Testimonio aprobado' : 'Error al aprobar']);
                        break;
                    
                    case 'delete':
                        $result = $cms->eliminarTestimonio($_POST['id']);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Testimonio eliminado' : 'Error al eliminar']);
                        break;
                }
                break;
            
            case 'secciones':
                switch ($action) {
                    case 'update':
                        $result = $cms->actualizarSeccionWeb($_POST['nombre_seccion'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Sección actualizada' : 'Error al actualizar']);
                        break;
                }
                break;
            
            case 'contacto':
                switch ($action) {
                    case 'get':
                        $result = $cms->getInformacionContacto();
                        echo json_encode(['success' => true, 'data' => $result]);
                        break;
                    
                    case 'update':
                        $result = $cms->actualizarInformacionContacto($_POST['id'], $_POST);
                        echo json_encode(['success' => $result, 'message' => $result ? 'Información actualizada' : 'Error al actualizar']);
                        break;
                }
                break;
            
            default:
                echo json_encode(['success' => false, 'message' => 'Módulo no válido']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>