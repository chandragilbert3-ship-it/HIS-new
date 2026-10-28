<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/laboratory.php';

require_permission('laboratory.create');

$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order   = $orderId > 0 ? lab_find_order($orderId) : null;
if ($order === null || !in_array($order['status'], ['ordered', 'in_progress'], true)) {
    flash_set('error', 'Order lab tidak ditemukan atau sudah selesai/dibatalkan.');
    redirect('modules/laboratory/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $types = is_array($_POST['type'] ?? null) ? $_POST['type'] : [];
    try {
        $n = lab_collect($orderId, $types);
        audit_log('Ambil spesimen order lab #' . $orderId . ' (' . $n . ' spesimen)', 'lab_specimens', null, (int) $order['visit_id']);
        flash_set('success', $n . ' spesimen dicatat. Cetak/tempel barcode pada tabung, lalu terima di laboratorium.');
        redirect('modules/laboratory/order.php?order_id=' . $orderId);
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    } catch (PDOException $ex) {
        error_log('Ambil spesimen gagal: ' . $ex->getMessage());
        $errors[] = 'Gagal menyimpan karena kesalahan database.';
    }
}

$items  = lab_uncollected_items($orderId);
$alergi = reg_allergies((int) $order['patient_id']);
$types  = is_array($_POST['type'] ?? null) ? $_POST['type'] : [];

$pageTitle    = 'Ambil Spesimen';
$activeModule = 'laboratory';
$breadcrumb   = 'Modul 10. Laboratorium &rsaquo; Ambil Spesimen';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Ambil Spesimen &mdash; Order #<?= (int) $orderId ?> &middot; <?= e($order['nama_pasien']) ?> (<?= e($order['no_rm']) ?>)</h2>
  <?php if ($alergi): ?><div class="alert alert-warning">⚠ Alergi: <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$items): ?>
    <div class="muted">Semua pemeriksaan pada order ini sudah diambil sampelnya.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('modules/laboratory/collect.php?order_id=' . $orderId)) ?>">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>">
    <div class="table-wrap"><table>
      <tr><th>Pemeriksaan</th><th>Catatan Dokter</th><th>Jenis Spesimen</th></tr>
      <?php foreach ($items as $it): $id = (int) $it['order_item_id']; ?>
        <tr>
          <td><?= e($it['test_name']) ?></td>
          <td><?= $it['instruction'] ? e((string) $it['instruction']) : '&mdash;' ?></td>
          <td><select name="type[<?= $id ?>]"><option value="">-- lewati --</option>
            <?php foreach (lab_specimen_types() as $t): ?><option value="<?= e($t) ?>" <?= (($types[$id] ?? '') === $t) ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
          </select></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p><button type="submit" class="btn btn-primary">Simpan Pengambilan Spesimen</button>
       <a class="btn btn-outline" href="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>">Batal</a></p>
  </form>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>