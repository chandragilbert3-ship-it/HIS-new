<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Gudang & Inventori.
 * Memakai INVENTORY_ITEMS, MASTER_DRUGS, SUPPLIERS, WAREHOUSES, WAREHOUSE_LOCATIONS, STOCK_BATCHES,
 * STOCK_TRANSACTIONS, GOODS_RECEIPTS, GOODS_RECEIPT_ITEMS.
 *
 * Aturan stok:
 *  - STOCK_BATCHES.quantity_on_hand adalah stok per batch (dipakai untuk penyerahan FEFO).
 *  - STOCK_TRANSACTIONS adalah buku besar: masuk positif, keluar negatif
 *    (txn_type: receipt, dispense, issue, opname_adjustment, opening_balance).
 *  - Stok tersedia = batch berstatus 'available', jumlah > 0, dan belum kedaluwarsa.
 * Jumlah dihitung dalam sen (x100) agar tidak ada kesalahan pembulatan.
 */

const INV_RECEIPT_LINES = 8;

function inv_batch_status_labels(): array
{
    return [
        'available' => ['Tersedia', 'cp-ok'], 'quarantine' => ['Karantina', 'cp-warn'],
        'expired'   => ['Kedaluwarsa', 'cp-off'], 'empty' => ['Habis', 'cp-info'],
    ];
}

/** Tingkat stok terhadap batas minimum/maksimum. Hasil: [label, kelas]. */
function inv_stock_level(float $onHand, float $min, float $max): array
{
    if ($onHand <= 0) {
        return ['Habis', 'cp-off'];
    }
    if ($onHand < $min) {
        return ['Di bawah minimum', 'cp-warn'];
    }
    if ($max > 0 && $onHand > $max) {
        return ['Melebihi maksimum', 'cp-info'];
    }
    return ['Aman', 'cp-ok'];
}

function inv_fmt(float $v): string
{
    return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
}

/** Angka untuk kolom isian form (tanpa pemisah ribuan): 100000 sen -> 1000, 1050 -> 10.5 */
function inv_input(int $cents): string
{
    return rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
}

function inv_dec(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/** Ubah teks jumlah menjadi sen. Null bila bukan angka (maksimal 2 desimal). */
function inv_parse_qty(string $raw): ?int
{
    $raw = trim($raw);
    if (!preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $raw)) {
        return null;
    }
    return (int) round((float) str_replace(',', '.', $raw) * 100);
}

function inv_valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function inv_warehouses(): array
{
    return db()->query('SELECT warehouse_id, warehouse_name, location FROM warehouses ORDER BY warehouse_id')->fetchAll();
}

function inv_pick_warehouse(array $warehouses, int $requested): int
{
    foreach ($warehouses as $w) {
        if ((int) $w['warehouse_id'] === $requested) {
            return $requested;
        }
    }
    return $warehouses ? (int) $warehouses[0]['warehouse_id'] : 0;
}

/** Baris tab modul Gudang. Tab fase berikutnya hanya muncul bila filenya sudah ada. */
function inv_tabs(string $active): void
{
    $tabs = [
        'index'     => ['Stok', 'index.php', 'inventory.view'],
        'batches'   => ['Batch & Kedaluwarsa', 'batches.php', 'inventory.view'],
        'receipts'  => ['Penerimaan Barang', 'receipts.php', 'inventory.view'],
        'requests'  => ['Permintaan Unit', 'requests.php', 'inventory.view'],
        'opname'    => ['Stok Opname', 'opname.php', 'inventory.view'],
        'items'     => ['Barang & Obat', 'items.php', 'inventory.view'],
        'suppliers' => ['Pemasok', 'suppliers.php', 'inventory.view'],
        'warehouses' => ['Gudang & Lokasi', 'warehouses.php', 'inventory.view'],
    ];
    echo '<div class="cp-tabs">';
    foreach ($tabs as $key => [$label, $file, $perm]) {
        if (!is_file(APP_ROOT . '/modules/inventory/' . $file) || !can($perm)) {
            continue;
        }
        echo '<a class="' . ($key === $active ? 'active' : '') . '" href="' . e(url('modules/inventory/' . $file)) . '">' . e($label) . '</a>';
    }
    echo '</div>';
}

/* ---------- Stok & batch ---------- */

