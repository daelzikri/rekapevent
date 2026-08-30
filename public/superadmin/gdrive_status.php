<?php
// public/superadmin/gdrive_status.php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../services/DriveUploader.php';

$user = require_role(['superadmin']);
$pdo = get_db_connection();

$errorMsg = null;
$successMsg = $_GET['success'] ?? null;

// Tangani aksi POST (Retry Single, Retry All Failed, Trigger Manual Sync)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();
    $action = $_POST['action'] ?? '';

    if ($action === 'retry_single') {
        $fotoId = (int)($_POST['foto_id'] ?? 0);
        if ($fotoId > 0) {
            $stmtReset = $pdo->prepare("UPDATE foto_barang SET gdrive_status = 'pending', gdrive_retry_count = 0 WHERE id = :id");
            $stmtReset->execute([':id' => $fotoId]);
            log_audit($pdo, $user['id'], 'RETRY_GDRIVE_SINGLE', "Reset status gdrive_status = pending untuk foto ID #{$fotoId}.");
            redirect('/superadmin/gdrive_status.php?success=Status+foto+berhasil+direset+ke+pending.');
        }
    } elseif ($action === 'retry_all_failed') {
        $stmtResetAll = $pdo->query("UPDATE foto_barang SET gdrive_status = 'pending', gdrive_retry_count = 0 WHERE gdrive_status = 'failed'");
        $count = $stmtResetAll->rowCount();
        log_audit($pdo, $user['id'], 'RETRY_GDRIVE_ALL', "Reset status gdrive_status = pending untuk {$count} foto failed.");
        redirect("/superadmin/gdrive_status.php?success={$count}+foto+berhasil+direset+ke+pending.");
    } elseif ($action === 'trigger_sync_now') {
        $uploader = new DriveUploader();
        if (!$uploader->isReady()) {
            $errorMsg = "Google Drive API belum terkonfigurasi. Pastikan Client ID, Client Secret, dan Refresh Token telah diisi di config/google_drive.php.";
        } else {
            $res = $uploader->retryPending($pdo, 20);
            $msg = "Manual Sync Selesai! Processed: {$res['processed']}, Success: {$res['success']}, Failed: {$res['failed']}.";
            log_audit($pdo, $user['id'], 'TRIGGER_GDRIVE_SYNC', $msg);
            redirect("/superadmin/gdrive_status.php?success=" . urlencode($msg));
        }
    }
}

// 1. Cek Status Kredensial Drive API
$uploaderCheck = new DriveUploader();
$isDriveReady = $uploaderCheck->isReady();

