<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/scheduling.php';

require_permission('scheduling.update');
require_permission('registration.create');

$id  = (int) ($_GET['id'] ?? $_POST['appointment_id'] ?? 0);
$app = $id > 0 ? sch_find_appointment($id) : null;
if ($app === null) {
    flash_set('error', 'Appointment tidak ditemukan.');
    redirect('modules/scheduling/appointments.php');
}
if ($app['status'] !== 'booked' || $app['appointment_date'] !== date('Y-m-d')) {
    flash_set('error', 'Check-in hanya untuk appointment berstatus "Terbooking" pada hari ini.');
    redirect('modules/scheduling/appointments.php');
}

$insurances = reg_usable_insurances((int) $app['patient_id']);
$alergi     = reg_allergies((int) $app['patient_id']);
$errors     = [];
$ins        = (string) ($_POST['patient_insurance_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if ($ins !== '' && !ctype_digit($ins)) {
        $errors[] = 'Pilihan penjamin tidak valid.';
    }
    if (!$errors) {
        try {
            [$visitId, $queueNo] = reg_create_visit(
                (int) $app['patient_id'],
                (int) $app['polyclinic_id'],
                (int) $app['doctor_id'],
                'outpatient',
                $ins === '' ? null : (int) $ins,
                (int) $app['appointment_id']
            );
            sch_refresh_filled((int) $app['schedule_id']);
            audit_log('Check-in appointment ' . $app['booking_code'] . ', antrean ' . $queueNo, 'visits', $visitId, $visitId);
            flash_set('success', 'Check-in berhasil. Nomor antrean: ' . $queueNo);
            redirect('modules/registration/visit_proof.php?id=' . $visitId);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            error_log('Check-in gagal: ' . $ex->getMessage());
            $errors[] = 'Check-in gagal karena kesalahan database.';
        }
    }
}

$pageTitle    = 'Check-in Appointment';
$activeModule = 'scheduling';
$breadcrumb   = 'Modul 3. Penjadwalan &rsaquo; Check-in';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Check-in Appointment <code class="k"><?= e($app['booking_code']) ?></code></h2>
  <div class="cp-info-box">
    <div><span class="muted">Pasien</span><br><b><?= e($app['nama_pasien']) ?></b> (<?= e($app['no_rm']) ?>)</div>
    <div><span class="muted">Poliklinik</span><br><?= e($app['polyclinic_name']) ?></div>
    <div><span class="muted">Dokter</span><br><?= e($app['doctor_name']) ?></div>
    <div><span class="muted">Jam praktik</span><br><?= e(substr($app['start_time'], 0, 5)) ?> - <?= e(substr($app['end_time'], 0, 5)) ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ Alergi tercatat: <?= e(implode(', ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('modules/scheduling/checkin.php?id=' . $id)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="appointment_id" value="<?= $id ?>">
    <div class="form-row">
      <label for="patient_insurance_id">Penjamin</label>
      <select id="patient_insurance_id" name="patient_insurance_id">
        <option value="">Pribadi / Umum</option>
        <?php foreach ($insurances as $i): ?>
          <option value="<?= (int) $i['patient_insurance_id'] ?>" <?= $ins === (string) $i['patient_insurance_id'] ? 'selected' : '' ?>>
            <?= e($i['payer_name'] . ' - ' . $i['membership_no']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Check-in &amp; Buat Antrean</button>
      <a class="btn btn-outline" href="<?= e(url('modules/scheduling/appointments.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>