<?php
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_setting') {
        $nomor = preg_replace('/\D+/', '', $_POST['nomor_wa_tujuan'] ?? '');
        $nama = trim($_POST['nama_panggilan'] ?? '');
        $template = trim($_POST['template_pesan'] ?? '');
        if ($nomor === '' || $template === '') {
            flash('danger', 'Nomor WhatsApp dan template pesan wajib diisi.');
        } else {
            $stmt = $pdo->prepare('UPDATE pengaturan SET nomor_wa_tujuan=?, nama_panggilan=?, template_pesan=? WHERE id=1');
            $stmt->execute([$nomor, $nama, $template]);
            flash('success', 'Pengaturan berhasil disimpan.');
        }
        redirect('index.php#pengaturan');
    }

    if ($action === 'save_schedule') {
        $tanggal = $_POST['tanggal'] ?? '';
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $jam = $_POST['jam_berangkat'] ?? '';
        $valid = $pdo->prepare('SELECT COUNT(*) FROM opsi_jam_berangkat WHERE shift_id=? AND jam_berangkat=?');
        $valid->execute([$shiftId, $jam]);
        if (!$tanggal || !$shiftId || !$jam || !$valid->fetchColumn()) {
            flash('danger', 'Data jadwal tidak valid.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO jadwal_harian (tanggal,shift_id,jam_berangkat_terpilih,status) VALUES (?,?,?,'pending') ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id), jam_berangkat_terpilih=VALUES(jam_berangkat_terpilih), status='pending', waktu_terkirim=NULL, pesan_error=NULL");
            $stmt->execute([$tanggal, $shiftId, $jam]);
            flash('success', 'Jadwal berhasil disimpan.');
        }
        redirect('index.php#jadwal');
    }

    if ($action === 'send_now') {
        $pdo->exec("INSERT INTO perintah_bot (jenis,payload,status) VALUES ('kirim_manual',JSON_OBJECT('source','dashboard'),'pending')");
        flash('success', 'Perintah kirim manual masuk antrean bot.');
        redirect('index.php#status');
    }
}

