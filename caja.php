<?php
ob_start();
define('DISABLE_JSON_RESPONSE', true);
require_once 'config.php';

class Caja {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function abrirCaja($montoInicial, $observaciones = null, $usuario = 'Sistema') {
        try {
            $fecha = date('Y-m-d');
            $horaApertura = date('H:i:s');
            
            // Verificar si ya hay una caja abierta para hoy
            $stmt = $this->db->prepare("
                SELECT id, estado FROM cajas WHERE fecha = ? AND estado = 'abierta'
            ");
            $stmt->execute([$fecha]);
            $cajaExistente = $stmt->fetch();
            
            if ($cajaExistente) {
                throw new Exception("Ya existe una caja abierta para el día de hoy");
            }
            
            // Crear nueva caja
            $stmt = $this->db->prepare("
                INSERT INTO cajas (
                    fecha, hora_apertura, monto_inicial, estado, usuario, observaciones
                ) VALUES (?, ?, ?, 'abierta', ?, ?)
            ");
            $stmt->execute([$fecha, $horaApertura, $montoInicial, $usuario, $observaciones]);
            
            $cajaId = $this->db->lastInsertId();
            
            // NO registrar movimiento de apertura automáticamente
            // El monto inicial ya está en la tabla cajas
            
            return $cajaId;
        } catch (PDOException $e) {
            throw new Exception("Error al abrir caja: " . $e->getMessage());
        }
    }

    public function obtenerGastosPorFechas($fechaDesde, $fechaHasta) {
    try {
        $stmt = $this->db->prepare("
            SELECT 
                mc.id,
                mc.fecha_hora as fecha,
                mc.concepto,
                mc.monto,
                CASE 
                    WHEN mc.observaciones IS NOT NULL AND mc.observaciones != '' 
                    THEN mc.observaciones 
                    ELSE mc.forma_pago 
                END as medio_pago,
                mc.observaciones
            FROM movimientos_caja mc 
            INNER JOIN cajas c ON mc.caja_id = c.id
            WHERE mc.tipo = 'egreso' 
            AND DATE(mc.fecha_hora) BETWEEN ? AND ?
            ORDER BY mc.fecha_hora DESC
        ");
        $stmt->execute([$fechaDesde, $fechaHasta]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        throw new Exception("Error al obtener gastos: " . $e->getMessage());
    }
}

// Agregar esta función a tu clase Caja
public function eliminarGasto($gastoId) {
    try {
        $stmt = $this->db->prepare("DELETE FROM movimientos_caja WHERE id = ? AND tipo = 'egreso'");
        $stmt->execute([$gastoId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se encontró el gasto o no se pudo eliminar");
        }
        
        return true;
    } catch (PDOException $e) {
        throw new Exception("Error al eliminar gasto: " . $e->getMessage());
    }
}
    
    public function cerrarCaja($cajaId, $observaciones = null) {
        try {
            $this->db->beginTransaction();
            
            // Verificar que la caja esté abierta
            $stmt = $this->db->prepare("
                SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'
            ");
            $stmt->execute([$cajaId]);
            $caja = $stmt->fetch();
            
            if (!$caja) {
                throw new Exception("La caja no está abierta o no existe");
            }
            
            // Calcular totales de ventas
            $fecha = $caja['fecha'];
            $totalesVentas = $this->calcularTotalesVentas($fecha);
            
            // Calcular totales de movimientos (excluyendo ventas automáticas)
            $totalesMovimientos = $this->calcularTotalesMovimientos($cajaId);
            
            // Calcular monto final esperado
            $montoFinal = $caja['monto_inicial'] + $totalesVentas['efectivo'] + $totalesMovimientos['ingresos_extra'] - $totalesMovimientos['egresos'];
            
            // Actualizar caja
            $stmt = $this->db->prepare("
                UPDATE cajas SET 
                    hora_cierre = NOW(),
                    monto_final = ?,
                    total_ventas_efectivo = ?,
                    total_ventas_credito = ?,
                    total_ventas_debito = ?,
                    total_ventas_transferencia = ?,
                    total_ventas = ?,
                    gastos = ?,
                    ingresos_extra = ?,
                    diferencia = 0,
                    observaciones = CONCAT(COALESCE(observaciones, ''), IF(COALESCE(observaciones, '') = '', '', '. '), 'Cierre: ', COALESCE(?, '')),
                    estado = 'cerrada'
                WHERE id = ?
            ");
            $stmt->execute([
                $montoFinal,
                $totalesVentas['efectivo'],
                $totalesVentas['tarjeta-credito'],
                $totalesVentas['tarjeta-debito'], // debito = tarjeta
                $totalesVentas['qr'], // transferencia = qr
                $totalesVentas['total'],
                $totalesMovimientos['egresos'],
                $totalesMovimientos['ingresos_extra'],
                $observaciones,
                $cajaId
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'monto_final' => $montoFinal,
                'total_ventas' => $totalesVentas['total'],
                'total_movimientos' => $totalesMovimientos
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function registrarMovimiento($cajaId, $tipo, $concepto, $monto, $formaPago = 'efectivo', $ventaId = null, $observaciones = null) {
    try {
        // DEBUG: Verificar qué se está guardando
        error_log("Registrando movimiento - Forma pago: $formaPago, Concepto: $concepto");
        
        $stmt = $this->db->prepare("
            INSERT INTO movimientos_caja (
                caja_id, fecha_hora, tipo, concepto, monto, forma_pago, venta_id, observaciones
            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cajaId, $tipo, $concepto, $monto, $formaPago, $ventaId, $observaciones]);
        
        return $this->db->lastInsertId();
    } catch (PDOException $e) {
        throw new Exception("Error al registrar movimiento: " . $e->getMessage());
    }
}
    
    public function obtenerCajaActiva() {
        try {
            $fecha = date('Y-m-d');
            $stmt = $this->db->prepare("
                SELECT * FROM cajas WHERE fecha = ? AND estado = 'abierta'
            ");
            $stmt->execute([$fecha]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Error al obtener caja activa: " . $e->getMessage());
        }
    }
    
    public function calcularTotalesVentas($fecha) {
    try {
        // ESTRATEGIA: Separar ventas simples de ventas mixtas para evitar doble contabilización
        
        // 1. Obtener totales de ventas SIMPLES (no mixtas) desde tabla ventas
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as ventas_simples,
                COALESCE(SUM(CASE WHEN tipo_pago = 'efectivo' THEN total ELSE 0 END), 0) as efectivo_simple,
                COALESCE(SUM(CASE WHEN tipo_pago = 'tarjeta-credito' THEN total ELSE 0 END), 0) as credito_simple,
                COALESCE(SUM(CASE WHEN tipo_pago = 'tarjeta-debito' THEN total ELSE 0 END), 0) as debito_simple,
                COALESCE(SUM(CASE WHEN tipo_pago = 'qr' THEN total ELSE 0 END), 0) as qr_simple,
                COALESCE(SUM(CASE WHEN tipo_pago != 'mixto' THEN total ELSE 0 END), 0) as total_simples
            FROM ventas 
            WHERE DATE(fecha_hora) = ? 
            AND estado IN ('pagado', 'completado') 
            AND tipo_pago != 'mixto'
        ");
        $stmt->execute([$fecha]);
        $totalesSimples = $stmt->fetch();
        
        // 2. Obtener totales de ventas MIXTAS desde venta_pagos_detalle
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT v.id) as ventas_mixtas,
                COALESCE(SUM(CASE WHEN vpd.tipo_pago = 'efectivo' THEN vpd.monto ELSE 0 END), 0) as efectivo_mixto,
                COALESCE(SUM(CASE WHEN vpd.tipo_pago = 'tarjeta-credito' THEN vpd.monto ELSE 0 END), 0) as credito_mixto,
                COALESCE(SUM(CASE WHEN vpd.tipo_pago = 'tarjeta-debito' THEN vpd.monto ELSE 0 END), 0) as debito_mixto,
                COALESCE(SUM(CASE WHEN vpd.tipo_pago IN ('qr', 'transferencia') THEN vpd.monto ELSE 0 END), 0) as qr_mixto,
                COALESCE(SUM(vpd.monto), 0) as total_mixtos
            FROM ventas v
            INNER JOIN venta_pagos_detalle vpd ON v.id = vpd.venta_id
            WHERE DATE(v.fecha_hora) = ? 
            AND v.estado IN ('pagado', 'completado') 
            AND v.tipo_pago = 'mixto'
        ");
        $stmt->execute([$fecha]);
        $totalesMixtos = $stmt->fetch();
        
        // 3. COMBINAR ambos resultados
        return [
            'total_transacciones' => $totalesSimples['ventas_simples'] + $totalesMixtos['ventas_mixtas'],
            'efectivo' => $totalesSimples['efectivo_simple'] + $totalesMixtos['efectivo_mixto'],
            'tarjeta-credito' => $totalesSimples['credito_simple'] + $totalesMixtos['credito_mixto'],
            'tarjeta-debito' => $totalesSimples['debito_simple'] + $totalesMixtos['debito_mixto'],
            'qr' => $totalesSimples['qr_simple'] + $totalesMixtos['qr_mixto'],
            'total' => $totalesSimples['total_simples'] + $totalesMixtos['total_mixtos']
        ];
        
    } catch (PDOException $e) {
        throw new Exception("Error al calcular totales: " . $e->getMessage());
    }
}

public function obtenerDetallesPagosPorFecha($fecha) {
    try {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(CASE 
                    WHEN vpd.tipo_pago = 'efectivo' AND v.tipo_pago = 'mixto' 
                    THEN vpd.monto ELSE 0 
                END), 0) as efectivo_detalle,
                COALESCE(SUM(CASE 
                    WHEN vpd.tipo_pago = 'tarjeta-credito' AND v.tipo_pago = 'mixto' 
                    THEN vpd.monto ELSE 0 
                END), 0) as credito_detalle,
                COALESCE(SUM(CASE 
                    WHEN vpd.tipo_pago = 'tarjeta-debito' AND v.tipo_pago = 'mixto' 
                    THEN vpd.monto ELSE 0 
                END), 0) as debito_detalle,
                COALESCE(SUM(CASE 
                    WHEN vpd.tipo_pago IN ('qr', 'transferencia') AND v.tipo_pago = 'mixto' 
                    THEN vpd.monto ELSE 0 
                END), 0) as qr_detalle
            FROM venta_pagos_detalle vpd
            INNER JOIN ventas v ON vpd.venta_id = v.id
            WHERE DATE(v.fecha_hora) = ? 
            AND v.estado IN ('pagado', 'completado')
        ");
        $stmt->execute([$fecha]);
        $resultado = $stmt->fetch();
        
        return $resultado ?: [
            'efectivo_detalle' => 0,
            'credito_detalle' => 0,
            'debito_detalle' => 0,
            'qr_detalle' => 0
        ];
    } catch (PDOException $e) {
        throw new Exception("Error al obtener detalles de pagos: " . $e->getMessage());
    }
}
    
    public function calcularTotalesMovimientos($cajaId) {
        try {
            // Excluir movimientos automáticos de ventas y apertura
            $stmt = $this->db->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND concepto != 'Apertura de caja' AND venta_id IS NULL THEN monto ELSE 0 END), 0) as ingresos_extra,
                    COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos,
                    COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos_totales
                FROM movimientos_caja 
                WHERE caja_id = ?
            ");
            $stmt->execute([$cajaId]);
            
            $resultado = $stmt->fetch();
            
            return [
                'ingresos_extra' => $resultado['ingresos_extra'],
                'egresos' => $resultado['egresos'],
                'ingresos_totales' => $resultado['ingresos_totales']
            ];
        } catch (PDOException $e) {
            throw new Exception("Error al calcular movimientos: " . $e->getMessage());
        }
    }
    
    public function listarCajas($fechaDesde = null, $fechaHasta = null, $limit = 10) {
        try {
            $where = [];
            $params = [];
            
            if ($fechaDesde) {
                $where[] = "fecha >= ?";
                $params[] = $fechaDesde;
            }
            
            if ($fechaHasta) {
                $where[] = "fecha <= ?";
                $params[] = $fechaHasta;
            }
            
            $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
            
            $stmt = $this->db->prepare("
                SELECT * FROM cajas 
                $whereClause
                ORDER BY fecha DESC, hora_apertura DESC
                LIMIT ?
            ");
            $params[] = $limit;
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error al listar cajas: " . $e->getMessage());
        }
    }
    
    public function eliminarCaja($cajaId) {
        try {
            $this->db->beginTransaction();
            
            // Eliminar movimientos asociados
            $stmt = $this->db->prepare("DELETE FROM movimientos_caja WHERE caja_id = ?");
            $stmt->execute([$cajaId]);
            
            // Eliminar caja
            $stmt = $this->db->prepare("DELETE FROM cajas WHERE id = ?");
            $stmt->execute([$cajaId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Error al eliminar caja: " . $e->getMessage());
        }
    }
    
    public function registrarCompra($cajaId, $monto, $proveedor, $observaciones = null) {
    try {
        // Limpiar y verificar observaciones
        $observacionesLimpias = trim($observaciones ?? '');
        $medioPago = !empty($observacionesLimpias) ? $observacionesLimpias : 'efectivo';
        
        // DEBUG
        error_log("Compra - Observaciones: '$observaciones', Medio pago final: '$medioPago'");
        
        return $this->registrarMovimiento(
            $cajaId, 
            'egreso', 
            'Compra - ' . $proveedor,
            $monto, 
            $medioPago,
            null,
            $observacionesLimpias
        );
    } catch (Exception $e) {
        throw new Exception("Error al registrar compra: " . $e->getMessage());
    }
}
    
   public function registrarPago($cajaId, $monto, $concepto, $observaciones = null) {
    try {
        // Limpiar y verificar observaciones
        $observacionesLimpias = trim($observaciones ?? '');
        $medioPago = !empty($observacionesLimpias) ? $observacionesLimpias : 'efectivo';
        
        // DEBUG
        error_log("Pago - Observaciones: '$observaciones', Medio pago final: '$medioPago'");
        
        return $this->registrarMovimiento(
            $cajaId, 
            'egreso', 
            'Pago - ' . $concepto,
            $monto, 
            $medioPago,
            null,
            $observacionesLimpias
        );
    } catch (Exception $e) {
        throw new Exception("Error al registrar pago: " . $e->getMessage());
    }
}
    
    // Método para registrar venta en caja (debe ser llamado desde el sistema de ventas)
    public function registrarVenta($ventaId, $monto, $tipoPago, $observaciones = null) {
    try {
        $cajaActiva = $this->obtenerCajaActiva();
        if (!$cajaActiva) {
            throw new Exception("No hay caja abierta para registrar la venta");
        }
        
        // Usar observaciones como medio de pago si está disponible, sino usar tipoPago
        $medioPago = !empty(trim($observaciones)) ? trim($observaciones) : $tipoPago;
        
        // Solo registrar movimiento si es efectivo (los otros no afectan el efectivo en caja)
        if ($tipoPago === 'efectivo' || strpos($tipoPago, 'mixto') !== false) {
            return $this->registrarMovimiento(
                $cajaActiva['id'],
                'ingreso',
                'Venta #' . $ventaId,
                $monto,
                $medioPago,
                $ventaId,
                $observaciones ?: 'Venta registrada automáticamente'
            );
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception("Error al registrar venta en caja: " . $e->getMessage());
    }
}

public function registrarMovimientoGenerico($cajaId, $tipo, $concepto, $monto, $observaciones = null, $ventaId = null) {
    try {
        // Usar observaciones como medio de pago, o 'efectivo' por defecto
        $medioPago = !empty(trim($observaciones)) ? trim($observaciones) : 'efectivo';
        
        return $this->registrarMovimiento(
            $cajaId, 
            $tipo, 
            $concepto, 
            $monto, 
            $medioPago,
            $ventaId,
            $observaciones
        );
    } catch (Exception $e) {
        throw new Exception("Error al registrar movimiento: " . $e->getMessage());
    }
}
}

// Inicializar la clase
$caja = new Caja();
$mensaje = '';
$tipo_mensaje = '';

// Procesar formularios
try {
    if (isset($_POST['abrir_caja'])) {
        $montoInicial = (float)$_POST['monto_apertura'];
        $observaciones = trim($_POST['observaciones']) ?: null;
        
        $cajaId = $caja->abrirCaja($montoInicial, $observaciones);
        header('Location: caja.php?apertura_success=1');
        exit;
    }
    
    if (isset($_POST['cerrar_caja']) && isset($_POST['caja_id'])) {
        $cajaId = (int)$_POST['caja_id'];
        $observaciones = trim($_POST['observaciones_cierre']) ?: null;
        
        $resultado = $caja->cerrarCaja($cajaId, $observaciones);
        header('Location: caja.php?cierre_success=1');
        exit;
    }
    
    if (isset($_POST['registrar_compra']) && isset($_POST['caja_id'])) {
        $cajaId = (int)$_POST['caja_id'];
        $monto = (float)$_POST['monto_compra'];
        $proveedor = trim($_POST['proveedor']);
        $observaciones = trim($_POST['observaciones_compra']) ?: null;
        
        $caja->registrarCompra($cajaId, $monto, $proveedor, $observaciones);
        $mensaje = 'Compra registrada exitosamente';
        $tipo_mensaje = 'success';
        header('Location: caja.php?compra_success=1');
        exit;
    }
    
    if (isset($_POST['registrar_pago']) && isset($_POST['caja_id'])) {
        $cajaId = (int)$_POST['caja_id'];
        $monto = (float)$_POST['monto_pago'];
        $concepto = trim($_POST['concepto_pago']);
        $observaciones = trim($_POST['observaciones_pago']) ?: null;
        
        $caja->registrarPago($cajaId, $monto, $concepto, $observaciones);
        $mensaje = 'Pago registrado exitosamente';
        $tipo_mensaje = 'success';
        header('Location: caja.php?compra_success=1');
        exit;
    }

    if (isset($_POST['eliminar_gasto'])) {
        $gastoId = (int)$_POST['id_gasto'];
        $caja->eliminarGasto($gastoId);
        header('Location: caja.php?gasto_eliminado=1');
        exit;
    }
    
    if (isset($_POST['eliminar_caja'])) {
        $cajaId = (int)$_POST['id_caja'];
        $caja->eliminarCaja($cajaId);
        $mensaje = 'Caja eliminada exitosamente';
        $tipo_mensaje = 'success';
    }
    
} catch (Exception $e) {
    $mensaje = $e->getMessage();
    $tipo_mensaje = 'error';
}

// Obtener datos para mostrar
$caja_abierta = $caja->obtenerCajaActiva();
$historial_cajas = $caja->listarCajas(null, null, 10);

// Calcular totales si hay caja abierta
$total_efectivo = 0;
$total_tarjeta_credito = 0;  
$total_tarjeta_debito = 0; 
$total_qr = 0;
$total_ingresos_extra = 0;
$total_gastos = 0;
$saldo_actual = 0;

if ($caja_abierta) {
    $totalesVentas = $caja->calcularTotalesVentas($caja_abierta['fecha']);
    $totalesMovimientos = $caja->calcularTotalesMovimientos($caja_abierta['id']);
    
    $total_efectivo = $totalesVentas['efectivo'];
    $total_tarjeta_credito = $totalesVentas['tarjeta-credito'] ?? 0;
    $total_tarjeta_debito = $totalesVentas['tarjeta-debito'] ?? 0;
    $total_qr = $totalesVentas['qr'];
    $total_ingresos_extra = $totalesMovimientos['ingresos_extra'];
    $total_gastos = $totalesMovimientos['egresos'];
    $saldo_actual = $caja_abierta['monto_inicial'] + $total_efectivo + $total_ingresos_extra - $total_gastos;
}

// Procesar reportes
$reporte_cajas = [];
if (isset($_POST['generar_reporte'])) {
    $fechaInicio = $_POST['fecha_inicio'];
    $fechaFin = $_POST['fecha_fin'];
    $reporte_cajas = $caja->listarCajas($fechaInicio, $fechaFin, 100);
}

$reporte_gastos = [];
if (isset($_POST['generar_reporte_gastos'])) {
    $fechaInicio_gastos = $_POST['fecha_inicio_gastos'];
    $fechaFin_gastos = $_POST['fecha_fin_gastos'];
    $reporte_gastos = $caja->obtenerGastosPorFechas($fechaInicio_gastos, $fechaFin_gastos);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Caja</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon">
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
            padding: 10px 15px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        nav ul li a:hover {
            background-color: #495057;
        }

        .container {
            width: 95%;
            margin: 20px auto;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            flex: 1;
            min-width: 300px;
        }

        .card h2 {
            color: #343a40;
            margin-top: 0;
            border-bottom: 2px solid #f4f4f4;
            padding-bottom: 10px;
        }

        .caja-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .caja-info h3 {
            margin-top: 0;
            color: #007bff;
        }

        .caja-abierta {
            border-left: 5px solid #28a745;
        }

        .caja-cerrada {
            border-left: 5px solid #dc3545;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            color: white;
            transition: background-color 0.3s ease;
            margin: 5px;
        }

        .btn-primary { background-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-warning { background-color: #ffc107; color: #212529; }
        .btn-warning:hover { background-color: #e0a800; }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: white;
            flex: 0 0 100%;
        }

        .alert-success { background-color: #28a745; }
        .alert-danger { background-color: #dc3545; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background-color: #343a40;
            color: white;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #f0f0f0;
        }

        .totales-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .total-box {
            flex: 1;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            min-width: 150px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .total-box h4 {
            margin: 0 0 10px 0;
            color: #343a40;
        }

        .total-box p {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }

        .total-box.efectivo { background-color: #d4edda; color: #155724; }
        .total-box.tarjeta { background-color: #cce5ff; color: #004085; }
        .total-box.qr { background-color: #fff3cd; color: #856404; }
        .total-box.total { background-color: #343a40; color: white; }
        .total-box.gastos { background-color: #f8d7da; color: #721c24; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            max-width: 600px;
            border-radius: 8px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .operaciones-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        @media print {
            header, nav, .no-print, .btn, .modal {
                display: none !important;
            }
            .container { width: 100%; }
            .card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
        }

        @media (max-width: 768px) {
            .container { width: 98%; }
            .modal-content { width: 95%; margin: 10% auto; }
            .operaciones-buttons { flex-direction: column; }
            .totales-container { flex-direction: column; }
        }
    </style>
</head>
<body>
<header>
    <h1>Gestión de Caja</h1>
    <img src="./imagenes/tu-web-mensajes.jpg" alt="Logo">
    <nav>
        <ul>
            <li><a href="index.php">Inicio</a></li>
            <li><a href="Nuevaventa.php">Ventas</a></li>
            <li><a href="info_ventas.php">Info Ventas</a></li>
            <li><a href="Reporte.php">Reportes</a></li>
            
        </ul>
    </nav>
</header>

<div class="container">
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?= $tipo_mensaje === 'error' ? 'danger' : 'success' ?>">
            <i class="fas fa-<?= $tipo_mensaje === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i> 
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['apertura_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Caja abierta exitosamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cierre_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Caja cerrada exitosamente.
        </div>
    <?php endif; ?>

    <!-- Estado de la caja actual -->
    <div class="card" style="flex: 0 0 100%;">
        <h2><i class="fas fa-cash-register"></i> Estado de Caja</h2>
        
        <?php if ($caja_abierta): ?>
            <div class="caja-info caja-abierta">
                <h3>Caja Abierta #<?= $caja_abierta['id'] ?></h3>
                <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($caja_abierta['fecha'])) ?></p>
                <p><strong>Hora de Apertura:</strong> <?= date('H:i', strtotime($caja_abierta['hora_apertura'])) ?></p>
                <p><strong>Monto Inicial:</strong> $<?= number_format($caja_abierta['monto_inicial'], 2) ?></p>
                <?php if (!empty($caja_abierta['observaciones'])): ?>
                    <p><strong>Observaciones:</strong> <?= htmlspecialchars($caja_abierta['observaciones']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="totales-container">
                <div class="total-box efectivo">
                    <h4><i class="fas fa-money-bill-wave"></i> Efectivo</h4>
                    <p>$<?= number_format($total_efectivo, 2) ?></p>
                </div>
                <div class="total-box tarjeta">
                    <h4><i class="fas fa-credit-card"></i> Tarjeta Credito</h4>
                    <p>$<?= number_format($total_tarjeta_credito, 2) ?></p>
                </div>
                <div class="total-box tarjeta">
                    <h4><i class="fas fa-credit-card"></i> Tarjeta Debito</h4>
                    <p>$<?= number_format($total_tarjeta_debito, 2) ?></p>
                </div>
                <div class="total-box qr">
                    <h4><i class="fas fa-qrcode"></i> QR/Transferencia</h4>
                    <p>$<?= number_format($total_qr, 2) ?></p>
                </div>
                <div class="total-box gastos">
                    <h4><i class="fas fa-minus-circle"></i> Gastos</h4>
                    <p>$<?= number_format($total_gastos, 2) ?></p>
                </div>
                <div class="total-box">
                    <h4><i class="fas fa-calculator"></i> Saldo Actual</h4>
                    <p>$<?= number_format($saldo_actual, 2) ?></p>
                </div>
            </div>
            
            <div class="operaciones-buttons">
                <button type="button" class="btn btn-warning" onclick="openModal('compraModal')">
                    <i class="fas fa-shopping-cart"></i> Registrar Compra
                </button>
                <button type="button" class="btn btn-warning" onclick="openModal('pagoModal')">
                    <i class="fas fa-hand-holding-usd"></i> Registrar Pago
                </button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir Reporte
                </button>
                <button type="button" class="btn btn-danger" onclick="openModal('cerrarCajaModal')">
                    <i class="fas fa-door-closed"></i> Cerrar Caja
                </button>
            </div>
        <?php else: ?>
            <div class="caja-info caja-cerrada">
                <h3>No hay caja abierta</h3>
                <p>Para comenzar a registrar operaciones, debe abrir una caja.</p>
            </div>
            
            <button type="button" class="btn btn-success" onclick="openModal('abrirCajaModal')">
                <i class="fas fa-door-open"></i> Abrir Caja
            </button>
        <?php endif; ?>
    </div>

<div class="card" style="flex: 0 0 100%;">
    <h2><i class="fas fa-history"></i> Historial de Cajas</h2>
    
    <?php if (empty($historial_cajas)): ?>
        <p>No hay registros de cajas anteriores.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th>Monto Inicial</th>
                    <th>Monto Final</th>
                    <th>Total Ventas</th>
                    <th>Gastos</th>
                    <th>Estado</th>
                    <th>Opciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial_cajas as $cajaHist): ?>
                    <tr>
                        <td><?= $cajaHist['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($cajaHist['fecha'])) ?></td>
                        <td><?= date('H:i', strtotime($cajaHist['hora_apertura'])) ?></td>
                        <td><?= $cajaHist['hora_cierre'] ? date('H:i', strtotime($cajaHist['hora_cierre'])) : '-' ?></td>
                        <td>$<?= number_format($cajaHist['monto_inicial'], 2) ?></td>
                        <td>$<?= number_format($cajaHist['monto_final'] ?? 0, 2) ?></td>
                        <td>$<?= number_format($cajaHist['total_ventas'] ?? 0, 2) ?></td>
                        <td>$<?= number_format($cajaHist['gastos'] ?? 0, 2) ?></td>
                        <td>
                            <?php if ($cajaHist['estado'] == 'abierta'): ?>
                                <span style="color: #28a745; font-weight: bold;">Abierta</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: bold;">Cerrada</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cajaHist['estado'] == 'abierta'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar la caja ID: <?= $cajaHist['id'] ?>?')">
                                    <input type="hidden" name="id_caja" value="<?= $cajaHist['id'] ?>">
                                    <button type="submit" name="eliminar_caja" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Reportes de gastos -->
   <div class="card" style="flex: 0 0 100%;">
    <h2><i class="fas fa-chart-bar"></i> Reportes de Gastos</h2>
    <form method="POST" action="" class="no-print">
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="fecha_inicio">Fecha Inicio:</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio_gastos" class="form-control" required>
            </div>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="fecha_fin">Fecha Fin:</label>
                <input type="date" id="fecha_fin" name="fecha_fin_gastos" class="form-control" required>
            </div>
            <div class="form-group" style="align-self: end;">
                <button type="submit" name="generar_reporte_gastos" class="btn btn-primary">
                    <i class="fas fa-search"></i> Generar Reporte
                </button>
            </div>
        </div>
    </form>
             
    <?php if (!empty($reporte_gastos)): ?>
        <div class="reporte-resultado_gastos">
            <h3>Resultados del Reporte</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                        <th>Medio de pago</th>
                        <th>Opciones</th>
                    </tr>
                </thead>
                <tbody>
    <?php
    $total_gastos_reporte = 0;
    foreach ($reporte_gastos as $gasto):
        $total_gastos_reporte += $gasto['monto'];
        
        // DEBUG: Mostrar todos los datos del gasto
        echo "<!-- DEBUG: " . print_r($gasto, true) . " -->";
    ?>
        <tr>
            <td><?= htmlspecialchars($gasto['id']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($gasto['fecha'])) ?></td>
            <td><?= htmlspecialchars($gasto['concepto']) ?></td>
            <td>$<?= number_format($gasto['monto'], 2) ?></td>
            <td>
                <?php 
                // Mostrar medio_pago con debug
                $medio = $gasto['medio_pago'] ?? 'No definido';
                echo htmlspecialchars($medio);
                echo "<!-- Debug medio: '$medio' -->";
                ?>
            </td>
            <td>
            <?php if (isset($_POST['generar_reporte_gastos'])): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar el gasto: <?= $gasto['concepto'] ?>?')">
                    <input type="hidden" name="id_gasto" value="<?= $gasto['id'] ?>">
                    <button type="submit" name="eliminar_gasto" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            <?php else: ?>
                <span style="color: #ccc; font-style: italic;">-</span>
            <?php endif; ?>
        </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                <tfoot>
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td colspan="4"><strong>TOTAL GASTOS:</strong></td>
                        <td><strong>$<?= number_format($total_gastos_reporte, 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" class="btn btn-primary no-print" onclick="window.print()" style="margin-top: 15px;">
                <i class="fas fa-print"></i> Imprimir Reporte
            </button>
        </div>
    <?php elseif (isset($_POST['generar_reporte_gastos'])): ?>
        <p>No se encontraron gastos para el período seleccionado.</p>
    <?php endif; ?>
</div>

    <!-- Reportes de Caja -->
    <div class="card" style="flex: 0 0 100%;">
        <h2><i class="fas fa-chart-bar"></i> Reportes de Caja</h2>
        <form method="POST" action="" class="no-print">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required>
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label for="fecha_fin">Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required>
                </div>
                <div class="form-group" style="align-self: end;">
                    <button type="submit" name="generar_reporte" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generar Reporte
                    </button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($reporte_cajas)): ?>
            <div class="reporte-resultado">
                <h3>Resultados del Reporte</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th>Monto Inicial</th>
                            <th>Monto Final</th>
                            <th>Total Ventas</th>
                            <th>Gastos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_inicial_reporte = 0;
                        $total_final_reporte = 0;
                        $total_ventas_reporte = 0;
                        $total_gastos_reporte = 0;
                        
                        foreach ($reporte_cajas as $cajaRep): 
                            $total_inicial_reporte += $cajaRep['monto_inicial'];
                            $total_final_reporte += $cajaRep['monto_final'] ?? 0;
                            $total_ventas_reporte += $cajaRep['total_ventas'] ?? 0;
                            $total_gastos_reporte += $cajaRep['gastos'] ?? 0;
                        ?>
                            <tr>
                                <td><?= $cajaRep['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($cajaRep['fecha'])) ?></td>
                                <td><?= date('H:i', strtotime($cajaRep['hora_apertura'])) ?></td>
                                <td><?= $cajaRep['hora_cierre'] ? date('H:i', strtotime($cajaRep['hora_cierre'])) : '-' ?></td>
                                <td>$<?= number_format($cajaRep['monto_inicial'], 2) ?></td>
                                <td>$<?= number_format($cajaRep['monto_final'] ?? 0, 2) ?></td>
                                <td>$<?= number_format($cajaRep['total_ventas'] ?? 0, 2) ?></td>
                                <td>$<?= number_format($cajaRep['gastos'] ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="4"><strong>TOTALES:</strong></td>
                            <td><strong>$<?= number_format($total_inicial_reporte, 2) ?></strong></td>
                            <td><strong>$<?= number_format($total_final_reporte, 2) ?></strong></td>
                            <td><strong>$<?= number_format($total_ventas_reporte, 2) ?></strong></td>
                            <td><strong>$<?= number_format($total_gastos_reporte, 2) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
                <button type="button" class="btn btn-primary no-print" onclick="window.print()" style="margin-top: 15px;">
                    <i class="fas fa-print"></i> Imprimir Reporte
                </button>
            </div>
        <?php elseif (isset($_POST['generar_reporte'])): ?>
            <p>No se encontraron registros para el período seleccionado.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Abrir Caja -->
<div id="abrirCajaModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('abrirCajaModal')">&times;</span>
        <h2><i class="fas fa-door-open"></i> Apertura de Caja</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="monto_apertura">Monto Inicial (Efectivo):</label>
                <input type="number" id="monto_apertura" name="monto_apertura" step="0.01" min="0" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="observaciones">Observaciones:</label>
                <textarea id="observaciones" name="observaciones" class="form-control" rows="3" placeholder="Observaciones de apertura (opcional)"></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="closeModal('abrirCajaModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="abrir_caja" class="btn btn-success">
                    <i class="fas fa-check"></i> Abrir Caja
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Cerrar Caja -->
<div id="cerrarCajaModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('cerrarCajaModal')">&times;</span>
        <h2><i class="fas fa-door-closed"></i> Cierre de Caja</h2>
        
        <?php if ($caja_abierta): ?>
            <div class="caja-info">
                <h4>Resumen del Día</h4>
                <p><strong>Monto Inicial:</strong> $<?= number_format($caja_abierta['monto_inicial'], 2) ?></p>
                <p><strong>Total Ventas Efectivo:</strong> $<?= number_format($total_efectivo, 2) ?></p>
                <p><strong>Total Ventas Tarjeta Crédito:</strong> $<?= number_format($total_tarjeta_credito, 2) ?></p>
                <p><strong>Total Ventas Tarjeta Débito:</strong> $<?= number_format($total_tarjeta_debito, 2) ?></p>
                <p><strong>Total Ventas QR:</strong> $<?= number_format($total_qr, 2) ?></p>
                <p><strong>Total Gastos:</strong> $<?= number_format($total_gastos, 2) ?></p>
                <p><strong>Saldo Final Esperado:</strong> $<?= number_format($saldo_actual, 2) ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="caja_id" value="<?= $caja_abierta['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="observaciones_cierre">Observaciones de Cierre:</label>
                <textarea id="observaciones_cierre" name="observaciones_cierre" class="form-control" rows="3" placeholder="Observaciones del cierre (opcional)"></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="closeModal('cerrarCajaModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="cerrar_caja" class="btn btn-success">
                    <i class="fas fa-check"></i> Cerrar Caja
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Registrar Compra -->
<div id="compraModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('compraModal')">&times;</span>
        <h2><i class="fas fa-shopping-cart"></i> Registrar Compra</h2>
        <form method="POST" action="">
            <input type="hidden" name="caja_id" value="<?= $caja_abierta['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="proveedor">Proveedor:</label>
                <input type="text" id="proveedor" name="proveedor" class="form-control" required placeholder="Nombre del proveedor">
            </div>
            
            <div class="form-group">
                <label for="monto_compra">Monto de la Compra:</label>
                <input type="number" id="monto_compra" name="monto_compra" step="0.01" min="0.01" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="observaciones_compra">Observaciones/ Tipo de pago:</label>
                <textarea id="observaciones_compra" name="observaciones_compra" class="form-control" rows="3" placeholder="Ej: transferencia, cheque, tarjeta, etc."></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="closeModal('compraModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="registrar_compra" class="btn btn-success">
                    <i class="fas fa-check"></i> Registrar Compra
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Registrar Pago -->
<div id="pagoModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('pagoModal')">&times;</span>
        <h2><i class="fas fa-hand-holding-usd"></i> Registrar Pago</h2>
        <form method="POST" action="">
            <input type="hidden" name="caja_id" value="<?= $caja_abierta['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="concepto_pago">Concepto del Pago:</label>
                <input type="text" id="concepto_pago" name="concepto_pago" class="form-control" required placeholder="Ej: Servicios, Alquiler, Sueldos, etc.">
            </div>
            
            <div class="form-group">
                <label for="monto_pago">Monto del Pago:</label>
                <input type="number" id="monto_pago" name="monto_pago" step="0.01" min="0.01" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="observaciones_pago">Observaciones/ Tipo de pago:</label>
                <textarea id="observaciones_pago" name="observaciones_pago" class="form-control" rows="3" placeholder="Ej: transferencia, cheque, tarjeta, etc."></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="closeModal('pagoModal')">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" name="registrar_pago" class="btn btn-success">
                    <i class="fas fa-check"></i> Registrar Pago
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        // Limpiar formularios
        const form = document.querySelector(`#${modalId} form`);
        if (form) {
            form.reset();
        }
    }

    // Cerrar modales con click fuera
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Cerrar modales con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            });
        }
    });

    // Configurar fecha actual para reportes
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const fechaInicio = document.getElementById('fecha_inicio');
        const fechaFin = document.getElementById('fecha_fin');
        
        if (fechaInicio && !fechaInicio.value) {
            // Primer día del mes actual
            const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            fechaInicio.value = firstDay.toISOString().split('T')[0];
        }
        
        if (fechaFin && !fechaFin.value) {
            fechaFin.value = today;
        }
    });

    // Validación de formularios
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const numberInputs = form.querySelectorAll('input[type="number"]');
            numberInputs.forEach(input => {
                if (input.value && parseFloat(input.value) < 0) {
                    e.preventDefault();
                    alert('Los montos no pueden ser negativos');
                    input.focus();
                    return;
                }
            });
        });
    });

    // Auto-actualización cada 30 segundos (solo si hay caja abierta)
    <?php if ($caja_abierta): ?>
    setInterval(function() {
        // Recargar la página silenciosamente para actualizar totales
        if (!document.querySelector('.modal[style*="block"]')) {
            location.reload();
        }
    }, 30000);
    <?php endif; ?>
</script>
</body>
</html>