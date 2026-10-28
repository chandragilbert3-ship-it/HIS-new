<?php
declare(strict_types=1);

require_once __DIR__ . '/inventory.php';

/**
 * Fungsi bantu Gudang bagian 2: permintaan stok dari unit dan stok opname.
 * Memakai INVENTORY_REQUESTS, INVENTORY_REQUEST_ITEMS, STOCK_OPNAMES, STOCK_OPNAME_ITEMS, STOCK_BATCHES, STOCK_TRANSACTIONS.
 * Pengeluaran stok memakai FEFO dan menulis buku besar bernilai negatif (txn_type 'issue').
 * Penyesuaian opname menulis txn_type 'opname_adjustment' (positif atau negatif).
 */

const INVR_LINES = 8;

/* ---------- Permintaan unit ---------- */

function invr_status_labels(): array
{
    return [
        'pending' => ['Menunggu', 'cp-info'], 'processed' => ['Diproses', 'cp-warn'],
        'completed' => ['Selesai', 'cp-ok'], 'cancelled' => ['Dibatalkan', 'cp-off'],
    ];
}

function invr_list(string $status): array
{
    $sql = "SELECT r.request_id, r.requesting_unit, r.status, w.warehouse_name, u.nama AS user_name,
                   (SELECT COUNT(*) FROM inventory_request_items ri WHERE ri.request_id = r.request_id) AS jml_item
              FROM inventory_requests r
              JOIN warehouses w ON w.warehouse_id = r.warehouse_id
              LEFT JOIN users u ON u.user_id = r.requested_by";
    $p = [];
    if (in_array($status, ['pending', 'processed', 'completed', 'cancelled'], true)) {
        $sql .= ' WHERE r.status = :s';
        $p[':s'] = $status;
    }
    $stmt = db()->prepare($sql . ' ORDER BY r.status IN (\'pending\',\'processed\') DESC, r.request_id DESC LIMIT 200');
    $stmt->execute($p);
    return $stmt->fetchAll();
}

function invr_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.request_id, r.requesting_unit, r.status, r.warehouse_id, w.warehouse_name, u.nama AS user_name
           FROM inventory_requests r
           JOIN warehouses w ON w.warehouse_id = r.warehouse_id
           LEFT JOIN users u ON u.user_id = r.requested_by
          WHERE r.request_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Item permintaan beserta stok tersedia di gudang sumber (sen). */
function invr_items(int $id, int $warehouseId): array
{
    $stmt = db()->prepare(
        "SELECT ri.request_item_id, ri.inventory_item_id, ri.quantity, ii.item_code, ii.item_name, ii.unit,
                COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_batches sb WHERE sb.inventory_item_id = ri.inventory_item_id AND sb.warehouse_id = :w
                           AND sb.status = 'available' AND sb.quantity_on_hand > 0 AND (sb.expiry_date IS NULL OR sb.expiry_date >= :t)), 0) AS available
           FROM inventory_request_items ri
           JOIN inventory_items ii ON ii.inventory_item_id = ri.inventory_item_id
          WHERE ri.request_id = :r ORDER BY ri.request_item_id"
    );
    $stmt->execute([':w' => $warehouseId, ':t' => date('Y-m-d'), ':r' => $id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['qty_c']       = (int) round((float) $r['quantity'] * 100);
        $r['available_c'] = (int) round((float) $r['available'] * 100);
    }
    return $rows;
}