$setting = $pdo->query('SELECT * FROM pengaturan WHERE id=1')->fetch();
$runtime = $pdo->query('SELECT * FROM bot_runtime WHERE id=1')->fetch();
$shifts = $pdo->query('SELECT * FROM shift ORDER BY jam_mulai')->fetchAll();
$options = $pdo->query('SELECT * FROM opsi_jam_berangkat ORDER BY shift_id,jam_berangkat')->fetchAll();
$optionMap = [];
foreach ($options as $o) $optionMap[$o['shift_id']][] = substr($o['jam_berangkat'],0,5);
$schedules = $pdo->query('SELECT j.*,s.kode_shift,s.nama_shift FROM jadwal_harian j JOIN shift s ON s.id=j.shift_id ORDER BY j.tanggal DESC LIMIT 20')->fetchAll();
$logs = $pdo->query('SELECT * FROM log_pesan ORDER BY id DESC LIMIT 50')->fetchAll();
$flash = get_flash();
$heartbeatAge = !empty($runtime['heartbeat_at']) ? time() - strtotime($runtime['heartbeat_at']) : 999999;
$serviceAlive = $heartbeatAge <= 120;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Bot WA</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f7fb}.card{border:0;box-shadow:0 .125rem .5rem rgba(0,0,0,.06)}.qr{max-width:280px;width:100%}.nav-pills .nav-link{color:#495057}</style></head>
<body><nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Bot WA Berangkat Kerja</span><a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a></div></nav>
<main class="container py-4">
<?php if($flash): ?><div class="alert alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>
<div class="row g-3 mb-4" id="status"><div class="col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Status Bot</h2><p class="mb-2">WhatsApp: <span class="badge text-bg-<?= $setting['status_koneksi_bot']==='connected'?'success':'warning' ?>"><?=e($setting['status_koneksi_bot'])?></span></p><p class="mb-2">Service Node.js: <span class="badge text-bg-<?= $serviceAlive?'success':'danger' ?>"><?= $serviceAlive?'aktif':'heartbeat tidak terdeteksi' ?></span></p><p class="text-secondary small">Heartbeat: <?=e($runtime['heartbeat_at'] ?: '-')?><br><?=e($runtime['info'] ?: '-')?></p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="send_now"><button class="btn btn-success" onclick="return confirm('Kirim pesan sekarang?')">Kirim Sekarang</button></form></div></div></div><div class="col-lg-6"><div class="card h-100"><div class="card-body text-center"><h2 class="h5">QR WhatsApp</h2><?php if(!empty($runtime['qr_base64'])): ?><img class="qr" src="<?=e($runtime['qr_base64'])?>" alt="QR WhatsApp"><p class="small text-secondary mt-2">Scan melalui WhatsApp > Perangkat tertaut.</p><?php else: ?><div class="py-5 text-secondary">QR tidak tersedia. Jika status connected, ini normal.</div><?php endif; ?></div></div></div></div>

<div class="card mb-4" id="pengaturan"><div class="card-body"><h2 class="h5">Pengaturan</h2><form method="post" class="row g-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_setting"><div class="col-md-4"><label class="form-label">Nomor WA Tujuan</label><input class="form-control" name="nomor_wa_tujuan" value="<?=e($setting['nomor_wa_tujuan'])?>" placeholder="62812..." required></div><div class="col-md-3"><label class="form-label">Nama Panggilan</label><input class="form-control" name="nama_panggilan" value="<?=e($setting['nama_panggilan'])?>"></div><div class="col-md-5"><label class="form-label">Template Pesan</label><input class="form-control" name="template_pesan" value="<?=e($setting['template_pesan'])?>" required></div><div class="col-12"><button class="btn btn-primary">Simpan Pengaturan</button></div></form></div></div>

<div class="card mb-4" id="jadwal"><div class="card-body"><h2 class="h5">Jadwal Harian</h2><form method="post" class="row g-3 align-items-end"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_schedule"><div class="col-md-4"><label class="form-label">Tanggal</label><input type="date" class="form-control" name="tanggal" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required></div><div class="col-md-4"><label class="form-label">Shift</label><select class="form-select" name="shift_id" id="shift" required><option value="">Pilih shift</option><?php foreach($shifts as $s): ?><option value="<?=e($s['id'])?>"><?=e($s['kode_shift'].' - '.$s['nama_shift'].' '.substr($s['jam_mulai'],0,5).'-'.substr($s['jam_selesai'],0,5))?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Jam Berangkat</label><select class="form-select" name="jam_berangkat" id="jam" required><option value="">Pilih shift dulu</option></select></div><div class="col-md-1"><button class="btn btn-primary w-100">Simpan</button></div></form><hr><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Tanggal</th><th>Shift</th><th>Jam</th><th>Status</th><th>Terkirim</th></tr></thead><tbody><?php foreach($schedules as $j): ?><tr><td><?=e($j['tanggal'])?></td><td><?=e($j['kode_shift'].' - '.$j['nama_shift'])?></td><td><?=e(substr($j['jam_berangkat_terpilih'],0,5))?></td><td><?=e($j['status'])?></td><td><?=e($j['waktu_terkirim'] ?: '-')?></td></tr><?php endforeach; ?></tbody></table></div></div></div>

<div class="card"><div class="card-body"><h2 class="h5">Riwayat Log</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Waktu</th><th>Nomor</th><th>Pesan</th><th>Status</th><th>Error</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?=e($l['waktu'])?></td><td><?=e($l['nomor_tujuan'])?></td><td><?=e($l['isi_pesan'])?></td><td><?=e($l['status'])?></td><td class="text-danger small"><?=e($l['pesan_error'] ?: '-')?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</main>
<script>const map=<?=json_encode($optionMap,JSON_UNESCAPED_SLASHES)?>;const shift=document.getElementById('shift'),jam=document.getElementById('jam');shift.addEventListener('change',()=>{jam.innerHTML='<option value="">Pilih jam</option>';(map[shift.value]||[]).forEach(v=>jam.add(new Option(v,v+':00')))});setTimeout(()=>location.reload(),60000);</script></body></html>
