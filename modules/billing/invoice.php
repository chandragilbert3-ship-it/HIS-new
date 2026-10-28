<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/billing.php';

require_permission('billing.view');

$id  = (int) ($_GET['id'] ?? $_POST['invoice_id'] ?? 0);
$inv = $id > 0 ? bl_find($id) : null;
if ($inv === null) {
    flash_set('error', 'Tagihan tidak ditemukan.');
    redirect('modules/billing/index.php');
}
$back = 'modules/billing/invoice.php?id=' . $id;
$uid  = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do  = (string) ($_POST['do'] ?? '');
    $err = null;
    $ok  = null;   // [teks audit, teks flash]
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $reasonOk = mb_strlen($reason) >= 5 && mb_strlen($reason) <= 300;

    switch ($do) {
        case 'add_item':
            require_permission('billing.update');
            $qty = bl_parse((string) ($_POST['qty'] ?? ''));
            $err = $qty === null ? 'Jumlah tidak valid.' : bl_add_item($id, (int) ($_POST['tariff_id'] ?? 0), $qty);
            $ok  = $err === null ? ['Tambah layanan tarif #' . (int) $_POST['tariff_id'] . ' ke tagihan ' . $inv['invoice_no'], 'Layanan ditambahkan.'] : null;
            break;
        case 'remove_item':
            require_permission('billing.update');
            $err = bl_remove_item($id, (int) ($_POST['item_id'] ?? 0));
            $ok  = $err === null ? ['Hapus baris draft #' . (int) $_POST['item_id'] . ' dari tagihan ' . $inv['invoice_no'], 'Baris dihapus.'] : null;
            break;
        case 'issue':
            require_permission('billing.update');
            $err = bl_issue($id, (int) ($_POST['coverage'] ?? 0));
            $ok  = $err === null ? ['Terbitkan tagihan ' . $inv['invoice_no'], 'Tagihan diterbitkan.'] : null;
            break;
        case 'pay':
            require_permission('billing.create');
            $amt = bl_parse((string) ($_POST['amount'] ?? ''));
            if ($amt === null) {
                $err = 'Jumlah pembayaran tidak valid.';
            } else {
                [$err, $pid] = bl_pay($id, (string) ($_POST['method'] ?? ''), $amt, $uid);
                $ok = $err === null ? ['Catat pembayaran ' . bl_rp($amt) . ' tagihan ' . $inv['invoice_no'], 'Pembayaran dicatat.'] : null;
            }
            break;
        case 'void':
            require_permission('billing.approve');
            if (!$reasonOk) {
                $err = 'Alasan pembatalan wajib diisi (5-300 karakter).';
            } else {
                $err = bl_void_payment($id, (int) ($_POST['payment_id'] ?? 0));
                $ok  = $err === null ? ['Void pembayaran #' . (int) $_POST['payment_id'] . ' - alasan: ' . $reason, 'Pembayaran dibatalkan.'] : null;
            }
            break;
        case 'cancel':
            require_permission($inv['invoice_status'] === 'issued' ? 'billing.approve' : 'billing.update');
            if (!$reasonOk) {
                $err = 'Alasan pembatalan wajib diisi (5-300 karakter).';
            } else {
                $err = bl_cancel($id);
                $ok  = $err === null ? ['Batalkan tagihan ' . $inv['invoice_no'] . ' - alasan: ' . $reason, 'Tagihan dibatalkan.'] : null;
            }
            break;
        default:
            $err = 'Aksi tidak dikenal.';
    }
    if ($err === null && $ok !== null) {
        audit_log($ok[0], 'billing_invoices', $id, null);
        flash_set('success', $ok[1]);
    } elseif ($err !== null) {
        flash_set('error', $err);
    }
    redirect($back);
}

