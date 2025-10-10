<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Caja - Mini Supermercado</title>
    <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="shortcut icon" href="./imagenes/tu-web-mensajes.jpg" type="image/x-icon"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
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
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }
        
        .scanner-section, .payment-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #4facfe;
            padding-bottom: 10px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .input-group input, .input-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus, .input-group select:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .items-list {
            background: white;
            border-radius: 8px;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e0e6ed;
        }
        
        .item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .item:hover {
            background: #f8f9fa;
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-price {
            font-weight: bold;
            color: #28a745;
            font-size: 1.2em;
        }
        
        .total-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .total-amount {
            font-size: 2.5em;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .payment-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 600;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Estilos para departamentos */
        .departamento-verduleria {
            border-left: 4px solid #28a745;
            background: #f8fff9;
        }
        
        .departamento-despensa {
            border-left: 4px solid #17a2b8;
            background: #f0fdff;
        }
        
        .departamento-polleria {
            border-left: 4px solid #ffc107;
            background: #fffef0;
        }

        .departamento-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 8px;
        }

        .tag-verduleria {
            background: #28a745;
            color: white;
        }

        .tag-despensa {
            background: #17a2b8;
            color: white;
        }

        .tag-polleria-trozado {
            background: #ffc107;
            color: #333;
        }

        .tag-polleria-procesado {
            background: #e73a06ff;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .payment-buttons {
                grid-template-columns: 1fr;
            }
        }
        /* Agregar estos estilos */
#alertContainer {
    position: fixed !important;
    top: 80px !important;
    right: 20px !important;
    z-index: 9999 !important;
    max-width: 350px !important;
    pointer-events: none;
}

#alertContainer .alert {
    pointer-events: auto;
    margin-bottom: 10px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    border-radius: 8px !important;
    animation: slideIn 0.3s ease-out !important;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Para móviles */
@media (max-width: 768px) {
    #alertContainer {
        top: 60px !important;
        right: 10px !important;
        left: 10px !important;
        max-width: none !important;
    }
}
    </style>
