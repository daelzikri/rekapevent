# Rancangan & Dokumentasi Sistem Final — Sistem Rekapan Barang Event

**Versi:** 2.2 (Hasil Akhir Produksi + Dual Storage Google Drive — 100% Gratis)  
**Tanggal Update:** 30 Agustus 2026  
**Stack Teknologi:**  
- **Frontend:** HTML5 + Tailwind CSS (via CDN) + Inter Google Font + Vanilla JavaScript + `heic2any` (Client-side HEIC Converter)
- **Backend:** PHP Native 8.x (Hostinger / Apache / LiteSpeed Optimized)
- **Database:** MySQL / MariaDB via PDO Prepared Statements
- **Image Engine:** Hybrid Multi-Tier Pipeline (Client JS `heic2any` → Server `Imagick` → Python CLI `pillow-heif` → Server `GD WebP`)
- **Document Engine:** PhpOffice `PhpSpreadsheet` (Excel `.xlsx`) & `PhpWord` (Word `.docx`) via Composer
- **Cloud Backup:** Google Drive API v3 via `google/apiclient`, class `DriveUploader` reusable, sinkronisasi asynchronous (CLI + HTTP cron), OAuth2 refresh token (scope `drive.file`, gratis tanpa Workspace)

---

## 1. Ringkasan Sistem

Sistem Rekapan Barang Event adalah aplikasi berbasis web yang dirancang khusus untuk manajemen dan pencatatan inventaris barang pada proyek/pekerjaan event. 

Sistem ini menerapkan arsitektur terisolasi di mana setiap akun pekerja terikat secara eksklusif ke **satu pekerjaan event tertentu**. Aplikasi dilengkapi dengan proteksi **Single Active Session** (satu akun hanya dapat diakses satu perangkat pada satu waktu), **Inactivity Timeout 15 Menit**, **Account Lockout Brute-Force Protection**, validasi **Anti-IDOR** (*Insecure Direct Object Reference*), serta pipeline konversi foto otomatis (termasuk format HEIC dari iPhone) menjadi format terkompresi WebP/JPEG.

---

## 2. Role & Matriks Hak Akses

Sistem membagi pengguna ke dalam 3 role dengan batasan wewenang yang tegas:

| Fitur / Modul | Superadmin | Admin | Akun Pekerja |
|---|:---:|:---:|:---:|
| Login & Logout | ✅ | ✅ | ✅ |
| Kelola Akun (Buat, Hapus, Reset Pass, Unlock, Reset Sesi) | ✅ | ❌ | ❌ |
| Kelola Pekerjaan (Buat Event, Assign Pekerja, Edit, Hapus) | ✅ | ❌ | ❌ |
| Monitoring Dashboard Seluruh Data Pekerjaan (Read-Only) | ✅ | ✅ | ❌ |
| Input Data Barang (Nama, Qty Teks, Keterangan, Multi-Foto) | ❌ | ❌ | ✅ (Hanya milik sendiri) |
| Edit Data Barang & Tambah/Hapus Foto Terlampir | ❌ | ❌ | ✅ (Hanya milik sendiri) |
| Hapus Barang Milik Pekerjaan Sendiri | ❌ | ❌ | ✅ (Hanya milik sendiri) |
| Export Data Rekapan ke Excel (.xlsx) & Word (.docx) | ✅ | ❌ | ❌ |
| Single Active Session (1 Akun = 1 Device) | ✅ | ✅ | ✅ |
| Inactivity Auto-Logout (15 Menit) | ✅ | ✅ | ✅ |

### 2.1 Superadmin
- Berkuasa penuh mengelola akun pengguna (`admin` dan `pekerja`).
- Dapat melakukan **Reset Password**, **Unlock Akun** (yang terkunci akibat 5x salah password), dan **Reset Sesi** (memaksa logout akun yang aktif di perangkat lain).
- Mengatur pembuatan proyek event dan mengikat (*assign*) 1 Akun Pekerja ke 1 Pekerjaan.
- Mengakses modul **Export Rekapan Data** ke file Excel (`.xlsx`) dan Word (`.docx`) lengkap dengan foto barang yang di-embed secara vertikal.
- Memiliki akses ke **Dashboard View Read-Only** untuk memantau seluruh data dari semua event.

