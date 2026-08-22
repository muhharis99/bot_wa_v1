CREATE DATABASE IF NOT EXISTS bot_wa_v1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bot_wa_v1;

CREATE TABLE pengaturan (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    nomor_wa_tujuan VARCHAR(25) NOT NULL DEFAULT '',
    nama_panggilan VARCHAR(100) NOT NULL DEFAULT 'seng',
    template_pesan VARCHAR(500) NOT NULL DEFAULT 'aku berangkat dulu yaa seng',
    status_koneksi_bot ENUM('terputus','butuh_scan','menghubungkan','connected') NOT NULL DEFAULT 'terputus',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO pengaturan (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE shift (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode_shift CHAR(1) NOT NULL UNIQUE,
    nama_shift VARCHAR(30) NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL
) ENGINE=InnoDB;

INSERT INTO shift (kode_shift,nama_shift,jam_mulai,jam_selesai) VALUES
('P','Pagi','07:00:00','14:00:00'),
('W','Siang','10:00:00','17:00:00'),
('S','Sore','14:00:00','20:00:00')
ON DUPLICATE KEY UPDATE nama_shift=VALUES(nama_shift), jam_mulai=VALUES(jam_mulai), jam_selesai=VALUES(jam_selesai);

CREATE TABLE opsi_jam_berangkat (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shift_id TINYINT UNSIGNED NOT NULL,
    jam_berangkat TIME NOT NULL,
    UNIQUE KEY uk_shift_jam (shift_id,jam_berangkat),
    CONSTRAINT fk_opsi_shift FOREIGN KEY (shift_id) REFERENCES shift(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
SELECT id,'06:20:00' FROM shift WHERE kode_shift='P';
INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
SELECT id,'06:30:00' FROM shift WHERE kode_shift='P';
INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
SELECT id,'09:00:00' FROM shift WHERE kode_shift='W';
INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
SELECT id,'09:15:00' FROM shift WHERE kode_shift='W';
INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
SELECT id,'13:00:00' FROM shift WHERE kode_shift='S';
INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
SELECT id,'13:20:00' FROM shift WHERE kode_shift='S';

CREATE TABLE jadwal_harian (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    shift_id TINYINT UNSIGNED NOT NULL,
    jam_berangkat_terpilih TIME NOT NULL,
    status ENUM('pending','diproses','terkirim','gagal') NOT NULL DEFAULT 'pending',
    waktu_terkirim DATETIME NULL,
    pesan_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tanggal (tanggal),
    KEY idx_scheduler (status,tanggal,jam_berangkat_terpilih),
    CONSTRAINT fk_jadwal_shift FOREIGN KEY (shift_id) REFERENCES shift(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE log_pesan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jadwal_harian_id BIGINT UNSIGNED NULL,
    isi_pesan VARCHAR(1000) NOT NULL,
    nomor_tujuan VARCHAR(25) NOT NULL,
    status ENUM('terkirim','gagal') NOT NULL,
    waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pesan_error TEXT NULL,
    CONSTRAINT fk_log_jadwal FOREIGN KEY (jadwal_harian_id) REFERENCES jadwal_harian(id) ON DELETE SET NULL ON UPDATE CASCADE,
    KEY idx_log_waktu (waktu)
) ENGINE=InnoDB;

CREATE TABLE bot_runtime (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    qr_base64 MEDIUMTEXT NULL,
    qr_updated_at DATETIME NULL,
    heartbeat_at DATETIME NULL,
    info VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO bot_runtime (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE perintah_bot (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenis ENUM('kirim_manual') NOT NULL,
    payload JSON NULL,
    status ENUM('pending','diproses','selesai','gagal') NOT NULL DEFAULT 'pending',
    pesan_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    KEY idx_perintah (status,created_at)
) ENGINE=InnoDB;
