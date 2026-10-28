<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/users.php';

require_permission('auth.view');

$roles = usr_roles_overview();

$pageTitle    = 'Role & Izin';
$activeModule = 'auth';
$breadcrumb   = 'Modul 1. Autentikasi &rsaquo; Role &amp; Izin';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/auth/index.php')) ?>">Pengguna</a>
  <a class="active" href="<?= e(url('modules/auth/roles.php')) ?>">Role &amp; Izin</a>
</div>

<div class="card">
  <h2>Role dan Izin Akses</h2>
</div>

<div class="rp-grid">
  <?php foreach ($roles as $r): ?>
    <div class="card">
      <h2><?= e($r['name']) ?> <span class="cp-badge cp-info"><?= (int) $r['users'] ?> pengguna</span></h2>
      <div class="muted"><?= e($r['desc']) ?></div>
      <p>
        <?php if (!$r['perms']): ?>
          <span class="muted">Belum ada izin.</span>
        <?php endif; ?>
        <?php foreach ($r['perms'] as $p): ?>
          <code class="k"><?= e($p) ?></code>
        <?php endforeach; ?>
      </p>
    </div>
  <?php endforeach; ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>