/** Validasi form permintaan. Hasil: [data, errors]. */
function invr_validate(array $in, array $itemMap, array $warehouseIds): array
{
    $errors = [];
    $wid  = (int) ($in['warehouse_id'] ?? 0);
    $unit = is_string($in['requesting_unit'] ?? null) ? trim($in['requesting_unit']) : '';
    if (!in_array($wid, $warehouseIds, true)) {
        $errors[] = 'Pilih gudang sumber.';
    }
    if (mb_strlen($unit) < 2 || mb_strlen($unit) > 100) {
        $errors[] = 'Unit peminta wajib diisi (2-100 karakter).';
    }
    $raw = is_array($in['line'] ?? null) ? $in['line'] : [];
    $lines = [];
    $seen = [];
    for ($i = 0; $i < INVR_LINES; $i++) {
        $l = is_array($raw[$i] ?? null) ? $raw[$i] : [];
        $item = is_string($l['item'] ?? null) ? trim($l['item']) : '';
        $qty  = is_string($l['qty'] ?? null) ? trim($l['qty']) : '';
        if ($item === '' && $qty === '') {
            continue;
        }
        $no = 'Baris ' . ($i + 1) . ': ';
        $iid = (int) $item;
        if (!isset($itemMap[$iid])) {
            $errors[] = $no . 'pilih barang.';
            continue;
        }
        $c = inv_parse_qty($qty);
        if ($c === null || $c <= 0) {
            $errors[] = $no . 'jumlah harus angka lebih dari 0 (maksimal 2 desimal).';
            continue;
        }
        if (isset($seen[$iid])) {
            $errors[] = $no . 'barang yang sama sudah ada di baris lain. Gabungkan jumlahnya.';
            continue;
        }
        $seen[$iid] = true;
        $lines[] = ['item_id' => $iid, 'qty_c' => $c];
    }
    if (!$lines && !$errors) {
        $errors[] = 'Isi minimal satu baris barang.';
    }
    return [['warehouse_id' => $wid, 'unit' => $unit, 'lines' => $lines], $errors];
}

