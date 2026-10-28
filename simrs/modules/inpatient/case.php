<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/inpatient.php';

require_permission('inpatient.view');

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$case = $id > 0 ? ipd_find_case($id) : null;
if ($case === null) {
    flash_set('error', 'Data rawat inap tidak ditemukan.');
    redirect('modules/inpatient/index.php');
}
$visitId = (int) $case['visit_id'];
$dokter  = cpoe_current_doctor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do  = (string) ($_POST['do'] ?? '');
    $err = null;
    switch ($do) {
        case 'note':
            require_permission('inpatient.progress');
            if ($dokter === null) {
                $err = 'Catatan perkembangan hanya dapat dibuat oleh dokter (akun harus terhubung ke Master Data Dokter).';
                break;
            }
            [$text, $errs] = ipd_validate_note($_POST);
            if ($errs) {
                $err = implode(' ', $errs);
                break;
            }
            $err = ipd_add_note($id, (int) $dokter['doctor_id'], $text);
            if ($err === null) {
                audit_log('Catatan perkembangan ' . $case['no_rawat_inap'], 'inpatient_progress_notes', null, $visitId);
                flash_set('success', 'Catatan perkembangan tersimpan.');
            }
            break;
        case 'transfer':
            require_permission('inpatient.admit');
            $err = ipd_transfer($id, (int) ($_POST['to_bed_id'] ?? 0), (int) current_user()['id']);
            if ($err === null) {
                audit_log('Pindah tempat tidur ' . $case['no_rawat_inap'], 'bed_transfer_history', null, $visitId);
                flash_set('success', 'Pasien dipindahkan.');
            }
            break;
        case 'order':
        case 'revoke':
        case 'confirm':
            require_permission('inpatient.discharge');
            if ($do === 'order' && $dokter === null) {
                $err = 'Rencana pulang hanya dapat ditetapkan oleh dokter.';
                break;
            }
            $err = ipd_discharge_step($id, $do);
            if ($err === null) {
                $names = ['order' => 'Tetapkan rencana pulang', 'revoke' => 'Batalkan rencana pulang', 'confirm' => 'Konfirmasi pemulangan'];
                audit_log($names[$do] . ' ' . $case['no_rawat_inap'], 'inpatient_admissions', $id, $visitId);
                flash_set('success', $names[$do] . ' berhasil.');
            }
            break;
        default:
            $err = 'Aksi tidak dikenal.';
    }
    if ($err !== null) {
        flash_set('error', $err);
    }
    redirect('modules/inpatient/case.php?id=' . $id);
}

audit_log('Buka rawat inap ' . $case['no_rawat_inap'], 'inpatient_admissions', $id, $visitId);

$notes     = ipd_notes($id);
$transfers = ipd_transfers($id);
$alergi    = reg_allergies((int) $case['patient_id']);
$stat      = ipd_status_labels();
$active    = ipd_is_active_status((string) $case['status']);
$freeBeds  = ($active && $case['status'] === 'admitted' && can('inpatient.admit')) ? ipd_free_beds() : [];
[$sl, $sc] = $stat[$case['status']] ?? [$case['status'], 'cp-info'];
$end       = $case['discharged_at'] ? strtotime($case['discharged_at']) : time();
$days      = max(1, (int) floor(($end - strtotime($case['admitted_at'])) / 86400) + 1);

