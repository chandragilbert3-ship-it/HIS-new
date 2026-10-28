<?php
declare(strict_types=1);

$menuModul = sidebar_modules();
?>
    <div class="sidebar">
      <a class="menu-item <?= $activeModule === 'dashboard' ? 'active' : '' ?>"
         href="<?= e(url('dashboard.php')) ?>">🏠 Dashboard</a>

      <?php foreach ($menuModul as $kode => $modul): ?>
        <?php if ($modul['ready']): ?>
          <a class="menu-item <?= $activeModule === $kode ? 'active' : '' ?>"
             href="<?= e(module_url($kode)) ?>"><?= e($modul['icon']) ?> <?= e($modul['label']) ?></a>
        <?php else: ?>
          <span class="menu-item disabled" title="Modul ini belum tersedia"><?= e($modul['icon']) ?> <?= e($modul['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>