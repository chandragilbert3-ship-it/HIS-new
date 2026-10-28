<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';

require_permission('cpoe.view');

$q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$semua  = ($_GET['semua'] ?? '') === '1';
$dokter = cpoe_current_doctor();
$visits = cpoe_active_visits($q, $semua, $dokter ? (int) $dokter['doctor_id'] : null);

$pageTitle    = 'CPOE - Pilih Kunjungan';
$activeModule = 'cpoe';
$breadcrumb   = 'Modul 9. CPOE';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a class="active" href="<?= e(url('modules/cpoe/index.php')) ?>">1. Pilih Kunjungan</a>
  <a href="<?= e(url('modules/cpoe/orders.php')) ?>">2. Order Saya</a>
</div>

<div class="card">
  <h2>Pilih Kunjungan Pasien</h2>

  <?php if (!$dokter): ?>
    <div class="alert alert-warning">Akun Anda belum terhubung ke data dokter aktif (Master Data &rarr; Dokter). Anda hanya bisa melihat daftar, belum bisa membuat order.</div>
  <?php endif; ?>

  <form method="get" action="<?= e(url('modules/cpoe/index.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama / no. RM / no. antrean..." autofocus>
    <label class="inline"><input type="checkbox" name="semua" value="1" <?= $semua ? 'checked' : '' ?>> Tampilkan semua dokter</label>
    <button type="submit" class="btn btn-outline">Cari</button>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>Visit</th><th>Antrean</th><th>Pasien</th><th>Layanan</th><th>Dokter</th><th>Alergi</th><th>Aksi</th></tr>
      <?php if (!$visits): ?>
        <tr><td colspan="7" class="empty">Tidak ada kunjungan aktif yang cocok.</td></tr>
      <?php endif; ?>
      <?php foreach ($visits as $v): ?>
        <tr>
          <td>#<?= (int) $v['visit_id'] ?><br><span class="muted"><?= e(reg_date_id($v['visit_date'])) ?></span></td>
          <td><?= $v['queue_no'] ? e($v['queue_no']) : '&mdash;' ?></td>
          <td><b><?= e($v['nama_pasien']) ?></b><br><code class="k"><?= e($v['no_rm']) ?></code></td>
          <td><?= e(reg_service_labels()[$v['service_type']] ?? $v['service_type']) ?><br><span class="muted"><?= e($v['polyclinic_name']) ?></span></td>
          <td><?= $v['doctor_name'] ? e($v['doctor_name']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= (int) $v['jml_alergi'] > 0 ? '<span class="cp-badge cp-warn">⚠ ' . (int) $v['jml_alergi'] . '</span>' : '<span class="muted">&mdash;</span>' ?></td>
          <td class="aksi">
            <?php if ($dokter && can('cpoe.create')): ?>
              <a class="action-link" href="<?= e(url('modules/cpoe/order_form.php?visit_id=' . (int) $v['visit_id'])) ?>">➕ Buat Order</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>