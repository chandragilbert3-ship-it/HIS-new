<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/outpatient.php';

require_permission('outpatient.view');

$visitId = (int) ($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$visit   = $visitId > 0 ? opd_find_visit($visitId) : null;
if ($visit === null) {
    flash_set('error', 'Kunjungan rawat jalan tidak ditemukan.');
    redirect('modules/outpatient/index.php');
}

$dokter  = cpoe_current_doctor();
$note    = opd_note($visitId);
$final   = $note !== null && $note['status'] === 'final';
$isMine  = $dokter !== null && $visit['doctor_id'] !== null && (int) $visit['doctor_id'] === (int) $dokter['doctor_id'];
$canEdit = can('outpatient.update') && $isMine && $visit['status'] === 'in_progress' && !$final;
$icd     = opd_icd_options();
$errors  = [];

$dxNow = opd_diagnoses($visitId);
$old   = [
    'subjective' => (string) ($note['subjective'] ?? ''),
    'objective'  => (string) ($note['objective'] ?? ''),
    'assessment' => (string) ($note['assessment'] ?? ''),
    'plan'       => (string) ($note['plan'] ?? ''),
    'dx'         => array_column($dxNow, 'icd10_code'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('outpatient.update');
    $finalize = ($_POST['do'] ?? '') === 'finalize';
    if ($finalize) {
        require_permission('emr.finalize');
    }
    $old = array_merge($old, $_POST);

    if (!$canEdit) {
        $errors[] = 'Anda tidak dapat mengubah pemeriksaan ini (bukan dokter pemeriksa, kunjungan tidak berjalan, atau catatan sudah final).';
    } else {
        [$data, $errors] = opd_validate_exam($_POST, $finalize, $icd);
        if (!$errors) {
            $err = opd_save_exam($visitId, (int) $dokter['doctor_id'], $data, $finalize);
            if ($err === null) {
                audit_log(($finalize ? 'Finalisasi' : 'Simpan draft') . ' SOAP kunjungan #' . $visitId, 'emr_notes', null, $visitId);
                flash_set('success', $finalize ? 'Catatan difinalisasi. Anda dapat membuat order atau menyelesaikan kunjungan.' : 'Draft tersimpan.');
                redirect('modules/outpatient/exam.php?visit_id=' . $visitId);
            }
            $errors[] = $err;
        }
    }
}

audit_log('Buka pemeriksaan rawat jalan kunjungan #' . $visitId, 'visits', $visitId, $visitId);

$alergi  = reg_allergies((int) $visit['patient_id']);
$nursing = opd_nursing($visitId);
$vStat   = array_map(fn($v) => $v[0], reg_visit_status());

$pageTitle    = 'Pemeriksaan Pasien';
$activeModule = 'outpatient';
$breadcrumb   = 'Modul 4. Rawat Jalan &rsaquo; Pemeriksaan';

require APP_ROOT . '/includes/layout_header.php';
$val = fn(string $k): string => (string) ($old[$k] ?? '');
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/outpatient/index.php')) ?>">1. Antrean Poli</a>
  <a class="active" href="#">2. Pemeriksaan</a>
  <a href="<?= e(url('modules/outpatient/history.php?patient_id=' . (int) $visit['patient_id'])) ?>">3. Riwayat Kunjungan</a>
</div>

<div class="card">
  <h2>Pasien</h2>
  <div class="cp-info-box">
    <div><span class="muted">Pasien</span><br><b><?= e($visit['nama_pasien']) ?></b> (<?= e($visit['no_rm']) ?>)</div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($visit['tanggal_lahir'])) ?> th / <?= e($visit['jenis_kelamin']) ?></div>
    <div><span class="muted">Kunjungan</span><br>#<?= (int) $visit['visit_id'] ?> &middot; <?= e((string) $visit['queue_no']) ?> &middot; <?= e($visit['polyclinic_name']) ?></div>
    <div><span class="muted">Status</span><br><?= e($vStat[$visit['status']] ?? $visit['status']) ?> &middot; <?= $visit['doctor_name'] ? e($visit['doctor_name']) : 'belum ada dokter' ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi:</b>
      <?= e(implode('; ', array_map(fn($a) => $a['allergen'] . (isset($a['severity']) && $a['severity'] ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?></div>
  <?php else: ?>
    <div class="muted">Belum ada data alergi tercatat.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Pengkajian Awal Perawat</h2>
  <?php if ($nursing === null): ?>
    <div class="muted">Belum ada pengkajian awal untuk kunjungan ini.</div>
  <?php else: ?>
    <div class="cp-info-box">
      <?php foreach ($nursing['vital_list'] as $k => $v): ?>
        <div><span class="muted"><?= e((string) $k) ?></span><br><?= e(is_scalar($v) ? (string) $v : json_encode($v)) ?></div>
      <?php endforeach; ?>
      <div><span class="muted">Skor nyeri</span><br><?= e((string) $nursing['pain_score']) ?> / 10</div>
      <div><span class="muted">Risiko jatuh</span><br><?= e((string) $nursing['fall_risk']) ?></div>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Catatan SOAP &amp; Diagnosis <?= $note ? '<span class="cp-badge ' . ($final ? 'cp-ok' : 'cp-warn') . '">' . ($final ? 'Final' : 'Draft') . '</span>' : '' ?></h2>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if (!$canEdit && !$final && $visit['status'] === 'in_progress'): ?>
    <div class="alert alert-warning">Anda hanya dapat melihat. Pemeriksaan ini milik dokter lain atau akun Anda tidak punya izin mengubah.</div>
  <?php endif; ?>
  <?php if (!$canEdit && $visit['status'] === 'registered'): ?>
    <div class="alert alert-warning">Pemeriksaan belum dimulai. Tekan "Mulai Periksa" di halaman Antrean Poli.</div>
  <?php endif; ?>
  <?php if ($final): ?>
    <div class="alert alert-warning">Catatan sudah final dan terkunci. Koreksi dilakukan lewat addendum di modul EMR.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('modules/outpatient/exam.php?visit_id=' . $visitId)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="visit_id" value="<?= $visitId ?>">
    <?php foreach (['subjective' => 'Subjective (keluhan)', 'objective' => 'Objective (pemeriksaan)', 'assessment' => 'Assessment (penilaian)', 'plan' => 'Plan (rencana)'] as $k => $label): ?>
      <div class="form-row full">
        <label for="<?= $k ?>"><?= e($label) ?></label>
        <textarea id="<?= $k ?>" name="<?= $k ?>" rows="3" maxlength="5000" <?= $canEdit ? '' : 'readonly' ?>><?= e($val($k)) ?></textarea>
      </div>
    <?php endforeach; ?>

    <?php foreach ([0, 1, 2] as $i): ?>
      <div class="form-row">
        <label for="dx<?= $i ?>"><?= $i === 0 ? 'Diagnosis Utama (ICD-10)' : 'Diagnosis Sekunder ' . $i ?></label>
        <select id="dx<?= $i ?>" name="dx[<?= $i ?>]" <?= $canEdit ? '' : 'disabled' ?>>
          <option value="">-- pilih --</option>
          <?php foreach ($icd as $code => $label): ?>
            <option value="<?= e((string) $code) ?>" <?= (string) ($old['dx'][$i] ?? '') === (string) $code ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>

    <?php if ($canEdit): ?>
      <div class="form-row full">
        <button type="submit" name="do" value="draft" class="btn btn-outline">Simpan Draft</button>
        <?php if (can('emr.finalize')): ?>
          <button type="submit" name="do" value="finalize" class="btn btn-primary" onclick="return confirm('Finalisasi catatan? Setelah final tidak bisa diubah.');">Finalisasi</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </form>

  <?php if ($final && $visit['status'] === 'in_progress' && $isMine): ?>
    <hr>
    <?php if (can('cpoe.create')): ?>
      <a class="btn btn-outline" href="<?= e(url('modules/cpoe/order_form.php?visit_id=' . $visitId)) ?>">➕ Buat Order (CPOE)</a>
    <?php endif; ?>
    <form method="post" action="<?= e(url('modules/outpatient/complete.php')) ?>" class="inline-form" onsubmit="return confirm('Selesaikan kunjungan ini? Order baru tidak bisa dibuat setelahnya.');">
      <?= csrf_field() ?><input type="hidden" name="visit_id" value="<?= $visitId ?>">
      <button type="submit" class="btn btn-primary">✔ Selesaikan Kunjungan</button>
    </form>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>