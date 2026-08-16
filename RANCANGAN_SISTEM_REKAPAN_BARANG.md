# Rancangan Sistem — Sistem Rekapan Barang untuk Bisnis Event

**Stack:** HTML + Tailwind CSS + Vanilla JavaScript (frontend) · PHP native (backend) · MySQL (database) · Python (khusus konverter HEIC, dipanggil dari PHP)

---

## 1. Ringkasan Sistem

Sistem berbasis web untuk mencatat rekapan barang dalam proyek/pekerjaan event. Setiap pekerjaan ditangani oleh satu akun pekerja yang menginput data barang (foto, kuantitas, keterangan). Terdapat 3 level role dengan hak akses berbeda, sistem sesi eksklusif (satu akun hanya bisa dipakai satu orang dalam satu waktu), dan auto-logout berbasis idle timeout.

---

## 2. Role & Hak Akses

| Fitur | Superadmin | Admin | Akun Pekerja |
|---|---|---|---|
| Login | ✅ | ✅ | ✅ |
| Kelola akun (buat/hapus/reset password) | ✅ | ❌ | ❌ |
| Kelola pekerjaan (buat/edit/hapus) | ✅ | ❌ | ❌ |
| Lihat seluruh data semua pekerjaan | ✅ | ✅ | ❌ (hanya milik sendiri) |
| Edit seluruh data semua pekerjaan | ✅ | ❌ | ❌ |
| Tambah/edit data milik pekerjaan sendiri | — | ❌ | ✅ |
| Export data ke Excel/Word | ✅ | ❌ | ❌ |
| Auto-logout 30 menit idle | ✅ | ✅ | ✅ |

### 2.1 Superadmin
- Mengatur jumlah akun pekerja dan pekerjaannya (create/update/delete).
- Mengatur password akun-akun (reset manual).
- Mengelola data akun admin.
- Bisa export data ke Excel/Word.
- Tidak melakukan input data barang.

### 2.2 Admin
- Login → dashboard lihat **seluruh** data dari semua pekerjaan (read-only).
- **Tidak bisa** mengedit data apa pun.
- **Tidak bisa** export ke Excel/Word.
- Tidak bisa mengelola akun/password.

### 2.3 Akun Pekerja
- Setiap akun terikat ke **1 pekerjaan** spesifik.
- Login → langsung diarahkan ke halaman **"Data Pekerjaan Saya"** — berisi list barang yang sudah pernah diinput untuk pekerjaan tersebut (bukan form kosong).
- Dari halaman list ini bisa:
  - **Tambah barang baru**: upload foto (bisa >1 foto per barang), kuantitas, keterangan.
  - **Edit barang yang sudah ada**: ubah kuantitas/keterangan, tambah/hapus foto pada item yang sudah diinput sebelumnya.
- Hanya bisa melihat & mengedit data milik pekerjaannya sendiri — **wajib divalidasi di backend PHP di setiap request** (cek kepemilikan data berdasarkan session, bukan hanya disembunyikan di UI).

---

## 3. Model Data (Skema MySQL)

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('superadmin','admin','pekerja') NOT NULL,
  session_token VARCHAR(255) NULL,
  last_activity_at DATETIME NULL,
  failed_login_count INT DEFAULT 0,
  locked_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE pekerjaan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_pekerjaan VARCHAR(255) NOT NULL,
  user_id INT NOT NULL,
  dibuat_oleh INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
);

CREATE TABLE barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pekerjaan_id INT NOT NULL,
  kuantitas INT NOT NULL,
  keterangan TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pekerjaan_id) REFERENCES pekerjaan(id)
);

CREATE TABLE foto_barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  barang_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  format_asli VARCHAR(10),
  nama_file_server VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  aksi VARCHAR(100) NOT NULL,
  detail TEXT NULL,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

> Semua akses ke tabel-tabel ini di PHP **wajib** menggunakan PDO dengan prepared statement (`$pdo->prepare(...)->execute([...])`) — tidak boleh ada raw string concatenation ke query SQL, untuk mencegah SQL injection.

