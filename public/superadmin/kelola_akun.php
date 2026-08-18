<?php
// public/superadmin/kelola_akun.php

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['superadmin']);

$pdo = get_db_connection();
$errorMsg = null;
$successMsg = $_GET['success'] ?? null;

// Handler POST (Create, Edit, Delete, Reset Password, Unlock Account)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'pekerja';

        if (empty($username) || empty($password)) {
            $errorMsg = "Username dan password wajib diisi.";
        } elseif (!in_array($role, ['admin', 'pekerja'], true)) {
            $errorMsg = "Role tidak valid.";
        } else {
            // Cek ketersediaan username
            $stmtCek = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmtCek->execute([':username' => $username]);
            if ($stmtCek->fetch()) {
                $errorMsg = "Username '{$username}' sudah digunakan.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtIns = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:username, :hash, :role)");
                $stmtIns->execute([':username' => $username, ':hash' => $hash, ':role' => $role]);
                $newId = (int)$pdo->lastInsertId();

                log_audit($pdo, $user['id'], 'CREATE_USER', "Membuat akun baru ID #{$newId} ({$username}, role: {$role}).");
                redirect('/superadmin/kelola_akun.php?success=Akun+baru+berhasil+dibuat.');
            }
        }
    } elseif ($action === 'reset_password') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($targetUserId <= 0 || empty($newPassword)) {
            $errorMsg = "ID User dan password baru wajib diisi.";
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtReset = $pdo->prepare("UPDATE users SET password_hash = :hash, failed_login_count = 0, locked_until = NULL WHERE id = :id");
            $stmtReset->execute([':hash' => $hash, ':id' => $targetUserId]);

            log_audit($pdo, $user['id'], 'RESET_PASSWORD', "Mereset password untuk user ID #{$targetUserId}.");
            redirect('/superadmin/kelola_akun.php?success=Password+berhasil+direset.');
        }
    } elseif ($action === 'unlock_account') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId > 0) {
            $stmtUnl = $pdo->prepare("UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = :id");
            $stmtUnl->execute([':id' => $targetUserId]);

            log_audit($pdo, $user['id'], 'UNLOCK_ACCOUNT', "Membuka kunci akun ID #{$targetUserId}.");
            redirect('/superadmin/kelola_akun.php?success=Akun+berhasil+di-unlock.');
        }
    } elseif ($action === 'reset_session') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId > 0) {
            $stmtResSess = $pdo->prepare("UPDATE users SET session_token = NULL, last_activity_at = NULL WHERE id = :id");
            $stmtResSess->execute([':id' => $targetUserId]);

            log_audit($pdo, $user['id'], 'RESET_SESSION', "Mereset sesi aktif akun ID #{$targetUserId}.");
            redirect('/superadmin/kelola_akun.php?success=Sesi+aktif+akun+berhasil+direset.');
        }
    } elseif ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId === $user['id']) {
            $errorMsg = "Anda tidak bisa menghapus akun Anda sendiri.";
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = :id AND role != 'superadmin'");
            $stmtDel->execute([':id' => $targetUserId]);

            log_audit($pdo, $user['id'], 'DELETE_USER', "Menghapus akun ID #{$targetUserId}.");
            redirect('/superadmin/kelola_akun.php?success=Akun+berhasil+dihapus.');
        }
    }
}

