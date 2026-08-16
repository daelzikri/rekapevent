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
$namaBarangVal = '';
$kuantitasVal = '';
$keteranganVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();

    $namaBarangVal = trim($_POST['nama_barang'] ?? '');
    $kuantitasVal = trim($_POST['kuantitas'] ?? '');
    $keteranganVal = trim($_POST['keterangan'] ?? '');

    if (empty($namaBarangVal)) {
        $errorMsg = "Nama barang wajib diisi.";
    } elseif (empty($kuantitasVal)) {
        $errorMsg = "Kuantitas / jumlah barang wajib diisi.";
    } elseif (empty($keteranganVal)) {
        $errorMsg = "Keterangan / deskripsi barang wajib diisi.";
    } else {
        // Insert record barang baru (4 Input: nama_barang, kuantitas, keterangan, foto)
        $stmtBarang = $pdo->prepare("INSERT INTO barang (pekerjaan_id, nama_barang, kuantitas, keterangan) VALUES (:pekerjaan_id, :nama_barang, :kuantitas, :keterangan)");
        $stmtBarang->execute([
            ':pekerjaan_id' => $pekerjaan['id'],
            ':nama_barang'  => $namaBarangVal,
            ':kuantitas'    => $kuantitasVal,
            ':keterangan'   => $keteranganVal
        ]);
        $barangId = (int)$pdo->lastInsertId();

        log_audit($pdo, $user['id'], 'TAMBAH_BARANG', "Menambahkan barang '{$namaBarangVal}' ID #{$barangId} ke pekerjaan ID #{$pekerjaan['id']}.");

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

                <!-- Input 1: Nama Barang -->
                <div>
                    <label for="nama_barang" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">1. Nama Barang</label>
                    <input type="text" id="nama_barang" name="nama_barang" value="<?= e($namaBarangVal) ?>" required placeholder="Contoh: Kursi Futura, Meja Round Table, Sound System"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>

                <!-- Input 2: Kuantitas (Teks) -->
                <div>
                    <label for="kuantitas" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">2. Kuantitas / Jumlah (Teks)</label>
                    <input type="text" id="kuantitas" name="kuantitas" value="<?= e($kuantitasVal) ?>" required placeholder="Contoh: 150 Pcs, 10 Unit, 2 Box, 1 Roll"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    <p class="text-xs text-slate-500 mt-1">Anda bebas memasukkan format teks seperti "10 Unit", "5 Box", "100 Pcs", dll.</p>
                </div>

                <!-- Input 3: Keterangan / Deskripsi -->
                <div>
                    <label for="keterangan" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">3. Keterangan / Detail Barang</label>
                    <textarea id="keterangan" name="keterangan" rows="4" required
                        placeholder="Contoh: Cover Putih Pita Gold - Kondisi Baik, Siap Dipasang"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"><?= e($keteranganVal) ?></textarea>
                </div>

                <!-- Input 4: Foto Barang (Multi-upload dengan live preview) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">4. Upload Foto Barang (HEIC, PNG, JPG, JPEG)</label>
                    
                    <div id="drop-zone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-700 border-dashed rounded-xl bg-slate-950 hover:border-indigo-500 transition-all cursor-pointer">
                        <div class="space-y-2 text-center">
                            <svg class="mx-auto h-12 w-12 text-indigo-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-slate-400 justify-center">
                                <span class="bg-indigo-600/20 text-indigo-300 hover:bg-indigo-600/30 px-4 py-1.5 rounded-lg border border-indigo-500/30 font-semibold transition-all">
                                    Klik atau Drag Foto di Sini
                                </span>
                            </div>
                            <p class="text-xs text-slate-500">Mendukung format HEIC (iPhone), PNG, JPG, JPEG hingga 10MB per file. Bisa pilih lebih dari 1 foto.</p>
                        </div>
                    </div>
                    <input id="foto" name="foto[]" type="file" multiple accept="image/*,.heic,.heif" class="hidden">

                    <!-- Container Pratinjau Foto Terpilih -->
                    <div id="preview-section" class="hidden mt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-300 uppercase tracking-wider">
                                Pratinjau Foto Terpilih (<span id="preview-count" class="text-indigo-400 font-bold">0</span> file)
                            </span>
                            <button type="button" id="btnClearFiles" class="text-xs text-rose-400 hover:underline">Hapus Semua Foto</button>
                        </div>
                        <div id="preview-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>
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
        const fileInput = document.getElementById('foto');
        const dropZone = document.getElementById('drop-zone');
        const previewSection = document.getElementById('preview-section');
        const previewGrid = document.getElementById('preview-grid');
        const previewCount = document.getElementById('preview-count');
        const btnClearFiles = document.getElementById('btnClearFiles');
        const btnSubmit = document.getElementById('btnSubmit');

        let selectedFilesContainer = new DataTransfer();

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-indigo-500', 'bg-indigo-950/20');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-indigo-500', 'bg-indigo-950/20');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-indigo-500', 'bg-indigo-950/20');
            if (e.dataTransfer.files.length) {
                handleFilesSelected(Array.from(e.dataTransfer.files));
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                handleFilesSelected(Array.from(e.target.files));
            }
        });

        async function handleFilesSelected(newFiles) {
            const notice = document.getElementById('heic-status-notice');
            const noticeText = document.getElementById('heic-status-text');

            let hasHeic = newFiles.some(f => {
                const ext = f.name.split('.').pop().toLowerCase();
                return ext === 'heic' || ext === 'heif' || f.type.includes('heic') || f.type.includes('heif');
            });

            if (hasHeic && typeof heic2any !== 'undefined') {
                notice.classList.remove('hidden');
                btnSubmit.disabled = true;
                btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');

                for (let i = 0; i < newFiles.length; i++) {
                    const f = newFiles[i];
                    const ext = f.name.split('.').pop().toLowerCase();
                    if (ext === 'heic' || ext === 'heif' || f.type.includes('heic') || f.type.includes('heif')) {
                        noticeText.textContent = `Mengonversi foto HEIC ${i+1}/${newFiles.length}... Mohon tunggu sebentar.`;
                        try {
                            const resBlob = await heic2any({ blob: f, toType: 'image/jpeg', quality: 0.85 });
                            const resultBlob = Array.isArray(resBlob) ? resBlob[0] : resBlob;
                            const convertedFile = new File([resultBlob], f.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
                            selectedFilesContainer.items.add(convertedFile);
                        } catch (err) {
                            console.warn('Client HEIC conversion failed:', err);
                            selectedFilesContainer.items.add(f);
                        }
                    } else {
                        selectedFilesContainer.items.add(f);
                    }
                }

                notice.classList.add('hidden');
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                newFiles.forEach(f => selectedFilesContainer.items.add(f));
            }

            fileInput.files = selectedFilesContainer.files;
            renderPreviews();
        }

        function renderPreviews() {
            previewGrid.innerHTML = '';
            const files = Array.from(selectedFilesContainer.files);

            if (files.length === 0) {
                previewSection.classList.add('hidden');
                return;
            }

            previewSection.classList.remove('hidden');
            previewCount.textContent = files.length;

            files.forEach((file, index) => {
                const card = document.createElement('div');
                card.className = 'relative group bg-slate-950 border border-slate-800 rounded-xl overflow-hidden shadow';

                const img = document.createElement('img');
                img.className = 'w-full h-28 object-cover';
                img.src = URL.createObjectURL(file);

                const info = document.createElement('div');
                info.className = 'p-2 bg-slate-900 border-t border-slate-800 text-[11px] truncate flex items-center justify-between text-slate-400';
                info.innerHTML = `<span class="truncate" title="${file.name}">${file.name}</span>`;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'absolute top-1.5 right-1.5 bg-rose-600/90 text-white rounded-full p-1 hover:bg-rose-500 shadow transition-all';
                removeBtn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
                removeBtn.onclick = (e) => {
                    e.stopPropagation();
                    removeFile(index);
                };

                card.appendChild(img);
                card.appendChild(info);
                card.appendChild(removeBtn);
                previewGrid.appendChild(card);
            });
        }

        function removeFile(indexToRemove) {
            const dt = new DataTransfer();
            const files = Array.from(selectedFilesContainer.files);
            files.forEach((file, index) => {
                if (index !== indexToRemove) {
                    dt.items.add(file);
                }
            });
            selectedFilesContainer = dt;
            fileInput.files = selectedFilesContainer.files;
            renderPreviews();
        }

        btnClearFiles.addEventListener('click', () => {
            selectedFilesContainer = new DataTransfer();
            fileInput.files = selectedFilesContainer.files;
            renderPreviews();
        });
    </script>
</body>
</html>
