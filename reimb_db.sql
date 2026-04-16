-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 13, 2026 at 10:05 AM
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
-- Database: `reimb_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `size_bytes` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `submission_id`, `category`, `path`, `original_name`, `mime`, `size_bytes`, `uploaded_at`) VALUES
(38, 15, 'tol', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_15/SCREENSHOT_2026-03-02_131634_69af9a200ac23_Screenshot_2026-03-02_131634_20260310_051216.png', 'Screenshot 2026-03-02 131634.png', 'image/png', 329567, '2026-03-10 04:12:16'),
(39, 15, 'tol', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_15/SCREENSHOT_2026-03-02_131530_69af9a200bdfe_Screenshot_2026-03-02_131530_20260310_051216.png', 'Screenshot 2026-03-02 131530.png', 'image/png', 41415, '2026-03-10 04:12:16'),
(40, 15, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_15/SCREENSHOT_2026-03-02_132113_69af9a882ebca_Screenshot_2026-03-02_132113_20260310_051400.png', 'Screenshot 2026-03-02 132113.png', 'image/png', 57388, '2026-03-10 04:14:00'),
(41, 15, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_15/SCREENSHOT_2026-03-02_133624_69af9b993e47a_Screenshot_2026-03-02_133624_20260310_051833.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-10 04:18:33'),
(42, 15, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_15/SCREENSHOT_2026-03-02_143110_69af9b993f91e_Screenshot_2026-03-02_143110_20260310_051833.png', 'Screenshot 2026-03-02 143110.png', 'image/png', 71452, '2026-03-10 04:18:33'),
(43, 16, 'tol', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_16/SCREENSHOT_2026-03-02_131634_69af9bb52c4d0_Screenshot_2026-03-02_131634_20260310_051901.png', 'Screenshot 2026-03-02 131634.png', 'image/png', 329567, '2026-03-10 04:19:01'),
(44, 16, 'tol', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_16/SCREENSHOT_2026-03-02_131530_69af9bb52d65a_Screenshot_2026-03-02_131530_20260310_051901.png', 'Screenshot 2026-03-02 131530.png', 'image/png', 41415, '2026-03-10 04:19:01'),
(45, 16, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_16/SCREENSHOT_2026-03-02_132113_69af9bb52e8ec_Screenshot_2026-03-02_132113_20260310_051901.png', 'Screenshot 2026-03-02 132113.png', 'image/png', 57388, '2026-03-10 04:19:01'),
(46, 16, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_16/SCREENSHOT_2026-03-02_133624_69af9bb52f70c_Screenshot_2026-03-02_133624_20260310_051901.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-10 04:19:01'),
(47, 16, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Suroyo_2026-03-10_20260310_16/SCREENSHOT_2026-03-02_143110_69af9bb530533_Screenshot_2026-03-02_143110_20260310_051901.png', 'Screenshot 2026-03-02 143110.png', 'image/png', 71452, '2026-03-10 04:19:01'),
(48, 17, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-12_20260312_17/SCREENSHOT_2026-03-02_133610_69b239ce2e1ca_Screenshot_2026-03-02_133610_20260312_045806.png', 'Screenshot 2026-03-02 133610.png', 'image/png', 344885, '2026-03-12 03:58:06'),
(49, 17, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-12_20260312_17/SCREENSHOT_2026-03-02_133624_69b24fc800b26_Screenshot_2026-03-02_133624_20260312_063152.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-12 05:31:52'),
(50, 17, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-12_20260312_17/SCREENSHOT_2026-03-02_133624_69b252b10489b_Screenshot_2026-03-02_133624_20260312_064417.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-12 05:44:17'),
(51, 17, 'parkir', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-12_20260312_17/SCREENSHOT_2026-03-02_143110_69b252b86f773_Screenshot_2026-03-02_143110_20260312_064424.png', 'Screenshot 2026-03-02 143110.png', 'image/png', 71452, '2026-03-12 05:44:24'),
(52, 18, 'parkir', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-12_20260312_18/SCREENSHOT_2026-03-02_143110_69b252bdc5562_Screenshot_2026-03-02_143110_20260312_064429.png', 'Screenshot 2026-03-02 143110.png', 'image/png', 71452, '2026-03-12 05:44:29'),
(53, 18, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-12_20260312_18/SCREENSHOT_2026-03-02_133624_69b252bdc5d56_Screenshot_2026-03-02_133624_20260312_064429.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-12 05:44:29'),
(54, 19, 'bbm', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-14_20260312_19/SCREENSHOT_2026-03-02_133610_69b25d5caed3c_Screenshot_2026-03-02_133610_20260312_072948.png', 'Screenshot 2026-03-02 133610.png', 'image/png', 344885, '2026-03-12 06:29:48'),
(55, 20, 'makan', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_20/SCREENSHOT_2026-03-02_133624_69b372ecc1a45_Screenshot_2026-03-02_133624_20260313_031404.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-13 02:14:04'),
(56, 20, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_20/SCREENSHOT_2026-03-02_132113_69b372fd26852_Screenshot_2026-03-02_132113_20260313_031421.png', 'Screenshot 2026-03-02 132113.png', 'image/png', 57388, '2026-03-13 02:14:21'),
(57, 20, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_20/SCREENSHOT_2026-03-02_131530_69b372fd27ac3_Screenshot_2026-03-02_131530_20260313_031421.png', 'Screenshot 2026-03-02 131530.png', 'image/png', 41415, '2026-03-13 02:14:21'),
(58, 21, 'makan', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_21/SCREENSHOT_2026-03-02_133624_69b3731a3ebfe_Screenshot_2026-03-02_133624_20260313_031450.png', 'Screenshot 2026-03-02 133624.png', 'image/png', 250127, '2026-03-13 02:14:50'),
(59, 21, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_21/SCREENSHOT_2026-03-02_132113_69b3731a3fc93_Screenshot_2026-03-02_132113_20260313_031450.png', 'Screenshot 2026-03-02 132113.png', 'image/png', 57388, '2026-03-13 02:14:50'),
(60, 21, 'entertain', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_21/SCREENSHOT_2026-03-02_131530_69b3731a40f64_Screenshot_2026-03-02_131530_20260313_031450.png', 'Screenshot 2026-03-02 131530.png', 'image/png', 41415, '2026-03-13 02:14:50'),
(61, 22, 'makan', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-14_20260313_22/SCREENSHOT_2026-03-02_133610_69b3765eb79ee_Screenshot_2026-03-02_133610_20260313_032846.png', 'Screenshot 2026-03-02 133610.png', 'image/png', 344885, '2026-03-13 02:28:46'),
(62, 20, 'makan', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_20/SCREENSHOT_2026-03-02_133610_69b376870274a_Screenshot_2026-03-02_133610_20260313_032927.png', 'Screenshot 2026-03-02 133610.png', 'image/png', 344885, '2026-03-13 02:29:27'),
(63, 23, 'makan', 'C:\\xampp\\htdocs\\reimburs/uploads/Dedek_Alamsyah_2026-03-13_20260313_23/SCREENSHOT_2026-03-02_133610_69b37690a8a4b_Screenshot_2026-03-02_133610_20260313_032936.png', 'Screenshot 2026-03-02 133610.png', 'image/png', 344885, '2026-03-13 02:29:36');

-- --------------------------------------------------------

--
-- Table structure for table `parcels`
--

CREATE TABLE `parcels` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nik` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'draft',
  `updated_at` datetime DEFAULT NULL,
  `total_kasbon` decimal(12,2) DEFAULT 0.00,
  `admin_total` decimal(14,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parcels`
--

INSERT INTO `parcels` (`id`, `user_id`, `nik`, `category`, `created_at`, `status`, `updated_at`, `total_kasbon`, `admin_total`) VALUES
(19, 12, '92016227', 'idul-ftiri', '2026-03-10 14:47:13', 'draft', NULL, 0.00, NULL),
(20, 12, '92016227', 'idul-ftiri', '2026-03-10 14:48:02', 'draft', NULL, 0.00, 25000.00),
(21, 3, '52024523', 'idul-ftiri', '2026-03-12 10:27:46', 'draft', NULL, 0.00, NULL),
(22, 3, '52024523', 'idul-ftiri', '2026-03-12 10:28:32', 'draft', NULL, 0.00, NULL),
(23, 3, '52024523', 'idul-ftiri', '2026-03-12 10:28:46', 'draft', NULL, 0.00, NULL),
(24, 3, '52024523', 'idul-ftiri', '2026-03-12 15:21:27', 'draft', NULL, 0.00, NULL),
(25, 3, '52024523', 'idul-ftiri', '2026-03-12 15:21:53', 'draft', NULL, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `parcel_categories`
--

CREATE TABLE `parcel_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parcel_categories`
--

INSERT INTO `parcel_categories` (`id`, `name`, `slug`, `enabled`, `created_at`) VALUES
(1, 'Idul Ftiri', 'idul-ftiri', 1, '2026-03-09 04:38:56'),
(2, 'Imlek', 'imlek', 0, '2026-03-10 04:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `parcel_files`
--

CREATE TABLE `parcel_files` (
  `id` int(11) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime` varchar(50) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parcel_files`
--

INSERT INTO `parcel_files` (`id`, `outlet_id`, `filename`, `original_name`, `mime`, `size`, `created_at`) VALUES
(16, 18, '1074bfe8a4050e11_1773128833.png', 'company profile.png', 'image/png', 2922401, '2026-03-10 14:47:13'),
(17, 19, 'a2bd79a9cdd18ec6_1773128882.png', 'production tracking system.png', 'image/png', 2825860, '2026-03-10 14:48:02'),
(18, 20, 'c9089839b48c6f69_1773286066.png', 'penyewaan.png', 'image/png', 2807336, '2026-03-12 10:27:46'),
(19, 21, '4f492a9f483e018a_1773286112.png', 'company profile.png', 'image/png', 2922401, '2026-03-12 10:28:32'),
(20, 23, '05664c4fc6431477_1773303687.png', 'absensi.png', 'image/png', 2954050, '2026-03-12 15:21:27'),
(21, 24, '9c19c3c6f9641983_1773303713.png', 'absensi.png', 'image/png', 2954050, '2026-03-12 15:21:53');

-- --------------------------------------------------------

--
-- Table structure for table `parcel_outlets`
--

CREATE TABLE `parcel_outlets` (
  `id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  `outlet_name` varchar(255) NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parcel_outlets`
--

INSERT INTO `parcel_outlets` (`id`, `parcel_id`, `outlet_name`, `amount`, `created_at`, `note`) VALUES
(18, 19, 'PT. TJAKRINDO', NULL, '2026-03-10 14:47:13', NULL),
(19, 20, 'PT A', NULL, '2026-03-10 14:48:02', NULL),
(20, 21, 'PT. TJAKRINDO', NULL, '2026-03-12 10:27:46', NULL),
(21, 22, 'PT. Damar', NULL, '2026-03-12 10:28:32', NULL),
(22, 23, 'PT. Damar', NULL, '2026-03-12 10:28:46', NULL),
(23, 24, 'PT. TJAKRINDO', NULL, '2026-03-12 15:21:27', NULL),
(24, 25, 'PT. TJAKRINDO', NULL, '2026-03-12 15:21:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nik` varchar(50) DEFAULT NULL,
  `departemen` varchar(100) DEFAULT NULL,
  `perjalanan_dinas` varchar(50) DEFAULT NULL,
  `tujuan` varchar(150) DEFAULT NULL,
  `biaya_tol` decimal(15,2) DEFAULT 0.00,
  `biaya_bensin` decimal(15,2) DEFAULT 0.00,
  `biaya_hotel` decimal(15,2) DEFAULT 0.00,
  `biaya_makan` decimal(15,2) DEFAULT 0.00,
  `biaya_entertain` decimal(15,2) DEFAULT 0.00,
  `biaya_parkir` decimal(15,2) DEFAULT 0.00,
  `total_biaya_lain` decimal(15,2) DEFAULT 0.00,
  `entertain_dengan` varchar(150) DEFAULT NULL,
  `plat_number` varchar(50) DEFAULT NULL,
  `km` decimal(15,2) DEFAULT NULL,
  `keterangan_lain` text DEFAULT NULL,
  `total_all` decimal(15,2) DEFAULT 0.00,
  `status` enum('DRAFT','SUBMITTED') DEFAULT 'DRAFT',
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `km_awal` int(11) DEFAULT NULL,
  `km_akhir` int(11) DEFAULT NULL,
  `km_terpakai` int(11) DEFAULT NULL,
  `km_per_hari` decimal(10,2) DEFAULT NULL,
  `harga_per_liter` decimal(12,2) DEFAULT NULL,
  `liter` decimal(10,2) DEFAULT NULL,
  `realisasi_km_per_l` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `tanggal`, `nama`, `nik`, `departemen`, `perjalanan_dinas`, `tujuan`, `biaya_tol`, `biaya_bensin`, `biaya_hotel`, `biaya_makan`, `biaya_entertain`, `biaya_parkir`, `total_biaya_lain`, `entertain_dengan`, `plat_number`, `km`, `keterangan_lain`, `total_all`, `status`, `submitted_at`, `created_at`, `km_awal`, `km_akhir`, `km_terpakai`, `km_per_hari`, `harga_per_liter`, `liter`, `realisasi_km_per_l`) VALUES
