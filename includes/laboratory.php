<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Laboratorium.
 * Memakai ORDERS (jenis lab), ORDER_ITEMS, LAB_SPECIMENS, LAB_RESULTS, MASTER_LAB_TESTS.
 * Nilai rujukan diambil dari MASTER_LAB_TESTS (tidak ditanam di kode). Hasil tervalidasi tidak diubah.
 */

function lab_specimen_types(): array
{
    return ['Darah', 'Serum', 'Plasma', 'Urin', 'Feses', 'Sputum', 'Swab', 'Lainnya'];
}

function lab_specimen_status_labels(): array
{
    return [
        'collected' => ['Diambil', 'cp-info'], 'received' => ['Diterima Lab', 'cp-warn'],
        'processed' => ['Selesai Diproses', 'cp-ok'], 'rejected' => ['Ditolak', 'cp-off'],
    ];
}

function lab_result_status_labels(): array
{
    return ['pending' => ['Menunggu', 'cp-warn'], 'validated' => ['Tervalidasi', 'cp-ok'], 'rejected' => ['Ditolak', 'cp-off']];
}

function lab_flag_labels(): array
{
    return [
        'normal' => ['Normal', 'cp-ok'], 'low' => ['Rendah', 'cp-warn'],
        'high' => ['Tinggi', 'cp-warn'], 'critical' => ['Kritis', 'cp-off'],
    ];
}

/** Saran flag otomatis dari nilai rujukan berformat "min - max" dan nilai numerik. null bila tidak dapat dinilai. */
function lab_flag_auto(?string $value, ?string $range): ?string
{
    if ($value === null || $range === null) {
        return null;
    }
    $v = str_replace(',', '.', trim($value));
    if ($v === '' || !is_numeric($v)) {
        return null;
    }
    if (!preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s*[-–]\s*(-?\d+(?:[.,]\d+)?)\s*$/u', $range, $m)) {
        return null;
    }
    $min = (float) str_replace(',', '.', $m[1]);
    $max = (float) str_replace(',', '.', $m[2]);
    $n   = (float) $v;
    return $n < $min ? 'low' : ($n > $max ? 'high' : 'normal');
}

/** Alasan penolakan: wajib 5-300 karakter. Hasil: teks error atau null. */
function lab_validate_reason(string $reason): ?string
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
        return 'Alasan wajib diisi (5-300 karakter).';
    }
    return null;
}

/** Antrean order lab. $mode: 'active' (dipesan/berjalan) atau 'done' (selesai 7 hari terakhir). */
function lab_queue(string $mode): array
{
    $done = $mode === 'done';
    $cond = $done ? "o.status = 'completed' AND o.ordered_at >= :sejak" : "o.status IN ('ordered','in_progress')";
    $stmt = db()->prepare(
        "SELECT o.order_id, o.priority, o.status, o.ordered_at, p.no_rm, p.nama_pasien, md.doctor_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id AND oi.item_type = 'lab') AS jml_item,
                (SELECT COUNT(*) FROM lab_results r JOIN lab_specimens s ON s.specimen_id = r.specimen_id
                  WHERE s.order_id = o.order_id AND r.status = 'validated') AS jml_valid
           FROM orders o
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.order_type = 'lab' AND $cond
          ORDER BY o.priority = 'cito' DESC, o.ordered_at LIMIT 200"
    );
    $stmt->execute($done ? [':sejak' => date('Y-m-d H:i:s', strtotime('-7 days'))] : []);
    return $stmt->fetchAll();
}

