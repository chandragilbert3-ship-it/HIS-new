<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Keperawatan.
 * Memakai NURSING_ASSESSMENTS, NURSING_CARE_PLANS, NURSING_INTERVENTIONS + VISITS, PATIENTS.
 * Pengkajian dan intervensi bersifat tambah-saja (tidak diubah/dihapus).
 * Rentang angka hanya batas kewajaran input (cegah salah ketik), BUKAN kriteria klinis.
 */

function nrs_fall_labels(): array
{
    return [
        'rendah' => ['Rendah', 'cp-ok'],
        'sedang' => ['Sedang', 'cp-warn'],
        'tinggi' => ['Tinggi', 'cp-off'],
    ];
}

function nrs_service_labels(): array
{
    return ['outpatient' => 'Rawat Jalan', 'emergency' => 'IGD', 'inpatient' => 'Rawat Inap'];
}

function nrs_plan_status_labels(): array
{
    return [
        'active'       => ['Aktif', 'cp-info'],
        'achieved'     => ['Tercapai', 'cp-ok'],
        'discontinued' => ['Dihentikan', 'cp-off'],
    ];
}

/** Kunjungan aktif (terdaftar/berjalan), opsional dicari nama atau No. RM. */
function nrs_active_visits(string $q = ''): array
{
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $stmt = db()->prepare(
        "SELECT v.visit_id, v.service_type, v.status, v.visit_date, p.no_rm, p.nama_pasien,
                (SELECT COUNT(*) FROM nursing_assessments a WHERE a.visit_id = v.visit_id) AS jml_kaji,
                (SELECT COUNT(*) FROM nursing_care_plans c WHERE c.visit_id = v.visit_id AND c.status = 'active') AS jml_plan
           FROM visits v
           JOIN patients p ON p.patient_id = v.patient_id
          WHERE v.status IN ('registered','in_progress')
            AND (:q1 = '' OR p.nama_pasien LIKE :q2 OR p.no_rm LIKE :q3)
          ORDER BY v.visit_id DESC LIMIT 200"
    );
    $stmt->execute([':q1' => $q, ':q2' => $like, ':q3' => $like]);
    return $stmt->fetchAll();
}

