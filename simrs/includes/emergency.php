<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Gawat Darurat (IGD).
 * Memakai ED_ENCOUNTERS, ED_TRIAGE, ED_OBSERVATIONS, ED_DISPOSITIONS + VISITS, QUEUE.
 * Rentang angka tanda vital hanya batas kewajaran input (cegah salah ketik), BUKAN kriteria klinis.
 */

function ed_priority_labels(): array
{
    return [
        'merah'  => ['Merah (Segera)', 'cp-off'],
        'kuning' => ['Kuning (Urgen)', 'cp-warn'],
        'hijau'  => ['Hijau (Tidak Urgen)', 'cp-ok'],
        'hitam'  => ['Hitam', 'cp-black'],
    ];
}

function ed_status_labels(): array
{
    return [
        'waiting'      => ['Menunggu', 'cp-info'],
        'in_treatment' => ['Ditangani', 'cp-warn'],
        'observation'  => ['Observasi', 'cp-info'],
        'completed'    => ['Selesai', 'cp-ok'],
        'cancelled'    => ['Dibatalkan', 'cp-off'],
    ];
}

function ed_arrival_labels(): array
{
    return ['walk_in' => 'Datang sendiri', 'ambulance' => 'Ambulans', 'referral' => 'Rujukan', 'other' => 'Lainnya'];
}

function ed_consciousness_labels(): array
{
    return ['compos mentis' => 'Compos mentis', 'somnolen' => 'Somnolen', 'sopor' => 'Sopor', 'koma' => 'Koma'];
}

function ed_disposition_labels(): array
{
    return ['home' => 'Pulang', 'admit' => 'Rawat Inap', 'refer' => 'Rujuk', 'deceased' => 'Meninggal'];
}

/** Kasus IGD aktif, urut prioritas triase lalu waktu datang. */
function ed_active_cases(): array
{
    $sql = "SELECT * FROM (
              SELECT e.ed_encounter_id, e.arrival_time, e.arrival_mode, e.status,
                     v.visit_id, p.patient_id, p.no_rm, p.nama_pasien,
                     (SELECT t.triage_priority FROM ed_triage t WHERE t.ed_encounter_id = e.ed_encounter_id ORDER BY t.triage_id DESC LIMIT 1) AS prioritas,
                     (SELECT COUNT(*) FROM patient_allergies a WHERE a.patient_id = p.patient_id) AS jml_alergi
                FROM ed_encounters e
                JOIN visits v   ON v.visit_id = e.visit_id
                JOIN patients p ON p.patient_id = v.patient_id
               WHERE e.status IN ('waiting','in_treatment','observation')
            ) x
            ORDER BY CASE x.prioritas WHEN 'merah' THEN 1 WHEN 'kuning' THEN 2 WHEN 'hijau' THEN 3 WHEN 'hitam' THEN 4 ELSE 5 END, x.arrival_time";
    return db()->query($sql)->fetchAll();
}

/** Kunjungan IGD (2 hari terakhir) yang belum dibuka kasusnya. */
function ed_unopened_visits(): array
{
    $stmt = db()->prepare(
        "SELECT v.visit_id, v.visit_date, p.no_rm, p.nama_pasien,
                (SELECT q.queue_no FROM queue q WHERE q.visit_id = v.visit_id ORDER BY q.queue_id DESC LIMIT 1) AS queue_no
           FROM visits v
           JOIN patients p ON p.patient_id = v.patient_id
          WHERE v.service_type = 'emergency' AND v.status IN ('registered','in_progress')
            AND v.visit_date >= :sejak
            AND NOT EXISTS (SELECT 1 FROM ed_encounters e WHERE e.visit_id = v.visit_id)
          ORDER BY v.visit_id"
    );
    $stmt->execute([':sejak' => date('Y-m-d', strtotime('-2 days'))]);
    return $stmt->fetchAll();
}

