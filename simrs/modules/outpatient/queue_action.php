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
$action  = (string) ($_POST['action'] ?? '');
if ($visitId <= 0 || !in_array($action, ['call', 'skip', 'start'], true)) {
    flash_set('error', 'Permintaan tidak valid.');
    redirect('modules/outpatient/index.php');
}

$dokter = cpoe_current_doctor();
$err    = opd_queue_move($visitId, $action, $dokter ? (int) $dokter['doctor_id'] : null);

if ($err !== null) {
    flash_set('error', $err);
    redirect('modules/outpatient/index.php');
}

$label = ['call' => 'Panggil pasien', 'skip' => 'Lewati pasien', 'start' => 'Mulai pemeriksaan'][$action];
audit_log($label . ' (kunjungan #' . $visitId . ')', 'visits', $visitId, $visitId);

if ($action === 'start') {
    redirect('modules/outpatient/exam.php?visit_id=' . $visitId);
}
flash_set('success', $label . ' berhasil.');
redirect('modules/outpatient/index.php');