<?php
session_start();

// Configuración y validación de archivos
$archivos_requeridos = ['config.php'];

foreach ($archivos_requeridos as $archivo) {
    if (!file_exists($archivo)) {
        die("Error: El archivo $archivo no existe.");
    }
    require_once $archivo;
}

// Cargar CalculadoraIVA si existe
$calculadora_disponible = false;
if (file_exists('CalculadoraIVA.php')) {
    try {
        require_once 'CalculadoraIVA.php';
        $calculadora_disponible = true;
    } catch (Exception $e) {
        error_log("CalculadoraIVA no disponible: " . $e->getMessage());
    }
}

// Conexión a base de datos
$servername = "localhost";
$username = "root";
$password = "";
$database = "mini_supermercado";

try {
    $conn = new mysqli($servername, $username, $password, $database);
    $conn->set_charset("utf8");
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función principal para mostrar recibo por venta
function mostrarReciboPorVenta($ventaID) {
    global $conn, $calculadora_disponible;

    if (!is_numeric($ventaID) || $ventaID <= 0) {
        echo "<div class='error-message'>ID de venta inválido.</div>";
        return;
    }

    try {
        // Consulta principal de venta
        $sql = "SELECT 
                    v.id as VentaID,
                    v.fecha_hora as FechaVenta,
                    v.total as Total,
                    v.tipo_pago as MedioPago,
                    v.monto_recibido,
                    v.vuelto,
                    COALESCE(v.ticket_fiscal, 0) as ticket_fiscal,
                    v.estado,
                    'Cliente General' as NombreCliente
                FROM ventas v
                WHERE v.id = ? AND v.estado IN ('pagado', 'pendiente', 'completado')";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conn->error);
        }
        
        $stmt->bind_param("i", $ventaID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo "<div class='error-message'>No se encontró la venta solicitada.</div>";
            return;
        }

        $venta = $result->fetch_assoc();

        // Consulta de productos
        $sql_productos = "SELECT 
                        d.nombre as Articulo,
                        vi.codigo_barras,
                        1 as Cantidad,
                        vi.precio as Precio,
                        vi.precio as Subtotal,
                        vi.departamento_id,
                        COALESCE(vi.precio_sin_iva, 0) as PrecioSinIVA,
                        COALESCE(vi.monto_iva, 0) as MontoIVA,
                        COALESCE(vi.porcentaje_iva, 0) as PorcentajeIVA
                      FROM venta_items vi
                      JOIN departamentos d ON vi.departamento_id = d.id
                      WHERE vi.venta_id = ?
                      ORDER BY vi.fecha_ticket";
        
        $stmt_productos = $conn->prepare($sql_productos);
        if (!$stmt_productos) {
            throw new Exception("Error preparando consulta productos: " . $conn->error);
        }
        
        $stmt_productos->bind_param("i", $ventaID);
        $stmt_productos->execute();
        $result_productos = $stmt_productos->get_result();

        $productos = [];
        $total_items = 0;
        
        while ($producto = $result_productos->fetch_assoc()) {
            // Si no hay IVA calculado y tenemos la calculadora, calcular
            if ($producto['PrecioSinIVA'] == 0 && $calculadora_disponible) {
                try {
                    $calculoIVA = CalculadoraIVA::calcularIVAInverso($producto['Precio'], $producto['departamento_id']);
                    $producto['PrecioSinIVA'] = $calculoIVA['precio_sin_iva'];
                    $producto['MontoIVA'] = $calculoIVA['monto_iva'];
                    $producto['PorcentajeIVA'] = $calculoIVA['porcentaje_iva'];
                } catch (Exception $e) {
                    // Calcular IVA básico (21% por defecto)
                    $producto['PrecioSinIVA'] = round($producto['Precio'] / 1.21, 2);
                    $producto['MontoIVA'] = $producto['Precio'] - $producto['PrecioSinIVA'];
                    $producto['PorcentajeIVA'] = 21.0;
                }
            } elseif ($producto['PrecioSinIVA'] == 0) {
                // Sin calculadora, usar IVA 21% por defecto
                $producto['PrecioSinIVA'] = round($producto['Precio'] / 1.21, 2);
                $producto['MontoIVA'] = $producto['Precio'] - $producto['PrecioSinIVA'];
                $producto['PorcentajeIVA'] = 21.0;
            }
            
            $productos[] = $producto;
            $total_items += $producto['Subtotal'];
        }

        // Calcular totales de IVA
        $totalesIVA = calcularTotalesVenta($productos);

        // Obtener pagos mixtos si existen
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
            }
        }

        // Verificar si necesita documento fiscal
        $es_fiscal = ($venta['ticket_fiscal'] == 1) || 
                    in_array($venta['MedioPago'], ['tarjeta-credito', 'tarjeta-debito', 'qr', 'tarjeta']);

        // Calcular recargo si aplica
        $recargo = 0;
        if (in_array($venta['MedioPago'], ['tarjeta-credito', 'tarjeta'])) {
            $recargo = $total_items * 0.10;
        }

        // Generar el HTML del recibo
        generarHTMLRecibo($venta, $productos, $totalesIVA, $pagos_mixtos, $recargo, $es_fiscal);

    } catch (Exception $e) {
        echo "<div class='error-message'>Error procesando la venta: " . htmlspecialchars($e->getMessage()) . "</div>";
        error_log("Error en mostrarReciboPorVenta: " . $e->getMessage());
    }
}

