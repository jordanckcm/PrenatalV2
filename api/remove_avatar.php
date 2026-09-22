<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldAvatar = $stmt->fetchColumn();

    if ($oldAvatar && file_exists(__DIR__ . '/../' . $oldAvatar)) {
        @unlink(__DIR__ . '/../' . $oldAvatar);
    }

    $stmt = $db->prepare("UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);

    logAudit('AVATAR_REMOVED', "User removed avatar");

    echo json_encode(['success' => true, 'message' => 'Profile picture removed']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}