---

## 4. Alur Autentikasi & Session (PHP native session + kolom DB)

Karena PHP punya `$_SESSION` bawaan, tapi requirement "1 akun cuma boleh dipakai 1 orang dalam satu waktu" butuh kontrol lintas-device — solusinya kombinasi: `$_SESSION` untuk state di browser + kolom `session_token` di tabel `users` sebagai sumber kebenaran (source of truth) yang divalidasi di server tiap request.

### 4.1 Login (`login.php`)
1. Cek `locked_until` — kalau masih terkunci, tolak dengan pesan jelas.
2. Verifikasi password dengan `password_verify()` (PHP native, setara bcrypt).
3. Kalau gagal: increment `failed_login_count`, catat ke `audit_log`. Kalau mencapai 5x berturut-turut → set `locked_until` = NOW() + 15 menit.
4. Kalau berhasil, cek `session_token` di tabel `users`:
   - **NULL** → izinkan login. Generate token acak (`bin2hex(random_bytes(32))`), simpan ke `session_token`, set `last_activity_at` = NOW(), simpan juga ke `$_SESSION['token']`.
   - **Ada token**, tapi `NOW() - last_activity_at > 30 menit` → sesi lama dianggap mati otomatis, izinkan login, overwrite token.
   - **Ada token**, masih dalam 30 menit terakhir → **tolak login**, pesan "Akun sedang digunakan oleh perangkat/pengguna lain."
5. Reset `failed_login_count` ke 0.
6. Regenerate session ID PHP (`session_regenerate_id(true)`) setelah login berhasil — mencegah session fixation.
7. Redirect sesuai role.

### 4.2 Validasi Sesi di Setiap Halaman/Endpoint (`middleware/auth.php`, di-include di setiap file)
- Cek `$_SESSION['token']` cocok dengan `session_token` di DB untuk user tsb.
- Cek `NOW() - last_activity_at <= 30 menit`. Kalau lewat → set `session_token = NULL` di DB, `session_destroy()`, redirect ke login.
- Kalau valid → update `last_activity_at` = NOW() (dari request normal ATAU dari heartbeat, lihat 4.3).
- Cek role sesuai halaman yang diakses (role-check per halaman/endpoint).

### 4.3 Idle Timeout 30 Menit Berbasis Aktivitas UI (berlaku semua role)
- Definisi "aktivitas" mencakup interaksi UI: `mousemove`, `keydown`, `scroll`, `click`, `touchstart` — bukan hanya request ke server.
- Di JavaScript (vanilla JS), pasang event listener global. Setiap ada aktivitas, reset timer idle di sisi client.
- Kirim heartbeat via `fetch('ping.php')` secara berkala (misal tiap 2–5 menit), **hanya jika** ada aktivitas terdeteksi sejak heartbeat terakhir — `ping.php` meng-update `last_activity_at` di DB.
- Tampilkan modal peringatan "Sesi akan berakhir dalam 1 menit" sebelum auto-logout terjadi (dihitung di JS berdasarkan waktu idle lokal).
- Gunakan `navigator.sendBeacon('logout.php')` pada event `beforeunload` untuk mempercepat pelepasan sesi saat tab/browser ditutup.

### 4.4 Logout Manual (`logout.php`)
- Set `session_token = NULL`, `last_activity_at = NULL` di DB.
- `session_unset()` + `session_destroy()`.
- Catat ke `audit_log`.

---

## 5. Upload & Konversi Gambar (PHP + Python)