// Función para calcular totales de IVA
function calcularTotalesVenta($productos) {
    $subtotal_sin_iva = 0;
    $total_iva = 0;
    $iva_por_alicuota = [];
    $detalle_por_departamento = [];
    
    foreach ($productos as $producto) {
        $subtotal_sin_iva += $producto['PrecioSinIVA'];
        $total_iva += $producto['MontoIVA'];
        
        $alicuota = $producto['PorcentajeIVA'];
        if (!isset($iva_por_alicuota[$alicuota])) {
            $iva_por_alicuota[$alicuota] = ['iva' => 0, 'base' => 0];
        }
        $iva_por_alicuota[$alicuota]['iva'] += $producto['MontoIVA'];
        $iva_por_alicuota[$alicuota]['base'] += $producto['PrecioSinIVA'];
        
        // Detalle por departamento
        $depto_id = $producto['departamento_id'];
        if (!isset($detalle_por_departamento[$depto_id])) {
            $detalle_por_departamento[$depto_id] = [
                'nombre' => $producto['Articulo'],
                'cantidad_items' => 0,
                'subtotal_sin_iva' => 0,
                'total_iva' => 0,
                'porcentaje_iva' => $alicuota
            ];
        }
        $detalle_por_departamento[$depto_id]['cantidad_items']++;
        $detalle_por_departamento[$depto_id]['subtotal_sin_iva'] += $producto['PrecioSinIVA'];
        $detalle_por_departamento[$depto_id]['total_iva'] += $producto['MontoIVA'];
    }
    
    return [
        'subtotal_sin_iva' => $subtotal_sin_iva,
        'total_con_iva' => $subtotal_sin_iva + $total_iva,
        'total_iva' => $total_iva,
        'iva_por_alicuota' => $iva_por_alicuota,
        'detalle_por_departamento' => array_values($detalle_por_departamento)
    ];
}

