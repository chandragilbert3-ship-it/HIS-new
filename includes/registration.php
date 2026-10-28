<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Registrasi Pasien.
 * Semua query memakai prepared statement.
 */

function reg_service_labels(): array
{
    return ['outpatient' => 'Rawat Jalan', 'emergency' => 'IGD', 'inpatient' => 'Rawat Inap'];
}

/** [status => [teks, kelas CSS]] */
function reg_visit_status(): array
{
    return [
        'registered'  => ['Terdaftar', 'status-info'],
        'in_progress' => ['Berjalan',  'status-warn'],
        'completed'   => ['Selesai',   'status-aktif'],
        'cancelled'   => ['Dibatalkan', 'status-nonaktif'],
    ];
}

function reg_severity_labels(): array
{
    return ['mild' => 'Ringan', 'moderate' => 'Sedang', 'severe' => 'Berat'];
}

function reg_verification_labels(): array
{
    return ['pending' => 'Menunggu Verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'];
}

/** NIK sensitif: di daftar hanya 4 digit terakhir yang terlihat. */
function reg_mask_nik(?string $nik): string
{
    if ($nik === null || $nik === '') {
        return '';
    }
    return str_repeat('*', max(0, strlen($nik) - 4)) . substr($nik, -4);
}

function reg_age(string $dob): int
{
    try {
        return (new DateTimeImmutable($dob))->diff(new DateTimeImmutable('today'))->y;
    } catch (Exception $ex) {
        return 0;
    }
}

/** Tanggal Y-m-d yang valid atau null. */
function reg_valid_date(string $s): ?string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return null;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $s : null;
}

function reg_date_id(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '';
    }
    $t = strtotime($ymd);
    return $t === false ? '' : date('d-m-Y', $t);
}

/** Nilai konfigurasi dari tabel system_config. */
function reg_config(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT config_value FROM system_config WHERE config_key = :k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $v = $stmt->fetchColumn();
    return ($v === false || $v === null || $v === '') ? $default : (string) $v;
}

