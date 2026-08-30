# Panduan Setup & Deploy: Fitur Backup Otomatis Google Drive (100% Gratis)

Panduan ini berisi langkah-langkah *step-by-step* untuk mengonfigurasi dan mengaktifkan fitur backup otomatis foto ke Google Drive pada **Sistem Rekapan Barang Event**.

---

## 1. Setup di Google Cloud Console (One-Time Setup)

> ⚠️ **Prinsip Bebas Biaya**: Solusi ini menggunakan OAuth 2.0 dengan **1 akun Gmail gratis biasa** (misal `backup.rekapanbarang@gmail.com`), tanpa perlu Google Workspace atau Service Account berbayar.

### Langkah 1: Buat Project & Aktifkan Drive API
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Login menggunakan akun Gmail yang didedikasikan khusus untuk sistem ini.
3. Klik dropdown project di bagian atas, pilih **New Project** (misal beri nama `Backup Rekapan Barang`).
4. Buka menu **APIs & Services** > **Library**.
5. Cari `Google Drive API`, klik dan tekan tombol **Enable**.

### Langkah 2: Konfigurasi OAuth Consent Screen
1. Buka menu **APIs & Services** > **OAuth consent screen**.
2. Pilih User Type **External**, lalu klik **Create**.
3. Isi informasi aplikasi:
   - **App name**: `Sistem Rekapan Barang Backup`
   - **User support email**: Pilih email Gmail Anda.
   - **Developer contact information**: Isi email Gmail Anda.
4. Klik **Save and Continue**.
5. Di bagian **Scopes**:
   - Klik **Add or Remove Scopes**.
   - Cari dan centang scope: `https://www.googleapis.com/auth/drive.file`
   - ⚠️ *Scope ini hanya memberi akses ke file/folder yang dibuat oleh aplikasi itu sendiri (paling aman & tidak termasuk kategori restricted scope)*.
   - Klik **Update** lalu **Save and Continue**.
6. Di bagian **Test Users**:
   - Tambahkan email Gmail Anda tadi sebagai Test User.
   - Klik **Save and Continue**.
7. 🚨 **LANGKAH PALING KRUSIAL (Ganti ke Production)**:
   - Kembali ke halaman utama **OAuth consent screen**.
   - Di bagian **Publishing Status**, klik tombol **PUBLISH APP** (Ubah dari *Testing* menjadi *In Production*).
   - *Alasan*: Jika status tetap "Testing", Google otomatis membatalkan (*expire*) refresh token setiap **7 hari**. Dengan mengubah ke "In Production", refresh token akan berlaku **permanen selamanya tanpa perlu perpanjangan**. (Untuk aplikasi personal <100 user dengan scope `drive.file`, proses publish ini **langsung aktif tanpa perlu verifikasi/review dari Google**).

### Langkah 3: Buat OAuth 2.0 Client ID Credentials
1. Buka menu **APIs & Services** > **Credentials**.
2. Klik **Create Credentials** > **OAuth client ID**.
3. Pilih Application type: **Web application** atau **Desktop app**.
4. Beri nama (misal `CLI Backup Client`).
5. Jika memilih Web application, tambahkan Authorized redirect URI:
   - `urn:ietf:wg:oauth:2.0:oob` atau `http://localhost`
6. Klik **Create**. Catat/Simpan **Client ID** dan **Client Secret** Anda.

---

## 2. Menjalankan Script One-Time Setup Refresh Token

Buka terminal CLI di komputer lokal/server Anda, lalu jalankan script setup:

```bash
php scripts/gdrive_get_refresh_token.php
```

**Alur Eksekusi:**
1. Masukkan **Client ID** dan **Client Secret** saat diminta CLI.
2. Script akan menampilkan URL Otorisasi Google.
3. **Copy URL tersebut** dan buka di browser tempat Anda login dengan akun Gmail khusus.
4. Jika muncul peringatan *"Google hasn't verified this app"*, klik **Advanced** / **Lanjutan**, lalu klik **Go to [App Name] (unsafe)** (Aman karena Anda sendiri pembuat aplikasinya).
5. Izinkan akses scope `drive.file`.
6. Copy **Authorization Code** yang diberikan Google di browser.
7. Paste kembali kode tersebut ke CLI terminal lalu tekan ENTER.
8. Script akan menampilkan **Refresh Token** Anda.

---

## 3. Buat Folder Induk di Google Drive & Konfigurasi File

