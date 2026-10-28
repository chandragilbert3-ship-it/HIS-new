<?php
declare(strict_types=1);

/**
 * Header supaya halaman terlindungi tidak disimpan cache browser
 * (tombol Back setelah logout tidak boleh menampilkan halaman lama).
 */
function send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

/**
 * Data user yang sedang login: id, username, nama, roles. Null kalau belum login.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Wajib login. Panggil di awal setiap halaman terlindungi.
 */
function require_login(): void
{
    send_no_cache_headers();

    if (!is_logged_in()) {
        redirect('login.php');
    }

    refresh_authorization();
}

/**
 * Cek ulang ke database di setiap permintaan: akun masih aktif? Role dan izin terbaru?
 * Akun yang dinonaktifkan langsung dikeluarkan, perubahan izin langsung berlaku.
 */
function refresh_authorization(): void
{
    $userId = (int) $_SESSION['user']['id'];

    $stmt = db()->prepare('SELECT status FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $status = $stmt->fetchColumn();

    if ($status !== 'active') {
        logout_user();
        session_start();
        flash_set('error', 'Akun Anda tidak aktif. Hubungi administrator.');
        redirect('login.php');
    }

    $_SESSION['user']['roles'] = load_user_roles($userId);
    $_SESSION['perms']         = array_fill_keys(load_user_permissions($userId), true);
}

/**
 * Proses login. Mengembalikan null kalau berhasil, atau teks error kalau gagal.
 */
function attempt_login(string $username, string $password): ?string
{
    $username = trim($username);
    $now      = time();

    // Penundaan sementara setelah terlalu banyak percobaan gagal
    if (($_SESSION['login_locked_until'] ?? 0) > $now) {
        $sisa = (int) $_SESSION['login_locked_until'] - $now;
        return 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $sisa . ' detik.';
    }

    $stmt = db()->prepare(
        'SELECT user_id, username, password_hash, nama, status
           FROM users
          WHERE username = :username
          LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // password_verify selalu dijalankan (juga saat username tidak ada)
    // supaya waktu respons tidak membocorkan username mana yang valid.
    $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
    $ok   = password_verify($password, $hash) && $user && $user['status'] === 'active';

    if (!$ok) {
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) {
            $_SESSION['login_locked_until'] = $now + 60;
            $_SESSION['login_fail']         = 0;
        }
        audit_log('Login gagal untuk username: ' . mb_substr($username, 0, 50));
        return 'Username atau password salah.';
    }

    // Login berhasil: session ID baru (mencegah session fixation)
    session_regenerate_id(true);
    unset($_SESSION['login_fail'], $_SESSION['login_locked_until']);

    $userId = (int) $user['user_id'];

    $_SESSION['user'] = [
        'id'       => $userId,
        'username' => $user['username'],
        'nama'     => $user['nama'],
        'roles'    => load_user_roles($userId),
    ];
    $_SESSION['perms']         = array_fill_keys(load_user_permissions($userId), true);
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
    $_SESSION['last_activity'] = $now;

    // Perbarui hash kalau algoritma PHP default berubah di masa depan
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $upd = db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
        $upd->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);
    }

    audit_log('Login berhasil', 'users', $userId);

    return null;
}

/**
 * Logout: catat, kosongkan session, hapus cookie, hancurkan session.
 */
function logout_user(): void
{
    if (is_logged_in()) {
        audit_log('Logout', 'users', (int) $_SESSION['user']['id']);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }

    session_destroy();
}