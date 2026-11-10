<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/scheduling.php';

require_permission('scheduling.create');

$patientId = (int) ($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$patient   = $patientId > 0 ? reg_find_patient($patientId) : null;
$q         = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

$found = [];
if ($patient === null && $q !== '') {
    [$found] = reg_search_patients($q, 1, 10);
}

$errors = [];
$polis  = crud_field_options(['type' => 'ref', 'ref' => ['table' => 'master_polyclinics', 'id' => 'polyclinic_id', 'label' => 'polyclinic_name', 'where' => 'is_active = 1', 'order' => 'polyclinic_name']], 'create');
$polyId = (int) ($_GET['poli'] ?? $_POST['poli'] ?? 0);
$slots  = [];
$alergi = [];

if ($patient !== null) {
    $alergi = reg_allergies($patientId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_require();
        $slot = (string) ($_POST['slot'] ?? '');
        if (!preg_match('/^(\d+)\|(\d{4}-\d{2}-\d{2})$/', $slot, $m)) {
            $errors[] = 'Pilih jadwal dan tanggal terlebih dahulu.';
        } else {
            try {
                [$apId, $code] = sch_book($patientId, (int) $m[1], $m[2]);
                audit_log('Booking appointment ' . $code, 'appointments', $apId);
                flash_set('success', 'Booking berhasil. Kode booking: ' . $code);
                redirect('modules/scheduling/print.php?id=' . $apId);
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            } catch (PDOException $ex) {
                error_log('Booking gagal: ' . $ex->getMessage());
                $errors[] = 'Booking gagal disimpan karena kesalahan database.';
            }
        }
    }

    if ($polyId > 0 && isset($polis[(string) $polyId])) {
        $slots = sch_open_slots(sch_schedules($polyId, 0));
    }
}

$pageTitle    = 'Booking Appointment';
$activeModule = 'scheduling';
$breadcrumb   = 'Modul 3. Penjadwalan &rsaquo; Booking';

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/scheduling/index.php')) ?>">1. Jadwal Dokter</a>
  <a class="active" href="<?= e(url('modules/scheduling/booking.php')) ?>">2. Booking Appointment</a>
  <a href="<?= e(url('modules/scheduling/appointments.php')) ?>">3. Daftar Appointment</a>
</div>

<?php if ($patient === null): ?>
<div class="card">
  <h2>Cari Pasien</h2>
  <form method="get" action="<?= e(url('modules/scheduling/booking.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="No. RM / nama / NIK / telepon..." autofocus>
    <button type="submit" class="btn btn-outline">Cari</button>
  </form>
  <?php if ($q !== ''): ?>
    <div class="table-wrap"><table>
      <tr><th>No. RM</th><th>Nama</th><th>Tgl Lahir</th><th>Aksi</th></tr>
      <?php if (!$found): ?><tr><td colspan="4" class="empty">Pasien tidak ditemukan. Daftarkan dulu di modul Registrasi.</td></tr><?php endif; ?>
      <?php foreach ($found as $p): ?>
        <tr>
          <td><code class="k"><?= e($p['no_rm']) ?></code></td>
          <td><?= e($p['nama_pasien']) ?></td>
          <td><?= e(reg_date_id($p['tanggal_lahir'])) ?></td>
          <td class="aksi"><a class="action-link" href="<?= e(url('modules/scheduling/booking.php?patient_id=' . (int) $p['patient_id'])) ?>">✔ Pilih</a></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <h2>Pasien</h2>
  <p><b><?= e($patient['nama_pasien']) ?></b> (<?= e($patient['no_rm']) ?>) &middot; <?= e(reg_age($patient['tanggal_lahir'])) ?> th
    &nbsp; <a class="action-link" href="<?= e(url('modules/scheduling/booking.php')) ?>">Ganti pasien</a></p>
</div>

<div class="card">
  <h2>Pilih Jadwal</h2>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="get" action="<?= e(url('modules/scheduling/booking.php')) ?>" class="toolbar">
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
    <select name="poli" required>
      <option value="">-- pilih poliklinik --</option>
      <?php foreach ($polis as $id => $nm): ?><option value="<?= e((string) $id) ?>" <?= (string) $polyId === (string) $id ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Tampilkan Jadwal</button>
  </form>

  <?php if ($polyId > 0): ?>
  <form method="post" action="<?= e(url('modules/scheduling/booking.php?patient_id=' . $patientId . '&poli=' . $polyId)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
    <input type="hidden" name="poli" value="<?= $polyId ?>">
    <div class="table-wrap"><table>
      <tr><th></th><th>Tanggal</th><th>Hari</th><th>Jam</th><th>Dokter</th><th>Sisa Kuota</th></tr>
      <?php if (!$slots): ?><tr><td colspan="6" class="empty">Tidak ada jadwal praktik pada poliklinik ini dalam <?= SCH_HORIZON_DAYS ?> hari ke depan.</td></tr><?php endif; ?>
      <?php foreach ($slots as $sl): $s = $sl['schedule']; ?>
        <tr>
          <td><input type="radio" name="slot" value="<?= (int) $s['schedule_id'] ?>|<?= e($sl['date']) ?>" <?= $sl['sisa'] <= 0 ? 'disabled' : '' ?>></td>
          <td><?= e(reg_date_id($sl['date'])) ?></td>
          <td><?= e($s['day_of_week']) ?></td>
          <td><?= e(substr($s['start_time'], 0, 5)) ?> - <?= e(substr($s['end_time'], 0, 5)) ?></td>
          <td><?= e($s['doctor_name']) ?></td>
          <td><?= $sl['sisa'] > 0 ? (int) $sl['sisa'] . ' dari ' . (int) $s['quota'] : '<span class="cp-badge cp-warn">Penuh</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <?php if ($slots): ?>
      <p><button type="submit" class="btn btn-primary">Simpan Booking</button></p>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>