// Función para generar HTML del recibo
function generarHTMLRecibo($venta, $productos, $totalesIVA, $pagos_mixtos, $recargo, $es_fiscal) {
    // Determinar método de pago
    $metodoPago = '';
    $detallePago = '';
    
    switch($venta['MedioPago']) {
        case 'efectivo':
            $metodoPago = 'Efectivo';
            if ($venta['monto_recibido'] > 0) {
                $detallePago = 'Recibido: $' . number_format($venta['monto_recibido'], 2) . ' - Vuelto: $' . number_format($venta['vuelto'], 2);
            }
            break;
        case 'tarjeta':
        case 'tarjeta-credito':
            $metodoPago = 'Tarjeta de Crédito';
            $detallePago = 'Pago con Tarjeta - Recargo 10% incluido';
            break;
        case 'tarjeta-debito':
            $metodoPago = 'Tarjeta de Débito';
            $detallePago = 'Pago con Tarjeta de Débito';
            break;
        case 'qr':
            $metodoPago = 'QR/Transferencia';
            $detallePago = 'Pago con QR/Transferencia';
            break;
        case 'mixto':
            $metodoPago = 'Pago Mixto';
            $partes_pago = [];
            foreach ($pagos_mixtos as $pago) {
                $tipo_texto = ($pago['tipo_pago'] === 'efectivo') ? 'Efectivo' : 'Transferencia';
                $partes_pago[] = $tipo_texto . ': $' . number_format($pago['monto'], 2);
            }
            $detallePago = implode(' + ', $partes_pago);
            if ($venta['vuelto'] > 0) {
                $detallePago .= ' - Vuelto: $' . number_format($venta['vuelto'], 2);
            }
            break;
        default:
            $metodoPago = ucfirst($venta['MedioPago']);
            $detallePago = 'Método de pago: ' . ucfirst($venta['MedioPago']);
            break;
    }

    echo "<div class='recibo-container'>";
    
    // Título según tipo de documento
    if ($es_fiscal) {
        echo "<h2 class='documento-fiscal'>DOCUMENTO FISCAL</h2>";
        echo "<h3>Factura Electrónica</h3>";
        echo "<div class='fiscal-info'>";
        echo "<p><strong>⚠️ PENDIENTE REGISTRO AFIP</strong></p>";
        echo "<p>Este documento requiere facturación electrónica</p>";
        echo "</div>";
    } else {
        echo "<h2>TICKET DE VENTA</h2>";
    }
    
    echo "<h2>Mini Supermercado La Nueva</h2>";
    echo "<h2>Consumidor Final</h2>";

    // Información básica
    echo "<div class='info-venta'>";
    echo "<p><strong>Número de Venta:</strong> " . $venta['VentaID'] . "</p>";
    echo "<p><strong>Fecha:</strong> " . date('d-m-Y H:i:s', strtotime($venta['FechaVenta'])) . "</p>";
    echo "<p><strong>Cliente:</strong> " . $venta['NombreCliente'] . "</p>";
    echo "<p><strong>Método de Pago:</strong> " . $metodoPago . "</p>";
    if (!empty($detallePago)) {
        echo "<p><strong>Detalle:</strong> " . $detallePago . "</p>";
    }
    echo "</div>";

    // Tabla de productos
    echo "<table class='tabla-productos'>";
    echo "<tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>IVA %</th><th>Subtotal</th></tr>";

    foreach ($productos as $producto) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($producto['Articulo']) . "</td>";
        echo "<td>1</td>";
        echo "<td>$" . number_format($producto['Precio'], 2) . "</td>";
        echo "<td>" . number_format($producto['PorcentajeIVA'], 1) . "%</td>";
        echo "<td>$" . number_format($producto['Subtotal'], 2) . "</td>";
        echo "</tr>";
    }

    // Totales IVA
    if ($es_fiscal) {
        echo "<tr class='subtotal-sin-iva'>";
        echo "<td colspan='4'><strong>SUBTOTAL SIN IVA</strong></td>";
        echo "<td><strong>$" . number_format($totalesIVA['subtotal_sin_iva'], 2) . "</strong></td>";
        echo "</tr>";

        foreach ($totalesIVA['iva_por_alicuota'] as $alicuota => $datos) {
            if ($datos['iva'] > 0) {
                echo "<tr class='fila-iva'>";
                echo "<td colspan='4'><strong>IVA {$alicuota}%</strong></td>";
                echo "<td><strong>+$" . number_format($datos['iva'], 2) . "</strong></td>";
                echo "</tr>";
            }
        }

        echo "<tr class='subtotal-con-iva'>";
        echo "<td colspan='4'><strong>SUBTOTAL CON IVA</strong></td>";
        echo "<td><strong>$" . number_format($totalesIVA['total_con_iva'], 2) . "</strong></td>";
        echo "</tr>";
    }

    // Recargo si aplica
    if ($recargo > 0) {
        echo "<tr class='fila-recargo'>";
        echo "<td colspan='4'><strong>RECARGO TARJETA 10%</strong></td>";
        echo "<td><strong>+$" . number_format($recargo, 2) . "</strong></td>";
        echo "</tr>";
    }

    // Total final
    echo "<tr class='total-final'>";
    echo "<td colspan='4'><strong>TOTAL FINAL</strong></td>";
    echo "<td><strong>$" . number_format($venta['Total'], 2) . "</strong></td>";
    echo "</tr>";

    echo "</table>";

    // Botones de acción
    echo "<div class='acciones'>";
    if ($es_fiscal) {
        echo "<button onclick='registrarAFIP({$venta['VentaID']})' class='btn-afip'>📄 Registrar en AFIP</button>";
        echo "<button onclick='imprimirFiscal()' class='btn-fiscal'>🖨️ Imprimir Fiscal</button>";
    }
    echo "<button onclick='window.print()' class='btn-imprimir'>🖨️ Imprimir</button>";
    echo "<a class='volver' href='info_ventas.php'>Volver</a>";
    echo "</div>";

    echo "</div>";

    // JavaScript para funcionalidades
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        const esFiscal = " . ($es_fiscal ? 'true' : 'false') . ";
        const metodoPago = '" . $venta['MedioPago'] . "';
        
        // Auto-impresión para documentos fiscales con ciertos métodos de pago
        if (esFiscal && ['tarjeta-credito', 'tarjeta-debito', 'qr', 'tarjeta'].includes(metodoPago)) {
            setTimeout(function() {
                if (confirm('Documento fiscal detectado. ¿Imprimir automáticamente?')) {
                    imprimirFiscal();
                }
            }, 1000);
        }
    });

    function registrarAFIP(ventaID) {
        // Esta función se implementará con la integración AFIP
        alert('Función de registro AFIP en desarrollo. Venta ID: ' + ventaID);
        // Aquí irá la llamada AJAX para registrar en AFIP
        /*
        fetch('afip_registrar.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({venta_id: ventaID})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Registrado en AFIP: CAE ' + data.cae);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
        */
    }

    function imprimirFiscal() {
        const style = document.createElement('style');
        style.innerHTML = `
            @media print {
                @page { size: 80mm auto; margin: 2mm; }
                body { font-family: 'Courier New', monospace; font-size: 11px; }
                .acciones, .fiscal-info { display: none; }
            }
        `;
        document.head.appendChild(style);
        window.print();
        setTimeout(() => document.head.removeChild(style), 1000);
    }
    </script>";
}