### 2.2 Admin
- Pengawas / Monitor internal event.
- Login langsung diarahkan ke **Dashboard Monitoring** (`/admin/dashboard.php`).
- Mampu melakukan pencarian barang dan filter berdasarkan Pekerjaan Event.
- Akses bersifat **Read-Only** (tidak bisa menambah, mengubah, menghapus data, mengelola akun, maupun melakukan export).

### 2.3 Akun Pekerja
- Setiap akun pekerja terikat pada **1 Pekerjaan Event**.
- Setelah login, pekerja langsung diarahkan ke halaman **"Data Pekerjaan Saya"** (`/pekerja/index.php`).
- Dapat melakukan:
  - **Tambah Barang Baru**: Mengisi Nama Barang, Kuantitas (bebas teks seperti `"150 Pcs"`, `"10 Unit"`), Keterangan Detail, dan Multi-upload Foto (JPG, PNG, WEBP, HEIC).
  - **Edit Barang**: Memperbarui informasi barang dan menambah foto baru.
  - **Hapus Foto**: Menghapus foto tertentu per item barang.
  - **Hapus Barang**: Menghapus item barang milik pekerjaannya sendiri.
- **Validasi Keamanan Anti-IDOR**: Seluruh request ke backend PHP divalidasi ketat bahwa `pekerjaan_id` barang yang diakses sesuai dengan `user_id` pada sesi login.

---

## 3. Model Data & Skema Database (MySQL / MariaDB)

Database bernama `rekapan_barang` (atau `u602243872_rekapevent` pada hosting server). Seluruh tabel menggunakan engine **InnoDB** dengan `utf8mb4_unicode_ci`.

### 3.1 DDL Database Final (`schema.sql`)

```sql
CREATE DATABASE IF NOT EXISTS `rekapan_barang` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rekapan_barang`;

-- 1. Tabel Users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin','admin','pekerja') NOT NULL,
  `session_token` VARCHAR(255) NULL,
  `last_activity_at` DATETIME NULL,
  `failed_login_count` INT DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Pekerjaan (1 Pekerja = 1 Job)
CREATE TABLE `pekerjaan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_pekerjaan` VARCHAR(255) NOT NULL,
  `user_id` INT NOT NULL,
  `dibuat_oleh` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pekerjaan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pekerjaan_dibuat_oleh` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Barang (Kuantitas VARCHAR untuk fleksibilitas unit)
CREATE TABLE `barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pekerjaan_id` INT NOT NULL,
  `nama_barang` VARCHAR(255) NOT NULL,
  `kuantitas` VARCHAR(255) NOT NULL,
  `keterangan` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_barang_pekerjaan` FOREIGN KEY (`pekerjaan_id`) REFERENCES `pekerjaan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Foto Barang
CREATE TABLE `foto_barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `format_asli` VARCHAR(10),
  `nama_file_server` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_foto_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Audit Log
