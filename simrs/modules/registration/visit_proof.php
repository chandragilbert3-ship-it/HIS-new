<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';

require_permission('registration.view');

$visitId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT v.visit_id, v.visit_date, v.service_type, v.status, v.patient_id,
            p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin,
            mp.polyclinic_name, md.doctor_name, q.queue_no,
            py.payer_name, pi.membership_no
       FROM visits v
       JOIN patients p            ON p.patient_id = v.patient_id
       JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
       LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
       LEFT JOIN queue q           ON q.visit_id = v.visit_id
       LEFT JOIN patient_insurance pi ON pi.patient_insurance_id = v.patient_insurance_id
       LEFT JOIN payers py            ON py.payer_id = pi.payer_id
      WHERE v.visit_id = :id
      LIMIT 1'
);
$stmt->execute([':id' => $visitId]);
$v = $stmt->fetch();

if (!$v) {
    flash_set('error', 'Kunjungan tidak ditemukan.');
    redirect('modules/registration/index.php');
}

$svcLabel = reg_service_labels();
$stsLabel = reg_visit_status();
[$stTeks, $stKelas] = $stsLabel[$v['status']] ?? [$v['status'], 'status-info'];
$namaRs = reg_config('hospital_name', 'Rumah Sakit Dunia Indah');

$pageTitle    = 'Bukti Registrasi';
$activeModule = 'registration';
$breadcrumb   = 'Modul 2. Registrasi Pasien › Bukti Registrasi';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="card proof">
  <h2 class="no-print">Bukti Registrasi &amp; Nomor Antrean</h2>

  <div class="proof-head">
    <strong><?= e($namaRs) ?></strong>
    <span>Bukti Pendaftaran Kunjungan</span>
  </div>

  <div class="summary-box">
    <div>
      <span>Nomor Antrean</span>
      <div class="queue-number"><?= $v['queue_no'] ? e($v['queue_no']) : '&mdash;' ?></div>
      <span class="status-badge <?= e($stKelas) ?>"><?= e($stTeks) ?></span>
    </div>
    <div class="proof-detail">
      <div><span>No. Kunjungan</span><strong>VS-<?= e(str_pad((string) $v['visit_id'], 6, '0', STR_PAD_LEFT)) ?></strong></div>
      <div><span>Tanggal &amp; Jam</span><strong><?= e(date('d-m-Y H:i', (int) strtotime((string) $v['visit_date']))) ?></strong></div>
      <div><span>Pasien</span><strong><?= e($v['nama_pasien']) ?> (<?= e($v['no_rm']) ?>)</strong></div>
      <div><span>Tanggal Lahir</span><strong><?= e(reg_date_id($v['tanggal_lahir'])) ?> (<?= e(reg_age($v['tanggal_lahir'])) ?> th)</strong></div>
      <div><span>Poliklinik</span><strong><?= e($v['polyclinic_name']) ?></strong></div>
      <div><span>Dokter</span><strong><?= $v['doctor_name'] ? e($v['doctor_name']) : 'Ditentukan kemudian' ?></strong></div>
      <div><span>Layanan</span><strong><?= e($svcLabel[$v['service_type']] ?? $v['service_type']) ?></strong></div>
      <div><span>Penjamin</span><strong><?= $v['payer_name'] ? e($v['payer_name']) . ' (' . e($v['membership_no']) . ')' : 'Pribadi / Umum' ?></strong></div>
    </div>
  </div>

  <div class="form-actions no-print" style="margin-top:16px;">
    <a class="btn btn-outline" href="<?= e(url('modules/registration/patient.php?id=' . (int) $v['patient_id'])) ?>">‹ Detail Pasien</a>
    <a class="btn btn-outline" href="<?= e(url('modules/registration/index.php')) ?>">Registrasi Baru Lagi</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Cetak Bukti</button>
  </div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>