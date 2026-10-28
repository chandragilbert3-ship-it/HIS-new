<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

// Dibuka lewat alamat biasa (GET)? Jangan logout, cukup arahkan kembali.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
}

csrf_require();
logout_user();

// Session lama sudah dihancurkan; buka session baru hanya untuk menampilkan pesan.
session_start();
flash_set('success', 'Anda berhasil logout.');

send_no_cache_headers();
redirect('login.php');