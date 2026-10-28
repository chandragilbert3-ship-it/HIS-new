<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Radiologi.
 * Memakai ORDERS (jenis radiologi), ORDER_ITEMS, RAD_SCHEDULES, RAD_IMAGES, RAD_REPORTS, MASTER_RAD_TESTS.
 * Gambar disimpan di folder storage (tidak bisa diakses langsung dari browser) dan ditampilkan lewat image.php.
 * Laporan final dan gambar tidak diubah/dihapus.
 */

const RAD_MAX_BYTES   = 5242880; // 5 MB
const RAD_MAX_IMAGES  = 10;

function rad_schedule_status_labels(): array
{
    return [
        'scheduled' => ['Terjadwal', 'cp-info'], 'in_progress' => ['Pemeriksaan', 'cp-warn'],
        'completed' => ['Selesai Periksa', 'cp-ok'], 'cancelled' => ['Dibatalkan', 'cp-off'],
    ];
}

function rad_report_status_labels(): array
{
    return ['draft' => ['Draft', 'cp-warn'], 'final' => ['Final', 'cp-ok']];
}

/** Folder penyimpanan gambar (dibuat otomatis, dengan .htaccess penolak akses langsung). */
function rad_storage_root(): string
{
    $root = APP_ROOT . '/storage';
    if (!is_dir($root . '/radiology')) {
        @mkdir($root . '/radiology', 0750, true);
    }
    $ht = $root . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
    return $root;
}

function rad_queue(string $mode): array
{
    $done = $mode === 'done';
    $cond = $done ? "o.status = 'completed' AND o.ordered_at >= :sejak" : "o.status IN ('ordered','in_progress')";
    $stmt = db()->prepare(
        "SELECT o.order_id, o.priority, o.status, o.ordered_at, p.no_rm, p.nama_pasien, md.doctor_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id AND oi.item_type = 'radiology') AS jml_item,
                (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.order_id AND oi2.item_type = 'radiology'
                    AND EXISTS (SELECT 1 FROM rad_reports r WHERE r.order_item_id = oi2.order_item_id AND r.status = 'final')) AS jml_final
           FROM orders o
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.order_type = 'radiology' AND $cond
          ORDER BY o.priority = 'cito' DESC, o.ordered_at LIMIT 200"
    );
    $stmt->execute($done ? [':sejak' => date('Y-m-d H:i:s', strtotime('-7 days'))] : []);
    return $stmt->fetchAll();
}

