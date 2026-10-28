<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.view');

$editId  = (int) ($_GET['edit'] ?? $_POST['supplier_id'] ?? 0);
$isNew   = isset($_GET['new']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && $editId === 0);
$editing = $editId > 0 ? inv_find_supplier($editId) : null;
if ($editId > 0 && $editing === null) {
    flash_set('error', 'Pemasok tidak ditemukan.');
    redirect('modules/inventory/suppliers.php');
}
$showForm = ($isNew && can('inventory.create')) || ($editing !== null && can('inventory.update'));
$errors = [];
$old = [
    'supplier_name' => (string) ($editing['supplier_name'] ?? ''), 'contact' => (string) ($editing['contact'] ?? ''),
    'is_active' => $editing ? ((int) $editing['is_active'] === 1 ? '1' : '') : '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission($editId > 0 ? 'inventory.update' : 'inventory.create');
    $old = [
        'supplier_name' => (string) ($_POST['supplier_name'] ?? ''), 'contact' => (string) ($_POST['contact'] ?? ''),
        'is_active' => ($_POST['is_active'] ?? '') === '1' ? '1' : '',
    ];
    [$data, $errors] = inv_validate_supplier($_POST, $editId > 0 ? $editId : null);
    if (!$errors) {
        try {
            $sid = inv_save_supplier($editId > 0 ? $editId : null, $data);
            audit_log(($editId > 0 ? 'Ubah' : 'Tambah') . ' pemasok ' . $data['name'], 'suppliers', $sid, null);
            flash_set('success', 'Pemasok tersimpan.');
            redirect('modules/inventory/suppliers.php');
        } catch (PDOException $ex) {
            if (($ex->errorInfo[1] ?? 0) !== 1062) {
                throw $ex;
            }
            $errors[] = 'Nama pemasok sudah ada.';
        }
    }
    $showForm = true;
}

$rows = inv_suppliers();

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Pemasok';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('suppliers');
?>
<?php if ($showForm): ?>
<div class="card">
  <h2><?= $editId > 0 ? 'Ubah Pemasok' : 'Tambah Pemasok' ?></h2>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/inventory/suppliers.php' . ($editId > 0 ? '?edit=' . $editId : ''))) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $editId ?>">
    <div class="form-row"><label for="supplier_name">Nama pemasok *</label><input type="text" id="supplier_name" name="supplier_name" maxlength="150" required value="<?= e($old['supplier_name']) ?>"></div>
    <div class="form-row"><label for="contact">Kontak</label><input type="text" id="contact" name="contact" maxlength="100" value="<?= e($old['contact']) ?>"></div>
    <div class="form-row"><label><input type="checkbox" name="is_active" value="1"<?= $old['is_active'] === '1' ? ' checked' : '' ?>> Aktif</label></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan</button>
      <a class="btn btn-outline" href="<?= e(url('modules/inventory/suppliers.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Pemasok</h2>
  <?php if (can('inventory.create')): ?><div class="toolbar"><a class="btn btn-primary" href="<?= e(url('modules/inventory/suppliers.php?new=1')) ?>">➕ Tambah Pemasok</a></div><?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Nama</th><th>Kontak</th><th>Penerimaan</th><th>Status</th><?= can('inventory.update') ? '<th>Aksi</th>' : '' ?></tr>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty">Belum ada pemasok.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['supplier_name']) ?></td>
        <td><?= $r['contact'] ? e((string) $r['contact']) : '&mdash;' ?></td>
        <td><?= (int) $r['jml_terima'] ?></td>
        <td><span class="cp-badge <?= (int) $r['is_active'] === 1 ? 'cp-ok' : 'cp-off' ?>"><?= (int) $r['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
        <?php if (can('inventory.update')): ?><td class="aksi"><a class="action-link" href="<?= e(url('modules/inventory/suppliers.php?edit=' . (int) $r['supplier_id'])) ?>">Ubah</a></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>