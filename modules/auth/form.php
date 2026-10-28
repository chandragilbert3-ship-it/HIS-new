<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/users.php';

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$mode = $id > 0 ? 'edit' : 'create';
require_permission($mode === 'edit' ? 'auth.update' : 'auth.create');

$user = null;
if ($mode === 'edit') {
    $user = usr_find($id);
    if ($user === null) {
        flash_set('error', 'Pengguna tidak ditemukan.');
        redirect('modules/auth/index.php');
    }
}

$allRoles = usr_all_roles();
$errors   = [];
$created  = null;
$old      = [
    'username' => $user['username'] ?? '',
    'nama'     => $user['nama'] ?? '',
    'roles'    => $mode === 'edit' ? usr_role_ids($id) : [],
];
$actorId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    [$data, $errors] = usr_validate($_POST, $mode, $mode === 'edit' ? $id : null);
    $old = ['username' => $data['username'] ?: $old['username'], 'nama' => $data['nama'], 'roles' => $data['roles']];

    if (!$errors) {
        if ($mode === 'create') {
            $password = usr_generate_password();
            try {
                $newId   = usr_create($data, $password);
                audit_log('Buat pengguna ' . $data['username'], 'users', $newId);
                $created = ['username' => $data['username'], 'password' => $password];
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        } else {
            $err = usr_update($id, $data['nama'], $data['roles'], $actorId);
            if ($err === null) {
                audit_log('Ubah pengguna ' . $user['username'] . ' (nama/role)', 'users', $id);
                flash_set('success', 'Data pengguna diperbarui.');
                redirect('modules/auth/index.php');
            }
            $errors[] = $err;
        }
    }
}

$pageTitle    = $mode === 'edit' ? 'Ubah Pengguna' : 'Pengguna Baru';
$activeModule = 'auth';
$breadcrumb   = 'Modul 1. Autentikasi &rsaquo; ' . $pageTitle;

require APP_ROOT . '/includes/layout_header.php';
?>

<?php if ($created): ?>
<div class="card">
  <h2>✅ Pengguna Dibuat</h2>
  <p>Username: <code class="k"><?= e($created['username']) ?></code></p>
  <p>Password sementara: <code class="k" style="font-size:18px"><?= e($created['password']) ?></code></p>
  <div class="alert alert-warning">Catat sekarang dan sampaikan lewat jalur aman. Password ini <b>tidak akan ditampilkan lagi</b>. Minta pengguna menggantinya lewat halaman "Ubah Password Saya".</div>
  <a class="btn btn-primary" href="<?= e(url('modules/auth/index.php')) ?>">Kembali ke Daftar</a>
</div>
<?php else: ?>
<div class="card">
  <h2><?= e($pageTitle) ?></h2>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('modules/auth/form.php' . ($mode === 'edit' ? '?id=' . $id : ''))) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="form-row">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" maxlength="50" value="<?= e($old['username']) ?>" <?= $mode === 'edit' ? 'readonly' : 'required' ?>>
    </div>
    <div class="form-row">
      <label for="nama">Nama Lengkap</label>
      <input type="text" id="nama" name="nama" maxlength="100" value="<?= e($old['nama']) ?>" required>
    </div>
    <div class="form-row full">
      <label>Role (boleh lebih dari satu)</label>
      <div class="cp-checks">
        <?php foreach ($allRoles as $rid => [$rname, $rdesc]): ?>
          <label title="<?= e($rdesc) ?>"><input type="checkbox" name="roles[]" value="<?= $rid ?>" <?= in_array($rid, $old['roles'], true) ? 'checked' : '' ?>> <?= e($rname) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="muted">Akun Dokter dan Radiografer juga harus dihubungkan di Master Data &rarr; Dokter agar bisa membuat order.</div>
    </div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Simpan Perubahan' : 'Buat Pengguna' ?></button>
      <a class="btn btn-outline" href="<?= e(url('modules/auth/index.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>