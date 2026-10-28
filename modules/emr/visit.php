<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/nursing.php';
require_once APP_ROOT . '/includes/emergency.php';
require_once APP_ROOT . '/includes/inpatient.php';
require_once APP_ROOT . '/includes/laboratory.php';
require_once APP_ROOT . '/includes/radiology.php';
require_once APP_ROOT . '/includes/pharmacy.php';
require_once APP_ROOT . '/includes/emr.php';

require_permission('emr.view');

$visitId = (int) ($_GET['visit_id'] ?? 0);
$visit   = $visitId > 0 ? emr_find_visit($visitId) : null;
if ($visit === null) {
    flash_set('error', 'Kunjungan tidak ditemukan.');
    redirect('modules/emr/index.php');
}

audit_log('Buka rekam medis kunjungan #' . $visitId, 'visits', $visitId, $visitId);

$canDraft = can('emr.update');
$dokter   = $canDraft ? cpoe_current_doctor() : null;
$notes    = emr_notes($visitId, $canDraft);
$dx       = emr_diagnoses($visitId);
$orders   = emr_orders($visitId);
$labRes   = lab_visit_results($visitId);
$radRes   = rad_visit_reports($visitId);
$drugRes  = ph_visit_drugs($visitId);
$assess   = nrs_assessments($visitId);
$plans    = nrs_care_plans($visitId);
$alergi   = reg_allergies((int) $visit['patient_id']);
$svc      = emr_service_labels();
$vstat    = emr_visit_status_labels();
$ostat    = emr_order_status_labels();
$otype    = emr_order_type_labels();
$fall     = nrs_fall_labels();
$pstat    = nrs_plan_status_labels();
[$vl, $vc] = $vstat[$visit['status']] ?? [$visit['status'], 'cp-info'];

$edId = $visit['service_type'] === 'emergency' ? emr_ed_encounter_id($visitId) : null;
$ipId = $visit['service_type'] === 'inpatient' ? emr_inpatient_id($visitId) : null;
$triages = $edId ? ed_triages($edId) : [];
$edObs   = $edId ? ed_observations($edId) : [];
$edDisp  = $edId ? ed_disposition($edId) : null;
$ipCase  = $ipId ? ipd_find_case($ipId) : null;
$ipNotes = $ipId ? ipd_notes($ipId) : [];
$prio    = ed_priority_labels();

