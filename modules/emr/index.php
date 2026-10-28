<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/emr.php';

require_permission('emr.view');

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
$patients = emr_search($q);
if ($q !== '') {
    audit_log('Cari rekam medis: ' . $q, 'patients');
}

$pageTitle    = 'Rekam Medis Elektronik';
$activeModule = 'emr';
$breadcrumb   = 'Modul 7. Rekam Medis Elektronik';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Cari Rekam Medis Pasien</h2>
  <form method="get" action="<?= e(url('modules/emr/index.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" maxlength="60" placeholder="Nama pasien / No. RM">
    <button type="submit" class="btn btn-primary">Cari</button>
    <?php if ($q !== ''): ?><a class="btn btn-outline" href="<?= e(url('modules/emr/index.php')) ?>">Reset</a><?php endif; ?>
  </form>
  <div class="table-wrap"><table>
    <tr><th>No. RM</th><th>Nama</th><th>Usia / JK</th><th>Kunjungan Terakhir</th><th>Jml Kunjungan</th><th>Aksi</th></tr>
    <?php if (!$patients): ?><tr><td colspan="6" class="empty">Pasien tidak ditemukan.</td></tr><?php endif; ?>
    <?php foreach ($patients as $p): ?>
      <tr>
        <td><code class="k"><?= e($p['no_rm']) ?></code></td>
        <td><?= e($p['nama_pasien']) ?></td>
        <td><?= e((string) reg_age($p['tanggal_lahir'])) ?> th / <?= e($p['jenis_kelamin']) ?></td>
        <td><?= $p['last_visit'] ? e(date('d/m/Y', strtotime($p['last_visit']))) : '&mdash;' ?></td>
        <td><?= (int) $p['jml_kunjungan'] ?></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/emr/patient.php?patient_id=' . (int) $p['patient_id'])) ?>">📁 Buka Rekam Medis</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>