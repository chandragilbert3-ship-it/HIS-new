<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Reporting & Dashboard.
 * Semua angka dihitung langsung dari database (tidak ada data tetap di kode).
 */

/** Baca rentang tanggal dari GET. Hasil: [dari, sampai, errors]. Default 30 hari terakhir. */
function rpt_range(array $src): array
{
    $errors = [];
    $valid  = function (string $s): ?string {
        $d = DateTime::createFromFormat('!Y-m-d', $s);
        return ($d && $d->format('Y-m-d') === $s) ? $s : null;
    };

    $from = $valid((string) ($src['dari'] ?? '')) ;
    $to   = $valid((string) ($src['sampai'] ?? ''));

    if (isset($src['dari']) && $src['dari'] !== '' && $from === null) {
        $errors[] = 'Format tanggal "dari" tidak valid.';
    }
    if (isset($src['sampai']) && $src['sampai'] !== '' && $to === null) {
        $errors[] = 'Format tanggal "sampai" tidak valid.';
    }

    $to   = $to ?? date('Y-m-d');
    $from = $from ?? date('Y-m-d', strtotime($to . ' -29 days'));

    if ($from > $to) {
        $errors[] = 'Tanggal "dari" tidak boleh setelah tanggal "sampai".';
        [$from, $to] = [$to, $from];
    }
    if ((strtotime($to) - strtotime($from)) / 86400 > 366) {
        $errors[] = 'Rentang maksimal 366 hari. Rentang dipersempit otomatis.';
        $from = date('Y-m-d', strtotime($to . ' -366 days'));
    }
    return [$from, $to, $errors];
}

function rpt_scalar(string $sql, array $params): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Angka ringkas untuk periode. */
function rpt_kpi(string $from, string $to): array
{
    $p = [':f' => $from, ':t' => $to];
    return [
        'patients_total' => rpt_scalar('SELECT COUNT(*) FROM patients', []),
        'patients_new'   => rpt_scalar('SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN :f AND :t', $p),
        'visits'         => rpt_scalar('SELECT COUNT(*) FROM visits WHERE DATE(visit_date) BETWEEN :f AND :t', $p),
        'visits_done'    => rpt_scalar("SELECT COUNT(*) FROM visits WHERE status = 'completed' AND DATE(visit_date) BETWEEN :f AND :t", $p),
        'visits_cancel'  => rpt_scalar("SELECT COUNT(*) FROM visits WHERE status = 'cancelled' AND DATE(visit_date) BETWEEN :f AND :t", $p),
        'orders'         => rpt_scalar('SELECT COUNT(*) FROM orders WHERE DATE(ordered_at) BETWEEN :f AND :t', $p),
        'orders_cito'    => rpt_scalar("SELECT COUNT(*) FROM orders WHERE priority = 'cito' AND DATE(ordered_at) BETWEEN :f AND :t", $p),
    ];
}

/**
 * Hitung kunjungan per dimensi. $dim hanya dari daftar tetap (bukan input pengguna).
 * Hasil: [kunci => jumlah] urut terbanyak.
 */
function rpt_visits_by(string $dim, string $from, string $to): array
{
    $sqlMap = [
        'service' => 'SELECT v.service_type AS k, COUNT(*) AS n FROM visits v
                       WHERE DATE(v.visit_date) BETWEEN :f AND :t GROUP BY v.service_type ORDER BY n DESC',
        'status'  => 'SELECT v.status AS k, COUNT(*) AS n FROM visits v
                       WHERE DATE(v.visit_date) BETWEEN :f AND :t GROUP BY v.status ORDER BY n DESC',
        'poli'    => 'SELECT mp.polyclinic_name AS k, COUNT(*) AS n FROM visits v
                       JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
                       WHERE DATE(v.visit_date) BETWEEN :f AND :t GROUP BY mp.polyclinic_name ORDER BY n DESC',
        'dokter'  => "SELECT COALESCE(md.doctor_name, '(belum ada dokter)') AS k, COUNT(*) AS n FROM visits v
                       LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
                       WHERE DATE(v.visit_date) BETWEEN :f AND :t GROUP BY k ORDER BY n DESC LIMIT 10",
    ];
    return rpt_pairs($sqlMap[$dim] ?? '', $from, $to);
}

