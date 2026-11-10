-- Akun + data dokter untuk setiap poliklinik yang belum punya dokter, dan jadwal praktik Senin-Minggu agar appointment/check-in bisa diuji kapan saja.
INSERT IGNORE INTO users (username, password_hash, nama, status) VALUES
('dokter.umum', '$2y$10$VrGj9K.TqaH.GoZza6jHJe/BSRqJyNqMemqXdyml8AYqg./Vv4q86', 'dr. Siti Rahma', 'active'),
('dokter.anak', '$2y$10$VrGj9K.TqaH.GoZza6jHJe/BSRqJyNqMemqXdyml8AYqg./Vv4q86', 'dr. Maya Anggraini, Sp.A', 'active'),
('dokter.gigi', '$2y$10$VrGj9K.TqaH.GoZza6jHJe/BSRqJyNqMemqXdyml8AYqg./Vv4q86', 'drg. Bagas Pratama', 'active'),
('dokter.igd', '$2y$10$VrGj9K.TqaH.GoZza6jHJe/BSRqJyNqMemqXdyml8AYqg./Vv4q86', 'dr. Fajar Nugroho, Sp.EM', 'active');
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.user_id, r.role_id FROM users u JOIN roles r ON r.role_name = 'Dokter'
 WHERE u.username IN ('dokter.umum', 'dokter.anak', 'dokter.gigi', 'dokter.igd');
INSERT IGNORE INTO master_doctors (user_id, doctor_name, specialty, is_active)
SELECT u.user_id, u.nama,
       CASE u.username WHEN 'dokter.umum' THEN 'Dokter Umum' WHEN 'dokter.anak' THEN 'Anak' WHEN 'dokter.gigi' THEN 'Gigi' ELSE 'Emergency' END,
       1
  FROM users u
 WHERE u.username IN ('dokter.umum', 'dokter.anak', 'dokter.gigi', 'dokter.igd');
INSERT INTO master_schedules (doctor_id, polyclinic_id, day_of_week, start_time, end_time, quota, filled)
SELECT d.doctor_id, m.polyclinic_id, h.hari, '08:00:00', '16:00:00', 30, 0
  FROM master_doctors d
  JOIN users u ON u.user_id = d.user_id
  JOIN (SELECT 'dokter' AS uname, 2 AS polyclinic_id
        UNION ALL SELECT 'dokter2', 5
        UNION ALL SELECT 'dokter.umum', 1
        UNION ALL SELECT 'dokter.anak', 3
        UNION ALL SELECT 'dokter.gigi', 4
        UNION ALL SELECT 'dokter.igd', 6) m ON m.uname = u.username
  JOIN (SELECT 'Senin' AS hari UNION ALL SELECT 'Selasa' UNION ALL SELECT 'Rabu' UNION ALL SELECT 'Kamis'
        UNION ALL SELECT 'Jumat' UNION ALL SELECT 'Sabtu' UNION ALL SELECT 'Minggu') h
 WHERE NOT EXISTS (SELECT 1 FROM master_schedules s
                    WHERE s.doctor_id = d.doctor_id AND s.polyclinic_id = m.polyclinic_id AND s.day_of_week = h.hari);
