<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/reporting.php';

require_permission('reporting.view');

[$from, $to, $errors] = rpt_range($_GET);
$svcLbl = reg_service_labels();
$stLbl  = array_map(fn($v) => is_array($v) ? $v[0] : $v, reg_visit_status());

$service = (string) ($_GET['layanan'] ?? '');
$status  = (string) ($_GET['status'] ?? '');
if (!isset($svcLbl[$service])) {
    $service = '';
}
if (!isset($stLbl[$status])) {
    $status = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$per  = 20;
[$rows, $total] = rpt_visit_rows($from, $to, $service, $status, $page, $per);
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
    [$rows, $total] = rpt_visit_rows($from, $to, $service, $status, $page, $per);
}

$query = ['dari' => $from, 'sampai' => $to, 'layanan' => $service, 'status' => $status];

$pageTitle    = 'Laporan Kunjungan';
$activeModule = 'reporting';
$breadcrumb   = 'Modul 15. Reporting &rsaquo; Laporan Kunjungan';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/reporting/index.php?dari=' . $from . '&sampai=' . $to)) ?>">Ringkasan</a>
  <a class="active" href="<?= e(url('modules/reporting/visits.php')) ?>">Laporan Kunjungan</a>
</div>

<div class="card">
  <h2>Laporan Kunjungan</h2>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="get" action="<?= e(url('modules/reporting/visits.php')) ?>" class="toolbar">
    <label class="inline">Dari <input type="date" name="dari" value="<?= e($from) ?>"></label>
    <label class="inline">Sampai <input type="date" name="sampai" value="<?= e($to) ?>"></label>
    <select name="layanan">
      <option value="">Semua layanan</option>
      <?php foreach ($svcLbl as $c => $l): ?><option value="<?= e($c) ?>" <?= $service === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Semua status</option>
      <?php foreach ($stLbl as $c => $l): ?><option value="<?= e($c) ?>" <?= $status === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Tampilkan</button>
    <a class="btn btn-outline" href="<?= e(url('modules/reporting/export.php?' . http_build_query($query))) ?>">⬇ Ekspor CSV</a>
  </form>

  <p><b><?= $total ?></b> kunjungan ditemukan.</p>

  <div class="table-wrap">
    <table>
      <tr><th>Visit</th><th>Tanggal</th><th>Antrean</th><th>Pasien</th><th>Layanan</th><th>Poliklinik</th><th>Dokter</th><th>Status</th></tr>
      <?php if (!$rows): ?><tr><td colspan="8" class="empty">Tidak ada data pada filter ini.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>#<?= (int) $r['visit_id'] ?></td>
          <td><?= e(reg_date_id($r['visit_date'])) ?></td>
          <td><?= $r['queue_no'] ? e($r['queue_no']) : '&mdash;' ?></td>
          <td><?= e($r['nama_pasien']) ?><br><code class="k"><?= e($r['no_rm']) ?></code></td>
          <td><?= e($svcLbl[$r['service_type']] ?? $r['service_type']) ?></td>
          <td><?= e($r['polyclinic_name']) ?></td>
          <td><?= $r['doctor_name'] ? e($r['doctor_name']) : '&mdash;' ?></td>
          <td><?= e($stLbl[$r['status']] ?? $r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/reporting/visits.php'), $query, $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>