<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/nursing.php';

require_permission('nursing.view');

$visitId = (int) ($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$visit   = $visitId > 0 ? nrs_find_visit($visitId) : null;
if ($visit === null) {
    flash_set('error', 'Kunjungan tidak ditemukan.');
    redirect('modules/nursing/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do     = (string) ($_POST['do'] ?? '');
    $planId = (int) ($_POST['care_plan_id'] ?? 0);
    $err    = null;

    if ($planId <= 0 || nrs_plan_visit($planId) !== $visitId) {
        $err = 'Rencana asuhan tidak ditemukan pada kunjungan ini.';
    } elseif ($do === 'plan_status') {
        require_permission('nursing.update');
        $new = (string) ($_POST['new_status'] ?? '');
        $err = nrs_set_plan_status($planId, $new);
        if ($err === null) {
            audit_log('Ubah status rencana asuhan #' . $planId . ' menjadi ' . $new, 'nursing_care_plans', $planId, $visitId);
            flash_set('success', 'Status rencana asuhan diperbarui.');
        }
    } elseif ($do === 'intervention') {
        require_permission('nursing.create');
        [$data, $errs] = nrs_validate_intervention($_POST);
        if ($errs) {
            $err = implode(' ', $errs);
        } else {
            $err = nrs_add_intervention($planId, (int) current_user()['id'], $data);
            if ($err === null) {
                audit_log('Tambah intervensi pada rencana asuhan #' . $planId, 'nursing_interventions', null, $visitId);
                flash_set('success', 'Intervensi dicatat.');
            }
        }
    } else {
        $err = 'Aksi tidak dikenal.';
    }
    if ($err !== null) {
        flash_set('error', $err);
    }
    redirect('modules/nursing/visit.php?visit_id=' . $visitId);
}

audit_log('Buka halaman keperawatan', 'visits', $visitId, $visitId);

$assess = nrs_assessments($visitId);
$plans  = nrs_care_plans($visitId);
$alergi = reg_allergies((int) $visit['patient_id']);
$svc    = nrs_service_labels();
$fall   = nrs_fall_labels();
$pstat  = nrs_plan_status_labels();
$open   = nrs_visit_open($visit);

$pageTitle    = 'Keperawatan Pasien';
$activeModule = 'nursing';
$breadcrumb   = 'Modul 8. Keperawatan &rsaquo; Kunjungan';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2><?= e($visit['nama_pasien']) ?> &mdash; Kunjungan #<?= (int) $visit['visit_id'] ?></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($visit['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($visit['tanggal_lahir'])) ?> th / <?= e($visit['jenis_kelamin']) ?></div>
    <div><span class="muted">Layanan</span><br><?= e((string) ($svc[$visit['service_type']] ?? $visit['service_type'])) ?></div>
    <div><span class="muted">Status kunjungan</span><br><?= e($visit['status']) ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => $a['allergen'] . (!empty($a['severity']) ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?></div>
  <?php endif; ?>
  <?php if (!$open): ?><div class="alert alert-warning">Kunjungan sudah tidak aktif. Halaman ini hanya untuk dibaca.</div><?php endif; ?>
  <div class="toolbar">
    <a class="btn btn-outline" href="<?= e(url('modules/nursing/index.php')) ?>">&larr; Daftar</a>
    <?php if ($open && can('nursing.create')): ?>
      <a class="btn btn-primary" href="<?= e(url('modules/nursing/assessment.php?visit_id=' . $visitId)) ?>">➕ Pengkajian Baru</a>
      <a class="btn btn-primary" href="<?= e(url('modules/nursing/careplan.php?visit_id=' . $visitId)) ?>">➕ Rencana Asuhan</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Riwayat Pengkajian</h2>
  <?php if (!$assess): ?><div class="muted">Belum ada pengkajian.</div><?php endif; ?>
  <?php foreach ($assess as $a): [$fl, $fc] = $fall[$a['fall_risk']] ?? [(string) $a['fall_risk'], 'cp-info']; ?>
    <div class="cp-info-box">
      <div><span class="muted">Waktu</span><br><?= e(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?></div>
      <?php foreach ($a['vital_list'] as $k => $val): ?>
        <div><span class="muted"><?= e((string) $k) ?></span><br><?= e(is_scalar($val) ? (string) $val : json_encode($val)) ?></div>
      <?php endforeach; ?>
      <div><span class="muted">Nyeri</span><br><?= e((string) $a['pain_score']) ?>/10</div>
      <div><span class="muted">Risiko jatuh</span><br><span class="cp-badge <?= e($fc) ?>"><?= e($fl) ?></span></div>
      <div><span class="muted">Perawat</span><br><?= e((string) ($a['nurse_name'] ?? '-')) ?></div>
    </div><hr>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Rencana Asuhan &amp; Intervensi</h2>
  <?php if (!$plans): ?><div class="muted">Belum ada rencana asuhan.</div><?php endif; ?>
  <?php foreach ($plans as $p): [$sl, $sc] = $pstat[$p['status']] ?? [(string) $p['status'], 'cp-info']; $isActive = $p['status'] === 'active'; ?>
    <div class="card">
      <h3>Rencana #<?= (int) $p['care_plan_id'] ?> <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></h3>
      <div class="cp-info-box">
        <div><span class="muted">Masalah keperawatan</span><br><?= nl2br(e((string) $p['nursing_problem'])) ?></div>
        <div><span class="muted">Target</span><br><?= nl2br(e((string) $p['target'])) ?></div>
        <div><span class="muted">Dibuat oleh</span><br><?= e((string) ($p['nurse_name'] ?? '-')) ?></div>
      </div>

      <div class="table-wrap"><table>
        <tr><th>Waktu</th><th>Perawat</th><th>Intervensi</th><th>Respons Pasien</th></tr>
        <?php if (!$p['interventions']): ?><tr><td colspan="4" class="empty">Belum ada intervensi.</td></tr><?php endif; ?>
        <?php foreach ($p['interventions'] as $i): ?>
          <tr>
            <td><?= e(date('d/m H:i', strtotime((string) $i['performed_at']))) ?></td>
            <td><?= e((string) ($i['nurse_name'] ?? '-')) ?></td>
            <td><?= nl2br(e((string) $i['intervention'])) ?></td>
            <td><?= $i['response'] ? nl2br(e((string) $i['response'])) : '&mdash;' ?></td>
          </tr>
        <?php endforeach; ?>
      </table></div>

      <?php if ($isActive && can('nursing.create')): ?>
        <form method="post" action="<?= e(url('modules/nursing/visit.php?visit_id=' . $visitId)) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="visit_id" value="<?= $visitId ?>">
          <input type="hidden" name="care_plan_id" value="<?= (int) $p['care_plan_id'] ?>">
          <input type="hidden" name="do" value="intervention">
          <div class="form-row full"><label>Intervensi / Tindakan</label><textarea name="intervention" rows="2" maxlength="1000" required></textarea></div>
          <div class="form-row"><label>Respons Pasien <span class="muted">(opsional)</span></label><textarea name="response" rows="2" maxlength="1000"></textarea></div>
          <div class="form-row"><label>Waktu Tindakan</label><input type="datetime-local" name="performed_at" required value="<?= e(date('Y-m-d\TH:i')) ?>"></div>
          <div class="form-row full"><button type="submit" class="btn btn-primary">Tambah Intervensi</button></div>
        </form>
      <?php endif; ?>

      <?php if ($isActive && can('nursing.update')): ?>
        <div class="toolbar">
          <?php foreach (['achieved' => ['✔ Tandai Tercapai', 'btn-primary', 'Tandai rencana ini tercapai?'], 'discontinued' => ['⏹ Hentikan Rencana', 'btn-outline', 'Hentikan rencana ini?']] as $st => [$lbl, $cls, $msg]): ?>
            <form method="post" class="inline-form" action="<?= e(url('modules/nursing/visit.php?visit_id=' . $visitId)) ?>" onsubmit="return confirm('<?= e($msg) ?>');">
              <?= csrf_field() ?>
              <input type="hidden" name="visit_id" value="<?= $visitId ?>">
              <input type="hidden" name="care_plan_id" value="<?= (int) $p['care_plan_id'] ?>">
              <input type="hidden" name="do" value="plan_status">
              <input type="hidden" name="new_status" value="<?= e($st) ?>">
              <button type="submit" class="btn <?= e($cls) ?>"><?= e($lbl) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
