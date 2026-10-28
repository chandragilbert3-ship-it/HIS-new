<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.view');

$id      = (int) ($_GET['id'] ?? 0);
$receipt = $id > 0 ? inv_find_receipt($id) : null;
if ($id > 0 && $receipt === null) {
    flash_set('error', 'Penerimaan tidak ditemukan.');
    redirect('modules/inventory/receipts.php');
}
if ($receipt !== null) {
    audit_log('Buka penerimaan barang #' . $id, 'goods_receipts', $id, null);
}
$items = $receipt !== null ? inv_receipt_items($id) : [];
$rows  = $receipt === null ? inv_receipts() : [];

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Penerimaan Barang';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('receipts');
?>
<?php if ($receipt !== null): ?>
<div class="card">
  <h2>Penerimaan GR-<?= (int) $receipt['receipt_id'] ?></h2>
  <div class="cp-info-box">
    <div><span class="muted">Gudang</span><br><?= e($receipt['warehouse_name']) ?></div>
    <div><span class="muted">Pemasok</span><br><?= e($receipt['supplier_name']) ?></div>
    <div><span class="muted">No. faktur / surat jalan</span><br><?= e((string) $receipt['source_document']) ?></div>
    <div><span class="muted">Diterima</span><br><?= e(date('d/m/Y H:i', strtotime((string) $receipt['received_at']))) ?></div>
    <div><span class="muted">Petugas</span><br><?= e((string) $receipt['user_name']) ?></div>
  </div>
  <div class="table-wrap"><table>
    <tr><th>Barang</th><th>Batch</th><th>Kedaluwarsa</th><th>Jumlah</th></tr>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['item_name']) ?> <code class="k"><?= e($it['item_code']) ?></code></td>
        <td><?= e($it['batch_no']) ?></td>
        <td><?= $it['expiry_date'] ? e(date('d/m/Y', strtotime((string) $it['expiry_date']))) : '&mdash;' ?></td>
        <td><?= e(inv_fmt((float) $it['quantity'])) ?> <?= e((string) $it['unit']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/inventory/receipts.php')) ?>">&larr; Daftar Penerimaan</a></div>
</div>
<?php else: ?>
<div class="card">
  <h2>Penerimaan Barang</h2>
  <?php if (can('inventory.create')): ?><div class="toolbar"><a class="btn btn-primary" href="<?= e(url('modules/inventory/receipt_form.php')) ?>">➕ Terima Barang</a></div><?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>No.</th><th>Tanggal</th><th>Gudang</th><th>Pemasok</th><th>No. Faktur / SJ</th><th>Item</th><th>Petugas</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">Belum ada penerimaan.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>GR-<?= (int) $r['receipt_id'] ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime((string) $r['received_at']))) ?></td>
        <td><?= e($r['warehouse_name']) ?></td>
        <td><?= e($r['supplier_name']) ?></td>
        <td><?= e((string) $r['source_document']) ?></td>
        <td><?= (int) $r['jml_item'] ?></td>
        <td><?= e((string) $r['user_name']) ?></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/inventory/receipts.php?id=' . (int) $r['receipt_id'])) ?>">Detail</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>