<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';

require_permission('masterdata.view');

$entities = crud_entities();
$type     = (string) ($_GET['type'] ?? '');
if (!isset($entities[$type])) {
    $type = (string) array_key_first($entities);
}
$entity = $entities[$type];

$q       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

[$rows, $total] = crud_list($entity, $q, $page, $perPage);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
    [$rows, $total] = crud_list($entity, $q, $page, $perPage);
}

$listFields = array_filter($entity['fields'], fn($f) => !empty($f['list']));
$bisaUbah   = can('masterdata.update');
$bisaTambah = can('masterdata.create');
$punyaAktif = $entity['active'] !== null;

$pageTitle    = 'Master Data';
$activeModule = 'masterdata';
$breadcrumb   = 'Modul 16. Master Data & Konfigurasi';

require APP_ROOT . '/includes/layout_header.php';
?>

<?= masterdata_subnav('data') ?>

<div class="card">
  <h2>1. Pilih Jenis Master Data</h2>
  <div class="master-types">
    <?php foreach ($entities as $kunci => $ent): ?>
      <a class="master-type <?= $kunci === $type ? 'selected' : '' ?>"
         href="<?= e(url('modules/masterdata/index.php?type=' . $kunci)) ?>">
        <span class="icon"><?= e($ent['icon']) ?></span><?= e($ent['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h2>2. Data <?= e($entity['label']) ?> (<?= e(number_format($total, 0, ',', '.')) ?>)</h2>
  <?php if (!empty($entity['note'])): ?>
  <?php endif; ?>

  <form method="get" action="<?= e(url('modules/masterdata/index.php')) ?>" class="toolbar">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari data <?= e(mb_strtolower($entity['label'])) ?>...">
    <button type="submit" class="btn btn-outline">Cari</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-outline" href="<?= e(url('modules/masterdata/index.php?type=' . $type)) ?>">Reset</a>
    <?php endif; ?>
    <?php if ($bisaTambah): ?>
      <a class="btn btn-primary" href="<?= e(url('modules/masterdata/form.php?type=' . $type)) ?>">+ Tambah Data</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <tr>
        <?php foreach ($listFields as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?>
        <?php if ($punyaAktif): ?><th>Status</th><?php endif; ?>
        <?php if ($bisaUbah): ?><th>Aksi</th><?php endif; ?>
      </tr>

      <?php if (!$rows): ?>
        <tr><td colspan="20" class="empty">Belum ada data<?= $q !== '' ? ' yang cocok dengan pencarian' : '' ?>.</td></tr>
      <?php endif; ?>

      <?php foreach ($rows as $row): ?>
        <?php $id = (string) $row[$entity['pk']]; ?>
        <tr>
          <?php foreach ($listFields as $nama => $f): ?>
            <td><?= crud_cell($f, $row, $nama) ?></td>
          <?php endforeach; ?>

          <?php if ($punyaAktif): ?>
            <?php $aktif = (int) $row[$entity['active']] === 1; ?>
            <td><span class="status-badge <?= $aktif ? 'status-aktif' : 'status-nonaktif' ?>"><?= $aktif ? 'Aktif' : 'Nonaktif' ?></span></td>
          <?php endif; ?>

          <?php if ($bisaUbah): ?>
            <td class="aksi">
              <a class="action-link" href="<?= e(url('modules/masterdata/form.php?type=' . $type . '&id=' . rawurlencode($id))) ?>">✏️ Edit</a>
              <?php if ($punyaAktif): ?>
                <form method="post" action="<?= e(url('modules/masterdata/toggle.php')) ?>" class="inline-form"
                      onsubmit="return confirm('<?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?> data ini?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="type" value="<?= e($type) ?>">
                  <input type="hidden" name="id" value="<?= e($id) ?>">
                  <input type="hidden" name="q" value="<?= e($q) ?>">
                  <input type="hidden" name="page" value="<?= e($page) ?>">
                  <button type="submit" class="action-link"><?= $aktif ? '🚫 Nonaktifkan' : '✅ Aktifkan' ?></button>
                </form>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/masterdata/index.php'), ['type' => $type, 'q' => $q], $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>