<?php
// config/csrf.php

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
    // Cek jika POST payload terpotong/kosong karena melebihi post_max_size PHP
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        http_response_code(413);
        die("⚠️ Ukuran total foto/data yang diunggah melebihi batas maksimal server (post_max_size). Silakan kurangi jumlah/ukuran foto dan coba lagi.");
    }

    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        die("⚠️ Permintaan ditolak karena token keamanan CSRF tidak cocok. Silakan refresh halaman dan coba kembali.");
    }
}

