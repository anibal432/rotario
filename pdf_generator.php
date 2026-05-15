<?php
require_once 'config.php';

use TCPDF;

class PDFGenerator {
    
    public function generarPDFConfirmacion($datos) {
        try {
            // Crear instancia de TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configuración del documento
            $pdf->SetCreator(EMAIL_FROM_NAME);
            $pdf->SetAuthor(EMAIL_FROM_NAME);
            $pdf->SetTitle('Confirmación de Registro - ' . $datos['numero_corredor']);
            $pdf->SetSubject('Confirmación de Registro Carrera 21K');
            
            // Configurar márgenes
            $pdf->SetMargins(15, 20, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Agregar página
            $pdf->AddPage();
            
            // Generar contenido del PDF
            $html = $this->generarHTMLPDF($datos);
            
            // Escribir HTML en el PDF
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Generar nombre de archivo
            $nombreArchivo = 'confirmacion_' . $datos['numero_corredor'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $rutaCompleta = CONFIRMACIONES_DIR . $nombreArchivo;
            
            // Guardar PDF
            $pdf->Output($rutaCompleta, 'F');
            
            return $rutaCompleta;
            
        } catch (Exception $e) {
            error_log("Error generando PDF: " . $e->getMessage());
            throw new Exception("Error al generar el comprobante PDF: " . $e->getMessage());
        }
    }
    
    private function generarHTMLPDF($datos) {
        $logoBase64 = $this->obtenerLogoBase64();
        
        $html = '
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding: 20px 0;
                border-bottom: 3px solid #1e40af;
            }
            .logo {
                width: 80px;
                height: 80px;
                margin-bottom: 15px;
            }
            .titulo {
                font-size: 24px;
                font-weight: bold;
                color: #1e40af;
                margin: 10px 0;
            }
            .subtitulo {
                font-size: 16px;
                color: #666;
                margin-bottom: 5px;
            }
            .numero-corredor {
                background: #1e40af;
                color: white;
                text-align: center;
                padding: 25px;
                margin: 30px 0;
                border-radius: 10px;
            }
            .numero-corredor h1 {
                font-size: 48px;
                margin: 0;
                font-weight: bold;
            }
            .numero-corredor p {
                font-size: 18px;
                margin: 10px 0 0 0;
            }
            .info-section {
                margin: 25px 0;
                padding: 20px;
                background: #f8f9fa;
                border-left: 5px solid #1e40af;
            }
            .info-title {
                font-size: 16px;
                font-weight: bold;
                color: #1e40af;
                margin-bottom: 15px;
            }
            .info-row {
                display: table;
                width: 100%;
                margin-bottom: 8px;
            }
            .info-label {
                display: table-cell;
                width: 40%;
                font-weight: bold;
                color: #1e40af;
                padding-right: 10px;
            }
            .info-value {
                display: table-cell;
                width: 60%;
            }
            .instrucciones {
                background: #fff3cd;
                border: 2px solid #ffc107;
                padding: 20px;
                margin: 30px 0;
                border-radius: 8px;
            }
            .instrucciones h3 {
                color: #856404;
                margin-top: 0;
                font-size: 16px;
            }
            .instrucciones ul {
                margin: 15px 0;
                padding-left: 20px;
            }
            .instrucciones li {
                margin-bottom: 8px;
            }
            .footer {
                margin-top: 40px;
                padding: 20px 0;
                border-top: 2px solid #e9ecef;
                text-align: center;
                color: #666;
                font-size: 10px;
            }
            .motivacion {
                background: #d4edda;
                border: 2px solid #28a745;
                padding: 20px;
                margin: 25px 0;
                border-radius: 8px;
                text-align: center;
            }
            .motivacion h3 {
                color: #155724;
                margin-top: 0;
            }
            .motivacion p {
                color: #155724;
                font-style: italic;
                font-size: 14px;
                line-height: 1.6;
            }
            .qr-section {
                text-align: center;
                margin: 30px 0;
                padding: 20px;
                border: 2px dashed #1e40af;
            }
        </style>
        
        <div class="header">
            ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo" alt="Logo">' : '') . '
            <div class="titulo">CLUB ROTARIO COATEPEQUE COLOMBA</div>
            <div class="subtitulo">' . NOMBRE_EVENTO . '</div>
            <div class="subtitulo">Comprobante de Registro</div>
        </div>
        
        <div class="numero-corredor">
            <p>Tu Número de Corredor</p>
            <h1>' . $datos['numero_corredor'] . '</h1>
        </div>
        
        <div class="info-section">
            <div class="info-title">INFORMACIÓN DEL PARTICIPANTE</div>
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value">' . htmlspecialchars($datos['nombre']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Edad:</div>
                <div class="info-value">' . $datos['edad'] . ' años</div>
            </div>
            <div class="info-row">
                <div class="info-label">Categoría:</div>
                <div class="info-value">' . htmlspecialchars($datos['categoria']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Género:</div>
                <div class="info-value">' . htmlspecialchars($datos['genero']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Talla Playera:</div>
                <div class="info-value">' . htmlspecialchars($datos['talla_playera']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">' . htmlspecialchars($datos['email']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Teléfono:</div>
                <div class="info-value">' . htmlspecialchars($datos['telefono']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">DPI:</div>
                <div class="info-value">' . htmlspecialchars($datos['dpi']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Registro:</div>
                <div class="info-value">' . $datos['fecha_registro_formateada'] . '</div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-title">INFORMACIÓN DEL EVENTO</div>
            <div class="info-row">
                <div class="info-label">Fecha:</div>
                <div class="info-value">' . $datos['fecha_carrera'] . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Hora de Concentración:</div>
                <div class="info-value">6:30 AM</div>
            </div>
            <div class="info-row">
                <div class="info-label">Hora de Salida:</div>
                <div class="info-value">' . $datos['hora'] . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Punto de Salida:</div>
                <div class="info-value">' . htmlspecialchars($datos['lugar']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Distancia:</div>
                <div class="info-value">21 Kilómetros</div>
            </div>
        </div>
        
        <div class="motivacion">
            <h3>¡GRACIAS POR SER PARTE DEL CAMBIO!</h3>
            <p>Tu participación en esta carrera no solo representa un desafío personal de superación y bienestar, sino también un acto de solidaridad hacia la educación en nuestra comunidad. Cada paso que des el día de la carrera contribuirá directamente a las becas estudiantiles que otorga nuestro Club Rotario.</p>
            <p><strong>¡Prepárate, entrena con dedicación y únete a nosotros para correr por una causa noble!</strong></p>
        </div>
        
        <div class="instrucciones">
            <h3>INSTRUCCIONES IMPORTANTES</h3>
            <ul>
                <li><strong>Llegada:</strong> Preséntate 30 minutos antes del inicio con tu DPI y este comprobante</li>
                <li><strong>Registro el día del evento:</strong> Dirígete al área de registro para recoger tu playera y número</li>
                <li><strong>Hidratación:</strong> Habrá estaciones de hidratación cada 3-4 km en la ruta</li>
                <li><strong>Asistencia médica:</strong> Personal médico estará disponible durante todo el evento</li>
                <li><strong>Meta:</strong> La meta estará ubicada en el mismo punto de salida</li>
                <li><strong>Premiación:</strong> Se realizará aproximadamente 2 horas después del inicio</li>
            </ul>
        </div>
        
        <div class="qr-section">
            <p><strong>MANTÉN ESTE COMPROBANTE SEGURO</strong></p>
            <p>Preséntalo el día del evento junto con tu documento de identidad</p>
        </div>
        
        <div style="page-break-before: always;">
            <div class="header">
                <div class="titulo">NÚMERO DE CORREDOR PARA IMPRIMIR</div>
                <div class="subtitulo">Recorta y pega en lugar visible</div>
            </div>
            
            <div style="text-align: center; margin-top: 50px;">
                <div style="border: 5px solid #1e40af; padding: 40px; display: inline-block; margin: 20px;">
                    <div style="font-size: 72px; font-weight: bold; color: #1e40af; margin-bottom: 10px;">' . $datos['numero_corredor'] . '</div>
                    <div style="font-size: 18px; color: #666;">' . htmlspecialchars($datos['nombre']) . '</div>
                    <div style="font-size: 14px; color: #666; margin-top: 5px;">' . htmlspecialchars($datos['categoria']) . '</div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 50px; color: #666;">
                <p>Recorta este número y pégalo en tu ropa o en un lugar visible durante la carrera</p>
                <p><strong>¡Te deseamos mucho éxito!</strong></p>
            </div>
        </div>
        
        <div class="footer">
            <p>Club Rotario Coatepeque Colomba | Carrera 21K "Por la Educación" | ' . date('Y') . '</p>
            <p>Para consultas: registro@rotariocoatepeque.org | Teléfono: 7775-0000</p>
            <p>Generado el ' . date('d/m/Y H:i:s') . '</p>
        </div>';
        
        return $html;
    }
    
    private function obtenerLogoBase64() {
        // Ruta al logo (ajusta según tu estructura de archivos)
        $logoPath = __DIR__ . '/assets/logo-rotario.png';
        
        if (file_exists($logoPath)) {
            $imageData = base64_encode(file_get_contents($logoPath));
            $imageType = pathinfo($logoPath, PATHINFO_EXTENSION);
            return 'data:image/' . $imageType . ';base64,' . $imageData;
        }
        
        return null;
    }
}
?>