<?php
declare(strict_types=1);

/**
 * Fungsi bantu modul Penjadwalan & Appointment.
 * Kuota dihitung per TANGGAL dari tabel APPOINTMENTS (status booked / checked_in).
 * Kolom MASTER_SCHEDULES.filled diisi otomatis = jumlah booking aktif TERBANYAK pada satu tanggal ke depan.
 */

const SCH_HORIZON_DAYS = 14;   // booking maksimal 14 hari ke depan

function sch_status_labels(): array
{
    return [
        'booked'     => ['Terbooking', 'cp-info'],
        'checked_in' => ['Sudah Check-in', 'cp-ok'],
        'cancelled'  => ['Dibatalkan', 'cp-off'],
    ];
}

function sch_day_name(string $ymd): string
{
    $names = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
    return $names[(int) date('N', strtotime($ymd))];
}

/** Jadwal dokter beserta nama dokter & poliklinik. */
function sch_schedules(int $polyId, int $doctorId): array
{
    $sql = 'SELECT s.schedule_id, s.day_of_week, s.start_time, s.end_time, s.quota, s.filled,
                   d.doctor_id, d.doctor_name, p.polyclinic_id, p.polyclinic_name
              FROM master_schedules s
              JOIN master_doctors d      ON d.doctor_id = s.doctor_id
              JOIN master_polyclinics p  ON p.polyclinic_id = s.polyclinic_id
             WHERE d.is_active = 1 AND p.is_active = 1';
    $params = [];
    if ($polyId > 0) {
        $sql .= ' AND s.polyclinic_id = :poli';
        $params[':poli'] = $polyId;
    }
    if ($doctorId > 0) {
        $sql .= ' AND s.doctor_id = :dok';
        $params[':dok'] = $doctorId;
    }
    $sql .= " ORDER BY p.polyclinic_name, d.doctor_name, FIELD(s.day_of_week,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), s.start_time";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function sch_find_schedule(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*, d.doctor_name, p.polyclinic_name
           FROM master_schedules s
           JOIN master_doctors d     ON d.doctor_id = s.doctor_id
           JOIN master_polyclinics p ON p.polyclinic_id = s.polyclinic_id
          WHERE s.schedule_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Jumlah booking aktif pada satu jadwal & tanggal. */
function sch_count_active(int $scheduleId, string $date): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM appointments
          WHERE schedule_id = :s AND appointment_date = :d AND status IN ('booked','checked_in')"
    );
    $stmt->execute([':s' => $scheduleId, ':d' => $date]);
    return (int) $stmt->fetchColumn();
}

/** Perbarui kolom turunan filled. Dipanggil di dalam transaksi booking/pembatalan. */
function sch_refresh_filled(int $scheduleId): void
{
    $stmt = db()->prepare(
        "SELECT COALESCE(MAX(c), 0) FROM (
            SELECT COUNT(*) AS c FROM appointments
             WHERE schedule_id = :s AND status IN ('booked','checked_in') AND appointment_date >= :hari
             GROUP BY appointment_date
         ) x"
    );
    $stmt->execute([':s' => $scheduleId, ':hari' => date('Y-m-d')]);
    $filled = (int) $stmt->fetchColumn();
    db()->prepare('UPDATE master_schedules SET filled = :f WHERE schedule_id = :s')
        ->execute([':f' => $filled, ':s' => $scheduleId]);
}

/**
 * Slot yang bisa dibooking (14 hari ke depan) untuk daftar jadwal.
 * Hasil: daftar [schedule, date, sisa].
 */
function sch_open_slots(array $schedules): array
{
    $slots = [];
    $now   = date('H:i:s');
    for ($i = 0; $i <= SCH_HORIZON_DAYS; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $day  = sch_day_name($date);
        foreach ($schedules as $s) {
            if ($s['day_of_week'] !== $day) {
                continue;
            }
            if ($i === 0 && $s['end_time'] <= $now) {
                continue; // jadwal hari ini sudah lewat
            }
            $sisa = (int) $s['quota'] - sch_count_active((int) $s['schedule_id'], $date);
            $slots[] = ['schedule' => $s, 'date' => $date, 'sisa' => max(0, $sisa)];
        }
    }
    return $slots;
}

/** Validasi aturan booking. Hasil: teks error atau null. */
function sch_booking_error(int $patientId, array $schedule, string $date): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return 'Tanggal tidak valid.';
    }
    $today = date('Y-m-d');
    if ($date < $today) {
        return 'Tanggal appointment tidak boleh di masa lalu.';
    }
    if ($date > date('Y-m-d', strtotime('+' . SCH_HORIZON_DAYS . ' days'))) {
        return 'Booking maksimal ' . SCH_HORIZON_DAYS . ' hari ke depan.';
    }
    if (sch_day_name($date) !== $schedule['day_of_week']) {
        return 'Tanggal tidak sesuai dengan hari praktik dokter (' . $schedule['day_of_week'] . ').';
    }
    if ($date === $today && $schedule['end_time'] <= date('H:i:s')) {
        return 'Jadwal praktik hari ini sudah selesai.';
    }

    $dup = db()->prepare(
        "SELECT COUNT(*) FROM appointments a
           JOIN master_schedules s ON s.schedule_id = a.schedule_id
          WHERE a.patient_id = :p AND a.appointment_date = :d
            AND s.polyclinic_id = :poli AND a.status IN ('booked','checked_in')"
    );
    $dup->execute([':p' => $patientId, ':d' => $date, ':poli' => $schedule['polyclinic_id']]);
    if ((int) $dup->fetchColumn() > 0) {
        return 'Pasien sudah punya appointment aktif di poliklinik ini pada tanggal tersebut.';
    }
    return null;
}

