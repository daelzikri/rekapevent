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
        @session_write_close();
    }
    header("Location: " . $url);
    exit;
}

/**
 * Tampilkan halaman sukses interaktif + auto redirect via JS (mencegah cookie drop pada HTTP 302 POST redirect di LiteSpeed/Hostinger)
 */
function response_success_redirect(string $targetUrl, string $message = 'Data berhasil disimpan.'): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Berhasil Disimpan</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-8 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto border border-emerald-500/30">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="text-sm text-slate-400">Mengalihkan halaman dalam 1 detik...</p>
            <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>" 
               class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-all shadow-lg shadow-indigo-600/30">
                Klik Di Sini Jika Tidak Teralihkan
            </a>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = <?= json_encode($targetUrl) ?>;
            }, 700);
        </script>
    </body>
    </html>
    <?php
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
