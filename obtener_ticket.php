
<?php
// obtener_ticket.php
// Archivo para obtener solo el texto del ticket (sin HTML)

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexión a base de datos (usar la misma configuración que tu sistema)
$servername = "localhost";
$username = "root";
$password = "";
$database = "mini_supermercado";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo "Error: No se pudo conectar a la base de datos";
    exit;
}

// Incluir las funciones de tu sistema original
function centrarTexto($texto, $ancho = 32) {
    $longitud = mb_strlen($texto, 'UTF-8');
    if ($longitud >= $ancho) return $texto;
    $espacios = floor(($ancho - $longitud) / 2);
    return str_repeat(' ', $espacios) . $texto;
}

function lineaIzqDer($izq, $der, $ancho = 32) {
    $longitudIzq = mb_strlen($izq, 'UTF-8');
    $longitudDer = mb_strlen($der, 'UTF-8');
    $espacios = $ancho - $longitudIzq - $longitudDer;
    return $izq . str_repeat(' ', max(1, $espacios)) . $der;
}

// Función para generar ticket optimizada para impresión térmica
function generarTicketTermico($ventaID, $conn) {
    $sql = "SELECT 
                v.id as VentaID,
                v.fecha_hora as FechaVenta,
                v.total as Total,
                v.tipo_pago as MedioPago,
                v.monto_recibido,
                v.vuelto,
                v.estado,
                'Cliente General' as NombreCliente
            FROM ventas v
            WHERE v.id = ? AND v.estado IN ('pagado', 'pendiente', 'completado')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return "Error: " . $conn->error;
    }
    
    $stmt->bind_param("i", $ventaID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return "Error: Venta no encontrada";
    }

    $venta = $result->fetch_assoc();

    // Obtener productos
    $sql_productos = "SELECT 
                d.nombre as Articulo,
                1 as Cantidad,
                vi.precio as Precio,
                vi.precio as Subtotal
              FROM venta_items vi
              JOIN departamentos d ON vi.departamento_id = d.id
              WHERE vi.venta_id = ?";
    
    $stmt_productos = $conn->prepare($sql_productos);
    if (!$stmt_productos) {
        return "Error: " . $conn->error;
    }
    
    $stmt_productos->bind_param("i", $ventaID);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();

    $productos = [];
    $total_items = 0;
    while ($producto = $result_productos->fetch_assoc()) {
        $productos[] = $producto;
        $total_items += $producto['Subtotal'];
    }

    // Obtener pagos mixtos si aplica
    $pagos_mixtos = [];
    if ($venta['MedioPago'] === 'mixto') {
        $sql_pagos = "SELECT tipo_pago, monto FROM venta_pagos_detalle WHERE venta_id = ? ORDER BY tipo_pago";
        $stmt_pagos = $conn->prepare($sql_pagos);
        if ($stmt_pagos) {
            $stmt_pagos->bind_param("i", $ventaID);
            $stmt_pagos->execute();
            $result_pagos = $stmt_pagos->get_result();
            
            while ($pago = $result_pagos->fetch_assoc()) {
                $pagos_mixtos[] = $pago;
            }
            $stmt_pagos->close();
        }
    }

    // GENERAR TICKET PARA IMPRESION TERMICA
    $ticket = "";
    
    // Header - Más compacto para térmica
    $ticket .= centrarTexto("MINI SUPERMERCADO LA NUEVA") . "\n";
    $ticket .= centrarTexto("CONSUMIDOR FINAL") . "\n";
    $ticket .= str_repeat("=", 32) . "\n";
    
    // Información básica
    $ticket .= "N° Venta: " . $venta['VentaID'] . "\n";
    $ticket .= date('d/m/Y H:i', strtotime($venta['FechaVenta'])) . "\n";
    $ticket .= "Cliente: " . $venta['NombreCliente'] . "\n";
    $ticket .= str_repeat("-", 32) . "\n";
    
    // Productos - Formato optimizado para térmica
    foreach ($productos as $producto) {
        // Nombre del producto (truncar si es muy largo)
        $nombre = mb_substr($producto['Articulo'], 0, 30, 'UTF-8');
        $ticket .= $nombre . "\n";
        
        // Cantidad y precio
        $linea_producto = $producto['Cantidad'] . "x$" . number_format($producto['Precio'], 2);
        $precio_total = "$" . number_format($producto['Subtotal'], 2);
        $ticket .= lineaIzqDer($linea_producto, $precio_total) . "\n";
    }
    
    $ticket .= str_repeat("-", 32) . "\n";
    
    // Cálculo de totales
    $total_sin_recargo = $total_items;
    $recargo = 0;
    $total_final = floatval($venta['Total']);
    
    // Aplicar recargo por tarjeta si corresponde
    if ($venta['MedioPago'] === 'tarjeta') {
        $recargo = $total_sin_recargo * 0.10;
    }
    
    // Mostrar totales
    $ticket .= lineaIzqDer("SUBTOTAL:", "$" . number_format($total_sin_recargo, 2)) . "\n";
    
    if ($recargo > 0) {
        $ticket .= lineaIzqDer("RECARGO 10%:", "$" . number_format($recargo, 2)) . "\n";
    }
    
    $ticket .= lineaIzqDer("TOTAL:", "$" . number_format($total_final, 2)) . "\n";
    $ticket .= str_repeat("-", 32) . "\n";
    
    // Información de pago
    switch($venta['MedioPago']) {
        case 'efectivo':
            $ticket .= "PAGO: Efectivo\n";
            if ($venta['monto_recibido'] > 0) {
                $ticket .= lineaIzqDer("Recibido:", "$" . number_format($venta['monto_recibido'], 2)) . "\n";
            }
            if ($venta['vuelto'] > 0) {
                $ticket .= lineaIzqDer("Vuelto:", "$" . number_format($venta['vuelto'], 2)) . "\n";
            }
            break;
            
        case 'tarjeta':
            $ticket .= "PAGO: Tarjeta (Recargo incluido)\n";
            break;
            
        case 'qr':
            $ticket .= "PAGO: QR/Transferencia\n";
            break;
            
        case 'mixto':
            $ticket .= "PAGO: Mixto\n";
            foreach ($pagos_mixtos as $pago) {
                $tipo = ($pago['tipo_pago'] === 'efectivo') ? 'Efectivo:' : 'Transfer.:';
                $ticket .= lineaIzqDer($tipo, "$" . number_format($pago['monto'], 2)) . "\n";
            }
            if ($venta['vuelto'] > 0) {
                $ticket .= lineaIzqDer("Vuelto:", "$" . number_format($venta['vuelto'], 2)) . "\n";
            }
            break;
            
        default:
            $ticket .= "PAGO: " . ucfirst($venta['MedioPago']) . "\n";
            break;
    }
    
    $ticket .= str_repeat("=", 32) . "\n";
    
    // Footer
    $ticket .= centrarTexto("GRACIAS POR SU COMPRA") . "\n";
    $ticket .= centrarTexto("QUE LA DISFRUTE") . "\n";
    
    // Espacios adicionales para corte de papel
    $ticket .= "\n\n\n";
    
    // Cerrar statements
    $stmt->close();
    $stmt_productos->close();
    
    return $ticket;
}

// Validar parámetros
if (!isset($_GET['ventaID'])) {
    http_response_code(400);
    echo "Error: ID de venta no especificado";
    exit;
}

$ventaID = filter_input(INPUT_GET, 'ventaID', FILTER_VALIDATE_INT);

if ($ventaID === false || $ventaID === null || $ventaID <= 0) {
    http_response_code(400);
    echo "Error: ID de venta inválido";
    exit;
}

// Generar y devolver el ticket
try {
    $ticketTexto = generarTicketTermico($ventaID, $conn);
    
    // Verificar si hay error en el ticket
    if (strpos($ticketTexto, 'Error:') === 0) {
        http_response_code(404);
        echo $ticketTexto;
        exit;
    }
    
    // Devolver el ticket como texto plano
    echo $ticketTexto;
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
} finally {
    $conn->close();
}