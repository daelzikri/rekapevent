<?php
// public/pekerja/tambah_barang.php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/upload_helper.php';

$user = require_role(['pekerja']);
$pdo = get_db_connection();

// 1. Dapatkan pekerjaan terikat milik pekerja (Anti-IDOR)
$stmtJob = $pdo->prepare("SELECT * FROM pekerjaan WHERE user_id = :user_id LIMIT 1");
$stmtJob->execute([':user_id' => $user['id']]);
$pekerjaan = $stmtJob->fetch();

if (!$pekerjaan) {
    redirect('/pekerja/index.php?error=Akun+Anda+belum+memiliki+pekerjaan+terikat.');
}

$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $kuantitas = (int)($_POST['kuantitas'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($kuantitas <= 0) {
        $errorMsg = "Kuantitas barang harus lebih dari 0.";
    } elseif (empty($keterangan)) {
        $errorMsg = "Keterangan/nama barang wajib diisi.";
    } else {
        // Insert record barang baru
        $stmtBarang = $pdo->prepare("INSERT INTO barang (pekerjaan_id, kuantitas, keterangan) VALUES (:pekerjaan_id, :kuantitas, :keterangan)");
        $stmtBarang->execute([
            ':pekerjaan_id' => $pekerjaan['id'],
            ':kuantitas'    => $kuantitas,
            ':keterangan'   => $keterangan
        ]);
        $barangId = (int)$pdo->lastInsertId();

        log_audit($pdo, $user['id'], 'TAMBAH_BARANG', "Menambahkan barang ID #{$barangId} ke pekerjaan ID #{$pekerjaan['id']}.");

        // Penanganan upload foto
        if (!empty($_FILES['foto'])) {
            $uploadResult = handle_photo_uploads($_FILES['foto'], $pekerjaan['id'], $barangId, $pdo);
            if (!empty($uploadResult['errors'])) {
                $errorMsg = implode(" ", $uploadResult['errors']);
            }
        }

        if (!$errorMsg) {
            redirect('/pekerja/index.php?success=Barang+berhasil+ditambahkan.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Barang - Pekerjaan Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-30 backdrop-blur-md">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="/pekerja/index.php" class="p-2 rounded-lg bg-slate-800 text-slate-300 hover:text-white transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <span class="font-bold text-white tracking-tight">Tambah Barang Baru</span>
            </div>
            <span class="text-xs text-slate-400 font-medium"><?= e($pekerjaan['nama_pekerjaan']) ?></span>
        </div>
    </nav>

    <main class="flex-grow max-w-4xl w-full mx-auto px-4 sm:px-6 py-8">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8">
            <h2 class="text-xl font-bold text-white mb-6">Form Input Barang Event</h2>

            <?php if ($errorMsg): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-start space-x-3">
                    <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span><?= e($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Status Info Konversi Gambar HEIC -->
            <div id="heic-status-notice" class="hidden mb-6 p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-sm flex items-center space-x-3">
                <svg class="w-5 h-5 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span id="heic-status-text">Mengonversi format foto HEIC ke JPEG... Mohon tunggu sebentar.</span>
            </div>

            <form id="tambahBarangForm" method="POST" action="/pekerja/tambah_barang.php" enctype="multipart/form-data" class="space-y-6">
                <?= get_csrf_input() ?>

                <div>
                    <label for="kuantitas" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Kuantitas / Jumlah (Unit/Pcs)</label>
                    <input type="number" id="kuantitas" name="kuantitas" min="1" value="1" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>

                <div>
                    <label for="keterangan" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Keterangan / Deskripsi Barang</label>
                    <textarea id="keterangan" name="keterangan" rows="4" required
                        placeholder="Contoh: Kursi Futura Cover Putih Pita Gold - Kondisi Baik"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Upload Foto Barang (HEIC, PNG, JPG, JPEG)</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-700 border-dashed rounded-xl bg-slate-950 hover:border-indigo-500 transition-all">
                        <div class="space-y-2 text-center">
                            <svg class="mx-auto h-12 w-12 text-slate-500" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-slate-400 justify-center">
                                <label for="foto" class="relative cursor-pointer bg-slate-900 rounded-md font-semibold text-indigo-400 hover:text-indigo-300 px-3 py-1 border border-indigo-500/30">
                                    <span>Pilih File Gambar</span>
                                    <input id="foto" name="foto[]" type="file" multiple accept="image/*,.heic,.heif" class="sr-only">
                                </label>
                            </div>
                            <p class="text-xs text-slate-500">Mendukung format HEIC (iPhone), PNG, JPG, JPEG hingga 10MB per file. Bisa pilih lebih dari 1 foto.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-4 pt-4 border-t border-slate-800">
                    <a href="/pekerja/index.php" class="px-5 py-2.5 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800 text-sm font-semibold transition-all">Batal</a>
                    <button id="btnSubmit" type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-lg shadow-indigo-600/30 transition-all">
                        Simpan Barang
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Automatic Browser Client-Side HEIC to JPEG Conversion via heic2any
        document.getElementById('foto').addEventListener('change', async function(e) {
            const files = Array.from(e.target.files);
            if (!files.length) return;

            const notice = document.getElementById('heic-status-notice');
            const noticeText = document.getElementById('heic-status-text');
            const btnSubmit = document.getElementById('btnSubmit');

            const container = new DataTransfer();
            let hasHeic = false;

            for (let file of files) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (ext === 'heic' || ext === 'heif' || file.type.includes('heic') || file.type.includes('heif')) {
                    hasHeic = true;
                    break;
                }
            }

            if (hasHeic && typeof heic2any !== 'undefined') {
                notice.classList.remove('hidden');
                btnSubmit.disabled = true;
                btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const ext = file.name.split('.').pop().toLowerCase();

                    if (ext === 'heic' || ext === 'heif' || file.type.includes('heic') || file.type.includes('heif')) {
                        noticeText.textContent = `Mengonversi foto HEIC ${i+1}/${files.length}... Mohon tunggu sebentar.`;
                        try {
                            const resBlob = await heic2any({
                                blob: file,
                                toType: 'image/jpeg',
                                quality: 0.85
                            });
                            const resultBlob = Array.isArray(resBlob) ? resBlob[0] : resBlob;
                            const convertedFile = new File([resultBlob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
                            container.items.add(convertedFile);
                        } catch (err) {
                            console.warn('Client HEIC conversion failed, fallback to original:', err);
                            container.items.add(file);
                        }
                    } else {
                        container.items.add(file);
                    }
                }

                e.target.files = container.files;
                notice.classList.add('hidden');
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>
    <script src="/assets/js/activity-tracker.js"></script>
</body>
</html>
