<?php

ob_start();
require_once 'config.php';
require_once 'venta.php';
require_once 'reportes.php';

// Inicializar las clases
$ventaManager = new Venta();
$reportes = new Reportes();

// Variables para filtros
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// Mensaje de resultado de operaciones
$mensaje = '';
$tipo_mensaje = '';
$redirect_url = '';

// Procesar eliminación de ventas (solo administrador)
if (isset($_POST['delete']) && isset($_POST['ventaID'])) {
    $admin_password = $_POST['admin_password'] ?? '';
    $ventaID = (int)$_POST['ventaID'];
    
    // Verificar contraseña de administrador (cambiar por tu contraseña)
    if ($admin_password === 'admin123') {
        try {
            // Eliminar venta (cambiar estado a cancelado)
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("UPDATE ventas SET estado = 'cancelado' WHERE id = ?");
            $stmt->execute([$ventaID]);
            
            $redirect_url = $_SERVER['PHP_SELF'] . '?delete_success=1';
        } catch (Exception $e) {
            $mensaje = 'Error al eliminar venta: ' . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    } else {
        $redirect_url = $_SERVER['PHP_SELF'] . '?delete_error=1';
    }
}

// Procesar redirección si es necesaria
if (!empty($redirect_url)) {
    header('Location: ' . $redirect_url);
    exit;
}

// Obtener datos para mostrar
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // ===========================================
    // USAR LA FUNCIÓN UNIFICADA PARA LOS TOTALES
    // ===========================================
    $fecha_desde_real = $fecha_inicio ?: date('Y-m-d');
$fecha_hasta_real = $fecha_fin ?: date('Y-m-d');
$datosUnificados = $reportes->obtenerTotalesUnificados($fecha_desde_real, $fecha_hasta_real, $busqueda);
    
    // Extraer totales generales
    $totalesGenerales = $datosUnificados['totales_generales'];
    $total_efectivo = $totalesGenerales['efectivo'];
    $total_tarjeta_credito = $totalesGenerales['tarjeta-credito'];
    $total_tarjeta_debito = $totalesGenerales['tarjeta-debito'];
    $total_qr = $totalesGenerales['qr'];
    $total_general = $totalesGenerales['total'];
    $total_personas_general = $totalesGenerales['personas'];
    $total_general_bancos = $total_tarjeta_credito + $total_tarjeta_debito + $total_qr;

    // Extraer totales por departamento
    $totales_por_depto = $datosUnificados['totales_por_departamento'];
    
    // ===========================================
    // CONSULTA PARA LA TABLA DE VENTAS (sin cambios)
    // ===========================================
    
    // Construir consulta con filtros para la tabla
    $where = ["v.estado IN ('pagado', 'pendiente', 'completado')"];
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
    
    // Consulta para obtener las ventas individuales (para la tabla)
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
            'Ventas' as Departamento,
            CASE 
                WHEN v.tipo_pago = 'efectivo' THEN CONCAT('Efectivo - Recibido: $', FORMAT(v.monto_recibido, 2), ' - Vuelto: $', FORMAT(v.vuelto, 2))
                WHEN v.tipo_pago = 'tarjeta-credito' THEN CONCAT('Pago con Tarjeta (Ticket Fiscal) - Total con recargo: $', FORMAT(v.total, 2))
                WHEN v.tipo_pago = 'tarjeta-debito' THEN CONCAT('Pago con Tarjeta (Ticket Fiscal) - Total: $', FORMAT(v.total, 2))
                WHEN v.tipo_pago = 'qr' THEN 'Pago con QR/Transferencia (Ticket Fiscal)'
                WHEN v.tipo_pago = 'mixto' THEN 'Pago Mixto (Ver desglose)'
                ELSE CONCAT(UPPER(SUBSTRING(v.tipo_pago, 1, 1)), LOWER(SUBSTRING(v.tipo_pago, 2)))
            END as DetallePago
        FROM ventas v
        LEFT JOIN venta_items vi ON v.id = vi.venta_id
        LEFT JOIN departamentos d ON vi.departamento_id = d.id
        $whereClause
        GROUP BY v.id
        ORDER BY v.fecha_hora DESC
    ");
    $stmt->execute($params);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener detalles de pagos mixtos (para la tabla)
    $pagos_mixtos = [];
    if (!empty($ventas)) {
        $ventaIds = array_column($ventas, 'VentaID');
        $ventaIdsPlaceholder = str_repeat('?,', count($ventaIds) - 1) . '?';
        
        $stmt_mixtos = $db->prepare("
            SELECT venta_id, tipo_pago, monto 
            FROM venta_pagos_detalle 
            WHERE venta_id IN ($ventaIdsPlaceholder)
        ");
        $stmt_mixtos->execute($ventaIds);
        $detalles_mixtos = $stmt_mixtos->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detalles_mixtos as $detalle) {
            $pagos_mixtos[$detalle['venta_id']][$detalle['tipo_pago']] = $detalle['monto'];
        }
    }

    
    
} catch (Exception $e) {
    $mensaje = 'Error al cargar datos: ' . $e->getMessage();
    $tipo_mensaje = 'error';
    $ventas = [];
    $totales_por_depto = [];
    $total_efectivo = $total_tarjeta_credito= $total_tarjeta_debito = $total_qr = $total_general = 0;
    $total_personas_general = 0;
    $pagos_mixtos = [];
}



