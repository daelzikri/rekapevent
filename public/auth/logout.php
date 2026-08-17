<?php
// public/auth/logout.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

init_session();

$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    try {
        $pdo = get_db_connection();
        $stmtOut = $pdo->prepare("UPDATE users SET session_token = NULL, last_activity_at = NULL WHERE id = :id");
        $stmtOut->execute([':id' => $userId]);

        log_audit($pdo, $userId, 'LOGOUT', 'Pengguna melakukan logout.');
    } catch (Exception $e) {
        error_log("Logout Error: " . $e->getMessage());
    }
}

// Hapus semua data sesi
$_SESSION = [];

// Hapus cookie sesi
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: /auth/login.php?success=Anda+telah+berhasil+logout");
exit;
