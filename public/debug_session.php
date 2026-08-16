<?php
// public/debug_session.php — Halaman diagnosa sesi LENGKAP. HAPUS SETELAH DEBUGGING SELESAI.

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
init_session();

header('Content-Type: text/html; charset=utf-8');

// Aksi: clear old cookies
if (isset($_GET['clear_cookies'])) {
    // Hapus REKAPEVENT_SESSID cookie lama
    setcookie('REKAPEVENT_SESSID', '', time() - 3600, '/');
    // Hapus PHPSESSID
    setcookie('PHPSESSID', '', time() - 3600, '/');
    // Destroy current session
    $_SESSION = [];
    session_destroy();
    echo "<h2>Semua cookie sesi dihapus!</h2>";
    echo "<p><a href='/debug_session.php'>Kembali ke debug page</a></p>";
    echo "<p><a href='/auth/login.php'>Ke halaman login</a></p>";
    exit;
}

// Aksi: quick login test
if (isset($_GET['quick_login'])) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id, username, role FROM users LIMIT 1");
        $stmt->execute();
        $testUser = $stmt->fetch();
        if ($testUser) {
            $_SESSION['user_id'] = $testUser['id'];
            $_SESSION['username'] = $testUser['username'];
            $_SESSION['role'] = $testUser['role'];
            session_write_close();
            echo "<h2 style='color:green'>Quick login berhasil!</h2>";
            echo "<p>user_id={$testUser['id']}, username={$testUser['username']}, role={$testUser['role']}</p>";
            echo "<p>session_id: " . session_id() . "</p>";
            echo "<p><a href='/debug_session.php'>Cek sesi sekarang</a></p>";
            echo "<p><a href='/pekerja/index.php'>Coba buka halaman pekerja</a></p>";
            exit;
        }
    } catch (Exception $e) {
        echo "<h2 style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
        exit;
    }
}

echo "<h2>Session Debug - Rekap Event (v2)</h2>";
echo "<pre style='background:#222; color:#0f0; padding:15px; border-radius:8px; overflow-x:auto;'>";
echo "=== IDENTITAS SESI ===\n";
echo "session_status(): " . session_status() . " (2=active)\n";
echo "session_name(): " . session_name() . "\n";
echo "session_id(): " . session_id() . "\n";
echo "session_save_path(): " . session_save_path() . "\n";

echo "\n=== ISI \$_SESSION ===\n";
print_r($_SESSION);

echo "\n=== ISI \$_COOKIE ===\n";
print_r($_COOKIE);

echo "\n=== SERVER INFO ===\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'off') . "\n";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";

// Test: set a value and see if it persists
if (!isset($_SESSION['debug_counter'])) {
    $_SESSION['debug_counter'] = 0;
}
$_SESSION['debug_counter']++;
echo "\n=== TES PERSISTENSI ===\n";
echo "debug_counter: " . $_SESSION['debug_counter'] . " (harus naik tiap refresh)\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'TIDAK ADA') . "\n";
echo "username: " . ($_SESSION['username'] ?? 'TIDAK ADA') . "\n";
echo "role: " . ($_SESSION['role'] ?? 'TIDAK ADA') . "\n";

echo "</pre>";

// POST TEST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div style="background:green; color:white; padding:15px; margin:15px 0; border-radius:8px;">';
    echo '<h3>✅ POST BERHASIL DITERIMA</h3>';
    echo 'test_field = ' . htmlspecialchars($_POST['test_field'] ?? 'KOSONG') . '<br>';
    echo 'Session user_id setelah POST: <b>' . ($_SESSION['user_id'] ?? '❌ KOSONG/HILANG') . '</b><br>';
    echo 'Session debug_counter: ' . ($_SESSION['debug_counter'] ?? '???') . '<br>';
    echo 'session_id: ' . session_id() . '<br>';
    
    // Cek jika ada file uploaded
    if (!empty($_FILES['test_foto'])) {
        echo 'File upload diterima: ' . htmlspecialchars($_FILES['test_foto']['name'] ?? 'N/A') . ' (' . ($_FILES['test_foto']['size'] ?? 0) . ' bytes, error=' . ($_FILES['test_foto']['error'] ?? '?') . ')<br>';
    }
    echo '</div>';
}

echo '<hr>';
echo '<h3>🔧 Aksi Perbaikan</h3>';
echo '<p><a href="/debug_session.php?clear_cookies=1" style="background:red;color:white;padding:10px 20px;border-radius:5px;text-decoration:none;">🗑️ Hapus Semua Cookie Sesi Lama</a></p>';
echo '<p style="font-size:12px;color:gray;">Klik ini dulu untuk menghapus cookie REKAPEVENT_SESSID dan PHPSESSID yang bertabrakan. Lalu login ulang.</p>';

echo '<hr>';
echo '<h3>🔑 Quick Login Test (tanpa form)</h3>';
echo '<p><a href="/debug_session.php?quick_login=1" style="background:blue;color:white;padding:10px 20px;border-radius:5px;text-decoration:none;">Login Otomatis ke User Pertama</a></p>';

echo '<hr>';
echo '<h3>📝 Test POST Form Biasa</h3>';
echo '<form method="POST" action="">';
echo '<input type="hidden" name="test_field" value="hello_post">';
echo '<button type="submit" style="padding:10px 20px; background:#4F46E5; color:white; border:none; cursor:pointer; border-radius:5px;">Test Submit POST Biasa</button>';
echo '</form>';

echo '<hr>';
echo '<h3>📷 Test POST Form dengan File Upload (multipart)</h3>';
echo '<form method="POST" action="" enctype="multipart/form-data">';
echo '<input type="hidden" name="test_field" value="hello_multipart">';
echo '<input type="file" name="test_foto" style="margin:10px 0;"><br>';
echo '<button type="submit" style="padding:10px 20px; background:#7C3AED; color:white; border:none; cursor:pointer; border-radius:5px;">Test Submit POST Multipart</button>';
echo '</form>';