function ed_find_visit(int $visitId): ?array
{
    $stmt = db()->prepare(
        "SELECT v.visit_id, v.visit_date, v.status, p.patient_id, p.no_rm, p.nama_pasien
           FROM visits v JOIN patients p ON p.patient_id = v.patient_id
          WHERE v.visit_id = :id AND v.service_type = 'emergency' LIMIT 1"
    );
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function ed_find_encounter(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT e.*, v.status AS visit_status, v.doctor_id, p.patient_id, p.no_rm, p.nama_pasien,
                p.tanggal_lahir, p.jenis_kelamin
           FROM ed_encounters e
           JOIN visits v   ON v.visit_id = e.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
          WHERE e.ed_encounter_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Buka kasus IGD untuk kunjungan emergency. Hasil: ed_encounter_id. RuntimeException bila aturan dilanggar. */
function ed_open_case(int $visitId, string $mode, ?string $source): int
{
    if (!array_key_exists($mode, ed_arrival_labels())) {
        throw new RuntimeException('Cara datang tidak valid.');
    }
    if ($mode === 'referral' && ($source === null || $source === '')) {
        throw new RuntimeException('Asal rujukan wajib diisi untuk pasien rujukan.');
    }
    if ($source !== null && mb_strlen($source) > 100) {
        throw new RuntimeException('Asal rujukan maksimal 100 karakter.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare("SELECT status, visit_date, service_type FROM visits WHERE visit_id = :id FOR UPDATE");
        $v->execute([':id' => $visitId]);
        $visit = $v->fetch();
        if (!$visit || $visit['service_type'] !== 'emergency' || !in_array($visit['status'], ['registered', 'in_progress'], true)) {
            throw new RuntimeException('Kunjungan IGD tidak ditemukan atau sudah tidak aktif.');
        }
        $c = $pdo->prepare('SELECT COUNT(*) FROM ed_encounters WHERE visit_id = :id');
        $c->execute([':id' => $visitId]);
        if ((int) $c->fetchColumn() > 0) {
            throw new RuntimeException('Kasus IGD untuk kunjungan ini sudah dibuka.');
        }
        $ins = $pdo->prepare('INSERT INTO ed_encounters (visit_id, arrival_time, arrival_mode, source_referral, status) VALUES (:v, :t, :m, :s, :st)');
        $ins->bindValue(':v', $visitId, PDO::PARAM_INT);
        $ins->bindValue(':t', $visit['visit_date']);
        $ins->bindValue(':m', $mode);
        $ins->bindValue(':s', $source === '' ? null : $source, $source === null || $source === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $ins->bindValue(':st', 'waiting');
        $ins->execute();
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function ed_triages(int $encounterId): array
{
    $stmt = db()->prepare(
        'SELECT t.*, u.nama AS nurse_name FROM ed_triage t
           LEFT JOIN users u ON u.user_id = t.nurse_id
          WHERE t.ed_encounter_id = :id ORDER BY t.triage_id DESC'
    );
    $stmt->execute([':id' => $encounterId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $vs = json_decode((string) $r['vital_signs'], true);
        $r['vital_list'] = is_array($vs) ? $vs : [];
    }
    return $rows;
}

/** Validasi form triase. Hasil: [data, errors]. */
function ed_validate_triage(array $in): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

    $complaint = $get('chief_complaint');
    if ($complaint === '' || mb_strlen($complaint) > 1000) {
        $errors[] = 'Keluhan utama wajib diisi (maksimal 1000 karakter).';
    }

    $num = function (string $key, string $label, float $min, float $max, bool $int) use ($get, &$errors) {
        $raw = str_replace(',', '.', $get($key));
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $raw) || ($int && str_contains($raw, '.'))) {
            $errors[] = "$label harus berupa angka" . ($int ? ' bulat.' : '.');
            return null;
        }
        $n = $int ? (int) $raw : (float) $raw;
        if ($n < $min || $n > $max) {
            $errors[] = "$label di luar batas input wajar ($min - $max). Periksa kembali.";
            return null;
        }
        return $n;
    };

    $sys  = $num('sys', 'Tekanan darah sistolik', 30, 300, true);
    $dia  = $num('dia', 'Tekanan darah diastolik', 10, 200, true);
    $hr   = $num('hr', 'Nadi', 0, 300, true);
    $rr   = $num('rr', 'Frekuensi napas', 0, 100, true);
    $temp = $num('temp', 'Suhu', 25, 45, false);
    $spo2 = $num('spo2', 'SpO2', 0, 100, true);

    if (($sys === null) !== ($dia === null)) {
        $errors[] = 'Isi sistolik dan diastolik bersama-sama.';
    }
    if ($sys !== null && $dia !== null && $dia >= $sys) {
        $errors[] = 'Diastolik harus lebih rendah dari sistolik.';
    }

    $vital = [];
    if ($sys !== null && $dia !== null) {
        $vital['TD'] = $sys . '/' . $dia;
    }
    foreach (['Nadi' => $hr, 'Napas' => $rr, 'Suhu' => $temp, 'SpO2' => $spo2] as $k => $v) {
        if ($v !== null) {
            $vital[$k] = $v;
        }
    }
    if (!$vital) {
        $errors[] = 'Isi minimal satu tanda vital.';
    }

    $cons = $get('consciousness');
    if (!array_key_exists($cons, ed_consciousness_labels())) {
        $errors[] = 'Tingkat kesadaran wajib dipilih.';
    }
    $painRaw = $get('pain_score');
    if (!preg_match('/^\d{1,2}$/', $painRaw) || (int) $painRaw > 10) {
        $errors[] = 'Skala nyeri harus angka 0-10.';
    }
    $prio = $get('triage_priority');
    if (!array_key_exists($prio, ed_priority_labels())) {
        $errors[] = 'Prioritas triase wajib dipilih.';
    }

    return [[
        'complaint' => $complaint, 'vital' => $vital, 'consciousness' => $cons,
        'pain' => (int) $painRaw, 'priority' => $prio,
    ], $errors];
}

/** Simpan triase (boleh berkali-kali untuk re-triase). Hasil: null jika sukses, atau teks error. */
function ed_add_triage(int $encounterId, int $nurseUserId, array $data): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM ed_encounters WHERE ed_encounter_id = :id FOR UPDATE');
        $st->execute([':id' => $encounterId]);
        $status = $st->fetchColumn();
        if ($status === false || !in_array($status, ['waiting', 'in_treatment', 'observation'], true)) {
            $pdo->rollBack();
            return 'Kasus IGD tidak aktif.';
        }
        $pdo->prepare(
            'INSERT INTO ed_triage (ed_encounter_id, nurse_id, chief_complaint, vital_signs, consciousness, pain_score, triage_priority)
             VALUES (:e, :n, :c, :v, :k, :p, :t)'
        )->execute([
            ':e' => $encounterId, ':n' => $nurseUserId, ':c' => $data['complaint'],
            ':v' => json_encode($data['vital'], JSON_UNESCAPED_UNICODE), ':k' => $data['consciousness'],
            ':p' => $data['pain'], ':t' => $data['priority'],
        ]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function ed_observations(int $encounterId): array
{
    $stmt = db()->prepare(
        'SELECT o.observation, o.observed_at, u.nama AS user_name FROM ed_observations o
           LEFT JOIN users u ON u.user_id = o.user_id
          WHERE o.ed_encounter_id = :id ORDER BY o.ed_observation_id DESC'
    );
    $stmt->execute([':id' => $encounterId]);
    return $stmt->fetchAll();
}

/** Tambah catatan observasi (hanya tambah, tidak ada ubah/hapus). */
function ed_add_observation(int $encounterId, int $userId, string $text): ?string
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 1000) {
        return 'Catatan observasi wajib diisi (maksimal 1000 karakter).';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM ed_encounters WHERE ed_encounter_id = :id FOR UPDATE');
        $st->execute([':id' => $encounterId]);
        $status = $st->fetchColumn();
        if (!in_array($status, ['in_treatment', 'observation'], true)) {
            $pdo->rollBack();
            return 'Observasi hanya bisa dicatat saat pasien berstatus "Ditangani" atau "Observasi".';
        }
        $pdo->prepare('INSERT INTO ed_observations (ed_encounter_id, user_id, observation, observed_at) VALUES (:e, :u, :o, :t)')
            ->execute([':e' => $encounterId, ':u' => $userId, ':o' => $text, ':t' => date('Y-m-d H:i:s')]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Ubah status kasus: treat (waiting -> in_treatment) atau observe (in_treatment -> observation). */
function ed_move(int $encounterId, string $action, ?int $doctorId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT e.status, e.visit_id, v.doctor_id FROM ed_encounters e JOIN visits v ON v.visit_id = e.visit_id
              WHERE e.ed_encounter_id = :id FOR UPDATE'
        );
        $st->execute([':id' => $encounterId]);
        $e = $st->fetch();
        if (!$e) {
            $pdo->rollBack();
            return 'Kasus IGD tidak ditemukan.';
        }

        if ($action === 'treat') {
            if ($e['status'] !== 'waiting') {
                $pdo->rollBack();
                return 'Hanya pasien berstatus "Menunggu" yang bisa mulai ditangani.';
            }
            $c = $pdo->prepare('SELECT COUNT(*) FROM ed_triage WHERE ed_encounter_id = :id');
            $c->execute([':id' => $encounterId]);
            if ((int) $c->fetchColumn() === 0) {
                $pdo->rollBack();
                return 'Lakukan triase terlebih dahulu.';
            }
            $pdo->prepare("UPDATE ed_encounters SET status = 'in_treatment' WHERE ed_encounter_id = :id")->execute([':id' => $encounterId]);
            $pdo->prepare("UPDATE visits SET status = 'in_progress', doctor_id = COALESCE(doctor_id, :d) WHERE visit_id = :v")
                ->execute([':d' => $doctorId, ':v' => $e['visit_id']]);
            $pdo->prepare("UPDATE queue SET status = 'called' WHERE visit_id = :v AND status = 'waiting'")->execute([':v' => $e['visit_id']]);
        } elseif ($action === 'observe') {
            if ($e['status'] !== 'in_treatment') {
                $pdo->rollBack();
                return 'Hanya pasien berstatus "Ditangani" yang bisa dipindah ke observasi.';
            }
            $pdo->prepare("UPDATE ed_encounters SET status = 'observation' WHERE ed_encounter_id = :id")->execute([':id' => $encounterId]);
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

function ed_disposition(int $encounterId): ?array
{
    $stmt = db()->prepare(
        'SELECT d.*, md.doctor_name FROM ed_dispositions d
           JOIN master_doctors md ON md.doctor_id = d.doctor_id
          WHERE d.ed_encounter_id = :id ORDER BY d.disposition_id DESC LIMIT 1'
    );
    $stmt->execute([':id' => $encounterId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Validasi disposisi. Hasil: [data, errors]. */
function ed_validate_disposition(array $in, string $arrivalTime): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

    $type = $get('disposition_type');
    if (!array_key_exists($type, ed_disposition_labels())) {
        $errors[] = 'Jenis disposisi wajib dipilih.';
    }

    $at = DateTime::createFromFormat('Y-m-d\TH:i', $get('decision_at'));
    $decision = null;
    if (!$at || $at->format('Y-m-d\TH:i') !== $get('decision_at')) {
        $errors[] = 'Waktu keputusan tidak valid.';
    } else {
        $decision = $at->format('Y-m-d H:i:s');
        if ($decision < date('Y-m-d H:i:s', strtotime($arrivalTime))) {
            $errors[] = 'Waktu keputusan tidak boleh sebelum waktu kedatangan.';
        }
        if ($decision > date('Y-m-d H:i:s', strtotime('+5 minutes'))) {
            $errors[] = 'Waktu keputusan tidak boleh di masa depan.';
        }
    }

    $notes = $get('notes');
    if (mb_strlen($notes) > 1000) {
        $errors[] = 'Catatan maksimal 1000 karakter.';
    }
    if ($notes === '' && in_array($type, ['refer', 'deceased', 'admit'], true)) {
        $errors[] = 'Catatan wajib diisi untuk disposisi rawat inap, rujuk, atau meninggal (alasan/tujuan).';
    }

    return [['type' => $type, 'decision_at' => $decision, 'notes' => $notes === '' ? null : $notes], $errors];
}

/** Simpan disposisi + tutup kasus + selesaikan kunjungan. Hasil: null jika sukses, atau teks error. */
function ed_save_disposition(int $encounterId, int $doctorId, array $data): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, visit_id FROM ed_encounters WHERE ed_encounter_id = :id FOR UPDATE');
        $st->execute([':id' => $encounterId]);
        $e = $st->fetch();
        if (!$e || !in_array($e['status'], ['in_treatment', 'observation'], true)) {
            $pdo->rollBack();
            return 'Disposisi hanya bisa dibuat untuk pasien berstatus "Ditangani" atau "Observasi".';
        }
        $c = $pdo->prepare('SELECT COUNT(*) FROM ed_dispositions WHERE ed_encounter_id = :id');
        $c->execute([':id' => $encounterId]);
        if ((int) $c->fetchColumn() > 0) {
            $pdo->rollBack();
            return 'Disposisi untuk kasus ini sudah dibuat.';
        }
        $t = $pdo->prepare('SELECT COUNT(*) FROM ed_triage WHERE ed_encounter_id = :id');
        $t->execute([':id' => $encounterId]);
        if ((int) $t->fetchColumn() === 0) {
            $pdo->rollBack();
            return 'Triase belum dilakukan.';
        }

        $ins = $pdo->prepare('INSERT INTO ed_dispositions (ed_encounter_id, doctor_id, disposition_type, decision_at, notes) VALUES (:e, :d, :t, :a, :n)');
        $ins->bindValue(':e', $encounterId, PDO::PARAM_INT);
        $ins->bindValue(':d', $doctorId, PDO::PARAM_INT);
        $ins->bindValue(':t', $data['type']);
        $ins->bindValue(':a', $data['decision_at']);
        $ins->bindValue(':n', $data['notes'], $data['notes'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $ins->execute();

        $pdo->prepare("UPDATE ed_encounters SET status = 'completed' WHERE ed_encounter_id = :id")->execute([':id' => $encounterId]);
        $pdo->prepare("UPDATE visits SET status = 'completed' WHERE visit_id = :v")->execute([':v' => $e['visit_id']]);
        $pdo->prepare("UPDATE queue SET status = 'served' WHERE visit_id = :v AND status IN ('waiting','called','skipped')")->execute([':v' => $e['visit_id']]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Riwayat kasus IGD yang sudah selesai pada rentang tanggal kedatangan. */
function ed_history(string $from, string $to, int $limit = 200): array
{
    $stmt = db()->prepare(
        "SELECT e.ed_encounter_id, e.arrival_time, e.arrival_mode, e.status,
                p.no_rm, p.nama_pasien,
                (SELECT t.triage_priority FROM ed_triage t WHERE t.ed_encounter_id = e.ed_encounter_id ORDER BY t.triage_id DESC LIMIT 1) AS prioritas,
                (SELECT d.disposition_type FROM ed_dispositions d WHERE d.ed_encounter_id = e.ed_encounter_id ORDER BY d.disposition_id DESC LIMIT 1) AS disposisi
           FROM ed_encounters e
           JOIN visits v   ON v.visit_id = e.visit_id
           JOIN patients p ON p.patient_id = v.patient_id
          WHERE e.status IN ('completed','cancelled') AND DATE(e.arrival_time) BETWEEN :f AND :t
          ORDER BY e.ed_encounter_id DESC LIMIT :lim"
    );
    $stmt->bindValue(':f', $from);
    $stmt->bindValue(':t', $to);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function ed_case_no(int $id): string
{
    return 'ED-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}