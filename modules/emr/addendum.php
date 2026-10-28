<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/emr.php';

require_permission('emr.update');

$noteId = (int) ($_GET['note_id'] ?? $_POST['note_id'] ?? 0);
$note   = $noteId > 0 ? emr_find_note($noteId) : null;
if ($note === null || $note['status'] !== 'final') {
    flash_set('error', 'Catatan tidak ditemukan atau belum final. Addendum hanya untuk catatan final.');
    redirect('modules/emr/index.php');
}
$visitId = (int) $note['visit_id'];
$dokter  = cpoe_current_doctor();
if ($dokter === null) {
    flash_set('error', 'Addendum hanya dapat dibuat oleh dokter (akun harus terhubung ke Master Data Dokter).');
    redirect('modules/emr/visit.php?visit_id=' . $visitId);
}

$errors = [];
$old    = ['reason' => '', 'content' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $old = array_merge($old, array_map(fn($v) => is_string($v) ? $v : '', $_POST));
    [$data, $errors] = emr_validate_addendum($_POST);
    if (!$errors) {
        $err = emr_add_addendum($noteId, (int) $dokter['doctor_id'], $data);
        if ($err === null) {
            audit_log('Tambah addendum catatan EMR #' . $noteId, 'emr_addenda', null, $visitId);
            flash_set('success', 'Addendum tersimpan.');
            redirect('modules/emr/visit.php?visit_id=' . $visitId);
        }
        $errors[] = $err;
    }
}

$pageTitle    = 'Tambah Addendum';
$activeModule = 'emr';
$breadcrumb   = 'Modul 7. EMR &rsaquo; Addendum';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Addendum &mdash; <?= e($note['nama_pasien']) ?> (<?= e($note['no_rm']) ?>)</h2>
  <div class="cp-info-box">
    <div><span class="muted">S</span><br><?= nl2br(e((string) $note['subjective'])) ?></div>
    <div><span class="muted">O</span><br><?= nl2br(e((string) $note['objective'])) ?></div>
    <div><span class="muted">A</span><br><?= nl2br(e((string) $note['assessment'])) ?></div>
    <div><span class="muted">P</span><br><?= nl2br(e((string) $note['plan'])) ?></div>
  </div>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('modules/emr/addendum.php?note_id=' . $noteId)) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="note_id" value="<?= $noteId ?>">
    <div class="form-row full"><label for="reason">Alasan Addendum</label>
      <textarea id="reason" name="reason" rows="2" maxlength="500" required><?= e($old['reason']) ?></textarea></div>
    <div class="form-row full"><label for="content">Isi Addendum</label>
      <textarea id="content" name="content" rows="5" maxlength="3000" required><?= e($old['content']) ?></textarea></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan Addendum</button>
      <a class="btn btn-outline" href="<?= e(url('modules/emr/visit.php?visit_id=' . $visitId)) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>