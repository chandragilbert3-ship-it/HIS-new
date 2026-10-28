<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once APP_ROOT . '/includes/radiology.php';

// Gambar hanya bisa dilihat oleh pengguna dengan izin radiologi atau EMR.
if (!can('emr.view')) {
    require_permission('radiology.view');
}

$id   = (int) ($_GET['id'] ?? 0);
$img  = $id > 0 ? rad_image_for_view($id) : null;
$path = $img ? rad_image_path((string) $img['image_uri']) : null;
if ($img === null || $path === null) {
    http_response_code(404);
    exit('Gambar tidak ditemukan.');
}

audit_log('Lihat gambar radiologi #' . $id, 'rad_images', $id, (int) $img['visit_id']);

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
exit;