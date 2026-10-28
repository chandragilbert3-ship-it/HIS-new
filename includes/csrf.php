<?php
declare(strict_types=1);

/**
 * Token CSRF milik session ini (dibuat sekali).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Input tersembunyi untuk ditaruh di dalam setiap <form method="post">.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Cek token yang dikirim form.
 */
function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent)
        && $sent !== ''
        && hash_equals(csrf_token(), $sent);
}

/**
 * Hentikan permintaan kalau token salah. Panggil di awal setiap proses POST.
 */
function csrf_require(): void
{
    if (!csrf_verify()) {
        http_response_code(419);
        exit('Token keamanan tidak valid atau sudah kedaluwarsa. Kembali ke halaman sebelumnya, muat ulang, lalu coba lagi.');
    }
}