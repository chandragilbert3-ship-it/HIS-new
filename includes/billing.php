<?php
declare(strict_types=1);

/**
 * Fungsi bantu Modul 13 Billing & Insurance.
 * Tabel: BILLING_INVOICES, BILLING_INVOICE_ITEMS, MASTER_TARIFFS, PAYERS, PATIENT_INSURANCE, PAYMENTS, CLAIMS.
 * Semua uang diproses dalam sen (bilangan bulat x100) agar tidak ada galat pembulatan.
 */

const BL_METHODS = ['Tunai', 'Transfer Bank', 'Kartu Debit', 'QRIS'];

function bl_invoice_status_labels(): array
{
    return ['draft' => ['Draft', 'cp-info'], 'issued' => ['Diterbitkan', 'cp-warn'], 'cancelled' => ['Dibatalkan', 'cp-off']];
}

function bl_billing_status_labels(): array
{
    return ['unpaid' => ['Belum bayar', 'cp-off'], 'partial' => ['Sebagian', 'cp-warn'], 'paid' => ['Lunas', 'cp-ok']];
}

function bl_claim_status_labels(): array
{
    return [
        'draft' => ['Draft', 'cp-info'], 'submitted' => ['Diajukan', 'cp-warn'], 'verified' => ['Terverifikasi', 'cp-info'],
        'paid' => ['Dibayar', 'cp-ok'], 'rejected' => ['Ditolak', 'cp-off'],
    ];
}

/** "15000" / "15000,50" / "15000.50" -> sen; null jika tidak valid. Tanpa pemisah ribuan. */
function bl_parse(string $s): ?int
{
    $s = trim($s);
    if (!preg_match('/^(\d{1,12})(?:[.,](\d{1,2}))?$/', $s, $m)) {
        return null;
    }
    return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
}

function bl_input(int $c): string
{
    return $c % 100 === 0 ? (string) intdiv($c, 100) : sprintf('%d,%02d', intdiv($c, 100), $c % 100);
}

function bl_rp(int $c): string
{
    return 'Rp ' . number_format($c / 100, $c % 100 === 0 ? 0 : 2, ',', '.');
}

function bl_qty(int $c): string
{
    return number_format($c / 100, $c % 100 === 0 ? 0 : 2, ',', '.');
}

function bl_tabs(string $active): void
{
    $tabs = [
        'index'  => ['Tagihan', 'index.php'],
        'claims' => ['Klaim Asuransi', 'claims.php'],
    ];
    echo '<div class="cp-tabs">';
    foreach ($tabs as $key => [$label, $file]) {
        if (!is_file(APP_ROOT . '/modules/billing/' . $file)) {
            continue;
        }
        echo '<a class="' . ($key === $active ? 'active' : '') . '" href="' . e(url('modules/billing/' . $file)) . '">' . e($label) . '</a>';
    }
    echo '</div>';
}

/* ---------- Referensi ---------- */

function bl_payers(): array
{
    return db()->query('SELECT payer_id, payer_name, payer_type FROM payers WHERE is_active = 1 ORDER BY payer_id')->fetchAll();
}

