<?php
// public/admin/dashboard.php

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['admin', 'superadmin']);

$pdo = get_db_connection();

$filterPekerjaanId = (int)($_GET['pekerjaan_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Dapatkan daftar semua pekerjaan untuk dropdown filter
$stmtAllJobs = $pdo->query("SELECT p.*, u.username AS nama_pekerja FROM pekerjaan p JOIN users u ON p.user_id = u.id ORDER BY p.nama_pekerjaan ASC");
$allJobs = $stmtAllJobs->fetchAll();

// Query Barang Read-only dengan Filter
$sql = "
    SELECT b.*, p.nama_pekerjaan, u.username AS nama_pekerja
    FROM barang b
    JOIN pekerjaan p ON b.pekerjaan_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($filterPekerjaanId > 0) {
    $sql .= " AND b.pekerjaan_id = :pekerjaan_id";
    $params[':pekerjaan_id'] = $filterPekerjaanId;
}

if (!empty($search)) {
    $sql .= " AND (b.nama_barang LIKE :search OR b.keterangan LIKE :search OR p.nama_pekerjaan LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$sql .= " ORDER BY p.nama_pekerjaan ASC, b.created_at DESC";

$stmtBarang = $pdo->prepare($sql);
$stmtBarang->execute($params);
$barangList = $stmtBarang->fetchAll();

// Map foto per barang
foreach ($barangList as &$b) {
    $stmtFoto = $pdo->prepare("SELECT * FROM foto_barang WHERE barang_id = :barang_id ORDER BY id ASC");
    $stmtFoto->execute([':barang_id' => $b['id']]);
    $b['foto'] = $stmtFoto->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Rekapan Barang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header / Navbar -->
    <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-30 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center border border-blue-500/30 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <div class="truncate">
                    <span class="font-bold text-white tracking-tight">Sistem Rekapan Barang</span>
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-medium hidden sm:inline-block">Dashboard Admin (Read-Only)</span>
                </div>
            </div>

            <!-- Desktop Header Right -->
            <div class="hidden md:flex items-center space-x-4">
                <?php if ($user['role'] === 'superadmin'): ?>
                    <a href="/superadmin/kelola_pekerjaan.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold underline">Mode Superadmin</a>
                <?php endif; ?>
                <span class="text-sm text-slate-400">Halo, <strong class="text-white"><?= e($user['username']) ?></strong></span>
                <a href="/auth/logout.php" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
                    Logout
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="md:hidden flex items-center space-x-2">
                <button type="button" id="mobile-menu-btn" class="p-2 rounded-xl bg-slate-800 text-slate-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all" aria-label="Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Dropdown Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900/95 px-4 pt-3 pb-4 space-y-3 shadow-2xl">
            <div class="px-3 py-1 text-xs text-slate-400 flex items-center justify-between">
                <span>User: <strong class="text-white"><?= e($user['username']) ?></strong></span>
                <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20 font-semibold text-[10px]"><?= strtoupper($user['role']) ?></span>
            </div>
            <?php if ($user['role'] === 'superadmin'): ?>
                <a href="/superadmin/kelola_pekerjaan.php" class="block px-3 py-2 rounded-xl text-sm font-semibold text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 transition-all">Panel Superadmin</a>
            <?php endif; ?>
            <a href="/auth/logout.php" class="block w-full text-center px-3 py-2.5 rounded-xl text-xs font-semibold bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">Logout</a>
        </div>
    </nav>

    <!-- Main Body -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Monitoring Data Rekapan Event</h1>
                <p class="text-slate-400 text-sm mt-1">Lihat seluruh data barang yang diinput oleh tim pekerja (Mode Pemantauan / Read-Only).</p>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-xl">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Filter Pekerjaan</label>
                    <select name="pekerjaan_id" class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">-- Semua Pekerjaan --</option>
                        <?php foreach ($allJobs as $job): ?>
                            <option value="<?= $job['id'] ?>" <?= $filterPekerjaanId === $job['id'] ? 'selected' : '' ?>>
                                <?= e($job['nama_pekerjaan']) ?> (Pekerja: <?= e($job['nama_pekerja']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Cari Barang / Event</label>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Nama barang / keterangan..." 
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex items-stretch md:items-end space-x-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm transition-all shadow-lg shadow-blue-600/30">
                        Terapkan Filter
                    </button>
                    <?php if ($filterPekerjaanId > 0 || !empty($search)): ?>
                        <a href="/admin/dashboard.php" class="py-2.5 px-4 rounded-xl border border-slate-700 text-slate-400 hover:text-white text-sm font-semibold transition-all">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel / Cards Data Barang -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="font-bold text-white">Daftar Barang Event (<?= count($barangList) ?> record)</h3>
                <span class="text-xs text-amber-400 bg-amber-500/10 px-3 py-1 rounded-full border border-amber-500/20 font-medium">Read-Only Mode</span>
            </div>

            <?php if (empty($barangList)): ?>
                <div class="p-12 text-center text-slate-500">
                    Tidak ditemukan data barang yang sesuai dengan filter.
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3.5 font-semibold">Pekerjaan / PJ</th>
                                <th class="px-6 py-3.5 font-semibold">Nama Barang</th>
                                <th class="px-6 py-3.5 font-semibold">Qty</th>
                                <th class="px-6 py-3.5 font-semibold">Keterangan Detail</th>
                                <th class="px-6 py-3.5 font-semibold">Foto Terlampir</th>
                                <th class="px-6 py-3.5 font-semibold">Tanggal Input</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($barangList as $item): ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-white"><?= e($item['nama_pekerjaan']) ?></div>
                                        <div class="text-xs text-slate-400">PJ: <?= e($item['nama_pekerja']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-white">
                                        <?= e(!empty($item['nama_barang']) ? $item['nama_barang'] : '-') ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-blue-400">
                                        <?= e($item['kuantitas']) ?>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs truncate">
                                        <?= e($item['keterangan']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if (!empty($item['foto'])): ?>
                                            <div class="flex items-center space-x-2">
                                                <?php foreach (array_slice($item['foto'], 0, 3) as $f): ?>
                                                    <a href="<?= e($f['file_path']) ?>" target="_blank" class="block w-10 h-10 rounded-lg overflow-hidden border border-slate-700 hover:border-blue-500 transition-all">
                                                        <img src="<?= e($f['file_path']) ?>" class="w-full h-full object-cover">
                                                    </a>
                                                <?php endforeach; ?>
                                                <?php if (count($item['foto']) > 3): ?>
                                                    <span class="text-xs text-slate-400">+<?= count($item['foto']) - 3 ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-600">Tidak ada foto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-slate-400">
                                        <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden divide-y divide-slate-800/80">
                    <?php foreach ($barangList as $item): ?>
                        <div class="p-4 space-y-3 hover:bg-slate-800/20 transition-colors">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="text-xs text-indigo-400 font-semibold uppercase block"><?= e($item['nama_pekerjaan']) ?></span>
                                    <h4 class="font-bold text-white text-base mt-0.5"><?= e(!empty($item['nama_barang']) ? $item['nama_barang'] : '-') ?></h4>
                                    <p class="text-xs text-slate-400">PJ Input: <?= e($item['nama_pekerja']) ?></p>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20 shrink-0">
                                    <?= e($item['kuantitas']) ?>
                                </span>
                            </div>

                            <div class="text-xs text-slate-300 bg-slate-950/60 p-2.5 rounded-lg border border-slate-800">
                                <?= e($item['keterangan']) ?>
                            </div>

                            <?php if (!empty($item['foto'])): ?>
                                <div class="space-y-1">
                                    <span class="text-[11px] text-slate-400 font-medium">Foto Terlampir:</span>
                                    <div class="flex items-center space-x-2">
                                        <?php foreach (array_slice($item['foto'], 0, 4) as $f): ?>
                                            <a href="<?= e($f['file_path']) ?>" target="_blank" class="block w-12 h-12 rounded-lg overflow-hidden border border-slate-700">
                                                <img src="<?= e($f['file_path']) ?>" class="w-full h-full object-cover">
                                            </a>
                                        <?php endforeach; ?>
                                        <?php if (count($item['foto']) > 4): ?>
                                            <span class="text-xs text-slate-400 font-semibold bg-slate-800 px-2 py-1.5 rounded-lg border border-slate-700">
                                                +<?= count($item['foto']) - 4 ?> foto
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="text-[11px] text-slate-500 text-right pt-1">
                                Input: <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

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
