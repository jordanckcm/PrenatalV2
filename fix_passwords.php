<?php
/**
 * Password Reset Utility
 * Fixes initial seed accounts by updating user password hashes to valid bcrypt format.
 */

require_once __DIR__ . '/config/config.php';

// Utility for the local demo only: refuse to run for anyone but the computer hosting the site
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This utility only runs on the server itself (localhost).');
}

try {
    $db = getDB();
    $newHash = password_hash('password123', PASSWORD_BCRYPT);

    // Only repair accounts whose stored hash is not a valid bcrypt hash; real passwords are left alone
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE LENGTH(password) <> 60 OR password NOT LIKE '\$2y\$%'");
    $stmt->execute([$newHash]);

    $count = $stmt->rowCount();

    echo "<!DOCTYPE html><html><head><title>Password Fix</title>";
    echo "<style>body{font-family:sans-serif;padding:2rem;background:#f8fafc;} .card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);max-width:500px;margin:auto;} .btn{display:inline-block;padding:10px 20px;background:#6366f1;color:#fff;text-decoration:none;border-radius:8px;margin-top:1rem;}</style></head><body>";
    echo "<div class='card'>";
    echo "<h2 style='color:#10b981;'>✓ Passwords Reset Successfully!</h2>";
    echo "<p>Updated <strong>{$count}</strong> user account(s) to password: <code>password123</code></p>";
    echo "<a href='login.php' class='btn'>Go to Login Page</a>";
    echo "</div></body></html>";

} catch (Exception $e) {
    echo "Error updating passwords: " . htmlspecialchars($e->getMessage());
}
