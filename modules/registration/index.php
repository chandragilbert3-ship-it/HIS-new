<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';          // untuk paginasi
require_once APP_ROOT . '/includes/registration.php';

require_permission('registration.view');

$q       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

[$rows, $total] = reg_search_patients($q, $page, $perPage);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
    [$rows, $total] = reg_search_patients($q, $page, $perPage);
}

$pageTitle    = 'Registrasi Pasien';
$activeModule = 'registration';
$breadcrumb   = 'Modul 2. Registrasi Pasien';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="card">
  <h2>Cari Pasien atau Daftarkan Pasien Baru</h2>

  <form method="get" action="<?= e(url('modules/registration/index.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari no. RM / NIK / nama / telepon..." autofocus>
    <button type="submit" class="btn btn-outline">Cari Pasien</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-outline" href="<?= e(url('modules/registration/index.php')) ?>">Reset</a>
    <?php endif; ?>
    <?php if (can('registration.create')): ?>
      <a class="btn btn-primary" href="<?= e(url('modules/registration/patient_form.php')) ?>">🆕 Pasien Baru</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <tr>
        <th>No. RM</th><th>Nama Pasien</th><th>Tgl Lahir (Usia)</th><th>JK</th><th>NIK</th><th>Telepon</th><th>Aksi</th>
      </tr>

      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty">
          <?= $q !== '' ? 'Pasien tidak ditemukan. Periksa ejaan, atau daftarkan sebagai pasien baru.' : 'Belum ada pasien terdaftar.' ?>
        </td></tr>
      <?php endif; ?>

      <?php foreach ($rows as $p): ?>
        <tr>
          <td><code class="k"><?= e($p['no_rm']) ?></code></td>
          <td><?= e($p['nama_pasien']) ?></td>
          <td><?= e(reg_date_id($p['tanggal_lahir'])) ?> (<?= e(reg_age($p['tanggal_lahir'])) ?> th)</td>
          <td><?= e($p['jenis_kelamin']) ?></td>
          <td><?= $p['nik'] ? e(reg_mask_nik($p['nik'])) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= $p['telepon'] ? e($p['telepon']) : '<span class="muted">&mdash;</span>' ?></td>
          <td class="aksi">
            <a class="action-link" href="<?= e(url('modules/registration/patient.php?id=' . (int) $p['patient_id'])) ?>">📄 Detail</a>
            <?php if (can('registration.create')): ?>
              <a class="action-link" href="<?= e(url('modules/registration/visit_form.php?patient_id=' . (int) $p['patient_id'])) ?>">🎫 Daftarkan Kunjungan</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/registration/index.php'), ['q' => $q], $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>