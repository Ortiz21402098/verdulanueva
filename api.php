<?php
// api.php - API corregida para el sistema de caja del mini supermercado

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
require_once 'venta.php';
require_once 'ImpresoraFiscal3nStar.php';
require_once 'ExcelPlanillaXLSX.php';

try {
    // Obtener datos de la request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        jsonResponse(false, null, 'Acción no especificada');
    }
    
    $action = $input['action'];
    $venta = new Venta();
    
    switch ($action) {
        case 'nueva_venta':
            try {
                error_log("API: Procesando nueva_venta");
                $ventaId = $venta->crearVenta();
                error_log("API: Venta creada con ID: " . $ventaId);
                jsonResponse(true, ['venta_id' => $ventaId], 'Nueva venta creada');
            } catch (Exception $e) {
                error_log("API: Error en nueva_venta: " . $e->getMessage());
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'agregar_item':
            try {
                $ventaId = $input['venta_id'] ?? null;
                $codigoBarras = $input['codigo_barras'] ?? null;
                
                if (!$ventaId || !$codigoBarras) {
                    jsonResponse(false, null, 'Datos incompletos: se requiere venta_id y codigo_barras');
                }
                
                $resultado = $venta->agregarItem($ventaId, $codigoBarras);
                jsonResponse(true, $resultado, 'Item agregado exitosamente');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;

        case 'eliminar_item':
    try {
        $ventaId = $input['venta_id'] ?? null;
        $codigoBarras = $input['codigo_barras'] ?? null;
        
        if (!$ventaId || !$codigoBarras) {
            jsonResponse(false, null, 'Datos incompletos: se requiere venta_id y codigo_barras');
        }
        
        // Eliminar el item usando la clase Venta
        $resultado = $venta->eliminarItem($ventaId, $codigoBarras);
        jsonResponse(true, $resultado, 'Item eliminado correctamente');
        
    } catch (Exception $e) {
        jsonResponse(false, null, $e->getMessage());
    }
    break;
            
        case 'obtener_venta':
            try {
                $ventaId = $input['venta_id'] ?? null;
                
                if (!$ventaId) {
                    jsonResponse(false, null, 'ID de venta requerido');
                }
                
                $resultado = $venta->obtenerVenta($ventaId);
                jsonResponse(true, $resultado, 'Venta obtenida');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'procesar_pago':
    try {
        $ventaId = $input['venta_id'] ?? null;
        $tipoPago = $input['tipo_pago'] ?? null;
        $montoRecibido = $input['monto_recibido'] ?? null;
        $montoEfectivo = $input['monto_efectivo'] ?? null;
        $montoTransferencia = $input['monto_transferencia'] ?? null;
        
        if (!$ventaId || !$tipoPago) {
            jsonResponse(false, null, 'Datos de pago incompletos');
        }
        
        // MODIFICADO: Usar procesarPago() que actualiza estado a 'pagado'
        if ($tipoPago === 'ambos') {
            // Para pago mixto, usar lógica especial
            $resultado = $venta->procesarPagoMixto($ventaId, $montoEfectivo, $montoTransferencia);
        } else {
            $resultado = $venta->procesarPago($ventaId, $tipoPago, $montoRecibido);
        }
        
        // Agregar venta_id al resultado
        $resultado['venta_id'] = $ventaId;
        $resultado['tipo_pago'] = $tipoPago;
        
        jsonResponse(true, $resultado, 'Pago procesado y venta finalizada');
        
    } catch (Exception $e) {
        jsonResponse(false, null, $e->getMessage());
    }
    break;

        case 'finalizar_venta':
            try {
                $ventaId = $input['venta_id'] ?? null;
                $datosPago = $input['datos_pago'] ?? null;
                $items = $input['items'] ?? null;
                
                if (!$ventaId || !$datosPago) {
                    jsonResponse(false, null, 'Datos incompletos para finalizar venta');
                }
                
                $resultado = $venta->finalizarVenta($ventaId, $datosPago, $items);
                
                // Procesar impresión fiscal si es necesario
                if (in_array($datosPago['tipo_pago'], ['tarjeta', 'qr'])) {
                    $impresoraResult = procesarImpresionFiscal($ventaId, $datosPago['tipo_pago']);
                    $resultado['impresion_fiscal'] = $impresoraResult;
                }
                
                jsonResponse(true, $resultado, 'Venta finalizada exitosamente');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'cancelar_venta':
            try {
                $ventaId = $input['venta_id'] ?? null;
                
                if (!$ventaId) {
                    jsonResponse(false, null, 'ID de venta requerido');
                }
                
                $venta->cancelarVenta($ventaId);
                jsonResponse(true, null, 'Venta cancelada');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'resumen_diario':
            try {
                $fecha = $input['fecha'] ?? null;
                $resumen = $venta->resumenDiario($fecha);
                jsonResponse(true, $resumen, 'Resumen obtenido');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'test_codigo':
            try {
                $codigoBarras = $input['codigo_barras'] ?? null;
                
                if (!$codigoBarras) {
                    jsonResponse(false, null, 'Código de barras requerido');
                }
                
                $decoder = new CodigoBarras();
                $resultado = $decoder->decodificarPrecio($codigoBarras);
                jsonResponse(true, $resultado, 'Código decodificado');
                
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        // FUNCIONALIDADES PARA IMPRESORA FISCAL
        case 'estado_impresora':
            try {
                $resultado = verificarEstadoImpresora();
                jsonResponse(true, $resultado, 'Estado de impresora obtenido');
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'imprimir_ticket_fiscal':
            try {
                $ventaId = $input['venta_id'] ?? null;
                if (!$ventaId) {
                    jsonResponse(false, null, 'ID de venta requerido');
                }
                
                $resultado = procesarImpresionFiscal($ventaId, 'manual');
                jsonResponse(true, $resultado, 'Ticket fiscal procesado');
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'cierre_diario':
            try {
                $resultado = realizarCierreDiario();
                jsonResponse(true, $resultado, 'Cierre diario procesado');
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        case 'reporte_x':
            try {
                $resultado = generarReporteX();
                jsonResponse(true, $resultado, 'Reporte X generado');
            } catch (Exception $e) {
                jsonResponse(false, null, $e->getMessage());
            }
            break;
            
        default:
            jsonResponse(false, null, 'Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    logError('Error en API: ' . $e->getMessage());
    jsonResponse(false, null, 'Error interno del servidor');
}

function procesarImpresionFiscal($ventaId, $tipoPago) {
    try {
        $config = ConfigManager::getInstance();
        
        // Verificar si la impresión fiscal está habilitada
        if (!$config->get('impresora_fiscal_habilitada', true)) {
            return [
                'impreso' => false,
                'motivo' => 'Impresión fiscal deshabilitada en configuración',
                'requiere_atencion' => false
            ];
        }
        
        // Obtener datos de la venta
        $venta = new Venta();
        $ventaData = $venta->obtenerVenta($ventaId);
        
        if (!$ventaData || empty($ventaData['items'])) {
            return [
                'impreso' => false,
                'motivo' => 'Datos de venta no válidos',
                'requiere_atencion' => true
            ];
        }
        
        // Crear instancia de la impresora
        $puerto = $config->get('impresora_fiscal_puerto', 'COM1');
        $impresora = new ImpresoraTermica($puerto);
        
        // Estructurar datos para la impresora
        $datosVenta = [
            'id' => $ventaData['venta']['id'],
            'total' => $ventaData['venta']['total'],
            'tipo_pago' => $tipoPago,
            'items' => []
        ];
        
        // Convertir items al formato requerido por la impresora
        foreach ($ventaData['items'] as $item) {
            $datosVenta['items'][] = [
                'descripcion' => substr($item['departamento'] ?? 'PRODUCTO', 0, 20),
                'codigo' => $item['codigo_barras'],
                'precio' => floatval($item['precio']),
                'cantidad' => 1
            ];
        }
        
        // Imprimir ticket fiscal
        $resultado = $impresora->imprimirTicketVenta($datosVenta);
        
        // Registrar resultado en log
        if ($resultado['success']) {
            logOperacion($ventaId, 'FISCAL_IMPRESO', "Ticket fiscal impreso - Tipo: {$tipoPago}");
        } else {
            logOperacion($ventaId, 'FISCAL_ERROR', "Error impresión: " . $resultado['message']);
        }
        
        return [
            'impreso' => $resultado['success'],
            'motivo' => $resultado['message'],
            'detalles' => $resultado,
            'requiere_atencion' => !$resultado['success'],
            'tipo_pago' => $tipoPago,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        logError("Error en procesarImpresionFiscal: " . $e->getMessage());
        logOperacion($ventaId, 'FISCAL_EXCEPCION', "Excepción: " . $e->getMessage());
        
        return [
            'impreso' => false,
            'motivo' => 'Error de sistema: ' . $e->getMessage(),
            'requiere_atencion' => true,
            'error_tecnico' => $e->getMessage()
        ];
    }
}

function verificarEstadoImpresora() {
    try {
        $config = ConfigManager::getInstance();
        $puerto = $config->get('impresora_fiscal_puerto', 'COM1');
        
        $impresora = new ImpresoraTermica($puerto);
        $estado = $impresora->obtenerEstado();
        
        return [
            'habilitada' => $config->get('impresora_fiscal_habilitada', true),
            'puerto' => $puerto,
            'modelo' => $config->get('impresora_fiscal_modelo', 'RPT001'),
            'estado_conexion' => $estado,
            'verificado_en' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'habilitada' => false,
            'error' => $e->getMessage(),
            'verificado_en' => date('Y-m-d H:i:s')
        ];
    }
}

function realizarCierreDiario() {
    try {
        $config = ConfigManager::getInstance();
        
        if (!$config->get('impresora_fiscal_habilitada', true)) {
            return [
                'success' => false,
                'message' => 'Impresora fiscal no habilitada'
            ];
        }
        
        $puerto = $config->get('impresora_fiscal_puerto', 'COM1');
        $impresora = new ImpresoraTermica($puerto);
        
        $resultado = $impresora->cierreDiario();
        
        if ($resultado['success']) {
            logOperacion(null, 'CIERRE_DIARIO', 'Cierre diario fiscal realizado');
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        logError("Error en cierre diario: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function generarReporteX() {
    try {
        $config = ConfigManager::getInstance();
        
        if (!$config->get('impresora_fiscal_habilitada', true)) {
            return [
                'success' => false,
                'message' => 'Impresora fiscal no habilitada'
            ];
        }
        
        $puerto = $config->get('impresora_fiscal_puerto', 'COM1');
        $impresora = new ImpresoraTermica($puerto);
        
        $resultado = $impresora->reporteX();
        
        if ($resultado['success']) {
            logOperacion(null, 'REPORTE_X', 'Reporte X fiscal generado');
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        logError("Error en reporte X: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

function generarTicketFiscal($ventaData, $pagoData) {
    $venta = $ventaData['venta'];
    $items = $ventaData['items'];
    
    $html = '<div class="ticket-fiscal" style="font-family: monospace; width: 300px; margin: 0 auto; background: white; padding: 20px; border: 1px solid #ccc;">';
    
    // Encabezado
    $html .= '<div style="text-align: center; border-bottom: 1px dashed #333; padding-bottom: 10px; margin-bottom: 10px;">';
    $html .= '<h3>' . NOMBRE_NEGOCIO . '</h3>';
    $html .= '<p>' . DIRECCION_NEGOCIO . '</p>';
    
    // Indicar si es documento fiscal o no
    if (isset($pagoData['impresion_fiscal']) && $pagoData['impresion_fiscal']['impreso']) {
        $html .= '<p><strong>DOCUMENTO FISCAL IMPRESO</strong></p>';
    } else {
        $html .= '<p>DOCUMENTO NO FISCAL</p>';
    }
    $html .= '</div>';
    
    // Fecha y hora
    $html .= '<div style="margin-bottom: 10px;">';
    $html .= '<p>Fecha: ' . date('d/m/Y H:i:s', strtotime($venta['fecha_hora'])) . '</p>';
    $html .= '<p>Venta N°: ' . str_pad($venta['id'], 6, '0', STR_PAD_LEFT) . '</p>';
    $html .= '</div>';
    
    // Items
    $html .= '<div style="border-bottom: 1px dashed #333; padding-bottom: 10px; margin-bottom: 10px;">';
    foreach ($items as $item) {
        $html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
        $html .= '<span>' . $item['departamento'] . '</span>';
        $html .= '<span>' . formatearPrecio($item['precio']) . '</span>';
        $html .= '</div>';
        $html .= '<div style="font-size: 0.8em; color: #666;">';
        $html .= 'Código: ' . $item['codigo_barras'];
        $html .= '</div>';
    }
    $html .= '</div>';
    
    // Total
    $html .= '<div style="font-weight: bold; font-size: 1.2em; margin-bottom: 10px;">';
    $html .= '<div style="display: flex; justify-content: space-between;">';
    $html .= '<span>TOTAL:</span>';
    $html .= '<span>' . formatearPrecio($venta['total']) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Pago
    $html .= '<div style="border-top: 1px dashed #333; padding-top: 10px; margin-top: 10px;">';
    $html .= '<p>Tipo de pago: ' . strtoupper(TIPOS_PAGO[$venta['tipo_pago']]) . '</p>';
    $html .= '<p>Monto recibido: ' . formatearPrecio($pagoData['monto_recibido']) . '</p>';
    
    if ($pagoData['vuelto'] > 0) {
        $html .= '<p><strong>VUELTO: ' . formatearPrecio($pagoData['vuelto']) . '</strong></p>';
    }
    
    // Información de impresión fiscal
    if (isset($pagoData['impresion_fiscal'])) {
        $impresion = $pagoData['impresion_fiscal'];
        if ($impresion['impreso']) {
            $html .= '<p style="color: green;">✓ Ticket fiscal impreso correctamente</p>';
        } else {
            $html .= '<p style="color: red;">⚠ Ticket fiscal no impreso: ' . $impresion['motivo'] . '</p>';
        }
    }
    
    $html .= '</div>';
    
    // Pie
    $html .= '<div style="text-align: center; margin-top: 20px; border-top: 1px dashed #333; padding-top: 10px;">';
    $html .= '<p>¡Gracias por su compra!</p>';
    $html .= '<p style="font-size: 0.8em;">Sistema POS - Mini Supermercado</p>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}
?>