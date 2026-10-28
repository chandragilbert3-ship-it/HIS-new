<?php
declare(strict_types=1);

// Pengaturan koneksi database (XAMPP default: user root tanpa password).
// Kalau nanti dipasang di server sungguhan, GANTI user dan password ini.
const DB_HOST    = '127.0.0.1';
const DB_PORT    = '3306';
const DB_NAME    = 'simrs_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Mengembalikan koneksi PDO (dibuat sekali, lalu dipakai ulang).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // prepared statement sungguhan
        ]);
    } catch (PDOException $e) {
        // Detail error hanya ke log server, bukan ke layar pengguna.
        error_log('Koneksi database gagal: ' . $e->getMessage());
        http_response_code(500);
        exit('Koneksi database gagal. Pastikan MySQL di XAMPP sudah berjalan dan pengaturan di config/database.php benar.');
    }

    return $pdo;
}