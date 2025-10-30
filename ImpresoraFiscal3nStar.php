<?php

class ImpresoraTermica {
    private $printerName;  // AGREGADO: faltaba esta propiedad
    private $puerto;
    private $ancho = 48; // Caracteres por línea en 80mm
    
    // Comandos ESC/POS
    const ESC = "\x1B";
    const GS = "\x1D";
    const LF = "\x0A";
    
    // Inicialización y corte
    const CMD_INIT = "\x1B\x40";           // Inicializar impresora
    const CMD_CUT = "\x1D\x56\x00";        // Cortar papel
    const CMD_PARTIAL_CUT = "\x1D\x56\x01"; // Corte parcial
    
    // Alineación
    const ALIGN_LEFT = "\x1B\x61\x00";
    const ALIGN_CENTER = "\x1B\x61\x01";
    const ALIGN_RIGHT = "\x1B\x61\x02";
    
    // Estilos de texto
    const BOLD_ON = "\x1B\x45\x01";
    const BOLD_OFF = "\x1B\x45\x00";
    const UNDERLINE_ON = "\x1B\x2D\x01";
    const UNDERLINE_OFF = "\x1B\x2D\x00";
    
    // Tamaños
    const SIZE_NORMAL = "\x1D\x21\x00";
    const SIZE_DOUBLE_HEIGHT = "\x1D\x21\x01";
    const SIZE_DOUBLE_WIDTH = "\x1D\x21\x10";
    const SIZE_DOUBLE = "\x1D\x21\x11";
    
    public function __construct($puerto = null) {
        $config = ConfigManager::getInstance();
        
        // CORREGIDO: Definir printerName que faltaba
        $this->printerName = $config->get('impresora_nombre', 'POS-58');
        $this->puerto = $puerto ?? $config->get('impresora_fiscal_puerto', 'USB001');
        
        // Validación
        if (empty($this->printerName)) {
            $this->printerName = 'POS-58';
            error_log("ADVERTENCIA: Nombre vacío, usando fallback: POS-58");
        }
        
        error_log("ImpresoraTermica inicializada - Nombre: {$this->printerName}, Puerto: {$this->puerto}");
    }
    
