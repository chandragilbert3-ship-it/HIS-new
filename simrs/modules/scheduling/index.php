<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/scheduling.php';

require_permission('scheduling.view');

$polyId   = (int) ($_GET['poli'] ?? 0);
$doctorId = (int) ($_GET['dokter'] ?? 0);
$rows     = sch_schedules($polyId, $doctorId);

$polis = crud_field_options(['type' => 'ref', 'ref' => ['table' => 'master_polyclinics', 'id' => 'polyclinic_id', 'label' => 'polyclinic_name', 'where' => 'is_active = 1', 'order' => 'polyclinic_name']], 'create');
$docs  = crud_field_options(['type' => 'ref', 'ref' => ['table' => 'master_doctors', 'id' => 'doctor_id', 'label' => 'doctor_name', 'where' => 'is_active = 1', 'order' => 'doctor_name']], 'create');

$pageTitle    = 'Jadwal Dokter';
$activeModule = 'scheduling';
$breadcrumb   = 'Modul 3. Penjadwalan &amp; Appointment';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a class="active" href="<?= e(url('modules/scheduling/index.php')) ?>">1. Jadwal Dokter</a>
  <?php if (can('scheduling.create')): ?><a href="<?= e(url('modules/scheduling/booking.php')) ?>">2. Booking Appointment</a><?php endif; ?>
  <a href="<?= e(url('modules/scheduling/appointments.php')) ?>">3. Daftar Appointment</a>
</div>

<div class="card">
  <h2>Jadwal Dokter Tersedia</h2>

  <form method="get" action="<?= e(url('modules/scheduling/index.php')) ?>" class="toolbar">
    <select name="poli"><option value="">Semua poliklinik</option>
      <?php foreach ($polis as $id => $nm): ?><option value="<?= e((string) $id) ?>" <?= (string) $polyId === (string) $id ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select>
    <select name="dokter"><option value="">Semua dokter</option>
      <?php foreach ($docs as $id => $nm): ?><option value="<?= e((string) $id) ?>" <?= (string) $doctorId === (string) $id ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>Dokter</th><th>Poliklinik</th><th>Hari</th><th>Jam</th><th>Kuota</th><th>Terisi</th><th>Status</th></tr>
      <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada jadwal. Tambahkan di Master Data &rarr; Jadwal Dokter.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <?php $penuh = (int) $r['quota'] <= 0 ? null : ((int) $r['filled'] >= (int) $r['quota']); ?>
        <tr>
          <td><?= e($r['doctor_name']) ?></td>
          <td><?= e($r['polyclinic_name']) ?></td>
          <td><?= e($r['day_of_week']) ?></td>
          <td><?= e(substr($r['start_time'], 0, 5)) ?> - <?= e(substr($r['end_time'], 0, 5)) ?></td>
          <td><?= (int) $r['quota'] ?></td>
          <td><?= (int) $r['filled'] ?></td>
          <td>
            <?php if ($penuh === null): ?><span class="cp-badge cp-off">Ditutup</span>
            <?php elseif ($penuh): ?><span class="cp-badge cp-warn">Penuh</span>
            <?php else: ?><span class="cp-badge cp-ok">Tersedia</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>