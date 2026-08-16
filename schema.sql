-- Skema Database Sistem Rekapan Barang (XAMPP MySQL / MariaDB)
-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS `rekapan_barang` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rekapan_barang`;

-- Drop tabel jika ada (urutan child ke parent)
DROP TABLE IF EXISTS `foto_barang`;
DROP TABLE IF EXISTS `barang`;
DROP TABLE IF EXISTS `pekerjaan`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `users`;

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

-- 2. Tabel Pekerjaan
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

-- 3. Tabel Barang
CREATE TABLE `barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pekerjaan_id` INT NOT NULL,
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
