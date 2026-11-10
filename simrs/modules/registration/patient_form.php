<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = $id > 0 ? 'update' : 'create';

require_permission($mode === 'create' ? 'registration.create' : 'registration.update');

$existing = null;
if ($mode === 'update') {
    $existing = reg_find_patient($id);
    if ($existing === null) {
        flash_set('error', 'Pasien tidak ditemukan.');
        redirect('modules/registration/index.php');
    }
}

$errors     = [];
$duplicates = [];
$values     = $existing ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $values = $_POST;

    [$data, $errors] = reg_validate_patient($_POST, $mode === 'update' ? $id : null);

    // Peringatan kemungkinan pasien ganda (nama + tanggal lahir sama)
    if (!$errors && $mode === 'create' && empty($_POST['confirm_duplicate'])) {
        $duplicates = reg_find_possible_duplicates($data['nama_pasien'], $data['tanggal_lahir']);
        if ($duplicates) {
            $errors[] = 'Ada pasien dengan nama dan tanggal lahir yang sama. Periksa dulu, lalu centang konfirmasi di bawah jika memang pasien baru.';
        }
    }

    if (!$errors) {
        try {
            if ($mode === 'create') {
                $newId = reg_create_patient($data, (int) current_user()['id']);
                audit_log('Registrasi pasien baru', 'patients', $newId);
                flash_set('success', 'Pasien berhasil didaftarkan.');
                redirect('modules/registration/patient.php?id=' . $newId);
            } else {
                reg_update_patient($id, $data);
                audit_log('Ubah data pasien', 'patients', $id);
                flash_set('success', 'Data pasien berhasil diperbarui.');
                redirect('modules/registration/patient.php?id=' . $id);
            }
        } catch (PDOException $ex) {
            if (($ex->errorInfo[1] ?? 0) === 1062) {
                $errors[] = 'NIK sudah terdaftar pada pasien lain.';
            } else {
                error_log('Simpan pasien gagal: ' . $ex->getMessage());
                $errors[] = 'Data gagal disimpan karena kesalahan database.';
            }
        }
    }
}

$pageTitle    = $mode === 'create' ? 'Pasien Baru' : 'Ubah Data Pasien';
$activeModule = 'registration';
$breadcrumb   = 'Modul 2. Registrasi Pasien › ' . $pageTitle;

require APP_ROOT . '/includes/layout_header.php';

$action = url('modules/registration/patient_form.php' . ($mode === 'update' ? '?id=' . $id : ''));
$v      = fn(string $k): string => (string) ($values[$k] ?? '');
?>

<div class="card">
  <h2><?= e($pageTitle) ?></h2>
  <?php if ($mode === 'update'): ?>
  <?php else: ?>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <strong>Data belum bisa disimpan:</strong>
      <ul class="error-list">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($duplicates): ?>
    <div class="alert alert-info">
      <strong>Pasien yang mirip:</strong>
      <ul class="error-list">
        <?php foreach ($duplicates as $d): ?>
          <li>
            <?= e($d['no_rm']) ?> &middot; <?= e($d['nama_pasien']) ?> &middot; lahir <?= e(reg_date_id($d['tanggal_lahir'])) ?>
            &mdash; <a href="<?= e(url('modules/registration/patient.php?id=' . (int) $d['patient_id'])) ?>" target="_blank" rel="noopener">buka detail</a>
          </li>
        <?php endforeach; ?>
      </ul>
      <label class="check">
        <input type="checkbox" name="confirm_duplicate" value="1" form="form-pasien">
        Saya sudah memeriksa, ini memang pasien yang berbeda.
      </label>
    </div>
  <?php endif; ?>

  <form id="form-pasien" method="post" action="<?= e($action) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group">
        <label for="nama_pasien">Nama Lengkap <span class="req">*</span></label>
        <input type="text" id="nama_pasien" name="nama_pasien" maxlength="150" value="<?= e($v('nama_pasien')) ?>" required>
      </div>
      <div class="form-group">
        <label for="nik">NIK</label>
        <input type="text" id="nik" name="nik" maxlength="16" inputmode="numeric" value="<?= e($v('nik')) ?>">
        <div class="hint">16 digit. Boleh dikosongkan bila belum ada (misalnya bayi atau pasien tanpa identitas).</div>
      </div>
      <div class="form-group">
        <label for="tanggal_lahir">Tanggal Lahir <span class="req">*</span></label>
        <input type="date" id="tanggal_lahir" name="tanggal_lahir" max="<?= e(date('Y-m-d')) ?>" value="<?= e($v('tanggal_lahir')) ?>" required>
      </div>
      <div class="form-group">
        <label for="jenis_kelamin">Jenis Kelamin <span class="req">*</span></label>
        <select id="jenis_kelamin" name="jenis_kelamin" required>
          <option value="">&mdash; pilih &mdash;</option>
          <option value="L" <?= $v('jenis_kelamin') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
          <option value="P" <?= $v('jenis_kelamin') === 'P' ? 'selected' : '' ?>>Perempuan</option>
        </select>
      </div>
      <div class="form-group">
        <label for="telepon">Telepon</label>
        <input type="text" id="telepon" name="telepon" maxlength="20" inputmode="tel" value="<?= e($v('telepon')) ?>">
      </div>
      <div class="form-group full">
        <label for="alamat">Alamat</label>
        <textarea id="alamat" name="alamat" rows="3" maxlength="500"><?= e($v('alamat')) ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn btn-outline" href="<?= e(url($mode === 'update' ? 'modules/registration/patient.php?id=' . $id : 'modules/registration/index.php')) ?>">Batal</a>
      <button type="submit" class="btn btn-primary"><?= $mode === 'create' ? 'Simpan Pasien' : 'Simpan Perubahan' ?></button>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>