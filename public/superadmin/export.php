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

if ($type === 'excel' || $type === 'word') {
    // Query data sesuai filter pekerjaan_id
    $sql = "
        SELECT b.*, p.nama_pekerjaan, u.username AS nama_pekerja,
               (SELECT COUNT(*) FROM foto_barang fb WHERE fb.barang_id = b.id) AS total_foto
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

    log_audit($pdo, $user['id'], 'EXPORT_DATA', "Mengunduh export format " . strtoupper($type) . " (Pekerjaan ID: {$pekerjaanId}).");

    if ($type === 'excel') {
        // --- GENERATE EXCEL ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekapan Barang Event');

        // Judul Utama
        $sheet->setCellValue('A1', 'REKAPAN BARANG EVENT');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d F Y H:i:s'));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Tabel
        $headers = ['No', 'Nama Pekerjaan / Proyek', 'Penanggung Jawab', 'Nama Barang', 'Kuantitas', 'Keterangan Barang', 'Total Foto'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        
        foreach ($headers as $idx => $headerText) {
            $col = $cols[$idx];
            $sheet->setCellValue("{$col}4", $headerText);
        }

        // Style Header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
        ];
        $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

        // Populate Data Rows
        $rowNum = 5;
        $no = 1;
        foreach ($data as $item) {
            $sheet->setCellValue("A{$rowNum}", $no++);
            $sheet->setCellValue("B{$rowNum}", $item['nama_pekerjaan']);
            $sheet->setCellValue("C{$rowNum}", $item['nama_pekerja']);
            $sheet->setCellValue("D{$rowNum}", !empty($item['nama_barang']) ? $item['nama_barang'] : '-');
            $sheet->setCellValue("E{$rowNum}", $item['kuantitas']);
            $sheet->setCellValue("F{$rowNum}", $item['keterangan']);
            $sheet->setCellValue("G{$rowNum}", $item['total_foto']);

            // Align Center for No, Qty, Total Foto
            $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $rowNum++;
        }

        // Auto dimension size
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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
        $section = $phpWord->addSection();

        // Judul Dokumen
        $section->addTitle('REKAPAN BARANG EVENT', 1);
        $section->addText('Tanggal Export: ' . date('d F Y H:i:s'), ['italic' => true, 'size' => 9]);
        $section->addTextBreak(1);

        // Define Table Style
        $tableStyle = [
            'borderColor' => '000000',
            'borderSize'  => 6,
            'cellMargin'  => 80
        ];
        $headerStyle = ['backgroundColor' => '4F46E5'];
        $headerTextStyle = ['bold' => true, 'color' => 'FFFFFF', 'size' => 10];

        $phpWord->addTableStyle('RekapanTable', $tableStyle);
        $table = $section->addTable('RekapanTable');

        // Table Header
        $table->addRow();
        $table->addCell(600, $headerStyle)->addText('No', $headerTextStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(2000, $headerStyle)->addText('Pekerjaan', $headerTextStyle);
        $table->addCell(1200, $headerStyle)->addText('PJ Pekerja', $headerTextStyle);
        $table->addCell(1800, $headerStyle)->addText('Nama Barang', $headerTextStyle);
        $table->addCell(1000, $headerStyle)->addText('Qty', $headerTextStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(2400, $headerStyle)->addText('Keterangan', $headerTextStyle);
        $table->addCell(800, $headerStyle)->addText('Foto', $headerTextStyle, ['alignment' => Jc::CENTER]);

        $no = 1;
        foreach ($data as $item) {
            $table->addRow();
            $table->addCell(600)->addText($no++, [], ['alignment' => Jc::CENTER]);
            $table->addCell(2000)->addText($item['nama_pekerjaan']);
            $table->addCell(1200)->addText($item['nama_pekerja']);
            $table->addCell(1800)->addText(!empty($item['nama_barang']) ? $item['nama_barang'] : '-');
            $table->addCell(1000)->addText($item['kuantitas'], [], ['alignment' => Jc::CENTER]);
            $table->addCell(2400)->addText($item['keterangan']);
            $table->addCell(800)->addText($item['total_foto'] . ' foto', [], ['alignment' => Jc::CENTER]);
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
                <p class="text-sm text-slate-400 mt-1">Unduh laporan resmi barang event dalam format Microsoft Excel (`.xlsx`) atau Microsoft Word (`.docx`).</p>
            </div>

            <form method="GET" action="" class="space-y-6 pt-4 border-t border-slate-800">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Pilih Pekerjaan Event</label>
                    <select name="pekerjaan_id" class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all">
                        <option value="0">-- Seluruh Pekerjaan Event (Semua Data) --</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?= $job['id'] ?>"><?= e($job['nama_pekerjaan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4">
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

    <script src="/assets/js/activity-tracker.js"></script>
</body>
</html>
