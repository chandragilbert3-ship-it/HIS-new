<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory.php';

require_permission('inventory.view');

$warehouses = inv_warehouses_full();
$whId = (int) ($_GET['w'] ?? $_POST['warehouse_id'] ?? 0);
$byId = [];
foreach ($warehouses as $w) {
    $byId[(int) $w['warehouse_id']] = $w;
}
$whEditId = (int) ($_GET['edit_wh'] ?? 0);
$locEditId = (int) ($_GET['edit_loc'] ?? 0);
$errors = [];
$errorsLoc = [];
$oldWh  = ['warehouse_name' => '', 'location' => ''];
$oldLoc = ['bin_code' => '', 'location_name' => '', 'status' => 'active'];

if ($whEditId > 0 && isset($byId[$whEditId])) {
    $oldWh = ['warehouse_name' => (string) $byId[$whEditId]['warehouse_name'], 'location' => (string) $byId[$whEditId]['location']];
}
$locations = ($whId > 0 && isset($byId[$whId])) ? inv_locations($whId) : [];
if ($locEditId > 0) {
    foreach ($locations as $l) {
        if ((int) $l['location_id'] === $locEditId) {
            $oldLoc = ['bin_code' => (string) $l['bin_code'], 'location_name' => (string) $l['location_name'], 'status' => (string) $l['status']];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do = (string) ($_POST['do'] ?? '');
    if ($do === 'warehouse') {
        $id = (int) ($_POST['id'] ?? 0);
        require_permission($id > 0 ? 'inventory.update' : 'inventory.create');
        $oldWh = ['warehouse_name' => (string) ($_POST['warehouse_name'] ?? ''), 'location' => (string) ($_POST['location'] ?? '')];
        $whEditId = $id;
        [$d, $errors] = inv_validate_warehouse($_POST, $id > 0 ? $id : null);
        if ($id > 0 && !isset($byId[$id])) {
            $errors[] = 'Gudang tidak ditemukan.';
        }
        if (!$errors) {
            try {
                $newId = inv_save_warehouse($id > 0 ? $id : null, $d);
                audit_log(($id > 0 ? 'Ubah' : 'Tambah') . ' gudang ' . $d['name'], 'warehouses', $newId, null);
                flash_set('success', 'Gudang tersimpan.');
                redirect('modules/inventory/warehouses.php?w=' . $newId);
            } catch (PDOException $ex) {
                if (($ex->errorInfo[1] ?? 0) !== 1062) {
                    throw $ex;
                }
                $errors[] = 'Nama gudang sudah ada.';
            }
        }
    } elseif ($do === 'location') {
        $id = (int) ($_POST['id'] ?? 0);
        require_permission($id > 0 ? 'inventory.update' : 'inventory.create');
        $oldLoc = ['bin_code' => (string) ($_POST['bin_code'] ?? ''), 'location_name' => (string) ($_POST['location_name'] ?? ''), 'status' => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active'];
        $locEditId = $id;
        if (!isset($byId[$whId])) {
            $errorsLoc[] = 'Pilih gudang terlebih dahulu.';
        } else {
            [$d, $errorsLoc] = inv_validate_location($_POST, $whId, $id > 0 ? $id : null);
            if (!$errorsLoc) {
                inv_save_location($whId, $id > 0 ? $id : null, $d);
                audit_log(($id > 0 ? 'Ubah' : 'Tambah') . ' lokasi rak ' . $d['bin'] . ' gudang #' . $whId, 'warehouse_locations', null, null);
                flash_set('success', 'Lokasi rak tersimpan.');
                redirect('modules/inventory/warehouses.php?w=' . $whId);
            }
        }
    } else {
        flash_set('error', 'Aksi tidak dikenal.');
        redirect('modules/inventory/warehouses.php');
    }
}

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Gudang & Lokasi';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('warehouses');
?>
<div class="card">
  <h2>Gudang</h2>
  <div class="table-wrap"><table>
    <tr><th>Nama</th><th>Lokasi</th><th>Rak</th><th>Aksi</th></tr>
    <?php if (!$warehouses): ?><tr><td colspan="4" class="empty">Belum ada gudang.</td></tr><?php endif; ?>
    <?php foreach ($warehouses as $w): ?>
      <tr>
        <td><?= e($w['warehouse_name']) ?></td>
        <td><?= $w['location'] ? e((string) $w['location']) : '&mdash;' ?></td>
        <td><?= (int) $w['jml_lokasi'] ?></td>
        <td class="aksi">
          <a class="action-link" href="<?= e(url('modules/inventory/warehouses.php?w=' . (int) $w['warehouse_id'])) ?>">Rak</a>
          <?php if (can('inventory.update')): ?><a class="action-link" href="<?= e(url('modules/inventory/warehouses.php?edit_wh=' . (int) $w['warehouse_id'])) ?>">Ubah</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (can($whEditId > 0 ? 'inventory.update' : 'inventory.create')): ?>
  <form method="post" action="<?= e(url('modules/inventory/warehouses.php')) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="do" value="warehouse"><input type="hidden" name="id" value="<?= $whEditId ?>">
    <div class="form-row"><label for="warehouse_name"><?= $whEditId > 0 ? 'Ubah gudang' : 'Tambah gudang' ?> *</label><input type="text" id="warehouse_name" name="warehouse_name" maxlength="100" required value="<?= e($oldWh['warehouse_name']) ?>"></div>
    <div class="form-row"><label for="location">Lokasi</label><input type="text" id="location" name="location" maxlength="100" value="<?= e($oldWh['location']) ?>"></div>
    <div class="form-row full"><button type="submit" class="btn btn-primary">Simpan Gudang</button>
      <?php if ($whEditId > 0): ?><a class="btn btn-outline" href="<?= e(url('modules/inventory/warehouses.php')) ?>">Batal</a><?php endif; ?></div>
  </form>
  <?php endif; ?>
</div>

<?php if (isset($byId[$whId])): ?>
<div class="card">
  <h2>Rak &mdash; <?= e($byId[$whId]['warehouse_name']) ?></h2>
  <div class="table-wrap"><table>
    <tr><th>Kode rak</th><th>Nama lokasi</th><th>Status</th><?= can('inventory.update') ? '<th>Aksi</th>' : '' ?></tr>
    <?php if (!$locations): ?><tr><td colspan="4" class="empty">Belum ada rak.</td></tr><?php endif; ?>
    <?php foreach ($locations as $l): ?>
      <tr>
        <td><code class="k"><?= e($l['bin_code']) ?></code></td>
        <td><?= $l['location_name'] ? e((string) $l['location_name']) : '&mdash;' ?></td>
        <td><span class="cp-badge <?= $l['status'] === 'active' ? 'cp-ok' : 'cp-off' ?>"><?= $l['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?></span></td>
        <?php if (can('inventory.update')): ?><td class="aksi"><a class="action-link" href="<?= e(url('modules/inventory/warehouses.php?w=' . $whId . '&edit_loc=' . (int) $l['location_id'])) ?>">Ubah</a></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($errorsLoc): ?><div class="alert alert-error"><ul><?php foreach ($errorsLoc as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (can($locEditId > 0 ? 'inventory.update' : 'inventory.create')): ?>
  <form method="post" action="<?= e(url('modules/inventory/warehouses.php?w=' . $whId)) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="do" value="location"><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="id" value="<?= $locEditId ?>">
    <div class="form-row"><label for="bin_code"><?= $locEditId > 0 ? 'Ubah rak' : 'Tambah rak' ?> *</label><input type="text" id="bin_code" name="bin_code" maxlength="30" required value="<?= e($oldLoc['bin_code']) ?>" placeholder="A-01"></div>
    <div class="form-row"><label for="location_name">Nama lokasi</label><input type="text" id="location_name" name="location_name" maxlength="100" value="<?= e($oldLoc['location_name']) ?>"></div>
    <div class="form-row"><label for="status">Status</label>
      <select id="status" name="status"><option value="active"<?= $oldLoc['status'] === 'active' ? ' selected' : '' ?>>Aktif</option><option value="inactive"<?= $oldLoc['status'] === 'inactive' ? ' selected' : '' ?>>Nonaktif</option></select></div>
    <div class="form-row full"><button type="submit" class="btn btn-primary">Simpan Rak</button>
      <?php if ($locEditId > 0): ?><a class="btn btn-outline" href="<?= e(url('modules/inventory/warehouses.php?w=' . $whId)) ?>">Batal</a><?php endif; ?></div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
