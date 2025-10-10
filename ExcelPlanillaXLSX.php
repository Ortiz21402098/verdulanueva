<?php

// Cargar autoload de Composer
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;


class ExcelPlanillaXLSX {
    private $directorioBase;
    private $reportes;
    
    public function __construct() {
        $this->directorioBase = './planillas_excel/';
        
        // Crear directorio si no existe
        if (!is_dir($this->directorioBase)) {
            if (!mkdir($this->directorioBase, 0755, true)) {
                throw new Exception("No se pudo crear el directorio {$this->directorioBase}");
            }
        }
        
        // Verificar permisos
        if (!is_writable($this->directorioBase)) {
            throw new Exception("El directorio {$this->directorioBase} no tiene permisos de escritura");
        }
        
        // Inicializar reportes
        if (file_exists('reportes.php')) {
            require_once 'reportes.php';
            $this->reportes = new Reportes();
        } else {
            throw new Exception("Archivo reportes.php no encontrado");
        }
    }
    
private function crearHojaDetalleVentas($spreadsheet, $fecha) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Detalle Ventas');
    
    // Obtener datos detallados
    $detalleCompleto = $this->reportes->detalleVentasCompleto($fecha, $fecha);
    
    if (empty($detalleCompleto['detalle_completo'])) {
        $sheet->setCellValue('A1', 'No hay datos de ventas para mostrar');
        return;
    }
    
    $row = 1;
    
    // Encabezado principal
    $sheet->setCellValue('A' . $row, 'DETALLE INDIVIDUAL DE VENTAS POR DEPARTAMENTO');
    $sheet->setCellValue('A' . ($row + 1), 'Fecha: ' . date('d/m/Y', strtotime($fecha)));
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->mergeCells('A' . ($row + 1) . ':C' . ($row + 1));
    $sheet->getStyle('A' . $row . ':A' . ($row + 1))->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row . ':A' . ($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row += 3;
    
    foreach ($detalleCompleto['detalle_completo'] as $departamento => $datosDept) {
        // Encabezado del departamento
        $sheet->setCellValue('A' . $row, strtoupper($departamento));
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        // Encabezados de columnas para el departamento
        $sheet->setCellValue('A' . $row, '#');
        $sheet->setCellValue('B' . $row, 'Monto');
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        $row++;
        
        // Recopilar TODAS las ventas del departamento (sin separar por tipo de pago)
        $todasLasVentas = [];
        $totalDepartamento = 0;
        $contadorVentas = 0;
        
        // Unir ventas de todos los tipos de pago
        foreach (['efectivo', 'tarjeta', 'qr'] as $tipoPago) {
            if (!empty($datosDept[$tipoPago]['ventas'])) {
                foreach ($datosDept[$tipoPago]['ventas'] as $venta) {
                    $todasLasVentas[] = $venta;
                    $totalDepartamento += $venta['monto'];
                    $contadorVentas++;
                }
            }
        }
        
        // Ordenar ventas por ID o por orden (opcional)
        usort($todasLasVentas, function($a, $b) {
            return $a['venta_id'] <=> $b['venta_id'];
        });
        
        // Mostrar todas las ventas del departamento
        $numeroVenta = 1;
        foreach ($todasLasVentas as $venta) {
            $sheet->setCellValue('A' . $row, $numeroVenta);
            $sheet->setCellValue('B' . $row, '$' . number_format($venta['monto'], 2));
            $numeroVenta++;
            $row++;
        }
        
        // TOTAL por departamento
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, $contadorVentas . ' ventas');
        $sheet->setCellValue('C' . $row, '$' . number_format($totalDepartamento, 2));
        $sheet->mergeCells('A' . $row . ':A' . $row); // Solo para que el texto se vea mejor
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF9800');
        
        $row += 3; // Espacio entre departamentos
    }
    
    // Ajustar columnas - MÁS ANCHAS
    $sheet->getColumnDimension('A')->setWidth(12);  // Número de venta
    $sheet->getColumnDimension('B')->setWidth(25); // Monto o descripción
    $sheet->getColumnDimension('C')->setWidth(25); // Total (solo en fila de totales)
    