/** Hitung order per dimensi: type, status, priority. */
function rpt_orders_by(string $dim, string $from, string $to): array
{
    $col = ['type' => 'order_type', 'status' => 'status', 'priority' => 'priority'][$dim] ?? null;
    if ($col === null) {
        return [];
    }
    return rpt_pairs("SELECT $col AS k, COUNT(*) AS n FROM orders
                       WHERE DATE(ordered_at) BETWEEN :f AND :t GROUP BY $col ORDER BY n DESC", $from, $to);
}

function rpt_pairs(string $sql, string $from, string $to): array
{
    if ($sql === '') {
        return [];
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([':f' => $from, ':t' => $to]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(string) $r['k']] = (int) $r['n'];
    }
    return $out;
}

/** Kunjungan per hari (hari tanpa kunjungan diisi 0). */
function rpt_daily_visits(string $from, string $to): array
{
    $stmt = db()->prepare('SELECT DATE(visit_date) AS d, COUNT(*) AS n FROM visits
                            WHERE DATE(visit_date) BETWEEN :f AND :t GROUP BY DATE(visit_date)');
    $stmt->execute([':f' => $from, ':t' => $to]);
    $found = [];
    foreach ($stmt->fetchAll() as $r) {
        $found[(string) $r['d']] = (int) $r['n'];
    }
    $out = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $d       = date('Y-m-d', $t);
        $out[$d] = $found[$d] ?? 0;
    }
    return $out;
}

/** Diagram batang horizontal murni HTML/CSS. $labels: peta kunci => teks tampilan. */
function rpt_bars_html(array $data, array $labels = []): string
{
    if (!$data) {
        return '<div class="muted">Belum ada data pada periode ini.</div>';
    }
    $max  = max($data);
    $html = '<div class="rp-bars">';
    foreach ($data as $k => $n) {
        $pct   = $max > 0 ? max(2, (int) round($n / $max * 100)) : 0;
        $label = $labels[$k] ?? (string) $k;
        $html .= '<div class="rp-row"><div class="rp-label">' . e($label) . '</div>'
               . '<div class="rp-track"><div class="rp-fill" style="width:' . $pct . '%"></div></div>'
               . '<div class="rp-num">' . (int) $n . '</div></div>';
    }
    return $html . '</div>';
}

/** Daftar kunjungan terperinci untuk laporan & ekspor. Hasil: [baris, total]. */
function rpt_visit_rows(string $from, string $to, string $service, string $status, ?int $page, int $perPage): array
{
    $where  = ' WHERE DATE(v.visit_date) BETWEEN :f AND :t';
    $params = [':f' => $from, ':t' => $to];
    if ($service !== '') {
        $where .= ' AND v.service_type = :svc';
        $params[':svc'] = $service;
    }
    if ($status !== '') {
        $where .= ' AND v.status = :st';
        $params[':st'] = $status;
    }

    $count = db()->prepare('SELECT COUNT(*) FROM visits v' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $sql = 'SELECT v.visit_id, v.visit_date, v.service_type, v.status,
                   p.no_rm, p.nama_pasien, mp.polyclinic_name, md.doctor_name, q.queue_no
              FROM visits v
              JOIN patients p            ON p.patient_id = v.patient_id
              JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
              LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
              LEFT JOIN queue q           ON q.visit_id = v.visit_id'
          . $where . ' ORDER BY v.visit_id DESC';
    if ($page !== null) {
        $sql .= ' LIMIT :lim OFFSET :off';
    } else {
        $sql .= ' LIMIT 5000'; // batas aman ekspor
    }
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    if ($page !== null) {
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    }
    $stmt->execute();
    return [$stmt->fetchAll(), $total];
}

/** Cegah CSV/formula injection di Excel: sel berawalan = + - @ diberi tanda petik. */
function rpt_csv_safe(string $v): string
{
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}