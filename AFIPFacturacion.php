<?php

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

    private function crearSoapClient($url) {
        if (!strpos($url, '?wsdl')) {
            $url .= '?wsdl';
        }
        
        $contextoSSL = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'capture_peer_cert' => false,
                'capture_peer_chain' => false,
                'SNI_enabled' => true,
                'disable_compression' => true
            ],
            'http' => [
                'timeout' => 30,
                'user_agent' => 'PHP-SOAP/AFIP'
            ]
        ]);
        
        try {
            return new SoapClient($url, [
                'stream_context' => $contextoSSL,
                'connection_timeout' => 30,
                'exceptions' => true,
                'trace' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'soap_version' => SOAP_1_2,
                'keep_alive' => false,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
            ]);
        } catch (Exception $e) {
            error_log("Error creando cliente SOAP para {$url}: " . $e->getMessage());
            throw new Exception("Error de conectividad: No se puede conectar a " . $url);
        }
    }
    
    public function obtenerCredenciales() {
        try {
            error_log("=== OBTENER CREDENCIALES ===");
            
            error_log("1. Creando TRA...");
            $tra = $this->crearTRA();
            error_log("✓ TRA creado");
            
            error_log("2. Firmando TRA...");
            $cms = $this->firmarTRA($tra);
            error_log("✓ TRA firmado (" . strlen($cms) . " bytes)");
            
            error_log("3. Conectando a WSAA: " . self::URLS_WSAA[$this->ambiente]);
            $soap = $this->crearSoapClient(self::URLS_WSAA[$this->ambiente]);
            error_log("✓ Cliente SOAP creado");
            
            error_log("4. Enviando loginCms...");
            $resultado = $soap->loginCms(['in0' => $cms]);
            error_log("✓ Respuesta recibida");
            
            // NUEVO: Validar respuesta antes de parsear
            if (!isset($resultado->loginCmsReturn)) {
                throw new Exception('Respuesta inválida de WSAA');
            }
            
            error_log("5. Parseando XML...");
            $xml = simplexml_load_string($resultado->loginCmsReturn);
            
            if (!$xml) {
                throw new Exception('Error parseando respuesta de WSAA: ' . libxml_get_last_error()->message);
            }
            
            // NUEVO: Verificar si hay errores en la respuesta
            if (isset($xml->error)) {
                throw new Exception("Error WSAA: {$xml->error->message} (Código: {$xml->error->code})");
            }
            
            $this->token = (string)$xml->credentials->token;
            $this->sign = (string)$xml->credentials->sign;
            $this->tokenExpira = strtotime((string)$xml->header->expirationTime);
            
            // Validar que se obtuvieron los datos
            if (empty($this->token) || empty($this->sign)) {
                throw new Exception('Token o Sign vacíos en la respuesta de WSAA');
            }
            
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
    
    private function encontrarOpenSSL() {
        $paths = [
            'openssl',
            'C:/xampp/apache/bin/openssl.exe',
            'C:/Program Files/OpenSSL-Win64/bin/openssl.exe',
            'C:/Program Files (x86)/OpenSSL-Win32/bin/openssl.exe',
            '/usr/bin/openssl',
            '/usr/local/bin/openssl'
        ];
        
        foreach ($paths as $path) {
            $testCmd = is_file($path) ? "\"{$path}\" version 2>&1" : "{$path} version 2>&1";
            @exec($testCmd, $output, $return);
            
            if ($return === 0) {
                error_log("OpenSSL encontrado: {$path}");
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * CORREGIDO: Firma TRA con el formato exacto que AFIP requiere
     */
    private function firmarTRA($tra) {
        try {
            // Verificar archivos
            if (!file_exists($this->certificado)) {
                throw new Exception("Certificado no encontrado: {$this->certificado}");
            }
            if (!file_exists($this->clavePrivada)) {
                throw new Exception("Clave privada no encontrada: {$this->clavePrivada}");
            }
            
            // Buscar OpenSSL
            $opensslCmd = $this->encontrarOpenSSL();
            
            if (!$opensslCmd) {
                throw new Exception("OpenSSL no encontrado. Instalá OpenSSL o usá XAMPP que lo incluye.");
            }
            
            // Archivos temporales
            $traFile = tempnam(sys_get_temp_dir(), 'tra_');
            $cmsFile = tempnam(sys_get_temp_dir(), 'cms_');
            
            file_put_contents($traFile, $tra);
            
            // Usar rutas absolutas
            $certPath = realpath($this->certificado);
            $keyPath = realpath($this->clavePrivada);
            
            // CORREGIDO: Comando con -nochain para evitar certificados intermedios
            $command = sprintf(
                '"%s" smime -sign -in "%s" -out "%s" -signer "%s" -inkey "%s" -outform DER -nodetach -nochain 2>&1',
                $opensslCmd,
                $traFile,
                $cmsFile,
                $certPath,
                $keyPath
            );
            
            error_log("Ejecutando: " . $command);
            
            exec($command, $output, $return);
            
            if ($return !== 0) {
                $error = implode("\n", $output);
                error_log("Error OpenSSL: " . $error);
                throw new Exception("Error ejecutando OpenSSL: " . $error);
            }
            
            if (!file_exists($cmsFile) || filesize($cmsFile) == 0) {
                throw new Exception("Archivo CMS no fue generado");
            }
            
            // Leer el archivo DER y convertir a base64
            $cmsBinary = file_get_contents($cmsFile);
            $cmsBase64 = base64_encode($cmsBinary);
            
            error_log("✓ CMS generado correctamente");
            error_log("  - Binario: " . strlen($cmsBinary) . " bytes");
            error_log("  - Base64: " . strlen($cmsBase64) . " caracteres");
            
            // Limpiar archivos temporales
            @unlink($traFile);
            @unlink($cmsFile);
            
            return $cmsBase64;
            
        } catch (Exception $e) {
            error_log("✗ Error en firmarTRA: " . $e->getMessage());
            
            // Limpiar
            if (isset($traFile) && file_exists($traFile)) @unlink($traFile);
            if (isset($cmsFile) && file_exists($cmsFile)) @unlink($cmsFile);
            
            throw $e;
        }
    }
    
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
    
    public function crearComprobante($datosVenta, $tipoComprobante = 11) {
        try {
            if (!$this->credencialesValidas()) {
                error_log("Credenciales vencidas, renovando...");
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
            
            error_log("Solicitando CAE para venta {$datosVenta['venta_id']}...");
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
    
    private function obtenerProximoNumero($tipoComprobante) {
        try {
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
                error_log("Credenciales cargadas desde caché");
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
            return 80; // CUIT
        } elseif (strlen($documento) == 8) {
            return 96; // DNI
        } else {
            return 99; // Consumidor Final
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
    
    public function validarConfiguracion() {
        $errores = [];
        
        if (empty($this->cuit)) $errores[] = 'CUIT no configurado';
        if (!file_exists($this->certificado)) $errores[] = 'Certificado no encontrado';
        if (!file_exists($this->clavePrivada)) $errores[] = 'Clave privada no encontrada';
        
        // Verificar OpenSSL
        if (!$this->encontrarOpenSSL()) {
            $errores[] = 'OpenSSL no encontrado en el sistema';
        }
        
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
}
?>