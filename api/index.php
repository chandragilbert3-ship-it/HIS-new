<?php
declare(strict_types=1);

/**
 * Pintu masuk (front controller) untuk Vercel.
 *
 * Vercel hanya menjalankan PHP lewat file di folder api/. File ini menerima SEMUA
 * request (lihat vercel.json), lalu menjalankan file .php yang diminta di folder
 * simrs/, persis seperti Apache di XAMPP. Jadi file aplikasi tidak perlu dipindah.
 *
 *   /                              -> redirect ke /simrs/
 *   /simrs/                        -> simrs/index.php
 *   /simrs/modules/emr/visit.php   -> simrs/modules/emr/visit.php
 */

$appRoot = realpath(__DIR__ . '/../simrs');
$prefix  = '/simrs';

// Vercel melayani HTTPS di depan; beri tahu PHP supaya cookie session diberi flag "secure".
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

function not_found(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('404 - Halaman tidak ditemukan.');
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if ($path === '/' || $path === $prefix) {
    header('Location: ' . $prefix . '/', true, 302);
    exit;
}
if (!str_starts_with($path, $prefix . '/') || str_contains($path, "\0")) {
    not_found();
}

$rel = substr($path, strlen($prefix) + 1);       // mis. "modules/emr/visit.php"

// File statis (CSS/JS/gambar) di simrs/assets: di Vercel dilayani langsung oleh vercel.json;
// saat uji lokal dengan "php -S", biarkan server bawaan PHP yang mengirim file-nya.
if (str_starts_with($rel, 'assets/')) {
    $asset = realpath($appRoot . '/' . $rel);
    $assetsDir = $appRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
    if (PHP_SAPI === 'cli-server' && $asset !== false && is_file($asset)
        && str_starts_with($asset, $assetsDir) && !str_ends_with($asset, '.php')) {
        return false;
    }
    not_found();
}

// Folder tanpa garis miring di akhir => arahkan ke versi dengan garis miring.
if ($rel !== '' && !str_ends_with($rel, '/') && is_dir($appRoot . '/' . $rel)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $prefix . '/' . $rel . '/' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}
if ($rel === '' || str_ends_with($rel, '/')) {
    $rel .= 'index.php';
}

// Hanya halaman .php; folder internal tidak boleh dibuka langsung dari browser.
$top = explode('/', $rel)[0];
if (!str_ends_with($rel, '.php') || in_array($top, ['includes', 'config', 'storage', 'assets'], true)) {
    not_found();
}

$file = realpath($appRoot . '/' . $rel);
if ($file === false || !is_file($file) || !str_starts_with($file, $appRoot . DIRECTORY_SEPARATOR)) {
    not_found();
}

// Buat lingkungan seolah-olah file dipanggil langsung oleh Apache.
$_SERVER['SCRIPT_FILENAME'] = $file;
$_SERVER['SCRIPT_NAME']     = $prefix . '/' . $rel;
$_SERVER['PHP_SELF']        = $prefix . '/' . $rel;
chdir(dirname($file));

require $file;
