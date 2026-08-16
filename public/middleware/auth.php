<?php
// public/middleware/auth.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/csrf.php';

init_session();

/**
 * Autentikasi Pengguna — Sesederhana mungkin.
 * Jika belum login, redirect ke login page. TANPA menghancurkan sesi apapun.
 */
function authenticate_user(): array {
    if (!empty($GLOBALS['currentUser'])) {
        return $GLOBALS['currentUser'];
    }

    if (empty($_SESSION['user_id'])) {
        // Debug: log ke error_log agar bisa dilihat di Hostinger
        error_log("AUTH_DEBUG: SESSION kosong saat akses " . ($_SERVER['REQUEST_URI'] ?? '???') . " | session_id=" . session_id() . " | cookie=" . ($_COOKIE[session_name()] ?? 'NONE'));
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

// Jalankan autentikasi saat middleware di-include
$user = authenticate_user();
