<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul EMR (Rekam Medis Elektronik).
 * Tampilan terpadu lintas kunjungan: EMR_NOTES, EMR_ADDENDA, DIAGNOSES, ORDERS, + data modul lain.
 * Catatan final tidak diubah; koreksi dilakukan lewat addendum (tambah-saja).
 */

function emr_service_labels(): array
{
    return ['outpatient' => 'Rawat Jalan', 'emergency' => 'IGD', 'inpatient' => 'Rawat Inap'];
}

function emr_visit_status_labels(): array
{
    return [
        'registered'  => ['Terdaftar', 'cp-info'],
        'in_progress' => ['Berjalan', 'cp-warn'],
        'completed'   => ['Selesai', 'cp-ok'],
        'cancelled'   => ['Dibatalkan', 'cp-off'],
    ];
}

function emr_order_type_labels(): array
{
    return [
        'lab' => 'Laboratorium', 'radiology' => 'Radiologi', 'drug' => 'Obat',
        'procedure' => 'Tindakan', 'consultation' => 'Konsultasi', 'admission' => 'Rawat Inap',
    ];
}

function emr_order_status_labels(): array
{
    return [
        'ordered' => ['Dipesan', 'cp-info'], 'in_progress' => ['Diproses', 'cp-warn'],
        'completed' => ['Selesai', 'cp-ok'], 'cancelled' => ['Dibatalkan', 'cp-off'],
    ];
}

/** Cari pasien (nama / No. RM); tanpa kata kunci menampilkan pasien dengan kunjungan terbaru. */
function emr_search(string $q): array
{
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $stmt = db()->prepare(
        "SELECT p.patient_id, p.no_rm, p.nama_pasien, p.tanggal_lahir, p.jenis_kelamin,
                (SELECT MAX(v.visit_date) FROM visits v WHERE v.patient_id = p.patient_id) AS last_visit,
                (SELECT COUNT(*) FROM visits v2 WHERE v2.patient_id = p.patient_id) AS jml_kunjungan
           FROM patients p
          WHERE (:q1 = '' OR p.nama_pasien LIKE :q2 OR p.no_rm LIKE :q3)
          ORDER BY last_visit IS NULL, last_visit DESC, p.patient_id DESC
          LIMIT 50"
    );
    $stmt->execute([':q1' => $q, ':q2' => $like, ':q3' => $like]);
    return $stmt->fetchAll();
}

