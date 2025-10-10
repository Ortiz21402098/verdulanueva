<?php

require_once 'config.php';

class InicializadorPlanilla {
    
    public static function crearPlanillaVirgen() {
        $archivo = __DIR__ . '/PLANILLA_DIARIA_VIRGEN_Verduleria.xlsx';
        
        $fp = fopen($archivo, 'w');
        
        // Escribir BOM para UTF-8
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // === SECCIÓN VERDULERIA (HOJA 1) ===
        self::escribirSeccion($fp, 1, 'VERDULERIA');
        
        // === SECCIÓN POLLERIA (HOJA 2) ===
        self::escribirSeccion($fp, 2, 'POLLERIA');
        
        // === SECCIÓN DESPENSA (HOJA 3) ===
        self::escribirSeccion($fp, 3, 'DESPENSA');
        
        fclose($fp);
        
        echo "Planilla virgen creada exitosamente en: $archivo\n";
        return $archivo;
    }
    
    private static function escribirSeccion($fp, $hoja, $departamento) {
        // Encabezado de la sección
        if ($hoja == 1) {
            $encabezado = ["    // PLANILLA DIARIA", "", "", "", "", "", "", "", "", "HOJA {$hoja}", "__/__/__PLANILLA DIARIA {$departamento}"];
        } else {
            $encabezado = ["   PLANILLA DIARIA      HOJA {$hoja}", "", "", "", "", "", "", "", "", "", "__/__/__PLANILLA DIARIA {$departamento}"];
        }
        
        // Escribir con punto y coma como separador
        fputcsv($fp, $encabezado, ';');
        
        // Línea GENERAL
        $general = ["GENERAL"];
        for ($i = 1; $i <= 22; $i++) $general[] = "";
        fputcsv($fp, $general, ';');
        
        // Encabezados de columnas
        $columnas = [];
        $tipos = ["EFECTIVO", "EFECTIVO", "EFECTIVO", "DEBITO", "DEBITO", "DEBITO", 
                 "EFECTIVO", "EFECTIVO", "EFECTIVO", "EFECTVO", "EFECTIVO", "EFECTIVO"];
        
        for ($i = 0; $i < 12; $i++) {
            $columnas[] = "Nº";
            $columnas[] = $tipos[$i];
        }
        fputcsv($fp, $columnas, ';');
        
        // Filas numeradas (1-42)
        for ($fila = 1; $fila <= 42; $fila++) {
            $datos = [];
            
            // Primera columna siempre tiene número
            $datos[] = $fila;
            $datos[] = ($fila == 1) ? "0" : "";
            
            // Resto de columnas
            for ($col = 1; $col < 12; $col++) {
                $numero = $fila + ($col * 42);
                $datos[] = $numero;
                $datos[] = ($fila == 1) ? "0" : "";
            }
            
            fputcsv($fp, $datos, ';');
        }
        
        // Fila de información de pago (solo para verdulería)
        if ($departamento == 'VERDULERIA') {
            self::escribirInfoPagos($fp);
        } else {
            self::escribirInfoPedidos($fp);
        }
        
        // Línea de totales
        $totales = ["TOT", "0"];
        for ($i = 1; $i < 12; $i++) {
            $totales[] = "TOT";
            $totales[] = "0";
        }
        fputcsv($fp, $totales, ';');
    }
    
    private static function escribirInfoPagos($fp) {
        // Sección específica para Verdulería con información de pagos
        $filas_info = [
            ["", "", "TOT", "0", "TOT", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
            ["", "", "PAGOS EN EFECTIVO", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
            ["", "", "1", "VALES MERCADERIA", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
            ["", "", "2", "EFECTIVO QUEDA", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
        ];
        
        // Escribir filas adicionales hasta completar estructura
        for ($i = 3; $i <= 15; $i++) {
            $filas_info[] = ["", "", $i, "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        }
        
        // Información de totales y resumen
        $filas_info[] = ["", "", "TOTAL EFECTIVO", "", "", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "PERSONAS", "", "PEDIDOS", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "0", "", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "SUMA FILAS EF", "", "SUMA FILA DEBITO", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "0", "", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "EFECTIVO", "", "POSNET", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "0", "", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "VALES", "", "TOTAL RUBROS", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "0", "", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        $filas_info[] = ["", "", "CTA.CTE", "", "", "TOTAL", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        
        foreach ($filas_info as $fila) {
            fputcsv($fp, $fila, ';');
        }
    }
    
    private static function escribirInfoPedidos($fp) {
        // Sección para Pollería y Despensa con información de pedidos
        $filas_info = [
            ["", "", "TOT", "0", "TOT", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
            ["", "", "PEDIDOS EN CALLE", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
            ["", "", "1", "", "", "0", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""],
        ];
        
        // Completar estructura con filas numeradas
        for ($i = 2; $i <= 25; $i++) {
            $filas_info[] = ["", "", $i, "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""];
        }
        
        foreach ($filas_info as $fila) {
            fputcsv($fp, $fila, ';');
        }
    }
    
    /**
     * Método para verificar si existe la planilla virgen
     */
    public static function verificarPlanillaVirgen() {
        $archivo = __DIR__ . '/PLANILLA_DIARIA_VIRGEN_Verduleria.csv';
        return file_exists($archivo);
    }
    
    /**
     * Método para recrear la planilla si no existe
     */
    public static function asegurarPlanillaVirgen() {
        if (!self::verificarPlanillaVirgen()) {
            return self::crearPlanillaVirgen();
        }
        return __DIR__ . '/PLANILLA_DIARIA_VIRGEN_Verduleria.xlsx';
    }
}

// Ejecutar si se llama directamente
if (basename(__FILE__) == basename($_SERVER["SCRIPT_NAME"])) {
    echo "Inicializando planilla virgen...\n";
    InicializadorPlanilla::crearPlanillaVirgen();
    echo "Proceso completado.\n";
}
?>