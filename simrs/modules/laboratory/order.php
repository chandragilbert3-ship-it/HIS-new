<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/laboratory.php';

require_permission('laboratory.view');

$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order   = $orderId > 0 ? lab_find_order($orderId) : null;
if ($order === null) {
    flash_set('error', 'Order lab tidak ditemukan.');
    redirect('modules/laboratory/index.php');
}
$visitId = (int) $order['visit_id'];
$userId  = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('laboratory.update');
    $do  = (string) ($_POST['do'] ?? '');
    $err = null;
    $ok  = null;
    $sid = (int) ($_POST['specimen_id'] ?? 0);
    $rid = (int) ($_POST['result_id'] ?? 0);

    if (in_array($do, ['receive', 'reject_specimen'], true) && lab_specimen_order($sid) !== $orderId) {
        $err = 'Spesimen tidak ditemukan pada order ini.';
    } elseif (in_array($do, ['validate', 'reject_result'], true) && lab_result_order($rid) !== $orderId) {
        $err = 'Hasil tidak ditemukan pada order ini.';
    } elseif ($do === 'receive') {
        $err = lab_receive($sid);
        $ok  = $err === null ? ['Terima spesimen #' . $sid, 'lab_specimens', $sid, 'Spesimen diterima laboratorium.'] : null;
    } elseif ($do === 'reject_specimen') {
        $reason = (string) ($_POST['reason'] ?? '');
        $err = lab_validate_reason($reason) ?? lab_reject_specimen($sid);
        $ok  = $err === null ? ['Tolak spesimen #' . $sid . ' - alasan: ' . trim($reason), 'lab_specimens', $sid, 'Spesimen ditolak. Pemeriksaan terkait perlu diambil ulang.'] : null;
    } elseif ($do === 'validate') {
        $err = lab_validate_result($rid, $userId);
        $ok  = $err === null ? ['Validasi hasil lab #' . $rid, 'lab_results', $rid, 'Hasil divalidasi.'] : null;
    } elseif ($do === 'validate_all') {
        $n = 0;
        foreach (lab_ready_result_ids($orderId) as $id) {
            if (lab_validate_result($id, $userId) === null) {
                $n++;
            }
        }
        if ($n === 0) {
            $err = 'Tidak ada hasil terisi yang menunggu validasi.';
        } else {
            $ok = ['Validasi ' . $n . ' hasil lab order #' . $orderId, 'lab_results', null, $n . ' hasil divalidasi.'];
        }
    } elseif ($do === 'reject_result') {
        $reason = (string) ($_POST['reason'] ?? '');
        $err = lab_validate_reason($reason) ?? lab_reject_result($rid);
        $ok  = $err === null ? ['Tolak hasil lab #' . $rid . ' - alasan: ' . trim($reason), 'lab_results', $rid, 'Hasil ditolak. Pemeriksaan perlu diambil ulang.'] : null;
    } else {
        $err = 'Aksi tidak dikenal.';
    }

    if ($err === null && $ok !== null) {
        audit_log($ok[0], $ok[1], $ok[2], $visitId);
        flash_set('success', $ok[3]);
    } elseif ($err !== null) {
        flash_set('error', $err);
    }
    redirect('modules/laboratory/order.php?order_id=' . $orderId);
}

audit_log('Buka order lab #' . $orderId, 'orders', $orderId, $visitId);

$active    = in_array($order['status'], ['ordered', 'in_progress'], true);
$specimens = lab_specimens($orderId);
$results   = lab_results($orderId);
$open      = lab_uncollected_items($orderId);
$enterable = $active ? lab_enterable_results($orderId) : [];
$ready     = $active ? lab_ready_result_ids($orderId) : [];
$alergi    = reg_allergies((int) $order['patient_id']);
$sstat     = lab_specimen_status_labels();
$rstat     = lab_result_status_labels();
$flags     = lab_flag_labels();

