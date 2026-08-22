<?php
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_setting') {
        $nomor = preg_replace('/\D+/', '', $_POST['nomor_wa_tujuan'] ?? '');
        if (str_starts_with($nomor, '62')) $nomor = '0' . substr($nomor, 2);
        $nama = trim($_POST['nama_panggilan'] ?? '');
        $template = trim($_POST['template_pesan'] ?? '');
        if ($nomor === '' || $template === '') {
            flash('danger', 'Nomor WhatsApp dan template pesan wajib diisi.');
        } elseif (!preg_match('/^08\d{8,13}$/', $nomor)) {
            flash('danger', 'Nomor WhatsApp harus memakai format lokal, contoh 081234567890.');
        } else {
            $stmt = $pdo->prepare('UPDATE pengaturan SET nomor_wa_tujuan=?, nama_panggilan=?, template_pesan=? WHERE id=1');
            $stmt->execute([$nomor, $nama, $template]);
            flash('success', 'Pengaturan berhasil disimpan. Nomor otomatis dikonversi ke 62 saat bot mengirim pesan.');
        }
        redirect('index.php#pengaturan');
    }

    if ($action === 'send_now') {
        $pdo->exec("INSERT INTO perintah_bot (jenis,payload,status) VALUES ('kirim_manual',JSON_OBJECT('source','dashboard'),'pending')");
        flash('success', 'Perintah kirim manual masuk antrean bot.');
        redirect('index.php#status');
    }
}

function status_badge(string $status): array {
    return match ($status) {
        'terjadwal' => ['primary', 'Terjadwal'],
        'terkirim' => ['success', 'Terkirim'],
        'libur' => ['warning', 'Libur'],
        'gagal' => ['danger', 'Gagal'],
        default => ['secondary', 'Belum diatur'],
    };
}

$setting = $pdo->query('SELECT * FROM pengaturan WHERE id=1')->fetch();
$runtime = $pdo->query('SELECT * FROM bot_runtime WHERE id=1')->fetch();
$shifts = $pdo->query('SELECT * FROM shift ORDER BY jam_mulai')->fetchAll();
$options = $pdo->query('SELECT * FROM opsi_jam_berangkat ORDER BY shift_id,jam_berangkat')->fetchAll();
$optionMap = [];
foreach ($options as $o) $optionMap[$o['shift_id']][] = substr($o['jam_berangkat'], 0, 5);

$requestedWeek = $_GET['week'] ?? date('Y-m-d');
$weekDate = DateTime::createFromFormat('Y-m-d', $requestedWeek) ?: new DateTime('today');
$weekDate->modify('monday this week');
$weekStart = $weekDate->format('Y-m-d');
$weekEndDate = (clone $weekDate)->modify('+6 days');
$weekEnd = $weekEndDate->format('Y-m-d');
$prevWeek = (clone $weekDate)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $weekDate)->modify('+7 days')->format('Y-m-d');
$thisWeek = (new DateTime('monday this week'))->format('Y-m-d');

$scheduleStmt = $pdo->prepare('SELECT j.*,s.kode_shift,s.nama_shift FROM jadwal_harian j LEFT JOIN shift s ON s.id=j.shift_id WHERE j.tanggal BETWEEN ? AND ?');
$scheduleStmt->execute([$weekStart, $weekEnd]);
$scheduleMap = [];
foreach ($scheduleStmt->fetchAll() as $row) $scheduleMap[$row['tanggal']] = $row;

$days = [];
$dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
for ($i = 0; $i < 7; $i++) {
    $date = (clone $weekDate)->modify("+$i days");
    $dateKey = $date->format('Y-m-d');
    $row = $scheduleMap[$dateKey] ?? null;
    $days[] = [
        'tanggal' => $dateKey,
        'hari' => $dayNames[(int)$date->format('N')],
        'display' => $date->format('d-m-Y'),
        'shift_id' => $row['shift_id'] ?? '',
        'jam' => !empty($row['jam_berangkat_terpilih']) ? substr($row['jam_berangkat_terpilih'], 0, 5) : '',
        'status' => $row['status'] ?? 'belum_diatur',
        'locked' => $row && $row['status'] === 'terkirim' && $dateKey < date('Y-m-d'),
    ];
}

