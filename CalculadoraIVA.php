<?php
class CalculadoraIVA {
    
    // Configuración de IVA por departamento
    const IVA_POR_DEPARTAMENTO = [
        1 => 10.5, // Verdulería
        2 => 21.0, // Despensa  
        3 => 10.5, // Pollería trozado
        4 => 21.0  // Pollería procesado
    ];
    
    const NOMBRES_DEPARTAMENTO = [
        1 => 'Verdulería',
        2 => 'Despensa',
        3 => 'Pollería Trozado', 
        4 => 'Pollería Procesado'
    ];
    
    /**
     * Calcular IVA de un producto según su departamento
     */
    public static function calcularIVA($precioSinIVA, $departamentoId) {
        $porcentajeIVA = self::obtenerPorcentajeIVA($departamentoId);
        $montoIVA = ($precioSinIVA * $porcentajeIVA) / 100;
        $precioConIVA = $precioSinIVA + $montoIVA;
        
        return [
            'precio_sin_iva' => round($precioSinIVA, 2),
            'porcentaje_iva' => $porcentajeIVA,
            'monto_iva' => round($montoIVA, 2),
            'precio_con_iva' => round($precioConIVA, 2),
            'departamento' => self::NOMBRES_DEPARTAMENTO[$departamentoId] ?? 'Desconocido'
        ];
    }
    
    /**
     * Calcular IVA inverso (desde precio final)
     */
    public static function calcularIVAInverso($precioConIVA, $departamentoId) {
        $porcentajeIVA = self::obtenerPorcentajeIVA($departamentoId);
        $divisor = 1 + ($porcentajeIVA / 100);
        $precioSinIVA = $precioConIVA / $divisor;
        $montoIVA = $precioConIVA - $precioSinIVA;
        
        return [
            'precio_con_iva' => round($precioConIVA, 2),
            'precio_sin_iva' => round($precioSinIVA, 2),
            'porcentaje_iva' => $porcentajeIVA,
            'monto_iva' => round($montoIVA, 2),
            'departamento' => self::NOMBRES_DEPARTAMENTO[$departamentoId] ?? 'Desconocido'
        ];
    }
    
    /**
     * Calcular totales de IVA para una venta completa
     */
    public static function calcularTotalesVenta($items) {
        $totales = [
            'subtotal_sin_iva' => 0,
            'total_iva' => 0,
            'total_con_iva' => 0,
            'iva_por_alicuota' => [
                '10.5' => ['base' => 0, 'iva' => 0],
                '21.0' => ['base' => 0, 'iva' => 0]
            ],
            'detalle_por_departamento' => []
        ];
        
        foreach ($items as $item) {
            $departamentoId = $item['departamento_id'];
            $precioConIVA = $item['precio'];
            
            $calculoIVA = self::calcularIVAInverso($precioConIVA, $departamentoId);
            
            // Sumar a totales generales
            $totales['subtotal_sin_iva'] += $calculoIVA['precio_sin_iva'];
            $totales['total_iva'] += $calculoIVA['monto_iva'];
            $totales['total_con_iva'] += $calculoIVA['precio_con_iva'];
            
            // Agrupar por alícuota
            $alicuota = strval($calculoIVA['porcentaje_iva']);
            $totales['iva_por_alicuota'][$alicuota]['base'] += $calculoIVA['precio_sin_iva'];
            $totales['iva_por_alicuota'][$alicuota]['iva'] += $calculoIVA['monto_iva'];
            
            // Detalle por departamento
            if (!isset($totales['detalle_por_departamento'][$departamentoId])) {
                $totales['detalle_por_departamento'][$departamentoId] = [
                    'nombre' => $calculoIVA['departamento'],
                    'cantidad_items' => 0,
                    'subtotal_sin_iva' => 0,
                    'total_iva' => 0,
                    'total_con_iva' => 0,
                    'porcentaje_iva' => $calculoIVA['porcentaje_iva']
                ];
            }
            
            $totales['detalle_por_departamento'][$departamentoId]['cantidad_items']++;
            $totales['detalle_por_departamento'][$departamentoId]['subtotal_sin_iva'] += $calculoIVA['precio_sin_iva'];
            $totales['detalle_por_departamento'][$departamentoId]['total_iva'] += $calculoIVA['monto_iva'];
            $totales['detalle_por_departamento'][$departamentoId]['total_con_iva'] += $calculoIVA['precio_con_iva'];
        }
        
        // Redondear totales
        $totales['subtotal_sin_iva'] = round($totales['subtotal_sin_iva'], 2);
        $totales['total_iva'] = round($totales['total_iva'], 2);
        $totales['total_con_iva'] = round($totales['total_con_iva'], 2);
        
        foreach ($totales['iva_por_alicuota'] as &$alicuota) {
            $alicuota['base'] = round($alicuota['base'], 2);
            $alicuota['iva'] = round($alicuota['iva'], 2);
        }
        
        foreach ($totales['detalle_por_departamento'] as &$depto) {
            $depto['subtotal_sin_iva'] = round($depto['subtotal_sin_iva'], 2);
            $depto['total_iva'] = round($depto['total_iva'], 2);
            $depto['total_con_iva'] = round($depto['total_con_iva'], 2);
        }
        
        return $totales;
    }
    
