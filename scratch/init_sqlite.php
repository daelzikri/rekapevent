<?php
// scratch/init_sqlite.php

$dbPath = __DIR__ . '/../rekapan_barang.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON;");

// Create Tables for SQLite local smoke test
$pdo->exec("
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('superadmin','admin','pekerja')),
  session_token TEXT NULL,
  last_activity_at DATETIME NULL,
  failed_login_count INTEGER DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pekerjaan (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nama_pekerjaan TEXT NOT NULL,
  user_id INTEGER NOT NULL,
  dibuat_oleh INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (dibuat_oleh) REFERENCES users(id)
);

CREATE TABLE barang (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pekerjaan_id INTEGER NOT NULL,
  kuantitas INTEGER NOT NULL,
  keterangan TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pekerjaan_id) REFERENCES pekerjaan(id)
);

CREATE TABLE foto_barang (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  barang_id INTEGER NOT NULL,
  file_path TEXT NOT NULL,
  format_asli TEXT,
  nama_file_server TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NULL,
  aksi TEXT NOT NULL,
  detail TEXT NULL,
  ip_address TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
");

// Insert Seed Data
$hash = password_hash('password123', PASSWORD_BCRYPT);

$pdo->exec("
INSERT INTO users (id, username, password_hash, role) VALUES
(1, 'superadmin', '{$hash}', 'superadmin'),
(2, 'admin', '{$hash}', 'admin'),
(3, 'pekerja1', '{$hash}', 'pekerja'),
(4, 'pekerja2', '{$hash}', 'pekerja');

INSERT INTO pekerjaan (id, nama_pekerjaan, user_id, dibuat_oleh) VALUES
(1, 'Wedding Reception - Grand Ballroom Hotel Mulia', 3, 1),
(2, 'Tech Launch Event 2026 - Main Stage Hall A', 4, 1);

INSERT INTO barang (id, pekerjaan_id, kuantitas, keterangan) VALUES
(1, 1, 150, 'Kursi Futura Cover Putih Pita Gold'),
(2, 1, 10, 'Meja Round Table 180cm + Taplak Champagne'),
(3, 1, 2, 'Sound System Line Array 5000 Watt'),
(4, 2, 1, 'Videotron LED P2.5 Indoor 6x3 Meter'),
(5, 2, 20, 'Lighting Moving Head Beam 230W');
");

echo "SQLite database initialized successfully at " . realpath($dbPath) . "\n";
