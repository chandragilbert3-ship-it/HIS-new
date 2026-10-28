<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/users.php';

require_permission('auth.view');

$q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$status = (string) ($_GET['status'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 15;

[$rows, $total] = usr_list($q, $status, $page, $per);
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
    [$rows, $total] = usr_list($q, $status, $page, $per);
}
$myId = (int) current_user()['id'];

$pageTitle    = 'Manajemen Pengguna';
$activeModule = 'auth';
$breadcrumb   = 'Modul 1. Autentikasi &amp; Pengguna';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a class="active" href="<?= e(url('modules/auth/index.php')) ?>">Pengguna</a>
  <a href="<?= e(url('modules/auth/roles.php')) ?>">Role &amp; Izin</a>
</div>

<div class="card">
  <h2>Daftar Pengguna</h2>

  <form method="get" action="<?= e(url('modules/auth/index.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari username / nama...">
    <select name="status">
      <option value="">Semua status</option>
      <?php foreach (usr_status_labels() as $c => $l): ?>
        <option value="<?= e($c) ?>" <?= $status === $c ? 'selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Cari</button>
    <?php if (can('auth.create')): ?>
      <a class="btn btn-primary" href="<?= e(url('modules/auth/form.php')) ?>">🆕 Pengguna Baru</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th>Aksi</th></tr>
      <?php if (!$rows): ?><tr><td colspan="5" class="empty">Tidak ada pengguna.</td></tr><?php endif; ?>
      <?php foreach ($rows as $u): ?>
        <?php $aktif = $u['status'] === 'active'; ?>
        <tr>
          <td><code class="k"><?= e($u['username']) ?></code><?= (int) $u['user_id'] === $myId ? ' <span class="muted">(Anda)</span>' : '' ?></td>
          <td><?= e($u['nama']) ?></td>
          <td><?= $u['roles'] ? e($u['roles']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><span class="cp-badge <?= $aktif ? 'cp-ok' : 'cp-off' ?>"><?= e(usr_status_labels()[$u['status']] ?? $u['status']) ?></span></td>
          <td class="aksi">
            <?php if (can('auth.update')): ?>
              <a class="action-link" href="<?= e(url('modules/auth/form.php?id=' . (int) $u['user_id'])) ?>">✏ Ubah</a>
              <form method="post" action="<?= e(url('modules/auth/reset.php')) ?>" class="inline-form"
                    onsubmit="return confirm('Reset password akun ini? Password lama langsung tidak berlaku.');">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                <button type="submit" class="action-link">🔑 Reset Password</button>
              </form>
              <form method="post" action="<?= e(url('modules/auth/status.php')) ?>" class="inline-form"
                    onsubmit="return confirm('<?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?> akun ini?');">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                <input type="hidden" name="status" value="<?= $aktif ? 'inactive' : 'active' ?>">
                <button type="submit" class="action-link <?= $aktif ? 'danger' : '' ?>"><?= $aktif ? '⛔ Nonaktifkan' : '✅ Aktifkan' ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/auth/index.php'), ['q' => $q, 'status' => $status], $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>