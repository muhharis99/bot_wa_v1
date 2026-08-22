<?php
require_once __DIR__ . '/functions.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function json_response(bool $success, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_response(false, 'Method tidak diizinkan.');
}

csrf_check();
$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $tanggal = trim($_POST['tanggal'] ?? '');
    $shiftRaw = $_POST['shift_id'] ?? '';
    $jam = trim($_POST['jam_berangkat'] ?? '');

    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$date || $date->format('Y-m-d') !== $tanggal) json_response(false, 'Tanggal tidak valid.');

    $existingStmt = $pdo->prepare('SELECT status FROM jadwal_harian WHERE tanggal=? LIMIT 1');
    $existingStmt->execute([$tanggal]);
    $existing = $existingStmt->fetch();
    if ($existing && $existing['status'] === 'terkirim' && $tanggal < date('Y-m-d')) {
        json_response(false, 'Jadwal yang sudah terkirim pada tanggal lampau tidak dapat diubah.');
    }

    if ($shiftRaw === 'libur') {
        $stmt = $pdo->prepare("INSERT INTO jadwal_harian (tanggal,shift_id,jam_berangkat_terpilih,status) VALUES (?,NULL,NULL,'libur') ON DUPLICATE KEY UPDATE shift_id=NULL,jam_berangkat_terpilih=NULL,status='libur'");
        $stmt->execute([$tanggal]);
        json_response(true, 'Tersimpan', ['status' => 'libur']);
    }

    $shiftId = (int)$shiftRaw;
    if ($shiftId <= 0) {
        $stmt = $pdo->prepare("INSERT INTO jadwal_harian (tanggal,shift_id,jam_berangkat_terpilih,status) VALUES (?,NULL,NULL,'belum_diatur') ON DUPLICATE KEY UPDATE shift_id=NULL,jam_berangkat_terpilih=NULL,status='belum_diatur'");
        $stmt->execute([$tanggal]);
        json_response(true, 'Tersimpan', ['status' => 'belum_diatur']);
    }

    $shiftStmt = $pdo->prepare('SELECT id FROM shift WHERE id=? LIMIT 1');
    $shiftStmt->execute([$shiftId]);
    if (!$shiftStmt->fetch()) json_response(false, 'Shift tidak valid.');

    $normalizedJam = $jam !== '' ? (strlen($jam) === 5 ? $jam . ':00' : $jam) : null;
    if ($normalizedJam !== null) {
        $validStmt = $pdo->prepare('SELECT COUNT(*) FROM opsi_jam_berangkat WHERE shift_id=? AND jam_berangkat=?');
        $validStmt->execute([$shiftId, $normalizedJam]);
        if (!$validStmt->fetchColumn()) json_response(false, 'Jam berangkat tidak sesuai dengan shift.');
    }

    $status = $normalizedJam ? 'terjadwal' : 'belum_diatur';
    $stmt = $pdo->prepare("INSERT INTO jadwal_harian (tanggal,shift_id,jam_berangkat_terpilih,status) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id),jam_berangkat_terpilih=VALUES(jam_berangkat_terpilih),status=VALUES(status)");
    $stmt->execute([$tanggal, $shiftId, $normalizedJam, $status]);
    json_response(true, 'Tersimpan', ['status' => $status]);
}

if ($action === 'copy_previous') {
    $weekStart = trim($_POST['week_start'] ?? '');
    $start = DateTime::createFromFormat('Y-m-d', $weekStart);
    if (!$start || $start->format('Y-m-d') !== $weekStart || $start->format('N') !== '1') json_response(false, 'Awal minggu tidak valid.');

    $sourceStart = (clone $start)->modify('-7 days');
    $sourceEnd = (clone $sourceStart)->modify('+6 days');
    $srcStmt = $pdo->prepare('SELECT tanggal,shift_id,jam_berangkat_terpilih,status FROM jadwal_harian WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal');
    $srcStmt->execute([$sourceStart->format('Y-m-d'), $sourceEnd->format('Y-m-d')]);
    $sourceRows = [];
    foreach ($srcStmt->fetchAll() as $row) $sourceRows[$row['tanggal']] = $row;

    if (!$sourceRows) json_response(false, 'Minggu sebelumnya belum memiliki jadwal.');

    $checkTarget = $pdo->prepare('SELECT status FROM jadwal_harian WHERE tanggal=? LIMIT 1');
    $saveTarget = $pdo->prepare("INSERT INTO jadwal_harian (tanggal,shift_id,jam_berangkat_terpilih,status) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id),jam_berangkat_terpilih=VALUES(jam_berangkat_terpilih),status=VALUES(status)");
    $copied = 0;

    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < 7; $i++) {
            $sourceDate = (clone $sourceStart)->modify("+$i days")->format('Y-m-d');
            $targetDate = (clone $start)->modify("+$i days")->format('Y-m-d');
            if (!isset($sourceRows[$sourceDate])) continue;

            $checkTarget->execute([$targetDate]);
            $target = $checkTarget->fetch();
            if ($target && $target['status'] === 'terkirim' && $targetDate < date('Y-m-d')) continue;

            $src = $sourceRows[$sourceDate];
            $isLibur = $src['status'] === 'libur' || $src['shift_id'] === null;
            $status = $isLibur ? 'libur' : (!empty($src['jam_berangkat_terpilih']) ? 'terjadwal' : 'belum_diatur');
            $saveTarget->execute([$targetDate, $isLibur ? null : $src['shift_id'], $isLibur ? null : $src['jam_berangkat_terpilih'], $status]);
            $copied++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(false, 'Gagal menyalin jadwal minggu lalu.');
    }

    json_response(true, "{$copied} jadwal berhasil disalin.");
}

json_response(false, 'Aksi tidak dikenal.');
