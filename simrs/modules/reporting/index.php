<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/reporting.php';

require_permission('reporting.view');

[$from, $to, $errors] = rpt_range($_GET);
$kpi    = rpt_kpi($from, $to);
$daily  = rpt_daily_visits($from, $to);
$svcLbl = reg_service_labels();
$stLbl  = array_map(fn($v) => is_array($v) ? $v[0] : $v, reg_visit_status());
$ordLbl = array_map(fn($v) => $v[0], cpoe_order_types());
$ordSt  = array_map(fn($v) => $v[0], cpoe_status_labels());

audit_log('Buka dashboard laporan ' . $from . ' s/d ' . $to, 'visits', null, null);

$pageTitle    = 'Laporan & Dashboard';
$activeModule = 'reporting';
$breadcrumb   = 'Modul 15. Reporting &amp; Dashboard';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a class="active" href="<?= e(url('modules/reporting/index.php')) ?>">Ringkasan</a>
  <a href="<?= e(url('modules/reporting/visits.php?dari=' . $from . '&sampai=' . $to)) ?>">Laporan Kunjungan</a>
</div>

<div class="card">
  <h2>Periode Laporan</h2>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="get" action="<?= e(url('modules/reporting/index.php')) ?>" class="toolbar">
    <label class="inline">Dari <input type="date" name="dari" value="<?= e($from) ?>"></label>
    <label class="inline">Sampai <input type="date" name="sampai" value="<?= e($to) ?>"></label>
    <button type="submit" class="btn btn-primary">Tampilkan</button>
  </form>
</div>

<div class="rp-kpis">
  <div class="rp-kpi"><span>Total Pasien Terdaftar</span><b><?= $kpi['patients_total'] ?></b></div>
  <div class="rp-kpi"><span>Pasien Baru (periode)</span><b><?= $kpi['patients_new'] ?></b></div>
  <div class="rp-kpi"><span>Kunjungan</span><b><?= $kpi['visits'] ?></b></div>
  <div class="rp-kpi"><span>Kunjungan Selesai</span><b><?= $kpi['visits_done'] ?></b></div>
  <div class="rp-kpi"><span>Kunjungan Dibatalkan</span><b><?= $kpi['visits_cancel'] ?></b></div>
  <div class="rp-kpi"><span>Order CPOE</span><b><?= $kpi['orders'] ?></b></div>
  <div class="rp-kpi"><span>Order Cito</span><b><?= $kpi['orders_cito'] ?></b></div>
</div>

<div class="card">
  <h2>Kunjungan per Hari</h2>
  <?php $max = max(1, max($daily)); ?>
  <div class="rp-daily">
    <?php foreach ($daily as $d => $n): ?>
      <div class="rp-col" title="<?= e(reg_date_id($d)) ?>: <?= $n ?> kunjungan">
        <div class="rp-colbar" style="height:<?= $n > 0 ? max(4, (int) round($n / $max * 100)) : 0 ?>%"></div>
        <small><?= e(date('d', strtotime($d))) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="rp-grid">
  <div class="card"><h2>Kunjungan per Jenis Layanan</h2><?= rpt_bars_html(rpt_visits_by('service', $from, $to), $svcLbl) ?></div>
  <div class="card"><h2>Kunjungan per Status</h2><?= rpt_bars_html(rpt_visits_by('status', $from, $to), $stLbl) ?></div>
  <div class="card"><h2>Kunjungan per Poliklinik</h2><?= rpt_bars_html(rpt_visits_by('poli', $from, $to)) ?></div>
  <div class="card"><h2>10 Dokter Terbanyak</h2><?= rpt_bars_html(rpt_visits_by('dokter', $from, $to)) ?></div>
  <div class="card"><h2>Order per Jenis</h2><?= rpt_bars_html(rpt_orders_by('type', $from, $to), $ordLbl) ?></div>
  <div class="card"><h2>Order per Status</h2><?= rpt_bars_html(rpt_orders_by('status', $from, $to), $ordSt) ?></div>
</div>

<div class="card muted">Modul lain (laboratorium, farmasi, billing, dll.) akan ditambahkan ke laporan ini setelah modulnya selesai dibangun.</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>