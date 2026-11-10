<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';

require_permission('registration.view');

$id      = (int) ($_GET['id'] ?? 0);
$patient = $id > 0 ? reg_find_patient($id) : null;
if ($patient === null) {
    flash_set('error', 'Pasien tidak ditemukan.');
    redirect('modules/registration/index.php');
}

$allergyErrors   = [];
$insuranceErrors = [];
$allergyValues   = [];
$insuranceValues = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Menambah alergi / asuransi = mengubah data pasien
    require_permission('registration.update');
    csrf_require();

    $aksi = (string) ($_POST['action'] ?? '');

    if ($aksi === 'add_allergy') {
        $allergyValues = $_POST;
        [$data, $allergyErrors] = reg_validate_allergy($_POST);
        if (!$allergyErrors) {
            $stmt = db()->prepare(
                'INSERT INTO patient_allergies (patient_id, allergen, reaction, severity)
                 VALUES (:p, :a, :r, :s)'
            );
            $stmt->execute([':p' => $id, ':a' => $data['allergen'], ':r' => $data['reaction'], ':s' => $data['severity']]);
            audit_log('Tambah alergi pasien: ' . $data['allergen'], 'patient_allergies', (int) db()->lastInsertId());
            flash_set('success', 'Alergi berhasil dicatat.');
            redirect('modules/registration/patient.php?id=' . $id);
        }
    } elseif ($aksi === 'add_insurance') {
        $insuranceValues = $_POST;
        [$data, $insuranceErrors] = reg_validate_insurance($_POST);
        if (!$insuranceErrors) {
            $stmt = db()->prepare(
                'INSERT INTO patient_insurance (patient_id, payer_id, membership_no, valid_until, verification_status)
                 VALUES (:p, :py, :no, :until, :st)'
            );
            $stmt->execute([
                ':p' => $id, ':py' => $data['payer_id'], ':no' => $data['membership_no'],
                ':until' => $data['valid_until'], ':st' => $data['verification_status'],
            ]);
            audit_log('Tambah asuransi pasien', 'patient_insurance', (int) db()->lastInsertId());
            flash_set('success', 'Data asuransi berhasil ditambahkan.');
            redirect('modules/registration/patient.php?id=' . $id);
        }
    } else {
        flash_set('error', 'Permintaan tidak dikenal.');
        redirect('modules/registration/patient.php?id=' . $id);
    }
} else {
    // Membuka data medis dicatat di audit log
    audit_log('Lihat data pasien', 'patients', $id);
}

$allergies  = reg_allergies($id);
$insurances = reg_insurances($id);
$visits     = reg_patient_visits($id);
$payers     = reg_insurance_payers();
$bisaUbah   = can('registration.update');
$bisaBuat   = can('registration.create');
$svcLabel   = reg_service_labels();
$stsLabel   = reg_visit_status();
$verLabel   = reg_verification_labels();
$sevLabel   = reg_severity_labels();

$pageTitle    = 'Detail Pasien';
$activeModule = 'registration';
$breadcrumb   = 'Modul 2. Registrasi Pasien › ' . $patient['no_rm'];

require APP_ROOT . '/includes/layout_header.php';
?>

