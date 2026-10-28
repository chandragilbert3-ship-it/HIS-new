<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/inpatient.php';

require_permission('inpatient.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    require_permission('inpatient.admit');
    $bedId = (int) ($_POST['bed_id'] ?? 0);
    $new   = (string) ($_POST['new_status'] ?? '');
    $err   = $bedId > 0 ? ipd_set_bed_status($bedId, $new) : 'Tempat tidur tidak valid.';
    if ($err === null) {
        audit_log('Ubah status tempat tidur #' . $bedId . ' menjadi ' . $new, 'beds', $bedId);
        flash_set('success', 'Status tempat tidur diperbarui.');
    } else {
        flash_set('error', $err);
    }
    redirect('modules/inpatient/index.php');
}

$cases   = ipd_active_cases();
$pending = can('inpatient.admit') ? ipd_pending_orders() : [];
$beds    = ipd_beds_overview();
$stat    = ipd_status_labels();
$bstat   = ipd_bed_status_labels();

$count = ['available' => 0, 'occupied' => 0, 'cleaning' => 0, 'maintenance' => 0];
foreach ($beds as $b) {
    if ((int) $b['is_active'] === 1 && isset($count[$b['status']])) {
        $count[$b['status']]++;
    }
}

$pageTitle    = 'Rawat Inap';
$activeModule = 'inpatient';
$breadcrumb   = 'Modul 5. Rawat Inap';

require APP_ROOT . '/includes/layout_header.php';
?>

<?php if ($pending): ?>
<div class="card">
  <h2>Order Admisi Menunggu</h2>
  <div class="table-wrap"><table>
    <tr><th>Order</th><th>Pasien</th><th>Dokter Pemesan</th><th>Waktu</th><th>Alasan Klinis</th><th>Aksi</th></tr>
    <?php foreach ($pending as $o): ?>
      <tr>
        <td>#<?= (int) $o['order_id'] ?><?= $o['priority'] === 'cito' ? ' <span class="cp-badge cp-off">CITO</span>' : '' ?></td>
        <td><?= e($o['nama_pasien']) ?><br><code class="k"><?= e($o['no_rm']) ?></code></td>
        <td><?= e((string) $o['doctor_name']) ?></td>
        <td><?= e(date('d/m H:i', strtotime($o['ordered_at']))) ?></td>
        <td><?= $o['clinical_reason'] ? e((string) $o['clinical_reason']) : '&mdash;' ?></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/inpatient/admit.php?order_id=' . (int) $o['order_id'])) ?>">🛏 Proses Admisi</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<div class="card">
  <h2>Pasien Rawat Inap</h2>
  <div class="table-wrap"><table>
    <tr><th>No. Rawat Inap</th><th>Pasien</th><th>Kamar / Bed</th><th>DPJP</th><th>Masuk</th><th>Alergi</th><th>Status</th><th>Aksi</th></tr>
    <?php if (!$cases): ?><tr><td colspan="8" class="empty">Tidak ada pasien rawat inap.</td></tr><?php endif; ?>
    <?php foreach ($cases as $c): [$sl, $sc] = $stat[$c['status']] ?? [$c['status'], 'cp-info']; ?>
      <tr>
        <td><b><?= e($c['no_rawat_inap']) ?></b></td>
        <td><?= e($c['nama_pasien']) ?><br><code class="k"><?= e($c['no_rm']) ?></code></td>
        <td><?= e($c['unit_name']) ?> &middot; <?= e($c['room_name']) ?> / <?= e($c['bed_no']) ?></td>
        <td><?= e((string) $c['dpjp_name']) ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime($c['admitted_at']))) ?></td>
        <td><?= (int) $c['jml_alergi'] > 0 ? '<span class="cp-badge cp-warn">⚠ ' . (int) $c['jml_alergi'] . '</span>' : '<span class="muted">&mdash;</span>' ?></td>
        <td><span class="cp-badge <?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="aksi"><a class="action-link" href="<?= e(url('modules/inpatient/case.php?id=' . (int) $c['inpatient_id'])) ?>">📄 Buka</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Tempat Tidur</h2>
  <div class="table-wrap"><table>
    <tr><th>Unit</th><th>Kamar</th><th>Kelas</th><th>Bed</th><th>Status</th><th>Pasien</th><th>Aksi</th></tr>
    <?php if (!$beds): ?><tr><td colspan="7" class="empty">Belum ada tempat tidur. Tambahkan lewat Kelola Kamar &amp; Bed.</td></tr><?php endif; ?>
    <?php foreach ($beds as $b): [$bl, $bc] = $bstat[$b['status']] ?? [$b['status'], 'cp-info']; ?>
      <tr>
        <td><?= e($b['unit_name']) ?></td>
        <td><?= e($b['room_name']) ?><?= (int) $b['is_active'] === 1 ? '' : ' <span class="cp-badge cp-off">nonaktif</span>' ?></td>
        <td><?= e((string) $b['room_class']) ?></td>
        <td><?= e($b['bed_no']) ?></td>
        <td><span class="cp-badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
        <td><?php if ($b['inpatient_id']): ?><a href="<?= e(url('modules/inpatient/case.php?id=' . (int) $b['inpatient_id'])) ?>"><?= e((string) $b['nama_pasien']) ?></a><?php else: ?>&mdash;<?php endif; ?></td>
        <td class="aksi">
          <?php if (can('inpatient.admit')):
            $moves = ['cleaning' => ['available', '✔ Siap Dipakai'], 'available' => ['maintenance', '🔧 Perbaikan'], 'maintenance' => ['available', '✔ Selesai Perbaikan']];
            if (isset($moves[$b['status']])): [$to, $lbl] = $moves[$b['status']]; ?>
              <form method="post" class="inline-form" action="<?= e(url('modules/inpatient/index.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="bed_id" value="<?= (int) $b['bed_id'] ?>"><input type="hidden" name="new_status" value="<?= e($to) ?>">
                <button type="submit" class="btn btn-outline"><?= e($lbl) ?></button>
              </form>
            <?php endif; endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>