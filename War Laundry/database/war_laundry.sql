-- ==========================================================================
-- Database War Laundry (PHP native)
-- Impor: C:\xampp\mysql\bin\mysql.exe -u root < database\war_laundry.sql
-- File ini aman diimpor ulang: tabel lama dihapus lalu dibuat lagi.
-- Tanggal data contoh memakai CURDATE() agar dashboard langsung terisi
-- pada hari database diimpor.
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS war_laundry_php
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE war_laundry_php;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS pembayaran;
DROP TABLE IF EXISTS pesanan;
DROP TABLE IF EXISTS layanan;
DROP TABLE IF EXISTS pelanggan;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Akun pengguna aplikasi (pemilik dan petugas)
CREATE TABLE users (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama       VARCHAR(100) NOT NULL,
  username   VARCHAR(50)  NOT NULL,
  password   VARCHAR(255) NOT NULL,              -- hasil password_hash()
  peran      ENUM('pemilik','petugas') NOT NULL,
  aktif      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pelanggan dikenali dari nomor telepon
CREATE TABLE pelanggan (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama       VARCHAR(100) NOT NULL,
  telepon    VARCHAR(20)  NOT NULL,
  alamat     VARCHAR(255) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pelanggan_telepon (telepon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jenis layanan dan tarif per kg
CREATE TABLE layanan (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama         VARCHAR(100) NOT NULL,
  tarif_per_kg INT UNSIGNED NOT NULL,
  durasi_hari  TINYINT UNSIGNED NOT NULL,
  tanpa_cuci   TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = Setrika saja (lewati Dicuci & Dikeringkan)
  aktif        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pesanan laundry
CREATE TABLE pesanan (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode              VARCHAR(10)  NOT NULL,         -- LD-001, LD-002, dst.
  pelanggan_id      INT UNSIGNED NOT NULL,
  layanan_id        INT UNSIGNED NOT NULL,
  user_id           INT UNSIGNED NOT NULL,         -- petugas yang mencatat
  berat             DECIMAL(6,2) NOT NULL,
  tarif             INT UNSIGNED NOT NULL,         -- tarif saat pesanan dibuat
  total             INT UNSIGNED NOT NULL,
  dibayar           INT UNSIGNED NOT NULL DEFAULT 0,
  status            ENUM('diterima','dicuci','dikeringkan','disetrika','siap_diambil','selesai')
                    NOT NULL DEFAULT 'diterima',
  estimasi_selesai  DATETIME     NOT NULL,
  selesai_at        DATETIME     NULL,
  catatan           VARCHAR(255) NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pesanan_kode (kode),
  KEY idx_pesanan_created (created_at),
  KEY idx_pesanan_status (status),
  CONSTRAINT fk_pesanan_pelanggan FOREIGN KEY (pelanggan_id) REFERENCES pelanggan (id),
  CONSTRAINT fk_pesanan_layanan   FOREIGN KEY (layanan_id)   REFERENCES layanan (id),
  CONSTRAINT fk_pesanan_user      FOREIGN KEY (user_id)      REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riwayat pembayaran (boleh bertahap)
CREATE TABLE pembayaran (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pesanan_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  nominal    INT UNSIGNED NOT NULL,
  metode     ENUM('tunai','transfer') NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pembayaran_created (created_at),
  CONSTRAINT fk_pembayaran_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan (id),
  CONSTRAINT fk_pembayaran_user    FOREIGN KEY (user_id)    REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Data contoh
-- --------------------------------------------------------------------------

-- Semua kata sandi: laundry123 (disimpan sebagai hash bcrypt)
INSERT INTO users (id, nama, username, password, peran, aktif) VALUES
(1, 'Raffi',       'raffi',  '$2y$12$/NbktuOIiqm095H9gudXnuS6pKyoNlLb5WmLdkcp1Az9AP2LI58Ga', 'pemilik', 1),
(2, 'Cittra',      'cittra', '$2y$12$YZtD/Yy5s1INNdV0lh6.3uk3be0Q58A/q.GvL9Y8T0O1haMkHOt9y', 'petugas', 1),
(3, 'Nadia Putri', 'nadia',  '$2y$12$ug9EWR8dJ549bYPiBx1pSelKdULv7xX7GL38/fz0x8P2II8OCO8za', 'petugas', 1);

INSERT INTO layanan (id, nama, tarif_per_kg, durasi_hari, tanpa_cuci, aktif) VALUES
(1, 'Cuci reguler', 8000,  2, 0, 1),
(2, 'Cuci ekspres', 12000, 1, 0, 1),
(3, 'Setrika saja', 5000,  1, 1, 1);

INSERT INTO pelanggan (id, nama, telepon, alamat) VALUES
(1, 'Agus Salim',   '081234560021', 'Jl. Kenanga No. 3'),
(2, 'Dewi Lestari', '081234560022', 'Jl. Mawar No. 8'),
(3, 'Andi Wijaya',  '081234560023', 'Perumahan Batam Centre Blok C2'),
(4, 'Rina Putri',   '081234560024', 'Jl. Anggrek No. 15'),
(5, 'Budi Santoso', '081234560025', 'Jl. Melati No. 12'),
(6, 'Siti Aminah',  '081234560026', 'Jl. Flamboyan No. 7');

-- 6 pesanan contoh seperti di desain (semua masuk hari ini)
INSERT INTO pesanan (id, kode, pelanggan_id, layanan_id, user_id, berat, tarif, total, dibayar,
                     status, estimasi_selesai, selesai_at, catatan, created_at) VALUES
(1, 'LD-021', 1, 3, 2, 4.00, 5000,  20000, 20000, 'selesai',
    TIMESTAMP(CURDATE(), '07:30:00') + INTERVAL 1 DAY, TIMESTAMP(CURDATE(), '10:30:00'), NULL,
    TIMESTAMP(CURDATE(), '07:30:00')),
(2, 'LD-022', 2, 2, 2, 4.00, 12000, 48000, 48000, 'siap_diambil',
    TIMESTAMP(CURDATE(), '08:00:00') + INTERVAL 1 DAY, NULL, NULL,
    TIMESTAMP(CURDATE(), '08:00:00')),
(3, 'LD-023', 3, 1, 2, 6.00, 8000,  48000, 48000, 'disetrika',
    TIMESTAMP(CURDATE(), '08:45:00') + INTERVAL 2 DAY, NULL, NULL,
    TIMESTAMP(CURDATE(), '08:45:00')),
(4, 'LD-024', 4, 1, 3, 3.00, 8000,  24000, 0,     'dikeringkan',
    TIMESTAMP(CURDATE(), '09:30:00') + INTERVAL 2 DAY, NULL, 'Pisahkan pakaian putih.',
    TIMESTAMP(CURDATE(), '09:30:00')),
(5, 'LD-025', 5, 2, 2, 5.00, 12000, 60000, 60000, 'dicuci',
    TIMESTAMP(CURDATE(), '09:50:00') + INTERVAL 1 DAY, NULL, NULL,
    TIMESTAMP(CURDATE(), '09:50:00')),
(6, 'LD-026', 6, 1, 2, 4.00, 8000,  32000, 0,     'diterima',
    TIMESTAMP(CURDATE(), '10:45:00') + INTERVAL 2 DAY, NULL, NULL,
    TIMESTAMP(CURDATE(), '10:45:00'));

INSERT INTO pembayaran (pesanan_id, user_id, nominal, metode, created_at) VALUES
(1, 2, 20000, 'tunai',    TIMESTAMP(CURDATE(), '08:00:00')),
(2, 2, 48000, 'transfer', TIMESTAMP(CURDATE(), '08:30:00')),
(3, 2, 48000, 'transfer', TIMESTAMP(CURDATE(), '09:00:00')),
(5, 2, 60000, 'tunai',    TIMESTAMP(CURDATE(), '10:00:00'));
