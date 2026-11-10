<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/registration/index.php');
}

require_permission('registration.update');
csrf_require();

$visitId   = (int) ($_POST['visit_id'] ?? 0);
$patientId = (int) ($_POST['patient_id'] ?? 0);

if ($visitId <= 0) {
    flash_set('error', 'Permintaan tidak valid.');
    redirect('modules/registration/index.php');
}

if (reg_cancel_visit($visitId)) {
    audit_log('Batalkan kunjungan #' . $visitId, 'visits', $visitId, $visitId);
    flash_set('success', 'Kunjungan dibatalkan.');
} else {
    flash_set('error', 'Kunjungan tidak bisa dibatalkan (sudah diproses atau tidak ditemukan).');
}

redirect($patientId > 0 ? 'modules/registration/patient.php?id=' . $patientId : 'modules/registration/index.php');