<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory_ops.php';

require_permission('inventory.create');

$warehouses = inv_warehouses();
$whId       = inv_pick_warehouse($warehouses, (int) ($_POST['warehouse_id'] ?? $_GET['w'] ?? 0));
$allItems   = inv_all_items();
$itemMap    = [];
foreach ($allItems as $it) {
    $itemMap[(int) $it['inventory_item_id']] = $it;
}
$errors = [];
$old    = ['requesting_unit' => '', 'line' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $old = ['requesting_unit' => (string) ($_POST['requesting_unit'] ?? ''), 'line' => is_array($_POST['line'] ?? null) ? $_POST['line'] : []];
    [$data, $errors] = invr_validate($_POST, $itemMap, array_map(fn($w) => (int) $w['warehouse_id'], $warehouses));
    if (!$errors) {
        $rid = invr_create($data, (int) current_user()['id']);
        audit_log('Buat permintaan stok REQ-' . $rid . ' (unit ' . $data['unit'] . ', ' . count($data['lines']) . ' item)', 'inventory_requests', $rid, null);
        flash_set('success', 'Permintaan REQ-' . $rid . ' dibuat.');
        redirect('modules/inventory/requests.php?id=' . $rid);
    }
}

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Buat Permintaan';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('requests');
?>
<div class="card">
  <h2>Buat Permintaan Stok</h2>
  <?php if (!$warehouses): ?><div class="alert alert-warning">Belum ada gudang.</div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/inventory/request_form.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-row"><label for="warehouse_id">Gudang sumber *</label>
        <select id="warehouse_id" name="warehouse_id" required>
          <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['warehouse_id'] ?>"<?= (int) $w['warehouse_id'] === $whId ? ' selected' : '' ?>><?= e($w['warehouse_name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-row"><label for="requesting_unit">Unit peminta *</label>
        <input type="text" id="requesting_unit" name="requesting_unit" maxlength="100" required value="<?= e($old['requesting_unit']) ?>" placeholder="Contoh: IGD, Rawat Inap, Poli Umum"></div>
    </div>
    <div class="table-wrap"><table>
      <tr><th>#</th><th>Barang</th><th>Jumlah</th></tr>
      <?php for ($i = 0; $i < INVR_LINES; $i++): $l = is_array($old['line'][$i] ?? null) ? $old['line'][$i] : []; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><select name="line[<?= $i ?>][item]">
            <option value="">--</option>
            <?php foreach ($allItems as $it): ?><option value="<?= (int) $it['inventory_item_id'] ?>"<?= (string) $it['inventory_item_id'] === (string) ($l['item'] ?? '') ? ' selected' : '' ?>><?= e($it['item_name']) ?> (<?= e((string) $it['unit']) ?>)</option><?php endforeach; ?>
          </select></td>
          <td><input type="text" inputmode="decimal" name="line[<?= $i ?>][qty]" style="width:90px" value="<?= e((string) ($l['qty'] ?? '')) ?>"></td>
        </tr>
      <?php endfor; ?>
    </table></div>
    <p class="muted">Isi hanya baris yang dipakai. Satu barang hanya boleh muncul sekali.</p>
    <button type="submit" class="btn btn-primary">Kirim Permintaan</button>
    <a class="btn btn-outline" href="<?= e(url('modules/inventory/requests.php')) ?>">Batal</a>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
