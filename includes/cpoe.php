<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul CPOE (Computerized Physician Order Entry).
 * Semua query memakai prepared statement.
 */

function cpoe_order_types(): array
{
    return [
        'lab'          => ['Laboratorium',   '🧪'],
        'radiology'    => ['Radiologi',      '📷'],
        'drug'         => ['Obat / Resep',   '💊'],
        'procedure'    => ['Tindakan Medis', '🩹'],
        'consultation' => ['Konsultasi',     '🗣️'],
        'admission'    => ['Rawat Inap',     '🛏️'],
    ];
}

function cpoe_priority_labels(): array
{
    return ['routine' => 'Rutin', 'cito' => 'Cito / Segera'];
}

/** [status => [teks, kelas CSS]] */
function cpoe_status_labels(): array
{
    return [
        'ordered'     => ['Dipesan',    'cp-info'],
        'in_progress' => ['Diproses',   'cp-warn'],
        'completed'   => ['Selesai',    'cp-ok'],
        'cancelled'   => ['Dibatalkan', 'cp-off'],
    ];
}

function cpoe_route_labels(): array
{
    return [
        'oral'       => 'Oral',
        'iv'         => 'Intravena (IV)',
        'im'         => 'Intramuskular (IM)',
        'sc'         => 'Subkutan (SC)',
        'topical'    => 'Topikal',
        'inhalation' => 'Inhalasi',
        'other'      => 'Lainnya',
    ];
}

function cpoe_order_no(int $orderId): string
{
    return 'ORD-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
}

/**
 * Konfigurasi sumber item per jenis order.
 * item_type = nilai ORDER_ITEMS.item_type; col = kolom referensi di ORDER_ITEMS.
 */
function cpoe_item_source(string $orderType): ?array
{
    return match ($orderType) {
        'lab'          => ['item_type' => 'lab',       'col' => 'lab_test_id',  'table' => 'master_lab_tests',  'pk' => 'lab_test_id',  'name' => 'test_name',      'category' => null],
        'radiology'    => ['item_type' => 'radiology', 'col' => 'rad_test_id',  'table' => 'master_rad_tests',  'pk' => 'rad_test_id',  'name' => 'test_name',      'category' => null],
        'drug'         => ['item_type' => 'drug',      'col' => 'drug_id',      'table' => 'master_drugs',      'pk' => 'drug_id',      'name' => 'drug_name',      'category' => null],
        'procedure'    => ['item_type' => 'procedure', 'col' => 'procedure_id', 'table' => 'master_procedures', 'pk' => 'procedure_id', 'name' => 'procedure_name', 'category' => 'procedure'],
        'consultation' => ['item_type' => 'procedure', 'col' => 'procedure_id', 'table' => 'master_procedures', 'pk' => 'procedure_id', 'name' => 'procedure_name', 'category' => 'consultation'],
        'admission'    => ['item_type' => 'procedure', 'col' => 'procedure_id', 'table' => 'master_procedures', 'pk' => 'procedure_id', 'name' => 'procedure_name', 'category' => 'admission'],
        default        => null,
    };
}

/** Pilihan aktif untuk satu jenis order: [id => nama]. */
function cpoe_item_options(string $orderType): array
{
    $src = cpoe_item_source($orderType);
    if ($src === null) {
        return [];
    }

    $sql    = "SELECT {$src['pk']} AS id, {$src['name']} AS nama FROM {$src['table']} WHERE is_active = 1";
    $params = [];
    if ($src['category'] !== null) {
        $sql .= ' AND category = :cat';
        $params[':cat'] = $src['category'];
    }
    $sql .= " ORDER BY {$src['name']}";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(string) $r['id']] = (string) $r['nama'];
    }
    return $out;
}