audit_log('Buka tagihan ' . $inv['invoice_no'], 'billing_invoices', $id, null);
$items    = bl_items($id);
$payments = bl_payments($id);
$claim    = bl_claim_of($id);
$paidC    = bl_paid_c($id);
$remainC  = max(0, (int) $inv['patient_c'] - $paidC);
$stI = bl_invoice_status_labels();
$stB = bl_billing_status_labels();
$stC = bl_claim_status_labels();
[$il, $ic] = $stI[$inv['invoice_status']] ?? [$inv['invoice_status'], 'cp-info'];
[$bl, $bc] = $stB[$inv['billing_status']] ?? [$inv['billing_status'], 'cp-info'];
$draft  = $inv['invoice_status'] === 'draft';
$issued = $inv['invoice_status'] === 'issued';
$tariffs = ($draft && can('billing.update')) ? bl_tariffs() : [];

$pageTitle    = 'Billing & Asuransi';
$activeModule = 'billing';
$breadcrumb   = 'Modul 13. Billing &rsaquo; Tagihan';

require APP_ROOT . '/includes/layout_header.php';
bl_tabs('index');
?>
<div class="card">
  <h2><?= e($inv['invoice_no']) ?> <span class="cp-badge <?= e($ic) ?>"><?= e($il) ?></span>
    <?php if ($issued): ?><span class="cp-badge <?= e($bc) ?>"><?= e($bl) ?></span><?php endif; ?></h2>
  <div class="cp-info-box">
    <div><span class="muted">Pasien</span><br><?= e($inv['nama_pasien']) ?> (<?= e($inv['no_rm']) ?>)</div>
    <div><span class="muted">Kunjungan</span><br>#<?= (int) $inv['visit_id'] ?> &middot; <?= e(date('d/m/Y', strtotime((string) $inv['visit_date']))) ?></div>
    <div><span class="muted">Penjamin</span><br><?= e($inv['payer_name']) ?></div>
  </div>

  <div class="table-wrap"><table>
    <tr><th>Kode</th><th>Layanan</th><th>Tarif</th><th>Jumlah</th><th>Subtotal</th><?= ($draft && can('billing.update')) ? '<th>Aksi</th>' : '' ?></tr>
    <?php if (!$items): ?><tr><td colspan="6" class="empty">Belum ada layanan.</td></tr><?php endif; ?>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><code class="k"><?= e($it['service_code']) ?></code></td>
        <td><?= e($it['service_name']) ?></td>
        <td><?= e(bl_rp((int) $it['unit_c'])) ?></td>
        <td><?= e(bl_qty((int) $it['qty_c'])) ?></td>
        <td><?= e(bl_rp((int) $it['sub_c'])) ?></td>
        <?php if ($draft && can('billing.update')): ?>
        <td class="aksi">
          <form method="post" action="<?= e(url($back)) ?>" style="display:inline" onsubmit="return confirm('Hapus baris ini dari draft?');">
            <?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="do" value="remove_item"><input type="hidden" name="item_id" value="<?= (int) $it['invoice_item_id'] ?>">
            <button type="submit" class="btn btn-outline">Hapus</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <tr><th colspan="4" style="text-align:right">Total</th><th><?= e(bl_rp((int) $inv['gross_c'])) ?></th><?= ($draft && can('billing.update')) ? '<th></th>' : '' ?></tr>
  </table></div>

  <?php if ($draft && can('billing.update')): ?>
  <form method="post" action="<?= e(url($back)) ?>" class="toolbar">
    <?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="do" value="add_item">
    <select name="tariff_id" required>
      <?php foreach ($tariffs as $t): ?><option value="<?= (int) $t['tariff_id'] ?>"><?= e($t['service_code']) ?> &middot; <?= e($t['service_name']) ?> (<?= e(bl_rp((int) $t['amount_c'])) ?>)</option><?php endforeach; ?>
    </select>
    <input type="text" inputmode="decimal" name="qty" value="1" style="width:70px" required>
    <button type="submit" class="btn btn-primary">➕ Tambah Layanan</button>
  </form>
  <form method="post" action="<?= e(url($back)) ?>" class="toolbar" onsubmit="return confirm('Terbitkan tagihan? Total tidak dapat diubah lagi.');">
    <?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="do" value="issue">
    <?php if ($inv['payer_type'] === 'self'): ?>
      <span class="muted">Penjamin pribadi: seluruh tagihan menjadi tanggungan pasien.</span><input type="hidden" name="coverage" value="0">
    <?php else: ?>
      <label>Ditanggung penjamin (%) <input type="text" inputmode="numeric" name="coverage" value="100" style="width:60px" required></label>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Terbitkan Tagihan</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($issued): ?>