function lab_find_order(int $orderId): ?array
{
    $stmt = db()->prepare(
        "SELECT o.order_id, o.visit_id, o.priority, o.status, o.ordered_at, o.clinical_reason,
                p.patient_id, p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin, md.doctor_name
           FROM orders o
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.order_id = :id AND o.order_type = 'lab' LIMIT 1"
    );
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Item lab yang belum punya hasil aktif (pending/validated) = belum diambil sampelnya atau perlu ambil ulang. */
function lab_uncollected_items(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT oi.order_item_id, oi.instruction, lt.test_name, lt.unit, lt.reference_range
           FROM order_items oi
           JOIN master_lab_tests lt ON lt.lab_test_id = oi.lab_test_id
          WHERE oi.order_id = :o AND oi.item_type = 'lab'
            AND NOT EXISTS (SELECT 1 FROM lab_results r WHERE r.order_item_id = oi.order_item_id AND r.status IN ('pending','validated'))
          ORDER BY oi.order_item_id"
    );
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

function lab_specimens(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT s.*, (SELECT COUNT(*) FROM lab_results r WHERE r.specimen_id = s.specimen_id) AS jml_hasil
           FROM lab_specimens s WHERE s.order_id = :o ORDER BY s.specimen_id"
    );
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

function lab_results(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT r.*, s.barcode, s.specimen_type, s.status AS specimen_status,
                lt.test_name, lt.unit, lt.reference_range, u.nama AS validator
           FROM lab_results r
           JOIN lab_specimens s ON s.specimen_id = r.specimen_id
           JOIN master_lab_tests lt ON lt.lab_test_id = r.lab_test_id
           LEFT JOIN users u ON u.user_id = r.validated_by
          WHERE s.order_id = :o ORDER BY r.lab_result_id'
    );
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

/** Hasil tervalidasi pada satu kunjungan (untuk EMR). */
function lab_visit_results(int $visitId): array
{
    $stmt = db()->prepare(
        "SELECT o.order_id, o.ordered_at, lt.test_name, lt.unit, lt.reference_range, r.result_value, r.result_flag
           FROM lab_results r
           JOIN lab_specimens s ON s.specimen_id = r.specimen_id
           JOIN orders o ON o.order_id = s.order_id
           JOIN master_lab_tests lt ON lt.lab_test_id = r.lab_test_id
          WHERE o.visit_id = :v AND r.status = 'validated'
          ORDER BY o.order_id DESC, r.lab_result_id"
    );
    $stmt->execute([':v' => $visitId]);
    return $stmt->fetchAll();
}

function lab_new_barcode(): string
{
    return 'LAB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Pengambilan spesimen. $typeByItem: [order_item_id => jenis spesimen]; item dengan jenis kosong dilewati.
 * Satu spesimen (barcode) dibuat per jenis. Hasil: jumlah spesimen dibuat. RuntimeException bila aturan dilanggar.
 */
function lab_collect(int $orderId, array $typeByItem): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare("SELECT status FROM orders WHERE order_id = :id AND order_type = 'lab' FOR UPDATE");
        $o->execute([':id' => $orderId]);
        $status = $o->fetchColumn();
        if (!in_array($status, ['ordered', 'in_progress'], true)) {
            throw new RuntimeException('Order lab tidak aktif.');
        }
        $open  = [];
        foreach (lab_uncollected_items($orderId) as $it) {
            $open[(int) $it['order_item_id']] = true;
        }
        $groups = [];
        foreach ($typeByItem as $itemId => $type) {
            $type = is_string($type) ? trim($type) : '';
            if ($type === '') {
                continue;
            }
            if (!in_array($type, lab_specimen_types(), true)) {
                throw new RuntimeException('Jenis spesimen tidak valid.');
            }
            if (!isset($open[(int) $itemId])) {
                throw new RuntimeException('Ada item yang tidak valid atau sudah diambil sampelnya.');
            }
            $groups[$type][] = (int) $itemId;
        }
        if (!$groups) {
            throw new RuntimeException('Pilih jenis spesimen untuk minimal satu pemeriksaan.');
        }

        $insS = $pdo->prepare("INSERT INTO lab_specimens (order_id, barcode, specimen_type, status) VALUES (:o, :b, :t, 'collected')");
        $insR = $pdo->prepare(
            "INSERT INTO lab_results (specimen_id, order_item_id, lab_test_id, status)
             SELECT :s, oi.order_item_id, oi.lab_test_id, 'pending' FROM order_items oi WHERE oi.order_item_id = :i AND oi.order_id = :o"
        );
        $made = 0;
        foreach ($groups as $type => $itemIds) {
            $specimenId = 0;
            for ($try = 0; $try < 3 && $specimenId === 0; $try++) {
                try {
                    $insS->execute([':o' => $orderId, ':b' => lab_new_barcode(), ':t' => $type]);
                    $specimenId = (int) $pdo->lastInsertId();
                } catch (PDOException $ex) {
                    if ($ex->getCode() !== '23000' || $try === 2) {
                        throw $ex;
                    }
                }
            }
            foreach ($itemIds as $itemId) {
                $insR->execute([':s' => $specimenId, ':i' => $itemId, ':o' => $orderId]);
            }
            $made++;
        }
        $pdo->prepare("UPDATE orders SET status = 'in_progress' WHERE order_id = :o AND status = 'ordered'")->execute([':o' => $orderId]);
        $pdo->commit();
        return $made;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Spesimen diterima lab: collected -> received. */
function lab_receive(int $specimenId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM lab_specimens WHERE specimen_id = :id FOR UPDATE');
        $st->execute([':id' => $specimenId]);
        $cur = $st->fetchColumn();
        if ($cur !== 'collected') {
            $pdo->rollBack();
            return 'Hanya spesimen berstatus "Diambil" yang bisa diterima.';
        }
        $pdo->prepare("UPDATE lab_specimens SET status = 'received', received_at = :t WHERE specimen_id = :id")
            ->execute([':t' => date('Y-m-d H:i:s'), ':id' => $specimenId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Tolak spesimen (collected/received); hasil yang masih menunggu ikut ditolak. */
function lab_reject_specimen(int $specimenId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM lab_specimens WHERE specimen_id = :id FOR UPDATE');
        $st->execute([':id' => $specimenId]);
        $cur = $st->fetchColumn();
        if (!in_array($cur, ['collected', 'received'], true)) {
            $pdo->rollBack();
            return 'Spesimen ini tidak dapat ditolak pada status saat ini.';
        }
        $pdo->prepare("UPDATE lab_specimens SET status = 'rejected' WHERE specimen_id = :id")->execute([':id' => $specimenId]);
        $pdo->prepare("UPDATE lab_results SET status = 'rejected' WHERE specimen_id = :id AND status = 'pending'")->execute([':id' => $specimenId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Hasil menunggu pada spesimen yang sudah diterima (untuk form input). */
function lab_enterable_results(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT r.lab_result_id, r.result_value, r.result_flag, s.barcode, s.specimen_type,
                lt.test_name, lt.unit, lt.reference_range
           FROM lab_results r
           JOIN lab_specimens s ON s.specimen_id = r.specimen_id
           JOIN master_lab_tests lt ON lt.lab_test_id = r.lab_test_id
          WHERE s.order_id = :o AND r.status = 'pending' AND s.status IN ('received','processed')
          ORDER BY r.lab_result_id"
    );
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

/**
 * Simpan nilai hasil (belum validasi). $values/$flags: [lab_result_id => teks]. Flag 'auto' = hitung dari nilai rujukan.
 * Semua-atau-tidak-sama-sekali. Hasil: [jumlah tersimpan, daftar error].
 */
function lab_save_results(int $orderId, array $values, array $flags): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare("SELECT status FROM orders WHERE order_id = :id AND order_type = 'lab' FOR UPDATE");
        $o->execute([':id' => $orderId]);
        if (!in_array($o->fetchColumn(), ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return [0, ['Order lab tidak aktif.']];
        }
        $rows = $pdo->prepare(
            "SELECT r.lab_result_id, lt.test_name, lt.reference_range
               FROM lab_results r
               JOIN lab_specimens s ON s.specimen_id = r.specimen_id
               JOIN master_lab_tests lt ON lt.lab_test_id = r.lab_test_id
              WHERE s.order_id = :o AND r.status = 'pending' AND s.status IN ('received','processed') FOR UPDATE"
        );
        $rows->execute([':o' => $orderId]);
        $upd = $pdo->prepare("UPDATE lab_results SET result_value = :v, result_flag = :f WHERE lab_result_id = :id AND status = 'pending'");
        $errors = [];
        $saved  = 0;
        foreach ($rows->fetchAll() as $r) {
            $id = (int) $r['lab_result_id'];
            if (!isset($values[$id]) || !is_string($values[$id])) {
                continue;
            }
            $val = trim($values[$id]);
            if ($val === '') {
                continue;
            }
            if (mb_strlen($val) > 100) {
                $errors[] = $r['test_name'] . ': nilai maksimal 100 karakter.';
                continue;
            }
            $f = is_string($flags[$id] ?? null) ? $flags[$id] : 'auto';
            if ($f === 'auto') {
                $f = lab_flag_auto($val, $r['reference_range']);
            } elseif ($f === '') {
                $f = null;
            } elseif (!array_key_exists($f, lab_flag_labels())) {
                $errors[] = $r['test_name'] . ': flag tidak valid.';
                continue;
            }
            $upd->bindValue(':v', $val);
            $upd->bindValue(':f', $f, $f === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $upd->bindValue(':id', $id, PDO::PARAM_INT);
            $upd->execute();
            $saved++;
        }
        if ($errors) {
            $pdo->rollBack();
            return [0, $errors];
        }
        $pdo->commit();
        return [$saved, []];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function lab_refresh_specimen(PDO $pdo, int $specimenId): void
{
    $c = $pdo->prepare("SELECT SUM(status = 'pending'), SUM(status = 'validated') FROM lab_results WHERE specimen_id = :s");
    $c->execute([':s' => $specimenId]);
    [$pending, $validated] = array_map('intval', $c->fetch(PDO::FETCH_NUM));
    if ($pending === 0 && $validated > 0) {
        $pdo->prepare("UPDATE lab_specimens SET status = 'processed' WHERE specimen_id = :s AND status = 'received'")->execute([':s' => $specimenId]);
    }
}

function lab_complete_if_done(PDO $pdo, int $orderId): void
{
    $c = $pdo->prepare(
        "SELECT COUNT(*) FROM order_items oi
          WHERE oi.order_id = :o AND oi.item_type = 'lab'
            AND NOT EXISTS (SELECT 1 FROM lab_results r WHERE r.order_item_id = oi.order_item_id AND r.status = 'validated')"
    );
    $c->execute([':o' => $orderId]);
    if ((int) $c->fetchColumn() === 0) {
        $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_id = :o AND status IN ('ordered','in_progress')")->execute([':o' => $orderId]);
    }
}

/** Validasi satu hasil (nilai harus sudah terisi). Hasil: null jika sukses, atau teks error. */
function lab_validate_result(int $resultId, int $userId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT r.status, r.result_value, s.specimen_id, s.status AS sstatus, s.order_id
               FROM lab_results r JOIN lab_specimens s ON s.specimen_id = r.specimen_id
              WHERE r.lab_result_id = :id FOR UPDATE'
        );
        $st->execute([':id' => $resultId]);
        $r = $st->fetch();
        if (!$r) {
            $pdo->rollBack();
            return 'Hasil tidak ditemukan.';
        }
        if ($r['status'] !== 'pending') {
            $pdo->rollBack();
            return 'Hanya hasil berstatus "Menunggu" yang dapat divalidasi.';
        }
        if ($r['result_value'] === null || trim((string) $r['result_value']) === '') {
            $pdo->rollBack();
            return 'Nilai hasil belum diisi.';
        }
        if (!in_array($r['sstatus'], ['received', 'processed'], true)) {
            $pdo->rollBack();
            return 'Spesimen belum diterima laboratorium.';
        }
        $pdo->prepare("UPDATE lab_results SET status = 'validated', validated_by = :u WHERE lab_result_id = :id")
            ->execute([':u' => $userId, ':id' => $resultId]);
        lab_refresh_specimen($pdo, (int) $r['specimen_id']);
        lab_complete_if_done($pdo, (int) $r['order_id']);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Tolak hasil yang masih menunggu (sampel perlu diambil ulang). */
function lab_reject_result(int $resultId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, specimen_id FROM lab_results WHERE lab_result_id = :id FOR UPDATE');
        $st->execute([':id' => $resultId]);
        $r = $st->fetch();
        if (!$r || $r['status'] !== 'pending') {
            $pdo->rollBack();
            return 'Hanya hasil berstatus "Menunggu" yang dapat ditolak.';
        }
        $pdo->prepare("UPDATE lab_results SET status = 'rejected' WHERE lab_result_id = :id")->execute([':id' => $resultId]);
        lab_refresh_specimen($pdo, (int) $r['specimen_id']);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** ID hasil berstatus pending dengan nilai terisi pada order (untuk "Validasi Semua"). */
function lab_ready_result_ids(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT r.lab_result_id FROM lab_results r JOIN lab_specimens s ON s.specimen_id = r.specimen_id
          WHERE s.order_id = :o AND r.status = 'pending' AND r.result_value IS NOT NULL AND r.result_value <> ''
            AND s.status IN ('received','processed') ORDER BY r.lab_result_id"
    );
    $stmt->execute([':o' => $orderId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function lab_result_order(int $resultId): ?int
{
    $stmt = db()->prepare('SELECT s.order_id FROM lab_results r JOIN lab_specimens s ON s.specimen_id = r.specimen_id WHERE r.lab_result_id = :id');
    $stmt->execute([':id' => $resultId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

function lab_specimen_order(int $specimenId): ?int
{
    $stmt = db()->prepare('SELECT order_id FROM lab_specimens WHERE specimen_id = :id');
    $stmt->execute([':id' => $specimenId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}