CREATE TABLE `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `aksi` VARCHAR(100) NOT NULL,
  `detail` TEXT NULL,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Fitur Auto-Migration Database (`config/database.php`)
Aplikasi dilengkapi skrip migrasi otomatis saat koneksi database diinisialisasi:
1. Memastikan kolom `kuantitas` pada tabel `barang` bertipe `VARCHAR(255)`.
2. Memastikan kolom `nama_barang` bertipe `VARCHAR(255)` berada setelah `pekerjaan_id`.

---

## 4. Alur Autentikasi & Keamanan Sesi

### 4.1 Login (`/auth/login.php`)
1. **Pemeriksaan Account Lockout**: Sistem mengecek `locked_until`. Jika waktu sekarang < `locked_until`, login ditolak dengan notifikasi sisa waktu kunci.
2. **Verifikasi Password**: Menggunakan `password_verify()` dengan algoritma Bcrypt.
   - Jika gagal: `failed_login_count` bertambah. Jika mencapai 5 kali berturut-turut, `locked_until` di-set 15 menit ke depan dan dicatat di `audit_log`.
3. **Proteksi Single Active Session (1 Akun = 1 Device)**:
   - Jika akun memiliki `session_token` di DB dan `last_activity_at` masih dalam kurun waktu **15 menit terakhir**, maka **login ditolak**.
   - Menampilkan pesan: *"Akun sedang aktif digunakan di perangkat/browser lain."*
4. **Sesi Berhasil**:
   - Sistem mereset `failed_login_count = 0` dan `locked_until = NULL`.
   - Mengenerate token acak 64 karakter hex: `bin2hex(random_bytes(32))`.
   - Token disimpan di DB (`users.session_token`) dan `$_SESSION['session_token']`.
   - Redirect pengguna sesuai role menggunakan fungsi kompatibilitas LiteSpeed/Hostinger (`response_success_redirect()`).

### 4.2 Middleware Autentikasi (`/middleware/auth.php`)
Di-include di baris paling atas pada setiap file endpoint terproteksi.
- **Token Verification**: Membandingkan `$_SESSION['session_token']` dengan `session_token` di tabel `users`. Jika berbeda (misal di-reset oleh Superadmin atau di-login dari tempat lain), sesi langsung dibatalkan.
- **Inactivity Timeout Enforcement (15 Menit)**:
  - Cek `time() - last_activity_at > 900 detik (15 menit)`.
  - Jika melewati batas, token di DB di-NULL-kan, sesi dibersihkan, dan user didireksi ke login dengan pesan timeout.
  - Jika aktivitas valid dan selisih waktu > 30 detik, `last_activity_at` di DB di-update otomatis (`NOW()`).

### 4.3 Heartbeat Ping Endpoint (`/auth/ping.php`)
- Dipanggil oleh frontend untuk menjaga sesi tetap aktif (*keep-alive*) selama pengguna membuka halaman aplikasi dan melakukan aktivitas UI.

### 4.4 Kompatibilitas Redirect LiteSpeed / Hostinger (`config/helpers.php`)
Untuk mencegah *cookie drop* pada HTTP 302 POST Redirect di server LiteSpeed/Hostinger, aplikasi menggunakan `response_success_redirect()` yang menampilkan halaman konfirmasi sukses interaktif dengan JavaScript `window.location.href` auto-redirect (700ms delay).

---

## 5. Pipeline Multi-Upload & Konversi Gambar (Hybrid 4-Tier)

Mendukung upload banyak foto sekaligus, termasuk format khas Apple iPhone (**HEIC / HEIF**).

```
[User Input File (HEIC / PNG / JPG)]
       │
       ├── Tier 1 (Client JS): Preview & Konversi HEIC via heic2any.js di Browser
       │
       └── Form Submit ke PHP Server (upload_helper.php)
              │
              ├── Validasi Ukuran (Max 10MB)
              ├── Validasi Filename Scrutiny (Anti Dangerous/Double Ext)
              ├── Validasi Magic Bytes (finfo_file MIME Check)
              ├── Validasi PHP Script Payload Regex Check inside Images
              │
              ├── Process HEIC (Server Fallback Pipeline):
              │     ├── Tier 2: Imagick PHP Extension (`setImageFormat('webp')`)
              │     ├── Tier 3: Python CLI (`scripts/convert_heic.py` via `pillow-heif`)
              │     └── Tier 4: GD Library Fallback (`imagewebp`)
              │
              └── Simpan File Akhir ke `/uploads/{pekerjaan_id}/{uuid}.webp`
