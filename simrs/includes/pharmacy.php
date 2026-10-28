<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Farmasi.
 * Memakai PRESCRIPTIONS, PRESCRIPTION_ITEMS, DISPENSING_TRANSACTIONS, MASTER_DRUGS, INVENTORY_ITEMS,
 * STOCK_TRANSACTIONS, WAREHOUSES. Halaman wajib memuat includes/registration.php (reg_allergies) lebih dulu.
 *
 * Aturan stok: stok tersedia = batch STOCK_BATCHES berstatus 'available', jumlah > 0, belum kedaluwarsa.
 * Penyerahan obat mengurangi batch secara FEFO (kedaluwarsa terdekat dahulu) dan menulis satu baris buku besar
 * STOCK_TRANSACTIONS bernilai negatif (txn_type 'dispense').
 */

require_once __DIR__ . '/inventory.php';

function ph_status_labels(): array
{
    return [
        'new'       => ['Baru', 'cp-info'], 'prepared'  => ['Disiapkan', 'cp-warn'],
        'dispensed' => ['Diserahkan', 'cp-ok'], 'cancelled' => ['Dibatalkan', 'cp-off'],
    ];
}

/** Tampilkan angka desimal tanpa nol di belakang (10,50 -> 10,5; 10,00 -> 10). */
function ph_fmt(float $v): string
{
    $s = number_format($v, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}

function ph_queue(string $mode): array
{
    $done = $mode === 'done';
    $cond = $done ? "p.status IN ('dispensed','cancelled') AND o.ordered_at >= :sejak" : "p.status IN ('new','prepared')";
    $stmt = db()->prepare(
        "SELECT p.prescription_id, p.status, o.order_id, o.priority, o.ordered_at, pt.no_rm, pt.nama_pasien, md.doctor_name,
                (SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id = p.prescription_id) AS jml_item
           FROM prescriptions p
           JOIN orders o ON o.order_id = p.order_id
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients pt ON pt.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = p.doctor_id
          WHERE $cond
          ORDER BY o.priority = 'cito' DESC, o.ordered_at LIMIT 200"
    );
    $stmt->execute($done ? [':sejak' => date('Y-m-d H:i:s', strtotime('-7 days'))] : []);
    return $stmt->fetchAll();
}

function ph_find(int $rxId): ?array
{
    $stmt = db()->prepare(
        "SELECT p.prescription_id, p.status, p.order_id, o.visit_id, o.priority, o.status AS order_status, o.ordered_at, o.clinical_reason,
                pt.patient_id, pt.no_rm, pt.nama_pasien, pt.tanggal_lahir, pt.jenis_kelamin, md.doctor_name
           FROM prescriptions p
           JOIN orders o ON o.order_id = p.order_id
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients pt ON pt.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = p.doctor_id
          WHERE p.prescription_id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $rxId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function ph_warehouses(): array
{
    return db()->query('SELECT warehouse_id, warehouse_name FROM warehouses ORDER BY warehouse_id')->fetchAll();
}

/** Pilih gudang: yang diminta jika ada, kalau tidak gudang pertama. 0 bila belum ada gudang. */
function ph_pick_warehouse(array $warehouses, int $requested): int
{
    foreach ($warehouses as $w) {
        if ((int) $w['warehouse_id'] === $requested) {
            return $requested;
        }
    }
    return $warehouses ? (int) $warehouses[0]['warehouse_id'] : 0;
}

/** Item resep beserta jumlah yang sudah diserahkan, sisa, dan stok di gudang tertentu. Jumlah dalam angka dan sen (x100). */
function ph_items(int $rxId, int $warehouseId): array
{
    $stmt = db()->prepare(
        "SELECT pi.prescription_item_id, pi.dose, pi.route, pi.frequency, pi.quantity, oi.instruction,
                d.drug_name, d.dosage_form, d.strength, ii.inventory_item_id, ii.item_name, ii.unit, ii.min_stock,
                COALESCE((SELECT SUM(dt.quantity_dispensed) FROM dispensing_transactions dt WHERE dt.prescription_item_id = pi.prescription_item_id), 0) AS dispensed,
                COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_batches sb WHERE sb.inventory_item_id = ii.inventory_item_id AND sb.warehouse_id = :w
                           AND sb.status = 'available' AND sb.quantity_on_hand > 0 AND (sb.expiry_date IS NULL OR sb.expiry_date >= :today)), 0) AS stock
           FROM prescription_items pi
           JOIN order_items oi ON oi.order_item_id = pi.order_item_id
           JOIN master_drugs d ON d.drug_id = oi.drug_id
           JOIN inventory_items ii ON ii.inventory_item_id = d.inventory_item_id
          WHERE pi.prescription_id = :p
          ORDER BY pi.prescription_item_id"
    );
    $stmt->execute([':w' => $warehouseId, ':today' => date('Y-m-d'), ':p' => $rxId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['ordered_c']   = (int) round((float) $r['quantity'] * 100);
        $r['dispensed_c'] = (int) round((float) $r['dispensed'] * 100);
        $r['remaining_c'] = max(0, $r['ordered_c'] - $r['dispensed_c']);
        $r['stock_c']     = (int) round((float) $r['stock'] * 100);
    }
    return $rows;
}

/** Riwayat penyerahan obat satu resep. */
function ph_history(int $rxId): array
{
    $stmt = db()->prepare(
        'SELECT dt.dispense_id, dt.quantity_dispensed, dt.dispensed_at, d.drug_name, ii.unit, u.nama AS pharmacist_name
           FROM dispensing_transactions dt
           JOIN prescription_items pi ON pi.prescription_item_id = dt.prescription_item_id
           JOIN order_items oi ON oi.order_item_id = pi.order_item_id
           JOIN master_drugs d ON d.drug_id = oi.drug_id
           JOIN inventory_items ii ON ii.inventory_item_id = d.inventory_item_id
           LEFT JOIN users u ON u.user_id = dt.pharmacist_id
          WHERE pi.prescription_id = :p
          ORDER BY dt.dispense_id'
    );
    $stmt->execute([':p' => $rxId]);
    return $stmt->fetchAll();
}

/**
 * Cocokkan alergi pasien dengan nama obat (kecocokan teks sebagian, tanpa memperhatikan huruf besar/kecil).
 * Hasil: [prescription_item_id => [alergen, ...]]. Alergi golongan obat tidak terdeteksi otomatis; apoteker tetap memeriksa manual.
 */
function ph_allergy_matches(array $allergies, array $items): array
{
    $out = [];
    foreach ($items as $it) {
        $names = array_filter([mb_strtolower(trim((string) $it['drug_name'])), mb_strtolower(trim((string) $it['item_name']))], fn($n) => $n !== '');
        foreach ($allergies as $al) {
            $a = mb_strtolower(trim((string) $al['allergen']));
            if ($a === '') {
                continue;
            }
            foreach ($names as $n) {
                if (str_contains($n, $a) || str_contains($a, $n)) {
                    $out[(int) $it['prescription_item_id']][] = (string) $al['allergen'];
                    break;
                }
            }
        }
    }
    return $out;
}

/** Kunci resep dan order-nya. Hasil: [status resep, order_id, status order] atau null. */
function ph_lock(PDO $pdo, int $rxId): ?array
{
    $st = $pdo->prepare('SELECT status, order_id FROM prescriptions WHERE prescription_id = :p FOR UPDATE');
    $st->execute([':p' => $rxId]);
    $rx = $st->fetch();
    if ($rx === false) {
        return null;
    }
    $o = $pdo->prepare('SELECT status FROM orders WHERE order_id = :o FOR UPDATE');
    $o->execute([':o' => $rx['order_id']]);
    $os = $o->fetchColumn();
    return [(string) $rx['status'], (int) $rx['order_id'], $os === false ? '' : (string) $os];
}

function ph_patient_allergy_matches(PDO $pdo, int $orderId, array $items): array
{
    $st = $pdo->prepare('SELECT v.patient_id FROM orders o JOIN visits v ON v.visit_id = o.visit_id WHERE o.order_id = :o');
    $st->execute([':o' => $orderId]);
    $pid = (int) $st->fetchColumn();
    return ph_allergy_matches(reg_allergies($pid), $items);
}

/** Verifikasi dan siapkan resep: Baru -> Disiapkan. Hasil: null jika sukses, atau teks error. */
function ph_prepare(int $rxId, bool $allergyAck): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lk = ph_lock($pdo, $rxId);
        if ($lk === null) {
            $pdo->rollBack();
            return 'Resep tidak ditemukan.';
        }
        [$status, $orderId, $orderStatus] = $lk;
        if ($status !== 'new' || !in_array($orderStatus, ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Hanya resep berstatus "Baru" yang dapat disiapkan.';
        }
        $items = ph_items($rxId, 0);
        if (!$items) {
            $pdo->rollBack();
            return 'Resep tidak memiliki item obat.';
        }
        if (ph_patient_allergy_matches($pdo, $orderId, $items) && !$allergyAck) {
            $pdo->rollBack();
            return 'Ada obat yang namanya cocok dengan alergi pasien. Centang konfirmasi alergi setelah memeriksa ulang.';
        }
        $pdo->prepare("UPDATE prescriptions SET status = 'prepared' WHERE prescription_id = :p")->execute([':p' => $rxId]);
        $pdo->prepare("UPDATE orders SET status = 'in_progress' WHERE order_id = :o AND status = 'ordered'")->execute([':o' => $orderId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Batalkan resep bila belum ada obat yang diserahkan. Hasil: null jika sukses, atau teks error. */
function ph_cancel(int $rxId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lk = ph_lock($pdo, $rxId);
        if ($lk === null) {
            $pdo->rollBack();
            return 'Resep tidak ditemukan.';
        }
        [$status, $orderId, ] = $lk;
        if (!in_array($status, ['new', 'prepared'], true)) {
            $pdo->rollBack();
            return 'Resep ini tidak dapat dibatalkan.';
        }
        $c = $pdo->prepare(
            'SELECT COUNT(*) FROM dispensing_transactions dt JOIN prescription_items pi ON pi.prescription_item_id = dt.prescription_item_id WHERE pi.prescription_id = :p'
        );
        $c->execute([':p' => $rxId]);
        if ((int) $c->fetchColumn() > 0) {
            $pdo->rollBack();
            return 'Sebagian obat sudah diserahkan, resep tidak dapat dibatalkan.';
        }
        $pdo->prepare("UPDATE prescriptions SET status = 'cancelled' WHERE prescription_id = :p")->execute([':p' => $rxId]);
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = :o AND status IN ('ordered','in_progress')")->execute([':o' => $orderId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Validasi bentuk jumlah penyerahan. Hasil: [id_item => 'jumlah 2 desimal', ...] dan daftar error. Item tanpa isi dianggap 0. */
function ph_validate_dispense(array $in, array $items): array
{
    $errors = [];
    $lines  = [];
    $qty    = is_array($in['qty'] ?? null) ? $in['qty'] : [];
    foreach ($items as $it) {
        if ($it['remaining_c'] <= 0) {
            continue;
        }
        $id  = (int) $it['prescription_item_id'];
        $raw = is_string($qty[$id] ?? null) ? trim($qty[$id]) : '';
        if ($raw === '') {
            continue;
        }
        if (!preg_match('/^\d{1,8}([.,]\d{1,2})?$/', $raw)) {
            $errors[] = 'Jumlah ' . $it['drug_name'] . ' tidak valid (angka, maksimal 2 desimal).';
            continue;
        }
        $c = (int) round((float) str_replace(',', '.', $raw) * 100);
        if ($c <= 0) {
            continue;
        }
        if ($c > $it['remaining_c']) {
            $errors[] = 'Jumlah ' . $it['drug_name'] . ' melebihi sisa resep (' . ph_fmt($it['remaining_c'] / 100) . ').';
            continue;
        }
        $lines[$id] = number_format($c / 100, 2, '.', '');
    }
    if (!$errors && !$lines) {
        $errors[] = 'Isi jumlah penyerahan minimal satu obat.';
    }
    return [$lines, $errors];
}

/**
 * Serahkan obat: tulis DISPENSING_TRANSACTIONS dan kurangi stok gudang (STOCK_TRANSACTIONS bernilai negatif).
 * Semua atau tidak sama sekali. Hasil: [error|null, resepSelesai].
 */
function ph_dispense(int $rxId, int $warehouseId, array $lines, int $userId, bool $allergyAck): array
{
    if (!$lines) {
        return ['Isi jumlah penyerahan minimal satu obat.', false];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lk = ph_lock($pdo, $rxId);
        if ($lk === null) {
            $pdo->rollBack();
            return ['Resep tidak ditemukan.', false];
        }
        [$status, $orderId, $orderStatus] = $lk;
        if ($status !== 'prepared' || !in_array($orderStatus, ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return ['Obat hanya dapat diserahkan untuk resep berstatus "Disiapkan".', false];
        }
        $w = $pdo->prepare('SELECT COUNT(*) FROM warehouses WHERE warehouse_id = :w');
        $w->execute([':w' => $warehouseId]);
        if ((int) $w->fetchColumn() === 0) {
            $pdo->rollBack();
            return ['Gudang tidak valid.', false];
        }
        // Kunci barang yang terlibat agar dua penyerahan tidak membaca stok yang sama.
        $pdo->prepare(
            'SELECT ii.inventory_item_id FROM inventory_items ii
              WHERE ii.inventory_item_id IN (
                    SELECT d.inventory_item_id FROM prescription_items pi
                      JOIN order_items oi ON oi.order_item_id = pi.order_item_id
                      JOIN master_drugs d ON d.drug_id = oi.drug_id
                     WHERE pi.prescription_id = :p)
              ORDER BY ii.inventory_item_id FOR UPDATE'
        )->execute([':p' => $rxId]);

        $items = ph_items($rxId, $warehouseId);
        $by = [];
        foreach ($items as $it) {
            $by[(int) $it['prescription_item_id']] = $it;
        }
        if (ph_patient_allergy_matches($pdo, $orderId, $items) && !$allergyAck) {
            $pdo->rollBack();
            return ['Ada obat yang namanya cocok dengan alergi pasien. Centang konfirmasi alergi setelah memeriksa ulang.', false];
        }

        $avail = [];
        foreach ($items as $it) {
            $avail[(int) $it['inventory_item_id']] = $it['stock_c'];
        }
        $plan = [];
        foreach ($lines as $itemId => $q) {
            $itemId = (int) $itemId;
            if (!isset($by[$itemId])) {
                $pdo->rollBack();
                return ['Item resep tidak ditemukan pada resep ini.', false];
            }
            $it = $by[$itemId];
            $c  = (int) round((float) $q * 100);
            if ($c <= 0 || $c > $it['remaining_c']) {
                $pdo->rollBack();
                return ['Jumlah ' . $it['drug_name'] . ' melebihi sisa resep.', false];
            }
            $inv = (int) $it['inventory_item_id'];
            if ($c > $avail[$inv]) {
                $pdo->rollBack();
                return ['Stok ' . $it['drug_name'] . ' tidak cukup. Tersedia ' . ph_fmt($avail[$inv] / 100) . ' ' . $it['unit'] . '.', false];
            }
            $avail[$inv] -= $c;
            $plan[] = [$itemId, $inv, $c];
        }

        $now = date('Y-m-d H:i:s');
        foreach ($plan as [$itemId, $inv, $c]) {
            if (!inv_take_fefo($pdo, $inv, $warehouseId, $c)) {
                $pdo->rollBack();
                return ['Stok batch tidak mencukupi. Muat ulang halaman lalu coba lagi.', false];
            }
            $qty = number_format($c / 100, 2, '.', '');
            $pdo->prepare('INSERT INTO dispensing_transactions (prescription_item_id, pharmacist_id, quantity_dispensed, dispensed_at) VALUES (:i, :u, :q, :t)')
                ->execute([':i' => $itemId, ':u' => $userId, ':q' => $qty, ':t' => $now]);
            $dispId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO stock_transactions (inventory_item_id, warehouse_id, user_id, txn_type, quantity, reference_no, txn_at) VALUES (:i, :w, :u, 'dispense', :q, :r, :t)")
                ->execute([':i' => $inv, ':w' => $warehouseId, ':u' => $userId, ':q' => '-' . $qty, ':r' => 'DSP-' . $dispId, ':t' => $now]);
            $by[$itemId]['remaining_c'] -= $c;
        }

        $done = true;
        foreach ($by as $it) {
            if ($it['remaining_c'] > 0) {
                $done = false;
                break;
            }
        }
        if ($done) {
            $pdo->prepare("UPDATE prescriptions SET status = 'dispensed' WHERE prescription_id = :p")->execute([':p' => $rxId]);
            $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_id = :o AND status IN ('ordered','in_progress')")->execute([':o' => $orderId]);
        }
        $pdo->commit();
        return [null, $done];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Obat pada satu kunjungan (resep tidak dibatalkan) beserta jumlah yang sudah diserahkan, untuk EMR. */
function ph_visit_drugs(int $visitId): array
{
    $stmt = db()->prepare(
        "SELECT p.prescription_id, p.status, o.order_id, o.ordered_at, d.drug_name, d.dosage_form, d.strength, ii.unit,
                pi.dose, pi.route, pi.frequency, pi.quantity,
                COALESCE((SELECT SUM(dt.quantity_dispensed) FROM dispensing_transactions dt WHERE dt.prescription_item_id = pi.prescription_item_id), 0) AS dispensed
           FROM prescriptions p
           JOIN orders o ON o.order_id = p.order_id
           JOIN prescription_items pi ON pi.prescription_id = p.prescription_id
           JOIN order_items oi ON oi.order_item_id = pi.order_item_id
           JOIN master_drugs d ON d.drug_id = oi.drug_id
           JOIN inventory_items ii ON ii.inventory_item_id = d.inventory_item_id
          WHERE o.visit_id = :v AND p.status <> 'cancelled'
          ORDER BY o.order_id DESC, pi.prescription_item_id"
    );
    $stmt->execute([':v' => $visitId]);
    return $stmt->fetchAll();
}