(15, '2026-03-10', 'Suroyo', '92016227', 'Sales', 'Luar Kota', '-', 20500.00, 300000.00, 0.00, 0.00, 500000.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 0.00, 'DRAFT', NULL, '2026-03-10 04:12:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, '2026-03-10', 'Suroyo', '92016227', 'Sales', 'Luar Kota', 'Aceh', 20500.00, 300000.00, 0.00, 0.00, 500000.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 820500.00, 'SUBMITTED', '2026-03-10 11:19:01', '2026-03-10 04:19:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, '2026-03-12', 'Dedek Alamsyah', '52024523', 'Sales', 'Dalam Kota', '-', 0.00, 9000.00, 0.00, 0.00, 0.00, 6000.00, 0.00, NULL, 'b 4444 cd', NULL, NULL, 9000.00, 'DRAFT', '2026-03-12 10:58:06', '2026-03-12 03:58:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, '2026-03-12', 'Dedek Alamsyah', '52024523', 'Sales', 'Luar Kota', 'Aceh', 0.00, 9000.00, 0.00, 0.00, 0.00, 6000.00, 0.00, NULL, 'b 4444 cd', NULL, NULL, 15000.00, 'SUBMITTED', '2026-03-12 12:44:29', '2026-03-12 05:44:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, '2026-03-14', 'Dedek Alamsyah', '52024523', 'Sales', 'Dalam Kota', '-', 0.00, 300000.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 'b 4444 cd', NULL, NULL, 300000.00, 'SUBMITTED', '2026-03-12 13:29:48', '2026-03-12 06:29:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, '2026-03-13', 'Dedek Alamsyah', '52024523', 'Sales', 'Luar Kota', '-', 0.00, 0.00, 0.00, 7000.00, 500000.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 0.00, 'DRAFT', NULL, '2026-03-13 02:14:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, '2026-03-13', 'Dedek Alamsyah', '52024523', 'Sales', 'Luar Kota', 'Aceh', 0.00, 0.00, 0.00, 90000.00, 500000.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 590000.00, 'SUBMITTED', '2026-03-13 09:14:50', '2026-03-13 02:14:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, '2026-03-14', 'Dedek Alamsyah', '52024523', 'Sales', 'Luar Kota', 'Jombang', 0.00, 0.00, 0.00, 7000.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 7000.00, 'SUBMITTED', '2026-03-13 09:28:46', '2026-03-13 02:28:46', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, '2026-03-13', 'Dedek Alamsyah', '52024523', 'Sales', 'Luar Kota', 'Jember', 0.00, 0.00, 0.00, 7000.00, 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, 7000.00, 'SUBMITTED', '2026-03-13 09:29:36', '2026-03-13 02:29:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `submission_items`
--

CREATE TABLE `submission_items` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `biaya` decimal(10,2) DEFAULT 0.00,
  `nama_orang` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `submission_items`
--

INSERT INTO `submission_items` (`id`, `submission_id`, `category`, `biaya`, `nama_orang`, `keterangan`) VALUES
(11, 15, 'tol', 8500.00, NULL, NULL),
(12, 15, 'tol', 12000.00, NULL, NULL),
(13, 15, 'bbm', 300000.00, NULL, '{\"plat\":\"b 4444 cd\",\"km_awal\":123,\"km_akhir\":129,\"km_terpakai\":6,\"harga_per_liter\":1000,\"liter\":300,\"realisasi_km_per_l\":0.02,\"tgl_awal\":\"\",\"tgl_akhir\":\"\"}'),
(14, 15, 'entertain', 500000.00, NULL, '{\"dengan\":\"Pak Rahmat Owner PT DMA\"}'),
(15, 16, 'tol', 8500.00, NULL, NULL),
(16, 16, 'tol', 12000.00, NULL, NULL),
(17, 16, 'bbm', 300000.00, NULL, '{\"plat\":\"b 4444 cd\",\"km_awal\":123,\"km_akhir\":129,\"km_terpakai\":6,\"harga_per_liter\":1000,\"liter\":300,\"realisasi_km_per_l\":0.02,\"tgl_awal\":\"\",\"tgl_akhir\":\"\"}'),
(18, 16, 'entertain', 500000.00, NULL, '{\"dengan\":\"Pak Rahmat Owner PT DMA\"}'),
(21, 17, 'bbm', 9000.00, NULL, '{\"plat\":\"b 4444 cd\",\"km_awal\":0,\"km_akhir\":0,\"km_terpakai\":0,\"harga_per_liter\":0,\"liter\":0,\"realisasi_km_per_l\":0,\"tgl_awal\":\"\",\"tgl_akhir\":\"\"}'),
(22, 17, 'parkir', 6000.00, NULL, NULL),
(23, 18, 'bbm', 9000.00, NULL, '{\"plat\":\"b 4444 cd\",\"km_awal\":0,\"km_akhir\":0,\"km_terpakai\":0,\"harga_per_liter\":0,\"liter\":0,\"realisasi_km_per_l\":0,\"tgl_awal\":\"\",\"tgl_akhir\":\"\"}'),
(24, 18, 'parkir', 6000.00, NULL, NULL),
(25, 19, 'bbm', 300000.00, NULL, '{\"plat\":\"b 4444 cd\",\"km_awal\":0,\"km_akhir\":0,\"km_terpakai\":0,\"harga_per_liter\":0,\"liter\":0,\"realisasi_km_per_l\":0,\"tgl_awal\":\"\",\"tgl_akhir\":\"\"}'),
(27, 20, 'entertain', 500000.00, NULL, '{\"dengan\":\"Pak Rahmat Owner PT DMA\"}'),
(28, 21, 'makan', 90000.00, NULL, '{\"nama\":\"Pak eko\"}'),
(29, 21, 'entertain', 500000.00, NULL, '{\"dengan\":\"Pak Rahmat Owner PT DMA\"}'),
(30, 22, 'makan', 7000.00, NULL, '{\"nama\":\"Pak eko\"}'),
(31, 20, 'makan', 7000.00, NULL, '{\"nama\":\"Pak eko\"}'),
(32, 23, 'makan', 7000.00, NULL, '{\"nama\":\"Pak eko\"}');

-- --------------------------------------------------------

--
-- Table structure for table `uc_logs`
--

CREATE TABLE `uc_logs` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uc_outlets`
--

CREATE TABLE `uc_outlets` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `trip_date` date DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `outlet_name` varchar(255) DEFAULT NULL,
  `est_sales` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uc_outlets`
--

INSERT INTO `uc_outlets` (`id`, `request_id`, `trip_date`, `destination`, `outlet_name`, `est_sales`) VALUES
(1, 2, '2026-03-05', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(2, 3, '2026-03-05', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(3, 4, '2026-03-12', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(4, 5, '2026-03-12', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(5, 6, '2026-03-12', 'Aceh', 'PT. TJAKRINDO', 100000.00),
(6, 7, '2026-03-05', 'Aceh', 'PT. TJAKRINDO', 100000.00),
(7, 7, '2026-03-06', 'Aceh', 'pt abc', 100000.00),
(8, 7, '2026-03-07', 'Aceh', 'pt bcd', 100000.00),
(9, 8, '2026-03-04', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(10, 9, '2026-03-05', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(11, 10, '2026-03-05', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(12, 11, '2026-03-07', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(13, 12, '2026-03-10', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(14, 13, '2026-03-10', 'Aceh', 'PT. TJAKRINDO', 100000.00),
(15, 13, '2026-03-11', 'medan', 'pt abc', 100000.00),
(16, 13, '2026-03-13', 'Aceh', 'PT. TJAKRINDO', 100000.00),
(17, 14, '2026-03-14', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(18, 15, '2026-03-14', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(19, 16, '2026-03-14', 'Pekalongan', 'PT. TJAKRINDO', 100000.00),
(20, 17, '2026-03-14', 'Pekalongan', 'PT. TJAKRINDO', 100000.00);

-- --------------------------------------------------------

--
-- Table structure for table `uc_requests`
--

CREATE TABLE `uc_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requester_name` varchar(255) NOT NULL,
  `branch` varchar(120) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `month_label` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `hotel_per_day` decimal(12,2) DEFAULT 0.00,
  `hotel_nights` int(11) DEFAULT 0,
  `meal_per_day` decimal(12,2) DEFAULT 0.00,
  `meal_days` int(11) DEFAULT 0,
  `fuel_amount` decimal(12,2) DEFAULT 0.00,
  `other_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uc_requests`
--

INSERT INTO `uc_requests` (`id`, `user_id`, `requester_name`, `branch`, `start_date`, `end_date`, `month_label`, `title`, `hotel_per_day`, `hotel_nights`, `meal_per_day`, `meal_days`, `fuel_amount`, `other_amount`, `total_amount`, `signature_path`, `created_at`) VALUES
(1, 3, '', '', NULL, NULL, '', NULL, 0.00, 0, 0.00, 0, 0.00, 0.00, 0.00, 'uploads/sig_1772604437_5a22356b.png', '2026-03-04 06:07:17'),
(2, 3, 'Dedek Alamsyah', '', '2026-03-03', '2026-03-04', 'Februari 2026', NULL, 10000.00, 3, 0.00, 0, 0.00, 0.00, 30000.00, NULL, '2026-03-04 07:00:15'),
(3, 3, 'Dedek Alamsyah', 'ke-0', '2026-03-03', '2026-03-04', 'Februari 2026', NULL, 10000.01, 3, 0.00, 0, 0.00, 0.00, 30000.03, NULL, '2026-03-04 07:22:03'),
(4, 3, 'Dedek Alamsyah', 'kepo', '2026-03-04', '2026-03-06', 'Februari 2026', NULL, 80000.00, 5, 0.00, 0, 0.00, 0.00, 400000.00, NULL, '2026-03-04 07:29:23'),
(5, 3, 'Dedek Alamsyah', 'kepo', '2026-03-01', '2026-03-14', '', NULL, 100000.00, 3, 1000.00, 4, 0.00, 0.00, 304000.00, NULL, '2026-03-05 01:39:01'),
(6, 3, 'Dedek Alamsyah', 'Medan', '2026-03-01', '2026-03-31', 'Maret 2026', NULL, 250000.00, 7, 75000.00, 7, 1000000.00, 500000.00, 3775000.00, NULL, '2026-03-05 04:26:12'),
(7, 3, 'Dedek Alamsyah', 'Medan', '2026-03-01', '2026-03-31', 'Maret 2026', NULL, 250000.00, 6, 75000.00, 7, 1000000.00, 200000.00, 3225000.00, NULL, '2026-03-05 04:28:35'),
(8, 3, 'Dedek Alamsyah', 'Medan', '2026-03-04', '2026-03-05', 'Februari 2026', NULL, 300000.00, 3, 0.00, 0, 0.00, 0.00, 900000.00, NULL, '2026-03-05 05:31:53'),
(9, 3, 'Dedek Alamsyah', 'Medan', '2026-03-04', '2026-03-05', 'Februari 2026', NULL, 600000.00, 4, 0.00, 0, 0.00, 0.00, 2400000.00, NULL, '2026-03-05 05:40:07'),
(10, 3, 'Dedek Alamsyah', 'Medan', '2026-03-04', '2026-03-05', 'Februari 2026', NULL, 600000.00, 5, 0.00, 0, 0.00, 0.00, 3000000.00, NULL, '2026-03-05 05:44:17'),
(11, 3, 'Dedek Alamsyah', 'Medan', '2026-03-06', '2026-03-07', 'Februari 2026', NULL, 400000.00, 4, 0.00, 0, 0.00, 0.00, 1600000.00, NULL, '2026-03-06 04:27:22'),
(12, 3, 'Dedek Alamsyah', 'Medan', '2026-03-09', '2026-03-10', 'Februari 2026', NULL, 60000.00, 4, 0.00, 0, 0.00, 0.00, 240000.00, NULL, '2026-03-09 04:06:22'),
(13, 12, 'Suroyo', 'Medan', '2026-03-01', '2026-03-31', 'Maret 2026', NULL, 250000.00, 2, 75000.00, 3, 500000.00, 200000.00, 1425000.00, NULL, '2026-03-10 03:44:55'),
(14, 3, 'Dedek Alamsyah', 'Medan', '2026-03-12', '2026-03-13', '', NULL, 100000.00, 4, 30000.00, 4, 50000.00, 0.00, 570000.00, NULL, '2026-03-12 03:09:35'),
(15, 3, 'Dedek Alamsyah', 'Medan', '2026-03-12', '2026-03-13', '', NULL, 100000.00, 4, 30000.00, 4, 50000.00, 0.00, 570000.00, NULL, '2026-03-12 03:09:46'),
(16, 3, 'Dedek Alamsyah', 'Medan', '2026-03-13', '2026-03-14', 'Februari 2026', NULL, 5000000.00, 4, 400000.00, 5, 5000000.00, 0.00, 27000000.00, NULL, '2026-03-12 08:20:25'),
(17, 3, 'Dedek Alamsyah', 'Medan', '2026-03-13', '2026-03-14', 'Februari 2026', NULL, 5000000.00, 4, 400000.00, 5, 5000000.00, 0.00, 27000000.00, NULL, '2026-03-12 08:20:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nik` varchar(32) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `departemen` varchar(100) DEFAULT NULL,
  `cabang` varchar(100) DEFAULT NULL,
  `jabatan` varchar(150) DEFAULT NULL,
  `singkatan_nama` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `uid` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `role` varchar(20) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nik`, `username`, `nama`, `departemen`, `cabang`, `jabatan`, `singkatan_nama`, `password`, `uid`, `created_at`, `role`) VALUES
(1, '12024501', 'kornelius', 'Kornelius Andrean Bayu krisma', 'Sales', 'Surabaya', 'Sales Promotion', 'KAB', 'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3', 'uid-12024501', '2026-03-02 13:30:17', 'user'),
(2, '12024509', 'syukur', 'Syukur Aroziduhu Laia', 'Sales', 'Medan', 'Sales Supervisor', 'SAA', '97a6d21df7c51e8289ac1a8c026aaac143e15aa1957f54f42e30d8f8a85c3a55', 'uid-12024509', '2026-03-02 13:30:17', 'user'),
(3, '52024523', 'dedek', 'Dedek Alamsyah', 'Sales', 'Medan', 'Sales Promotion', 'DAL', 'b3a8e0e1f9ab1bfe3a36f231f676f78bb30a519d2b21e6c530c0eee8ebb4a5d0', 'uid-52024523', '2026-03-02 13:30:17', 'user'),
(4, '122024508', 'yogatama', 'Yogatama Aji Nugraha', 'Sales', 'Semarang', 'Central Java Sales Supervisor', 'YAU', '35a9e381b1a27567549b5f8a6f783c167ebf809f1c4d6a9e367240484d8ce281', 'uid-122024508', '2026-03-02 13:30:17', 'user'),
(5, '12025514', 'arifin', 'Muhammad Arifin', 'Sales', 'Sidoarjo', 'East Java Sales Supervisor', 'MAI', '97a6d21df7c51e8289ac1a8c026aaac143e15aa1957f54f42e30d8f8a85c3a55', 'uid-12025514', '2026-03-02 13:30:17', 'user'),
(6, '42025508', 'belvan', 'Belvan Almer Faran Jovian Olong', 'Sales', 'Cirebon', 'Sales Promotion', 'BAF', 'b3a8e0e1f9ab1bfe3a36f231f676f78bb30a519d2b21e6c530c0eee8ebb4a5d0', 'uid-42025508', '2026-03-02 13:30:17', 'user'),
(7, '92017284', 'adhon', 'Achmat Romadhon', 'Sales', 'Madura', 'Sales Promotion', 'ARO', 'b3a8e0e1f9ab1bfe3a36f231f676f78bb30a519d2b21e6c530c0eee8ebb4a5d0', 'uid-92017284', '2026-03-02 13:30:17', 'user'),
(8, '32020386', 'devi', 'Devi Yudha Aprilia', 'Sales', 'Jember', 'Sales Promotion', 'DYA', '97a6d21df7c51e8289ac1a8c026aaac143e15aa1957f54f42e30d8f8a85c3a55', 'uid-32020386', '2026-03-02 13:30:17', 'user'),
(9, '112014217', 'opan', 'Opan Sopian', 'Sales', 'Bandung', 'Sales Supervisor', 'OSO', 'b3a8e0e1f9ab1bfe3a36f231f676f78bb30a519d2b21e6c530c0eee8ebb4a5d0', 'uid-112014217', '2026-03-02 13:30:17', 'user'),
(10, '42016212', 'rahmat', 'Rahmat Adi', 'Sales', 'Surabaya', 'East Regional Sales Sub Dept Head', 'RAD', '35a9e381b1a27567549b5f8a6f783c167ebf809f1c4d6a9e367240484d8ce281', 'uid-42016212', '2026-03-02 13:30:17', 'user'),
(11, '82019362', 'sunardi', 'Sunardi', 'Sales', 'Palembang', 'Sales Supervisor', 'SUN', '97a6d21df7c51e8289ac1a8c026aaac143e15aa1957f54f42e30d8f8a85c3a55', 'uid-82019362', '2026-03-02 13:30:17', 'user'),
(12, '92016227', 'suroyo', 'Suroyo', 'Sales', 'Medan', 'West Regional Sales Sub Dept Head (Medan)', 'SUO', 'b3a8e0e1f9ab1bfe3a36f231f676f78bb30a519d2b21e6c530c0eee8ebb4a5d0', 'uid-92016227', '2026-03-02 13:30:17', 'user'),
(13, '122025553', 'eki', 'Eki Firmansyah', 'Sales', 'Jambi', 'Sales Promotion', 'EFI', 'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3', 'uid-122025553', '2026-03-02 13:30:17', 'user'),
(14, '12026561', 'kurniawan', 'Kurniawan Apryanto', 'Sales', 'Kalimantan Selatan', 'Sales Promotion', 'KAP', '97a6d21df7c51e8289ac1a8c026aaac143e15aa1957f54f42e30d8f8a85c3a55', 'uid-12026561', '2026-03-02 13:30:17', 'user'),
(15, '0000', 'admin', 'Administrator', 'IT', 'HQ', 'System Admin', 'ADM', 'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3', 'uid-admin', '2026-03-03 13:54:46', 'admin'),
(16, '12345678', 'elang', 'Elang Damar Galih Pamungkas', 'IT', 'HQ', 'admin', 'elg', 'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3', 'uid-12345678', '2026-03-09 12:15:43', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`);

--
-- Indexes for table `parcels`
--
ALTER TABLE `parcels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcel_categories`
--
ALTER TABLE `parcel_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `parcel_files`
--
ALTER TABLE `parcel_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `outlet_id` (`outlet_id`);

--
-- Indexes for table `parcel_outlets`
--
ALTER TABLE `parcel_outlets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcel_id` (`parcel_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `submission_items`
--
ALTER TABLE `submission_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`);

--
-- Indexes for table `uc_logs`
--
ALTER TABLE `uc_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `uc_outlets`
--
ALTER TABLE `uc_outlets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `uc_requests`
--
ALTER TABLE `uc_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nik` (`nik`),
  ADD UNIQUE KEY `uq_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `parcels`
--
ALTER TABLE `parcels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `parcel_categories`
--
ALTER TABLE `parcel_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `parcel_files`
--
ALTER TABLE `parcel_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `parcel_outlets`
--
ALTER TABLE `parcel_outlets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `submission_items`
--
ALTER TABLE `submission_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `uc_logs`
--
ALTER TABLE `uc_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uc_outlets`
--
ALTER TABLE `uc_outlets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `uc_requests`
--
ALTER TABLE `uc_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parcel_files`
--
ALTER TABLE `parcel_files`
  ADD CONSTRAINT `parcel_files_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `parcel_outlets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parcel_outlets`
--
ALTER TABLE `parcel_outlets`
  ADD CONSTRAINT `parcel_outlets_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uc_logs`
--
ALTER TABLE `uc_logs`
  ADD CONSTRAINT `uc_logs_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `uc_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uc_outlets`
--
ALTER TABLE `uc_outlets`
  ADD CONSTRAINT `uc_outlets_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `uc_requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