function nrs_find_visit(int $visitId): ?array
{
    $stmt = db()->prepare(
        'SELECT v.visit_id, v.service_type, v.status, v.visit_date, p.patient_id, p.no_rm, p.nama_pasien,
                p.tanggal_lahir, p.jenis_kelamin
           FROM visits v JOIN patients p ON p.patient_id = v.patient_id
          WHERE v.visit_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $visitId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function nrs_visit_open(array $visit): bool
{
    return in_array($visit['status'], ['registered', 'in_progress'], true);
}

function nrs_assessments(int $visitId): array
{
    $stmt = db()->prepare(
        'SELECT a.*, u.nama AS nurse_name FROM nursing_assessments a
           LEFT JOIN users u ON u.user_id = a.nurse_id
          WHERE a.visit_id = :v ORDER BY a.assessment_id DESC'
    );
    $stmt->execute([':v' => $visitId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $vs = json_decode((string) $r['vital_signs'], true);
        $r['vital_list'] = is_array($vs) ? $vs : [];
    }
    return $rows;
}

/** Validasi pengkajian. Hasil: [data, errors]. */
function nrs_validate_assessment(array $in): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

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
    $bb   = $num('bb', 'Berat badan', 1, 500, false);
    $tb   = $num('tb', 'Tinggi badan', 20, 250, false);

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
    foreach (['Nadi' => $hr, 'Napas' => $rr, 'Suhu' => $temp, 'SpO2' => $spo2, 'BB' => $bb, 'TB' => $tb] as $k => $v) {
        if ($v !== null) {
            $vital[$k] = $v;
        }
    }
    if (!$vital) {
        $errors[] = 'Isi minimal satu tanda vital atau ukuran tubuh.';
    }

    $painRaw = $get('pain_score');
    if (!preg_match('/^\d{1,2}$/', $painRaw) || (int) $painRaw > 10) {
        $errors[] = 'Skala nyeri harus angka 0-10.';
    }
    $fall = $get('fall_risk');
    if (!array_key_exists($fall, nrs_fall_labels())) {
        $errors[] = 'Risiko jatuh wajib dipilih.';
    }

    return [['vital' => $vital, 'pain' => (int) $painRaw, 'fall' => $fall], $errors];
}

/** Simpan pengkajian baru (riwayat tetap). Hasil: null jika sukses, atau teks error. */
function nrs_add_assessment(int $visitId, int $nurseUserId, array $data): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare('SELECT status FROM visits WHERE visit_id = :id FOR UPDATE');
        $v->execute([':id' => $visitId]);
        $status = $v->fetchColumn();
        if (!in_array($status, ['registered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Kunjungan sudah tidak aktif. Pengkajian tidak dapat ditambahkan.';
        }
        $pdo->prepare(
            'INSERT INTO nursing_assessments (visit_id, nurse_id, vital_signs, pain_score, fall_risk, created_at)
             VALUES (:v, :n, :vs, :p, :f, :c)'
        )->execute([
            ':v' => $visitId, ':n' => $nurseUserId,
            ':vs' => json_encode($data['vital'], JSON_UNESCAPED_UNICODE),
            ':p' => $data['pain'], ':f' => $data['fall'], ':c' => date('Y-m-d H:i:s'),
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

/** Rencana asuhan satu kunjungan, tiap rencana membawa daftar intervensinya. */
function nrs_care_plans(int $visitId): array
{
    $stmt = db()->prepare(
        'SELECT c.*, u.nama AS nurse_name FROM nursing_care_plans c
           LEFT JOIN users u ON u.user_id = c.nurse_id
          WHERE c.visit_id = :v ORDER BY c.care_plan_id DESC'
    );
    $stmt->execute([':v' => $visitId]);
    $plans = $stmt->fetchAll();

    $i = db()->prepare(
        'SELECT i.*, u.nama AS nurse_name FROM nursing_interventions i
           JOIN nursing_care_plans c ON c.care_plan_id = i.care_plan_id
           LEFT JOIN users u ON u.user_id = i.nurse_id
          WHERE c.visit_id = :v ORDER BY i.performed_at DESC, i.intervention_id DESC'
    );
    $i->execute([':v' => $visitId]);
    $byPlan = [];
    foreach ($i->fetchAll() as $row) {
        $byPlan[(int) $row['care_plan_id']][] = $row;
    }
    foreach ($plans as &$p) {
        $p['interventions'] = $byPlan[(int) $p['care_plan_id']] ?? [];
    }
    return $plans;
}

/** Validasi rencana asuhan. Hasil: [data, errors]. */
function nrs_validate_plan(array $in): array
{
    $errors  = [];
    $problem = is_string($in['nursing_problem'] ?? null) ? trim($in['nursing_problem']) : '';
    $target  = is_string($in['target'] ?? null) ? trim($in['target']) : '';
    if ($problem === '' || mb_strlen($problem) > 1000) {
        $errors[] = 'Masalah keperawatan wajib diisi (maksimal 1000 karakter).';
    }
    if ($target === '' || mb_strlen($target) > 1000) {
        $errors[] = 'Target/tujuan wajib diisi (maksimal 1000 karakter).';
    }
    return [['problem' => $problem, 'target' => $target], $errors];
}

function nrs_add_plan(int $visitId, int $nurseUserId, array $data): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = $pdo->prepare('SELECT status FROM visits WHERE visit_id = :id FOR UPDATE');
        $v->execute([':id' => $visitId]);
        $status = $v->fetchColumn();
        if (!in_array($status, ['registered', 'in_progress'], true)) {
            $pdo->rollBack();
            return 'Kunjungan sudah tidak aktif. Rencana asuhan tidak dapat ditambahkan.';
        }
        $pdo->prepare(
            "INSERT INTO nursing_care_plans (visit_id, nurse_id, nursing_problem, target, status) VALUES (:v, :n, :p, :t, 'active')"
        )->execute([':v' => $visitId, ':n' => $nurseUserId, ':p' => $data['problem'], ':t' => $data['target']]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Tutup rencana: active -> achieved|discontinued. Hasil: null jika sukses, atau teks error. */
function nrs_set_plan_status(int $planId, string $newStatus): ?string
{
    if (!in_array($newStatus, ['achieved', 'discontinued'], true)) {
        return 'Status tidak valid.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM nursing_care_plans WHERE care_plan_id = :id FOR UPDATE');
        $st->execute([':id' => $planId]);
        $cur = $st->fetchColumn();
        if ($cur === false) {
            $pdo->rollBack();
            return 'Rencana asuhan tidak ditemukan.';
        }
        if ($cur !== 'active') {
            $pdo->rollBack();
            return 'Hanya rencana berstatus "Aktif" yang bisa ditutup.';
        }
        $pdo->prepare('UPDATE nursing_care_plans SET status = :s WHERE care_plan_id = :id')
            ->execute([':s' => $newStatus, ':id' => $planId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Validasi intervensi. Hasil: [data, errors]. */
function nrs_validate_intervention(array $in): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

    $text = $get('intervention');
    if ($text === '' || mb_strlen($text) > 1000) {
        $errors[] = 'Tindakan/intervensi wajib diisi (maksimal 1000 karakter).';
    }
    $resp = $get('response');
    if (mb_strlen($resp) > 1000) {
        $errors[] = 'Respons pasien maksimal 1000 karakter.';
    }
    $at   = DateTime::createFromFormat('Y-m-d\TH:i', $get('performed_at'));
    $when = null;
    if (!$at || $at->format('Y-m-d\TH:i') !== $get('performed_at')) {
        $errors[] = 'Waktu tindakan tidak valid.';
    } else {
        $when = $at->format('Y-m-d H:i:s');
        if ($when > date('Y-m-d H:i:s', strtotime('+5 minutes'))) {
            $errors[] = 'Waktu tindakan tidak boleh di masa depan.';
        }
    }
    return [['text' => $text, 'response' => $resp === '' ? null : $resp, 'at' => $when], $errors];
}

/** Tambah intervensi pada rencana aktif (tambah-saja). Hasil: null jika sukses, atau teks error. */
function nrs_add_intervention(int $planId, int $nurseUserId, array $data): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM nursing_care_plans WHERE care_plan_id = :id FOR UPDATE');
        $st->execute([':id' => $planId]);
        $cur = $st->fetchColumn();
        if ($cur !== 'active') {
            $pdo->rollBack();
            return 'Intervensi hanya bisa ditambahkan pada rencana berstatus "Aktif".';
        }
        $ins = $pdo->prepare(
            'INSERT INTO nursing_interventions (care_plan_id, nurse_id, intervention, response, performed_at)
             VALUES (:c, :n, :i, :r, :t)'
        );
        $ins->bindValue(':c', $planId, PDO::PARAM_INT);
        $ins->bindValue(':n', $nurseUserId, PDO::PARAM_INT);
        $ins->bindValue(':i', $data['text']);
        $ins->bindValue(':r', $data['response'], $data['response'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $ins->bindValue(':t', $data['at']);
        $ins->execute();
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Visit_id milik sebuah rencana asuhan (untuk audit/redirect), atau null. */
function nrs_plan_visit(int $planId): ?int
{
    $stmt = db()->prepare('SELECT visit_id FROM nursing_care_plans WHERE care_plan_id = :id');
    $stmt->execute([':id' => $planId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}