function reg_find_patient(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, u.nama AS created_by_name
           FROM patients p
           LEFT JOIN users u ON u.user_id = p.created_by
          WHERE p.patient_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Pencarian pasien: no RM, NIK, nama, atau telepon. Hasil: [baris, total].
 */
function reg_search_patients(string $q, int $page, int $perPage): array
{
    $where  = '';
    $params = [];
    if ($q !== '') {
        $like   = '%' . addcslashes($q, '%_\\') . '%';
        $where  = ' WHERE (no_rm LIKE :q1 OR nik LIKE :q2 OR nama_pasien LIKE :q3 OR telepon LIKE :q4)';
        $params = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
    }

    $count = db()->prepare('SELECT COUNT(*) FROM patients' . $where);
    foreach ($params as $k => $v) {
        $count->bindValue($k, $v);
    }
    $count->execute();
    $total = (int) $count->fetchColumn();

    $stmt = db()->prepare(
        'SELECT patient_id, no_rm, nik, nama_pasien, tanggal_lahir, jenis_kelamin, telepon
           FROM patients' . $where . ' ORDER BY patient_id DESC LIMIT :lim OFFSET :off'
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();

    return [$stmt->fetchAll(), $total];
}

/**
 * Validasi data pasien. Hasil: [data bersih, error].
 */
function reg_validate_patient(array $in, ?int $editId): array
{
    $data   = [];
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

    $nama = $get('nama_pasien');
    if ($nama === '') {
        $errors[] = 'Nama pasien wajib diisi.';
    } elseif (mb_strlen($nama) > 150) {
        $errors[] = 'Nama pasien maksimal 150 karakter.';
    } else {
        $data['nama_pasien'] = $nama;
    }

    $dob = reg_valid_date($get('tanggal_lahir'));
    if ($dob === null) {
        $errors[] = 'Tanggal lahir wajib diisi dengan tanggal yang valid.';
    } elseif ($dob > date('Y-m-d')) {
        $errors[] = 'Tanggal lahir tidak boleh di masa depan.';
    } elseif ($dob < '1900-01-01') {
        $errors[] = 'Tanggal lahir tidak masuk akal.';
    } else {
        $data['tanggal_lahir'] = $dob;
    }

    $jk = $get('jenis_kelamin');
    if (!in_array($jk, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin wajib dipilih.';
    } else {
        $data['jenis_kelamin'] = $jk;
    }

    $nik = $get('nik');
    if ($nik === '') {
        $data['nik'] = null;
    } elseif (!preg_match('/^\d{16}$/', $nik)) {
        $errors[] = 'NIK harus 16 digit angka (atau dikosongkan).';
    } else {
        $stmt = db()->prepare('SELECT patient_id, no_rm, nama_pasien FROM patients WHERE nik = :n AND patient_id <> :id LIMIT 1');
        $stmt->execute([':n' => $nik, ':id' => $editId ?? 0]);
        $dup = $stmt->fetch();
        if ($dup) {
            $errors[] = 'NIK sudah terdaftar atas nama ' . $dup['nama_pasien'] . ' (No. RM ' . $dup['no_rm'] . ').';
        } else {
            $data['nik'] = $nik;
        }
    }

    $tel = $get('telepon');
    if ($tel === '') {
        $data['telepon'] = null;
    } elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $tel)) {
        $errors[] = 'Telepon hanya boleh angka, spasi, tanda + dan - (8 sampai 20 karakter).';
    } else {
        $data['telepon'] = $tel;
    }

    $alamat = $get('alamat');
    if (mb_strlen($alamat) > 500) {
        $errors[] = 'Alamat maksimal 500 karakter.';
    } else {
        $data['alamat'] = $alamat === '' ? null : $alamat;
    }

    return [$data, $errors];
}

/**
 * Kemungkinan pasien ganda: nama dan tanggal lahir sama.
 */
function reg_find_possible_duplicates(string $nama, string $dob, ?int $excludeId = null): array
{
    $stmt = db()->prepare(
        'SELECT patient_id, no_rm, nama_pasien, tanggal_lahir
           FROM patients
          WHERE nama_pasien = :n AND tanggal_lahir = :d AND patient_id <> :id
          LIMIT 5'
    );
    $stmt->execute([':n' => $nama, ':d' => $dob, ':id' => $excludeId ?? 0]);
    return $stmt->fetchAll();
}

/**
 * Simpan pasien baru. No. RM dibuat otomatis dari patient_id: RM-000001, RM-000002, ...
 */
function reg_create_patient(array $data, int $userId): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO patients (no_rm, nik, nama_pasien, tanggal_lahir, jenis_kelamin, alamat, telepon, created_by)
             VALUES (:no_rm, :nik, :nama, :dob, :jk, :alamat, :telepon, :by)'
        );
        $stmt->execute([
            ':no_rm'   => 'TMP-' . bin2hex(random_bytes(6)),
            ':nik'     => $data['nik'],
            ':nama'    => $data['nama_pasien'],
            ':dob'     => $data['tanggal_lahir'],
            ':jk'      => $data['jenis_kelamin'],
            ':alamat'  => $data['alamat'],
            ':telepon' => $data['telepon'],
            ':by'      => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        $upd = $pdo->prepare('UPDATE patients SET no_rm = :rm WHERE patient_id = :id');
        $upd->execute([':rm' => 'RM-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), ':id' => $id]);

        $pdo->commit();
        return $id;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function reg_update_patient(int $id, array $data): void
{
    $stmt = db()->prepare(
        'UPDATE patients
            SET nik = :nik, nama_pasien = :nama, tanggal_lahir = :dob, jenis_kelamin = :jk,
                alamat = :alamat, telepon = :telepon
          WHERE patient_id = :id'
    );
    $stmt->execute([
        ':nik'     => $data['nik'],
        ':nama'    => $data['nama_pasien'],
        ':dob'     => $data['tanggal_lahir'],
        ':jk'      => $data['jenis_kelamin'],
        ':alamat'  => $data['alamat'],
        ':telepon' => $data['telepon'],
        ':id'      => $id,
    ]);
}

function reg_allergies(int $patientId): array
{
    $stmt = db()->prepare('SELECT * FROM patient_allergies WHERE patient_id = :id ORDER BY allergy_id DESC');
    $stmt->execute([':id' => $patientId]);
    return $stmt->fetchAll();
}

function reg_insurances(int $patientId): array
{
    $stmt = db()->prepare(
        'SELECT pi.*, py.payer_name
           FROM patient_insurance pi
           JOIN payers py ON py.payer_id = pi.payer_id
          WHERE pi.patient_id = :id
          ORDER BY pi.patient_insurance_id DESC'
    );
    $stmt->execute([':id' => $patientId]);
    return $stmt->fetchAll();
}

/** Asuransi yang boleh dipakai untuk kunjungan: tidak ditolak dan belum kedaluwarsa. */
function reg_usable_insurances(int $patientId): array
{
    $stmt = db()->prepare(
        "SELECT pi.patient_insurance_id, pi.membership_no, pi.verification_status, py.payer_name
           FROM patient_insurance pi
           JOIN payers py ON py.payer_id = pi.payer_id
          WHERE pi.patient_id = :id
            AND pi.verification_status <> 'rejected'
            AND (pi.valid_until IS NULL OR pi.valid_until >= :hari)
          ORDER BY pi.patient_insurance_id DESC"
    );
    $stmt->execute([':id' => $patientId, ':hari' => date('Y-m-d')]);
    return $stmt->fetchAll();
}

/** Penjamin aktif selain "Pribadi / Umum" (jenis self). */
function reg_insurance_payers(): array
{
    $rows = db()->query("SELECT payer_id, payer_name FROM payers WHERE is_active = 1 AND payer_type <> 'self' ORDER BY payer_name")->fetchAll();
    $out  = [];
    foreach ($rows as $r) {
        $out[(string) $r['payer_id']] = (string) $r['payer_name'];
    }
    return $out;
}

function reg_validate_allergy(array $in): array
{
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
    $errors = [];
    $data   = [];

    $allergen = $get('allergen');
    if ($allergen === '') {
        $errors[] = 'Nama alergen wajib diisi.';
    } elseif (mb_strlen($allergen) > 150) {
        $errors[] = 'Nama alergen maksimal 150 karakter.';
    } else {
        $data['allergen'] = $allergen;
    }

    $reaction = $get('reaction');
    if (mb_strlen($reaction) > 500) {
        $errors[] = 'Reaksi maksimal 500 karakter.';
    } else {
        $data['reaction'] = $reaction === '' ? null : $reaction;
    }

    $sev = $get('severity');
    if ($sev === '') {
        $data['severity'] = null;
    } elseif (!array_key_exists($sev, reg_severity_labels())) {
        $errors[] = 'Tingkat keparahan tidak valid.';
    } else {
        $data['severity'] = $sev;
    }

    return [$data, $errors];
}

function reg_validate_insurance(array $in): array
{
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
    $errors = [];
    $data   = [];

    $payer = $get('payer_id');
    if (!array_key_exists($payer, reg_insurance_payers())) {
        $errors[] = 'Penjamin tidak valid.';
    } else {
        $data['payer_id'] = (int) $payer;
    }

    $no = $get('membership_no');
    if ($no === '') {
        $errors[] = 'Nomor keanggotaan wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9\-\/]{4,50}$/', $no)) {
        $errors[] = 'Nomor keanggotaan hanya huruf, angka, strip, atau garis miring (4 sampai 50 karakter).';
    } else {
        $data['membership_no'] = $no;
    }

    $until = $get('valid_until');
    if ($until === '') {
        $data['valid_until'] = null;
    } elseif (reg_valid_date($until) === null) {
        $errors[] = 'Tanggal berlaku tidak valid.';
    } else {
        $data['valid_until'] = $until;
    }

    $status = $get('verification_status');
    if (!array_key_exists($status, reg_verification_labels())) {
        $errors[] = 'Status verifikasi tidak valid.';
    } else {
        $data['verification_status'] = $status;
    }

    return [$data, $errors];
}

/**
 * Buat kunjungan + nomor antrean dalam satu transaksi.
 * Melempar RuntimeException berisi pesan yang aman ditampilkan jika aturan bisnis dilanggar.
 *
 * @return array [visit_id, queue_no]
 */
function reg_create_visit(int $patientId, int $polyclinicId, ?int $doctorId, string $serviceType, ?int $insuranceId, ?int $appointmentId = null): array{
    $pdo = db();

    if (!in_array($serviceType, ['outpatient', 'emergency'], true)) {
        throw new RuntimeException('Jenis layanan tidak valid. Rawat inap dibuat lewat modul Rawat Inap.');
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM master_polyclinics WHERE polyclinic_id = :id AND is_active = 1');
    $st->execute([':id' => $polyclinicId]);
    if ((int) $st->fetchColumn() === 0) {
        throw new RuntimeException('Poliklinik tidak valid atau tidak aktif.');
    }

    if ($doctorId !== null) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM master_doctors WHERE doctor_id = :id AND is_active = 1');
        $st->execute([':id' => $doctorId]);
        if ((int) $st->fetchColumn() === 0) {
            throw new RuntimeException('Dokter tidak valid atau tidak aktif.');
        }
    }

    if ($insuranceId !== null) {
        $boleh = array_column(reg_usable_insurances($patientId), 'patient_insurance_id');
        if (!in_array($insuranceId, array_map('intval', $boleh), true)) {
            throw new RuntimeException('Asuransi tidak valid, ditolak, atau sudah kedaluwarsa.');
        }
    }

    $hariIni = date('Y-m-d');
    $besok   = date('Y-m-d', strtotime('+1 day'));
    $sekarang = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        // Kunci satu baris konfigurasi supaya pembuatan nomor antrean berurutan (tidak bentrok)
        $pdo->query("SELECT config_id FROM system_config WHERE config_key = 'queue_prefix' FOR UPDATE");
		
        // Check-in dari appointment: kunci baris appointment, pastikan masih 'booked', hari ini, dan milik pasien ini
        if ($appointmentId !== null) {
            $st = $pdo->prepare('SELECT status, appointment_date, patient_id FROM appointments WHERE appointment_id = :id FOR UPDATE');
            $st->execute([':id' => $appointmentId]);
            $ap = $st->fetch();
            if (!$ap || (int) $ap['patient_id'] !== $patientId) {
                throw new RuntimeException('Appointment tidak ditemukan untuk pasien ini.');
            }
            if ($ap['status'] !== 'booked' || $ap['appointment_date'] !== $hariIni) {
                throw new RuntimeException('Appointment sudah tidak bisa di-check-in (bukan status terbooking atau bukan hari ini).');
            }
            $pdo->prepare("UPDATE appointments SET status = 'checked_in' WHERE appointment_id = :id")->execute([':id' => $appointmentId]);
        }

        // Cegah kunjungan ganda: pasien sama, poliklinik sama, masih aktif hari ini
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM visits
              WHERE patient_id = :p AND polyclinic_id = :poli
                AND status IN ('registered','in_progress')
                AND visit_date >= :s AND visit_date < :e"
        );
        $st->execute([':p' => $patientId, ':poli' => $polyclinicId, ':s' => $hariIni, ':e' => $besok]);
        if ((int) $st->fetchColumn() > 0) {
            throw new RuntimeException('Pasien ini sudah punya kunjungan aktif hari ini di poliklinik yang sama.');
        }

        $ins = $pdo->prepare(
            'INSERT INTO visits (patient_id, doctor_id, polyclinic_id, appointment_id, patient_insurance_id, service_type, visit_date, status)
             VALUES (:p, :d, :poli, :apt, :ins, :svc, :tgl, :st)'
        );
        $ins->bindValue(':p', $patientId, PDO::PARAM_INT);
        $ins->bindValue(':d', $doctorId, $doctorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ins->bindValue(':poli', $polyclinicId, PDO::PARAM_INT);
        $ins->bindValue(':ins', $insuranceId, $insuranceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ins->bindValue(':svc', $serviceType);
        $ins->bindValue(':tgl', $sekarang);
        $ins->bindValue(':apt', $appointmentId, $appointmentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ins->bindValue(':st', 'registered');        $ins->execute();
        $visitId = (int) $pdo->lastInsertId();

        // Nomor antrean: PREFIX-001, PREFIX-002, ... diulang setiap hari
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', reg_config('queue_prefix', 'A')));
        $prefix = $prefix === '' ? 'A' : substr($prefix, 0, 5);

        $st = $pdo->prepare(
            'SELECT queue_no FROM queue
              WHERE created_at >= :s AND created_at < :e AND queue_no LIKE :pf
              ORDER BY queue_id DESC LIMIT 1'
        );
        $st->execute([':s' => $hariIni, ':e' => $besok, ':pf' => $prefix . '-%']);
        $last = $st->fetchColumn();
        $next = $last === false ? 1 : ((int) substr((string) $last, strlen($prefix) + 1)) + 1;
        $queueNo = $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);

        $q = $pdo->prepare('INSERT INTO queue (visit_id, queue_no, status, created_at) VALUES (:v, :no, :st, :tgl)');
        $q->execute([':v' => $visitId, ':no' => $queueNo, ':st' => 'waiting', ':tgl' => $sekarang]);

        $pdo->commit();
        return [$visitId, $queueNo];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/**
 * Batalkan kunjungan yang masih berstatus "registered" (beserta antreannya).
 */
function reg_cancel_visit(int $visitId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("UPDATE visits SET status = 'cancelled' WHERE visit_id = :id AND status = 'registered'");
        $st->execute([':id' => $visitId]);
        $ok = $st->rowCount() > 0;

        if ($ok) {
            $q = $pdo->prepare("UPDATE queue SET status = 'cancelled' WHERE visit_id = :id AND status = 'waiting'");
            $q->execute([':id' => $visitId]);
        }
        $pdo->commit();
        return $ok;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function reg_patient_visits(int $patientId, int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT v.visit_id, v.visit_date, v.service_type, v.status,
                mp.polyclinic_name, md.doctor_name, q.queue_no
           FROM visits v
           JOIN master_polyclinics mp ON mp.polyclinic_id = v.polyclinic_id
           LEFT JOIN master_doctors md ON md.doctor_id = v.doctor_id
           LEFT JOIN queue q ON q.visit_id = v.visit_id
          WHERE v.patient_id = :id
          ORDER BY v.visit_id DESC
          LIMIT :lim'
    );
    $stmt->bindValue(':id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}