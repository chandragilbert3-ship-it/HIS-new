<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inpatient.php';

require_permission('masterdata.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do  = (string) ($_POST['do'] ?? '');
    $err = null;
    if ($do === 'room') {
        require_permission('masterdata.create');
        [$d, $errs] = ipd_validate_room($_POST);
        $err = $errs ? implode(' ', $errs) : ipd_add_room($d);
        if ($err === null) {
            audit_log('Tambah kamar ' . $d['unit'] . ' / ' . $d['name'], 'master_rooms');
            flash_set('success', 'Kamar ditambahkan.');
        }
    } elseif ($do === 'bed') {
        require_permission('masterdata.create');
        $err = ipd_add_bed((int) ($_POST['room_id'] ?? 0), (string) ($_POST['bed_no'] ?? ''));
        if ($err === null) {
            audit_log('Tambah tempat tidur pada kamar #' . (int) $_POST['room_id'], 'beds');
            flash_set('success', 'Tempat tidur ditambahkan.');
        }
    } elseif ($do === 'room_active') {
        require_permission('masterdata.update');
        $active = ($_POST['active'] ?? '') === '1';
        $err = ipd_set_room_active((int) ($_POST['room_id'] ?? 0), $active);
        if ($err === null) {
            audit_log(($active ? 'Aktifkan' : 'Nonaktifkan') . ' kamar #' . (int) $_POST['room_id'], 'master_rooms', (int) $_POST['room_id']);
            flash_set('success', 'Status kamar diperbarui.');
        }
    } else {
        $err = 'Aksi tidak dikenal.';
    }
    if ($err !== null) {
        flash_set('error', $err);
    }
    redirect('modules/inpatient/rooms.php');
}

$rooms = ipd_rooms();

$pageTitle    = 'Kamar & Tempat Tidur';
$activeModule = 'masterdata';
$breadcrumb   = 'Master Data &rsaquo; Kamar &amp; Tempat Tidur';

require APP_ROOT . '/includes/layout_header.php';
?>
<?php if (can('masterdata.create')): ?>
<div class="card">
  <h2>Tambah Kamar</h2>
  <form method="post" action="<?= e(url('modules/inpatient/rooms.php')) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="do" value="room">
    <div class="form-row"><label for="unit_name">Unit / Bangsal</label><input type="text" id="unit_name" name="unit_name" maxlength="100" required></div>
    <div class="form-row"><label for="room_name">Nama Kamar</label><input type="text" id="room_name" name="room_name" maxlength="100" required></div>
    <div class="form-row"><label for="room_class">Kelas</label>
      <select id="room_class" name="room_class"><option value="">-- tanpa kelas --</option>
        <?php foreach (ipd_room_classes() as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row full"><button type="submit" class="btn btn-primary">Tambah Kamar</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Daftar Kamar</h2>
  <div class="table-wrap"><table>
    <tr><th>Unit</th><th>Kamar</th><th>Kelas</th><th>Bed (terisi/total)</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$rooms): ?><tr><td colspan="6" class="empty">Belum ada kamar.</td></tr><?php endif; ?>
    <?php foreach ($rooms as $r): $on = (int) $r['is_active'] === 1; ?>
      <tr>
        <td><?= e($r['unit_name']) ?></td>
        <td><?= e($r['room_name']) ?></td>
        <td><?= e((string) $r['room_class']) ?></td>
        <td><?= (int) $r['jml_terisi'] ?> / <?= (int) $r['jml_bed'] ?></td>
        <td><span class="cp-badge <?= $on ? 'cp-ok' : 'cp-off' ?>"><?= $on ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td class="aksi">
          <?php if ($on && can('masterdata.create')): ?>
            <form method="post" class="inline-form" action="<?= e(url('modules/inpatient/rooms.php')) ?>">
              <?= csrf_field() ?><input type="hidden" name="do" value="bed"><input type="hidden" name="room_id" value="<?= (int) $r['room_id'] ?>">
              <input type="text" name="bed_no" maxlength="20" placeholder="No. bed" required style="width:90px">
              <button type="submit" class="btn btn-outline">+ Bed</button>
            </form>
          <?php endif; ?>
          <?php if (can('masterdata.update')): ?>
            <form method="post" class="inline-form" action="<?= e(url('modules/inpatient/rooms.php')) ?>">
              <?= csrf_field() ?><input type="hidden" name="do" value="room_active"><input type="hidden" name="room_id" value="<?= (int) $r['room_id'] ?>"><input type="hidden" name="active" value="<?= $on ? '0' : '1' ?>">
              <button type="submit" class="btn btn-outline"><?= $on ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p><a class="btn btn-outline" href="<?= e(url('modules/inpatient/index.php')) ?>">&larr; Kembali ke Rawat Inap</a></p>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>