<?php if ($allergies): ?>
  <div class="alert-box">
    ⚠️ <b>Alergi tercatat:</b>
    <?php foreach ($allergies as $i => $a): ?>
      <?= $i > 0 ? ', ' : '' ?><b><?= e($a['allergen']) ?></b><?= $a['severity'] ? ' (' . e($sevLabel[$a['severity']] ?? $a['severity']) . ')' : '' ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>1. Data Pasien</h2>
  <div class="info-grid">
    <div><span>No. RM</span><strong><?= e($patient['no_rm']) ?></strong></div>
    <div><span>Nama</span><strong><?= e($patient['nama_pasien']) ?></strong></div>
    <div><span>Tanggal Lahir</span><strong><?= e(reg_date_id($patient['tanggal_lahir'])) ?> (<?= e(reg_age($patient['tanggal_lahir'])) ?> th)</strong></div>
    <div><span>Jenis Kelamin</span><strong><?= $patient['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></strong></div>
    <div><span>NIK</span><strong><?= $patient['nik'] ? e($patient['nik']) : '&mdash;' ?></strong></div>
    <div><span>Telepon</span><strong><?= $patient['telepon'] ? e($patient['telepon']) : '&mdash;' ?></strong></div>
    <div class="wide"><span>Alamat</span><strong><?= $patient['alamat'] ? e($patient['alamat']) : '&mdash;' ?></strong></div>
    <div><span>Didaftarkan</span><strong><?= e(date('d-m-Y H:i', (int) strtotime((string) $patient['created_at']))) ?> oleh <?= e($patient['created_by_name'] ?? '-') ?></strong></div>
  </div>
  <div class="form-actions" style="margin-top:16px;">
    <a class="btn btn-outline" href="<?= e(url('modules/registration/index.php')) ?>">‹ Kembali ke Pencarian</a>
    <?php if ($bisaUbah): ?>
      <a class="btn btn-outline" href="<?= e(url('modules/registration/patient_form.php?id=' . $id)) ?>">✏️ Ubah Data</a>
    <?php endif; ?>
    <?php if ($bisaBuat): ?>
      <a class="btn btn-primary" href="<?= e(url('modules/registration/visit_form.php?patient_id=' . $id)) ?>">🎫 Daftarkan Kunjungan</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>2. Alergi</h2>
  <div class="table-wrap">
    <table>
      <tr><th>Alergen</th><th>Reaksi</th><th>Keparahan</th><th>Dicatat</th></tr>
      <?php if (!$allergies): ?><tr><td colspan="4" class="empty">Belum ada alergi tercatat.</td></tr><?php endif; ?>
      <?php foreach ($allergies as $a): ?>
        <tr>
          <td><?= e($a['allergen']) ?></td>
          <td><?= $a['reaction'] ? e($a['reaction']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= $a['severity'] ? e($sevLabel[$a['severity']] ?? $a['severity']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= e(date('d-m-Y', (int) strtotime((string) $a['recorded_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($bisaUbah): ?>
    <?php if ($allergyErrors): ?>
      <div class="alert alert-error" style="margin-top:14px;"><ul class="error-list">
        <?php foreach ($allergyErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('modules/registration/patient.php?id=' . $id)) ?>" autocomplete="off" class="subform">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_allergy">
      <div class="form-grid">
        <div class="form-group">
          <label for="allergen">Alergen <span class="req">*</span></label>
          <input type="text" id="allergen" name="allergen" maxlength="150" value="<?= e($allergyValues['allergen'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="severity">Keparahan</label>
          <select id="severity" name="severity">
            <option value="">&mdash; tidak diketahui &mdash;</option>
            <?php foreach ($sevLabel as $k => $teks): ?>
              <option value="<?= e($k) ?>" <?= ($allergyValues['severity'] ?? '') === $k ? 'selected' : '' ?>><?= e($teks) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full">
          <label for="reaction">Reaksi</label>
          <input type="text" id="reaction" name="reaction" maxlength="500" value="<?= e($allergyValues['reaction'] ?? '') ?>">
        </div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">+ Catat Alergi</button></div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>3. Penjamin / Asuransi</h2>
  <div class="table-wrap">
    <table>
      <tr><th>Penjamin</th><th>No. Keanggotaan</th><th>Berlaku Hingga</th><th>Verifikasi</th></tr>
      <?php if (!$insurances): ?><tr><td colspan="4" class="empty">Belum ada data asuransi.</td></tr><?php endif; ?>
      <?php foreach ($insurances as $i): ?>
        <?php
          $kedaluwarsa = $i['valid_until'] !== null && $i['valid_until'] < date('Y-m-d');
          $kelas = $i['verification_status'] === 'verified' ? 'status-aktif' : ($i['verification_status'] === 'rejected' ? 'status-nonaktif' : 'status-warn');
        ?>
        <tr>
          <td><?= e($i['payer_name']) ?></td>
          <td><?= e($i['membership_no']) ?></td>
          <td><?= $i['valid_until'] ? e(reg_date_id($i['valid_until'])) : '<span class="muted">&mdash;</span>' ?>
              <?= $kedaluwarsa ? '<span class="status-badge status-nonaktif">Kedaluwarsa</span>' : '' ?></td>
          <td><span class="status-badge <?= $kelas ?>"><?= e($verLabel[$i['verification_status']] ?? $i['verification_status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($bisaUbah): ?>
    <?php if ($insuranceErrors): ?>
      <div class="alert alert-error" style="margin-top:14px;"><ul class="error-list">
        <?php foreach ($insuranceErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('modules/registration/patient.php?id=' . $id)) ?>" autocomplete="off" class="subform">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_insurance">
      <div class="form-grid">
        <div class="form-group">
          <label for="payer_id">Penjamin <span class="req">*</span></label>
          <select id="payer_id" name="payer_id" required>
            <option value="">&mdash; pilih &mdash;</option>
            <?php foreach ($payers as $pid => $pname): ?>
              <option value="<?= e($pid) ?>" <?= (string) ($insuranceValues['payer_id'] ?? '') === (string) $pid ? 'selected' : '' ?>><?= e($pname) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="membership_no">Nomor Keanggotaan <span class="req">*</span></label>
          <input type="text" id="membership_no" name="membership_no" maxlength="50" value="<?= e($insuranceValues['membership_no'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="valid_until">Berlaku Hingga</label>
          <input type="date" id="valid_until" name="valid_until" value="<?= e($insuranceValues['valid_until'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="verification_status">Status Verifikasi <span class="req">*</span></label>
          <select id="verification_status" name="verification_status" required>
            <?php foreach ($verLabel as $k => $teks): ?>
              <option value="<?= e($k) ?>" <?= ($insuranceValues['verification_status'] ?? 'pending') === $k ? 'selected' : '' ?>><?= e($teks) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">+ Tambah Asuransi</button></div>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>4. Riwayat Kunjungan (10 terakhir)</h2>
  <div class="table-wrap">
    <table>
      <tr><th>Tanggal</th><th>Poliklinik</th><th>Dokter</th><th>Layanan</th><th>Antrean</th><th>Status</th><th>Aksi</th></tr>
      <?php if (!$visits): ?><tr><td colspan="7" class="empty">Belum ada kunjungan.</td></tr><?php endif; ?>
      <?php foreach ($visits as $v): ?>
        <?php [$stTeks, $stKelas] = $stsLabel[$v['status']] ?? [$v['status'], 'status-info']; ?>
        <tr>
          <td><?= e(date('d-m-Y H:i', (int) strtotime((string) $v['visit_date']))) ?></td>
          <td><?= e($v['polyclinic_name']) ?></td>
          <td><?= $v['doctor_name'] ? e($v['doctor_name']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= e($svcLabel[$v['service_type']] ?? $v['service_type']) ?></td>
          <td><?= $v['queue_no'] ? '<b>' . e($v['queue_no']) . '</b>' : '<span class="muted">&mdash;</span>' ?></td>
          <td><span class="status-badge <?= e($stKelas) ?>"><?= e($stTeks) ?></span></td>
          <td class="aksi">
            <a class="action-link" href="<?= e(url('modules/registration/visit_proof.php?id=' . (int) $v['visit_id'])) ?>">🖨️ Bukti</a>
            <?php if ($bisaUbah && $v['status'] === 'registered'): ?>
              <form method="post" action="<?= e(url('modules/registration/visit_cancel.php')) ?>" class="inline-form"
                    onsubmit="return confirm('Batalkan kunjungan ini?');">
                <?= csrf_field() ?>
                <input type="hidden" name="visit_id" value="<?= (int) $v['visit_id'] ?>">
                <input type="hidden" name="patient_id" value="<?= $id ?>">
                <button type="submit" class="action-link">🚫 Batalkan</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>