function bl_tariffs(): array
{
    return db()->query('SELECT tariff_id, service_code, service_name, ROUND(amount * 100) AS amount_c
                          FROM master_tariffs WHERE is_active = 1 ORDER BY service_code')->fetchAll();
}

/** Kunjungan yang belum punya tagihan aktif (draft/diterbitkan). */
function bl_eligible_visits(): array
{
    return db()->query("SELECT v.visit_id, v.visit_date, p.no_rm, p.nama_pasien
                          FROM visits v JOIN patients p ON p.patient_id = v.patient_id
                         WHERE NOT EXISTS (SELECT 1 FROM billing_invoices i
                                            WHERE i.visit_id = v.visit_id AND i.invoice_status <> 'cancelled')
                         ORDER BY v.visit_date DESC, v.visit_id DESC LIMIT 100")->fetchAll();
}

/* ---------- Baca ---------- */

function bl_list(string $q, string $status): array
{
    $sql = "SELECT i.invoice_id, i.invoice_no, i.invoice_status, i.billing_status, i.visit_id,
                   ROUND(i.gross_amount * 100) AS gross_c, ROUND(i.payer_due * 100) AS payer_c, ROUND(i.patient_due * 100) AS patient_c,
                   p.no_rm, p.nama_pasien, py.payer_name, v.visit_date,
                   (SELECT COALESCE(ROUND(SUM(pm.amount) * 100), 0) FROM payments pm WHERE pm.invoice_id = i.invoice_id AND pm.payment_status = 'paid') AS paid_c
              FROM billing_invoices i
              JOIN visits v ON v.visit_id = i.visit_id
              JOIN patients p ON p.patient_id = v.patient_id
              JOIN payers py ON py.payer_id = i.payer_id
             WHERE 1 = 1";
    $par = [];
    if ($q !== '') {
        $sql .= ' AND (i.invoice_no LIKE :q1 OR p.no_rm LIKE :q2 OR p.nama_pasien LIKE :q3)';
        $par[':q1'] = $par[':q2'] = $par[':q3'] = '%' . $q . '%';
    }
    if (in_array($status, ['draft', 'issued', 'cancelled'], true)) {
        $sql .= ' AND i.invoice_status = :st';
        $par[':st'] = $status;
    }
    $st = db()->prepare($sql . ' ORDER BY i.invoice_id DESC LIMIT 200');
    $st->execute($par);
    return $st->fetchAll();
}

function bl_find(int $id): ?array
{
    $st = db()->prepare("SELECT i.*, ROUND(i.gross_amount * 100) AS gross_c, ROUND(i.payer_due * 100) AS payer_c, ROUND(i.patient_due * 100) AS patient_c,
                                p.patient_id, p.no_rm, p.nama_pasien, py.payer_name, py.payer_type, v.visit_date
                           FROM billing_invoices i
                           JOIN visits v ON v.visit_id = i.visit_id
                           JOIN patients p ON p.patient_id = v.patient_id
                           JOIN payers py ON py.payer_id = i.payer_id
                          WHERE i.invoice_id = :id");
    $st->execute([':id' => $id]);
    $r = $st->fetch();
    return $r ?: null;
}

function bl_items(int $id): array
{
    $st = db()->prepare("SELECT it.invoice_item_id, it.quantity, ROUND(it.quantity * 100) AS qty_c, ROUND(it.subtotal * 100) AS sub_c,
                                t.service_code, t.service_name, ROUND(t.amount * 100) AS unit_c
                           FROM billing_invoice_items it JOIN master_tariffs t ON t.tariff_id = it.tariff_id
                          WHERE it.invoice_id = :id ORDER BY it.invoice_item_id");
    $st->execute([':id' => $id]);
    return $st->fetchAll();
}

function bl_payments(int $id): array
{
    $st = db()->prepare("SELECT pm.payment_id, pm.transaction_no, pm.payment_method, pm.payment_status, ROUND(pm.amount * 100) AS amount_c, u.nama AS user_name
                           FROM payments pm LEFT JOIN users u ON u.user_id = pm.received_by
                          WHERE pm.invoice_id = :id ORDER BY pm.payment_id");
    $st->execute([':id' => $id]);
    return $st->fetchAll();
}

function bl_paid_c(int $id): int
{
    $st = db()->prepare("SELECT COALESCE(ROUND(SUM(amount) * 100), 0) FROM payments WHERE invoice_id = :id AND payment_status = 'paid'");
    $st->execute([':id' => $id]);
    return (int) $st->fetchColumn();
}

function bl_claim_of(int $invoiceId): ?array
{
    $st = db()->prepare('SELECT claim_id, claim_no, claim_status, ROUND(claim_amount * 100) AS amount_c FROM claims WHERE invoice_id = :id ORDER BY claim_id DESC LIMIT 1');
    $st->execute([':id' => $invoiceId]);
    $r = $st->fetch();
    return $r ?: null;
}

function bl_claims_list(string $status): array
{
    $sql = "SELECT c.claim_id, c.claim_no, c.claim_status, ROUND(c.claim_amount * 100) AS amount_c, c.invoice_id,
                   i.invoice_no, p.no_rm, p.nama_pasien, py.payer_name
              FROM claims c
              JOIN billing_invoices i ON i.invoice_id = c.invoice_id
              JOIN visits v ON v.visit_id = i.visit_id
              JOIN patients p ON p.patient_id = v.patient_id
              JOIN payers py ON py.payer_id = c.payer_id";
    $par = [];
    if (array_key_exists($status, bl_claim_status_labels())) {
        $sql .= ' WHERE c.claim_status = :s';
        $par[':s'] = $status;
    }
    $st = db()->prepare($sql . ' ORDER BY c.claim_id DESC LIMIT 200');
    $st->execute($par);
    return $st->fetchAll();
}

/* ---------- Tulis (semua dalam transaksi) ---------- */

/** Jalankan $fn dalam transaksi. $fn mengembalikan null (sukses) atau teks error. */
function bl_tx(callable $fn): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $err = $fn($pdo);
        if ($err !== null) {
            $pdo->rollBack();
            return $err;
        }
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return 'Terjadi kesalahan database. Coba lagi.';
    }
}

/** Kunci baris tagihan; null jika tidak ada. */
function bl_lock(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT invoice_id, invoice_status, billing_status, visit_id, payer_id,
                                ROUND(gross_amount * 100) AS gross_c, ROUND(payer_due * 100) AS payer_c, ROUND(patient_due * 100) AS patient_c
                           FROM billing_invoices WHERE invoice_id = :id FOR UPDATE');
    $st->execute([':id' => $id]);
    $r = $st->fetch();
    return $r ?: null;
}

function bl_recalc_gross(PDO $pdo, int $id): void
{
    $st = $pdo->prepare('UPDATE billing_invoices SET gross_amount = (SELECT COALESCE(SUM(subtotal), 0) FROM billing_invoice_items WHERE invoice_id = :a) WHERE invoice_id = :b');
    $st->execute([':a' => $id, ':b' => $id]);
}

/** Hitung ulang status pembayaran (di dalam transaksi, baris tagihan sudah dikunci). */
function bl_refresh_status(PDO $pdo, int $id): void
{
    $st = $pdo->prepare("SELECT COALESCE(ROUND(SUM(amount) * 100), 0) FROM payments WHERE invoice_id = :id AND payment_status = 'paid'");
    $st->execute([':id' => $id]);
    $paid = (int) $st->fetchColumn();

    $st = $pdo->prepare('SELECT ROUND(payer_due * 100) AS payer_c, ROUND(patient_due * 100) AS patient_c FROM billing_invoices WHERE invoice_id = :id');
    $st->execute([':id' => $id]);
    $inv = $st->fetch();

    $st = $pdo->prepare('SELECT claim_status FROM claims WHERE invoice_id = :id ORDER BY claim_id DESC LIMIT 1');
    $st->execute([':id' => $id]);
    $claim = $st->fetchColumn();

    $patientDone = $paid >= (int) $inv['patient_c'];
    $claimDone   = (int) $inv['payer_c'] === 0 || $claim === 'paid';
    if ($patientDone && $claimDone) {
        $new = 'paid';
    } elseif ($paid > 0 || ($claim === 'paid' && (int) $inv['payer_c'] > 0)) {
        $new = 'partial';
    } else {
        $new = 'unpaid';
    }
    $st = $pdo->prepare('UPDATE billing_invoices SET billing_status = :s WHERE invoice_id = :id');
    $st->execute([':s' => $new, ':id' => $id]);
}

/** Buat tagihan draft untuk satu kunjungan. Return [error, invoice_id]. */
function bl_create(int $visitId, int $payerId): array
{
    $newId = 0;
    $err = bl_tx(function (PDO $pdo) use ($visitId, $payerId, &$newId) {
        $st = $pdo->prepare('SELECT visit_id FROM visits WHERE visit_id = :id FOR UPDATE');
        $st->execute([':id' => $visitId]);
        if (!$st->fetch()) {
            return 'Kunjungan tidak ditemukan.';
        }
        $st = $pdo->prepare('SELECT payer_id FROM payers WHERE payer_id = :id AND is_active = 1');
        $st->execute([':id' => $payerId]);
        if (!$st->fetch()) {
            return 'Penjamin tidak valid.';
        }
        $st = $pdo->prepare("SELECT COUNT(*) FROM billing_invoices WHERE visit_id = :v AND invoice_status <> 'cancelled'");
        $st->execute([':v' => $visitId]);
        if ((int) $st->fetchColumn() > 0) {
            return 'Kunjungan ini sudah memiliki tagihan aktif.';
        }
        $tmp = 'TMP' . bin2hex(random_bytes(8));
        $st = $pdo->prepare("INSERT INTO billing_invoices (visit_id, payer_id, invoice_no, gross_amount, payer_due, patient_due, invoice_status, billing_status)
                             VALUES (:v, :p, :n, 0, 0, 0, 'draft', 'unpaid')");
        $st->execute([':v' => $visitId, ':p' => $payerId, ':n' => $tmp]);
        $newId = (int) $pdo->lastInsertId();
        $st = $pdo->prepare('UPDATE billing_invoices SET invoice_no = :n WHERE invoice_id = :id');
        $st->execute([':n' => 'INV-' . date('Ymd') . '-' . str_pad((string) $newId, 5, '0', STR_PAD_LEFT), ':id' => $newId]);
        return null;
    });
    return [$err, $newId];
}

function bl_add_item(int $invoiceId, int $tariffId, int $qtyC): ?string
{
    if ($qtyC < 1 || $qtyC > 999999) {
        return 'Jumlah tidak valid (0,01 - 9999).';
    }
    return bl_tx(function (PDO $pdo) use ($invoiceId, $tariffId, $qtyC) {
        $inv = bl_lock($pdo, $invoiceId);
        if (!$inv || $inv['invoice_status'] !== 'draft') {
            return 'Layanan hanya dapat ditambah pada tagihan berstatus Draft.';
        }
        $st = $pdo->prepare('SELECT ROUND(amount * 100) FROM master_tariffs WHERE tariff_id = :id AND is_active = 1');
        $st->execute([':id' => $tariffId]);
        $unit = $st->fetchColumn();
        if ($unit === false) {
            return 'Tarif tidak ditemukan atau nonaktif.';
        }
        $subC = (int) round(((int) $unit) * $qtyC / 100);
        $st = $pdo->prepare("INSERT INTO billing_invoice_items (invoice_id, source_module, source_item_id, tariff_id, quantity, subtotal)
                             VALUES (:i, 'manual', 0, :t, :q, :s)");
        $st->execute([':i' => $invoiceId, ':t' => $tariffId, ':q' => number_format($qtyC / 100, 2, '.', ''), ':s' => number_format($subC / 100, 2, '.', '')]);
        bl_recalc_gross($pdo, $invoiceId);
        return null;
    });
}

/** Baris tagihan draft boleh dihapus (data keuangan draft, bukan data medis). */
function bl_remove_item(int $invoiceId, int $itemId): ?string
{
    return bl_tx(function (PDO $pdo) use ($invoiceId, $itemId) {
        $inv = bl_lock($pdo, $invoiceId);
        if (!$inv || $inv['invoice_status'] !== 'draft') {
            return 'Baris hanya dapat dihapus pada tagihan berstatus Draft.';
        }
        $st = $pdo->prepare('DELETE FROM billing_invoice_items WHERE invoice_item_id = :i AND invoice_id = :v');
        $st->execute([':i' => $itemId, ':v' => $invoiceId]);
        bl_recalc_gross($pdo, $invoiceId);
        return null;
    });
}

/** Terbitkan tagihan. $coveragePct = persen ditanggung penjamin (0-100). */
function bl_issue(int $invoiceId, int $coveragePct): ?string
{
    return bl_tx(function (PDO $pdo) use ($invoiceId, $coveragePct) {
        $inv = bl_lock($pdo, $invoiceId);
        if (!$inv || $inv['invoice_status'] !== 'draft') {
            return 'Hanya tagihan Draft yang dapat diterbitkan.';
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM billing_invoice_items WHERE invoice_id = :id');
        $st->execute([':id' => $invoiceId]);
        if ((int) $st->fetchColumn() === 0) {
            return 'Tambahkan minimal satu layanan sebelum menerbitkan.';
        }
        bl_recalc_gross($pdo, $invoiceId);
        $st = $pdo->prepare('SELECT ROUND(i.gross_amount * 100) AS gross_c, py.payer_type, v.patient_id
                               FROM billing_invoices i JOIN payers py ON py.payer_id = i.payer_id JOIN visits v ON v.visit_id = i.visit_id
                              WHERE i.invoice_id = :id');
        $st->execute([':id' => $invoiceId]);
        $row = $st->fetch();
        $gross = (int) $row['gross_c'];

        if ($row['payer_type'] === 'self') {
            $coveragePct = 0;
        } else {
            if ($coveragePct < 0 || $coveragePct > 100) {
                return 'Persentase ditanggung penjamin harus 0-100.';
            }
            $st = $pdo->prepare("SELECT COUNT(*) FROM patient_insurance
                                  WHERE patient_id = :pt AND payer_id = :py AND verification_status = 'verified'
                                    AND (valid_until IS NULL OR valid_until >= :d)");
            $st->execute([':pt' => $row['patient_id'], ':py' => $inv['payer_id'], ':d' => date('Y-m-d')]);
            if ((int) $st->fetchColumn() === 0) {
                return 'Pasien belum memiliki kepesertaan asuransi yang terverifikasi dan masih berlaku untuk penjamin ini.';
            }
        }
        $payer   = (int) round($gross * $coveragePct / 100);
        $patient = $gross - $payer;
        $st = $pdo->prepare("UPDATE billing_invoices SET invoice_status = 'issued', payer_due = :pd, patient_due = :pt WHERE invoice_id = :id");
        $st->execute([':pd' => number_format($payer / 100, 2, '.', ''), ':pt' => number_format($patient / 100, 2, '.', ''), ':id' => $invoiceId]);

        if ($payer > 0) {
            $tmp = 'TMP' . bin2hex(random_bytes(8));
            $st = $pdo->prepare("INSERT INTO claims (invoice_id, payer_id, claim_no, claim_amount, claim_status) VALUES (:i, :p, :n, :a, 'draft')");
            $st->execute([':i' => $invoiceId, ':p' => $inv['payer_id'], ':n' => $tmp, ':a' => number_format($payer / 100, 2, '.', '')]);
            $cid = (int) $pdo->lastInsertId();
            $st = $pdo->prepare('UPDATE claims SET claim_no = :n WHERE claim_id = :id');
            $st->execute([':n' => 'CLM-' . date('Ymd') . '-' . str_pad((string) $cid, 5, '0', STR_PAD_LEFT), ':id' => $cid]);
        }
        bl_refresh_status($pdo, $invoiceId);
        return null;
    });
}

/** Catat pembayaran bagian pasien. Return [error, payment_id]. */
function bl_pay(int $invoiceId, string $method, int $amountC, int $userId): array
{
    $pid = 0;
    $err = bl_tx(function (PDO $pdo) use ($invoiceId, $method, $amountC, $userId, &$pid) {
        if (!in_array($method, BL_METHODS, true)) {
            return 'Metode pembayaran tidak valid.';
        }
        $inv = bl_lock($pdo, $invoiceId);
        if (!$inv || $inv['invoice_status'] !== 'issued') {
            return 'Pembayaran hanya dapat dicatat pada tagihan yang sudah diterbitkan.';
        }
        $st = $pdo->prepare("SELECT COALESCE(ROUND(SUM(amount) * 100), 0) FROM payments WHERE invoice_id = :id AND payment_status = 'paid'");
        $st->execute([':id' => $invoiceId]);
        $remain = (int) $inv['patient_c'] - (int) $st->fetchColumn();
        if ($amountC < 1) {
            return 'Jumlah pembayaran harus lebih dari nol.';
        }
        if ($amountC > $remain) {
            return 'Jumlah melebihi sisa tagihan pasien (' . bl_rp(max(0, $remain)) . ').';
        }
        $tmp = 'TMP' . bin2hex(random_bytes(8));
        $st = $pdo->prepare("INSERT INTO payments (invoice_id, received_by, transaction_no, payment_method, amount, payment_status)
                             VALUES (:i, :u, :t, :m, :a, 'paid')");
        $st->execute([':i' => $invoiceId, ':u' => $userId, ':t' => $tmp, ':m' => $method, ':a' => number_format($amountC / 100, 2, '.', '')]);
        $pid = (int) $pdo->lastInsertId();
        $st = $pdo->prepare('UPDATE payments SET transaction_no = :t WHERE payment_id = :id');
        $st->execute([':t' => 'TRX-' . date('Ymd') . '-' . str_pad((string) $pid, 5, '0', STR_PAD_LEFT), ':id' => $pid]);
        bl_refresh_status($pdo, $invoiceId);
        return null;
    });
    return [$err, $pid];
}

function bl_void_payment(int $invoiceId, int $paymentId): ?string
{
    return bl_tx(function (PDO $pdo) use ($invoiceId, $paymentId) {
        $inv = bl_lock($pdo, $invoiceId);
        if (!$inv || $inv['invoice_status'] !== 'issued') {
            return 'Tagihan tidak dalam status Diterbitkan.';
        }
        $st = $pdo->prepare("UPDATE payments SET payment_status = 'void' WHERE payment_id = :p AND invoice_id = :i AND payment_status = 'paid'");
        $st->execute([':p' => $paymentId, ':i' => $invoiceId]);
        if ($st->rowCount() === 0) {
            return 'Pembayaran tidak ditemukan atau sudah dibatalkan.';
        }
        bl_refresh_status($pdo, $invoiceId);
        return null;
    });
}

/** Batalkan tagihan: draft bebas; diterbitkan hanya jika tidak ada pembayaran aktif dan klaim belum diajukan. */
function bl_cancel(int $invoiceId): ?string
{
    return bl_tx(function (PDO $pdo) use ($invoiceId) {
        $inv = bl_lock($pdo, $invoiceId);
        if (!$inv || $inv['invoice_status'] === 'cancelled') {
            return 'Tagihan tidak ditemukan atau sudah dibatalkan.';
        }
        if ($inv['invoice_status'] === 'issued') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = :id AND payment_status = 'paid'");
            $st->execute([':id' => $invoiceId]);
            if ((int) $st->fetchColumn() > 0) {
                return 'Masih ada pembayaran aktif. Batalkan (void) pembayaran terlebih dahulu.';
            }
            $st = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE invoice_id = :id AND claim_status IN ('submitted','verified','paid')");
            $st->execute([':id' => $invoiceId]);
            if ((int) $st->fetchColumn() > 0) {
                return 'Klaim sudah diajukan atau diproses. Tagihan tidak dapat dibatalkan.';
            }
            $st = $pdo->prepare("UPDATE claims SET claim_status = 'rejected' WHERE invoice_id = :id AND claim_status = 'draft'");
            $st->execute([':id' => $invoiceId]);
        }
        $st = $pdo->prepare("UPDATE billing_invoices SET invoice_status = 'cancelled' WHERE invoice_id = :id");
        $st->execute([':id' => $invoiceId]);
        return null;
    });
}

/** Alur klaim: draft->submitted (update); submitted->verified|rejected, verified->paid|rejected (approve). */
function bl_claim_move(int $claimId, string $to): ?string
{
    $flow = ['draft' => ['submitted'], 'submitted' => ['verified', 'rejected'], 'verified' => ['paid', 'rejected']];
    return bl_tx(function (PDO $pdo) use ($claimId, $to, $flow) {
        $st = $pdo->prepare('SELECT invoice_id FROM claims WHERE claim_id = :id');
        $st->execute([':id' => $claimId]);
        $invoiceId = $st->fetchColumn();
        if ($invoiceId === false) {
            return 'Klaim tidak ditemukan.';
        }
        $inv = bl_lock($pdo, (int) $invoiceId);   // kunci tagihan lebih dulu, lalu klaim
        $st = $pdo->prepare('SELECT claim_status FROM claims WHERE claim_id = :id FOR UPDATE');
        $st->execute([':id' => $claimId]);
        $cur = (string) $st->fetchColumn();
        if (!$inv || $inv['invoice_status'] !== 'issued') {
            return 'Tagihan untuk klaim ini tidak aktif.';
        }
        if (!in_array($to, $flow[$cur] ?? [], true)) {
            return 'Perubahan status klaim tidak diizinkan.';
        }
        $st = $pdo->prepare('UPDATE claims SET claim_status = :s WHERE claim_id = :id');
        $st->execute([':s' => $to, ':id' => $claimId]);
        bl_refresh_status($pdo, (int) $invoiceId);
        return null;
    });
}
