<?php
// config/google_drive.php

/**
 * Konfigurasi Google Drive API OAuth2 (100% Gratis via Akun Gmail Biasa)
 * 
 * ⚠️ PENTING:
 * 1. Simpan Client ID, Client Secret, Refresh Token, dan Drive Root Folder ID Anda di file ini atau google_drive_credentials.php
 * 2. Jangan pernah meng-commit kredensial sensitif ke repositori publik.
 */

// Muat Autoloader Composer jika ada
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Muat file kredensial eksternal jika terpisah
$credentialsFile = __DIR__ . '/google_drive_credentials.php';
if (file_exists($credentialsFile)) {
    require_once $credentialsFile;
}

// Definisikan konstanta default jika belum didefinisikan di google_drive_credentials.php
if (!defined('GDRIVE_CLIENT_ID')) {
    define('GDRIVE_CLIENT_ID', ''); // Isikan Client ID dari Google Cloud Console
}

if (!defined('GDRIVE_CLIENT_SECRET')) {
    define('GDRIVE_CLIENT_SECRET', ''); // Isikan Client Secret
}

if (!defined('GDRIVE_REFRESH_TOKEN')) {
    define('GDRIVE_REFRESH_TOKEN', ''); // Isikan Refresh Token dari script scripts/gdrive_get_refresh_token.php
}

if (!defined('GDRIVE_ROOT_FOLDER_ID')) {
    define('GDRIVE_ROOT_FOLDER_ID', ''); // Isikan ID folder induk Google Drive (misal: "1A2b3C4d5E6f...")
}

if (!defined('GDRIVE_CRON_SECRET_KEY')) {
    define('GDRIVE_CRON_SECRET_KEY', 'REKAP_GDRIVE_CRON_SECRET_2026'); // Secret key untuk HTTP cron trigger
}

/**
 * Inisialisasi dan dapatkan instance Google\Service\Drive yang siap dipakai.
 * Mengembalikan NULL jika kredensial belum dikonfigurasi (Fail-Safe: sistem utama tetap jalan).
 *
 * @return \Google\Service\Drive|null
 */
function get_gdrive_service(): ?Google\Service\Drive {
    static $driveService = null;
    static $initialized = false;

    if ($initialized) {
        return $driveService;
    }
    $initialized = true;

    // Pastikan library Google API Client telah terinstall via Composer
    if (!class_exists('Google\Client') || !class_exists('Google\Service\Drive')) {
        error_log("Google Drive Error: Class Google\\Client atau Google\\Service\\Drive tidak ditemukan. Jalankan composer require google/apiclient.");
        return null;
    }

    // Pastikan kredensial utama telah diisi
    if (empty(GDRIVE_CLIENT_ID) || empty(GDRIVE_CLIENT_SECRET) || empty(GDRIVE_REFRESH_TOKEN)) {
        // Kredensial belum diset (silent fallback)
        return null;
    }

    try {
        $client = new Google\Client();
        $client->setClientId(GDRIVE_CLIENT_ID);
        $client->setClientSecret(GDRIVE_CLIENT_SECRET);
        $client->addScope(Google\Service\Drive::DRIVE_FILE);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        // Refresh access token menggunakan refresh token yang tersimpan
        $accessToken = $client->fetchAccessTokenWithRefreshToken(GDRIVE_REFRESH_TOKEN);

        if (isset($accessToken['error'])) {
            error_log("Google Drive OAuth Error: " . ($accessToken['error_description'] ?? $accessToken['error']));
            return null;
        }

        $driveService = new Google\Service\Drive($client);
        return $driveService;
    } catch (Throwable $t) {
        error_log("Google Drive Client Init Exception: " . $t->getMessage());
        return null;
    }
}
