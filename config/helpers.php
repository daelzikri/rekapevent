<?php
// config/helpers.php

/**
 * Escape string untuk mencegah XSS
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Inisialisasi Sesi PHP — Sesederhana mungkin, tanpa kustomisasi apapun.
 * Pendekatan: biarkan PHP/Hostinger mengurus semuanya, kita hanya panggil session_start().
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header("Location: " . $url);
    exit;
}

/**
 * Catat aksi ke audit_log (silent fail - jangan pernah crash user flow)
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
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