// 2. Ambil Statistik Jumlah Foto Per Status
$stmtStat = $pdo->query("
    SELECT 
        COUNT(*) AS total_foto,
        SUM(CASE WHEN gdrive_status = 'pending' THEN 1 ELSE 0 END) AS count_pending,
        SUM(CASE WHEN gdrive_status = 'success' THEN 1 ELSE 0 END) AS count_success,
        SUM(CASE WHEN gdrive_status = 'failed' THEN 1 ELSE 0 END) AS count_failed
    FROM foto_barang
");
$stats = $stmtStat->fetch() ?: ['total_foto' => 0, 'count_pending' => 0, 'count_success' => 0, 'count_failed' => 0];

// 3. Ambil Daftar Foto Berstatus Failed (Membutuhkan Intervensi)
$stmtFailed = $pdo->query("
    SELECT f.*, b.nama_barang, b.kuantitas, p.nama_pekerjaan, u.username AS nama_pekerja
    FROM foto_barang f
    JOIN barang b ON f.barang_id = b.id
    JOIN pekerjaan p ON b.pekerjaan_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE f.gdrive_status = 'failed' OR f.gdrive_retry_count >= 5
    ORDER BY f.id DESC
");
$failedList = $stmtFailed->fetchAll();

// 4. Ambil 15 Foto Terbaru untuk Tabel Log Pemantauan
$stmtRecent = $pdo->query("
    SELECT f.*, b.nama_barang, p.nama_pekerjaan
    FROM foto_barang f
    JOIN barang b ON f.barang_id = b.id
    JOIN pekerjaan p ON b.pekerjaan_id = p.id
    ORDER BY f.id DESC
    LIMIT 15
");
$recentList = $stmtRecent->fetchAll();

// Read log file jika ada
$logFile = __DIR__ . '/../../logs/gdrive_sync.log';
$logSummaryText = '';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $logSummaryText = implode("", array_slice($lines, -15)); // 15 baris log terakhir
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Backup Google Drive - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header / Navbar Superadmin -->
    <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-30 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center border border-blue-500/30 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </div>
                <div class="truncate">
                    <span class="font-bold text-white tracking-tight">Superadmin Panel</span>
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-medium hidden sm:inline-block">Status Backup Drive</span>
                </div>
            </div>

            <!-- Desktop Links -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="/superadmin/kelola_pekerjaan.php" class="text-sm text-slate-400 hover:text-white transition-all">Kelola Pekerjaan</a>
                <a href="/superadmin/kelola_akun.php" class="text-sm text-slate-400 hover:text-white transition-all">Kelola Akun</a>
                <a href="/superadmin/export.php" class="text-sm text-slate-400 hover:text-white transition-all">Export Excel/Word</a>
                <a href="/superadmin/gdrive_status.php" class="text-sm font-bold text-blue-400">Backup Drive</a>
                <a href="/admin/dashboard.php" class="text-sm text-slate-400 hover:text-white transition-all">Dashboard View</a>
                <a href="/auth/logout.php" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">Logout</a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="md:hidden flex items-center">
                <button type="button" id="mobile-menu-btn" class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all" aria-label="Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Dropdown Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900/95 px-4 pt-3 pb-4 space-y-2 shadow-2xl">
            <a href="/superadmin/kelola_pekerjaan.php" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-all">Kelola Pekerjaan</a>
            <a href="/superadmin/kelola_akun.php" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-all">Kelola Akun</a>
            <a href="/superadmin/export.php" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-all">Export Excel/Word</a>
            <a href="/superadmin/gdrive_status.php" class="block px-3 py-2.5 rounded-xl text-sm font-bold text-blue-400 bg-blue-500/10 border border-blue-500/20 transition-all">Backup Drive</a>
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

        <!-- Banner Connection Status -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-xl border shrink-0 <?= $isDriveReady ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border-amber-500/30' ?>">
                    <?= $isDriveReady ? '✓' : '!' ?>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Status Konfigurasi Google Drive API</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-medium <?= $isDriveReady ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' ?>">
                            <?= $isDriveReady ? 'Terhubung (Ready)' : 'Belum Konfigurasi' ?>
                        </span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">
                        <?= $isDriveReady 
                            ? 'Google Drive API siap digunakan. Foto akan disinkronkan secara otomatis oleh background cron job.' 
                            : 'Kredensial OAuth belum lengkap di <code>config/google_drive.php</code>. Jalankan script setup <code>php scripts/gdrive_get_refresh_token.php</code>.' ?>
                    </p>
                </div>
            </div>

            <?php if ($isDriveReady): ?>
                <form method="POST" action="" class="shrink-0 w-full md:w-auto">
                    <?= get_csrf_input() ?>
                    <input type="hidden" name="action" value="trigger_sync_now">
                    <button type="submit" class="w-full md:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs shadow-lg shadow-blue-600/30 transition-all flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Jalankan Sync Sekarang</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Total Foto -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Foto Server</div>
                <div class="text-2xl font-bold text-white mt-2"><?= number_format((int)$stats['total_foto']) ?></div>
                <div class="text-[11px] text-slate-500 mt-1">Foto tersimpan di server lokal</div>
            </div>

            <!-- Success -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <div class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Tersinkron (Success)</div>
                <div class="text-2xl font-bold text-emerald-400 mt-2"><?= number_format((int)$stats['count_success']) ?></div>
                <div class="text-[11px] text-slate-500 mt-1">Berhasil ter-backup di Drive</div>
            </div>

            <!-- Pending -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <div class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Antrean (Pending)</div>
                <div class="text-2xl font-bold text-amber-400 mt-2"><?= number_format((int)$stats['count_pending']) ?></div>
                <div class="text-[11px] text-slate-500 mt-1">Menunggu giliran cron job</div>
            </div>

            <!-- Failed -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl">
                <div class="text-xs font-semibold text-rose-400 uppercase tracking-wider">Gagal (Failed 5x)</div>
                <div class="text-2xl font-bold text-rose-400 mt-2"><?= number_format((int)$stats['count_failed']) ?></div>
                <div class="text-[11px] text-slate-500 mt-1">Perlu intervensi manual</div>
            </div>
        </div>

        <!-- Daftar Foto Gagal (Needs Attention) -->
        <?php if (!empty($failedList)): ?>
            <div class="bg-slate-900 border border-rose-500/30 rounded-2xl overflow-hidden shadow-2xl">
                <div class="px-6 py-4 bg-rose-500/10 border-b border-rose-500/20 flex items-center justify-between">
                    <div class="flex items-center space-x-2 text-rose-300 font-bold text-base">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Daftar Foto Gagal Sinkronisasi Permanen (<?= count($failedList) ?>)</span>
                    </div>

                    <form method="POST" action="" onsubmit="return confirm('Reset semua foto gagal agar dicoba lagi oleh cron job?');">
                        <?= get_csrf_input() ?>
                        <input type="hidden" name="action" value="retry_all_failed">
                        <button type="submit" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition-all shadow">
                            Retry Semua Foto Gagal
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/80 text-xs uppercase text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Foto</th>
                                <th class="px-6 py-3 font-semibold">Detail Barang & Event</th>
                                <th class="px-6 py-3 font-semibold">Percobaan</th>
                                <th class="px-6 py-3 font-semibold">Waktu Terakhir</th>
                                <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($failedList as $f): ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-6 py-3">
                                        <img src="<?= e($f['file_path']) ?>" alt="Foto" class="w-12 h-12 rounded-lg object-cover border border-slate-700">
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="font-bold text-white"><?= e($f['nama_barang']) ?></div>
                                        <div class="text-xs text-slate-400"><?= e($f['nama_pekerjaan']) ?> • PJ: <?= e($f['nama_pekerja']) ?></div>
                                    </td>
                                    <td class="px-6 py-3 font-semibold text-rose-400">
                                        <?= $f['gdrive_retry_count'] ?> / 5 kali
                                    </td>
                                    <td class="px-6 py-3 text-xs text-slate-400">
                                        <?= $f['gdrive_last_attempt_at'] ? date('d M Y H:i', strtotime($f['gdrive_last_attempt_at'])) : '-' ?>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <form method="POST" action="" class="inline">
                                            <?= get_csrf_input() ?>
                                            <input type="hidden" name="action" value="retry_single">
                                            <input type="hidden" name="foto_id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow transition-all">
                                                Retry Manual
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabel 15 Foto Terbaru & Status Backup -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="font-bold text-white">Status Backup Foto Terbaru</h3>
                <span class="text-xs text-slate-400">Menampilkan 15 foto terakhir</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5 font-semibold">Foto Server</th>
                            <th class="px-6 py-3.5 font-semibold">Barang & Pekerjaan</th>
                            <th class="px-6 py-3.5 font-semibold">Status Drive</th>
                            <th class="px-6 py-3.5 font-semibold">File ID Drive</th>
                            <th class="px-6 py-3.5 font-semibold text-right">Link Google Drive</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($recentList as $f): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-3.5 flex items-center space-x-3">
                                    <img src="<?= e($f['file_path']) ?>" alt="Foto" class="w-10 h-10 rounded-lg object-cover border border-slate-700">
                                    <div class="text-xs truncate max-w-[150px]" title="<?= e($f['nama_file_server']) ?>">
                                        <?= e($f['nama_file_server']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="font-bold text-white text-xs"><?= e($f['nama_barang']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= e($f['nama_pekerjaan']) ?></div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <?php if ($f['gdrive_status'] === 'success'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            ✓ Success
                                        </span>
                                    <?php elseif ($f['gdrive_status'] === 'pending'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                            ⏳ Pending (Try: <?= $f['gdrive_retry_count'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                            ✕ Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5 font-mono text-xs text-slate-400">
                                    <?= e($f['gdrive_file_id'] ?: '-') ?>
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <?php if (!empty($f['gdrive_view_link'])): ?>
                                        <a href="<?= e($f['gdrive_view_link']) ?>" target="_blank" rel="noopener noreferrer" 
                                           class="inline-flex items-center space-x-1 text-xs text-blue-400 hover:text-blue-300 font-semibold hover:underline">
                                            <span>Buka di Drive</span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Log Eksekusi Cron Job Terakhir -->
        <?php if (!empty($logSummaryText)): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white mb-3 flex items-center justify-between">
                    <span>Log Eksekusi Cron Job Terbaru (<code>logs/gdrive_sync.log</code>)</span>
                </h3>
                <pre class="bg-slate-950 p-4 rounded-xl text-xs font-mono text-slate-300 border border-slate-800 overflow-x-auto max-h-48 leading-relaxed"><?= e($logSummaryText) ?></pre>
            </div>
        <?php endif; ?>

    </main>

    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
