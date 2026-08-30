<?php
// scripts/gdrive_get_refresh_token.php

/**
 * Script One-Time Setup OAuth 2.0 Google Drive
 * 
 * Script CLI ini digunakan untuk mendapatkan REFRESH TOKEN dari akun Gmail khusus Anda.
 * 
 * Cara menjalankan di CLI Terminal:
 * php scripts/gdrive_get_refresh_token.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Akses Ditolak: Script ini hanya dapat dijalankan melalui CLI Terminal.\n");
}

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    die("ERROR: Vendor autoloader tidak ditemukan. Jalankan 'composer require google/apiclient:^2.15' terlebih dahulu.\n");
}
require_once $vendorAutoload;

$configFile = __DIR__ . '/../config/google_drive.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

echo "=================================================================\n";
echo "    ONE-TIME SETUP OAUTH 2.0 REFRESH TOKEN GOOGLE DRIVE (GRATIS) \n";
echo "=================================================================\n\n";

$clientId = defined('GDRIVE_CLIENT_ID') ? GDRIVE_CLIENT_ID : '';
$clientSecret = defined('GDRIVE_CLIENT_SECRET') ? GDRIVE_CLIENT_SECRET : '';

if (empty($clientId)) {
    echo "Masukkan Google OAuth 2.0 Client ID: ";
    $clientId = trim(fgets(STDIN));
} else {
    echo "Menggunakan Client ID dari config: {$clientId}\n";
}

if (empty($clientSecret)) {
    echo "Masukkan Google OAuth 2.0 Client Secret: ";
    $clientSecret = trim(fgets(STDIN));
} else {
    echo "Menggunakan Client Secret dari config.\n";
}

if (empty($clientId) || empty($clientSecret)) {
    die("ERROR: Client ID dan Client Secret wajib diisi!\n");
}

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
// urn:ietf:wg:oauth:2.0:oob (Out-Of-Band manual copy-paste code)
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
$client->addScope('https://www.googleapis.com/auth/drive.file');
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->setIncludeGrantedScopes(true);

$authUrl = $client->createAuthUrl();

echo "\n-----------------------------------------------------------------\n";
echo "LANGKAH 1: Buka URL Otorisasi berikut di Browser Anda:\n";
echo "-----------------------------------------------------------------\n";
echo "\n" . $authUrl . "\n\n";
echo "-----------------------------------------------------------------\n";
echo "LANGKAH 2:\n";
echo "1. Login menggunakan Akun Gmail khusus sistem ini.\n";
echo "2. Jika muncul peringatan 'Google hasn't verified this app', klik 'Advanced' / 'Lanjutan' lalu klik 'Go to [App Name] (unsafe)'. (Aman karena Anda sendiri pembuatnya).\n";
echo "3. Berikan izin akses (Scope: drive.file).\n";
echo "4. Copy/salin Authorization Code yang ditampilkan oleh Google.\n";
echo "-----------------------------------------------------------------\n\n";

echo "Tempel (paste) Authorization Code di sini, lalu tekan ENTER:\n> ";
$authCode = trim(fgets(STDIN));

if (empty($authCode)) {
    die("\nERROR: Authorization Code tidak boleh kosong!\n");
}

echo "\nMenukar Authorization Code dengan Access & Refresh Token...\n";

try {
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

    if (isset($accessToken['error'])) {
        die("\nERROR GAGAL OAUTH: " . ($accessToken['error_description'] ?? $accessToken['error']) . "\n");
    }

    $refreshToken = $accessToken['refresh_token'] ?? null;

    if (empty($refreshToken)) {
        die("\n⚠️ PERINGATAN: Refresh token tidak ditemukan dalam respon Google.\n" .
            "Solusi: Buka kembali URL otorisasi, pastikan memasukkan prompt=consent dan pastikan akun Gmail sudah di-remove permissions sebelumnya dari https://myaccount.google.com/permissions.\n");
    }

    echo "\n=================================================================\n";
    echo "🎉 PROSES OTORISASI BERHASIL!\n";
    echo "=================================================================\n\n";
    echo "BERIKUT ADALAH REFRESH TOKEN ANDA:\n\n";
    echo ">>>  " . $refreshToken . "  <<<\n\n";
    echo "-----------------------------------------------------------------\n";
    echo "PETUNJUK SIMPAN MANUAL (DEMI KEAMANAN):\n";
    echo "1. Salin (copy) Refresh Token di atas.\n";
    echo "2. Buka file `config/google_drive_credentials.php` (atau `config/google_drive.php`).\n";
    echo "3. Isikan nilai konstanta:\n";
    echo "   define('GDRIVE_CLIENT_ID', '{$clientId}');\n";
    echo "   define('GDRIVE_CLIENT_SECRET', '{$clientSecret}');\n";
    echo "   define('GDRIVE_REFRESH_TOKEN', '{$refreshToken}');\n";
    echo "   define('GDRIVE_ROOT_FOLDER_ID', 'ID_FOLDER_INDUK_DRIVE_ANDA');\n";
    echo "-----------------------------------------------------------------\n\n";

} catch (Throwable $t) {
    die("\nEXCEPTION OAUTH: " . $t->getMessage() . "\n");
}
