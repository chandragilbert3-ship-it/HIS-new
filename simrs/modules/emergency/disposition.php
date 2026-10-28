<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/emergency.php';

require_permission('emergency.update');

$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$enc    = $id > 0 ? ed_find_encounter($id) : null;
$dokter = cpoe_current_doctor();
if ($enc === null || !in_array($enc['status'], ['in_treatment', 'observation'], true)) {
    flash_set('error', 'Kasus IGD tidak ditemukan atau belum/sudah tidak bisa diberi disposisi.');
    redirect('modules/emergency/index.php');
}
if ($dokter === null) {
    flash_set('error', 'Disposisi hanya dapat diputuskan oleh dokter (akun harus terhubung ke Master Data Dokter).');
    redirect('modules/emergency/case.php?id=' . $id);
}

$errors = [];
$old    = ['disposition_type' => '', 'decision_at' => date('Y-m-d\TH:i'), 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $old = array_merge($old, $_POST);
    [$data, $errors] = ed_validate_disposition($_POST, (string) $enc['arrival_time']);
    if (!$errors) {
        $err = ed_save_disposition($id, (int) $dokter['doctor_id'], $data);
        if ($err === null) {
            audit_log('Disposisi ' . ed_case_no($id) . ': ' . $data['type'], 'ed_dispositions', null, (int) $enc['visit_id']);
            flash_set('success', 'Disposisi tersimpan. Kasus IGD selesai.');
            redirect('modules/emergency/case.php?id=' . $id);
        }
        $errors[] = $err;
    }
}

$pageTitle    = 'Disposisi IGD';
$activeModule = 'emergency';
$breadcrumb   = 'Modul 6. IGD &rsaquo; Disposisi';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Disposisi <?= e(ed_case_no($id)) ?> &mdash; <?= e($enc['nama_pasien']) ?></h2>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('modules/emergency/disposition.php?id=' . $id)) ?>" class="form-grid"
        onsubmit="return confirm('Simpan disposisi? Kasus akan ditutup dan tidak bisa diubah.');">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-row"><label for="disposition_type">Jenis Disposisi</label>
      <select id="disposition_type" name="disposition_type" required><option value="">-- pilih --</option>
        <?php foreach (ed_disposition_labels() as $c => $l): ?><option value="<?= e($c) ?>" <?= (string) $old['disposition_type'] === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row"><label for="decision_at">Waktu Keputusan</label>
      <input type="datetime-local" id="decision_at" name="decision_at" required value="<?= e((string) $old['decision_at']) ?>"></div>
    <div class="form-row full"><label for="notes">Catatan <span class="muted">(wajib untuk rawat inap, rujuk, meninggal)</span></label>
      <textarea id="notes" name="notes" rows="3" maxlength="1000"><?= e((string) $old['notes']) ?></textarea></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan Disposisi</button>
      <a class="btn btn-outline" href="<?= e(url('modules/emergency/case.php?id=' . $id)) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>