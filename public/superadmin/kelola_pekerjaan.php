<?php
// public/superadmin/kelola_pekerjaan.php

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['superadmin']);

$pdo = get_db_connection();
$errorMsg = null;
$successMsg = $_GET['success'] ?? null;

// POST Handlers (Create Job, Edit Job Assignment, Delete Job)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_job') {
        $namaPekerjaan = trim($_POST['nama_pekerjaan'] ?? '');
        $userId = (int)($_POST['user_id'] ?? 0);

        if (empty($namaPekerjaan) || $userId <= 0) {
            $errorMsg = "Nama pekerjaan dan akun pekerja penanggung jawab wajib diisi.";
        } else {
            // Cek apakah akun pekerja ini sudah punya pekerjaan terikat
            $stmtCek = $pdo->prepare("SELECT id FROM pekerjaan WHERE user_id = :user_id");
            $stmtCek->execute([':user_id' => $userId]);
            if ($stmtCek->fetch()) {
                $errorMsg = "Akun pekerja yang dipilih sudah terikat ke pekerjaan lain. 1 akun pekerja hanya bisa memegang 1 pekerjaan.";
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO pekerjaan (nama_pekerjaan, user_id, dibuat_oleh) VALUES (:nama, :user_id, :dibuat_oleh)");
                $stmtIns->execute([
                    ':nama'        => $namaPekerjaan,
                    ':user_id'     => $userId,
                    ':dibuat_oleh' => $user['id']
                ]);
                $newJobId = (int)$pdo->lastInsertId();

                log_audit($pdo, $user['id'], 'CREATE_PEKERJAAN', "Membuat pekerjaan baru ID #{$newJobId} ('{$namaPekerjaan}').");
                redirect('/superadmin/kelola_pekerjaan.php?success=Pekerjaan+baru+berhasil+dibuat.');
            }
        }
    } elseif ($action === 'edit_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $namaPekerjaan = trim($_POST['nama_pekerjaan'] ?? '');
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($jobId <= 0 || empty($namaPekerjaan) || $userId <= 0) {
            $errorMsg = "Data form tidak lengkap.";
        } else {
            // Cek jika user_id diganti, pastikan user_id baru belum memegang pekerjaan lain
            $stmtCek = $pdo->prepare("SELECT id FROM pekerjaan WHERE user_id = :user_id AND id != :job_id");
            $stmtCek->execute([':user_id' => $userId, ':job_id' => $jobId]);
            if ($stmtCek->fetch()) {
                $errorMsg = "Akun pekerja yang dipilih sudah terikat ke pekerjaan lain.";
            } else {
                $stmtUpd = $pdo->prepare("UPDATE pekerjaan SET nama_pekerjaan = :nama, user_id = :user_id, updated_at = NOW() WHERE id = :id");
                $stmtUpd->execute([
                    ':nama'    => $namaPekerjaan,
                    ':user_id' => $userId,
                    ':id'      => $jobId
                ]);

                log_audit($pdo, $user['id'], 'EDIT_PEKERJAAN', "Mengubah pekerjaan ID #{$jobId}.");
                redirect('/superadmin/kelola_pekerjaan.php?success=Data+pekerjaan+berhasil+diperbarui.');
            }
        }
    } elseif ($action === 'delete_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM pekerjaan WHERE id = :id");
            $stmtDel->execute([':id' => $jobId]);

            log_audit($pdo, $user['id'], 'DELETE_PEKERJAAN', "Menghapus pekerjaan ID #{$jobId}.");
            redirect('/superadmin/kelola_pekerjaan.php?success=Pekerjaan+berhasil+dihapus.');
        }
    }
}

// Ambil daftar pekerja yang tersedia
$stmtPekerja = $pdo->query("SELECT id, username FROM users WHERE role = 'pekerja' ORDER BY username ASC");
$pekerjaList = $stmtPekerja->fetchAll();

