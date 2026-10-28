<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/outpatient.php';

require_permission('outpatient.view');

$polyId = (int) ($_GET['poli'] ?? 0);
$status = (string) ($_GET['status'] ?? '');
$semua  = ($_GET['semua'] ?? '') === '1';
$dokter = cpoe_current_doctor();

$rows  = opd_today_queue($polyId, $status, ($dokter && !$semua) ? (int) $dokter['doctor_id'] : null);
$polis = crud_field_options(['type' => 'ref', 'ref' => ['table' => 'master_polyclinics', 'id' => 'polyclinic_id', 'label' => 'polyclinic_name', 'where' => 'is_active = 1', 'order' => 'polyclinic_name']], 'create');
$qLbl  = opd_queue_labels();

$pageTitle    = 'Antrean Poli';
$activeModule = 'outpatient';
$breadcrumb   = 'Modul 4. Rawat Jalan';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a class="active" href="<?= e(url('modules/outpatient/index.php')) ?>">1. Antrean Poli</a>
</div>

<div class="card">
  <h2>Antrean Poli Hari Ini (<?= e(reg_date_id(date('Y-m-d'))) ?>)</h2>

  <?php if (!$dokter): ?>
    <div class="alert alert-warning">Akun Anda belum terhubung ke data dokter aktif. Anda bisa memanggil pasien, tetapi tidak bisa memulai pemeriksaan.</div>
  <?php endif; ?>

  <form method="get" action="<?= e(url('modules/outpatient/index.php')) ?>" class="toolbar">
    <select name="poli"><option value="">Semua poliklinik</option>
      <?php foreach ($polis as $id => $nm): ?><option value="<?= e((string) $id) ?>" <?= (string) $polyId === (string) $id ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select>
    <select name="status"><option value="">Semua status</option>
      <?php foreach ($qLbl as $c => [$l]): ?><option value="<?= e($c) ?>" <?= $status === $c ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <label class="inline"><input type="checkbox" name="semua" value="1" <?= $semua ? 'checked' : '' ?>> Semua dokter</label>
    <button type="submit" class="btn btn-outline">Filter</button>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>Antrean</th><th>Pasien</th><th>Poliklinik / Dokter</th><th>Alergi</th><th>Status</th><th>Aksi</th></tr>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">Tidak ada antrean rawat jalan.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <?php [$ql, $qc] = $qLbl[$r['q_status']] ?? [$r['q_status'], 'cp-info']; ?>
        <tr>
          <td><b><?= e($r['queue_no']) ?></b><br><span class="muted">#<?= (int) $r['visit_id'] ?></span></td>
          <td><?= e($r['nama_pasien']) ?><br><code class="k"><?= e($r['no_rm']) ?></code></td>
          <td><?= e($r['polyclinic_name']) ?><br><span class="muted"><?= $r['doctor_name'] ? e($r['doctor_name']) : '&mdash;' ?></span></td>
          <td><?= (int) $r['jml_alergi'] > 0 ? '<span class="cp-badge cp-warn">⚠ ' . (int) $r['jml_alergi'] . '</span>' : '<span class="muted">&mdash;</span>' ?></td>
          <td><span class="cp-badge <?= e($qc) ?>"><?= e($ql) ?></span>
              <?php if ($r['v_status'] === 'in_progress'): ?><br><span class="muted">sedang diperiksa</span><?php endif; ?></td>
          <td class="aksi">
            <?php if (can('outpatient.update') && $r['v_status'] === 'registered'): ?>
              <?php if (in_array($r['q_status'], ['waiting', 'skipped'], true)): ?>
                <form method="post" action="<?= e(url('modules/outpatient/queue_action.php')) ?>" class="inline-form">
                  <?= csrf_field() ?><input type="hidden" name="visit_id" value="<?= (int) $r['visit_id'] ?>"><input type="hidden" name="action" value="call">
                  <button type="submit" class="action-link">📣 Panggil</button>
                </form>
              <?php elseif ($r['q_status'] === 'called'): ?>
                <form method="post" action="<?= e(url('modules/outpatient/queue_action.php')) ?>" class="inline-form">
                  <?= csrf_field() ?><input type="hidden" name="visit_id" value="<?= (int) $r['visit_id'] ?>"><input type="hidden" name="action" value="skip">
                  <button type="submit" class="action-link danger">⏭ Lewati</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (can('outpatient.update') && $dokter && in_array($r['v_status'], ['registered', 'in_progress'], true)): ?>
              <form method="post" action="<?= e(url('modules/outpatient/queue_action.php')) ?>" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="visit_id" value="<?= (int) $r['visit_id'] ?>"><input type="hidden" name="action" value="start">
                <button type="submit" class="action-link">▶ <?= $r['v_status'] === 'in_progress' ? 'Lanjut Periksa' : 'Mulai Periksa' ?></button>
              </form>
            <?php endif; ?>
            <a class="action-link" href="<?= e(url('modules/outpatient/exam.php?visit_id=' . (int) $r['visit_id'])) ?>">📄 Buka</a>
            <a class="action-link" href="<?= e(url('modules/outpatient/history.php?patient_id=' . (int) $r['patient_id'])) ?>">📋 Riwayat</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>