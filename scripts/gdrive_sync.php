<?php
// scripts/gdrive_sync.php

/**
 * Entry Point Cron Job Background Sync Foto ke Google Drive
 * 
 * Cara Eksekusi:
 * 1. Via CLI Terminal / Cron cPanel:
 *    php /path/to/rekapevent/scripts/gdrive_sync.php
 * 
 * 2. Via HTTP Request (Fallback Hosting):
 *    GET https://domain-anda.com/scripts/gdrive_sync.php?key=REKAP_GDRIVE_CRON_SECRET_2026
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google_drive.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../services/DriveUploader.php';

$isCli = (php_sapi_name() === 'cli');

// Validation Proteksi HTTP Cron Trigger dengan Timing-Attack-Safe comparison
if (!$isCli) {
    $suppliedKey = $_GET['key'] ?? '';
    $expectedKey = defined('GDRIVE_CRON_SECRET_KEY') ? GDRIVE_CRON_SECRET_KEY : '';

    if (empty($suppliedKey) || empty($expectedKey) || !hash_equals($expectedKey, $suppliedKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'error',
            'code'    => 403,
            'message' => '403 Forbidden - Akses cron job HTTP ditolak. Parameter key tidak valid.'
        ]);
        exit;
    }
}

// Inisialisasi Koneksi Database PDO
$pdo = get_db_connection();

// Inisialisasi Service DriveUploader
$uploader = new DriveUploader();

$startTime = microtime(true);
$res = $uploader->retryPending($pdo, 20);
$executionTime = round(microtime(true) - $startTime, 2);

$modeText = $isCli ? 'CLI' : 'HTTP';
$timestamp = date('Y-m-d H:i:s');

// Format Pesan Log Ringkas
$logMessage = "[{$timestamp}] Mode: {$modeText} | Processed: {$res['processed']} | Success: {$res['success']} | Failed: {$res['failed']} | Duration: {$executionTime}s\n";
if (!empty($res['messages'])) {
    foreach ($res['messages'] as $msg) {
        $logMessage .= "  - {$msg}\n";
    }
}

// Tulis Log ke file logs/gdrive_sync.log (Append mode)
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/gdrive_sync.log';
@file_put_contents($logFile, $logMessage, FILE_APPEND);

// Response Output
if ($isCli) {
    echo $logMessage;
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'         => 'success',
        'mode'           => 'HTTP',
        'processed'      => $res['processed'],
        'success'        => $res['success'],
        'failed'         => $res['failed'],
        'duration_sec'   => $executionTime,
        'messages'       => $res['messages'],
        'timestamp'      => $timestamp
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
