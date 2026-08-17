<?php
// public/superadmin/export.php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

$user = require_role(['superadmin']);
$pdo = get_db_connection();

$type = $_GET['type'] ?? null;
$pekerjaanId = (int)($_GET['pekerjaan_id'] ?? 0);

// Filter Opsi Kolom & Ukuran Foto
$includeEvent = isset($_GET['type']) ? isset($_GET['include_event']) : true;
$includePekerja = isset($_GET['type']) ? isset($_GET['include_pekerja']) : true;
$maxPhotoSize = isset($_GET['max_photo_size']) ? (int)$_GET['max_photo_size'] : 80;
if (!in_array($maxPhotoSize, [60, 80, 100, 120], true)) {
    $maxPhotoSize = 80;
}

if ($type === 'excel' || $type === 'word') {
    // Query data sesuai filter pekerjaan_id
    $sql = "
        SELECT b.*, p.nama_pekerjaan, u.username AS nama_pekerja
        FROM barang b
        JOIN pekerjaan p ON b.pekerjaan_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    if ($pekerjaanId > 0) {
        $sql .= " AND b.pekerjaan_id = :pekerjaan_id";
        $params[':pekerjaan_id'] = $pekerjaanId;
    }
    $sql .= " ORDER BY p.nama_pekerjaan ASC, b.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    // Ambil daftar foto untuk setiap barang
    $publicDir = realpath(__DIR__ . '/..');
    foreach ($data as &$item) {
        $stmtFoto = $pdo->prepare("SELECT file_path FROM foto_barang WHERE barang_id = :barang_id ORDER BY id ASC");
        $stmtFoto->execute([':barang_id' => $item['id']]);
        $item['foto_list'] = $stmtFoto->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($item);

    // Susun Header Kolom & Key secara Dinamis
    $headers = ['No'];
    $columnKeys = ['no'];

    if ($includeEvent) {
        $headers[] = 'Nama Event';
        $columnKeys[] = 'nama_pekerjaan';
    }

    if ($includePekerja) {
        $headers[] = 'Pekerja Input';
        $columnKeys[] = 'nama_pekerja';
    }

    $headers[] = 'Nama Barang';
    $columnKeys[] = 'nama_barang';

    $headers[] = 'Foto';
    $columnKeys[] = 'foto';

    $headers[] = 'Qty';
    $columnKeys[] = 'kuantitas';

    $headers[] = 'Keterangan';
    $columnKeys[] = 'keterangan';

    log_audit($pdo, $user['id'], 'EXPORT_DATA', "Mengunduh export format " . strtoupper($type) . " (Pekerjaan ID: {$pekerjaanId}).");

    if ($type === 'excel') {
        // --- GENERATE EXCEL ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekapan Barang Event');

        $cols = range('A', chr(ord('A') + count($headers) - 1));
        $lastCol = end($cols);

        // Judul Utama
        $sheet->setCellValue('A1', 'REKAPAN BARANG EVENT');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d F Y H:i:s'));
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Tabel (Row 4)
        foreach ($headers as $idx => $headerText) {
            $colLetter = $cols[$idx];
            $sheet->setCellValue("{$colLetter}4", $headerText);
        }

        // Style Header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ];
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(25);

        // Populate Data Rows
        $rowNum = 5;
        $no = 1;
        $fotoColIdx = array_search('foto', $columnKeys, true);
        $fotoColLetter = $cols[$fotoColIdx];

        foreach ($data as $item) {
            foreach ($columnKeys as $cIdx => $key) {
                $colLetter = $cols[$cIdx];
                $cellRef = "{$colLetter}{$rowNum}";

                if ($key === 'no') {
                    $sheet->setCellValue($cellRef, $no);
                    $sheet->getStyle($cellRef)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } elseif ($key === 'nama_pekerjaan') {
                    $sheet->setCellValue($cellRef, $item['nama_pekerjaan']);
                } elseif ($key === 'nama_pekerja') {
                    $sheet->setCellValue($cellRef, $item['nama_pekerja']);
                } elseif ($key === 'nama_barang') {
                    $sheet->setCellValue($cellRef, !empty($item['nama_barang']) ? $item['nama_barang'] : '-');
                } elseif ($key === 'kuantitas') {
                    $sheet->setCellValue($cellRef, $item['kuantitas']);
                    $sheet->getStyle($cellRef)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } elseif ($key === 'keterangan') {
                    $sheet->setCellValue($cellRef, $item['keterangan']);
                } elseif ($key === 'foto') {
                    // Embed Foto ke dalam Sel Excel (Vertikal)
                    $validPhotos = [];
                    if (!empty($item['foto_list'])) {
                        foreach ($item['foto_list'] as $relPath) {
                            $absPath = realpath($publicDir . $relPath);
                            if ($absPath && file_exists($absPath)) {
                                $validPhotos[] = $absPath;
                            }
                        }
                    }

                    if (!empty($validPhotos)) {
                        $sheet->setCellValue($cellRef, '');
                        $yOffset = 5;
                        foreach ($validPhotos as $fIdx => $absPath) {
                            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                            $drawing->setName('Foto_' . ($fIdx + 1));
                            $drawing->setDescription('Foto Barang');
                            $drawing->setPath($absPath);
                            $drawing->setHeight($maxPhotoSize);
                            $drawing->setCoordinates($cellRef);
                            $drawing->setOffsetX(5);
                            $drawing->setOffsetY($yOffset);
                            $drawing->setWorksheet($sheet);

                            $yOffset += $maxPhotoSize + 5;
                        }

                        $totalHeightPt = (count($validPhotos) * ($maxPhotoSize + 5) + 10) * 0.75;
                        $sheet->getRowDimension($rowNum)->setRowHeight(max(40, $totalHeightPt));
                    } else {
                        $sheet->setCellValue($cellRef, '-');
                        $sheet->getStyle($cellRef)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // Vertical alignment center untuk semua sel
                $sheet->getStyle($cellRef)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }

            $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $no++;
            $rowNum++;
        }

        // Auto dimension width kecuali kolom foto
        foreach ($columnKeys as $cIdx => $key) {
            $colLetter = $cols[$cIdx];
            if ($key === 'foto') {
                $sheet->getColumnDimension($colLetter)->setWidth(max(16, round(($maxPhotoSize * 0.15) + 4)));
            } elseif ($key === 'keterangan') {
                $sheet->getColumnDimension($colLetter)->setWidth(30);
                $sheet->getStyle("{$colLetter}5:{$colLetter}" . ($rowNum - 1))->getAlignment()->setWrapText(true);
            } else {
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }
        }

        $filename = "Rekapan_Barang_Event_" . date('Ymd_His') . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } elseif ($type === 'word') {
        // --- GENERATE WORD ---
        $phpWord = new PhpWord();
        
        // Halaman tetap PORTRAIT dengan Margin A4 Presisi
        $section = $phpWord->addSection([
            'orientation'  => 'portrait',
            'marginLeft'   => 800,
            'marginRight'  => 800,
            'marginTop'    => 800,
            'marginBottom' => 800,
        ]);

        // Judul Dokumen
        $section->addTitle('REKAPAN BARANG EVENT', 1);
        $section->addText('Tanggal Export: ' . date('d F Y H:i:s'), ['italic' => true, 'size' => 9]);
        $section->addTextBreak(1);

        // Alokasi Lebar Kolom Presisi (Total ~10300 twips pas margin A4 Portrait)
        $baseWidths = [
            'no'             => 600,
            'nama_pekerjaan' => 2000,
            'nama_pekerja'   => 1500,
            'nama_barang'    => 1800,
            'foto'           => 1800,
            'kuantitas'      => 800,
            'keterangan'     => 1800,
        ];

        // Hitung ulang jika ada kolom yang di-uncheck
        $colWidths = [];
        $extraWidth = 0;
        if (!$includeEvent) {
            $extraWidth += $baseWidths['nama_pekerjaan'];
        }
        if (!$includePekerja) {
            $extraWidth += $baseWidths['nama_pekerja'];
        }

        foreach ($columnKeys as $key) {
            $colWidths[$key] = $baseWidths[$key];
        }

        if ($extraWidth > 0) {
            $colWidths['nama_barang'] += (int)round($extraWidth * 0.5);
            $colWidths['keterangan']  += (int)round($extraWidth * 0.5);
        }

        // Define Table Style
        $tableStyle = [
            'borderColor' => '000000',
            'borderSize'  => 6,
            'cellMargin'  => 80,
            'cantSplit'   => true,
        ];
        $headerStyle = ['backgroundColor' => '4F46E5'];
        $headerTextStyle = ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];

        $phpWord->addTableStyle('RekapanTable', $tableStyle);
        $table = $section->addTable('RekapanTable');

        // Table Header
        $table->addRow();
        foreach ($columnKeys as $idx => $key) {
            $w = $colWidths[$key];
            $text = $headers[$idx];
            $align = in_array($key, ['no', 'kuantitas', 'foto'], true) ? Jc::CENTER : Jc::LEFT;
            $table->addCell($w, $headerStyle)->addText($text, $headerTextStyle, ['alignment' => $align]);
        }

        // Table Rows
        $no = 1;
        foreach ($data as $item) {
            $table->addRow();
            foreach ($columnKeys as $key) {
                $w = $colWidths[$key];

                if ($key === 'no') {
                    $table->addCell($w)->addText($no, [], ['alignment' => Jc::CENTER]);
                } elseif ($key === 'nama_pekerjaan') {
                    $table->addCell($w)->addText($item['nama_pekerjaan']);
                } elseif ($key === 'nama_pekerja') {
                    $table->addCell($w)->addText($item['nama_pekerja']);
                } elseif ($key === 'nama_barang') {
                    $table->addCell($w)->addText(!empty($item['nama_barang']) ? $item['nama_barang'] : '-');
                } elseif ($key === 'kuantitas') {
                    $table->addCell($w)->addText($item['kuantitas'], [], ['alignment' => Jc::CENTER]);
                } elseif ($key === 'keterangan') {
                    $table->addCell($w)->addText($item['keterangan']);
                } elseif ($key === 'foto') {
                    // Embed Foto secara Vertikal dalam 1 Sel Kolom Foto
                    $cellFoto = $table->addCell($w);
                    $hasFoto = false;

                    if (!empty($item['foto_list'])) {
                        foreach ($item['foto_list'] as $relPath) {
                            $absPath = realpath($publicDir . $relPath);
                            if ($absPath && file_exists($absPath)) {
                                $cellFoto->addImage($absPath, [
                                    'width'        => $maxPhotoSize,
                                    'height'       => $maxPhotoSize,
                                    'alignment'    => Jc::CENTER,
                                    'marginTop'    => 2,
                                    'marginBottom' => 4,
                                ]);
                                $hasFoto = true;
                            }
                        }
                    }

                    if (!$hasFoto) {
                        $cellFoto->addText('-', [], ['alignment' => Jc::CENTER]);
                    }
                }
            }
            $no++;
        }

        $filename = "Rekapan_Barang_Event_" . date('Ymd_His') . ".docx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('php://output');
        exit;
    }
}

// Ambil daftar pekerjaan untuk pilihan export
$stmtJobs = $pdo->query("SELECT id, nama_pekerjaan FROM pekerjaan ORDER BY nama_pekerjaan ASC");
$jobs = $stmtJobs->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Superadmin</title>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
                <div>
                    <span class="font-bold text-white tracking-tight">Superadmin Panel</span>
                    <span class="ml-2 text-xs px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-medium">Export Rekapan Data</span>
                </div>
            </div>

            <div class="flex items-center space-x-6">
                <a href="/superadmin/kelola_pekerjaan.php" class="text-sm text-slate-400 hover:text-white transition-all">Kelola Pekerjaan</a>
                <a href="/superadmin/kelola_akun.php" class="text-sm text-slate-400 hover:text-white transition-all">Kelola Akun</a>
                <a href="/superadmin/export.php" class="text-sm font-bold text-purple-400">Export Excel/Word</a>
                <a href="/admin/dashboard.php" class="text-sm text-slate-400 hover:text-white transition-all">Dashboard View</a>
                <a href="/auth/logout.php" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-all">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow max-w-4xl w-full mx-auto px-4 sm:px-6 py-8">

        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Export Data Rekapan Barang</h1>
                <p class="text-sm text-slate-400 mt-1">Unduh laporan resmi barang event dalam format Microsoft Excel (`.xlsx`) atau Microsoft Word (`.docx`) lengkap dengan foto terlampir.</p>
            </div>

            <form method="GET" action="" class="space-y-6 pt-4 border-t border-slate-800">

                <!-- Dropdown Pekerjaan -->
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Pilih Pekerjaan Event</label>
                    <select name="pekerjaan_id" class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all">
                        <option value="0">-- Seluruh Pekerjaan Event (Semua Data) --</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?= $job['id'] ?>" <?= $pekerjaanId === $job['id'] ? 'selected' : '' ?>>
                                <?= e($job['nama_pekerjaan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Pengaturan Opsi Kolom & Foto -->
                <div class="bg-slate-950/60 border border-slate-800 p-5 rounded-xl space-y-4">
                    <h4 class="text-sm font-bold text-slate-200">Pengaturan Kolom & Ukuran Foto Export</h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="include_event" value="1" <?= $includeEvent ? 'checked' : '' ?> 
                                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm text-slate-300">Tampilkan Kolom <strong>Nama Event</strong></span>
                        </label>

                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="include_pekerja" value="1" <?= $includePekerja ? 'checked' : '' ?> 
                                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm text-slate-300">Tampilkan Kolom <strong>Pekerja Input (PJ)</strong></span>
                        </label>
                    </div>

                    <div class="pt-2 border-t border-slate-800/80">
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Ukuran Maksimal Foto dalam Kolom</label>
                        <select name="max_photo_size" class="w-full sm:w-64 px-4 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="60" <?= $maxPhotoSize === 60 ? 'selected' : '' ?>>Kecil (60px x 60px)</option>
                            <option value="80" <?= $maxPhotoSize === 80 ? 'selected' : '' ?>>Sedang (80px x 80px) - Default</option>
                            <option value="100" <?= $maxPhotoSize === 100 ? 'selected' : '' ?>>Besar (100px x 100px)</option>
                        </select>
                    </div>
                </div>

                <!-- Tombol Export -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <button type="submit" name="type" value="excel" 
                        class="flex items-center justify-center space-x-3 py-4 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-lg shadow-emerald-600/30 transition-all">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm10.5-8.5l-2.1 3.5 2.1 3.5h-1.8l-1.3-2.3-1.3 2.3h-1.8l2.1-3.5-2.1-3.5h1.8l1.3 2.3 1.3-2.3h1.8z"/>
                        </svg>
                        <span>Export ke Excel (.xlsx)</span>
                    </button>

                    <button type="submit" name="type" value="word" 
                        class="flex items-center justify-center space-x-3 py-4 px-6 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold shadow-lg shadow-blue-600/30 transition-all">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM6 20V4h5v6h6v10H6z"/>
                        </svg>
                        <span>Export ke Word (.docx)</span>
                    </button>
                </div>
            </form>
        </div>

    </main>

</body>
</html>

