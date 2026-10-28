<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Rawat Inap.
 * Memakai INPATIENT_ADMISSIONS, INPATIENT_PROGRESS_NOTES, BEDS, MASTER_ROOMS, BED_TRANSFER_HISTORY
 * + ORDERS (order admisi), VISITS, PATIENTS. Catatan perkembangan bersifat tambah-saja.
 */

function ipd_status_labels(): array
{
    return [
        'admitted'          => ['Dirawat', 'cp-info'],
        'discharge_ordered' => ['Rencana Pulang', 'cp-warn'],
        'discharged'        => ['Pulang', 'cp-ok'],
        'cancelled'         => ['Dibatalkan', 'cp-off'],
    ];
}

function ipd_bed_status_labels(): array
{
    return [
        'available'   => ['Tersedia', 'cp-ok'],
        'occupied'    => ['Terisi', 'cp-info'],
        'cleaning'    => ['Dibersihkan', 'cp-warn'],
        'maintenance' => ['Perbaikan', 'cp-off'],
    ];
}

function ipd_room_classes(): array
{
    return ['VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'ICU', 'Isolasi'];
}

function ipd_is_active_status(string $s): bool
{
    return in_array($s, ['admitted', 'discharge_ordered'], true);
}

/** Pasien rawat inap yang masih dirawat. */
function ipd_active_cases(): array
{
    return db()->query(
        "SELECT a.inpatient_id, a.no_rawat_inap, a.status, a.admitted_at, p.no_rm, p.nama_pasien,
                b.bed_no, r.room_name, r.unit_name, md.doctor_name AS dpjp_name,
                (SELECT COUNT(*) FROM patient_allergies al WHERE al.patient_id = p.patient_id) AS jml_alergi
           FROM inpatient_admissions a
           JOIN patients p ON p.patient_id = a.patient_id
           JOIN beds b ON b.bed_id = a.bed_id
           JOIN master_rooms r ON r.room_id = b.room_id
           LEFT JOIN master_doctors md ON md.doctor_id = a.dpjp_id
          WHERE a.status IN ('admitted','discharge_ordered')
          ORDER BY a.admitted_at DESC"
    )->fetchAll();
}

/** Order admisi (30 hari terakhir) yang belum diproses menjadi rawat inap. */
function ipd_pending_orders(): array
{
    $stmt = db()->prepare(
        "SELECT o.order_id, o.ordered_at, o.priority, o.clinical_reason, o.visit_id,
                p.patient_id, p.no_rm, p.nama_pasien, md.doctor_name
           FROM orders o
           JOIN visits v ON v.visit_id = o.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
           LEFT JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.order_type = 'admission' AND o.status IN ('ordered','in_progress')
            AND o.ordered_at >= :sejak
            AND NOT EXISTS (SELECT 1 FROM inpatient_admissions a WHERE a.admission_order_id = o.order_id)
            AND NOT EXISTS (SELECT 1 FROM inpatient_admissions a2 WHERE a2.patient_id = p.patient_id AND a2.status IN ('admitted','discharge_ordered'))
          ORDER BY o.ordered_at LIMIT 100"
    );
    $stmt->execute([':sejak' => date('Y-m-d H:i:s', strtotime('-30 days'))]);
    return $stmt->fetchAll();
}

function ipd_find_order(int $orderId): ?array
{
    $stmt = db()->prepare(
        "SELECT o.order_id, o.visit_id, o.status, o.order_type, o.ordering_doctor_id, o.clinical_reason,
                p.patient_id, p.no_rm, p.nama_pasien
           FROM orders o JOIN visits v ON v.visit_id = o.visit_id JOIN patients p ON p.patient_id = v.patient_id
          WHERE o.order_id = :id AND o.order_type = 'admission' LIMIT 1"
    );
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function ipd_free_beds(): array
{
    return db()->query(
        "SELECT b.bed_id, b.bed_no, r.room_name, r.unit_name, r.room_class
           FROM beds b JOIN master_rooms r ON r.room_id = b.room_id
          WHERE b.status = 'available' AND r.is_active = 1
          ORDER BY r.unit_name, r.room_name, b.bed_no"
    )->fetchAll();
}

function ipd_doctors(): array
{
    return db()->query('SELECT doctor_id, doctor_name FROM master_doctors WHERE is_active = 1 ORDER BY doctor_name')->fetchAll();
}

/** Peta tempat tidur beserta pasien yang menempati. */
function ipd_beds_overview(): array
{
    return db()->query(
        "SELECT b.bed_id, b.bed_no, b.status, r.room_id, r.room_name, r.unit_name, r.room_class, r.is_active,
                a.inpatient_id, p.nama_pasien
           FROM beds b
           JOIN master_rooms r ON r.room_id = b.room_id
           LEFT JOIN inpatient_admissions a ON a.bed_id = b.bed_id AND a.status IN ('admitted','discharge_ordered')
           LEFT JOIN patients p ON p.patient_id = a.patient_id
          ORDER BY r.unit_name, r.room_name, b.bed_no"
    )->fetchAll();
}

/** Proses admisi dari order. Hasil: inpatient_id. RuntimeException bila aturan dilanggar. */
function ipd_admit(int $orderId, int $bedId, int $dpjpId): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare("SELECT order_id, visit_id, status, order_type FROM orders WHERE order_id = :id FOR UPDATE");
        $o->execute([':id' => $orderId]);
        $order = $o->fetch();
        if (!$order || $order['order_type'] !== 'admission' || !in_array($order['status'], ['ordered', 'in_progress'], true)) {
            throw new RuntimeException('Order admisi tidak ditemukan atau sudah diproses/dibatalkan.');
        }
        $c = $pdo->prepare('SELECT COUNT(*) FROM inpatient_admissions WHERE admission_order_id = :o');
        $c->execute([':o' => $orderId]);
        if ((int) $c->fetchColumn() > 0) {
            throw new RuntimeException('Order ini sudah diproses menjadi rawat inap.');
        }

        $v = $pdo->prepare('SELECT patient_id, polyclinic_id, patient_insurance_id FROM visits WHERE visit_id = :v');
        $v->execute([':v' => $order['visit_id']]);
        $src = $v->fetch();
        if (!$src) {
            throw new RuntimeException('Kunjungan asal order tidak ditemukan.');
        }
        $patientId = (int) $src['patient_id'];

        $act = $pdo->prepare("SELECT COUNT(*) FROM inpatient_admissions WHERE patient_id = :p AND status IN ('admitted','discharge_ordered')");
        $act->execute([':p' => $patientId]);
        if ((int) $act->fetchColumn() > 0) {
            throw new RuntimeException('Pasien ini masih tercatat dirawat inap.');
        }

        $b = $pdo->prepare('SELECT b.status, r.is_active FROM beds b JOIN master_rooms r ON r.room_id = b.room_id WHERE b.bed_id = :b FOR UPDATE');
        $b->execute([':b' => $bedId]);
        $bed = $b->fetch();
        if (!$bed || $bed['status'] !== 'available' || (int) $bed['is_active'] !== 1) {
            throw new RuntimeException('Tempat tidur tidak tersedia. Pilih tempat tidur lain.');
        }

        $d = $pdo->prepare('SELECT COUNT(*) FROM master_doctors WHERE doctor_id = :d AND is_active = 1');
        $d->execute([':d' => $dpjpId]);
        if ((int) $d->fetchColumn() === 0) {
            throw new RuntimeException('DPJP tidak valid.');
        }

        $now = date('Y-m-d H:i:s');
        $vi  = $pdo->prepare(
            'INSERT INTO visits (patient_id, doctor_id, polyclinic_id, patient_insurance_id, service_type, visit_date, status)
             VALUES (:p, :d, :poli, :ins, :svc, :tgl, :st)'
        );
        $vi->bindValue(':p', $patientId, PDO::PARAM_INT);
        $vi->bindValue(':d', $dpjpId, PDO::PARAM_INT);
        $vi->bindValue(':poli', $src['polyclinic_id'], $src['polyclinic_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $vi->bindValue(':ins', $src['patient_insurance_id'], $src['patient_insurance_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $vi->bindValue(':svc', 'inpatient');
        $vi->bindValue(':tgl', $now);
        $vi->bindValue(':st', 'in_progress');
        $vi->execute();
        $newVisit = (int) $pdo->lastInsertId();

        $prefix = 'RI-' . date('Ymd') . '-';
        $m = $pdo->prepare('SELECT MAX(no_rawat_inap) FROM inpatient_admissions WHERE no_rawat_inap LIKE :pre');
        $m->execute([':pre' => $prefix . '%']);
        $last = (string) $m->fetchColumn();
        $seq  = $last !== '' ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        $no   = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        $ins = $pdo->prepare(
            "INSERT INTO inpatient_admissions (patient_id, visit_id, bed_id, dpjp_id, admission_order_id, no_rawat_inap, status, admitted_at)
             VALUES (:p, :v, :b, :d, :o, :no, 'admitted', :at)"
        );
        $ins->execute([':p' => $patientId, ':v' => $newVisit, ':b' => $bedId, ':d' => $dpjpId, ':o' => $orderId, ':no' => $no, ':at' => $now]);
        $id = (int) $pdo->lastInsertId();

        $pdo->prepare("UPDATE beds SET status = 'occupied' WHERE bed_id = :b")->execute([':b' => $bedId]);
        $pdo->prepare("UPDATE orders SET status = 'completed' WHERE order_id = :o")->execute([':o' => $orderId]);
        $pdo->commit();
        return $id;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '23000') {
            throw new RuntimeException('Nomor rawat inap bentrok atau data ganda. Silakan ulangi.');
        }
        throw $ex;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function ipd_find_case(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin,
                b.bed_no, r.room_name, r.unit_name, r.room_class, md.doctor_name AS dpjp_name, v.status AS visit_status
           FROM inpatient_admissions a
           JOIN patients p ON p.patient_id = a.patient_id
           JOIN visits v ON v.visit_id = a.visit_id
           JOIN beds b ON b.bed_id = a.bed_id
           JOIN master_rooms r ON r.room_id = b.room_id
           LEFT JOIN master_doctors md ON md.doctor_id = a.dpjp_id
          WHERE a.inpatient_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function ipd_notes(int $id): array
{
    $stmt = db()->prepare(
        'SELECT n.soap_note, n.note_date, md.doctor_name FROM inpatient_progress_notes n
           LEFT JOIN master_doctors md ON md.doctor_id = n.doctor_id
          WHERE n.inpatient_id = :id ORDER BY n.progress_note_id DESC'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetchAll();
}

/** Validasi catatan perkembangan SOAP. Hasil: [teks gabungan, errors]. */
function ipd_validate_note(array $in): array
{
    $errors = [];
    $parts  = [];
    foreach (['s' => 'Subjective (S)', 'o' => 'Objective (O)', 'a' => 'Assessment (A)', 'p' => 'Plan (P)'] as $k => $label) {
        $val = is_string($in[$k] ?? null) ? trim($in[$k]) : '';
        if ($val === '' || mb_strlen($val) > 1500) {
            $errors[] = "$label wajib diisi (maksimal 1500 karakter).";
        }
        $parts[] = strtoupper($k) . ': ' . $val;
    }
    return [implode("\n", $parts), $errors];
}

/** Tambah catatan perkembangan (tambah-saja). Hasil: null jika sukses, atau teks error. */
function ipd_add_note(int $id, int $doctorId, string $text): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM inpatient_admissions WHERE inpatient_id = :id FOR UPDATE');
        $st->execute([':id' => $id]);
        $status = $st->fetchColumn();
        if (!is_string($status) || !ipd_is_active_status($status)) {
            $pdo->rollBack();
            return 'Catatan hanya bisa ditambahkan pada pasien yang masih dirawat.';
        }
        $pdo->prepare('INSERT INTO inpatient_progress_notes (inpatient_id, doctor_id, soap_note, note_date) VALUES (:i, :d, :s, :t)')
            ->execute([':i' => $id, ':d' => $doctorId, ':s' => $text, ':t' => date('Y-m-d H:i:s')]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function ipd_transfers(int $id): array
{
    $stmt = db()->prepare(
        "SELECT t.transferred_at, u.nama AS user_name,
                CONCAT(fr.room_name, ' / ', fb.bed_no) AS dari, CONCAT(tr.room_name, ' / ', tb.bed_no) AS ke
           FROM bed_transfer_history t
           JOIN beds fb ON fb.bed_id = t.from_bed_id JOIN master_rooms fr ON fr.room_id = fb.room_id
           JOIN beds tb ON tb.bed_id = t.to_bed_id   JOIN master_rooms tr ON tr.room_id = tb.room_id
           LEFT JOIN users u ON u.user_id = t.transferred_by
          WHERE t.inpatient_id = :id ORDER BY t.transfer_id DESC"
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetchAll();
}

/** Pindah tempat tidur. Hasil: null jika sukses, atau teks error. */
function ipd_transfer(int $id, int $toBedId, int $userId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, bed_id FROM inpatient_admissions WHERE inpatient_id = :id FOR UPDATE');
        $st->execute([':id' => $id]);
        $a = $st->fetch();
        if (!$a || $a['status'] !== 'admitted') {
            $pdo->rollBack();
            return 'Pindah tempat tidur hanya untuk pasien berstatus "Dirawat".';
        }
        $from = (int) $a['bed_id'];
        if ($from === $toBedId) {
            $pdo->rollBack();
            return 'Tempat tidur tujuan sama dengan tempat tidur saat ini.';
        }
        $ids = [$from, $toBedId];
        sort($ids);
        $lock = $pdo->prepare('SELECT b.bed_id, b.status, r.is_active FROM beds b JOIN master_rooms r ON r.room_id = b.room_id WHERE b.bed_id = :b FOR UPDATE');
        $info = [];
        foreach ($ids as $bid) {
            $lock->execute([':b' => $bid]);
            $info[$bid] = $lock->fetch();
        }
        if (!$info[$toBedId] || $info[$toBedId]['status'] !== 'available' || (int) $info[$toBedId]['is_active'] !== 1) {
            $pdo->rollBack();
            return 'Tempat tidur tujuan tidak tersedia.';
        }
        $pdo->prepare("UPDATE beds SET status = 'cleaning' WHERE bed_id = :b")->execute([':b' => $from]);
        $pdo->prepare("UPDATE beds SET status = 'occupied' WHERE bed_id = :b")->execute([':b' => $toBedId]);
        $pdo->prepare('UPDATE inpatient_admissions SET bed_id = :b WHERE inpatient_id = :id')->execute([':b' => $toBedId, ':id' => $id]);
        $pdo->prepare('INSERT INTO bed_transfer_history (inpatient_id, from_bed_id, to_bed_id, transferred_by, transferred_at) VALUES (:i, :f, :t, :u, :a)')
            ->execute([':i' => $id, ':f' => $from, ':t' => $toBedId, ':u' => $userId, ':a' => date('Y-m-d H:i:s')]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Alur pulang: 'order' (admitted -> discharge_ordered), 'revoke' (kembali), 'confirm' (-> discharged). */
function ipd_discharge_step(int $id, string $step): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, bed_id, visit_id FROM inpatient_admissions WHERE inpatient_id = :id FOR UPDATE');
        $st->execute([':id' => $id]);
        $a = $st->fetch();
        if (!$a) {
            $pdo->rollBack();
            return 'Data rawat inap tidak ditemukan.';
        }
        if ($step === 'order') {
            if ($a['status'] !== 'admitted') {
                $pdo->rollBack();
                return 'Rencana pulang hanya untuk pasien berstatus "Dirawat".';
            }
            $n = $pdo->prepare('SELECT COUNT(*) FROM inpatient_progress_notes WHERE inpatient_id = :id');
            $n->execute([':id' => $id]);
            if ((int) $n->fetchColumn() === 0) {
                $pdo->rollBack();
                return 'Catat minimal satu catatan perkembangan sebelum menetapkan rencana pulang.';
            }
            $pdo->prepare("UPDATE inpatient_admissions SET status = 'discharge_ordered' WHERE inpatient_id = :id")->execute([':id' => $id]);
        } elseif ($step === 'revoke') {
            if ($a['status'] !== 'discharge_ordered') {
                $pdo->rollBack();
                return 'Hanya rencana pulang yang bisa dibatalkan.';
            }
            $pdo->prepare("UPDATE inpatient_admissions SET status = 'admitted' WHERE inpatient_id = :id")->execute([':id' => $id]);
        } elseif ($step === 'confirm') {
            if ($a['status'] !== 'discharge_ordered') {
                $pdo->rollBack();
                return 'Pemulangan hanya bisa dikonfirmasi setelah rencana pulang ditetapkan dokter.';
            }
            $pdo->prepare("UPDATE inpatient_admissions SET status = 'discharged', discharged_at = :t WHERE inpatient_id = :id")
                ->execute([':t' => date('Y-m-d H:i:s'), ':id' => $id]);
            $pdo->prepare("UPDATE beds SET status = 'cleaning' WHERE bed_id = :b")->execute([':b' => $a['bed_id']]);
            $pdo->prepare("UPDATE visits SET status = 'completed' WHERE visit_id = :v")->execute([':v' => $a['visit_id']]);
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

/** Ubah status tempat tidur: cleaning->available, available<->maintenance. */
function ipd_set_bed_status(int $bedId, string $new): ?string
{
    $allowed = ['cleaning' => ['available'], 'available' => ['maintenance'], 'maintenance' => ['available']];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM beds WHERE bed_id = :b FOR UPDATE');
        $st->execute([':b' => $bedId]);
        $cur = $st->fetchColumn();
        if (!is_string($cur) || !in_array($new, $allowed[$cur] ?? [], true)) {
            $pdo->rollBack();
            return 'Perubahan status tempat tidur tidak diizinkan dari kondisi saat ini.';
        }
        $pdo->prepare('UPDATE beds SET status = :s WHERE bed_id = :b')->execute([':s' => $new, ':b' => $bedId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Kamar beserta jumlah tempat tidur. */
function ipd_rooms(): array
{
    return db()->query(
        "SELECT r.room_id, r.unit_name, r.room_name, r.room_class, r.is_active,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.room_id) AS jml_bed,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.room_id AND b.status = 'occupied') AS jml_terisi
           FROM master_rooms r ORDER BY r.unit_name, r.room_name"
    )->fetchAll();
}

function ipd_validate_room(array $in): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
    $unit = $get('unit_name');
    $name = $get('room_name');
    $cls  = $get('room_class');
    if ($unit === '' || mb_strlen($unit) > 100) {
        $errors[] = 'Unit/bangsal wajib diisi (maksimal 100 karakter).';
    }
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Nama kamar wajib diisi (maksimal 100 karakter).';
    }
    if ($cls !== '' && !in_array($cls, ipd_room_classes(), true)) {
        $errors[] = 'Kelas kamar tidak valid.';
    }
    return [['unit' => $unit, 'name' => $name, 'class' => $cls === '' ? null : $cls], $errors];
}

function ipd_add_room(array $d): ?string
{
    $c = db()->prepare('SELECT COUNT(*) FROM master_rooms WHERE unit_name = :u AND room_name = :n');
    $c->execute([':u' => $d['unit'], ':n' => $d['name']]);
    if ((int) $c->fetchColumn() > 0) {
        return 'Kamar dengan unit dan nama yang sama sudah ada.';
    }
    $ins = db()->prepare('INSERT INTO master_rooms (unit_name, room_name, room_class, is_active) VALUES (:u, :n, :c, 1)');
    $ins->bindValue(':u', $d['unit']);
    $ins->bindValue(':n', $d['name']);
    $ins->bindValue(':c', $d['class'], $d['class'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $ins->execute();
    return null;
}

function ipd_add_bed(int $roomId, string $bedNo): ?string
{
    $bedNo = trim($bedNo);
    if ($bedNo === '' || mb_strlen($bedNo) > 20 || !preg_match('/^[A-Za-z0-9 .\-]+$/', $bedNo)) {
        return 'Nomor tempat tidur wajib diisi (huruf/angka, maksimal 20 karakter).';
    }
    $r = db()->prepare('SELECT COUNT(*) FROM master_rooms WHERE room_id = :r AND is_active = 1');
    $r->execute([':r' => $roomId]);
    if ((int) $r->fetchColumn() === 0) {
        return 'Kamar tidak ditemukan atau tidak aktif.';
    }
    $c = db()->prepare('SELECT COUNT(*) FROM beds WHERE room_id = :r AND bed_no = :n');
    $c->execute([':r' => $roomId, ':n' => $bedNo]);
    if ((int) $c->fetchColumn() > 0) {
        return 'Nomor tempat tidur sudah ada di kamar ini.';
    }
    db()->prepare("INSERT INTO beds (room_id, bed_no, status) VALUES (:r, :n, 'available')")->execute([':r' => $roomId, ':n' => $bedNo]);
    return null;
}

/** Aktif/nonaktifkan kamar. Nonaktif ditolak bila ada tempat tidur terisi. */
function ipd_set_room_active(int $roomId, bool $active): ?string
{
    if (!$active) {
        $c = db()->prepare("SELECT COUNT(*) FROM beds WHERE room_id = :r AND status = 'occupied'");
        $c->execute([':r' => $roomId]);
        if ((int) $c->fetchColumn() > 0) {
            return 'Kamar tidak dapat dinonaktifkan karena masih ada tempat tidur terisi.';
        }
    }
    db()->prepare('UPDATE master_rooms SET is_active = :a WHERE room_id = :r')->execute([':a' => $active ? 1 : 0, ':r' => $roomId]);
    return null;
}