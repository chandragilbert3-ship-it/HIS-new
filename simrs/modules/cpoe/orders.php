<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/cpoe.php';

require_permission('cpoe.view');

$dokter = cpoe_current_doctor();
$status = (string) ($_GET['status'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 15;

$rows = [];
$total = 0;
if ($dokter) {
    [$rows, $total] = cpoe_my_orders((int) $dokter['doctor_id'], $status, $page, $per);
}
$pages = max(1, (int) ceil($total / $per));

$pageTitle    = 'CPOE - Order Saya';
$activeModule = 'cpoe';
$breadcrumb   = 'Modul 9. CPOE &rsaquo; Order Saya';
$types        = cpoe_order_types();
$stat         = cpoe_status_labels();

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/cpoe/index.php')) ?>">1. Pilih Kunjungan</a>
  <a class="active" href="<?= e(url('modules/cpoe/orders.php')) ?>">2. Order Saya</a>
</div>

<div class="card">
  <h2>Status Order Saya</h2>

  <?php if (!$dokter): ?>
    <div class="alert alert-warning">Akun Anda belum terhubung ke data dokter aktif, jadi tidak ada order yang bisa ditampilkan.</div>
  <?php endif; ?>

  <form method="get" action="<?= e(url('modules/cpoe/orders.php')) ?>" class="toolbar">
    <select name="status">
      <option value="">Semua status</option>
      <?php foreach ($stat as $code => [$label]): ?>
        <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filter</button>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>No. Order</th><th>Pasien</th><th>Jenis</th><th>Item</th><th>Prioritas</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty">Belum ada order.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $o): ?>
        <?php [$sl, $sc] = $stat[$o['status']] ?? [$o['status'], 'cp-info']; ?>
        <tr>
          <td><code class="k"><?= e(cpoe_order_no((int) $o['order_id'])) ?></code></td>
          <td><?= e($o['nama_pasien']) ?><br><span class="muted"><?= e($o['no_rm']) ?></span></td>
          <td><?= e(($types[$o['order_type']][1] ?? '') . ' ' . ($types[$o['order_type']][0] ?? $o['order_type'])) ?></td>
          <td><?= e((string) $o['item_names']) ?></td>
          <td><?= $o['priority'] === 'cito' ? '<span class="cp-badge cp-off">CITO</span>' : 'Rutin' ?></td>
          <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
          <td><?= e(date('d/m/Y H:i', strtotime($o['ordered_at']))) ?></td>
          <td class="aksi">
            <a class="action-link" href="<?= e(url('modules/cpoe/print.php?id=' . (int) $o['order_id'])) ?>" target="_blank" rel="noopener">🖨 Cetak</a>
            <?php if ($o['status'] === 'ordered' && can('cpoe.update')): ?>
              <form method="post" action="<?= e(url('modules/cpoe/cancel.php')) ?>" class="inline-form"
                    onsubmit="return confirm('Batalkan order ini?');">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                <button type="submit" class="action-link danger">✖ Batalkan</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/cpoe/orders.php'), ['status' => $status], $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>