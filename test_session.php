<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: text/plain');

echo "=== SESSION DATA ===\n";
print_r($_SESSION);

echo "\n=== getCurrentUserId() ===\n";
var_dump(getCurrentUserId());

echo "\n=== getCurrentUserRole() ===\n";
var_dump(getCurrentUserRole());

echo "\n=== Users in DB ===\n";
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, username, role FROM users");
    print_r($stmt->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "\n=== Patients in DB ===\n";
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, user_id, patient_code FROM patients");
    print_r($stmt->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}