<?php
// public/auth/logout.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

init_session();


$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE users SET session_token = NULL, last_activity_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        log_audit($pdo, $userId, 'LOGOUT', 'Pengguna melakukan logout.');
    } catch (Exception $e) {
        error_log("Logout Error: " . $e->getMessage());
    }
}

session_unset();
session_destroy();

if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
    json_response(['status' => 'success', 'message' => 'Logged out successfully']);
}

redirect('/auth/login.php?success=Anda+telah+berhasil+logout');
