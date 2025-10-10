<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Argentina/Cordoba');

class Database {
    private $host = 'localhost';
    private $db_name = 'mini_supermercado';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => true
                    ]
                );
            } catch(PDOException $exception) {
                logError("Error de conexión a base de datos: " . $exception->getMessage());
                die("Error de conexión a la base de datos");
            }
        }
        return $this->conn;
    }
}


class ConfigManager {
    private static $instance = null;
    private $db;
    private $config = [];
    
    private function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->cargarConfiguracion();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ConfigManager();
        }
        return self::$instance;
    }
    
    private function cargarConfiguracion() {
        try {
            $stmt = $this->db->prepare("SELECT clave, valor, tipo FROM configuracion");
            $stmt->execute();
            
            while ($row = $stmt->fetch()) {
                $valor = $row['valor'];
                
                // Convertir según el tipo
                switch ($row['tipo']) {
                    case 'int':
                        $valor = (int)$valor;
                        break;
                    case 'float':
                        $valor = (float)$valor;
                        break;
                    case 'boolean':
                        $valor = (bool)$valor;
                        break;
                    case 'json':
                        $valor = json_decode($valor, true);
                        break;
                }
                
                $this->config[$row['clave']] = $valor;
            }
        } catch (PDOException $e) {
            logError("Error cargando configuración: " . $e->getMessage());
        }
    }
    
    public function get($clave, $default = null) {
        return isset($this->config[$clave]) ? $this->config[$clave] : $default;
    }
    
    public function set($clave, $valor, $tipo = 'string') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO configuracion (clave, valor, tipo) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)
            ");
            $stmt->execute([$clave, $valor, $tipo]);
            $this->config[$clave] = $valor;
            return true;
        } catch (PDOException $e) {
            logError("Error guardando configuración: " . $e->getMessage());
            return false;
        }
    }
}

// Incluir clases necesarias
require_once 'CodigoBarras.php';

// Inicializar configuración
$config = ConfigManager::getInstance();

// Constantes del sistema
define('NOMBRE_NEGOCIO', $config->get('nombre_negocio', 'Mini Supermercado La Nueva'));
define('DIRECCION_NEGOCIO', $config->get('direccion_negocio', 'Av. Amadeo Sabattini 2165'));
define('CUIT_NEGOCIO', $config->get('cuit_negocio', '20-12345678-9'));

// Configuración de impresora fiscal
define('IMPRESORA_FISCAL_HABILITADA', $config->get('impresora_fiscal_habilitada', true));
define('IMPRESORA_FISCAL_PUERTO', $config->get('impresora_fiscal_puerto', 'COM1'));
define('IMPRESORA_FISCAL_MODELO', $config->get('impresora_fiscal_modelo', 'RPT001'));

// Departamentos - cargar desde BD
function obtenerDepartamentos() {
    static $departamentos = null;
    
    if ($departamentos === null) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $stmt = $db->prepare("SELECT id, nombre, codigo_prefijo FROM departamentos WHERE activo = 1 ORDER BY id");
            $stmt->execute();
            $departamentos = [];
            
            while ($row = $stmt->fetch()) {
                $departamentos[$row['id']] = [
                    'nombre' => $row['nombre'],
                    'codigo_prefijo' => $row['codigo_prefijo']
                ];
            }
        } catch (PDOException $e) {
            logError("Error cargando departamentos: " . $e->getMessage());
            // Fallback a departamentos por defecto
            $departamentos = [
                1 => ['nombre' => 'Verdulería', 'codigo_prefijo' => '1204'],
                2 => ['nombre' => 'Despensa', 'codigo_prefijo' => '2204'],
                3 => ['nombre' => 'Pollería Trozado', 'codigo_prefijo' => '3204'],
                4 => ['nombre' => 'Pollería Elaborado', 'codigo_prefijo' => '4204']
            ];
        }
    }
    
    return $departamentos;
}

// Tipos de pago
define('TIPOS_PAGO', [
    'efectivo' => 'Efectivo',
    'tarjeta' => 'Tarjeta de Débito/Crédito',
    'qr' => 'Código QR / Transferencia',
    'mixto' => 'Efectivo / Transferencia'
]);

