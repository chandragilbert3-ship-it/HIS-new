<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';

require_permission('cpoe.create');

$dokter = cpoe_current_doctor();
if ($dokter === null) {
    flash_set('error', 'Akun Anda belum terhubung ke data dokter aktif, sehingga tidak dapat membuat order.');
    redirect('modules/cpoe/index.php');
}

$visitId = (int) ($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$visit   = $visitId > 0 ? cpoe_find_visit($visitId) : null;
if ($visit === null) {
    flash_set('error', 'Kunjungan tidak ditemukan.');
    redirect('modules/cpoe/index.php');
}
if (!in_array($visit['status'], ['registered', 'in_progress'], true)) {
    flash_set('error', 'Order hanya bisa dibuat untuk kunjungan yang masih aktif.');
    redirect('modules/cpoe/index.php');
}

$types = cpoe_order_types();
$type  = (string) ($_GET['type'] ?? $_POST['order_type'] ?? 'lab');
if (!isset($types[$type])) {
    $type = 'lab';
}

$alergi   = reg_allergies((int) $visit['patient_id']);
$alergen  = array_map(fn($a) => (string) $a['allergen'], $alergi);
$options  = cpoe_item_options($type);
$errors   = [];
$warnings = [];
$old      = ['priority' => 'routine', 'clinical_reason' => '', 'instruction' => '', 'items' => [], 'drug' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $old = array_merge($old, $_POST);

    [$data, $errors] = cpoe_validate_order($type, $_POST, $visitId);

    if (!$errors && $type === 'drug') {
        $warnings = cpoe_allergy_hits($alergen, array_column($data['items'], 'label'));
        if ($warnings && ($_POST['ack_allergy'] ?? '') !== '1') {
            $errors[] = 'Ada peringatan alergi. Centang konfirmasi di bawah jika dokter tetap melanjutkan.';
        }
    }

    if (!$errors) {
        $orderId = cpoe_save_order($visitId, (int) $dokter['doctor_id'], $type, $data);
        $note = $warnings ? ' [peringatan alergi dikonfirmasi dokter]' : '';
        audit_log('Buat order ' . cpoe_order_no($orderId) . ' (' . $type . ')' . $note, 'orders', $orderId, $visitId);
        flash_set('success', 'Order ' . cpoe_order_no($orderId) . ' berhasil dibuat.');
        redirect('modules/cpoe/orders.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    audit_log('Buka form order kunjungan #' . $visitId, 'visits', $visitId, $visitId);
}

$pageTitle    = 'CPOE - Buat Order';
$activeModule = 'cpoe';
$breadcrumb   = 'Modul 9. CPOE &rsaquo; Buat Order';
$selected     = array_map('strval', (array) ($old['items'] ?? []));

require APP_ROOT . '/includes/layout_header.php';
?>

<div class="cp-tabs">
  <a href="<?= e(url('modules/cpoe/index.php')) ?>">1. Pilih Kunjungan</a>
  <a class="active" href="#">Buat Order</a>
  <a href="<?= e(url('modules/cpoe/orders.php')) ?>">2. Order Saya</a>
</div>

<div class="card">
  <h2>Pasien</h2>
  <div class="cp-info-box">
    <div><span class="muted">Pasien</span><br><b><?= e($visit['nama_pasien']) ?></b> (<?= e($visit['no_rm']) ?>)</div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($visit['tanggal_lahir'])) ?> th / <?= e($visit['jenis_kelamin']) ?></div>
    <div><span class="muted">Kunjungan</span><br>#<?= (int) $visit['visit_id'] ?> &middot; <?= e($visit['polyclinic_name']) ?></div>
    <div><span class="muted">Dokter pemesan</span><br><?= e($dokter['doctor_name']) ?></div>
  </div>
  <?php if ($alergen): ?>
    <div class="alert alert-warning">⚠ <b>Alergi tercatat:</b> <?= e(implode(', ', $alergen)) ?></div>
  <?php else: ?>
    <div class="muted">Belum ada data alergi tercatat untuk pasien ini.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Buat Order Baru</h2>

  <div class="cp-types">
    <?php foreach ($types as $code => [$label, $icon]): ?>
      <a class="<?= $code === $type ? 'active' : '' ?>" href="<?= e(url('modules/cpoe/order_form.php?visit_id=' . $visitId . '&type=' . $code)) ?>"><?= $icon ?> <?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-error"><ul>
      <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>
  <?php if ($warnings): ?>
    <div class="alert alert-warning"><b>⚠ Peringatan alergi:</b><ul>
      <?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <?php if (!$options): ?>
    <div class="alert alert-warning">Belum ada data aktif untuk jenis order ini. Tambahkan lebih dulu di Master Data<?= $type === 'drug' ? ' (atau jalankan SQL seed obat)' : '' ?>.</div>
  <?php else: ?>
  <form method="post" action="<?= e(url('modules/cpoe/order_form.php?visit_id=' . $visitId . '&type=' . $type)) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="visit_id" value="<?= $visitId ?>">
    <input type="hidden" name="order_type" value="<?= e($type) ?>">

    <?php if ($type === 'drug'): ?>
      <div class="form-row full">
        <label>Daftar Obat (isi baris yang diperlukan, maksimal 5)</label>
        <div class="table-wrap"><table>
          <tr><th>Obat</th><th>Dosis</th><th>Rute</th><th>Frekuensi</th><th>Jumlah</th><th>Instruksi</th></tr>
          <?php for ($i = 0; $i < 5; $i++):
              $r = is_array($old['drug'][$i] ?? null) ? $old['drug'][$i] : []; ?>
            <tr>
              <td><select name="drug[<?= $i ?>][drug_id]">
                <option value="">-- pilih --</option>
                <?php foreach ($options as $id => $nm): ?>
                  <option value="<?= e((string) $id) ?>" <?= (string) ($r['drug_id'] ?? '') === (string) $id ? 'selected' : '' ?>><?= e($nm) ?></option>
                <?php endforeach; ?>
              </select></td>
              <td><input type="text" name="drug[<?= $i ?>][dose]" maxlength="50" placeholder="500 mg" value="<?= e((string) ($r['dose'] ?? '')) ?>"></td>
              <td><select name="drug[<?= $i ?>][route]">
                <option value="">--</option>
                <?php foreach (cpoe_route_labels() as $rc => $rl): ?>
                  <option value="<?= e($rc) ?>" <?= ($r['route'] ?? '') === $rc ? 'selected' : '' ?>><?= e($rl) ?></option>
                <?php endforeach; ?>
              </select></td>
              <td><input type="text" name="drug[<?= $i ?>][frequency]" maxlength="50" placeholder="3 x sehari" value="<?= e((string) ($r['frequency'] ?? '')) ?>"></td>
              <td><input type="text" name="drug[<?= $i ?>][quantity]" maxlength="7" placeholder="10" size="5" value="<?= e((string) ($r['quantity'] ?? '')) ?>"></td>
              <td><input type="text" name="drug[<?= $i ?>][instruction]" maxlength="255" placeholder="sesudah makan" value="<?= e((string) ($r['instruction'] ?? '')) ?>"></td>
            </tr>
          <?php endfor; ?>
        </table></div>
      </div>
    <?php else: ?>
      <div class="form-row full">
        <label><?= $type === 'admission' ? 'Pilih Layanan (satu)' : 'Pilih Item (boleh lebih dari satu)' ?></label>
        <div class="cp-checks">
          <?php foreach ($options as $id => $nm): ?>
            <label><input type="<?= $type === 'admission' ? 'radio' : 'checkbox' ?>" name="items[]" value="<?= e((string) $id) ?>" <?= in_array((string) $id, $selected, true) ? 'checked' : '' ?>> <?= e($nm) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row full">
        <label for="instruction">Instruksi / Catatan untuk Unit Tujuan</label>
        <input type="text" id="instruction" name="instruction" maxlength="255" value="<?= e((string) ($old['instruction'] ?? '')) ?>">
      </div>
    <?php endif; ?>

    <div class="form-row">
      <label for="priority">Prioritas</label>
      <select id="priority" name="priority">
        <?php foreach (cpoe_priority_labels() as $pc => $pl): ?>
          <option value="<?= e($pc) ?>" <?= ($old['priority'] ?? '') === $pc ? 'selected' : '' ?>><?= e($pl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row full">
      <label for="clinical_reason">Alasan Klinis <span class="muted">(wajib untuk Cito, Radiologi, Rawat Inap)</span></label>
      <textarea id="clinical_reason" name="clinical_reason" rows="3" maxlength="500"><?= e((string) ($old['clinical_reason'] ?? '')) ?></textarea>
    </div>

    <?php if ($type === 'drug' && $warnings): ?>
      <div class="form-row full">
        <label class="inline"><input type="checkbox" name="ack_allergy" value="1"> Saya (dokter) telah menilai peringatan alergi dan tetap melanjutkan order ini.</label>
      </div>
    <?php endif; ?>

    <div class="form-row full">
      <button type="submit" class="btn btn-primary">Kirim Order</button>
      <a class="btn btn-outline" href="<?= e(url('modules/cpoe/index.php')) ?>">Batal</a>
    </div>
  </form>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>