    /**
     * Obtener porcentaje de IVA según departamento
     */
    public static function obtenerPorcentajeIVA($departamentoId) {
        return self::IVA_POR_DEPARTAMENTO[$departamentoId] ?? 21.0; // Default 21%
    }
    
    /**
     * Validar si un departamento existe
     */
    public static function departamentoValido($departamentoId) {
        return isset(self::IVA_POR_DEPARTAMENTO[$departamentoId]);
    }
    
    /**
     * Obtener todos los departamentos con sus IVAs
     */
    public static function obtenerTodosLosDepartamentos() {
        $departamentos = [];
        foreach (self::IVA_POR_DEPARTAMENTO as $id => $iva) {
            $departamentos[] = [
                'id' => $id,
                'nombre' => self::NOMBRES_DEPARTAMENTO[$id],
                'porcentaje_iva' => $iva
            ];
        }
        return $departamentos;
    }
    
    /**
     * Formatear para mostrar en facturas AFIP
     */
    public static function formatearParaAFIP($totales) {
        $alicuotas = [];
        
        foreach ($totales['iva_por_alicuota'] as $porcentaje => $datos) {
            if ($datos['base'] > 0) {
                $alicuotas[] = [
                    'Id' => self::obtenerCodigoAlicuotaAFIP(floatval($porcentaje)),
                    'BaseImp' => $datos['base'],
                    'Importe' => $datos['iva']
                ];
            }
        }
        
        return [
            'Concepto' => 1, // Productos
            'DocTipo' => 99, // Consumidor Final (sin documento)
            'DocNro' => 0,
            'ImpTotal' => $totales['total_con_iva'],
            'ImpTotConc' => 0, // No hay conceptos no gravados
            'ImpNeto' => $totales['subtotal_sin_iva'],
            'ImpOpEx' => 0, // No hay operaciones exentas
            'ImpIVA' => $totales['total_iva'],
            'ImpTrib' => 0, // No hay otros tributos
            'FchServDesde' => null,
            'FchServHasta' => null,
            'FchVtoPago' => null,
            'MonId' => 'PES',
            'MonCotiz' => 1,
            'Iva' => $alicuotas
        ];
    }
    
    /**
     * Convertir porcentaje IVA a código AFIP
     */
    private static function obtenerCodigoAlicuotaAFIP($porcentaje) {
        $codigos = [
            10.5 => 4, // IVA 10.5%
            21.0 => 5, // IVA 21%
            27.0 => 6, // IVA 27%
            0.0 => 3   // IVA 0%
        ];
        
        return $codigos[$porcentaje] ?? 5; // Default IVA 21%
    }
    
    /**
     * Generar comprobante detallado para impresión
     */
    public static function generarComprobanteDetallado($items, $datosVenta = []) {
        $totales = self::calcularTotalesVenta($items);
        
        $comprobante = [
            'fecha' => date('d/m/Y'),
            'hora' => date('H:i:s'),
            'venta_id' => $datosVenta['id'] ?? 0,
            'items' => [],
            'totales' => $totales,
            'resumen_iva' => []
        ];
        
        // Procesar items individuales
        foreach ($items as $item) {
            $calculoIVA = self::calcularIVAInverso($item['precio'], $item['departamento_id']);
            $comprobante['items'][] = [
                'descripcion' => $item['departamento'] ?? self::NOMBRES_DEPARTAMENTO[$item['departamento_id']],
                'codigo' => $item['codigo_barras'],
                'precio_unitario' => $calculoIVA['precio_con_iva'],
                'precio_sin_iva' => $calculoIVA['precio_sin_iva'],
                'iva_porcentaje' => $calculoIVA['porcentaje_iva'],
                'iva_monto' => $calculoIVA['monto_iva'],
                'departamento_id' => $item['departamento_id']
            ];
        }
        
        // Resumen de IVA
        foreach ($totales['iva_por_alicuota'] as $porcentaje => $datos) {
            if ($datos['base'] > 0) {
                $comprobante['resumen_iva'][] = [
                    'porcentaje' => floatval($porcentaje),
                    'base_imponible' => $datos['base'],
                    'monto_iva' => $datos['iva']
                ];
            }
        }
        
        return $comprobante;
    }
}
?>