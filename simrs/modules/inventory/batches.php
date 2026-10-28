<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.view');

$warehouses = inv_warehouses();
$whId   = inv_pick_warehouse($warehouses, (int) ($_POST['warehouse_id'] ?? $_GET['w'] ?? 0));
$filter = (string) ($_POST['f'] ?? $_GET['f'] ?? 'active');
if (!in_array($filter, ['active', 'expiring', 'expired', 'quarantine', 'all'], true)) {
    $filter = 'active';
}
$back = 'modules/inventory/batches.php?w=' . $whId . '&f=' . $filter;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('inventory.update');
    $do  = (string) ($_POST['do'] ?? '');
    $bid = (int) ($_POST['batch_id'] ?? 0);
    if ($do === 'sync') {
        $n = inv_sync_expired();
        audit_log('Sinkron status kedaluwarsa batch (' . $n . ' diubah)', 'stock_batches', null, null);
        flash_set('success', $n . ' batch diperbarui.');
    } elseif ($do === 'quarantine' || $do === 'release') {
        $err = inv_set_batch_status($bid, $do === 'quarantine' ? 'quarantine' : 'available');
        if ($err === null) {
            audit_log(($do === 'quarantine' ? 'Karantina' : 'Lepas karantina') . ' batch #' . $bid, 'stock_batches', $bid, null);
            flash_set('success', $do === 'quarantine' ? 'Batch dikarantina.' : 'Batch dilepas dari karantina.');
        } else {
            flash_set('error', $err);
        }
    } else {
        flash_set('error', 'Aksi tidak dikenal.');
    }
    redirect($back);
}

$rows = $whId > 0 ? inv_batches($whId, $filter) : [];
$bst  = inv_batch_status_labels();
$filters = ['active' => 'Ada stok', 'expiring' => 'Kedaluwarsa ≤ 90 hari', 'expired' => 'Sudah kedaluwarsa', 'quarantine' => 'Karantina', 'all' => 'Semua'];

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Batch';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('batches');
?>
<div class="card">
  <h2>Batch &amp; Kedaluwarsa</h2>
  <form method="get" class="toolbar" action="<?= e(url('modules/inventory/batches.php')) ?>">
    <select name="w" onchange="this.form.submit()">
      <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['warehouse_id'] ?>"<?= (int) $w['warehouse_id'] === $whId ? ' selected' : '' ?>><?= e($w['warehouse_name']) ?></option><?php endforeach; ?>
    </select>
    <select name="f" onchange="this.form.submit()">
      <?php foreach ($filters as $k => $lbl): ?><option value="<?= e($k) ?>"<?= $k === $filter ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn-outline">Tampilkan</button></noscript>
  </form>
  <?php if (can('inventory.update')): ?>
    <form method="post" class="toolbar" action="<?= e(url($back)) ?>">
      <?= csrf_field() ?><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="f" value="<?= e($filter) ?>"><input type="hidden" name="do" value="sync">
      <button type="submit" class="btn btn-outline">🔄 Perbarui status kedaluwarsa</button>
    </form>
  <?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Barang</th><th>Batch</th><th>Rak</th><th>Kedaluwarsa</th><th>Jumlah</th><th>Status</th><?= can('inventory.update') ? '<th>Aksi</th>' : '' ?></tr>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada batch.</td></tr><?php endif; ?>
    <?php foreach ($rows as $b): $es = inv_effective_status($b); [$sl, $sc] = $bst[$es] ?? [$es, 'cp-info']; ?>
      <tr>
        <td><?= e($b['item_name']) ?><br><code class="k"><?= e($b['item_code']) ?></code></td>
        <td><?= e($b['batch_no']) ?></td>
        <td><?= $b['bin_code'] ? e((string) $b['bin_code']) : '&mdash;' ?></td>
        <td><?= $b['expiry_date'] ? e(date('d/m/Y', strtotime((string) $b['expiry_date']))) : '&mdash;' ?></td>
        <td><?= e(inv_fmt((float) $b['quantity_on_hand'])) ?> <?= e((string) $b['unit']) ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <?php if (can('inventory.update')): ?>
          <td class="aksi">
            <?php if ($es === 'available'): ?>
              <form method="post" class="inline-form" action="<?= e(url($back)) ?>" onsubmit="return confirm('Karantina batch ini?');"><?= csrf_field() ?><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="f" value="<?= e($filter) ?>"><input type="hidden" name="batch_id" value="<?= (int) $b['stock_batch_id'] ?>"><input type="hidden" name="do" value="quarantine"><button type="submit" class="btn btn-outline">Karantina</button></form>
            <?php elseif ($es === 'quarantine'): ?>
              <form method="post" class="inline-form" action="<?= e(url($back)) ?>"><?= csrf_field() ?><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="f" value="<?= e($filter) ?>"><input type="hidden" name="batch_id" value="<?= (int) $b['stock_batch_id'] ?>"><input type="hidden" name="do" value="release"><button type="submit" class="btn btn-outline">Lepas</button></form>
            <?php else: ?>&mdash;<?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>