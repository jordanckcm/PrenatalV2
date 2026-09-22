<?php
/**
 * Database Connection Configuration (PDO)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

// ============================================
// RAILWAY DATABASE CONNECTION
// ============================================

// Railway provides this through the DATABASE_URL environment variable
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    die("Database Connection Error: DATABASE_URL is not configured");
}

// Parse Railway's MySQL connection URL
$db = parse_url($databaseUrl);

if (!$db || !isset($db['host'], $db['user'], $db['pass'], $db['path'])) {
    die("Database Connection Error: Invalid DATABASE_URL");
}

// Database credentials
define('DB_HOST', $db['host']);
define('DB_USER', $db['user']);
define('DB_PASS', $db['pass']);
define('DB_NAME', ltrim($db['path'], '/'));
define('DB_PORT', $db['port'] ?? 3306);


// ============================================
// DATABASE CONNECTION CLASS
// ============================================

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {

            // PDO connection string
            $dsn = "mysql:host=" . DB_HOST .
                   ";port=" . DB_PORT .
                   ";dbname=" . DB_NAME .
                   ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            // Connect to Railway MySQL
            $this->conn = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $options
            );

            // Set timezone to Asia/Manila
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


// ============================================
// GLOBAL DATABASE HELPER
// ============================================

function getDB() {
    return Database::getInstance()->getConnection();
}
