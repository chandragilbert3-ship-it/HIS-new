<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/pharmacy.php';

require_permission('pharmacy.view');

$mode  = ($_GET['tab'] ?? 'active') === 'done' ? 'done' : 'active';
$rows  = ph_queue($mode);
$stat  = ph_status_labels();

$pageTitle    = 'Farmasi';
$activeModule = 'pharmacy';
$breadcrumb   = 'Modul 12. Farmasi';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="cp-tabs">
  <a class="<?= $mode === 'active' ? 'active' : '' ?>" href="<?= e(url('modules/pharmacy/index.php?tab=active')) ?>">1. Antrean Resep</a>
  <a class="<?= $mode === 'done' ? 'active' : '' ?>" href="<?= e(url('modules/pharmacy/index.php?tab=done')) ?>">2. Selesai (7 hari)</a>
</div>
<div class="card">
  <h2><?= $mode === 'done' ? 'Resep Selesai / Dibatalkan' : 'Antrean Resep' ?></h2>
  <div class="table-wrap"><table>
    <tr><th>Resep</th><th>Pasien</th><th>Dokter</th><th>Waktu</th><th>Item</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada resep.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$sl, $sc] = $stat[$r['status']] ?? [$r['status'], 'cp-info']; ?>
      <tr>
        <td>#<?= (int) $r['prescription_id'] ?><?= $r['priority'] === 'cito' ? ' <span class="cp-badge cp-off">CITO</span>' : '' ?></td>
        <td><?= e($r['nama_pasien']) ?><br><code class="k"><?= e($r['no_rm']) ?></code></td>
        <td><?= e((string) $r['doctor_name']) ?></td>
        <td><?= e(date('d/m H:i', strtotime($r['ordered_at']))) ?></td>
        <td><?= (int) $r['jml_item'] ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/pharmacy/prescription.php?prescription_id=' . (int) $r['prescription_id'])) ?>">💊 Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>