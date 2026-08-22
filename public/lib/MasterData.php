<?php

function ensure_shift_master(PDO $pdo): void
{
    $pdo->exec("INSERT INTO shift (kode_shift,nama_shift,jam_mulai,jam_selesai) VALUES
        ('P','Pagi','07:00:00','14:00:00'),
        ('W','Siang','10:00:00','17:00:00'),
        ('S','Sore','14:00:00','20:00:00')
        ON DUPLICATE KEY UPDATE nama_shift=VALUES(nama_shift),jam_mulai=VALUES(jam_mulai),jam_selesai=VALUES(jam_selesai)");

    $defaults = [
        'P' => ['06:20:00','06:30:00'],
        'W' => ['09:00:00','09:15:00'],
        'S' => ['13:00:00','13:20:00'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat) SELECT id,? FROM shift WHERE kode_shift=?");
    foreach ($defaults as $kode => $times) {
        foreach ($times as $time) $stmt->execute([$time, $kode]);
    }
}
