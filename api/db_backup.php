<?php
/**
 * Database Backup Download Handler (Admin Only)
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 *
 * Produces a .sql file that can be re-imported in phpMyAdmin: NULLs stay NULL, values are quoted safely,
 * and foreign-key checks are switched off while tables are dropped/created/filled.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

try {
    $db = getDB();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $filename = 'prenatal_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- MaternalCare System Database Backup\n";
    echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach ($tables as $table) {
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n";

        $result = $db->query("SELECT * FROM `$table`");
        $batch = [];
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $value) {
                $values[] = ($value === null) ? 'NULL' : $db->quote((string)$value);
            }
            $batch[] = '(' . implode(',', $values) . ')';
            if (count($batch) >= 100) {
                echo "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) {
            echo "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";

    logAudit('DB_BACKUP_DOWNLOAD', "Database backup downloaded by Admin ID " . getCurrentUserId());
    exit;

} catch (Exception $e) {
    die("Backup Error: " . $e->getMessage());
}
