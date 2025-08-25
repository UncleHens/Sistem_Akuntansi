-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 14, 2025 at 07:22 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `catatcepat`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `cek_penyesuaian_aktif` (IN `bulan` INT, IN `tahun` INT)   BEGIN
    SELECT
        dt.id_akun,
        a.nama_akun,
        t.nama_transaksi,
        t.tgl_transaksi,
        dt.debit_kredit,
        dt.nilai,
        t.post AS status_post
    FROM 
        detail_transaksi dt
    INNER JOIN 
        transaksi t ON dt.id_transaksi = t.id_transaksi
    INNER JOIN 
        akun a ON dt.id_akun = a.id_akun
    WHERE 
        MONTH(t.tgl_transaksi) = bulan
        AND YEAR(t.tgl_transaksi) = tahun
        AND t.post = '1'  -- Hanya transaksi yang sudah di-post
        AND t.hapus = '0' -- Tidak termasuk transaksi yang dihapus
    ORDER BY 
        t.tgl_transaksi, dt.id_detail_transaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cek_penyesuaian_bulan_ini` (IN `bulan` INT, IN `tahun` INT)   BEGIN
    SELECT COUNT(*) AS jumlah_penyesuaian
    FROM jurnal_penyesuaian
    WHERE MONTH(tanggal) = bulan AND YEAR(tanggal) = tahun;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cek_status_transaksi` (IN `bulan` INT, IN `tahun` INT)   BEGIN
    SELECT
        dt.id_akun,
        a.nama_akun,
        t.nama_transaksi,
        t.tgl_transaksi,
        dt.debit_kredit,
        dt.nilai,
        CASE 
            WHEN t.post = '1' THEN 'Sudah Post'
            WHEN t.post = '0' THEN 'Belum Post'
        END AS status_post,
        CASE 
            WHEN t.hapus = '1' THEN 'Dihapus'
            WHEN t.hapus = '0' THEN 'Aktif'
        END AS status_hapus
    FROM 
        detail_transaksi dt
    INNER JOIN 
        transaksi t ON dt.id_transaksi = t.id_transaksi
    INNER JOIN 
        akun a ON dt.id_akun = a.id_akun
    WHERE 
        MONTH(t.tgl_transaksi) = bulan
        AND YEAR(t.tgl_transaksi) = tahun
    ORDER BY 
        t.tgl_transaksi, dt.id_detail_transaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `cek_transaksi_belum_post` (IN `bulan` INT, IN `tahun` INT)   BEGIN
    SELECT
        dt.id_akun,
        a.nama_akun,
        t.nama_transaksi,
        t.tgl_transaksi,
        dt.debit_kredit,
        dt.nilai,
        'Belum Post' AS status
    FROM 
        detail_transaksi dt
    INNER JOIN 
        transaksi t ON dt.id_transaksi = t.id_transaksi
    INNER JOIN 
        akun a ON dt.id_akun = a.id_akun
    WHERE 
        MONTH(t.tgl_transaksi) = bulan
        AND YEAR(t.tgl_transaksi) = tahun
        AND t.post = '0'  -- Transaksi yang belum di-post
        AND t.hapus = '0' -- Tidak termasuk transaksi yang dihapus
    ORDER BY 
        t.tgl_transaksi, dt.id_detail_transaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetBukuBesar` ()   BEGIN
SET @debit = 0;
SET @kredit = 0;

SELECT 
    DATE_FORMAT(t.tgl_transaksi, '%Y %b') as bulan,
    DATE_FORMAT(t.tgl_transaksi, '%d') as tanggal,
    a.nama_akun,
    t.nama_transaksi,
    (IF(dt.debit_kredit = 'D', dt.nilai, 0)) as Debit,
    (IF(dt.debit_kredit = 'K', dt.nilai, 0)) as Kredit,
    (IF(dt.debit_kredit = 'D', @debit := @debit + dt.nilai, @debit)) as total_debit,
    (IF(dt.debit_kredit = 'K', @kredit := @kredit + dt.nilai, @kredit)) as total_kredit
FROM 
    transaksi t
    JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
    JOIN akun a ON dt.id_akun = a.id_akun
WHERE 
    1=1  -- Kondisi selalu benar, lebih mudah untuk menambahkan filter tambahan
    -- Hapus filter akun untuk membuatnya universal
    -- AND a.nama_akun = 'Modal'  
ORDER BY 
    t.tgl_transaksi, dt.id_detail_transaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetJurnal` ()   BEGIN
    SELECT 
        transaksi.id_transaksi,
        transaksi.tgl_transaksi,
        transaksi.nama_transaksi,
        akun.nama_akun,
        IF(detail_transaksi.debit_kredit = "D", detail_transaksi.nilai, 0) AS Debit,
        IF(detail_transaksi.debit_kredit = "K", detail_transaksi.nilai, 0) AS Kredit
    FROM 
        transaksi
    JOIN 
        detail_transaksi ON transaksi.id_transaksi = detail_transaksi.id_transaksi
    JOIN 
        akun ON detail_transaksi.id_akun = akun.id_akun
    ORDER BY 
        transaksi.id_transaksi, detail_transaksi.id_detail_transaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetLabaRugi` ()   BEGIN
    SET @total = 0;
    
    SELECT 
        DATE_FORMAT(t.tgl_transaksi, '%Y %b') AS bln,
        a.nama_akun, 
        t.nama_transaksi,
        IF(dt.debit_kredit = 'D', dt.nilai, 0) AS Debit,
        IF(dt.debit_kredit = 'K', dt.nilai, 0) AS Kredit,
        IF(dt.debit_kredit = 'D', @total := @total - dt.nilai, @total := @total + dt.nilai) AS ProfitLoss
    FROM 
        akun a
    JOIN 
        detail_transaksi dt ON a.id_akun = dt.id_akun
    JOIN 
        transaksi t ON t.id_transaksi = dt.id_transaksi
    WHERE 
        SUBSTRING(a.id_akun, 1, 1) IN ('4', '5')
    ORDER BY 
        t.tgl_transaksi, a.nama_akun;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetNeracaSaldo` ()   BEGIN
    SET @debit = 0;
    SET @kredit = 0;
    
    SELECT 
        (IF(akun.aktiva_pasiva = "A", "AKTIVA", "PASIVA")) AS kelompok,
        DATE_FORMAT(transaksi.tgl_transaksi, "%Y %b") AS bln, 
        DATE_FORMAT(transaksi.tgl_transaksi, "%d") AS tgl,
        akun.nama_akun,
        (IF(detail_transaksi.debit_kredit = "D", detail_transaksi.nilai, 0)) AS Debit,
        (IF(detail_transaksi.debit_kredit = "K", detail_transaksi.nilai, 0)) AS Kredit,
        (IF(detail_transaksi.debit_kredit = "D", @debit := @debit + detail_transaksi.nilai, @debit)) AS totaldebit,
        (IF(detail_transaksi.debit_kredit = "K", @kredit := @kredit + detail_transaksi.nilai, @kredit)) AS totalkredit
    FROM 
        akun
    JOIN 
        detail_transaksi ON detail_transaksi.id_akun = akun.id_akun
    JOIN 
        transaksi ON transaksi.id_transaksi = detail_transaksi.id_transaksi
    WHERE 
        SUBSTRING(akun.id_akun, 1, 1) IN ('1', '2', '3')
    ORDER BY 
        transaksi.tgl_transaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetReaksiDetail` ()   BEGIN
    SELECT 
        r.id_reaksi,
        r.nama_reaksi,
        dr.id_detail_reaksi,
        dr.id_akun,
        a.nama_akun,
        dr.debit_kredit
    FROM 
        reaksi r
    LEFT JOIN 
        detail_reaksi dr ON r.id_reaksi = dr.id_reaksi
    LEFT JOIN
        akun a ON dr.id_akun = a.id_akun
    ORDER BY
        r.id_reaksi, dr.id_detail_reaksi;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `proses_closing` (IN `v_bulan` INT, IN `v_tahun` INT, IN `v_posted_by` INT)   BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_id_akun INT;
    DECLARE v_nama_akun VARCHAR(100);
    DECLARE v_jenis VARCHAR(20);
    DECLARE v_total_debit DECIMAL(15,2);
    DECLARE v_total_kredit DECIMAL(15,2);
    DECLARE v_saldo DECIMAL(15,2);
    DECLARE v_laba_rugi DECIMAL(15,2) DEFAULT 0;
    DECLARE v_modal_awal DECIMAL(15,2) DEFAULT 0;
    DECLARE v_modal_tambahan DECIMAL(15,2) DEFAULT 0;
    DECLARE v_modal_akhir DECIMAL(15,2) DEFAULT 0;
    DECLARE v_prive_total DECIMAL(15,2) DEFAULT 0;
    DECLARE v_total_pendapatan DECIMAL(15,2) DEFAULT 0;
    DECLARE v_total_beban DECIMAL(15,2) DEFAULT 0;

    DECLARE akun_cursor CURSOR FOR
        SELECT id_akun, nama_akun,
               CASE 
                   WHEN SUBSTRING(CAST(id_akun AS CHAR), 1, 1) = '4' THEN 'pendapatan'
                   WHEN SUBSTRING(CAST(id_akun AS CHAR), 1, 1) = '5' THEN 'beban'
                   WHEN id_akun = 302 THEN 'prive'
                   ELSE 'lainnya'
               END as jenis
        FROM akun
        WHERE SUBSTRING(CAST(id_akun AS CHAR), 1, 1) IN ('4', '5') OR id_akun = 302;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    IF EXISTS (SELECT 1 FROM closing WHERE bulan = v_bulan AND tahun = v_tahun LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periode sudah ditutup sebelumnya.';
    END IF;

    IF v_bulan = 1 AND v_tahun = YEAR(CURDATE()) THEN
        SELECT IFNULL(SUM(CASE WHEN dt.debit_kredit = 'K' THEN dt.nilai ELSE 0 END), 0)
        INTO v_modal_awal
        FROM detail_transaksi dt 
        JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
        WHERE dt.id_akun = 311
          AND MONTH(t.tgl_transaksi) = v_bulan
          AND YEAR(t.tgl_transaksi) = v_tahun
          AND t.post = '1'
          AND t.nama_transaksi LIKE '%Modal Awal%';

        IF v_modal_awal = 0 THEN
            SELECT SUM(CASE WHEN dt.debit_kredit = 'K' THEN dt.nilai ELSE -dt.nilai END)
            INTO v_modal_awal
            FROM detail_transaksi dt
            JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
            WHERE dt.id_akun = 311
              AND (YEAR(t.tgl_transaksi) < v_tahun OR 
                   (YEAR(t.tgl_transaksi) = v_tahun AND MONTH(t.tgl_transaksi) < v_bulan))
              AND t.post = '1';

            IF v_modal_awal IS NULL THEN
                SET v_modal_awal = 150000000;
            END IF;
        END IF;

        INSERT INTO closing (id_akun, jenis_penyesuaian, bulan, tahun, debit, kredit, keterangan, tanggal_closing, posted_by)
        VALUES (311, 'modal_awal', v_bulan, v_tahun, 0, v_modal_awal, 'Modal awal periode', CURDATE(), v_posted_by);
    ELSE
        SELECT kredit INTO v_modal_awal
        FROM closing
        WHERE id_akun = 311 AND jenis_penyesuaian = 'modal_akhir'
          AND ((bulan = v_bulan-1 AND tahun = v_tahun) OR (bulan = 12 AND tahun = v_tahun-1))
        ORDER BY tahun DESC, bulan DESC LIMIT 1;
    END IF;

    SELECT IFNULL(SUM(CASE WHEN dt.debit_kredit = 'K' THEN dt.nilai ELSE 0 END), 0)
    INTO v_modal_tambahan
    FROM detail_transaksi dt 
    JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
    WHERE dt.id_akun = 311
      AND MONTH(t.tgl_transaksi) = v_bulan
      AND YEAR(t.tgl_transaksi) = v_tahun
      AND t.post = '1'
      AND t.nama_transaksi LIKE '%Modal Tambahan%';

    IF v_modal_tambahan > 0 THEN
        INSERT INTO closing (id_akun, jenis_penyesuaian, bulan, tahun, debit, kredit, keterangan, tanggal_closing, posted_by)
        VALUES (311, 'modal_tambahan', v_bulan, v_tahun, 0, v_modal_tambahan, 'Modal tambahan periode', CURDATE(), v_posted_by);
    END IF;

    OPEN akun_cursor;
    akun_loop: LOOP
        FETCH akun_cursor INTO v_id_akun, v_nama_akun, v_jenis;
        IF done THEN LEAVE akun_loop; END IF;

        SELECT 
            IFNULL(SUM(CASE WHEN dt.debit_kredit = 'D' THEN dt.nilai ELSE 0 END), 0),
            IFNULL(SUM(CASE WHEN dt.debit_kredit = 'K' THEN dt.nilai ELSE 0 END), 0)
        INTO v_total_debit, v_total_kredit
        FROM detail_transaksi dt 
        JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
        WHERE dt.id_akun = v_id_akun
          AND MONTH(t.tgl_transaksi) = v_bulan
          AND YEAR(t.tgl_transaksi) = v_tahun
          AND t.hapus = '0';

        IF v_jenis = 'pendapatan' THEN
            SET v_saldo = v_total_kredit - v_total_debit;
            SET v_total_pendapatan = v_total_pendapatan + v_saldo;

            IF v_saldo > 0 THEN
                INSERT INTO closing VALUES
                (NULL, v_bulan, v_tahun, v_id_akun, 'laba_rugi', v_saldo, 0, NULL, CONCAT('Tutup pendapatan - ', v_nama_akun), CURDATE(), v_posted_by),
                (NULL, v_bulan, v_tahun, 311, 'laba_rugi', 0, v_saldo, NULL, CONCAT('Tambah modal dari pendapatan - ', v_nama_akun), CURDATE(), v_posted_by);
            END IF;

        ELSEIF v_jenis = 'beban' THEN
            SET v_saldo = v_total_debit - v_total_kredit;
            SET v_total_beban = v_total_beban + v_saldo;

            IF v_saldo > 0 THEN
                INSERT INTO closing VALUES
                (NULL, v_bulan, v_tahun, v_id_akun, 'laba_rugi', 0, v_saldo, NULL, CONCAT('Tutup beban - ', v_nama_akun), CURDATE(), v_posted_by),
                (NULL, v_bulan, v_tahun, 311, 'laba_rugi', v_saldo, 0, NULL, CONCAT('Kurangi modal dari beban - ', v_nama_akun), CURDATE(), v_posted_by);
            END IF;

        ELSEIF v_jenis = 'prive' THEN
            SET v_saldo = v_total_debit - v_total_kredit;
            SET v_prive_total = v_prive_total + v_saldo;

            IF v_saldo > 0 THEN
                INSERT INTO closing VALUES
                (NULL, v_bulan, v_tahun, v_id_akun, 'prive', 0, v_saldo, NULL, CONCAT('Tutup prive - ', v_nama_akun), CURDATE(), v_posted_by),
                (NULL, v_bulan, v_tahun, 311, 'prive', v_saldo, 0, NULL, CONCAT('Kurangi modal karena prive - ', v_nama_akun), CURDATE(), v_posted_by);
            END IF;
        END IF;
    END LOOP;
    CLOSE akun_cursor;

    SET v_laba_rugi = v_total_pendapatan - v_total_beban;
    SET v_modal_akhir = v_modal_awal + v_modal_tambahan + v_laba_rugi - v_prive_total;

    INSERT INTO closing (id_akun, jenis_penyesuaian, bulan, tahun, debit, kredit, keterangan, tanggal_closing, posted_by)
    VALUES (311, 'modal_akhir', v_bulan, v_tahun, 0, v_modal_akhir, 'Modal akhir periode', CURDATE(), v_posted_by);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `proses_reopening` (IN `v_bulan` INT, IN `v_tahun` INT)   BEGIN
    DECLARE v_count INT DEFAULT 0;
    
    -- Cek apakah periode sudah ditutup
    SELECT COUNT(*) INTO v_count 
    FROM closing 
    WHERE bulan = v_bulan AND tahun = v_tahun;
    
    IF v_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periode ini belum pernah ditutup sebelumnya.';
    END IF;
    
    -- Hapus semua data closing untuk periode tersebut
    DELETE FROM closing 
    WHERE bulan = v_bulan AND tahun = v_tahun;
    
    -- Reset status post transaksi untuk periode tersebut (jika diperlukan)
    UPDATE transaksi t
    SET t.post = '0'
    WHERE MONTH(t.tgl_transaksi) = v_bulan 
    AND YEAR(t.tgl_transaksi) = v_tahun
    AND t.post = '1';
    
    -- Log aktivitas reopening (opsional)
    -- INSERT INTO log_aktivitas (aktivitas, bulan, tahun, tanggal, user) 
    -- VALUES ('REOPENING', v_bulan, v_tahun, NOW(), USER());
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `akun`
--

CREATE TABLE `akun` (
  `id_akun` int NOT NULL,
  `nama_akun` varchar(100) NOT NULL,
  `aktiva_pasiva` varchar(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `akun`
--

INSERT INTO `akun` (`id_akun`, `nama_akun`, `aktiva_pasiva`) VALUES
(101, 'Cash', 'A'),
(102, 'Bank', 'A'),
(112, 'Accounts Receivable', 'A'),
(120, 'Inventory', 'A'),
(126, 'Supplies', 'A'),
(131, 'Prepaid Rent', 'A'),
(132, 'Prepaid Insurance', 'A'),
(133, 'Prepaid Advertising', 'A'),
(134, 'Prepaid Utilities', 'A'),
(135, 'Prepaid Legal Fees', 'A'),
(136, 'Prepaid Maintenance', 'A'),
(137, 'Prepaid Software License', 'A'),
(138, 'Prepaid Travel Expenses', 'A'),
(139, 'Prepaid Training Costs', 'A'),
(140, 'Prepaid Subscription', 'A'),
(141, 'Prepaid Taxes', 'A'),
(142, 'Prepaid Interest', 'A'),
(143, 'Prepaid Commission', 'A'),
(144, 'Prepaid Office Supplies', 'A'),
(145, 'Prepaid Royalties', 'A'),
(157, 'Equipment', 'A'),
(160, 'Building', 'A'),
(200, 'Notes Payable', 'P'),
(201, 'Accounts Payable', 'P'),
(301, 'Dividens', 'P'),
(302, 'Prive', 'P'),
(311, 'Share Capital', 'P'),
(400, 'Service Revenue', 'P'),
(401, 'Sales Revenue', 'P'),
(511, 'Depreciation Expense', 'P'),
(526, 'Salaries and Wages Expense', 'P'),
(529, 'Rent Expense', 'P'),
(532, 'Utilities Expense', 'P'),
(533, 'Insurance Expense', 'P'),
(534, 'Advertising Expense', 'P'),
(535, 'Software License Expense', 'P'),
(536, 'Subscription Expense', 'P'),
(537, 'Legal Fees Expense', 'P'),
(538, 'Maintenance Expense', 'P'),
(539, 'Travel Expense', 'P'),
(540, 'Training Expense', 'P'),
(541, 'Tax Expense', 'P'),
(542, 'Interest Expense', 'P'),
(543, 'Commission Expense', 'P'),
(544, 'Office Supplies Expense', 'P'),
(545, 'Royalty Expense', 'P');

-- --------------------------------------------------------

--
-- Table structure for table `closing`
--

CREATE TABLE `closing` (
  `id_closing` int NOT NULL,
  `bulan` int NOT NULL,
  `tahun` int NOT NULL,
  `id_akun` int NOT NULL,
  `jenis_penyesuaian` enum('modal_awal','modal_tambahan','modal_akhir','laba_rugi','prive','penyesuaian','ikhtisar') NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `kredit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `referensi_transaksi` int DEFAULT NULL COMMENT 'ID transaksi terkait',
  `keterangan` text,
  `tanggal_closing` datetime NOT NULL,
  `posted_by` int NOT NULL COMMENT 'ID user yang melakukan posting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `closing`
