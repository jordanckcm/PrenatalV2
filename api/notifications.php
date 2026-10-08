<?php
/**
 * API: Notifications for the logged-in user (any role).
 *   GET  ?action=summary            -> unread count + latest notifications (topbar bell)
 *   POST action=mark_read  id=N     -> mark one notification read
 *   POST action=mark_all            -> mark all read
 * Every query is scoped to the current user, so nobody can touch another user's notifications.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)getCurrentUserId();
$action = $_REQUEST['action'] ?? 'summary';

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        if ($action === 'mark_read') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        } elseif ($action === 'mark_all') {
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
        }
        echo json_encode(['success' => true, 'unread' => getUnreadNotificationCount($userId)]);
        exit;
    }

    // summary
    $stmt = $db->prepare("SELECT id, title, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 6");
    $stmt->execute([$userId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $items[] = [
            'id'      => (int)$n['id'],
            'title'   => $n['title'],
            'message' => clipText($n['message'], 120),
            'type'    => $n['type'],
            'link'    => $n['link'] ? BASE_URL . ltrim($n['link'], '/') : null,
            'is_read' => (bool)$n['is_read'],
            'time'    => formatDate($n['created_at'], 'M d, g:i A'),
        ];
    }
    echo json_encode(['success' => true, 'unread' => getUnreadNotificationCount($userId), 'items' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
