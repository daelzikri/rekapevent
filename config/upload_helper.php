<?php
// config/upload_helper.php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Generasi UUID v4 acak untuk nama file server
 */
function generate_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Konversi HEIC ke JPEG di Server (Mencoba Imagick PHP terlebih dahulu, lalu Python CLI)
 */
function convert_heic_to_jpeg_server(string $inputPath, string $outputPath): bool {
    // 1. Coba via Imagick PHP Extension (Biasa tersedia di Hostinger/cPanel)
    if (extension_loaded('imagick') || class_exists('Imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($inputPath);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            return true;
        } catch (Exception $e) {
            error_log("Imagick HEIC conversion failed: " . $e->getMessage());
        }
    }

    // 2. Coba via Python Script CLI jika Python terinstall di server
    $scriptPath = realpath(__DIR__ . '/../scripts/convert_heic.py');
    if ($scriptPath && file_exists($scriptPath)) {
        foreach (['python3', 'python'] as $pyCmd) {
            $cmd = "{$pyCmd} " . escapeshellarg($scriptPath) . " " . escapeshellarg($inputPath) . " " . escapeshellarg($outputPath) . " 2>&1";
            $output = [];
            $returnVar = 0;
            @exec($cmd, $output, $returnVar);
            if ($returnVar === 0 && file_exists($outputPath)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Penanganan Upload Multi-Foto dengan Validasi Magic Bytes & HEIC Conversion
 */
function handle_photo_uploads(array $fileInput, int $pekerjaanId, int $barangId, PDO $pdo): array {
    $uploadedCount = 0;
    $errors = [];

    if (empty($fileInput['name'])) {
        return ['success_count' => 0, 'errors' => []];
    }

    // Normalisasi struktur $_FILES jika single atau multi file
    $files = [];
    if (is_array($fileInput['name'])) {
        foreach ($fileInput['name'] as $i => $name) {
            $err = $fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE || empty($name)) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                switch ($err) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors[] = "File '{$name}' melebihi batas ukuran maksimal upload server.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errors[] = "File '{$name}' terpotong saat proses upload.";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $errors[] = "Folder temporary upload server tidak ditemukan.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $errors[] = "Gagal menulis file '{$name}' ke disk server.";
                        break;
                    default:
                        $errors[] = "Gagal mengupload file '{$name}' (Kode Error: {$err}).";
                        break;
                }
                continue;
            }
            $files[] = [
                'name'     => $fileInput['name'][$i],
                'type'     => $fileInput['type'][$i],
                'tmp_name' => $fileInput['tmp_name'][$i],
                'size'     => $fileInput['size'][$i],
            ];
        }
    } else {
        $err = $fileInput['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_OK && !empty($fileInput['name'])) {
            $files[] = $fileInput;
        } elseif ($err !== UPLOAD_ERR_NO_FILE && !empty($fileInput['name'])) {
            $errors[] = "Gagal mengupload file '{$fileInput['name']}' (Kode Error: {$err}).";
        }
    }

    if (empty($files)) {
        return ['success_count' => 0, 'errors' => $errors];
    }

    $uploadDir = __DIR__ . "/../public/uploads/{$pekerjaanId}";
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    foreach ($files as $f) {
        // 1. Validasi Ukuran Maksimal 10MB
        if ($f['size'] > 10 * 1024 * 1024) {
            $errors[] = "File {$f['name']} melebihi batas 10MB.";
            continue;
        }

        // 2. Validasi MIME Type via Magic Bytes (finfo_file)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);

        $extOriginal = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

        $allowedMimes = [
            'image/jpeg', 'image/jpg', 'image/pjpeg',
            'image/png', 'image/webp',
            'image/heic', 'image/heif', 'image/x-heic', 'application/octet-stream'
        ];

        if (!in_array($mimeType, $allowedMimes, true) && !in_array($extOriginal, ['heic', 'heif', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            $errors[] = "File {$f['name']} memiliki format tidak diizinkan ({$mimeType}). Hanya HEIC, PNG, JPG, JPEG yang didukung.";
            continue;
        }

        $uuid = generate_uuid();
        $isHeic = ($extOriginal === 'heic' || $extOriginal === 'heif' || str_contains($mimeType, 'heic') || str_contains($mimeType, 'heif'));

        if ($isHeic) {
            // Format HEIC: simpan sementara -> panggil converter server (Imagick PHP / Python)
            $tmpHeicPath = "{$uploadDir}/{$uuid}_tmp.heic";
            $finalJpegPath = "{$uploadDir}/{$uuid}.jpg";

            if (!move_uploaded_file($f['tmp_name'], $tmpHeicPath)) {
                $errors[] = "Gagal memindahkan file upload {$f['name']}.";
                continue;
            }

            $converted = convert_heic_to_jpeg_server($tmpHeicPath, $finalJpegPath);

            if (file_exists($tmpHeicPath)) {
                @unlink($tmpHeicPath);
            }

            if (!$converted || !file_exists($finalJpegPath)) {
                $errors[] = "Gagal mengonversi file HEIC {$f['name']} di server. Pastikan ekstensi PHP Imagick aktif atau gunakan opsi konversi di browser.";
                continue;
            }

            $finalFileName = "{$uuid}.jpg";
            $relativePath = "/uploads/{$pekerjaanId}/{$finalFileName}";
        } else {
            // Format JPG / PNG / WEBP
            $targetExt = ($mimeType === 'image/png' || $extOriginal === 'png') ? 'png' : 'jpg';
            $finalFileName = "{$uuid}.{$targetExt}";
            $finalPath = "{$uploadDir}/{$finalFileName}";

            if (!move_uploaded_file($f['tmp_name'], $finalPath)) {
                $errors[] = "Gagal menyimpan file {$f['name']}.";
                continue;
            }

            $relativePath = "/uploads/{$pekerjaanId}/{$finalFileName}";
        }

        // Catat ke database foto_barang
        $stmt = $pdo->prepare("INSERT INTO foto_barang (barang_id, file_path, format_asli, nama_file_server) VALUES (:barang_id, :file_path, :format_asli, :nama_file_server)");
        $stmt->execute([
            ':barang_id'        => $barangId,
            ':file_path'        => $relativePath,
            ':format_asli'      => strtoupper($extOriginal),
            ':nama_file_server' => $finalFileName
        ]);

        $uploadedCount++;
    }

    return [
        'success_count' => $uploadedCount,
        'errors'        => $errors
    ];
}
