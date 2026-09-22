<?php
/**
 * Database Connection Configuration (PDO)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

// ============================================
// DATABASE CREDENTIALS
// ============================================
// On Railway, DATABASE_URL looks like: mysql://user:password@host:port/database
$databaseUrl = getenv('DATABASE_URL');

error_log('DEBUG DATABASE_URL is: ' . var_export($databaseUrl, true));

if ($databaseUrl) {
    $dbParts = parse_url($databaseUrl);
    define('DB_HOST', $dbParts['host']);
    define('DB_USER', $dbParts['user']);
    define('DB_PASS', $dbParts['pass']);
    define('DB_NAME', ltrim($dbParts['path'], '/'));
    define('DB_PORT', $dbParts['port'] ?? 3306);
} else {
    // Local XAMPP fallback
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'prenatal');
    define('DB_PORT', 3306);
}

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            // ✅ APIL ANG PORT SA DSN
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->conn->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}
