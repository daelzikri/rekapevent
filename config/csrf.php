<?php
// config/csrf.php
// CSRF Protection DINONAKTIFKAN SEMENTARA untuk debugging session logout.
// Fungsi-fungsi tetap ada agar kode tidak error, tetapi validate_csrf_or_die() tidak memblokir apapun.

require_once __DIR__ . '/helpers.php';
init_session();

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function get_csrf_input(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function validate_csrf_or_die(): void {
    // SEMENTARA DINONAKTIFKAN — tidak memblokir apapun.
    // Akan diaktifkan kembali setelah bug session terselesaikan.
    return;
}