```

### 5.1 Proteksi Keamanan File Upload
1. **Batas Ukuran**: Maksimal 10MB per file.
2. **Scrutiny Filename**: Mencegah nama file berbahaya (`.php`, `.phtml`, `.phar`, `.htaccess`, double extension seperti `foto.php.jpg`, null byte injection `%00`).
3. **Magic Bytes Validation**: Memeriksa header asli file menggunakan `finfo_file(FILEINFO_MIME_TYPE)`.
4. **Polyglot WebShell Protection**: Memindai isi file mentah dengan regex `preg_match('/<\?(php|=|...)/i')` untuk mendeteksi skrip PHP terselubung di dalam metadata gambar.
5. **Generasi Nama Server (UUID v4)**: Nama file asli dari user dibuang dan diganti dengan UUID v4 acak (misal: `550e8400-e29b-41d4-a716-446655440000.webp`).
6. **Eksekusi Script Dimatikan**: Folder `/public/uploads/` dilindungi oleh `.htaccess` dengan aturan `php_flag engine off` dan `SetHandler default-handler`.

---

## 6. Modul Export Data (Excel & Word)

Fitur khusus **Superadmin** di `/superadmin/export.php` untuk mengunduh dokumen rekapan resmi.

### 6.1 Export Microsoft Excel (`.xlsx`)
- Menggunakan library **PhpOffice\PhpSpreadsheet**.
- Designed dengan tema header **Indigo Blue**, font Inter/Calibri, border rapi, dan text wrapping pada kolom keterangan.
- **Embedded Vertical Photos**: Foto-foto barang disisipkan secara langsung (*Drawing Object*) ke dalam sel kolom Foto secara berurutan ke bawah (vertikal), dengan penyesuaian tinggi baris (*row height*) secara otomatis.
- **Pengaturan Fleksibel**: Superadmin dapat memilih ukuran foto (60px, 80px, 100px) dan mengaktifkan/mematikan kolom Nama Event dan PJ Input.

### 6.2 Export Microsoft Word (`.docx`)
- Menggunakan library **PhpOffice\PhpWord**.
- Format layout **A4 Portrait** dengan kalkulasi lebar kolom presisi (*twip width*) untuk mencegah tabel terpotong di margin kertas.
- Foto barang disisipkan di dalam sel tabel secara vertikal dengan border dan margin teratur.

---

## 7. Proteksi Keamanan Aplikasi

1. **SQL Injection (SQLi)**: 100% database query menggunakan **PDO Prepared Statements** (`$stmt->execute([...])`). Tidak ada kueri mentah dengan penggabungan string (*string concatenation*).
2. **Cross-Site Scripting (XSS)**: Seluruh output variabel ke HTML diawasi oleh helper `e($val)` yang membungkus `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
3. **Anti-IDOR (Insecure Direct Object Reference)**:
   - Akses data barang pada modul pekerja diikat ketat pada ID Pekerjaan milik pengguna:
     `WHERE b.id = :barang_id AND p.user_id = :user_id`
   - Pekerja tidak dapat melihat atau mengubah barang milik pekerjaan lain dengan memanipulasi ID di URL.
4. **Cross-Site Request Forgery (CSRF)**: Seluruh form POST dilengkapi dengan input tersembunyi `csrf_token` yang digenerate per sesi.

---

## 8. Struktur File & Folder Final Project

```
rekapevent/
├── RANCANGAN_SISTEM_REKAPAN_BARANG_FINAL.md  # File Rancangan Final (Dokumen Ini)
├── RANCANGAN_SISTEM_REKAPAN_BARANG.md        # File Rancangan Awal (Legacy)
├── schema.sql                                # DDL Database MySQL / MariaDB
├── seed.sql                                  # Data Awal (Account Superadmin Default)
├── composer.json                             # Dependensi Composer (PhpSpreadsheet & PhpWord)
├── composer.lock
│
├── config/                                   # Konfigurasi Core System
│   ├── database.php                          # Koneksi PDO & Auto-Migrations
│   ├── helpers.php                           # Helper XSS, Sesi, Redirect, & Audit Log
│   ├── csrf.php                              # Token CSRF Generator & Validator
│   └── upload_helper.php                     # UUID, Multi-Upload & Multi-Tier HEIC Converter
│
├── public/                                   # Document Root Web Server
│   ├── .htaccess                             # Mod_rewrite & Security Headers
│   ├── index.php                             # Main Entry & Role Router
│   ├── debug_session.php                     # Debugging Tool Sesi
│   │
│   ├── auth/                                 # Modul Autentikasi
│   │   ├── login.php                         # Form Login, Single Session & Lockout Check
│   │   ├── logout.php                        # Logout & Sesi Destroy
│   │   └── ping.php                          # Heartbeat Endpoint Sesi
│   │
│   ├── middleware/                           # Middleware Keamanan
│   │   └── auth.php                          # Inactivity Timeout & Role Protection
│   │
│   ├── pekerja/                              # Modul Pekerja Event
│   │   ├── index.php                         # Halaman "Data Pekerjaan Saya" & Delete Item
│   │   ├── tambah_barang.php                 # Form Tambah Barang & Live Preview HEIC
│   │   ├── edit_barang.php                   # Form Edit Barang & Tambah Foto
│   │   └── hapus_foto.php                    # Action Hapus Single Foto (Anti-IDOR)
│   │
│   ├── admin/                                # Modul Admin
│   │   └── dashboard.php                     # Monitoring Dashboard (Read-Only)
│   │
│   ├── superadmin/                           # Modul Superadmin
│   │   ├── kelola_pekerjaan.php              # Kelola Event & Assignment Pekerja
│   │   ├── kelola_akun.php                   # Kelola User, Reset Pass, Unlock, Reset Sesi
│   │   └── export.php                        # Generator Export Excel & Word + Embedded Images
│   │
│   └── uploads/                              # Direktori Penyimpanan Foto Event
│       └── .htaccess                         # Security: php_flag engine off
│
├── scripts/                                  # Skrip Pembantu Server
│   └── convert_heic.py                       # Python Script Konversi HEIC via pillow-heif
│
└── vendor/                                   # Vendor Library Composer (PhpOffice)
```

