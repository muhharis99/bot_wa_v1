-- Migrasi fitur Jadwal Mingguan
-- Jalankan sekali pada database existing sebelum memakai dashboard versi baru.

ALTER TABLE jadwal_harian
    MODIFY shift_id TINYINT UNSIGNED NULL,
    MODIFY jam_berangkat_terpilih TIME NULL,
    MODIFY status ENUM('pending','diproses','terkirim','gagal','belum_diatur','terjadwal','libur') NOT NULL DEFAULT 'pending';

UPDATE jadwal_harian SET status='terjadwal' WHERE status IN ('pending','diproses');
UPDATE jadwal_harian SET status='libur', jam_berangkat_terpilih=NULL WHERE shift_id IS NULL;

ALTER TABLE jadwal_harian DROP INDEX idx_scheduler;

ALTER TABLE jadwal_harian
    MODIFY status ENUM('belum_diatur','terjadwal','terkirim','gagal','libur') NOT NULL DEFAULT 'belum_diatur',
    DROP COLUMN waktu_terkirim,
    DROP COLUMN pesan_error,
    DROP COLUMN created_at,
    ADD KEY idx_scheduler (status,tanggal,jam_berangkat_terpilih,shift_id);
