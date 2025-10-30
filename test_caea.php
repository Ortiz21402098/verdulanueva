<?php
require_once 'afip/class/AfipCAEA.php';

// Configuración desde BD
$config = [
    'cuit' => '20355274684',
    'certificado' => __DIR__ . '/afip/certificados/moreno_homologacion.crt',
    'clave_privada' => __DIR__ . '/afip/certificados/moreno_homologacion.key',
    'punto_venta' => 1,
    'ambiente' => 2 // 2 = Testing, 1 = Producción
];

try {
    $afip = new AfipCAEA(
        $config['cuit'],
        $config['certificado'],
        $config['clave_privada'],
        $config['ambiente']
    );
    
    // 1. Solicitar CAEA para octubre 2025, primera quincena
    echo "=== SOLICITANDO CAEA ===\n";
    $caea_data = $afip->solicitarCAEA(202510, 1);
    print_r($caea_data);
    
    // Guardar CAEA en BD
    // ... código para guardar en tu tabla ...
    
    // 2. Ejemplo: Informar un comprobante
    echo "\n=== INFORMANDO COMPROBANTE ===\n";
    $comprobante = [
        'punto_venta' => 1,
        'tipo_comprobante' => 11, // Factura C
        'concepto' => 1, // Productos
        'doc_tipo' => 99, // Consumidor Final
        'doc_nro' => 0,
        'cbte_desde' => 1,
        'cbte_hasta' => 1,
        'cbte_fecha' => date('Ymd'),
        'imp_total' => 2100.00,
        'imp_tot_conc' => 0,
        'imp_neto' => 1735.54,
        'imp_op_ex' => 0,
        'imp_iva' => 364.46,
        'imp_trib' => 0,
        'moneda' => 'PES',
        'cotizacion' => 1,
        'iva' => [[
            'Id' => 5, // 21%
            'BaseImp' => 1735.54,
            'Importe' => 364.46
        ]]
    ];
    
    $resultado = $afip->informarComprobante($caea_data['CAEA'], $comprobante);
    print_r($resultado);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}