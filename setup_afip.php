<?php

require_once 'config.php';
require_once 'impresoraFiscal3nStar.php';

echo "<h1>Configuración AFIP - Mini Supermercado La Nueva</h1>";
echo "<hr>";

// Verificar certificados
$cert_key = 'certificados/afip.key';
$cert_crt = 'certificados/afip.crt';

echo "<h2>1. Verificando Certificados</h2>";

if (file_exists($cert_key)) {
    echo "✓ Clave privada encontrada: {$cert_key}<br>";
} else {
    echo "<span style='color:red'>✗ Clave privada NO encontrada: {$cert_key}</span><br>";
    echo "<p><strong>Acción requerida:</strong> Ejecutar comando: <code>openssl genrsa -out certificados/moreno_VERDU.key 2048</code></p>";
}

if (file_exists($cert_crt)) {
    echo "✓ Certificado encontrado: {$cert_crt}<br>";
} else {
    echo "<span style='color:orange'>⚠ Certificado NO encontrado: {$cert_crt}</span><br>";
    echo "<p><strong>Acción requerida:</strong></p>";
    echo "<ol>";
    echo "<li>Subir <code>moreno_VERDU.csr</code> a AFIP (Administrador de Relaciones → Certificados)</li>";
    echo "<li>Descargar el archivo .crt que te da AFIP</li>";
    echo "<li>Guardarlo como <code>certificados/moreno_VERDU.crt</code></li>";
    echo "</ol>";
}

echo "<hr>";

// Crear tabla de configuración si no existe
echo "<h2>2. Configurando Base de Datos</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Crear tabla configuracion si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS configuracion (
            clave VARCHAR(100) PRIMARY KEY,
            valor TEXT,
            tipo VARCHAR(20) DEFAULT 'string',
            descripcion TEXT,
            fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    echo "✓ Tabla 'configuracion' verificada<br>";
    
    // Insertar configuraciones AFIP
    $config = ConfigManager::getInstance();
    
    $configuraciones = [
        // AFIP
        ['afip_habilitado', 'false', 'boolean', 'Habilitar integración con AFIP'],
        ['afip_cuit', '20355274684', 'string', 'CUIT del negocio'],
        ['afip_certificado', 'certificados/moreno_VERDU.crt', 'string', 'Ruta al certificado AFIP'],
        ['afip_clave_privada', 'certificados/moreno_VERDU.key', 'string', 'Ruta a la clave privada'],
        ['afip_punto_venta', '1', 'int', 'Punto de venta AFIP'],
        ['afip_ambiente', '2', 'int', 'Ambiente AFIP (1=Producción, 2=Testing)'],
        ['afip_tipo_comprobante', '11', 'int', 'Tipo de comprobante default (11=Factura C)'],
        
        // Impresora
        ['impresora_fiscal_habilitada', 'true', 'boolean', 'Habilitar impresión automática'],
        ['impresora_nombre', 'POS-58', 'string', 'Nombre de la impresora'],
        ['impresora_fiscal_puerto', 'USB001', 'string', 'Puerto de la impresora'],
        
        // Negocio
        ['nombre_negocio', 'Mini Supermercado La Nueva', 'string', 'Nombre del negocio'],
        ['direccion_negocio', 'Av. Amadeo Sabattini 2607', 'string', 'Dirección'],
        ['cuit_negocio', '20-35527468-4', 'string', 'CUIT formateado']
    ];
    
    foreach ($configuraciones as $cfg) {
        $stmt = $db->prepare("
            INSERT INTO configuracion (clave, valor, tipo, descripcion) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                valor = IF(valor IS NULL OR valor = '', VALUES(valor), valor),
                tipo = VALUES(tipo),
                descripcion = VALUES(descripcion)
        ");
        $stmt->execute($cfg);
    }
    
    echo "✓ Configuraciones insertadas/actualizadas<br>";
    
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Error: " . $e->getMessage() . "</span><br>";
}

echo "<hr>";

// Test de conectividad AFIP
echo "<h2>3. Test de Conectividad AFIP</h2>";

