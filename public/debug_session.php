<?php
// public/debug_session.php — Halaman diagnosa sesi. HAPUS SETELAH DEBUGGING SELESAI.

require_once __DIR__ . '/../config/helpers.php';
init_session();

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Session Debug - Rekap Event</h2>";
echo "<pre>";
echo "session_status(): " . session_status() . " (1=disabled, 2=active)\n";
echo "session_name(): " . session_name() . "\n";
echo "session_id(): " . session_id() . "\n";
echo "session_save_path(): " . session_save_path() . "\n";
echo "\n--- \$_SESSION ---\n";
print_r($_SESSION);
echo "\n--- \$_COOKIE ---\n";
print_r($_COOKIE);
echo "\n--- REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . " ---\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'off') . "\n";
echo "HTTP_X_FORWARDED_PROTO: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'N/A') . "\n";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";
echo "\n--- PHP Info ---\n";
echo "PHP Version: " . phpversion() . "\n";
echo "session.save_handler: " . ini_get('session.save_handler') . "\n";
echo "session.save_path (ini): " . ini_get('session.save_path') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";

// Test: set a value and see if it persists
if (!isset($_SESSION['debug_counter'])) {
    $_SESSION['debug_counter'] = 0;
}
$_SESSION['debug_counter']++;
echo "\ndebug_counter (should increase on refresh): " . $_SESSION['debug_counter'] . "\n";

echo "</pre>";

echo '<hr>';
echo '<h3>Test POST Form</h3>';
echo '<form method="POST" action="">';
echo '<input type="hidden" name="test_field" value="hello">';
echo '<button type="submit" style="padding:10px 20px; background:blue; color:white; border:none; cursor:pointer;">Test Submit POST</button>';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div style="background:green; color:white; padding:10px; margin:10px 0;">POST diterima! test_field = ' . htmlspecialchars($_POST['test_field'] ?? 'KOSONG') . '</div>';
    echo '<div>Session user_id setelah POST: ' . ($_SESSION['user_id'] ?? 'KOSONG/HILANG') . '</div>';
}
