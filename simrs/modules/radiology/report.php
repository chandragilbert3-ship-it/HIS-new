<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/radiology.php';

require_permission('radiology.update');

$itemId = (int) ($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
$item   = $itemId > 0 ? rad_find_item($itemId) : null;
if ($item === null) {
    flash_set('error', 'Pemeriksaan tidak ditemukan.');
    redirect('modules/radiology/index.php');
}
$orderId = (int) $item['order_id'];
$dokter  = cpoe_current_doctor();
if ($dokter === null) {
    flash_set('error', 'Hasil baca hanya dapat dibuat oleh dokter (akun harus terhubung ke Master Data Dokter).');
    redirect('modules/radiology/order.php?order_id=' . $orderId);
}
$report = rad_report($itemId);
if (($report && $report['status'] === 'final') || $item['sch_status'] !== 'completed' || !in_array($item['order_status'], ['ordered', 'in_progress'], true)) {
    flash_set('error', 'Laporan tidak dapat diubah: pemeriksaan belum selesai, laporan sudah final, atau order tidak aktif.');
    redirect('modules/radiology/order.php?order_id=' . $orderId);
}

$errors = [];
$old    = ['interpretation' => (string) ($report['interpretation'] ?? ''), 'conclusion' => (string) ($report['conclusion'] ?? '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $final = ($_POST['action'] ?? '') === 'final';
    $old   = ['interpretation' => (string) ($_POST['interpretation'] ?? ''), 'conclusion' => (string) ($_POST['conclusion'] ?? '')];
    [$data, $errors] = rad_validate_report($_POST, $final);
    if (!$errors) {
        $err = rad_save_report($itemId, (int) $dokter['doctor_id'], $data, $final);
        if ($err === null) {
            audit_log(($final ? 'Finalkan' : 'Simpan draft') . ' laporan radiologi item #' . $itemId, 'rad_reports', null, (int) $item['visit_id']);
            flash_set('success', $final ? 'Laporan difinalkan.' : 'Draft laporan tersimpan.');
            redirect('modules/radiology/order.php?order_id=' . $orderId);
        }
        $errors[] = $err;
    }
}

$images = rad_images($itemId);

$pageTitle    = 'Hasil Baca Radiologi';
$activeModule = 'radiology';
$breadcrumb   = 'Modul 11. Radiologi &rsaquo; Hasil Baca';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Hasil Baca &mdash; <?= e($item['test_name']) ?> (<?= e((string) $item['modality']) ?>) &middot; <?= e($item['nama_pasien']) ?></h2>
  <?php if ($item['instruction']): ?><p><span class="muted">Catatan dokter pemesan:</span> <?= e((string) $item['instruction']) ?></p><?php endif; ?>
  <div>
    <?php foreach ($images as $im): ?>
      <a href="<?= e(url('modules/radiology/image.php?id=' . (int) $im['image_id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('modules/radiology/image.php?id=' . (int) $im['image_id'])) ?>" alt="Gambar radiologi" style="max-width:220px;max-height:220px;margin:4px;border:1px solid #cbd5e1"></a>
    <?php endforeach; ?>
  </div>
  <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('modules/radiology/report.php?item_id=' . $itemId)) ?>" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="item_id" value="<?= $itemId ?>">
    <div class="form-row full"><label for="interpretation">Temuan / Interpretasi</label>
      <textarea id="interpretation" name="interpretation" rows="6" maxlength="5000"><?= e($old['interpretation']) ?></textarea></div>
    <div class="form-row full"><label for="conclusion">Kesimpulan</label>
      <textarea id="conclusion" name="conclusion" rows="3" maxlength="2000"><?= e($old['conclusion']) ?></textarea></div>
    <div class="form-row full">
      <button type="submit" name="action" value="draft" class="btn btn-outline">Simpan Draft</button>
      <button type="submit" name="action" value="final" class="btn btn-primary" onclick="return confirm('Finalkan laporan? Setelah final tidak dapat diubah.');">Finalkan Laporan</button>
      <a class="btn btn-outline" href="<?= e(url('modules/radiology/order.php?order_id=' . $orderId)) ?>">Batal</a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>