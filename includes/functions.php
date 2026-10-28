<?php
declare(strict_types=1);

/**
 * Escape output HTML (anti-XSS). WAJIB dipakai untuk setiap data yang ditampilkan.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Membuat URL absolut dari path relatif, misalnya url('login.php') => /simrs/login.php
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Pindah halaman lalu hentikan skrip.
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Pesan sekali tampil (flash), misalnya "Data berhasil disimpan".
 * $type: success | error | info
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil pesan flash lalu hapus supaya tidak tampil lagi.
 */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Catat aktivitas ke tabel audit_logs.
 * Kegagalan mencatat tidak boleh menghentikan aplikasi.
 */
function audit_log(string $activity, ?string $table = null, ?int $referenceId = null, ?int $visitId = null): void
{
    try {
        $userId = $_SESSION['user']['id'] ?? null;

        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, visit_id, activity, table_name, reference_id)
             VALUES (:user_id, :visit_id, :activity, :table_name, :reference_id)'
        );
        $stmt->execute([
            ':user_id'      => $userId,
            ':visit_id'     => $visitId,
            ':activity'     => mb_substr($activity, 0, 255),
            ':table_name'   => $table,
            ':reference_id' => $referenceId,
        ]);
    } catch (Throwable $e) {
        error_log('Audit log gagal: ' . $e->getMessage());
    }
}