<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/reporting.php';

require_permission('reporting.view');

[$from, $to] = rpt_range($_GET);
$svcLbl = reg_service_labels();
$stLbl  = array_map(fn($v) => is_array($v) ? $v[0] : $v, reg_visit_status());
$service = (string) ($_GET['layanan'] ?? '');
$status  = (string) ($_GET['status'] ?? '');
$service = isset($svcLbl[$service]) ? $service : '';
$status  = isset($stLbl[$status]) ? $status : '';

[$rows] = rpt_visit_rows($from, $to, $service, $status, null, 0);

audit_log('Ekspor CSV kunjungan ' . $from . ' s/d ' . $to . ' (' . count($rows) . ' baris)', 'visits', null, null);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan_kunjungan_' . $from . '_' . $to . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
fputcsv($out, ['Visit', 'Tanggal', 'Antrean', 'No. RM', 'Nama Pasien', 'Layanan', 'Poliklinik', 'Dokter', 'Status']);
foreach ($rows as $r) {
    fputcsv($out, array_map('rpt_csv_safe', [
        (string) $r['visit_id'],
        (string) $r['visit_date'],
        (string) ($r['queue_no'] ?? ''),
        (string) $r['no_rm'],
        (string) $r['nama_pasien'],
        (string) ($svcLbl[$r['service_type']] ?? $r['service_type']),
        (string) $r['polyclinic_name'],
        (string) ($r['doctor_name'] ?? ''),
        (string) ($stLbl[$r['status']] ?? $r['status']),
    ]));
}
fclose($out);
exit;