function invr_create(array $data, int $userId): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO inventory_requests (requested_by, warehouse_id, requesting_unit, status) VALUES (:u, :w, :n, 'pending')")
            ->execute([':u' => $userId, ':w' => $data['warehouse_id'], ':n' => $data['unit']]);
        $id = (int) $pdo->lastInsertId();
        foreach ($data['lines'] as $ln) {
            $pdo->prepare('INSERT INTO inventory_request_items (request_id, inventory_item_id, quantity) VALUES (:r, :i, :q)')
                ->execute([':r' => $id, ':i' => $ln['item_id'], ':q' => inv_dec($ln['qty_c'])]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Ubah status: 'process' (pending -> processed) atau 'cancel' (pending/processed -> cancelled). Hasil: null jika sukses, atau teks error. */
function invr_set_status(int $id, string $step): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM inventory_requests WHERE request_id = :r FOR UPDATE');
        $st->execute([':r' => $id]);
        $cur = $st->fetchColumn();
        if ($cur === false) {
            $pdo->rollBack();
            return 'Permintaan tidak ditemukan.';
        }
        if ($step === 'process' && $cur === 'pending') {
            $to = 'processed';
        } elseif ($step === 'cancel' && in_array($cur, ['pending', 'processed'], true)) {
            $to = 'cancelled';
        } else {
            $pdo->rollBack();
            return 'Status permintaan tidak memungkinkan aksi ini.';
        }
        $pdo->prepare('UPDATE inventory_requests SET status = :s WHERE request_id = :r')->execute([':s' => $to, ':r' => $id]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Keluarkan stok untuk permintaan berstatus Diproses (FEFO). Semua atau tidak sama sekali. Hasil: null jika sukses, atau teks error. */
function invr_issue(int $id, int $userId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, warehouse_id FROM inventory_requests WHERE request_id = :r FOR UPDATE');
        $st->execute([':r' => $id]);
        $req = $st->fetch();
        if ($req === false) {
            $pdo->rollBack();
            return 'Permintaan tidak ditemukan.';
        }
        if ($req['status'] !== 'processed') {
            $pdo->rollBack();
            return 'Stok hanya dapat dikeluarkan untuk permintaan berstatus "Diproses".';
        }
        $wid = (int) $req['warehouse_id'];
        // Kunci barang yang terlibat sebelum membaca stok.
        $pdo->prepare(
            'SELECT inventory_item_id FROM inventory_items WHERE inventory_item_id IN
                (SELECT inventory_item_id FROM inventory_request_items WHERE request_id = :r) ORDER BY inventory_item_id FOR UPDATE'
        )->execute([':r' => $id]);

        $items = invr_items($id, $wid);
        foreach ($items as $it) {
            if ($it['qty_c'] > $it['available_c']) {
                $pdo->rollBack();
                return 'Stok ' . $it['item_name'] . ' tidak cukup. Diminta ' . inv_fmt($it['qty_c'] / 100) . ', tersedia ' . inv_fmt($it['available_c'] / 100) . ' ' . $it['unit'] . '.';
            }
        }
        $now = date('Y-m-d H:i:s');
        foreach ($items as $it) {
            if (!inv_take_fefo($pdo, (int) $it['inventory_item_id'], $wid, $it['qty_c'])) {
                $pdo->rollBack();
                return 'Stok batch tidak mencukupi. Muat ulang halaman lalu coba lagi.';
            }
            $pdo->prepare("INSERT INTO stock_transactions (inventory_item_id, warehouse_id, user_id, txn_type, quantity, reference_no, txn_at) VALUES (:i, :w, :u, 'issue', :q, :r, :t)")
                ->execute([':i' => $it['inventory_item_id'], ':w' => $wid, ':u' => $userId, ':q' => '-' . inv_dec($it['qty_c']), ':r' => 'REQ-' . $id, ':t' => $now]);
        }
        $pdo->prepare("UPDATE inventory_requests SET status = 'completed' WHERE request_id = :r")->execute([':r' => $id]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/* ---------- Stok opname ---------- */

function invo_status_labels(): array
{
    return ['draft' => ['Draft', 'cp-warn'], 'completed' => ['Selesai', 'cp-ok'], 'cancelled' => ['Dibatalkan', 'cp-off']];
}

function invo_list(): array
{
    return db()->query(
        'SELECT o.opname_id, o.opname_date, o.status, w.warehouse_name, u.nama AS user_name,
                (SELECT COUNT(*) FROM stock_opname_items oi WHERE oi.opname_id = o.opname_id AND oi.physical_qty <> oi.book_qty) AS jml_selisih
           FROM stock_opnames o
           JOIN warehouses w ON w.warehouse_id = o.warehouse_id
           LEFT JOIN users u ON u.user_id = o.performed_by
          ORDER BY o.opname_id DESC LIMIT 100'
    )->fetchAll();
}

function invo_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT o.opname_id, o.warehouse_id, o.opname_date, o.status, w.warehouse_name, u.nama AS user_name
           FROM stock_opnames o
           JOIN warehouses w ON w.warehouse_id = o.warehouse_id
           LEFT JOIN users u ON u.user_id = o.performed_by
          WHERE o.opname_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function invo_items(int $id): array
{
    $stmt = db()->prepare(
        'SELECT oi.opname_item_id, oi.inventory_item_id, oi.book_qty, oi.physical_qty, oi.physical_qty - oi.book_qty AS diff, ii.item_code, ii.item_name, ii.unit
           FROM stock_opname_items oi JOIN inventory_items ii ON ii.inventory_item_id = oi.inventory_item_id
          WHERE oi.opname_id = :id ORDER BY ii.item_name'
    );
    $stmt->execute([':id' => $id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['book_c']     = (int) round((float) $r['book_qty'] * 100);
        $r['physical_c'] = (int) round((float) $r['physical_qty'] * 100);
        $r['diff_c']     = $r['physical_c'] - $r['book_c'];
    }
    return $rows;
}

/** Stok buku saat ini (seluruh status batch) per barang di satu gudang, dalam sen. Barang tanpa batch bernilai 0. */
function invo_book_qty(PDO $pdo, int $warehouseId): array
{
    $out = [];
    foreach ($pdo->query('SELECT inventory_item_id FROM inventory_items')->fetchAll() as $r) {
        $out[(int) $r['inventory_item_id']] = 0;
    }
    $st = $pdo->prepare('SELECT inventory_item_id, SUM(quantity_on_hand) AS q FROM stock_batches WHERE warehouse_id = :w GROUP BY inventory_item_id');
    $st->execute([':w' => $warehouseId]);
    foreach ($st->fetchAll() as $r) {
        $out[(int) $r['inventory_item_id']] = (int) round((float) $r['q'] * 100);
    }
    return $out;
}

/** Buat draft opname dan simpan stok buku sebagai titik awal. Hasil: [error|null, id]. */
function invo_create(int $warehouseId, string $date, int $userId): array
{
    if (!inv_valid_date($date)) {
        return ['Tanggal opname tidak valid.', 0];
    }
    if ($date > date('Y-m-d')) {
        return ['Tanggal opname tidak boleh di masa depan.', 0];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $w = $pdo->prepare('SELECT warehouse_id FROM warehouses WHERE warehouse_id = :w FOR UPDATE');
        $w->execute([':w' => $warehouseId]);
        if ($w->fetchColumn() === false) {
            $pdo->rollBack();
            return ['Gudang tidak valid.', 0];
        }
        $d = $pdo->prepare("SELECT COUNT(*) FROM stock_opnames WHERE warehouse_id = :w AND status = 'draft'");
        $d->execute([':w' => $warehouseId]);
        if ((int) $d->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['Gudang ini masih punya opname berstatus Draft. Selesaikan atau batalkan dulu.', 0];
        }
        $book = invo_book_qty($pdo, $warehouseId);
        if (!$book) {
            $pdo->rollBack();
            return ['Belum ada barang yang terdaftar.', 0];
        }
        $pdo->prepare("INSERT INTO stock_opnames (warehouse_id, performed_by, opname_date, status) VALUES (:w, :u, :d, 'draft')")
            ->execute([':w' => $warehouseId, ':u' => $userId, ':d' => $date]);
        $id = (int) $pdo->lastInsertId();
        foreach ($book as $itemId => $c) {
            $pdo->prepare('INSERT INTO stock_opname_items (opname_id, inventory_item_id, book_qty, physical_qty) VALUES (:o, :i, :b, :p)')
                ->execute([':o' => $id, ':i' => $itemId, ':b' => inv_dec($c), ':p' => inv_dec($c)]);
        }
        $pdo->commit();
        return [null, $id];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Validasi hitungan fisik. Hasil: [item_id => 'jumlah 2 desimal', ...] dan errors. */
function invo_validate_counts(array $in, array $items): array
{
    $errors = [];
    $counts = [];
    $raw = is_array($in['physical'] ?? null) ? $in['physical'] : [];
    foreach ($items as $it) {
        $id = (int) $it['inventory_item_id'];
        $v  = is_string($raw[$id] ?? null) ? trim($raw[$id]) : '';
        if ($v === '') {
            $errors[] = $it['item_name'] . ': jumlah fisik wajib diisi (isi 0 bila kosong).';
            continue;
        }
        $c = inv_parse_qty($v);
        if ($c === null) {
            $errors[] = $it['item_name'] . ': jumlah fisik harus angka 0 atau lebih (maksimal 2 desimal).';
            continue;
        }
        $counts[$id] = inv_dec($c);
    }
    return [$counts, $errors];
}

/** Simpan hitungan fisik pada draft. Hasil: null jika sukses, atau teks error. */
function invo_save_counts(int $id, array $counts): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM stock_opnames WHERE opname_id = :o FOR UPDATE');
        $st->execute([':o' => $id]);
        $s = $st->fetchColumn();
        if ($s === false) {
            $pdo->rollBack();
            return 'Opname tidak ditemukan.';
        }
        if ($s !== 'draft') {
            $pdo->rollBack();
            return 'Hanya opname berstatus Draft yang dapat diubah.';
        }
        foreach ($counts as $itemId => $q) {
            $pdo->prepare('UPDATE stock_opname_items SET physical_qty = :q WHERE opname_id = :o AND inventory_item_id = :i')
                ->execute([':q' => $q, ':o' => $id, ':i' => $itemId]);
        }
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Ambil stok dari batch mana pun (kedaluwarsa terdekat dahulu) untuk penyesuaian opname. Di dalam transaksi. */
function invo_take_any(PDO $pdo, int $itemId, int $warehouseId, int $cents): bool
{
    $st = $pdo->prepare(
        'SELECT stock_batch_id, quantity_on_hand, status FROM stock_batches
          WHERE inventory_item_id = :i AND warehouse_id = :w AND quantity_on_hand > 0
          ORDER BY expiry_date IS NULL, expiry_date, stock_batch_id FOR UPDATE'
    );
    $st->execute([':i' => $itemId, ':w' => $warehouseId]);
    $need = $cents;
    foreach ($st->fetchAll() as $b) {
        if ($need <= 0) {
            break;
        }
        $have = (int) round((float) $b['quantity_on_hand'] * 100);
        $take = min($have, $need);
        $left = $have - $take;
        $pdo->prepare('UPDATE stock_batches SET quantity_on_hand = :q, status = :s WHERE stock_batch_id = :b')
            ->execute([':q' => inv_dec($left), ':s' => $left === 0 ? 'empty' : (string) $b['status'], ':b' => $b['stock_batch_id']]);
        $need -= $take;
    }
    return $need <= 0;
}

/**
 * Selesaikan opname: terapkan selisih ke stok. Selisih negatif mengurangi batch; selisih positif membuat batch
 * 'OPNAME-<id>' berstatus Karantina (tanggal kedaluwarsa belum diketahui) agar diperiksa dulu.
 * Ditolak bila stok buku berubah sejak draft dibuat. Hasil: [error|null, jumlah barang yang disesuaikan].
 */
function invo_complete(int $id, int $userId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, warehouse_id FROM stock_opnames WHERE opname_id = :o FOR UPDATE');
        $st->execute([':o' => $id]);
        $op = $st->fetch();
        if ($op === false) {
            $pdo->rollBack();
            return ['Opname tidak ditemukan.', 0];
        }
        if ($op['status'] !== 'draft') {
            $pdo->rollBack();
            return ['Hanya opname berstatus Draft yang dapat diselesaikan.', 0];
        }
        $wid = (int) $op['warehouse_id'];
        $pdo->prepare('SELECT inventory_item_id FROM inventory_items ORDER BY inventory_item_id FOR UPDATE')->execute();

        $now  = invo_book_qty($pdo, $wid);
        $rows = invo_items($id);
        $changed = [];
        foreach ($rows as $r) {
            if (($now[(int) $r['inventory_item_id']] ?? 0) !== $r['book_c']) {
                $changed[] = $r['item_name'];
            }
        }
        if ($changed) {
            $pdo->rollBack();
            return ['Stok berubah sejak draft dibuat (' . implode(', ', $changed) . '). Batalkan draft ini dan buat opname baru.', 0];
        }
        $adj = 0;
        $ts  = date('Y-m-d H:i:s');
        foreach ($rows as $r) {
            $d = $r['diff_c'];
            if ($d === 0) {
                continue;
            }
            $iid = (int) $r['inventory_item_id'];
            if ($d < 0) {
                if (!invo_take_any($pdo, $iid, $wid, -$d)) {
                    $pdo->rollBack();
                    return ['Stok batch tidak cukup untuk menyesuaikan ' . $r['item_name'] . '.', 0];
                }
            } else {
                $pdo->prepare("INSERT INTO stock_batches (inventory_item_id, warehouse_id, location_id, batch_no, expiry_date, quantity_on_hand, status) VALUES (:i, :w, NULL, :b, NULL, :q, 'quarantine')")
                    ->execute([':i' => $iid, ':w' => $wid, ':b' => 'OPNAME-' . $id, ':q' => inv_dec($d)]);
            }
            $pdo->prepare("INSERT INTO stock_transactions (inventory_item_id, warehouse_id, user_id, txn_type, quantity, reference_no, txn_at) VALUES (:i, :w, :u, 'opname_adjustment', :q, :r, :t)")
                ->execute([':i' => $iid, ':w' => $wid, ':u' => $userId, ':q' => ($d < 0 ? '-' : '') . inv_dec(abs($d)), ':r' => 'OPN-' . $id, ':t' => $ts]);
            $adj++;
        }
        $pdo->prepare("UPDATE stock_opnames SET status = 'completed' WHERE opname_id = :o")->execute([':o' => $id]);
        $pdo->commit();
        return [null, $adj];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function invo_cancel(int $id): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM stock_opnames WHERE opname_id = :o FOR UPDATE');
        $st->execute([':o' => $id]);
        $s = $st->fetchColumn();
        if ($s === false) {
            $pdo->rollBack();
            return 'Opname tidak ditemukan.';
        }
        if ($s !== 'draft') {
            $pdo->rollBack();
            return 'Hanya opname berstatus Draft yang dapat dibatalkan.';
        }
        $pdo->prepare("UPDATE stock_opnames SET status = 'cancelled' WHERE opname_id = :o")->execute([':o' => $id]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
