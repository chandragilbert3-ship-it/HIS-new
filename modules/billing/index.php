<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/billing.php';

require_permission('billing.view');

$errors = [];
$oldVisit = 0;
$oldPayer = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('billing.create');
    $oldVisit = (int) ($_POST['visit_id'] ?? 0);
    $oldPayer = (int) ($_POST['payer_id'] ?? 0);
    [$err, $newId] = bl_create($oldVisit, $oldPayer);
    if ($err === null) {
        audit_log('Buat tagihan untuk kunjungan #' . $oldVisit, 'billing_invoices', $newId, null);
        flash_set('success', 'Tagihan draft dibuat. Tambahkan layanan.');
        redirect('modules/billing/invoice.php?id=' . $newId);
    }
    $errors[] = $err;
}

$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$rows   = bl_list($q, $status);
$inv    = bl_invoice_status_labels();
$bil    = bl_billing_status_labels();
$visits = can('billing.create') ? bl_eligible_visits() : [];
$payers = can('billing.create') ? bl_payers() : [];

$pageTitle    = 'Billing & Asuransi';
$activeModule = 'billing';
$breadcrumb   = 'Modul 13. Billing &rsaquo; Tagihan';

require APP_ROOT . '/includes/layout_header.php';
bl_tabs('index');
?>
<?php if (can('billing.create')): ?>
<div class="card">
  <h2>Buat Tagihan Baru</h2>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/billing/index.php')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <div class="form-row"><label for="visit_id">Kunjungan *</label>
      <select id="visit_id" name="visit_id" required>
        <option value="">-- pilih kunjungan --</option>
        <?php foreach ($visits as $v): ?>
          <option value="<?= (int) $v['visit_id'] ?>"<?= (int) $v['visit_id'] === $oldVisit ? ' selected' : '' ?>>#<?= (int) $v['visit_id'] ?> &middot; <?= e(date('d/m/Y', strtotime((string) $v['visit_date']))) ?> &middot; <?= e($v['no_rm']) ?> <?= e($v['nama_pasien']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-row"><label for="payer_id">Penjamin *</label>
      <select id="payer_id" name="payer_id" required>
        <?php foreach ($payers as $p): ?><option value="<?= (int) $p['payer_id'] ?>"<?= (int) $p['payer_id'] === $oldPayer ? ' selected' : '' ?>><?= e($p['payer_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row full"><button type="submit" class="btn btn-primary">Buat Tagihan</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Daftar Tagihan</h2>
  <form method="get" class="toolbar" action="<?= e(url('modules/billing/index.php')) ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="No. tagihan / No. RM / nama pasien" maxlength="100">
    <select name="status">
      <option value="">Semua status</option>
      <?php foreach ($inv as $k => [$lbl]): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Cari</button>
  </form>
  <div class="table-wrap"><table>
    <tr><th>No. Tagihan</th><th>Pasien</th><th>Penjamin</th><th>Total</th><th>Tanggungan pasien</th><th>Terbayar</th><th>Status</th><th>Pembayaran</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="9" class="empty">Belum ada tagihan.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r):
        [$il, $ic] = $inv[$r['invoice_status']] ?? [$r['invoice_status'], 'cp-info'];
        [$bl, $bc] = $bil[$r['billing_status']] ?? [$r['billing_status'], 'cp-info']; ?>
      <tr>
        <td><code class="k"><?= e($r['invoice_no']) ?></code></td>
        <td><?= e($r['nama_pasien']) ?> <span class="muted"><?= e($r['no_rm']) ?></span></td>
        <td><?= e($r['payer_name']) ?></td>
        <td><?= e(bl_rp((int) $r['gross_c'])) ?></td>
        <td><?= e(bl_rp((int) $r['patient_c'])) ?></td>
        <td><?= e(bl_rp((int) $r['paid_c'])) ?></td>
        <td><span class="cp-badge <?= e($ic) ?>"><?= e($il) ?></span></td>
        <td><?php if ($r['invoice_status'] === 'issued'): ?><span class="cp-badge <?= e($bc) ?>"><?= e($bl) ?></span><?php else: ?>&mdash;<?php endif; ?></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/billing/invoice.php?id=' . (int) $r['invoice_id'])) ?>">Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
