<?php
// config/database.php

// =========================================================================
// ISIKAN KREDENSIAL DATABASE HOSTINGER ANDA DI BAWAH INI:
// =========================================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'u602243872_rekapevent');
define('DB_USER', 'u602243872_rekapevent');
define('DB_PASS', '@Rekap123'); 
// =========================================================================

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("
            <div style='font-family:sans-serif; padding:20px; background:#fef2f2; border:1px solid #fca5a5; border-radius:10px; color:#991b1b; max-width:600px; margin:50px auto;'>
                <h3 style='margin-top:0;'>⚠️ Koneksi Database Gagal</h3>
                <p><strong>Pesan Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <hr style='border:0; border-top:1px solid #fca5a5;'>
                <p style='font-size:14px;'><strong>Solusi:</strong> Buka file <code>config/database.php</code> di hosting Anda dan sesuaikan <code>DB_NAME</code>, <code>DB_USER</code>, dan <code>DB_PASS</code> dengan database Hostinger Anda (misal DB Name: <code>u602243872_rekapevent</code>).</p>
            </div>
        ");
    }
}
