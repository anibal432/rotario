<?php
// content_loader.php - Carga contenido dinámico desde la base de datos

require_once 'config.php';

class ContentLoader {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Programas de becas activos
    public function getProgramasBecasActivos() {
        $stmt = $this->pdo->prepare("SELECT * FROM Programas_Becas WHERE Activo = 1 ORDER BY Orden ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Pasos de aplicación activos
    public function getPasosAplicacionActivos() {
        $stmt = $this->pdo->prepare("SELECT * FROM Pasos_Aplicacion WHERE Activo = 1 ORDER BY Numero_Paso ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Requisitos de documentos activos
    public function getRequisitosActivos() {
        $stmt = $this->pdo->prepare("SELECT * FROM Requisitos_Documentos WHERE Activo = 1 ORDER BY Orden ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Eventos próximos
    public function getEventosProximos($limite = 3) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM Eventos 
            WHERE Activo = 1 AND Estado = 'Próximo' 
            ORDER BY Fecha_Evento ASC 
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Testimonios aprobados
    public function getTestimoniosAprobados($limite = 3) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM Testimonios 
            WHERE Activo = 1 AND Aprobado = 1 
            ORDER BY Orden ASC, Fecha_Creacion DESC 
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Información de sección específica
    public function getSeccionWeb($nombre) {
        $stmt = $this->pdo->prepare("SELECT * FROM Secciones_Web WHERE Nombre_Seccion = :nombre AND Activo = 1");
        $stmt->execute(['nombre' => $nombre]);
        return $stmt->fetch();
    }
    
    // Información de contacto
    public function getInformacionContacto($tipo = null) {
        $sql = "SELECT * FROM Informacion_Contacto WHERE Activo = 1";
        if ($tipo) {
            $sql .= " AND Tipo = :tipo";
        }
        $sql .= " ORDER BY Orden ASC";
        
        $stmt = $this->pdo->prepare($sql);
        if ($tipo) {
            $stmt->execute(['tipo' => $tipo]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
}

// Inicializar el cargador de contenido
$contentLoader = new ContentLoader();

// Cargar contenido para el sitio web
try {
    $heroSection = $contentLoader->getSeccionWeb('hero');
    $misionSection = $contentLoader->getSeccionWeb('mision');
    $programasBecas = $contentLoader->getProgramasBecasActivos();
    $pasosAplicacion = $contentLoader->getPasosAplicacionActivos();
    $requisitosDocumentos = $contentLoader->getRequisitosActivos();
    $eventosProximos = $contentLoader->getEventosProximos(3);
    $testimonios = $contentLoader->getTestimoniosAprobados(3);
    $infoContacto = $contentLoader->getInformacionContacto();
} catch (Exception $e) {
    error_log("Error cargando contenido: " . $e->getMessage());
    // Valores por defecto en caso de error
    $programasBecas = [];
    $pasosAplicacion = [];
    $requisitosDocumentos = [];
    $eventosProximos = [];
    $testimonios = [];
    $infoContacto = [];
}
?>