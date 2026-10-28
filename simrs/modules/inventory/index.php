<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.view');

$warehouses = inv_warehouses();
$whId = inv_pick_warehouse($warehouses, (int) ($_GET['w'] ?? 0));
$q    = trim((string) ($_GET['q'] ?? ''));
$rows = $whId > 0 ? inv_stock_summary($whId, $q) : [];
$jmlExpiring = $whId > 0 ? count(inv_batches($whId, 'expiring')) : 0;
$jmlExpired  = $whId > 0 ? count(inv_batches($whId, 'expired')) : 0;

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Stok';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('index');
?>
<div class="card">
  <h2>Stok Barang</h2>
  <?php if (!$warehouses): ?><div class="alert alert-warning">Belum ada gudang. Tambahkan di tab Gudang &amp; Lokasi.</div><?php endif; ?>
  <form method="get" class="toolbar" action="<?= e(url('modules/inventory/index.php')) ?>">
    <label for="w" class="muted">Gudang:</label>
    <select id="w" name="w" onchange="this.form.submit()">
      <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['warehouse_id'] ?>"<?= (int) $w['warehouse_id'] === $whId ? ' selected' : '' ?>><?= e($w['warehouse_name']) ?></option><?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kode / nama barang" maxlength="100">
    <button type="submit" class="btn btn-outline">Cari</button>
  </form>
  <?php if ($jmlExpired > 0): ?><div class="alert alert-error">⛔ <?= $jmlExpired ?> batch sudah kedaluwarsa dan masih ada stok. <a href="<?= e(url('modules/inventory/batches.php?w=' . $whId . '&f=expired')) ?>">Lihat</a></div><?php endif; ?>
  <?php if ($jmlExpiring > 0): ?><div class="alert alert-warning">⚠ <?= $jmlExpiring ?> batch akan kedaluwarsa dalam 90 hari. <a href="<?= e(url('modules/inventory/batches.php?w=' . $whId . '&f=expiring')) ?>">Lihat</a></div><?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Kode</th><th>Barang</th><th>Kategori</th><th>Tersedia</th><th>Total di rak</th><th>Min / Maks</th><th>Kedaluwarsa terdekat</th><th>Status</th></tr>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">Tidak ada data.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$lv, $lc] = inv_stock_level((float) $r['available'], (float) $r['min_stock'], (float) $r['max_stock']); ?>
      <tr>
        <td><code class="k"><?= e($r['item_code']) ?></code></td>
        <td><?= e($r['item_name']) ?></td>
        <td><?= e((string) $r['category']) ?></td>
        <td><b><?= e(inv_fmt((float) $r['available'])) ?></b> <?= e((string) $r['unit']) ?></td>
        <td><?= e(inv_fmt((float) $r['on_hand'])) ?></td>
        <td><?= e(inv_fmt((float) $r['min_stock'])) ?> / <?= e(inv_fmt((float) $r['max_stock'])) ?></td>
        <td><?= $r['nearest_expiry'] ? e(date('d/m/Y', strtotime((string) $r['nearest_expiry']))) : '&mdash;' ?></td>
        <td><span class="cp-badge <?= e($lc) ?>"><?= e($lv) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>