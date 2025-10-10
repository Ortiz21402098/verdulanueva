<?php
class CodigoBarras {
       
    public function decodificarPrecio($codigoBarras) {
        // Validar longitud del código
        if (strlen($codigoBarras) != 13) {
            return [
                'valido' => false,
                'error' => 'Código de barras debe tener 13 dígitos'
            ];
        }
        
        // Validar que sean solo números
        if (!preg_match('/^\d{13}$/', $codigoBarras)) {
            return [
                'valido' => false,
                'error' => 'Código de barras debe contener solo números'
            ];
        }
        
        // NUEVA LÓGICA: Determinar departamento por el PRIMER dígito
        $primerDigito = substr($codigoBarras, 0, 1);
        
        // Mapeo según la nueva especificación
        $departamentos = [
            '1' => ['id' => 1, 'nombre' => 'Verdulería'],
            '2' => ['id' => 2, 'nombre' => 'Despensa'], 
            '3' => ['id' => 3, 'nombre' => 'Pollería Trozado'],
            '4' => ['id' => 4, 'nombre' => 'Pollería Procesados']
        ];
        
        // Validar que el primer dígito corresponda a un departamento válido
        if (!isset($departamentos[$primerDigito])) {
            return [
                'valido' => false,
                'error' => "Código no válido: el primer dígito '$primerDigito' no corresponde a ningún departamento conocido"
            ];
        }
        
        $departamentoInfo = $departamentos[$primerDigito];
        
        // Extraer información del código (mantenemos la lógica de precio existente)
        $prefijo = substr($codigoBarras, 0, 3); // Ej: 120, 220, 320
        $tipoBalanza = substr($codigoBarras, 3, 1); // 1 o 4
        $codigoProducto = substr($codigoBarras, 4, 6); // 000002
        $verificacion = substr($codigoBarras, 10, 3); // 004
        
        $parteNumerica = substr($codigoBarras, 4); // Desde posición 4: "000000895"
        
        $sinCeros = ltrim($parteNumerica, '0'); // "895"
        $longitudSinCeros = strlen($sinCeros);
        
        // LÓGICA CORREGIDA PARA EXTRACCIÓN DE PRECIOS
        if ($longitudSinCeros == 4) {
            // Para 4 dígitos como "2004", el precio son los primeros 3: "200"
            $precioExtraido = substr($sinCeros, 0, 3); // "200"
        } else if ($longitudSinCeros == 5) {
            // Para 5 dígitos, tomar primeros 4
            $precioExtraido = substr($sinCeros, 0, 4);
        } else if ($longitudSinCeros == 3) {
            // CORRECCIÓN: Para 3 dígitos, tomar primeros 2 como precio
            // Ejemplo: "895" -> "89" (que representa $89.00)
            $precioExtraido = substr($sinCeros, 0, 2);
        } else if ($longitudSinCeros <= 2) {
            // Para números muy cortos (1-2 dígitos), usar todo
            $precioExtraido = $sinCeros;
        } else {
            // Para números largos (6+), aplicar lógica anterior
            if ($longitudSinCeros == 6) {
                $precioExtraido = substr($sinCeros, 0, 5);
            } else if ($longitudSinCeros >= 7) {
                $precioExtraido = substr($sinCeros, 0, -2);
            } else {
                $precioExtraido = $sinCeros;
            }
        }
        
        // Debug info actualizado para el nuevo formato
        $debug_extracciones = [
            'codigo_completo' => $codigoBarras,
            'primer_digito' => $primerDigito,
            'departamento_detectado' => $departamentoInfo['nombre'],
            'estructura_analizada' => [
                'departamento' => $primerDigito,
                'prefijo_restante' => substr($codigoBarras, 1, 3), // posiciones 1-3
                'parte_numerica' => $parteNumerica,
            ],
            'sin_ceros' => $sinCeros,
            'longitud_sin_ceros' => $longitudSinCeros,
            'precio_extraido' => $precioExtraido,
            'logica_aplicada' => "Nuevo formato - Longitud $longitudSinCeros: " . 
                ($longitudSinCeros == 4 ? "tomar primeros 3 dígitos" : 
                ($longitudSinCeros == 5 ? "tomar primeros 4 dígitos" : 
                ($longitudSinCeros == 3 ? "tomar primeros 2 dígitos (CORREGIDO)" : 
                ($longitudSinCeros <= 2 ? "usar todos los dígitos" : "aplicar lógica para números largos"))))
        ];
        
        // Remover ceros a la izquierda del precio final
        $precioExtraido = ltrim($precioExtraido, '0');
        if (empty($precioExtraido)) {
            $precioExtraido = '0';
        }
        
        $precioReal = floatval($precioExtraido);
        
        return [
            'valido' => true,
            'precio' => $precioReal,
            'departamento' => $departamentoInfo['nombre'],
            'departamento_id' => $departamentoInfo['id'],
            'codigo_departamento' => $primerDigito,
            'codigo_producto' => $codigoProducto,
            'codigo_completo' => $codigoBarras,
            'info_debug' => [
                'prefijo' => $prefijo,
                'tipo_balanza' => $tipoBalanza,
                'extracciones_probadas' => $debug_extracciones,
                'precio_final_extraido' => $precioExtraido,
                'calculo' => "Precio extraído: $precioExtraido = $precioReal"
            ]
        ];
    }
    
    /**
     * Validar código de barras con nueva lógica
     */
    public function validarCodigoKendal($codigoBarras) {
        $resultado = $this->decodificarPrecio($codigoBarras);
        return $resultado['valido'];
    }
    
