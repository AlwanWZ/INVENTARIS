-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 10, 2026 at 12:23 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventaris`
--

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `id` int NOT NULL,
  `kode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kode_barang` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kategori` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stok` int DEFAULT '0',
  `stok_reserved` int DEFAULT '0',
  `stok_available` int DEFAULT '0',
  `harga` int DEFAULT '0',
  `harga_jual` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('aktif','nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `satuan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stok_min` int DEFAULT '10',
  `kategori_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `kode_customer` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `perusahaan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_hp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `kota` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_stok`
--

CREATE TABLE `log_stok` (
  `id` int NOT NULL,
  `barang_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `tipe` enum('masuk','keluar','ng') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jumlah` int NOT NULL,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penerimaan`
--

CREATE TABLE `penerimaan` (
  `id` int NOT NULL,
  `nomor_penerimaan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pesanan_id` int DEFAULT NULL,
  `spk_id` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `status` enum('draft','received','checked','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penerimaan_items`
--

CREATE TABLE `penerimaan_items` (
  `id` int NOT NULL,
  `penerimaan_id` int DEFAULT NULL,
  `barang_id` int DEFAULT NULL,
  `qty_order` int DEFAULT NULL,
  `qty_diterima` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int NOT NULL,
  `nomor_pengeluaran` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spk_id` int DEFAULT NULL,
  `pesanan_id` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `status` enum('draft','picking','packing','shipped','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stok_before` int DEFAULT '0',
  `stok_after` int DEFAULT '0',
  `pic` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran_items`
--

CREATE TABLE `pengeluaran_items` (
  `id` int NOT NULL,
  `pengeluaran_id` int DEFAULT NULL,
  `barang_id` int DEFAULT NULL,
  `qty` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int NOT NULL,
  `nomor_pesanan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `status` enum('draft','pending_review','approved','rejected','completed') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `alasan_reject` text COLLATE utf8mb4_general_ci,
  `status_stok` enum('draft','reserved','partial','ready','completed') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `approval_date` datetime DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `tanggal_pengiriman` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `customer_id` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pesanan_items`
--

CREATE TABLE `pesanan_items` (
  `id` int NOT NULL,
  `pesanan_id` int NOT NULL,
  `barang_id` int DEFAULT NULL,
  `kode_material` varchar(100) DEFAULT NULL,
  `nama_material` varchar(255) DEFAULT NULL,
  `uom` varchar(20) DEFAULT 'Sheet',
  `qty` int NOT NULL,
  `qty_dikirim` int DEFAULT '0',
  `qty_available` int DEFAULT '0',
  `qty_pending` int DEFAULT '0',
  `reserved_qty` int DEFAULT '0',
  `is_reserved` enum('no','yes') DEFAULT 'no',
  `harga_satuan` decimal(15,2) DEFAULT NULL,
  `diskon` decimal(15,2) DEFAULT '0.00',
  `amount` decimal(15,2) DEFAULT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spk`
--

CREATE TABLE `spk` (
  `id` int NOT NULL,
  `nomor_spk` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pesanan_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('draft','on_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `progress` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `spk_items`
--

CREATE TABLE `spk_items` (
  `id` int NOT NULL,
  `spk_id` int NOT NULL,
  `pic_id` int DEFAULT NULL,
  `barang_id` int DEFAULT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `stok_gudang` int DEFAULT '0',
  `stok_available` int DEFAULT '0',
  `qty_po` int DEFAULT '0',
  `qty_schedule` int DEFAULT '0',
  `qty_preparation` int DEFAULT '0',
  `qty_outstanding` int DEFAULT '0',
  `status_produksi` varchar(50) DEFAULT 'pending',
  `note` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_log`
--

CREATE TABLE `stok_log` (
  `id` int NOT NULL,
  `barang_id` int NOT NULL,
  `tipe_transaksi` enum('po_reserve','po_unreserve','verifikasi_add','pengeluaran_sub','adjustment') DEFAULT 'adjustment',
  `qty_change` int DEFAULT NULL,
  `stok_before` int DEFAULT NULL,
  `stok_after` int DEFAULT NULL,
  `stok_reserved_before` int DEFAULT NULL,
  `stok_reserved_after` int DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `keterangan` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_jalan`
--

CREATE TABLE `surat_jalan` (
  `id` int NOT NULL,
  `nomor_sj` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pengeluaran_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `tanggal_kirim` date DEFAULT NULL,
  `alamat_kirim` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `driver` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kendaraan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_general_ci,
  `status` enum('draft','dikirim','diterima') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `penerima` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waktu_terima` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_jalan_items`
--

CREATE TABLE `surat_jalan_items` (
  `id` int NOT NULL,
  `surat_jalan_id` int DEFAULT NULL,
  `barang_id` int DEFAULT NULL,
  `qty` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('marketing','gudang','manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `no_telpon` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `no_telpon`) VALUES
(5, 'MRK-001', '$2y$10$.QggMB4sdGNVKDo/1fuZSe9xAL/OuJTDnjrp2fcB6zcplhxHeCSba', 'marketing', NULL),
(13, 'marketing', '$2y$10$ytKpIhIM1KsnRDI08.P8x.zNAMQ5EI/0/jBOM/CdpyMnJPnidZdA2', 'marketing', '08896222'),
(17, 'MNG-001', '$2y$10$WDi7L8EjXjkMl93h2lT3puDuRiybHkQEmYBVQLWvtecL9k5c9Xedi', 'manager', '08896222'),
(18, 'GDG-001', '$2y$10$r57L8YmY.qtodFyqiUZACuKwqSU7t3XXHVb4YJFO8JOG2TriwSDrK', 'gudang', '');

-- --------------------------------------------------------

--
-- Table structure for table `verifikasi`
--

CREATE TABLE `verifikasi` (
  `id` int NOT NULL,
  `penerimaan_id` int DEFAULT NULL,
  `spk_id` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `pic` int DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `jenis` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'finish_good'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verifikasi_items`
--

CREATE TABLE `verifikasi_items` (
  `id` int NOT NULL,
  `verifikasi_id` int DEFAULT NULL,
  `barang_id` int DEFAULT NULL,
  `qty_masuk` int DEFAULT NULL,
  `qty_ok` int DEFAULT NULL,
  `keterangan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_produk` (`kode_barang`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_customer` (`kode_customer`);

--
-- Indexes for table `log_stok`
--
ALTER TABLE `log_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produk_id` (`barang_id`);

--
-- Indexes for table `penerimaan`
--
ALTER TABLE `penerimaan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penerimaan_items`
--
ALTER TABLE `penerimaan_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spk_id` (`spk_id`),
  ADD KEY `pic` (`pic`),
  ADD KEY `pesanan_id` (`pesanan_id`);

--
-- Indexes for table `pengeluaran_items`
--
ALTER TABLE `pengeluaran_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengeluaran_id` (`pengeluaran_id`),
  ADD KEY `produk_id` (`barang_id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_po` (`nomor_pesanan`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_customer` (`customer_id`);

--
-- Indexes for table `pesanan_items`
--
ALTER TABLE `pesanan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`pesanan_id`),
  ADD KEY `produk_id` (`barang_id`);

--
-- Indexes for table `spk`
--
ALTER TABLE `spk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_spk` (`nomor_spk`),
  ADD KEY `po_id` (`pesanan_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `pic` (`pic`);

--
-- Indexes for table `spk_items`
--
ALTER TABLE `spk_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stok_log`
--
ALTER TABLE `stok_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `produk_id` (`barang_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `tipe_transaksi` (`tipe_transaksi`);

--
-- Indexes for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_sj` (`nomor_sj`),
  ADD KEY `pengeluaran_id` (`pengeluaran_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surat_jalan_id` (`surat_jalan_id`),
  ADD KEY `produk_id` (`barang_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `verifikasi`
--
ALTER TABLE `verifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penerimaan_id` (`penerimaan_id`),
  ADD KEY `pic` (`pic`);

--
-- Indexes for table `verifikasi_items`
--
ALTER TABLE `verifikasi_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `verifikasi_id` (`verifikasi_id`),
  ADD KEY `produk_id` (`barang_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `log_stok`
--
ALTER TABLE `log_stok`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `penerimaan`
--
ALTER TABLE `penerimaan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `penerimaan_items`
--
ALTER TABLE `penerimaan_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `pengeluaran_items`
--
ALTER TABLE `pengeluaran_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `pesanan_items`
--
ALTER TABLE `pesanan_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `spk`
--
ALTER TABLE `spk`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `spk_items`
--
ALTER TABLE `spk_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `stok_log`
--
ALTER TABLE `stok_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `verifikasi`
--
ALTER TABLE `verifikasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `verifikasi_items`
--
ALTER TABLE `verifikasi_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `log_stok`
--
ALTER TABLE `log_stok`
  ADD CONSTRAINT `log_stok_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Constraints for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD CONSTRAINT `pengeluaran_ibfk_1` FOREIGN KEY (`spk_id`) REFERENCES `spk` (`id`),
  ADD CONSTRAINT `pengeluaran_ibfk_2` FOREIGN KEY (`pic`) REFERENCES `users` (`id`);

--
-- Constraints for table `pengeluaran_items`
--
ALTER TABLE `pengeluaran_items`
  ADD CONSTRAINT `pengeluaran_items_ibfk_1` FOREIGN KEY (`pengeluaran_id`) REFERENCES `pengeluaran` (`id`),
  ADD CONSTRAINT `pengeluaran_items_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pesanan_items`
--
ALTER TABLE `pesanan_items`
  ADD CONSTRAINT `pesanan_items_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pesanan_items_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Constraints for table `spk`
--
ALTER TABLE `spk`
  ADD CONSTRAINT `spk_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`),
  ADD CONSTRAINT `spk_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `spk_ibfk_3` FOREIGN KEY (`pic`) REFERENCES `users` (`id`);

--
-- Constraints for table `stok_log`
--
ALTER TABLE `stok_log`
  ADD CONSTRAINT `stok_log_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`),
  ADD CONSTRAINT `stok_log_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  ADD CONSTRAINT `surat_jalan_ibfk_1` FOREIGN KEY (`pengeluaran_id`) REFERENCES `pengeluaran` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surat_jalan_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `surat_jalan_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD CONSTRAINT `surat_jalan_items_ibfk_1` FOREIGN KEY (`surat_jalan_id`) REFERENCES `surat_jalan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `surat_jalan_items_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `verifikasi`
--
ALTER TABLE `verifikasi`
  ADD CONSTRAINT `verifikasi_ibfk_1` FOREIGN KEY (`penerimaan_id`) REFERENCES `penerimaan` (`id`),
  ADD CONSTRAINT `verifikasi_ibfk_2` FOREIGN KEY (`pic`) REFERENCES `users` (`id`);

--
-- Constraints for table `verifikasi_items`
--
ALTER TABLE `verifikasi_items`
  ADD CONSTRAINT `verifikasi_items_ibfk_1` FOREIGN KEY (`verifikasi_id`) REFERENCES `verifikasi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `verifikasi_items_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;