// Funciones utilitarias
function formatearPrecio($precio) {
    $simbolo = ConfigManager::getInstance()->get('moneda_simbolo', '$');
    return $simbolo . number_format($precio, 2, '.', ',');
}

function logError($mensaje, $archivo = 'logs/error.log') {
    // Crear directorio de logs si no existe
    $dirLogs = dirname($archivo);
    if (!is_dir($dirLogs)) {
        mkdir($dirLogs, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log = "[$timestamp] [$ip] $mensaje" . PHP_EOL;
    file_put_contents($archivo, $log, FILE_APPEND | LOCK_EX);
}

function logOperacion($ventaId, $operacion, $descripcion, $usuario = 'sistema') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO log_operaciones (venta_id, operacion, descripcion, usuario) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ventaId, $operacion, $descripcion, $usuario]);
    } catch (PDOException $e) {
        logError("Error logging operación: " . $e->getMessage());
    }
}

function jsonResponse($success, $data = null, $message = '', $httpCode = 200) {
    // Limpiar TODOS los buffers de salida y descartar el contenido
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verificar si ya se enviaron headers
    if (headers_sent($file, $line)) {
        // Si ya se enviaron headers, logear el error y enviar solo JSON sin headers
        logError("Headers ya enviados en $file línea $line. Enviando JSON sin headers.");
        
        // Solo enviar el JSON sin modificar headers
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            if (is_array($data)) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        }
        
        if (defined('DEBUG')) {
            $response['debug'] = [
                'memory_usage' => memory_get_usage(true),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'headers_sent_at' => "$file:$line"
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // Si no se enviaron headers, proceder normalmente
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];
    
    if ($data !== null) {
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }
    }
    
    if (defined('DEBUG')) {
        $response['debug'] = [
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, null, 'JSON inválido: ' . json_last_error_msg(), 400);
    }
    
    return $data;
}

// Función para sanitizar entrada
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'string':
        default:
            return trim(strip_tags($input));
    }
}

// Configuración para manejo de errores
// Configuración para manejo de errores - VERSIÓN MEJORADA
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorMsg = "Error PHP [$severity]: $message en $file línea $line";
    logError($errorMsg);
    
    // Si estamos en una página HTML (no API), no enviar JSON
    $isHtmlPage = basename($_SERVER['SCRIPT_NAME']) === 'info_ventas.php' || 
                  strpos($_SERVER['SCRIPT_NAME'], 'ajax_') === false;
    
    if ($isHtmlPage) {
        // Solo logear y continuar para páginas HTML
        return true;
    }
    
    // Limpiar buffers antes de responder (solo para API)
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // En producción, no mostrar errores detallados
    if (!defined('DEBUG')) {
        jsonResponse(false, null, 'Error interno del servidor', 500);
    }
    
    return true;
});

set_exception_handler(function($exception) {
    $errorMsg = "Excepción no capturada: " . $exception->getMessage() . 
                " en " . $exception->getFile() . 
                " línea " . $exception->getLine();
    logError($errorMsg);
    
    // Si estamos en una página HTML (no API), no enviar JSON
    $isHtmlPage = basename($_SERVER['SCRIPT_NAME']) === 'info_ventas.php' || 
                  strpos($_SERVER['SCRIPT_NAME'], 'ajax_') === false;
    
    if ($isHtmlPage) {
        // Para páginas HTML, mostrar error amigable
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:20px; border-radius:5px; border:1px solid #f5c6cb;'>";
        echo "<strong>Error del sistema:</strong> Ha ocurrido un problema. Por favor, contacte al administrador.";
        echo "</div>";
        return;
    }
    
    // Limpiar buffers antes de responder (solo para API)
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!defined('DEBUG')) {
        jsonResponse(false, null, 'Error interno del servidor', 500);
    } else {
        jsonResponse(false, null, $exception->getMessage(), 500);
    }
});
// Verificar conexión a la base de datos al cargar
try {
    $testDb = new Database();
    $testDb->getConnection();
} catch (Exception $e) {
    logError("Error crítico: No se puede conectar a la base de datos");
    die("Sistema temporalmente no disponible");
}
?>