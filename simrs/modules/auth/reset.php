<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/auth/index.php');
}
require_permission('auth.update');
csrf_require();

$userId = (int) ($_POST['user_id'] ?? 0);
$user   = $userId > 0 ? usr_find($userId) : null;
if ($user === null) {
    flash_set('error', 'Pengguna tidak ditemukan.');
    redirect('modules/auth/index.php');
}

$password = usr_generate_password();
usr_set_password($userId, $password);
audit_log('Reset password pengguna ' . $user['username'], 'users', $userId);

$pageTitle    = 'Reset Password';
$activeModule = 'auth';
$breadcrumb   = 'Modul 1. Autentikasi &rsaquo; Reset Password';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>🔑 Password Direset</h2>
  <p>Username: <code class="k"><?= e($user['username']) ?></code> (<?= e($user['nama']) ?>)</p>
  <p>Password sementara: <code class="k" style="font-size:18px"><?= e($password) ?></code></p>
  <div class="alert alert-warning">Catat sekarang dan sampaikan lewat jalur aman. Password ini <b>tidak akan ditampilkan lagi</b>. Jangan muat ulang halaman ini (akan membuat password baru lagi).</div>
  <a class="btn btn-primary" href="<?= e(url('modules/auth/index.php')) ?>">Kembali ke Daftar</a>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>