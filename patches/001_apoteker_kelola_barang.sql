-- Apoteker perlu mengelola Barang & Obat (tambah/ubah), bukan hanya melihat.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
  FROM roles r
  JOIN permissions p ON p.permission_code IN ('inventory.create', 'inventory.update')
 WHERE r.role_name = 'Apoteker';
