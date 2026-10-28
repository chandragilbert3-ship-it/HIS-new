<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/pharmacy.php';

require_permission('pharmacy.view');

$rxId = (int) ($_GET['prescription_id'] ?? $_POST['prescription_id'] ?? 0);
$rx   = $rxId > 0 ? ph_find($rxId) : null;
if ($rx === null) {
    flash_set('error', 'Resep tidak ditemukan.');
    redirect('modules/pharmacy/index.php');
}
$visitId    = (int) $rx['visit_id'];
$userId     = (int) current_user()['id'];
$warehouses = ph_warehouses();
$whId       = ph_pick_warehouse($warehouses, (int) ($_POST['warehouse_id'] ?? $_GET['w'] ?? 0));
$back       = 'modules/pharmacy/prescription.php?prescription_id=' . $rxId . ($whId > 0 ? '&w=' . $whId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do  = (string) ($_POST['do'] ?? '');
    $ack = ($_POST['allergy_ack'] ?? '') === '1';
    $err = null;
    $ok  = null;

    if ($do === 'prepare') {
        require_permission('pharmacy.update');
        $err = ph_prepare($rxId, $ack);
        $ok  = $err === null ? ['Verifikasi dan siapkan resep #' . $rxId, 'Resep disiapkan. Obat siap diserahkan.'] : null;
    } elseif ($do === 'cancel') {
        require_permission('pharmacy.update');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
            $err = 'Alasan pembatalan wajib diisi (5-300 karakter).';
        } else {
            $err = ph_cancel($rxId);
            $ok  = $err === null ? ['Batalkan resep #' . $rxId . ' - alasan: ' . $reason, 'Resep dibatalkan.'] : null;
        }
    } elseif ($do === 'dispense') {
        require_permission('pharmacy.create');
        if ($whId === 0) {
            $err = 'Belum ada gudang. Jalankan SQL gudang dan stok awal.';
        } else {
            [$lines, $errs] = ph_validate_dispense($_POST, ph_items($rxId, $whId));
            if ($errs) {
                $err = implode(' ', $errs);
            } else {
                [$err, $done] = ph_dispense($rxId, $whId, $lines, $userId, $ack);
                if ($err === null) {
                    $ok = ['Serahkan obat resep #' . $rxId . ' dari gudang #' . $whId . ' (' . count($lines) . ' item)', $done ? 'Obat diserahkan. Resep selesai.' : 'Sebagian obat diserahkan. Sisa dapat diserahkan kemudian.'];
                }
            }
        }
    } else {
        $err = 'Aksi tidak dikenal.';
    }

    if ($err === null && $ok !== null) {
        audit_log($ok[0], 'prescriptions', $rxId, $visitId);
        flash_set('success', $ok[1]);
    } elseif ($err !== null) {
        flash_set('error', $err);
    }
    redirect($back);
}

audit_log('Buka resep #' . $rxId, 'prescriptions', $rxId, $visitId);

$items   = ph_items($rxId, $whId);
$hist    = ph_history($rxId);
$alergi  = reg_allergies((int) $rx['patient_id']);
$match   = ph_allergy_matches($alergi, $items);
$stat    = ph_status_labels();
[$sl, $sc] = $stat[$rx['status']] ?? [$rx['status'], 'cp-info'];
$active  = in_array($rx['status'], ['new', 'prepared'], true) && in_array($rx['order_status'], ['ordered', 'in_progress'], true);
$hasRemaining = false;
foreach ($items as $it) {
    if ($it['remaining_c'] > 0) {
        $hasRemaining = true;
    }
}

