-- Tarif untuk tes laboratorium, radiologi, dan obat agar bisa ditagihkan (harga contoh untuk demo, ubah di Master Data > Tarif).
INSERT INTO master_tariffs (service_code, service_name, amount, is_active)
SELECT CONCAT('LAB-', LPAD(t.lab_test_id, 2, '0')), CONCAT('Lab: ', t.test_name), 60000.00, 1
  FROM master_lab_tests t
 WHERE NOT EXISTS (SELECT 1 FROM master_tariffs m WHERE m.service_code = CONCAT('LAB-', LPAD(t.lab_test_id, 2, '0')));
INSERT INTO master_tariffs (service_code, service_name, amount, is_active)
SELECT CONCAT('RAD-', LPAD(t.rad_test_id, 2, '0')), CONCAT('Radiologi: ', t.test_name),
       CASE t.modality WHEN 'X-Ray' THEN 150000.00 WHEN 'USG' THEN 250000.00 WHEN 'CT Scan' THEN 1200000.00 ELSE 200000.00 END, 1
  FROM master_rad_tests t
 WHERE NOT EXISTS (SELECT 1 FROM master_tariffs m WHERE m.service_code = CONCAT('RAD-', LPAD(t.rad_test_id, 2, '0')));
INSERT INTO master_tariffs (service_code, service_name, amount, is_active)
SELECT CONCAT('OBAT-', LPAD(d.drug_id, 2, '0')), CONCAT('Obat: ', d.drug_name, ' ', IFNULL(d.strength, '')), 3000.00, 1
  FROM master_drugs d
 WHERE NOT EXISTS (SELECT 1 FROM master_tariffs m WHERE m.service_code = CONCAT('OBAT-', LPAD(d.drug_id, 2, '0')));
