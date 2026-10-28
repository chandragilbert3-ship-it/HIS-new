<?php
declare(strict_types=1);

/**
 * Semua kode izin milik seorang user (gabungan dari semua rolenya).
 *
 * @return string[]
 */
function load_user_permissions(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT p.permission_code
           FROM user_roles ur
           JOIN role_permissions rp ON rp.role_id = ur.role_id
           JOIN permissions p       ON p.permission_id = rp.permission_id
          WHERE ur.user_id = :user_id
          ORDER BY p.permission_code'
    );
    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Nama-nama role milik seorang user.
 *
 * @return string[]
 */
function load_user_roles(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.role_name
           FROM user_roles ur
           JOIN roles r ON r.role_id = ur.role_id
          WHERE ur.user_id = :user_id
          ORDER BY r.role_name'
    );
    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Apakah user yang sedang login punya izin ini? Contoh: can('registration.create')
 */
function can(string $permissionCode): bool
{
    return isset($_SESSION['perms'][$permissionCode]);
}

/**
 * Punya salah satu dari beberapa izin?
 */
function can_any(array $permissionCodes): bool
{
    foreach ($permissionCodes as $code) {
        if (can($code)) {
            return true;
        }
    }
    return false;
}

/**
 * Wajib punya izin ini. Belum login => ke halaman login. Tidak punya izin => 403.
 * Panggil di AWAL setiap halaman dan setiap proses simpan/ubah.
 */
function require_permission(string $permissionCode): void
{
    require_login();

    if (can($permissionCode)) {
        return;
    }

    audit_log('Akses ditolak: ' . $permissionCode);
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
       . '<title>Akses Ditolak</title></head>'
       . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#eef3fa;padding:60px;text-align:center;">'
       . '<h1 style="color:#b91c1c;">403 — Akses Ditolak</h1>'
       . '<p>Akun Anda tidak memiliki izin untuk membuka halaman atau melakukan aksi ini.</p>'
       . '<p><a href="' . e(url('dashboard.php')) . '">Kembali ke Dashboard</a></p>'
       . '</body></html>';
    exit;
}