--

INSERT INTO `closing` (`id_closing`, `bulan`, `tahun`, `id_akun`, `jenis_penyesuaian`, `debit`, `kredit`, `referensi_transaksi`, `keterangan`, `tanggal_closing`, `posted_by`) VALUES
(249, 1, 2025, 311, 'modal_awal', 0.00, 150000000.00, NULL, 'Modal awal periode', '2025-07-15 00:00:00', 1),
(250, 1, 2025, 401, 'laba_rugi', 12000000.00, 0.00, NULL, 'Tutup pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(251, 1, 2025, 311, 'laba_rugi', 0.00, 12000000.00, NULL, 'Tambah modal dari pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(252, 1, 2025, 311, 'modal_akhir', 0.00, 162000000.00, NULL, 'Modal akhir periode', '2025-07-15 00:00:00', 1),
(253, 2, 2025, 302, 'prive', 0.00, 5000000.00, NULL, 'Tutup prive - Prive', '2025-07-15 00:00:00', 1),
(254, 2, 2025, 311, 'prive', 5000000.00, 0.00, NULL, 'Kurangi modal karena prive - Prive', '2025-07-15 00:00:00', 1),
(255, 2, 2025, 526, 'laba_rugi', 0.00, 8500000.00, NULL, 'Tutup beban - Salaries and Wages Expense', '2025-07-15 00:00:00', 1),
(256, 2, 2025, 311, 'laba_rugi', 8500000.00, 0.00, NULL, 'Kurangi modal dari beban - Salaries and Wages Expense', '2025-07-15 00:00:00', 1),
(257, 2, 2025, 532, 'laba_rugi', 0.00, 2300000.00, NULL, 'Tutup beban - Utilities Expense', '2025-07-15 00:00:00', 1),
(258, 2, 2025, 311, 'laba_rugi', 2300000.00, 0.00, NULL, 'Kurangi modal dari beban - Utilities Expense', '2025-07-15 00:00:00', 1),
(259, 2, 2025, 311, 'modal_akhir', 0.00, 146200000.00, NULL, 'Modal akhir periode', '2025-07-15 00:00:00', 1),
(260, 3, 2025, 400, 'laba_rugi', 12500000.00, 0.00, NULL, 'Tutup pendapatan - Service Revenue', '2025-07-15 00:00:00', 1),
(261, 3, 2025, 311, 'laba_rugi', 0.00, 12500000.00, NULL, 'Tambah modal dari pendapatan - Service Revenue', '2025-07-15 00:00:00', 1),
(262, 3, 2025, 401, 'laba_rugi', 22000000.00, 0.00, NULL, 'Tutup pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(263, 3, 2025, 311, 'laba_rugi', 0.00, 22000000.00, NULL, 'Tambah modal dari pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(264, 3, 2025, 311, 'modal_akhir', 0.00, 180700000.00, NULL, 'Modal akhir periode', '2025-07-15 00:00:00', 1),
(265, 4, 2025, 401, 'laba_rugi', 18000000.00, 0.00, NULL, 'Tutup pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(266, 4, 2025, 311, 'laba_rugi', 0.00, 18000000.00, NULL, 'Tambah modal dari pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(267, 4, 2025, 526, 'laba_rugi', 0.00, 9000000.00, NULL, 'Tutup beban - Salaries and Wages Expense', '2025-07-15 00:00:00', 1),
(268, 4, 2025, 311, 'laba_rugi', 9000000.00, 0.00, NULL, 'Kurangi modal dari beban - Salaries and Wages Expense', '2025-07-15 00:00:00', 1),
(269, 4, 2025, 532, 'laba_rugi', 0.00, 1500000.00, NULL, 'Tutup beban - Utilities Expense', '2025-07-15 00:00:00', 1),
(270, 4, 2025, 311, 'laba_rugi', 1500000.00, 0.00, NULL, 'Kurangi modal dari beban - Utilities Expense', '2025-07-15 00:00:00', 1),
(271, 4, 2025, 311, 'modal_akhir', 0.00, 188200000.00, NULL, 'Modal akhir periode', '2025-07-15 00:00:00', 1),
(272, 5, 2025, 401, 'laba_rugi', 30000000.00, 0.00, NULL, 'Tutup pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(273, 5, 2025, 311, 'laba_rugi', 0.00, 30000000.00, NULL, 'Tambah modal dari pendapatan - Sales Revenue', '2025-07-15 00:00:00', 1),
(274, 5, 2025, 526, 'laba_rugi', 0.00, 9500000.00, NULL, 'Tutup beban - Salaries and Wages Expense', '2025-07-15 00:00:00', 1),
(275, 5, 2025, 311, 'laba_rugi', 9500000.00, 0.00, NULL, 'Kurangi modal dari beban - Salaries and Wages Expense', '2025-07-15 00:00:00', 1),
(276, 5, 2025, 532, 'laba_rugi', 0.00, 2750000.00, NULL, 'Tutup beban - Utilities Expense', '2025-07-15 00:00:00', 1),
(277, 5, 2025, 311, 'laba_rugi', 2750000.00, 0.00, NULL, 'Kurangi modal dari beban - Utilities Expense', '2025-07-15 00:00:00', 1),
(278, 5, 2025, 311, 'modal_akhir', 0.00, 205950000.00, NULL, 'Modal akhir periode', '2025-07-15 00:00:00', 1),
(279, 6, 2025, 302, 'prive', 0.00, 4000000.00, NULL, 'Tutup prive - Prive', '2025-07-15 00:00:00', 1),
(280, 6, 2025, 311, 'prive', 4000000.00, 0.00, NULL, 'Kurangi modal karena prive - Prive', '2025-07-15 00:00:00', 1),
(281, 6, 2025, 400, 'laba_rugi', 17500000.00, 0.00, NULL, 'Tutup pendapatan - Service Revenue', '2025-07-15 00:00:00', 1),
(282, 6, 2025, 311, 'laba_rugi', 0.00, 17500000.00, NULL, 'Tambah modal dari pendapatan - Service Revenue', '2025-07-15 00:00:00', 1),
(283, 6, 2025, 311, 'modal_akhir', 0.00, 219450000.00, NULL, 'Modal akhir periode', '2025-07-15 00:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `detail_reaksi`
--

CREATE TABLE `detail_reaksi` (
  `id_detail_reaksi` int NOT NULL,
  `id_reaksi` int NOT NULL,
  `id_akun` int NOT NULL,
  `debit_kredit` varchar(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `detail_reaksi`
--

INSERT INTO `detail_reaksi` (`id_detail_reaksi`, `id_reaksi`, `id_akun`, `debit_kredit`) VALUES
(1, 1, 101, 'D'),
(2, 1, 102, 'D'),
(3, 1, 311, 'K'),
(4, 2, 157, 'D'),
(5, 2, 160, 'D'),
(6, 2, 101, 'K'),
(7, 2, 102, 'K'),
(8, 3, 157, 'D'),
(9, 3, 160, 'D'),
(10, 3, 201, 'K'),
(11, 4, 529, 'D'),
(12, 4, 101, 'K'),
(13, 4, 102, 'K'),
(14, 5, 120, 'D'),
(15, 5, 101, 'K'),
(16, 5, 102, 'K'),
(17, 6, 120, 'D'),
(18, 6, 201, 'K'),
(19, 7, 101, 'D'),
(20, 7, 102, 'D'),
(21, 7, 401, 'K'),
(22, 8, 112, 'D'),
(23, 8, 401, 'K'),
(24, 9, 526, 'D'),
(25, 9, 101, 'K'),
(26, 9, 102, 'K'),
(27, 10, 532, 'D'),
(28, 10, 101, 'K'),
(29, 10, 102, 'K'),
(30, 11, 101, 'D'),
(31, 11, 102, 'D'),
(32, 11, 112, 'K'),
(33, 12, 201, 'D'),
(34, 12, 101, 'K'),
(35, 12, 102, 'K'),
(36, 13, 511, 'D'),
(37, 13, 157, 'K'),
(38, 13, 160, 'K'),
(39, 14, 311, 'D'),
(40, 14, 101, 'K'),
(41, 14, 102, 'K'),
(42, 15, 126, 'D'),
(43, 15, 101, 'K'),
(44, 15, 102, 'K'),
(45, 16, 126, 'D'),
(46, 16, 201, 'K'),
(47, 17, 101, 'D'),
(48, 17, 102, 'D'),
(49, 17, 400, 'K'),
(50, 18, 112, 'D'),
(51, 18, 400, 'K'),
(52, 19, 200, 'D'),
(53, 19, 101, 'K'),
(54, 19, 102, 'K'),
(55, 20, 101, 'D'),
(56, 20, 102, 'D'),
(57, 20, 200, 'K'),
(58, 21, 131, 'D'),
(59, 21, 101, 'K'),
(60, 21, 102, 'K'),
(61, 22, 132, 'D'),
(62, 22, 101, 'K'),
(63, 22, 102, 'K'),
(64, 23, 133, 'D'),
(65, 23, 101, 'K'),
(66, 23, 102, 'K'),
(67, 24, 137, 'D'),
(68, 24, 101, 'K'),
(69, 24, 102, 'K'),
(70, 25, 140, 'D'),
(71, 25, 101, 'K'),
(72, 25, 102, 'K'),
(73, 26, 529, 'D'),
(74, 26, 131, 'K'),
(75, 27, 533, 'D'),
(76, 27, 132, 'K'),
(77, 28, 534, 'D'),
(78, 28, 133, 'K'),
(79, 29, 535, 'D'),
(80, 29, 137, 'K'),
(81, 30, 536, 'D'),
(82, 30, 140, 'K'),
(87, 31, 134, 'D'),
(88, 31, 101, 'K'),
(89, 31, 102, 'K'),
(90, 32, 135, 'D'),
(91, 32, 101, 'K'),
(92, 32, 102, 'K'),
(93, 33, 136, 'D'),
(94, 33, 101, 'K'),
(95, 33, 102, 'K'),
(96, 34, 138, 'D'),
(97, 34, 101, 'K'),
(98, 34, 102, 'K'),
(99, 35, 139, 'D'),
(100, 35, 101, 'K'),
(101, 35, 102, 'K'),
(102, 36, 141, 'D'),
(103, 36, 101, 'K'),
(104, 36, 102, 'K'),
(105, 37, 142, 'D'),
(106, 37, 101, 'K'),
(107, 37, 102, 'K'),
(108, 38, 143, 'D'),
(109, 38, 101, 'K'),
(110, 38, 102, 'K'),
(111, 39, 144, 'D'),
(112, 39, 101, 'K'),
(113, 39, 102, 'K'),
(114, 40, 532, 'D'),
(115, 40, 134, 'K'),
(116, 41, 537, 'D'),
(117, 41, 135, 'K'),
(118, 42, 538, 'D'),
(119, 42, 136, 'K'),
(120, 43, 539, 'D'),
(121, 43, 138, 'K'),
(122, 44, 540, 'D'),
(123, 44, 139, 'K'),
(124, 45, 541, 'D'),
(125, 45, 141, 'K'),
(126, 46, 542, 'D'),
(127, 46, 142, 'K'),
(128, 47, 543, 'D'),
(129, 47, 143, 'K'),
(130, 48, 544, 'D'),
(131, 48, 144, 'K');

-- --------------------------------------------------------

--
-- Table structure for table `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id_detail_transaksi` int NOT NULL,
  `id_transaksi` int NOT NULL,
  `id_akun` int NOT NULL,
  `debit_kredit` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nilai` int NOT NULL,
  `penyesuaian` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id_detail_transaksi`, `id_transaksi`, `id_akun`, `debit_kredit`, `nilai`, `penyesuaian`) VALUES
(1, 1, 101, 'D', 45000000, 0),
(2, 1, 102, 'D', 105000000, 0),
(3, 1, 311, 'K', 150000000, 0),
(4, 2, 157, 'D', 15000000, 0),
(5, 2, 101, 'K', 15000000, 0),
(6, 3, 131, 'D', 18000000, 0),
(7, 3, 102, 'K', 18000000, 0),
(8, 4, 120, 'D', 35000000, 0),
(9, 4, 201, 'K', 35000000, 0),
(10, 5, 101, 'D', 12000000, 0),
(11, 5, 401, 'K', 12000000, 0),
(12, 6, 526, 'D', 8500000, 0),
(13, 6, 101, 'K', 8500000, 0),
(14, 7, 532, 'D', 2300000, 0),
(15, 7, 102, 'K', 2300000, 0),
(16, 8, 101, 'D', 7500000, 0),
(17, 8, 112, 'K', 7500000, 0),
(18, 9, 126, 'D', 5500000, 0),
(19, 9, 101, 'K', 5500000, 0),
(20, 10, 302, 'D', 5000000, 0),
(21, 10, 101, 'K', 5000000, 0),
(22, 11, 132, 'D', 12000000, 0),
(23, 11, 102, 'K', 12000000, 0),
(24, 12, 112, 'D', 22000000, 0),
(25, 12, 401, 'K', 22000000, 0),
(26, 13, 201, 'D', 15000000, 0),
(27, 13, 102, 'K', 15000000, 0),
(28, 14, 101, 'D', 12500000, 0),
(29, 14, 400, 'K', 12500000, 0),
(30, 15, 157, 'D', 10000000, 0),
(31, 15, 102, 'K', 10000000, 0),
(32, 16, 133, 'D', 6000000, 0),
(33, 16, 101, 'K', 6000000, 0),
(34, 17, 101, 'D', 50000000, 0),
(35, 17, 200, 'K', 50000000, 0),
(36, 18, 526, 'D', 9000000, 0),
(37, 18, 101, 'K', 9000000, 0),
(38, 19, 532, 'D', 1500000, 0),
(39, 19, 102, 'K', 1500000, 0),
(40, 20, 101, 'D', 18000000, 0),
(41, 20, 401, 'K', 18000000, 0),
(42, 21, 131, 'D', 12000000, 0),
(43, 21, 101, 'K', 12000000, 0),
(44, 22, 137, 'D', 24000000, 0),
(45, 22, 102, 'K', 24000000, 0),
(46, 23, 112, 'D', 30000000, 0),
(47, 23, 401, 'K', 30000000, 0),
(48, 24, 526, 'D', 9500000, 0),
(49, 24, 101, 'K', 9500000, 0),
(50, 25, 532, 'D', 2750000, 0),
(51, 25, 102, 'K', 2750000, 0),
(52, 26, 101, 'D', 20000000, 0),
(53, 26, 112, 'K', 20000000, 0),
(54, 27, 120, 'D', 28000000, 0),
(55, 27, 201, 'K', 28000000, 0),
(56, 28, 102, 'D', 17500000, 0),
(57, 28, 400, 'K', 17500000, 0),
(58, 29, 133, 'D', 4000000, 0),
(59, 29, 101, 'K', 4000000, 0),
(60, 30, 302, 'D', 4000000, 0),
(61, 30, 102, 'K', 4000000, 0),
(62, 31, 134, 'D', 1000000, 0),
(63, 31, 101, 'K', 1000000, 0),
(64, 32, 143, 'D', 500000, 0),
(65, 32, 101, 'K', 500000, 0),
(66, 33, 526, 'D', 10000000, 0),
(67, 33, 101, 'K', 10000000, 0),
(68, 34, 101, 'D', 25000000, 0),
(69, 34, 401, 'K', 25000000, 0),
(70, 35, 529, 'D', 1500000, 1),
(71, 35, 131, 'K', 1500000, 1),
(72, 36, 533, 'D', 1000000, 1),
(73, 36, 132, 'K', 1000000, 1),
(74, 37, 534, 'D', 1000000, 1),
(75, 37, 133, 'K', 1000000, 1),
(76, 38, 141, 'D', 1000, 0),
(77, 38, 101, 'K', 1000, 0),
(78, 38, 102, 'K', 0, 0),
(79, 39, 541, 'D', 500, 1),
(80, 39, 141, 'K', 500, 1);

-- --------------------------------------------------------

--
-- Table structure for table `jurnal_penyesuaian`
--

CREATE TABLE `jurnal_penyesuaian` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `akun_debit` int NOT NULL,
  `akun_kredit` int NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `referensi_jurnal` int DEFAULT NULL,
  `dibuat_oleh` int NOT NULL,
  `waktu_input` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','processed') DEFAULT 'pending',
  `tanggal_eksekusi` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jurnal_penyesuaian`
--

INSERT INTO `jurnal_penyesuaian` (`id`, `tanggal`, `akun_debit`, `akun_kredit`, `jumlah`, `keterangan`, `referensi_jurnal`, `dibuat_oleh`, `waktu_input`, `status`, `tanggal_eksekusi`) VALUES
(1, '2025-07-20', 529, 131, 1500000.00, 'Penyesuaian sewa terpakai Juli', 35, 1, '2025-07-14 11:50:07', 'processed', '2025-07-20 14:00:00'),
(2, '2025-07-25', 533, 132, 1000000.00, 'Penyesuaian asuransi terpakai Juli', 36, 1, '2025-07-14 11:50:07', 'processed', '2025-07-25 14:00:00'),
(3, '2025-07-31', 534, 133, 1000000.00, 'Penyesuaian iklan terpakai Juli', 37, 1, '2025-07-14 11:50:07', 'processed', '2025-07-31 14:00:00'),
(4, '2025-07-14', 541, 141, 500.00, 'Penyesuaian bulanan untuk test 1', 39, 1, '2025-07-14 13:07:49', 'processed', '2025-07-14 20:08:13');

-- --------------------------------------------------------

--
-- Table structure for table `prepaid_adjustment_templates`
--

CREATE TABLE `prepaid_adjustment_templates` (
  `id` int NOT NULL,
  `prepaid_account_id` int NOT NULL,
  `expense_account_id` int NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `prepaid_adjustment_templates`
--

INSERT INTO `prepaid_adjustment_templates` (`id`, `prepaid_account_id`, `expense_account_id`, `description`) VALUES
(1, 131, 529, 'Sewa yang sudah terpakai'),
(2, 132, 533, 'Asuransi yang sudah terpakai'),
(3, 133, 534, 'Iklan yang sudah terpakai'),
(4, 137, 535, 'Lisensi software yang sudah terpakai'),
(5, 140, 536, 'Langganan yang sudah terpakai'),
(6, 131, 529, 'Sewa yang sudah terpakai'),
(7, 132, 533, 'Asuransi yang sudah terpakai'),
(8, 133, 534, 'Iklan yang sudah terpakai'),
(9, 137, 535, 'Lisensi software yang sudah terpakai'),
(10, 140, 536, 'Langganan yang sudah terpakai'),
(11, 134, 532, 'Utilitas yang sudah terpakai'),
(12, 135, 537, 'Biaya hukum yang sudah terpakai'),
(13, 136, 538, 'Pemeliharaan yang sudah terpakai'),
(14, 138, 539, 'Biaya perjalanan yang sudah terpakai'),
(15, 139, 540, 'Biaya pelatihan yang sudah terpakai'),
(16, 141, 541, 'Pajak yang sudah terpakai'),
(17, 142, 542, 'Bunga yang sudah terpakai'),
(18, 143, 543, 'Komisi yang sudah terpakai'),
(19, 144, 544, 'Perlengkapan kantor yang sudah terpakai');

-- --------------------------------------------------------

--
-- Table structure for table `prepaid_balances`
--

CREATE TABLE `prepaid_balances` (
  `id` int NOT NULL,
  `account_id` int NOT NULL,
  `expense_account_id` int NOT NULL,
  `current_balance` decimal(15,2) NOT NULL,
  `original_transaction_id` int DEFAULT NULL,
  `adjustment_transaction_id` int DEFAULT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `adjusted_amount` decimal(15,2) NOT NULL,
  `remaining_amount` decimal(15,2) NOT NULL,
  `adjustment_date` date NOT NULL,
  `notes` text,
  `status` enum('active','fully_adjusted') DEFAULT 'active',
  `period_covered` int DEFAULT NULL,
  `period_unit` enum('month','day','year') DEFAULT 'month'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `prepaid_balances`
--

INSERT INTO `prepaid_balances` (`id`, `account_id`, `expense_account_id`, `current_balance`, `original_transaction_id`, `adjustment_transaction_id`, `original_amount`, `adjusted_amount`, `remaining_amount`, `adjustment_date`, `notes`, `status`, `period_covered`, `period_unit`) VALUES
(1, 131, 529, 16500000.00, 3, NULL, 18000000.00, 1500000.00, 16500000.00, '2025-07-20', NULL, 'active', 11, 'month'),
(2, 132, 533, 11000000.00, 11, NULL, 12000000.00, 1000000.00, 11000000.00, '2025-07-25', NULL, 'active', 11, 'month'),
(3, 133, 534, 5000000.00, 16, NULL, 6000000.00, 1000000.00, 5000000.00, '2025-07-31', NULL, 'active', 5, 'month'),
(4, 134, 532, 1000000.00, 31, NULL, 1000000.00, 0.00, 1000000.00, '2025-07-01', NULL, 'active', 1, 'month'),
(5, 143, 543, 500000.00, 32, NULL, 500000.00, 0.00, 500000.00, '2025-07-05', NULL, 'active', 1, 'month');

-- --------------------------------------------------------

--
-- Table structure for table `reaksi`
--

CREATE TABLE `reaksi` (
  `id_reaksi` int NOT NULL,
  `nama_reaksi` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reaksi`
--

INSERT INTO `reaksi` (`id_reaksi`, `nama_reaksi`) VALUES
(1, 'Penyetoran Modal'),
(2, 'Pembelian Aset Tetap Tunai'),
(3, 'Pembelian Aset Tetap Kredit'),
(4, 'Pembayaran Beban Sewa'),
(5, 'Pembelian Persediaan Tunai'),
(6, 'Pembelian Persediaan Kredit'),
(7, 'Penjualan Barang Tunai'),
(8, 'Penjualan Barang Kredit'),
(9, 'Pembayaran Beban Gaji'),
(10, 'Pembayaran Beban Utilitas'),
(11, 'Penerimaan Piutang Usaha'),
(12, 'Pembayaran Utang Usaha'),
(13, 'Pencatatan Beban Penyusutan'),
(14, 'Pengambilan Prive Pemilik'),
(15, 'Pembelian Perlengkapan Tunai'),
(16, 'Pembelian Perlengkapan Kredit'),
(17, 'Pendapatan Jasa Tunai'),
(18, 'Pendapatan Jasa Kredit'),
(19, 'Pembayaran Utang Bank'),
(20, 'Penerimaan Pinjaman Bank'),
(21, 'Pembayaran Sewa di Muka'),
(22, 'Pembayaran Asuransi di Muka'),
(23, 'Pembayaran Iklan di Muka'),
(24, 'Pembayaran Lisensi Software di Muka'),
(25, 'Pembayaran Langganan di Muka'),
(26, 'Penyesuaian Sewa yang Terpakai'),
(27, 'Penyesuaian Asuransi yang Terpakai'),
(28, 'Penyesuaian Iklan yang Terpakai'),
(29, 'Penyesuaian Lisensi yang Terpakai'),
(30, 'Penyesuaian Langganan yang Terpakai'),
(31, 'Pembayaran Utilitas di Muka'),
(32, 'Pembayaran Biaya Hukum di Muka'),
(33, 'Pembayaran Pemeliharaan di Muka'),
(34, 'Pembayaran Biaya Perjalanan di Muka'),
(35, 'Pembayaran Biaya Pelatihan di Muka'),
(36, 'Pembayaran Pajak di Muka'),
(37, 'Pembayaran Bunga di Muka'),
(38, 'Pembayaran Komisi di Muka'),
(39, 'Pembayaran Perlengkapan Kantor di Muka'),
(40, 'Penyesuaian Utilitas yang Terpakai'),
(41, 'Penyesuaian Biaya Hukum yang Terpakai'),
(42, 'Penyesuaian Pemeliharaan yang Terpakai'),
(43, 'Penyesuaian Biaya Perjalanan yang Terpakai'),
(44, 'Penyesuaian Biaya Pelatihan yang Terpakai'),
(45, 'Penyesuaian Pajak yang Terpakai'),
(46, 'Penyesuaian Bunga yang Terpakai'),
(47, 'Penyesuaian Komisi yang Terpakai'),
(48, 'Penyesuaian Perlengkapan Kantor yang Terpakai');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int NOT NULL,
  `tgl_transaksi` date NOT NULL,
  `nama_transaksi` varchar(100) NOT NULL,
  `post` varchar(1) NOT NULL DEFAULT '0',
  `hapus` varchar(1) NOT NULL DEFAULT '0',
  `adjustment_end_date` date DEFAULT NULL,
  `needs_adjustment` tinyint(1) NOT NULL DEFAULT '0',
  `adjustment_start_date` date DEFAULT NULL,
  `penyesuaian` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id_transaksi`, `tgl_transaksi`, `nama_transaksi`, `post`, `hapus`, `adjustment_end_date`, `needs_adjustment`, `adjustment_start_date`, `penyesuaian`) VALUES
(1, '2025-01-01', 'Setoran Modal Awal 2025', '0', '0', NULL, 0, NULL, 0),
(2, '2025-01-05', 'Pembelian peralatan kantor', '0', '0', NULL, 0, NULL, 0),
(3, '2025-01-10', 'Pembayaran sewa kantor 1 tahun', '0', '0', NULL, 0, NULL, 0),
(4, '2025-01-15', 'Pembelian persediaan barang', '0', '0', NULL, 0, NULL, 0),
(5, '2025-01-20', 'Penjualan produk A', '0', '0', NULL, 0, NULL, 0),
(6, '2025-02-05', 'Gaji karyawan Februari', '0', '0', NULL, 0, NULL, 0),
(7, '2025-02-10', 'Pembayaran utilitas', '0', '0', NULL, 0, NULL, 0),
(8, '2025-02-15', 'Penerimaan piutang', '0', '0', NULL, 0, NULL, 0),
(9, '2025-02-20', 'Pembelian perlengkapan', '0', '0', NULL, 0, NULL, 0),
(10, '2025-02-25', 'Pengambilan prive', '0', '0', NULL, 0, NULL, 0),
(11, '2025-03-01', 'Pembayaran asuransi 1 tahun', '0', '0', NULL, 0, NULL, 0),
(12, '2025-03-05', 'Penjualan kredit ke PT X', '0', '0', NULL, 0, NULL, 0),
(13, '2025-03-10', 'Pelunasan utang', '0', '0', NULL, 0, NULL, 0),
(14, '2025-03-15', 'Pendapatan jasa konsultasi', '0', '0', NULL, 0, NULL, 0),
(15, '2025-03-20', 'Pembelian meja kerja', '0', '0', NULL, 0, NULL, 0),
(16, '2025-04-05', 'Pembayaran iklan 6 bulan', '0', '0', NULL, 0, NULL, 0),
(17, '2025-04-10', 'Pinjaman bank', '0', '0', NULL, 0, NULL, 0),
(18, '2025-04-15', 'Gaji karyawan April', '0', '0', NULL, 0, NULL, 0),
(19, '2025-04-20', 'Tagihan internet', '0', '0', NULL, 0, NULL, 0),
(20, '2025-04-25', 'Penjualan produk B', '0', '0', NULL, 0, NULL, 0),
(21, '2025-05-01', 'Pembayaran sewa gudang', '0', '0', NULL, 0, NULL, 0),
(22, '2025-05-05', 'Pembelian lisensi software', '0', '0', NULL, 0, NULL, 0),
(23, '2025-05-10', 'Penjualan ke PT Y', '0', '0', NULL, 0, NULL, 0),
(24, '2025-05-15', 'Gaji karyawan Mei', '0', '0', NULL, 0, NULL, 0),
(25, '2025-05-20', 'Tagihan air', '0', '0', NULL, 0, NULL, 0),
(26, '2025-06-01', 'Pelunasan piutang', '0', '0', NULL, 0, NULL, 0),
(27, '2025-06-05', 'Pembelian bahan baku', '0', '0', NULL, 0, NULL, 0),
(28, '2025-06-10', 'Pendapatan training', '0', '0', NULL, 0, NULL, 0),
(29, '2025-06-15', 'Pembayaran iklan', '0', '0', NULL, 0, NULL, 0),
(30, '2025-06-20', 'Pengambilan prive', '0', '0', NULL, 0, NULL, 0),
(31, '2025-07-01', 'Pembayaran utilitas di muka', '0', '0', NULL, 0, NULL, 0),
(32, '2025-07-05', 'Pembayaran komisi di muka', '0', '0', NULL, 0, NULL, 0),
(33, '2025-07-10', 'Gaji karyawan Juli', '0', '0', NULL, 0, NULL, 0),
(34, '2025-07-15', 'Penjualan produk C', '0', '0', NULL, 0, NULL, 0),
(35, '2025-07-20', 'Penyesuaian sewa terpakai', '0', '0', NULL, 0, NULL, 1),
(36, '2025-07-25', 'Penyesuaian asuransi terpakai', '0', '0', NULL, 0, NULL, 1),
(37, '2025-07-31', 'Penyesuaian iklan terpakai', '0', '0', NULL, 0, NULL, 1),
(38, '2025-07-14', 'test 1', '0', '0', '2025-08-14', 1, '2025-07-14', 0),
(39, '2025-07-31', 'Penyesuaian bulanan untuk test 1', '0', '0', NULL, 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `roles` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `roles`) VALUES
(1, 'admin', '$2a$12$2WvTm6saLvagDIber0dWA.1jbZoT5BEjaiWrLZhgRf/c1lKbphic.', 'Administrator', 'admin'),
(2, 'kasir', '$2y$10$HSreZLYq1psQIAi107wCfuLJQ03jVs.xCDblhUwZoPGOAFYlGjeeq', 'Kasir', 'cashier'),
(3, 'akuntan', '$2y$10$SVsOYLm0ppniv5kdlDhf4eXWxY8EkxXlQO6YMD1G7SZftGnL.kFvy', 'Akuntan', 'accounting');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `akun`
--
ALTER TABLE `akun`
  ADD PRIMARY KEY (`id_akun`);

--
-- Indexes for table `closing`
--
ALTER TABLE `closing`
  ADD PRIMARY KEY (`id_closing`),
  ADD KEY `id_akun` (`id_akun`),
  ADD KEY `referensi_transaksi` (`referensi_transaksi`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `detail_reaksi`
--
ALTER TABLE `detail_reaksi`
  ADD PRIMARY KEY (`id_detail_reaksi`),
  ADD KEY `id_reaksi` (`id_reaksi`),
  ADD KEY `id_akun` (`id_akun`);

--
-- Indexes for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id_detail_transaksi`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `id_akun` (`id_akun`);

--
-- Indexes for table `jurnal_penyesuaian`
--
ALTER TABLE `jurnal_penyesuaian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `akun_debit` (`akun_debit`),
  ADD KEY `akun_kredit` (`akun_kredit`),
  ADD KEY `referensi_jurnal` (`referensi_jurnal`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indexes for table `prepaid_adjustment_templates`
--
ALTER TABLE `prepaid_adjustment_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prepaid_account_id` (`prepaid_account_id`),
  ADD KEY `expense_account_id` (`expense_account_id`);

--
-- Indexes for table `prepaid_balances`
--
ALTER TABLE `prepaid_balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `expense_account_id` (`expense_account_id`),
  ADD KEY `prepaid_balances_ibfk_3` (`original_transaction_id`),
  ADD KEY `prepaid_balances_ibfk_4` (`adjustment_transaction_id`);

--
-- Indexes for table `reaksi`
--
ALTER TABLE `reaksi`
  ADD PRIMARY KEY (`id_reaksi`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `closing`
--
ALTER TABLE `closing`
  MODIFY `id_closing` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=284;

--
-- AUTO_INCREMENT for table `detail_reaksi`
--
ALTER TABLE `detail_reaksi`
  MODIFY `id_detail_reaksi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id_detail_transaksi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `jurnal_penyesuaian`
--
ALTER TABLE `jurnal_penyesuaian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `prepaid_adjustment_templates`
--
ALTER TABLE `prepaid_adjustment_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `prepaid_balances`
--
ALTER TABLE `prepaid_balances`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `closing`
--
ALTER TABLE `closing`
  ADD CONSTRAINT `closing_ibfk_1` FOREIGN KEY (`id_akun`) REFERENCES `akun` (`id_akun`),
  ADD CONSTRAINT `closing_ibfk_2` FOREIGN KEY (`referensi_transaksi`) REFERENCES `transaksi` (`id_transaksi`),
  ADD CONSTRAINT `closing_ibfk_3` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `detail_reaksi`
--
ALTER TABLE `detail_reaksi`
  ADD CONSTRAINT `detail_reaksi_ibfk_1` FOREIGN KEY (`id_reaksi`) REFERENCES `reaksi` (`id_reaksi`),
  ADD CONSTRAINT `detail_reaksi_ibfk_2` FOREIGN KEY (`id_akun`) REFERENCES `akun` (`id_akun`);

--
-- Constraints for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `detail_transaksi_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`),
  ADD CONSTRAINT `detail_transaksi_ibfk_2` FOREIGN KEY (`id_akun`) REFERENCES `akun` (`id_akun`);

--
-- Constraints for table `jurnal_penyesuaian`
--
ALTER TABLE `jurnal_penyesuaian`
  ADD CONSTRAINT `jurnal_penyesuaian_ibfk_1` FOREIGN KEY (`akun_debit`) REFERENCES `akun` (`id_akun`),
  ADD CONSTRAINT `jurnal_penyesuaian_ibfk_2` FOREIGN KEY (`akun_kredit`) REFERENCES `akun` (`id_akun`),
  ADD CONSTRAINT `jurnal_penyesuaian_ibfk_3` FOREIGN KEY (`referensi_jurnal`) REFERENCES `transaksi` (`id_transaksi`),
  ADD CONSTRAINT `jurnal_penyesuaian_ibfk_4` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`);

--
-- Constraints for table `prepaid_adjustment_templates`
--
ALTER TABLE `prepaid_adjustment_templates`
  ADD CONSTRAINT `prepaid_adjustment_templates_ibfk_1` FOREIGN KEY (`prepaid_account_id`) REFERENCES `akun` (`id_akun`),
  ADD CONSTRAINT `prepaid_adjustment_templates_ibfk_2` FOREIGN KEY (`expense_account_id`) REFERENCES `akun` (`id_akun`);

--
-- Constraints for table `prepaid_balances`
--
ALTER TABLE `prepaid_balances`
  ADD CONSTRAINT `prepaid_balances_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `akun` (`id_akun`),
  ADD CONSTRAINT `prepaid_balances_ibfk_2` FOREIGN KEY (`expense_account_id`) REFERENCES `akun` (`id_akun`),
  ADD CONSTRAINT `prepaid_balances_ibfk_3` FOREIGN KEY (`original_transaction_id`) REFERENCES `transaksi` (`id_transaksi`),
  ADD CONSTRAINT `prepaid_balances_ibfk_4` FOREIGN KEY (`adjustment_transaction_id`) REFERENCES `transaksi` (`id_transaksi`);

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `EVENT_CLOSING_BULANAN` ON SCHEDULE EVERY 1 MONTH STARTS '2025-05-28 01:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
DECLARE V_BULAN INT;
DECLARE V_TAHUN INT;
-- TUTUP BULAN SEBELUMNYA
SET V_BULAN = MONTH(CURRENT_DATE - INTERVAL 1 MONTH);
SET V_TAHUN = YEAR(CURRENT_DATE - INTERVAL 1 MONTH);
CALL PROSES_CLOSING(V_BULAN, V_TAHUN);
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
