<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/nursing.php';

require_permission('nursing.view');

$q      = trim((string) ($_GET['q'] ?? ''));
$q      = mb_substr($q, 0, 60);
$visits = nrs_active_visits($q);
$svc    = nrs_service_labels();

$pageTitle    = 'Pasien Keperawatan';
$activeModule = 'nursing';
$breadcrumb   = 'Modul 8. Keperawatan';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Kunjungan Aktif</h2>
  <form method="get" action="<?= e(url('modules/nursing/index.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" maxlength="60" placeholder="Cari nama / No. RM">
    <button type="submit" class="btn btn-primary">Cari</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline" href="<?= e(url('modules/nursing/index.php')) ?>">Reset</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table>
    <tr><th>Visit</th><th>Pasien</th><th>Layanan</th><th>Tanggal</th><th>Pengkajian</th><th>Rencana Aktif</th><th>Aksi</th></tr>
    <?php if (!$visits): ?><tr><td colspan="7" class="empty">Tidak ada kunjungan aktif.</td></tr><?php endif; ?>
    <?php foreach ($visits as $v): ?>
      <tr>
        <td>#<?= (int) $v['visit_id'] ?></td>
        <td><?= e($v['nama_pasien']) ?><br><code class="k"><?= e($v['no_rm']) ?></code></td>
        <td><?= e((string) ($svc[$v['service_type']] ?? $v['service_type'])) ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime($v['visit_date']))) ?></td>
        <td><?= (int) $v['jml_kaji'] > 0 ? '<span class="cp-badge cp-ok">' . (int) $v['jml_kaji'] . 'x</span>' : '<span class="cp-badge cp-warn">belum</span>' ?></td>
        <td><?= (int) $v['jml_plan'] ?></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/nursing/visit.php?visit_id=' . (int) $v['visit_id'])) ?>">🩺 Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>