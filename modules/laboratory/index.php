<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/laboratory.php';

require_permission('laboratory.view');

$mode  = ($_GET['tab'] ?? 'active') === 'done' ? 'done' : 'active';
$rows  = lab_queue($mode);

$pageTitle    = 'Laboratorium';
$activeModule = 'laboratory';
$breadcrumb   = 'Modul 10. Laboratorium';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="cp-tabs">
  <a class="<?= $mode === 'active' ? 'active' : '' ?>" href="<?= e(url('modules/laboratory/index.php?tab=active')) ?>">1. Antrean Order Lab</a>
  <a class="<?= $mode === 'done' ? 'active' : '' ?>" href="<?= e(url('modules/laboratory/index.php?tab=done')) ?>">2. Selesai (7 hari)</a>
</div>
<div class="card">
  <h2><?= $mode === 'done' ? 'Order Lab Selesai' : 'Antrean Order Laboratorium' ?></h2>
  <div class="table-wrap"><table>
    <tr><th>Order</th><th>Pasien</th><th>Dokter</th><th>Waktu</th><th>Pemeriksaan</th><th>Tervalidasi</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">Tidak ada order.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= (int) $r['order_id'] ?><?= $r['priority'] === 'cito' ? ' <span class="cp-badge cp-off">CITO</span>' : '' ?></td>
        <td><?= e($r['nama_pasien']) ?><br><code class="k"><?= e($r['no_rm']) ?></code></td>
        <td><?= e((string) $r['doctor_name']) ?></td>
        <td><?= e(date('d/m H:i', strtotime($r['ordered_at']))) ?></td>
        <td><?= (int) $r['jml_item'] ?></td>
        <td><?= (int) $r['jml_valid'] ?> / <?= (int) $r['jml_item'] ?></td>
        <td><span class="cp-badge <?= $r['status'] === 'completed' ? 'cp-ok' : ($r['status'] === 'in_progress' ? 'cp-warn' : 'cp-info') ?>"><?= $r['status'] === 'completed' ? 'Selesai' : ($r['status'] === 'in_progress' ? 'Diproses' : 'Dipesan') ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/laboratory/order.php?order_id=' . (int) $r['order_id'])) ?>">🧪 Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