/** Ringkasan stok per barang di satu gudang. */
function inv_stock_summary(int $warehouseId, string $q): array
{
    $today = date('Y-m-d');
    $sql = "SELECT ii.inventory_item_id, ii.item_code, ii.item_name, ii.category, ii.unit, ii.min_stock, ii.max_stock,
                   COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_batches sb WHERE sb.inventory_item_id = ii.inventory_item_id AND sb.warehouse_id = :w1), 0) AS on_hand,
                   COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_batches sb WHERE sb.inventory_item_id = ii.inventory_item_id AND sb.warehouse_id = :w2
                              AND sb.status = 'available' AND sb.quantity_on_hand > 0 AND (sb.expiry_date IS NULL OR sb.expiry_date >= :t1)), 0) AS available,
                   (SELECT MIN(sb.expiry_date) FROM stock_batches sb WHERE sb.inventory_item_id = ii.inventory_item_id AND sb.warehouse_id = :w3
                              AND sb.status = 'available' AND sb.quantity_on_hand > 0 AND sb.expiry_date >= :t2) AS nearest_expiry
              FROM inventory_items ii";
    $p = [':w1' => $warehouseId, ':w2' => $warehouseId, ':w3' => $warehouseId, ':t1' => $today, ':t2' => $today];
    $q = trim($q);
    if ($q !== '') {
        $sql .= ' WHERE ii.item_code LIKE :q1 OR ii.item_name LIKE :q2';
        $p[':q1'] = '%' . $q . '%';
        $p[':q2'] = '%' . $q . '%';
    }
    $stmt = db()->prepare($sql . ' ORDER BY ii.item_name');
    $stmt->execute($p);
    return $stmt->fetchAll();
}

/** Daftar batch. Filter: active, expiring (90 hari), expired, quarantine, all. */
function inv_batches(int $warehouseId, string $filter): array
{
    $p = [':w' => $warehouseId];
    $cond = '';
    $today = date('Y-m-d');
    switch ($filter) {
        case 'expiring':
            $cond = " AND sb.status = 'available' AND sb.quantity_on_hand > 0 AND sb.expiry_date >= :t1 AND sb.expiry_date <= :t2";
            $p[':t1'] = $today;
            $p[':t2'] = date('Y-m-d', strtotime('+90 days'));
            break;
        case 'expired':
            $cond = ' AND sb.quantity_on_hand > 0 AND sb.expiry_date IS NOT NULL AND sb.expiry_date < :t1';
            $p[':t1'] = $today;
            break;
        case 'quarantine':
            $cond = " AND sb.status = 'quarantine' AND sb.quantity_on_hand > 0";
            break;
        case 'all':
            break;
        default:
            $cond = ' AND sb.quantity_on_hand > 0';
    }
    $stmt = db()->prepare(
        "SELECT sb.stock_batch_id, sb.batch_no, sb.expiry_date, sb.quantity_on_hand, sb.status, ii.item_code, ii.item_name, ii.unit, wl.bin_code
           FROM stock_batches sb
           JOIN inventory_items ii ON ii.inventory_item_id = sb.inventory_item_id
           LEFT JOIN warehouse_locations wl ON wl.location_id = sb.location_id
          WHERE sb.warehouse_id = :w $cond
          ORDER BY sb.expiry_date IS NULL, sb.expiry_date, ii.item_name, sb.stock_batch_id
          LIMIT 500"
    );
    $stmt->execute($p);
    return $stmt->fetchAll();
}

/** Status efektif batch untuk tampilan: batch 'available'/'quarantine' yang lewat tanggal kedaluwarsa dianggap kedaluwarsa. */
function inv_effective_status(array $b): string
{
    if ((float) $b['quantity_on_hand'] <= 0) {
        return 'empty';
    }
    if ($b['expiry_date'] !== null && $b['expiry_date'] < date('Y-m-d')) {
        return 'expired';
    }
    return (string) $b['status'];
}

