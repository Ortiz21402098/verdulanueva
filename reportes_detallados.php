<?php

error_reporting(E_ERROR | E_PARSE); // Solo errores fatales
ini_set('display_errors', 1); // Para depuración visual
ini_set('log_errors', 1);

require_once 'config.php';
require_once 'reportes.php';

// CORREGIDO: Cargar autoload de Composer PRIMERO
$excelDisponible = false;
$mensajeError = '';

try {
    // Intentar cargar el autoload de Composer
    if (file_exists('vendor/autoload.php')) {
        require_once 'vendor/autoload.php';
        
        // Verificar que PhpSpreadsheet esté disponible
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Ahora cargar nuestra clase
            if (file_exists('ExcelPlanillaXLSX.php')) {
                require_once 'ExcelPlanillaXLSX.php';
                
                if (class_exists('ExcelPlanillaXLSX')) {
                    $excelPlanilla = new ExcelPlanillaXLSX();
                    $excelDisponible = true;
                } else {
                    $mensajeError = 'Clase ExcelPlanillaXLSX no encontrada. Verifica que el archivo ExcelPlanillaXLSX.php existe.';
                }
            } else {
                $mensajeError = 'Archivo ExcelPlanillaXLSX.php no encontrado.';
            }
        } else {
            $mensajeError = 'PhpSpreadsheet no está disponible. Ejecuta: composer install';
        }
    } else {
        $mensajeError = 'Composer no instalado. Ejecuta: composer install';
    }
} catch (Exception $e) {
    $excelDisponible = false;
    $mensajeError = 'Error cargando sistema Excel: ' . $e->getMessage();
}

$reportes = new Reportes();

// Manejar descarga de planilla Excel específica
if (isset($_GET['descargar_planilla']) && $_GET['descargar_planilla'] === 'true') {
    if (!$excelDisponible) {
        die('Error: Sistema de planillas no disponible - ' . $mensajeError);
    }
    
    $nombreArchivo = $_GET['archivo'] ?? null;
    try {
        $excelPlanilla->descargarPlanilla($nombreArchivo);
    } catch (Exception $e) {
        die('Error al descargar planilla: ' . $e->getMessage());
    }
}

// Manejar generación manual de planilla
if (isset($_POST['generar_planilla_manual'])) {
    if (!$excelDisponible) {
        $mensajePlanilla = 'Error: Sistema de planillas no disponible - ' . $mensajeError;
    } else {
        $fechaPlanilla = $_POST['fecha_planilla'] ?? date('Y-m-d');
        try {
            $resultado = $excelPlanilla->generarPlanillaDia($fechaPlanilla);
            $mensajePlanilla = $resultado['success'] 
                ? 'Planilla XLSX generada exitosamente: ' . $resultado['archivo']
                : 'Error: ' . $resultado['error'];
        } catch (Exception $e) {
            $mensajePlanilla = 'Error al generar planilla XLSX: ' . $e->getMessage();
        }
    }
}

// Manejar exportación a Excel (reportes existentes)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $fechaDesde = $_GET['fecha_desde'] ?? null;
    $fechaHasta = $_GET['fecha_hasta'] ?? null;
    $tipoReporte = $_GET['tipo'] ?? 'departamentos';
    
    exportarExcel($reportes, $tipoReporte, $fechaDesde, $fechaHasta);
    exit;
}

// Obtener datos para mostrar
$fechaDesde = $_GET['fecha_desde'] ?? null;
$fechaHasta = $_GET['fecha_hasta'] ?? null;

$ventasPorDepartamento = $reportes->ventasPorDepartamento($fechaDesde, $fechaHasta);
$ventasPorFormaPago = $reportes->ventasPorFormaPago($fechaDesde, $fechaHasta);
$ventasDiarias = $reportes->ventasDiarias($fechaDesde, $fechaHasta);
$resumenGeneral = $reportes->resumenGeneral($fechaDesde, $fechaHasta);

// Obtener planillas disponibles para descarga
$planillasDisponibles = [];
if ($excelDisponible) {
    try {
        $planillasDisponibles = $excelPlanilla->obtenerPlanillasDisponibles();
    } catch (Exception $e) {
        $mensajeError = 'Error al obtener planillas: ' . $e->getMessage();
    }
}