/** Data dokter untuk akun yang sedang login (null jika bukan dokter aktif). */
function cpoe_current_doctor(): ?array
{
    $stmt = db()->prepare('SELECT doctor_id, doctor_name FROM master_doctors WHERE user_id = :u AND is_active = 1 LIMIT 1');
    $stmt->execute([':u' => (int) ($_SESSION['user']['id'] ?? 0)]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Kunjungan aktif (7 hari terakhir) untuk dipilih dokter.
 * $semua = false: hanya pasien milik dokter ini atau yang belum punya dokter.
 */
function cpoe_active_visits(string $q, bool $semua, ?int $doctorId, int $limit = 30): array
{
    $sql = "SELECT v.visit_id, v.visit_date, v.service_type, v.status,
                   p.patient_id, p.no_rm, p.nama_pasien,
                   mp.polyclinic_name, md.doctor_name, q.queue_no,
                   (SELECT COUNT(*) FROM patient_allergies a WHERE a.patient_id = p.patient_id) AS jml_alergi
              FROM visits v
              JOIN patients p            ON p.patient_id = v.patient_id
              JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
              LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
              LEFT JOIN queue q           ON q.visit_id = v.visit_id
             WHERE v.status IN ('registered','in_progress')
               AND v.visit_date >= :sejak";
    $params = [':sejak' => date('Y-m-d', strtotime('-7 days'))];

    if (!$semua) {
        $sql .= ' AND (v.doctor_id IS NULL OR v.doctor_id = :dok)';
        $params[':dok'] = $doctorId ?? 0;
    }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $sql .= ' AND (p.nama_pasien LIKE :q1 OR p.no_rm LIKE :q2 OR q.queue_no LIKE :q3)';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
    }
    $sql .= ' ORDER BY v.visit_id DESC LIMIT :lim';

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function cpoe_find_visit(int $visitId): ?array
{
    $stmt = db()->prepare(
        'SELECT v.visit_id, v.visit_date, v.service_type, v.status, v.doctor_id,
                p.patient_id, p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin,
                mp.polyclinic_name, md.doctor_name, q.queue_no
           FROM visits v
           JOIN patients p            ON p.patient_id = v.patient_id
           JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
           LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
           LEFT JOIN queue q           ON q.visit_id = v.visit_id
          WHERE v.visit_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Peringatan alergi sederhana: pencocokan teks nama alergen dengan nama obat,
 * ditambah daftar kecil kelas penisilin. BUKAN pengganti penilaian klinis dokter.
 *
 * @param string[] $allergens
 * @param string[] $drugNames
 * @return array daftar teks peringatan
 */
function cpoe_allergy_hits(array $allergens, array $drugNames): array
{
    $kelas = [
        'penisilin'  => ['penisilin', 'penicillin', 'amoxicillin', 'amoksisilin', 'ampicillin', 'ampisilin', 'cloxacillin', 'kloksasilin'],
        'penicillin' => ['penisilin', 'penicillin', 'amoxicillin', 'amoksisilin', 'ampicillin', 'ampisilin', 'cloxacillin', 'kloksasilin'],
    ];

    $hits = [];
    foreach ($allergens as $alergen) {
        $a = mb_strtolower(trim($alergen));
        if ($a === '') {
            continue;
        }
        foreach ($drugNames as $obat) {
            $d     = mb_strtolower($obat);
            $cocok = str_contains($d, $a) || str_contains($a, $d);
            if (!$cocok && isset($kelas[$a])) {
                foreach ($kelas[$a] as $anggota) {
                    if (str_contains($d, $anggota)) {
                        $cocok = true;
                        break;
                    }
                }
            }
            if ($cocok) {
                $hits[] = $obat . ' (alergi tercatat: ' . $alergen . ')';
            }
        }
    }
    return array_values(array_unique($hits));
}

/**
 * Cari order aktif untuk item yang sama pada kunjungan yang sama (cegah duplikat).
 * Hasil: nomor order atau null.
 */
function cpoe_active_duplicate(int $visitId, string $col, int $refId): ?string
{
    $allowed = ['lab_test_id', 'rad_test_id', 'drug_id', 'procedure_id'];
    if (!in_array($col, $allowed, true)) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT o.order_id
           FROM order_items oi
           JOIN orders o ON o.order_id = oi.order_id
          WHERE o.visit_id = :v AND o.status IN ('ordered','in_progress') AND oi.{$col} = :id
          LIMIT 1"
    );
    $stmt->execute([':v' => $visitId, ':id' => $refId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : cpoe_order_no((int) $id);
}

/**
 * Validasi input form order.
 * Hasil: [data, errors]. data = priority, reason, items[].
 * Tiap item: ref_id, label, instruction, rx (null atau dose/route/frequency/quantity).
 */
function cpoe_validate_order(string $type, array $in, int $visitId): array
{
    $errors = [];
    $src    = cpoe_item_source($type);
    if ($src === null) {
        return [[], ['Jenis order tidak valid.']];
    }

    $get = fn(array $a, string $k): string => is_string($a[$k] ?? null) ? trim($a[$k]) : '';

    $priority = $get($in, 'priority');
    if (!array_key_exists($priority, cpoe_priority_labels())) {
        $errors[] = 'Prioritas tidak valid.';
    }

    $reason = $get($in, 'clinical_reason');
    if (mb_strlen($reason) > 500) {
        $errors[] = 'Alasan klinis maksimal 500 karakter.';
    }
    if ($reason === '' && ($priority === 'cito' || in_array($type, ['radiology', 'admission'], true))) {
        $errors[] = 'Alasan klinis wajib diisi untuk order Cito, Radiologi, dan Rawat Inap.';
    }

    $options = cpoe_item_options($type);
    $items   = [];

    if ($type === 'drug') {
        $rows = is_array($in['drug'] ?? null) ? $in['drug'] : [];
        $seen = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $no       = ((int) $i) + 1;
            $drugId   = $get($row, 'drug_id');
            $dose     = $get($row, 'dose');
            $route    = $get($row, 'route');
            $freq     = $get($row, 'frequency');
            $qty      = $get($row, 'quantity');
            $instr    = $get($row, 'instruction');

            if ($drugId === '' && $dose === '' && $freq === '' && $qty === '' && $instr === '') {
                continue; // baris kosong diabaikan
            }
            if (!array_key_exists($drugId, $options)) {
                $errors[] = "Baris obat $no: obat belum dipilih atau tidak valid.";
                continue;
            }
            if (isset($seen[$drugId])) {
                $errors[] = "Baris obat $no: obat yang sama sudah ada di order ini.";
                continue;
            }
            $seen[$drugId] = true;

            $rowErr = [];
            if ($dose === '' || mb_strlen($dose) > 50) {
                $rowErr[] = 'dosis wajib diisi (maks. 50 karakter)';
            }
            if (!array_key_exists($route, cpoe_route_labels())) {
                $rowErr[] = 'rute pemberian wajib dipilih';
            }
            if ($freq === '' || mb_strlen($freq) > 50) {
                $rowErr[] = 'frekuensi wajib diisi (maks. 50 karakter)';
            }
            if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $qty) || (float) $qty <= 0) {
                $rowErr[] = 'jumlah harus angka lebih dari 0 (contoh 10 atau 7.5)';
            }
            if (mb_strlen($instr) > 255) {
                $rowErr[] = 'instruksi maksimal 255 karakter';
            }
            if ($rowErr) {
                $errors[] = "Baris obat $no ({$options[$drugId]}): " . implode('; ', $rowErr) . '.';
                continue;
            }

            $items[] = [
                'ref_id'      => (int) $drugId,
                'label'       => $options[$drugId],
                'instruction' => $instr === '' ? null : $instr,
                'rx'          => ['dose' => $dose, 'route' => $route, 'frequency' => $freq, 'quantity' => $qty],
            ];
        }
        if (!$items && !$errors) {
            $errors[] = 'Isi minimal satu obat.';
        }
    } else {
        $chosen = $in['items'] ?? [];
        $chosen = is_array($chosen) ? $chosen : [$chosen];
        $chosen = array_values(array_unique(array_map('strval', $chosen)));

        $instr = $get($in, 'instruction');
        if (mb_strlen($instr) > 255) {
            $errors[] = 'Instruksi maksimal 255 karakter.';
        }

        if (!$chosen) {
            $errors[] = 'Pilih minimal satu item.';
        }
        if (count($chosen) > 20) {
            $errors[] = 'Satu order maksimal 20 item.';
        }
        if ($type === 'admission' && count($chosen) > 1) {
            $errors[] = 'Order rawat inap hanya boleh satu layanan.';
        }
        foreach ($chosen as $id) {
            if (!array_key_exists($id, $options)) {
                $errors[] = 'Ada item yang tidak valid atau sudah tidak aktif.';
                break;
            }
            $items[] = [
                'ref_id'      => (int) $id,
                'label'       => $options[$id],
                'instruction' => $instr === '' ? null : $instr,
                'rx'          => null,
            ];
        }
    }

    // Cegah order ganda yang masih aktif pada kunjungan yang sama
    if (!$errors) {
        foreach ($items as $it) {
            $dup = cpoe_active_duplicate($visitId, $src['col'], $it['ref_id']);
            if ($dup !== null) {
                $errors[] = $it['label'] . ' sudah dipesan pada kunjungan ini dan masih aktif (' . $dup . ').';
            }
        }
    }

    return [['priority' => $priority, 'reason' => $reason === '' ? null : $reason, 'items' => $items], $errors];
}

/**
 * Simpan order + item (+ resep untuk obat) dalam satu transaksi. Hasil: order_id.
 */
function cpoe_save_order(int $visitId, int $doctorId, string $type, array $data): int
{
    $src = cpoe_item_source($type);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare(
            'INSERT INTO orders (visit_id, ordering_doctor_id, order_type, priority, clinical_reason, status, ordered_at)
             VALUES (:v, :d, :t, :p, :r, :s, :at)'
        );
        $o->bindValue(':v', $visitId, PDO::PARAM_INT);
        $o->bindValue(':d', $doctorId, PDO::PARAM_INT);
        $o->bindValue(':t', $type);
        $o->bindValue(':p', $data['priority']);
        $o->bindValue(':r', $data['reason'], $data['reason'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $o->bindValue(':s', 'ordered');
        $o->bindValue(':at', date('Y-m-d H:i:s'));
        $o->execute();
        $orderId = (int) $pdo->lastInsertId();

        $prescriptionId = null;
        if ($type === 'drug') {
            $rx = $pdo->prepare("INSERT INTO prescriptions (order_id, doctor_id, status) VALUES (:o, :d, 'new')");
            $rx->execute([':o' => $orderId, ':d' => $doctorId]);
            $prescriptionId = (int) $pdo->lastInsertId();
        }

        foreach ($data['items'] as $it) {
            $cols = ['lab_test_id' => null, 'rad_test_id' => null, 'drug_id' => null, 'procedure_id' => null];
            $cols[$src['col']] = $it['ref_id'];

            $i = $pdo->prepare(
                'INSERT INTO order_items (order_id, item_type, lab_test_id, rad_test_id, drug_id, procedure_id, instruction)
                 VALUES (:o, :t, :lab, :rad, :drug, :proc, :ins)'
            );
            $i->bindValue(':o', $orderId, PDO::PARAM_INT);
            $i->bindValue(':t', $src['item_type']);
            foreach ([':lab' => 'lab_test_id', ':rad' => 'rad_test_id', ':drug' => 'drug_id', ':proc' => 'procedure_id'] as $ph => $col) {
                $i->bindValue($ph, $cols[$col], $cols[$col] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            }
            $i->bindValue(':ins', $it['instruction'], $it['instruction'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $i->execute();
            $orderItemId = (int) $pdo->lastInsertId();

            if ($prescriptionId !== null && $it['rx'] !== null) {
                $pi = $pdo->prepare(
                    'INSERT INTO prescription_items (prescription_id, order_item_id, dose, route, frequency, quantity)
                     VALUES (:p, :oi, :dose, :route, :freq, :qty)'
                );
                $pi->execute([
                    ':p' => $prescriptionId, ':oi' => $orderItemId, ':dose' => $it['rx']['dose'],
                    ':route' => $it['rx']['route'], ':freq' => $it['rx']['frequency'], ':qty' => $it['rx']['quantity'],
                ]);
            }
        }

        $pdo->commit();
        return $orderId;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/**
 * Batalkan order milik dokter ini yang belum diproses. Hasil: null jika sukses, atau teks error.
 */
function cpoe_cancel_order(int $orderId, int $doctorId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, ordering_doctor_id FROM orders WHERE order_id = :id FOR UPDATE');
        $st->execute([':id' => $orderId]);
        $o = $st->fetch();

        if (!$o) {
            $pdo->rollBack();
            return 'Order tidak ditemukan.';
        }
        if ((int) $o['ordering_doctor_id'] !== $doctorId) {
            $pdo->rollBack();
            return 'Hanya dokter yang membuat order yang dapat membatalkannya.';
        }
        if ($o['status'] !== 'ordered') {
            $pdo->rollBack();
            return 'Order sudah diproses unit tujuan dan tidak bisa dibatalkan di sini.';
        }

        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = :id")->execute([':id' => $orderId]);
        $pdo->prepare("UPDATE prescriptions SET status = 'cancelled' WHERE order_id = :id AND status = 'new'")->execute([':id' => $orderId]);

        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/**
 * Daftar order milik seorang dokter. Hasil: [baris, total].
 */
function cpoe_my_orders(int $doctorId, string $status, int $page, int $perPage): array
{
    $where  = ' WHERE o.ordering_doctor_id = :d';
    $params = [':d' => $doctorId];
    if ($status !== '' && array_key_exists($status, cpoe_status_labels())) {
        $where .= ' AND o.status = :s';
        $params[':s'] = $status;
    }

    $count = db()->prepare('SELECT COUNT(*) FROM orders o' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $sql = "SELECT o.order_id, o.order_type, o.priority, o.status, o.ordered_at, p.no_rm, p.nama_pasien,
                   GROUP_CONCAT(COALESCE(lt.test_name, rt.test_name, d.drug_name, pr.procedure_name) ORDER BY oi.order_item_id SEPARATOR ', ') AS item_names
              FROM orders o
              JOIN visits v    ON v.visit_id = o.visit_id
              JOIN patients p  ON p.patient_id = v.patient_id
              LEFT JOIN order_items oi        ON oi.order_id = o.order_id
              LEFT JOIN master_lab_tests lt   ON lt.lab_test_id = oi.lab_test_id
              LEFT JOIN master_rad_tests rt   ON rt.rad_test_id = oi.rad_test_id
              LEFT JOIN master_drugs d        ON d.drug_id = oi.drug_id
              LEFT JOIN master_procedures pr  ON pr.procedure_id = oi.procedure_id
              $where
              GROUP BY o.order_id, o.order_type, o.priority, o.status, o.ordered_at, p.no_rm, p.nama_pasien
              ORDER BY o.order_id DESC
              LIMIT :lim OFFSET :off";
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();

    return [$stmt->fetchAll(), $total];
}

function cpoe_find_order(int $orderId): ?array
{
    $stmt = db()->prepare(
        'SELECT o.*, md.doctor_name AS ordering_doctor_name
           FROM orders o
           JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.order_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Item order beserta nama dan data resep (bila obat). */
function cpoe_order_items(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT oi.order_item_id, oi.item_type, oi.instruction,
                COALESCE(lt.test_name, rt.test_name, d.drug_name, pr.procedure_name) AS nama,
                d.dosage_form, d.strength,
                pit.dose, pit.route, pit.frequency, pit.quantity
           FROM order_items oi
           LEFT JOIN master_lab_tests lt  ON lt.lab_test_id = oi.lab_test_id
           LEFT JOIN master_rad_tests rt  ON rt.rad_test_id = oi.rad_test_id
           LEFT JOIN master_drugs d       ON d.drug_id = oi.drug_id
           LEFT JOIN master_procedures pr ON pr.procedure_id = oi.procedure_id
           LEFT JOIN prescription_items pit ON pit.order_item_id = oi.order_item_id
          WHERE oi.order_id = :id
          ORDER BY oi.order_item_id'
    );
    $stmt->execute([':id' => $orderId]);
    return $stmt->fetchAll();
}