- Format diterima: **HEIC, PNG, JPG, JPEG**.
- Satu barang bisa memiliki **lebih dari satu foto**.
- **PHP tidak punya dukungan native yang baik untuk decode HEIC**, jadi alurnya:
  1. PHP terima file upload via `$_FILES`, validasi awal (ukuran, MIME type via `finfo_file()` / magic bytes — bukan hanya ekstensi).
  2. Simpan sementara ke folder `tmp_uploads/` dengan nama UUID.
  3. Kalau format HEIC → PHP panggil script Python lewat `shell_exec()` atau `proc_open()`, script Python pakai library `pillow-heif` (atau `pyheif`) untuk convert ke JPEG/WebP.
  4. Kalau PNG/JPG/JPEG → bisa langsung dipakai, atau tetap diproses ulang lewat Python/GD/Imagick untuk normalisasi (opsional, misal resize/compress).
  5. Simpan hasil akhir ke folder permanen `uploads/{pekerjaan_id}/`, catat path ke tabel `foto_barang`.
- **Wajib** validasi MIME type via magic bytes (`finfo_file()` di PHP), bukan hanya ekstensi nama file — cegah file berbahaya menyamar sebagai gambar.
- Nama file di-generate ulang oleh server (UUID) — **jangan pernah pakai nama file asli** dari user (cegah path traversal & overwrite).
- Batasi ukuran maksimum per file (misal 10MB), validasi dimensi gambar sebelum diproses (cegah decompression bomb).
- Folder upload diletakkan **di luar document root yang bisa dieksekusi PHP**, atau pastikan folder upload di-disable eksekusi script (`.htaccess`: `php_flag engine off` di folder uploads) — supaya kalaupun ada file berbahaya lolos validasi, tidak bisa dieksekusi sebagai script.

### Contoh pemanggilan Python dari PHP:
```php
$output = shell_exec(escapeshellcmd("python3 convert_heic.py " . escapeshellarg($tmpPath) . " " . escapeshellarg($outputPath)));
```
> Gunakan `escapeshellarg()` untuk setiap parameter yang berasal dari input user — mencegah command injection.

---

## 6. Fitur Export (Excel/Word)

- Hanya dapat diakses oleh **superadmin** (dicek di `middleware/auth.php` sebelum masuk ke `export.php`).
- Library PHP:
  - **PhpSpreadsheet** (via Composer) untuk generate Excel.
  - **PhpWord** (via Composer) untuk generate Word.
- Generate on-demand berdasarkan pekerjaan yang dipilih (atau seluruh data).
- Isi export: nama pekerjaan, daftar barang (kuantitas, keterangan), referensi foto (link path, atau embed langsung ke file kalau dikonfirmasi client perlu).

---

## 7. Keamanan Sistem (Spesifik untuk PHP + MySQL)

### 7.1 SQL Injection
- **Wajib PDO prepared statement** di semua query — tidak ada `mysqli_query()` dengan string concatenation langsung dari input user.

### 7.2 XSS
- Sanitize input teks (`keterangan`) dengan `htmlspecialchars()` saat output ke HTML, dan/atau `strip_tags()` saat simpan.
- Karena sistem ini bukan SPA framework yang auto-escape (seperti React), **setiap kali menampilkan data user di HTML wajib manual escape** dengan `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`.
- Set header `Content-Security-Policy` di PHP (`header("Content-Security-Policy: ...")`).
- Cookie session: set `session.cookie_httponly = 1`, `session.cookie_secure = 1` (HTTPS), `session.cookie_samesite = Strict` di `php.ini` atau `session_set_cookie_params()`.

### 7.3 CSRF (penting untuk PHP form-based, sering terlewat)
- Setiap form (POST) wajib menyertakan **CSRF token** tersembunyi (`<input type="hidden" name="csrf_token">`), digenerate per sesi, divalidasi di backend sebelum memproses request.

### 7.4 DDoS / Brute Force
- Rate limiting sederhana bisa diimplementasi manual di PHP (hitung request per IP dalam rentang waktu, simpan di tabel/cache), atau lebih baik di level web server (Nginx `limit_req`) / Cloudflare di depan aplikasi.
- Lockout akun setelah 5x gagal login berturut-turut (15 menit) — sudah dijelaskan di bagian 4.1.

