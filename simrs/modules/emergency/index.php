<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/emergency.php';

require_permission('emergency.view');

$cases    = ed_active_cases();
$unopened = ed_unopened_visits();
$prio     = ed_priority_labels();
$stat     = ed_status_labels();
$arr      = ed_arrival_labels();

$pageTitle    = 'Pasien IGD Aktif';
$activeModule = 'emergency';
$breadcrumb   = 'Modul 6. Gawat Darurat (IGD)';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a class="active" href="<?= e(url('modules/emergency/index.php')) ?>">1. Pasien IGD Aktif</a>
  <a href="<?= e(url('modules/emergency/history.php')) ?>">2. Riwayat IGD</a>
</div>

<?php if ($unopened): ?>
<div class="card">
  <h2>Menunggu Dibuka (sudah didaftarkan, belum ada kasus IGD)</h2>
  <div class="table-wrap"><table>
    <tr><th>Visit</th><th>Antrean</th><th>Pasien</th><th>Waktu Daftar</th><th>Aksi</th></tr>
    <?php foreach ($unopened as $u): ?>
      <tr>
        <td>#<?= (int) $u['visit_id'] ?></td>
        <td><?= $u['queue_no'] ? e($u['queue_no']) : '&mdash;' ?></td>
        <td><?= e($u['nama_pasien']) ?><br><code class="k"><?= e($u['no_rm']) ?></code></td>
        <td><?= e(date('d/m/Y H:i', strtotime($u['visit_date']))) ?></td>
        <td class="aksi"><?php if (can('emergency.create')): ?>
          <a class="action-link" href="<?= e(url('modules/emergency/open_case.php?visit_id=' . (int) $u['visit_id'])) ?>">🚑 Buka Kasus IGD</a>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <h2>Pasien IGD Aktif</h2>
  <div class="table-wrap"><table>
    <tr><th>Kasus</th><th>Pasien</th><th>Datang</th><th>Cara Datang</th><th>Prioritas</th><th>Alergi</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$cases): ?><tr><td colspan="8" class="empty">Tidak ada pasien IGD aktif.</td></tr><?php endif; ?>
    <?php foreach ($cases as $c): ?>
      <?php [$sl, $sc] = $stat[$c['status']] ?? [$c['status'], 'cp-info']; ?>
      <tr>
        <td><b><?= e(ed_case_no((int) $c['ed_encounter_id'])) ?></b></td>
        <td><?= e($c['nama_pasien']) ?><br><code class="k"><?= e($c['no_rm']) ?></code></td>
        <td><?= e(date('d/m H:i', strtotime($c['arrival_time']))) ?></td>
        <td><?= e($arr[$c['arrival_mode']] ?? $c['arrival_mode']) ?></td>
        <td><?php if ($c['prioritas']): [$pl, $pc] = $prio[$c['prioritas']] ?? [$c['prioritas'], 'cp-info']; ?>
            <span class="cp-badge <?= e($pc) ?>"><?= e($pl) ?></span>
            <?php else: ?><span class="muted">belum triase</span><?php endif; ?></td>
        <td><?= (int) $c['jml_alergi'] > 0 ? '<span class="cp-badge cp-warn">⚠ ' . (int) $c['jml_alergi'] . '</span>' : '<span class="muted">&mdash;</span>' ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/emergency/case.php?id=' . (int) $c['ed_encounter_id'])) ?>">📄 Buka Kasus</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