---

## 9. Panduan Konfigurasi & Deployment (Hostinger / cPanel)

### 9.1 Konfigurasi Database (`config/database.php`)
Sesuaikan kredensial database sesuai dengan server hosting:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'nama_db_anda');
define('DB_USER', 'user_db_anda');
define('DB_PASS', 'password_db_anda');
```

### 9.2 Import Database
1. Eksekusi `schema.sql` pada phpMyAdmin / MySQL CLI.
2. Eksekusi `seed.sql` untuk membuat akun Superadmin awal:
   - **Username:** `superadmin`
   - **Password:** `admin123` (segera ganti setelah login pertama).

### 9.3 Rekomendasi Ekstensi PHP Server
- PHP 8.1 / 8.2 / 8.3
- `pdo_mysql`
- `gd` (dengan support WebP & JPEG)
- `fileinfo` (untuk validasi magic bytes MIME type)
- `imagick` (opsional, untuk performa konversi HEIC lebih cepat di server)
- `zip` & `xml` (diperlukan oleh PhpSpreadsheet & PhpWord)

---

## 10. Fitur Baru (v2.2): Dual Storage — Sinkronisasi Foto ke Google Drive (100% Gratis, Tanpa Langganan)

Fitur tambahan agar setiap foto WebP yang tersimpan di server (`/public/uploads/`) juga otomatis tersalin ke Google Drive, sehingga foto memiliki 2 salinan (server lokal + cloud) sebagai backup. Rancangan ini mengadopsi pola arsitektur kode **`DriveUploader` class** yang reusable (folder-caching, retry-safe, fail-safe) yang sudah terbukti stabil di project lain — namun lapisan autentikasinya diganti agar **tidak membutuhkan Google Workspace atau biaya langganan apa pun**.

### 10.1 Keputusan Arsitektur & Alasan Perubahan dari Rancangan Sebelumnya

| Keputusan | Pilihan yang Diambil | Alasan |
|---|---|---|
| Metode autentikasi | **OAuth2 dengan 1 akun Gmail gratis khusus** + Refresh Token | Service Account **tidak bisa dipakai tanpa Google Workspace** — dikonfirmasi resmi oleh dokumentasi Google: *"Service accounts don't have storage quota and can't own any files. Instead, they must upload files and folders into shared drives, or use OAuth 2.0..."*. Shared Drive hanya tersedia di paket Workspace berbayar, dan client tidak mau ada biaya langganan apa pun — jadi OAuth2 adalah **satu-satunya opsi yang benar-benar gratis** |
| Scope OAuth | **`drive.file`** (bukan scope `drive` penuh) | Scope `drive.file` hanya memberi akses ke file/folder yang **dibuat oleh aplikasi itu sendiri** — cukup untuk kebutuhan backup foto, lebih aman (tidak bisa menyentuh file lain di akun Gmail tsb), dan **tidak termasuk kategori "restricted scope"** sehingga proses publish aplikasi jauh lebih sederhana (lihat 10.3) |
| Status publishing OAuth Consent Screen | **Wajib diset "In Production"**, bukan dibiarkan di "Testing" | Google otomatis meng-*expire* refresh token dalam **7 hari** jika status aplikasi masih "Testing"/belum diverifikasi. Untuk penggunaan personal (di bawah 100 pengguna) dengan scope `drive.file`, status bisa langsung diset "In Production" **tanpa proses verifikasi/review dari Google** — sehingga refresh token berlaku permanen tanpa biaya apa pun |
| Waktu sinkronisasi | **Asynchronous / Background Queue** via Cron Job (CLI + fallback HTTP trigger) | Upload ke Drive tidak memperlambat response saat pekerja mengunggah foto. Fallback HTTP trigger penting karena beberapa hosting shared (Hostinger dsb) kadang membatasi akses cron CLI |
| Arsitektur kode | **Class `DriveUploader` reusable** dengan folder-caching in-memory + persisten di DB | Menghindari pemanggilan API Google berulang-ulang untuk mencari/membuat folder yang sama, mempercepat proses cron, dan kode lebih mudah dirawat/dipakai ulang di project lain |
| Struktur folder di Drive | 1 folder induk → sub-folder otomatis per **nama pekerjaan/event** | Foto tidak menumpuk di satu folder besar, mudah ditelusuri manual jika perlu |

> ⚠️ **Catatan kuota**: Karena tetap menggunakan akun Gmail biasa, kuota penyimpanan yang dipakai adalah kuota gratis 15GB bawaan Gmail/Google Drive akun tsb (dibagi bersama Gmail & Google Foto jika ada). Sarankan client memakai **akun Gmail baru yang didedikasikan khusus** untuk sistem ini (bukan akun pribadi harian) — supaya 15GB itu murni untuk foto barang, dan mudah dipantau kapan mendekati penuh. Kalau nanti kuota 15GB gratis ini habis, solusinya cukup buat akun Gmail baru lagi (gratis) dan pindahkan folder induk — **tidak perlu upgrade berbayar** kalau memang ingin tetap gratis selamanya.

### 10.2 Perubahan Skema Database

```sql
ALTER TABLE `foto_barang`
  ADD COLUMN `gdrive_file_id` VARCHAR(150) NULL AFTER `nama_file_server`,
  ADD COLUMN `gdrive_view_link` VARCHAR(500) NULL AFTER `gdrive_file_id`,
  ADD COLUMN `gdrive_status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending' AFTER `gdrive_view_link`,
  ADD COLUMN `gdrive_retry_count` INT NOT NULL DEFAULT 0 AFTER `gdrive_status`,
  ADD COLUMN `gdrive_last_attempt_at` DATETIME NULL AFTER `gdrive_retry_count`;

-- Cache persisten folder Drive per pekerjaan (dipakai oleh DriveUploader::getOrCreateFolder agar tidak query API berulang)
CREATE TABLE `pekerjaan_gdrive_folder` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pekerjaan_id` INT NOT NULL UNIQUE,
  `gdrive_folder_id` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_gdrive_folder_pekerjaan` FOREIGN KEY (`pekerjaan_id`) REFERENCES `pekerjaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 10.3 Alur Setup Awal (One-Time, Dilakukan Manual oleh Superadmin/Developer — Seluruhnya Gratis)