// Ambil daftar seluruh user (kecuali superadmin utama jika diinginkan, atau tampilkan semua)
$stmtUsers = $pdo->query("
    SELECT u.*, 
           (SELECT nama_pekerjaan FROM pekerjaan p WHERE p.user_id = u.id LIMIT 1) AS pekerjaan_terikat
    FROM users u
    ORDER BY u.role DESC, u.username ASC
");
$userList = $stmtUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header / Navbar Superadmin -->
    <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-30 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center border border-purple-500/30 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div class="truncate">
                    <span class="font-bold text-white tracking-tight">Superadmin Panel</span>
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-medium hidden sm:inline-block">Kelola Akun</span>
                </div>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="/superadmin/kelola_pekerjaan.php" class="text-sm text-slate-400 hover:text-white transition-all">Kelola Pekerjaan</a>
                <a href="/superadmin/kelola_akun.php" class="text-sm font-bold text-purple-400">Kelola Akun</a>
                <a href="/superadmin/export.php" class="text-sm text-slate-400 hover:text-white transition-all">Export Excel/Word</a>
                <a href="/admin/dashboard.php" class="text-sm text-slate-400 hover:text-white transition-all">Dashboard View</a>
                <a href="/auth/logout.php" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">Logout</a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="md:hidden flex items-center">
                <button type="button" id="mobile-menu-btn" class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all" aria-label="Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Dropdown Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900/95 px-4 pt-3 pb-4 space-y-2 shadow-2xl">
            <a href="/superadmin/kelola_pekerjaan.php" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-all">Kelola Pekerjaan</a>
            <a href="/superadmin/kelola_akun.php" class="block px-3 py-2.5 rounded-xl text-sm font-bold text-purple-400 bg-purple-500/10 border border-purple-500/20 transition-all">Kelola Akun</a>
            <a href="/superadmin/export.php" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-all">Export Excel/Word</a>
            <a href="/admin/dashboard.php" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-all">Dashboard View</a>
            <a href="/auth/logout.php" class="block w-full text-center mt-3 px-3 py-2.5 rounded-xl text-xs font-semibold bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <?php if ($successMsg): ?>
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span><?= e($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start space-x-3">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= e($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Buat Akun Baru -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">Buat Akun Pengguna Baru (Admin / Pekerja)</h3>
            <form method="POST" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-stretch md:items-end">
                <?= get_csrf_input() ?>
                <input type="hidden" name="action" value="create_user">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" required placeholder="Contoh: pekerja3"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Password Awal</label>
                    <input type="password" name="password" required placeholder="••••••••"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Role Akun</label>
                    <select name="role" class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="pekerja">Akun Pekerja (Input Barang)</option>
                        <option value="admin">Akun Admin (Read-Only Viewer)</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold text-sm shadow-lg shadow-purple-600/30 transition-all">
                        Buat Akun
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel / Cards Daftar Akun -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="font-bold text-white">Daftar Akun Pengguna System (<?= count($userList) ?>)</h3>
            </div>

            <!-- Desktop View: Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5 font-semibold">Username</th>
                            <th class="px-6 py-3.5 font-semibold">Role</th>
                            <th class="px-6 py-3.5 font-semibold">Pekerjaan Terikat</th>
                            <th class="px-6 py-3.5 font-semibold">Status Login / Lock</th>
                            <th class="px-6 py-3.5 font-semibold text-right">Aksi Superadmin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($userList as $u): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 font-bold text-white">
                                    <?= e($u['username']) ?>
                                    <?php if ($u['id'] === $user['id']): ?>
                                        <span class="ml-2 text-[10px] bg-purple-500/20 text-purple-300 px-2 py-0.5 rounded border border-purple-500/30">Anda</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4">
                                    <?php if ($u['role'] === 'superadmin'): ?>
                                        <span class="text-xs px-2.5 py-1 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/30 font-semibold">Superadmin</span>
                                    <?php elseif ($u['role'] === 'admin'): ?>
                                        <span class="text-xs px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/30 font-semibold">Admin</span>
                                    <?php else: ?>
                                        <span class="text-xs px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 font-semibold">Pekerja</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4 text-xs text-slate-400">
                                    <?= $u['pekerjaan_terikat'] ? e($u['pekerjaan_terikat']) : '<span class="text-slate-600">-</span>' ?>
                                </td>

                                <td class="px-6 py-4 text-xs">
                                    <?php 
                                    $isLocked = (!empty($u['locked_until']) && strtotime($u['locked_until']) > time());
                                    $isActive = (!empty($u['session_token']) && !empty($u['last_activity_at']) && (time() - strtotime($u['last_activity_at'])) <= 1800);
                                    ?>
                                    <?php if ($isLocked): ?>
                                        <span class="text-rose-400 font-bold bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">Terkunci (5x Gagal)</span>
                                    <?php elseif ($isActive): ?>
                                        <span class="text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">Sesi Aktif</span>
                                    <?php else: ?>
                                        <span class="text-slate-500">Offline</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-4 text-right space-x-2">
                                    <?php if ($isLocked): ?>
                                        <form method="POST" action="" class="inline">
                                            <?= get_csrf_input() ?>
                                            <input type="hidden" name="action" value="unlock_account">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 font-semibold">Unlock</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isActive): ?>
                                        <form method="POST" action="" class="inline" onsubmit="return confirm('Keluarkan sesi aktif akun <?= e($u['username']) ?>?');">
                                            <?= get_csrf_input() ?>
                                            <input type="hidden" name="action" value="reset_session">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded bg-purple-500/20 text-purple-300 hover:bg-purple-500/30 font-semibold" title="Keluarkan / Hapus sesi aktif akun ini">Reset Sesi</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Trigger Modal Reset Password -->
                                    <button type="button" onclick="promptResetPassword(<?= $u['id'] ?>, '<?= e($u['username']) ?>')"
                                        class="px-2.5 py-1 text-xs rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 font-semibold">
                                        Reset Pass
                                    </button>

                                    <?php if ($u['role'] !== 'superadmin' && $u['id'] !== $user['id']): ?>
                                        <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus akun <?= e($u['username']) ?>?');">
                                            <?= get_csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 font-semibold">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Cards -->
            <div class="md:hidden divide-y divide-slate-800/80">
                <?php foreach ($userList as $u): ?>
                    <div class="p-4 space-y-3 hover:bg-slate-800/20 transition-colors">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-bold text-white text-base">
                                    <?= e($u['username']) ?>
                                    <?php if ($u['id'] === $user['id']): ?>
                                        <span class="ml-1 text-[10px] bg-purple-500/20 text-purple-300 px-1.5 py-0.5 rounded border border-purple-500/30">Anda</span>
                                    <?php endif; ?>
                                </h4>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Job Terikat: <?= $u['pekerjaan_terikat'] ? e($u['pekerjaan_terikat']) : '<span class="text-slate-600">Tidak Ada</span>' ?>
                                </p>
                            </div>
                            <div>
                                <?php if ($u['role'] === 'superadmin'): ?>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/30 font-semibold">Superadmin</span>
                                <?php elseif ($u['role'] === 'admin'): ?>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/30 font-semibold">Admin</span>
                                <?php else: ?>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/30 font-semibold">Pekerja</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 text-xs">
                            <span class="text-slate-400">Status:</span>
                            <?php 
                            $isLocked = (!empty($u['locked_until']) && strtotime($u['locked_until']) > time());
                            $isActive = (!empty($u['session_token']) && !empty($u['last_activity_at']) && (time() - strtotime($u['last_activity_at'])) <= 1800);
                            ?>
                            <?php if ($isLocked): ?>
                                <span class="text-rose-400 font-bold bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">Terkunci (5x Gagal)</span>
                            <?php elseif ($isActive): ?>
                                <span class="text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">Sesi Aktif</span>
                            <?php else: ?>
                                <span class="text-slate-500">Offline</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-slate-800/60">
                            <?php if ($isLocked): ?>
                                <form method="POST" action="" class="inline">
                                    <?= get_csrf_input() ?>
                                    <input type="hidden" name="action" value="unlock_account">
                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 text-xs rounded-lg bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 font-semibold border border-amber-500/30">Unlock</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($isActive): ?>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Keluarkan sesi aktif akun <?= e($u['username']) ?>?');">
                                    <?= get_csrf_input() ?>
                                    <input type="hidden" name="action" value="reset_session">
                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 text-xs rounded-lg bg-purple-500/20 text-purple-300 hover:bg-purple-500/30 font-semibold border border-purple-500/30">Reset Sesi</button>
                                </form>
                            <?php endif; ?>

                            <button type="button" onclick="promptResetPassword(<?= $u['id'] ?>, '<?= e($u['username']) ?>')"
                                class="px-2.5 py-1 text-xs rounded-lg bg-slate-800 text-slate-300 hover:text-white font-semibold border border-slate-700">
                                Reset Pass
                            </button>

                            <?php if ($u['role'] !== 'superadmin' && $u['id'] !== $user['id']): ?>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus akun <?= e($u['username']) ?>?');">
                                    <?= get_csrf_input() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1 text-xs rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 font-semibold border border-rose-500/20">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <!-- Hidden Reset Password Form -->
    <form id="resetPassForm" method="POST" action="" class="hidden">
        <?= get_csrf_input() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" id="resetTargetId" name="target_user_id" value="">
        <input type="hidden" id="resetNewPass" name="new_password" value="">
    </form>

    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        function promptResetPassword(userId, username) {
            const pass = prompt(`Masukkan password baru untuk akun '${username}':`);
            if (pass && pass.trim().length > 0) {
                document.getElementById('resetTargetId').value = userId;
                document.getElementById('resetNewPass').value = pass.trim();
                document.getElementById('resetPassForm').submit();
            }
        }
    </script>

</body>
</html>
