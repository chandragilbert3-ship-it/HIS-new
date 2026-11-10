<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/scheduling.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/scheduling/appointments.php');
}
require_permission('scheduling.update');
csrf_require();

$id  = (int) ($_POST['appointment_id'] ?? 0);
$app = $id > 0 ? sch_find_appointment($id) : null;
if ($app === null) {
    flash_set('error', 'Appointment tidak ditemukan.');
    redirect('modules/scheduling/appointments.php');
}

$err = sch_cancel($id);
if ($err === null) {
    audit_log('Batalkan appointment ' . $app['booking_code'], 'appointments', $id);
    flash_set('success', 'Appointment ' . $app['booking_code'] . ' dibatalkan.');
} else {
    flash_set('error', $err);
}
redirect('modules/scheduling/appointments.php');