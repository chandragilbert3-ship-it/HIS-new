<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/emergency.php';

require_permission('emergency.view');

$id  = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$enc = $id > 0 ? ed_find_encounter($id) : null;
if ($enc === null) {
    flash_set('error', 'Kasus IGD tidak ditemukan.');
    redirect('modules/emergency/index.php');
}
$visitId = (int) $enc['visit_id'];
$dokter  = cpoe_current_doctor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('emergency.update');
    $do  = (string) ($_POST['do'] ?? '');
    $err = null;
    switch ($do) {
        case 'treat':
        case 'observe':
            $err = ed_move($id, $do, $dokter ? (int) $dokter['doctor_id'] : null);
            if ($err === null) {
                audit_log(($do === 'treat' ? 'Mulai tangani ' : 'Pindah observasi ') . ed_case_no($id), 'ed_encounters', $id, $visitId);
                flash_set('success', $do === 'treat' ? 'Pasien mulai ditangani.' : 'Pasien dipindah ke observasi.');
            }
            break;
        case 'observation':
            $err = ed_add_observation($id, (int) current_user()['id'], (string) ($_POST['observation'] ?? ''));
            if ($err === null) {
                audit_log('Catat observasi ' . ed_case_no($id), 'ed_observations', null, $visitId);
                flash_set('success', 'Observasi dicatat.');
            }
            break;
        default:
            $err = 'Aksi tidak dikenal.';
    }
    if ($err !== null) {
        flash_set('error', $err);
    }
    redirect('modules/emergency/case.php?id=' . $id);
}

audit_log('Buka kasus IGD ' . ed_case_no($id), 'ed_encounters', $id, $visitId);

$triages = ed_triages($id);
$obs     = ed_observations($id);
$disp    = ed_disposition($id);
$alergi  = reg_allergies((int) $enc['patient_id']);
$prio    = ed_priority_labels();
$stat    = ed_status_labels();
$aktif   = in_array($enc['status'], ['waiting', 'in_treatment', 'observation'], true);
$latest  = $triages[0] ?? null;
[$sl, $sc] = $stat[$enc['status']] ?? [$enc['status'], 'cp-info'];

$pageTitle    = 'Kasus IGD';
$activeModule = 'emergency';
$breadcrumb   = 'Modul 6. IGD &rsaquo; Kasus';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/emergency/index.php')) ?>">1. Pasien IGD Aktif</a>
  <a class="active" href="#">Kasus <?= e(ed_case_no($id)) ?></a>
  <a href="<?= e(url('modules/emergency/history.php')) ?>">2. Riwayat IGD</a>
</div>

