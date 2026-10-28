<?php
declare(strict_types=1);

/**
 * Daftar 16 modul SIMRS, urut sesuai nomor di TRD.
 * Kunci = nilai kolom permissions.module.
 * 'ready' => true kalau halaman modul sudah dibuat (folder modules/<kunci>/index.php).
 */
function module_registry(): array
{
    return [
        'auth'         => ['no' => 1,  'label' => 'Autentikasi & Pengguna', 'icon' => '🔐', 'ready' => true],
        'registration' => ['no' => 2,  'label' => 'Registrasi Pasien',       'icon' => '📝', 'ready' => true],
        'scheduling'   => ['no' => 3,  'label' => 'Penjadwalan',             'icon' => '📅', 'ready' => true],
        'outpatient'   => ['no' => 4,  'label' => 'Rawat Jalan',             'icon' => '🩺', 'ready' => true],
        'inpatient'    => ['no' => 5,  'label' => 'Rawat Inap',              'icon' => '🛏️', 'ready' => true],
        'emergency'    => ['no' => 6,  'label' => 'IGD',                     'icon' => '🚑', 'ready' => true],
        'emr'          => ['no' => 7,  'label' => 'Rekam Medis (EMR)',       'icon' => '📋', 'ready' => true],
        'nursing'      => ['no' => 8,  'label' => 'Keperawatan',             'icon' => '💉', 'ready' => true],
        'cpoe'         => ['no' => 9,  'label' => 'CPOE (Order Dokter)',     'icon' => '✍️', 'ready' => true],
        'laboratory'   => ['no' => 10, 'label' => 'Laboratorium',            'icon' => '🧪', 'ready' => true],
        'radiology'    => ['no' => 11, 'label' => 'Radiologi',               'icon' => '🩻', 'ready' => true],
        'pharmacy'     => ['no' => 12, 'label' => 'Farmasi',                 'icon' => '💊', 'ready' => true],
        'billing'      => ['no' => 13, 'label' => 'Billing & Kasir',         'icon' => '💳', 'ready' => true],
        'inventory'    => ['no' => 14, 'label' => 'Inventori & Gudang',      'icon' => '📦', 'ready' => true],
        'reporting'    => ['no' => 15, 'label' => 'Laporan & Dashboard',     'icon' => '📊', 'ready' => true],
        'masterdata'   => ['no' => 16, 'label' => 'Master Data',             'icon' => '⚙️', 'ready' => true],
    ];
}

/**
 * Modul yang boleh dilihat user yang sedang login, diambil dari database:
 * semua izin berakhiran "view" milik role-role user.
 */
function sidebar_modules(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $stmt = db()->prepare(
        "SELECT DISTINCT p.module
           FROM user_roles ur
           JOIN role_permissions rp ON rp.role_id = ur.role_id
           JOIN permissions p       ON p.permission_id = rp.permission_id
          WHERE ur.user_id = :user_id
            AND p.action = 'view'"
    );
    $stmt->execute([':user_id' => (int) ($_SESSION['user']['id'] ?? 0)]);
    $boleh = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $hasil = [];
    foreach (module_registry() as $kode => $modul) {
        if (in_array($kode, $boleh, true)) {
            $hasil[$kode] = $modul;
        }
    }

    return $cache = $hasil;
}

/**
 * Alamat halaman utama sebuah modul.
 */
function module_url(string $kode): string
{
    return url('modules/' . $kode . '/index.php');
}