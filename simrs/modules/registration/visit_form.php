<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';          // untuk crud_field_options()
require_once APP_ROOT . '/includes/registration.php';

require_permission('registration.create');

$patientId = (int) ($_GET['patient_id'] ?? 0);
$patient   = $patientId > 0 ? reg_find_patient($patientId) : null;
if ($patient === null) {
    flash_set('error', 'Pilih pasien terlebih dahulu.');
    redirect('modules/registration/index.php');
}

$polyclinics = crud_field_options(
    ['type' => 'ref', 'ref' => ['table' => 'master_polyclinics', 'id' => 'polyclinic_id', 'label' => 'polyclinic_name', 'where' => 'is_active = 1', 'order' => 'polyclinic_name']],
    'create'
);
$doctors = crud_field_options(
    ['type' => 'ref', 'ref' => ['table' => 'master_doctors', 'id' => 'doctor_id', 'label' => "CONCAT(doctor_name, IFNULL(CONCAT(' - ', specialty), ''))", 'where' => 'is_active = 1', 'order' => 'doctor_name']],
    'create'
);
$insurances = reg_usable_insurances($patientId);
$allergies  = reg_allergies($patientId);
$svcLabel   = ['outpatient' => 'Rawat Jalan', 'emergency' => 'IGD'];

$errors = [];
$values = ['service_type' => 'outpatient'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $values = $_POST;

    $poli   = (string) ($_POST['polyclinic_id'] ?? '');
    $dokter = (string) ($_POST['doctor_id'] ?? '');
    $svc    = (string) ($_POST['service_type'] ?? '');
    $ins    = (string) ($_POST['patient_insurance_id'] ?? '');

    if (!array_key_exists($poli, $polyclinics)) {
        $errors[] = 'Poliklinik / unit tujuan wajib dipilih.';
    }
    if ($dokter !== '' && !array_key_exists($dokter, $doctors)) {
        $errors[] = 'Dokter tidak valid.';
    }
    if (!array_key_exists($svc, $svcLabel)) {
        $errors[] = 'Jenis layanan tidak valid.';
    }
    if ($ins !== '' && !ctype_digit($ins)) {
        $errors[] = 'Pilihan penjamin tidak valid.';
    }

    if (!$errors) {
        try {
            [$visitId, $queueNo] = reg_create_visit(
                $patientId,
                (int) $poli,
                $dokter === '' ? null : (int) $dokter,
                $svc,
                $ins === '' ? null : (int) $ins
            );
            audit_log('Registrasi kunjungan, antrean ' . $queueNo, 'visits', $visitId, $visitId);
            flash_set('success', 'Kunjungan berhasil didaftarkan. Nomor antrean: ' . $queueNo);
            redirect('modules/registration/visit_proof.php?id=' . $visitId);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            error_log('Registrasi kunjungan gagal: ' . $ex->getMessage());
            $errors[] = 'Kunjungan gagal disimpan karena kesalahan database.';
        }
    }
}

$pageTitle    = 'Daftarkan Kunjungan';
$activeModule = 'registration';
$breadcrumb   = 'Modul 2. Registrasi Pasien › Daftarkan Kunjungan';

require APP_ROOT . '/includes/layout_header.php';
$val = fn(string $k): string => (string) ($values[$k] ?? '');
?>

<div class="card">
  <h2>Kunjungan Baru</h2>
  <div class="info-grid">
    <div><span>No. RM</span><strong><?= e($patient['no_rm']) ?></strong></div>
    <div><span>Nama</span><strong><?= e($patient['nama_pasien']) ?></strong></div>
    <div><span>Tanggal Lahir</span><strong><?= e(reg_date_id($patient['tanggal_lahir'])) ?> (<?= e(reg_age($patient['tanggal_lahir'])) ?> th)</strong></div>
    <div><span>Telepon</span><strong><?= $patient['telepon'] ? e($patient['telepon']) : '&mdash;' ?></strong></div>
  </div>

  <?php if ($allergies): ?>
    <div class="alert-box">
      ⚠️ <b>Alergi tercatat:</b>
      <?php foreach ($allergies as $i => $a): ?><?= $i > 0 ? ', ' : '' ?><b><?= e($a['allergen']) ?></b><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Tujuan Kunjungan</h2>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <strong>Kunjungan belum bisa disimpan:</strong>
      <ul class="error-list">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('modules/registration/visit_form.php?patient_id=' . $patientId)) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group">
        <label for="polyclinic_id">Poliklinik / Unit Tujuan <span class="req">*</span></label>
        <select id="polyclinic_id" name="polyclinic_id" required>
          <option value="">&mdash; pilih &mdash;</option>
          <?php foreach ($polyclinics as $k => $teks): ?>
            <option value="<?= e($k) ?>" <?= $val('polyclinic_id') === (string) $k ? 'selected' : '' ?>><?= e($teks) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="doctor_id">Dokter</label>
        <select id="doctor_id" name="doctor_id">
          <option value="">&mdash; ditentukan kemudian &mdash;</option>
          <?php foreach ($doctors as $k => $teks): ?>
            <option value="<?= e($k) ?>" <?= $val('doctor_id') === (string) $k ? 'selected' : '' ?>><?= e($teks) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="service_type">Jenis Layanan <span class="req">*</span></label>
        <select id="service_type" name="service_type" required>
          <?php foreach ($svcLabel as $k => $teks): ?>
            <option value="<?= e($k) ?>" <?= $val('service_type') === $k ? 'selected' : '' ?>><?= e($teks) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="patient_insurance_id">Penjamin</label>
        <select id="patient_insurance_id" name="patient_insurance_id">
          <option value="">Pribadi / Umum (tanpa asuransi)</option>
          <?php foreach ($insurances as $i): ?>
            <option value="<?= (int) $i['patient_insurance_id'] ?>" <?= $val('patient_insurance_id') === (string) $i['patient_insurance_id'] ? 'selected' : '' ?>>
              <?= e($i['payer_name']) ?> &middot; <?= e($i['membership_no']) ?><?= $i['verification_status'] === 'pending' ? ' (belum terverifikasi)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!$insurances): ?>
          <div class="hint">Pasien belum punya asuransi yang berlaku. Tambahkan di halaman detail pasien bila perlu.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-actions">
      <a class="btn btn-outline" href="<?= e(url('modules/registration/patient.php?id=' . $patientId)) ?>">Batal</a>
      <button type="submit" class="btn btn-primary">Simpan Registrasi &amp; Ambil Antrean</button>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>