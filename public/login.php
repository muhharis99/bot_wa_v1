<?php
require_once __DIR__ . '/functions.php';
if (!empty($_SESSION['logged_in'])) redirect('index.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    if (hash_equals((string)env('DASHBOARD_USER', 'admin'), $user) && hash_equals((string)env('DASHBOARD_PASS', 'ganti-password'), $pass)) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        redirect('index.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login Bot WA</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:440px"><div class="card shadow-sm border-0"><div class="card-body p-4"><h1 class="h4 mb-1">Bot WA Berangkat Kerja</h1><p class="text-secondary mb-4">Login dashboard</p><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required autofocus></div><div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div><button class="btn btn-primary w-100">Masuk</button></form></div></div></main></body></html>
