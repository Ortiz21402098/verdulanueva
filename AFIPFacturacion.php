<?php
/**
 * AFIPFacturacion.php - VERSIÓN CORREGIDA CON crearSoapClient EN TODOS LADOS
 */

require_once 'CalculadoraIVA.php';

class AFIPFacturacion {
    
    private $cuit;
    private $certificado;
    private $clavePrivada;
    private $puntoVenta;
    private $ambiente;
    private $token;
    private $sign;
    private $tokenExpira;
    
    private const URLS_WSAA = [
        1 => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
        2 => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms'
    ];
    
    private const URLS_WSFEv1 = [
        1 => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx',
        2 => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx'
    ];
    
    public function __construct($config = []) {
        $this->cuit = $config['cuit'] ?? '';
        $this->certificado = $config['certificado'] ?? '';
        $this->clavePrivada = $config['clave_privada'] ?? '';
        $this->puntoVenta = $config['punto_venta'] ?? 1;
        $this->ambiente = $config['ambiente'] ?? 2;
        
        if (empty($this->cuit) || empty($this->certificado) || empty($this->clavePrivada)) {
            throw new Exception('Configuración AFIP incompleta');
        }
        
        $this->cargarCredenciales();
    }

    /**
     * Crear cliente SOAP con configuración correcta
     */
    private function crearSoapClient($url) {
        if (!strpos($url, '?wsdl')) {
            $url .= '?wsdl';
        }
        
        return new SoapClient($url, [
            'verify_peer' => false,
            'verify_host' => false,
            'connection_timeout' => 10,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_host' => false,
                    'allow_self_signed' => true
                ]
            ]),
            'exceptions' => true,
            'trace' => true,
            'cache_wsdl' => WSDL_CACHE_NONE
        ]);
    }
    
    /**
     * Obtener credenciales de acceso desde WSAA
     */
    public function obtenerCredenciales() {
        try {
            error_log("=== OBTENER CREDENCIALES ===");
            
            error_log("1. Creando TRA...");
            $tra = $this->crearTRA();
            error_log("✓ TRA creado");
            
            error_log("2. Firmando TRA...");
            $cms = $this->firmarTRA($tra);
            error_log("✓ TRA firmado");
            
            error_log("3. Conectando a WSAA...");
            $soap = $this->crearSoapClient(self::URLS_WSAA[$this->ambiente]);
            error_log("✓ Cliente SOAP creado");
            
            error_log("4. Enviando loginCms...");
            $resultado = $soap->loginCms(['in0' => $cms]);
            error_log("✓ Respuesta recibida");
            
            error_log("5. Parseando XML...");
            $xml = simplexml_load_string($resultado->loginCmsReturn);
            
            if (!$xml) {
                throw new Exception('Error parseando respuesta de WSAA');
            }
            
            $this->token = (string)$xml->credentials->token;
            $this->sign = (string)$xml->credentials->sign;
            $this->tokenExpira = strtotime((string)$xml->header->expirationTime);
            
            $this->guardarCredenciales();
            error_log("✓ Credenciales guardadas. Expira: " . date('Y-m-d H:i:s', $this->tokenExpira));
            
            return [
                'success' => true,
                'token' => $this->token,
                'sign' => $this->sign,
                'expira' => date('Y-m-d H:i:s', $this->tokenExpira)
            ];
            
        } catch (Exception $e) {
            error_log("✗ Error en obtenerCredenciales: " . $e->getMessage());
            throw new Exception('Error obteniendo credenciales AFIP: ' . $e->getMessage());
        }
    }
    
    /**
     * Crear comprobante en AFIP
     */
    public function crearComprobante($datosVenta, $tipoComprobante = 11) {
        try {
            if (!$this->credencialesValidas()) {
                $this->obtenerCredenciales();
            }
            
            $items = $this->obtenerItemsVenta($datosVenta['venta_id']);
            $totalesIVA = CalculadoraIVA::calcularTotalesVenta($items);
            $datosAFIP = CalculadoraIVA::formatearParaAFIP($totalesIVA);
            
            $proximoNumero = $this->obtenerProximoNumero($tipoComprobante);
            
            $comprobante = array_merge($datosAFIP, [
                'CbteTipo' => $tipoComprobante,
                'PtoVta' => $this->puntoVenta,
                'CbteDesde' => $proximoNumero,
                'CbteHasta' => $proximoNumero,
                'CbteFch' => date('Ymd'),
                'ImpTotal' => $totalesIVA['total_con_iva'],
                'ImpTotConc' => 0,
                'ImpNeto' => $totalesIVA['subtotal_sin_iva'],
                'ImpOpEx' => 0,
                'ImpIVA' => $totalesIVA['total_iva'],
                'ImpTrib' => 0,
                'MonId' => 'PES',
                'MonCotiz' => 1,
                'Concepto' => 1
            ]);
            
            if (isset($datosVenta['cliente_documento']) && !empty($datosVenta['cliente_documento'])) {
                $comprobante['DocTipo'] = $this->determinarTipoDocumento($datosVenta['cliente_documento']);
                $comprobante['DocNro'] = $datosVenta['cliente_documento'];
            } else {
                $comprobante['DocTipo'] = 99;
                $comprobante['DocNro'] = 0;
            }
            
            // USAR crearSoapClient
            $soap = $this->crearSoapClient(self::URLS_WSFEv1[$this->ambiente]);
            
            $request = [
                'Auth' => [
                    'Token' => $this->token,
                    'Sign' => $this->sign,
                    'Cuit' => $this->cuit
                ],
                'FeCAEReq' => [
                    'FeCabReq' => [
                        'CantReg' => 1,
                        'PtoVta' => $this->puntoVenta,
                        'CbteTipo' => $tipoComprobante
                    ],
                    'FeDetReq' => [
                        'FECAEDetRequest' => $comprobante
                    ]
                ]
            ];
            
            $resultado = $soap->FECAESolicitar($request);
            
            if ($resultado->FECAESolicitarResult->Errors) {
                $errores = is_array($resultado->FECAESolicitarResult->Errors->Err) 
                    ? $resultado->FECAESolicitarResult->Errors->Err 
                    : [$resultado->FECAESolicitarResult->Errors->Err];
                
                $mensajeError = '';
                foreach ($errores as $error) {
                    $mensajeError .= "Error {$error->Code}: {$error->Msg}. ";
                }
                throw new Exception('Error AFIP: ' . $mensajeError);
            }
            
            $detalle = $resultado->FECAESolicitarResult->FeDetResp->FECAEDetResponse;
            
            $this->registrarComprobanteAFIP([
                'venta_id' => $datosVenta['venta_id'],
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $this->puntoVenta,
                'numero_comprobante' => $detalle->CbteDesde,
                'cae' => $detalle->CAE,
                'vencimiento_cae' => $detalle->CAEFchVto,
                'total' => $totalesIVA['total_con_iva'],
                'datos_json' => json_encode($comprobante)
            ]);
            
            return [
                'success' => true,
                'cae' => $detalle->CAE,
                'numero_comprobante' => $detalle->CbteDesde,
                'vencimiento_cae' => $detalle->CAEFchVto,
                'punto_venta' => $this->puntoVenta,
                'tipo_comprobante' => $tipoComprobante,
                'total' => $totalesIVA['total_con_iva'],
                'totales_iva' => $totalesIVA
            ];
            
        } catch (Exception $e) {
            error_log("Error creando comprobante AFIP: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'codigo_error' => $e->getCode()
            ];
        }
    }
    
    /**
     * Obtener próximo número de comprobante
     */
    private function obtenerProximoNumero($tipoComprobante) {
        try {
            // USAR crearSoapClient
            $soap = $this->crearSoapClient(self::URLS_WSFEv1[$this->ambiente]);
            
            $request = [
                'Auth' => [
                    'Token' => $this->token,
                    'Sign' => $this->sign,
                    'Cuit' => $this->cuit
                ],
                'PtoVta' => $this->puntoVenta,
                'CbteTipo' => $tipoComprobante
            ];
            
            $resultado = $soap->FECompUltimoAutorizado($request);
            return $resultado->FECompUltimoAutorizadoResult->CbteNro + 1;
            
        } catch (Exception $e) {
            throw new Exception('Error obteniendo próximo número: ' . $e->getMessage());
        }
    }
    
    /**
     * Crear Ticket Request (TRA)
     */
    private function crearTRA() {
        $ahora = date('c');
        $expira = date('c', strtotime('+24 hours'));
        $uniqueId = time();
        
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<loginTicketRequest version=\"1.0\">
    <header>
        <uniqueId>{$uniqueId}</uniqueId>
        <generationTime>{$ahora}</generationTime>
        <expirationTime>{$expira}</expirationTime>
    </header>
    <service>wsfe</service>
</loginTicketRequest>";
    }
    
    /**
     * Firmar TRA con OpenSSL
     */
    private function firmarTRA($tra) {
        try {
            $traFile = tempnam(sys_get_temp_dir(), 'tra_');
            file_put_contents($traFile, $tra);
            
            $cmsFile = tempnam(sys_get_temp_dir(), 'cms_');
            
            $traFile = str_replace('\\', '/', $traFile);
            $cmsFile = str_replace('\\', '/', $cmsFile);
            $cert = str_replace('\\', '/', realpath($this->certificado));
            $key = str_replace('\\', '/', realpath($this->clavePrivada));
            
            $openssl = 'C:\\Program Files (x86)\\OpenSSL-Win32\\bin\\openssl.exe';
        $command = "\"{$openssl}\" smime -sign -in \"{$traFile}\" -out \"{$cmsFile}\" -signer \"{$cert}\" -inkey \"{$key}\" -outform DER -nodetach -nochain 2>&1";
            
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("OpenSSL error: " . implode(" ", $output));
            }
            
            if (!file_exists($cmsFile) || filesize($cmsFile) == 0) {
                throw new Exception("CMS file not created");
            }
            
            $cms = base64_encode(file_get_contents($cmsFile));
            
            @unlink($traFile);
            @unlink($cmsFile);
            
            return $cms;
            
        } catch (Exception $e) {
            error_log("Error en firmarTRA: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function credencialesValidas() {
        return !empty($this->token) && !empty($this->sign) && $this->tokenExpira > time();
    }
    
    private function cargarCredenciales() {
        $archivo = "cache/afip_credentials_{$this->cuit}.json";
        if (file_exists($archivo)) {
            $datos = json_decode(file_get_contents($archivo), true);
            if ($datos && $datos['expira'] > time()) {
                $this->token = $datos['token'];
                $this->sign = $datos['sign'];
                $this->tokenExpira = $datos['expira'];
            }
        }
    }
    
    private function guardarCredenciales() {
        $dir = 'cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $archivo = "cache/afip_credentials_{$this->cuit}.json";
        file_put_contents($archivo, json_encode([
            'token' => $this->token,
            'sign' => $this->sign,
            'expira' => $this->tokenExpira
        ]));
    }
    
    private function obtenerItemsVenta($ventaId) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                SELECT vi.*, d.nombre as departamento 
                FROM venta_items vi 
                JOIN departamentos d ON vi.departamento_id = d.id 
                WHERE vi.venta_id = ?
            ");
            $stmt->execute([$ventaId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            throw new Exception('Error obteniendo items: ' . $e->getMessage());
        }
    }
    
    private function registrarComprobanteAFIP($datos) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $db->exec("CREATE TABLE IF NOT EXISTS afip_comprobantes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                venta_id INT NOT NULL,
                tipo_comprobante INT NOT NULL,
                punto_venta INT NOT NULL,
                numero_comprobante INT NOT NULL,
                cae VARCHAR(14) NOT NULL,
                vencimiento_cae DATE NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                datos_json TEXT,
                fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(venta_id),
                INDEX(cae)
            )");
            
            $stmt = $db->prepare("
                INSERT INTO afip_comprobantes 
                (venta_id, tipo_comprobante, punto_venta, numero_comprobante, cae, vencimiento_cae, total, datos_json) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $datos['venta_id'],
                $datos['tipo_comprobante'],
                $datos['punto_venta'],
                $datos['numero_comprobante'],
                $datos['cae'],
                date('Y-m-d', strtotime($datos['vencimiento_cae'])),
                $datos['total'],
                $datos['datos_json']
            ]);
            
        } catch (Exception $e) {
            error_log("Error registrando comprobante: " . $e->getMessage());
        }
    }
    
    private function determinarTipoDocumento($documento) {
        $documento = preg_replace('/[^0-9]/', '', $documento);
        if (strlen($documento) == 11) {
            return 80;
        } elseif (strlen($documento) == 8) {
            return 96;
        } else {
            return 99;
        }
    }
    
    public function consultarComprobante($tipoComprobante, $puntoVenta, $numeroComprobante) {
        try {
            if (!$this->credencialesValidas()) {
                $this->obtenerCredenciales();
            }
            
            $soap = $this->crearSoapClient(self::URLS_WSFEv1[$this->ambiente]);
            
            $resultado = $soap->FECompConsultar([
                'Auth' => [
                    'Token' => $this->token,
                    'Sign' => $this->sign,
                    'Cuit' => $this->cuit
                ],
                'FeCompConsReq' => [
                    'CbteTipo' => $tipoComprobante,
                    'PtoVta' => $puntoVenta,
                    'CbteNro' => $numeroComprobante
                ]
            ]);
            
            return [
                'success' => true,
                'comprobante' => $resultado->FECompConsultarResult->ResultGet
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public static function getConfiguracionTesting() {
        return [
            'cuit' => '20123456789',
            'certificado' => 'certificados/testing.crt',
            'clave_privada' => 'certificados/testing.key',
            'punto_venta' => 1,
            'ambiente' => 2
        ];
    }
    
    public static function getConfiguracionProduccion() {
        return [
            'cuit' => '20123456789',
            'certificado' => 'certificados/produccion.crt',
            'clave_privada' => 'certificados/produccion.key',
            'punto_venta' => 1,
            'ambiente' => 1
        ];
    }
    
    public function validarConfiguracion() {
        $errores = [];
        
        if (empty($this->cuit)) $errores[] = 'CUIT no configurado';
        if (!file_exists($this->certificado)) $errores[] = 'Certificado no encontrado';
        if (!file_exists($this->clavePrivada)) $errores[] = 'Clave privada no encontrada';
        
        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }
    
    public function testConectividad() {
        try {
            $validacion = $this->validarConfiguracion();
            if (!$validacion['valido']) {
                return [
                    'success' => false,
                    'message' => 'Configuración inválida',
                    'errores' => $validacion['errores']
                ];
            }
            
            $credenciales = $this->obtenerCredenciales();
            
            return [
                'success' => true,
                'message' => 'Conectividad AFIP OK',
                'ambiente' => $this->ambiente === 1 ? 'Producción' : 'Testing',
                'token_expira' => $credenciales['expira']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error de conectividad: ' . $e->getMessage()
            ];
        }
    }
}

class ConfiguracionAFIP {
    public static function obtenerConfiguracion() {
        $config = ConfigManager::getInstance();
        return [
            'habilitado' => $config->get('afip_habilitado', false),
            'cuit' => $config->get('afip_cuit', ''),
            'certificado' => $config->get('afip_certificado', ''),
            'clave_privada' => $config->get('afip_clave_privada', ''),
            'punto_venta' => $config->get('afip_punto_venta', 1),
            'ambiente' => $config->get('afip_ambiente', 2),
            'tipo_comprobante_default' => $config->get('afip_tipo_comprobante', 11),
            'generar_para_debito' => $config->get('afip_generar_debito', true),
            'generar_para_credito' => $config->get('afip_generar_credito', true),
            'generar_para_transferencia' => $config->get('afip_generar_transferencia', true)
        ];
    }
    
    public static function guardarConfiguracion($datos) {
        $config = ConfigManager::getInstance();
        foreach ($datos as $clave => $valor) {
            $tipoValor = is_bool($valor) ? 'boolean' : (is_int($valor) ? 'int' : 'string');
            $config->set('afip_' . $clave, $valor, $tipoValor);
        }
        return true;
    }
    
    public static function configuracionInicial() {
        return [
            'habilitado' => false,
            'cuit' => '',
            'certificado' => 'certificados/afip.crt',
            'clave_privada' => 'certificados/afip.key',
            'punto_venta' => 1,
            'ambiente' => 2,
            'tipo_comprobante_default' => 11,
            'generar_para_debito' => true,
            'generar_para_credito' => true,
            'generar_para_transferencia' => true
        ];
    }
}
?>