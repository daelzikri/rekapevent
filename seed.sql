-- Seed Data Awal Sistem Rekapan Barang
USE `rekapan_barang`;

-- Passwords for all accounts below: password123
-- Hash: $2y$12$E0ybKSZR4TOuRsJRFcd7yeoDnkMR99qOE7QgnUVvsYiRSH9FjegNG

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`) VALUES
(1, 'superadmin', '$2y$12$E0ybKSZR4TOuRsJRFcd7yeoDnkMR99qOE7QgnUVvsYiRSH9FjegNG', 'superadmin'),
(2, 'admin', '$2y$12$E0ybKSZR4TOuRsJRFcd7yeoDnkMR99qOE7QgnUVvsYiRSH9FjegNG', 'admin'),
(3, 'pekerja1', '$2y$12$E0ybKSZR4TOuRsJRFcd7yeoDnkMR99qOE7QgnUVvsYiRSH9FjegNG', 'pekerja'),
(4, 'pekerja2', '$2y$12$E0ybKSZR4TOuRsJRFcd7yeoDnkMR99qOE7QgnUVvsYiRSH9FjegNG', 'pekerja');

INSERT INTO `pekerjaan` (`id`, `nama_pekerjaan`, `user_id`, `dibuat_oleh`) VALUES
(1, 'Wedding Reception - Grand Ballroom Hotel Mulia', 3, 1),
(2, 'Tech Launch Event 2026 - Main Stage Hall A', 4, 1);

INSERT INTO `barang` (`id`, `pekerjaan_id`, `kuantitas`, `keterangan`) VALUES
(1, 1, 150, 'Kursi Futura Cover Putih Pita Gold'),
(2, 1, 10, 'Meja Round Table 180cm + Taplak Champagne'),
(3, 1, 2, 'Sound System Line Array 5000 Watt'),
(4, 2, 1, 'Videotron LED P2.5 Indoor 6x3 Meter'),
(5, 2, 20, 'Lighting Moving Head Beam 230W');

INSERT INTO `audit_log` (`user_id`, `aksi`, `detail`, `ip_address`) VALUES
(1, 'SYSTEM_INIT', 'Sistem berhasil di-seed dengan data awal.', '127.0.0.1');
