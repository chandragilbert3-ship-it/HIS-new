<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

// Wajib login
if (function_exists('require_login')) {
    require_login();
} elseif (!function_exists('current_user') || current_user() === null) {
    redirect('login.php');
}
$user = current_user();
$uid  = (int) ($user['id'] ?? 0);

// Aman: jalankan query, kalau tabel/kolom belum ada -> null (kartu disembunyikan)
function dash_count(string $sql, array $p = []): ?int
{
    try {
        $st = db()->prepare($sql);
        $st->execute($p);
        return (int) $st->fetchColumn();
    } catch (Throwable $ex) {
        return null;
    }
}

// Nama lengkap & peran dari database
$nama = (string) ($user['nama'] ?? $user['name'] ?? $user['username'] ?? '');
$roles = [];
try {
    $st = db()->prepare('SELECT u.nama, r.role_name FROM users u
        LEFT JOIN user_roles ur ON ur.user_id = u.user_id
        LEFT JOIN roles r ON r.role_id = ur.role_id
        WHERE u.user_id = :uid ORDER BY r.role_name');
    $st->execute([':uid' => $uid]);
    foreach ($st->fetchAll() as $r) {
        if ($r['nama'] !== null && $r['nama'] !== '') { $nama = (string) $r['nama']; }
        if ($r['role_name'] !== null) { $roles[] = (string) $r['role_name']; }
    }
} catch (Throwable $ex) { /* biarkan kosong */ }

// Daftar modul: key => [judul, ikon, deskripsi]
$all = [
    'registration' => ['Pendaftaran Pasien', '📝', 'Registrasi pasien dan kunjungan'],
    'scheduling'   => ['Penjadwalan', '📅', 'Jadwal dokter dan janji temu'],
    'outpatient'   => ['Rawat Jalan', '🩺', 'Antrian dan pemeriksaan poli'],
    'emergency'    => ['IGD', '🚑', 'Triase dan penanganan gawat darurat'],
    'inpatient'    => ['Rawat Inap', '🛏️', 'Admisi, kamar, dan pulang'],
    'nursing'      => ['Keperawatan', '👩‍⚕️', 'Asuhan dan catatan perawat'],
    'cpoe'         => ['CPOE', '📋', 'Order obat, lab, dan radiologi'],
    'emr'          => ['Rekam Medis (EMR)', '📁', 'Riwayat dan catatan medis pasien'],
    'laboratory'   => ['Laboratorium', '🧪', 'Order dan hasil laboratorium'],
    'radiology'    => ['Radiologi', '🩻', 'Order dan hasil radiologi'],
    'pharmacy'     => ['Farmasi', '💊', 'Resep dan pengeluaran obat'],
    'inventory'    => ['Gudang & Inventori', '📦', 'Stok, penerimaan, permintaan, opname'],
    'billing'      => ['Billing & Asuransi', '💳', 'Tagihan dan pembayaran'],
    'masterdata'   => ['Master Data', '🗂️', 'Data referensi rumah sakit'],
    'reporting'    => ['Laporan', '📊', 'Laporan dan analitik'],
    'auth'         => ['Pengguna & Hak Akses', '🔐', 'Akun, peran, dan audit'],
];

$cards = [];
foreach ($all as $key => [$title, $icon, $desc]) {
    if (!can($key . '.view')) { continue; }
    $file = __DIR__ . '/modules/' . $key . '/index.php';
    $cards[] = ['key' => $key, 'title' => $title, 'icon' => $icon, 'desc' => $desc, 'ready' => is_file($file)];
}
$readyCount = count(array_filter($cards, fn($c) => $c['ready']));

// Ringkasan angka sesuai hak akses
$stats = [];
if (can('registration.view')) {
    $stats[] = ['Pasien terdaftar', dash_count('SELECT COUNT(*) FROM patients')];
}
if (can('pharmacy.view')) {
    $stats[] = ['Resep menunggu', dash_count("SELECT COUNT(*) FROM prescriptions WHERE status IN ('new','prepared')")];
}
if (can('laboratory.view') || can('radiology.view') || can('cpoe.view')) {
    $stats[] = ['Order klinis aktif', dash_count("SELECT COUNT(*) FROM orders WHERE status IN ('pending','in_progress','ordered')")];
}
if (can('inventory.view')) {
    $stats[] = ['Batch tersedia', dash_count("SELECT COUNT(*) FROM stock_batches WHERE status='available' AND quantity_on_hand>0")];
    $stats[] = ['Batch segera kedaluwarsa (30 hari)', dash_count("SELECT COUNT(*) FROM stock_batches WHERE status='available' AND quantity_on_hand>0 AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")];
}
if (can('auth.view')) {
    $stats[] = ['Pengguna aktif', dash_count("SELECT COUNT(*) FROM users WHERE status='active'")];
}
$stats = array_filter($stats, fn($s) => $s[1] !== null);

audit_log('Buka dashboard', 'dashboard', null, null);

$pageTitle    = 'Dashboard';
$activeModule = 'dashboard';
$breadcrumb   = 'Beranda';
require APP_ROOT . '/includes/layout_header.php';
?>
<style>
  .dash-hero{background:linear-gradient(135deg,#1e5fbf,#2f80ed);color:#fff;border-radius:12px;padding:22px 26px;margin-bottom:18px}
  .dash-hero h2{margin:0 0 6px;color:#fff;font-size:1.35rem}
  .dash-hero p{margin:0;opacity:.92}
  .dash-role{display:inline-block;background:rgba(255,255,255,.2);border-radius:999px;padding:2px 12px;margin:8px 6px 0 0;font-size:.85rem}
  .dash-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px}
  .dash-stat{background:#fff;border:1px solid #dbe6f5;border-radius:10px;padding:14px 16px}
  .dash-stat b{display:block;font-size:1.6rem;color:#1e5fbf}
  .dash-stat span{color:#5b6b82;font-size:.85rem}
  .dash-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px}
  .dash-card{display:block;background:#fff;border:1px solid #dbe6f5;border-radius:12px;padding:16px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
  .dash-card:hover{box-shadow:0 6px 18px rgba(30,95,191,.15);transform:translateY(-2px)}
  .dash-card.off{opacity:.55;pointer-events:none}
  .dash-card .ic{font-size:1.8rem}
  .dash-card h3{margin:8px 0 4px;font-size:1rem;color:#1e5fbf}
  .dash-card p{margin:0;color:#5b6b82;font-size:.85rem}
  .dash-card small{color:#a15c00}
</style>

<div class="dash-hero">
  <h2>Selamat datang, <?= e($nama !== '' ? $nama : 'Pengguna') ?></h2>
  <p>Rumah Sakit Dunia Indah &mdash; Sistem Informasi Manajemen Rumah Sakit</p>
  <?php foreach ($roles as $rn): ?><span class="dash-role"><?= e($rn) ?></span><?php endforeach; ?>
  <?php if (!$roles): ?><span class="dash-role">Belum ada peran</span><?php endif; ?>
</div>

<?php if ($stats): ?>
<div class="dash-stats">
  <?php foreach ($stats as [$label, $val]): ?>
    <div class="dash-stat"><b><?= e(number_format((int) $val, 0, ',', '.')) ?></b><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Modul yang dapat Anda akses (<?= (int) $readyCount ?>)</h2>
  <?php if (!$cards): ?>
    <p class="empty">Peran Anda belum memiliki hak akses modul. Hubungi Admin IT.</p>
  <?php endif; ?>
  <div class="dash-grid">
    <?php foreach ($cards as $c): ?>
      <a class="dash-card<?= $c['ready'] ? '' : ' off' ?>" href="<?= e(url('modules/' . $c['key'] . '/index.php')) ?>">
        <div class="ic"><?= $c['icon'] ?></div>
        <h3><?= e($c['title']) ?></h3>
        <p><?= e($c['desc']) ?></p>
        <?php if (!$c['ready']): ?><small>Segera hadir</small><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>