/** Ambil stok FEFO (kedaluwarsa terdekat dahulu) dari batch tersedia. Wajib dipanggil di dalam transaksi. True bila seluruh jumlah terpenuhi. */
function inv_take_fefo(PDO $pdo, int $itemId, int $warehouseId, int $cents): bool
{
    $st = $pdo->prepare(
        "SELECT stock_batch_id, quantity_on_hand FROM stock_batches
          WHERE inventory_item_id = :i AND warehouse_id = :w AND status = 'available' AND quantity_on_hand > 0
            AND (expiry_date IS NULL OR expiry_date >= :t)
          ORDER BY expiry_date IS NULL, expiry_date, stock_batch_id FOR UPDATE"
    );
    $st->execute([':i' => $itemId, ':w' => $warehouseId, ':t' => date('Y-m-d')]);
    $need = $cents;
    foreach ($st->fetchAll() as $b) {
        if ($need <= 0) {
            break;
        }
        $have = (int) round((float) $b['quantity_on_hand'] * 100);
        $take = min($have, $need);
        $left = $have - $take;
        $pdo->prepare('UPDATE stock_batches SET quantity_on_hand = :q, status = :s WHERE stock_batch_id = :b')
            ->execute([':q' => inv_dec($left), ':s' => $left === 0 ? 'empty' : 'available', ':b' => $b['stock_batch_id']]);
        $need -= $take;
    }
    return $need <= 0;
}

