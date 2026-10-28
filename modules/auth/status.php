<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/users.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/auth/index.php');
}
require_permission('auth.update');
csrf_require();

$userId = (int) ($_POST['user_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$user   = $userId > 0 ? usr_find($userId) : null;

if ($user === null) {
    flash_set('error', 'Pengguna tidak ditemukan.');
    redirect('modules/auth/index.php');
}

$err = usr_set_status($userId, $status, (int) current_user()['id']);
if ($err === null) {
    audit_log(($status === 'active' ? 'Aktifkan' : 'Nonaktifkan') . ' pengguna ' . $user['username'], 'users', $userId);
    flash_set('success', 'Status ' . $user['username'] . ' menjadi ' . (usr_status_labels()[$status] ?? $status) . '.');
} else {
    flash_set('error', $err);
}
redirect('modules/auth/index.php');