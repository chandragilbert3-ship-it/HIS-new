<?php
declare(strict_types=1);

// Pengaturan koneksi database.
// - Di XAMPP (lokal): tidak perlu mengubah apa pun, nilai default XAMPP dipakai
//   (user root tanpa password).
// - Di Vercel: nilai VERCEL_DEFAULTS di bawah dipakai otomatis. Environment Variables
//   di Vercel (DB_HOST, DB_PASS, dst.) tetap bisa dipakai untuk menimpanya.

/** Nilai otomatis saat berjalan di Vercel (database TiDB Cloud, Singapura). */
const VERCEL_DEFAULTS = [
    'DB_HOST'        => 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com',
    'DB_PORT'        => '4000',
    'DB_NAME'        => 'simrs_db',
    'DB_USER'        => 'WyEp8FuiAxUT2zT.root',
    'DB_PASS'        => 'SWyagq1nF8eJUihP',
    'DB_SSL'         => 'true',
    'SESSION_DRIVER' => 'db',
    'STORAGE_DRIVER' => 'db',
    'APP_DEBUG'      => 'false',
];

/** Apakah aplikasi sedang berjalan di Vercel? (Vercel mengisi variabel VERCEL=1) */
function on_vercel(): bool
{
    return getenv('VERCEL') !== false && getenv('VERCEL') !== '';
}

/** Baca environment variable; kalau kosong pakai nilai Vercel (jika di Vercel) atau default. */
function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    if (on_vercel() && isset(VERCEL_DEFAULTS[$key])) {
        return VERCEL_DEFAULTS[$key];
    }
    return $default;
}

define('DB_HOST',    env('DB_HOST', '127.0.0.1'));
define('DB_PORT',    env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME', 'simrs_db'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');
// Database cloud (mis. TiDB Cloud) mewajibkan koneksi terenkripsi (TLS).
define('DB_SSL',     filter_var(env('DB_SSL', 'false'), FILTER_VALIDATE_BOOLEAN));

/** Cari file sertifikat CA bawaan sistem (untuk verifikasi TLS). */
function db_ssl_ca(): ?string
{
    $custom = env('DB_SSL_CA');
    $candidates = $custom !== '' ? [$custom] : [
        '/etc/pki/tls/certs/ca-bundle.crt',      // Amazon Linux (Vercel)
        '/etc/ssl/certs/ca-certificates.crt',    // Debian/Ubuntu
        '/etc/ssl/cert.pem',                     // macOS / Alpine
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/** Buat koneksi PDO; $withDb=false untuk koneksi tanpa memilih database. */
function db_connect(bool $withDb = true): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET)
         . ($withDb ? ';dbname=' . DB_NAME : '');

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // prepared statement sungguhan
    ];
    if (DB_SSL) {
        $ca = db_ssl_ca();
        if ($ca !== null) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $ca !== null;
    }
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Samakan zona waktu database dengan aplikasi (WIB).
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}

/**
 * Instalasi otomatis (hanya di Vercel): kalau database simrs_db belum ada / belum
 * lengkap, jalankan simrs_db_tidb.sql. File SQL-nya aman dijalankan ulang (CREATE
 * TABLE IF NOT EXISTS, INSERT IGNORE), jadi instalasi yang terputus akan dilanjutkan. Dikunci dengan GET_LOCK supaya dua request
 * pertama yang bersamaan tidak mengimpor dua kali.
 */
function db_auto_install(): void
{
    $file = dirname(APP_ROOT) . '/simrs_db_tidb.sql';
    if (!is_file($file)) {
        throw new RuntimeException('File simrs_db_tidb.sql tidak ditemukan untuk instalasi otomatis.');
    }
    @set_time_limit(120);
    $pdo = db_connect(false);
    try {
        $pdo->query("SELECT GET_LOCK('simrs_install', 90)")->fetchColumn();
    } catch (PDOException) {
        // kunci tidak didukung: lanjutkan tanpa kunci
    }
    try {
        // Tabel `sessions` dibuat paling akhir oleh file SQL => penanda instalasi selesai.
        $done = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :n AND table_name = 'sessions'"
        );
        $done->execute([':n' => DB_NAME]);
        if ((int) $done->fetchColumn() > 0) {
            return; // sudah terinstal (mungkin oleh request lain)
        }
        $buf = '';
        foreach (preg_split('/\r?\n/', (string) file_get_contents($file)) as $line) {
            $t = trim($line);
            if ($buf === '' && ($t === '' || str_starts_with($t, '--'))) {
                continue;
            }
            $buf .= $line . "\n";
            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($buf);
                $buf = '';
            }
        }
        error_log('SIMRS: database ' . DB_NAME . ' berhasil diinstal otomatis.');
    } finally {
        try {
            $pdo->query("SELECT RELEASE_LOCK('simrs_install')");
        } catch (PDOException) {
        }
    }
}

/**
 * Mengembalikan koneksi PDO (dibuat sekali, lalu dipakai ulang).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        try {
            $pdo = db_connect();
        } catch (PDOException $e) {
            // 1049 = database belum ada => di Vercel, instal otomatis lalu coba lagi.
            $unknownDb = (int) ($e->errorInfo[1] ?? 0) === 1049 || (int) $e->getCode() === 1049
                || str_contains($e->getMessage(), '[1049]');
            if (on_vercel() && $unknownDb) {
                db_auto_install();
                $pdo = db_connect();
            } else {
                throw $e;
            }
        }
    } catch (Throwable $e) {
        // Detail error hanya ke log server, bukan ke layar pengguna.
        error_log('Koneksi database gagal: ' . $e->getMessage());
        http_response_code(500);
        exit('Koneksi database gagal. Pastikan database berjalan dan pengaturan koneksi (config/database.php atau Environment Variables) benar.');
    }

    return $pdo;
}
