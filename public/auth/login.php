<?php
// public/auth/login.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/csrf.php';

init_session();

// Redirect jika sudah login
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'superadmin') redirect('/superadmin/kelola_pekerjaan.php');
    if ($_SESSION['role'] === 'admin') redirect('/admin/dashboard.php');
    if ($_SESSION['role'] === 'pekerja') redirect('/pekerja/index.php');
}

$errorMessage = $_GET['error'] ?? null;
$successMessage = $_GET['success'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errorMessage = "Username dan password wajib diisi.";
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $errorMessage = "Username atau password salah.";
        } else {
            // Cek Lockout
            if (!empty($user['locked_until'])) {
                $lockedTime = strtotime($user['locked_until']);
                if (time() < $lockedTime) {
                    $sisaMenit = ceil(($lockedTime - time()) / 60);
                    $errorMessage = "Akun terkunci. Coba lagi dalam {$sisaMenit} menit.";
                }
            }

            if (!$errorMessage) {
                if (!password_verify($password, $user['password_hash'])) {
                    $newFailedCount = $user['failed_login_count'] + 1;
                    $lockedUntil = null;
                    if ($newFailedCount >= 5) {
                        $lockedUntil = date('Y-m-d H:i:s', time() + (15 * 60));
                    }
                    $upd = $pdo->prepare("UPDATE users SET failed_login_count = :cnt, locked_until = :lock WHERE id = :id");
                    $upd->execute([':cnt' => $newFailedCount, ':lock' => $lockedUntil, ':id' => $user['id']]);
                    log_audit($pdo, $user['id'], 'LOGIN_FAILED', "Gagal login. Percobaan ke-{$newFailedCount}.");

                    if ($newFailedCount >= 5) {
                        $errorMessage = "Akun dikunci 15 menit karena 5x gagal login.";
                    } else {
                        $sisaCoba = 5 - $newFailedCount;
                        $errorMessage = "Username atau password salah. Sisa: {$sisaCoba}x.";
                    }
                } else {
                    // LOGIN BERHASIL
                    $updSuccess = $pdo->prepare("UPDATE users SET last_activity_at = NOW(), failed_login_count = 0, locked_until = NULL WHERE id = :id");
                    $updSuccess->execute([':id' => $user['id']]);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    // Debug: pastikan session tersimpan
                    error_log("LOGIN_DEBUG: Login OK user_id={$user['id']} session_id=" . session_id() . " session_name=" . session_name());

                    log_audit($pdo, $user['id'], 'LOGIN_SUCCESS', "Login sebagai {$user['role']}.");

                    if ($user['role'] === 'superadmin') {
                        redirect('/superadmin/kelola_pekerjaan.php');
                    } elseif ($user['role'] === 'admin') {
                        redirect('/admin/dashboard.php');
                    } else {
                        redirect('/pekerja/index.php');
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Rekapan Barang Event</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-800/90 backdrop-blur-md rounded-2xl border border-slate-700/60 shadow-2xl p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/20 text-indigo-400 mb-4 border border-indigo-500/30">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Sistem Rekapan Barang</h1>
            <p class="text-sm text-slate-400 mt-1">Manajemen Inventaris Event & Pekerjaan</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start space-x-3">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= e($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-start space-x-3">
                <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span><?= e($successMessage) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5">
            <?= get_csrf_input() ?>
            <div>
                <label for="username" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username"
                    class="w-full px-4 py-3 rounded-xl bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    placeholder="Masukkan username Anda">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                    class="w-full px-4 py-3 rounded-xl bg-slate-900/80 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    placeholder="••••••••">
            </div>

            <button type="submit"
                class="w-full py-3.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow-lg shadow-indigo-600/30 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition-all duration-200">
                Masuk ke Sistem
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <p class="text-xs text-slate-500">
                Akses Terbatas — Hanya untuk staf dan tim event resmi.
            </p>
        </div>
    </div>
</body>
</html>