// Procesar mensajes de URL
if (isset($_GET['delete_success'])) {
    $mensaje = 'Venta cancelada exitosamente';
    $tipo_mensaje = 'success';
}
if (isset($_GET['delete_error'])) {
    $mensaje = 'Contraseña de administrador incorrecta';
    $tipo_mensaje = 'error';
}

// Función para obtener detalles de pago mixto
// Función para obtener detalles de pago mixto - CORREGIDA
function obtenerDetallePagoMixto($db, $ventaId) {
    $stmt = $db->prepare("
        SELECT tipo_pago, monto 
        FROM venta_pagos_detalle 
        WHERE venta_id = ? 
        ORDER BY tipo_pago
    ");
    $stmt->execute([$ventaId]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($detalles)) {
        return "Pago Mixto: Sin detalles registrados";
    }
    
    $detalle_texto = "Pago Mixto: ";
    $partes = [];
    foreach ($detalles as $detalle) {
        $tipo_texto = ($detalle['tipo_pago'] === 'efectivo') ? 'Efectivo' : 'Transferencia/QR';
        $partes[] = $tipo_texto . ": $" . number_format($detalle['monto'], 2);
    }
    
    return $detalle_texto . implode(' + ', $partes);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información de Ventas Realizadas</title>
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.6;
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

        .alert {
            padding: 15px;
            margin: 20px auto;
            width: 95%;
            border-radius: 5px;
            font-weight: bold;
        }

        .alert-success {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .table-container {
            width: 95%;
            margin: 20px auto;
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table thead {
            background-color: #343a40;
            color: white;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            word-wrap: break-word;
            font-size: 13px;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #f0f0f0;
        }

        .total-ventas-container {
            width: 95%;
            margin: 20px auto;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .total-recuadro {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: 10px;
            text-align: center;
            min-width: 200px;
            font-size: 1.1em;
        }

        .total-general {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
            font-weight: bold;
            font-size: 1.2em;
        }

        .efectivo {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .tarjeta {
            background-color: #cff4fc;
            border: 1px solid #b6effb;
            color: #055160;
        }

        .qr {
            background-color: #e2e3e5;
            border: 1px solid #d3d3d4;
            color: #41464b;
        }

        .departamento-section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            border-left: 5px solid #007bff;
        }

        .departamento-title {
            font-size: 1.5em;
            margin-bottom: 15px;
            color: #333;
            font-weight: bold;
        }

        .search-form {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 20px;
            align-items: center;
            width: 80%;
            margin: 20px auto;
            flex-wrap: wrap;
        }

        .search-form .form-control,
        .search-form input[type="date"] {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            flex-grow: 1;
            min-width: 200px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            color: white;
            background-color: #007bff;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            padding: 6px 10px;
            font-size: 12px;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .btn-print {
            background-color: #28a745;
            color: white;
            padding: 6px 10px;
            font-size: 12px;
        }

        .btn-print:hover {
            background-color: #218838;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 25px;
            border: 1px solid #ccc;
            width: 350px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-content h3 {
            margin-top: 0;
            color: #333;
            margin-bottom: 15px;
        }

        .modal-content input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .modal-buttons {
            text-align: right;
            margin-top: 20px;
        }

        .modal-buttons button {
            margin-left: 10px;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-cancelar {
            background-color: #6c757d;
            color: white;
        }

        .btn-eliminar {
            background-color: #dc3545;
            color: white;
        }

        .filtro-activo {
            background-color: #e8f4ff;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            font-weight: bold;
        }

        .estado-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .estado-pagado {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .estado-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .estado-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }

        @media print {
            .btn, nav, input, .search-form, .modal {
                display: none;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                font-size: 10px;
            }
            table td, table th {
                border: 1px solid black;
                padding: 4px;
                text-align: left;
            }
            body {
                margin: 10px;
            }
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
            <li><a href="Reporte.php">Reportes</a></li>
            <li><a href="caja.php">Cierre de caja</a></li>
        </ul>
    </nav>
</header>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?= $tipo_mensaje === 'error' ? 'error' : 'success' ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <h2>Filtrar Ventas</h2>
    <form method="GET" action="" class="search-form">
        <input type="text" name="buscar" placeholder="Buscar por código o ID de venta" 
               value="<?= htmlspecialchars($busqueda) ?>" class="form-control">
        <div>
            <label>Desde:</label>
            <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
        </div>
        <div>
            <label>Hasta:</label>
            <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
        </div>
        <button type="submit" class="btn">Buscar</button>
        <?php if(!empty($busqueda) || !empty($fecha_inicio) || !empty($fecha_fin)): ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn" style="background-color: #6c757d;">Limpiar filtros</a>
        <?php endif; ?>
    </form>
    
    <?php if(!empty($busqueda) || !empty($fecha_inicio) || !empty($fecha_fin)): ?>
    <div class="filtro-activo">
        Filtros activos: 
        <?php 
        $filtros = [];
        if(!empty($busqueda)) $filtros[] = "Búsqueda: " . htmlspecialchars($busqueda);
        if(!empty($fecha_inicio)) {
            $fecha_inicio_formateada = date('d/m/Y', strtotime($fecha_inicio));
            $filtros[] = "Desde: " . $fecha_inicio_formateada;
        }
        if(!empty($fecha_fin)) {
            $fecha_fin_formateada = date('d/m/Y', strtotime($fecha_fin));
            $filtros[] = "Hasta: " . $fecha_fin_formateada;
        }
        echo implode(" | ", $filtros);
        ?>
    </div>
    <?php endif; ?>
    
    <!-- Resumen General -->
    <h1>Resumen General de Ventas</h1>
    <div class="total-ventas-container">
        <div class="total-recuadro efectivo">
            <i class="fas fa-money-bill-wave"></i><br>
            Total Efectivo: <strong>$<?= number_format($total_efectivo, 2) ?></strong>
        </div>
        <div class="total-recuadro tarjeta">
            <i class="fas fa-credit-card"></i><br>
            Total Tarjeta-Credito: <strong>$<?= number_format($total_tarjeta_credito, 2) ?></strong>
        </div>
        <div class="total-recuadro tarjeta">
            <i class="fas fa-credit-card"></i><br>
            Total Tarjeta-Debito: <strong>$<?= number_format($total_tarjeta_debito, 2) ?></strong>
        </div>
        <div class="total-recuadro qr">
            <i class="fas fa-qrcode"></i><br>
            Total QR/Transferencia: <strong>$<?= number_format($total_qr, 2) ?></strong>
        </div>
        <div class="total-recuadro total-general">
            <i class="fas fa-calculator"></i><br>
            Total General: <strong>$<?= number_format($total_general, 2) ?></strong>
        </div>
        <div class="total-recuadro total-general">
            <i class="fas fa-calculator"></i><br>
            Total Banco: <strong>$<?= number_format($total_general_bancos, 2) ?></strong>
        </div>
        <div class="total-recuadro total-general">
            <i class="fas fa-users"></i><br>
            Total Personas Atendidas: <strong><?= number_format($total_personas_general, 0) ?></strong>
        </div>
    </div>

    <!-- Resumen por Departamentos - CORREGIDO -->
    <?php foreach ($totales_por_depto as $departamento => $totales_depto): ?>
    <div class="departamento-section">
        <div class="departamento-title">
            <i class="fas fa-store"></i> Resumen de Ventas - <?= htmlspecialchars($departamento) ?>
        </div>
        <div class="total-ventas-container">
            <div class="total-recuadro efectivo">
                <i class="fas fa-money-bill-wave"></i><br>
                Total Efectivo: <strong>$<?= number_format($totales_depto['efectivo'], 2) ?></strong>
            </div>
            <div class="total-recuadro tarjeta">
                <i class="fas fa-credit-card"></i><br>
                Total Tarjeta-Credito: <strong>$<?= number_format($totales_depto['tarjeta-credito'], 2) ?></strong>
            </div>
            <div class="total-recuadro tarjeta">
                <i class="fas fa-credit-card"></i><br>
                Total Tarjeta-Debito: <strong>$<?= number_format($totales_depto['tarjeta-debito'], 2) ?></strong>
            </div>
            <div class="total-recuadro qr">
                <i class="fas fa-qrcode"></i><br>
                Total QR/Transferencia: <strong>$<?= number_format($totales_depto['qr'], 2) ?></strong>
            </div>
            <div class="total-recuadro total-general">
                <i class="fas fa-calculator"></i><br>
                Total <?= htmlspecialchars($departamento) ?>: <strong>$<?= number_format($totales_depto['total'], 2) ?></strong>
            </div>
            <div class="total-recuadro total-general">
                <i class="fas fa-users"></i><br>
                Personas Atendidas: <strong><?= number_format($totales_depto['personas_atendidas'], 0) ?></strong>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <br><br>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Venta ID</th>
                <th style="width: 12%;">Fecha/Hora</th>
                <th style="width: 15%;">Productos</th>
                <th style="width: 8%;">Cant.</th>
                <th style="width: 10%;">Total</th>
                <th style="width: 12%;">Forma Pago</th>
                <th style="width: 20%;">Detalle Pago</th>
                <th style="width: 8%;">Estado</th>
                <th style="width: 7%;">Opciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($ventas)): ?>
            <?php foreach ($ventas as $venta): ?>
                <tr>
                    <td><strong>#<?= htmlspecialchars($venta["VentaID"]) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($venta["FechaVenta"])) ?></td>
                    <td style="font-size: 11px;">
                        <?php if (!empty($venta["Productos"])): ?>
                            <?= htmlspecialchars($venta["Productos"]) ?>
                        <?php else: ?>
                            <em>Sin productos registrados</em>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($venta["Cantidad"] ?? 0) ?></td>
                    <td><strong>$<?= number_format($venta["Total"], 2) ?></strong></td>
                    <td>
                        <?php
                        $iconoPago = '';
                        switch($venta['MedioPago']) {
                            case 'efectivo': $iconoPago = '<i class="fas fa-money-bill-wave"></i> Efectivo'; break;
                            case 'tarjeta-credito': $iconoPago = '<i class="fas fa-credit-card"></i> Tarjeta-Credito'; break;
                            case 'tarjeta-debito': $iconoPago = '<i class="fas fa-credit-card"></i> Tarjeta-Debito'; break;
                            case 'qr': $iconoPago = '<i class="fas fa-qrcode"></i> QR'; break;
                            case 'mixto': $iconoPago = '<i class="fas fa-coins"></i> Mixto'; break;
                            default: $iconoPago = htmlspecialchars($venta['MedioPago'] ?? 'N/A');
                        }
                        echo $iconoPago;
                        ?>
                    </td>
                    <td style="font-size: 11px;">
                        <?php 
                        if ($venta['MedioPago'] === 'mixto') {
                            echo obtenerDetallePagoMixto($db, $venta['VentaID']);
                        } else {
                            echo htmlspecialchars($venta['DetallePago']);
                        }
                        ?>
                    </td>
                    <td>
                        <span class="estado-badge estado-<?= $venta['estado'] ?>">
                            <?= ucfirst($venta['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($venta['estado'] !== 'cancelado'): ?>
                        <button type="button" class="btn-delete" onclick="openDeleteModal(<?= $venta['VentaID'] ?>)" title="Cancelar venta">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                        <a href="recibo.php?ventaID=<?= $venta['VentaID'] ?>" class="btn-print" title="Imprimir recibo">
                            <i class="fas fa-print"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="9" style="text-align: center; padding: 20px; color: #666;">
                <i class="fas fa-inbox"></i><br>
                No hay ventas disponibles con los filtros aplicados
            </td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f8f9fa; font-weight: bold;">
                <td colspan="4">TOTALES:</td>
                <td>$<?= number_format($total_general, 2) ?></td>
                <td colspan="4"><?= count($ventas) ?> ventas</td>
            </tr>
        </tfoot>
    </table>
</div>

<div style="margin-top: 20px; text-align: center;">
    <button class="btn" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir Reporte
    </button>
    <a href="reportes_detallados.php" class="btn" style="background-color: #28a745;">
        <i class="fas fa-chart-bar"></i> Ver Reportes Detallados
    </a>
</div>

<!-- Modal para confirmación de cancelación -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirmar Cancelación</h3>
        <p>¿Está seguro que desea <strong>cancelar</strong> esta venta?</p>
        <p><small style="color: #666;">Esta acción cambiará el estado de la venta a "cancelado".</small></p>
        <form method="POST">
            <input type="hidden" id="modalVentaID" name="ventaID">
            <input type="password" name="admin_password" placeholder="Contraseña de administrador" required>
            <div class="modal-buttons">
                <button type="button" class="btn-cancelar" onclick="closeModal()">Cancelar</button>
                <button type="submit" name="delete" class="btn-eliminar">Confirmar Cancelación</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('deleteModal');

    function openDeleteModal(ventaID) {
        document.getElementById('modalVentaID').value = ventaID;
        modal.style.display = "block";
    }

    function closeModal() {
        modal.style.display = "none";
        document.querySelector('form input[name="admin_password"]').value = '';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }

    // Tecla ESC para cerrar modal
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    // Auto actualizar cada 30 segundos (opcional)
    setInterval(function() {
        // Si necesitas actualización automática, puedes hacer una llamada AJAX a ajax_ventas.php
        console.log('Auto-refresh disponible');
    }, 30000);
</script>
</body>
</html>