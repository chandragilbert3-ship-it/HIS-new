<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/crud.php';

require_permission('masterdata.view');

$q       = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where  = '';
$params = [];
if ($q !== '') {
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $where  = ' WHERE (a.activity LIKE :q1 OR u.username LIKE :q2)';
    $params = [':q1' => $like, ':q2' => $like];
}

$from = ' FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id' . $where;

$count = db()->prepare('SELECT COUNT(*)' . $from);
foreach ($params as $k => $v) {
    $count->bindValue($k, $v);
}
$count->execute();
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

$stmt = db()->prepare(
    'SELECT a.audit_id, a.created_at, u.username, a.activity, a.table_name' . $from
    . ' ORDER BY a.audit_id DESC LIMIT :lim OFFSET :off'
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$pageTitle    = 'Audit Trail';
$activeModule = 'masterdata';
$breadcrumb   = 'Modul 16. Master Data & Konfigurasi › Audit Trail';

require APP_ROOT . '/includes/layout_header.php';
?>

<?= masterdata_subnav('audit') ?>

<div class="card">
  <h2>Audit Trail (<?= e(number_format($total, 0, ',', '.')) ?> catatan)</h2>

  <form method="get" action="<?= e(url('modules/masterdata/audit.php')) ?>" class="toolbar">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari aktivitas atau username...">
    <button type="submit" class="btn btn-outline">Cari</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-outline" href="<?= e(url('modules/masterdata/audit.php')) ?>">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table>
      <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Tabel</th></tr>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="empty">Belum ada catatan.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e(date('d-m-Y H:i:s', (int) strtotime((string) $r['created_at']))) ?></td>
          <td><?= $r['username'] !== null ? e($r['username']) : '<span class="muted">&mdash;</span>' ?></td>
          <td><?= e($r['activity']) ?></td>
          <td><?= $r['table_name'] !== null ? '<code class="k">' . e($r['table_name']) . '</code>' : '<span class="muted">&mdash;</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?= crud_pagination_html(url('modules/masterdata/audit.php'), ['q' => $q], $page, $pages) ?>
</div>

<?php require APP_ROOT . '/includes/layout_footer.php'; ?>