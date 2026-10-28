<?php
declare(strict_types=1);

if (!is_logged_in()) {
    redirect('login.php');
}

$pageTitle    = $pageTitle    ?? 'SIMRS';
$activeModule = $activeModule ?? '';
$breadcrumb   = $breadcrumb   ?? $pageTitle;

$user    = current_user();
$flash   = flash_get();
$inisial = mb_strtoupper(mb_substr((string) $user['nama'], 0, 1));
$peran   = implode(', ', $user['roles']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - SIMRS Rumah Sakit Dunia Indah</title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>

  <div class="navbar">
    <div class="brand">
      <div class="brand-mini"><img src="<?= e(url('assets/images/logo-rs.png')) ?>" alt="Logo RS Dunia Indah"></div>
      <h1>SIMRS Rumah Sakit Dunia Indah</h1>
    </div>
    <div class="user-info">
      <span><?= e($user['nama']) ?> (<?= e($peran) ?>)</span>
      <div class="avatar"><?= e($inisial) ?></div>
      <form method="post" action="<?= e(url('logout.php')) ?>" class="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="logout-btn">🚪 Logout</button>
      </form>
    </div>
  </div>

  <div class="layout">
    <?php require APP_ROOT . '/includes/layout_sidebar.php'; ?>

    <div class="content">
      <div class="breadcrumb">Beranda &nbsp;›&nbsp; <span><?= e($breadcrumb) ?></span></div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"
             <?= $flash['type'] !== 'error' ? 'data-autohide="1"' : '' ?>><?= e($flash['message']) ?></div>
      <?php endif; ?>