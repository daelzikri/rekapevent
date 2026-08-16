<?php
// public/pekerja/hapus_foto.php

require_once __DIR__ . '/../middleware/auth.php';

$user = require_role(['pekerja']);
$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

validate_csrf_or_die();

$fotoId = (int)($_POST['foto_id'] ?? 0);
$barangId = (int)($_POST['barang_id'] ?? 0);

if ($fotoId <= 0 || $barangId <= 0) {
    redirect('/pekerja/index.php?error=Parameter+tidak+valid.');
}

// 1. Anti-IDOR Security Check: Pastikan foto ini terikat ke barang milik pekerjaan user yang sedang login!
$stmt = $pdo->prepare("
    SELECT fb.*, b.pekerjaan_id, p.user_id AS owner_user_id
    FROM foto_barang fb
    JOIN barang b ON fb.barang_id = b.id
    JOIN pekerjaan p ON b.pekerjaan_id = p.id
    WHERE fb.id = :foto_id AND b.id = :barang_id AND p.user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    ':foto_id'   => $fotoId,
    ':barang_id' => $barangId,
    ':user_id'   => $user['id']
]);
$foto = $stmt->fetch();

if (!$foto) {
    http_response_code(403);
    die("403 Forbidden - Anda tidak memiliki izin untuk menghapus foto ini (Anti-IDOR Protection).");
}

// 2. Hapus file fisik dari filesystem server
$fullPath = __DIR__ . '/../public' . $foto['file_path'];
if (file_exists($fullPath)) {
    @unlink($fullPath);
}

// 3. Hapus record dari database
$stmtDel = $pdo->prepare("DELETE FROM foto_barang WHERE id = :id");
$stmtDel->execute([':id' => $fotoId]);

log_audit($pdo, $user['id'], 'HAPUS_FOTO', "Menghapus foto ID #{$fotoId} dari barang ID #{$barangId}.");

redirect("/pekerja/edit_barang.php?id={$barangId}&success=Foto+berhasil+dihapus.");