if (file_exists($cert_key) && file_exists($cert_crt)) {
    try {
        require_once 'AFIPFacturacion.php';
        
        $afipConfig = [
            'cuit' => '20355274684',
            'certificado' => $cert_crt,
            'clave_privada' => $cert_key,
            'punto_venta' => 1,
            'ambiente' => 2 // Testing
        ];
        
        echo "<p>Intentando conectar con AFIP (ambiente Testing)...</p>";
        
        $afip = new AFIPFacturacion($afipConfig);
        $resultado = $afip->testConectividad();
        
        if ($resultado['success']) {
            echo "<div style='background:#d4edda; padding:15px; border-radius:5px; color:#155724;'>";
            echo "<strong>✓ CONEXIÓN EXITOSA</strong><br>";
            echo "Ambiente: {$resultado['ambiente']}<br>";
            echo "Token expira: {$resultado['token_expira']}<br>";
            echo "</div>";
            
            // Habilitar AFIP en configuración
            $config->set('afip_habilitado', 'true', 'boolean');
            echo "<p>✓ AFIP habilitado automáticamente</p>";
            
        } else {
            echo "<div style='background:#f8d7da; padding:15px; border-radius:5px; color:#721c24;'>";
            echo "<strong>✗ ERROR DE CONEXIÓN</strong><br>";
            echo "Mensaje: {$resultado['message']}<br>";
            if (isset($resultado['errores'])) {
                echo "<ul>";
                foreach ($resultado['errores'] as $error) {
                    echo "<li>{$error}</li>";
                }
                echo "</ul>";
            }
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background:#f8d7da; padding:15px; border-radius:5px; color:#721c24;'>";
        echo "<strong>✗ EXCEPCIÓN</strong><br>";
        echo $e->getMessage();
        echo "</div>";
    }
} else {
    echo "<p style='color:orange'>⚠ No se puede probar conectividad sin los certificados</p>";
}

echo "<hr>";

// Test de impresora
echo "<h2>4. Test de Impresora</h2>";

try {
    require_once 'ImpresoraFiscal3nStar.php';
    
    $impresora = new ImpresoraTermica();
    
    echo "<p>Impresora configurada: {$config->get('impresora_nombre')}</p>";
    echo "<p>Puerto: {$config->get('impresora_fiscal_puerto')}</p>";
    
    echo "<form method='post'>";
    echo "<button type='submit' name='test_impresora' style='padding:10px 20px; background:#007bff; color:white; border:none; border-radius:5px; cursor:pointer;'>";
    echo "🖨️ Imprimir Ticket de Prueba";
    echo "</button>";
    echo "</form>";
    
    if (isset($_POST['test_impresora'])) {
        $resultado = $impresora->imprimirPrueba();
        
        if ($resultado['success']) {
            echo "<div style='background:#d4edda; padding:15px; margin-top:10px; border-radius:5px; color:#155724;'>";
            echo "<strong>✓ IMPRESIÓN EXITOSA</strong><br>";
            echo $resultado['message'];
            echo "</div>";
        } else {
            echo "<div style='background:#f8d7da; padding:15px; margin-top:10px; border-radius:5px; color:#721c24;'>";
            echo "<strong>✗ ERROR DE IMPRESIÓN</strong><br>";
            echo $resultado['message'];
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Resumen final
echo "<h2>5. Resumen de Configuración</h2>";

echo "<table border='1' cellpadding='10' style='width:100%; border-collapse:collapse;'>";
echo "<tr style='background:#f8f9fa;'><th>Configuración</th><th>Valor</th><th>Estado</th></tr>";

$items_verificar = [
    ['AFIP Habilitado', $config->get('afip_habilitado') ? 'SI' : 'NO', $config->get('afip_habilitado')],
    ['AFIP Ambiente', $config->get('afip_ambiente') == 2 ? 'Testing' : 'Producción', true],
    ['CUIT', $config->get('afip_cuit'), !empty($config->get('afip_cuit'))],
    ['Certificado Existe', $cert_crt, file_exists($cert_crt)],
    ['Clave Privada Existe', $cert_key, file_exists($cert_key)],
    ['Impresora Habilitada', $config->get('impresora_fiscal_habilitada') ? 'SI' : 'NO', $config->get('impresora_fiscal_habilitada')],
    ['Nombre Impresora', $config->get('impresora_nombre'), !empty($config->get('impresora_nombre'))]
];

foreach ($items_verificar as $item) {
    $color = $item[2] ? '#d4edda' : '#f8d7da';
    $icono = $item[2] ? '✓' : '✗';
    
    echo "<tr>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td>{$item[1]}</td>";
    echo "<td style='background:{$color}; text-align:center;'>{$icono}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

echo "<h2>Próximos Pasos</h2>";

if (!file_exists($cert_crt)) {
    echo "<div style='background:#fff3cd; padding:15px; border-radius:5px; color:#856404;'>";
    echo "<strong>⚠️ ACCIÓN REQUERIDA</strong><br><br>";
    echo "<ol>";
    echo "<li>Ir a <a href='https://auth.afip.gob.ar' target='_blank'>AFIP con Clave Fiscal</a></li>";
    echo "<li>Administrador de Relaciones → Certificados Digitales</li>";
    echo "<li>Subir el archivo <code>certificados/moreno_VERDU.csr</code></li>";
    echo "<li>Descargar el certificado y guardarlo como <code>certificados/moreno_VERDU.crt</code></li>";
    echo "<li>Volver a ejecutar este script</li>";
    echo "</ol>";
    echo "</div>";
} elseif (!$config->get('afip_habilitado')) {
    echo "<div style='background:#fff3cd; padding:15px; border-radius:5px; color:#856404;'>";
    echo "<strong>⚠️ CONFIGURACIÓN INCOMPLETA</strong><br><br>";
    echo "<p>Los certificados existen pero la conexión con AFIP falló.</p>";
    echo "<p>Revisa los errores arriba y contacta al contador si persisten.</p>";
    echo "</div>";
} else {
    echo "<div style='background:#d4edda; padding:20px; border-radius:5px; color:#155724;'>";
    echo "<strong>✓ SISTEMA LISTO PARA USAR</strong><br><br>";
    echo "<p>La integración con AFIP está configurada y funcionando.</p>";
    echo "<p><strong>Recuerda:</strong></p>";
    echo "<ul>";
    echo "<li>Estás en ambiente de <strong>TESTING</strong></li>";
    echo "<li>Los comprobantes generados NO son válidos fiscalmente</li>";
    echo "<li>Probá varias ventas antes de pasar a PRODUCCIÓN</li>";
    echo "<li>Para activar PRODUCCIÓN, cambia 'afip_ambiente' a 1 en la configuración</li>";
    echo "</ul>";
    echo "<p><a href='Nuevaventa.php' style='background:#28a745; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ir al Sistema de Ventas</a></p>";
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align:center; color:#666; font-size:12px;'>Setup AFIP v1.0 - Mini Supermercado La Nueva</p>";
?>