// Función de compatibilidad para pedidos (simplificada)
function mostrarReciboPorPedido($pedidoID) {
    echo "<div class='error-message'>Función de pedidos no implementada en esta versión. Use ventaID en su lugar.</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon">
    <title>Ticket de venta</title>
    <style>
/* CSS Optimizado para Impresora Térmica 80mm CON ESTILOS IVA */
body {
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f8f9fa;
    color: #212529;
}

header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #333;
    color: white;
    padding: 10px 20px;
}

header img {
    max-width: 60px;
    height: auto;
    border-radius: 50%;
}

header h1 {
    margin: 0;
    font-size: 24px;
    flex: 1;
    text-align: center;
}

nav ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    gap: 15px;
}

nav ul li {
    margin: 0;
}

nav ul li a {
    color: white;
    text-decoration: none;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 5px;
    transition: background-color 0.3s ease;
}

nav ul li a:hover {
    background-color: #575757;
}



.container {
    padding: 20px;
    background-color: white;
    max-width: 900px;
    margin: 20px auto;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.recibo-container h2 {
    text-align: center;
    color: black;
    margin-bottom: 20px;
    text-transform: uppercase;
}

.recibo-container h3 {
    text-align: center;
    color: #007bff;
    margin-bottom: 15px;
}

/* NUEVO: Estilos para información AFIP */
.afip-info {
    background-color: #e7f3ff;
    padding: 10px;
    border-radius: 5px;
    margin: 10px 0;
    border-left: 4px solid #007bff;
}

.afip-info p {
    margin: 5px 0;
    font-size: 0.9em;
}

/* NUEVO: Indicador de impresión automática */
.impresion-automatica {
    background-color: #d4edda;
    color: #155724;
    padding: 8px;
    border-radius: 4px;
    text-align: center;
    margin: 10px 0;
    font-weight: bold;
}

.cliente-info h3 {
    margin-bottom: 10px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}

.cliente-info p, .pago-info p {
    margin: 5px 0;
    font-size: 1rem;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    font-size: 1rem;
    background-color: white;
}

table th, table td {
    border: 1px solid #dddddd;
    text-align: center;
    padding: 10px;
}

table th {
    background-color: black;
    color: white;
    text-transform: uppercase;
}

table tr:nth-child(even) {
    background-color: #f2f2f2;
}

table tr:last-child td {
    font-weight: bold;
    background-color: black;
    color: white;
}

/* NUEVO: Estilos para tabla de resumen IVA */
.resumen-iva {
    margin: 20px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.resumen-iva h4 {
    margin-top: 0;
    color: #495057;
}

.tabla-iva {
    font-size: 0.9em;
}

.tabla-iva th {
    background-color: #6c757d;
    color: white;
}

.acciones {
    text-align: center;
    margin-top: 20px;
}

.volver {
    color: #e2effdff; /* Color del texto */
    text-decoration: none; /* Quitar el subrayado */
    font-size: 16px; /* Tamaño de la fuente */
    font-weight: bold; /* Negrita */
    padding: 10px 15px; /* Espaciado alrededor del texto */
    border: 2px solid #007BFF; /* Borde alrededor del enlace */
    border-radius: 5px; /* Bordes redondeados */
    transition: all 0.3s ease; /* Efecto de transición */
    background-color: #3789e6ff; /* Color de fondo al pasar el ratón */
}

.volver a:hover {
    color: white; /* Color del texto al pasar el ratón */
    background-color: #007BFF; /* Color de fondo al pasar el ratón */
    border-color: #0056b3; /* Color del borde al pasar el ratón */
}


.acciones button {
    padding: 10px 20px;
    font-size: 1rem;
    background-color: black;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    margin: 5px;
}

.acciones button:hover {
    background-color: #0056b3;
}

/* NUEVO: Botón especial para documentos fiscales */
.btn-fiscal {
    background-color: #28a745 !important;
    border: 2px solid #28a745;
}

.btn-fiscal:hover {
    background-color: #218838 !important;
}

@media (max-width: 768px) {
    .container {
        padding: 15px;
    }

    table th, table td {
        font-size: 0.9rem;
        padding: 8px;
    }

    .afip-info {
        font-size: 0.8em;
    }
}

h3 {
    text-align: center;
}

.error-message {
    color: #dc3545;
    text-align: center;
    padding: 20px;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 5px;
    margin: 20px 0;
}

@media print {
    /* Ocultar elementos no necesarios */
    header, nav, .acciones, .impresion-automatica, .afip-info {
        display: none !important;
    }

    /* Configuración básica del cuerpo */
    * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    body {
        font-family: 'Courier New', monospace !important;
        font-size: 12px !important;
        line-height: 1.3 !important;
        color: #000 !important;
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 58mm !important;
        font-weight: bold !important;
    }
    
    /* Container principal */
    .container {
        width: 100% !important;
        max-width: 58mm !important;
        margin: 0 !important;
        padding: 1mm !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        background: transparent !important;
    }
    
    .recibo-container {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }

    /* Títulos */
    .recibo-container h2 {
        font-size: 12px !important;
        font-weight: bold !important;
        text-align: center !important;
        margin: 1mm 0 !important;
        padding: 0 !important;
        text-transform: uppercase !important;
        line-height: 1.2 !important;
    }

    .recibo-container h3 {
        font-size: 10px !important;
        font-weight: bold !important;
        text-align: center !important;
        margin: 1mm 0 !important;
        padding: 0 !important;
    }
    
    /* Información de pago */
    .pago-info {
        margin: 2mm 0 !important;
        padding: 0 !important;
    }
    
    .pago-info p {
        font-size: 9px !important;
        margin: 0.5mm 0 !important;
        padding: 0 !important;
        line-height: 1.2 !important;
        text-align: left !important;
        font-weight: bold !important;
    }
    
    /* Tabla principal con IVA */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin: 1mm 0 !important;
        font-size: 8px !important;
        border: none !important;
    }
    
    /* Encabezados de tabla */
    table th {
        background-color: #000 !important;
        color: #fff !important;
        font-weight: bold !important;
        padding: 1mm !important;
        text-align: center !important;
        font-size: 7px !important;
        border: 1px solid #000 !important;
        text-transform: uppercase !important;
    }
    
    /* Celdas de datos */
    table td {
        padding: 1mm !important;
        text-align: center !important;
        font-size: 8px !important;
        border: 1px solid #000 !important;
        background-color: #fff !important;
        color: #000 !important;
        vertical-align: middle !important;
        font-weight: bold !important;
    }

    /* Columna de producto más ancha */
    table td:first-child, table th:first-child {
        width: 30% !important;
        text-align: left !important;
        padding-left: 0.5mm !important;
        font-size: 8px !important;
    }
    
    /* Columnas más estrechas para cantidad, precio, IVA, subtotal */
    table td:nth-child(2), table th:nth-child(2) { width: 15% !important; }
    table td:nth-child(3), table th:nth-child(3) { width: 20% !important; }
    table td:nth-child(4), table th:nth-child(4) { width: 15% !important; }
    table td:nth-child(5), table th:nth-child(5) { width: 20% !important; }
    
    /* Filas de totales */
    table tr[style*="background-color: #e8f4f8"] td,
    table tr[style*="background-color: #f8f9fa"] td {
        background-color: #e0e0e0 !important;
        font-weight: bold !important;
        font-size: 8px !important;
        border: 1px solid #000 !important;
    }
    
    /* Fila del IVA */
    table tr[style*="background-color: #fff3cd"] td {
        background-color: #e0e0e0 !important;
        font-weight: bold !important;
        font-size: 8px !important;
        border: 1px solid #000 !important;
    }
    
    /* Fila del total final */
    table tr[style*="background-color: #d4edda"] td {
        background-color: #000 !important;
        color: #fff !important;
        font-weight: bold !important;
        font-size: 9px !important;
        border: 2px solid #000 !important;
        padding: 1.5mm !important;
    }

    /* NUEVO: Ocultar resumen IVA detallado en impresión */
    .resumen-iva {
        display: none !important;
    }

    /* Mensaje final */
    h3 {
        font-size: 12px !important;
        text-align: center !important;
        margin: 3mm 0 2mm 0 !important;
        padding: 0 !important;
        font-weight: bold !important;
        font-style: normal !important;
        line-height: 1.3 !important;
    }

    /* Forzar salto de página después del ticket */
    .recibo-container::after {
        content: "" !important;
        display: block !important;
        page-break-after: always !important;
    }

    /* Configuración de página */
    @page {
        margin: 2mm !important;
        size: 80mm auto !important;
    }

    /* Resetear centrado */
    html, body {
        text-align: left !important;
    }
    
    .container {
        display: block !important;
        text-align: left !important;
    }
}
    </style>
</head>
<body>
<header>
    <h1>Recibo Mini Supermercado la Nueva</h1>
    <img src="./imagenes/tu-web-mensajes.jpg" alt="Logo">
    <nav>
        <ul class="nav-buttons">
            <li><a href="index.php">Inicio</a></li>
            <li><a href="Nuevaventa.php">Venta</a></li>
            <li><a href="info_ventas.php">Pedidos</a></li>
            <li><a href="caja.php">Caja</a></li>
        </ul>
    </nav>
</header>

<center>
    <div class="container">
        <div id="carrito">
            <?php 
            // Verificar si se recibió ventaID o pedidoID
            if (isset($_GET['ventaID'])) {
                // Validar que ventaID es un número
                $ventaID = filter_input(INPUT_GET, 'ventaID', FILTER_VALIDATE_INT);
                
                if ($ventaID !== false && $ventaID !== null) {
                    mostrarReciboPorVenta($ventaID);
                } else {
                    echo "<div class='error-message'>Número de venta inválido.</div>";
                }
            } elseif (isset($_GET['pedidoID'])) {
                // Mantener compatibilidad con pedidoID
                $pedidoID = filter_input(INPUT_GET, 'pedidoID', FILTER_VALIDATE_INT);
                
                if ($pedidoID !== false && $pedidoID !== null) {
                    mostrarReciboPorPedido($pedidoID);
                } else {
                    echo "<div class='error-message'>Número de pedido inválido.</div>";
                }
            } else {
                echo "<div class='error-message'>No se especificó un número de venta o pedido.</div>";
            }
            ?>
        </div>
    </div>
</center>
<h3>"Muchas gracias por su compra, que la disfrute."</h3> 
</body>
</html>

<?php
$conn->close();
?>