$pageTitle    = 'Resep';
$activeModule = 'pharmacy';
$breadcrumb   = 'Modul 12. Farmasi &rsaquo; Resep';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Resep #<?= $rxId ?> &mdash; <?= e($rx['nama_pasien']) ?>
    <?= $rx['priority'] === 'cito' ? '<span class="cp-badge cp-off">CITO</span>' : '' ?>
    <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($rx['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($rx['tanggal_lahir'])) ?> th / <?= e($rx['jenis_kelamin']) ?></div>
    <div><span class="muted">Dokter peresep</span><br><?= e((string) $rx['doctor_name']) ?></div>
    <div><span class="muted">Dipesan</span><br><?= e(date('d/m/Y H:i', strtotime($rx['ordered_at']))) ?></div>
    <div><span class="muted">Alasan klinis</span><br><?= $rx['clinical_reason'] ? e((string) $rx['clinical_reason']) : '&mdash;' ?></div>
  </div>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ <b>Alergi pasien:</b> <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'] . (!empty($a['severity']) ? ' (' . $a['severity'] . ')' : ''), $alergi))) ?>
      <br><span class="muted">Sistem hanya mencocokkan nama obat. Alergi golongan obat tetap harus diperiksa apoteker.</span></div>
  <?php else: ?>
    <div class="muted">Tidak ada alergi tercatat.</div>
  <?php endif; ?>
  <?php if ($match): ?>
    <div class="alert alert-error">⛔ <b>Nama obat cocok dengan alergi pasien.</b> Hubungi dokter peresep sebelum melanjutkan.</div>
  <?php endif; ?>
  <div class="toolbar">
    <a class="btn btn-outline" href="<?= e(url('modules/pharmacy/index.php')) ?>">&larr; Antrean</a>
    <?php if ($warehouses): ?>
      <form method="get" class="inline-form" action="<?= e(url('modules/pharmacy/prescription.php')) ?>">
        <input type="hidden" name="prescription_id" value="<?= $rxId ?>">
        <label for="w" class="muted">Gudang:</label>
        <select id="w" name="w" onchange="this.form.submit()">
          <?php foreach ($warehouses as $w): ?>
            <option value="<?= (int) $w['warehouse_id'] ?>"<?= (int) $w['warehouse_id'] === $whId ? ' selected' : '' ?>><?= e($w['warehouse_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-outline">Pilih</button></noscript>
      </form>
    <?php endif; ?>
  </div>
  <?php if (!$warehouses): ?><div class="alert alert-warning">Belum ada gudang. Jalankan SQL gudang dan stok awal agar stok dapat dihitung.</div><?php endif; ?>
</div>

<div class="card">
  <h2>Item Obat</h2>

  <?php $canDispenseNow = $active && $rx['status'] === 'prepared' && can('pharmacy.create') && $hasRemaining; ?>
  <?php if ($canDispenseNow): ?>
  <form method="post" action="<?= e(url('modules/pharmacy/prescription.php?prescription_id=' . $rxId)) ?>">
    <?= csrf_field() ?><input type="hidden" name="prescription_id" value="<?= $rxId ?>"><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="do" value="dispense">
  <?php endif; ?>
  <div class="table-wrap"><table>
    <tr><th>Obat</th><th>Dosis / Rute / Frekuensi</th><th>Diminta</th><th>Sudah diserahkan</th><th>Sisa</th><th>Stok gudang</th><?= $canDispenseNow ? '<th>Serahkan sekarang</th>' : '' ?></tr>
    <?php if (!$items): ?><tr><td colspan="7" class="empty">Resep tidak memiliki item obat.</td></tr><?php endif; ?>
    <?php foreach ($items as $it):
        $iid = (int) $it['prescription_item_id'];
        $low = $it['stock_c'] < $it['remaining_c'];
        $def = min($it['remaining_c'], max(0, $it['stock_c']));
    ?>
      <tr>
        <td><b><?= e($it['drug_name']) ?></b> <span class="muted"><?= e(trim((string) $it['dosage_form'] . ' ' . (string) $it['strength'])) ?></span>
          <?php if (isset($match[$iid])): ?><br><span class="cp-badge cp-off">Cocok alergi: <?= e(implode(', ', $match[$iid])) ?></span><?php endif; ?>
          <?php if ($it['instruction']): ?><br><span class="muted"><?= e((string) $it['instruction']) ?></span><?php endif; ?></td>
        <td><?= e(trim((string) $it['dose'] . ' / ' . (string) $it['route'] . ' / ' . (string) $it['frequency'], ' /')) ?></td>
        <td><?= e(ph_fmt($it['ordered_c'] / 100)) ?> <?= e((string) $it['unit']) ?></td>
        <td><?= e(ph_fmt($it['dispensed_c'] / 100)) ?></td>
        <td><?= e(ph_fmt($it['remaining_c'] / 100)) ?></td>
        <td><?= $whId > 0 ? '<span class="cp-badge ' . ($low ? 'cp-off' : 'cp-ok') . '">' . e(ph_fmt($it['stock_c'] / 100)) . '</span>' : '&mdash;' ?></td>
        <?php if ($canDispenseNow): ?>
          <td><?php if ($it['remaining_c'] > 0): ?>
            <input type="text" inputmode="decimal" name="qty[<?= $iid ?>]" value="<?= e(inv_input($def)) ?>" style="width:80px">
          <?php else: ?>&mdash;<?php endif; ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>

  <?php if ($canDispenseNow): ?>
    <?php if ($match): ?><p><label><input type="checkbox" name="allergy_ack" value="1"> Saya sudah memeriksa alergi pasien dan menghubungi dokter peresep.</label></p><?php endif; ?>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Serahkan obat sesuai jumlah di atas? Stok gudang akan berkurang.');">✔ Serahkan Obat</button>
  </form>
  <?php endif; ?>

  <?php if ($active && $rx['status'] === 'new' && can('pharmacy.update')): ?>
    <form method="post" class="toolbar" action="<?= e(url('modules/pharmacy/prescription.php?prescription_id=' . $rxId)) ?>">
      <?= csrf_field() ?><input type="hidden" name="prescription_id" value="<?= $rxId ?>"><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="do" value="prepare">
      <?php if ($match): ?><label><input type="checkbox" name="allergy_ack" value="1"> Saya sudah memeriksa alergi pasien dan menghubungi dokter peresep.</label><?php endif; ?>
      <button type="submit" class="btn btn-primary">☑ Verifikasi &amp; Siapkan Resep</button>
    </form>
  <?php endif; ?>

  <?php if ($active && can('pharmacy.update') && !$hist): ?>
    <form method="post" class="toolbar" action="<?= e(url('modules/pharmacy/prescription.php?prescription_id=' . $rxId)) ?>" onsubmit="return confirm('Batalkan resep ini?');">
      <?= csrf_field() ?><input type="hidden" name="prescription_id" value="<?= $rxId ?>"><input type="hidden" name="warehouse_id" value="<?= $whId ?>"><input type="hidden" name="do" value="cancel">
      <input type="text" name="reason" maxlength="300" placeholder="Alasan pembatalan" required style="width:220px">
      <button type="submit" class="btn btn-outline">✖ Batalkan Resep</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Riwayat Penyerahan</h2>
  <div class="table-wrap"><table>
    <tr><th>No.</th><th>Waktu</th><th>Obat</th><th>Jumlah</th><th>Apoteker</th></tr>
    <?php if (!$hist): ?><tr><td colspan="5" class="empty">Belum ada penyerahan.</td></tr><?php endif; ?>
    <?php foreach ($hist as $h): ?>
      <tr>
        <td>DSP-<?= (int) $h['dispense_id'] ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime((string) $h['dispensed_at']))) ?></td>
        <td><?= e($h['drug_name']) ?></td>
        <td><?= e(ph_fmt((float) $h['quantity_dispensed'])) ?> <?= e((string) $h['unit']) ?></td>
        <td><?= e((string) $h['pharmacist_name']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>
