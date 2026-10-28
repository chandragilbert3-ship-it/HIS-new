<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.view');

$editId = (int) ($_GET['edit'] ?? $_POST['inventory_item_id'] ?? 0);
$isNew  = isset($_GET['new']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && $editId === 0);
$editing = $editId > 0 ? inv_find_item($editId) : null;
if ($editId > 0 && $editing === null) {
    flash_set('error', 'Barang tidak ditemukan.');
    redirect('modules/inventory/items.php');
}
$showForm = ($isNew && can('inventory.create')) || ($editing !== null && can('inventory.update'));
$errors = [];
$old = [
    'item_code' => (string) ($editing['item_code'] ?? ''), 'item_name' => (string) ($editing['item_name'] ?? ''),
    'category' => (string) ($editing['category'] ?? 'Obat'), 'unit' => (string) ($editing['unit'] ?? ''),
    'min_stock' => $editing ? inv_input((int) round((float) $editing['min_stock'] * 100)) : '0', 'max_stock' => $editing ? inv_input((int) round((float) $editing['max_stock'] * 100)) : '0',
    'is_drug' => ($editing && $editing['drug_id'] !== null) ? '1' : '', 'drug_name' => (string) ($editing['drug_name'] ?? ''),
    'dosage_form' => (string) ($editing['dosage_form'] ?? ''), 'strength' => (string) ($editing['strength'] ?? ''),
    'drug_active' => ($editing && $editing['drug_id'] !== null) ? ((int) $editing['drug_active'] === 1 ? '1' : '') : '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission($editId > 0 ? 'inventory.update' : 'inventory.create');
    $old = array_merge($old, array_map(fn($v) => is_string($v) ? $v : '', array_intersect_key($_POST, $old)));
    $old['is_drug']     = ($_POST['is_drug'] ?? '') === '1' ? '1' : '';
    $old['drug_active'] = ($_POST['drug_active'] ?? '') === '1' ? '1' : '';
    [$data, $errors] = inv_validate_item($_POST, $editId > 0 ? $editId : null);
    if (!$errors) {
        [$err, $newId] = inv_save_item($editId > 0 ? $editId : null, $data);
        if ($err === null) {
            audit_log(($editId > 0 ? 'Ubah' : 'Tambah') . ' barang ' . $data['item_code'], 'inventory_items', $newId, null);
            flash_set('success', 'Barang tersimpan.');
            redirect('modules/inventory/items.php');
        }
        $errors[] = $err;
    }
    $showForm = true;
}

$q    = trim((string) ($_GET['q'] ?? ''));
$rows = inv_items_list($q);

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Barang & Obat';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('items');
?>
<?php if ($showForm): ?>
<div class="card">
  <h2><?= $editId > 0 ? 'Ubah Barang' : 'Tambah Barang' ?></h2>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/inventory/items.php' . ($editId > 0 ? '?edit=' . $editId : ''))) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="inventory_item_id" value="<?= $editId ?>">
    <div class="form-row"><label for="item_code">Kode barang *</label><input type="text" id="item_code" name="item_code" maxlength="30" required value="<?= e($old['item_code']) ?>"></div>
    <div class="form-row"><label for="item_name">Nama barang *</label><input type="text" id="item_name" name="item_name" maxlength="150" required value="<?= e($old['item_name']) ?>"></div>
    <div class="form-row"><label for="category">Kategori</label><input type="text" id="category" name="category" maxlength="50" list="kategori" value="<?= e($old['category']) ?>">
      <datalist id="kategori"><option value="Obat"><option value="Alat Kesehatan"><option value="BMHP"><option value="Umum"></datalist></div>
    <div class="form-row"><label for="unit">Satuan *</label><input type="text" id="unit" name="unit" maxlength="20" required value="<?= e($old['unit']) ?>" placeholder="tablet, kapsul, botol"></div>
    <div class="form-row"><label for="min_stock">Stok minimum</label><input type="text" inputmode="decimal" id="min_stock" name="min_stock" value="<?= e($old['min_stock']) ?>"></div>
    <div class="form-row"><label for="max_stock">Stok maksimum</label><input type="text" inputmode="decimal" id="max_stock" name="max_stock" value="<?= e($old['max_stock']) ?>"></div>
    <div class="form-row full"><label><input type="checkbox" name="is_drug" value="1"<?= $old['is_drug'] === '1' ? ' checked' : '' ?>> Barang ini adalah obat (dapat diresepkan di CPOE)</label></div>
    <div class="form-row"><label for="drug_name">Nama obat (di CPOE)</label><input type="text" id="drug_name" name="drug_name" maxlength="150" value="<?= e($old['drug_name']) ?>"></div>
    <div class="form-row"><label for="dosage_form">Bentuk sediaan</label><input type="text" id="dosage_form" name="dosage_form" maxlength="50" value="<?= e($old['dosage_form']) ?>" placeholder="Tablet, Kapsul"></div>
    <div class="form-row"><label for="strength">Kekuatan</label><input type="text" id="strength" name="strength" maxlength="50" value="<?= e($old['strength']) ?>" placeholder="500 mg"></div>
    <div class="form-row"><label><input type="checkbox" name="drug_active" value="1"<?= $old['drug_active'] === '1' ? ' checked' : '' ?>> Obat aktif (tampil di CPOE)</label></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan</button>
      <a class="btn btn-outline" href="<?= e(url('modules/inventory/items.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Barang &amp; Obat</h2>
  <form method="get" class="toolbar" action="<?= e(url('modules/inventory/items.php')) ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kode / nama barang" maxlength="100">
    <button type="submit" class="btn btn-outline">Cari</button>
    <?php if (can('inventory.create')): ?><a class="btn btn-primary" href="<?= e(url('modules/inventory/items.php?new=1')) ?>">➕ Tambah Barang</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table>
    <tr><th>Kode</th><th>Barang</th><th>Kategori</th><th>Satuan</th><th>Min / Maks</th><th>Total stok</th><th>Obat (CPOE)</th><?= can('inventory.update') ? '<th>Aksi</th>' : '' ?></tr>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">Tidak ada barang.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><code class="k"><?= e($r['item_code']) ?></code></td>
        <td><?= e($r['item_name']) ?></td>
        <td><?= e((string) $r['category']) ?></td>
        <td><?= e((string) $r['unit']) ?></td>
        <td><?= e(inv_fmt((float) $r['min_stock'])) ?> / <?= e(inv_fmt((float) $r['max_stock'])) ?></td>
        <td><?= e(inv_fmt((float) $r['on_hand'])) ?></td>
        <td><?php if ($r['drug_id'] !== null): ?><?= e((string) $r['drug_name']) ?> <span class="muted"><?= e(trim((string) $r['dosage_form'] . ' ' . (string) $r['strength'])) ?></span> <span class="cp-badge <?= (int) $r['drug_active'] === 1 ? 'cp-ok' : 'cp-off' ?>"><?= (int) $r['drug_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span><?php else: ?>&mdash;<?php endif; ?></td>
        <?php if (can('inventory.update')): ?><td class="aksi"><a class="action-link" href="<?= e(url('modules/inventory/items.php?edit=' . (int) $r['inventory_item_id'])) ?>">Ubah</a></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