$pageTitle    = 'Rekam Medis Kunjungan';
$activeModule = 'emr';
$breadcrumb   = 'Modul 7. EMR &rsaquo; Kunjungan';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2><?= e($visit['nama_pasien']) ?> &mdash; Kunjungan #<?= (int) $visit['visit_id'] ?> <span class="cp-badge <?= e($vc) ?>"><?= e($vl) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($visit['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($visit['tanggal_lahir'])) ?> th / <?= e($visit['jenis_kelamin']) ?></div>
    <div><span class="muted">Tanggal</span><br><?= e(date('d/m/Y H:i', strtotime($visit['visit_date']))) ?></div>
    <div><span class="muted">Layanan</span><br><?= e((string) ($svc[$visit['service_type']] ?? $visit['service_type'])) ?></div>
    <div><span class="muted">Poli / Unit</span><br><?= e((string) $visit['polyclinic_name']) ?></div>
    <div><span class="muted">Dokter</span><br><?= e((string) $visit['doctor_name']) ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => $a['allergen'] . (!empty($a['severity']) ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?></div>
  <?php endif; ?>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/emr/patient.php?patient_id=' . (int) $visit['patient_id'])) ?>">&larr; Rekam Medis Pasien</a></div>
</div>

<div class="card">
  <h2>Diagnosis</h2>
  <div class="table-wrap"><table>
    <tr><th>Kode ICD-10</th><th>Diagnosis</th><th>Jenis</th></tr>
    <?php if (!$dx): ?><tr><td colspan="3" class="empty">Belum ada diagnosis.</td></tr><?php endif; ?>
    <?php foreach ($dx as $d): ?>
      <tr><td><code class="k"><?= e($d['icd10_code']) ?></code></td><td><?= e($d['diagnosis_name']) ?></td><td><?= $d['diagnosis_type'] === 'primary' ? 'Utama' : 'Sekunder' ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Catatan Dokter (SOAP)</h2>
  <?php if (!$notes): ?><div class="muted">Belum ada catatan<?= $canDraft ? '' : ' final' ?>.</div><?php endif; ?>
  <?php foreach ($notes as $n): ?>
    <div class="card">
      <h3>Catatan #<?= (int) $n['emr_note_id'] ?> &middot; <?= e((string) $n['doctor_name']) ?>
        <span class="cp-badge <?= $n['status'] === 'final' ? 'cp-ok' : 'cp-warn' ?>"><?= $n['status'] === 'final' ? 'Final' : 'Draft' ?></span></h3>
      <div class="cp-info-box">
        <div><span class="muted">Subjective</span><br><?= nl2br(e((string) $n['subjective'])) ?></div>
        <div><span class="muted">Objective</span><br><?= nl2br(e((string) $n['objective'])) ?></div>
        <div><span class="muted">Assessment</span><br><?= nl2br(e((string) $n['assessment'])) ?></div>
        <div><span class="muted">Plan</span><br><?= nl2br(e((string) $n['plan'])) ?></div>
      </div>
      <?php foreach ($n['addenda'] as $a): ?>
        <div class="alert alert-warning">
          <b>Addendum</b> &middot; <?= e((string) $a['doctor_name']) ?> &middot; <?= e(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?><br>
          <span class="muted">Alasan:</span> <?= e((string) $a['reason']) ?><br><?= nl2br(e((string) $a['content'])) ?>
        </div>
      <?php endforeach; ?>
      <?php if ($n['status'] === 'final' && $canDraft && $dokter): ?>
        <a class="btn btn-outline" href="<?= e(url('modules/emr/addendum.php?note_id=' . (int) $n['emr_note_id'])) ?>">➕ Tambah Addendum</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($edId): ?>
<div class="card">
  <h2>Data IGD <span class="muted"><?= e(ed_case_no($edId)) ?></span></h2>
  <?php foreach ($triages as $t): [$pl, $pc] = $prio[$t['triage_priority']] ?? [$t['triage_priority'], 'cp-info']; ?>
    <div class="cp-info-box">
      <div><span class="muted">Triase</span><br><span class="cp-badge <?= e($pc) ?>"><?= e($pl) ?></span></div>
      <div><span class="muted">Keluhan</span><br><?= e((string) $t['chief_complaint']) ?></div>
      <?php foreach ($t['vital_list'] as $k => $val): ?><div><span class="muted"><?= e((string) $k) ?></span><br><?= e(is_scalar($val) ? (string) $val : json_encode($val)) ?></div><?php endforeach; ?>
      <div><span class="muted">Kesadaran / Nyeri</span><br><?= e((string) $t['consciousness']) ?> / <?= e((string) $t['pain_score']) ?></div>
    </div><hr>
  <?php endforeach; ?>
  <?php foreach ($edObs as $o): ?>
    <p><span class="muted"><?= e(date('d/m H:i', strtotime($o['observed_at']))) ?> &middot; <?= e((string) $o['user_name']) ?>:</span> <?= nl2br(e((string) $o['observation'])) ?></p>
  <?php endforeach; ?>
  <?php if ($edDisp): ?>
    <div class="cp-info-box">
      <div><span class="muted">Disposisi</span><br><b><?= e(ed_disposition_labels()[$edDisp['disposition_type']] ?? $edDisp['disposition_type']) ?></b></div>
      <div><span class="muted">Waktu</span><br><?= e(date('d/m/Y H:i', strtotime($edDisp['decision_at']))) ?></div>
      <div><span class="muted">Dokter</span><br><?= e($edDisp['doctor_name']) ?></div>
      <div><span class="muted">Catatan</span><br><?= $edDisp['notes'] ? e($edDisp['notes']) : '&mdash;' ?></div>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($ipCase): ?>
<div class="card">
  <h2>Data Rawat Inap <span class="muted"><?= e($ipCase['no_rawat_inap']) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">Kamar / Bed</span><br><?= e($ipCase['room_name']) ?> / <?= e($ipCase['bed_no']) ?></div>
    <div><span class="muted">DPJP</span><br><?= e((string) $ipCase['dpjp_name']) ?></div>
    <div><span class="muted">Masuk</span><br><?= e(date('d/m/Y H:i', strtotime($ipCase['admitted_at']))) ?></div>
    <div><span class="muted">Pulang</span><br><?= $ipCase['discharged_at'] ? e(date('d/m/Y H:i', strtotime($ipCase['discharged_at']))) : '&mdash;' ?></div>
  </div>
  <?php foreach ($ipNotes as $n): ?>
    <p><span class="muted"><?= e(date('d/m H:i', strtotime($n['note_date']))) ?> &middot; <?= e((string) $n['doctor_name']) ?>:</span><br><?= nl2br(e((string) $n['soap_note'])) ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Keperawatan</h2>
  <?php if (!$assess): ?><div class="muted">Belum ada pengkajian.</div><?php endif; ?>
  <?php foreach ($assess as $a): [$fl, $fc] = $fall[$a['fall_risk']] ?? [(string) $a['fall_risk'], 'cp-info']; ?>
    <div class="cp-info-box">
      <div><span class="muted">Waktu</span><br><?= e(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?></div>
      <?php foreach ($a['vital_list'] as $k => $val): ?><div><span class="muted"><?= e((string) $k) ?></span><br><?= e(is_scalar($val) ? (string) $val : json_encode($val)) ?></div><?php endforeach; ?>
      <div><span class="muted">Nyeri</span><br><?= e((string) $a['pain_score']) ?>/10</div>
      <div><span class="muted">Risiko jatuh</span><br><span class="cp-badge <?= e($fc) ?>"><?= e($fl) ?></span></div>
    </div><hr>
  <?php endforeach; ?>
  <?php foreach ($plans as $p): [$sl, $sc] = $pstat[$p['status']] ?? [(string) $p['status'], 'cp-info']; ?>
    <p><b>Rencana #<?= (int) $p['care_plan_id'] ?></b> <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span> &mdash; <?= e((string) $p['nursing_problem']) ?> <span class="muted">(target: <?= e((string) $p['target']) ?>)</span></p>
    <?php foreach ($p['interventions'] as $i): ?>
      <p class="muted">&bull; <?= e(date('d/m H:i', strtotime((string) $i['performed_at']))) ?> &mdash; <?= e((string) $i['intervention']) ?><?= $i['response'] ? ' (respons: ' . e((string) $i['response']) . ')' : '' ?></p>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Hasil Laboratorium</h2>
  <div class="table-wrap"><table>
    <tr><th>Order</th><th>Pemeriksaan</th><th>Hasil</th><th>Satuan</th><th>Nilai Rujukan</th><th>Flag</th></tr>
    <?php if (!$labRes): ?><tr><td colspan="6" class="empty">Belum ada hasil laboratorium tervalidasi.</td></tr><?php endif; ?>
    <?php foreach ($labRes as $lr): $lf = lab_flag_labels()[$lr['result_flag']] ?? null; ?>
      <tr>
        <td>#<?= (int) $lr['order_id'] ?></td>
        <td><?= e($lr['test_name']) ?></td>
        <td><b><?= e((string) $lr['result_value']) ?></b></td>
        <td><?= e((string) $lr['unit']) ?></td>
        <td><?= $lr['reference_range'] ? e((string) $lr['reference_range']) : '&mdash;' ?></td>
        <td><?= $lf ? '<span class="cp-badge ' . e($lf[1]) . '">' . e($lf[0]) . '</span>' : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Hasil Radiologi</h2>
  <?php if (!$radRes): ?><div class="muted">Belum ada laporan radiologi final.</div><?php endif; ?>
  <?php foreach ($radRes as $rr): ?>
    <div class="cp-info-box">
      <div><span class="muted">Pemeriksaan</span><br><b><?= e($rr['test_name']) ?></b> (<?= e((string) $rr['modality']) ?>) &middot; order #<?= (int) $rr['order_id'] ?></div>
      <div><span class="muted">Temuan / Interpretasi</span><br><?= nl2br(e((string) $rr['interpretation'])) ?></div>
      <div><span class="muted">Kesimpulan</span><br><?= nl2br(e((string) $rr['conclusion'])) ?></div>
      <div><span class="muted">Dokter pembaca</span><br><?= e((string) $rr['doctor_name']) ?></div>
    </div>
    <div>
      <?php foreach ($rr['image_ids'] as $imgId): ?>
        <a href="<?= e(url('modules/radiology/image.php?id=' . (int) $imgId)) ?>" target="_blank" rel="noopener"><img src="<?= e(url('modules/radiology/image.php?id=' . (int) $imgId)) ?>" alt="Gambar radiologi" style="max-width:140px;max-height:140px;margin:4px;border:1px solid #cbd5e1"></a>
      <?php endforeach; ?>
    </div><hr>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Obat &amp; Farmasi</h2>
  <div class="table-wrap"><table>
    <tr><th>Resep</th><th>Obat</th><th>Dosis / Rute / Frekuensi</th><th>Diminta</th><th>Diserahkan</th><th>Status</th></tr>
    <?php if (!$drugRes): ?><tr><td colspan="6" class="empty">Belum ada resep obat.</td></tr><?php endif; ?>
    <?php foreach ($drugRes as $dr): [$dl, $dc] = ph_status_labels()[$dr['status']] ?? [$dr['status'], 'cp-info']; ?>
      <tr>
        <td>#<?= (int) $dr['prescription_id'] ?></td>
        <td><b><?= e($dr['drug_name']) ?></b> <span class="muted"><?= e(trim((string) $dr['dosage_form'] . ' ' . (string) $dr['strength'])) ?></span></td>
        <td><?= e(trim((string) $dr['dose'] . ' / ' . (string) $dr['route'] . ' / ' . (string) $dr['frequency'], ' /')) ?></td>
        <td><?= e(ph_fmt((float) $dr['quantity'])) ?> <?= e((string) $dr['unit']) ?></td>
        <td><?= e(ph_fmt((float) $dr['dispensed'])) ?></td>
        <td><span class="cp-badge <?= e($dc) ?>"><?= e($dl) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Order Klinis</h2>
  <div class="table-wrap"><table>
    <tr><th>Order</th><th>Jenis</th><th>Prioritas</th><th>Item</th><th>Dokter</th><th>Waktu</th><th>Status</th></tr>
    <?php if (!$orders): ?><tr><td colspan="7" class="empty">Belum ada order.</td></tr><?php endif; ?>
    <?php foreach ($orders as $o): [$sl, $sc] = $ostat[$o['status']] ?? [$o['status'], 'cp-info']; ?>
      <tr>
        <td>#<?= (int) $o['order_id'] ?></td>
        <td><?= e($otype[$o['order_type']] ?? $o['order_type']) ?></td>
        <td><?= $o['priority'] === 'cito' ? '<span class="cp-badge cp-off">CITO</span>' : 'Rutin' ?></td>
        <td><?= (int) $o['jml_item'] ?></td>
        <td><?= e((string) $o['doctor_name']) ?></td>
        <td><?= e(date('d/m H:i', strtotime($o['ordered_at']))) ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>