    /**
     * Obtener información del departamento por código (actualizado)
     */
    public function obtenerDepartamento($codigoDepartamento) {
        $departamentos = [
            '1' => ['id' => 1, 'nombre' => 'Verdulería', 'color' => '#28a745'],
            '2' => ['id' => 2, 'nombre' => 'Despensa', 'color' => '#17a2b8'],
            '3' => ['id' => 3, 'nombre' => 'Pollería Trozado', 'color' => '#ffc107'],
            '4' => ['id' => 4, 'nombre' => 'Pollería Procesados', 'color' => '#ff1707ff'],
        ];
        
        return isset($departamentos[$codigoDepartamento]) ? 
               $departamentos[$codigoDepartamento] : null;
    }
    
    /**
     * Generar código de barras de prueba con nueva lógica
     */
    public function generarCodigoPrueba($departamento, $precio) {
        $codigosDep = ['1', '2', '3', '4']; // Verdulería, Despensa, Pollería trozado, polleria elaborado
        
        if (!in_array($departamento, $codigosDep)) {
            return false;
        }
        
        // Convertir precio a centavos para el ejemplo
        $precioCentavos = intval($precio * 100);
        
        // Nuevo formato: [DEP][XX]4[PRODUCTO][PRECIO][CHECK]
        // Donde DEP es 1, 2, 3 o 4
        $prefijo = $departamento . '20'; // Ej: 120, 220, 320, 420
        $tipoBalanza = '1';
        $codigoProducto = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $precioStr = str_pad($precioCentavos, 4, '0', STR_PAD_LEFT);
        $checksum = substr($precioStr, -1);
        
        return $prefijo . $tipoBalanza . substr($codigoProducto, 0, 5) . $precioStr . $checksum;
    }
}

// Ejemplo de uso y testing con los nuevos códigos
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    $codigoBarras = new CodigoBarras();
    
    // Probar con el código problemático y otros casos
    $codigosNuevos = [
        '1201000002004', // Verdulería - debería dar $200.00
        '1201000000895', // Verdulería - CASO PROBLEMÁTICO: debería dar $89.00
        '2201000021004', // Despensa  
        '3201000064956', // Pollería Trozado
        '4201000064956', // Pollería Procesados
        '1201000000055', // Caso de 2 dígitos
        '1201000000005', // Caso de 1 dígito
    ];
    
    echo "<h2>Prueba de Códigos con Lógica Corregida:</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Código</th><th>Departamento</th><th>Precio</th><th>Proceso</th><th>Debug Info</th>";
    echo "</tr>";
    
    foreach ($codigosNuevos as $codigo) {
        $resultado = $codigoBarras->decodificarPrecio($codigo);
        
        echo "<tr>";
        echo "<td><strong>$codigo</strong></td>";
        
        if ($resultado['valido']) {
            echo "<td style='color: " . ($resultado['codigo_departamento'] == '1' ? 'green' : ($resultado['codigo_departamento'] == '2' ? 'blue' : 'orange')) . ";'>";
            echo $resultado['departamento'] . " (ID: " . $resultado['departamento_id'] . ")";
            echo "</td>";
            
            // Destacar el caso problemático
            $esProblematico = $codigo == '1201000000895';
            $colorPrecio = $esProblematico ? 'red' : 'green';
            $fontWeight = $esProblematico ? 'bold' : 'normal';
            
            echo "<td style='color: $colorPrecio; font-weight: $fontWeight;'>$" . number_format($resultado['precio'], 2);
            if ($esProblematico) echo " ← CORREGIDO";
            echo "</td>";
            
            // Mostrar el proceso de extracción
            $debug = $resultado['info_debug']['extracciones_probadas'];
            echo "<td style='font-size: 0.8em;'>";
            echo "Parte numérica: " . $debug['parte_numerica'] . "<br>";
            echo "Sin ceros: " . $debug['sin_ceros'] . "<br>";
            echo "Extraído: " . $debug['precio_extraido'] . "<br>";
            echo "<em>" . $debug['logica_aplicada'] . "</em>";
            echo "</td>";
            
            echo "<td><details><summary>Ver detalles</summary><pre style='font-size: 10px;'>" . print_r($resultado['info_debug'], true) . "</pre></details></td>";
        } else {
            echo "<td colspan='4' style='color: red;'>" . $resultado['error'] . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Lógica de Extracción de Precios CORREGIDA:</h3>";
    echo "<ul>";
    echo "<li><strong>4 dígitos</strong> sin ceros → Tomar primeros 3 (ej: 2004 → 200 = $200.00)</li>";
    echo "<li><strong>5 dígitos</strong> sin ceros → Tomar primeros 4</li>";
    echo "<li><strong style='color: red;'>3 dígitos</strong> sin ceros → Tomar primeros 2 (ej: 895 → 89 = $89.00) ← CORREGIDO</li>";
    echo "<li><strong>≤2 dígitos</strong> → Usar todos</li>";
    echo "<li><strong>≥6 dígitos</strong> → Aplicar lógica anterior</li>";
    echo "</ul>";
    
    echo "<h4>Resumen de Departamentos:</h4>";
    echo "<ul>";
    echo "<li><strong>1</strong>XXXXXXXXXX = Verdulería (ID: 1)</li>";
    echo "<li><strong>2</strong>XXXXXXXXXX = Despensa (ID: 2)</li>";
    echo "<li><strong>3</strong>XXXXXXXXXX = Pollería Trozado (ID: 3)</li>";
    echo "<li><strong>4</strong>XXXXXXXXXX = Pollería Procesados (ID: 4)</li>";
    echo "</ul>";
}
?>