// Ambil daftar seluruh pekerjaan beserta pekerja terikat dan statistik barang
$stmtJobs = $pdo->query("
    SELECT p.*, u.username AS nama_pekerja, uCreator.username AS nama_pembuat,
           (SELECT COUNT(*) FROM barang b WHERE b.pekerjaan_id = p.id) AS total_barang
    FROM pekerjaan p
    JOIN users u ON p.user_id = u.id
    JOIN users uCreator ON p.dibuat_oleh = uCreator.id
    ORDER BY p.created_at DESC
");
$jobsList = $stmtJobs->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pekerjaan - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header / Navbar Superadmin -->
    <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-30 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center border border-purple-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-white tracking-tight">Superadmin Panel</span>
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-medium">Kelola Pekerjaan</span>
                </div>
            </div>

            <div class="flex items-center space-x-6">
                <a href="/superadmin/kelola_pekerjaan.php" class="text-sm font-bold text-purple-400">Kelola Pekerjaan</a>
                <a href="/superadmin/kelola_akun.php" class="text-sm text-slate-400 hover:text-white transition-all">Kelola Akun</a>
                <a href="/superadmin/export.php" class="text-sm text-slate-400 hover:text-white transition-all">Export Excel/Word</a>
                <a href="/admin/dashboard.php" class="text-sm text-slate-400 hover:text-white transition-all">Dashboard View</a>
                <a href="/auth/logout.php" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <?php if ($successMsg): ?>
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

        <!-- Form Tambah Pekerjaan Baru -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">Buat Pekerjaan Event Baru & Assign Akun Pekerja</h3>
            <form method="POST" action="/superadmin/kelola_pekerjaan.php" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <?= get_csrf_input() ?>
                <input type="hidden" name="action" value="create_job">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Nama Pekerjaan / Proyek Event</label>
                    <input type="text" name="nama_pekerjaan" required placeholder="Contoh: Wedding Reception Hotel Mulia"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Assign ke Akun Pekerja (1 Pekerja = 1 Job)</label>
                    <select name="user_id" required class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">-- Pilih Akun Pekerja --</option>
                        <?php foreach ($pekerjaList as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-semibold text-sm shadow-lg shadow-purple-600/30 transition-all">
                        Buat & Assign Pekerjaan
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Daftar Pekerjaan -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="font-bold text-white">Daftar Pekerjaan Event Active (<?= count($jobsList) ?>)</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5 font-semibold">Nama Pekerjaan</th>
                            <th class="px-6 py-3.5 font-semibold">PJ (Akun Pekerja)</th>
                            <th class="px-6 py-3.5 font-semibold">Jumlah Barang</th>
                            <th class="px-6 py-3.5 font-semibold">Dibuat Oleh</th>
                            <th class="px-6 py-3.5 font-semibold text-right">Aksi Superadmin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($jobsList as $job): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 font-bold text-white">
                                    <?= e($job['nama_pekerjaan']) ?>
                                    <div class="text-xs font-normal text-slate-500">ID: #<?= $job['id'] ?> • <?= date('d M Y H:i', strtotime($job['created_at'])) ?></div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="text-xs px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-300 border border-indigo-500/30 font-semibold">
                                        <?= e($job['nama_pekerja']) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-4 font-bold text-purple-400">
                                    <?= $job['total_barang'] ?> Barang
                                </td>

                                <td class="px-6 py-4 text-xs text-slate-400">
                                    <?= e($job['nama_pembuat']) ?>
                                </td>

                                <td class="px-6 py-4 text-right space-x-2">
                                    <button type="button" onclick="promptEditJob(<?= $job['id'] ?>, '<?= e(addslashes($job['nama_pekerjaan'])) ?>', <?= $job['user_id'] ?>)"
                                        class="px-2.5 py-1 text-xs rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 font-semibold">
                                        Edit
                                    </button>

                                    <form method="POST" action="/superadmin/kelola_pekerjaan.php" class="inline" onsubmit="return confirm('Hapus pekerjaan <?= e(addslashes($job['nama_pekerjaan'])) ?> beserta seluruh barangnya?');">
                                        <?= get_csrf_input() ?>
                                        <input type="hidden" name="action" value="delete_job">
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="px-2.5 py-1 text-xs rounded bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 font-semibold">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Hidden Edit Job Modal Form -->
    <form id="editJobForm" method="POST" action="/superadmin/kelola_pekerjaan.php" class="hidden">
        <?= get_csrf_input() ?>
        <input type="hidden" name="action" value="edit_job">
        <input type="hidden" id="editJobId" name="job_id" value="">
        <input type="hidden" id="editJobNama" name="nama_pekerjaan" value="">
        <input type="hidden" id="editJobUser" name="user_id" value="">
    </form>

    <script>
        function promptEditJob(jobId, namaCurrent, userIdCurrent) {
            const newNama = prompt("Ubah Nama Pekerjaan:", namaCurrent);
            if (newNama && newNama.trim().length > 0) {
                document.getElementById('editJobId').value = jobId;
                document.getElementById('editJobNama').value = newNama.trim();
                document.getElementById('editJobUser').value = userIdCurrent;
                document.getElementById('editJobForm').submit();
            }
        }
    </script>
    <script src="/assets/js/activity-tracker.js"></script>
</body>
</html>
