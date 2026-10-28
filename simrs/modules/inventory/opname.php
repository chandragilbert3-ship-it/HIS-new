<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory_ops.php';

require_permission('inventory.view');

$warehouses = inv_warehouses();
$errors = [];
$oldDate = date('Y-m-d');
$oldWh = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('inventory.create');
    $oldWh   = (int) ($_POST['warehouse_id'] ?? 0);
    $oldDate = (string) ($_POST['opname_date'] ?? '');
    [$err, $oid] = invo_create($oldWh, $oldDate, (int) current_user()['id']);
    if ($err === null) {
        audit_log('Buat stok opname OPN-' . $oid . ' gudang #' . $oldWh, 'stock_opnames', $oid, null);
        flash_set('success', 'Draft opname dibuat. Isi jumlah fisik hasil hitung.');
        redirect('modules/inventory/opname_view.php?id=' . $oid);
    }
    $errors[] = $err;
}

$rows = invo_list();
$stat = invo_status_labels();

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Stok Opname';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('opname');
?>
<?php if (can('inventory.create')): ?>
<div class="card">
  <h2>Mulai Stok Opname</h2>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/inventory/opname.php')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <div class="form-row"><label for="warehouse_id">Gudang *</label>
      <select id="warehouse_id" name="warehouse_id" required>
        <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['warehouse_id'] ?>"<?= (int) $w['warehouse_id'] === $oldWh ? ' selected' : '' ?>><?= e($w['warehouse_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row"><label for="opname_date">Tanggal opname *</label>
      <input type="date" id="opname_date" name="opname_date" required max="<?= e(date('Y-m-d')) ?>" value="<?= e($oldDate) ?>"></div>
    <div class="form-row full"><button type="submit" class="btn btn-primary">Buat Draft Opname</button></div>
  </form>
</div>
<?php endif; ?>
<div class="card">
  <h2>Riwayat Stok Opname</h2>
  <div class="table-wrap"><table>
    <tr><th>No.</th><th>Tanggal</th><th>Gudang</th><th>Petugas</th><th>Selisih (barang)</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada opname.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$sl, $sc] = $stat[$r['status']] ?? [$r['status'], 'cp-info']; ?>
      <tr>
        <td>OPN-<?= (int) $r['opname_id'] ?></td>
        <td><?= e(date('d/m/Y', strtotime((string) $r['opname_date']))) ?></td>
        <td><?= e($r['warehouse_name']) ?></td>
        <td><?= e((string) $r['user_name']) ?></td>
        <td><?= (int) $r['jml_selisih'] ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/inventory/opname_view.php?id=' . (int) $r['opname_id'])) ?>">Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
