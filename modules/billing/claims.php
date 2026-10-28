<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/billing.php';

require_permission('billing.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $claimId = (int) ($_POST['claim_id'] ?? 0);
    $to      = (string) ($_POST['to'] ?? '');
    // Mengajukan klaim = update; verifikasi/bayar/tolak = approve
    require_permission($to === 'submitted' ? 'billing.update' : 'billing.approve');
    $err = bl_claim_move($claimId, $to);
    if ($err === null) {
        audit_log('Ubah status klaim #' . $claimId . ' menjadi ' . $to, 'claims', $claimId, null);
        flash_set('success', 'Status klaim diperbarui.');
    } else {
        flash_set('error', $err);
    }
    redirect('modules/billing/claims.php' . (isset($_GET['status']) ? '?status=' . urlencode((string) $_GET['status']) : ''));
}

$status = (string) ($_GET['status'] ?? '');
$rows   = bl_claims_list($status);
$stC    = bl_claim_status_labels();
$next   = [
    'draft'     => [['submitted', 'Ajukan', 'billing.update']],
    'submitted' => [['verified', 'Verifikasi', 'billing.approve'], ['rejected', 'Tolak', 'billing.approve']],
    'verified'  => [['paid', 'Tandai Dibayar', 'billing.approve'], ['rejected', 'Tolak', 'billing.approve']],
];

$pageTitle    = 'Billing & Asuransi';
$activeModule = 'billing';
$breadcrumb   = 'Modul 13. Billing &rsaquo; Klaim Asuransi';

require APP_ROOT . '/includes/layout_header.php';
bl_tabs('claims');
?>
<div class="card">
  <h2>Klaim Asuransi</h2>
  <form method="get" class="toolbar" action="<?= e(url('modules/billing/claims.php')) ?>">
    <select name="status">
      <option value="">Semua status</option>
      <?php foreach ($stC as $k => [$lbl]): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Tampilkan</button>
  </form>
  <div class="table-wrap"><table>
    <tr><th>No. Klaim</th><th>Tagihan</th><th>Pasien</th><th>Penjamin</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada klaim.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$cl, $cc] = $stC[$r['claim_status']] ?? [$r['claim_status'], 'cp-info']; ?>
      <tr>
        <td><code class="k"><?= e($r['claim_no']) ?></code></td>
        <td><a class="action-link" href="<?= e(url('modules/billing/invoice.php?id=' . (int) $r['invoice_id'])) ?>"><?= e($r['invoice_no']) ?></a></td>
        <td><?= e($r['nama_pasien']) ?> <span class="muted"><?= e($r['no_rm']) ?></span></td>
        <td><?= e($r['payer_name']) ?></td>
        <td><?= e(bl_rp((int) $r['amount_c'])) ?></td>
        <td><span class="cp-badge <?= e($cc) ?>"><?= e($cl) ?></span></td>
        <td class="aksi">
          <?php foreach ($next[$r['claim_status']] ?? [] as [$to, $label, $perm]): if (!can($perm)) { continue; } ?>
            <form method="post" action="<?= e(url('modules/billing/claims.php' . ($status !== '' ? '?status=' . urlencode($status) : ''))) ?>" style="display:inline" onsubmit="return confirm('<?= e($label) ?> klaim ini?');">
              <?= csrf_field() ?><input type="hidden" name="claim_id" value="<?= (int) $r['claim_id'] ?>"><input type="hidden" name="to" value="<?= e($to) ?>">
              <button type="submit" class="btn btn-outline"><?= e($label) ?></button>
            </form>
          <?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