function rad_find_order(int $orderId): ?array
{
    $stmt = db()->prepare(
        "SELECT o.order_id, o.visit_id, o.priority, o.status, o.ordered_at, o.clinical_reason,
                p.patient_id, p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin, md.doctor_name
           FROM orders o
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.order_id = :id AND o.order_type = 'radiology' LIMIT 1"
    );
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Item radiologi pada order beserta status jadwal terbaru (bukan batal), jumlah gambar, dan status laporan. */
function rad_items(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT oi.order_item_id, oi.instruction, rt.test_name, rt.modality,
                (SELECT s.status FROM rad_schedules s WHERE s.order_item_id = oi.order_item_id AND s.status <> 'cancelled' ORDER BY s.rad_schedule_id DESC LIMIT 1) AS sch_status,
                (SELECT s.scheduled_at FROM rad_schedules s WHERE s.order_item_id = oi.order_item_id AND s.status <> 'cancelled' ORDER BY s.rad_schedule_id DESC LIMIT 1) AS sch_at,
                (SELECT COUNT(*) FROM rad_images i WHERE i.order_item_id = oi.order_item_id) AS jml_img,
                (SELECT r.status FROM rad_reports r WHERE r.order_item_id = oi.order_item_id ORDER BY r.rad_report_id DESC LIMIT 1) AS report_status
           FROM order_items oi
           JOIN master_rad_tests rt ON rt.rad_test_id = oi.rad_test_id
          WHERE oi.order_id = :o AND oi.item_type = 'radiology'
          ORDER BY oi.order_item_id"
    );
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

/** Satu item radiologi beserta order dan pasien. */
function rad_find_item(int $itemId): ?array
{
    $stmt = db()->prepare(
        "SELECT oi.order_item_id, oi.order_id, oi.instruction, rt.test_name, rt.modality,
                o.status AS order_status, o.visit_id, p.patient_id, p.no_rm, p.nama_pasien,
                (SELECT s.status FROM rad_schedules s WHERE s.order_item_id = oi.order_item_id AND s.status <> 'cancelled' ORDER BY s.rad_schedule_id DESC LIMIT 1) AS sch_status
           FROM order_items oi
           JOIN master_rad_tests rt ON rt.rad_test_id = oi.rad_test_id
           JOIN orders o ON o.order_id = oi.order_id AND o.order_type = 'radiology'
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
          WHERE oi.order_item_id = :i AND oi.item_type = 'radiology' LIMIT 1"
    );
    $stmt->execute([':i' => $itemId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function rad_images(int $itemId): array
{
    $stmt = db()->prepare('SELECT image_id, image_uri, metadata, created_at FROM rad_images WHERE order_item_id = :i ORDER BY image_id');
    $stmt->execute([':i' => $itemId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $m = json_decode((string) $r['metadata'], true);
        $r['meta'] = is_array($m) ? $m : [];
    }
    return $rows;
}

function rad_report(int $itemId): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, md.doctor_name FROM rad_reports r JOIN master_doctors md ON md.doctor_id = r.radiologist_id
          WHERE r.order_item_id = :i ORDER BY r.rad_report_id DESC LIMIT 1'
    );
    $stmt->execute([':i' => $itemId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Validasi waktu jadwal. Hasil: [datetime|null, error|null]. */
function rad_validate_schedule(string $when, string $orderedAt): array
{
    $when = trim($when);
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $when);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== $when) {
        return [null, 'Waktu jadwal tidak valid.'];
    }
    $s = $dt->format('Y-m-d H:i:s');
    if ($s < date('Y-m-d H:i:s', strtotime($orderedAt))) {
        return [null, 'Jadwal tidak boleh sebelum waktu order.'];
    }
    if ($s < date('Y-m-d H:i:s', strtotime('-5 minutes'))) {
        return [null, 'Jadwal tidak boleh di masa lalu.'];
    }
    if ($s > date('Y-m-d H:i:s', strtotime('+90 days'))) {
        return [null, 'Jadwal maksimal 90 hari ke depan.'];
    }
    return [$s, null];
}

/** Kunci baris order milik item; hasil status order atau null bila item radiologi tidak ada. */
function rad_lock_order(PDO $pdo, int $itemId): ?array
{
    $st = $pdo->prepare(
        "SELECT o.order_id, o.status FROM orders o JOIN order_items oi ON oi.order_id = o.order_id
          WHERE oi.order_item_id = :i AND oi.item_type = 'radiology' AND o.order_type = 'radiology' FOR UPDATE"
    );
    $st->execute([':i' => $itemId]);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function rad_latest_schedule(PDO $pdo, int $itemId): ?array
{
    $st = $pdo->prepare("SELECT rad_schedule_id, status FROM rad_schedules WHERE order_item_id = :i AND status <> 'cancelled' ORDER BY rad_schedule_id DESC LIMIT 1 FOR UPDATE");
    $st->execute([':i' => $itemId]);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

/** Buat jadwal pemeriksaan. Hasil: null jika sukses, atau teks error. */
function rad_schedule(int $itemId, string $when): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = rad_lock_order($pdo, $itemId);
        if ($o === null || !in_array($o['status'], ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Order radiologi tidak aktif.';
        }
        $ord = $pdo->prepare('SELECT ordered_at FROM orders WHERE order_id = :o');
        $ord->execute([':o' => $o['order_id']]);
        [$dt, $err] = rad_validate_schedule($when, (string) $ord->fetchColumn());
        if ($err !== null) {
            $pdo->rollBack();
            return $err;
        }
        if (rad_latest_schedule($pdo, $itemId) !== null) {
            $pdo->rollBack();
            return 'Pemeriksaan ini sudah memiliki jadwal aktif atau sudah selesai diperiksa.';
        }
        $pdo->prepare("INSERT INTO rad_schedules (order_item_id, scheduled_at, status) VALUES (:i, :t, 'scheduled')")->execute([':i' => $itemId, ':t' => $dt]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Langkah jadwal: 'start' (scheduled->in_progress), 'cancel' (scheduled->cancelled), 'complete' (in_progress->completed, wajib ada gambar). */
function rad_schedule_step(int $itemId, string $step): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = rad_lock_order($pdo, $itemId);
        if ($o === null || !in_array($o['status'], ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Order radiologi tidak aktif.';
        }
        $s = rad_latest_schedule($pdo, $itemId);
        if ($s === null) {
            $pdo->rollBack();
            return 'Belum ada jadwal untuk pemeriksaan ini.';
        }
        $sid = (int) $s['rad_schedule_id'];
        if ($step === 'start') {
            if ($s['status'] !== 'scheduled') {
                $pdo->rollBack();
                return 'Hanya jadwal berstatus "Terjadwal" yang bisa dimulai.';
            }
            $pdo->prepare("UPDATE rad_schedules SET status = 'in_progress' WHERE rad_schedule_id = :s")->execute([':s' => $sid]);
            $pdo->prepare("UPDATE orders SET status = 'in_progress' WHERE order_id = :o AND status = 'ordered'")->execute([':o' => $o['order_id']]);
        } elseif ($step === 'cancel') {
            if ($s['status'] !== 'scheduled') {
                $pdo->rollBack();
                return 'Hanya jadwal berstatus "Terjadwal" yang bisa dibatalkan.';
            }
            $pdo->prepare("UPDATE rad_schedules SET status = 'cancelled' WHERE rad_schedule_id = :s")->execute([':s' => $sid]);
        } elseif ($step === 'complete') {
            if ($s['status'] !== 'in_progress') {
                $pdo->rollBack();
                return 'Hanya pemeriksaan berstatus "Pemeriksaan" yang bisa diselesaikan.';
            }
            $c = $pdo->prepare('SELECT COUNT(*) FROM rad_images WHERE order_item_id = :i');
            $c->execute([':i' => $itemId]);
            if ((int) $c->fetchColumn() === 0) {
                $pdo->rollBack();
                return 'Unggah minimal satu gambar sebelum menyelesaikan pemeriksaan.';
            }
            $pdo->prepare("UPDATE rad_schedules SET status = 'completed' WHERE rad_schedule_id = :s")->execute([':s' => $sid]);
        } else {
            $pdo->rollBack();
            return 'Aksi tidak dikenal.';
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

/** Periksa berkas gambar. Hasil: [ext, meta] atau RuntimeException. Hanya JPG/PNG. */
function rad_inspect_image(string $path, int $size): array
{
    if ($size <= 0 || $size > RAD_MAX_BYTES) {
        throw new RuntimeException('Ukuran gambar harus antara 1 byte dan 5 MB.');
    }
    $info = @getimagesize($path);
    if ($info === false) {
        throw new RuntimeException('Berkas bukan gambar yang valid.');
    }
    $map = [IMAGETYPE_JPEG => ['jpg', 'image/jpeg'], IMAGETYPE_PNG => ['png', 'image/png']];
    if (!isset($map[$info[2]])) {
        throw new RuntimeException('Format gambar harus JPG atau PNG.');
    }
    if ($info[0] > 10000 || $info[1] > 10000) {
        throw new RuntimeException('Dimensi gambar terlalu besar (maksimal 10000 px).');
    }
    $fi = new finfo(FILEINFO_MIME_TYPE);
    if ($fi->file($path) !== $map[$info[2]][1]) {
        throw new RuntimeException('Tipe berkas tidak konsisten dengan isinya.');
    }
    return [$map[$info[2]][0], ['mime' => $map[$info[2]][1], 'width' => $info[0], 'height' => $info[1], 'bytes' => $size]];
}

/** Simpan gambar unggahan. Hasil: null jika sukses, atau teks error. */
function rad_store_image(int $itemId, array $file, int $userId): ?string
{
    $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code === UPLOAD_ERR_NO_FILE) {
        return 'Pilih berkas gambar terlebih dahulu.';
    }
    if ($code !== UPLOAD_ERR_OK) {
        return 'Unggah gagal (kode ' . $code . '). Pastikan ukuran maksimal 5 MB.';
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return 'Berkas unggahan tidak valid.';
    }
    try {
        [$ext, $meta] = rad_inspect_image($tmp, (int) ($file['size'] ?? 0));
    } catch (RuntimeException $ex) {
        return $ex->getMessage();
    }
    $orig = preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) ($file['name'] ?? ''))) ?? '';
    $meta['original']    = mb_substr($orig, 0, 100);
    $meta['uploaded_by'] = $userId;

    $root = rad_storage_root();
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $root . '/radiology/' . $name;

    $pdo = db();
    $pdo->beginTransaction();
    $moved = false;
    try {
        $o = rad_lock_order($pdo, $itemId);
        if ($o === null || !in_array($o['status'], ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Order radiologi tidak aktif.';
        }
        $s = rad_latest_schedule($pdo, $itemId);
        if ($s === null || !in_array($s['status'], ['in_progress', 'completed'], true)) {
            $pdo->rollBack();
            return 'Gambar hanya bisa diunggah saat pemeriksaan berlangsung atau sudah selesai.';
        }
        $rep = $pdo->prepare("SELECT COUNT(*) FROM rad_reports WHERE order_item_id = :i AND status = 'final'");
        $rep->execute([':i' => $itemId]);
        if ((int) $rep->fetchColumn() > 0) {
            $pdo->rollBack();
            return 'Laporan sudah final. Gambar tidak dapat ditambahkan.';
        }
        $c = $pdo->prepare('SELECT COUNT(*) FROM rad_images WHERE order_item_id = :i');
        $c->execute([':i' => $itemId]);
        if ((int) $c->fetchColumn() >= RAD_MAX_IMAGES) {
            $pdo->rollBack();
            return 'Maksimal ' . RAD_MAX_IMAGES . ' gambar per pemeriksaan.';
        }
        if (!move_uploaded_file($tmp, $dest)) {
            $pdo->rollBack();
            return 'Gagal menyimpan berkas. Periksa izin folder storage.';
        }
        $moved = true;
        @chmod($dest, 0640);
        $pdo->prepare('INSERT INTO rad_images (order_item_id, image_uri, metadata, created_at) VALUES (:i, :u, :m, :t)')
            ->execute([':i' => $itemId, ':u' => 'radiology/' . $name, ':m' => json_encode($meta, JSON_UNESCAPED_UNICODE), ':t' => date('Y-m-d H:i:s')]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($moved && is_file($dest)) {
            @unlink($dest);
        }
        throw $ex;
    }
}

/** Data gambar untuk ditampilkan (beserta visit_id untuk audit), atau null. */
function rad_image_for_view(int $imageId): ?array
{
    $stmt = db()->prepare(
        'SELECT i.image_id, i.image_uri, o.visit_id
           FROM rad_images i JOIN order_items oi ON oi.order_item_id = i.order_item_id JOIN orders o ON o.order_id = oi.order_id
          WHERE i.image_id = :id'
    );
    $stmt->execute([':id' => $imageId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Path berkas aman untuk sebuah image_uri, atau null bila tidak sesuai pola. */
function rad_image_path(string $uri): ?string
{
    if (!preg_match('#^radiology/[a-f0-9]{32}\.(jpg|png)$#', $uri)) {
        return null;
    }
    $path = rad_storage_root() . '/' . $uri;
    return is_file($path) ? $path : null;
}

/** Validasi isi laporan. Hasil: [data, errors]. */
function rad_validate_report(array $in, bool $final): array
{
    $errors = [];
    $interp = is_string($in['interpretation'] ?? null) ? trim($in['interpretation']) : '';
    $concl  = is_string($in['conclusion'] ?? null) ? trim($in['conclusion']) : '';
    if (mb_strlen($interp) > 5000) {
        $errors[] = 'Temuan/interpretasi maksimal 5000 karakter.';
    }
    if (mb_strlen($concl) > 2000) {
        $errors[] = 'Kesimpulan maksimal 2000 karakter.';
    }
    if ($final && ($interp === '' || $concl === '')) {
        $errors[] = 'Temuan dan kesimpulan wajib diisi sebelum laporan difinalkan.';
    }
    if (!$final && $interp === '' && $concl === '') {
        $errors[] = 'Isi minimal salah satu bagian untuk menyimpan draft.';
    }
    return [['interpretation' => $interp === '' ? null : $interp, 'conclusion' => $concl === '' ? null : $concl], $errors];
}

function rad_complete_if_done(PDO $pdo, int $orderId): void
{
    $c = $pdo->prepare(
        "SELECT COUNT(*) FROM order_items oi
          WHERE oi.order_id = :o AND oi.item_type = 'radiology'
            AND NOT EXISTS (SELECT 1 FROM rad_reports r WHERE r.order_item_id = oi.order_item_id AND r.status = 'final')"
    );
    $c->execute([':o' => $orderId]);
    if ((int) $c->fetchColumn() === 0) {
        $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_id = :o AND status IN ('ordered','in_progress')")->execute([':o' => $orderId]);
    }
}

/** Simpan laporan (draft atau final). Hasil: null jika sukses, atau teks error. */
function rad_save_report(int $itemId, int $doctorId, array $data, bool $final): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = rad_lock_order($pdo, $itemId);
        if ($o === null || !in_array($o['status'], ['ordered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Order radiologi tidak aktif.';
        }
        $s = rad_latest_schedule($pdo, $itemId);
        if ($s === null || $s['status'] !== 'completed') {
            $pdo->rollBack();
            return 'Laporan hanya dapat dibuat setelah pemeriksaan diselesaikan.';
        }
        $ex = $pdo->prepare('SELECT rad_report_id, status FROM rad_reports WHERE order_item_id = :i ORDER BY rad_report_id DESC LIMIT 1 FOR UPDATE');
        $ex->execute([':i' => $itemId]);
        $rep = $ex->fetch();
        if ($rep && $rep['status'] === 'final') {
            $pdo->rollBack();
            return 'Laporan sudah final dan tidak dapat diubah.';
        }
        $status = $final ? 'final' : 'draft';
        if ($rep) {
            $stmt = $pdo->prepare('UPDATE rad_reports SET radiologist_id = :d, interpretation = :i, conclusion = :c, status = :s WHERE rad_report_id = :id');
            $stmt->bindValue(':id', (int) $rep['rad_report_id'], PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare('INSERT INTO rad_reports (order_item_id, radiologist_id, interpretation, conclusion, status) VALUES (:oi, :d, :i, :c, :s)');
            $stmt->bindValue(':oi', $itemId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':d', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':i', $data['interpretation'], $data['interpretation'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':c', $data['conclusion'], $data['conclusion'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':s', $status);
        $stmt->execute();
        if ($final) {
            rad_complete_if_done($pdo, (int) $o['order_id']);
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

/** Laporan final pada satu kunjungan beserta id gambarnya (untuk EMR). */
function rad_visit_reports(int $visitId): array
{
    $stmt = db()->prepare(
        "SELECT r.order_item_id, r.interpretation, r.conclusion, rt.test_name, rt.modality, o.order_id, o.ordered_at, md.doctor_name
           FROM rad_reports r
           JOIN order_items oi ON oi.order_item_id = r.order_item_id
           JOIN orders o ON o.order_id = oi.order_id
           JOIN master_rad_tests rt ON rt.rad_test_id = oi.rad_test_id
           JOIN master_doctors md ON md.doctor_id = r.radiologist_id
          WHERE o.visit_id = :v AND r.status = 'final'
          ORDER BY o.order_id DESC, r.rad_report_id"
    );
    $stmt->execute([':v' => $visitId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }
    $ids = array_map(fn($r) => (int) $r['order_item_id'], $rows);
    $in  = implode(',', $ids);
    $img = db()->query("SELECT image_id, order_item_id FROM rad_images WHERE order_item_id IN ($in) ORDER BY image_id")->fetchAll();
    $by  = [];
    foreach ($img as $i) {
        $by[(int) $i['order_item_id']][] = (int) $i['image_id'];
    }
    foreach ($rows as &$r) {
        $r['image_ids'] = $by[(int) $r['order_item_id']] ?? [];
    }
    return $rows;
}