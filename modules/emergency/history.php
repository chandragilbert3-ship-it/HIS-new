<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/emergency.php';

require_permission('emergency.view');

$from = reg_valid_date((string) ($_GET['dari'] ?? '')) ?? date('Y-m-d', strtotime('-7 days'));
$to   = reg_valid_date((string) ($_GET['sampai'] ?? '')) ?? date('Y-m-d');
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$rows = ed_history($from, $to);
$prio = ed_priority_labels();
$stat = ed_status_labels();

audit_log('Buka riwayat IGD ' . $from . ' s/d ' . $to, 'ed_encounters');

$pageTitle    = 'Riwayat IGD';
$activeModule = 'emergency';
$breadcrumb   = 'Modul 6. IGD &rsaquo; Riwayat';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="cp-tabs">
  <a href="<?= e(url('modules/emergency/index.php')) ?>">1. Pasien IGD Aktif</a>
  <a class="active" href="<?= e(url('modules/emergency/history.php')) ?>">2. Riwayat IGD</a>
</div>
<div class="card">
  <h2>Riwayat IGD</h2>
  <form method="get" action="<?= e(url('modules/emergency/history.php')) ?>" class="toolbar">
    <label class="inline">Dari <input type="date" name="dari" value="<?= e($from) ?>"></label>
    <label class="inline">Sampai <input type="date" name="sampai" value="<?= e($to) ?>"></label>
    <button type="submit" class="btn btn-primary">Tampilkan</button>
  </form>
  <div class="table-wrap"><table>
    <tr><th>Kasus</th><th>Pasien</th><th>Datang</th><th>Prioritas</th><th>Disposisi</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada data pada rentang ini.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$sl, $sc] = $stat[$r['status']] ?? [$r['status'], 'cp-info']; ?>
      <tr>
        <td><?= e(ed_case_no((int) $r['ed_encounter_id'])) ?></td>
        <td><?= e($r['nama_pasien']) ?><br><code class="k"><?= e($r['no_rm']) ?></code></td>
        <td><?= e(date('d/m/Y H:i', strtotime($r['arrival_time']))) ?></td>
        <td><?php if ($r['prioritas']): [$pl, $pc] = $prio[$r['prioritas']] ?? [$r['prioritas'], 'cp-info']; ?><span class="cp-badge <?= e($pc) ?>"><?= e($pl) ?></span><?php else: ?>&mdash;<?php endif; ?></td>
        <td><?= $r['disposisi'] ? e(ed_disposition_labels()[$r['disposisi']] ?? $r['disposisi']) : '&mdash;' ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/emergency/case.php?id=' . (int) $r['ed_encounter_id'])) ?>">📄 Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>