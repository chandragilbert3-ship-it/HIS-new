<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';

$entities = crud_entities();
$type     = (string) ($_GET['type'] ?? '');
if (!isset($entities[$type])) {
    redirect('modules/masterdata/index.php');
}
$entity = $entities[$type];

$id   = isset($_GET['id']) && $_GET['id'] !== '' ? (string) $_GET['id'] : null;
$mode = $id === null ? 'create' : 'update';

// Izin dicek di server untuk setiap aksi
require_permission($mode === 'create' ? 'masterdata.create' : 'masterdata.update');

$existing = null;
if ($mode === 'update') {
    $existing = crud_find($entity, $id);
    if ($existing === null) {
        flash_set('error', 'Data tidak ditemukan.');
        redirect('modules/masterdata/index.php?type=' . $type);
    }
}

$errors = [];
$values = $existing ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    [$data, $errors] = crud_validate($entity, $_POST, $mode, $existing);
    $values = $_POST;

    if (!$errors) {
        try {
            if ($mode === 'create') {
                $newId = crud_insert($entity, $data);
                $ref   = ctype_digit($newId) ? (int) $newId : null;
                audit_log('Tambah Master ' . $entity['label'] . ' #' . $newId, $entity['table'], $ref);
                flash_set('success', 'Data ' . $entity['label'] . ' berhasil ditambahkan.');
            } else {
                crud_update($entity, $id, $data);
                $ref = ctype_digit($id) ? (int) $id : null;
                audit_log('Ubah Master ' . $entity['label'] . ' #' . $id, $entity['table'], $ref);
                flash_set('success', 'Data ' . $entity['label'] . ' berhasil diperbarui.');
            }
            redirect('modules/masterdata/index.php?type=' . $type);
        } catch (PDOException $ex) {
            if (($ex->errorInfo[1] ?? 0) === 1062) {
                $errors[] = 'Data duplikat: nilai yang sama sudah ada di database.';
            } else {
                error_log('Master data gagal disimpan: ' . $ex->getMessage());
                $errors[] = 'Data gagal disimpan karena kesalahan database.';
            }
        }
    }
}

$formFields   = array_filter($entity['fields'], fn($f) => empty($f['readonly']));
$pageTitle    = ($mode === 'create' ? 'Tambah ' : 'Ubah ') . $entity['label'];
$activeModule = 'masterdata';
$breadcrumb   = 'Modul 16. Master Data › ' . $pageTitle;

require APP_ROOT . '/includes/layout_header.php';

$action = url('modules/masterdata/form.php?type=' . $type . ($id !== null ? '&id=' . rawurlencode($id) : ''));
?>

<?= masterdata_subnav('data') ?>

<div class="card">
  <h2><?= e($pageTitle) ?></h2>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <strong>Data belum bisa disimpan:</strong>
      <ul class="error-list">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e($action) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?php foreach ($formFields as $nama => $f): ?>
        <div class="form-group <?= ($f['type'] === 'textarea') ? 'full' : '' ?>">
          <label for="f_<?= e($nama) ?>">
            <?= e($f['label']) ?><?= !empty($f['required']) ? ' <span class="req">*</span>' : '' ?>
          </label>
          <?= crud_input_html($nama, $f, $values[$nama] ?? '', $mode, $existing) ?>
          <?php if (!empty($f['hint'])): ?><div class="hint"><?= e($f['hint']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="form-actions">
      <a class="btn btn-outline" href="<?= e(url('modules/masterdata/index.php?type=' . $type)) ?>">Batal</a>
      <button type="submit" class="btn btn-primary"><?= $mode === 'create' ? 'Simpan Data' : 'Simpan Perubahan' ?></button>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>