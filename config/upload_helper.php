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
 * Konversi HEIC ke WebP di Server (Mencoba Imagick PHP, Python CLI, lalu GD Fallback)
 */
function convert_heic_to_webp_server(string $inputPath, string $outputPath): bool {
    // 1. Coba via Imagick PHP Extension
    if (extension_loaded('imagick') || class_exists('Imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($inputPath);
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(85);
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            if (file_exists($outputPath)) {
                return true;
            }
        } catch (Exception $e) {
            error_log("Imagick HEIC to WebP conversion failed: " . $e->getMessage());
        }
    }

    // 2. Coba via Python Script CLI
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

    // 3. Fallback: Konversi HEIC ke JPEG temp terlebih dahulu via convert_heic_to_jpeg_server, lalu ubah ke WebP via GD
    $tmpJpegPath = dirname($outputPath) . '/' . basename($outputPath, '.webp') . '_tmp.jpg';
    if (convert_heic_to_jpeg_server($inputPath, $tmpJpegPath)) {
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $gdImg = @imagecreatefromjpeg($tmpJpegPath);
            if ($gdImg !== false) {
                $saved = imagewebp($gdImg, $outputPath, 85);
                imagedestroy($gdImg);
                @unlink($tmpJpegPath);
                if ($saved && file_exists($outputPath)) {
                    return true;
                }
            }
        }
        if (file_exists($tmpJpegPath)) {
            @rename($tmpJpegPath, $outputPath);
            return true;
        }
    }

    return false;
}

/**
 * Konversi HEIC ke JPEG di Server (Fungsi Kompatibilitas)
 */