1. Buat akun **Gmail baru** khusus untuk sistem ini (misal `backup.rekapanbarang@gmail.com`) — gratis, tanpa upgrade apa pun.
2. Buat project baru di [Google Cloud Console](https://console.cloud.google.com/) (gratis, tidak perlu kartu kredit untuk sekadar Drive API), aktifkan **Google Drive API**.
3. Di **OAuth Consent Screen**: pilih tipe **External**, isi info aplikasi seadanya, tambahkan scope **`drive.file`** saja (jangan pilih scope `drive` penuh — tidak perlu dan lebih berisiko).
4. Tambahkan akun Gmail khusus tadi sebagai **Test User**, lalu **ubah Publishing Status dari "Testing" ke "In Production"** — langkah ini krusial supaya refresh token tidak expired dalam 7 hari.
5. Buat kredensial **OAuth 2.0 Client ID** (tipe *Web application* atau *Desktop app*), unduh `client_secret.json`.
6. Jalankan script one-time `scripts/gdrive_get_refresh_token.php` — membuka URL otorisasi Google, login pakai akun Gmail khusus tadi, klik lewati peringatan "Google hasn't verified this app" (wajar untuk aplikasi personal use, aman karena kita sendiri yang membuat aplikasinya), izinkan akses, lalu Google mengembalikan **authorization code** yang ditukar otomatis oleh script menjadi **refresh token**.
7. Refresh token, `client_id`, `client_secret` disimpan di `config/google_drive.php` — dilindungi `.htaccess` (`Deny from all`) dan didaftarkan ke `.gitignore`.
8. Buat folder induk secara manual di Drive akun tsb (misal `"Rekapan Foto Event"`), catat `folder_id`-nya ke konfigurasi sebagai `DRIVE_ROOT_FOLDER_ID`.

### 10.4 Cetak Biru Modul Kode

**`config/google_drive.php`**
- Memuat autoloader Composer.
- Mendefinisikan `DRIVE_ROOT_FOLDER_ID`.
- Membaca `client_id`, `client_secret`, `refresh_token` dari file konfigurasi terlindungi.
- Mengembalikan instance `Google\Service\Drive` yang siap pakai, atau `null` secara elegan kalau kredensial belum diset (sistem utama tetap jalan normal tanpa fitur Drive).

**`services/DriveUploader.php`** — class reusable dengan method:
1. `getOrCreateFolder(string $parentId, string $folderName): string` — cek cache in-memory → cek tabel `pekerjaan_gdrive_folder` → kalau belum ada, cari/buat folder via API lalu simpan ke cache DB.
2. `upload(string $localFilePath, string $targetFolderId, string $fileName): array` — upload file fisik, kembalikan `['file_id' => ..., 'view_link' => ...]`.
3. `uploadFotoBarang(PDO $db, int $fotoId, string $relativePath, string $serverFileName): bool` — helper khusus untuk update status DB setelah upload foto barang.
4. `retryPending(PDO $db, int $limit = 20): array` — ambil foto berstatus `pending`/`failed` dengan `retry_count < 5`, upload ulang, update status.

**`scripts/gdrive_sync.php`** — entry point cron, mendukung:
- Eksekusi **CLI**: `php scripts/gdrive_sync.php`
- Eksekusi **HTTP** (fallback untuk hosting yang tidak leluasa dengan cron CLI): `GET /scripts/gdrive_sync.php?key=RAHASIA_ACAK` — request tanpa key yang benar langsung ditolak.
- Menulis log ringkas ke `logs/gdrive_sync.log`.

### 10.5 Alur Data Lengkap

**A. Saat foto baru diupload (synchronous lokal → asynchronous Drive)**
```
[Pekerja upload foto] → Konversi ke WebP → Simpan ke /public/uploads/{pekerjaan_id}/ (TIDAK BERUBAH dari alur sebelumnya)
       │
       └── Insert record ke `foto_barang` dengan gdrive_status = 'pending'
              (Response ke user selesai di sini — TIDAK menunggu proses Drive sama sekali)
```

**B. Cron job berkelanjutan (tiap 5–15 menit)**
```
[Cron Trigger] → DriveUploader::retryPending()
       │
       ├── Query: foto WHERE gdrive_status IN ('pending','failed') AND gdrive_retry_count < 5, LIMIT 20
       ├── Refresh access token dari refresh token tersimpan
       ├── Untuk tiap foto:
       │      ├── getOrCreateFolder() untuk pekerjaan terkait (pakai cache kalau sudah ada)
       │      ├── Upload file lokal ke folder Drive tsb
       │      ├── BERHASIL → update gdrive_file_id, gdrive_view_link, gdrive_status='success', gdrive_last_attempt_at=NOW()
       │      └── GAGAL → increment gdrive_retry_count; jika mencapai 5x → gdrive_status='failed' permanen + catat ke audit_log
       └── Tulis ringkasan hasil ke logs/gdrive_sync.log
```

### 10.6 Dashboard Monitoring Sinkronisasi (Superadmin)

Halaman baru `/superadmin/gdrive_status.php`:
- Indikator jumlah foto per status: `pending`, `success`, `failed`.
- Tombol **"Retry Manual"** untuk foto `failed` (reset `gdrive_retry_count = 0`).
- Link langsung ke `gdrive_view_link` per foto / folder Drive per pekerjaan untuk verifikasi manual.

### 10.7 Library & Dependency Tambahan (Seluruhnya Gratis/Open-Source)

- `google/apiclient:^2.15` (Google API PHP Client resmi) — via Composer, gratis.
- Cron job di cPanel/Hostinger: `*/10 * * * * php /path/ke/project/scripts/gdrive_sync.php >> /path/ke/project/logs/gdrive_sync.log 2>&1`

### 10.8 Pertimbangan Keamanan

- `config/google_drive.php` (berisi `client_secret` & `refresh_token`) dilindungi `.htaccess` (`Deny from all`) dan masuk `.gitignore` — tidak boleh bocor ke repository publik.
- Scope dibatasi ke `drive.file` saja (least privilege) — aplikasi hanya bisa menyentuh file yang dibuatnya sendiri, tidak bisa mengakses/menghapus file lain di akun Gmail tsb sekalipun kredensial bocor.
- Refresh token bisa langsung dicabut sewaktu-waktu lewat [Google Account — Third-party access](https://myaccount.google.com/permissions) tanpa perlu ganti password akun Gmail.
- Endpoint HTTP cron dilindungi query parameter secret key acak yang panjang.
- Seluruh proses Drive didesain **fail-safe**: kegagalan sinkronisasi tidak pernah membatalkan/roll-back data barang atau foto yang sudah tersimpan di server lokal — foto lokal tetap menjadi *source of truth* utama.

### 10.9 Perubahan Struktur Folder Project

```
config/
  └── google_drive.php               # client_id, client_secret, refresh_token, DRIVE_ROOT_FOLDER_ID (dilindungi .htaccess)
services/
  └── DriveUploader.php               # Class reusable: getOrCreateFolder, upload, uploadFotoBarang, retryPending
scripts/
  ├── gdrive_get_refresh_token.php    # Script one-time setup OAuth (dijalankan sekali saja)
  └── gdrive_sync.php                 # Cron job — mendukung CLI & HTTP trigger dengan secret key
public/superadmin/
  └── gdrive_status.php               # Dashboard monitoring status sinkronisasi
logs/
  └── gdrive_sync.log                 # Log hasil tiap eksekusi cron
```

### 10.10 Ringkasan: Kenapa Rancangan Ini Tidak Butuh Biaya Sama Sekali

| Komponen | Status Biaya |
|---|---|
| Akun Gmail khusus | Gratis |
| Google Cloud Project + Drive API | Gratis (tidak perlu billing account untuk penggunaan skala ini) |
| OAuth Consent Screen "In Production" tanpa verifikasi penuh | Gratis (berlaku untuk personal use <100 pengguna dengan scope non-sensitif seperti `drive.file`) |
| Kuota penyimpanan Drive | Gratis 15GB bawaan (cukup dipantau lewat dashboard, tinggal ganti akun baru kalau penuh) |
| Library `google/apiclient` | Open-source, gratis |
| Cron job | Sudah termasuk fasilitas hosting yang ada, tidak ada biaya tambahan |

---

## 11. Ringkasan Perubahan dari Rancangan Awal ke Rancangan Final

| Topik / Fitur | Rancangan Awal (Draft) | Hasil Akhir Produksi (Final State) |
|---|---|---|
| **Struktur Tabel Barang** | `kuantitas INT NOT NULL`, Tanpa `nama_barang` | `nama_barang VARCHAR(255)` & `kuantitas VARCHAR(255)` (Mendukung unit teks seperti `"10 Unit"`) |
| **Pencegahan Sesi Ganda** | Hanya berbasis polling manual | Strict DB Token Check + Inactivity Timeout 15 Menit + Fitur **Reset Sesi** oleh Superadmin |
| **Pipeline Konversi HEIC** | Hanya PHP panggil Python CLI | **Hybrid 4-Tier Engine**: Client JS `heic2any` → Server `Imagick` → Python CLI → Server `GD WebP` |
| **Redirect Post-Submit** | Standard HTTP 302 Header | `response_success_redirect()` (Anti Cookie Drop pada LiteSpeed/Hostinger Web Server) |
| **Export Excel & Word** | Wacana dasar link / embed | Full Feature: Auto vertical photo embedding, dynamic column toggles, custom photo scaling |
| **Fitur Hapus Barang & Foto** | Belum didefinisikan jelas | Lengkap dengan Anti-IDOR check di backend PHP |
| **Responsif Mobile** | Layout dasar | Layout responsif penuh dengan Dual-View (Table pada Desktop, Card View pada Smartphone) |
| **Penyimpanan Foto** | Hanya server lokal (`/public/uploads/`) | **Dual Storage**: Server lokal + Google Drive (sinkronisasi otomatis via background cron, OAuth2 refresh token) |

---

*Dokumen rancangan final ini disusun secara komprehensif untuk merefleksikan seluruh arsitektur, keamanan, dan fungsionalitas sistem yang berjalan pada produksi.*