</head>
<body>
    <header>
        <h1>Nueva venta</h1>
        <img src="./imagenes/tu-web-mensajes.jpg" alt="Logo">
        <nav>
            <ul>
                <li><a href="index.php">Inicio</a></li>
                <li><a href="info_ventas.php">Pedidos</a></li>
                <li><a href="Reporte.php">Reportes</a></li>
                <li><a href="caja.php">Cierre de caja</a></li>
            </ul>
        </nav>
    </header>
    <br><br>
    
    <!-- Contenedor para alertas -->
    <div id="alertContainer" style="position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 400px;"></div>
    
    <div class="container">
        <div class="header">
            <h1>🛒 Sistema de Caja</h1>
            <p>Mini Supermercado La Nueva</p>
        </div>
        
        <div class="main-content">
            <!-- Sección de escaneo -->
            <div class="scanner-section">
                <h2 class="section-title">📱 Escanear Tickets</h2>
                
                <div class="input-group">
                    <label for="codigoBarras">Código de Barras:</label>
                    <input type="text" id="codigoBarras" placeholder="Escanee o ingrese el código (ej: 1201000002004)">
                </div>
                
                <button class="btn btn-primary" onclick="agregarItem()">➕ Agregar Item</button>
                
                <div class="items-list" id="itemsList">
                    <div style="padding: 20px; text-align: center; color: #666;">
                        No hay items escaneados
                    </div>
                </div>
                
                <div class="total-display">
                    <div>Total a Pagar:</div>
                    <div class="total-amount" id="totalAmount">$0.00</div>
                </div>
            </div>
            
            <!-- Sección de pago -->
            <div class="payment-section">
                <h2 class="section-title">💳 Procesamiento de Pago</h2>
                
                <div class="input-group" id="montoRecibidoGroup" style="display: none;">
                    <label for="montoRecibido">Monto Recibido (Efectivo):</label>
                    <input type="number" id="montoRecibido" step="0.01" placeholder="0.00">
                </div>

                <div class="input-group" id="montoRecibidoAmbosGroup" style="display: none;">
                    <label for="montoEfectivo">Monto Recibido (Efectivo):</label>
                    <input type="number" id="montoEfectivo" step="0.01" placeholder="0.00">
                    <label for="montoTransferencia">Transferencia o QR:</label>
                    <input type="number" id="montoTransferencia" step="0.01" placeholder="0.00">
                </div>
                
                <div class="payment-buttons">
                    <button class="btn btn-success" onclick="procesarPago('efectivo')">💵 Efectivo</button>
                    <button class="btn btn-primary" onclick="procesarPago('tarjeta-credito')">💳 Tarjeta credito</button>
                    <button class="btn btn-primary" onclick="procesarPago('tarjeta-debito')">💳 Tarjeta debito</button>
                    <button class="btn btn-primary" onclick="procesarPago('qr')">📱 QR</button>
                    <button class="btn btn-primary" onclick="procesarPago('ambos')">💵📱 Ambos</button>
                </div>

                <div style="margin-top: 20px;">

                    <button class="btn btn-danger" onclick="cancelarVenta()">❌ Cancelar Venta</button>
                    <button class="btn btn-primary" onclick="nuevaVenta()" style="margin-left: 10px;">🆕 Nueva Venta</button>
                </div>
                
                <!-- Resultado del pago -->
                <div id="resultadoPago"></div>
            </div>
        </div>
    </div>

    <script>
        let ventaActual = 0;
        let totalVenta = 0;
        let itemsVenta = [];

        // Inicializar nueva venta al cargar la página
       window.onload = function() {
    nuevaVenta();
    
    // NO hacer focus automáticamente
    const codigoBarrasInput = document.getElementById('codigoBarras');
    if (codigoBarrasInput) {
        // Solo agregar el event listener para Enter
        codigoBarrasInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                agregarItem();
            }
        });
    }
};

        async function nuevaVenta() {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'nueva_venta'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            ventaActual = data.venta_id;
            console.log('Nueva venta creada con ID:', ventaActual);
            totalVenta = 0;
            itemsVenta = [];
            
            // Limpiar campos del formulario
            limpiarCamposEfectivo();
            
            actualizarInterfaz();
            mostrarAlerta('Nueva venta iniciada', 'success');
            
            
            const codigoBarrasInput = document.getElementById('codigoBarras');
            if (codigoBarrasInput) {
                 codigoBarrasInput.focus();
             }
        } else {
            mostrarAlerta('Error al crear nueva venta: ' + data.message, 'error');
        }
    } catch (error) {
        mostrarAlerta('Error de conexión: ' + error.message, 'error');
        console.error('Error completo:', error);
    }
}

        function limpiarCamposEfectivo() {
            // Limpiar el campo de monto recibido
            const montoRecibido = document.getElementById('montoRecibido');
            const montoEfectivo = document.getElementById('montoEfectivo');
            const montoTransferencia = document.getElementById('montoTransferencia');
            
            if (montoRecibido) montoRecibido.value = '';
            if (montoEfectivo) montoEfectivo.value = '';
            if (montoTransferencia) montoTransferencia.value = '';
            
            // Ocultar los grupos de monto recibido
            const montoRecibidoGroup = document.getElementById('montoRecibidoGroup');
            const montoRecibidoAmbosGroup = document.getElementById('montoRecibidoAmbosGroup');
            
            if (montoRecibidoGroup) montoRecibidoGroup.style.display = 'none';
            if (montoRecibidoAmbosGroup) montoRecibidoAmbosGroup.style.display = 'none';
            
            // Limpiar el resultado del pago
            const resultadoPago = document.getElementById('resultadoPago');
            if (resultadoPago) resultadoPago.innerHTML = '';
        }

        async function agregarItem() {
    const codigoBarrasInput = document.getElementById('codigoBarras');
    if (!codigoBarrasInput) {
        mostrarAlerta('Error: Campo código de barras no encontrado', 'error');
        return;
    }
    
    const codigoBarras = codigoBarrasInput.value.trim();

    console.log('Agregando item - Venta ID:', ventaActual);
    console.log('Código:', codigoBarras);
    
    if (!codigoBarras) {
        mostrarAlerta('Por favor ingrese un código de barras', 'error');
        return;
    }
    
    if (!ventaActual) {
        mostrarAlerta('No hay una venta activa', 'error');
        return;
    }
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'agregar_item',
                venta_id: ventaActual,
                codigo_barras: codigoBarras
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            itemsVenta.push({
                codigo: codigoBarras,
                precio: data.precio,
                departamento: data.departamento,
                departamento_id: data.departamento_id
            });
            
            totalVenta += parseFloat(data.precio);
            actualizarInterfaz();
            codigoBarrasInput.value = '';
            
            // ELIMINADO: NO HACER FOCUS AUTOMÁTICAMENTE
            // codigoBarrasInput.focus();
            
            mostrarAlerta(`✅ ${data.departamento}: ${formatearPrecio(data.precio)}`, 'success');
        } else {
            mostrarAlerta('❌ Error: ' + data.message, 'error');
        }
    } catch (error) {
        mostrarAlerta('Error de conexión: ' + error.message, 'error');
        console.error('Error de conexión completo:', error);
    }
}

    async function procesarPago(tipoPago) {
    console.log('=== INICIANDO PROCESO DE PAGO ===');
    console.log('Tipo de pago:', tipoPago);
    console.log('Venta actual:', ventaActual);
    console.log('Total venta:', totalVenta);
    
    if (!ventaActual || totalVenta <= 0) {
        mostrarAlerta('No hay items para procesar el pago', 'error');
        return;
    }
    
    let montoRecibido = null;
    let montoEfectivo = null;
    let montoTransferencia = null;
    
    if (tipoPago === 'tarjeta-credito') {
        const recargo = totalVenta * 0.10;
        const totalConRecargo = totalVenta + recargo;
        
        if (!confirm(`RECARGO POR TARJETA:\n\nTotal original: ${formatearPrecio(totalVenta)}\nRecargo (10%): ${formatearPrecio(recargo)}\nTotal final: ${formatearPrecio(totalConRecargo)}\n\n¿Confirma el pago con tarjeta?`)) {
            return;
        }
    }
    
    if (tipoPago === 'efectivo') {
        const montoInput = document.getElementById('montoRecibido');
        if (!montoInput || !montoInput.value || parseFloat(montoInput.value) < totalVenta) {
            mostrarAlerta('Ingrese un monto válido mayor o igual al total', 'error');
            mostrarCampoEfectivo();
            return;
        }
        montoRecibido = parseFloat(montoInput.value);
    } else if (tipoPago === 'ambos') {
        const montoEfectivoInput = document.getElementById('montoEfectivo');
        const montoTransferenciaInput = document.getElementById('montoTransferencia');
        
        if (!montoEfectivoInput || !montoTransferenciaInput) {
            mostrarAlerta('Error en los campos de pago combinado', 'error');
            mostrarCampoAmbos();
            return;
        }
        
        montoEfectivo = parseFloat(montoEfectivoInput.value) || 0;
        montoTransferencia = parseFloat(montoTransferenciaInput.value) || 0;
        
        if ((montoEfectivo + montoTransferencia) < totalVenta) {
            mostrarAlerta('La suma de ambos montos debe ser mayor o igual al total', 'error');
            mostrarCampoAmbos();
            return;
        }
    }
    
    try {
        console.log('Enviando request a API...');
        
        const requestBody = {
            action: 'procesar_pago',
            venta_id: ventaActual,
            tipo_pago: tipoPago,
            monto_recibido: montoRecibido,
            monto_efectivo: montoEfectivo,
            monto_transferencia: montoTransferencia
        };
        
        console.log('Request body:', requestBody);

        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestBody)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        console.log('=== RESPUESTA DEL SERVIDOR ===');
        console.log('Data completa:', JSON.stringify(data, null, 2));
        console.log('Success:', data.success);
        console.log('Ticket fiscal:', data.ticket_fiscal);
        console.log('Resultado impresión:', data.resultado_impresion);
        
        if (data.success) {
            // Mostrar estado de impresión ANTES de mostrar resultado
            if (data.resultado_impresion) {
                console.log('Estado impresión:', data.resultado_impresion.success ? 'EXITOSA' : 'FALLIDA');
                
                if (data.resultado_impresion.success) {
                    mostrarAlerta('Ticket impreso automáticamente', 'success');
                } else {
                    mostrarAlerta('Error impresión: ' + data.resultado_impresion.message, 'error');
                    console.error('Detalles error impresión:', data.resultado_impresion);
                }
            } else if (data.ticket_fiscal) {
                console.warn('Se requiere ticket fiscal pero no hay resultado_impresion');
                mostrarAlerta('ADVERTENCIA: Ticket fiscal requerido pero no se imprimió', 'error');
            }
            
            mostrarResultadoPago(data);
            ventaActual = null;
        } else {
            mostrarAlerta('Error al procesar pago: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('=== ERROR EN PROCESARPAGO ===');
        console.error('Error completo:', error);
        console.error('Stack:', error.stack);
        mostrarAlerta('Error de conexión: ' + error.message, 'error');
    }
}

       function mostrarCampoEfectivo() {
    const montoRecibidoGroup = document.getElementById('montoRecibidoGroup');
    const montoRecibidoAmbosGroup = document.getElementById('montoRecibidoAmbosGroup');
    
    if (montoRecibidoGroup) {
        montoRecibidoGroup.style.display = 'block';
    }
    if (montoRecibidoAmbosGroup) {
        montoRecibidoAmbosGroup.style.display = 'none';
    }
    
}

       function mostrarCampoAmbos() {
    const montoRecibidoGroup = document.getElementById('montoRecibidoGroup');
    const montoRecibidoAmbosGroup = document.getElementById('montoRecibidoAmbosGroup');
    
    if (montoRecibidoGroup) {
        montoRecibidoGroup.style.display = 'none';
    }
    if (montoRecibidoAmbosGroup) {
        montoRecibidoAmbosGroup.style.display = 'block';
    }
    
}

     function mostrarResultadoPago(data) {
    console.log('=== RESULTADO PAGO ===', data);
    
    const resultadoPago = document.getElementById('resultadoPago');
    if (!resultadoPago) {
        console.error('Elemento resultadoPago no encontrado');
        return;
    }
    
    let html = '<div class="alert alert-success">';
    html += '<h3>Pago Procesado Exitosamente</h3>';
    html += `<p><strong>Total:</strong> ${formatearPrecio(data.total)}</p>`;
    html += `<p><strong>Monto Recibido:</strong> ${formatearPrecio(data.monto_recibido)}</p>`;
    
    if (data.vuelto && data.vuelto > 0) {
        html += `<p><strong>Vuelto:</strong> <span style="font-size: 1.5em; color: #dc3545;">${formatearPrecio(data.vuelto)}</span></p>`;
    }

    // NUEVO: Verificar resultado de impresión del backend
    const requiereTicketFiscal = data.ticket_fiscal === true || 
                                 ['tarjeta-credito', 'tarjeta-debito', 'qr'].includes(data.tipo_pago);
    
    if (requiereTicketFiscal && data.venta_id) {
        console.log('Ticket fiscal requerido - Verificando impresión backend...');
        
        // Verificar si el backend reportó impresión exitosa
        let impresoEnBackend = false;
        if (data.resultado_impresion) {
            console.log('Estado impresión backend:', data.resultado_impresion);
            impresoEnBackend = data.resultado_impresion.success === true;
        }
        
        if (impresoEnBackend) {
            // Backend imprimió exitosamente
            html += `<div style="background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;">
                        <p style="margin: 0; color: #155724;">
                            <strong>✓ Ticket impreso automáticamente</strong><br>
                            <small>Impresión realizada por el servidor</small>
                        </p>
                    </div>`;
            
            mostrarAlerta('Ticket impreso automáticamente', 'success');
        } else {
            // Backend NO imprimió o falló - abrir ventana
            console.log('Backend no imprimió - Abriendo ventana...');
            
            if (data.resultado_impresion && !data.resultado_impresion.success) {
                console.warn('Error backend:', data.resultado_impresion.message);
                html += `<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px;">
                            <p style="margin: 0; color: #856404;">
                                <strong>⚠ Impresión backend falló</strong><br>
                                <small>${data.resultado_impresion.message}</small><br>
                                <small>Abriendo ticket manualmente...</small>
                            </p>
                        </div>`;
            }
            
            // Abrir ventana automáticamente
            setTimeout(() => {
                const url = `recibo.php?ventaID=${data.venta_id}`;
                const ventana = window.open(url, 'TicketImpresion', 'width=400,height=700,scrollbars=yes');
                
                if (ventana) {
                    ventana.addEventListener('load', function() {
                        setTimeout(() => {
                            ventana.print();
                        }, 1000);
                    });
                    
                    mostrarAlerta('Ticket abierto para impresión', 'info');
                } else {
                    alert('BLOQUEO DE VENTANAS EMERGENTES\n\nPermite ventanas emergentes para impresión automática.\n\nPuedes reimprimir con el botón de abajo.');
                }
            }, 500);
            
            html += `<div style="background: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 5px;">
                        <p style="margin: 0; color: #0c5460;">
                            <strong>Ventana de ticket abierta</strong><br>
                            <small>Si no apareció, habilita ventanas emergentes</small>
                        </p>
                    </div>`;
        }
    }

    // Botón manual siempre disponible
    html += `<div style="margin-top: 15px;">
                <button class="btn btn-primary" onclick="imprimirTicketManual(${data.venta_id})" style="padding: 10px 20px;">
                    🖨️ Imprimir Ticket
                </button>
            </div>`;
    
    html += '</div>';
    resultadoPago.innerHTML = html;
}

// Nueva funcion unificada de impresion
function imprimirTicketManual(ventaId) {
    if (!ventaId) {
        mostrarAlerta('Error: ID de venta no valido', 'error');
        return;
    }
    
    const id = parseInt(ventaId);
    if (isNaN(id) || id <= 0) {
        mostrarAlerta('Error: ID invalido', 'error');
        return;
    }
    
    const url = `recibo.php?ventaID=${id}`;
    const ventana = window.open(url, 'TicketImpresion', 'width=400,height=700,scrollbars=yes');
    
    if (ventana) {
        ventana.focus();
        mostrarAlerta('Ticket abierto', 'info');
    } else {
        alert('No se pudo abrir la ventana.\n\nHabilita ventanas emergentes en tu navegador.');
    }
}

// Eliminar la funcion imprimirTicketEfectivo() antigua

        async function cancelarVenta() {
            if (!ventaActual) {
                mostrarAlerta('No hay una venta activa para cancelar', 'error');
                return;
            }
            
            if (confirm('¿Está seguro que desea cancelar la venta actual?')) {
                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'cancelar_venta',
                            venta_id: ventaActual
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarAlerta('Venta cancelada exitosamente', 'info');
                        nuevaVenta();
                    } else {
                        mostrarAlerta('Error al cancelar venta: ' + data.message, 'error');
                    }
                } catch (error) {
                    mostrarAlerta('Error de conexión: ' + error.message, 'error');
                    console.error('Error completo:', error);
                }
            }
        }

        
        function actualizarInterfaz() {
            // Actualizar lista de items
            const itemsList = document.getElementById('itemsList');
            if (!itemsList) {
                console.error('Elemento itemsList no encontrado');
                return;
            }
            
            if (itemsVenta.length === 0) {
                itemsList.innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">No hay items escaneados</div>';
            } else {
                let html = '';
                itemsVenta.forEach((item, index) => {
                    // Determinar clase CSS según departamento
                    let claseDepto = '';
                    let tagClass = '';
                    switch(item.departamento_id) {
                        case 1:
                            claseDepto = 'departamento-verduleria';
                            tagClass = 'tag-verduleria';
                            break;
                        case 2:
                            claseDepto = 'departamento-despensa';
                            tagClass = 'tag-despensa';
                            break;
                        case 3:
                            claseDepto = 'departamento-polleria-trozado';
                            tagClass = 'tag-polleria-trozado';
                            break;
                        case 4:
                            claseDepto = 'departamento-polleria-procesado';
                            tagClass = 'tag-polleria-procesado';
                            break;
                        default:
                            claseDepto = 'departamento-despensa';
                            tagClass = 'tag-despensa';
                    }
                    
                    html += `
                            <div class="item ${claseDepto}" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #ddd;">
                                <div class="item-info">
                                    <div>
                                        <strong>${item.departamento || 'Producto'}</strong>
                                        <span class="departamento-tag ${tagClass}">${item.departamento_id || 'N/A'}</span>
                                    </div>
                                    <div style="font-size: 0.9em; color: #666;">Código: ${item.codigo}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="item-price">${formatearPrecio(item.precio)}</div>
                                    <button class="btn btn-danger btn-sm" style="margin-top: 5px;" onclick="eliminarItem(${index})">❌</button>
                                </div>
                            </div>
                        `;

                });
                itemsList.innerHTML = html;
            }
            
            // Actualizar total
            const totalAmount = document.getElementById('totalAmount');
            if (totalAmount) {
                totalAmount.textContent = formatearPrecio(totalVenta);
            }
            
            // Ocultar campos de pago si no hay items
            if (itemsVenta.length === 0) {
                limpiarCamposEfectivo();
            }
        }

        async function eliminarItem(index) {
        if (index >= 0 && index < itemsVenta.length) {
        const itemEliminado = itemsVenta[index];
        
        try {
            // Eliminar del servidor/base de datos
            const response = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'eliminar_item',
                    venta_id: ventaActual,
                    codigo_barras: itemEliminado.codigo,
                    index: index // opcional, por si el backend lo necesita
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Solo eliminar del frontend si el backend confirma la eliminación
                itemsVenta.splice(index, 1);
                totalVenta -= parseFloat(itemEliminado.precio || 0);
                actualizarInterfaz();
                mostrarAlerta(`Producto eliminado: ${itemEliminado.codigo}`, 'info');
            } else {
                mostrarAlerta('Error al eliminar producto: ' + data.message, 'error');
            }
        } catch (error) {
            mostrarAlerta('Error de conexión al eliminar: ' + error.message, 'error');
            console.error('Error al eliminar item:', error);
        }
    }
}


        function mostrarAlerta(mensaje, tipo) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        console.error('Contenedor de alertas no encontrado');
        return;
    }
    
    // Remover alertas anteriores para evitar acumulación
    const alertasAnteriores = alertContainer.querySelectorAll('.alert');
    alertasAnteriores.forEach(alerta => {
        alerta.style.animation = 'slideOut 0.3s ease-in forwards';
        setTimeout(() => alerta.remove(), 300);
    });
    
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo === 'success' ? 'success' : tipo === 'error' ? 'error' : 'info'}`;
    alerta.textContent = mensaje;
    
    // Añadir al contenedor
    alertContainer.appendChild(alerta);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        if (alerta.parentNode) {
            alerta.style.animation = 'slideOut 0.5s ease-in forwards';
            setTimeout(() => alerta.remove(), 300);
        }
    }, 3000);
}

        function formatearPrecio(precio) {
            if (precio === null || precio === undefined) {
                return '$0.00';
            }
            return '$' + parseFloat(precio).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        // Event listeners adicionales
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar event listener para el botón de efectivo
            const efectivoBtn = document.querySelector('button[onclick*="efectivo"]');
            if (efectivoBtn) {
                efectivoBtn.addEventListener('click', function(e) {
                    if (totalVenta > 0) {
                        mostrarCampoEfectivo();
                    }
                });
            }

            // Agregar event listener para el botón de pago combinado
            const ambosBtn = document.querySelector('button[onclick*="ambos"]');
            if (ambosBtn) {
                ambosBtn.addEventListener('click', function(e) {
                    if (totalVenta > 0) {
                        mostrarCampoAmbos();
                    }
                });
            }

            // Event listener para calcular automáticamente en pago combinado
            const montoEfectivoInput = document.getElementById('montoEfectivo');
            const montoTransferenciaInput = document.getElementById('montoTransferencia');
            
            if (montoEfectivoInput && montoTransferenciaInput) {
                [montoEfectivoInput, montoTransferenciaInput].forEach(input => {
                    input.addEventListener('input', function() {
                        const efectivo = parseFloat(montoEfectivoInput.value) || 0;
                        const transferencia = parseFloat(montoTransferenciaInput.value) || 0;
                        const total = efectivo + transferencia;
                        
                        // Mostrar el total calculado visualmente
                        let infoElement = document.getElementById('totalCombinado');
                        if (!infoElement) {
                            infoElement = document.createElement('div');
                            infoElement.id = 'totalCombinado';
                            infoElement.style.cssText = 'margin-top: 10px; padding: 8px; background: #e9ecef; border-radius: 4px; font-weight: bold;';
                            montoTransferenciaInput.parentNode.appendChild(infoElement);
                        }
                        
                        infoElement.innerHTML = `Total ingresado: ${formatearPrecio(total)} / Requerido: ${formatearPrecio(totalVenta)}`;
                        infoElement.style.color = total >= totalVenta ? '#28a745' : '#dc3545';
                    });
                });
            }
        });

        // Funciones de utilidad adicionales
        function limpiarVenta() {
            totalVenta = 0;
            itemsVenta = [];
            ventaActual = null;
            actualizarInterfaz();
            limpiarCamposEfectivo();
        }

        // Función para manejar errores de red
        function manejarErrorRed(error) {
            console.error('Error de red:', error);
            mostrarAlerta('Error de conexión con el servidor. Verifique su conexión a internet.', 'error');
        }

        // Función para validar código de barras
        function validarCodigoBarras(codigo) {
            // Validación básica de código de barras
            if (!codigo || codigo.length < 8) {
                return false;
            }
            // Puedes agregar más validaciones específicas aquí
            return true;
        }

        // Manejo de errores globales para JavaScript
        window.addEventListener('error', function(e) {
            console.error('Error JavaScript:', e.error);
            mostrarAlerta('Ha ocurrido un error inesperado. Por favor recargue la página.', 'error');
        });

        // Función para debug
        function mostrarDebugInfo() {
            console.log('=== DEBUG INFO ===');
            console.log('Venta Actual:', ventaActual);
            console.log('Total Venta:', totalVenta);
            console.log('Items Venta:', itemsVenta);
            console.log('==================');
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + N para nueva venta
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                nuevaVenta();
            }
            
            // Escape para cancelar venta
            if (e.key === 'Escape') {
                if (ventaActual && itemsVenta.length > 0) {
                    if (confirm('¿Cancelar la venta actual?')) {
                        cancelarVenta();
                    }
                }
            }
            
            // F1 para mostrar debug info
            if (e.key === 'F1') {
                e.preventDefault();
                mostrarDebugInfo();
            }
        });

    </script>
</body>
</html>