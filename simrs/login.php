<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

send_no_cache_headers();

// Sudah login? Tidak perlu melihat halaman login lagi.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error    = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Formulir kedaluwarsa. Silakan coba kirim lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } elseif (mb_strlen($username) > 50 || strlen($password) > 200) {
            $error = 'Username atau password salah.';
        } else {
            $error = attempt_login($username, $password);
            if ($error === null) {
                redirect('dashboard.php');
            }
        }
    }
}

$flash = flash_get();

// Daftar akun demo (hanya tampil kalau SHOW_DEMO_CREDENTIALS = true)
$demoAkun = [
    ['Admin IT',           'admin.it',   'AdminIT#2026'],
    ['Petugas Registrasi', 'registrasi', 'Registrasi#2026'],
    ['Dokter',             'dokter',     'Dokter#2026'],
    ['Dokter (Anak)',      'dokter2',    'Dokter2#2026'],
    ['Perawat',            'perawat',    'Perawat#2026'],
    ['Analis Lab',         'lab',        'Lab#2026'],
    ['Radiografer',        'radiologi',  'Radiologi#2026'],
    ['Apoteker',           'apoteker',   'Apoteker#2026'],
    ['Kasir',              'kasir',      'Kasir#2026'],
    ['Petugas Gudang',     'gudang',     'Gudang#2026'],
    ['Manajemen',          'manajemen',  'Manajemen#2026'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - SIMRS Rumah Sakit Dunia Indah</title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">

      <div class="login-logo">
        <img src="<?= e(url('assets/images/logo-rs.png')) ?>" alt="Logo Rumah Sakit Dunia Indah">
      </div>
      <h1>SIMRS</h1>
      <p class="login-sub">Rumah Sakit Dunia Indah</p>

      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" maxlength="50"
                 value="<?= e($username) ?>" required autofocus>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" maxlength="200" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
      </form>

      <?php if (SHOW_DEMO_CREDENTIALS): ?>
        <div class="demo-box">
          <strong>Akun demo (hanya untuk pengembangan)</strong>
          <table>
            <?php foreach ($demoAkun as [$peran, $user, $pass]): ?>
              <tr>
                <td><?= e($peran) ?></td>
                <td><?= e($user) ?></td>
                <td><?= e($pass) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>