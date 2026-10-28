<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/nursing.php';

require_permission('nursing.create');

$visitId = (int) ($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$visit   = $visitId > 0 ? nrs_find_visit($visitId) : null;
if ($visit === null || !nrs_visit_open($visit)) {
    flash_set('error', 'Kunjungan tidak ditemukan atau sudah tidak aktif.');
    redirect('modules/nursing/index.php');
}

$errors = [];
$old    = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    [$data, $errors] = nrs_validate_assessment($_POST);
    if (!$errors) {
        $err = nrs_add_assessment($visitId, (int) current_user()['id'], $data);
        if ($err === null) {
            audit_log('Tambah pengkajian keperawatan', 'nursing_assessments', null, $visitId);
            flash_set('success', 'Pengkajian tersimpan.');
            redirect('modules/nursing/visit.php?visit_id=' . $visitId);
        }
        $errors[] = $err;
    }
}

$alergi = reg_allergies((int) $visit['patient_id']);
$v      = fn(string $k): string => is_string($old[$k] ?? null) ? $old[$k] : '';

$pageTitle    = 'Pengkajian Keperawatan';
$activeModule = 'nursing';
$breadcrumb   = 'Modul 8. Keperawatan &rsaquo; Pengkajian';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Pengkajian &mdash; <?= e($visit['nama_pasien']) ?> (<?= e($visit['no_rm']) ?>)</h2>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ Alergi: <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('modules/nursing/assessment.php?visit_id=' . $visitId)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="visit_id" value="<?= $visitId ?>">
    <div class="form-row"><label>Tekanan Darah (mmHg) &mdash; sistolik / diastolik</label>
      <input type="text" name="sys" maxlength="3" placeholder="120" value="<?= e($v('sys')) ?>" style="width:80px"> /
      <input type="text" name="dia" maxlength="3" placeholder="80" value="<?= e($v('dia')) ?>" style="width:80px"></div>
    <div class="form-row"><label for="hr">Nadi (x/menit)</label><input type="text" id="hr" name="hr" maxlength="3" value="<?= e($v('hr')) ?>"></div>
    <div class="form-row"><label for="rr">Frekuensi Napas (x/menit)</label><input type="text" id="rr" name="rr" maxlength="3" value="<?= e($v('rr')) ?>"></div>
    <div class="form-row"><label for="temp">Suhu (&deg;C)</label><input type="text" id="temp" name="temp" maxlength="5" placeholder="36.7" value="<?= e($v('temp')) ?>"></div>
    <div class="form-row"><label for="spo2">SpO2 (%)</label><input type="text" id="spo2" name="spo2" maxlength="3" value="<?= e($v('spo2')) ?>"></div>
    <div class="form-row"><label for="bb">Berat Badan (kg)</label><input type="text" id="bb" name="bb" maxlength="6" value="<?= e($v('bb')) ?>"></div>
    <div class="form-row"><label for="tb">Tinggi Badan (cm)</label><input type="text" id="tb" name="tb" maxlength="6" value="<?= e($v('tb')) ?>"></div>
    <div class="form-row"><label for="pain_score">Skala Nyeri (0-10)</label>
      <input type="number" id="pain_score" name="pain_score" min="0" max="10" required value="<?= e($v('pain_score')) ?>"></div>
    <div class="form-row"><label for="fall_risk">Risiko Jatuh</label>
      <select id="fall_risk" name="fall_risk" required><option value="">-- pilih --</option>
        <?php foreach (nrs_fall_labels() as $c => [$l]): ?><option value="<?= e($c) ?>" <?= $v('fall_risk') === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan Pengkajian</button>
      <a class="btn btn-outline" href="<?= e(url('modules/nursing/visit.php?visit_id=' . $visitId)) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>