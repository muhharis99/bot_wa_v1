<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/lib/XlsxScheduleReader.php';
require_once __DIR__ . '/lib/MasterData.php';
require_login();

ensure_shift_master($pdo);

$reader = new XlsxScheduleReader();
$error = null;
$preview = $_SESSION['jadwal_import_preview'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'preview') {
        unset($_SESSION['jadwal_import_preview']);
        $preview = null;
        try {
            if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Pilih file Excel .xlsx terlebih dahulu.');
            $file = $_FILES['file_excel'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') throw new RuntimeException('Format yang didukung saat ini adalah .xlsx.');
            if ((int)$file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Ukuran file maksimal 5 MB.');

            $yearRaw = trim($_POST['tahun'] ?? '');
            $year = $yearRaw === '' ? null : (int)$yearRaw;
            if ($year !== null && ($year < 2000 || $year > 2100)) throw new RuntimeException('Tahun harus antara 2000 sampai 2100.');

            $rawRows = $reader->read($file['tmp_name']);
            $parsed = $reader->normalizeSchedule($rawRows, $year);
            if (!$parsed['rows']) throw new RuntimeException('Tidak ada data jadwal yang dapat dibaca dari Excel.');

            ensure_shift_master($pdo);
            $shiftRows = $pdo->query('SELECT id,kode_shift,nama_shift FROM shift')->fetchAll();
            $shiftMap = [];
            foreach ($shiftRows as $s) $shiftMap[strtoupper(trim($s['kode_shift']))] = $s;

            $validCount = 0; $warningCount = 0; $invalidCount = 0;
            foreach ($parsed['rows'] as &$row) {
                if (!$row['valid']) { $invalidCount++; continue; }
                $row['shift'] = strtoupper(trim((string)$row['shift']));
                $validCount++;
                if (!empty($row['warnings'])) $warningCount++;
                if ($row['shift'] !== 'L' && !isset($shiftMap[$row['shift']])) {
                    $row['valid'] = false;
                    $row['error'] = 'Kode shift ' . $row['shift'] . ' tidak tersedia di master shift.';
                    $validCount--; $invalidCount++;
                }
            }
            unset($row);

            $preview = [
                'year' => $parsed['year'],
                'filename' => basename($file['name']),
                'rows' => $parsed['rows'],
                'valid_count' => $validCount,
                'warning_count' => $warningCount,
                'invalid_count' => $invalidCount,
                'created_at' => time(),
            ];
            $_SESSION['jadwal_import_preview'] = $preview;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'commit') {
        try {
            $preview = $_SESSION['jadwal_import_preview'] ?? null;
            if (!$preview || empty($preview['rows'])) throw new RuntimeException('Preview import sudah tidak tersedia. Upload ulang file Excel.');
            if (($preview['created_at'] ?? 0) < time() - 1800) throw new RuntimeException('Preview sudah kedaluwarsa. Upload ulang file Excel.');

            ensure_shift_master($pdo);
            $shiftRows = $pdo->query('SELECT id,kode_shift FROM shift')->fetchAll();
            $shiftMap = [];
            foreach ($shiftRows as $s) $shiftMap[strtoupper(trim($s['kode_shift']))] = (int)$s['id'];

            $existingStmt = $pdo->prepare('SELECT id,status FROM jadwal_harian WHERE tanggal=? LIMIT 1');
            $saveStmt = $pdo->prepare("INSERT INTO jadwal_harian (tanggal,shift_id,jam_berangkat_terpilih,status) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id),jam_berangkat_terpilih=VALUES(jam_berangkat_terpilih),status=VALUES(status)");

            $deduped = [];
            foreach ($preview['rows'] as $row) if (!empty($row['valid'])) $deduped[$row['tanggal']] = $row;

            $inserted = 0; $updated = 0; $skippedLocked = 0; $skippedInvalid = 0;
            $pdo->beginTransaction();
            foreach ($deduped as $row) {
                $existingStmt->execute([$row['tanggal']]);
                $existing = $existingStmt->fetch();
                if ($existing && $existing['status'] === 'terkirim' && $row['tanggal'] < date('Y-m-d')) { $skippedLocked++; continue; }

                $shiftCode = strtoupper(trim((string)$row['shift']));
                if ($shiftCode === 'L') {
                    $shiftId = null; $jam = null; $status = 'libur';
                } else {
                    $shiftId = $shiftMap[$shiftCode] ?? null;
                    if (!$shiftId) { $skippedInvalid++; continue; }
                    $jam = $row['jam_berangkat'] ? $row['jam_berangkat'] . ':00' : null;
                    $status = $jam ? 'terjadwal' : 'belum_diatur';
                }

                $saveStmt->execute([$row['tanggal'], $shiftId, $jam, $status]);
                $existing ? $updated++ : $inserted++;
            }
            $pdo->commit();

            unset($_SESSION['jadwal_import_preview']);
            flash('success', "Import Excel selesai: {$inserted} tanggal baru, {$updated} diperbarui, {$skippedLocked} riwayat terkunci dilewati, {$skippedInvalid} data tidak valid dilewati.");
            $firstDate = array_key_first($deduped);
            redirect('index.php' . ($firstDate ? '?week=' . urlencode($firstDate) : '') . '#jadwal');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    if ($action === 'cancel') {
        unset($_SESSION['jadwal_import_preview']);
        redirect('index.php#jadwal');
    }
}

function shift_label(string $shift): string {
    return match ($shift) { 'P' => 'P - Pagi', 'W' => 'W - Siang', 'S' => 'S - Sore', 'L' => 'Libur', default => $shift };
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Import Jadwal Excel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#f5f7fb}.card{border:0;box-shadow:0 .125rem .5rem rgba(0,0,0,.06)}.drop-zone{border:2px dashed #adb5bd;border-radius:12px;padding:28px;text-align:center;background:#fff}.drop-zone.drag{border-color:#0d6efd;background:#f1f7ff}.sticky-actions{position:sticky;bottom:0;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);z-index:5}</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand text-decoration-none" href="index.php#jadwal">Bot WA Berangkat Kerja</a><a class="btn btn-outline-light btn-sm" href="index.php#jadwal">Kembali</a></div></nav>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-1">Import Jadwal dari Excel</h1><div class="text-secondary">Upload jadwal kerja, cek preview, lalu simpan ke jadwal web.</div></div></div>
    <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

    <?php if(!$preview): ?>
    <div class="card"><div class="card-body p-4">
        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="preview">
            <div class="row g-3">
                <div class="col-lg-9">
                    <label class="drop-zone d-block" id="dropZone">
                        <input type="file" class="d-none" name="file_excel" id="fileExcel" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        <div class="fs-5 fw-semibold">Tarik file Excel ke sini atau klik untuk memilih</div>
                        <div class="text-secondary mt-2" id="fileLabel">Format .xlsx, maksimal 5 MB</div>
                    </label>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Tahun Jadwal</label>
                    <input type="number" class="form-control" name="tahun" min="2000" max="2100" placeholder="Otomatis">
                    <div class="form-text">Kosongkan agar sistem menebak tahun dari tanggal + nama hari.</div>
                    <button class="btn btn-primary w-100 mt-3">Baca & Preview Excel</button>
                </div>
            </div>
        </form>
        <hr>
        <div class="small text-secondary"><strong>Format pintar:</strong> sistem mengenali variasi header seperti Tgl/Tanggal, Bulan, Hari, Jadwal/Shift, Jam Masuk, dan Jam Berangkat. Shift P/W/S dibaca sebagai kerja, sedangkan L/Libur/Off menjadi hari libur.</div>
    </div></div>
    <?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-secondary small">File</div><div class="fw-semibold text-truncate"><?=e($preview['filename'])?></div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-secondary small">Tahun terdeteksi/dipilih</div><div class="fs-4 fw-semibold"><?=e((string)$preview['year'])?></div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-secondary small">Valid</div><div class="fs-4 fw-semibold text-success"><?=e((string)$preview['valid_count'])?></div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-secondary small">Peringatan</div><div class="fs-4 fw-semibold text-warning"><?=e((string)$preview['warning_count'])?></div></div></div></div>
        <div class="col-md-2"><div class="card"><div class="card-body"><div class="text-secondary small">Tidak valid</div><div class="fs-4 fw-semibold text-danger"><?=e((string)$preview['invalid_count'])?></div></div></div></div>
    </div>

    <?php if($preview['warning_count']): ?><div class="alert alert-warning">Ada baris dengan peringatan, misalnya nama hari tidak cocok dengan tanggal atau jam berangkat kosong. Periksa sebelum menyimpan.</div><?php endif; ?>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Baris Excel</th><th>Tanggal</th><th>Hari</th><th>Shift</th><th>Jam Masuk</th><th>Jam Berangkat</th><th>Validasi</th></tr></thead><tbody>
    <?php foreach($preview['rows'] as $row): ?>
        <tr class="<?=empty($row['valid'])?'table-danger':(!empty($row['warnings'])?'table-warning':'')?>">
            <td><?=e((string)$row['excel_row'])?></td>
            <td><?=e($row['tanggal'] ?? '-')?></td>
            <td><?=e($row['hari'] ?? ($row['hari_excel'] ?? '-'))?></td>
            <td><?=isset($row['shift']) ? e(shift_label($row['shift'])) : '-'?></td>
            <td><?=e($row['jam_masuk'] ?? '-')?></td>
            <td><?=e($row['jam_berangkat'] ?? '-')?></td>
            <td><?php if(empty($row['valid'])): ?><span class="text-danger"><?=e($row['error'] ?? 'Tidak valid')?></span><?php elseif(!empty($row['warnings'])): ?><ul class="mb-0 ps-3 small"><?php foreach($row['warnings'] as $warning): ?><li><?=e($warning)?></li><?php endforeach; ?></ul><?php else: ?><span class="badge text-bg-success">Siap import</span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="card-body border-top sticky-actions"><div class="d-flex flex-column flex-md-row justify-content-between gap-2"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-secondary">Batal / Upload Ulang</button></form><form method="post" onsubmit="return confirm('Simpan semua jadwal valid dari preview ini? Data tanggal yang sama akan diperbarui, kecuali riwayat terkirim yang sudah terkunci.')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="commit"><button class="btn btn-success" <?= $preview['valid_count']<1?'disabled':'' ?>>Simpan <?=e((string)$preview['valid_count'])?> Jadwal ke Web</button></form></div></div></div>
    <?php endif; ?>
</main>
<script>
const fileInput=document.getElementById('fileExcel'),dropZone=document.getElementById('dropZone'),fileLabel=document.getElementById('fileLabel');
if(fileInput){fileInput.addEventListener('change',()=>{fileLabel.textContent=fileInput.files[0]?.name||'Format .xlsx, maksimal 5 MB'});['dragenter','dragover'].forEach(ev=>dropZone.addEventListener(ev,e=>{e.preventDefault();dropZone.classList.add('drag')}));['dragleave','drop'].forEach(ev=>dropZone.addEventListener(ev,e=>{e.preventDefault();dropZone.classList.remove('drag')}));dropZone.addEventListener('drop',e=>{if(e.dataTransfer.files.length){fileInput.files=e.dataTransfer.files;fileLabel.textContent=e.dataTransfer.files[0].name}})}
</script>
</body></html>
