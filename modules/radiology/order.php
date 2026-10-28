<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/registration.php';
require_once APP_ROOT . '/includes/cpoe.php';
require_once APP_ROOT . '/includes/radiology.php';

require_permission('radiology.view');

$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order   = $orderId > 0 ? rad_find_order($orderId) : null;
if ($order === null) {
    flash_set('error', 'Order radiologi tidak ditemukan.');
    redirect('modules/radiology/index.php');
}
$visitId = (int) $order['visit_id'];
$userId  = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $do     = (string) ($_POST['do'] ?? '');
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $item   = $itemId > 0 ? rad_find_item($itemId) : null;
    $err    = null;
    $ok     = null;

    if ($item === null || (int) $item['order_id'] !== $orderId) {
        $err = 'Pemeriksaan tidak ditemukan pada order ini.';
    } elseif ($do === 'schedule') {
        require_permission('radiology.create');
        $err = rad_schedule($itemId, (string) ($_POST['scheduled_at'] ?? ''));
        $ok  = $err === null ? ['Jadwalkan radiologi item #' . $itemId, 'rad_schedules', 'Pemeriksaan dijadwalkan.'] : null;
    } elseif ($do === 'start') {
        require_permission('radiology.update');
        $err = rad_schedule_step($itemId, 'start');
        $ok  = $err === null ? ['Mulai pemeriksaan radiologi item #' . $itemId, 'rad_schedules', 'Pemeriksaan dimulai.'] : null;
    } elseif ($do === 'cancel') {
        require_permission('radiology.update');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
            $err = 'Alasan pembatalan wajib diisi (5-300 karakter).';
        } else {
            $err = rad_schedule_step($itemId, 'cancel');
            $ok  = $err === null ? ['Batalkan jadwal radiologi item #' . $itemId . ' - alasan: ' . $reason, 'rad_schedules', 'Jadwal dibatalkan.'] : null;
        }
    } elseif ($do === 'complete') {
        require_permission('radiology.update');
        $err = rad_schedule_step($itemId, 'complete');
        $ok  = $err === null ? ['Selesaikan pemeriksaan radiologi item #' . $itemId, 'rad_schedules', 'Pemeriksaan selesai. Menunggu hasil baca dokter.'] : null;
    } elseif ($do === 'upload') {
        require_permission('radiology.create');
        $err = rad_store_image($itemId, $_FILES['image'] ?? [], $userId);
        $ok  = $err === null ? ['Unggah gambar radiologi item #' . $itemId, 'rad_images', 'Gambar tersimpan.'] : null;
    } else {
        $err = 'Aksi tidak dikenal.';
    }

    if ($err === null && $ok !== null) {
        audit_log($ok[0], $ok[1], null, $visitId);
        flash_set('success', $ok[2]);
    } elseif ($err !== null) {
        flash_set('error', $err);
    }
    redirect('modules/radiology/order.php?order_id=' . $orderId);
}

audit_log('Buka order radiologi #' . $orderId, 'orders', $orderId, $visitId);

$active = in_array($order['status'], ['ordered', 'in_progress'], true);
$items  = rad_items($orderId);
$alergi = reg_allergies((int) $order['patient_id']);
$dokter = can('radiology.update') ? cpoe_current_doctor() : null;
$sstat  = rad_schedule_status_labels();
$rstat  = rad_report_status_labels();

$pageTitle    = 'Order Radiologi';
$activeModule = 'radiology';
$breadcrumb   = 'Modul 11. Radiologi &rsaquo; Order';

require APP_ROOT . '/includes/layout_header.php';
?>
<div class="card">
  <h2>Order #<?= (int) $orderId ?> &mdash; <?= e($order['nama_pasien']) ?>
    <?= $order['priority'] === 'cito' ? '<span class="cp-badge cp-off">CITO</span>' : '' ?>
    <span class="cp-badge <?= $order['status'] === 'completed' ? 'cp-ok' : ($order['status'] === 'cancelled' ? 'cp-off' : 'cp-warn') ?>"><?= e($order['status']) ?></span></h2>
  <div class="cp-info-box">
    <div><span class="muted">No. RM</span><br><?= e($order['no_rm']) ?></div>
    <div><span class="muted">Usia / JK</span><br><?= e((string) reg_age($order['tanggal_lahir'])) ?> th / <?= e($order['jenis_kelamin']) ?></div>
    <div><span class="muted">Dokter pemesan</span><br><?= e((string) $order['doctor_name']) ?></div>
    <div><span class="muted">Dipesan</span><br><?= e(date('d/m/Y H:i', strtotime($order['ordered_at']))) ?></div>
    <div><span class="muted">Alasan klinis</span><br><?= $order['clinical_reason'] ? e((string) $order['clinical_reason']) : '&mdash;' ?></div>
  </div>
  <?php if ($alergi): ?><div class="alert alert-warning">⚠ <b>Alergi:</b> <?= e(implode('; ', array_map(fn($a) => (string) $a['allergen'], $alergi))) ?></div><?php endif; ?>
  <div class="toolbar"><a class="btn btn-outline" href="<?= e(url('modules/radiology/index.php')) ?>">&larr; Antrean</a></div>
</div>

