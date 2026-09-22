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

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['avatar'];

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File is too large (max 5MB)']);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}

$extMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext = $extMap[$mimeType] ?? 'jpg';

$filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
$uploadDir = __DIR__ . '/../assets/avatars/';
$uploadPath = $uploadDir . $filename;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
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

    $relativePath = 'assets/avatars/' . $filename;
    $stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$relativePath, $userId]);

    logAudit('AVATAR_UPLOAD', "User uploaded avatar");

    echo json_encode(['success' => true, 'message' => 'Profile picture updated!', 'avatar_url' => BASE_URL . $relativePath]);

} catch (Exception $e) {
    @unlink($uploadPath);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}