<div class="card">
  <h2><?= e(ed_case_no($id)) ?> &mdash; <?= e($enc['nama_pasien']) ?> <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span>
    <?php if ($latest): [$pl, $pc] = $prio[$latest['triage_priority']] ?? [$latest['triage_priority'], 'cp-info']; ?><span class="cp-badge <?= e($pc) ?>"><?= e($pl) ?></span><?php endif; ?></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($enc['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($enc['tanggal_lahir'])) ?> th / <?= e($enc['jenis_kelamin']) ?></div>
    <div><span class="muted">Datang</span><br><?= e(date('d/m/Y H:i', strtotime($enc['arrival_time']))) ?> &middot; <?= e(ed_arrival_labels()[$enc['arrival_mode']] ?? $enc['arrival_mode']) ?></div>
    <div><span class="muted">Asal rujukan</span><br><?= $enc['source_referral'] ? e($enc['source_referral']) : '&mdash;' ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => $a['allergen'] . (!empty($a['severity']) ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?></div>
  <?php endif; ?>

  <?php if ($aktif && can('emergency.update')): ?>
    <div class="toolbar">
      <?php if (can('emergency.create')): ?><a class="btn btn-outline" href="<?= e(url('modules/emergency/triage.php?id=' . $id)) ?>">🩹 <?= $triages ? 'Triase Ulang' : 'Isi Triase' ?></a><?php endif; ?>
      <?php if ($enc['status'] === 'waiting'): ?>
        <form method="post" class="inline-form" action="<?= e(url('modules/emergency/case.php?id=' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="treat"><button type="submit" class="btn btn-primary">▶ Mulai Tangani</button></form>
      <?php elseif ($enc['status'] === 'in_treatment'): ?>
        <form method="post" class="inline-form" action="<?= e(url('modules/emergency/case.php?id=' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="observe"><button type="submit" class="btn btn-outline">👁 Pindah ke Observasi</button></form>
      <?php endif; ?>
      <?php if (can('cpoe.create') && $dokter && in_array($enc['status'], ['in_treatment', 'observation'], true)): ?>
        <a class="btn btn-outline" href="<?= e(url('modules/cpoe/order_form.php?visit_id=' . $visitId)) ?>">➕ Buat Order (CPOE)</a>
      <?php endif; ?>
      <?php if ($dokter && in_array($enc['status'], ['in_treatment', 'observation'], true)): ?>
        <a class="btn btn-primary" href="<?= e(url('modules/emergency/disposition.php?id=' . $id)) ?>">📤 Disposisi</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Riwayat Triase</h2>
  <?php if (!$triages): ?><div class="muted">Belum ada triase.</div><?php endif; ?>
  <?php foreach ($triages as $t): [$pl, $pc] = $prio[$t['triage_priority']] ?? [$t['triage_priority'], 'cp-info']; ?>
    <div class="cp-info-box">
      <div><span class="muted">Prioritas</span><br><span class="cp-badge <?= e($pc) ?>"><?= e($pl) ?></span></div>
      <div><span class="muted">Keluhan</span><br><?= e($t['chief_complaint']) ?></div>
      <?php foreach ($t['vital_list'] as $k => $val): ?>
        <div><span class="muted"><?= e((string) $k) ?></span><br><?= e(is_scalar($val) ? (string) $val : json_encode($val)) ?></div>
      <?php endforeach; ?>
      <div><span class="muted">Kesadaran / Nyeri</span><br><?= e((string) $t['consciousness']) ?> / <?= e((string) $t['pain_score']) ?></div>
      <div><span class="muted">Perawat</span><br><?= e((string) ($t['nurse_name'] ?? '-')) ?></div>
    </div><hr>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Observasi</h2>
  <?php if (can('emergency.update') && in_array($enc['status'], ['in_treatment', 'observation'], true)): ?>
    <form method="post" action="<?= e(url('modules/emergency/case.php?id=' . $id)) ?>" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="observation">
      <div class="form-row full"><label for="observation">Catatan Observasi Baru</label>
        <textarea id="observation" name="observation" rows="2" maxlength="1000" required></textarea></div>
      <div class="form-row full"><button type="submit" class="btn btn-primary">Tambah Catatan</button></div>
    </form>
  <?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Waktu</th><th>Petugas</th><th>Catatan</th></tr>
    <?php if (!$obs): ?><tr><td colspan="3" class="empty">Belum ada catatan observasi.</td></tr><?php endif; ?>
    <?php foreach ($obs as $o): ?>
      <tr><td><?= e(date('d/m H:i', strtotime($o['observed_at']))) ?></td><td><?= e((string) $o['user_name']) ?></td><td><?= nl2br(e($o['observation'])) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php if ($disp): ?>
<div class="card">
  <h2>Disposisi</h2>
  <div class="cp-info-box">
    <div><span class="muted">Jenis</span><br><b><?= e(ed_disposition_labels()[$disp['disposition_type']] ?? $disp['disposition_type']) ?></b></div>
    <div><span class="muted">Waktu keputusan</span><br><?= e(date('d/m/Y H:i', strtotime($disp['decision_at']))) ?></div>
    <div><span class="muted">Dokter</span><br><?= e($disp['doctor_name']) ?></div>
    <div><span class="muted">Catatan</span><br><?= $disp['notes'] ? e($disp['notes']) : '&mdash;' ?></div>
  </div>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>