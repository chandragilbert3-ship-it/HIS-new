<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Rawat Jalan (Outpatient).
 * Memakai tabel VISITS, QUEUE, NURSING_ASSESSMENTS, EMR_NOTES, DIAGNOSES, MASTER_ICD10.
 */

function opd_queue_labels(): array
{
    return [
        'waiting'   => ['Menunggu',   'cp-info'],
        'called'    => ['Dipanggil',  'cp-warn'],
        'served'    => ['Selesai',    'cp-ok'],
        'skipped'   => ['Dilewati',   'cp-off'],
        'cancelled' => ['Dibatalkan', 'cp-off'],
    ];
}

/** Antrean rawat jalan hari ini. $doctorId != null => hanya pasien dokter itu atau yang belum punya dokter. */
function opd_today_queue(int $polyId, string $status, ?int $doctorId): array
{
    $sql = "SELECT q.queue_id, q.queue_no, q.status AS q_status,
                   v.visit_id, v.status AS v_status, v.doctor_id,
                   p.patient_id, p.no_rm, p.nama_pasien,
                   mp.polyclinic_name, md.doctor_name,
                   (SELECT COUNT(*) FROM patient_allergies a WHERE a.patient_id = p.patient_id) AS jml_alergi
              FROM queue q
              JOIN visits v              ON v.visit_id = q.visit_id
              JOIN patients p            ON p.patient_id = v.patient_id
              JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
              LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
             WHERE q.created_at >= :s AND q.created_at < :e AND v.service_type = 'outpatient'";
    $params = [':s' => date('Y-m-d'), ':e' => date('Y-m-d', strtotime('+1 day'))];
    if ($polyId > 0) {
        $sql .= ' AND v.polyclinic_id = :poli';
        $params[':poli'] = $polyId;
    }
    if (array_key_exists($status, opd_queue_labels())) {
        $sql .= ' AND q.status = :st';
        $params[':st'] = $status;
    }
    if ($doctorId !== null) {
        $sql .= ' AND (v.doctor_id IS NULL OR v.doctor_id = :dok)';
        $params[':dok'] = $doctorId;
    }
    $sql .= ' ORDER BY q.queue_id';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Kunjungan rawat jalan + data pasien + status antrean. */
function opd_find_visit(int $visitId): ?array
{
    $stmt = db()->prepare(
        "SELECT v.visit_id, v.visit_date, v.status, v.doctor_id, v.service_type,
                p.patient_id, p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin,
                mp.polyclinic_name, md.doctor_name,
                (SELECT q.queue_no FROM queue q WHERE q.visit_id = v.visit_id ORDER BY q.queue_id DESC LIMIT 1) AS queue_no
           FROM visits v
           JOIN patients p            ON p.patient_id = v.patient_id
           JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
           LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
          WHERE v.visit_id = :id AND v.service_type = 'outpatient' LIMIT 1"
    );
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Ubah status antrean/kunjungan. $action: call | skip | start.
 * Hasil: null jika sukses, atau teks error.
 */
function opd_queue_move(int $visitId, string $action, ?int $doctorId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare('SELECT status, doctor_id, service_type FROM visits WHERE visit_id = :id FOR UPDATE');
        $v->execute([':id' => $visitId]);
        $visit = $v->fetch();
        if (!$visit || $visit['service_type'] !== 'outpatient') {
            $pdo->rollBack();
            return 'Kunjungan rawat jalan tidak ditemukan.';
        }
        $q = $pdo->prepare('SELECT queue_id, status FROM queue WHERE visit_id = :id ORDER BY queue_id DESC LIMIT 1 FOR UPDATE');
        $q->execute([':id' => $visitId]);
        $queue = $q->fetch();
        if (!$queue) {
            $pdo->rollBack();
            return 'Antrean untuk kunjungan ini tidak ada.';
        }

        $setQueue = function (string $status) use ($pdo, $queue): void {
            $pdo->prepare('UPDATE queue SET status = :s WHERE queue_id = :id')->execute([':s' => $status, ':id' => $queue['queue_id']]);
        };

        switch ($action) {
            case 'call':
                if ($visit['status'] !== 'registered' || !in_array($queue['status'], ['waiting', 'skipped'], true)) {
                    $pdo->rollBack();
                    return 'Pasien ini tidak bisa dipanggil pada status sekarang.';
                }
                $setQueue('called');
                break;

            case 'skip':
                if ($visit['status'] !== 'registered' || $queue['status'] !== 'called') {
                    $pdo->rollBack();
                    return 'Hanya pasien yang sedang dipanggil yang bisa dilewati.';
                }
                $setQueue('skipped');
                break;

            case 'start':
                if ($doctorId === null) {
                    $pdo->rollBack();
                    return 'Hanya akun dokter yang terhubung ke Master Data Dokter yang bisa memulai pemeriksaan.';
                }
                if (!in_array($visit['status'], ['registered', 'in_progress'], true)) {
                    $pdo->rollBack();
                    return 'Kunjungan ini sudah selesai atau dibatalkan.';
                }
                if ($visit['doctor_id'] !== null && (int) $visit['doctor_id'] !== $doctorId) {
                    $pdo->rollBack();
                    return 'Kunjungan ini ditugaskan ke dokter lain.';
                }
                if (in_array($queue['status'], ['waiting', 'skipped'], true)) {
                    $setQueue('called');
                }
                $pdo->prepare("UPDATE visits SET status = 'in_progress', doctor_id = :d WHERE visit_id = :id")
                    ->execute([':d' => $doctorId, ':id' => $visitId]);
                break;

            default:
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

/** Pengkajian awal perawat terbaru untuk kunjungan (atau null). vital_signs didekode dari JSON. */
function opd_nursing(int $visitId): ?array
{
    $stmt = db()->prepare('SELECT vital_signs, pain_score, fall_risk, created_at FROM nursing_assessments WHERE visit_id = :id ORDER BY assessment_id DESC LIMIT 1');
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $vs = json_decode((string) $row['vital_signs'], true);
    $row['vital_list'] = is_array($vs) ? $vs : [];
    return $row;
}

/** Catatan SOAP kunjungan (atau null). */
function opd_note(int $visitId): ?array
{
    $stmt = db()->prepare('SELECT * FROM emr_notes WHERE visit_id = :id ORDER BY emr_note_id DESC LIMIT 1');
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Diagnosis kunjungan: [[icd10_code, diagnosis_name, diagnosis_type], ...]. */
function opd_diagnoses(int $visitId): array
{
    $stmt = db()->prepare(
        'SELECT d.icd10_code, i.diagnosis_name, d.diagnosis_type
           FROM diagnoses d
           JOIN master_icd10 i ON i.icd10_code = d.icd10_code
          WHERE d.visit_id = :id
          ORDER BY FIELD(d.diagnosis_type, \'primary\') DESC, d.diagnosis_id'
    );
    $stmt->execute([':id' => $visitId]);
    return $stmt->fetchAll();
}

/** Pilihan ICD-10 aktif: [kode => "kode - nama"]. */
function opd_icd_options(): array
{
    $out = [];
    foreach (db()->query('SELECT icd10_code, diagnosis_name FROM master_icd10 WHERE is_active = 1 ORDER BY icd10_code LIMIT 2000')->fetchAll() as $r) {
        $out[(string) $r['icd10_code']] = $r['icd10_code'] . ' - ' . $r['diagnosis_name'];
    }
    return $out;
}

/**
 * Validasi SOAP + diagnosis. $finalize = true => syarat lengkap.
 * Hasil: [data, errors]. data: soap[4], dx[[code,type],...].
 */
function opd_validate_exam(array $in, bool $finalize, array $icd): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

    $soap  = [];
    $names = ['subjective' => 'Subjective', 'objective' => 'Objective', 'assessment' => 'Assessment', 'plan' => 'Plan'];
    foreach ($names as $k => $label) {
        $soap[$k] = $get($k);
        if (mb_strlen($soap[$k]) > 5000) {
            $errors[] = "$label maksimal 5000 karakter.";
        }
        if ($finalize && $soap[$k] === '') {
            $errors[] = "$label wajib diisi sebelum finalisasi.";
        }
    }

    $dx   = [];
    $seen = [];
    $rows = is_array($in['dx'] ?? null) ? $in['dx'] : [];
    foreach ([0, 1, 2] as $i) {
        $code = is_string($rows[$i] ?? null) ? trim($rows[$i]) : '';
        if ($code === '') {
            continue;
        }
        if (!isset($icd[$code])) {
            $errors[] = 'Kode ICD-10 pada baris diagnosis ' . ($i + 1) . ' tidak valid atau tidak aktif.';
            continue;
        }
        if (isset($seen[$code])) {
            $errors[] = "Diagnosis $code dipilih lebih dari sekali.";
            continue;
        }
        $seen[$code] = true;
        $dx[] = ['code' => $code, 'type' => $i === 0 ? 'primary' : 'secondary'];
    }
    if ($finalize && !array_filter($dx, fn($d) => $d['type'] === 'primary')) {
        $errors[] = 'Diagnosis utama wajib diisi sebelum finalisasi.';
    }
    if (!empty($dx) && $dx[0]['type'] !== 'primary') {
        $errors[] = 'Diagnosis sekunder tidak boleh diisi tanpa diagnosis utama.';
    }

    return [['soap' => $soap, 'dx' => $dx], $errors];
}

/**
 * Simpan SOAP + diagnosis dalam satu transaksi. Catatan yang sudah final tidak bisa diubah di sini.
 * Hasil: null jika sukses, atau teks error.
 */
function opd_save_exam(int $visitId, int $doctorId, array $data, bool $finalize): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare('SELECT status, doctor_id, service_type FROM visits WHERE visit_id = :id FOR UPDATE');
        $v->execute([':id' => $visitId]);
        $visit = $v->fetch();
        if (!$visit || $visit['service_type'] !== 'outpatient') {
            $pdo->rollBack();
            return 'Kunjungan tidak ditemukan.';
        }
        if ($visit['status'] !== 'in_progress') {
            $pdo->rollBack();
            return 'Pemeriksaan hanya bisa diisi saat kunjungan berstatus "Berjalan". Tekan Mulai Periksa lebih dulu.';
        }
        if ((int) $visit['doctor_id'] !== $doctorId) {
            $pdo->rollBack();
            return 'Kunjungan ini ditugaskan ke dokter lain.';
        }

        $n = $pdo->prepare('SELECT emr_note_id, status FROM emr_notes WHERE visit_id = :id ORDER BY emr_note_id DESC LIMIT 1 FOR UPDATE');
        $n->execute([':id' => $visitId]);
        $note = $n->fetch();
        if ($note && $note['status'] === 'final') {
            $pdo->rollBack();
            return 'Catatan sudah difinalisasi dan tidak bisa diubah. Koreksi dilakukan lewat addendum di modul EMR.';
        }

        $status = $finalize ? 'final' : 'draft';
        $s      = $data['soap'];
        if ($note) {
            $pdo->prepare('UPDATE emr_notes SET doctor_id = :d, subjective = :s, objective = :o, assessment = :a, plan = :p, status = :st WHERE emr_note_id = :id')
                ->execute([':d' => $doctorId, ':s' => $s['subjective'], ':o' => $s['objective'], ':a' => $s['assessment'], ':p' => $s['plan'], ':st' => $status, ':id' => $note['emr_note_id']]);
        } else {
            $pdo->prepare('INSERT INTO emr_notes (visit_id, doctor_id, subjective, objective, assessment, plan, status) VALUES (:v, :d, :s, :o, :a, :p, :st)')
                ->execute([':v' => $visitId, ':d' => $doctorId, ':s' => $s['subjective'], ':o' => $s['objective'], ':a' => $s['assessment'], ':p' => $s['plan'], ':st' => $status]);
        }

        // Diagnosis pada catatan yang masih draft boleh disusun ulang
        $pdo->prepare('DELETE FROM diagnoses WHERE visit_id = :id')->execute([':id' => $visitId]);
        $ins = $pdo->prepare('INSERT INTO diagnoses (visit_id, icd10_code, doctor_id, diagnosis_type, created_at) VALUES (:v, :c, :d, :t, :at)');
        foreach ($data['dx'] as $d) {
            $ins->execute([':v' => $visitId, ':c' => $d['code'], ':d' => $doctorId, ':t' => $d['type'], ':at' => date('Y-m-d H:i:s')]);
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

/** Selesaikan kunjungan: butuh catatan final. Hasil: null jika sukses, atau teks error. */
function opd_complete(int $visitId, int $doctorId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare('SELECT status, doctor_id, service_type FROM visits WHERE visit_id = :id FOR UPDATE');
        $v->execute([':id' => $visitId]);
        $visit = $v->fetch();
        if (!$visit || $visit['service_type'] !== 'outpatient') {
            $pdo->rollBack();
            return 'Kunjungan tidak ditemukan.';
        }
        if ($visit['status'] !== 'in_progress') {
            $pdo->rollBack();
            return 'Kunjungan bukan berstatus "Berjalan".';
        }
        if ((int) $visit['doctor_id'] !== $doctorId) {
            $pdo->rollBack();
            return 'Kunjungan ini ditugaskan ke dokter lain.';
        }
        $n = $pdo->prepare("SELECT COUNT(*) FROM emr_notes WHERE visit_id = :id AND status = 'final'");
        $n->execute([':id' => $visitId]);
        if ((int) $n->fetchColumn() === 0) {
            $pdo->rollBack();
            return 'Finalisasi catatan SOAP terlebih dahulu sebelum menyelesaikan kunjungan.';
        }

        $pdo->prepare("UPDATE visits SET status = 'completed' WHERE visit_id = :id")->execute([':id' => $visitId]);
        $pdo->prepare("UPDATE queue SET status = 'served' WHERE visit_id = :id AND status IN ('waiting','called','skipped')")->execute([':id' => $visitId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Riwayat kunjungan rawat jalan seorang pasien beserta diagnosis utamanya. */
function opd_patient_history(int $patientId, int $limit = 30): array
{
    $stmt = db()->prepare(
        "SELECT v.visit_id, v.visit_date, v.status, mp.polyclinic_name, md.doctor_name,
                (SELECT GROUP_CONCAT(CONCAT(d.icd10_code, ' ', i.diagnosis_name) SEPARATOR '; ')
                   FROM diagnoses d JOIN master_icd10 i ON i.icd10_code = d.icd10_code
                  WHERE d.visit_id = v.visit_id AND d.diagnosis_type = 'primary') AS dx_utama,
                (SELECT n.status FROM emr_notes n WHERE n.visit_id = v.visit_id ORDER BY n.emr_note_id DESC LIMIT 1) AS note_status
           FROM visits v
           JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
           LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
          WHERE v.patient_id = :p AND v.service_type = 'outpatient'
          ORDER BY v.visit_id DESC LIMIT :lim"
    );
    $stmt->bindValue(':p', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}