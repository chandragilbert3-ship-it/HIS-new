<?php
declare(strict_types=1);

// ---- Pengaturan dasar aplikasi ------------------------------------------
define('BASE_URL', '/simrs');                 // folder di htdocs
define('APP_ROOT', dirname(__DIR__));         // C:\xampp\htdocs\simrs
define('SHOW_DEMO_CREDENTIALS', true);
define('SESSION_IDLE_SECONDS', 1800);         // 30 menit tanpa aktivitas => logout

// Koneksi database dimuat lebih dulu (dibutuhkan session berbasis database).
require_once APP_ROOT . '/config/database.php';

// Di XAMPP debug menyala; di Vercel set Environment Variable APP_DEBUG=false.
define('APP_DEBUG', filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

// ---- Session --------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('SIMRSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (env('SESSION_DRIVER') === 'db') {
        // Vercel: session disimpan di database supaya tidak hilang antar-instance.
        require_once APP_ROOT . '/includes/session_db.php';
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_set_save_handler(new DbSessionHandler(), true);
    }
    session_start();
}

// ---- Muat semua helper ----------------------------------------------------
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/permissions.php';
require_once APP_ROOT . '/includes/modules.php';
require_once APP_ROOT . '/includes/auth.php';

// ---- Sesi tidak aktif terlalu lama => dianggap logout ---------------------
if (isset($_SESSION['user'])) {
    $last = $_SESSION['last_activity'] ?? 0;
    if (time() - $last > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash_set('info', 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan login kembali.');
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// ---- Header keamanan dasar ------------------------------------------------
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');