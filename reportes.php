<?php
require_once 'config.php';
class Reportes {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }


public function detalleVentasCompleto($fechaDesde = null, $fechaHasta = null) {
    try {
        // CORREGIDO: Usar mismos estados que la función unificada
        $where = "v.estado IN ('pagado', 'pendiente', 'completado')";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                v.id as venta_id,
                v.fecha_hora,
                TIME(v.fecha_hora) as hora,
                v.total as monto_total_venta,
                v.tipo_pago,
                d.nombre as departamento,
                d.codigo_prefijo,
                d.id as departamento_id,
                COUNT(vi.id) as cantidad_items_departamento,
                SUM(vi.precio) as monto_departamento
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            WHERE $where
            GROUP BY v.id, d.id
            ORDER BY d.nombre ASC, v.tipo_pago ASC, v.fecha_hora ASC
        ");
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
        
        // Organizar datos por departamento y forma de pago
        $detalle = [];
        $resumen = [];
        
        foreach ($resultados as $row) {
            $dept = $row['departamento'];
            $pago = $row['tipo_pago'];
            $deptId = $row['departamento_id'];
            
            // CORREGIDO: Mapear tipos de pago correctamente
            $tipoMapeado = $this->mapearTipoPago($pago);
            
            // Inicializar estructura si no existe
            if (!isset($detalle[$dept])) {
                $detalle[$dept] = [
                    'departamento_id' => $deptId,
                    'codigo_prefijo' => $row['codigo_prefijo'],
                    'efectivo' => ['ventas' => [], 'total' => 0, 'cantidad' => 0],
                    'tarjeta' => ['ventas' => [], 'total' => 0, 'cantidad' => 0],
                    'qr' => ['ventas' => [], 'total' => 0, 'cantidad' => 0]
                ];
                
                $resumen[$dept] = [
                    'departamento_id' => $deptId,
                    'total_departamento' => 0,
                    'efectivo' => 0,
                    'tarjeta' => 0,
                    'qr' => 0,
                    'cantidad_ventas_efectivo' => 0,
                    'cantidad_ventas_tarjeta' => 0,
                    'cantidad_ventas_qr' => 0
                ];
            }
            
            // Agregar venta individual
            $venta = [
                'venta_id' => $row['venta_id'],
                'fecha_hora' => $row['fecha_hora'],
                'hora' => $row['hora'],
                'items' => $row['cantidad_items_departamento'],
                'monto' => $row['monto_departamento'],
                'monto_total_venta' => $row['monto_total_venta']
            ];
            
            $detalle[$dept][$tipoMapeado]['ventas'][] = $venta;
            $detalle[$dept][$tipoMapeado]['total'] += $row['monto_departamento'];
            $detalle[$dept][$tipoMapeado]['cantidad']++;
            
            // Actualizar resumen
            $resumen[$dept]['total_departamento'] += $row['monto_departamento'];
            $resumen[$dept][$tipoMapeado] += $row['monto_departamento'];
            $resumen[$dept]['cantidad_ventas_' . $tipoMapeado]++;
        }
        
        // CORREGIDO: Procesar pagos mixtos por separado
        $this->procesarPagosMixtos($detalle, $resumen, $fechaDesde, $fechaHasta);
        
        return [
            'detalle_completo' => $detalle,
            'resumen_por_departamento' => $resumen,
            'periodo' => [
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta
            ]
        ];
        
    } catch (PDOException $e) {
        throw new Exception("Error en detalle completo de ventas: " . $e->getMessage());
    }
}

private function mapearTipoPago($tipoPago) {
    switch($tipoPago) {
        case 'efectivo':
            return 'efectivo';
        case 'tarjeta-credito':
        case 'tarjeta-debito':
            return 'tarjeta';
        case 'qr':
            return 'qr';
        case 'mixto':
            return 'mixto'; // Se procesa por separado
        default:
            return 'qr'; // Por defecto
    }
}

