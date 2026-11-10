<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/scheduling.php';

require_permission('scheduling.view');

$valid = fn(string $s): ?string => reg_valid_date($s);
$from  = $valid((string) ($_GET['dari'] ?? '')) ?? date('Y-m-d', strtotime('-7 days'));
$to    = $valid((string) ($_GET['sampai'] ?? '')) ?? date('Y-m-d', strtotime('+' . SCH_HORIZON_DAYS . ' days'));
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$status = (string) ($_GET['status'] ?? '');
$q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 15;

[$rows, $total] = sch_list_appointments($from, $to, $status, $q, $page, $per);
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
    [$rows, $total] = sch_list_appointments($from, $to, $status, $q, $page, $per);
}
$stat  = sch_status_labels();
$query = ['dari' => $from, 'sampai' => $to, 'status' => $status, 'q' => $q];
$today = date('Y-m-d');

$pageTitle    = 'Daftar Appointment';
$activeModule = 'scheduling';
$breadcrumb   = 'Modul 3. Penjadwalan &rsaquo; Daftar Appointment';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/scheduling/index.php')) ?>">1. Jadwal Dokter</a>
  <?php if (can('scheduling.create')): ?><a href="<?= e(url('modules/scheduling/booking.php')) ?>">2. Booking Appointment</a><?php endif; ?>
  <a class="active" href="<?= e(url('modules/scheduling/appointments.php')) ?>">3. Daftar Appointment</a>
</div>

<div class="card">
  <h2>Daftar Appointment</h2>

  <form method="get" action="<?= e(url('modules/scheduling/appointments.php')) ?>" class="toolbar">
    <label class="inline">Dari <input type="date" name="dari" value="<?= e($from) ?>"></label>
    <label class="inline">Sampai <input type="date" name="sampai" value="<?= e($to) ?>"></label>
    <select name="status"><option value="">Semua status</option>
      <?php foreach ($stat as $c => [$l]): ?><option value="<?= e($c) ?>" <?= $status === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Kode booking / nama / no. RM">
    <button type="submit" class="btn btn-outline">Filter</button>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>Kode</th><th>Pasien</th><th>Tanggal</th><th>Jadwal</th><th>Status</th><th>Aksi</th></tr>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">Tidak ada appointment.</td></tr><?php endif; ?>
      <?php foreach ($rows as $a): ?>
        <?php [$sl, $sc] = $stat[$a['status']] ?? [$a['status'], 'cp-info']; ?>
        <tr>
          <td><code class="k"><?= e($a['booking_code']) ?></code></td>
          <td><?= e($a['nama_pasien']) ?><br><span class="muted"><?= e($a['no_rm']) ?></span></td>
          <td><?= e(reg_date_id($a['appointment_date'])) ?></td>
          <td><?= e($a['polyclinic_name']) ?><br><span class="muted"><?= e($a['doctor_name']) ?> &middot; <?= e($a['day_of_week']) ?> <?= e(substr($a['start_time'], 0, 5)) ?>-<?= e(substr($a['end_time'], 0, 5)) ?></span></td>
          <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
          <td class="aksi">
            <a class="action-link" href="<?= e(url('modules/scheduling/print.php?id=' . (int) $a['appointment_id'])) ?>" target="_blank" rel="noopener">🖨 Cetak</a>
            <?php if ($a['status'] === 'booked' && $a['appointment_date'] === $today && can('scheduling.update') && can('registration.create')): ?>
              <a class="action-link" href="<?= e(url('modules/scheduling/checkin.php?id=' . (int) $a['appointment_id'])) ?>">✅ Check-in</a>
            <?php endif; ?>
            <?php if ($a['status'] === 'booked' && can('scheduling.update')): ?>
              <form method="post" action="<?= e(url('modules/scheduling/cancel.php')) ?>" class="inline-form" onsubmit="return confirm('Batalkan appointment ini?');">
                <?= csrf_field() ?>
                <input type="hidden" name="appointment_id" value="<?= (int) $a['appointment_id'] ?>">
                <button type="submit" class="action-link danger">✖ Batalkan</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/scheduling/appointments.php'), $query, $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>