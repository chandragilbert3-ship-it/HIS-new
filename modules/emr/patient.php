<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/emr.php';

require_permission('emr.view');

$pid     = (int) ($_GET['patient_id'] ?? 0);
$patient = $pid > 0 ? emr_find_patient($pid) : null;
if ($patient === null) {
    flash_set('error', 'Pasien tidak ditemukan.');
    redirect('modules/emr/index.php');
}

audit_log('Buka rekam medis pasien ' . $patient['no_rm'], 'patients', $pid);

$visits  = emr_patient_visits($pid);
$alergi  = reg_allergies($pid);
$svc     = emr_service_labels();
$vstat   = emr_visit_status_labels();
$canDraft = can('emr.update');

$pageTitle    = 'Rekam Medis Pasien';
$activeModule = 'emr';
$breadcrumb   = 'Modul 7. EMR &rsaquo; Pasien';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2><?= e($patient['nama_pasien']) ?> &mdash; <?= e($patient['no_rm']) ?></h2>
  <div class="cp-info-box">
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($patient['tanggal_lahir'])) ?> th / <?= e($patient['jenis_kelamin']) ?></div>
    <div><span class="muted">Tanggal lahir</span><br><?= e(reg_date_id($patient['tanggal_lahir'])) ?></div>
    <div><span class="muted">Total kunjungan</span><br><?= count($visits) ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => $a['allergen'] . (!empty($a['severity']) ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?></div>
  <?php else: ?>
    <div class="muted">Tidak ada alergi tercatat.</div>
  <?php endif; ?>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/emr/index.php')) ?>">&larr; Cari Pasien Lain</a></div>
</div>

<div class="card">
  <h2>Riwayat Kunjungan</h2>
  <div class="table-wrap"><table>
    <tr><th>Tanggal</th><th>Layanan</th><th>Poli / Unit</th><th>Dokter</th><th>Diagnosis Utama</th><th>Catatan</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$visits): ?><tr><td colspan="8" class="empty">Belum ada kunjungan.</td></tr><?php endif; ?>
    <?php foreach ($visits as $v): [$sl, $sc] = $vstat[$v['status']] ?? [$v['status'], 'cp-info']; ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($v['visit_date']))) ?></td>
        <td><?= e((string) ($svc[$v['service_type']] ?? $v['service_type'])) ?></td>
        <td><?= e((string) $v['polyclinic_name']) ?></td>
        <td><?= e((string) $v['doctor_name']) ?></td>
        <td><?= $v['dx_utama'] ? e($v['dx_utama']) : '<span class="muted">&mdash;</span>' ?></td>
        <td><?php
            if ($v['note_status'] === 'final') { echo '<span class="cp-badge cp-ok">Final</span>'; }
            elseif ($v['note_status'] === 'draft') { echo $canDraft ? '<span class="cp-badge cp-warn">Draft</span>' : '<span class="muted">&mdash;</span>'; }
            else { echo '<span class="muted">&mdash;</span>'; } ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/emr/visit.php?visit_id=' . (int) $v['visit_id'])) ?>">📄 Detail</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>