/**
 * Simpan booking dalam transaksi (baris jadwal dikunci agar kuota tidak jebol saat dua petugas bersamaan).
 * Hasil: [appointment_id, booking_code]. Melempar RuntimeException untuk pelanggaran aturan.
 */
function sch_book(int $patientId, int $scheduleId, string $date): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT schedule_id FROM master_schedules WHERE schedule_id = :id FOR UPDATE');
        $st->execute([':id' => $scheduleId]);
        if ($st->fetchColumn() === false) {
            throw new RuntimeException('Jadwal tidak ditemukan.');
        }
        $schedule = sch_find_schedule($scheduleId);
        $err = sch_booking_error($patientId, $schedule, $date);
        if ($err !== null) {
            throw new RuntimeException($err);
        }
        if (sch_count_active($scheduleId, $date) >= (int) $schedule['quota']) {
            throw new RuntimeException('Kuota jadwal ini pada tanggal tersebut sudah penuh.');
        }

        $ins = $pdo->prepare(
            'INSERT INTO appointments (patient_id, schedule_id, appointment_date, booking_code, status)
             VALUES (:p, :s, :d, :c, :st)'
        );
        $appointmentId = 0;
        $code = '';
        for ($try = 0; $try < 5; $try++) {
            $code = 'BK-' . strtoupper(bin2hex(random_bytes(4)));
            try {
                $ins->execute([':p' => $patientId, ':s' => $scheduleId, ':d' => $date, ':c' => $code, ':st' => 'booked']);
                $appointmentId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000' || stripos($ex->getMessage(), 'booking_code') === false) {
                    throw $ex;
                }
            }
        }
        if ($appointmentId === 0) {
            throw new RuntimeException('Gagal membuat kode booking, silakan coba lagi.');
        }

        sch_refresh_filled($scheduleId);
        $pdo->commit();
        return [$appointmentId, $code];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function sch_find_appointment(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, p.no_rm, p.nama_pasien, p.patient_id,
                s.day_of_week, s.start_time, s.end_time, s.doctor_id, s.polyclinic_id,
                d.doctor_name, mp.polyclinic_name
           FROM appointments a
           JOIN patients p            ON p.patient_id = a.patient_id
           JOIN master_schedules s    ON s.schedule_id = a.schedule_id
           JOIN master_doctors d      ON d.doctor_id = s.doctor_id
           JOIN master_polyclinics mp ON mp.polyclinic_id = s.polyclinic_id
          WHERE a.appointment_id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Batalkan booking yang belum check-in. Hasil: null jika sukses, atau teks error. */
function sch_cancel(int $appointmentId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status, schedule_id FROM appointments WHERE appointment_id = :id FOR UPDATE');
        $st->execute([':id' => $appointmentId]);
        $a = $st->fetch();
        if (!$a) {
            $pdo->rollBack();
            return 'Appointment tidak ditemukan.';
        }
        if ($a['status'] !== 'booked') {
            $pdo->rollBack();
            return 'Hanya appointment berstatus "Terbooking" yang bisa dibatalkan.';
        }
        $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = :id")
            ->execute([':id' => $appointmentId]);
        sch_refresh_filled((int) $a['schedule_id']);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Daftar appointment. Hasil: [baris, total]. */
function sch_list_appointments(string $from, string $to, string $status, string $q, int $page, int $perPage): array
{
    $where  = ' WHERE a.appointment_date BETWEEN :f AND :t';
    $params = [':f' => $from, ':t' => $to];
    if (array_key_exists($status, sch_status_labels())) {
        $where .= ' AND a.status = :st';
        $params[':st'] = $status;
    }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (p.nama_pasien LIKE :q1 OR p.no_rm LIKE :q2 OR a.booking_code LIKE :q3)';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
    }
    $from_sql = ' FROM appointments a
                  JOIN patients p            ON p.patient_id = a.patient_id
                  JOIN master_schedules s    ON s.schedule_id = a.schedule_id
                  JOIN master_doctors d      ON d.doctor_id = s.doctor_id
                  JOIN master_polyclinics mp ON mp.polyclinic_id = s.polyclinic_id';

    $c = db()->prepare('SELECT COUNT(*)' . $from_sql . $where);
    $c->execute($params);
    $total = (int) $c->fetchColumn();

    $stmt = db()->prepare(
        'SELECT a.appointment_id, a.booking_code, a.appointment_date, a.status, p.no_rm, p.nama_pasien,
                s.day_of_week, s.start_time, s.end_time, d.doctor_name, mp.polyclinic_name'
        . $from_sql . $where . ' ORDER BY a.appointment_date DESC, s.start_time, a.appointment_id DESC LIMIT :lim OFFSET :off'
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    return [$stmt->fetchAll(), $total];
}