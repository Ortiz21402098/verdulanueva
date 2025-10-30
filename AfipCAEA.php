<?php
require_once 'AfipWSAA.php';

class AfipCAEA {
    private $wsaa;
    private $wsdl_homologacion = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
    private $wsdl_produccion = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';
    private $cuit;
    private $ambiente;
    
    public function __construct($cuit, $certificado, $clave_privada, $ambiente = 2) {
        $this->cuit = $cuit;
        $this->ambiente = $ambiente;
        $this->wsaa = new AfipWSAA($certificado, $clave_privada, $ambiente);
    }
    
    /**
     * Obtiene las credenciales de acceso
     */
    private function getCredenciales() {
        if (!$this->wsaa->validarTA()) {
            return $this->wsaa->generarTicketAcceso('wsfe');
        }
        
        // Leer TA existente
        $archivo = dirname(__FILE__) . '/../tickets/TA.xml';
        $ta = simplexml_load_file($archivo);
        
        return [
            'token' => (string)$ta->credentials->token,
            'sign' => (string)$ta->credentials->sign
        ];
    }
    
    /**
     * Solicita un CAEA
     * 
     * @param int $periodo Periodo en formato AAAAMM (ej: 202510)
     * @param int $orden Orden: 1 = Primera quincena, 2 = Segunda quincena
     */
    public function solicitarCAEA($periodo, $orden) {
        try {
            $credenciales = $this->getCredenciales();
            
            $wsdl = ($this->ambiente == 1) ? $this->wsdl_produccion : $this->wsdl_homologacion;
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_2,
                'trace' => 1,
                'exceptions' => 0
            ]);
            
            $params = [
                'Auth' => [
                    'Token' => $credenciales['token'],
                    'Sign' => $credenciales['sign'],
                    'Cuit' => $this->cuit
                ],
                'Periodo' => $periodo,
                'Orden' => $orden
            ];
            
            $resultado = $client->FECAEASolicitar($params);
            
            if (is_soap_fault($resultado)) {
                throw new Exception("Error SOAP: " . $resultado->faultstring);
            }
            
            $response = $resultado->FECAEASolicitarResult;
            
            // Verificar errores
            if (isset($response->Errors)) {
                $errores = is_array($response->Errors->Err) ? 
                          $response->Errors->Err : 
                          [$response->Errors->Err];
                
                $mensajes = [];
                foreach ($errores as $error) {
                    $mensajes[] = "Código {$error->Code}: {$error->Msg}";
                }
                throw new Exception("Errores AFIP:\n" . implode("\n", $mensajes));
            }
            
            return [
                'CAEA' => $response->ResultGet->CAEA,
                'Periodo' => $response->ResultGet->Periodo,
                'Orden' => $response->ResultGet->Orden,
                'FchVigDesde' => $response->ResultGet->FchVigDesde,
                'FchVigHasta' => $response->ResultGet->FchVigHasta,
                'FchTopeInf' => $response->ResultGet->FchTopeInf,
                'Observaciones' => isset($response->ResultGet->Observaciones) ? 
                                  $response->ResultGet->Observaciones : null
            ];
            
        } catch (Exception $e) {
            $this->logError('solicitarCAEA', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Consulta un CAEA previamente solicitado
     */
    public function consultarCAEA($periodo, $orden) {
        try {
            $credenciales = $this->getCredenciales();
            
            $wsdl = ($this->ambiente == 1) ? $this->wsdl_produccion : $this->wsdl_homologacion;
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_2,
                'trace' => 1
            ]);
            
            $params = [
                'Auth' => [
                    'Token' => $credenciales['token'],
                    'Sign' => $credenciales['sign'],
                    'Cuit' => $this->cuit
                ],
                'Periodo' => $periodo,
                'Orden' => $orden
            ];
            
            $resultado = $client->FECAEAConsultar($params);
            return $resultado->FECAEAConsultarResult->ResultGet;
            
        } catch (Exception $e) {
            $this->logError('consultarCAEA', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Informa comprobantes emitidos con CAEA
     */
    public function informarComprobante($caea, $datos_comprobante) {
        try {
            $credenciales = $this->getCredenciales();
            
            $wsdl = ($this->ambiente == 1) ? $this->wsdl_produccion : $this->wsdl_homologacion;
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_2,
                'trace' => 1
            ]);
            
            $params = [
                'Auth' => [
                    'Token' => $credenciales['token'],
                    'Sign' => $credenciales['sign'],
                    'Cuit' => $this->cuit
                ],
                'FeCAEARegInfReq' => [
                    'FeCabReq' => [
                        'CantReg' => 1,
                        'PtoVta' => $datos_comprobante['punto_venta'],
                        'CbteTipo' => $datos_comprobante['tipo_comprobante']
                    ],
                    'FeDetReq' => [
                        'FECAEADetRequest' => [
                            'Concepto' => $datos_comprobante['concepto'],
                            'DocTipo' => $datos_comprobante['doc_tipo'],
                            'DocNro' => $datos_comprobante['doc_nro'],
                            'CbteDesde' => $datos_comprobante['cbte_desde'],
                            'CbteHasta' => $datos_comprobante['cbte_hasta'],
                            'CbteFch' => $datos_comprobante['cbte_fecha'],
                            'ImpTotal' => $datos_comprobante['imp_total'],
                            'ImpTotConc' => $datos_comprobante['imp_tot_conc'],
                            'ImpNeto' => $datos_comprobante['imp_neto'],
                            'ImpOpEx' => $datos_comprobante['imp_op_ex'],
                            'ImpIVA' => $datos_comprobante['imp_iva'],
                            'ImpTrib' => $datos_comprobante['imp_trib'],
                            'MonId' => $datos_comprobante['moneda'],
                            'MonCotiz' => $datos_comprobante['cotizacion'],
                            'CAEA' => $caea
                        ]
                    ]
                ]
            ];
            
            // Agregar IVA si existe
            if (isset($datos_comprobante['iva']) && !empty($datos_comprobante['iva'])) {
                $params['FeCAEARegInfReq']['FeDetReq']['FECAEADetRequest']['Iva'] = [
                    'AlicIva' => $datos_comprobante['iva']
                ];
            }
            
            $resultado = $client->FECAEARegInformativo($params);
            return $resultado->FECAEARegInformativoResult;
            
        } catch (Exception $e) {
            $this->logError('informarComprobante', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Informa CAEA sin movimiento
     */
    public function informarSinMovimiento($punto_venta, $caea) {
        try {
            $credenciales = $this->getCredenciales();
            
            $wsdl = ($this->ambiente == 1) ? $this->wsdl_produccion : $this->wsdl_homologacion;
            $client = new SoapClient($wsdl, ['soap_version' => SOAP_1_2, 'trace' => 1]);
            
            $params = [
                'Auth' => [
                    'Token' => $credenciales['token'],
                    'Sign' => $credenciales['sign'],
                    'Cuit' => $this->cuit
                ],
                'PtoVta' => $punto_venta,
                'CAEA' => $caea
            ];
            
            $resultado = $client->FECAEASinMovimientoInformar($params);
            return $resultado->FECAEASinMovimientoInformarResult;
            
        } catch (Exception $e) {
            $this->logError('informarSinMovimiento', $e->getMessage());
            throw $e;
        }
    }
    
    private function logError($metodo, $mensaje) {
        $log = date('Y-m-d H:i:s') . " - {$metodo}: {$mensaje}\n";
        file_put_contents(
            dirname(__FILE__) . '/../logs/afip_log.txt', 
            $log, 
            FILE_APPEND
        );
    }
}