<?php foreach ($items as $it):
    $iid = (int) $it['order_item_id'];
    $images = rad_images($iid);
    $rep = $it['report_status'] ? rad_report($iid) : null;
    $ss = $it['sch_status'];
    [$sl, $sc] = $ss ? ($sstat[$ss] ?? [$ss, 'cp-info']) : ['Belum dijadwalkan', 'cp-off'];
    $final = $it['report_status'] === 'final';
?>
<div class="card">
  <h2><?= e($it['test_name']) ?> <span class="muted">(<?= e((string) $it['modality']) ?>)</span>
    <span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span>
    <?php if ($it['report_status']): [$rl, $rc] = $rstat[$it['report_status']] ?? [$it['report_status'], 'cp-info']; ?><span class="cp-badge <?= e($rc) ?>">Laporan <?= e($rl) ?></span><?php endif; ?></h2>
  <?php if ($it['instruction']): ?><p><span class="muted">Catatan dokter:</span> <?= e((string) $it['instruction']) ?></p><?php endif; ?>
  <?php if ($it['sch_at']): ?><p><span class="muted">Jadwal:</span> <?= e(date('d/m/Y H:i', strtotime($it['sch_at']))) ?></p><?php endif; ?>

  <?php if ($active && !$ss && can('radiology.create')): ?>
    <form method="post" action="<?= e(url('modules/radiology/order.php?order_id=' . $orderId)) ?>" class="toolbar">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="item_id" value="<?= $iid ?>"><input type="hidden" name="do" value="schedule">
      <input type="datetime-local" name="scheduled_at" required value="<?= e(date('Y-m-d\TH:i')) ?>">
      <button type="submit" class="btn btn-primary">📅 Jadwalkan</button>
    </form>
  <?php endif; ?>

  <?php if ($active && $ss === 'scheduled' && can('radiology.update')): ?>
    <div class="toolbar">
      <form method="post" class="inline-form" action="<?= e(url('modules/radiology/order.php?order_id=' . $orderId)) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="item_id" value="<?= $iid ?>"><input type="hidden" name="do" value="start"><button type="submit" class="btn btn-primary">▶ Mulai Pemeriksaan</button></form>
      <form method="post" class="inline-form" action="<?= e(url('modules/radiology/order.php?order_id=' . $orderId)) ?>" onsubmit="return confirm('Batalkan jadwal ini?');"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="item_id" value="<?= $iid ?>"><input type="hidden" name="do" value="cancel">
        <input type="text" name="reason" maxlength="300" placeholder="Alasan pembatalan" required style="width:180px"><button type="submit" class="btn btn-outline">✖ Batalkan</button></form>
    </div>
  <?php endif; ?>

  <?php if ($images): ?>
    <div>
      <?php foreach ($images as $im): ?>
        <a href="<?= e(url('modules/radiology/image.php?id=' . (int) $im['image_id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('modules/radiology/image.php?id=' . (int) $im['image_id'])) ?>" alt="Gambar radiologi" style="max-width:160px;max-height:160px;margin:4px;border:1px solid #cbd5e1"></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($active && in_array($ss, ['in_progress', 'completed'], true) && !$final && can('radiology.create') && count($images) < RAD_MAX_IMAGES): ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('modules/radiology/order.php?order_id=' . $orderId)) ?>" class="toolbar">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="item_id" value="<?= $iid ?>"><input type="hidden" name="do" value="upload">
      <input type="file" name="image" accept="image/jpeg,image/png" required>
      <button type="submit" class="btn btn-outline">⬆ Unggah Gambar (JPG/PNG, maks. 5 MB)</button>
    </form>
  <?php endif; ?>

  <?php if ($active && $ss === 'in_progress' && can('radiology.update')): ?>
    <form method="post" class="inline-form" action="<?= e(url('modules/radiology/order.php?order_id=' . $orderId)) ?>" onsubmit="return confirm('Selesaikan pemeriksaan? Pastikan semua gambar sudah diunggah.');"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $orderId ?>"><input type="hidden" name="item_id" value="<?= $iid ?>"><input type="hidden" name="do" value="complete"><button type="submit" class="btn btn-primary">✔ Selesaikan Pemeriksaan</button></form>
  <?php endif; ?>

  <?php if ($active && $ss === 'completed' && !$final && $dokter): ?>
    <a class="btn btn-primary" href="<?= e(url('modules/radiology/report.php?item_id=' . $iid)) ?>">📝 <?= $rep ? 'Lanjutkan Hasil Baca' : 'Tulis Hasil Baca' ?></a>
  <?php endif; ?>

  <?php if ($rep && ($final || can('radiology.update'))): ?>
    <div class="cp-info-box">
      <div><span class="muted">Temuan / Interpretasi</span><br><?= $rep['interpretation'] ? nl2br(e((string) $rep['interpretation'])) : '&mdash;' ?></div>
      <div><span class="muted">Kesimpulan</span><br><?= $rep['conclusion'] ? nl2br(e((string) $rep['conclusion'])) : '&mdash;' ?></div>
      <div><span class="muted">Dokter pembaca</span><br><?= e((string) $rep['doctor_name']) ?></div>
    </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$items): ?><div class="card"><div class="muted">Order ini tidak memiliki item radiologi.</div></div><?php endif; ?>
<?php require APP_ROOT . '/includes/layout_footer.php'; ?>