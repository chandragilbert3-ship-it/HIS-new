<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';

require_permission('cpoe.view');

$order = cpoe_find_order((int) ($_GET['id'] ?? 0));
if ($order === null) {
    http_response_code(404);
    exit('Order tidak ditemukan.');
}
$visit = cpoe_find_visit((int) $order['visit_id']);
$items = cpoe_order_items((int) $order['order_id']);
$types = cpoe_order_types();
$stat  = cpoe_status_labels();

audit_log('Cetak order ' . cpoe_order_no((int) $order['order_id']), 'orders', (int) $order['order_id'], (int) $order['visit_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Order <?= e(cpoe_order_no((int) $order['order_id'])) ?></title>
<style>
  body{font-family:Arial,sans-serif;max-width:720px;margin:24px auto;color:#111;font-size:14px}
  h1{font-size:18px;margin:0}.sub{color:#555;margin-bottom:16px}
  table{width:100%;border-collapse:collapse;margin:12px 0}
  th,td{border:1px solid #999;padding:6px 8px;text-align:left;vertical-align:top}
  th{background:#eee}.meta td{border:none;padding:2px 8px 2px 0}
  .ttd{margin-top:48px;text-align:right}
  @media print{.no-print{display:none}}
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">🖨 Cetak</button></p>
<h1>Rumah Sakit Dunia Indah</h1>
<div class="sub">Lembar Order Dokter (CPOE)</div>
<table class="meta">
  <tr><td>No. Order</td><td><b><?= e(cpoe_order_no((int) $order['order_id'])) ?></b></td></tr>
  <tr><td>Pasien</td><td><?= e($visit['nama_pasien'] ?? '-') ?> (<?= e($visit['no_rm'] ?? '-') ?>)</td></tr>
  <tr><td>Kunjungan</td><td>#<?= (int) $order['visit_id'] ?> &middot; <?= e($visit['polyclinic_name'] ?? '-') ?></td></tr>
  <tr><td>Jenis</td><td><?= e($types[$order['order_type']][0] ?? $order['order_type']) ?></td></tr>
  <tr><td>Prioritas</td><td><?= e(cpoe_priority_labels()[$order['priority']] ?? $order['priority']) ?></td></tr>
  <tr><td>Status</td><td><?= e($stat[$order['status']][0] ?? $order['status']) ?></td></tr>
  <tr><td>Waktu</td><td><?= e(date('d/m/Y H:i', strtotime($order['ordered_at']))) ?></td></tr>
  <tr><td>Alasan klinis</td><td><?= $order['clinical_reason'] ? e($order['clinical_reason']) : '-' ?></td></tr>
</table>
<table>
  <tr><th>#</th><th>Item</th><?php if ($order['order_type'] === 'drug'): ?><th>Dosis</th><th>Rute</th><th>Frekuensi</th><th>Jumlah</th><?php endif; ?><th>Instruksi</th></tr>
  <?php foreach ($items as $n => $it): ?>
    <tr>
      <td><?= $n + 1 ?></td>
      <td><?= e((string) $it['nama']) ?><?= $it['strength'] ? ' ' . e($it['strength']) : '' ?></td>
      <?php if ($order['order_type'] === 'drug'): ?>
        <td><?= e((string) $it['dose']) ?></td>
        <td><?= e(cpoe_route_labels()[$it['route']] ?? (string) $it['route']) ?></td>
        <td><?= e((string) $it['frequency']) ?></td>
        <td><?= e((string) $it['quantity']) ?></td>
      <?php endif; ?>
      <td><?= $it['instruction'] ? e($it['instruction']) : '-' ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<div class="ttd">Dokter pemesan,<br><br><br><b><?= e($order['ordering_doctor_name']) ?></b></div>
</body>
</html>