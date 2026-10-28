<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/outpatient.php';

require_permission('outpatient.view');

$patientId = (int) ($_GET['patient_id'] ?? 0);
$patient   = $patientId > 0 ? reg_find_patient($patientId) : null;
if ($patient === null) {
    flash_set('error', 'Pasien tidak ditemukan.');
    redirect('modules/outpatient/index.php');
}

audit_log('Buka riwayat rawat jalan pasien #' . $patientId, 'patients', $patientId);
$rows  = opd_patient_history($patientId);
$vStat = array_map(fn($v) => $v[0], reg_visit_status());

$pageTitle    = 'Riwayat Kunjungan';
$activeModule = 'outpatient';
$breadcrumb   = 'Modul 4. Rawat Jalan &rsaquo; Riwayat';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/outpatient/index.php')) ?>">1. Antrean Poli</a>
  <a class="active" href="#">3. Riwayat Kunjungan</a>
</div>

<div class="card">
  <h2>Riwayat Rawat Jalan: <?= e($patient['nama_pasien']) ?> (<?= e($patient['no_rm']) ?>)</h2>
  <div class="table-wrap">
    <table>
      <tr><th>Kunjungan</th><th>Tanggal</th><th>Poliklinik / Dokter</th><th>Diagnosis Utama</th><th>Catatan</th><th>Status</th><th>Aksi</th></tr>
      <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada kunjungan rawat jalan.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>#<?= (int) $r['visit_id'] ?></td>
          <td><?= e(reg_date_id(substr((string) $r['visit_date'], 0, 10))) ?></td>
          <td><?= e($r['polyclinic_name']) ?><br><span class="muted"><?= $r['doctor_name'] ? e($r['doctor_name']) : '&mdash;' ?></span></td>
          <td><?= $r['dx_utama'] ? e($r['dx_utama']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= $r['note_status'] === 'final' ? '<span class="cp-badge cp-ok">Final</span>' : ($r['note_status'] === 'draft' ? '<span class="cp-badge cp-warn">Draft</span>' : '<span class="muted">&mdash;</span>') ?></td>
          <td><?= e($vStat[$r['status']] ?? $r['status']) ?></td>
          <td class="aksi"><a class="action-link" href="<?= e(url('modules/outpatient/exam.php?visit_id=' . (int) $r['visit_id'])) ?>">📄 Buka</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>