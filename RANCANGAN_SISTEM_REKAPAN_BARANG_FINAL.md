# Rancangan & Dokumentasi Sistem Final — Sistem Rekapan Barang Event

**Versi:** 2.0 (Hasil Akhir Produksi / Final Implemented State)  
**Tanggal Update:** 26 Agustus 2026  
**Stack Teknologi:**  
- **Frontend:** HTML5 + Tailwind CSS (via CDN) + Inter Google Font + Vanilla JavaScript + `heic2any` (Client-side HEIC Converter)
- **Backend:** PHP Native 8.x (Hostinger / Apache / LiteSpeed Optimized)
- **Database:** MySQL / MariaDB via PDO Prepared Statements
- **Image Engine:** Hybrid Multi-Tier Pipeline (Client JS `heic2any` → Server `Imagick` → Python CLI `pillow-heif` → Server `GD WebP`)
- **Document Engine:** PhpOffice `PhpSpreadsheet` (Excel `.xlsx`) & `PhpWord` (Word `.docx`) via Composer

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
- Berkuasa penuh mengelola akun pengguna (`admBerikut adalah daftar fitur berdasarkan hasil kebutuhan meeting klien kamu, dikelompokkan sesuai dengan peran penggunanya (Role):

1. Fitur Utama Admin
Manajemen Data Barang (Master Items)

Menambah data barang baru (nama barang, deskripsi, stok awal, dan upload foto barang).

Mengubah/mengedit detail barang dan memperbarui stok jika ada barang baru masuk dari luar.

Menghapus data barang yang sudah tidak digunakan.

Otomatisasi konversi foto barang yang di-upload agar efisien dan hemat ruang penyimpanan.

Manajemen Pengguna (User Management)

Membuat dan mengelola akun login untuk setiap pekerja di divisi logistik.

Menentukan peran akun (Admin atau Pekerja Logistik).

Pemantauan & Laporan (Dashboard & Audit Log)

Dashboard Stok Realtime: Melihat sisa stok barang terkini secara langsung.

Riwayat Serah Terima: Melihat rekap seluruh aktivitas penyerahan barang (siapa yang menyerahkan, siapa yang menerima, barang apa, berapa jumlahnya, dan kapan waktunya).

Filter/pencarian riwayat berdasarkan tanggal, nama pekerja, atau nama barang.

2. Fitur Pekerja Logistik (Petugas Lapangan)
Katalog & Cek Stok Barang

Melihat daftar barang beserta foto dan sisa stok yang tersedia saat itu untuk memastikan ketersediaan barang sebelum diserahkan.

Form Lapor Serah Terima

Memilih barang yang akan diserahkan.

Memilih nama pekerja penerima (pihak yang mengambil/menerima barang).

Memasukkan jumlah barang yang diserahkan.

Menambahkan catatan opsional (misal: kondisi barang atau peruntukan acara).

Validasi Penyerahan Otomatis

Sistem menolak penyerahan jika jumlah barang yang dimasukkan melebihi sisa stok yang ada.

Sistem langsung menguraikan/memotong jumlah stok barang secara otomatis begitu laporan penyerahan dikirim.

Riwayat Penyerahan Pribadi

Melihat daftar transaksi penyerahan yang pernah dilakukan atau diterima oleh pekerja tersebut.

3. Fitur Keamanan & Sistem Dasar
Halaman Login & Akses Peran

Pembatasan akses halaman (Pekerja hanya bisa mengakses form serah terima dan katalog, sedangkan Admin bisa mengakses kontrol penuh/manajemen stok & user).

Sistem Notifikasi Ringkas

Pesan konfirmasi saat laporan serah terima berhasil dikirim beserta update sisa stok barang.in` dan `pekerja`).
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

## 10. Ringkasan Perubahan dari Rancangan Awal ke Rancangan Final

| Topik / Fitur | Rancangan Awal (Draft) | Hasil Akhir Produksi (Final State) |
|---|---|---|
| **Struktur Tabel Barang** | `kuantitas INT NOT NULL`, Tanpa `nama_barang` | `nama_barang VARCHAR(255)` & `kuantitas VARCHAR(255)` (Mendukung unit teks seperti `"10 Unit"`) |
| **Pencegahan Sesi Ganda** | Hanya berbasis polling manual | Strict DB Token Check + Inactivity Timeout 15 Menit + Fitur **Reset Sesi** oleh Superadmin |
| **Pipeline Konversi HEIC** | Hanya PHP panggil Python CLI | **Hybrid 4-Tier Engine**: Client JS `heic2any` → Server `Imagick` → Python CLI → Server `GD WebP` |
| **Redirect Post-Submit** | Standard HTTP 302 Header | `response_success_redirect()` (Anti Cookie Drop pada LiteSpeed/Hostinger Web Server) |
| **Export Excel & Word** | Wacana dasar link / embed | Full Feature: Auto vertical photo embedding, dynamic column toggles, custom photo scaling |
| **Fitur Hapus Barang & Foto** | Belum didefinisikan jelas | Lengkap dengan Anti-IDOR check di backend PHP |
| **Responsif Mobile** | Layout dasar | Layout responsif penuh dengan Dual-View (Table pada Desktop, Card View pada Smartphone) |

---

*Dokumen rancangan final ini disusun secara komprehensif untuk merefleksikan seluruh arsitektur, keamanan, dan fungsionalitas sistem yang berjalan pada produksi.*