function emr_find_patient(int $id): ?array
{
    $stmt = db()->prepare('SELECT patient_id, no_rm, nama_pasien, tanggal_lahir, jenis_kelamin FROM patients WHERE patient_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function emr_patient_visits(int $patientId): array
{
    $stmt = db()->prepare(
        "SELECT v.visit_id, v.service_type, v.status, v.visit_date, pc.polyclinic_name, md.doctor_name,
                (SELECT CONCAT(i.icd10_code, ' - ', i.diagnosis_name) FROM diagnoses d
                   JOIN master_icd10 i ON i.icd10_code = d.icd10_code
                  WHERE d.visit_id = v.visit_id AND d.diagnosis_type = 'primary' ORDER BY d.diagnosis_id LIMIT 1) AS dx_utama,
                (SELECT n.status FROM emr_notes n WHERE n.visit_id = v.visit_id ORDER BY n.emr_note_id DESC LIMIT 1) AS note_status
           FROM visits v
           LEFT JOIN master_polyclinics pc ON pc.polyclinic_id = v.polyclinic_id
           LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
          WHERE v.patient_id = :p
          ORDER BY v.visit_date DESC, v.visit_id DESC LIMIT 200"
    );
    $stmt->execute([':p' => $patientId]);
    return $stmt->fetchAll();
}

function emr_find_visit(int $visitId): ?array
{
    $stmt = db()->prepare(
        'SELECT v.visit_id, v.service_type, v.status, v.visit_date, p.patient_id, p.no_rm, p.nama_pasien,
                p.tanggal_lahir, p.jenis_kelamin, pc.polyclinic_name, md.doctor_name
           FROM visits v
           JOIN patients p ON p.patient_id = v.patient_id
           LEFT JOIN master_polyclinics pc ON pc.polyclinic_id = v.polyclinic_id
           LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
          WHERE v.visit_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Catatan SOAP kunjungan beserta addendum. Draft hanya disertakan bila $withDraft. */
function emr_notes(int $visitId, bool $withDraft): array
{
    $sql = 'SELECT n.*, md.doctor_name FROM emr_notes n LEFT JOIN master_doctors md ON md.doctor_id = n.doctor_id WHERE n.visit_id = :v'
         . ($withDraft ? '' : " AND n.status = 'final'") . ' ORDER BY n.emr_note_id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute([':v' => $visitId]);
    $notes = $stmt->fetchAll();

    $a = db()->prepare(
        'SELECT a.*, md.doctor_name FROM emr_addenda a
           JOIN emr_notes n ON n.emr_note_id = a.emr_note_id
           LEFT JOIN master_doctors md ON md.doctor_id = a.doctor_id
          WHERE n.visit_id = :v ORDER BY a.addendum_id'
    );
    $a->execute([':v' => $visitId]);
    $by = [];
    foreach ($a->fetchAll() as $row) {
        $by[(int) $row['emr_note_id']][] = $row;
    }
    foreach ($notes as &$n) {
        $n['addenda'] = $by[(int) $n['emr_note_id']] ?? [];
    }
    return $notes;
}

function emr_diagnoses(int $visitId): array
{
    $stmt = db()->prepare(
        "SELECT d.icd10_code, i.diagnosis_name, d.diagnosis_type
           FROM diagnoses d JOIN master_icd10 i ON i.icd10_code = d.icd10_code
          WHERE d.visit_id = :v ORDER BY d.diagnosis_type = 'primary' DESC, d.diagnosis_id"
    );
    $stmt->execute([':v' => $visitId]);
    return $stmt->fetchAll();
}

function emr_orders(int $visitId): array
{
    $stmt = db()->prepare(
        'SELECT o.order_id, o.order_type, o.priority, o.status, o.ordered_at, md.doctor_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS jml_item
           FROM orders o LEFT JOIN master_doctors md ON md.doctor_id = o.ordering_doctor_id
          WHERE o.visit_id = :v ORDER BY o.order_id DESC'
    );
    $stmt->execute([':v' => $visitId]);
    return $stmt->fetchAll();
}

function emr_ed_encounter_id(int $visitId): ?int
{
    $stmt = db()->prepare('SELECT ed_encounter_id FROM ed_encounters WHERE visit_id = :v ORDER BY ed_encounter_id DESC LIMIT 1');
    $stmt->execute([':v' => $visitId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

function emr_inpatient_id(int $visitId): ?int
{
    $stmt = db()->prepare('SELECT inpatient_id FROM inpatient_admissions WHERE visit_id = :v ORDER BY inpatient_id DESC LIMIT 1');
    $stmt->execute([':v' => $visitId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

function emr_find_note(int $noteId): ?array
{
    $stmt = db()->prepare(
        'SELECT n.emr_note_id, n.visit_id, n.status, n.subjective, n.objective, n.assessment, n.plan, p.nama_pasien, p.no_rm
           FROM emr_notes n JOIN visits v ON v.visit_id = n.visit_id JOIN patients p ON p.patient_id = v.patient_id
          WHERE n.emr_note_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $noteId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Validasi addendum. Hasil: [data, errors]. */
function emr_validate_addendum(array $in): array
{
    $errors = [];
    $reason  = is_string($in['reason'] ?? null) ? trim($in['reason']) : '';
    $content = is_string($in['content'] ?? null) ? trim($in['content']) : '';
    if ($reason === '' || mb_strlen($reason) > 500) {
        $errors[] = 'Alasan addendum wajib diisi (maksimal 500 karakter).';
    }
    if ($content === '' || mb_strlen($content) > 3000) {
        $errors[] = 'Isi addendum wajib diisi (maksimal 3000 karakter).';
    }
    return [['reason' => $reason, 'content' => $content], $errors];
}

/** Tambah addendum pada catatan FINAL (tambah-saja). Hasil: null jika sukses, atau teks error. */
function emr_add_addendum(int $noteId, int $doctorId, array $data): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM emr_notes WHERE emr_note_id = :id FOR UPDATE');
        $st->execute([':id' => $noteId]);
        $status = $st->fetchColumn();
        if ($status !== 'final') {
            $pdo->rollBack();
            return 'Addendum hanya dapat ditambahkan pada catatan yang sudah final.';
        }
        $pdo->prepare('INSERT INTO emr_addenda (emr_note_id, doctor_id, reason, content, created_at) VALUES (:n, :d, :r, :c, :t)')
            ->execute([':n' => $noteId, ':d' => $doctorId, ':r' => $data['reason'], ':c' => $data['content'], ':t' => date('Y-m-d H:i:s')]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}