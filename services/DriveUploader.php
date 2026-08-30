<?php
// services/DriveUploader.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google_drive.php';
require_once __DIR__ . '/../config/helpers.php';

/**
 * Class DriveUploader
 * 
 * Menangani seluruh operasional sinkronisasi foto ke Google Drive API v3:
 * - Cache folder pekerjaan di DB & In-Memory
 * - Upload file ke folder pekerjaan target
 * - Auto-retry & self-healing status sinkronisasi
 * - Fail-Safe handling (mencegah error mempengaruhi aplikasi utama)
 */
class DriveUploader {

    private ?Google\Service\Drive $service = null;
    private static array $folderCache = [];

    /**
     * Constructor
     * 
     * @param Google\Service\Drive|null $service Pass instance custom atau gunakan get_gdrive_service() default
     */
    public function __construct(?Google\Service\Drive $service = null) {
        if ($service !== null) {
            $this->service = $service;
        } else {
            $this->service = get_gdrive_service();
        }
    }

    /**
     * Cek apakah Google Drive Service aktif dan siap digunakan
     */
    public function isReady(): bool {
        return $this->service !== null;
    }

    /**
     * Dapatkan ID folder Drive untuk pekerjaan/event.
     * Menggunakan cache in-memory -> tabel `pekerjaan_gdrive_folder` -> baru buat via API jika belum ada.
     *
     * @param string $parentId ID folder induk (DRIVE_ROOT_FOLDER_ID)
     * @param string $folderName Nama folder (nama pekerjaan/event)
     * @param int $pekerjaanId ID pekerjaan di database
     * @param PDO|null $db Instance PDO database
     * @return string ID folder Google Drive
     * @throws Exception
     */
    public function getOrCreateFolder(string $parentId, string $folderName, int $pekerjaanId, ?PDO $db = null): string {
        if (!$this->isReady()) {
            throw new Exception("Google Drive Service belum terkonfigurasi.");
        }

        // 1. Cek Cache In-Memory
        if (isset(self::$folderCache[$pekerjaanId])) {
            return self::$folderCache[$pekerjaanId];
        }

        // 2. Cek Cache Persisten di Tabel Database `pekerjaan_gdrive_folder`
        if ($db !== null && $pekerjaanId > 0) {
            try {
                $stmt = $db->prepare("SELECT gdrive_folder_id FROM pekerjaan_gdrive_folder WHERE pekerjaan_id = :pid LIMIT 1");
                $stmt->execute([':pid' => $pekerjaanId]);
                $cachedFolder = $stmt->fetchColumn();
                if ($cachedFolder) {
                    self::$folderCache[$pekerjaanId] = $cachedFolder;
                    return $cachedFolder;
                }
            } catch (Throwable $t) {
                error_log("DriveUploader Folder Cache DB Check Error: " . $t->getMessage());
            }
        }

        // 3. Sanitasi nama folder untuk query API
        $cleanFolderName = trim($folderName);
        if (empty($cleanFolderName)) {
            $cleanFolderName = "Event #" . $pekerjaanId;
        }

        $escapedName = str_replace("'", "\\'", $cleanFolderName);
        $query = "'{$parentId}' in parents and name = '{$escapedName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        try {
            $result = $this->service->files->listFiles([
                'q' => $query,
                'pageSize' => 1,
                'fields' => 'files(id, name)'
            ]);

            $folderId = null;
            if (count($result->getFiles()) > 0) {
                $folderId = $result->getFiles()[0]->getId();
            } else {
                // 4. Jika folder belum ada di Drive, buat folder baru
                $folderMeta = new Google\Service\Drive\DriveFile([
                    'name' => $cleanFolderName,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents' => [$parentId]
                ]);

                $folder = $this->service->files->create($folderMeta, [
                    'fields' => 'id'
                ]);
                $folderId = $folder->getId();
            }

            // 5. Simpan ke database persisten `pekerjaan_gdrive_folder`
            if ($db !== null && $pekerjaanId > 0 && !empty($folderId)) {
                try {
                    $stmtIns = $db->prepare("INSERT INTO pekerjaan_gdrive_folder (pekerjaan_id, gdrive_folder_id) VALUES (:pid, :fid) ON DUPLICATE KEY UPDATE gdrive_folder_id = VALUES(gdrive_folder_id)");
                    $stmtIns->execute([':pid' => $pekerjaanId, ':fid' => $folderId]);
                } catch (Throwable $t) {
                    error_log("DriveUploader Save Folder DB Error: " . $t->getMessage());
                }
            }

            self::$folderCache[$pekerjaanId] = $folderId;
            return $folderId;

        } catch (Throwable $t) {
            throw new Exception("Gagal membuat/mencari folder Drive '{$cleanFolderName}': " . $t->getMessage(), 0, $t);
        }
    }

    /**
     * Upload file fisik ke folder Google Drive
     *
     * @param string $localFilePath Path fisik file di server lokal
     * @param string $targetFolderId ID folder tujuan di Drive
     * @param string $fileName Nama file di Google Drive
     * @return array ['file_id' => string, 'view_link' => string]
     * @throws Exception
     */
    public function upload(string $localFilePath, string $targetFolderId, string $fileName): array {
        if (!$this->isReady()) {
            throw new Exception("Google Drive Service tidak aktif.");
        }

        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            throw new Exception("File lokal tidak ditemukan atau tidak dapat dibaca: {$localFilePath}");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $localFilePath) ?: 'application/octet-stream';
        finfo_close($finfo);

        $fileMetadata = new Google\Service\Drive\DriveFile([
            'name' => $fileName,
            'parents' => [$targetFolderId]
        ]);

        $content = file_get_contents($localFilePath);

        $createdFile = $this->service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, webViewLink, webContentLink'
        ]);

        $fileId = $createdFile->getId();
        $viewLink = $createdFile->getWebViewLink();

        // Opsional: Buat permission agar file bisa dilihat oleh publik via view link
        try {
            $permission = new Google\Service\Drive\Permission([
                'role' => 'reader',
                'type' => 'anyone'
            ]);
            $this->service->permissions->create($fileId, $permission);
        } catch (Throwable $t) {
            // Abaikan jika setting permission gagal/dibatasi
        }

        if (empty($viewLink)) {
            $viewLink = "https://drive.google.com/file/d/{$fileId}/view";
        }

        return [
            'file_id'   => $fileId,
            'view_link' => $viewLink
        ];
    }

    /**
     * Helper khusus untuk mengunggah 1 record foto_barang dan memperbarui DB
     *
     * @param PDO $db Connection DB
     * @param int $fotoId ID foto_barang
     * @param string $relativePath Path relatif file (misal `/uploads/1/uuid.webp`)
     * @param string $serverFileName Nama file di server
     * @param int $pekerjaanId ID pekerjaan terkait
     * @param string $namaPekerjaan Nama pekerjaan terkait
     * @return bool True jika berhasil
     */
    public function uploadFotoBarang(PDO $db, int $fotoId, string $relativePath, string $serverFileName, int $pekerjaanId, string $namaPekerjaan): bool {
        if (!$this->isReady()) {
            return false;
        }

        // Resolusi path absolut file lokal di server
        $localAbsPath = realpath(__DIR__ . '/../public' . $relativePath);
        if (!$localAbsPath || !file_exists($localAbsPath)) {
            // Cobalah pencarian tanpa realpath jika file baru dibuat
            $localAbsPath = __DIR__ . '/../public' . $relativePath;
            if (!file_exists($localAbsPath)) {
                return false;
            }
        }

        $rootFolderId = defined('GDRIVE_ROOT_FOLDER_ID') ? GDRIVE_ROOT_FOLDER_ID : '';
        if (empty($rootFolderId)) {
            throw new Exception("GDRIVE_ROOT_FOLDER_ID belum diatur pada file konfigurasi.");
        }

        // 1. Dapatkan atau buat sub-folder pekerjaan
        $targetFolderId = $this->getOrCreateFolder($rootFolderId, $namaPekerjaan, $pekerjaanId, $db);

        // 2. Upload file ke Google Drive
        $result = $this->upload($localAbsPath, $targetFolderId, $serverFileName);

        // 3. Update status sukses di tabel `foto_barang`
        $stmtUpd = $db->prepare("
            UPDATE foto_barang 
            SET gdrive_file_id = :fid, 
                gdrive_view_link = :vlink, 
                gdrive_status = 'success', 
                gdrive_last_attempt_at = NOW() 
            WHERE id = :id
        ");
        $stmtUpd->execute([
            ':fid'   => $result['file_id'],
            ':vlink' => $result['view_link'],
            ':id'    => $fotoId
        ]);

        return true;
    }

    /**
     * Memproses antrean foto berstatus 'pending' atau 'failed' (retry_count < 5)
     *
     * @param PDO $db Connection DB
     * @param int $limit Batas jumlah foto per eksekusi cron (default: 20)
     * @return array Ringkasan hasil pemrosesan
     */
    public function retryPending(PDO $db, int $limit = 20): array {
        $summary = [
            'processed' => 0,
            'success'   => 0,
            'failed'    => 0,
            'messages'  => []
        ];

        if (!$this->isReady()) {
            $summary['messages'][] = "Google Drive Service belum terkonfigurasi atau kredensial kosong.";
            return $summary;
        }

        try {
            // Ambil foto yang belum tersinkronisasi sempurna
            $stmt = $db->prepare("
                SELECT f.id AS foto_id, f.file_path, f.nama_file_server, f.gdrive_retry_count,
                       b.pekerjaan_id, p.nama_pekerjaan
                FROM foto_barang f
                JOIN barang b ON f.barang_id = b.id
                JOIN pekerjaan p ON b.pekerjaan_id = p.id
                WHERE f.gdrive_status IN ('pending', 'failed')
                  AND f.gdrive_retry_count < 5
                ORDER BY f.id ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $pendingPhotos = $stmt->fetchAll();

            if (empty($pendingPhotos)) {
                $summary['messages'][] = "Tidak ada antrean foto pending/failed.";
                return $summary;
            }

            foreach ($pendingPhotos as $foto) {
                $summary['processed']++;
                $fotoId = (int)$foto['foto_id'];
                $retryCount = (int)$foto['gdrive_retry_count'] + 1;

                try {
                    $ok = $this->uploadFotoBarang(
                        $db,
                        $fotoId,
                        $foto['file_path'],
                        $foto['nama_file_server'],
                        (int)$foto['pekerjaan_id'],
                        $foto['nama_pekerjaan']
                    );

                    if ($ok) {
                        $summary['success']++;
                        $summary['messages'][] = "Foto ID #{$fotoId} berhasil diupload ke Google Drive.";
                    } else {
                        throw new Exception("Upload gagal (File lokal tidak ditemukan/terbaca).");
                    }
                } catch (Throwable $t) {
                    $summary['failed']++;
                    $newStatus = ($retryCount >= 5) ? 'failed' : 'pending';

                    // Update retry count dan status
                    $stmtErr = $db->prepare("
                        UPDATE foto_barang 
                        SET gdrive_retry_count = :rcount, 
                            gdrive_status = :status, 
                            gdrive_last_attempt_at = NOW() 
                        WHERE id = :id
                    ");
                    $stmtErr->execute([
                        ':rcount' => $retryCount,
                        ':status' => $newStatus,
                        ':id'     => $fotoId
                    ]);

                    $errMsg = "Foto ID #{$fotoId} gagal (Percobaan {$retryCount}/5): " . $t->getMessage();
                    $summary['messages'][] = $errMsg;
                    error_log("DriveUploader Sync Error: " . $errMsg);
                }
            }
        } catch (Throwable $t) {
            $summary['messages'][] = "Critical Error pada retryPending: " . $t->getMessage();
            error_log("DriveUploader Critical Retry Error: " . $t->getMessage());
        }

        return $summary;
    }
}
