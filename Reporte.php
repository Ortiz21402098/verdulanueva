<?php
require_once 'config.php';
require_once 'reportes.php';

$reportes = new Reportes();

// Obtener datos para el resumen rápido
$hoy = date('Y-m-d');
$reporteHoy = $reportes->reporteCajaDiario($hoy);
$resumenSemanal = $reportes->resumenSemanalSimple(date('Y-m-d', strtotime('-7 days')), $hoy);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Mini Supermercado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
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
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin: 15px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #4facfe;
            margin-bottom: 10px;
        }
        
        .stats-label {
            font-size: 1.1em;
            color: #666;
            font-weight: 600;
        }
        
        .report-section {
            padding: 30px;
        }
        
        .btn-report {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            margin: 10px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            min-width: 200px;
        }
        
        .btn-report:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .btn-view {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .alert-custom {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .departamento-card {
            border-left: 5px solid;
            margin: 10px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
        }
        
        .departamento-verduleria { border-left-color: #28a745; }
        .departamento-despensa { border-left-color: #17a2b8; }
        .departamento-polleria { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <header>
        <h1>📊 Reportes</h1>
        <img src="./imagenes/tu-web-mensajes.jpg" alt="Logo">
        <nav>
            <ul>
                <li><a href="index.php">🏠 Inicio</a></li>
                <li><a href="Nuevaventa.php">🛒 Nueva Venta</a></li>
                <li><a href="caja.php">💰 Caja</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <div class="header">
            <h1>📈 Centro de Reportes</h1>
            <p>Mini Supermercado La Nueva</p>
        </div>
        
        <div class="report-section">
            <!-- Resumen Rápido de Hoy -->
                    <div class="row mb-4">
            <div class="col-md-12">
                <h3>📅 Resumen del Día - <?= date('d/m/Y') ?></h3>
            </div>
            <?php if ($reporteHoy['ventas'] && $reporteHoy['ventas']['total_transacciones'] > 0): ?>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= number_format($reporteHoy['ventas']['total_transacciones']) ?></div>
                    <div class="stats-label">Transacciones Hoy</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?= number_format($reporteHoy['ventas']['total_ventas'], 0) ?></div>
                    <div class="stats-label">Total Vendido</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?= number_format($reporteHoy['ventas']['efectivo'], 0) ?></div>
                    <div class="stats-label">Efectivo (Total)</div>
                    <small style="font-size: 0.8em; color: #666;">Incluye mixtos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?= number_format($reporteHoy['ventas']['tarjeta-credito'], 0) ?></div>
                    <div class="stats-label">Tarjeta Crédito</div>
                </div>
            </div>
            
            <!-- Segunda fila -->
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?= number_format($reporteHoy['ventas']['tarjeta-debito'], 0) ?></div>
                    <div class="stats-label">Tarjeta Débito</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?= number_format($reporteHoy['ventas']['qr'], 0) ?></div>
                    <div class="stats-label">QR / Transferencia</div>
                    <small style="font-size: 0.8em; color: #666;">Incluye mixtos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">$<?= number_format($reporteHoy['ventas']['tarjeta'], 0) ?></div>
                    <div class="stats-label">Total Tarjetas</div>
                </div>
            </div>
                        
            <?php else: ?>
            <div class="col-md-12">
                <div class="alert alert-info">
                    📋 No hay ventas registradas para el día de hoy.
                </div>
            </div>
            <?php endif; ?>
        </div>


            <!-- Resumen Semanal -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h3>📊 Resumen de la Semana</h3>
                </div>
                <?php if (!empty($resumenSemanal['departamentos'])): ?>
                    <?php foreach($resumenSemanal['departamentos'] as $dept): ?>
                    <div class="col-md-4">
                        <div class="departamento-card departamento-<?= strtolower(str_replace(' ', '-', $dept['departamento'])) ?>">
                            <h5><?= $dept['departamento'] ?></h5>
                            <p><strong>Ventas:</strong> <?= number_format($dept['cantidad_ventas']) ?></p>
                            <p><strong>Total:</strong> $<?= number_format($dept['total_ventas'], 2) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Opciones de Reportes -->
            <div class="row">
                <div class="col-md-12">
                    <h3>📋 Opciones de Reportes</h3>
                    <div class="alert-custom">
                        <h5>🔍 Ver Reportes Detallados</h5>
                        <p>Accede a reportes completos con gráficos y análisis detallado de ventas.</p>
                        <a href="reportes_detallados.php" class="btn-report btn-view">
                            👁️ Ver Reportes Detallados
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Estado de Caja -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <h3>💰 Estado de Caja</h3>
                    <?php if ($reporteHoy['caja']): ?>
                    <div class="stats-card">
                        <h5>Caja del día <?= date('d/m/Y', strtotime($reporteHoy['caja']['fecha'])) ?></h5>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Monto Inicial:</strong><br>
                                $<?= number_format($reporteHoy['caja']['monto_inicial'], 2) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Total Ventas:</strong><br>
                                $<?= number_format($reporteHoy['caja']['total_ventas'], 2) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Monto Final:</strong><br>
                                $<?= number_format($reporteHoy['caja']['monto_final'], 2) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Estado:</strong><br>
                                <span class="badge <?= $reporteHoy['caja']['estado'] == 'abierta' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ucfirst($reporteHoy['caja']['estado']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        ⚠️ No hay caja abierta para el día de hoy. 
                        <a href="caja.php" class="btn btn-warning btn-sm">Abrir Caja</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>