$pageTitle    = 'Kasus Rawat Inap';
$activeModule = 'inpatient';
$breadcrumb   = 'Modul 5. Rawat Inap &rsaquo; Kasus';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2><?= e($case['no_rawat_inap']) ?> &mdash; <?= e($case['nama_pasien']) ?> <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($case['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($case['tanggal_lahir'])) ?> th / <?= e($case['jenis_kelamin']) ?></div>
    <div><span class="muted">Kamar / Bed</span><br><?= e($case['unit_name']) ?> &middot; <?= e($case['room_name']) ?> / <?= e($case['bed_no']) ?></div>
    <div><span class="muted">DPJP</span><br><?= e((string) $case['dpjp_name']) ?></div>
    <div><span class="muted">Masuk</span><br><?= e(date('d/m/Y H:i', strtotime($case['admitted_at']))) ?></div>
    <div><span class="muted">Pulang</span><br><?= $case['discharged_at'] ? e(date('d/m/Y H:i', strtotime($case['discharged_at']))) : '&mdash;' ?></div>
    <div><span class="muted">Lama rawat</span><br><?= $days ?> hari</div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => $a['allergen'] . (!empty($a['severity']) ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?></div>
  <?php endif; ?>

  <div class="toolbar">
    <a class="btn btn-outline" href="<?= e(url('modules/inpatient/index.php')) ?>">&larr; Daftar</a>
    <?php if ($active && can('nursing.view')): ?><a class="btn btn-outline" href="<?= e(url('modules/nursing/visit.php?visit_id=' . $visitId)) ?>">🩺 Keperawatan</a><?php endif; ?>
    <?php if ($active && $dokter && can('cpoe.create')): ?><a class="btn btn-outline" href="<?= e(url('modules/cpoe/order_form.php?visit_id=' . $visitId)) ?>">➕ Buat Order (CPOE)</a><?php endif; ?>
    <?php if (can('inpatient.discharge')): ?>
      <?php if ($case['status'] === 'admitted' && $dokter): ?>
        <form method="post" class="inline-form" action="<?= e(url('modules/inpatient/case.php?id=' . $id)) ?>" onsubmit="return confirm('Tetapkan rencana pulang?');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="order"><button type="submit" class="btn btn-primary">📤 Tetapkan Rencana Pulang</button></form>
      <?php elseif ($case['status'] === 'discharge_ordered'): ?>
        <form method="post" class="inline-form" action="<?= e(url('modules/inpatient/case.php?id=' . $id)) ?>" onsubmit="return confirm('Konfirmasi pasien pulang? Kunjungan akan ditutup dan tempat tidur dibersihkan.');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="confirm"><button type="submit" class="btn btn-primary">✔ Konfirmasi Pulang</button></form>
        <form method="post" class="inline-form" action="<?= e(url('modules/inpatient/case.php?id=' . $id)) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="revoke"><button type="submit" class="btn btn-outline">↩ Batalkan Rencana Pulang</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($freeBeds): ?>
<div class="card">
  <h2>Pindah Tempat Tidur</h2>
  <form method="post" action="<?= e(url('modules/inpatient/case.php?id=' . $id)) ?>" class="toolbar" onsubmit="return confirm('Pindahkan pasien ke tempat tidur ini?');">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="transfer">
    <select name="to_bed_id" required><option value="">-- tempat tidur tujuan --</option>
      <?php foreach ($freeBeds as $b): ?><option value="<?= (int) $b['bed_id'] ?>"><?= e($b['unit_name'] . ' · ' . $b['room_name'] . ' / ' . $b['bed_no']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Pindahkan</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Catatan Perkembangan (SOAP)</h2>
  <?php if ($active && can('inpatient.progress') && $dokter): ?>
    <form method="post" action="<?= e(url('modules/inpatient/case.php?id=' . $id)) ?>" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="note">
      <div class="form-row"><label for="s">Subjective (S)</label><textarea id="s" name="s" rows="2" maxlength="1500" required></textarea></div>
      <div class="form-row"><label for="o">Objective (O)</label><textarea id="o" name="o" rows="2" maxlength="1500" required></textarea></div>
      <div class="form-row"><label for="a">Assessment (A)</label><textarea id="a" name="a" rows="2" maxlength="1500" required></textarea></div>
      <div class="form-row"><label for="p">Plan (P)</label><textarea id="p" name="p" rows="2" maxlength="1500" required></textarea></div>
      <div class="form-row full"><button type="submit" class="btn btn-primary">Simpan Catatan</button></div>
    </form>
  <?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Waktu</th><th>Dokter</th><th>Catatan</th></tr>
    <?php if (!$notes): ?><tr><td colspan="3" class="empty">Belum ada catatan perkembangan.</td></tr><?php endif; ?>
    <?php foreach ($notes as $n): ?>
      <tr><td><?= e(date('d/m H:i', strtotime($n['note_date']))) ?></td><td><?= e((string) $n['doctor_name']) ?></td><td><?= nl2br(e((string) $n['soap_note'])) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Riwayat Pindah Tempat Tidur</h2>
  <div class="table-wrap"><table>
    <tr><th>Waktu</th><th>Dari</th><th>Ke</th><th>Petugas</th></tr>
    <?php if (!$transfers): ?><tr><td colspan="4" class="empty">Belum pernah berpindah.</td></tr><?php endif; ?>
    <?php foreach ($transfers as $t): ?>
      <tr><td><?= e(date('d/m H:i', strtotime($t['transferred_at']))) ?></td><td><?= e($t['dari']) ?></td><td><?= e($t['ke']) ?></td><td><?= e((string) $t['user_name']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>