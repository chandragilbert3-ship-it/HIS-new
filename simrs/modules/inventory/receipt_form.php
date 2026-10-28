<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.create');

$warehouses = inv_warehouses();
$whId       = inv_pick_warehouse($warehouses, (int) ($_POST['warehouse_id'] ?? $_GET['w'] ?? 0));
$suppliers  = inv_active_suppliers();
$allItems   = inv_all_items();
$locations  = $whId > 0 ? inv_active_locations($whId) : [];

$itemMap = [];
foreach ($allItems as $it) {
    $itemMap[(int) $it['inventory_item_id']] = $it;
}
$errors = [];
$old    = ['supplier_id' => '', 'source_document' => '', 'line' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $old = ['supplier_id' => (string) ($_POST['supplier_id'] ?? ''), 'source_document' => (string) ($_POST['source_document'] ?? ''), 'line' => is_array($_POST['line'] ?? null) ? $_POST['line'] : []];
    if ($whId === 0) {
        $errors[] = 'Belum ada gudang.';
    } else {
        [$data, $errors] = inv_validate_receipt(
            $_POST,
            $itemMap,
            array_map(fn($s) => (int) $s['supplier_id'], $suppliers),
            array_map(fn($l) => (int) $l['location_id'], $locations)
        );
        if (!$errors) {
            [$err, $rid] = inv_receive($whId, $data, (int) current_user()['id']);
            if ($err === null) {
                audit_log('Terima barang GR-' . $rid . ' (' . count($data['lines']) . ' baris, dokumen ' . $data['source_document'] . ')', 'goods_receipts', $rid, null);
                flash_set('success', 'Penerimaan GR-' . $rid . ' tersimpan. Stok bertambah.');
                redirect('modules/inventory/receipts.php?id=' . $rid);
            }
            $errors[] = $err;
        }
    }
}

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Terima Barang';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('receipts');
?>
<div class="card">
  <h2>Terima Barang</h2>
  <?php if (!$suppliers): ?><div class="alert alert-warning">Belum ada pemasok aktif. Tambahkan di tab Pemasok.</div><?php endif; ?>
  <?php if (!$warehouses): ?><div class="alert alert-warning">Belum ada gudang. Tambahkan di tab Gudang &amp; Lokasi.</div><?php endif; ?>
  <form method="get" class="toolbar" action="<?= e(url('modules/inventory/receipt_form.php')) ?>">
    <label for="w" class="muted">Gudang tujuan:</label>
    <select id="w" name="w" onchange="this.form.submit()">
      <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['warehouse_id'] ?>"<?= (int) $w['warehouse_id'] === $whId ? ' selected' : '' ?>><?= e($w['warehouse_name']) ?></option><?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn btn-outline">Pilih</button></noscript>
  </form>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/inventory/receipt_form.php?w=' . $whId)) ?>">
    <?= csrf_field() ?><input type="hidden" name="warehouse_id" value="<?= $whId ?>">
    <div class="form-grid">
      <div class="form-row"><label for="supplier_id">Pemasok *</label>
        <select id="supplier_id" name="supplier_id" required>
          <option value="">-- pilih --</option>
          <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['supplier_id'] ?>"<?= (string) $s['supplier_id'] === $old['supplier_id'] ? ' selected' : '' ?>><?= e($s['supplier_name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-row"><label for="source_document">No. faktur / surat jalan *</label>
        <input type="text" id="source_document" name="source_document" maxlength="50" required value="<?= e($old['source_document']) ?>"></div>
    </div>
    <div class="table-wrap"><table>
      <tr><th>#</th><th>Barang</th><th>No. Batch</th><th>Kedaluwarsa</th><th>Jumlah</th><th>Rak</th></tr>
      <?php for ($i = 0; $i < INV_RECEIPT_LINES; $i++): $l = is_array($old['line'][$i] ?? null) ? $old['line'][$i] : []; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><select name="line[<?= $i ?>][item]">
            <option value="">--</option>
            <?php foreach ($allItems as $it): ?><option value="<?= (int) $it['inventory_item_id'] ?>"<?= (string) $it['inventory_item_id'] === (string) ($l['item'] ?? '') ? ' selected' : '' ?>><?= e($it['item_name']) ?> (<?= e((string) $it['unit']) ?>)</option><?php endforeach; ?>
          </select></td>
          <td><input type="text" name="line[<?= $i ?>][batch]" maxlength="50" style="width:110px" value="<?= e((string) ($l['batch'] ?? '')) ?>"></td>
          <td><input type="date" name="line[<?= $i ?>][expiry]" value="<?= e((string) ($l['expiry'] ?? '')) ?>"></td>
          <td><input type="text" inputmode="decimal" name="line[<?= $i ?>][qty]" style="width:80px" value="<?= e((string) ($l['qty'] ?? '')) ?>"></td>
          <td><select name="line[<?= $i ?>][loc]">
            <option value="0">--</option>
            <?php foreach ($locations as $loc): ?><option value="<?= (int) $loc['location_id'] ?>"<?= (string) $loc['location_id'] === (string) ($l['loc'] ?? '') ? ' selected' : '' ?>><?= e($loc['bin_code']) ?></option><?php endforeach; ?>
          </select></td>
        </tr>
      <?php endfor; ?>
    </table></div>
    <p class="muted">Isi hanya baris yang dipakai. Kedaluwarsa wajib untuk kategori Obat. Barang yang sudah kedaluwarsa ditolak. Rak hanya dipakai bila batch baru.</p>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Simpan penerimaan? Stok akan bertambah dan tidak dapat diubah.');">Simpan Penerimaan</button>
    <a class="btn btn-outline" href="<?= e(url('modules/inventory/receipts.php')) ?>">Batal</a>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>