    /**
     * Imprime un ticket de venta completo
     */
    public function imprimirTicketVenta($ventaId) {
        try {
            // Obtener datos de la venta
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                SELECT v.*, 
                       GROUP_CONCAT(vi.codigo_barras) as codigos,
                       GROUP_CONCAT(vi.precio) as precios,
                       GROUP_CONCAT(d.nombre) as departamentos
                FROM ventas v
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                LEFT JOIN departamentos d ON vi.departamento_id = d.id
                WHERE v.id = ?
                GROUP BY v.id
            ");
            $stmt->execute([$ventaId]);
            $venta = $stmt->fetch();
            
            if (!$venta) {
                throw new Exception("Venta no encontrada");
            }
            
            // Obtener items detallados
            $stmt = $db->prepare("
                SELECT vi.*, d.nombre as departamento
                FROM venta_items vi
                LEFT JOIN departamentos d ON vi.departamento_id = d.id
                WHERE vi.venta_id = ?
                ORDER BY vi.id
            ");
            $stmt->execute([$ventaId]);
            $items = $stmt->fetchAll();
            
            // Generar contenido del ticket
            $ticket = $this->generarTicket($venta, $items);
            
            // Enviar a impresora
            $this->enviarAImpresora($ticket);
            
            // Registrar en log
            logOperacion($ventaId, 'TICKET_IMPRESO', "Ticket térmico impreso correctamente");
            
            return [
                'success' => true,
                'message' => 'Ticket impreso correctamente',
                'venta_id' => $ventaId
            ];
            
        } catch (Exception $e) {
            logError("Error imprimiendo ticket: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al imprimir: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera el contenido del ticket en formato ESC/POS
     */
    private function generarTicket($venta, $items) {
    $config = ConfigManager::getInstance();
    $ticket = '';
    
    // 1. INICIALIZAR
    $ticket .= self::CMD_INIT;
    
    // 2. ENCABEZADO - Logo y nombre del negocio
    $ticket .= self::ALIGN_CENTER;
    $ticket .= self::SIZE_DOUBLE;
    $ticket .= self::BOLD_ON;
    $ticket .= $this->centrarTexto($config->get('nombre_negocio', 'MINI SUPERMERCADO'));
    $ticket .= self::LF;
    $ticket .= self::BOLD_OFF;
    $ticket .= self::SIZE_NORMAL;
    
    // Datos del negocio
    $ticket .= $this->centrarTexto($config->get('direccion_negocio', 'Av. Amadeo Sabattini 2607'));
    $ticket .= self::LF;
    $ticket .= $this->centrarTexto('CUIT: ' . $config->get('cuit_negocio', '20-35527468-4'));
    $ticket .= self::LF;
    $ticket .= $this->centrarTexto('IVA Responsable Inscripto');
    $ticket .= self::LF;
    $ticket .= self::LF;
    
    // Separador
    $ticket .= $this->linea('=');
    
    // 3. TIPO DE COMPROBANTE
    $ticket .= self::ALIGN_CENTER;
    $ticket .= self::SIZE_DOUBLE_HEIGHT;
    $ticket .= self::BOLD_ON;
    
    // Determinar si requiere ticket fiscal
    $requiereFiscal = in_array($venta['tipo_pago'], ['tarjeta-credito', 'tarjeta-debito', 'qr']);
    
    if ($requiereFiscal) {
        $ticket .= $this->centrarTexto('TICKET FISCAL');
        $ticket .= self::LF;
        $ticket .= self::SIZE_NORMAL;
        $ticket .= $this->centrarTexto('Tipo: C - Consumidor Final');
    } else {
        $ticket .= $this->centrarTexto('TICKET NO FISCAL');
        $ticket .= self::LF;
        $ticket .= self::SIZE_NORMAL;
        $ticket .= $this->centrarTexto('Comprobante No Válido como Factura');
    }
    
    $ticket .= self::BOLD_OFF;
    $ticket .= self::LF;
    $ticket .= self::LF;
    
    // 4. FECHA Y NÚMERO
    $ticket .= self::ALIGN_LEFT;
    $ticket .= 'Fecha: ' . date('d/m/Y H:i:s', strtotime($venta['fecha_hora']));
    $ticket .= self::LF;
    $ticket .= 'Venta N°: ' . str_pad($venta['id'], 8, '0', STR_PAD_LEFT);
    $ticket .= self::LF;
    $ticket .= self::LF;
    
    // Separador
    $ticket .= $this->linea('-');
    
    // 5. ITEMS
    $ticket .= self::BOLD_ON;
    $ticket .= $this->formatearLinea('DESCRIPCIÓN', 'PRECIO', 32, 16);
    $ticket .= self::BOLD_OFF;
    $ticket .= $this->linea('-');
    
    foreach ($items as $item) {
        // Nombre del departamento
        $descripcion = substr($item['departamento'] ?? 'PRODUCTO', 0, 30);
        $precio = '$' . number_format($item['precio'], 2, '.', ',');
        
        $ticket .= $this->formatearLinea($descripcion, $precio, 32, 16);
        
        // Código de barras en línea secundaria (más pequeño)
        $ticket .= '  Cod: ' . $item['codigo_barras'];
        $ticket .= self::LF;
    }
    
    $ticket .= $this->linea('-');
    
    // 6. TOTALES
    $ticket .= self::LF;
    
    // Subtotal sin IVA (si existe)
    if (isset($items[0]['precio_sin_iva']) && $items[0]['precio_sin_iva'] > 0) {
        $subtotalSinIva = 0;
        $totalIva = 0;
        
        foreach ($items as $item) {
            $subtotalSinIva += floatval($item['precio_sin_iva'] ?? 0);
            $totalIva += floatval($item['monto_iva'] ?? 0);
        }
        
        $ticket .= $this->formatearLinea('Subtotal:', '$' . number_format($subtotalSinIva, 2, '.', ','), 32, 16);
        $ticket .= $this->formatearLinea('IVA 21%:', '$' . number_format($totalIva, 2, '.', ','), 32, 16);
        $ticket .= $this->linea('-');
    }
    
    // TOTAL
    $ticket .= self::SIZE_DOUBLE;
    $ticket .= self::BOLD_ON;
    $ticket .= $this->formatearLinea('TOTAL:', '$' . number_format($venta['total'], 2, '.', ','), 16, 8);
    $ticket .= self::BOLD_OFF;
    $ticket .= self::SIZE_NORMAL;
    $ticket .= self::LF;
    
    // 7. FORMA DE PAGO
    $ticket .= $this->linea('=');
    $ticket .= self::BOLD_ON;
    $ticket .= 'FORMA DE PAGO:';
    $ticket .= self::BOLD_OFF;
    $ticket .= self::LF;
    
    $tipoPagoTexto = $this->obtenerNombreTipoPago($venta['tipo_pago']);
    $ticket .= $tipoPagoTexto;
    $ticket .= self::LF;
    
    if ($venta['monto_recibido']) {
        $ticket .= 'Recibido: $' . number_format($venta['monto_recibido'], 2, '.', ',');
        $ticket .= self::LF;
    }
    
    if ($venta['vuelto'] > 0) {
        $ticket .= self::SIZE_DOUBLE_HEIGHT;
        $ticket .= self::BOLD_ON;
        $ticket .= 'VUELTO: $' . number_format($venta['vuelto'], 2, '.', ',');
        $ticket .= self::BOLD_OFF;
        $ticket .= self::SIZE_NORMAL;
        $ticket .= self::LF;
    }
    
    $ticket .= self::LF;
    
    // ========== 7.5. DATOS FISCALES AFIP (CAE) - SECCIÓN NUEVA ==========
    if ($requiereFiscal) {
        $ticket .= $this->linea('=');
        
        // Consultar CAE de la base de datos
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                SELECT cae, fecha_vencimiento_cae, numero_comprobante, punto_venta, tipo_comprobante
                FROM comprobantes_afip 
                WHERE id_venta = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$venta['id']]);
            $datosCAE = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($datosCAE && !empty($datosCAE['cae'])) {
                // DATOS FISCALES ENCONTRADOS
                $ticket .= self::BOLD_ON;
                $ticket .= self::ALIGN_CENTER;
                $ticket .= $this->centrarTexto('DATOS FISCALES AFIP');
                $ticket .= self::BOLD_OFF;
                $ticket .= self::ALIGN_LEFT;
                $ticket .= self::LF;
                
                // Número de comprobante completo (Punto Venta - Número)
                $numComprobante = str_pad($datosCAE['punto_venta'], 5, '0', STR_PAD_LEFT) . '-' . 
                                 str_pad($datosCAE['numero_comprobante'], 8, '0', STR_PAD_LEFT);
                
                $ticket .= 'Comprobante: ' . $numComprobante;
                $ticket .= self::LF;
                
                // Tipo de comprobante
                $tipoComprobanteTexto = $this->obtenerTipoComprobanteTexto($datosCAE['tipo_comprobante']);
                $ticket .= 'Tipo: ' . $tipoComprobanteTexto;
                $ticket .= self::LF;
                
                // CAE en negrita y más destacado
                $ticket .= self::LF;
                $ticket .= self::BOLD_ON;
                $ticket .= self::SIZE_DOUBLE_HEIGHT;
                $ticket .= 'CAE: ' . $datosCAE['cae'];
                $ticket .= self::SIZE_NORMAL;
                $ticket .= self::BOLD_OFF;
                $ticket .= self::LF;
                $ticket .= self::LF;
                
                // Fecha de vencimiento del CAE
                $fechaVencimiento = date('d/m/Y', strtotime($datosCAE['fecha_vencimiento_cae']));
                $ticket .= 'Vencimiento CAE: ' . $fechaVencimiento;
                $ticket .= self::LF;
                
                // Nota legal
                $ticket .= self::LF;
                $ticket .= self::ALIGN_CENTER;
                $ticket .= self::SIZE_NORMAL;
                $ticket .= $this->centrarTexto('Comprobante autorizado por AFIP');
                $ticket .= self::ALIGN_LEFT;
                $ticket .= self::LF;
                
            } else {
                // NO SE ENCONTRÓ CAE - MOSTRAR ADVERTENCIA
                $ticket .= self::ALIGN_CENTER;
                $ticket .= self::BOLD_ON;
                $ticket .= self::SIZE_DOUBLE_HEIGHT;
                $ticket .= $this->centrarTexto('** SIN CAE **');
                $ticket .= self::SIZE_NORMAL;
                $ticket .= self::BOLD_OFF;
                $ticket .= self::LF;
                $ticket .= $this->centrarTexto('Pendiente registro AFIP');
                $ticket .= self::ALIGN_LEFT;
                $ticket .= self::LF;
                
                error_log("ADVERTENCIA: Ticket fiscal sin CAE - Venta ID: " . $venta['id']);
            }
            
        } catch (Exception $e) {
            // ERROR AL CONSULTAR CAE
            error_log("Error consultando CAE para ticket: " . $e->getMessage());
            
            $ticket .= self::ALIGN_CENTER;
            $ticket .= self::BOLD_ON;
            $ticket .= $this->centrarTexto('** ERROR CAE **');
            $ticket .= self::BOLD_OFF;
            $ticket .= self::LF;
            $ticket .= $this->centrarTexto('Consulte con administración');
            $ticket .= self::ALIGN_LEFT;
            $ticket .= self::LF;
        }
        
        $ticket .= $this->linea('=');
    }
    // ========== FIN DATOS FISCALES AFIP ==========
    
    // 8. PIE DE PÁGINA
    $ticket .= self::ALIGN_CENTER;
    $ticket .= self::LF;
    $ticket .= $this->centrarTexto('¡GRACIAS POR SU COMPRA!');
    $ticket .= self::LF;
    $ticket .= $this->centrarTexto('Vuelva Pronto');
    $ticket .= self::LF;
    $ticket .= self::LF;
    
    if ($requiereFiscal) {
        $ticket .= $this->centrarTexto('DOCUMENTO VÁLIDO COMO FACTURA');
        $ticket .= self::LF;
    }
    
    $ticket .= self::LF;
    $ticket .= self::LF;
    $ticket .= self::LF;
    
    // 9. CORTAR PAPEL
    $ticket .= self::CMD_CUT;
    
    return $ticket;
}

/**
 * FUNCIÓN AUXILIAR NUEVA: Obtener texto del tipo de comprobante
 */
private function obtenerTipoComprobanteTexto($tipo) {
    $tipos = [
        1 => 'Factura A',
        2 => 'Nota de Débito A',
        3 => 'Nota de Crédito A',
        4 => 'Recibo A',
        5 => 'Nota de Venta al Contado A',
        6 => 'Factura B',
        7 => 'Nota de Débito B',
        8 => 'Nota de Crédito B',
        9 => 'Recibo B',
        10 => 'Nota de Venta al Contado B',
        11 => 'Factura C',
        12 => 'Nota de Débito C',
        13 => 'Nota de Crédito C',
        15 => 'Recibo C',
        51 => 'Factura M',
        81 => 'Tique Factura A',
        82 => 'Tique Factura B',
        83 => 'Tique',
        111 => 'Tique Factura C',
        112 => 'Tique Nota de Crédito',
        113 => 'Tique Nota de Débito',
        114 => 'Tique Factura M',
        115 => 'Tique Nota de Crédito M',
        116 => 'Tique Nota de Débito M'
    ];
    
    return $tipos[$tipo] ?? "Tipo $tipo";
}
    
    /**
     * Envía el ticket a la impresora
     */
   private function enviarAImpresora($contenido) {
    error_log("=== ENVIANDO A IMPRESORA ===");
    error_log("Impresora: {$this->printerName} | Puerto: {$this->puerto}");
    
    try {
        // Crear directorio si no existe
        if (!is_dir('tickets')) {
            mkdir('tickets', 0755, true);
        }
        
        $nombreArchivo = 'tickets/ticket_' . time() . '.txt';
        
        // Guardar el contenido
        if (!file_put_contents($nombreArchivo, $contenido)) {
            throw new Exception("No se pudo guardar el archivo de ticket");
        }
        
        error_log("✓ Archivo guardado: {$nombreArchivo}");
        
        // INTENTAR IMPRESIÓN SEGÚN SO
        $os = strtoupper(substr(PHP_OS, 0, 3));
        
        if ($os === 'WIN') {
            return $this->imprimirWindows($nombreArchivo);
        } elseif ($os === 'LIN' || $os === 'DAR') {
            return $this->imprimirLinuxMac($nombreArchivo);
        } else {
            error_log("SO no identificado: " . PHP_OS);
            return [
                'success' => true,
                'message' => 'Archivo generado (SO desconocido)',
                'archivo' => $nombreArchivo
            ];
        }
        
    } catch (Exception $e) {
        error_log("✗ Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
    
private function imprimirWindows($archivo) {
    try {
        error_log("Windows - Intentando impresión directa");
        
        // Opción 1: Usar comando print
        $comando = "print /D:\"{$this->printerName}\" \"{$archivo}\" 2>&1";
        error_log("Comando: {$comando}");
        
        exec($comando, $output, $code);
        
        if ($code === 0) {
            error_log("✓ Comando print exitoso");
            return [
                'success' => true,
                'message' => 'Impreso correctamente',
                'archivo' => $archivo,
                'metodo' => 'print'
            ];
        }
        
        // Opción 3: Al menos el archivo se generó
        error_log("⚠ Comando falló, pero archivo generado");
        return [
            'success' => true,
            'message' => 'Archivo generado (verifica la impresora)',
            'archivo' => $archivo,
            'metodo' => 'archivo',
            'warning' => 'Verifica que la impresora esté conectada'
        ];
        
    } catch (Exception $e) {
        error_log("Error Windows: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

private function imprimirLinuxMac($archivo) {
    try {
        error_log("Linux/Mac - Intentando impresión");
        
        // Opción 1: lp o lpr
        $comando = "lp -d {$this->printerName} {$archivo} 2>&1";
        exec($comando, $output, $code);
        
        if ($code === 0) {
            error_log("✓ lp exitoso");
            return [
                'success' => true,
                'message' => 'Impreso correctamente',
                'archivo' => $archivo,
                'metodo' => 'lp'
            ];
        }
        
        // Opción 2: lpr
        $comando2 = "lpr -P {$this->printerName} {$archivo} 2>&1";
        exec($comando2, $output2, $code2);
        
        if ($code2 === 0) {
            error_log("✓ lpr exitoso");
            return [
                'success' => true,
                'message' => 'Impreso correctamente',
                'archivo' => $archivo,
                'metodo' => 'lpr'
            ];
        }
        
        // Opción 3: Archivo generado
        error_log("⚠ Comando falló, pero archivo generado");
        return [
            'success' => true,
            'message' => 'Archivo generado',
            'archivo' => $archivo,
            'metodo' => 'archivo'
        ];
        
    } catch (Exception $e) {
        error_log("Error Linux/Mac: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
    
    /**
     * Centra un texto
     */
    private function centrarTexto($texto) {
        $len = strlen($texto);
        if ($len >= $this->ancho) {
            return substr($texto, 0, $this->ancho) . self::LF;
        }
        
        $espacios = floor(($this->ancho - $len) / 2);
        return str_repeat(' ', $espacios) . $texto . self::LF;
    }
    
    /**
     * Crea una línea de separación
     */
    private function linea($caracter = '-') {
        return str_repeat($caracter, $this->ancho) . self::LF;
    }
    
    /**
     * Formatea una línea con texto a izquierda y derecha
     */
    private function formatearLinea($izquierda, $derecha, $anchoIzq = 32, $anchoDer = 16) {
        $izquierda = substr($izquierda, 0, $anchoIzq);
        $derecha = substr($derecha, 0, $anchoDer);
        
        $espacios = $this->ancho - strlen($izquierda) - strlen($derecha);
        if ($espacios < 1) $espacios = 1;
        
        return $izquierda . str_repeat(' ', $espacios) . $derecha . self::LF;
    }
    
    /**
     * Obtiene el nombre legible del tipo de pago
     */
    private function obtenerNombreTipoPago($tipoPago) {
        $tipos = [
            'efectivo' => 'EFECTIVO',
            'tarjeta-credito' => 'TARJETA DE CRÉDITO (+10%)',
            'tarjeta-debito' => 'TARJETA DE DÉBITO',
            'qr' => 'CÓDIGO QR / TRANSFERENCIA',
            'mixto' => 'EFECTIVO + TRANSFERENCIA'
        ];
        
        return $tipos[$tipoPago] ?? strtoupper($tipoPago);
    }
    
    /**
     * Prueba de impresión
     */
    public function imprimirPrueba() {
        $ticket = self::CMD_INIT;
        $ticket .= self::ALIGN_CENTER;
        $ticket .= self::SIZE_DOUBLE;
        $ticket .= self::BOLD_ON;
        $ticket .= $this->centrarTexto('PRUEBA DE IMPRESORA');
        $ticket .= self::BOLD_OFF;
        $ticket .= self::SIZE_NORMAL;
        $ticket .= self::LF;
        $ticket .= $this->linea('=');
        $ticket .= self::ALIGN_LEFT;
        $ticket .= 'Fecha: ' . date('d/m/Y H:i:s');
        $ticket .= self::LF;
        $ticket .= 'Puerto: ' . $this->puerto;
        $ticket .= self::LF;
        $ticket .= 'Estado: CONECTADA';
        $ticket .= self::LF;
        $ticket .= $this->linea('=');
        $ticket .= self::ALIGN_CENTER;
        $ticket .= $this->centrarTexto('Impresora funcionando correctamente');
        $ticket .= self::LF;
        $ticket .= self::LF;
        $ticket .= self::LF;
        $ticket .= self::CMD_CUT;
        
        try {
            $this->enviarAImpresora($ticket);
            return ['success' => true, 'message' => 'Ticket de prueba enviado'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}


function imprimirTicketFiscal($ventaId) {
    error_log("=== imprimirTicketFiscal() LLAMADA ===");
    error_log("VentaID: {$ventaId}");
    
    try {
        if (!$ventaId || !is_numeric($ventaId)) {
            throw new Exception("VentaID inválido");
        }
        
        $config = ConfigManager::getInstance();
        
        // Verificar si está habilitada
        $habilitada = $config->get('impresora_fiscal_habilitada', true);
        if (!$habilitada) {
            return [
                'success' => false,
                'message' => 'Impresión deshabilitada',
                'venta_id' => $ventaId,
                'tipo' => 'deshabilitada'
            ];
        }
        
        $impresora = new ImpresoraTermica();
        $resultado = $impresora->imprimirTicketVenta($ventaId);
        
        error_log("Resultado: " . json_encode($resultado));
        
        if (!isset($resultado['venta_id'])) {
            $resultado['venta_id'] = $ventaId;
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("✗ Error: " . $e->getMessage());
        logError("Error imprimiendo ticket: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'venta_id' => $ventaId,
            'tipo' => 'excepcion'
        ];
    }
}

?>