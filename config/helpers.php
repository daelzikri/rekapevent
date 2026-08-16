<?php
// config/helpers.php

/**
 * Escape string untuk mencegah XSS
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Inisialisasi Sesi PHP dengan Parameter Cookie Aman & Mendukung Proxy/HTTPS
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = (
            (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
}


/**
 * Kirim respon JSON
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect ke URL
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Catat aksi ke audit_log
 */
function log_audit(PDO $pdo, ?int $userId, string $aksi, ?string $detail = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, aksi, detail, ip_address) VALUES (:user_id, :aksi, :detail, :ip)");
        $stmt->execute([
            ':user_id' => $userId,
            ':aksi'    => $aksi,
            ':detail'  => $detail,
            ':ip'      => $ip
        ]);
    } catch (Exception $e) {
        // Fallback silently if audit log insert fails to prevent breaking main user flow
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
