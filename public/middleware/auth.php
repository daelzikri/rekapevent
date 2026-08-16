<?php
// public/middleware/auth.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/csrf.php';

init_session();

/**
 * Autentikasi Pengguna Standar Sesi PHP
 */
function authenticate_user(): array {
    if (empty($_SESSION['user_id'])) {
        redirect("/auth/login.php");
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect("/auth/login.php?error=User+tidak+ditemukan");
    }

    $GLOBALS['currentUser'] = $user;
    return $user;
}


/**
 * Validasi Role Pengguna
 */
function require_role(array $allowedRoles): array {
    $user = authenticate_user();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        die("403 Forbidden - Anda tidak memiliki akses ke halaman ini.");
    }
    return $user;
}

/**
 * Reset dan Hapus Sesi
 */
function clear_session_and_redirect(string $redirectUrl): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    redirect($redirectUrl);
}

// Jalankan autentikasi otomatis saat file middleware di-include (kecuali untuk halaman bebas auth)
$user = authenticate_user();
