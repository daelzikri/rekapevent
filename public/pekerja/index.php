<?php
// public/pekerja/index.php

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['pekerja']);

$pdo = get_db_connection();

// 1. Dapatkan pekerjaan yang terikat ke akun pekerja ini (Anti-IDOR)
$stmtJob = $pdo->prepare("SELECT * FROM pekerjaan WHERE user_id = :user_id LIMIT 1");
$stmtJob->execute([':user_id' => $user['id']]);
$pekerjaan = $stmtJob->fetch();

$search = trim($_GET['search'] ?? '');
$barangList = [];

// Handle Delete Barang (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_barang') {
    validate_csrf_or_die();
    $barangId = (int)($_POST['barang_id'] ?? 0);
    if ($barangId > 0 && $pekerjaan) {
        $stmtDel = $pdo->prepare("DELETE FROM barang WHERE id = :id AND pekerjaan_id = :pekerjaan_id");
        $stmtDel->execute([':id' => $barangId, ':pekerjaan_id' => $pekerjaan['id']]);
        
        log_audit($pdo, $user['id'], 'DELETE_BARANG', "Menghapus barang ID #{$barangId} dari pekerjaan ID #{$pekerjaan['id']}.");
        redirect('/pekerja/index.php?success=Data+barang+berhasil+dihapus.');
    }
}

if ($pekerjaan) {
    // 2. Ambil daftar barang untuk pekerjaan ini
    $sql = "
        SELECT b.*, 
               (SELECT COUNT(*) FROM foto_barang fb WHERE fb.barang_id = b.id) AS total_foto
        FROM barang b
        WHERE b.pekerjaan_id = :pekerjaan_id
    ";
    $params = [':pekerjaan_id' => $pekerjaan['id']];

    if (!empty($search)) {
        $sql .= " AND (b.nama_barang LIKE :search OR b.keterangan LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $sql .= " ORDER BY b.created_at DESC";

    $stmtBarang = $pdo->prepare($sql);
    $stmtBarang->execute($params);
    $barangList = $stmtBarang->fetchAll();

    // Map foto per barang
    foreach ($barangList as &$b) {
        $stmtFoto = $pdo->prepare("SELECT * FROM foto_barang WHERE barang_id = :barang_id ORDER BY id ASC");
        $stmtFoto->execute([':barang_id' => $b['id']]);
        $b['foto'] = $stmtFoto->fetchAll();
    }
    unset($b);
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
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-medium">Pekerja Panel</span>
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
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <?php if ($successMsg): ?>
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span><?= e($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
                <div>
                    <span class="text-xs uppercase font-semibold text-indigo-400 tracking-wider">Pekerjaan Terhubung</span>
                    <h1 class="text-2xl font-extrabold text-white mt-1"><?= e($pekerjaan['nama_pekerjaan']) ?></h1>
                    <p class="text-xs text-slate-400 mt-1">ID Pekerjaan: #<?= $pekerjaan['id'] ?> • Total Barang: <strong class="text-indigo-400"><?= count($barangList) ?> item</strong></p>
                </div>
                <div>
                    <a href="/pekerja/tambah_barang.php" class="inline-flex items-center space-x-2 px-5 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-lg shadow-indigo-600/30 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Tambah Barang Baru</span>
                    </a>
                </div>
            </div>

            <!-- Bar Filter & Pencarian Barang -->
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-lg">
                <form method="GET" action="" class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="relative w-full sm:w-96">
                        <svg class="w-5 h-5 text-slate-500 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari nama barang / keterangan..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <button type="submit" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 border border-indigo-500/30 font-semibold text-xs transition-all">
                            Cari
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="/pekerja/index.php" class="px-3 py-2.5 rounded-xl bg-slate-800 text-slate-400 hover:text-white font-semibold text-xs transition-all">
                                Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabel List Barang (Bentuk List untuk Efisiensi Ratusan Barang) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="font-bold text-white text-base">Daftar Barang Event (<?= count($barangList) ?> record)</h3>
                    <span class="text-xs text-indigo-400 bg-indigo-500/10 px-3 py-1 rounded-full border border-indigo-500/20 font-medium">Tampilan List Ringkas</span>
                </div>

                <?php if (empty($barangList)): ?>
                    <div class="p-12 text-center text-slate-500 space-y-3">
                        <svg class="w-12 h-12 text-slate-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p>Tidak ada barang yang ditemukan.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-950/80 text-xs uppercase text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="px-5 py-3.5 font-semibold text-center w-12">No</th>
                                    <th class="px-5 py-3.5 font-semibold">Nama Barang</th>
                                    <th class="px-5 py-3.5 font-semibold text-center w-28">Qty</th>
                                    <th class="px-5 py-3.5 font-semibold">Keterangan</th>
                                    <th class="px-5 py-3.5 font-semibold">Foto Terlampir</th>
                                    <th class="px-5 py-3.5 font-semibold text-center w-36">Waktu Input</th>
                                    <th class="px-5 py-3.5 font-semibold text-right w-36">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <?php $no = 1; foreach ($barangList as $item): ?>
                                    <tr class="hover:bg-slate-800/40 transition-colors">
                                        <!-- No -->
                                        <td class="px-5 py-3.5 text-center font-medium text-slate-400 text-xs">
                                            <?= $no++ ?>
                                        </td>

                                        <!-- Nama Barang -->
                                        <td class="px-5 py-3.5 font-bold text-white">
                                            <?= e(!empty($item['nama_barang']) ? $item['nama_barang'] : '-') ?>
                                        </td>

                                        <!-- Qty -->
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="inline-block px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                                <?= e($item['kuantitas']) ?>
                                            </span>
                                        </td>

                                        <!-- Keterangan -->
                                        <td class="px-5 py-3.5 text-slate-300 max-w-xs text-xs">
                                            <?= e($item['keterangan']) ?>
                                        </td>

                                        <!-- Foto Terlampir -->
                                        <td class="px-5 py-3.5">
                                            <?php if (!empty($item['foto'])): ?>
                                                <div class="flex items-center space-x-1.5">
                                                    <?php foreach (array_slice($item['foto'], 0, 3) as $f): ?>
                                                        <a href="<?= e($f['file_path']) ?>" target="_blank" title="Lihat Foto Full" 
                                                           class="block w-9 h-9 rounded-lg overflow-hidden border border-slate-700 hover:border-indigo-500 transition-all shrink-0">
                                                            <img src="<?= e($f['file_path']) ?>" class="w-full h-full object-cover" alt="Foto">
                                                        </a>
                                                    <?php endforeach; ?>
                                                    <?php if (count($item['foto']) > 3): ?>
                                                        <span class="text-[11px] font-semibold text-slate-400 bg-slate-800 px-2 py-1 rounded-md border border-slate-700">
                                                            +<?= count($item['foto']) - 3 ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-600 italic">Tanpa Foto</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Tanggal Input -->
                                        <td class="px-5 py-3.5 text-center text-xs text-slate-400">
                                            <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                                        </td>

                                        <!-- Aksi Pekerja -->
                                        <td class="px-5 py-3.5 text-right space-x-2">
                                            <a href="/pekerja/edit_barang.php?id=<?= $item['id'] ?>" 
                                               class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 border border-indigo-500/30 transition-all">
                                                Edit
                                            </a>
                                            <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus barang ini?');">
                                                <?= get_csrf_input() ?>
                                                <input type="hidden" name="action" value="delete_barang">
                                                <input type="hidden" name="barang_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">
                                                    Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>

