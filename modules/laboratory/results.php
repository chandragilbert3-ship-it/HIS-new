<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/laboratory.php';

require_permission('laboratory.update');

$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order   = $orderId > 0 ? lab_find_order($orderId) : null;
if ($order === null || !in_array($order['status'], ['ordered', 'in_progress'], true)) {
    flash_set('error', 'Order lab tidak ditemukan atau sudah selesai/dibatalkan.');
    redirect('modules/laboratory/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $values = is_array($_POST['value'] ?? null) ? $_POST['value'] : [];
    $flags  = is_array($_POST['flag'] ?? null) ? $_POST['flag'] : [];
    [$saved, $errors] = lab_save_results($orderId, $values, $flags);
    if (!$errors) {
        audit_log('Input hasil lab order #' . $orderId . ' (' . $saved . ' hasil)', 'lab_results', null, (int) $order['visit_id']);
        flash_set('success', $saved . ' hasil disimpan. Hasil belum final sampai divalidasi.');
        redirect('modules/laboratory/order.php?order_id=' . $orderId);
    }
}

$rows = lab_enterable_results($orderId);

$pageTitle    = 'Input Hasil Lab';
$activeModule = 'laboratory';
$breadcrumb   = 'Modul 10. Laboratorium &rsaquo; Input Hasil';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Input Hasil &mdash; Order #<?= (int) $orderId ?> &middot; <?= e($order['nama_pasien']) ?> (<?= e($order['no_rm']) ?>)</h2>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$rows): ?>
    <div class="muted">Tidak ada hasil yang menunggu diisi. Pastikan spesimen sudah diterima laboratorium.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('modules/laboratory/results.php?order_id=' . $orderId)) ?>">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>">
    <div class="table-wrap"><table>
      <tr><th>Spesimen</th><th>Pemeriksaan</th><th>Hasil</th><th>Satuan</th><th>Nilai Rujukan</th><th>Flag</th></tr>
      <?php foreach ($rows as $r): $id = (int) $r['lab_result_id']; $pv = is_array($_POST['value'] ?? null) ? ($_POST['value'][$id] ?? null) : null; ?>
        <tr>
          <td><code class="k"><?= e($r['barcode']) ?></code><br><span class="muted"><?= e((string) $r['specimen_type']) ?></span></td>
          <td><?= e($r['test_name']) ?></td>
          <td><input type="text" name="value[<?= $id ?>]" maxlength="100" style="width:140px" value="<?= e(is_string($pv) ? $pv : (string) $r['result_value']) ?>"></td>
          <td><?= e((string) $r['unit']) ?></td>
          <td><?= $r['reference_range'] ? e((string) $r['reference_range']) : '&mdash;' ?></td>
          <td><select name="flag[<?= $id ?>]">
            <option value="auto">otomatis</option><option value="">tanpa penilaian</option>
            <?php foreach (lab_flag_labels() as $c => [$l]): ?><option value="<?= e($c) ?>"><?= e($l) ?></option><?php endforeach; ?>
          </select></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p><button type="submit" class="btn btn-primary">Simpan Hasil</button>
       <a class="btn btn-outline" href="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>">Batal</a></p>
  </form>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>