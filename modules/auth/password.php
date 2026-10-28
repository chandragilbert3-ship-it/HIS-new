<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/users.php';

require_login(); // semua pengguna yang login boleh mengganti password sendiri

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $userId = (int) current_user()['id'];
    $errors = usr_change_own_password(
        $userId,
        (string) ($_POST['current'] ?? ''),
        (string) ($_POST['new'] ?? ''),
        (string) ($_POST['confirm'] ?? '')
    );
    if (!$errors) {
        audit_log('Ubah password sendiri', 'users', $userId);
        session_regenerate_id(true);
        flash_set('success', 'Password berhasil diubah.');
        redirect('dashboard.php');
    }
}

$pageTitle    = 'Ubah Password Saya';
$activeModule = '';
$breadcrumb   = 'Akun &rsaquo; Ubah Password';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Ubah Password Saya</h2>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('modules/auth/password.php')) ?>" class="form-grid" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-row full"><label for="current">Password Saat Ini</label>
      <input type="password" id="current" name="current" required autocomplete="current-password"></div>
    <div class="form-row"><label for="new">Password Baru</label>
      <input type="password" id="new" name="new" required minlength="10" autocomplete="new-password"></div>
    <div class="form-row"><label for="confirm">Ulangi Password Baru</label>
      <input type="password" id="confirm" name="confirm" required minlength="10" autocomplete="new-password"></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Simpan Password</button>
      <a class="btn btn-outline" href="<?= e(url('dashboard.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>