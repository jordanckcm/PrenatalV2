<?php
/**
 * Get Slots API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/slot_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? '';

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'Missing date']);
    exit;
}

try {
    $db = getDB();
    ensureSlotOverridesTable($db);

    $slots = getSlotsForDate($db, $date);

    echo json_encode([
        'success' => true,
        'date' => $date,
        'slots' => $slots,
        'total_available' => count(array_filter($slots, fn($s) => !$s['is_blocked'] && $s['available'] > 0))
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}