function convert_heic_to_jpeg_server(string $inputPath, string $outputPath): bool {
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
 * Memeriksa apakah nama file mengandung ekstensi atau karakter berbahaya (PHP, executable, null byte, double extension dll)
 */
function is_dangerous_filename(string $filename): bool {
    if (str_contains($filename, "\0") || str_contains($filename, "%00")) {
        return true;
    }

    $lower = strtolower($filename);

    $dangerous = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar',
        'inc', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'sh', 'bash', 'exe',
        'htaccess', 'htpasswd', 'cmd', 'bat', 'vbs'
    ];

    $parts = explode('.', $lower);
    if (count($parts) > 1) {
        for ($i = 1; $i < count($parts); $i++) {
            $part = trim($parts[$i]);
            if (in_array($part, $dangerous, true)) {
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

        // 2. Strict Check: Cegah tamper nama file (Burp Suite) dengan ekstensi skrip/berbahaya (.php, .phtml, .php.jpg, dll)
        if (is_dangerous_filename($f['name'])) {
            $errors[] = "File '{$f['name']}' disetolak karena mengandung ekstensi atau karakter yang tidak diizinkan.";
            continue;
        }

        // 3. Validasi Ekstensi File Asli dengan Whitelist Ketat
        $extOriginal = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
        if (!in_array($extOriginal, $allowedExtensions, true)) {
            $errors[] = "File '{$f['name']}' memiliki ekstensi tidak diizinkan. Hanya format JPG, JPEG, PNG, WEBP, dan HEIC yang diperbolehkan.";
            continue;
        }

        // 4. Validasi MIME Type via Magic Bytes (finfo_file)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg', 'image/jpg', 'image/pjpeg',
            'image/png', 'image/webp',
            'image/heic', 'image/heif', 'image/x-heic'
        ];

        if (!in_array($mimeType, $allowedMimes, true)) {
            $errors[] = "File '{$f['name']}' memiliki format isi file tidak diizinkan ({$mimeType}).";
            continue;
        }

        // 5. Validasi Isi File untuk Mencegah Polyglot WebShell / Payload Skrip PHP yang Di-embed di Gambar
        $fileContents = @file_get_contents($f['tmp_name']);
        if ($fileContents !== false) {
            if (preg_match('/<\?(php|=|[\s\S]*?language\s*=\s*["\']?php)/i', $fileContents)) {
                $errors[] = "File '{$f['name']}' terdeteksi mengandung kode skrip eksekusi dan ditolak demi keamanan.";
                continue;
            }
        }

        $uuid = generate_uuid();
        $isHeic = ($extOriginal === 'heic' || $extOriginal === 'heif' || str_contains($mimeType, 'heic') || str_contains($mimeType, 'heif'));

        if ($isHeic) {
            // Validasi tambahan header HEIC
            $handle = @fopen($f['tmp_name'], 'rb');
            $headerBytes = $handle ? fread($handle, 32) : '';
            if ($handle) fclose($handle);

            if (!str_contains($headerBytes, 'ftyp') && !str_contains($mimeType, 'heic') && !str_contains($mimeType, 'heif')) {
                $errors[] = "File '{$f['name']}' terdeteksi bukan file HEIC/HEIF yang sah.";
                continue;
            }

            // Format HEIC: simpan sementara -> panggil converter server ke WebP
            $tmpHeicPath = "{$uploadDir}/{$uuid}_tmp.heic";
            $finalWebpPath = "{$uploadDir}/{$uuid}.webp";

            if (!move_uploaded_file($f['tmp_name'], $tmpHeicPath)) {
                $errors[] = "Gagal memindahkan file upload {$f['name']}.";
                continue;
            }

            $converted = convert_heic_to_webp_server($tmpHeicPath, $finalWebpPath);

            if (file_exists($tmpHeicPath)) {
                @unlink($tmpHeicPath);
            }

            if (!$converted || !file_exists($finalWebpPath)) {
                $errors[] = "Gagal mengonversi file HEIC {$f['name']} di server.";
                continue;
            }

            $finalFileName = basename($finalWebpPath);
            $relativePath = "/uploads/{$pekerjaanId}/{$finalFileName}";
        } else {
            // Validasi Struktur Gambar menggunakan getimagesize()
            $imgInfo = @getimagesize($f['tmp_name']);
            if ($imgInfo === false) {
                $errors[] = "File '{$f['name']}' bukan merupakan file gambar yang valid.";
                continue;
            }

            $imageType = $imgInfo[2] ?? 0;
            $validTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
            if (!in_array($imageType, $validTypes, true)) {
                $errors[] = "File '{$f['name']}' memiliki tipe struktur gambar tidak sah.";
                continue;
            }

            // SELALU KONVERSI SEMUA GAMBAR (JPG, JPEG, PNG, WEBP) MENJADI FORMAT WEBP
            $gdImg = false;
            if (extension_loaded('gd')) {
                if ($imageType === IMAGETYPE_JPEG) {
                    $gdImg = @imagecreatefromjpeg($f['tmp_name']);
                } elseif ($imageType === IMAGETYPE_PNG) {
                    $gdImg = @imagecreatefrompng($f['tmp_name']);
                } elseif ($imageType === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
                    $gdImg = @imagecreatefromwebp($f['tmp_name']);
                }
            }

            $convertedToWebp = false;
            if ($gdImg !== false && function_exists('imagewebp')) {
                $finalFileName = "{$uuid}.webp";
                $finalPath = "{$uploadDir}/{$finalFileName}";

                // Pertahankan alpha channel transparan untuk PNG/WEBP
                imagealphablending($gdImg, false);
                imagesavealpha($gdImg, true);

                if (imagewebp($gdImg, $finalPath, 85)) {
                    $convertedToWebp = true;
                }
                imagedestroy($gdImg);
            }

            if (!$convertedToWebp) {
                // Fallback jika GD / imagewebp tidak tersedia di server:
                // Simpan file dengan ekstensi terverifikasi hasil getimagesize()
                $fallbackExt = ($imageType === IMAGETYPE_PNG) ? 'png' : (($imageType === IMAGETYPE_WEBP) ? 'webp' : 'jpg');
                $finalFileName = "{$uuid}.{$fallbackExt}";
                $finalPath = "{$uploadDir}/{$finalFileName}";

                if (!move_uploaded_file($f['tmp_name'], $finalPath)) {
                    $errors[] = "Gagal menyimpan file {$f['name']}.";
                    continue;
                }
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
