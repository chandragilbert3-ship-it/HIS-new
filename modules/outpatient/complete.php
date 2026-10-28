<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/outpatient.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/outpatient/index.php');
}
require_permission('outpatient.update');
csrf_require();

$visitId = (int) ($_POST['visit_id'] ?? 0);
$dokter  = cpoe_current_doctor();
if ($visitId <= 0 || $dokter === null) {
    flash_set('error', 'Permintaan tidak valid atau akun Anda bukan dokter aktif.');
    redirect('modules/outpatient/index.php');
}

$err = opd_complete($visitId, (int) $dokter['doctor_id']);
if ($err === null) {
    audit_log('Selesaikan kunjungan rawat jalan #' . $visitId, 'visits', $visitId, $visitId);
    flash_set('success', 'Kunjungan diselesaikan.');
    redirect('modules/outpatient/index.php');
}
flash_set('error', $err);
redirect('modules/outpatient/exam.php?visit_id=' . $visitId);