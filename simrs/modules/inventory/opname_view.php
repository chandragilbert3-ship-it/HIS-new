<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inventory_ops.php';

require_permission('inventory.view');

$id = (int) ($_GET['id'] ?? $_POST['opname_id'] ?? 0);
$op = $id > 0 ? invo_find($id) : null;
if ($op === null) {
    flash_set('error', 'Opname tidak ditemukan.');
    redirect('modules/inventory/opname.php');
}
$back = 'modules/inventory/opname_view.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('inventory.update');
    $do    = (string) ($_POST['do'] ?? '');
    $items = invo_items($id);
    $err   = null;
    $ok    = null;
    if ($do === 'save' || $do === 'complete') {
        [$counts, $errs] = invo_validate_counts($_POST, $items);
        if ($errs) {
            $err = implode(' ', $errs);
        } else {
            $err = invo_save_counts($id, $counts);
            if ($err === null && $do === 'save') {
                $ok = ['Simpan hitungan fisik opname OPN-' . $id, 'Hitungan fisik tersimpan.'];
            } elseif ($err === null) {
                [$err, $adj] = invo_complete($id, (int) current_user()['id']);
                $ok = $err === null ? ['Selesaikan stok opname OPN-' . $id . ' (' . $adj . ' barang disesuaikan)', 'Opname selesai. ' . $adj . ' barang disesuaikan.'] : null;
            }
        }
    } elseif ($do === 'cancel') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
            $err = 'Alasan pembatalan wajib diisi (5-300 karakter).';
        } else {
            $err = invo_cancel($id);
            $ok  = $err === null ? ['Batalkan stok opname OPN-' . $id . ' - alasan: ' . $reason, 'Opname dibatalkan.'] : null;
        }
    } else {
        $err = 'Aksi tidak dikenal.';
    }
    if ($err === null && $ok !== null) {
        audit_log($ok[0], 'stock_opnames', $id, null);
        flash_set('success', $ok[1]);
    } elseif ($err !== null) {
        flash_set('error', $err);
    }
    redirect($back);
}

audit_log('Buka stok opname OPN-' . $id, 'stock_opnames', $id, null);
$items = invo_items($id);
$stat  = invo_status_labels();
[$sl, $sc] = $stat[$op['status']] ?? [$op['status'], 'cp-info'];
$draft = $op['status'] === 'draft';
$edit  = $draft && can('inventory.update');

$pageTitle    = 'Gudang & Inventori';
$activeModule = 'inventory';
$breadcrumb   = 'Modul 14. Gudang &rsaquo; Stok Opname';

require APP_ROOT . '/includes/layout_header.php';
inv_tabs('opname');
?>
<div class="card">
  <h2>Stok Opname OPN-<?= $id ?> <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">Gudang</span><br><?= e($op['warehouse_name']) ?></div>
    <div><span class="muted">Tanggal</span><br><?= e(date('d/m/Y', strtotime((string) $op['opname_date']))) ?></div>
    <div><span class="muted">Petugas</span><br><?= e((string) $op['user_name']) ?></div>
  </div>
  <?php if ($edit): ?>
  <form method="post" action="<?= e(url($back)) ?>">
    <?= csrf_field() ?><input type="hidden" name="opname_id" value="<?= $id ?>">
  <?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Barang</th><th>Stok buku</th><th>Fisik</th><th>Selisih</th></tr>
    <?php foreach ($items as $it): $d = $it['diff_c']; ?>
      <tr>
        <td><?= e($it['item_name']) ?> <code class="k"><?= e($it['item_code']) ?></code></td>
        <td><?= e(inv_fmt($it['book_c'] / 100)) ?> <?= e((string) $it['unit']) ?></td>
        <td><?php if ($edit): ?><input type="text" inputmode="decimal" name="physical[<?= (int) $it['inventory_item_id'] ?>]" value="<?= e(inv_input($it['physical_c'])) ?>" style="width:90px"><?php else: ?><?= e(inv_fmt($it['physical_c'] / 100)) ?><?php endif; ?></td>
        <td><span class="cp-badge <?= $d === 0 ? 'cp-ok' : ($d < 0 ? 'cp-off' : 'cp-warn') ?>"><?= $d > 0 ? '+' : '' ?><?= e(inv_fmt($d / 100)) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if ($edit): ?>
    <button type="submit" name="do" value="save" class="btn btn-outline">Simpan Hitungan</button>
    <button type="submit" name="do" value="complete" class="btn btn-primary" onclick="return confirm('Selesaikan opname? Selisih akan diterapkan ke stok dan tidak dapat diubah.');">Selesaikan &amp; Terapkan Selisih</button>
  </form>
  <form method="post" class="toolbar" action="<?= e(url($back)) ?>" onsubmit="return confirm('Batalkan opname ini?');">
    <?= csrf_field() ?><input type="hidden" name="opname_id" value="<?= $id ?>"><input type="hidden" name="do" value="cancel">
    <input type="text" name="reason" maxlength="300" placeholder="Alasan pembatalan" required style="width:220px">
    <button type="submit" class="btn btn-outline">✖ Batalkan Draft</button>
  </form>
  <?php endif; ?>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/inventory/opname.php')) ?>">&larr; Daftar Opname</a></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
