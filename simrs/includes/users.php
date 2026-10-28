<?php
declare(strict_types=1);

/**
 * Fungsi bantu Manajemen Pengguna (Modul 1 Auth).
 * Password tidak pernah disimpan/dicatat dalam bentuk asli, hanya hash.
 */

const USR_ADMIN_ROLE = 'Admin IT';

function usr_status_labels(): array
{
    return ['active' => 'Aktif', 'inactive' => 'Nonaktif'];
}

/** [role_id => [role_name, description]] */
function usr_all_roles(): array
{
    $out = [];
    foreach (db()->query('SELECT role_id, role_name, description FROM roles ORDER BY role_id')->fetchAll() as $r) {
        $out[(int) $r['role_id']] = [(string) $r['role_name'], (string) $r['description']];
    }
    return $out;
}

function usr_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT user_id, username, nama, status FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** @return int[] */
function usr_role_ids(int $userId): array
{
    $stmt = db()->prepare('SELECT role_id FROM user_roles WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Daftar pengguna. Hasil: [baris, total]. */
function usr_list(string $q, string $status, int $page, int $perPage): array
{
    $where  = ' WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where .= ' AND (u.username LIKE :q1 OR u.nama LIKE :q2)';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
    }
    if (array_key_exists($status, usr_status_labels())) {
        $where .= ' AND u.status = :st';
        $params[':st'] = $status;
    }

    $c = db()->prepare('SELECT COUNT(*) FROM users u' . $where);
    $c->execute($params);
    $total = (int) $c->fetchColumn();

    $sql = 'SELECT u.user_id, u.username, u.nama, u.status,
                   GROUP_CONCAT(r.role_name ORDER BY r.role_id SEPARATOR ", ") AS roles
              FROM users u
              LEFT JOIN user_roles ur ON ur.user_id = u.user_id
              LEFT JOIN roles r       ON r.role_id = ur.role_id'
          . $where . ' GROUP BY u.user_id, u.username, u.nama, u.status
              ORDER BY u.user_id LIMIT :lim OFFSET :off';
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    return [$stmt->fetchAll(), $total];
}

/** Aturan password: min 10 karakter, ada huruf besar, huruf kecil, angka, dan tidak sama dengan username. */
function usr_password_errors(string $pw, string $username): array
{
    $e = [];
    if (mb_strlen($pw) < 10) {
        $e[] = 'Password minimal 10 karakter.';
    }
    if (mb_strlen($pw) > 72) {
        $e[] = 'Password maksimal 72 karakter.';
    }
    if (!preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $e[] = 'Password harus mengandung huruf besar, huruf kecil, dan angka.';
    }
    if ($username !== '' && mb_strtolower($pw) === mb_strtolower($username)) {
        $e[] = 'Password tidak boleh sama dengan username.';
    }
    return $e;
}

/** Password sementara acak (12 karakter, tanpa karakter yang mirip seperti O/0/l/1). */
function usr_generate_password(): string
{
    $up = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lo = 'abcdefghijkmnpqrstuvwxyz';
    $dg = '23456789';
    $all = $up . $lo . $dg;
    $pick = fn(string $s): string => $s[random_int(0, strlen($s) - 1)];

    $chars = [$pick($up), $pick($up), $pick($lo), $pick($lo), $pick($dg), $pick($dg)];
    while (count($chars) < 12) {
        $chars[] = $pick($all);
    }
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

/**
 * Validasi form pengguna. $mode = 'create' | 'edit'.
 * Hasil: [data, errors]. data: username, nama, roles (int[]).
 */
function usr_validate(array $in, string $mode, ?int $existingId): array
{
    $errors = [];
    $get    = fn(string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';

    $username = mb_strtolower($get('username'));
    $nama     = $get('nama');

    if ($mode === 'create') {
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            $errors[] = 'Username 3-50 karakter: huruf kecil, angka, titik, garis bawah, atau strip.';
        } else {
            $c = db()->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
            $c->execute([':u' => $username]);
            if ((int) $c->fetchColumn() > 0) {
                $errors[] = 'Username sudah dipakai.';
            }
        }
    }

    if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $errors[] = 'Nama lengkap wajib diisi (3-100 karakter).';
    }

    $allRoles = usr_all_roles();
    $roles    = [];
    foreach ((array) ($in['roles'] ?? []) as $rid) {
        $rid = (int) $rid;
        if (isset($allRoles[$rid])) {
            $roles[$rid] = $rid;
        }
    }
    if (!$roles) {
        $errors[] = 'Pilih minimal satu role.';
    }

    return [['username' => $username, 'nama' => $nama, 'roles' => array_values($roles)], $errors];
}

function usr_admin_role_id(): ?int
{
    $stmt = db()->prepare('SELECT role_id FROM roles WHERE role_name = :n LIMIT 1');
    $stmt->execute([':n' => USR_ADMIN_ROLE]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Jumlah akun aktif ber-role Admin IT selain $excludeUserId. */
function usr_other_active_admins(int $excludeUserId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(DISTINCT u.user_id)
           FROM users u
           JOIN user_roles ur ON ur.user_id = u.user_id
           JOIN roles r       ON r.role_id = ur.role_id
          WHERE r.role_name = :n AND u.status = \'active\' AND u.user_id <> :ex'
    );
    $stmt->execute([':n' => USR_ADMIN_ROLE, ':ex' => $excludeUserId]);
    return (int) $stmt->fetchColumn();
}

/** Buat pengguna. Hasil: user_id. Melempar RuntimeException bila username bentrok. */
function usr_create(array $data, string $password): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, nama, status) VALUES (:u, :h, :n, :s)');
        $stmt->execute([
            ':u' => $data['username'],
            ':h' => password_hash($password, PASSWORD_DEFAULT),
            ':n' => $data['nama'],
            ':s' => 'active',
        ]);
        $userId = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        foreach ($data['roles'] as $rid) {
            $ins->execute([':u' => $userId, ':r' => $rid]);
        }
        $pdo->commit();
        return $userId;
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($ex->getCode() === '23000') {
            throw new RuntimeException('Username sudah dipakai.');
        }
        throw $ex;
    }
}

