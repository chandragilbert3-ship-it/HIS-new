<?php
declare(strict_types=1);

/**
 * Definisi 10 jenis Master Data. Satu entri = satu tabel MASTER_* di TRD.
 * Nama tabel dan kolom di sini TERPERCAYA (ditulis programmer), bukan dari input pengguna.
 *
 * Kunci field yang dikenali:
 *  label, type (text|textarea|int|decimal|select|ref|time|datetime), required, max, min,
 *  pattern, pattern_msg, upper, lower, options (untuk select), ref (untuk ref),
 *  list (tampil di tabel), search (ikut pencarian), create_only, readonly, format, hint
 */
return [

    'dokter' => [
        'label' => 'Dokter', 'icon' => '👨‍⚕️', 'table' => 'master_doctors', 'pk' => 'doctor_id',
        'order' => 't.doctor_name', 'active' => 'is_active',
        'fields' => [
            'user_id' => [
                'label' => 'Akun Pengguna', 'type' => 'ref', 'required' => true,
                'create_only' => true, 'list' => true,
                'hint' => 'Hanya akun berperan Dokter atau Radiografer yang belum terdaftar sebagai dokter. Tidak bisa diganti setelah disimpan.',
                'ref' => [
                    'table' => 'users', 'id' => 'user_id', 'label' => "CONCAT(nama, ' (', username, ')')",
                    'where' => "status = 'active' AND user_id IN (SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.role_id = ur.role_id WHERE r.role_name IN ('Dokter', 'Radiografer'))",
                    'where_create' => 'user_id NOT IN (SELECT user_id FROM master_doctors)',
                    'order' => 'nama',
                ],
            ],
            'doctor_name' => ['label' => 'Nama Dokter', 'type' => 'text', 'required' => true, 'max' => 100, 'list' => true, 'search' => true],
            'specialty'   => ['label' => 'Spesialisasi', 'type' => 'text', 'max' => 100, 'list' => true, 'search' => true],
        ],
    ],

    'poliklinik' => [
        'label' => 'Poliklinik', 'icon' => '🏢', 'table' => 'master_polyclinics', 'pk' => 'polyclinic_id',
        'order' => 't.polyclinic_name', 'active' => 'is_active',
        'fields' => [
            'polyclinic_name' => ['label' => 'Nama Poliklinik', 'type' => 'text', 'required' => true, 'max' => 100, 'list' => true, 'search' => true],
            'location'        => ['label' => 'Lokasi', 'type' => 'text', 'max' => 100, 'list' => true, 'search' => true],
        ],
    ],

    'jadwal' => [
        'label' => 'Jadwal Dokter', 'icon' => '📅', 'table' => 'master_schedules', 'pk' => 'schedule_id',
        'order' => "t.polyclinic_id, t.doctor_id, FIELD(t.day_of_week, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), t.start_time",
        'active' => null,
        'note' => 'Jadwal tidak punya status aktif di TRD. Untuk menutup jadwal, ubah kuota menjadi 0.',
        'search_exprs' => [
            '(SELECT d.doctor_name FROM master_doctors d WHERE d.doctor_id = t.doctor_id)',
            '(SELECT p.polyclinic_name FROM master_polyclinics p WHERE p.polyclinic_id = t.polyclinic_id)',
        ],
        'fields' => [
            'doctor_id' => [
                'label' => 'Dokter', 'type' => 'ref', 'required' => true, 'list' => true,
                'ref' => ['table' => 'master_doctors', 'id' => 'doctor_id', 'label' => 'doctor_name', 'where' => 'is_active = 1', 'order' => 'doctor_name'],
            ],
            'polyclinic_id' => [
                'label' => 'Poliklinik', 'type' => 'ref', 'required' => true, 'list' => true,
                'ref' => ['table' => 'master_polyclinics', 'id' => 'polyclinic_id', 'label' => 'polyclinic_name', 'where' => 'is_active = 1', 'order' => 'polyclinic_name'],
            ],
            'day_of_week' => [
                'label' => 'Hari', 'type' => 'select', 'required' => true, 'list' => true,
                'options' => ['Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu', 'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu', 'Minggu' => 'Minggu'],
            ],
            'start_time' => ['label' => 'Jam Mulai', 'type' => 'time', 'required' => true, 'list' => true, 'hint' => 'Format 24 jam, contoh 08:00'],
            'end_time'   => ['label' => 'Jam Selesai', 'type' => 'time', 'required' => true, 'list' => true, 'hint' => 'Format 24 jam, contoh 12:00'],
            'quota'      => ['label' => 'Kuota Pasien', 'type' => 'int', 'required' => true, 'min' => 0, 'max' => 500, 'list' => true],
            'filled'     => ['label' => 'Terisi (otomatis)', 'type' => 'int', 'readonly' => true, 'list' => true],
        ],
        'validate' => function (array $d, ?array $old): array {
            $err = [];
            if ($d['end_time'] <= $d['start_time']) {
                $err[] = 'Jam selesai harus setelah jam mulai.';
            }
            if ($old !== null && (int) $d['quota'] < (int) $old['filled']) {
                $err[] = 'Kuota tidak boleh lebih kecil dari jumlah yang sudah terisi (' . (int) $old['filled'] . ').';
            }
            if (!$err) {
                $stmt = db()->prepare(
                    'SELECT COUNT(*) FROM master_schedules
                      WHERE doctor_id = :dokter AND day_of_week = :hari
                        AND start_time < :selesai AND end_time > :mulai
                        AND schedule_id <> :id'
                );
                $stmt->execute([
                    ':dokter'  => $d['doctor_id'],
                    ':hari'    => $d['day_of_week'],
                    ':selesai' => $d['end_time'],
                    ':mulai'   => $d['start_time'],
                    ':id'      => $old['schedule_id'] ?? 0,
                ]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $err[] = 'Jadwal bentrok: dokter ini sudah punya jadwal di hari dan jam yang beririsan.';
                }
            }
            return $err;
        },
    ],

    'ruangan' => [
        'label' => 'Ruangan', 'icon' => '🛏️', 'table' => 'master_rooms', 'pk' => 'room_id',
        'order' => 't.unit_name, t.room_name', 'active' => 'is_active',
        'note' => 'Tempat tidur (BEDS) dikelola di modul Rawat Inap.',
        'fields' => [
            'unit_name' => ['label' => 'Unit / Bangsal', 'type' => 'text', 'required' => true, 'max' => 100, 'list' => true, 'search' => true],
            'room_name' => ['label' => 'Nama Ruangan', 'type' => 'text', 'required' => true, 'max' => 100, 'list' => true, 'search' => true],
            'room_class' => [
                'label' => 'Kelas', 'type' => 'select', 'list' => true,
                'options' => ['VIP' => 'VIP', 'Kelas 1' => 'Kelas 1', 'Kelas 2' => 'Kelas 2', 'Kelas 3' => 'Kelas 3', 'ICU' => 'ICU', 'Isolasi' => 'Isolasi'],
            ],
        ],
    ],

    'tarif' => [
        'label' => 'Tarif', 'icon' => '💰', 'table' => 'master_tariffs', 'pk' => 'tariff_id',
        'order' => 't.service_code', 'active' => 'is_active',
        'fields' => [
            'service_code' => [
                'label' => 'Kode Layanan', 'type' => 'text', 'required' => true, 'max' => 50, 'upper' => true,
                'pattern' => '/^[A-Z0-9._-]{2,50}$/', 'pattern_msg' => 'Kode hanya boleh huruf besar, angka, titik, garis bawah, atau strip.',
                'list' => true, 'search' => true,
            ],
            'service_name' => ['label' => 'Nama Layanan', 'type' => 'text', 'required' => true, 'max' => 150, 'list' => true, 'search' => true],
            'amount' => [
                'label' => 'Tarif (Rp)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'format' => 'rupiah', 'list' => true,
                'hint' => 'Angka saja tanpa titik ribuan. Contoh: 75000',
            ],
        ],
        'validate' => function (array $d, ?array $old): array {
            $stmt = db()->prepare('SELECT COUNT(*) FROM master_tariffs WHERE service_code = :kode AND tariff_id <> :id');
            $stmt->execute([':kode' => $d['service_code'], ':id' => $old['tariff_id'] ?? 0]);
            return ((int) $stmt->fetchColumn() > 0) ? ['Kode layanan ' . $d['service_code'] . ' sudah dipakai.'] : [];
        },
    ],

    'icd10' => [
        'label' => 'ICD-10', 'icon' => '📄', 'table' => 'master_icd10', 'pk' => 'icd10_code', 'pk_manual' => true,
        'order' => 't.icd10_code', 'active' => 'is_active',
        'fields' => [
            'icd10_code' => [
                'label' => 'Kode ICD-10', 'type' => 'text', 'required' => true, 'max' => 10, 'upper' => true, 'create_only' => true,
                'pattern' => '/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/', 'pattern_msg' => 'Format kode tidak valid. Contoh: J18.9 atau I10.',
                'list' => true, 'search' => true, 'hint' => 'Tidak bisa diubah setelah disimpan.',
            ],
            'diagnosis_name' => ['label' => 'Nama Diagnosis', 'type' => 'text', 'required' => true, 'max' => 255, 'list' => true, 'search' => true],
        ],
    ],

    'lab' => [
        'label' => 'Tes Lab', 'icon' => '🧪', 'table' => 'master_lab_tests', 'pk' => 'lab_test_id',
        'order' => 't.test_name', 'active' => 'is_active',
        'fields' => [
            'test_name'       => ['label' => 'Nama Tes', 'type' => 'text', 'required' => true, 'max' => 150, 'list' => true, 'search' => true],
            'unit'            => ['label' => 'Satuan', 'type' => 'text', 'max' => 30, 'list' => true],
            'reference_range' => ['label' => 'Rentang Rujukan', 'type' => 'text', 'max' => 100, 'list' => true, 'hint' => 'Isi sesuai rentang rujukan laboratorium RS.'],
        ],
    ],

    'radiologi' => [
        'label' => 'Tes Radiologi', 'icon' => '📷', 'table' => 'master_rad_tests', 'pk' => 'rad_test_id',
        'order' => 't.test_name', 'active' => 'is_active',
        'fields' => [
            'test_name' => ['label' => 'Nama Pemeriksaan', 'type' => 'text', 'required' => true, 'max' => 150, 'list' => true, 'search' => true],
            'modality'  => [
                'label' => 'Modalitas', 'type' => 'select', 'list' => true,
                'options' => ['X-Ray' => 'X-Ray', 'USG' => 'USG', 'CT Scan' => 'CT Scan', 'MRI' => 'MRI'],
            ],
        ],
    ],

    'prosedur' => [
        'label' => 'Tindakan / Layanan', 'icon' => '🩹', 'table' => 'master_procedures', 'pk' => 'procedure_id',
        'order' => 't.category, t.procedure_name', 'active' => 'is_active',
        'note' => 'Tabel tambahan di luar TRD asli, dipakai CPOE untuk order tindakan, konsultasi, dan rawat inap.',
        'fields' => [
            'procedure_name' => ['label' => 'Nama Tindakan / Layanan', 'type' => 'text', 'required' => true, 'max' => 150, 'list' => true, 'search' => true],
            'category' => [
                'label' => 'Kategori', 'type' => 'select', 'required' => true, 'list' => true,
                'options' => ['procedure' => 'Tindakan', 'consultation' => 'Konsultasi', 'admission' => 'Rawat Inap'],
            ],
        ],
    ],

    'konfigurasi' => [
        'label' => 'Konfigurasi', 'icon' => '⚙️', 'table' => 'system_config', 'pk' => 'config_id',
        'order' => 't.config_key', 'active' => null,
        'note' => 'Konfigurasi tidak dihapus dan tidak punya status aktif; ubah nilainya bila perlu.',
        'fields' => [
            'config_key' => [
                'label' => 'Kunci', 'type' => 'text', 'required' => true, 'max' => 100, 'lower' => true, 'create_only' => true,
                'pattern' => '/^[a-z][a-z0-9_.]{1,99}$/', 'pattern_msg' => 'Kunci hanya huruf kecil, angka, titik, dan garis bawah (diawali huruf).',
                'list' => true, 'search' => true, 'hint' => 'Tidak bisa diubah setelah disimpan.',
            ],
            'config_value' => ['label' => 'Nilai', 'type' => 'textarea', 'max' => 1000, 'list' => true],
            'updated_at'   => ['label' => 'Diperbarui', 'type' => 'datetime', 'readonly' => true, 'list' => true],
        ],
    ],

];