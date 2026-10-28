<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/cpoe.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/cpoe/orders.php');
}

require_permission('cpoe.update');
csrf_require();

$orderId = (int) ($_POST['order_id'] ?? 0);
$dokter  = cpoe_current_doctor();

if ($orderId <= 0 || $dokter === null) {
    flash_set('error', 'Permintaan tidak valid.');
    redirect('modules/cpoe/orders.php');
}

$error = cpoe_cancel_order($orderId, (int) $dokter['doctor_id']);
if ($error === null) {
    $o = cpoe_find_order($orderId);
    audit_log('Batalkan order ' . cpoe_order_no($orderId), 'orders', $orderId, $o ? (int) $o['visit_id'] : null);
    flash_set('success', 'Order ' . cpoe_order_no($orderId) . ' dibatalkan.');
} else {
    flash_set('error', $error);
}
redirect('modules/cpoe/orders.php');