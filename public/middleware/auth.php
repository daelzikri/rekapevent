<?php
// public/middleware/auth.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie params
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Autentikasi Pengguna & Cek Single Session Token
 */
function authenticate_user(): array {
    if (empty($_SESSION['user_id']) || empty($_SESSION['token'])) {
        clear_session_and_redirect("/auth/login.php");
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, username, role, session_token, last_activity_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        clear_session_and_redirect("/auth/login.php?error=User+tidak+ditemukan");
    }

    // 1. Validasi Token Sesi Eksklusif (Single Session)
    if ($user['session_token'] !== $_SESSION['token']) {
        clear_session_and_redirect("/auth/login.php?error=Sesi+Anda+telah+diakhiri+karena+login+di+perangkat+lain.");
    }

    // 2. Validasi Inaktivitas Idle 30 Menit (1800 detik)
    if (!empty($user['last_activity_at'])) {
        $lastActivity = strtotime($user['last_activity_at']);
        if ((time() - $lastActivity) > 1800) { // 30 Menit
            // Clear session_token in DB
            $updateStmt = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);
            log_audit($pdo, $user['id'], 'SESSION_EXPIRED', 'Sesi berakhir otomatis karena 30 menit tidak ada aktivitas.');
            clear_session_and_redirect("/auth/login.php?error=Sesi+telah+berakhir+karena+30+menit+tidak+ada+aktivitas.");
        }
    }

    // 3. Update last_activity_at untuk request aktif
    $updateAct = $pdo->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = :id");
    $updateAct->execute([':id' => $user['id']]);

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