<div class="card">
  <h2>Pembayaran</h2>
  <div class="cp-info-box">
    <div><span class="muted">Ditanggung penjamin</span><br><?= e(bl_rp((int) $inv['payer_c'])) ?></div>
    <div><span class="muted">Tanggungan pasien</span><br><?= e(bl_rp((int) $inv['patient_c'])) ?></div>
    <div><span class="muted">Sudah dibayar</span><br><?= e(bl_rp($paidC)) ?></div>
    <div><span class="muted">Sisa pasien</span><br><b><?= e(bl_rp($remainC)) ?></b></div>
  </div>
  <div class="table-wrap"><table>
    <tr><th>No. Transaksi</th><th>Metode</th><th>Jumlah</th><th>Penerima</th><th>Status</th><?= can('billing.approve') ? '<th>Aksi</th>' : '' ?></tr>
    <?php if (!$payments): ?><tr><td colspan="6" class="empty">Belum ada pembayaran.</td></tr><?php endif; ?>
    <?php foreach ($payments as $pm): ?>
      <tr>
        <td><code class="k"><?= e($pm['transaction_no']) ?></code></td>
        <td><?= e($pm['payment_method']) ?></td>
        <td><?= e(bl_rp((int) $pm['amount_c'])) ?></td>
        <td><?= e((string) $pm['user_name']) ?></td>
        <td><span class="cp-badge <?= $pm['payment_status'] === 'paid' ? 'cp-ok' : 'cp-off' ?>"><?= $pm['payment_status'] === 'paid' ? 'Dibayar' : 'Void' ?></span></td>
        <?php if (can('billing.approve')): ?>
        <td class="aksi"><?php if ($pm['payment_status'] === 'paid'): ?>
          <form method="post" action="<?= e(url($back)) ?>" class="toolbar" onsubmit="return confirm('Void pembayaran ini?');">
            <?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="do" value="void"><input type="hidden" name="payment_id" value="<?= (int) $pm['payment_id'] ?>">
            <input type="text" name="reason" maxlength="300" placeholder="Alasan" required style="width:140px">
            <button type="submit" class="btn btn-outline">Void</button>
          </form><?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php if (can('billing.create') && $remainC > 0): ?>
  <form method="post" action="<?= e(url($back)) ?>" class="toolbar">
    <?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="do" value="pay">
    <select name="method" required><?php foreach (BL_METHODS as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select>
    <input type="text" inputmode="decimal" name="amount" value="<?= e(bl_input($remainC)) ?>" style="width:130px" required>
    <button type="submit" class="btn btn-primary">💵 Catat Pembayaran</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($claim): [$cl, $cc] = $stC[$claim['claim_status']] ?? [$claim['claim_status'], 'cp-info']; ?>
<div class="card">
  <h2>Klaim Penjamin</h2>
  <div class="cp-info-box">
    <div><span class="muted">No. Klaim</span><br><?= e($claim['claim_no']) ?></div>
    <div><span class="muted">Jumlah klaim</span><br><?= e(bl_rp((int) $claim['amount_c'])) ?></div>
    <div><span class="muted">Status</span><br><span class="cp-badge <?= e($cc) ?>"><?= e($cl) ?></span></div>
  </div>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/billing/claims.php')) ?>">Kelola di Klaim Asuransi &rarr;</a></div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (($draft && can('billing.update')) || ($issued && can('billing.approve'))): ?>
<div class="card">
  <form method="post" class="toolbar" action="<?= e(url($back)) ?>" onsubmit="return confirm('Batalkan tagihan ini?');">
    <?= csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $id ?>"><input type="hidden" name="do" value="cancel">
    <input type="text" name="reason" maxlength="300" placeholder="Alasan pembatalan tagihan" required style="width:260px">
    <button type="submit" class="btn btn-outline">✖ Batalkan Tagihan</button>
  </form>
</div>
<?php endif; ?>
<div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/billing/index.php')) ?>">&larr; Daftar Tagihan</a></div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