// NUEVO MÉTODO: Procesar pagos mixtos
private function procesarPagosMixtos(&$detalle, &$resumen, $fechaDesde, $fechaHasta) {
    try {
        $where = "v.estado IN ('pagado', 'pendiente', 'completado') AND v.tipo_pago = 'mixto'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                v.id as venta_id,
                v.fecha_hora,
                TIME(v.fecha_hora) as hora,
                v.total as monto_total_venta,
                d.nombre as departamento,
                vpd.tipo_pago as tipo_pago_detalle,
                vpd.monto as monto_pago,
                SUM(vi.precio) as monto_departamento,
                COUNT(vi.id) as cantidad_items
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
            WHERE $where
            GROUP BY v.id, d.id, vpd.tipo_pago
            ORDER BY d.nombre ASC, v.fecha_hora ASC
        ");
        $stmt->execute($params);
        $pagosMixtos = $stmt->fetchAll();
        
        foreach ($pagosMixtos as $row) {
            $dept = $row['departamento'];
            $tipoPagoDetalle = $row['tipo_pago_detalle'];
            
            // Mapear tipo de pago mixto
            $tipoMapeado = $this->mapearTipoPagoMixto($tipoPagoDetalle);
            
            // Calcular monto proporcional por departamento
            $montoTotal = $row['monto_total_venta'];
            $montoDepartamento = $row['monto_departamento'];
            $montoPago = $row['monto_pago'];
            
            // Monto proporcional = (monto_pago * monto_departamento) / total_venta
            $montoProporcional = ($montoPago * $montoDepartamento) / $montoTotal;
            
            if (isset($detalle[$dept])) {
                $venta = [
                    'venta_id' => $row['venta_id'],
                    'fecha_hora' => $row['fecha_hora'],
                    'hora' => $row['hora'],
                    'items' => $row['cantidad_items'],
                    'monto' => $montoProporcional,
                    'monto_total_venta' => $montoTotal
                ];
                
                $detalle[$dept][$tipoMapeado]['ventas'][] = $venta;
                $detalle[$dept][$tipoMapeado]['total'] += $montoProporcional;
                $detalle[$dept][$tipoMapeado]['cantidad']++;
                
                // Actualizar resumen
                $resumen[$dept]['total_departamento'] += $montoProporcional;
                $resumen[$dept][$tipoMapeado] += $montoProporcional;
                $resumen[$dept]['cantidad_ventas_' . $tipoMapeado]++;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Error procesando pagos mixtos: " . $e->getMessage());
    }
}

// NUEVO MÉTODO: Mapear tipos de pago mixtos
private function mapearTipoPagoMixto($tipoPago) {
    switch($tipoPago) {
        case 'efectivo':
            return 'efectivo';
        case 'transferencia':
            return 'qr';
        default:
            return 'qr';
    }
}

/**
 * Obtiene ventas individuales para un departamento específico y forma de pago
 */


public function ventasIndividualesPorDepartamento($departamentoId, $tipoPago, $fechaDesde = null, $fechaHasta = null) {
    try {
        $where = "v.estado = 'pagado' AND vi.departamento_id = ? AND v.tipo_pago = ?";
        $params = [$departamentoId, $tipoPago];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                v.id as venta_id,
                v.fecha_hora,
                TIME(v.fecha_hora) as hora,
                v.total as monto_total_venta,
                v.tipo_pago,
                d.nombre as departamento,
                COUNT(vi.id) as items_departamento,
                SUM(vi.precio) as monto_departamento
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            WHERE $where
            GROUP BY v.id
            ORDER BY v.fecha_hora ASC
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        throw new Exception("Error en ventas individuales: " . $e->getMessage());
    }
}

/**
 * Genera estructura de datos para Excel con detalle de cada venta
 */

public function totalesGeneralesUnificados($fechaDesde = null, $fechaHasta = null, $busqueda = null) {
        try {
            $where = ["v.estado IN ('pagado', 'pendiente', 'completado')"];
            $params = [];
            
            if ($fechaDesde) {
                $where[] = "DATE(v.fecha_hora) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where[] = "DATE(v.fecha_hora) <= ?";
                $params[] = $fechaHasta;
            }
            
            if ($busqueda) {
                $where[] = "(vi.codigo_barras LIKE ? OR CAST(v.id AS CHAR) LIKE ?)";
                $params[] = "%$busqueda%";
                $params[] = "%$busqueda%";
            }
            
            $whereClause = "WHERE " . implode(" AND ", $where);
            
            // CONSULTA UNIFICADA: Totales por tipo de pago
            $stmt = $this->db->prepare("
                SELECT 
                    'efectivo' as tipo_pago_real,
                    SUM(
                        CASE 
                            WHEN v.tipo_pago = 'efectivo' THEN v.total
                            WHEN v.tipo_pago = 'mixto' AND vpd.tipo_pago = 'efectivo' THEN vpd.monto
                            ELSE 0
                        END
                    ) as total_monto
                FROM ventas v
                LEFT JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id AND v.tipo_pago = 'mixto' AND vpd.tipo_pago = 'efectivo'
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause

                UNION ALL

                SELECT 
                    'tarjeta-credito' as tipo_pago_real,
                    SUM(v.total) as total_monto
                FROM ventas v
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause AND v.tipo_pago = 'tarjeta-credito'

                UNION ALL

                SELECT 
                    'tarjeta-debito' as tipo_pago_real,
                    SUM(v.total) as total_monto
                FROM ventas v
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause AND v.tipo_pago = 'tarjeta-debito'

                UNION ALL

                SELECT 
                    'qr' as tipo_pago_real,
                    SUM(
                        CASE 
                            WHEN v.tipo_pago = 'qr' THEN v.total
                            WHEN v.tipo_pago = 'mixto' AND vpd.tipo_pago = 'transferencia' THEN vpd.monto
                            ELSE 0
                        END
                    ) as total_monto
                FROM ventas v
                LEFT JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id AND v.tipo_pago = 'mixto' AND vpd.tipo_pago = 'transferencia'
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause
            ");
            
            // Ejecutar con parámetros duplicados para cada UNION
            $paramsCompletos = array_merge($params, $params, $params, $params);
            $stmt->execute($paramsCompletos);
            $resultados = $stmt->fetchAll();
            
            // Organizar resultados
            $totales = [
                'efectivo' => 0,
                'tarjeta-credito' => 0,
                'tarjeta-debito' => 0,
                'qr' => 0,
                'total' => 0
            ];
            
            foreach ($resultados as $resultado) {
                $tipo = $resultado['tipo_pago_real'];
                $monto = (float)$resultado['total_monto'];
                $totales[$tipo] = $monto;
                $totales['total'] += $monto;
            }
            
            // Calcular tarjetas combinadas
            $totales['tarjeta'] = $totales['tarjeta-credito'] + $totales['tarjeta-debito'];
            
            // Obtener total de personas
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT v.id) as total_personas
                FROM ventas v
                LEFT JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause
            ");
            $stmt->execute($params);
            $personas = $stmt->fetch();
            $totales['personas'] = $personas['total_personas'];
            
            return $totales;
            
        } catch (PDOException $e) {
            throw new Exception("Error en totales generales: " . $e->getMessage());
        }
    }

    public function totalesPorDepartamentoUnificados($fechaDesde = null, $fechaHasta = null, $busqueda = null) {
        try {
            $where = ["v.estado IN ('pagado', 'pendiente', 'completado')"];
            $params = [];
            
            if ($fechaDesde) {
                $where[] = "DATE(v.fecha_hora) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where[] = "DATE(v.fecha_hora) <= ?";
                $params[] = $fechaHasta;
            }
            
            if ($busqueda) {
                $where[] = "(vi.codigo_barras LIKE ? OR CAST(v.id AS CHAR) LIKE ?)";
                $params[] = "%$busqueda%";
                $params[] = "%$busqueda%";
            }
            
            $whereClause = "WHERE " . implode(" AND ", $where);
            
            // CONSULTA SIMPLIFICADA: Totales por departamento
            $stmt = $this->db->prepare("
                SELECT 
                    d.nombre as departamento,
                    -- Efectivo: pagos simples + parte proporcional de mixtos
                    SUM(CASE 
                        WHEN v.tipo_pago = 'efectivo' THEN vi.precio
                        WHEN v.tipo_pago = 'mixto' THEN 
                            vi.precio * COALESCE(vpd_efectivo.monto, 0) / v.total
                        ELSE 0
                    END) as total_efectivo,
                    
                    -- Tarjeta Crédito: solo pagos simples
                    SUM(CASE WHEN v.tipo_pago = 'tarjeta-credito' THEN vi.precio ELSE 0 END) as total_tarjeta_credito,
                    
                    -- Tarjeta Débito: solo pagos simples  
                    SUM(CASE WHEN v.tipo_pago = 'tarjeta-debito' THEN vi.precio ELSE 0 END) as total_tarjeta_debito,
                    
                    -- QR/Transferencia: pagos simples + parte proporcional de mixtos
                    SUM(CASE 
                        WHEN v.tipo_pago = 'qr' THEN vi.precio
                        WHEN v.tipo_pago = 'mixto' THEN 
                            vi.precio * COALESCE(vpd_transferencia.monto, 0) / v.total
                        ELSE 0
                    END) as total_qr,
                    
                    -- Totales generales del departamento
                    SUM(vi.precio) as total_departamento,
                    COUNT(DISTINCT v.id) as personas_atendidas
                    
                FROM ventas v
                INNER JOIN venta_items vi ON v.id = vi.venta_id
                INNER JOIN departamentos d ON vi.departamento_id = d.id
                
                -- JOIN para obtener monto de efectivo en pagos mixtos
                LEFT JOIN venta_pagos_detalle vpd_efectivo ON v.id = vpd_efectivo.venta_id 
                    AND vpd_efectivo.tipo_pago = 'efectivo'
                    AND v.tipo_pago = 'mixto'
                
                -- JOIN para obtener monto de transferencia en pagos mixtos
                LEFT JOIN venta_pagos_detalle vpd_transferencia ON v.id = vpd_transferencia.venta_id 
                    AND vpd_transferencia.tipo_pago = 'transferencia'
                    AND v.tipo_pago = 'mixto'
                
                $whereClause
                GROUP BY d.id, d.nombre
                ORDER BY total_departamento DESC
            ");
            
            $stmt->execute($params);
            $resultados = $stmt->fetchAll();
            
            // Procesar resultados
            $totalesPorDepto = [];
            foreach ($resultados as $resultado) {
                $depto = $resultado['departamento'];
                $totalesPorDepto[$depto] = [
                    'efectivo' => (float)$resultado['total_efectivo'],
                    'tarjeta-credito' => (float)$resultado['total_tarjeta_credito'],
                    'tarjeta-debito' => (float)$resultado['total_tarjeta_debito'],
                    'qr' => (float)$resultado['total_qr'],
                    'total' => (float)$resultado['total_departamento'],
                    'personas_atendidas' => (int)$resultado['personas_atendidas']
                ];
                
                // Calcular tarjetas combinadas
                $totalesPorDepto[$depto]['tarjeta'] = 
                    $totalesPorDepto[$depto]['tarjeta-credito'] + 
                    $totalesPorDepto[$depto]['tarjeta-debito'];
            }
            
            return $totalesPorDepto;
            
        } catch (PDOException $e) {
            throw new Exception("Error en totales por departamento: " . $e->getMessage());
        }
    }
public function datosParaExcelDetallado($fechaDesde = null, $fechaHasta = null) {
    try {
        $detalleCompleto = $this->detalleVentasCompleto($fechaDesde, $fechaHasta);
        $estructura = [];
        
        foreach ($detalleCompleto['detalle_completo'] as $departamento => $datos) {
            $estructura[$departamento] = [
                'departamento_info' => [
                    'nombre' => $departamento,
                    'id' => $datos['departamento_id'],
                    'codigo' => $datos['codigo_prefijo']
                ],
                'ventas_por_tipo' => []
            ];
            
            // Procesar cada tipo de pago
            foreach (['efectivo', 'tarjeta', 'qr'] as $tipoPago) {
                if (!empty($datos[$tipoPago]['ventas'])) {
                    $ventasFormateadas = [];
                    $contador = 1;
                    
                    foreach ($datos[$tipoPago]['ventas'] as $venta) {
                        $ventasFormateadas[] = [
                            'numero' => $contador,
                            'venta_id' => $venta['venta_id'],
                            'hora' => $venta['hora'],
                            'items' => $venta['items'],
                            'monto' => $venta['monto'],
                            'monto_formateado' => '$' . number_format($venta['monto'], 2)
                        ];
                        $contador++;
                    }
                    
                    $estructura[$departamento]['ventas_por_tipo'][$tipoPago] = [
                        'ventas' => $ventasFormateadas,
                        'total' => $datos[$tipoPago]['total'],
                        'cantidad' => $datos[$tipoPago]['cantidad'],
                        'total_formateado' => '$' . number_format($datos[$tipoPago]['total'], 2)
                    ];
                }
            }
        }
        
        return [
            'estructura_detallada' => $estructura,
            'resumen' => $detalleCompleto['resumen_por_departamento'],
            'periodo' => $detalleCompleto['periodo']
        ];
        
    } catch (Exception $e) {
        throw new Exception("Error generando datos para Excel: " . $e->getMessage());
    }
}

/**
 * Obtiene resumen de totales por departamento y forma de pago para Excel
 */
public function resumenTotalesParaExcel($fechaDesde = null, $fechaHasta = null) {
    try {
        $datos = $this->datosParaExcelDetallado($fechaDesde, $fechaHasta);
        $resumen = [];
        
        $totalGeneral = [
            'efectivo' => 0,
            'tarjeta' => 0,
            'qr' => 0,
            'total' => 0
        ];
        
        foreach ($datos['estructura_detallada'] as $departamento => $info) {
            $resumen[$departamento] = [
                'efectivo' => 0,
                'tarjeta' => 0, 
                'qr' => 0,
                'total_departamento' => 0,
                'ventas_efectivo' => 0,
                'ventas_tarjeta' => 0,
                'ventas_qr' => 0
            ];
            
            foreach ($info['ventas_por_tipo'] as $tipo => $datos_tipo) {
                $resumen[$departamento][$tipo] = $datos_tipo['total'];
                $resumen[$departamento]['total_departamento'] += $datos_tipo['total'];
                $resumen[$departamento]['ventas_' . $tipo] = $datos_tipo['cantidad'];
                $totalGeneral[$tipo] += $datos_tipo['total'];
            }
            
            $totalGeneral['total'] += $resumen[$departamento]['total_departamento'];
        }
        
        return [
            'por_departamento' => $resumen,
            'total_general' => $totalGeneral,
            'estructura_completa' => $datos['estructura_detallada']
        ];
        
    } catch (Exception $e) {
        throw new Exception("Error en resumen para Excel: " . $e->getMessage());
    }
}

/**
 * Exporta datos detallados a CSV con estructura específica
 */
public function exportarDetalleCSV($fechaDesde = null, $fechaHasta = null) {
    try {
        $datos = $this->datosParaExcelDetallado($fechaDesde, $fechaHasta);
        $csvData = [];
        
        // Encabezados
        $csvData[] = [
            'Departamento',
            'Tipo Pago',
            'Numero Venta',
            'Venta ID',
            'Hora',
            'Items',
            'Monto'
        ];
        
        foreach ($datos['estructura_detallada'] as $departamento => $info) {
            foreach ($info['ventas_por_tipo'] as $tipoPago => $ventasTipo) {
                foreach ($ventasTipo['ventas'] as $venta) {
                    $csvData[] = [
                        $departamento,
                        ucfirst($tipoPago),
                        $venta['numero'],
                        $venta['venta_id'],
                        $venta['hora'],
                        $venta['items'],
                        $venta['monto']
                    ];
                }
                
                // Línea de subtotal por tipo
                $csvData[] = [
                    $departamento,
                    'SUBTOTAL ' . strtoupper($tipoPago),
                    '',
                    '',
                    '',
                    '',
                    $ventasTipo['total']
                ];
            }
            
            // Línea separadora entre departamentos
            $csvData[] = ['', '', '', '', '', '', ''];
        }
        
        return $csvData;
        
    } catch (Exception $e) {
        throw new Exception("Error exportando CSV detallado: " . $e->getMessage());
    }
}

/**
 * Obtiene estadísticas rápidas del detalle de ventas
 */
public function estadisticasDetalleVentas($fechaDesde = null, $fechaHasta = null) {
    try {
        $resumen = $this->resumenTotalesParaExcel($fechaDesde, $fechaHasta);
        
        $stats = [
            'departamentos_con_ventas' => count($resumen['por_departamento']),
            'total_general' => $resumen['total_general']['total'],
            'distribucion_pagos' => [
                'efectivo' => [
                    'monto' => $resumen['total_general']['efectivo'],
                    'porcentaje' => $resumen['total_general']['total'] > 0 ? 
                        round(($resumen['total_general']['efectivo'] / $resumen['total_general']['total']) * 100, 2) : 0
                ],
                'tarjeta' => [
                    'monto' => $resumen['total_general']['tarjeta'],
                    'porcentaje' => $resumen['total_general']['total'] > 0 ? 
                        round(($resumen['total_general']['tarjeta'] / $resumen['total_general']['total']) * 100, 2) : 0
                ],
                'qr' => [
                    'monto' => $resumen['total_general']['qr'],
                    'porcentaje' => $resumen['total_general']['total'] > 0 ? 
                        round(($resumen['total_general']['qr'] / $resumen['total_general']['total']) * 100, 2) : 0
                ]
            ],
            'departamento_mayor_venta' => '',
            'departamento_menor_venta' => ''
        ];
        
        // Encontrar departamento con mayor y menor venta
        $maxVenta = 0;
        $minVenta = PHP_FLOAT_MAX;
        
        foreach ($resumen['por_departamento'] as $dept => $totales) {
            if ($totales['total_departamento'] > $maxVenta) {
                $maxVenta = $totales['total_departamento'];
                $stats['departamento_mayor_venta'] = $dept;
            }
            
            if ($totales['total_departamento'] < $minVenta && $totales['total_departamento'] > 0) {
                $minVenta = $totales['total_departamento'];
                $stats['departamento_menor_venta'] = $dept;
            }
        }
        
        return $stats;
        
    } catch (Exception $e) {
        throw new Exception("Error en estadísticas de detalle: " . $e->getMessage());
    }
}
 
    public function ventasPorDepartamento($fechaDesde = null, $fechaHasta = null) {
        try {
            $where = "v.estado = 'pagado'";
            $params = [];
            
            if ($fechaDesde) {
                $where .= " AND DATE(v.fecha_hora) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where .= " AND DATE(v.fecha_hora) <= ?";
                $params[] = $fechaHasta;
            }
            
            // PASO 1: Obtener totales básicos por departamento
            $stmt = $this->db->prepare("
                SELECT 
                    d.nombre as departamento,
                    d.codigo_prefijo,
                    d.id as departamento_id,
                    COUNT(DISTINCT v.id) as cantidad_ventas,
                    COUNT(vi.id) as cantidad_items,
                    SUM(vi.precio) as total_ventas,
                    AVG(vi.precio) as precio_promedio,
                    MIN(vi.precio) as precio_minimo,
                    MAX(vi.precio) as precio_maximo
                FROM ventas v
                INNER JOIN venta_items vi ON v.id = vi.venta_id
                INNER JOIN departamentos d ON vi.departamento_id = d.id
                WHERE $where
                GROUP BY d.id, d.nombre, d.codigo_prefijo
                ORDER BY total_ventas DESC
            ");
            $stmt->execute($params);
            $resultados = $stmt->fetchAll();
            
            // PASO 2: Para cada departamento, calcular totales por tipo de pago
            foreach ($resultados as &$fila) {
                $deptId = $fila['departamento_id'];
                
                // Pagos NO mixtos por departamento
                $stmt = $this->db->prepare("
                    SELECT 
                        SUM(CASE WHEN v.tipo_pago = 'efectivo' THEN vi.precio ELSE 0 END) as efectivo_simple,
                        SUM(CASE WHEN v.tipo_pago = 'tarjeta-credito' THEN vi.precio ELSE 0 END) as tarjeta_credito,
                        SUM(CASE WHEN v.tipo_pago = 'tarjeta-debito' THEN vi.precio ELSE 0 END) as tarjeta_debito,
                        SUM(CASE WHEN v.tipo_pago = 'qr' THEN vi.precio ELSE 0 END) as qr_simple
                    FROM ventas v
                    INNER JOIN venta_items vi ON v.id = vi.venta_id
                    WHERE $where AND vi.departamento_id = ? AND v.tipo_pago != 'mixto'
                ");
                $paramsDeptoSimple = array_merge($params, [$deptId]);
                $stmt->execute($paramsDeptoSimple);
                $pagosSimples = $stmt->fetch();
                
                // Pagos mixtos por departamento (proporcional)
                $stmt = $this->db->prepare("
                    SELECT 
                        SUM(CASE WHEN vpd.tipo_pago = 'efectivo' THEN (vpd.monto * vi.precio / v.total) ELSE 0 END) as efectivo_mixto,
                        SUM(CASE WHEN vpd.tipo_pago = 'transferencia' THEN (vpd.monto * vi.precio / v.total) ELSE 0 END) as transferencia_mixto
                    FROM ventas v
                    INNER JOIN venta_items vi ON v.id = vi.venta_id
                    INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
                    WHERE $where AND vi.departamento_id = ? AND v.tipo_pago = 'mixto'
                ");
                $paramsDeptoMixto = array_merge($params, [$deptId]);
                $stmt->execute($paramsDeptoMixto);
                $pagosMixtos = $stmt->fetch();
                
                // Combinar totales
                $fila['total_efectivo'] = ($pagosSimples['efectivo_simple'] ?? 0) + ($pagosMixtos['efectivo_mixto'] ?? 0);
                $fila['total_tarjeta_credito'] = $pagosSimples['tarjeta_credito'] ?? 0;
                $fila['total_tarjeta_debito'] = $pagosSimples['tarjeta_debito'] ?? 0;
                $fila['total_qr'] = ($pagosSimples['qr_simple'] ?? 0) + ($pagosMixtos['transferencia_mixto'] ?? 0);
                $fila['total_tarjeta'] = $fila['total_tarjeta_credito'] + $fila['total_tarjeta_debito'];
            }
            
            return $resultados;
            
        } catch (PDOException $e) {
            throw new Exception("Error en reporte por departamento: " . $e->getMessage());
        }
    }

    public function detalleVentasMixtas($fechaDesde = null, $fechaHasta = null) {
        try {
            $where = "v.estado = 'pagado' AND v.tipo_pago = 'mixto'";
            $params = [];
            
            if ($fechaDesde) {
                $where .= " AND DATE(v.fecha_hora) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where .= " AND DATE(v.fecha_hora) <= ?";
                $params[] = $fechaHasta;
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    v.id as venta_id,
                    v.fecha_hora,
                    v.total as total_venta,
                    vpd.tipo_pago,
                    vpd.monto,
                    vpd.fecha_registro
                FROM ventas v
                INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
                WHERE $where
                ORDER BY v.fecha_hora DESC, v.id, vpd.tipo_pago
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error en detalle de ventas mixtas: " . $e->getMessage());
        }
    }

    public function resumenSemanalSimple($fechaDesde, $fechaHasta) {
    try {
        $where = "v.estado = 'pagado'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        // Obtener resumen por departamento SIN duplicar totales
        $stmt = $this->db->prepare("
            SELECT 
                d.nombre as departamento,
                d.codigo_prefijo,
                COUNT(DISTINCT v.id) as cantidad_ventas,
                SUM(vi.precio) as total_ventas
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            WHERE $where
            GROUP BY d.id, d.nombre, d.codigo_prefijo
            ORDER BY total_ventas DESC
        ");
        $stmt->execute($params);
        $departamentos = $stmt->fetchAll();
        
        // Obtener totales generales
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT v.id) as total_transacciones,
                SUM(v.total) as total_facturado,
                COUNT(vi.id) as total_items_vendidos
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            WHERE $where
        ");
        $stmt->execute($params);
        $totalesGenerales = $stmt->fetch();
        
        return [
            'departamentos' => $departamentos,
            'totales' => $totalesGenerales,
            'periodo' => [
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta
            ]
        ];
        
    } catch (PDOException $e) {
        throw new Exception("Error en resumen semanal: " . $e->getMessage());
    }
}

    
    public function ventasPorFormaPago($fechaDesde = null, $fechaHasta = null) {
    try {
        $where = "v.estado = 'pagado'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        // CORREGIDO: Obtener pagos simples usando v.total
        $stmt = $this->db->prepare("
            SELECT 
                v.tipo_pago,
                COUNT(*) as cantidad_transacciones,
                SUM(v.total) as total_monto,
                AVG(v.total) as promedio_por_transaccion,
                MIN(v.total) as minimo,
                MAX(v.total) as maximo
            FROM ventas v
            LEFT JOIN venta_items vi ON v.id = vi.venta_id
            WHERE $where AND v.tipo_pago != 'mixto'
            GROUP BY v.tipo_pago
        ");
        $stmt->execute($params);
        $pagosSimples = $stmt->fetchAll();
        
        // CORREGIDO: Obtener pagos mixtos
        $stmt = $this->db->prepare("
            SELECT 
                vpd.tipo_pago,
                COUNT(DISTINCT v.id) as cantidad_transacciones,
                SUM(vpd.monto) as total_monto,
                AVG(vpd.monto) as promedio_por_transaccion,
                MIN(vpd.monto) as minimo,
                MAX(vpd.monto) as maximo
            FROM ventas v
            INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
            LEFT JOIN venta_items vi ON v.id = vi.venta_id
            WHERE $where AND v.tipo_pago = 'mixto'
            GROUP BY vpd.tipo_pago
        ");
        $stmt->execute($params);
        $pagosMixtos = $stmt->fetchAll();
        
        // Combinar y consolidar resultados
        $consolidado = [];
        
        // Procesar pagos simples
        foreach ($pagosSimples as $pago) {
            $tipo = $pago['tipo_pago'];
            $consolidado[$tipo] = $pago;
        }
        
        // Agregar/combinar pagos mixtos
        foreach ($pagosMixtos as $pago) {
            $tipo = $pago['tipo_pago'];
            
            // Mapear transferencia a qr para consolidar
            if ($tipo == 'transferencia') {
                $tipo = 'qr';
            }
            
            if (isset($consolidado[$tipo])) {
                // Si ya existe, sumar
                $consolidado[$tipo]['cantidad_transacciones'] += $pago['cantidad_transacciones'];
                $consolidado[$tipo]['total_monto'] += $pago['total_monto'];
                $consolidado[$tipo]['promedio_por_transaccion'] = $consolidado[$tipo]['total_monto'] / $consolidado[$tipo]['cantidad_transacciones'];
                $consolidado[$tipo]['minimo'] = min($consolidado[$tipo]['minimo'], $pago['minimo']);
                $consolidado[$tipo]['maximo'] = max($consolidado[$tipo]['maximo'], $pago['maximo']);
            } else {
                // Si no existe, crear nuevo
                $consolidado[$tipo] = $pago;
                $consolidado[$tipo]['tipo_pago'] = $tipo;
            }
        }
        
        // Convertir a array indexado y ordenar
        $resultado = array_values($consolidado);
        usort($resultado, function($a, $b) {
            return $b['total_monto'] <=> $a['total_monto'];
        });
        
        return $resultado;
        
    } catch (PDOException $e) {
        throw new Exception("Error en reporte por forma de pago: " . $e->getMessage());
    }
}

    public function ventasDiarias($fechaDesde = null, $fechaHasta = null) {
    try {
        $where = "v.estado = 'pagado'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        // CORREGIDO: Obtener totales básicos de ventas usando v.total (SIN duplicar)
        $stmt = $this->db->prepare("
            SELECT 
                DATE(v.fecha_hora) as fecha,
                COUNT(*) as total_transacciones,
                SUM(v.total) as total_ventas,
                AVG(v.total) as promedio_venta
            FROM ventas v
            WHERE $where
            GROUP BY DATE(v.fecha_hora)
            ORDER BY fecha DESC
        ");
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
        
        // PASO 2: Para cada fecha, calcular los totales por tipo de pago usando v.total
        foreach ($resultados as &$fila) {
            $fecha = $fila['fecha'];
            
            // Obtener pagos NO mixtos usando v.total
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN v.tipo_pago = 'efectivo' THEN v.total ELSE 0 END) as efectivo_simple,
                    SUM(CASE WHEN v.tipo_pago = 'tarjeta-credito' THEN v.total ELSE 0 END) as tarjeta_credito,
                    SUM(CASE WHEN v.tipo_pago = 'tarjeta-debito' THEN v.total ELSE 0 END) as tarjeta_debito,
                    SUM(CASE WHEN v.tipo_pago = 'qr' THEN v.total ELSE 0 END) as qr_simple
                FROM ventas v
                WHERE DATE(v.fecha_hora) = ? AND v.estado = 'pagado' AND v.tipo_pago != 'mixto'
            ");
            $stmt->execute([$fecha]);
            $pagosSimples = $stmt->fetch();
            
            // Obtener pagos mixtos
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN vpd.tipo_pago = 'efectivo' THEN vpd.monto ELSE 0 END) as efectivo_mixto,
                    SUM(CASE WHEN vpd.tipo_pago = 'transferencia' THEN vpd.monto ELSE 0 END) as transferencia_mixto
                FROM ventas v
                INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
                WHERE DATE(v.fecha_hora) = ? AND v.estado = 'pagado' AND v.tipo_pago = 'mixto'
            ");
            $stmt->execute([$fecha]);
            $pagosMixtos = $stmt->fetch();
            
            // Combinar totales
            $fila['efectivo'] = ($pagosSimples['efectivo_simple'] ?? 0) + ($pagosMixtos['efectivo_mixto'] ?? 0);
            $fila['tarjeta-credito'] = $pagosSimples['tarjeta_credito'] ?? 0;
            $fila['tarjeta-debito'] = $pagosSimples['tarjeta_debito'] ?? 0;
            $fila['qr'] = ($pagosSimples['qr_simple'] ?? 0) + ($pagosMixtos['transferencia_mixto'] ?? 0);
            $fila['tarjeta'] = $fila['tarjeta-credito'] + $fila['tarjeta-debito'];
        }
        
        return $resultados;
        
    } catch (PDOException $e) {
        throw new Exception("Error en reporte diario: " . $e->getMessage());
    }
}
    
    public function productosMasVendidos($fechaDesde = null, $fechaHasta = null, $limite = 20) {
        try {
            $where = "v.estado = 'pagado'";
            $params = [];
            
            if ($fechaDesde) {
                $where .= " AND DATE(v.fecha_hora) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where .= " AND DATE(v.fecha_hora) <= ?";
                $params[] = $fechaHasta;
            }
            
            $params[] = $limite;
            
            $stmt = $this->db->prepare("
                SELECT 
                    vi.codigo_barras,
                    d.nombre as departamento,
                    COUNT(*) as cantidad_vendida,
                    SUM(vi.precio) as total_vendido,
                    AVG(vi.precio) as precio_promedio,
                    COUNT(DISTINCT v.id) as aparece_en_ventas
                FROM venta_items vi
                INNER JOIN ventas v ON vi.venta_id = v.id
                INNER JOIN departamentos d ON vi.departamento_id = d.id
                WHERE $where
                GROUP BY vi.codigo_barras, d.id, d.nombre
                ORDER BY cantidad_vendida DESC, total_vendido DESC
                LIMIT ?
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error en reporte de productos: " . $e->getMessage());
        }
    }
    
    public function reporteCajaDiario($fecha = null) {
        if (!$fecha) {
            $fecha = date('Y-m-d'); 
        }
        
        try {
            // Obtener datos de caja
            $stmt = $this->db->prepare("SELECT * FROM cajas WHERE fecha = ?");
            $stmt->execute([$fecha]);
            $caja = $stmt->fetch();
            
            // Usar la función ventasDiarias que ya está corregida
            $ventasDelDia = $this->ventasDiarias($fecha, $fecha);
            $ventas = !empty($ventasDelDia) ? $ventasDelDia[0] : null;
            
            // Obtener movimientos si existe la caja
            $movimientos = [];
            if ($caja) {
                $stmt = $this->db->prepare("
                    SELECT * FROM movimientos_caja 
                    WHERE caja_id = ? 
                    ORDER BY fecha_hora ASC
                ");
                $stmt->execute([$caja['id']]);
                $movimientos = $stmt->fetchAll();
            }
            
            return [
                'caja' => $caja,
                'ventas' => $ventas,
                'movimientos' => $movimientos
            ];
            
        } catch (PDOException $e) {
            throw new Exception("Error en reporte de caja: " . $e->getMessage());
        }
    }
    
   public function resumenGeneral($fechaDesde = null, $fechaHasta = null) {
    try {
        $where = "estado = 'pagado'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        // Resumen básico de transacciones
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_transacciones,
                COUNT(CASE WHEN estado = 'pagado' THEN 1 END) as ventas_completadas,
                COUNT(CASE WHEN estado = 'cancelado' THEN 1 END) as ventas_canceladas,
                SUM(CASE WHEN estado = 'pagado' THEN total ELSE 0 END) as total_facturado,
                CASE 
                    WHEN COUNT(CASE WHEN estado = 'pagado' THEN 1 END) > 0 
                    THEN SUM(CASE WHEN estado = 'pagado' THEN total ELSE 0 END) / COUNT(CASE WHEN estado = 'pagado' THEN 1 END)
                    ELSE 0 
                END as promedio_venta
            FROM ventas 
            WHERE $where
        ");
        $stmt->execute($params);
        $resumenTransacciones = $stmt->fetch();
        
        // Usar el resumen semanal simple para departamentos
        $resumenSemanal = $this->resumenSemanalSimple($fechaDesde, $fechaHasta);
        
        $formasPago = $this->ventasPorFormaPago($fechaDesde, $fechaHasta);
        
        return [
            'resumen' => $resumenTransacciones,
            'departamentos' => $resumenSemanal['departamentos'],
            'formas_pago' => $formasPago,
            'periodo' => [
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta
            ]
        ];
        
    } catch (PDOException $e) {
        throw new Exception("Error en resumen general: " . $e->getMessage());
    }
}
    
    public function exportarCSV($datos, $nombreArchivo, $encabezados) {
        try {
            $archivo = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');
            
            // Escribir encabezados
            fputcsv($archivo, $encabezados);
            
            // Escribir datos
            foreach ($datos as $fila) {
                fputcsv($archivo, $fila);
            }
            
            rewind($archivo);
            $contenido = stream_get_contents($archivo);
            fclose($archivo);
            
            // Configurar headers para descarga
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
            header('Content-Length: ' . strlen($contenido));
            
            echo $contenido;
            exit;
            
        } catch (Exception $e) {
            throw new Exception("Error al exportar CSV: " . $e->getMessage());
        }
    }

    public function detalleVentasPorDepartamentoYPago($fechaDesde = null, $fechaHasta = null) {
    try {
        $where = "v.estado = 'pagado'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(v.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(v.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                v.id as venta_id,
                v.fecha_hora,
                TIME(v.fecha_hora) as hora,
                v.total as monto_total,
                v.tipo_pago,
                d.nombre as departamento,
                d.codigo_prefijo,
                COUNT(vi.id) as cantidad_items,
                SUM(vi.precio) as monto_departamento
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            WHERE $where
            GROUP BY v.id, d.id
            ORDER BY d.nombre ASC, v.tipo_pago ASC, v.fecha_hora ASC
        ");
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
        
        // Organizar datos por departamento y forma de pago
        $detalle = [];
        $resumenGeneral = [
            'efectivo' => ['ventas' => 0, 'monto' => 0, 'clientes' => 0],
            'tarjeta' => ['ventas' => 0, 'monto' => 0, 'clientes' => 0],
            'qr' => ['ventas' => 0, 'monto' => 0, 'clientes' => 0]
        ];
        
        foreach ($resultados as $row) {
            $dept = $row['departamento'];
            $pago = $row['tipo_pago'];
            
            if (!isset($detalle[$dept])) {
                $detalle[$dept] = [
                    'efectivo' => ['ventas' => [], 'subtotal' => 0, 'cantidad' => 0],
                    'tarjeta' => ['ventas' => [], 'subtotal' => 0, 'cantidad' => 0],
                    'qr' => ['ventas' => [], 'subtotal' => 0, 'cantidad' => 0]
                ];
            }
            
            $venta = [
                'venta_id' => $row['venta_id'],
                'hora' => $row['hora'],
                'items' => $row['cantidad_items'],
                'monto' => $row['monto_departamento']
            ];
            
            $detalle[$dept][$pago]['ventas'][] = $venta;
            $detalle[$dept][$pago]['subtotal'] += $row['monto_departamento'];
            $detalle[$dept][$pago]['cantidad']++;
            
            // Acumular en resumen general (evitar duplicados por departamento)
            if (!isset($ventasContadas)) {
                $ventasContadas = [];
            }
            
            if (!in_array($row['venta_id'] . '_' . $pago, $ventasContadas)) {
                $resumenGeneral[$pago]['ventas']++;
                $resumenGeneral[$pago]['monto'] += $row['monto_total'];
                $resumenGeneral[$pago]['clientes']++;
                $ventasContadas[] = $row['venta_id'] . '_' . $pago;
            }
        }
        
        return [
            'detalle' => $detalle,
            'resumen_general' => $resumenGeneral
        ];
        
    } catch (PDOException $e) {
        throw new Exception("Error en detalle por departamento y pago: " . $e->getMessage());
    }
}

public function resumenClientesPorFormaPago($fechaDesde = null, $fechaHasta = null) {
    try {
        $where = "estado = 'pagado'";
        $params = [];
        
        if ($fechaDesde) {
            $where .= " AND DATE(fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where .= " AND DATE(fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                tipo_pago,
                COUNT(DISTINCT id) as clientes_atendidos,
                COUNT(*) as total_transacciones,
                SUM(total) as monto_total,
                AVG(total) as promedio_venta
            FROM ventas 
            WHERE $where
            GROUP BY tipo_pago
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        throw new Exception("Error en resumen de clientes: " . $e->getMessage());
    }
}

/**
 * FUNCIÓN UNIFICADA CORREGIDA: Como TODOS los pagos están en venta_pagos_detalle
 */
/**
 * FUNCIÓN CORREGIDA: Sin duplicación de totales
 */
public function obtenerTotalesUnificados($fechaDesde = null, $fechaHasta = null, $busqueda = null) {
    try {
        // Construir filtros BASE (sin venta_items para totales generales)
        $where_base = ["v.estado IN ('pagado', 'pendiente', 'completado')"];
        $params_base = [];
        
        if ($fechaDesde) {
            $where_base[] = "DATE(v.fecha_hora) >= ?";
            $params_base[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where_base[] = "DATE(v.fecha_hora) <= ?";
            $params_base[] = $fechaHasta;
        }
        
        $whereClause_base = "WHERE " . implode(" AND ", $where_base);
        
        // Construir filtros CON BÚSQUEDA (solo para cuando hay búsqueda)
        $where_busqueda = $where_base;
        $params_busqueda = $params_base;
        
        if ($busqueda) {
            $where_busqueda[] = "(vi.codigo_barras LIKE ? OR CAST(v.id AS CHAR) LIKE ?)";
            $params_busqueda[] = "%$busqueda%";
            $params_busqueda[] = "%$busqueda%";
        }
        
        $whereClause_busqueda = "WHERE " . implode(" AND ", $where_busqueda);
        
        // TOTALES GENERALES - SIN DUPLICACIÓN
        $totalesGenerales = [
            'efectivo' => 0,
            'tarjeta-credito' => 0,
            'tarjeta-debito' => 0,
            'qr' => 0,
            'total' => 0
        ];
        
        // 1. PAGOS SIMPLES (SIN JOIN con venta_items para evitar duplicación)
        if ($busqueda) {
            // Si hay búsqueda, necesitamos el JOIN pero con DISTINCT
            $stmt = $this->db->prepare("
                SELECT 
                    v.tipo_pago,
                    SUM(v.total) as total_monto
                FROM ventas v
                INNER JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause_busqueda AND v.tipo_pago != 'mixto'
                GROUP BY v.tipo_pago, v.id
                HAVING COUNT(DISTINCT v.id) >= 1
            ");
            $stmt->execute($params_busqueda);
            $temp_results = $stmt->fetchAll();
            
            // Reagrupar para evitar duplicación
            $pagos_agrupados = [];
            foreach ($temp_results as $row) {
                $tipo = $row['tipo_pago'];
                if (!isset($pagos_agrupados[$tipo])) {
                    $pagos_agrupados[$tipo] = 0;
                }
                $pagos_agrupados[$tipo] += $row['total_monto'];
            }
            
            foreach ($pagos_agrupados as $tipo => $monto) {
                if (isset($totalesGenerales[$tipo])) {
                    $totalesGenerales[$tipo] += $monto;
                    $totalesGenerales['total'] += $monto;
                }
            }
        } else {
            // Sin búsqueda, consulta directa sin JOIN problemático
            $stmt = $this->db->prepare("
                SELECT 
                    tipo_pago,
                    SUM(total) as total_monto
                FROM ventas v
                $whereClause_base AND v.tipo_pago != 'mixto'
                GROUP BY tipo_pago
            ");
            $stmt->execute($params_base);
            $pagosSimples = $stmt->fetchAll();
            
            foreach ($pagosSimples as $pago) {
                $tipo = $pago['tipo_pago'];
                $monto = (float)$pago['total_monto'];
                
                if (isset($totalesGenerales[$tipo])) {
                    $totalesGenerales[$tipo] += $monto;
                    $totalesGenerales['total'] += $monto;
                }
            }
        }
        
        // 2. PAGOS MIXTOS (aquí sí necesitamos JOIN pero controlado)
        $whereClause_final = $busqueda ? $whereClause_busqueda : $whereClause_base;
        $params_final = $busqueda ? $params_busqueda : $params_base;
        
        if ($busqueda) {
            $stmt = $this->db->prepare("
                SELECT 
                    vpd.tipo_pago,
                    SUM(vpd.monto) as total_monto
                FROM ventas v
                INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
                INNER JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause_final AND v.tipo_pago = 'mixto'
                GROUP BY vpd.tipo_pago, v.id
                HAVING COUNT(DISTINCT v.id) >= 1
            ");
            $stmt->execute($params_final);
            $temp_mixtos = $stmt->fetchAll();
            
            // Reagrupar mixtos
            $mixtos_agrupados = [];
            foreach ($temp_mixtos as $row) {
                $tipo = $row['tipo_pago'];
                if (!isset($mixtos_agrupados[$tipo])) {
                    $mixtos_agrupados[$tipo] = 0;
                }
                $mixtos_agrupados[$tipo] += $row['total_monto'];
            }
            
            foreach ($mixtos_agrupados as $tipo => $monto) {
                // Mapear transferencia -> qr
                if ($tipo === 'transferencia') {
                    $tipo = 'qr';
                }
                
                if (isset($totalesGenerales[$tipo])) {
                    $totalesGenerales[$tipo] += $monto;
                    $totalesGenerales['total'] += $monto;
                }
            }
        } else {
            $stmt = $this->db->prepare("
                SELECT 
                    vpd.tipo_pago,
                    SUM(vpd.monto) as total_monto
                FROM ventas v
                INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
                $whereClause_base AND v.tipo_pago = 'mixto'
                GROUP BY vpd.tipo_pago
            ");
            $stmt->execute($params_base);
            $pagosMixtos = $stmt->fetchAll();
            
            foreach ($pagosMixtos as $pago) {
                $tipo = $pago['tipo_pago'];
                $monto = (float)$pago['total_monto'];
                
                // Mapear transferencia -> qr
                if ($tipo === 'transferencia') {
                    $tipo = 'qr';
                }
                
                if (isset($totalesGenerales[$tipo])) {
                    $totalesGenerales[$tipo] += $monto;
                    $totalesGenerales['total'] += $monto;
                }
            }
        }
        
        // Obtener total de personas (SIN duplicación)
        if ($busqueda) {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT v.id) as total_personas
                FROM ventas v
                INNER JOIN venta_items vi ON v.id = vi.venta_id
                $whereClause_busqueda
            ");
            $stmt->execute($params_busqueda);
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT v.id) as total_personas
                FROM ventas v
                $whereClause_base
            ");
            $stmt->execute($params_base);
        }
        
        $personas = $stmt->fetch();
        $totalesGenerales['personas'] = $personas['total_personas'];
        
        // TOTALES POR DEPARTAMENTO (manteniendo la lógica existente pero corregida)
        $whereClause_depto = $busqueda ? $whereClause_busqueda : $whereClause_base;
        $params_depto = $busqueda ? $params_busqueda : $params_base;
        
        $stmt = $this->db->prepare("
            SELECT 
                d.nombre as departamento,
                SUM(vi.precio) as total_departamento,
                COUNT(DISTINCT v.id) as personas_atendidas,
                -- Pagos simples por departamento
                SUM(CASE WHEN v.tipo_pago = 'efectivo' AND v.tipo_pago != 'mixto' THEN vi.precio ELSE 0 END) as efectivo_simple,
                SUM(CASE WHEN v.tipo_pago = 'tarjeta-credito' AND v.tipo_pago != 'mixto' THEN vi.precio ELSE 0 END) as tarjeta_credito_simple,
                SUM(CASE WHEN v.tipo_pago = 'tarjeta-debito' AND v.tipo_pago != 'mixto' THEN vi.precio ELSE 0 END) as tarjeta_debito_simple,
                SUM(CASE WHEN v.tipo_pago = 'qr' AND v.tipo_pago != 'mixto' THEN vi.precio ELSE 0 END) as qr_simple
            FROM ventas v
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            $whereClause_depto
            GROUP BY d.id, d.nombre
            ORDER BY total_departamento DESC
        ");
        $stmt->execute($params_depto);
        $resultadosDeptos = $stmt->fetchAll();
        
        // Obtener pagos mixtos por departamento
        $stmt = $this->db->prepare("
            SELECT 
                d.nombre as departamento,
                vpd.tipo_pago,
                SUM(vpd.monto * (vi.precio / v.total)) as monto_proporcional
            FROM ventas v
            INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
            INNER JOIN venta_items vi ON v.id = vi.venta_id
            INNER JOIN departamentos d ON vi.departamento_id = d.id
            $whereClause_depto AND v.tipo_pago = 'mixto'
            GROUP BY d.id, d.nombre, vpd.tipo_pago
        ");
        $stmt->execute($params_depto);
        $mixtosPorDepto = $stmt->fetchAll();
        
        // Procesar departamentos combinando ambos sistemas
        $totalesPorDepto = [];
        
        // Inicializar con pagos simples
        foreach ($resultadosDeptos as $depto) {
            $nombre = $depto['departamento'];
            $totalesPorDepto[$nombre] = [
                'efectivo' => (float)$depto['efectivo_simple'],
                'tarjeta-credito' => (float)$depto['tarjeta_credito_simple'],
                'tarjeta-debito' => (float)$depto['tarjeta_debito_simple'],
                'qr' => (float)$depto['qr_simple'],
                'total' => (float)$depto['total_departamento'],
                'personas_atendidas' => (int)$depto['personas_atendidas']
            ];
        }
        
        // Agregar pagos mixtos
        foreach ($mixtosPorDepto as $mixto) {
            $nombre = $mixto['departamento'];
            $tipo = $mixto['tipo_pago'];
            $monto = (float)$mixto['monto_proporcional'];
            
            if (!isset($totalesPorDepto[$nombre])) {
                $totalesPorDepto[$nombre] = [
                    'efectivo' => 0, 'tarjeta-credito' => 0, 'tarjeta-debito' => 0, 'qr' => 0,
                    'total' => 0, 'personas_atendidas' => 0
                ];
            }
            
            // Mapear transferencia -> qr
            if ($tipo === 'transferencia') {
                $tipo = 'qr';
            }
            
            if (isset($totalesPorDepto[$nombre][$tipo])) {
                $totalesPorDepto[$nombre][$tipo] += $monto;
            }
        }
        
        return [
            'totales_generales' => $totalesGenerales,
            'totales_por_departamento' => $totalesPorDepto
        ];
        
    } catch (PDOException $e) {
        throw new Exception("Error en totales unificados: " . $e->getMessage());
    }
}


    public function estadisticasRendimiento($fechaDesde = null, $fechaHasta = null) {
        try {
            $where = "v.estado = 'pagado'";
            $params = [];
            
            if ($fechaDesde) {
                $where .= " AND DATE(v.fecha_hora) >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where .= " AND DATE(v.fecha_hora) <= ?";
                $params[] = $fechaHasta;
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(v.fecha_hora) as fecha,
                    HOUR(v.fecha_hora) as hora,
                    COUNT(*) as transacciones,
                    SUM(v.total) as total_hora,
                    d.nombre as departamento_principal
                FROM ventas v
                INNER JOIN venta_items vi ON v.id = vi.venta_id
                INNER JOIN departamentos d ON vi.departamento_id = d.id
                WHERE $where
                GROUP BY DATE(v.fecha_hora), HOUR(v.fecha_hora), d.id
                ORDER BY fecha DESC, hora ASC
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error en estadísticas: " . $e->getMessage());
        }
    }
}

?>

