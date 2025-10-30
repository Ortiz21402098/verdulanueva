<?php
/**
 * GENERADOR LIMPIO DE CERTIFICADOS AFIP
 * Sin dependencias de archivos anteriores
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$cuit = '20355274684';
$razonSocial = 'MORENOHOMOLOGACION';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
<title>Generador Certificado AFIP</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    min-height: 100vh;
}
.container { 
    max-width: 1000px; 
    margin: 0 auto; 
    background: white; 
    padding: 40px; 
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
h1 { color: #2d3748; margin-bottom: 10px; font-size: 32px; }
.subtitle { color: #718096; margin-bottom: 30px; font-size: 18px; }
.badge { 
    display: inline-block; 
    background: #f0fff4; 
    color: #22543d; 
    padding: 6px 12px; 
    border-radius: 20px; 
    font-size: 14px; 
    font-weight: 600;
    margin-bottom: 20px;
}
.success { 
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white; 
    padding: 24px; 
    margin: 20px 0; 
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}
.success h2 { color: white; margin-bottom: 15px; }
.error { 
    background: #fed7d7; 
    color: #742a2a; 
    padding: 20px; 
    margin: 20px 0; 
    border-radius: 12px;
    border-left: 4px solid #fc8181;
}
.warning { 
    background: #fef5e7; 
    color: #975a16; 
    padding: 20px; 
    margin: 20px 0; 
    border-radius: 12px;
    border-left: 4px solid #ed8936;
}
.info { 
    background: #ebf8ff; 
    color: #2c5282; 
    padding: 20px; 
    margin: 20px 0; 
    border-radius: 12px;
    border-left: 4px solid #4299e1;
}
.step { 
    background: #f7fafc; 
    padding: 24px; 
    margin: 20px 0; 
    border-radius: 12px;
    border-left: 4px solid #667eea;
}
.btn { 
    display: inline-block; 
    padding: 14px 28px; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white; 
    text-decoration: none; 
    border-radius: 8px; 
    margin: 10px 5px 10px 0; 
    border: none; 
    cursor: pointer; 
    font-size: 16px;
    font-weight: 600;
    transition: transform 0.2s;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}
.btn:hover { transform: translateY(-2px); }
textarea { 
    width: 100%; 
    min-height: 200px; 
    font-family: 'Courier New', monospace; 
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    background: #2d3748;
    color: #e2e8f0;
}
.file-item {
    display: flex;
    align-items: center;
    padding: 15px;
    margin: 10px 0;
    background: #f7fafc;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
}
.file-icon { font-size: 24px; margin-right: 12px; }
ol, ul { margin-left: 20px; margin-top: 10px; }
li { margin: 8px 0; line-height: 1.6; }
</style>
</head><body><div class='container'>";

echo "<span class='badge'>🧪 AMBIENTE DE HOMOLOGACIÓN</span>";
echo "<h1>🔐 Generador de Certificado AFIP</h1>";
echo "<p class='subtitle'>Certificado limpio para facturación electrónica</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar'])) {
    
    try {
        // Crear directorio limpio
        $certDir = 'certificados_nuevo';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }
        
        echo "<div class='info'>";
        echo "<h3>⚙️ Generando Certificados</h3>";
        echo "<p>Creando par de claves criptográficas...</p>";
        echo "</div>";
        
        // Configuración del DN (Distinguished Name)
        $dn = array(
            "countryName" => "AR",
            "organizationName" => $razonSocial,
            "commonName" => $razonSocial,
            "serialNumber" => "CUIT " . $cuit
        );
        
        // Configuración de la clave privada
        $config = array(
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        
        // PASO 1: Generar clave privada
        echo "<p>1️⃣ Generando clave privada RSA 2048 bits...</p>";
        $privateKey = openssl_pkey_new($config);
        
        if ($privateKey === false) {
            throw new Exception("Error al generar clave privada: " . openssl_error_string());
        }
        
        // PASO 2: Generar CSR
        echo "<p>2️⃣ Generando Certificate Signing Request (CSR)...</p>";
        $csr = openssl_csr_new($dn, $privateKey, array('digest_alg' => 'sha256'));
        
        if ($csr === false) {
            throw new Exception("Error al generar CSR: " . openssl_error_string());
        }
        
        // PASO 3: Exportar a formato PEM
        echo "<p>3️⃣ Exportando archivos...</p>";
        
        openssl_pkey_export($privateKey, $privateKeyPEM);
        openssl_csr_export($csr, $csrPEM);
        
        // PASO 4: Guardar archivos
        $timestamp = date('Ymd_His');
        $keyFile = "{$certDir}/afip_homo_{$timestamp}.key";
        $csrFile = "{$certDir}/afip_homo_{$timestamp}.csr";
        
        file_put_contents($keyFile, $privateKeyPEM);
        file_put_contents($csrFile, $csrPEM);
        
        // Permisos seguros para la clave privada
        chmod($keyFile, 0600);
        
        echo "<p style='color: green; font-weight: bold'>✅ ¡Certificados generados exitosamente!</p>";
        
        // MOSTRAR ÉXITO
        echo "</div>";
        
        echo "<div class='success'>";
        echo "<h2>✅ Generación Completada</h2>";
        echo "<p>Los archivos se han creado correctamente en el directorio <strong>{$certDir}/</strong></p>";
        echo "</div>";
        
        // ARCHIVOS GENERADOS
        echo "<div class='step'>";
        echo "<h3>📁 Archivos Generados</h3>";
        
        echo "<div class='file-item'>";
        echo "<span class='file-icon'>🔑</span>";
        echo "<div style='flex: 1'>";
        echo "<strong>" . basename($keyFile) . "</strong><br>";
        echo "<small style='color: #718096'>Clave Privada - ⚠️ MANTENER EN SECRETO</small>";
        echo "</div>";
        echo "<small style='color: #718096'>" . filesize($keyFile) . " bytes</small>";
        echo "</div>";
        
        echo "<div class='file-item'>";
        echo "<span class='file-icon'>📝</span>";
        echo "<div style='flex: 1'>";
        echo "<strong>" . basename($csrFile) . "</strong><br>";
        echo "<small style='color: #718096'>CSR para subir a AFIP</small>";
        echo "</div>";
        echo "<small style='color: #718096'>" . filesize($csrFile) . " bytes</small>";
        echo "</div>";
        
        echo "</div>";
        
        // CSR PARA COPIAR
        echo "<div class='info'>";
        echo "<h3>📋 CSR - Copiar y Pegar en AFIP</h3>";
        echo "<p style='margin-bottom: 10px'>Este es el contenido que debes subir a AFIP (incluye TODO desde BEGIN hasta END):</p>";
        echo "<textarea id='csrText' readonly onclick='this.select()'>" . htmlspecialchars($csrPEM) . "</textarea>";
        echo "<button onclick=\"
            const el = document.getElementById('csrText');
            el.select();
            document.execCommand('copy');
            this.textContent = '✓ Copiado al portapapeles';
            setTimeout(() => this.textContent = '📋 Copiar CSR', 2000);
        \" class='btn'>📋 Copiar CSR</button>";
        echo "</div>";
        
        // INSTRUCCIONES DETALLADAS
        echo "<div class='step'>";
        echo "<h3>🎯 Pasos para Obtener el Certificado en AFIP</h3>";
        echo "<ol style='font-size: 15px; line-height: 1.8'>";
        
        echo "<li><strong>Entrar a AFIP</strong><br>";
        echo "URL: <a href='https://auth.afip.gob.ar' target='_blank' style='color: #667eea'>https://auth.afip.gob.ar</a><br>";
        echo "Iniciar sesión con Clave Fiscal del CUIT <strong>{$cuit}</strong></li>";
        
        echo "<li><strong>Ir a Certificados Digitales</strong><br>";
        echo "Sistema Registral → Administrador de Relaciones de Clave Fiscal → Certificados Digitales</li>";
        
        echo "<li><strong>Crear Nuevo Certificado</strong><br>";
        echo "• Click en \"Nuevo Certificado\"<br>";
        echo "• Seleccionar <strong>\"Certificado de Homologación\"</strong><br>";
        echo "• Servicio: <strong>wsfe</strong> (Comprobantes en Línea - Factura Electrónica)<br>";
        echo "• Alias: Un nombre para identificarlo (ej: \"Certificado Pruebas 2025\")</li>";
        
        echo "<li><strong>Subir el CSR</strong><br>";
        echo "• En el campo \"Requerimiento\" o \"CSR\", pegar el contenido completo del cuadro de arriba<br>";
        echo "• Asegurarse de incluir las líneas BEGIN y END<br>";
        echo "• Click en \"Generar Certificado\"</li>";
        
        echo "<li><strong>Descargar el Certificado</strong><br>";
        echo "• AFIP generará el certificado (archivo .crt)<br>";
        echo "• Descargarlo y guardarlo como:<br>";
        echo "<code style='background: #2d3748; color: #e2e8f0; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px'>{$certDir}/afip_homo_{$timestamp}.crt</code></li>";
        
        echo "<li><strong>Configurar tu Sistema</strong><br>";
        echo "Usar estos datos en tu configuración:<br>";
        echo "<div style='background: #f7fafc; padding: 15px; border-radius: 8px; margin-top: 10px; font-family: monospace; font-size: 13px'>";
        echo "CUIT: {$cuit}<br>";
        echo "Certificado: {$certDir}/afip_homo_{$timestamp}.crt<br>";
        echo "Clave Privada: {$keyFile}<br>";
        echo "Punto de Venta: 1<br>";
        echo "Ambiente: 2 (Homologación)";
        echo "</div></li>";
        
        echo "</ol>";
        echo "</div>";
        
        // ADVERTENCIAS
        echo "<div class='warning'>";
        echo "<h4>⚠️ Advertencias Importantes</h4>";
        echo "<ul style='line-height: 1.8'>";
        echo "<li><strong>Clave Privada:</strong> El archivo <code>{$keyFile}</code> NUNCA debe compartirse ni enviarse por email</li>";
        echo "<li><strong>Homologación:</strong> Este es un certificado de PRUEBAS, los comprobantes NO tienen validez fiscal</li>";
        echo "<li><strong>Backup:</strong> Guardá una copia segura de la clave privada</li>";
        echo "<li><strong>Producción:</strong> Para emitir facturas válidas, necesitarás un certificado de PRODUCCIÓN</li>";
        echo "</ul>";
        echo "</div>";
        
        // SIGUIENTE PASO
        echo "<div style='text-align: center; margin-top: 30px'>";
        echo "<p style='color: #718096; margin-bottom: 15px'>Una vez que descargues el certificado de AFIP:</p>";
        echo "<a href='setup_afip.php' class='btn'>➡️ Continuar con Setup de AFIP</a>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Error en la Generación</h3>";
        echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<h4 style='margin-top: 15px'>Posibles Causas:</h4>";
        echo "<ul>";
        echo "<li>OpenSSL no está habilitado en PHP</li>";
        echo "<li>Permisos insuficientes para crear archivos</li>";
        echo "<li>Configuración incorrecta de PHP</li>";
        echo "</ul>";
        echo "<h4 style='margin-top: 15px'>Soluciones:</h4>";
        echo "<ol>";
        echo "<li>Verificar que la extensión <code>openssl</code> esté habilitada en <code>php.ini</code></li>";
        echo "<li>Dar permisos de escritura al directorio del proyecto</li>";
        echo "<li>Revisar los logs de PHP para más detalles</li>";
        echo "</ol>";
        echo "</div>";
    }
    
} else {
    
    // PANTALLA INICIAL
    echo "<div class='info'>";
    echo "<h3>ℹ️ ¿Qué hace este generador?</h3>";
    echo "<p style='margin-bottom: 10px'>Este script crea un <strong>par de claves criptográficas</strong> nuevo y limpio:</p>";
    echo "<ul style='line-height: 1.8'>";
    echo "<li><strong>Clave Privada (.key):</strong> Se queda en tu servidor de forma segura</li>";
    echo "<li><strong>CSR (.csr):</strong> Lo subís a AFIP para obtener el certificado</li>";
    echo "</ul>";
    echo "<p style='margin-top: 10px'>Esto es necesario porque el certificado que tenés actualmente no tiene la clave privada correcta.</p>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>📋 Información del Nuevo Certificado</h3>";
    echo "<div style='background: white; padding: 20px; border-radius: 8px; margin-top: 15px'>";
    echo "<table style='width: 100%; border-collapse: collapse'>";
    echo "<tr style='border-bottom: 1px solid #e2e8f0'>";
    echo "<td style='padding: 12px; color: #718096; width: 40%'>CUIT:</td>";
    echo "<td style='padding: 12px; font-weight: 600'>{$cuit}</td>";
    echo "</tr>";
    echo "<tr style='border-bottom: 1px solid #e2e8f0'>";
    echo "<td style='padding: 12px; color: #718096'>Razón Social:</td>";
    echo "<td style='padding: 12px; font-weight: 600'>{$razonSocial}</td>";
    echo "</tr>";
    echo "<tr style='border-bottom: 1px solid #e2e8f0'>";
    echo "<td style='padding: 12px; color: #718096'>Servicio AFIP:</td>";
    echo "<td style='padding: 12px; font-weight: 600'>wsfe (Web Services Factura Electrónica)</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td style='padding: 12px; color: #718096'>Ambiente:</td>";
    echo "<td style='padding: 12px'><span style='background: #f0fff4; color: #22543d; padding: 4px 12px; border-radius: 12px; font-weight: 600'>🧪 Homologación (Testing)</span></td>";
    echo "</tr>";
    echo "</table>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h4>⚡ Antes de Continuar</h4>";
    echo "<p style='margin-bottom: 10px'>Este proceso:</p>";
    echo "<ul style='line-height: 1.8'>";
    echo "<li>Generará un certificado completamente NUEVO</li>";
    echo "<li>Deberás ir a AFIP a crear un nuevo certificado de homologación</li>";
    echo "<li>El certificado actual <code>moreno_homologacion.crt</code> dejará de usarse</li>";
    echo "<li>El proceso toma 5-10 minutos (incluyendo el trámite en AFIP)</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px'>";
    echo "<form method='post'>";
    echo "<button type='submit' name='generar' class='btn' style='font-size: 18px; padding: 16px 32px'>🚀 Generar Nuevo Certificado</button>";
    echo "</form>";
    echo "</div>";
    
    // Verificar OpenSSL
    echo "<div class='info' style='margin-top: 30px'>";
    echo "<h4>🔧 Verificación del Sistema</h4>";
    if (extension_loaded('openssl')) {
        echo "<p style='color: green'>✅ Extensión OpenSSL habilitada</p>";
        echo "<p style='color: #718096; font-size: 14px'>Versión: " . OPENSSL_VERSION_TEXT . "</p>";
    } else {
        echo "<p style='color: red'>❌ Extensión OpenSSL NO habilitada</p>";
        echo "<p style='margin-top: 10px'>Debes habilitar la extensión <code>openssl</code> en tu archivo <code>php.ini</code></p>";
    }
    echo "</div>";
}

echo "</div></body></html>";
?>