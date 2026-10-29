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
<style>
  .lg { --b1:#0b3d91; --b2:#1e5fbf; --b3:#2f80ed; --ink:#12263f; --muted:#5b6b82; --line:#d7e3f5;
        min-height:100vh; display:grid; grid-template-columns:1.05fr 1fr; background:#f4f8ff; font-family:inherit; }
  .lg * { box-sizing:border-box; }

  /* Panel kiri */
  .lg-hero { position:relative; overflow:hidden; color:#fff; padding:48px 56px; display:flex; flex-direction:column; justify-content:space-between;
             background:linear-gradient(145deg,var(--b1) 0%,var(--b2) 55%,var(--b3) 100%); }
  .lg-hero::before, .lg-hero::after { content:""; position:absolute; border-radius:50%; background:rgba(255,255,255,.07); }
  .lg-hero::before { width:520px; height:520px; right:-180px; top:-160px; }
  .lg-hero::after  { width:380px; height:380px; left:-140px; bottom:-140px; background:rgba(255,255,255,.06); }
  .lg-hero > * { position:relative; z-index:1; }
  .lg-brand { display:flex; align-items:center; gap:14px; }
  .lg-brand .lg-badge { width:56px; height:56px; border-radius:14px; background:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 18px rgba(0,0,0,.18); }
  .lg-brand img { width:40px; height:40px; object-fit:contain; }
  .lg-brand b { display:block; font-size:1.15rem; letter-spacing:.02em; }
  .lg-brand span { font-size:.85rem; opacity:.85; }
  .lg-hero h2 { margin:0 0 12px; font-size:2.1rem; line-height:1.2; color:#fff; font-weight:700; }
  .lg-hero p.lead { margin:0 0 28px; max-width:440px; opacity:.9; line-height:1.6; }
  .lg-feat { list-style:none; margin:0; padding:0; display:grid; grid-template-columns:1fr 1fr; gap:12px 20px; max-width:480px; }
  .lg-feat li { display:flex; align-items:center; gap:10px; font-size:.92rem; }
  .lg-feat i { flex:none; width:30px; height:30px; border-radius:9px; background:rgba(255,255,255,.16); display:flex; align-items:center; justify-content:center; }
  .lg-feat svg { width:16px; height:16px; stroke:#fff; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
  .lg-foot { font-size:.8rem; opacity:.75; }

  /* Panel kanan */
  .lg-side { display:flex; align-items:center; justify-content:center; padding:32px 24px; }
  .lg-card { width:100%; max-width:410px; background:#fff; border:1px solid var(--line); border-radius:18px; padding:36px 34px; box-shadow:0 18px 50px rgba(30,95,191,.12); }
  .lg-mobile-brand { display:none; }
  .lg-card h1 { margin:0 0 6px; font-size:1.6rem; color:var(--ink); text-align:left; }
  .lg-card .lg-sub { margin:0 0 24px; color:var(--muted); font-size:.95rem; text-align:left; }
  .lg-field { margin-bottom:16px; }
  .lg-field label { display:block; margin-bottom:6px; font-size:.85rem; font-weight:600; color:var(--ink); }
  .lg-input { position:relative; }
  .lg-input > svg { position:absolute; left:13px; top:50%; transform:translateY(-50%); width:18px; height:18px; stroke:#7b8da6; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; pointer-events:none; }
  .lg-input input { width:100%; height:46px; padding:0 44px 0 42px; border:1.5px solid var(--line); border-radius:11px; background:#f9fbff; font-size:.95rem; color:var(--ink); transition:border-color .15s, box-shadow .15s, background .15s; }
  .lg-input input:focus { outline:none; border-color:var(--b3); background:#fff; box-shadow:0 0 0 4px rgba(47,128,237,.15); }
  .lg-eye { position:absolute; right:6px; top:50%; transform:translateY(-50%); width:36px; height:36px; border:0; background:transparent; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
  .lg-eye:hover { background:#eaf2ff; }
  .lg-eye svg { width:18px; height:18px; stroke:#7b8da6; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
  .lg-btn { width:100%; height:48px; margin-top:6px; border:0; border-radius:11px; font-size:1rem; font-weight:600; color:#fff; cursor:pointer;
            background:linear-gradient(135deg,var(--b2),var(--b3)); box-shadow:0 8px 20px rgba(30,95,191,.28); transition:transform .12s, box-shadow .12s, filter .12s; }
  .lg-btn:hover { transform:translateY(-1px); box-shadow:0 12px 26px rgba(30,95,191,.34); filter:brightness(1.04); }
  .lg-btn:active { transform:translateY(0); }
  .lg-btn:focus-visible { outline:3px solid rgba(47,128,237,.45); outline-offset:2px; }
  .lg-alert { padding:11px 14px; border-radius:10px; font-size:.9rem; margin-bottom:16px; border:1px solid; }
  .lg-alert.error { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
  .lg-alert.success { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
  .lg-alert.warning, .lg-alert.info { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }
  .lg-secure { display:flex; align-items:center; gap:8px; justify-content:center; margin-top:18px; color:var(--muted); font-size:.8rem; }
  .lg-secure svg { width:15px; height:15px; stroke:var(--muted); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
  .lg-demo { margin-top:18px; border:1px dashed var(--line); border-radius:11px; background:#fafcff; }
  .lg-demo summary { cursor:pointer; padding:10px 14px; font-size:.82rem; font-weight:600; color:var(--b2); }
  .lg-demo table { width:100%; border-collapse:collapse; font-size:.78rem; }
  .lg-demo td { padding:5px 14px; border-top:1px solid #edf2fb; color:var(--ink); }
  .lg-demo td:first-child { color:var(--muted); }

  @media (max-width:900px) {
    .lg { grid-template-columns:1fr; background:linear-gradient(160deg,var(--b1),var(--b3)); }
    .lg-hero { display:none; }
    .lg-card { padding:28px 22px; }
    .lg-mobile-brand { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
    .lg-mobile-brand img { width:44px; height:44px; object-fit:contain; }
    .lg-mobile-brand b { display:block; color:var(--ink); }
    .lg-mobile-brand span { font-size:.82rem; color:var(--muted); }
  }
  @media (prefers-reduced-motion:reduce) { .lg-btn, .lg-input input { transition:none; } }
</style>
</head>
<body>
<div class="lg">

  <aside class="lg-hero">
    <div class="lg-brand">
      <div class="lg-badge"><img src="<?= e(url('assets/images/logo-rs.png')) ?>" alt="Logo Rumah Sakit Dunia Indah"></div>
      <div><b>SIMRS</b><span>Rumah Sakit Dunia Indah</span></div>
    </div>

    <div>
      <h2>Satu sistem untuk seluruh layanan rumah sakit</h2>
      <p class="lead">Dari pendaftaran sampai pembayaran, semua data pasien tercatat rapi, aman, dan dapat diakses sesuai peran Anda.</p>
      <ul class="lg-feat">
        <li><i><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></i>Registrasi &amp; Rawat Jalan</li>
        <li><i><svg viewBox="0 0 24 24"><path d="M3 12h4l3-9 4 18 3-9h4"/></svg></i>IGD &amp; Rawat Inap</li>
        <li><i><svg viewBox="0 0 24 24"><path d="M9 3h6v5l4 9a3 3 0 0 1-2.7 4H7.7A3 3 0 0 1 5 17l4-9V3z"/></svg></i>Lab, Radiologi &amp; Farmasi</li>
        <li><i><svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg></i>Rekam Medis Elektronik</li>
        <li><i><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></i>Billing &amp; Asuransi</li>
        <li><i><svg viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg></i>Gudang &amp; Inventori</li>
      </ul>
    </div>

    <div class="lg-foot">&copy; <?= e(date('Y')) ?> Rumah Sakit Dunia Indah &middot; Kelompok 5</div>
  </aside>

  <main class="lg-side">
    <div class="lg-card">

      <div class="lg-mobile-brand">
        <img src="<?= e(url('assets/images/logo-rs.png')) ?>" alt="Logo Rumah Sakit Dunia Indah">
        <div><b>SIMRS</b><span>Rumah Sakit Dunia Indah</span></div>
      </div>

      <h1>Selamat datang</h1>
      <p class="lg-sub">Masuk dengan akun Anda untuk melanjutkan.</p>

      <?php if ($flash): ?>
        <div class="lg-alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="lg-alert error" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>

        <div class="lg-field">
          <label for="username">Username</label>
          <div class="lg-input">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="username" name="username" maxlength="50" placeholder="Masukkan username"
                   value="<?= e($username) ?>" required autofocus>
          </div>
        </div>

        <div class="lg-field">
          <label for="password">Password</label>
          <div class="lg-input">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="password" name="password" maxlength="200" placeholder="Masukkan password" required>
            <button type="button" class="lg-eye" id="lgEye" aria-label="Tampilkan password" aria-pressed="false">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="lg-btn">Masuk</button>
      </form>

      <div class="lg-secure">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Koneksi dan akses Anda dilindungi
      </div>

      <?php if (SHOW_DEMO_CREDENTIALS): ?>
        <details class="lg-demo">
          <summary>Akun demo (hanya untuk pengembangan)</summary>
          <table>
            <?php foreach ($demoAkun as [$peran, $user, $pass]): ?>
              <tr>
                <td><?= e($peran) ?></td>
                <td><?= e($user) ?></td>
                <td><?= e($pass) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </details>
      <?php endif; ?>

    </div>
  </main>
</div>
<script>
  (function () {
    var btn = document.getElementById('lgEye'), pw = document.getElementById('password');
    if (!btn || !pw) { return; }
    btn.addEventListener('click', function () {
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    });
  })();
</script>
</body>
</html>