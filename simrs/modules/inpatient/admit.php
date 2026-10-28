<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/inpatient.php';

require_permission('inpatient.admit');

$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order   = $orderId > 0 ? ipd_find_order($orderId) : null;
if ($order === null || !in_array($order['status'], ['ordered', 'in_progress'], true)) {
    flash_set('error', 'Order admisi tidak ditemukan atau sudah diproses.');
    redirect('modules/inpatient/index.php');
}

$errors  = [];
$bedId   = (int) ($_POST['bed_id'] ?? 0);
$dpjpId  = (int) ($_POST['dpjp_id'] ?? ($order['ordering_doctor_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    try {
        $id = ipd_admit($orderId, $bedId, $dpjpId);
        audit_log('Admisi rawat inap dari order #' . $orderId, 'inpatient_admissions', $id);
        flash_set('success', 'Pasien berhasil diadmisi.');
        redirect('modules/inpatient/case.php?id=' . $id);
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    } catch (PDOException $ex) {
        error_log('Admisi gagal: ' . $ex->getMessage());
        $errors[] = 'Gagal menyimpan karena kesalahan database.';
    }
}

$freeBeds = ipd_free_beds();
$doctors  = ipd_doctors();
$alergi   = reg_allergies((int) $order['patient_id']);

$pageTitle    = 'Admisi Rawat Inap';
$activeModule = 'inpatient';
$breadcrumb   = 'Modul 5. Rawat Inap &rsaquo; Admisi';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Admisi &mdash; <?= e($order['nama_pasien']) ?> (<?= e($order['no_rm']) ?>)</h2>
  <?php if ($order['clinical_reason']): ?><p><span class="muted">Alasan klinis:</span> <?= e((string) $order['clinical_reason']) ?></p><?php endif; ?>
  <?php if ($alergi): ?>
    <div class="alert alert-warning">⚠ Alergi: <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if (!$freeBeds): ?>
    <div class="alert alert-warning">Tidak ada tempat tidur tersedia. Siapkan tempat tidur terlebih dahulu.</div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('modules/inpatient/admit.php?order_id=' . $orderId)) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>">
    <div class="form-row"><label for="bed_id">Tempat Tidur</label>
      <select id="bed_id" name="bed_id" required><option value="">-- pilih --</option>
        <?php foreach ($freeBeds as $b): ?><option value="<?= (int) $b['bed_id'] ?>" <?= $bedId === (int) $b['bed_id'] ? 'selected' : '' ?>><?= e($b['unit_name'] . ' · ' . $b['room_name'] . ' / ' . $b['bed_no'] . ($b['room_class'] ? ' (' . $b['room_class'] . ')' : '')) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row"><label for="dpjp_id">DPJP</label>
      <select id="dpjp_id" name="dpjp_id" required><option value="">-- pilih --</option>
        <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['doctor_id'] ?>" <?= $dpjpId === (int) $d['doctor_id'] ? 'selected' : '' ?>><?= e($d['doctor_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-row full">
      <button type="submit" class="btn btn-primary" <?= $freeBeds ? '' : 'disabled' ?>>Proses Admisi</button>
      <a class="btn btn-outline" href="<?= e(url('modules/inpatient/index.php')) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>