function exportarExcel($reportes, $tipo, $fechaDesde, $fechaHasta) {
    // Crear el contenido del archivo Excel (formato CSV que Excel puede abrir)
    $filename = "reporte_" . $tipo . "_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // Escribir BOM para UTF-8 (para que Excel abra correctamente los acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch($tipo) {
        case 'departamentos':
            $datos = $reportes->ventasPorDepartamento($fechaDesde, $fechaHasta);
            fputcsv($output, [
                'Departamento', 'Código Prefijo', 'Cantidad Ventas', 'Cantidad Items', 
                'Total Ventas', 'Total Efectivo', 'Total Tarjeta', 'Total QR', 
                'Precio Promedio', 'Precio Mínimo', 'Precio Máximo'
            ]);
            
            foreach($datos as $fila) {
                fputcsv($output, [
                    $fila['departamento'],
                    $fila['codigo_prefijo'],
                    $fila['cantidad_ventas'],
                    $fila['cantidad_items'],
                    number_format($fila['total_ventas'], 2),
                    number_format($fila['total_efectivo'], 2),
                    number_format($fila['total_tarjeta'], 2),
                    number_format($fila['total_qr'], 2),
                    number_format($fila['precio_promedio'], 2),
                    number_format($fila['precio_minimo'], 2),
                    number_format($fila['precio_maximo'], 2)
                ]);
            }
            break;
            
        case 'formas_pago':
            $datos = $reportes->ventasPorFormaPago($fechaDesde, $fechaHasta);
            fputcsv($output, [
                'Forma de Pago', 'Cantidad Transacciones', 'Total Monto', 
                'Promedio por Transacción', 'Mínimo', 'Máximo'
            ]);
            
            foreach($datos as $fila) {
                fputcsv($output, [
                    ucfirst($fila['tipo_pago']),
                    $fila['cantidad_transacciones'],
                    number_format($fila['total_monto'], 2),
                    number_format($fila['promedio_por_transaccion'], 2),
                    number_format($fila['minimo'], 2),
                    number_format($fila['maximo'], 2)
                ]);
            }
            break;
            
        case 'diarias':
            $datos = $reportes->ventasDiarias($fechaDesde, $fechaHasta);
            fputcsv($output, [
                'Fecha', 'Total Transacciones', 'Total Ventas', 'Efectivo', 
                'Tarjeta', 'QR', 'Promedio Venta'
            ]);
            
            foreach($datos as $fila) {
                fputcsv($output, [
                    $fila['fecha'],
                    $fila['total_transacciones'],
                    number_format($fila['total_ventas'], 2),
                    number_format($fila['efectivo'], 2),
                    number_format($fila['tarjeta'], 2),
                    number_format($fila['qr'], 2),
                    number_format($fila['promedio_venta'], 2)
                ]);
            }
            break;
            
        case 'completo':

            $resumen = $reportes->resumenGeneral($fechaDesde, $fechaHasta);
            fputcsv($output, ['=== RESUMEN GENERAL ===']);
            fputcsv($output, ['Métrica', 'Valor']);
            fputcsv($output, ['Total Transacciones', $resumen['resumen']['total_transacciones']]);
            fputcsv($output, ['Ventas Completadas', $resumen['resumen']['ventas_completadas']]);
            fputcsv($output, ['Ventas Canceladas', $resumen['resumen']['ventas_canceladas']]);
            fputcsv($output, ['Total Facturado', number_format($resumen['resumen']['total_facturado'], 2)]);
            fputcsv($output, ['Promedio por Venta', number_format($resumen['resumen']['promedio_venta'], 2)]);
            fputcsv($output, []);
            
            // Ventas por Departamento
                    $totalVentasDepartamentos = 0;
            foreach($resumen['departamentos'] as $dept) {
                $totalVentasDepartamentos += $dept['cantidad_ventas'];
            }
            fputcsv($output, ['=== VENTAS POR DEPARTAMENTO ===']);
            fputcsv($output, ['Total Ventas por Departamentos', $totalVentasDepartamentos]);
            fputcsv($output, []);
            foreach($resumen['departamentos'] as $dept) {
                fputcsv($output, [
                    $dept['departamento'],
                    $dept['cantidad_ventas'],
                    number_format($dept['total_ventas'], 2),
                    number_format($dept['total_efectivo'], 2),
                    number_format($dept['total_tarjeta'], 2),
                    number_format($dept['total_qr'], 2)
                ]);
            }
            fputcsv($output, []);
            
            // Ventas por Forma de Pago
            fputcsv($output, ['=== VENTAS POR FORMA DE PAGO ===']);
            fputcsv($output, ['Forma de Pago', 'Transacciones', 'Total Monto', 'Promedio']);
            foreach($resumen['formas_pago'] as $forma) {
                fputcsv($output, [
                    ucfirst($forma['tipo_pago']),
                    $forma['cantidad_transacciones'],
                    number_format($forma['total_monto'], 2),
                    number_format($forma['promedio_por_transaccion'], 2)
                ]);
            }
            break;
    }
    
    fclose($output);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Detallados - Mini Supermercado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container-fluid {
            background: white;
            margin: 20px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #4facfe;
        }
        
        .departamento-verduleria { border-left-color: #28a745; }
        .departamento-despensa { border-left-color: #17a2b8; }
        .departamento-polleria { border-left-color: #ffc107; }
        
        .btn-export {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s ease;
        }

        .btn-planilla {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        
        .btn-export:hover, .btn-planilla:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .table-responsive {
            margin: 20px 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table th {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .planilla-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            margin: 20px 0;
            border-radius: 15px;
        }

        .planilla-item {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header {
            background-color: #333;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
            flex: 1;
            text-align: center;
        }

        header img {
            max-width: 60px;
            height: auto;
            border-radius: 50%;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
            margin: 0;
            padding: 0;
        }

        nav ul li a {
            text-decoration: none;
            color: white;
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        nav ul li a:hover {
            background-color: #495057;
        }

        .alert-planilla {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <header>
    <h1>Información de Ventas Realizadas</h1>
    <img src="./imagenes/tu-web-mensajes.jpg" alt="Logo">
    <nav>
        <ul>
            <li><a href="index.php">Inicio</a></li>
            <li><a href="Nuevaventa.php">Nueva Venta</a></li>
            <li><a href="caja.php">Cierre de caja</a></li>
        </ul>
    </nav>
</header>
    <div class="container-fluid">
        <div class="header">
            <h1>Reportes Detallados</h1>
            <p>Mini Supermercado La Nueva</p>
        </div>
        
        <div class="p-4">
            <?php if (isset($mensajePlanilla)): ?>
            <div class="alert alert-planilla">
                <?= $mensajePlanilla ?>
            </div>
            <?php endif; ?>

            <!-- NUEVA SECCIÓN: Planillas Excel Diarias -->
            <div class="planilla-section">
                <h2>Planillas Excel Diarias</h2>
                <p>Gestiona las planillas diarias que se generan automáticamente con cada venta</p>
                
                <!-- Generar planilla manualmente -->
                <div class="row">
                    <div class="col-md-6">
                        <h5>Generar Planilla Manual</h5>
                        <form method="POST" class="d-flex align-items-end gap-3">
                            <div>
                                <label for="fecha_planilla" class="form-label">Fecha:</label>
                                <input type="date" class="form-control" id="fecha_planilla" name="fecha_planilla" 
                                       value="<?= date('Y-m-d') ?>" style="background: rgba(255,255,255,0.9);">
                            </div>
                            <button type="submit" name="generar_planilla_manual" class="btn btn-planilla">
                                Generar Planilla
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h5>Descarga Rápida</h5>
                        <a href="?descargar_planilla=true" class="btn btn-planilla">
                            Descargar Planilla de Hoy
                        </a>
                    </div>
                </div>

                <!-- Planillas disponibles -->
                <?php if (!empty($planillasDisponibles)): ?>
                <div class="mt-4">
                    <h5>Planillas Disponibles (<?= count($planillasDisponibles) ?>)</h5>
                    <?php foreach (array_slice($planillasDisponibles, 0, 10) as $planilla): ?>
                    <div class="planilla-item">
                        <div>
                            <strong><?= basename($planilla['nombre']) ?></strong><br>
                            <small>Fecha: <?= date('d/m/Y', strtotime($planilla['fecha'])) ?> | 
                                   Tamaño: <?= round($planilla['tamaño']/1024, 2) ?>KB | 
                                   Modificado: <?= $planilla['fecha_modificacion'] ?></small>
                        </div>
                        <a href="?descargar_planilla=true&archivo=<?= urlencode($planilla['nombre']) ?>" 
                           class="btn btn-sm btn-planilla">
                            Descargar
                        </a>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($planillasDisponibles) > 10): ?>
                    <p class="text-center mt-3">
                        <small>Mostrando las 10 planillas más recientes de <?= count($planillasDisponibles) ?> disponibles</small>
                    </p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="mt-4">
                    <p>No hay planillas disponibles. Las planillas se generan automáticamente cuando se completa una venta.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Filtros de fecha -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Filtros de Búsqueda</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="fecha_desde" class="form-label">Fecha Desde:</label>
                                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                           value="<?= $_GET['fecha_desde'] ?? '' ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="fecha_hasta" class="form-label">Fecha Hasta:</label>
                                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                           value="<?= $_GET['fecha_hasta'] ?? '' ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Filtrar</button>
                                        <a href="?" class="btn btn-secondary">Limpiar</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php 
// Calcular total de ventas sumando departamentos
$totalVentasDepartamentos = 0;
$totalItemsGeneral = 0;
$totalMontoGeneral = 0;

if (!empty($ventasPorDepartamento)) {
    foreach($ventasPorDepartamento as $dept) {
        $totalVentasDepartamentos += $dept['cantidad_ventas'];
        $totalItemsGeneral += $dept['cantidad_items'];
        $totalMontoGeneral += $dept['total_ventas'];
    }
}
?>
            
            <!-- Resumen General -->
            <div class="row">
    <div class="col-md-12">
        <h3>Resumen General</h3>
        <?php if (!empty($resumenGeneral['resumen'])): ?>
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <!-- OPCIÓN 1: Usar transacciones (lo que devuelve la consulta) -->
                    <h4><?= number_format($resumenGeneral['resumen']['total_transacciones']) ?></h4>
                    <p>Total Transacciones</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <!-- OPCIÓN 2: Usar suma de departamentos (más preciso) -->
                    <h4><?= number_format($totalVentasDepartamentos) ?></h4>
                    <p>Total Ventas por Depto</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4>$<?= number_format($resumenGeneral['resumen']['total_facturado'], 2) ?></h4>
                    <p>Total Facturado</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><?= number_format($resumenGeneral['resumen']['ventas_completadas']) ?></h4>
                    <p>Personas Atendidas</p>
                </div>
            </div>
        </div>
        
        <!-- AGREGADO: Fila adicional con más estadísticas -->
        <div class="row" style="margin-top: 15px;">
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><?= number_format($totalItemsGeneral) ?></h4>
                    <p>Total Items Vendidos</p>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card">
                    <h4><?= number_format($resumenGeneral['resumen']['ventas_canceladas']) ?></h4>
                    <p>Ventas Canceladas</p>
                </div>
            </div>
            
        </div>
        <?php endif; ?>
    </div>
</div>
            
            <!-- Tabla de Ventas por Departamento -->
            <div class="row">
                <div class="col-md-12">
                    <h3>Ventas por Departamento</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Departamento</th>
                                    <th>Código</th>
                                    <th>Cantidad Ventas</th>
                                    <th>Items Vendidos</th>
                                    <th>Total Ventas</th>
                                    <th>Efectivo</th>
                                    <th>Tarjeta-Credito</th>
                                    <th>Tarjeta-Debito</th>
                                    <th>QR</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($ventasPorDepartamento as $dept): ?>
                                <tr class="<?= 'departamento-' . strtolower(str_replace(' ', '-', $dept['departamento'])) ?>">
                                    <td><strong><?= $dept['departamento'] ?></strong></td>
                                    <td><?= $dept['codigo_prefijo'] ?></td>
                                    <td><?= number_format($dept['cantidad_ventas']) ?></td>
                                    <td><?= number_format($dept['cantidad_items']) ?></td>
                                    <td><strong>$<?= number_format($dept['total_ventas'], 2) ?></strong></td>
                                    <td>$<?= number_format($dept['total_efectivo'], 2) ?></td>
                                    <td>$<?= number_format($dept['total_tarjeta_credito'], 2) ?></td>
                                    <td>$<?= number_format($dept['total_tarjeta_debito'], 2) ?></td>
                                    <td>$<?= number_format($dept['total_qr'], 2) ?></td>
                                    
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Ventas Diarias -->
            <div class="row">
                <div class="col-md-12">
                    <h3>Ventas Diarias</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Transacciones</th>
                                    <th>Total Ventas</th>
                                    <th>Efectivo</th>
                                    <th>Tarjeta</th>
                                    <th>QR</th>   
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($ventasDiarias as $dia): ?>
                                <tr>
                                    <td><strong><?= date('d/m/Y', strtotime($dia['fecha'])) ?></strong></td>
                                    <td><?= number_format($dia['total_transacciones']) ?></td>
                                    <td><strong>$<?= number_format($dia['total_ventas'], 2) ?></strong></td>
                                    <td>$<?= number_format($dia['efectivo'], 2) ?></td>
                                    <td>$<?= number_format($dia['tarjeta'], 2) ?></td>
                                    <td>$<?= number_format($dia['qr'], 2) ?></td>
                                    
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Navegación -->
            <div class="row">
                <div class="col-md-12 text-center">
                    <a href="index.php" class="btn btn-primary">Volver al Inicio</a>
                    <a href="caja.php" class="btn btn-secondary">Cierre de Caja</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gráfico de Departamentos
        const ctxDept = document.getElementById('chartDepartamentos').getContext('2d');
        const chartDepartamentos = new Chart(ctxDept, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach($ventasPorDepartamento as $dept): ?>
                    '<?= $dept['departamento'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach($ventasPorDepartamento as $dept): ?>
                        <?= $dept['total_ventas'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Formas de Pago
        const ctxPago = document.getElementById('chartFormasPago').getContext('2d');
        const chartFormasPago = new Chart(ctxPago, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach($ventasPorFormaPago as $forma): ?>
                    '<?= ucfirst($forma['tipo_pago']) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total Ventas ($)',
                    data: [
                        <?php foreach($ventasPorFormaPago as $forma): ?>
                        <?= $forma['total_monto'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4facfe',
                        '#00f2fe',
                        '#11998e'
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function generarPlanillaAjax(fecha = null) {
    if (!fecha) fecha = new Date().toISOString().split('T')[0];
    
    const formData = new FormData();
    formData.append('action', 'generar_planilla_fecha');
    formData.append('fecha', fecha);
    
    fetch('ajax_reportes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje('Planilla generada: ' + data.data.archivo, 'success');
            cargarPlanillasDisponibles(); // Recargar lista
        } else {
            mostrarMensaje('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error de conexión', 'error');
    });
}

function cargarPlanillasDisponibles() {
    const formData = new FormData();
    formData.append('action', 'planillas_excel_disponibles');
    
    fetch('ajax_reportes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            actualizarListaPlanillas(data.data);
        } else {
            console.error('Error cargando planillas:', data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

function actualizarListaPlanillas(planillas) {
    const contenedor = document.querySelector('.planillas-container');
    if (!contenedor) return;
    
    let html = '';
    planillas.forEach(planilla => {
        html += `
        <div class="planilla-item">
            <div>
                <strong>${planilla.nombre}</strong><br>
                <small>Fecha: ${planilla.fecha_formateada} | Tamaño: ${planilla.tamaño_formateado}</small>
            </div>
            <a href="?descargar_planilla=true&archivo=${encodeURIComponent(planilla.nombre)}" 
               class="btn btn-sm btn-planilla">
                Descargar
            </a>
        </div>`;
    });
    
    contenedor.innerHTML = html;
}

function mostrarMensaje(mensaje, tipo = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid .p-4');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Cargar planillas al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarPlanillasDisponibles();
});
    </script>
</body>
</html>