    // Ajustar altura de todas las filas - MÁS ALTAS
    for ($i = 1; $i <= $ultimaFila; $i++) {
        $sheet->getRowDimension($i)->setRowHeight(20); // Altura estándar
    }
    
    // Altura extra para encabezados principales
    $sheet->getRowDimension(1)->setRowHeight(25); // Título principal
    $sheet->getRowDimension(2)->setRowHeight(22); // Fecha
    
    // Bordes para toda la hoja
    $ultimaFila = $row - 1;
    $sheet->getStyle('A1:C' . $ultimaFila)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}

// Modificar el método generarPlanillaDia() para incluir la nueva hoja
public function generarPlanillaDia($fecha) {
    try {
        // Validar fecha
        $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj) {
            throw new Exception('Formato de fecha inválido. Use YYYY-MM-DD');
        }
        
        $nombreArchivo = 'planilla_detallada_' . $fecha . '.xlsx';
        $rutaCompleta = $this->directorioBase . $nombreArchivo;
        
        // *** USAR DATOS UNIFICADOS PARA EVITAR DUPLICACIÓN ***
        $datosUnificados = $this->reportes->obtenerTotalesUnificados($fecha, $fecha);
        
        // Obtener otros datos necesarios
        $ventasDiarias = $this->reportes->ventasDiarias($fecha, $fecha);
        
        // Crear spreadsheet
        $spreadsheet = new Spreadsheet();
        $this->crearHojaResumenUnificada($spreadsheet, $fecha, $datosUnificados, $ventasDiarias);
        
        // Agregar hoja de detalle si existe
        $this->crearHojaDetalleVentas($spreadsheet, $fecha);
        
        // Configurar propiedades del documento
        $spreadsheet->getProperties()
            ->setCreator("Mini Supermercado La Nueva")
            ->setLastModifiedBy("Sistema de Reportes")
            ->setTitle("Planilla Detallada - " . date('d/m/Y', strtotime($fecha)))
            ->setSubject("Reporte de Ventas Detallado")
            ->setDescription("Planilla diaria de ventas con detalle individual generada automáticamente")
            ->setKeywords("ventas planilla excel supermercado detalle")
            ->setCategory("Reportes");
        
        // Guardar archivo
        $writer = new Xlsx($spreadsheet);
        $writer->save($rutaCompleta);
        
        return [
            'success' => true,
            'archivo' => $nombreArchivo,
            'ruta' => $rutaCompleta,
            'tamaño' => file_exists($rutaCompleta) ? filesize($rutaCompleta) : 0
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

private function crearHojaResumenUnificada($spreadsheet, $fecha, $datosUnificados, $ventasDiarias) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Resumen ' . date('d-m', strtotime($fecha)));
    
    // Extraer datos
    $totalesGenerales = $datosUnificados['totales_generales'];
    $totalesPorDepto = $datosUnificados['totales_por_departamento'];
    
    // Encabezado principal
    $sheet->setCellValue('A1', 'MINI SUPERMERCADO LA NUEVA');
    $sheet->setCellValue('A2', 'PLANILLA DIARIA DE VENTAS');
    $sheet->setCellValue('A3', 'Fecha: ' . date('d/m/Y', strtotime($fecha)));
    $sheet->setCellValue('A4', 'Generado: ' . date('d/m/Y H:i:s'));
    
    // Estilos del encabezado
    $sheet->mergeCells('A1:G1');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3:A4')->getFont()->setBold(true);
    
    $row = 6;
    
    // RESUMEN GENERAL - USANDO DATOS UNIFICADOS
    $sheet->setCellValue('A' . $row, 'RESUMEN GENERAL');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
    $row++;
    
    $datosResumen = [
        ['Concepto', 'Valor'],
        ['Efectivo', '$' . number_format($totalesGenerales['efectivo'], 2)],
        ['Tarjeta Crédito', '$' . number_format($totalesGenerales['tarjeta-credito'], 2)],
        ['Tarjeta Débito', '$' . number_format($totalesGenerales['tarjeta-debito'], 2)],
        ['QR/Transferencia', '$' . number_format($totalesGenerales['qr'], 2)],
        ['TOTAL GENERAL', '$' . number_format($totalesGenerales['total'], 2)],
        ['Personas Atendidas', number_format($totalesGenerales['personas'])],
    ];
    
    foreach ($datosResumen as $i => $dato) {
        $sheet->setCellValue('A' . $row, $dato[0]);
        $sheet->setCellValue('B' . $row, $dato[1]);
        
        if ($i === 0) { // Encabezado
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
        } elseif ($dato[0] === 'TOTAL GENERAL') { // Total destacado
            $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFECB3');
        }
        $row++;
    }
    
    $row += 2;
    
    // VENTAS POR DEPARTAMENTO - USANDO DATOS UNIFICADOS
    if (!empty($totalesPorDepto)) {
        $sheet->setCellValue('A' . $row, 'VENTAS POR DEPARTAMENTO');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2196F3');
        $row++;
        
        $headers = ['Departamento', 'Personas', 'Efectivo', 'T.Crédito', 'T.Débito', 'QR', 'Total ($)'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
            $col++;
        }
        $row++;
        
        $totalesDept = [
            'personas' => 0,
            'efectivo' => 0,
            'tarjeta_credito' => 0,
            'tarjeta_debito' => 0,
            'qr' => 0,
            'total' => 0
        ];
        
        foreach ($totalesPorDepto as $nombreDept => $datosDept) {
            $sheet->setCellValue('A' . $row, $nombreDept);
            $sheet->setCellValue('B' . $row, number_format($datosDept['personas_atendidas']));
            $sheet->setCellValue('C' . $row, '$' . number_format($datosDept['efectivo'], 2));
            $sheet->setCellValue('D' . $row, '$' . number_format($datosDept['tarjeta-credito'], 2));
            $sheet->setCellValue('E' . $row, '$' . number_format($datosDept['tarjeta-debito'], 2));
            $sheet->setCellValue('F' . $row, '$' . number_format($datosDept['qr'], 2));
            $sheet->setCellValue('G' . $row, '$' . number_format($datosDept['total'], 2));
            
            // Acumular totales
            $totalesDept['personas'] += $datosDept['personas_atendidas'];
            $totalesDept['efectivo'] += $datosDept['efectivo'];
            $totalesDept['tarjeta_credito'] += $datosDept['tarjeta-credito'];
            $totalesDept['tarjeta_debito'] += $datosDept['tarjeta-debito'];
            $totalesDept['qr'] += $datosDept['qr'];
            $totalesDept['total'] += $datosDept['total'];
            
            $row++;
        }
        
        // TOTAL DEPARTAMENTOS
        $sheet->setCellValue('A' . $row, 'TOTAL DEPARTAMENTOS');
        $sheet->setCellValue('B' . $row, number_format($totalesDept['personas']));
        $sheet->setCellValue('C' . $row, '$' . number_format($totalesDept['efectivo'], 2));
        $sheet->setCellValue('D' . $row, '$' . number_format($totalesDept['tarjeta_credito'], 2));
        $sheet->setCellValue('E' . $row, '$' . number_format($totalesDept['tarjeta_debito'], 2));
        $sheet->setCellValue('F' . $row, '$' . number_format($totalesDept['qr'], 2));
        $sheet->setCellValue('G' . $row, '$' . number_format($totalesDept['total'], 2));
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFECB3');
        $row++;
        
        // *** NUEVA FILA: TOTAL QR + TARJETAS ***
        $totalQrTarjetas = $totalesDept['qr'] + $totalesDept['tarjeta_credito'] + $totalesDept['tarjeta_debito'];
        $sheet->setCellValue('A' . $row, 'TOTAL QR + TARJETAS');
        $sheet->setCellValue('B' . $row, ''); // Vacío para personas
        $sheet->setCellValue('C' . $row, ''); // Vacío para efectivo
        $sheet->setCellValue('D' . $row, '$' . number_format($totalesDept['tarjeta_credito'], 2));
        $sheet->setCellValue('E' . $row, '$' . number_format($totalesDept['tarjeta_debito'], 2));
        $sheet->setCellValue('F' . $row, '$' . number_format($totalesDept['qr'], 2));
        $sheet->setCellValue('G' . $row, '$' . number_format($totalQrTarjetas, 2));
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF44336');
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->getColor()->setARGB('FFFFFFFF'); // Texto blanco
        $row++;
    }
    
    $row += 2;
    
    // Ajustar columnas
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Bordes
    $sheet->getStyle('A1:G' . ($row))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
    private function crearHojaResumen($spreadsheet, $fecha, $resumen, $ventasDiarias) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Resumen ' . date('d-m', strtotime($fecha)));
    
    // Encabezado principal
    $sheet->setCellValue('A1', 'MINI SUPERMERCADO LA NUEVA');
    $sheet->setCellValue('A2', 'PLANILLA DIARIA DE VENTAS');
    $sheet->setCellValue('A3', 'Fecha: ' . date('d/m/Y', strtotime($fecha)));
    $sheet->setCellValue('A4', 'Generado: ' . date('d/m/Y H:i:s'));
    
    // Estilos del encabezado
    $sheet->mergeCells('A1:F1');
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A3:A4')->getFont()->setBold(true);
    
    $row = 6;
    
    // Resumen General
    $sheet->setCellValue('A' . $row, 'RESUMEN GENERAL');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
    $row++;
    
    if (!empty($resumen['resumen'])) {
        // CALCULAR TOTAL DE VENTAS DESDE DEPARTAMENTOS
        $totalVentasCalculado = 0;
        if (!empty($resumen['departamentos'])) {
            foreach ($resumen['departamentos'] as $dept) {
                $totalVentasCalculado += $dept['cantidad_ventas'];
            }
        }
        
        $datosResumen = [
            ['Concepto', 'Valor'],
            // CORREGIDO: Usar el total calculado desde departamentos
            ['Total Ventas', number_format($totalVentasCalculado)],
            // CORREGIDO: Usar total_transacciones que SÍ existe
            ['Total Transacciones', number_format($resumen['resumen']['total_transacciones'])],
            ['Personas Atendidas', number_format($resumen['resumen']['ventas_completadas'])],
            ['Total Facturado', '$' . number_format($resumen['resumen']['total_facturado'], 2)],
            // AGREGADO: Promedio por venta
            ['Promedio por Venta', '$' . number_format($resumen['resumen']['promedio_venta'], 2)],
        ];
        
        foreach ($datosResumen as $i => $dato) {
            $sheet->setCellValue('A' . $row, $dato[0]);
            $sheet->setCellValue('B' . $row, $dato[1]);
            
            if ($i === 0) { // Encabezado
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
            }
            $row++;
        }
    }
    
    $row += 2;
    
    // NUEVA SECCIÓN: Totales por Método de Pago
    $sheet->setCellValue('A' . $row, 'TOTALES POR MÉTODO DE PAGO');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF9800');
    $row++;
    
    if (!empty($resumen['formas_pago'])) {
        $headers = ['Método', 'Transacciones', 'Total Monto'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
            $col++;
        }
        $row++;
        
        $totalGeneral = 0;
        $transaccionesTotal = 0;
        
        foreach ($resumen['formas_pago'] as $forma) {
            $nombreMetodo = ucfirst(str_replace('-', ' ', $forma['tipo_pago']));
            $sheet->setCellValue('A' . $row, $nombreMetodo);
            $sheet->setCellValue('B' . $row, number_format($forma['cantidad_transacciones']));
            $sheet->setCellValue('C' . $row, '$' . number_format($forma['total_monto'], 2));
            
            $totalGeneral += $forma['total_monto'];
            $transaccionesTotal += $forma['cantidad_transacciones'];
            $row++;
        }
        
        // Fila de totales
        $sheet->setCellValue('A' . $row, 'TOTAL GENERAL');
        $sheet->setCellValue('B' . $row, number_format($transaccionesTotal));
        $sheet->setCellValue('C' . $row, '$' . number_format($totalGeneral, 2));
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFECB3');
        $row += 2;
    }
    
    // Ventas por Departamento (resumen)
    if (!empty($resumen['departamentos'])) {
        $sheet->setCellValue('A' . $row, 'VENTAS POR DEPARTAMENTO');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2196F3');
        $row++;
        
        $headers = ['Departamento', 'Ventas', 'Total ($)', 'Efectivo', 'Tarjeta-Credito','Tarjeta-Debito', 'QR'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
            $col++;
        }
        $row++;
        
        $totalesDept = [
            'ventas' => 0,
            'total' => 0,
            'efectivo' => 0,
            'tarjeta_credito' => 0,
            'tarjeta_debito' => 0,
            'qr' => 0
        ];
        
        foreach ($resumen['departamentos'] as $dept) {
            $sheet->setCellValue('A' . $row, $dept['departamento']);
            $sheet->setCellValue('B' . $row, number_format($dept['cantidad_ventas']));
            $sheet->setCellValue('C' . $row, '$' . number_format($dept['total_ventas'], 2));
            $sheet->setCellValue('D' . $row, '$' . number_format($dept['total_efectivo'], 2));
            $sheet->setCellValue('E' . $row, '$' . number_format($dept['total_tarjeta_credito'], 2));
            $sheet->setCellValue('F' . $row, '$' . number_format($dept['total_tarjeta_debito'], 2));
            $sheet->setCellValue('G' . $row, '$' . number_format($dept['total_qr'], 2));
            
            // Acumular totales
            $totalesDept['ventas'] += $dept['cantidad_ventas'];
            $totalesDept['total'] += $dept['total_ventas'];
            $totalesDept['efectivo'] += $dept['total_efectivo'];
            $totalesDept['tarjeta_credito'] += $dept['total_tarjeta_credito'];
            $totalesDept['tarjeta_debito'] += $dept['total_tarjeta_debito'];
            $totalesDept['qr'] += $dept['total_qr'];
            
            $row++;
        }
        
        // Fila de totales por departamento
        $sheet->setCellValue('A' . $row, 'TOTAL DEPARTAMENTOS');
        $sheet->setCellValue('B' . $row, number_format($totalesDept['ventas']));
        $sheet->setCellValue('C' . $row, '$' . number_format($totalesDept['total'], 2));
        $sheet->setCellValue('D' . $row, '$' . number_format($totalesDept['efectivo'], 2));
        $sheet->setCellValue('E' . $row, '$' . number_format($totalesDept['tarjeta_credito'], 2));
        $sheet->setCellValue('F' . $row, '$' . number_format($totalesDept['tarjeta_debito'], 2));
        $sheet->setCellValue('G' . $row, '$' . number_format($totalesDept['qr'], 2));
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFECB3');
    }
    
    // Ajustar columnas
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Bordes
    $sheet->getStyle('A1:G' . ($row))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
    
   
    public function obtenerPlanillasDisponibles() {
        $planillas = [];
        
        if (!is_dir($this->directorioBase)) {
            return $planillas;
        }
        
        $archivos = glob($this->directorioBase . "planilla_*.xlsx");
        
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $fechaArchivo = filemtime($archivo);
            
            // Extraer fecha del nombre del archivo
            preg_match('/planilla_(\d{4}-\d{2}-\d{2})\.xlsx/', $nombre, $matches);
            $fechaPlanilla = isset($matches[1]) ? $matches[1] : date('Y-m-d', $fechaArchivo);
            
            $planillas[] = [
                'nombre' => $nombre,
                'fecha' => $fechaPlanilla,
                'tamaño' => filesize($archivo),
                'fecha_modificacion' => date('d/m/Y H:i', $fechaArchivo)
            ];
        }
        
        // Ordenar por fecha descendente
        usort($planillas, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
        
        return $planillas;
    }
    
    /**
     * Descargar planilla específica
     */
    public function descargarPlanilla($nombreArchivo = null) {
        if (!$nombreArchivo) {
            $nombreArchivo = 'planilla_' . date('Y-m-d') . '.xlsx';
        }
        
        $rutaArchivo = $this->directorioBase . $nombreArchivo;
        
        if (!file_exists($rutaArchivo)) {
            throw new Exception('El archivo no existe: ' . $nombreArchivo);
        }
        
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers para descarga
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . filesize($rutaArchivo));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        
        // Enviar archivo
        readfile($rutaArchivo);
        exit;
    }
}
?>