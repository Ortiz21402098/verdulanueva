<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'reportes.php';

$reportes = new Reportes();

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch($action) {
        case 'resumen_hoy':
            $hoy = date('Y-m-d');
            $resultado = $reportes->reporteCajaDiario($hoy);
            echo json_encode([
                'success' => true,
                'data' => $resultado
            ]);
            break;
            
        case 'ventas_departamento_periodo':
            $fechaDesde = $_POST['fecha_desde'] ?? null;
            $fechaHasta = $_POST['fecha_hasta'] ?? null;
            $resultado = $reportes->ventasPorDepartamento($fechaDesde, $fechaHasta);
            echo json_encode([
                'success' => true,
                'data' => $resultado
            ]);
            break;
            
        case 'grafico_departamentos':
            $fechaDesde = $_POST['fecha_desde'] ?? null;
            $fechaHasta = $_POST['fecha_hasta'] ?? null;
            $datos = $reportes->ventasPorDepartamento($fechaDesde, $fechaHasta);
            
            $labels = [];
            $values = [];
            $colors = ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6f42c1'];
            
            foreach($datos as $index => $dept) {
                $labels[] = $dept['departamento'];
                $values[] = floatval($dept['total_ventas']);
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'data' => $values,
                        'backgroundColor' => array_slice($colors, 0, count($labels))
                    }]
                ]
            ]);
            break;

        case 'planillas_excel_disponibles':
    try {
        if (class_exists('ExcelPlanillaXLSX')) {
            $excel = new ExcelPlanillaXLSX();
            $planillas = $excel->obtenerPlanillasDisponibles();
            
            echo json_encode([
                'success' => true,
                'data' => $planillas
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Sistema de planillas Excel no disponible'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    break;

case 'generar_planilla_fecha':
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    
    try {
        if (class_exists('ExcelPlanillaXLSX')) {
            $excel = new ExcelPlanillaXLSX();
            $resultado = $excel->generarPlanillaDia($fecha);
            
            echo json_encode([
                'success' => $resultado['success'],
                'data' => $resultado,
                'message' => $resultado['success'] ? 'Planilla generada correctamente' : $resultado['error']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Sistema de planillas Excel no disponible'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    break;

case 'debug_planilla_sistema':
    try {
        if (class_exists('ExcelPlanillaXLSX')) {
            $excel = new ExcelPlanillaXLSX();
            $debug = $excel->debugPlanilla();
            
            echo json_encode([
                'success' => true,
                'data' => $debug
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Sistema de planillas Excel no disponible'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    break;

            
        case 'grafico_formas_pago':
            $fechaDesde = $_POST['fecha_desde'] ?? null;
            $fechaHasta = $_POST['fecha_hasta'] ?? null;
            $datos = $reportes->ventasPorFormaPago($fechaDesde, $fechaHasta);
            
            $labels = [];
            $values = [];
            
            foreach($datos as $forma) {
                $labels[] = ucfirst($forma['tipo_pago']);
                $values[] = floatval($forma['total_monto']);
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'datasets' => [{
                        'label' => 'Total Ventas ($)',
                        'data' => $values,
                        'backgroundColor' => ['#4facfe', '#00f2fe', '#11998e']
                    }]
                ]
            ]);
            break;
            
        case 'productos_mas_vendidos':
            $fechaDesde = $_POST['fecha_desde'] ?? null;
            $fechaHasta = $_POST['fecha_hasta'] ?? null;
            $limite = $_POST['limite'] ?? 10;
            $resultado = $reportes->productosMasVendidos($fechaDesde, $fechaHasta, $limite);
            echo json_encode([
                'success' => true,
                'data' => $resultado
            ]);
            break;
            
        case 'estadisticas_rendimiento':
            $fechaDesde = $_POST['fecha_desde'] ?? null;
            $fechaHasta = $_POST['fecha_hasta'] ?? null;
            $resultado = $reportes->estadisticasRendimiento($fechaDesde, $fechaHasta);
            echo json_encode([
                'success' => true,
                'data' => $resultado
            ]);
            break;
            
        case 'exportar_personalizado':
            $fechaDesde = $_POST['fecha_desde'] ?? null;
            $fechaHasta = $_POST['fecha_hasta'] ?? null;
            $tipo = $_POST['tipo'] ?? 'completo';
            $departamentos = $_POST['departamentos'] ?? [];
            
            // Generar URL de descarga
            $params = [
                'export' => '1',
                'tipo' => $tipo,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta
            ];
            
            if (!empty($departamentos)) {
                $params['departamentos'] = implode(',', $departamentos);
            }
            
            $url = 'exportar_excel.php?' . http_build_query($params);
            
            echo json_encode([
                'success' => true,
                'download_url' => $url
            ]);
            break;
            
        case 'comparativo_periodos':
            $periodo1_desde = $_POST['periodo1_desde'] ?? null;
            $periodo1_hasta = $_POST['periodo1_hasta'] ?? null;
            $periodo2_desde = $_POST['periodo2_desde'] ?? null;
            $periodo2_hasta = $_POST['periodo2_hasta'] ?? null;
            
            $datos1 = $reportes->resumenGeneral($periodo1_desde, $periodo1_hasta);
            $datos2 = $reportes->resumenGeneral($periodo2_desde, $periodo2_hasta);
            
            // Calcular diferencias
            $comparativo = [
                'periodo1' => $datos1,
                'periodo2' => $datos2,
                'diferencias' => [
                    'total_ventas' => $datos2['resumen']['total_ventas'] - $datos1['resumen']['total_ventas'],
                    'total_facturado' => $datos2['resumen']['total_facturado'] - $datos1['resumen']['total_facturado'],
                    'promedio_venta' => $datos2['resumen']['promedio_venta'] - $datos1['resumen']['promedio_venta']
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'data' => $comparativo
            ]);
            break;
            
        case 'alerta_inventario_bajo':
            // Productos que se venden mucho pero pueden estar con poco stock
            $fechaDesde = date('Y-m-d', strtotime('-30 days'));
            $fechaHasta = date('Y-m-d');
            
            $productosMasVendidos = $reportes->productosMasVendidos($fechaDesde, $fechaHasta, 50);
            
            // Simular análisis de productos con alta rotación
            $alertas = [];
            foreach($productosMasVendidos as $producto) {
                if ($producto['cantidad_vendida'] > 20) { // Si se vendieron más de 20 unidades en 30 días
                    $alertas[] = [
                        'codigo_barras' => $producto['codigo_barras'],
                        'departamento' => $producto['departamento'],
                        'cantidad_vendida' => $producto['cantidad_vendida'],
                        'total_vendido' => $producto['total_vendido'],
                        'nivel_alerta' => $producto['cantidad_vendida'] > 50 ? 'alto' : 'medio',
                        'mensaje' => "Producto con alta rotación - Verificar stock"
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $alertas
            ]);
            break;
            
        case 'tendencias_ventas':
            $fechaDesde = $_POST['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fechaHasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
            
            $ventasDiarias = $reportes->ventasDiarias($fechaDesde, $fechaHasta);
            
            // Procesar datos para tendencias
            $tendencias = [
                'fechas' => [],
                'ventas' => [],
                'transacciones' => [],
                'promedio_movil' => []
            ];
            
            $ventasArray = [];
            foreach($ventasDiarias as $venta) {
                $tendencias['fechas'][] = date('d/m', strtotime($venta['fecha']));
                $tendencias['ventas'][] = floatval($venta['total_ventas']);
                $tendencias['transacciones'][] = intval($venta['total_transacciones']);
                $ventasArray[] = floatval($venta['total_ventas']);
            }
            
            // Calcular promedio móvil de 7 días
            for($i = 0; $i < count($ventasArray); $i++) {
                $inicio = max(0, $i - 3);
                $fin = min(count($ventasArray) - 1, $i + 3);
                $suma = 0;
                $contador = 0;
                
                for($j = $inicio; $j <= $fin; $j++) {
                    $suma += $ventasArray[$j];
                    $contador++;
                }
                
                $tendencias['promedio_movil'][] = $contador > 0 ? $suma / $contador : 0;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $tendencias
            ]);
            break;
            
        case 'horas_pico':
            $fechaDesde = $_POST['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
            $fechaHasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
            
            $estadisticas = $reportes->estadisticasRendimiento($fechaDesde, $fechaHasta);
            
            // Agrupar por hora
            $horasPico = [];
            for($h = 0; $h < 24; $h++) {
                $horasPico[$h] = [
                    'hora' => $h,
                    'transacciones' => 0,
                    'total' => 0
                ];
            }
            
            foreach($estadisticas as $stat) {
                $hora = intval($stat['hora']);
                $horasPico[$hora]['transacciones'] += intval($stat['transacciones']);
                $horasPico[$hora]['total'] += floatval($stat['total_hora']);
            }
            
            // Convertir a array indexado y ordenar por transacciones
            $horasArray = array_values($horasPico);
            usort($horasArray, function($a, $b) {
                return $b['transacciones'] - $a['transacciones'];
            });
            
            echo json_encode([
                'success' => true,
                'data' => array_slice($horasArray, 0, 12) // Top 12 horas
            ]);
            break;
            
        case 'metricas_kpi':
            $fechaDesde = $_POST['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
            $fechaHasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
            
            $resumen = $reportes->resumenGeneral($fechaDesde, $fechaHasta);
            $ventasDiarias = $reportes->ventasDiarias($fechaDesde, $fechaHasta);
            
            // Calcular KPIs
            $diasConVentas = count($ventasDiarias);
            $totalVentas = $resumen['resumen']['total_facturado'];
            $totalTransacciones = $resumen['resumen']['ventas_completadas'];
            
            $kpis = [
                'facturacion_promedio_diaria' => $diasConVentas > 0 ? $totalVentas / $diasConVentas : 0,
                'transacciones_promedio_diarias' => $diasConVentas > 0 ? $totalTransacciones / $diasConVentas : 0,
                'ticket_promedio' => $totalTransacciones > 0 ? $totalVentas / $totalTransacciones : 0,
                'crecimiento_diario' => 0, // Se calculará comparando con período anterior
                'departamento_top' => '',
                'forma_pago_preferida' => ''
            ];
            
            // Departamento con más ventas
            if (!empty($resumen['departamentos'])) {
                $deptoTop = $resumen['departamentos'][0];
                $kpis['departamento_top'] = $deptoTop['departamento'];
            }
            
            // Forma de pago más usada
            if (!empty($resumen['formas_pago'])) {
                $pagoTop = $resumen['formas_pago'][0];
                $kpis['forma_pago_preferida'] = ucfirst($pagoTop['tipo_pago']);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $kpis
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>