/** Ubah nama dan role. Hasil: null jika sukses, atau teks error. */
function usr_update(int $userId, string $nama, array $roleIds, int $actorId): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM users WHERE user_id = :id FOR UPDATE');
        $st->execute([':id' => $userId]);
        $user = $st->fetch();
        if (!$user) {
            $pdo->rollBack();
            return 'Pengguna tidak ditemukan.';
        }

        $adminRid = usr_admin_role_id();
        $hadAdmin = in_array($adminRid, usr_role_ids($userId), true);
        $keepsAdmin = in_array($adminRid, $roleIds, true);

        if ($hadAdmin && !$keepsAdmin) {
            if ($userId === $actorId) {
                $pdo->rollBack();
                return 'Anda tidak boleh mencabut role Admin IT dari akun Anda sendiri.';
            }
            if ($user['status'] === 'active' && usr_other_active_admins($userId) < 1) {
                $pdo->rollBack();
                return 'Tidak bisa dicabut: harus tersisa minimal satu Admin IT aktif.';
            }
        }

        $pdo->prepare('UPDATE users SET nama = :n WHERE user_id = :id')->execute([':n' => $nama, ':id' => $userId]);
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute([':id' => $userId]);
        $ins = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        foreach ($roleIds as $rid) {
            $ins->execute([':u' => $userId, ':r' => $rid]);
        }
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Aktifkan/nonaktifkan. Hasil: null jika sukses, atau teks error. */
function usr_set_status(int $userId, string $newStatus, int $actorId): ?string
{
    if (!array_key_exists($newStatus, usr_status_labels())) {
        return 'Status tidak valid.';
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM users WHERE user_id = :id FOR UPDATE');
        $st->execute([':id' => $userId]);
        $user = $st->fetch();
        if (!$user) {
            $pdo->rollBack();
            return 'Pengguna tidak ditemukan.';
        }
        if ($newStatus === 'inactive') {
            if ($userId === $actorId) {
                $pdo->rollBack();
                return 'Anda tidak boleh menonaktifkan akun Anda sendiri.';
            }
            $adminRid = usr_admin_role_id();
            if (in_array($adminRid, usr_role_ids($userId), true) && usr_other_active_admins($userId) < 1) {
                $pdo->rollBack();
                return 'Tidak bisa dinonaktifkan: harus tersisa minimal satu Admin IT aktif.';
            }
        }
        $pdo->prepare('UPDATE users SET status = :s WHERE user_id = :id')->execute([':s' => $newStatus, ':id' => $userId]);
        $pdo->commit();
        return null;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function usr_set_password(int $userId, string $password): void
{
    db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id')
        ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);
}

/** Ubah password sendiri. Hasil: daftar error (kosong jika sukses). */
function usr_change_own_password(int $userId, string $current, string $new, string $confirm): array
{
    $u = db()->prepare('SELECT username, password_hash FROM users WHERE user_id = :id LIMIT 1');
    $u->execute([':id' => $userId]);
    $row = $u->fetch();
    if (!$row || !password_verify($current, (string) $row['password_hash'])) {
        return ['Password saat ini salah.'];
    }
    $errors = usr_password_errors($new, (string) $row['username']);
    if ($new !== $confirm) {
        $errors[] = 'Konfirmasi password baru tidak sama.';
    }
    if ($new === $current) {
        $errors[] = 'Password baru harus berbeda dari password saat ini.';
    }
    if ($errors) {
        return $errors;
    }
    usr_set_password($userId, $new);
    return [];
}

/** [role_id => ['name', 'desc', 'users' => n, 'perms' => [kode...]]] */
function usr_roles_overview(): array
{
    $out = [];
    foreach (usr_all_roles() as $id => [$name, $desc]) {
        $out[$id] = ['name' => $name, 'desc' => $desc, 'users' => 0, 'perms' => []];
    }
    foreach (db()->query('SELECT role_id, COUNT(*) AS n FROM user_roles GROUP BY role_id')->fetchAll() as $r) {
        if (isset($out[(int) $r['role_id']])) {
            $out[(int) $r['role_id']]['users'] = (int) $r['n'];
        }
    }
    $sql = 'SELECT rp.role_id, p.permission_code FROM role_permissions rp
              JOIN permissions p ON p.permission_id = rp.permission_id ORDER BY p.permission_code';
    foreach (db()->query($sql)->fetchAll() as $r) {
        if (isset($out[(int) $r['role_id']])) {
            $out[(int) $r['role_id']]['perms'][] = (string) $r['permission_code'];
        }
    }
    return $out;
}