<?php
/**
 * test_correo.php
 * Script de diagnóstico para correos
 * Ejecutar: http://localhost/proyecbeca/test_correo.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnóstico de Correo Electrónico</h1>";
echo "<pre>";

// ============================================
// TEST 1: Verificar archivos PHPMailer
// ============================================
echo "\n===== TEST 1: VERIFICAR PHPMAILER =====\n";

$archivos_necesarios = [
    'PHPMailer/src/PHPMailer.php',
    'PHPMailer/src/SMTP.php',
    'PHPMailer/src/Exception.php'
];

$todos_existen = true;
foreach ($archivos_necesarios as $archivo) {
    if (file_exists(__DIR__ . '/' . $archivo)) {
        echo "✓ Encontrado: $archivo\n";
    } else {
        echo "✗ FALTA: $archivo\n";
        $todos_existen = false;
    }
}

if (!$todos_existen) {
    die("\n❌ ERROR: Faltan archivos de PHPMailer. No se puede continuar.\n");
}

// ============================================
// TEST 2: Cargar PHPMailer
// ============================================
echo "\n===== TEST 2: CARGAR PHPMAILER =====\n";

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "✓ PHPMailer cargado correctamente\n";

// ============================================
// TEST 3: Probar conexión SMTP
// ============================================
echo "\n===== TEST 3: PROBAR CONEXIÓN SMTP =====\n";

$mail = new PHPMailer(true);

try {
    // Configuración SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'cmvitalis01@gmail.com';
    $mail->Password   = 'gcly petu tsfz ppdj';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 30;
    
    // Opciones SSL
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Habilitar debug
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "$str\n";
    };
    
    // Intentar conectar
    echo "\n→ Intentando conectar a smtp.gmail.com:587...\n";
    $mail->smtpConnect();
    
    echo "\n✓✓✓ CONEXIÓN SMTP EXITOSA ✓✓✓\n";
    
    $mail->smtpClose();
    
} catch (Exception $e) {
    echo "\n❌ ERROR EN CONEXIÓN SMTP:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "\nPosibles causas:\n";
    echo "1. Contraseña de aplicación incorrecta\n";
    echo "2. Verificación en 2 pasos no activada\n";
    echo "3. Puerto 587 bloqueado por firewall\n";
    echo "4. Límite de Gmail alcanzado\n";
    die("\n");
}

// ============================================
// TEST 4: Enviar correo de prueba
// ============================================
echo "\n===== TEST 4: ENVIAR CORREO DE PRUEBA =====\n";

// IMPORTANTE: Cambia este email por el tuyo
$email_destino = 'cmvitalis01@gmail.com'; // <-- CAMBIA ESTO

echo "→ Enviando correo de prueba a: $email_destino\n\n";

$mail = new PHPMailer(true);

try {
    // Configuración SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'cmvitalis01@gmail.com';
    $mail->Password   = 'gcly petu tsfz ppdj';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 30;
    
    // Opciones SSL
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Debug activado
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "$str\n";
    };
    
    // Configuración del correo
    $mail->setFrom('cmvitalis01@gmail.com', 'Club Rotario - TEST');
    $mail->addAddress($email_destino, 'Usuario de Prueba');
    
    $mail->isHTML(true);
    $mail->Subject = 'Prueba de Correo - ' . date('H:i:s');
    $mail->Body    = '
        <html>
        <body style="font-family: Arial; padding: 20px;">
            <h1 style="color: #667eea;">✅ ¡Prueba Exitosa!</h1>
            <p>Este es un correo de prueba del sistema de becas.</p>
            <p><strong>Hora de envío:</strong> ' . date('Y-m-d H:i:s') . '</p>
            <p>Si recibes este correo, significa que el sistema está funcionando correctamente.</p>
        </body>
        </html>
    ';
    $mail->AltBody = 'Prueba exitosa - ' . date('Y-m-d H:i:s');
    
    // Enviar
    echo "\n→ Enviando correo...\n\n";
    $mail->send();
    
    echo "\n\n";
    echo "╔══════════════════════════════════════════╗\n";
    echo "║   ✅✅✅ CORREO ENVIADO EXITOSAMENTE ✅✅✅   ║\n";
    echo "╚══════════════════════════════════════════╝\n";
    echo "\n";
    echo "🎉 ¡TODO FUNCIONA CORRECTAMENTE!\n\n";
    echo "Revisa tu correo en: $email_destino\n";
    echo "(Revisa también la carpeta de spam)\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR AL ENVIAR CORREO:\n";
    echo "Mensaje: " . $mail->ErrorInfo . "\n";
    echo "Excepción: " . $e->getMessage() . "\n\n";
}

echo "\n===== FIN DEL DIAGNÓSTICO =====\n";
echo "</pre>";

echo '<br><br>';
echo '<a href="solicitud_beca.php">← Volver al formulario</a>';
?>