### 7.5 File Upload
- Validasi MIME type via magic bytes (`finfo_file()`).
- Hanya izinkan HEIC/PNG/JPG/JPEG, tolak tegas tipe lain.
- Nama file di-generate ulang server (UUID).
- Folder upload dengan eksekusi script dimatikan.
- Validasi command injection saat memanggil Python (`escapeshellarg()`).

### 7.6 Authorization (Anti-IDOR)
- Setiap file PHP yang mengakses data spesifik (`barang.php?id=`, dst.) **wajib** query dengan `WHERE pekerjaan_id = (pekerjaan milik user yang sedang login)` — bukan hanya `WHERE id = ?` — supaya user tidak bisa akses data pekerjaan lain lewat manipulasi ID di URL.
- `middleware/auth.php` di-include di **setiap** file endpoint (bukan opsional), untuk konsistensi role-check.

### 7.7 Lain-lain
- HTTPS wajib (redirect otomatis dari HTTP di level server/`.htaccess`).
- Kredensial DB & secret disimpan di file config di luar document root (atau environment variable), bukan hardcode di file yang bisa diakses publik.
- Audit log untuk aktivitas sensitif (login gagal, akses admin, export data).
- Backup database MySQL rutin (`mysqldump` terjadwal via cron).
- Matikan `display_errors` di production (`php.ini`) supaya error PHP tidak membocorkan struktur internal ke user.

---

## 8. Struktur Endpoint/Halaman PHP (Rancangan Awal)

```
/public
  /auth
    login.php
    logout.php
    ping.php              (heartbeat idle timeout)
  /pekerja
    index.php              (list barang milik pekerjaan sendiri)
    tambah_barang.php
    edit_barang.php
    hapus_foto.php
  /admin
    dashboard.php           (lihat semua data, read-only)
  /superadmin
    kelola_akun.php
    kelola_pekerjaan.php
    export.php               (Excel/Word)
  /middleware
    auth.php                 (session check + role check, di-include tiap file)
  /uploads                   (folder upload, eksekusi script dimatikan)
  /assets
    /css (Tailwind build)
    /js
      activity-tracker.js    (idle timeout logic)
      main.js
/scripts
  convert_heic.py             (dipanggil dari PHP)
/config
  database.php                (koneksi PDO, kredensial via env/file di luar document root)
```

> Semua file di `/pekerja`, `/admin`, `/superadmin` wajib `require_once '../middleware/auth.php';` di baris paling atas.

---

## 9. Rekomendasi Tools & Library

| Kebutuhan | Rekomendasi |
|---|---|
| Frontend | HTML + Tailwind CSS (via CDN atau build lokal) + Vanilla JavaScript (fetch API untuk komunikasi ke PHP) |
| Backend | PHP native (disarankan PHP 8.x) |
| Database | MySQL, akses via PDO |
| Password hashing | `password_hash()` / `password_verify()` (PHP native, bcrypt) |
| Konversi HEIC | Python + `pillow-heif`, dipanggil dari PHP via `shell_exec()`/`proc_open()` |
| Export Excel | PhpSpreadsheet (Composer) |
| Export Word | PhpWord (Composer) |
| Dependency manager PHP | Composer |
| Web server | Apache (dengan `.htaccess`) atau Nginx |

---

## 10. Hal yang Masih Perlu Dikonfirmasi ke Client

- Apakah foto barang perlu ikut ter-embed di file export Excel/Word, atau cukup berupa link/referensi?
- Apakah 1 akun pekerja bisa suatu saat dipindah ke pekerjaan lain (reassign), atau permanen 1 akun = 1 pekerjaan seumur hidup akun tsb?
- Apakah perlu fitur hapus barang (delete), atau hanya create + edit seperti yang dibahas sejauh ini?
- Batas ukuran maksimum upload foto (disarankan 10MB, perlu konfirmasi apakah sesuai kebutuhan client).
- Apakah Python (untuk konversi HEIC) tersedia terinstal di server hosting yang akan dipakai (perlu dicek dukungan `shell_exec` di hosting — beberapa shared hosting mematikan fungsi ini).
