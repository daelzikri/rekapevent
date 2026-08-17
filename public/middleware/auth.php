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

    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
        unset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['role'], $_SESSION['username']);
        redirect("/auth/login.php");
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, username, role, session_token, last_activity_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['role'], $_SESSION['username']);
        redirect("/auth/login.php?error=User+tidak+ditemukan.");
    }

    // 1. Cek Token Sesi — Jika token tidak cocok, artinya akun di-login dari tempat lain atau di-reset
    if (empty($user['session_token']) || $user['session_token'] !== $_SESSION['session_token']) {
        unset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['role'], $_SESSION['username']);
        redirect("/auth/login.php?error=Sesi+Anda+telah+diakhiri+atau+akun+di-login+dari+perangkat+lain.");
    }

    // 2. Cek Inactivity Timeout (15 Menit)
    $timeoutSeconds = 15 * 60;
    if (!empty($user['last_activity_at'])) {
        $lastActive = strtotime($user['last_activity_at']);
        if ((time() - $lastActive) > $timeoutSeconds) {
            // Hapus sesi di DB
            $updExp = $pdo->prepare("UPDATE users SET session_token = NULL, last_activity_at = NULL WHERE id = :id");
            $updExp->execute([':id' => $user['id']]);

            unset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['role'], $_SESSION['username']);
            redirect("/auth/login.php?error=Sesi+Anda+telah+berakhir+karena+15+menit+tidak+ada+aktivitas.");
        }
    }

    // Update last_activity_at jika sudah > 30 detik dari aktivitas terakhir
    if (empty($user['last_activity_at']) || (time() - strtotime($user['last_activity_at'])) > 30) {
        $updAct = $pdo->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = :id");
        $updAct->execute([':id' => $user['id']]);
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
