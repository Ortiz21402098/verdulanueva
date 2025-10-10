<?php
//ARCHIVO VENTA.PHP
require_once 'config.php';
require_once 'CodigoBarras.php';
require_once 'CalculadoraIVA.php';
require_once 'AFIPFacturacion.php';
require_once 'ImpresoraFiscal3nStar.php';

// Verificar que la función de impresión existe
if (!function_exists('imprimirTicketFiscal')) {
    error_log("ADVERTENCIA CRÍTICA: La función imprimirTicketFiscal() no está definida");
    error_log("Archivos cargados: " . implode(', ', get_included_files()));
}

class Venta {
    private $db;
    private $codigoBarras;
    private $afip;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->codigoBarras = new CodigoBarras();
        
        // Inicializar AFIP si está configurado
        $config = ConfigManager::getInstance();
        if ($config->get('afip_habilitado', false)) {
            try {
                $this->afip = new AFIPFacturacion([
                    'cuit' => $config->get('afip_cuit', ''),
                    'certificado' => $config->get('afip_certificado', 'certificados/afip.crt'),
                    'clave_privada' => $config->get('afip_clave_privada', 'certificados/afip.key'),
                    'punto_venta' => $config->get('afip_punto_venta', 1),
                    'ambiente' => $config->get('afip_ambiente', 2)
                ]);
            } catch (Exception $e) {
                error_log("Error inicializando AFIP: " . $e->getMessage());
                $this->afip = null;
            }
        }
    }
    
    public function crearVenta() {
        try {
            error_log("Intentando crear nueva venta...");
            
            $stmt = $this->db->prepare("
                INSERT INTO `ventas` (
                    total, tipo_pago, monto_recibido, vuelto, ticket_fiscal, estado, fecha_hora
                ) VALUES (
                    0, NULL, NULL, NULL, 0, 'pendiente', NOW()
                )
            ");
            $stmt->execute();
            
            $ventaId = $this->db->lastInsertId();
            error_log("Nueva venta creada con ID: " . $ventaId);
            
            return $ventaId;
        } catch (PDOException $e) {
            error_log("Error al crear venta: " . $e->getMessage());
            throw new Exception("Error al crear venta: " . $e->getMessage());
        }
    }

    public function procesarPagoMixto($ventaId, $montoEfectivo, $montoTransferencia) {
        try {
            $this->db->beginTransaction();
            
            // Obtener total de la venta
            $stmt = $this->db->prepare("SELECT total FROM ventas WHERE id = ?");
            $stmt->execute([$ventaId]);
            $venta = $stmt->fetch();
            
            if (!$venta) {
                throw new Exception("Venta no encontrada");
            }
            
            $total = $venta['total'];
            $montoTotal = $montoEfectivo + $montoTransferencia;
            
            if ($montoTotal < $total) {
                throw new Exception("Monto insuficiente");
            }
            
            $vuelto = $montoTotal - $total;
            
            // Actualizar venta principal
            $stmt = $this->db->prepare("
                UPDATE ventas 
                SET total = ?, tipo_pago = 'mixto', monto_recibido = ?, vuelto = ?, estado = 'pagado'
                WHERE id = ?
            ");
            $stmt->execute([$total, $montoTotal, $vuelto, $ventaId]);
            
            // Insertar detalles del pago mixto
            $stmt = $this->db->prepare("DELETE FROM venta_pagos_detalle WHERE venta_id = ?");
            $stmt->execute([$ventaId]);
            
            // Insertar detalle del efectivo (si es mayor a 0)
            if ($montoEfectivo > 0) {
                $stmt = $this->db->prepare("
                    INSERT INTO venta_pagos_detalle (venta_id, tipo_pago, monto, fecha_registro) 
                    VALUES (?, 'efectivo', ?, NOW())
                ");
                $stmt->execute([$ventaId, $montoEfectivo]);
            }
            
            // Insertar detalle de la transferencia (si es mayor a 0)
            if ($montoTransferencia > 0) {
                $stmt = $this->db->prepare("
                    INSERT INTO venta_pagos_detalle (venta_id, tipo_pago, monto, fecha_registro) 
                    VALUES (?, 'transferencia', ?, NOW())
                ");
                $stmt->execute([$ventaId, $montoTransferencia]);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'total' => $total,
                'monto_recibido' => $montoTotal,
                'monto_efectivo' => $montoEfectivo,
                'monto_transferencia' => $montoTransferencia,
                'vuelto' => $vuelto,
                'ticket_fiscal' => false
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error en procesarPagoMixto: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar un item específico de la venta
     */
    public function eliminarItem($ventaId, $codigoBarras) {
        try {
            // Verificar que la venta existe y está activa
            $stmt = $this->db->prepare("SELECT estado FROM ventas WHERE id = ?");
            $stmt->execute([$ventaId]);
            $venta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venta) {
                throw new Exception('Venta no encontrada');
            }
            
            if ($venta['estado'] !== 'pendiente') {
                throw new Exception('No se puede eliminar items de una venta finalizada');
            }
            
            // Eliminar el item específico (solo el primero si hay duplicados)
            $stmt = $this->db->prepare("DELETE FROM venta_items WHERE venta_id = ? AND codigo_barras = ? LIMIT 1");
            $resultado = $stmt->execute([$ventaId, $codigoBarras]);
            
            if (!$resultado || $stmt->rowCount() === 0) {
                throw new Exception('No se encontró el item para eliminar');
            }
            
            // Actualizar el total de la venta
            $this->actualizarTotalVenta($ventaId);
            
            // Obtener nuevo total y contar items restantes
            $stmt = $this->db->prepare("SELECT total FROM ventas WHERE id = ?");
            $stmt->execute([$ventaId]);
            $nuevoTotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $itemsRestantes = $this->contarItemsVenta($ventaId);
            
            return [
                'item_eliminado' => $codigoBarras,
                'nuevo_total' => $nuevoTotal,
                'items_restantes' => $itemsRestantes
            ];
            
        } catch (Exception $e) {
            error_log("Error en eliminarItem: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Contar items en una venta
     */
    private function contarItemsVenta($ventaId) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM venta_items WHERE venta_id = ?");
            $stmt->execute([$ventaId]);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error contando items: " . $e->getMessage());
            return 0;
        }
    }

    public function agregarItem($ventaId, $codigoBarras) {
        try {
            // Verificar que la venta esté activa
            if (!$this->ventaEstaActiva($ventaId)) {
                throw new Exception("No hay una venta activa con ID: " . $ventaId);
            }
            
            // Decodificar el código de barras
            $resultado = $this->codigoBarras->decodificarPrecio($codigoBarras);
            
            if (!$resultado['valido']) {
                throw new Exception("Código de barras inválido: " . $resultado['error']);
            }
            
            $departamentoId = $resultado['departamento_id'];
            $precioConIVA = $resultado['precio'];
            
            // NUEVO: Calcular IVA automáticamente
            $calculoIVA = CalculadoraIVA::calcularIVAInverso($precioConIVA, $departamentoId);
            
            // Verificar si ya existe la columna precio_sin_iva, si no existe, agregarla
            $this->verificarColumnasIVA();
            
            // Insertar el item con información de IVA
            $stmt = $this->db->prepare("
                INSERT INTO venta_items (
                    venta_id, departamento_id, codigo_barras, precio, 
                    precio_sin_iva, monto_iva, porcentaje_iva, fecha_ticket
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $ventaId, 
                $departamentoId, 
                $codigoBarras, 
                $precioConIVA,
                $calculoIVA['precio_sin_iva'],
                $calculoIVA['monto_iva'], 
                $calculoIVA['porcentaje_iva']
            ]);
            
            // Actualizar el total de la venta
            $this->actualizarTotalVenta($ventaId);
            
            return [
                'success' => true,
                'precio' => $precioConIVA,
                'precio_sin_iva' => $calculoIVA['precio_sin_iva'],
                'monto_iva' => $calculoIVA['monto_iva'],
                'porcentaje_iva' => $calculoIVA['porcentaje_iva'],
                'codigo' => $codigoBarras,
                'departamento' => $resultado['departamento'],
                'departamento_id' => $departamentoId
            ];
            
        } catch (PDOException $e) {
            throw new Exception("Error al agregar item: " . $e->getMessage());
        }
    }

    /**
     * Verificar y crear columnas de IVA si no existen
     */
    private function verificarColumnasIVA() {
        try {
            // Verificar si las columnas existen
            $stmt = $this->db->prepare("SHOW COLUMNS FROM venta_items LIKE 'precio_sin_iva'");
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Agregar columnas de IVA
                $this->db->exec("
                    ALTER TABLE venta_items 
                    ADD COLUMN precio_sin_iva DECIMAL(10,2) DEFAULT 0,
                    ADD COLUMN monto_iva DECIMAL(10,2) DEFAULT 0,
                    ADD COLUMN porcentaje_iva DECIMAL(5,2) DEFAULT 0
                ");
                error_log("Columnas de IVA agregadas a venta_items");
            }
        } catch (PDOException $e) {
            error_log("Error verificando columnas IVA: " . $e->getMessage());
        }
    }

    private function ventaEstaActiva($ventaId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, estado 
                FROM ventas 
                WHERE id = ? AND estado = 'pendiente'
            ");
            $stmt->execute([$ventaId]);
            $venta = $stmt->fetch();
            
            return $venta !== false;
        } catch (PDOException $e) {
            logError("Error verificando venta activa: " . $e->getMessage());
            return false;
        }
    }
    
    private function actualizarTotalVenta($ventaId) {
        $stmt = $this->db->prepare("
            UPDATE ventas 
            SET total = (
                SELECT COALESCE(SUM(precio), 0) 
                FROM venta_items 
                WHERE venta_id = ?
            ) 
            WHERE id = ?
        ");
        $stmt->execute([$ventaId, $ventaId]);
    }

    
    
    public function obtenerVenta($ventaId) {
        try {
            // Obtener datos de la venta
            $stmt = $this->db->prepare("SELECT * FROM ventas WHERE id = ?");
            $stmt->execute([$ventaId]);
            $venta = $stmt->fetch();
            
            if (!$venta) {
                throw new Exception("Venta no encontrada");
            }
            
            // Obtener items de la venta
            $stmt = $this->db->prepare("
                SELECT vi.*, d.nombre as departamento 
                FROM venta_items vi 
                JOIN departamentos d ON vi.departamento_id = d.id 
                WHERE vi.venta_id = ? 
                ORDER BY vi.fecha_ticket
            ");
            $stmt->execute([$ventaId]);
            $items = $stmt->fetchAll();
            
            return [
                'venta' => $venta,
                'items' => $items
            ];
            
        } catch (PDOException $e) {
            throw new Exception("Error al obtener venta: " . $e->getMessage());
        }
    }
    

  /**
 * Procesar pago CON INTEGRACIÓN AUTOMÁTICA DE AFIP E IMPRESIÓN (MEJORADO)
 */
public function procesarPago($ventaId, $tipoPago, $montoRecibido = null) {
    $transactionStarted = false;

    try {
        if (!$this->ventaEstaActiva($ventaId)) {
            throw new Exception("No hay una venta activa para procesar el pago");
        }
        
        $stmt = $this->db->prepare("SELECT total FROM ventas WHERE id = ?");
        $stmt->execute([$ventaId]);
        $venta = $stmt->fetch();
        
        if (!$venta) {
            throw new Exception("Venta no encontrada");
        }
        
        $this->db->beginTransaction();
        $transactionStarted = true;
        
        $total = $venta['total'];
        $vuelto = 0;
        $ticketFiscal = false;
        $requiereAFIP = false;
        
        // Validar el pago según el tipo
        switch ($tipoPago) {
            case 'efectivo':
                if ($montoRecibido === null || $montoRecibido < $total) {
                    throw new Exception("Monto insuficiente para pago en efectivo");
                }
                $vuelto = $montoRecibido - $total;
                $ticketFiscal = false;
                $requiereAFIP = false;
                break;
                
            case 'tarjeta-credito':
                $total = $total * 1.10;
                $montoRecibido = $total;
                $vuelto = 0;
                $ticketFiscal = true;
                $requiereAFIP = true;
                break;

            case 'tarjeta-debito':
                $montoRecibido = $total;
                $vuelto = 0;
                $ticketFiscal = true;
                $requiereAFIP = true;
                break;
                
            case 'qr':
                $montoRecibido = $total;
                $vuelto = 0;
                $ticketFiscal = true;
                $requiereAFIP = true;
                break;
                
            default:
                throw new Exception("Tipo de pago no válido");
        }
        
        // Actualizar la venta
        $stmt = $this->db->prepare("
            UPDATE ventas 
            SET total = ?, tipo_pago = ?, monto_recibido = ?, vuelto = ?, ticket_fiscal = ?, estado = 'pagado'
            WHERE id = ?
        ");
        $stmt->execute([$total, $tipoPago, $montoRecibido, $vuelto, $ticketFiscal, $ventaId]);

        // Registrar detalle del pago
        $stmt = $this->db->prepare("DELETE FROM venta_pagos_detalle WHERE venta_id = ?");
        $stmt->execute([$ventaId]);

        $stmt = $this->db->prepare("
            INSERT INTO venta_pagos_detalle (venta_id, tipo_pago, monto, fecha_registro) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$ventaId, $tipoPago, $montoRecibido]);
        
        $this->db->commit();
        $transactionStarted = false;
        
        error_log("=== PROCESANDO PAGO VENTA {$ventaId} ===");
        error_log("Tipo: {$tipoPago} | Fiscal: " . ($ticketFiscal ? 'SI' : 'NO'));
        
        $comprobanteAFIP = null;
        $resultadoImpresion = null;
        
        // *** PROCESAR AFIP Y GUARDAR EN BD ***
        if ($requiereAFIP && $this->afip) {
            try {
                error_log("Procesando AFIP...");
                $comprobanteAFIP = $this->afip->crearComprobante([
                    'venta_id' => $ventaId,
                    'total' => $total,
                    'tipo_pago' => $tipoPago
                ]);
                
                if ($comprobanteAFIP && $comprobanteAFIP['success']) {
                    // GUARDAR COMPROBANTE EN BD
                    $this->guardarComprobanteAFIP($ventaId, $comprobanteAFIP);
                    error_log("✓ Comprobante AFIP guardado en BD - CAE: " . $comprobanteAFIP['cae']);
                    logOperacion($ventaId, 'AFIP_OK', "CAE: " . $comprobanteAFIP['cae']);
                }
            } catch (Exception $e) {
                error_log("Error AFIP: " . $e->getMessage());
                logError("Error AFIP venta {$ventaId}: " . $e->getMessage());
            }
        }

        // *** IMPRESIÓN AUTOMÁTICA ***
        if ($ticketFiscal) {
            try {
                error_log(">>> INICIANDO IMPRESIÓN AUTOMÁTICA <<<");
                
                if (!function_exists('imprimirTicketFiscal')) {
                    throw new Exception("Función imprimirTicketFiscal() no existe");
                }
                
                $resultadoImpresion = imprimirTicketFiscal($ventaId);
                
                error_log("Resultado: " . json_encode($resultadoImpresion));
                
                if ($resultadoImpresion && $resultadoImpresion['success']) {
                    error_log("✓ TICKET IMPRESO");
                    logOperacion($ventaId, 'IMPRESION_OK', "Tipo: {$tipoPago}");
                } else {
                    $msg = $resultadoImpresion['message'] ?? 'Desconocido';
                    error_log("✗ ERROR: " . $msg);
                    logError("Impresión falló venta {$ventaId}: " . $msg);
                }
            } catch (Exception $e) {
                error_log("✗ EXCEPCIÓN: " . $e->getMessage());
                
                $resultadoImpresion = [
                    'success' => false,
                    'message' => 'Excepción: ' . $e->getMessage(),
                    'error_tipo' => 'excepcion'
                ];
            }
        }
        
        error_log("=== FIN PROCESAMIENTO ===\n");
        
        return [
            'success' => true,
            'venta_id' => $ventaId,
            'total' => $total,
            'monto_recibido' => $montoRecibido,
            'vuelto' => $vuelto,
            'ticket_fiscal' => $ticketFiscal,
            'tipo_pago' => $tipoPago,
            'comprobante_afip' => $comprobanteAFIP,
            'impreso_automaticamente' => $ticketFiscal,
            'resultado_impresion' => $resultadoImpresion
        ];
        
    } catch (Exception $e) {
        if ($transactionStarted && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
        error_log("ERROR procesarPago: " . $e->getMessage());
        throw $e;
    }
}

private function guardarComprobanteAFIP($ventaId, $datosAFIP) {
    try {
        // Insertar en tabla comprobantes_afip
        $stmt = $this->db->prepare("
            INSERT INTO comprobantes_afip (
                id_venta,
                tipo_comprobante,
                numero_comprobante,
                punto_venta,
                cae,
                fecha_cae,
                fecha_vencimiento_cae,
                fecha_proceso,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $ventaId,
            $datosAFIP['tipo_comprobante'] ?? 'C',
            $datosAFIP['numero_comprobante'] ?? 0,
            $datosAFIP['punto_venta'] ?? 1,
            $datosAFIP['cae'] ?? '',
            $datosAFIP['fecha_cae'] ?? date('Y-m-d'),
            $datosAFIP['fecha_vencimiento_cae'] ?? date('Y-m-d', strtotime('+60 days')),
            $datosAFIP['estado'] ?? 'activo'
        ]);
        
        error_log("✓ Comprobante AFIP guardado - ID: " . $this->db->lastInsertId());
        
        return $this->db->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error guardando comprobante AFIP: " . $e->getMessage());
        throw new Exception("Error al guardar comprobante AFIP: " . $e->getMessage());
    }
}
  
    public function cancelarVenta($ventaId) {
        try {
            $stmt = $this->db->prepare("UPDATE ventas SET estado = 'cancelado' WHERE id = ?");
            $stmt->execute([$ventaId]);
            
            // Registrar log
            logOperacion($ventaId, 'VENTA_CANCELADA', "Venta cancelada por usuario");
            
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error al cancelar venta: " . $e->getMessage());
        }
    }
    
   
    public function resumenDiario($fecha = null) {
        if ($fecha === null) {
            $fecha = date('Y-m-d');
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_ventas,
                    SUM(total) as total_vendido,
                    SUM(CASE WHEN tipo_pago = 'efectivo' THEN total ELSE 0 END) as efectivo,
                    SUM(CASE WHEN tipo_pago LIKE '%tarjeta%' THEN total ELSE 0 END) as tarjeta,
                    SUM(CASE WHEN tipo_pago = 'qr' THEN total ELSE 0 END) as qr,
                    SUM(CASE WHEN tipo_pago = 'mixto' THEN total ELSE 0 END) as mixto
                FROM ventas 
                WHERE DATE(fecha_hora) = ? AND estado IN ('pagado', 'completado')
            ");
            $stmt->execute([$fecha]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Error al obtener resumen: " . $e->getMessage());
        }
    }
    
    /**
     * NUEVO: Obtener resumen de IVA de una venta
     */
    public function obtenerResumenIVA($ventaId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM venta_items 
                WHERE venta_id = ?
            ");
            $stmt->execute([$ventaId]);
            $items = $stmt->fetchAll();
            
            return CalculadoraIVA::calcularTotalesVenta($items);
            
        } catch (Exception $e) {
            throw new Exception("Error calculando IVA: " . $e->getMessage());
        }
    }
    

    public function obtenerVentasPorFecha($fechaInicio, $fechaFin) {
        try {
            $stmt = $this->db->prepare("
                SELECT v.*, 
                       COUNT(vi.id) as total_items,
                       GROUP_CONCAT(d.nombre SEPARATOR ', ') as departamentos
                FROM ventas v
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                LEFT JOIN departamentos d ON vi.departamento_id = d.id
                WHERE DATE(v.fecha_hora) BETWEEN ? AND ? 
                AND v.estado IN ('pagado', 'completado')
                GROUP BY v.id
                ORDER BY v.fecha_hora DESC
            ");
            $stmt->execute([$fechaInicio, $fechaFin]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error al obtener ventas por fecha: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas por departamento
     */
    public function estadisticasPorDepartamento($fecha = null) {
        if ($fecha === null) {
            $fecha = date('Y-m-d');
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT d.nombre as departamento,
                       COUNT(vi.id) as total_items,
                       SUM(vi.precio) as total_vendido,
                       SUM(vi.precio_sin_iva) as subtotal_sin_iva,
                       SUM(vi.monto_iva) as total_iva,
                       AVG(vi.precio) as precio_promedio,
                       MIN(vi.precio) as precio_minimo,
                       MAX(vi.precio) as precio_maximo
                FROM venta_items vi
                JOIN departamentos d ON vi.departamento_id = d.id
                JOIN ventas v ON vi.venta_id = v.id
                WHERE DATE(v.fecha_hora) = ? AND v.estado IN ('pagado', 'completado')
                GROUP BY d.id, d.nombre
                ORDER BY total_vendido DESC
            ");
            $stmt->execute([$fecha]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error al obtener estadísticas por departamento: " . $e->getMessage());
        }
    }
}
?>