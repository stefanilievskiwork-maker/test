
<?php
/**
 * Configuration File
 * Contains database settings, API keys, and global constants
 */

// Prevent direct access
if (!defined('DASHBOARD_ACCESS')) {
    die('Direct access not allowed');
}

// Environment (development, staging, production)
define('ENVIRONMENT', 'development');

// Database Configuration - UPDATE THESE!
define('DB_HOST', 'localhost');
define('DB_NAME', 'vidapi_movie');
define('DB_USER', 'vidapi_luck');
define('DB_PASS', 'Suzukininja1@');
define('DB_CHARSET', 'utf8mb4');

// API Keys
define('TMDB_API_KEY', 'your_tmdb_api_key_here');
define('OPENAI_API_KEY', 'your_openai_api_key_here');

// Security Settings
define('JWT_SECRET', 'your_super_secret_jwt_key_change_this_in_production');
define('PASSWORD_SALT', 'your_password_salt_change_this_in_production');
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 1800); // 30 minutes in seconds

// Site Configuration
define('SITE_NAME', 'Movie Database Dashboard');
define('SITE_URL', 'https://yourdomain.com/dashboard');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// File Upload Settings
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'ico']);

// TMDB API Settings
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3/');
define('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/');
define('TMDB_RATE_LIMIT', 40); // Requests per 10 seconds

// OpenAI API Settings
define('OPENAI_MODEL', 'gpt-3.5-turbo');
define('OPENAI_MAX_TOKENS', 1000);
define('OPENAI_TEMPERATURE', 0.7);

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG', true);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG', false);
}

// Timezone
date_default_timezone_set('UTC');

/**
 * Database Connection Class
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG) {
                die('Database connection failed: ' . $e->getMessage());
            } else {
                die('Database connection failed. Please try again later.');
            }
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
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Utility Functions
 */

/**
 * Get database connection
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password . PASSWORD_SALT, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password . PASSWORD_SALT, $hash);
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt->execute([$user_id, $action, $details, $ip, $userAgent]);
    } catch (Exception $e) {
        if (DEBUG) {
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_authenticated']) && $_SESSION['user_authenticated'] === true;
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, email, role, created_at, last_login FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([getCurrentUserId()]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Redirect function
 */
function redirect($url, $permanent = false) {
    if ($permanent) {
        header("HTTP/1.1 301 Moved Permanently");
    }
    header("Location: $url");
    exit;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'just now';
    } elseif ($time < 3600) {
        return floor($time / 60) . ' minutes ago';
    } elseif ($time < 86400) {
        return floor($time / 3600) . ' hours ago';
    } elseif ($time < 2592000) {
        return floor($time / 86400) . ' days ago';
    } elseif ($time < 31536000) {
        return floor($time / 2592000) . ' months ago';
    } else {
        return floor($time / 31536000) . ' years ago';
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// Define dashboard access constant
define('DASHBOARD_ACCESS', true);
?>
