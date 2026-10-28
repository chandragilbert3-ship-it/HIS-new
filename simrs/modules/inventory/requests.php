<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory_ops.php';

require_permission('inventory.view');

$id  = (int) ($_GET['id'] ?? $_POST['request_id'] ?? 0);
$req = $id > 0 ? invr_find($id) : null;
if ($id > 0 && $req === null) {
    flash_set('error', 'Permintaan tidak ditemukan.');
    redirect('modules/inventory/requests.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $req !== null) {
    csrf_require();
    require_permission('inventory.update');
    $do  = (string) ($_POST['do'] ?? '');
    $err = null;
    $msg = null;
    if ($do === 'process') {
        $err = invr_set_status($id, 'process');
        $msg = ['Proses permintaan stok REQ-' . $id, 'Permintaan diproses.'];
    } elseif ($do === 'issue') {
        $err = invr_issue($id, (int) current_user()['id']);
        $msg = ['Keluarkan stok untuk permintaan REQ-' . $id . ' (unit ' . $req['requesting_unit'] . ')', 'Stok dikeluarkan. Permintaan selesai.'];
    } elseif ($do === 'cancel') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
            $err = 'Alasan pembatalan wajib diisi (5-300 karakter).';
        } else {
            $err = invr_set_status($id, 'cancel');
            $msg = ['Batalkan permintaan stok REQ-' . $id . ' - alasan: ' . $reason, 'Permintaan dibatalkan.'];
        }
    } else {
        $err = 'Aksi tidak dikenal.';
    }
    if ($err === null && $msg !== null) {
        audit_log($msg[0], 'inventory_requests', $id, null);
        flash_set('success', $msg[1]);
    } else {
        flash_set('error', (string) $err);
    }
    redirect('modules/inventory/requests.php?id=' . $id);
}

$stat   = invr_status_labels();
$filter = (string) ($_GET['s'] ?? '');
if ($req !== null) {
    audit_log('Buka permintaan stok REQ-' . $id, 'inventory_requests', $id, null);
    $items = invr_items($id, (int) $req['warehouse_id']);
} else {
    $rows = invr_list($filter);
}

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Permintaan Unit';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('requests');
?>
<?php if ($req !== null): [$sl, $sc] = $stat[$req['status']] ?? [$req['status'], 'cp-info']; $open = in_array($req['status'], ['pending', 'processed'], true); ?>
<div class="card">
  <h2>Permintaan REQ-<?= $id ?> <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">Unit peminta</span><br><?= e($req['requesting_unit']) ?></div>
    <div><span class="muted">Gudang sumber</span><br><?= e($req['warehouse_name']) ?></div>
    <div><span class="muted">Pemohon</span><br><?= e((string) $req['user_name']) ?></div>
  </div>
  <div class="table-wrap"><table>
    <tr><th>Barang</th><th>Diminta</th><th>Stok tersedia</th></tr>
    <?php foreach ($items as $it): $low = $it['qty_c'] > $it['available_c']; ?>
      <tr>
        <td><?= e($it['item_name']) ?> <code class="k"><?= e($it['item_code']) ?></code></td>
        <td><?= e(inv_fmt($it['qty_c'] / 100)) ?> <?= e((string) $it['unit']) ?></td>
        <td><span class="cp-badge <?= $low ? 'cp-off' : 'cp-ok' ?>"><?= e(inv_fmt($it['available_c'] / 100)) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($open && can('inventory.update')): ?>
    <div class="toolbar">
      <?php if ($req['status'] === 'pending'): ?>
        <form method="post" class="inline-form" action="<?= e(url('modules/inventory/requests.php?id=' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $id ?>"><input type="hidden" name="do" value="process"><button type="submit" class="btn btn-primary">▶ Proses Permintaan</button></form>
      <?php else: ?>
        <form method="post" class="inline-form" action="<?= e(url('modules/inventory/requests.php?id=' . $id)) ?>" onsubmit="return confirm('Keluarkan stok sesuai permintaan? Stok gudang akan berkurang.');"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $id ?>"><input type="hidden" name="do" value="issue"><button type="submit" class="btn btn-primary">📦 Keluarkan Stok &amp; Selesaikan</button></form>
      <?php endif; ?>
      <form method="post" class="inline-form" action="<?= e(url('modules/inventory/requests.php?id=' . $id)) ?>" onsubmit="return confirm('Batalkan permintaan ini?');"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $id ?>"><input type="hidden" name="do" value="cancel">
        <input type="text" name="reason" maxlength="300" placeholder="Alasan pembatalan" required style="width:200px"><button type="submit" class="btn btn-outline">✖ Batalkan</button></form>
    </div>
  <?php endif; ?>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/inventory/requests.php')) ?>">&larr; Daftar Permintaan</a></div>
</div>
<?php else: ?>
<div class="card">
  <h2>Permintaan Stok Unit</h2>
  <form method="get" class="toolbar" action="<?= e(url('modules/inventory/requests.php')) ?>">
    <select name="s" onchange="this.form.submit()">
      <option value="">Semua status</option>
      <?php foreach ($stat as $k => [$lbl, ]): ?><option value="<?= e($k) ?>"<?= $k === $filter ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
    <?php if (can('inventory.create')): ?><a class="btn btn-primary" href="<?= e(url('modules/inventory/request_form.php')) ?>">➕ Buat Permintaan</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table>
    <tr><th>No.</th><th>Unit peminta</th><th>Gudang</th><th>Item</th><th>Pemohon</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada permintaan.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$sl, $sc] = $stat[$r['status']] ?? [$r['status'], 'cp-info']; ?>
      <tr>
        <td>REQ-<?= (int) $r['request_id'] ?></td>
        <td><?= e($r['requesting_unit']) ?></td>
        <td><?= e($r['warehouse_name']) ?></td>
        <td><?= (int) $r['jml_item'] ?></td>
        <td><?= e((string) $r['user_name']) ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/inventory/requests.php?id=' . (int) $r['request_id'])) ?>">Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
