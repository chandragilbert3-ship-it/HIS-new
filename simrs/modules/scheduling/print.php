<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/scheduling.php';

require_permission('scheduling.view');

$app = sch_find_appointment((int) ($_GET['id'] ?? 0));
if ($app === null) {
    http_response_code(404);
    exit('Appointment tidak ditemukan.');
}
audit_log('Cetak bukti booking ' . $app['booking_code'], 'appointments', (int) $app['appointment_id']);
$stat = sch_status_labels();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Bukti Booking <?= e($app['booking_code']) ?></title>
<style>
  body{font-family:Arial,sans-serif;max-width:480px;margin:24px auto;color:#111;font-size:14px}
  h1{font-size:18px;margin:0}.sub{color:#555;margin-bottom:16px}
  .kode{font-size:24px;font-weight:700;letter-spacing:2px;border:2px dashed #1d4ed8;padding:10px;text-align:center;margin:14px 0}
  td{padding:4px 8px 4px 0;vertical-align:top}
  @media print{.no-print{display:none}}
</style>
</head>
<body>
<p class="no-print"><button onclick="window.print()">🖨 Cetak</button></p>
<h1>Rumah Sakit Dunia Indah</h1>
<div class="sub">Bukti Booking Appointment</div>
<div class="kode"><?= e($app['booking_code']) ?></div>
<table>
  <tr><td>Pasien</td><td><b><?= e($app['nama_pasien']) ?></b> (<?= e($app['no_rm']) ?>)</td></tr>
  <tr><td>Tanggal</td><td><?= e($app['day_of_week']) ?>, <?= e(reg_date_id($app['appointment_date'])) ?></td></tr>
  <tr><td>Jam praktik</td><td><?= e(substr($app['start_time'], 0, 5)) ?> - <?= e(substr($app['end_time'], 0, 5)) ?></td></tr>
  <tr><td>Poliklinik</td><td><?= e($app['polyclinic_name']) ?></td></tr>
  <tr><td>Dokter</td><td><?= e($app['doctor_name']) ?></td></tr>
  <tr><td>Status</td><td><?= e($stat[$app['status']][0] ?? $app['status']) ?></td></tr>
</table>
<p>Tunjukkan kode ini di loket pendaftaran pada hari kunjungan. Harap hadir 30 menit sebelum jam praktik.</p>
</body>
</html>