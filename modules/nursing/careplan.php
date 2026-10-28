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

$errors  = [];
$problem = trim((string) ($_POST['nursing_problem'] ?? ''));
$target  = trim((string) ($_POST['target'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    [$data, $errors] = nrs_validate_plan($_POST);
    if (!$errors) {
        $err = nrs_add_plan($visitId, (int) current_user()['id'], $data);
        if ($err === null) {
            audit_log('Tambah rencana asuhan keperawatan', 'nursing_care_plans', null, $visitId);
            flash_set('success', 'Rencana asuhan tersimpan.');
            redirect('modules/nursing/visit.php?visit_id=' . $visitId);
        }
        $errors[] = $err;
    }
}

$pageTitle    = 'Rencana Asuhan';
$activeModule = 'nursing';
$breadcrumb   = 'Modul 8. Keperawatan &rsaquo; Rencana Asuhan';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Rencana Asuhan &mdash; <?= e($visit['nama_pasien']) ?> (<?= e($visit['no_rm']) ?>)</h2>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('modules/nursing/careplan.php?visit_id=' . $visitId)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="visit_id" value="<?= $visitId ?>">
    <div class="form-row full"><label for="nursing_problem">Masalah Keperawatan</label>
      <textarea id="nursing_problem" name="nursing_problem" rows="3" maxlength="1000" required><?= e($problem) ?></textarea></div>
    <div class="form-row full"><label for="target">Target / Tujuan</label>
      <textarea id="target" name="target" rows="3" maxlength="1000" required><?= e($target) ?></textarea></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan Rencana</button>
      <a class="btn btn-outline" href="<?= e(url('modules/nursing/visit.php?visit_id=' . $visitId)) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>