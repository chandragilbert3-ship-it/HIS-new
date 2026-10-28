<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/emergency.php';

require_permission('emergency.create');

$id  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$enc = $id > 0 ? ed_find_encounter($id) : null;
if ($enc === null || !in_array($enc['status'], ['waiting', 'in_treatment', 'observation'], true)) {
    flash_set('error', 'Kasus IGD tidak ditemukan atau sudah selesai.');
    redirect('modules/emergency/index.php');
}

$errors = [];
$old    = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    [$data, $errors] = ed_validate_triage($_POST);
    if (!$errors) {
        $err = ed_add_triage($id, (int) current_user()['id'], $data);
        if ($err === null) {
            audit_log('Triase ' . ed_case_no($id) . ' prioritas ' . $data['priority'], 'ed_triage', null, (int) $enc['visit_id']);
            flash_set('success', 'Triase tersimpan.');
            redirect('modules/emergency/case.php?id=' . $id);
        }
        $errors[] = $err;
    }
}

$prev   = ed_triages($id);
$alergi = reg_allergies((int) $enc['patient_id']);
$v      = fn(string $k): string => is_string($old[$k] ?? null) ? $old[$k] : '';

$pageTitle    = 'Triase IGD';
$activeModule = 'emergency';
$breadcrumb   = 'Modul 6. IGD &rsaquo; Triase';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Triase <?= e(ed_case_no($id)) ?> &mdash; <?= e($enc['nama_pasien']) ?> (<?= e($enc['no_rm']) ?>)</h2>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ Alergi: <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('modules/emergency/triage.php?id=' . $id)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="form-row full"><label for="chief_complaint">Keluhan Utama</label>
      <textarea id="chief_complaint" name="chief_complaint" rows="2" maxlength="1000" required><?= e($v('chief_complaint')) ?></textarea></div>

    <div class="form-row"><label>Tekanan Darah (mmHg) &mdash; sistolik / diastolik</label>
      <input type="text" name="sys" size="4" maxlength="3" placeholder="120" value="<?= e($v('sys')) ?>" style="width:80px"> /
      <input type="text" name="dia" size="4" maxlength="3" placeholder="80" value="<?= e($v('dia')) ?>" style="width:80px"></div>
    <div class="form-row"><label for="hr">Nadi (x/menit)</label><input type="text" id="hr" name="hr" maxlength="3" value="<?= e($v('hr')) ?>"></div>
    <div class="form-row"><label for="rr">Frekuensi Napas (x/menit)</label><input type="text" id="rr" name="rr" maxlength="3" value="<?= e($v('rr')) ?>"></div>
    <div class="form-row"><label for="temp">Suhu (&deg;C)</label><input type="text" id="temp" name="temp" maxlength="5" placeholder="36.7" value="<?= e($v('temp')) ?>"></div>
    <div class="form-row"><label for="spo2">SpO2 (%)</label><input type="text" id="spo2" name="spo2" maxlength="3" value="<?= e($v('spo2')) ?>"></div>

    <div class="form-row"><label for="consciousness">Kesadaran</label>
      <select id="consciousness" name="consciousness" required><option value="">-- pilih --</option>
        <?php foreach (ed_consciousness_labels() as $c => $l): ?><option value="<?= e($c) ?>" <?= $v('consciousness') === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row"><label for="pain_score">Skala Nyeri (0-10)</label>
      <input type="number" id="pain_score" name="pain_score" min="0" max="10" required value="<?= e($v('pain_score')) ?>"></div>
    <div class="form-row"><label for="triage_priority">Prioritas Triase</label>
      <select id="triage_priority" name="triage_priority" required><option value="">-- pilih --</option>
        <?php foreach (ed_priority_labels() as $c => [$l]): ?><option value="<?= e($c) ?>" <?= $v('triage_priority') === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>

    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan Triase</button>
      <a class="btn btn-outline" href="<?= e(url('modules/emergency/case.php?id=' . $id)) ?>">Batal</a>
    </div>
  </form>
  <?php if ($prev): ?><p class="muted">Triase sebelumnya: <?= count($prev) ?> kali.</p><?php endif; ?>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>