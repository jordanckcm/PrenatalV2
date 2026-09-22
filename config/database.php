<?php
/**
 * Database Connection Configuration (PDO)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

// ============================================
// DATABASE CREDENTIALS
// ============================================
// Para sa LOCAL (XAMPP):
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'prenatal');
// define('DB_PORT', 3307);

// Para sa LIVE SERVER (i-update kini base sa imong hosting):
define('DB_HOST', 'localhost');           // Usually 'localhost' sa shared hosting
define('DB_USER', 'root');   // ← ILISI ni
define('DB_PASS', '');   // ← ILISI ni
define('DB_NAME', 'prenatal');       // ← ILISI ni
define('DB_PORT', 3306);                  // ← 3306 para sa live server

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