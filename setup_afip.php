<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
<title>Setup Rápido AFIP</title>
<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
h1 { color: #007bff; }
.step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }
.success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
.error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
.btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
</style>
</head><body><div class='container'>";

echo "<h1>⚡ Setup Rápido AFIP</h1>";

$config = ConfigManager::getInstance();

// PASO 1: Verificar directorio de certificados
echo "<div class='step'>";
echo "<h3>Paso 1: Verificar directorio de certificados</h3>";

if (!is_dir('certificados')) {
    mkdir('certificados', 0755, true);
    echo "✓ Directorio 'certificados' creado<br>";
} else {
    echo "✓ Directorio 'certificados' existe<br>";
}

if (!is_dir('cache')) {
    mkdir('cache', 0755, true);
    echo "✓ Directorio 'cache' creado<br>";
} else {
    echo "✓ Directorio 'cache' existe<br>";
}
echo "</div>";

// PASO 2: Verificar certificados existentes
echo "<div class='step'>";
echo "<h3>Paso 2: Verificar certificados</h3>";

$certPath = 'certificados/moreno_homologacion.crt';
$keyPath = 'certificados/moreno_homologacion.key';

$certExiste = file_exists($certPath);
$keyExiste = file_exists($keyPath);

if ($certExiste && $keyExiste) {
    echo "<div class='success'>";
    echo "✓ Certificado encontrado: <code>{$certPath}</code><br>";
    echo "✓ Clave privada encontrada: <code>{$keyPath}</code><br>";
    
    // Verificar validez
    $cert = openssl_x509_parse(file_get_contents($certPath));
    if ($cert) {
        $diasRestantes = floor(($cert['validTo_time_t'] - time()) / 86400);
        echo "✓ Certificado válido hasta: " . date('Y-m-d', $cert['validTo_time_t']) . " ({$diasRestantes} días)<br>";
    }
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h4>⚠️ Certificados no encontrados</h4>";
    echo "<p>Necesitás los siguientes archivos en el directorio 'certificados/':</p>";
    echo "<ul>";
    echo "<li><strong>afip.crt</strong> - Certificado descargado de AFIP</li>";
    echo "<li><strong>afip.key</strong> - Clave privada generada</li>";
    echo "</ul>";
    echo "<p><strong>Pasos para obtenerlos:</strong></p>";
    echo "<ol>";
    echo "<li>Ingresar a <a href='https://auth.afip.gob.ar' target='_blank'>AFIP con Clave Fiscal</a></li>";
    echo "<li>Ir a: Administrador de Relaciones → Certificados Digitales</li>";
    echo "<li>Subir el archivo CSR (si lo tenés) o generar uno nuevo</li>";
    echo "<li>Descargar el certificado (.crt) y guardarlo como <code>certificados/afip.crt</code></li>";
    echo "<li>Copiar la clave privada (.key) como <code>certificados/afip.key</code></li>";
    echo "</ol>";
    echo "</div>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// PASO 3: Actualizar base de datos
echo "<div class='step'>";
echo "<h3>Paso 3: Configurar base de datos</h3>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Actualizar configuraciones
    $configs = [
        ['afip_habilitado', '0', 'boolean'],
        ['afip_cuit', '20355274684', 'string'],
        ['afip_certificado', $certPath, 'string'],
        ['afip_clave_privada', $keyPath, 'string'],
        ['afip_punto_venta', '1', 'int'],
        ['afip_ambiente', '2', 'int'],
        ['afip_tipo_comprobante', '11', 'int']
    ];
    
    foreach ($configs as $cfg) {
        $stmt = $db->prepare("
            INSERT INTO configuracion (clave, valor, tipo) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)
        ");
        $stmt->execute($cfg);
    }
    
    echo "✓ Configuración actualizada en la base de datos<br>";
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// PASO 4: Test de firma
echo "<div class='step'>";
echo "<h3>Paso 4: Test de firma digital</h3>";

try {
    $tra = '<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <uniqueId>' . time() . '</uniqueId>
        <generationTime>' . date('c') . '</generationTime>
        <expirationTime>' . date('c', strtotime('+1 hour')) . '</expirationTime>
    </header>
    <service>wsfe</service>
</loginTicketRequest>';
    
    $traFile = tempnam(sys_get_temp_dir(), 'tra_');
    $cmsFile = tempnam(sys_get_temp_dir(), 'cms_');
    
    file_put_contents($traFile, $tra);
    
    $resultado = openssl_pkcs7_sign(
        $traFile,
        $cmsFile,
        file_get_contents($certPath),
        file_get_contents($keyPath),
        [],
        PKCS7_BINARY | PKCS7_NOATTR
    );
    
    if ($resultado && file_exists($cmsFile) && filesize($cmsFile) > 0) {
        echo "<div class='success'>✓ Test de firma exitoso (" . filesize($cmsFile) . " bytes)</div>";
        $firmaOK = true;
    } else {
        echo "<div class='error'>✗ Error en la firma: " . openssl_error_string() . "</div>";
        $firmaOK = false;
    }
    
    @unlink($traFile);
    @unlink($cmsFile);
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Excepción: " . $e->getMessage() . "</div>";
    $firmaOK = false;
}
echo "</div>";

// PASO 5: Test de conectividad AFIP
if ($firmaOK) {
    echo "<div class='step'>";
    echo "<h3>Paso 5: Test de conectividad AFIP</h3>";
    
    try {
        require_once 'AFIPFacturacion.php';
        
        $afipConfig = [
            'cuit' => '20355274684',
            'certificado' => $certPath,
            'clave_privada' => $keyPath,
            'punto_venta' => 1,
            'ambiente' => 2
        ];
        
        echo "<p>⏳ Conectando con AFIP (ambiente Testing)...</p>";
        
        $afip = new AFIPFacturacion($afipConfig);
        $resultado = $afip->testConectividad();
        
        if ($resultado['success']) {
            echo "<div class='success'>";
            echo "<h3>✓ ¡CONEXIÓN EXITOSA!</h3>";
            echo "<p>Ambiente: <strong>{$resultado['ambiente']}</strong></p>";
            echo "<p>Token expira: <strong>{$resultado['token_expira']}</strong></p>";
            echo "</div>";
            
            // Habilitar AFIP
            $config->set('afip_habilitado', 'true', 'boolean');
            
            echo "<div class='success'>";
            echo "<h2>🎉 ¡CONFIGURACIÓN COMPLETA!</h2>";
            echo "<p>Tu sistema está listo para facturar electrónicamente con AFIP.</p>";
            echo "<p><strong>Importante:</strong></p>";
            echo "<ul>";
            echo "<li>Estás en ambiente de <strong>TESTING</strong></li>";
            echo "<li>Los comprobantes NO tienen validez fiscal</li>";
            echo "<li>Probá varias ventas antes de pasar a producción</li>";
            echo "</ul>";
            echo "<a href='Nuevaventa.php' class='btn'>🛒 Ir al Sistema de Ventas</a>";
            echo "<a href='diagnostico_afip.php' class='btn' style='background:#6c757d'>🔍 Ver Diagnóstico Completo</a>";
            echo "</div>";
            
        } else {
            echo "<div class='error'>";
            echo "<h3>✗ Error de conexión</h3>";
            echo "<p>{$resultado['message']}</p>";
            if (isset($resultado['errores'])) {
                echo "<ul>";
                foreach ($resultado['errores'] as $error) {
                    echo "<li>{$error}</li>";
                }
                echo "</ul>";
            }
            echo "<p><a href='diagnostico_afip.php' class='btn'>Ver Diagnóstico Detallado</a></p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>✗ Excepción</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    }
    
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ No se puede continuar</h3>";
    echo "<p>El test de firma falló. Verificá que los certificados sean correctos.</p>";
    echo "</div>";
}

echo "</div></body></html>";
?>