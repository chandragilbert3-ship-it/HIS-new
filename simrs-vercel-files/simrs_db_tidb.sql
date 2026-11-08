-- SIMRS Rumah Sakit Dunia Indah - versi untuk TiDB Cloud / MySQL online
-- Dibuat otomatis dari simrs_db.sql: PRIMARY KEY, INDEX dan AUTO_INCREMENT
-- dipindah ke dalam CREATE TABLE (TiDB tidak bisa menambah AUTO_INCREMENT lewat ALTER).

CREATE DATABASE IF NOT EXISTS `simrs_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `simrs_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2026 at 06:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `simrs_db`
--
CREATE DATABASE IF NOT EXISTS `simrs_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `simrs_db`;

-- --------------------------------------------------------

--
-- Table structure for table `adjustment_requests`
--

CREATE TABLE IF NOT EXISTS `adjustment_requests` (
  `adjustment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`adjustment_id`),
  KEY `idx_adj_invoice` (`invoice_id`),
  KEY `idx_adj_req` (`requested_by`),
  KEY `idx_adj_app` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE IF NOT EXISTS `appointments` (
  `appointment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `booking_code` varchar(30) NOT NULL,
  `status` enum('booked','checked_in','cancelled','no_show') NOT NULL DEFAULT 'booked',
  PRIMARY KEY (`appointment_id`),
  UNIQUE KEY `uq_appt_booking` (`booking_code`),
  KEY `idx_appt_patient` (`patient_id`),
  KEY `idx_appt_schedule` (`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `audit_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `visit_id` int(10) UNSIGNED DEFAULT NULL,
  `activity` varchar(255) NOT NULL,
  `table_name` varchar(64) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_visit` (`visit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT IGNORE INTO `audit_logs` (`audit_id`, `user_id`, `visit_id`, `activity`, `table_name`, `reference_id`, `created_at`) VALUES
(1, NULL, NULL, 'Login gagal untuk username: admin.it', NULL, NULL, '2026-10-28 17:58:49'),
(2, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 17:58:59'),
(3, 1, NULL, 'Logout', 'users', 1, '2026-10-28 17:59:05'),
(4, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 17:59:39'),
(5, 2, NULL, 'Logout', 'users', 2, '2026-10-28 17:59:53'),
(6, 3, NULL, 'Login berhasil', 'users', 3, '2026-10-28 17:59:59'),
(7, 3, NULL, 'Logout', 'users', 3, '2026-10-28 18:00:11'),
(8, NULL, NULL, 'Login gagal untuk username: <b>x</b>', NULL, NULL, '2026-10-28 18:00:15'),
(9, NULL, NULL, 'Login gagal untuk username: <b>x</b>', NULL, NULL, '2026-10-28 18:00:26'),
(10, NULL, NULL, 'Login gagal untuk username: <b>x</b>', NULL, NULL, '2026-10-28 18:00:28'),
(11, NULL, NULL, 'Login gagal untuk username: <b>x</b>', NULL, NULL, '2026-10-28 18:00:30'),
(12, NULL, NULL, 'Login gagal untuk username: <b>x</b>', NULL, NULL, '2026-10-28 18:00:32'),
(13, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 18:09:25'),
(14, 1, NULL, 'Logout', 'users', 1, '2026-10-28 18:09:30'),
(15, NULL, NULL, 'Login gagal untuk username: admin.it', NULL, NULL, '2026-10-28 18:10:24'),
(16, NULL, NULL, 'Login gagal untuk username: admin.it', NULL, NULL, '2026-10-28 18:10:27'),
(17, NULL, NULL, 'Login gagal untuk username: admin.it', NULL, NULL, '2026-10-28 18:10:28'),
(18, NULL, NULL, 'Login gagal untuk username: admin.it', NULL, NULL, '2026-10-28 18:10:30'),
(19, NULL, NULL, 'Login gagal untuk username: admin.it', NULL, NULL, '2026-10-28 18:10:31'),
(20, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 18:19:17'),
(21, 1, NULL, 'Logout', 'users', 1, '2026-10-28 18:19:38'),
(22, NULL, NULL, 'Login gagal untuk username: registrasi', NULL, NULL, '2026-10-28 18:19:57'),
(23, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 18:20:06'),
(24, 2, NULL, 'Logout', 'users', 2, '2026-10-28 18:20:22'),
(25, NULL, NULL, 'Login gagal untuk username: lab', NULL, NULL, '2026-10-28 18:20:33'),
(26, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-28 18:20:41'),
(27, 6, NULL, 'Logout', 'users', 6, '2026-10-28 18:20:47'),
(28, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 18:22:08'),
(29, 2, NULL, 'Akses ditolak: laboratory.view', NULL, NULL, '2026-10-28 18:22:11'),
(30, 2, NULL, 'Logout', 'users', 2, '2026-10-28 18:22:30'),
(31, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-28 18:22:36'),
(32, 6, NULL, 'Logout', 'users', 6, '2026-10-28 18:22:53'),
(33, 9, NULL, 'Login berhasil', 'users', 9, '2026-10-28 18:23:00'),
(34, 9, NULL, 'Logout', 'users', 9, '2026-10-28 18:23:48'),
(35, 9, NULL, 'Login berhasil', 'users', 9, '2026-10-28 18:24:15'),
(36, 9, NULL, 'Logout', 'users', 9, '2026-10-28 18:37:58'),
(37, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 18:38:05'),
(38, 1, NULL, 'Ubah Master Dokter #2', 'master_doctors', 2, '2026-10-28 18:41:30'),
(39, 1, NULL, 'Tambah Master Poliklinik #4', 'master_polyclinics', 4, '2026-10-28 18:42:13'),
(40, 1, NULL, 'Tambah Master ICD-10 #K35.8', 'master_icd10', NULL, '2026-10-28 18:45:12'),
(41, 1, NULL, 'Tambah Master Jadwal Dokter #1', 'master_schedules', 1, '2026-10-28 18:45:56'),
(42, 1, NULL, 'Tambah Master Tarif #5', 'master_tariffs', 5, '2026-10-28 18:46:53'),
(43, 1, NULL, 'Ubah Master Tes Lab #2', 'master_lab_tests', 2, '2026-10-28 18:47:33'),
(44, 1, NULL, 'Ubah Master Tes Lab #4', 'master_lab_tests', 4, '2026-10-28 18:47:59'),
(45, 1, NULL, 'Ubah Master Tes Lab #5', 'master_lab_tests', 5, '2026-10-28 18:48:25'),
(46, 1, NULL, 'Ubah Master Tes Lab #5', 'master_lab_tests', 5, '2026-10-28 18:48:34'),
(47, 1, NULL, 'Ubah Master Tes Lab #5', 'master_lab_tests', 5, '2026-10-28 18:49:20'),
(48, 1, NULL, 'Ubah Master Tes Lab #5', 'master_lab_tests', 5, '2026-10-28 18:49:31'),
(49, 1, NULL, 'Ubah Master Tes Lab #3', 'master_lab_tests', 3, '2026-10-28 18:50:09'),
(50, 1, NULL, 'Logout', 'users', 1, '2026-10-28 18:51:15'),
(51, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 18:51:26'),
(52, 2, NULL, 'Akses ditolak: masterdata.view', NULL, NULL, '2026-10-28 18:51:34'),
(53, 2, NULL, 'Akses ditolak: masterdata.create', NULL, NULL, '2026-10-28 18:51:45'),
(54, 2, NULL, 'Logout', 'users', 2, '2026-10-28 18:53:06'),
(55, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 18:53:18'),
(56, 1, NULL, 'Ubah Master Dokter #2', 'master_doctors', 2, '2026-10-28 18:54:59'),
(57, 1, NULL, 'Tambah Master Poliklinik #5', 'master_polyclinics', 5, '2026-10-28 18:55:27'),
(58, 1, NULL, 'Ubah Master Poliklinik #5', 'master_polyclinics', 5, '2026-10-28 18:55:39'),
(59, 1, NULL, 'Logout', 'users', 1, '2026-10-28 18:56:11'),
(60, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 18:56:24'),
(61, 2, NULL, 'Logout', 'users', 2, '2026-10-28 18:56:33'),
(62, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-28 18:56:44'),
(63, 6, NULL, 'Akses ditolak: masterdata.view', NULL, NULL, '2026-10-28 18:57:41'),
(64, 6, NULL, 'Logout', 'users', 6, '2026-10-28 18:57:47'),
(65, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 19:14:57'),
(66, 2, NULL, 'Registrasi pasien baru', 'patients', 1, '2026-10-28 19:17:38'),
(67, 2, NULL, 'Lihat data pasien', 'patients', 1, '2026-10-28 19:17:38'),
(68, 2, NULL, 'Registrasi pasien baru', 'patients', 2, '2026-10-28 19:19:02'),
(69, 2, NULL, 'Lihat data pasien', 'patients', 2, '2026-10-28 19:19:02'),
(70, 2, NULL, 'Lihat data pasien', 'patients', 1, '2026-10-28 19:19:09'),
(71, 2, NULL, 'Tambah alergi pasien: Penisilin', 'patient_allergies', 1, '2026-10-28 19:20:14'),
(72, 2, NULL, 'Lihat data pasien', 'patients', 1, '2026-10-28 19:20:14'),
(73, 2, NULL, 'Tambah asuransi pasien', 'patient_insurance', 1, '2026-10-28 19:20:50'),
(74, 2, NULL, 'Lihat data pasien', 'patients', 1, '2026-10-28 19:20:50'),
(75, 2, 1, 'Registrasi kunjungan, antrean A-001', 'visits', 1, '2026-10-28 19:22:13'),
(76, 2, 2, 'Registrasi kunjungan, antrean A-002', 'visits', 2, '2026-10-28 19:22:50'),
(77, 2, 3, 'Registrasi kunjungan, antrean A-003', 'visits', 3, '2026-10-28 19:23:08'),
(78, 2, NULL, 'Lihat data pasien', 'patients', 1, '2026-10-28 19:23:21'),
(79, 2, 2, 'Batalkan kunjungan #2', 'visits', 2, '2026-10-28 19:23:26'),
(80, 2, NULL, 'Lihat data pasien', 'patients', 1, '2026-10-28 19:23:26'),
(81, 2, NULL, 'Registrasi pasien baru', 'patients', 3, '2026-10-28 19:24:40'),
(82, 2, NULL, 'Lihat data pasien', 'patients', 3, '2026-10-28 19:24:40'),
(83, 2, NULL, 'Lihat data pasien', 'patients', 3, '2026-10-28 19:24:51'),
(84, 2, NULL, 'Logout', 'users', 2, '2026-10-28 19:25:36'),
(85, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 19:25:45'),
(86, 1, NULL, 'Logout', 'users', 1, '2026-10-28 19:30:40'),
(87, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 19:30:56'),
(88, 2, NULL, 'Logout', 'users', 2, '2026-10-28 19:33:07'),
(89, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-28 19:33:16'),
(90, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-28 20:18:48'),
(91, 6, NULL, 'Logout', 'users', 6, '2026-10-28 20:19:08'),
(92, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-28 20:19:16'),
(93, 5, NULL, 'Login berhasil', 'users', 5, '2026-10-28 21:46:32'),
(94, 5, NULL, 'Logout', 'users', 5, '2026-10-28 22:08:05'),
(95, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-28 22:08:15'),
(96, 6, NULL, 'Logout', 'users', 6, '2026-10-28 22:37:46'),
(97, 8, NULL, 'Login berhasil', 'users', 8, '2026-10-28 22:38:01'),
(98, 8, NULL, 'Logout', 'users', 8, '2026-10-28 22:58:17'),
(99, 10, NULL, 'Login berhasil', 'users', 10, '2026-10-28 22:58:34'),
(100, 10, NULL, 'Logout', 'users', 10, '2026-10-28 23:06:41'),
(101, 9, NULL, 'Login berhasil', 'users', 9, '2026-10-28 23:06:55'),
(102, 9, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:06:55'),
(103, 9, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:07:17'),
(104, 9, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:07:24'),
(105, 9, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:07:30'),
(106, 9, NULL, 'Logout', 'users', 9, '2026-10-28 23:07:32'),
(107, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-28 23:07:39'),
(108, 6, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:07:39'),
(109, 6, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:35:18'),
(110, 6, NULL, 'Logout', 'users', 6, '2026-10-28 23:35:23'),
(111, 11, NULL, 'Login berhasil', 'users', 11, '2026-10-28 23:35:39'),
(112, 11, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:35:39'),
(113, 11, NULL, 'Buka dashboard laporan 2026-09-29 s/d 2026-10-28', 'visits', NULL, '2026-10-28 23:35:42'),
(114, 11, NULL, 'Logout', 'users', 11, '2026-10-28 23:35:51'),
(115, 5, NULL, 'Login berhasil', 'users', 5, '2026-10-28 23:36:01'),
(116, 5, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:36:01'),
(117, 5, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:36:12'),
(118, 5, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:37:47'),
(119, 5, NULL, 'Logout', 'users', 5, '2026-10-28 23:37:54'),
(120, 7, NULL, 'Login berhasil', 'users', 7, '2026-10-28 23:38:08'),
(121, 7, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-28 23:38:08'),
(122, 1, NULL, 'Login berhasil', 'users', 1, '2026-10-29 00:13:21'),
(123, 1, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:13:21'),
(124, 1, NULL, 'Buka dashboard laporan 2026-09-30 s/d 2026-10-29', 'visits', NULL, '2026-10-29 00:14:00'),
(125, 1, NULL, 'Buka dashboard laporan 2026-09-30 s/d 2026-10-29', 'visits', NULL, '2026-10-29 00:14:07'),
(126, 1, NULL, 'Buka dashboard laporan 2026-09-30 s/d 2026-10-29', 'visits', NULL, '2026-10-29 00:14:32'),
(127, 1, NULL, 'Logout', 'users', 1, '2026-10-29 00:16:49'),
(128, 2, NULL, 'Login berhasil', 'users', 2, '2026-10-29 00:17:00'),
(129, 2, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:17:00'),
(130, 2, NULL, 'Logout', 'users', 2, '2026-10-29 00:18:01'),
(131, 3, NULL, 'Login berhasil', 'users', 3, '2026-10-29 00:18:12'),
(132, 3, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:18:12'),
(133, 3, NULL, 'Logout', 'users', 3, '2026-10-29 00:19:42'),
(134, 4, NULL, 'Login berhasil', 'users', 4, '2026-10-29 00:19:57'),
(135, 4, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:19:57'),
(136, 4, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:20:12'),
(137, 4, NULL, 'Logout', 'users', 4, '2026-10-29 00:20:21'),
(138, 5, NULL, 'Login berhasil', 'users', 5, '2026-10-29 00:20:34'),
(139, 5, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:20:34'),
(140, 5, NULL, 'Logout', 'users', 5, '2026-10-29 00:21:42'),
(141, 6, NULL, 'Login berhasil', 'users', 6, '2026-10-29 00:21:51'),
(142, 6, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:21:51'),
(143, 6, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:22:23'),
(144, 6, NULL, 'Logout', 'users', 6, '2026-10-29 00:22:29'),
(145, 7, NULL, 'Login berhasil', 'users', 7, '2026-10-29 00:22:38'),
(146, 7, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:22:38'),
(147, 7, NULL, 'Logout', 'users', 7, '2026-10-29 00:23:11'),
(148, 8, NULL, 'Login berhasil', 'users', 8, '2026-10-29 00:23:43'),
(149, 8, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:23:43'),
(150, 8, NULL, 'Logout', 'users', 8, '2026-10-29 00:24:30'),
(151, 9, NULL, 'Login berhasil', 'users', 9, '2026-10-29 00:24:43'),
(152, 9, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:24:43'),
(153, 9, NULL, 'Logout', 'users', 9, '2026-10-29 00:25:17'),
(154, 10, NULL, 'Login berhasil', 'users', 10, '2026-10-29 00:25:33'),
(155, 10, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:25:33'),
(156, 10, NULL, 'Logout', 'users', 10, '2026-10-29 00:26:04'),
(157, 11, NULL, 'Login berhasil', 'users', 11, '2026-10-29 00:26:13'),
(158, 11, NULL, 'Buka dashboard', 'dashboard', NULL, '2026-10-29 00:26:13'),
(159, 11, NULL, 'Buka dashboard laporan 2026-09-30 s/d 2026-10-29', 'visits', NULL, '2026-10-29 00:26:40');

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE IF NOT EXISTS `beds` (
  `bed_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_id` int(10) UNSIGNED NOT NULL,
  `bed_no` varchar(20) NOT NULL,
  `status` enum('available','occupied','cleaning','maintenance') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`bed_id`),
  UNIQUE KEY `uq_bed_room_no` (`room_id`,`bed_no`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `beds`
--

INSERT IGNORE INTO `beds` (`bed_id`, `room_id`, `bed_no`, `status`) VALUES
(1, 4, 'A', 'available'),
(2, 4, 'B', 'available'),
(3, 5, 'A', 'available'),
(4, 5, 'B', 'available'),
(8, 6, 'A', 'available'),
(9, 7, 'A', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `bed_transfer_history`
--

CREATE TABLE IF NOT EXISTS `bed_transfer_history` (
  `transfer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inpatient_id` int(10) UNSIGNED NOT NULL,
  `from_bed_id` int(10) UNSIGNED NOT NULL,
  `to_bed_id` int(10) UNSIGNED NOT NULL,
  `transferred_by` int(10) UNSIGNED NOT NULL,
  `transferred_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transfer_id`),
  KEY `idx_bth_inpatient` (`inpatient_id`),
  KEY `idx_bth_from` (`from_bed_id`),
  KEY `idx_bth_to` (`to_bed_id`),
  KEY `idx_bth_by` (`transferred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `benefit_plans`
--

CREATE TABLE IF NOT EXISTS `benefit_plans` (
  `benefit_plan_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` int(10) UNSIGNED NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `coverage_rule` text DEFAULT NULL,
  PRIMARY KEY (`benefit_plan_id`),
  KEY `idx_bplan_contract` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_invoices`
--

CREATE TABLE IF NOT EXISTS `billing_invoices` (
  `invoice_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `payer_id` int(10) UNSIGNED NOT NULL,
  `invoice_no` varchar(30) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payer_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `patient_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `invoice_status` enum('draft','issued','cancelled') NOT NULL DEFAULT 'draft',
  `billing_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `uq_invoice_no` (`invoice_no`),
  KEY `idx_inv_visit` (`visit_id`),
  KEY `idx_inv_payer` (`payer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_invoice_items`
--

CREATE TABLE IF NOT EXISTS `billing_invoice_items` (
  `invoice_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `source_module` varchar(30) NOT NULL,
  `source_item_id` int(10) UNSIGNED NOT NULL,
  `tariff_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`invoice_item_id`),
  KEY `idx_invitem_invoice` (`invoice_id`),
  KEY `idx_invitem_tariff` (`tariff_id`),
  KEY `idx_invitem_source` (`source_module`,`source_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE IF NOT EXISTS `claims` (
  `claim_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `payer_id` int(10) UNSIGNED NOT NULL,
  `claim_no` varchar(30) NOT NULL,
  `claim_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `claim_status` enum('draft','submitted','verified','paid','rejected') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`claim_id`),
  UNIQUE KEY `uq_claim_no` (`claim_no`),
  KEY `idx_claim_invoice` (`invoice_id`),
  KEY `idx_claim_payer` (`payer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claim_documents`
--

CREATE TABLE IF NOT EXISTS `claim_documents` (
  `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `claim_id` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`document_id`),
  KEY `idx_cdoc_claim` (`claim_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diagnoses`
--

CREATE TABLE IF NOT EXISTS `diagnoses` (
  `diagnosis_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `icd10_code` varchar(10) NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `diagnosis_type` varchar(20) NOT NULL DEFAULT 'primary',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`diagnosis_id`),
  KEY `idx_diag_visit` (`visit_id`),
  KEY `idx_diag_icd` (`icd10_code`),
  KEY `idx_diag_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discharge_orders`
--

CREATE TABLE IF NOT EXISTS `discharge_orders` (
  `discharge_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inpatient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `final_diagnosis` text DEFAULT NULL,
  `condition_on_discharge` varchar(100) DEFAULT NULL,
  `follow_up` text DEFAULT NULL,
  `status` enum('draft','ordered','completed') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`discharge_id`),
  KEY `idx_dis_inpatient` (`inpatient_id`),
  KEY `idx_dis_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispensing_transactions`
--

CREATE TABLE IF NOT EXISTS `dispensing_transactions` (
  `dispense_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_item_id` int(10) UNSIGNED NOT NULL,
  `pharmacist_id` int(10) UNSIGNED NOT NULL,
  `quantity_dispensed` decimal(10,2) NOT NULL,
  `dispensed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`dispense_id`),
  KEY `idx_disp_pitem` (`prescription_item_id`),
  KEY `idx_disp_pharm` (`pharmacist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ed_dispositions`
--

CREATE TABLE IF NOT EXISTS `ed_dispositions` (
  `disposition_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ed_encounter_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `disposition_type` varchar(30) NOT NULL,
  `decision_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`disposition_id`),
  KEY `idx_disp_enc` (`ed_encounter_id`),
  KEY `idx_disp_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ed_encounters`
--

CREATE TABLE IF NOT EXISTS `ed_encounters` (
  `ed_encounter_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `arrival_time` datetime NOT NULL DEFAULT current_timestamp(),
  `arrival_mode` varchar(30) DEFAULT NULL,
  `source_referral` varchar(100) DEFAULT NULL,
  `status` enum('waiting','in_treatment','observation','completed','cancelled') NOT NULL DEFAULT 'waiting',
  PRIMARY KEY (`ed_encounter_id`),
  KEY `idx_edenc_visit` (`visit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ed_observations`
--

CREATE TABLE IF NOT EXISTS `ed_observations` (
  `ed_observation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ed_encounter_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `observation` text NOT NULL,
  `observed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ed_observation_id`),
  KEY `idx_edobs_enc` (`ed_encounter_id`),
  KEY `idx_edobs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ed_triage`
--

CREATE TABLE IF NOT EXISTS `ed_triage` (
  `triage_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ed_encounter_id` int(10) UNSIGNED NOT NULL,
  `nurse_id` int(10) UNSIGNED NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `consciousness` varchar(30) DEFAULT NULL,
  `pain_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `triage_priority` varchar(20) NOT NULL,
  PRIMARY KEY (`triage_id`),
  KEY `idx_triage_enc` (`ed_encounter_id`),
  KEY `idx_triage_nurse` (`nurse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emr_addenda`
--

CREATE TABLE IF NOT EXISTS `emr_addenda` (
  `addendum_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `emr_note_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`addendum_id`),
  KEY `idx_addm_note` (`emr_note_id`),
  KEY `idx_addm_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emr_notes`
--

CREATE TABLE IF NOT EXISTS `emr_notes` (
  `emr_note_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `subjective` text DEFAULT NULL,
  `objective` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `plan` text DEFAULT NULL,
  `status` enum('draft','final') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`emr_note_id`),
  KEY `idx_emr_visit` (`visit_id`),
  KEY `idx_emr_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipts`
--

CREATE TABLE IF NOT EXISTS `goods_receipts` (
  `receipt_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `received_by` int(10) UNSIGNED NOT NULL,
  `source_document` varchar(50) DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receipt_id`),
  KEY `idx_gr_wh` (`warehouse_id`),
  KEY `idx_gr_supplier` (`supplier_id`),
  KEY `idx_gr_user` (`received_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipt_items`
--

CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
  `receipt_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_id` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `batch_no` varchar(50) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  PRIMARY KEY (`receipt_item_id`),
  KEY `idx_gri_receipt` (`receipt_id`),
  KEY `idx_gri_item` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inpatient_admissions`
--

CREATE TABLE IF NOT EXISTS `inpatient_admissions` (
  `inpatient_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `bed_id` int(10) UNSIGNED NOT NULL,
  `dpjp_id` int(10) UNSIGNED NOT NULL,
  `admission_order_id` int(10) UNSIGNED DEFAULT NULL,
  `no_rawat_inap` varchar(30) NOT NULL,
  `status` enum('admitted','discharge_ordered','discharged','cancelled') NOT NULL DEFAULT 'admitted',
  `admitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `discharged_at` datetime DEFAULT NULL,
  PRIMARY KEY (`inpatient_id`),
  UNIQUE KEY `uq_ipadm_no` (`no_rawat_inap`),
  KEY `idx_ipadm_patient` (`patient_id`),
  KEY `idx_ipadm_visit` (`visit_id`),
  KEY `idx_ipadm_bed` (`bed_id`),
  KEY `idx_ipadm_dpjp` (`dpjp_id`),
  KEY `idx_ipadm_order` (`admission_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inpatient_progress_notes`
--

CREATE TABLE IF NOT EXISTS `inpatient_progress_notes` (
  `progress_note_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inpatient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `soap_note` text NOT NULL,
  `note_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`progress_note_id`),
  KEY `idx_ipn_inpatient` (`inpatient_id`),
  KEY `idx_ipn_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurer_receivables`
--

CREATE TABLE IF NOT EXISTS `insurer_receivables` (
  `receivable_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `claim_id` int(10) UNSIGNED NOT NULL,
  `payer_id` int(10) UNSIGNED NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `outstanding_amount` decimal(15,2) NOT NULL,
  `status` enum('open','partial','settled') NOT NULL DEFAULT 'open',
  PRIMARY KEY (`receivable_id`),
  KEY `idx_recv_claim` (`claim_id`),
  KEY `idx_recv_payer` (`payer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integration_logs`
--

CREATE TABLE IF NOT EXISTS `integration_logs` (
  `integration_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED DEFAULT NULL,
  `resource_type` varchar(50) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `response_status` varchar(30) DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`integration_id`),
  KEY `idx_integ_visit` (`visit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE IF NOT EXISTS `inventory_items` (
  `inventory_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_code` varchar(30) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `min_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`inventory_item_id`),
  UNIQUE KEY `uq_invitem_code` (`item_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT IGNORE INTO `inventory_items` (`inventory_item_id`, `item_code`, `item_name`, `category`, `unit`, `min_stock`, `max_stock`) VALUES
(1, 'OBT-001', 'Paracetamol 500 mg', 'Obat', 'tablet', 100.00, 1000.00),
(2, 'OBT-002', 'Amoxicillin 500 mg', 'Obat', 'kapsul', 100.00, 1000.00),
(3, 'OBT-003', 'Ambroxol 30 mg', 'Obat', 'tablet', 50.00, 500.00),
(4, 'OBT-004', 'Omeprazole 20 mg', 'Obat', 'kapsul', 50.00, 500.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_requests`
--

CREATE TABLE IF NOT EXISTS `inventory_requests` (
  `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `requesting_unit` varchar(100) NOT NULL,
  `status` enum('pending','processed','completed','cancelled') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`request_id`),
  KEY `idx_ireq_user` (`requested_by`),
  KEY `idx_ireq_wh` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_request_items`
--

CREATE TABLE IF NOT EXISTS `inventory_request_items` (
  `request_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  PRIMARY KEY (`request_item_id`),
  KEY `idx_ireqi_req` (`request_id`),
  KEY `idx_ireqi_item` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE IF NOT EXISTS `lab_results` (
  `lab_result_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `specimen_id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `lab_test_id` int(10) UNSIGNED NOT NULL,
  `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `result_value` varchar(100) DEFAULT NULL,
  `result_flag` varchar(20) DEFAULT NULL,
  `status` enum('pending','validated','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`lab_result_id`),
  KEY `idx_lres_specimen` (`specimen_id`),
  KEY `idx_lres_item` (`order_item_id`),
  KEY `idx_lres_test` (`lab_test_id`),
  KEY `idx_lres_validator` (`validated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_specimens`
--

CREATE TABLE IF NOT EXISTS `lab_specimens` (
  `specimen_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `specimen_type` varchar(30) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `status` enum('collected','received','rejected','processed') NOT NULL DEFAULT 'collected',
  PRIMARY KEY (`specimen_id`),
  UNIQUE KEY `uq_specimen_barcode` (`barcode`),
  KEY `idx_spec_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_doctors`
--

CREATE TABLE IF NOT EXISTS `master_doctors` (
  `doctor_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `doctor_name` varchar(100) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`doctor_id`),
  UNIQUE KEY `uq_doctor_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_doctors`
--

INSERT IGNORE INTO `master_doctors` (`doctor_id`, `user_id`, `doctor_name`, `specialty`, `is_active`) VALUES
(1, 3, 'dr. Andi Wijaya, Sp.PD', 'Penyakit Dalam', 1),
(2, 4, 'dr. Deta Jevnia Baene, Sp. BS', 'Bedah Saraf', 1),
(3, 7, 'dr. Budi Santoso, Sp.Rad', 'Radiologi', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_drugs`
--

CREATE TABLE IF NOT EXISTS `master_drugs` (
  `drug_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `drug_name` varchar(150) NOT NULL,
  `dosage_form` varchar(50) DEFAULT NULL,
  `strength` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`drug_id`),
  KEY `idx_drug_inventory` (`inventory_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_drugs`
--

INSERT IGNORE INTO `master_drugs` (`drug_id`, `inventory_item_id`, `drug_name`, `dosage_form`, `strength`, `is_active`) VALUES
(1, 1, 'Paracetamol', 'Tablet', '500 mg', 1),
(2, 2, 'Amoxicillin', 'Kapsul', '500 mg', 1),
(3, 3, 'Ambroxol', 'Tablet', '30 mg', 1),
(4, 4, 'Omeprazole', 'Kapsul', '20 mg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_icd10`
--

CREATE TABLE IF NOT EXISTS `master_icd10` (
  `icd10_code` varchar(10) NOT NULL,
  `diagnosis_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`icd10_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_icd10`
--

INSERT IGNORE INTO `master_icd10` (`icd10_code`, `diagnosis_name`, `is_active`) VALUES
('A09', 'Other gastroenteritis and colitis of infectious and unspecified origin', 1),
('B34.9', 'Viral infection, unspecified', 1),
('E11.9', 'Type 2 diabetes mellitus without complications', 1),
('I10', 'Essential (primary) hypertension', 1),
('J06.9', 'Acute upper respiratory infection, unspecified', 1),
('J18.9', 'Pneumonia, unspecified', 1),
('K29.7', 'Gastritis, unspecified', 1),
('K35.8', 'Acute appendicitis, other and unspecified', 1),
('M54.5', 'Low back pain', 1),
('N39.0', 'Urinary tract infection, site not specified', 1),
('R50.9', 'Fever, unspecified', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_lab_tests`
--

CREATE TABLE IF NOT EXISTS `master_lab_tests` (
  `lab_test_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_name` varchar(150) NOT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`lab_test_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_lab_tests`
--

INSERT IGNORE INTO `master_lab_tests` (`lab_test_id`, `test_name`, `unit`, `reference_range`, `is_active`) VALUES
(1, 'Hematologi Rutin', NULL, NULL, 1),
(2, 'Glukosa Darah Sewaktu', 'mg/dL', '<200', 1),
(3, 'Ureum', 'mg/dL', '8–24 mg/dL', 1),
(4, 'Kreatinin', 'mg/dL', '0,7–1,3 mg/dL', 1),
(5, 'SGOT (AST)', 'U/L', '5–40 U/L', 1),
(6, 'Urinalisis', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_polyclinics`
--

CREATE TABLE IF NOT EXISTS `master_polyclinics` (
  `polyclinic_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `polyclinic_name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`polyclinic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_polyclinics`
--

INSERT IGNORE INTO `master_polyclinics` (`polyclinic_id`, `polyclinic_name`, `location`, `is_active`) VALUES
(1, 'Poli Umum', 'Lantai 1', 1),
(2, 'Poli Penyakit Dalam', 'Lantai 1', 1),
(3, 'Poli Anak', 'Lantai 2', 1),
(4, 'Poli Gigi', 'Lantai 2', 1),
(5, 'Bedah Saraf', 'Lantai 6', 1),
(6, 'IGD (Instalasi Gawat Darurat)', 'Lantai 1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_procedures`
--

CREATE TABLE IF NOT EXISTS `master_procedures` (
  `procedure_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `procedure_name` varchar(150) NOT NULL,
  `category` enum('procedure','consultation','admission') NOT NULL DEFAULT 'procedure',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`procedure_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_procedures`
--

INSERT IGNORE INTO `master_procedures` (`procedure_id`, `procedure_name`, `category`, `is_active`) VALUES
(1, 'Pemasangan Infus', 'procedure', 1),
(2, 'Jahit Luka (Hecting)', 'procedure', 1),
(3, 'Nebulisasi', 'procedure', 1),
(4, 'Konsultasi Spesialis', 'consultation', 1),
(5, 'Rawat Inap', 'admission', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_rad_tests`
--

CREATE TABLE IF NOT EXISTS `master_rad_tests` (
  `rad_test_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_name` varchar(150) NOT NULL,
  `modality` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`rad_test_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_rad_tests`
--

INSERT IGNORE INTO `master_rad_tests` (`rad_test_id`, `test_name`, `modality`, `is_active`) VALUES
(1, 'Rontgen Thorax PA', 'X-Ray', 1),
(2, 'Rontgen Abdomen', 'X-Ray', 1),
(3, 'USG Abdomen', 'USG', 1),
(4, 'CT Scan Kepala', 'CT Scan', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_rooms`
--

CREATE TABLE IF NOT EXISTS `master_rooms` (
  `room_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(100) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `room_class` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_rooms`
--

INSERT IGNORE INTO `master_rooms` (`room_id`, `unit_name`, `room_name`, `room_class`, `is_active`) VALUES
(1, 'Melati', 'Melati 3', 'Kelas 1', 1),
(2, 'Mawar', 'Mawar 1', 'Kelas 2', 1),
(3, 'IGD', 'Ruang Observasi IGD', NULL, 1),
(4, 'Melati', 'Melati 101', 'Kelas 1', 1),
(5, 'Melati', 'Melati 102', 'Kelas 2', 1),
(6, 'Mawar', 'Mawar 201', 'VIP', 1),
(7, 'ICU', 'ICU 01', 'ICU', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_schedules`
--

CREATE TABLE IF NOT EXISTS `master_schedules` (
  `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `polyclinic_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `quota` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `filled` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`schedule_id`),
  KEY `idx_sched_doctor` (`doctor_id`),
  KEY `idx_sched_poly` (`polyclinic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_schedules`
--

INSERT IGNORE INTO `master_schedules` (`schedule_id`, `doctor_id`, `polyclinic_id`, `day_of_week`, `start_time`, `end_time`, `quota`, `filled`) VALUES
(1, 1, 2, 'Senin', '08:00:00', '12:00:00', 20, 0);

-- --------------------------------------------------------

--
-- Table structure for table `master_tariffs`
--

CREATE TABLE IF NOT EXISTS `master_tariffs` (
  `tariff_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_code` varchar(50) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`tariff_id`),
  KEY `idx_tariff_code` (`service_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_tariffs`
--

INSERT IGNORE INTO `master_tariffs` (`tariff_id`, `service_code`, `service_name`, `amount`, `is_active`) VALUES
(1, 'ADM-001', 'Biaya Pendaftaran Pasien', 15000.00, 1),
(2, 'KONS-001', 'Konsultasi Dokter Umum', 75000.00, 1),
(3, 'KONS-002', 'Konsultasi Dokter Spesialis', 150000.00, 1),
(4, 'TDK-001', 'Pemasangan Infus', 50000.00, 1),
(5, 'KONS-003', 'Konsultasi Gigi', 100000.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `medication_returns`
--

CREATE TABLE IF NOT EXISTS `medication_returns` (
  `med_return_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dispense_id` int(10) UNSIGNED NOT NULL,
  `quantity_returned` decimal(10,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `returned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`med_return_id`),
  KEY `idx_medret_dispense` (`dispense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_assessments`
--

CREATE TABLE IF NOT EXISTS `nursing_assessments` (
  `assessment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `nurse_id` int(10) UNSIGNED NOT NULL,
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `pain_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `fall_risk` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assessment_id`),
  KEY `idx_nassess_visit` (`visit_id`),
  KEY `idx_nassess_nurse` (`nurse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_care_plans`
--

CREATE TABLE IF NOT EXISTS `nursing_care_plans` (
  `care_plan_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `nurse_id` int(10) UNSIGNED NOT NULL,
  `nursing_problem` text NOT NULL,
  `target` text DEFAULT NULL,
  `status` enum('active','achieved','discontinued') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`care_plan_id`),
  KEY `idx_ncp_visit` (`visit_id`),
  KEY `idx_ncp_nurse` (`nurse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_handovers`
--

CREATE TABLE IF NOT EXISTS `nursing_handovers` (
  `handover_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `from_nurse_id` int(10) UNSIGNED NOT NULL,
  `to_nurse_id` int(10) UNSIGNED NOT NULL,
  `summary` text NOT NULL,
  `handover_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`handover_id`),
  KEY `idx_nho_visit` (`visit_id`),
  KEY `idx_nho_from` (`from_nurse_id`),
  KEY `idx_nho_to` (`to_nurse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_interventions`
--

CREATE TABLE IF NOT EXISTS `nursing_interventions` (
  `intervention_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `care_plan_id` int(10) UNSIGNED NOT NULL,
  `nurse_id` int(10) UNSIGNED NOT NULL,
  `intervention` text NOT NULL,
  `response` text DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`intervention_id`),
  KEY `idx_nint_plan` (`care_plan_id`),
  KEY `idx_nint_nurse` (`nurse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nursing_observations`
--

CREATE TABLE IF NOT EXISTS `nursing_observations` (
  `observation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `nurse_id` int(10) UNSIGNED NOT NULL,
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `intake` decimal(10,2) DEFAULT NULL,
  `output` decimal(10,2) DEFAULT NULL,
  `observed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`observation_id`),
  KEY `idx_nobs_visit` (`visit_id`),
  KEY `idx_nobs_nurse` (`nurse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `ordering_doctor_id` int(10) UNSIGNED NOT NULL,
  `order_type` enum('lab','radiology','drug','procedure','consultation','admission') NOT NULL,
  `priority` enum('routine','cito') NOT NULL DEFAULT 'routine',
  `clinical_reason` text DEFAULT NULL,
  `status` enum('ordered','in_progress','completed','cancelled') NOT NULL DEFAULT 'ordered',
  `ordered_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_visit` (`visit_id`),
  KEY `idx_orders_doctor` (`ordering_doctor_id`),
  KEY `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `item_type` enum('lab','radiology','drug','procedure') NOT NULL,
  `lab_test_id` int(10) UNSIGNED DEFAULT NULL,
  `rad_test_id` int(10) UNSIGNED DEFAULT NULL,
  `drug_id` int(10) UNSIGNED DEFAULT NULL,
  `procedure_id` int(10) UNSIGNED DEFAULT NULL,
  `instruction` text DEFAULT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_oi_order` (`order_id`),
  KEY `idx_oi_lab` (`lab_test_id`),
  KEY `idx_oi_rad` (`rad_test_id`),
  KEY `idx_oi_drug` (`drug_id`),
  KEY `idx_oi_proc` (`procedure_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE IF NOT EXISTS `patients` (
  `patient_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `no_rm` varchar(20) NOT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `nama_pasien` varchar(150) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` char(1) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_patients_norm` (`no_rm`),
  UNIQUE KEY `uq_patients_nik` (`nik`),
  KEY `idx_patients_nama` (`nama_pasien`),
  KEY `fk_patients_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT IGNORE INTO `patients` (`patient_id`, `no_rm`, `nik`, `nama_pasien`, `tanggal_lahir`, `jenis_kelamin`, `alamat`, `telepon`, `created_at`, `created_by`, `updated_at`) VALUES
(1, 'RM-000001', '3201010101900001', 'Siti Rahma', '1990-05-12', 'P', 'Karawaci', '0812-3456-7890', '2026-10-28 19:17:38', 2, '2026-10-28 19:17:38'),
(2, 'RM-000002', NULL, 'Budi Hartono', '1985-03-20', 'L', NULL, NULL, '2026-10-28 19:19:02', 2, '2026-10-28 19:19:02');

-- --------------------------------------------------------

--
-- Table structure for table `patient_allergies`
--

CREATE TABLE IF NOT EXISTS `patient_allergies` (
  `allergy_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `allergen` varchar(150) NOT NULL,
  `reaction` text DEFAULT NULL,
  `severity` varchar(20) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`allergy_id`),
  KEY `idx_allergy_patient` (`patient_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_allergies`
--

INSERT IGNORE INTO `patient_allergies` (`allergy_id`, `patient_id`, `allergen`, `reaction`, `severity`, `recorded_at`) VALUES
(1, 1, 'Penisilin', NULL, 'severe', '2026-10-28 19:20:14');

-- --------------------------------------------------------

--
-- Table structure for table `patient_insurance`
--

CREATE TABLE IF NOT EXISTS `patient_insurance` (
  `patient_insurance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `payer_id` int(10) UNSIGNED NOT NULL,
  `benefit_plan_id` int(10) UNSIGNED DEFAULT NULL,
  `membership_no` varchar(50) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`patient_insurance_id`),
  KEY `idx_pins_patient` (`patient_id`),
  KEY `idx_pins_payer` (`payer_id`),
  KEY `idx_pins_plan` (`benefit_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_insurance`
--

INSERT IGNORE INTO `patient_insurance` (`patient_insurance_id`, `patient_id`, `payer_id`, `benefit_plan_id`, `membership_no`, `valid_until`, `verification_status`) VALUES
(1, 1, 2, NULL, '0001234567890', '2026-12-31', 'verified');

-- --------------------------------------------------------

--
-- Table structure for table `payers`
--

CREATE TABLE IF NOT EXISTS `payers` (
  `payer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payer_name` varchar(100) NOT NULL,
  `payer_type` varchar(30) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`payer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payers`
--

INSERT IGNORE INTO `payers` (`payer_id`, `payer_name`, `payer_type`, `is_active`) VALUES
(1, 'Pribadi / Umum', 'self', 1),
(2, 'BPJS Kesehatan', 'bpjs', 1),
(3, 'Asuransi Swasta (contoh)', 'private', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payer_contracts`
--

CREATE TABLE IF NOT EXISTS `payer_contracts` (
  `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payer_id` int(10) UNSIGNED NOT NULL,
  `contract_name` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  PRIMARY KEY (`contract_id`),
  KEY `idx_pcon_payer` (`payer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `received_by` int(10) UNSIGNED NOT NULL,
  `transaction_no` varchar(40) NOT NULL,
  `payment_method` varchar(30) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_status` enum('paid','void') NOT NULL DEFAULT 'paid',
  PRIMARY KEY (`payment_id`),
  KEY `idx_pay_invoice` (`invoice_id`),
  KEY `idx_pay_user` (`received_by`),
  KEY `idx_pay_trx` (`transaction_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE IF NOT EXISTS `permissions` (
  `permission_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `permission_code` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(30) NOT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_permissions_code` (`permission_code`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT IGNORE INTO `permissions` (`permission_id`, `permission_code`, `module`, `action`) VALUES
(1, 'auth.view', 'auth', 'view'),
(2, 'auth.create', 'auth', 'create'),
(3, 'auth.update', 'auth', 'update'),
(4, 'masterdata.view', 'masterdata', 'view'),
(5, 'masterdata.create', 'masterdata', 'create'),
(6, 'masterdata.update', 'masterdata', 'update'),
(7, 'registration.view', 'registration', 'view'),
(8, 'registration.create', 'registration', 'create'),
(9, 'registration.update', 'registration', 'update'),
(10, 'scheduling.view', 'scheduling', 'view'),
(11, 'scheduling.create', 'scheduling', 'create'),
(12, 'scheduling.update', 'scheduling', 'update'),
(13, 'outpatient.view', 'outpatient', 'view'),
(14, 'outpatient.create', 'outpatient', 'create'),
(15, 'outpatient.update', 'outpatient', 'update'),
(16, 'emergency.view', 'emergency', 'view'),
(17, 'emergency.create', 'emergency', 'create'),
(18, 'emergency.update', 'emergency', 'update'),
(19, 'cpoe.view', 'cpoe', 'view'),
(20, 'cpoe.create', 'cpoe', 'create'),
(21, 'cpoe.update', 'cpoe', 'update'),
(22, 'nursing.view', 'nursing', 'view'),
(23, 'nursing.create', 'nursing', 'create'),
(24, 'nursing.update', 'nursing', 'update'),
(25, 'laboratory.view', 'laboratory', 'view'),
(26, 'laboratory.create', 'laboratory', 'create'),
(27, 'laboratory.update', 'laboratory', 'update'),
(28, 'radiology.view', 'radiology', 'view'),
(29, 'radiology.create', 'radiology', 'create'),
(30, 'radiology.update', 'radiology', 'update'),
(31, 'pharmacy.view', 'pharmacy', 'view'),
(32, 'pharmacy.create', 'pharmacy', 'create'),
(33, 'pharmacy.update', 'pharmacy', 'update'),
(34, 'inventory.view', 'inventory', 'view'),
(35, 'inventory.create', 'inventory', 'create'),
(36, 'inventory.update', 'inventory', 'update'),
(37, 'emr.view', 'emr', 'view'),
(38, 'emr.create', 'emr', 'create'),
(39, 'emr.update', 'emr', 'update'),
(40, 'emr.finalize', 'emr', 'finalize'),
(41, 'billing.view', 'billing', 'view'),
(42, 'billing.create', 'billing', 'create'),
(43, 'billing.update', 'billing', 'update'),
(44, 'billing.approve', 'billing', 'approve'),
(45, 'reporting.view', 'reporting', 'view'),
(46, 'inpatient.view', 'inpatient', 'view'),
(47, 'inpatient.admit', 'inpatient', 'admit'),
(48, 'inpatient.progress', 'inpatient', 'progress'),
(49, 'inpatient.discharge', 'inpatient', 'discharge');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE IF NOT EXISTS `prescriptions` (
  `prescription_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED NOT NULL,
  `status` enum('new','prepared','dispensed','cancelled') NOT NULL DEFAULT 'new',
  PRIMARY KEY (`prescription_id`),
  KEY `idx_presc_order` (`order_id`),
  KEY `idx_presc_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE IF NOT EXISTS `prescription_items` (
  `prescription_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `dose` varchar(50) DEFAULT NULL,
  `route` varchar(30) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`prescription_item_id`),
  KEY `idx_pitem_presc` (`prescription_id`),
  KEY `idx_pitem_orderitem` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE IF NOT EXISTS `queue` (
  `queue_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `queue_no` varchar(20) NOT NULL,
  `status` enum('waiting','called','served','skipped','cancelled') NOT NULL DEFAULT 'waiting',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`queue_id`),
  KEY `idx_queue_visit` (`visit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue`
--

INSERT IGNORE INTO `queue` (`queue_id`, `visit_id`, `queue_no`, `status`, `created_at`) VALUES
(1, 1, 'A-001', 'waiting', '2026-10-28 19:22:13'),
(2, 2, 'A-002', 'cancelled', '2026-10-28 19:22:50'),
(3, 3, 'A-003', 'waiting', '2026-10-28 19:23:08');

-- --------------------------------------------------------

--
-- Table structure for table `rad_images`
--

CREATE TABLE IF NOT EXISTS `rad_images` (
  `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `image_uri` varchar(255) NOT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_id`),
  KEY `idx_radimg_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rad_reports`
--

CREATE TABLE IF NOT EXISTS `rad_reports` (
  `rad_report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `radiologist_id` int(10) UNSIGNED NOT NULL,
  `interpretation` text DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `status` enum('draft','final') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`rad_report_id`),
  KEY `idx_radrep_item` (`order_item_id`),
  KEY `idx_radrep_doctor` (`radiologist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rad_schedules`
--

CREATE TABLE IF NOT EXISTS `rad_schedules` (
  `rad_schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  PRIMARY KEY (`rad_schedule_id`),
  KEY `idx_radsch_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT IGNORE INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'Admin IT', 'Mengelola akun, peran, dan master data'),
(2, 'Petugas Registrasi', 'Pendaftaran pasien, penjadwalan, admisi rawat inap'),
(3, 'Dokter', 'Pemeriksaan, EMR, CPOE, rawat jalan, IGD, rawat inap'),
(4, 'Perawat', 'Asuhan keperawatan dan observasi'),
(5, 'Analis Lab', 'Pemeriksaan laboratorium'),
(6, 'Radiografer', 'Pemeriksaan radiologi'),
(7, 'Apoteker', 'Farmasi dan dispensing obat'),
(8, 'Kasir', 'Tagihan dan pembayaran'),
(9, 'Petugas Gudang', 'Stok dan gudang'),
(10, 'Manajemen', 'Laporan dan persetujuan penyesuaian tagihan');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_roleperm_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 45),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 46),
(2, 47),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 17),
(3, 18),
(3, 19),
(3, 20),
(3, 21),
(3, 25),
(3, 28),
(3, 30),
(3, 37),
(3, 38),
(3, 39),
(3, 40),
(3, 46),
(3, 47),
(3, 48),
(3, 49),
(4, 13),
(4, 16),
(4, 17),
(4, 22),
(4, 23),
(4, 24),
(4, 37),
(4, 46),
(4, 47),
(5, 25),
(5, 26),
(5, 27),
(6, 28),
(6, 29),
(6, 30),
(7, 31),
(7, 32),
(7, 33),
(7, 34),
(8, 41),
(8, 42),
(8, 43),
(9, 34),
(9, 35),
(9, 36),
(10, 41),
(10, 44),
(10, 45);

-- --------------------------------------------------------

--
-- Table structure for table `stock_batches`
--

CREATE TABLE IF NOT EXISTS `stock_batches` (
  `stock_batch_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED DEFAULT NULL,
  `batch_no` varchar(50) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity_on_hand` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('available','quarantine','expired','empty') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`stock_batch_id`),
  KEY `idx_sb_item` (`inventory_item_id`),
  KEY `idx_sb_wh` (`warehouse_id`),
  KEY `idx_sb_loc` (`location_id`),
  KEY `idx_sb_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_batches`
--

INSERT IGNORE INTO `stock_batches` (`stock_batch_id`, `inventory_item_id`, `warehouse_id`, `location_id`, `batch_no`, `expiry_date`, `quantity_on_hand`, `status`) VALUES
(1, 1, 1, NULL, 'SALDO-AWAL', '2028-10-28', 500.00, 'available'),
(2, 2, 1, NULL, 'SALDO-AWAL', '2028-10-28', 500.00, 'available'),
(3, 3, 1, NULL, 'SALDO-AWAL', '2028-10-28', 500.00, 'available'),
(4, 4, 1, NULL, 'SALDO-AWAL', '2028-10-28', 500.00, 'available');

-- --------------------------------------------------------

--
-- Table structure for table `stock_opnames`
--

CREATE TABLE IF NOT EXISTS `stock_opnames` (
  `opname_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `performed_by` int(10) UNSIGNED NOT NULL,
  `opname_date` date NOT NULL,
  `status` enum('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
  PRIMARY KEY (`opname_id`),
  KEY `idx_so_wh` (`warehouse_id`),
  KEY `idx_so_user` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_opname_items`
--

CREATE TABLE IF NOT EXISTS `stock_opname_items` (
  `opname_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `opname_id` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `book_qty` decimal(12,2) NOT NULL,
  `physical_qty` decimal(12,2) NOT NULL,
  `difference` decimal(12,2) GENERATED ALWAYS AS (`physical_qty` - `book_qty`) STORED,
  PRIMARY KEY (`opname_item_id`),
  KEY `idx_soi_opname` (`opname_id`),
  KEY `idx_soi_item` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE IF NOT EXISTS `stock_transactions` (
  `stock_txn_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `txn_type` varchar(30) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `txn_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stock_txn_id`),
  KEY `idx_stx_item` (`inventory_item_id`),
  KEY `idx_stx_wh` (`warehouse_id`),
  KEY `idx_stx_user` (`user_id`),
  KEY `idx_stx_ref` (`reference_no`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_transactions`
--

INSERT IGNORE INTO `stock_transactions` (`stock_txn_id`, `inventory_item_id`, `warehouse_id`, `user_id`, `txn_type`, `quantity`, `reference_no`, `txn_at`) VALUES
(1, 1, 1, 1, 'opening_balance', 500.00, 'SALDO-AWAL', '2026-10-28 22:09:28'),
(2, 2, 1, 1, 'opening_balance', 500.00, 'SALDO-AWAL', '2026-10-28 22:09:28'),
(3, 3, 1, 1, 'opening_balance', 500.00, 'SALDO-AWAL', '2026-10-28 22:09:28'),
(4, 4, 1, 1, 'opening_balance', 500.00, 'SALDO-AWAL', '2026-10-28 22:09:28');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE IF NOT EXISTS `suppliers` (
  `supplier_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(150) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE IF NOT EXISTS `system_config` (
  `config_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `uq_config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_config`
--

INSERT IGNORE INTO `system_config` (`config_id`, `config_key`, `config_value`, `updated_at`) VALUES
(1, 'hospital_name', 'Rumah Sakit Dunia Indah', '2026-10-28 18:31:15'),
(2, 'queue_prefix', 'A', '2026-10-28 18:31:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT IGNORE INTO `users` (`user_id`, `username`, `password_hash`, `nama`, `status`) VALUES
(1, 'admin.it', '$2y$10$Mn/FN2c9q8oHiavc3LdVFuu5Pc6r0/qOxw9V5p7x9ni7m67tbm4nq', 'Admin IT', 'active'),
(2, 'registrasi', '$2y$10$lWR5ugaEyI9MA09len917uXbBbCmlD5p1i4YDBvqXfoMp.8a9a6pS', 'Dewi Lestari', 'active'),
(3, 'dokter', '$2y$10$6y2U6Qe0OEkEFCzY/8zvsu7doA8C6EgJVeJWzFnYFxjH5CNUpkovK', 'dr. Andi Wijaya, Sp.PD', 'active'),
(4, 'dokter2', '$2y$10$atJcPJZFaT0qQi7UtpT8Wu5e51//E3HWLyn8VrWwocYbRene/LC3C', 'dr. Rina Sari, Sp.A', 'active'),
(5, 'perawat', '$2y$10$ot5HobqhWxvUJfbvR.FXU.Y1Pjx7k.aNZfYI7NeQK3dGHQW28aQBe', 'Sari Dewi, S.Kep', 'active'),
(6, 'lab', '$2y$10$qlq6kOpfFwPKGnrDyeqEneuTsOyZG3X46EzUY6x/6.uAmVf2iJpVi', 'Rudi Hartono, A.Md.AK', 'active'),
(7, 'radiologi', '$2y$10$Okur1bLD8IbQuC1joZvTY.0zG2bs1cS4EA8n9b56LkHg7pSBh1zpy', 'dr. Budi Santoso, Sp.Rad', 'active'),
(8, 'apoteker', '$2y$10$qPm2LBl4EiFtboM.nxKG1.MPtOLeXs2H4YAbbgv4.k/k1.Y3mMGSG', 'Maya Putri, S.Farm., Apt', 'active'),
(9, 'kasir', '$2y$10$9WIMdlNAW4Buetf9wEJ3puPQ46G.m0yR9Gzgi4bNDPhzK.zlE0fLe', 'Nina Marlina', 'active'),
(10, 'gudang', '$2y$10$yPOptndAWYYIq.P0ZJhNeuEvfPIQVdk9E2VhpOoV3TgBToFz0mAPy', 'Joko Susilo', 'active'),
(11, 'manajemen', '$2y$10$iHSMNPCsJK4.ea2PqO7fcuLrkV5bcj.uBX1L3xnJhHYHT9c7U1BlG', 'Hendra Gunawan, MARS', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_userroles_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `assigned_at`) VALUES
(1, 1, '2026-10-28 17:32:33'),
(2, 2, '2026-10-28 17:32:33'),
(3, 3, '2026-10-28 17:32:33'),
(4, 3, '2026-10-28 17:32:33'),
(5, 4, '2026-10-28 17:32:33'),
(6, 5, '2026-10-28 17:32:33'),
(7, 6, '2026-10-28 17:32:33'),
(8, 7, '2026-10-28 17:32:33'),
(9, 8, '2026-10-28 17:32:33'),
(10, 9, '2026-10-28 17:32:33'),
(11, 10, '2026-10-28 17:32:33');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE IF NOT EXISTS `visits` (
  `visit_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `doctor_id` int(10) UNSIGNED DEFAULT NULL,
  `polyclinic_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_insurance_id` int(10) UNSIGNED DEFAULT NULL,
  `service_type` enum('outpatient','inpatient','emergency') NOT NULL,
  `visit_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','in_progress','completed','cancelled') NOT NULL DEFAULT 'registered',
  PRIMARY KEY (`visit_id`),
  KEY `idx_visit_patient` (`patient_id`),
  KEY `idx_visit_doctor` (`doctor_id`),
  KEY `idx_visit_poly` (`polyclinic_id`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `fk_visit_appt` (`appointment_id`),
  KEY `fk_visit_pins` (`patient_insurance_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visits`
--

INSERT IGNORE INTO `visits` (`visit_id`, `patient_id`, `doctor_id`, `polyclinic_id`, `appointment_id`, `patient_insurance_id`, `service_type`, `visit_date`, `status`) VALUES
(1, 1, 1, 2, NULL, 1, 'outpatient', '2026-10-28 19:22:13', 'registered'),
(2, 1, 2, 5, NULL, 1, 'outpatient', '2026-10-28 19:22:50', 'cancelled'),
(3, 2, 1, 2, NULL, NULL, 'outpatient', '2026-10-28 19:23:08', 'registered');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE IF NOT EXISTS `warehouses` (
  `warehouse_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`warehouse_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT IGNORE INTO `warehouses` (`warehouse_id`, `warehouse_name`, `location`) VALUES
(1, 'Gudang Farmasi', 'Instalasi Farmasi');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_locations`
--

CREATE TABLE IF NOT EXISTS `warehouse_locations` (
  `location_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` int(10) UNSIGNED NOT NULL,
  `bin_code` varchar(30) NOT NULL,
  `location_name` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`location_id`),
  UNIQUE KEY `uq_whloc_bin` (`warehouse_id`,`bin_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--
-- (Foreign key constraints dilewati di versi TiDB agar instalasi cepat)
--





























































/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- Tabel tambahan untuk deployment di Vercel
-- --------------------------------------------------------


-- Isi gambar radiologi (dipakai jika STORAGE_DRIVER=db)
CREATE TABLE IF NOT EXISTS `rad_image_blobs` (
  `image_uri` varchar(255) NOT NULL,
  `data` longblob NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`image_uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

-- Penanda instalasi selesai (dibuat paling akhir)
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `data` mediumblob NOT NULL,
  `last_activity` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