$pageTitle    = 'Order Laboratorium';
$activeModule = 'laboratory';
$breadcrumb   = 'Modul 10. Laboratorium &rsaquo; Order';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Order #<?= (int) $orderId ?> &mdash; <?= e($order['nama_pasien']) ?>
    <?= $order['priority'] === 'cito' ? '<span class="cp-badge cp-off">CITO</span>' : '' ?>
    <span class="cp-badge <?= $order['status'] === 'completed' ? 'cp-ok' : ($order['status'] === 'cancelled' ? 'cp-off' : 'cp-warn') ?>"><?= e($order['status']) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($order['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($order['tanggal_lahir'])) ?> th / <?= e($order['jenis_kelamin']) ?></div>
    <div><span class="muted">Dokter</span><br><?= e((string) $order['doctor_name']) ?></div>
    <div><span class="muted">Dipesan</span><br><?= e(date('d/m/Y H:i', strtotime($order['ordered_at']))) ?></div>
    <div><span class="muted">Alasan klinis</span><br><?= $order['clinical_reason'] ? e((string) $order['clinical_reason']) : '&mdash;' ?></div>
  </div>
  <?php if ($alergi): ?><div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div><?php endif; ?>
  <div class="toolbar">
    <a class="btn btn-outline" href="<?= e(url('modules/laboratory/index.php')) ?>">&larr; Antrean</a>
    <?php if ($active && $open && can('laboratory.create')): ?><a class="btn btn-primary" href="<?= e(url('modules/laboratory/collect.php?order_id=' . $orderId)) ?>">🩸 Ambil Spesimen</a><?php endif; ?>
    <?php if ($enterable && can('laboratory.update')): ?><a class="btn btn-primary" href="<?= e(url('modules/laboratory/results.php?order_id=' . $orderId)) ?>">⌨ Input Hasil</a><?php endif; ?>
    <?php if ($ready && can('laboratory.update')): ?>
      <form method="post" class="inline-form" action="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>" onsubmit="return confirm('Validasi semua hasil yang sudah terisi? Hasil tervalidasi tidak dapat diubah.');">
        <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="do" value="validate_all">
        <button type="submit" class="btn btn-primary">✔ Validasi Semua Terisi (<?= count($ready) ?>)</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($open): ?>
<div class="card">
  <h2>Pemeriksaan Belum Diambil Sampelnya</h2>
  <ul><?php foreach ($open as $it): ?><li><?= e($it['test_name']) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="card">
  <h2>Spesimen</h2>
  <div class="table-wrap"><table>
    <tr><th>Barcode</th><th>Jenis</th><th>Diterima</th><th>Pemeriksaan</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$specimens): ?><tr><td colspan="6" class="empty">Belum ada spesimen.</td></tr><?php endif; ?>
    <?php foreach ($specimens as $s): [$sl, $sc] = $sstat[$s['status']] ?? [$s['status'], 'cp-info']; ?>
      <tr>
        <td><code class="k"><?= e($s['barcode']) ?></code></td>
        <td><?= e((string) $s['specimen_type']) ?></td>
        <td><?= $s['received_at'] ? e(date('d/m H:i', strtotime($s['received_at']))) : '&mdash;' ?></td>
        <td><?= (int) $s['jml_hasil'] ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi">
          <?php if ($active && can('laboratory.update')): ?>
            <?php if ($s['status'] === 'collected'): ?>
              <form method="post" class="inline-form" action="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="do" value="receive"><input type="hidden" name="specimen_id" value="<?= (int) $s['specimen_id'] ?>"><button type="submit" class="btn btn-primary">📥 Terima</button></form>
            <?php endif; ?>
            <?php if (in_array($s['status'], ['collected', 'received'], true)): ?>
              <form method="post" class="inline-form" action="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>" onsubmit="return confirm('Tolak spesimen ini?');"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="do" value="reject_specimen"><input type="hidden" name="specimen_id" value="<?= (int) $s['specimen_id'] ?>">
                <input type="text" name="reason" maxlength="300" placeholder="Alasan penolakan" required style="width:160px"><button type="submit" class="btn btn-outline">✖ Tolak</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Hasil Pemeriksaan</h2>
  <div class="table-wrap"><table>
    <tr><th>Pemeriksaan</th><th>Spesimen</th><th>Hasil</th><th>Satuan</th><th>Nilai Rujukan</th><th>Flag</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$results): ?><tr><td colspan="8" class="empty">Belum ada hasil.</td></tr><?php endif; ?>
    <?php foreach ($results as $r): [$sl, $sc] = $rstat[$r['status']] ?? [$r['status'], 'cp-info']; ?>
      <tr>
        <td><?= e($r['test_name']) ?></td>
        <td><code class="k"><?= e($r['barcode']) ?></code></td>
        <td><b><?= $r['result_value'] !== null && $r['result_value'] !== '' ? e((string) $r['result_value']) : '&mdash;' ?></b></td>
        <td><?= e((string) $r['unit']) ?></td>
        <td><?= $r['reference_range'] ? e((string) $r['reference_range']) : '&mdash;' ?></td>
        <td><?php if ($r['result_flag']): [$fl, $fc] = $flags[$r['result_flag']] ?? [$r['result_flag'], 'cp-info']; ?><span class="cp-badge <?= e($fc) ?>"><?= e($fl) ?></span><?php else: ?>&mdash;<?php endif; ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span><?= $r['validator'] ? '<br><span class="muted">' . e((string) $r['validator']) . '</span>' : '' ?></td>
        <td class="aksi">
          <?php if ($active && $r['status'] === 'pending' && can('laboratory.update') && in_array($r['specimen_status'], ['received', 'processed'], true)): ?>
            <?php if ($r['result_value'] !== null && $r['result_value'] !== ''): ?>
              <form method="post" class="inline-form" action="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="do" value="validate"><input type="hidden" name="result_id" value="<?= (int) $r['lab_result_id'] ?>"><button type="submit" class="btn btn-primary">✔ Validasi</button></form>
            <?php endif; ?>
            <form method="post" class="inline-form" action="<?= e(url('modules/laboratory/order.php?order_id=' . $orderId)) ?>" onsubmit="return confirm('Tolak hasil ini? Sampel perlu diambil ulang.');"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="do" value="reject_result"><input type="hidden" name="result_id" value="<?= (int) $r['lab_result_id'] ?>">
              <input type="text" name="reason" maxlength="300" placeholder="Alasan" required style="width:120px"><button type="submit" class="btn btn-outline">✖ Tolak</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>