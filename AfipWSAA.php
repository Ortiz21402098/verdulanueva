<?php
class AfipWSAA {
    private $wsdl_homologacion = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';
    private $wsdl_produccion = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl';
    private $certificado;
    private $clave_privada;
    private $ambiente; // 1=Producción, 2=Testing
    
    public function __construct($certificado, $clave_privada, $ambiente = 2) {
        $this->certificado = $certificado;
        $this->clave_privada = $clave_privada;
        $this->ambiente = $ambiente;
    }
    
    /**
     * Genera el Ticket de Acceso (TA)
     */
    public function generarTicketAcceso($servicio = 'wsfe') {
        try {
            // 1. Crear TRA (Ticket de Requerimiento de Acceso)
            $tra = $this->crearTRA($servicio);
            
            // 2. Firmar TRA con certificado
            $cms = $this->firmarTRA($tra);
            
            // 3. Llamar al WSAA
            $wsdl = ($this->ambiente == 1) ? $this->wsdl_produccion : $this->wsdl_homologacion;
            $client = new SoapClient($wsdl, [
                'soap_version' => SOAP_1_2,
                'location' => str_replace('?wsdl', '', $wsdl),
                'trace' => 1,
                'exceptions' => 1
            ]);
            
            // 4. Obtener respuesta
            $resultado = $client->loginCms(['in0' => $cms]);
            
            // 5. Parsear y guardar TA
            $ta = simplexml_load_string($resultado->loginCmsReturn);
            $this->guardarTA($ta);
            
            return [
                'token' => (string)$ta->credentials->token,
                'sign' => (string)$ta->credentials->sign,
                'expirationTime' => (string)$ta->header->expirationTime
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error al generar TA: " . $e->getMessage());
        }
    }
    
    /**
     * Crea el TRA en formato XML
     */
    private function crearTRA($servicio) {
        $uniqueId = time();
        $generationTime = date('c', time() - 600); // 10 minutos antes
        $expirationTime = date('c', time() + 43200); // 12 horas después
        
        $tra = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
    <header>
        <uniqueId>{$uniqueId}</uniqueId>
        <generationTime>{$generationTime}</generationTime>
        <expirationTime>{$expirationTime}</expirationTime>
    </header>
    <service>{$servicio}</service>
</loginTicketRequest>
XML;
        
        return $tra;
    }
    
    /**
     * Firma el TRA con el certificado
     */
    private function firmarTRA($tra) {
        $archivo_tra = sys_get_temp_dir() . '/tra_' . time() . '.xml';
        $archivo_cms = sys_get_temp_dir() . '/tra_' . time() . '.cms';
        
        file_put_contents($archivo_tra, $tra);
        
        // Firmar con OpenSSL
        $comando = "openssl smime -sign -in {$archivo_tra} -out {$archivo_cms} " .
                   "-signer {$this->certificado} -inkey {$this->clave_privada} " .
                   "-outform DER -nodetach 2>&1";
        
        exec($comando, $output, $return);
        
        if ($return != 0) {
            throw new Exception("Error al firmar TRA: " . implode("\n", $output));
        }
        
        $cms = file_get_contents($archivo_cms);
        
        // Limpiar archivos temporales
        @unlink($archivo_tra);
        @unlink($archivo_cms);
        
        return base64_encode($cms);
    }
    
    /**
     * Guarda el TA en archivo
     */
    private function guardarTA($ta) {
        $archivo = dirname(__FILE__) . '/../tickets/TA.xml';
        file_put_contents($archivo, $ta->asXML());
    }
    
    /**
     * Verifica si el TA existe y es válido
     */
    public function validarTA() {
        $archivo = dirname(__FILE__) . '/../tickets/TA.xml';
        
        if (!file_exists($archivo)) {
            return false;
        }
        
        $ta = simplexml_load_file($archivo);
        $expiration = strtotime((string)$ta->header->expirationTime);
        
        // Renovar si faltan menos de 10 minutos
        return ($expiration - time()) > 600;
    }
}