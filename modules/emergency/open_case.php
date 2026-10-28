<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/emergency.php';

require_permission('emergency.create');

$visitId = (int) ($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$visit   = $visitId > 0 ? ed_find_visit($visitId) : null;
if ($visit === null) {
    flash_set('error', 'Kunjungan IGD tidak ditemukan. Daftarkan pasien IGD lewat modul Registrasi (layanan IGD).');
    redirect('modules/emergency/index.php');
}

$errors = [];
$mode   = (string) ($_POST['arrival_mode'] ?? 'walk_in');
$source = trim((string) ($_POST['source_referral'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    try {
        $id = ed_open_case($visitId, $mode, $source);
        audit_log('Buka kasus IGD ' . ed_case_no($id), 'ed_encounters', $id, $visitId);
        flash_set('success', 'Kasus ' . ed_case_no($id) . ' dibuka. Lanjutkan dengan triase.');
        redirect('modules/emergency/triage.php?id=' . $id);
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    } catch (PDOException $ex) {
        error_log('Buka kasus IGD gagal: ' . $ex->getMessage());
        $errors[] = 'Gagal menyimpan karena kesalahan database.';
    }
}

$pageTitle    = 'Buka Kasus IGD';
$activeModule = 'emergency';
$breadcrumb   = 'Modul 6. IGD &rsaquo; Buka Kasus';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Buka Kasus IGD</h2>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('modules/emergency/open_case.php?visit_id=' . $visitId)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="visit_id" value="<?= $visitId ?>">
    <div class="form-row"><label for="arrival_mode">Cara Datang</label>
      <select id="arrival_mode" name="arrival_mode">
        <?php foreach (ed_arrival_labels() as $c => $l): ?><option value="<?= e($c) ?>" <?= $mode === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row"><label for="source_referral">Asal Rujukan <span class="muted">(wajib jika rujukan)</span></label>
      <input type="text" id="source_referral" name="source_referral" maxlength="100" value="<?= e($source) ?>"></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Buka Kasus &amp; Lanjut Triase</button>
      <a class="btn btn-outline" href="<?= e(url('modules/emergency/index.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>