/** Karantina atau lepas karantina satu batch. Hasil: null jika sukses, atau teks error. */
function inv_set_batch_status(int $batchId, string $to): ?string
{
    if (!in_array($to, ['quarantine', 'available'], true)) {
        return 'Aksi tidak dikenal.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, quantity_on_hand, expiry_date FROM stock_batches WHERE stock_batch_id = :b FOR UPDATE');
        $st->execute([':b' => $batchId]);
        $b = $st->fetch();
        if ($b === false) {
            $pdo->rollBack();
            return 'Batch tidak ditemukan.';
        }
        if ((float) $b['quantity_on_hand'] <= 0) {
            $pdo->rollBack();
            return 'Batch sudah habis.';
        }
        if ($to === 'quarantine' && $b['status'] !== 'available') {
            $pdo->rollBack();
            return 'Hanya batch berstatus "Tersedia" yang dapat dikarantina.';
        }
        if ($to === 'available') {
            if ($b['status'] !== 'quarantine') {
                $pdo->rollBack();
                return 'Hanya batch berstatus "Karantina" yang dapat dilepas.';
            }
            if ($b['expiry_date'] !== null && $b['expiry_date'] < date('Y-m-d')) {
                $pdo->rollBack();
                return 'Batch kedaluwarsa tidak dapat dilepas dari karantina.';
            }
        }
        $pdo->prepare('UPDATE stock_batches SET status = :s WHERE stock_batch_id = :b')->execute([':s' => $to, ':b' => $batchId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Tandai batch yang lewat tanggal kedaluwarsa dan rapikan batch kosong. Hasil: jumlah batch yang diubah. */
function inv_sync_expired(): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $a = $pdo->prepare("UPDATE stock_batches SET status = 'expired' WHERE status IN ('available','quarantine') AND quantity_on_hand > 0 AND expiry_date IS NOT NULL AND expiry_date < :t");
        $a->execute([':t' => date('Y-m-d')]);
        $n = $a->rowCount();
        $b = $pdo->prepare("UPDATE stock_batches SET status = 'empty' WHERE quantity_on_hand <= 0 AND status <> 'empty'");
        $b->execute();
        $n += $b->rowCount();
        $pdo->commit();
        return $n;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/* ---------- Penerimaan barang ---------- */

function inv_receipts(int $limit = 100): array
{
    $stmt = db()->prepare(
        'SELECT r.receipt_id, r.received_at, r.source_document, w.warehouse_name, s.supplier_name, u.nama AS user_name,
                (SELECT COUNT(*) FROM goods_receipt_items gi WHERE gi.receipt_id = r.receipt_id) AS jml_item,
                (SELECT COALESCE(SUM(gi2.quantity), 0) FROM goods_receipt_items gi2 WHERE gi2.receipt_id = r.receipt_id) AS total_qty
           FROM goods_receipts r
           JOIN warehouses w ON w.warehouse_id = r.warehouse_id
           JOIN suppliers s ON s.supplier_id = r.supplier_id
           LEFT JOIN users u ON u.user_id = r.received_by
          ORDER BY r.receipt_id DESC LIMIT ' . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function inv_find_receipt(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.receipt_id, r.received_at, r.source_document, w.warehouse_name, s.supplier_name, u.nama AS user_name
           FROM goods_receipts r
           JOIN warehouses w ON w.warehouse_id = r.warehouse_id
           JOIN suppliers s ON s.supplier_id = r.supplier_id
           LEFT JOIN users u ON u.user_id = r.received_by
          WHERE r.receipt_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function inv_receipt_items(int $id): array
{
    $stmt = db()->prepare(
        'SELECT gi.batch_no, gi.expiry_date, gi.quantity, ii.item_code, ii.item_name, ii.unit
           FROM goods_receipt_items gi JOIN inventory_items ii ON ii.inventory_item_id = gi.inventory_item_id
          WHERE gi.receipt_id = :id ORDER BY gi.receipt_item_id'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetchAll();
}

function inv_active_suppliers(): array
{
    return db()->query('SELECT supplier_id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name')->fetchAll();
}

function inv_all_items(): array
{
    return db()->query('SELECT inventory_item_id, item_code, item_name, unit, category FROM inventory_items ORDER BY item_name')->fetchAll();
}

function inv_active_locations(int $warehouseId): array
{
    $stmt = db()->prepare("SELECT location_id, bin_code, location_name FROM warehouse_locations WHERE warehouse_id = :w AND status = 'active' ORDER BY bin_code");
    $stmt->execute([':w' => $warehouseId]);
    return $stmt->fetchAll();
}

/**
 * Validasi form penerimaan. $itemMap: id => baris barang; $supplierIds, $locationIds: id yang sah.
 * Hasil: [data, errors]; data = [supplier_id, source_document, lines[]].
 */
function inv_validate_receipt(array $in, array $itemMap, array $supplierIds, array $locationIds): array
{
    $errors = [];
    $sid = (int) ($in['supplier_id'] ?? 0);
    if (!in_array($sid, $supplierIds, true)) {
        $errors[] = 'Pilih pemasok yang aktif.';
    }
    $doc = is_string($in['source_document'] ?? null) ? trim($in['source_document']) : '';
    if ($doc === '' || mb_strlen($doc) > 50) {
        $errors[] = 'No. faktur / surat jalan wajib diisi (maksimal 50 karakter).';
    }
    $raw   = is_array($in['line'] ?? null) ? $in['line'] : [];
    $lines = [];
    $seen  = [];
    $today = date('Y-m-d');
    for ($i = 0; $i < INV_RECEIPT_LINES; $i++) {
        $l = is_array($raw[$i] ?? null) ? $raw[$i] : [];
        $g = fn(string $k): string => is_string($l[$k] ?? null) ? trim($l[$k]) : '';
        if ($g('item') === '' && $g('batch') === '' && $g('expiry') === '' && $g('qty') === '') {
            continue;
        }
        $no = 'Baris ' . ($i + 1) . ': ';
        $itemId = (int) $g('item');
        if (!isset($itemMap[$itemId])) {
            $errors[] = $no . 'pilih barang.';
            continue;
        }
        $item = $itemMap[$itemId];
        $batch = $g('batch');
        if (!preg_match('/^[A-Za-z0-9._\/-]{1,50}$/', $batch)) {
            $errors[] = $no . 'No. batch wajib (huruf, angka, titik, garis, maksimal 50 karakter).';
            continue;
        }
        $exp = $g('expiry');
        $isDrug = mb_strtolower((string) $item['category']) === 'obat';
        if ($exp === '' && $isDrug) {
            $errors[] = $no . 'tanggal kedaluwarsa wajib untuk obat.';
            continue;
        }
        if ($exp !== '' && !inv_valid_date($exp)) {
            $errors[] = $no . 'tanggal kedaluwarsa tidak valid.';
            continue;
        }
        if ($exp !== '' && $exp < $today) {
            $errors[] = $no . 'barang yang sudah kedaluwarsa tidak boleh diterima.';
            continue;
        }
        $c = inv_parse_qty($g('qty'));
        if ($c === null || $c <= 0) {
            $errors[] = $no . 'jumlah harus angka lebih dari 0 (maksimal 2 desimal).';
            continue;
        }
        $loc = (int) $g('loc');
        if ($loc !== 0 && !in_array($loc, $locationIds, true)) {
            $errors[] = $no . 'lokasi rak tidak valid.';
            continue;
        }
        $key = $itemId . '|' . $batch . '|' . $exp;
        if (isset($seen[$key])) {
            $errors[] = $no . 'barang, batch, dan kedaluwarsa yang sama sudah ada di baris lain. Gabungkan jumlahnya.';
            continue;
        }
        $seen[$key] = true;
        $lines[] = ['item_id' => $itemId, 'batch_no' => $batch, 'expiry' => $exp === '' ? null : $exp, 'qty_c' => $c, 'loc' => $loc === 0 ? null : $loc];
    }
    if (!$lines && !$errors) {
        $errors[] = 'Isi minimal satu baris barang.';
    }
    return [['supplier_id' => $sid, 'source_document' => $doc, 'lines' => $lines], $errors];
}

/** Simpan penerimaan: GOODS_RECEIPTS, GOODS_RECEIPT_ITEMS, STOCK_BATCHES (tambah/baru), STOCK_TRANSACTIONS (+). Hasil: [error|null, receipt_id]. */
function inv_receive(int $warehouseId, array $data, int $userId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $w = $pdo->prepare('SELECT COUNT(*) FROM warehouses WHERE warehouse_id = :w');
        $w->execute([':w' => $warehouseId]);
        $s = $pdo->prepare('SELECT COUNT(*) FROM suppliers WHERE supplier_id = :s AND is_active = 1');
        $s->execute([':s' => $data['supplier_id']]);
        if ((int) $w->fetchColumn() === 0 || (int) $s->fetchColumn() === 0) {
            $pdo->rollBack();
            return ['Gudang atau pemasok tidak valid.', 0];
        }
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO goods_receipts (warehouse_id, supplier_id, received_by, source_document, received_at) VALUES (:w, :s, :u, :d, :t)')
            ->execute([':w' => $warehouseId, ':s' => $data['supplier_id'], ':u' => $userId, ':d' => $data['source_document'], ':t' => $now]);
        $rid = (int) $pdo->lastInsertId();

        foreach ($data['lines'] as $ln) {
            $qty = inv_dec($ln['qty_c']);
            $pdo->prepare('INSERT INTO goods_receipt_items (receipt_id, inventory_item_id, batch_no, expiry_date, quantity) VALUES (:r, :i, :b, :e, :q)')
                ->execute([':r' => $rid, ':i' => $ln['item_id'], ':b' => $ln['batch_no'], ':e' => $ln['expiry'], ':q' => $qty]);

            $sql = 'SELECT stock_batch_id, status, quantity_on_hand FROM stock_batches WHERE inventory_item_id = :i AND warehouse_id = :w AND batch_no = :b';
            $p = [':i' => $ln['item_id'], ':w' => $warehouseId, ':b' => $ln['batch_no']];
            if ($ln['expiry'] === null) {
                $sql .= ' AND expiry_date IS NULL';
            } else {
                $sql .= ' AND expiry_date = :e';
                $p[':e'] = $ln['expiry'];
            }
            $f = $pdo->prepare($sql . ' ORDER BY stock_batch_id LIMIT 1 FOR UPDATE');
            $f->execute($p);
            $b = $f->fetch();
            if ($b !== false) {
                $newQty = (int) round((float) $b['quantity_on_hand'] * 100) + $ln['qty_c'];
                $status = in_array($b['status'], ['empty', 'expired'], true) ? 'available' : (string) $b['status'];
                $pdo->prepare('UPDATE stock_batches SET quantity_on_hand = :q, status = :s WHERE stock_batch_id = :b')
                    ->execute([':q' => inv_dec($newQty), ':s' => $status, ':b' => $b['stock_batch_id']]);
            } else {
                $pdo->prepare("INSERT INTO stock_batches (inventory_item_id, warehouse_id, location_id, batch_no, expiry_date, quantity_on_hand, status) VALUES (:i, :w, :l, :b, :e, :q, 'available')")
                    ->execute([':i' => $ln['item_id'], ':w' => $warehouseId, ':l' => $ln['loc'], ':b' => $ln['batch_no'], ':e' => $ln['expiry'], ':q' => $qty]);
            }
            $pdo->prepare("INSERT INTO stock_transactions (inventory_item_id, warehouse_id, user_id, txn_type, quantity, reference_no, txn_at) VALUES (:i, :w, :u, 'receipt', :q, :r, :t)")
                ->execute([':i' => $ln['item_id'], ':w' => $warehouseId, ':u' => $userId, ':q' => $qty, ':r' => 'GR-' . $rid, ':t' => $now]);
        }
        $pdo->commit();
        return [null, $rid];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/* ---------- Barang & obat ---------- */

function inv_items_list(string $q): array
{
    $sql = 'SELECT ii.inventory_item_id, ii.item_code, ii.item_name, ii.category, ii.unit, ii.min_stock, ii.max_stock,
                   d.drug_id, d.drug_name, d.dosage_form, d.strength, d.is_active AS drug_active,
                   COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_batches sb WHERE sb.inventory_item_id = ii.inventory_item_id), 0) AS on_hand
              FROM inventory_items ii
              LEFT JOIN master_drugs d ON d.drug_id = (SELECT MIN(d2.drug_id) FROM master_drugs d2 WHERE d2.inventory_item_id = ii.inventory_item_id)';
    $p = [];
    $q = trim($q);
    if ($q !== '') {
        $sql .= ' WHERE ii.item_code LIKE :q1 OR ii.item_name LIKE :q2';
        $p = [':q1' => '%' . $q . '%', ':q2' => '%' . $q . '%'];
    }
    $stmt = db()->prepare($sql . ' ORDER BY ii.item_name LIMIT 300');
    $stmt->execute($p);
    return $stmt->fetchAll();
}

function inv_find_item(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT ii.inventory_item_id, ii.item_code, ii.item_name, ii.category, ii.unit, ii.min_stock, ii.max_stock,
                d.drug_id, d.drug_name, d.dosage_form, d.strength, d.is_active AS drug_active
           FROM inventory_items ii
           LEFT JOIN master_drugs d ON d.drug_id = (SELECT MIN(d2.drug_id) FROM master_drugs d2 WHERE d2.inventory_item_id = ii.inventory_item_id)
          WHERE ii.inventory_item_id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Validasi barang (dan data obat bila ditandai sebagai obat). Hasil: [data, errors]. */
function inv_validate_item(array $in, ?int $id): array
{
    $errors = [];
    $s = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
    $code = $s('item_code');
    $name = $s('item_name');
    $cat  = $s('category');
    $unit = $s('unit');
    if (!preg_match('/^[A-Za-z0-9._-]{2,30}$/', $code)) {
        $errors[] = 'Kode barang 2-30 karakter (huruf, angka, titik, garis).';
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        $errors[] = 'Nama barang 2-150 karakter.';
    }
    if (mb_strlen($cat) > 50) {
        $errors[] = 'Kategori maksimal 50 karakter.';
    }
    if ($unit === '' || mb_strlen($unit) > 20) {
        $errors[] = 'Satuan wajib diisi (maksimal 20 karakter).';
    }
    $min = inv_parse_qty($s('min_stock') === '' ? '0' : $s('min_stock'));
    $max = inv_parse_qty($s('max_stock') === '' ? '0' : $s('max_stock'));
    if ($min === null || $max === null) {
        $errors[] = 'Stok minimum dan maksimum harus angka (maksimal 2 desimal).';
    } elseif ($max > 0 && $max < $min) {
        $errors[] = 'Stok maksimum tidak boleh lebih kecil dari stok minimum.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT inventory_item_id FROM inventory_items WHERE item_code = :c');
        $st->execute([':c' => $code]);
        $dup = $st->fetchColumn();
        if ($dup !== false && (int) $dup !== (int) $id) {
            $errors[] = 'Kode barang sudah dipakai.';
        }
    }
    $isDrug = ($in['is_drug'] ?? '') === '1';
    $drug   = ['name' => '', 'form' => '', 'strength' => '', 'active' => 1];
    if ($isDrug) {
        $drug['name']     = $s('drug_name');
        $drug['form']     = $s('dosage_form');
        $drug['strength'] = $s('strength');
        $drug['active']   = ($in['drug_active'] ?? '') === '1' ? 1 : 0;
        if (mb_strlen($drug['name']) < 2 || mb_strlen($drug['name']) > 150) {
            $errors[] = 'Nama obat 2-150 karakter.';
        }
        if (mb_strlen($drug['form']) > 50 || mb_strlen($drug['strength']) > 50) {
            $errors[] = 'Bentuk sediaan dan kekuatan maksimal 50 karakter.';
        }
    }
    return [[
        'item_code' => $code, 'item_name' => $name, 'category' => $cat === '' ? null : $cat, 'unit' => $unit,
        'min_c' => $min ?? 0, 'max_c' => $max ?? 0, 'is_drug' => $isDrug, 'drug' => $drug,
    ], $errors];
}

/** Simpan barang dan data obatnya. Obat tidak pernah dihapus; bila tidak lagi ditandai obat, diubah menjadi nonaktif. Hasil: [error|null, id]. */
function inv_save_item(?int $id, array $d): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO inventory_items (item_code, item_name, category, unit, min_stock, max_stock) VALUES (:c, :n, :k, :u, :mi, :ma)')
                ->execute([':c' => $d['item_code'], ':n' => $d['item_name'], ':k' => $d['category'], ':u' => $d['unit'], ':mi' => inv_dec($d['min_c']), ':ma' => inv_dec($d['max_c'])]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE inventory_items SET item_code = :c, item_name = :n, category = :k, unit = :u, min_stock = :mi, max_stock = :ma WHERE inventory_item_id = :id')
                ->execute([':c' => $d['item_code'], ':n' => $d['item_name'], ':k' => $d['category'], ':u' => $d['unit'], ':mi' => inv_dec($d['min_c']), ':ma' => inv_dec($d['max_c']), ':id' => $id]);
        }
        $f = $pdo->prepare('SELECT MIN(drug_id) FROM master_drugs WHERE inventory_item_id = :i');
        $f->execute([':i' => $id]);
        $drugId = $f->fetchColumn();
        $drugId = ($drugId === false || $drugId === null) ? null : (int) $drugId;
        if ($d['is_drug']) {
            $dr = $d['drug'];
            if ($drugId === null) {
                $pdo->prepare('INSERT INTO master_drugs (inventory_item_id, drug_name, dosage_form, strength, is_active) VALUES (:i, :n, :f, :s, :a)')
                    ->execute([':i' => $id, ':n' => $dr['name'], ':f' => $dr['form'] === '' ? null : $dr['form'], ':s' => $dr['strength'] === '' ? null : $dr['strength'], ':a' => $dr['active']]);
            } else {
                $pdo->prepare('UPDATE master_drugs SET drug_name = :n, dosage_form = :f, strength = :s, is_active = :a WHERE drug_id = :d')
                    ->execute([':n' => $dr['name'], ':f' => $dr['form'] === '' ? null : $dr['form'], ':s' => $dr['strength'] === '' ? null : $dr['strength'], ':a' => $dr['active'], ':d' => $drugId]);
            }
        } elseif ($drugId !== null) {
            $pdo->prepare('UPDATE master_drugs SET is_active = 0 WHERE inventory_item_id = :i')->execute([':i' => $id]);
        }
        $pdo->commit();
        return [null, $id];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (($ex->errorInfo[1] ?? 0) === 1062) {
            return ['Kode barang sudah dipakai.', 0];
        }
        throw $ex;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/* ---------- Pemasok ---------- */

function inv_suppliers(): array
{
    return db()->query(
        'SELECT s.supplier_id, s.supplier_name, s.contact, s.is_active,
                (SELECT COUNT(*) FROM goods_receipts r WHERE r.supplier_id = s.supplier_id) AS jml_terima
           FROM suppliers s ORDER BY s.is_active DESC, s.supplier_name'
    )->fetchAll();
}

function inv_find_supplier(int $id): ?array
{
    $st = db()->prepare('SELECT supplier_id, supplier_name, contact, is_active FROM suppliers WHERE supplier_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function inv_validate_supplier(array $in, ?int $id): array
{
    $errors = [];
    $name = is_string($in['supplier_name'] ?? null) ? trim($in['supplier_name']) : '';
    $cont = is_string($in['contact'] ?? null) ? trim($in['contact']) : '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        $errors[] = 'Nama pemasok 2-150 karakter.';
    }
    if (mb_strlen($cont) > 100) {
        $errors[] = 'Kontak maksimal 100 karakter.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT supplier_id FROM suppliers WHERE supplier_name = :n');
        $st->execute([':n' => $name]);
        $dup = $st->fetchColumn();
        if ($dup !== false && (int) $dup !== (int) $id) {
            $errors[] = 'Nama pemasok sudah ada.';
        }
    }
    return [['name' => $name, 'contact' => $cont === '' ? null : $cont, 'active' => ($in['is_active'] ?? '') === '1' ? 1 : 0], $errors];
}

function inv_save_supplier(?int $id, array $d): int
{
    $pdo = db();
    if ($id === null) {
        $pdo->prepare('INSERT INTO suppliers (supplier_name, contact, is_active) VALUES (:n, :c, :a)')->execute([':n' => $d['name'], ':c' => $d['contact'], ':a' => $d['active']]);
        return (int) $pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE suppliers SET supplier_name = :n, contact = :c, is_active = :a WHERE supplier_id = :id')->execute([':n' => $d['name'], ':c' => $d['contact'], ':a' => $d['active'], ':id' => $id]);
    return $id;
}

/* ---------- Gudang & lokasi ---------- */

function inv_warehouses_full(): array
{
    return db()->query(
        'SELECT w.warehouse_id, w.warehouse_name, w.location,
                (SELECT COUNT(*) FROM warehouse_locations wl WHERE wl.warehouse_id = w.warehouse_id) AS jml_lokasi
           FROM warehouses w ORDER BY w.warehouse_id'
    )->fetchAll();
}

function inv_validate_warehouse(array $in, ?int $id): array
{
    $errors = [];
    $name = is_string($in['warehouse_name'] ?? null) ? trim($in['warehouse_name']) : '';
    $loc  = is_string($in['location'] ?? null) ? trim($in['location']) : '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Nama gudang 2-100 karakter.';
    }
    if (mb_strlen($loc) > 100) {
        $errors[] = 'Lokasi maksimal 100 karakter.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT warehouse_id FROM warehouses WHERE warehouse_name = :n');
        $st->execute([':n' => $name]);
        $dup = $st->fetchColumn();
        if ($dup !== false && (int) $dup !== (int) $id) {
            $errors[] = 'Nama gudang sudah ada.';
        }
    }
    return [['name' => $name, 'location' => $loc === '' ? null : $loc], $errors];
}

function inv_save_warehouse(?int $id, array $d): int
{
    $pdo = db();
    if ($id === null) {
        $pdo->prepare('INSERT INTO warehouses (warehouse_name, location) VALUES (:n, :l)')->execute([':n' => $d['name'], ':l' => $d['location']]);
        return (int) $pdo->lastInsertId();
    }
    $pdo->prepare('UPDATE warehouses SET warehouse_name = :n, location = :l WHERE warehouse_id = :id')->execute([':n' => $d['name'], ':l' => $d['location'], ':id' => $id]);
    return $id;
}

function inv_locations(int $warehouseId): array
{
    $st = db()->prepare('SELECT location_id, bin_code, location_name, status FROM warehouse_locations WHERE warehouse_id = :w ORDER BY bin_code');
    $st->execute([':w' => $warehouseId]);
    return $st->fetchAll();
}

function inv_validate_location(array $in, int $warehouseId, ?int $id): array
{
    $errors = [];
    $bin  = is_string($in['bin_code'] ?? null) ? trim($in['bin_code']) : '';
    $name = is_string($in['location_name'] ?? null) ? trim($in['location_name']) : '';
    $stat = ($in['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
    if (!preg_match('/^[A-Za-z0-9._-]{1,30}$/', $bin)) {
        $errors[] = 'Kode rak 1-30 karakter (huruf, angka, titik, garis).';
    }
    if (mb_strlen($name) > 100) {
        $errors[] = 'Nama lokasi maksimal 100 karakter.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT location_id FROM warehouse_locations WHERE warehouse_id = :w AND bin_code = :b');
        $st->execute([':w' => $warehouseId, ':b' => $bin]);
        $dup = $st->fetchColumn();
        if ($dup !== false && (int) $dup !== (int) $id) {
            $errors[] = 'Kode rak sudah dipakai di gudang ini.';
        }
    }
    return [['bin' => $bin, 'name' => $name === '' ? null : $name, 'status' => $stat], $errors];
}

function inv_save_location(int $warehouseId, ?int $id, array $d): void
{
    $pdo = db();
    if ($id === null) {
        $pdo->prepare('INSERT INTO warehouse_locations (warehouse_id, bin_code, location_name, status) VALUES (:w, :b, :n, :s)')
            ->execute([':w' => $warehouseId, ':b' => $d['bin'], ':n' => $d['name'], ':s' => $d['status']]);
        return;
    }
    $pdo->prepare('UPDATE warehouse_locations SET bin_code = :b, location_name = :n, status = :s WHERE location_id = :id AND warehouse_id = :w')
        ->execute([':b' => $d['bin'], ':n' => $d['name'], ':s' => $d['status'], ':id' => $id, ':w' => $warehouseId]);
}