$logs = $pdo->query('SELECT * FROM log_pesan ORDER BY id DESC LIMIT 50')->fetchAll();
$flash = get_flash();
$heartbeatAge = !empty($runtime['heartbeat_at']) ? time() - strtotime($runtime['heartbeat_at']) : 999999;
$serviceAlive = $heartbeatAge <= 120;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard Bot WA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f5f7fb}.card{border:0;box-shadow:0 .125rem .5rem rgba(0,0,0,.06)}.qr{max-width:280px;width:100%}.schedule-row.is-locked{background:#f8f9fa}.save-feedback{min-width:92px;display:inline-block}.today-row{box-shadow:inset 4px 0 #0d6efd}.schedule-table td{vertical-align:middle}.quick-time{font-size:.72rem;padding:.1rem .35rem}
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Bot WA Berangkat Kerja</span><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div></nav>
<main class="container py-4">
    <?php if($flash): ?><div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>

    <div class="row g-3 mb-4" id="status">
        <div class="col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Status Bot</h2><p class="mb-2">WhatsApp: <span class="badge text-bg-<?= $setting['status_koneksi_bot']==='connected'?'success':'warning' ?>"><?=e($setting['status_koneksi_bot'])?></span></p><p class="mb-2">Service Node.js: <span class="badge text-bg-<?= $serviceAlive?'success':'danger' ?>"><?= $serviceAlive?'aktif':'heartbeat tidak terdeteksi' ?></span></p><p class="text-secondary small">Heartbeat: <?=e($runtime['heartbeat_at'] ?: '-')?><br><?=e($runtime['info'] ?: '-')?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="send_now"><button class="btn btn-success" onclick="return confirm('Kirim pesan sekarang?')">Kirim Sekarang</button></form></div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-body text-center"><h2 class="h5">QR WhatsApp</h2><?php if(!empty($runtime['qr_base64'])): ?><img class="qr" src="<?=e($runtime['qr_base64'])?>" alt="QR WhatsApp"><p class="small text-secondary mt-2">Scan melalui WhatsApp &gt; Perangkat tertaut.</p><?php else: ?><div class="py-5 text-secondary">QR tidak tersedia. Jika status connected, ini normal.</div><?php endif; ?></div></div></div>
    </div>

    <div class="card mb-4" id="pengaturan"><div class="card-body"><h2 class="h5">Pengaturan</h2><form method="post" class="row g-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_setting"><div class="col-md-4"><label class="form-label">Nomor WA Tujuan</label><input class="form-control" name="nomor_wa_tujuan" value="<?=e($setting['nomor_wa_tujuan'])?>" placeholder="081234567890" inputmode="numeric" required><div class="form-text">Gunakan format 08... Bot otomatis mengubah menjadi 62... saat mengirim.</div></div><div class="col-md-3"><label class="form-label">Nama Panggilan</label><input class="form-control" name="nama_panggilan" value="<?=e($setting['nama_panggilan'])?>"></div><div class="col-md-5"><label class="form-label">Template Pesan</label><input class="form-control" name="template_pesan" value="<?=e($setting['template_pesan'])?>" required></div><div class="col-12"><button class="btn btn-primary">Simpan Pengaturan</button></div></form></div></div>

    <div class="card mb-4 border-start border-4 border-success"><div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"><div><h2 class="h5 mb-1">Import Jadwal Excel</h2><div class="text-secondary small">Upload file .xlsx. Sistem membaca tanggal, bulan, hari, shift, jam masuk, jam berangkat, Libur, dan dapat menebak tahun dari kecocokan nama hari.</div></div><a href="import_jadwal.php" class="btn btn-success text-nowrap">Upload & Preview Excel</a></div></div>

    <div class="card mb-4" id="jadwal"><div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3"><div><h2 class="h5 mb-1">Jadwal Mingguan</h2><div class="text-secondary small"><?=e($weekDate->format('d-m-Y'))?> s/d <?=e($weekEndDate->format('d-m-Y'))?></div></div><button type="button" class="btn btn-outline-primary" id="copyPrevious">Salin Jadwal Minggu Lalu</button></div>
        <div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-secondary btn-sm" href="?week=<?=e($prevWeek)?>#jadwal">◀ Minggu Sebelumnya</a><a class="btn btn-outline-dark btn-sm" href="?week=<?=e($thisWeek)?>#jadwal">Minggu Ini</a><a class="btn btn-outline-secondary btn-sm" href="?week=<?=e($nextWeek)?>#jadwal">Minggu Berikutnya ▶</a></div>
        <div id="ajaxAlert"></div>
        <div class="table-responsive"><table class="table schedule-table align-middle mb-0"><thead><tr><th style="min-width:180px">Tanggal &amp; Hari</th><th style="min-width:180px">Shift</th><th style="min-width:200px">Jam Berangkat</th><th style="min-width:120px">Status</th><th style="min-width:110px">Simpan</th></tr></thead><tbody>
        <?php foreach($days as $d): $badge = status_badge($d['status']); ?>
            <tr class="schedule-row <?= $d['locked']?'is-locked':'' ?> <?= $d['tanggal']===date('Y-m-d')?'today-row':'' ?>" data-date="<?=e($d['tanggal'])?>" data-status="<?=e($d['status'])?>">
                <td><strong><?=e($d['hari'])?></strong><br><span class="text-secondary small"><?=e($d['display'])?></span><?php if($d['locked']): ?><br><span class="text-danger small">Riwayat terkunci</span><?php endif; ?></td>
                <td><select class="form-select form-select-sm shift-select" <?= $d['locked']?'disabled':'' ?>><option value="">Belum diatur</option><?php foreach($shifts as $s): ?><option value="<?=e((string)$s['id'])?>" <?= (string)$d['shift_id']===(string)$s['id']?'selected':'' ?>><?=e($s['kode_shift'].' - '.$s['nama_shift'])?></option><?php endforeach; ?><option value="libur" <?= $d['status']==='libur'?'selected':'' ?>>Libur</option></select></td>
                <td><input type="time" class="form-control form-control-sm jam-input" value="<?=e($d['jam'])?>" step="60" <?= ($d['locked'] || $d['status']==='libur' || !$d['shift_id'])?'disabled':'' ?>><div class="quick-times mt-1 d-flex flex-wrap gap-1"></div></td>
                <td><span class="badge status-badge text-bg-<?=e($badge[0])?>"><?=e($badge[1])?></span></td>
                <td><span class="save-feedback small text-secondary"><?= $d['locked']?'Terkunci':'' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>

    <div class="card"><div class="card-body"><h2 class="h5">Riwayat Log</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Waktu</th><th>Nomor</th><th>Pesan</th><th>Status</th><th>Error</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?=e($l['waktu'])?></td><td><?=e($l['nomor_tujuan'])?></td><td><?=e($l['isi_pesan'])?></td><td><?=e($l['status'])?></td><td class="text-danger small"><?=e($l['pesan_error'] ?: '-')?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</main>
<script>
const optionMap = <?=json_encode($optionMap, JSON_UNESCAPED_SLASHES)?>;
const csrf = <?=json_encode(csrf_token())?>;
const weekStart = <?=json_encode($weekStart)?>;
const statusMeta = {belum_diatur:['secondary','Belum diatur'],terjadwal:['primary','Terjadwal'],terkirim:['success','Terkirim'],libur:['warning','Libur'],gagal:['danger','Gagal']};

function setStatus(row,status){row.dataset.status=status;const badge=row.querySelector('.status-badge');const meta=statusMeta[status]||statusMeta.belum_diatur;badge.className=`badge status-badge text-bg-${meta[0]}`;badge.textContent=meta[1]}
function renderQuickTimes(row){const shift=row.querySelector('.shift-select').value,wrap=row.querySelector('.quick-times'),input=row.querySelector('.jam-input');wrap.innerHTML='';if(!shift||shift==='libur')return;(optionMap[shift]||[]).forEach(jam=>{const b=document.createElement('button');b.type='button';b.className='btn btn-outline-secondary quick-time';b.textContent=jam;b.disabled=row.classList.contains('is-locked');b.addEventListener('click',()=>{input.value=jam;saveRow(row)});wrap.appendChild(b)})}
async function saveRow(row){const feedback=row.querySelector('.save-feedback'),shift=row.querySelector('.shift-select').value,input=row.querySelector('.jam-input');feedback.className='save-feedback small text-secondary';feedback.textContent='Menyimpan...';const body=new FormData();body.append('csrf',csrf);body.append('action','save');body.append('tanggal',row.dataset.date);body.append('shift_id',shift);body.append('jam_berangkat',input.value);try{const response=await fetch('ajax_jadwal.php',{method:'POST',body});const data=await response.json();if(!data.success)throw new Error(data.message||'Gagal menyimpan.');setStatus(row,data.status);feedback.className='save-feedback small text-success';feedback.textContent='✓ Tersimpan'}catch(error){feedback.className='save-feedback small text-danger';feedback.textContent='Gagal';showAlert(error.message,'danger')}}
function showAlert(message,type='success'){document.getElementById('ajaxAlert').innerHTML=`<div class="alert alert-${type} py-2">${message}</div>`;setTimeout(()=>document.getElementById('ajaxAlert').innerHTML='',4000)}

document.querySelectorAll('.schedule-row').forEach(row=>{const shift=row.querySelector('.shift-select'),input=row.querySelector('.jam-input');renderQuickTimes(row);if(row.classList.contains('is-locked'))return;shift.addEventListener('change',async()=>{if(shift.value==='libur'||!shift.value){input.value='';input.disabled=true}else input.disabled=false;renderQuickTimes(row);await saveRow(row)});input.addEventListener('change',()=>saveRow(row))});

document.getElementById('copyPrevious').addEventListener('click',async()=>{if(!confirm('Salin pola jadwal dari minggu sebelumnya ke minggu ini? Jadwal terkirim yang sudah lampau tidak akan diubah.'))return;const button=document.getElementById('copyPrevious');button.disabled=true;const body=new FormData();body.append('csrf',csrf);body.append('action','copy_previous');body.append('week_start',weekStart);try{const response=await fetch('ajax_jadwal.php',{method:'POST',body});const data=await response.json();if(!data.success)throw new Error(data.message||'Gagal menyalin jadwal.');showAlert(data.message,'success');setTimeout(()=>location.reload(),700)}catch(error){showAlert(error.message,'danger');button.disabled=false}});
setTimeout(()=>location.reload(),60000);
</script>
</body></html>
