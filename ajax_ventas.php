<?php
// ajax_ventas.php - Archivo separado para manejar peticiones AJAX
require_once 'config.php';
require_once 'venta.php';
require_once 'reportes.php';

// Solo manejar peticiones AJAX
if (!isset($_GET['ajax']) && !isset($_POST['ajax']) && 
    !(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
    
    jsonResponse(false, null, 'Acceso no autorizado', 403);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener parámetros de filtro
    $busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
    $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
    $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
    
    // Construir consulta con filtros
    $where = ["v.estado IN ('pagado', 'pendiente')"];
    $params = [];
    
    if ($fecha_inicio) {
        $where[] = "DATE(v.fecha_hora) >= ?";
        $params[] = $fecha_inicio;
    }
    
    if ($fecha_fin) {
        $where[] = "DATE(v.fecha_hora) <= ?";
        $params[] = $fecha_fin;
    }
    
    if ($busqueda) {
        $where[] = "(vi.codigo_barras LIKE ? OR CAST(v.id AS CHAR) LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    // Consulta principal de ventas
    $stmt = $db->prepare("
        SELECT 
            v.id as VentaID,
            v.fecha_hora as FechaVenta,
            v.total as Total,
            v.tipo_pago as MedioPago,
            v.monto_recibido,
            v.vuelto,
            v.ticket_fiscal,
            v.estado,
            'Cliente General' as NombreCliente,
            GROUP_CONCAT(DISTINCT vi.codigo_barras ORDER BY vi.id SEPARATOR ', ') as CodigosProducto,
            GROUP_CONCAT(DISTINCT CONCAT(d.nombre, ' ($', vi.precio, ')') ORDER BY vi.id SEPARATOR ', ') as Productos,
            COUNT(vi.id) as Cantidad,
            'Ventas' as Departamento
        FROM ventas v
        LEFT JOIN venta_items vi ON v.id = vi.venta_id
        LEFT JOIN departamentos d ON vi.departamento_id = d.id
        $whereClause
        GROUP BY v.id
        ORDER BY v.fecha_hora DESC
    ");
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener totales
    $reportes = new Reportes();
    $totales = $reportes->ventasPorFormaPago($fecha_inicio, $fecha_fin);
    
    // Calcular totales por forma de pago
    $total_efectivo = 0;
    $total_tarjeta_credito = 0;
    $total_tarjeta_debito = 0;
    $total_qr = 0;
    $total_general = 0;
    
    foreach ($totales as $total) {
        switch ($total['tipo_pago']) {
            case 'efectivo':
                $total_efectivo = $total['total_monto'];
                break;
            case 'tarjeta-credito':
                $total_tarjeta_credito = $total['total_monto'];
                break;
            case 'tarjeta-debito':
                $total_tarjeta_debito = $total['total_monto'];
                break;
            case 'qr':
                $total_qr = $total['total_monto'];
                break;
        }
        $total_general += $total['total_monto'];
    }
    
    jsonResponse(true, [
        'ventas' => $ventas,
        'totales' => [
            'efectivo' => $total_efectivo,
            'tarjeta-credito' => $total_tarjeta_credito,
            'tarjeta-debito' => $total_tarjeta_debito,
            'qr' => $total_qr,
            'general' => $total_general
        ]
    ], 'Datos actualizados correctamente');
    
} catch (Exception $e) {
    jsonResponse(false, null, 'Error al obtener datos: ' . $e->getMessage(), 500);
}
?>