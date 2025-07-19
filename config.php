<?php
//Configuración principal del sistema

session_start(); // Empezar la sesión

// Cargar variables
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception("Archivo .env no encontrado");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Cargar .env
loadEnv(__DIR__ . '/.env');

// Configuración de timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

// Configuración de base de datos
define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));

// Configuración de la aplicación
define('APP_NAME', getenv('APP_NAME'));
define('APP_URL', getenv('APP_URL'));
define('LOG_PATH', getenv('LOG_PATH'));

// Crear directorios necesarios
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Clase para conexión a base de datos
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Función para registrar logs
function logAccess($user_id, $email, $accion, $ip, $user_agent = '') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO accesos (user_id, email, accion, ip, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $email, $accion, $ip, $user_agent]);
    } catch (Exception $e) {
        error_log("Error logging access: " . $e->getMessage());
    }
}

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar rol
function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

// Función para redirigir
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Función para limpiar datos de entrada
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Función para obtener IP del usuario
function getUser IP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
?>