1. Buka [Google Drive](https://drive.google.com/) akun Gmail khusus Anda.
2. Buat folder baru secara manual, misal bernama `"Backup Rekapan Foto Event"`.
3. Buka folder tersebut, perhatikan URL di browser:
   `https://drive.google.com/drive/folders/1A2b3C4d5E6f7G8h9...`
   - String `1A2b3C4d5E6f7G8h9...` adalah **DRIVE_ROOT_FOLDER_ID**.

4. Buat file baru `config/google_drive_credentials.php` pada project (file ini sudah otomatis diabaikan oleh `.gitignore` dan dilindungi oleh `.htaccess`):

```php
<?php
// config/google_drive_credentials.php

define('GDRIVE_CLIENT_ID', 'PASTE_CLIENT_ID_ANDA_DI_SINI');
define('GDRIVE_CLIENT_SECRET', 'PASTE_CLIENT_SECRET_ANDA_DI_SINI');
define('GDRIVE_REFRESH_TOKEN', 'PASTE_REFRESH_TOKEN_ANDA_DI_SINI');
define('GDRIVE_ROOT_FOLDER_ID', 'PASTE_FOLDER_ID_INDUK_DI_SINI');

// Secret key acak untuk proteksi trigger HTTP cron job
define('GDRIVE_CRON_SECRET_KEY', 'BuatKataKunciRahasiaAcakPanjang123456');
```

---

## 4. Setting Cron Job Background di Hosting (Hostinger / cPanel)

Proses upload berjalan secara *asynchronous* via background cron job agar tidak mengganggu kecepatan aplikasi utama.

### Opsi A: Cron CLI (Direkomendasikan)
Tambahkan Command Cron di hPanel Hostinger / cPanel (misal dieksekusi tiap 10 menit):

```bash
*/10 * * * * php /home/u12345678/public_html/scripts/gdrive_sync.php >> /home/u12345678/public_html/logs/gdrive_sync.log 2>&1
```
*(Sesuaikan `/home/u12345678/public_html/` dengan path direktori hosting Anda)*.

### Opsi B: HTTP Cron Fallback Trigger
Jika hosting Anda membatasi eksekusi CLI, gunakan URL trigger berbasis HTTP (dilindungi secret key):

```bash
*/10 * * * * curl -s "https://domain-anda.com/scripts/gdrive_sync.php?key=BuatKataKunciRahasiaAcakPanjang123456" >/dev/null 2>&1
```

---

## 5. Ringkasan File Project yang Ditambahkan & Diubah

| File | Status | Keterangan |
|---|---|---|
| `schema.sql` | [MODIFY] | Menambahkan kolom `gdrive_*` pada `foto_barang` & tabel `pekerjaan_gdrive_folder` |
| `composer.json` | [MODIFY] | Menambahkan dependensi `"google/apiclient": "^2.15"` |
| `config/google_drive.php` | [NEW] | Inisialisasi Google API Client & handler fail-safe null |
| `config/.htaccess` | [NEW] | Memproteksi direktori config dari akses web publik (`Deny from all`) |
| `.gitignore` | [NEW/MODIFY] | Memastikan file `google_drive_credentials.php` tidak ter-commit ke git |
| `services/DriveUploader.php` | [NEW] | Class utama pendukung folder caching & retry mechanism |
| `scripts/gdrive_get_refresh_token.php` | [NEW] | Script CLI *one-time setup* penukar auth code ke refresh token |
| `scripts/gdrive_sync.php` | [NEW] | Entry point cron job background sync (CLI & HTTP trigger) |
| `public/superadmin/gdrive_status.php` | [NEW] | Dashboard monitoring status backup Google Drive khusus Superadmin |
| `public/superadmin/*.php` | [MODIFY] | Menambahkan link navigasi "Backup Drive" pada navbar Superadmin |
| `logs/gdrive_sync.log` | [NEW] | File log histori eksekusi cron job |

---

## 6. Verifikasi & Pemantauan

1. Buka dashboard Superadmin di browser: `/superadmin/gdrive_status.php`.
2. Pastikan banner status menunjukkan **"Terhubung (Ready)"**.
3. Anda dapat menekan tombol **"Jalankan Sync Sekarang"** untuk menguji sinkronisasi foto secara manual.
4. Buka folder Google Drive Anda di browser untuk memastikan sub-folder per event dibuat otomatis dan foto WebP berhasil terunggah.
