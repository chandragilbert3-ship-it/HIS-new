<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/scheduling.php';
require_once APP_ROOT . '/includes/cpoe.php';

require_permission('cpoe.create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/cpoe/index.php');
}
csrf_require();

$dokter = cpoe_current_doctor();
if ($dokter === null) {
    flash_set('error', 'Akun Anda belum terhubung ke data dokter aktif.');
    redirect('modules/cpoe/index.php');
}

$app = sch_find_appointment((int) ($_POST['appointment_id'] ?? 0));
if ($app === null || $app['status'] !== 'booked' || $app['appointment_date'] !== date('Y-m-d')) {
    flash_set('error', 'Appointment tidak ditemukan atau sudah tidak bisa di-check-in (harus berstatus Terbooking dan bertanggal hari ini).');
    redirect('modules/cpoe/index.php');
}

try {
    [$visitId, $queueNo] = reg_create_visit(
        (int) $app['patient_id'],
        (int) $app['polyclinic_id'],
        (int) $app['doctor_id'],
        'outpatient',
        null,
        (int) $app['appointment_id']
    );
    sch_refresh_filled((int) $app['schedule_id']);
    audit_log('Check-in appointment ' . $app['booking_code'] . ' dari CPOE, antrean ' . $queueNo, 'visits', $visitId, $visitId);
    flash_set('success', 'Check-in berhasil (antrean ' . $queueNo . '). Silakan buat order.');
    redirect('modules/cpoe/order_form.php?visit_id=' . $visitId);
} catch (RuntimeException $ex) {
    flash_set('error', $ex->getMessage());
} catch (PDOException $ex) {
    error_log('Check-in dari CPOE gagal: ' . $ex->getMessage());
    flash_set('error', 'Check-in gagal karena kesalahan database.');
}
redirect('modules/cpoe/index.php');
