<?php
// public/pekerja/index.php

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['pekerja']);

$pdo = get_db_connection();

// 1. Dapatkan pekerjaan yang terikat ke akun pekerja ini (Anti-IDOR)
$stmtJob = $pdo->prepare("SELECT * FROM pekerjaan WHERE user_id = :user_id LIMIT 1");
$stmtJob->execute([':user_id' => $user['id']]);
$pekerjaan = $stmtJob->fetch();

$barangList = [];
if ($pekerjaan) {
    // 2. Ambil daftar barang untuk pekerjaan ini beserta foto-fotonya
    $stmtBarang = $pdo->prepare("
        SELECT b.*, 
               (SELECT COUNT(*) FROM foto_barang fb WHERE fb.barang_id = b.id) AS total_foto
        FROM barang b
        WHERE b.pekerjaan_id = :pekerjaan_id
        ORDER BY b.created_at DESC
    ");
    $stmtBarang->execute([':pekerjaan_id' => $pekerjaan['id']]);
    $barangList = $stmtBarang->fetchAll();

    // Map foto per barang
    foreach ($barangList as &$b) {
        $stmtFoto = $pdo->prepare("SELECT * FROM foto_barang WHERE barang_id = :barang_id ORDER BY id ASC");
        $stmtFoto->execute([':barang_id' => $b['id']]);
        $b['foto'] = $stmtFoto->fetchAll();
    }
}

$successMsg = $_GET['success'] ?? null;
$errorMsg = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pekerjaan Saya - Rekapan Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header / Navbar -->
    <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-30 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center border border-indigo-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-white tracking-tight">Sistem Rekapan Barang</span>
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-medium">Pekerja</span>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-slate-400">Halo, <strong class="text-white"><?= e($user['username']) ?></strong></span>
                <a href="/auth/logout.php" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <?php if ($successMsg): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span><?= e($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?= e($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$pekerjaan): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 p-6 rounded-2xl text-center">
                <svg class="w-12 h-12 text-amber-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h3 class="text-lg font-bold text-amber-200">Belum Ada Pekerjaan Terkait</h3>
                <p class="text-sm mt-1 text-amber-400/80">Akun Anda belum di-assign ke pekerjaan apa pun oleh Superadmin. Silakan hubungi koordinator/superadmin.</p>
            </div>
        <?php else: ?>
            <!-- Header Pekerjaan Saya -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
                <div>
                    <span class="text-xs uppercase font-semibold text-indigo-400 tracking-wider">Pekerjaan Terhubung</span>
                    <h1 class="text-2xl font-extrabold text-white mt-1"><?= e($pekerjaan['nama_pekerjaan']) ?></h1>
                    <p class="text-xs text-slate-400 mt-1">ID Pekerjaan: #<?= $pekerjaan['id'] ?> • Total Barang: <?= count($barangList) ?> item</p>
                </div>
                <div>
                    <a href="/pekerja/tambah_barang.php" class="inline-flex items-center space-x-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow-lg shadow-indigo-600/30 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Tambah Barang Baru</span>
                    </a>
                </div>
            </div>

            <!-- List Barang -->
            <?php if (empty($barangList)): ?>
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-12 text-center">
                    <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <h3 class="text-lg font-bold text-white">Belum Ada Rekapan Barang</h3>
                    <p class="text-slate-400 text-sm mt-1 mb-6">Klik tombol di bawah untuk mulai mencatat barang event pertama Anda.</p>
                    <a href="/pekerja/tambah_barang.php" class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Input Barang Sekarang</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($barangList as $item): ?>
                        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg hover:border-slate-700 transition-all flex flex-col">
                            <!-- Image Gallery Preview -->
                            <div class="relative bg-slate-950 h-52 overflow-hidden group">
                                <?php if (!empty($item['foto'])): ?>
                                    <img src="<?= e($item['foto'][0]['file_path']) ?>" alt="Foto Barang" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    <?php if (count($item['foto']) > 1): ?>
                                        <span class="absolute top-3 right-3 bg-slate-900/90 text-white text-xs font-semibold px-2.5 py-1 rounded-lg border border-slate-700/60 backdrop-blur-md">
                                            +<?= count($item['foto']) - 1 ?> Foto
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-600 bg-slate-900/40">
                                        <svg class="w-10 h-10 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-xs">Tanpa Foto</span>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute bottom-3 left-3 bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-lg shadow">
                                    Qty: <?= e($item['kuantitas']) ?>
                                </div>
                            </div>

                            <!-- Details -->
                            <div class="p-5 flex-grow flex flex-col justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-200 line-clamp-3 mb-2">
                                        <?= e($item['keterangan']) ?>
                                    </h4>
                                    <p class="text-xs text-slate-500">
                                        Diinput: <?= date('d M Y H:i', strtotime($item['created_at'])) ?>
                                    </p>
                                </div>

                                <div class="mt-4 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                                    <span class="text-xs text-slate-400"><?= count($item['foto']) ?> Foto terlampir</span>
                                    <a href="/pekerja/edit_barang.php?id=<?= $item['id'] ?>" 
                                       class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 border border-indigo-500/30 transition-all flex items-center space-x-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span>Edit Barang</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Activity Tracker JS for Idle Timeout -->
    <script src="/assets/js/activity-tracker.js"></script>
</body>
</html>
