<?php
session_start();
require_once '../../../src/auth.php';
require_once '../../../src/config.php';

$produk_id = $_GET['produk_id'] ?? null;
if (!$produk_id) {
		header('Location: index.php');
		exit;
}

$produk = $pdo->prepare("SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE p.id = ?");
$produk->execute([$produk_id]);
$row = $produk->fetch(PDO::FETCH_ASSOC);
if (!$row) {
		echo '<div style="padding:2em">Produk tidak ditemukan.</div>';
		exit;
}


// Ambil range tanggal mutasi jika filter belum dipilih
$tgl_awal = $_GET['tgl_awal'] ?? null;
$tgl_akhir = $_GET['tgl_akhir'] ?? null;
if (!$tgl_awal || !$tgl_akhir) {
	$range = $pdo->prepare("SELECT MIN(tanggal) as min_tgl, MAX(tanggal) as max_tgl FROM log_stok WHERE produk_id = ?");
	$range->execute([$produk_id]);
	$r = $range->fetch(PDO::FETCH_ASSOC);
	if (!$tgl_awal) $tgl_awal = $r['min_tgl'] ?: date('Y-m-01');
	if (!$tgl_akhir) $tgl_akhir = $r['max_tgl'] ?: date('Y-m-d');
}

$mutasi = $pdo->prepare("SELECT * FROM log_stok WHERE produk_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
$mutasi->execute([$produk_id, $tgl_awal, $tgl_akhir]);
$listMutasi = $mutasi->fetchAll(PDO::FETCH_ASSOC);

function tipeMutasi($tipe) {
		switch ($tipe) {
				case 'masuk': return 'Masuk';
				case 'keluar': return 'Keluar';
				default: return ucfirst($tipe);
		}
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Kartu Stok | Inventory</title>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
	<link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
	<link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
	<link href="/Inventaris/public/assets/css/gudang-css/stok-barang.css" rel="stylesheet">
</head>
<body>
<?php include '../../../templates/nav.php'; ?>


<main class="main">
	<div class="content">
		<div class="topbar">
			<div class="top-left">
				<button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
				<div class="breadcrumb">
					<a href="/Inventaris/public/gudang/stok/index.php">Stok Barang</a>
					<i class="bi bi-chevron-right"></i>
					<a href="/Inventaris/public/gudang/Laporan_Persediaan/index.php">Laporan Persediaan</a>
					<i class="bi bi-chevron-right"></i>
					<span>Kartu Stok</span>
				</div>
			</div>
			<div class="top-right">
				<button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
				<div class="user-box">
					<div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
					<div class="user-info">
						<span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
						<span class="user-role">Gudang</span>
					</div>
				</div>
			</div>
		</div>

		<div class="page-header">
			<div class="page-header-left">
				<h1 class="page-title-lg">Kartu Stok</h1>
				<p class="page-subtitle">Mutasi stok produk: <b><?= htmlspecialchars($row['nama'] ?? '') ?></b></p>
			</div>
			<a href="javascript:history.back()" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
		</div>

		<div class="stat-row">
			<div class="stat-pill">
				<span class="stat-pill-label">Kode</span>
				<span class="stat-pill-val"><?= htmlspecialchars($row['kode'] ?? '') ?></span>
			</div>
			<div class="stat-pill">
				<span class="stat-pill-label">Kategori</span>
				<span class="stat-pill-val"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori') ?></span>
			</div>
			<div class="stat-pill">
				<span class="stat-pill-label">Satuan</span>
				<span class="stat-pill-val"><?= htmlspecialchars($row['satuan'] ?? '') ?></span>
			</div>
			<div class="stat-pill">
				<span class="stat-pill-label">Stok FG</span>
				<span class="stat-pill-val ok"><?= number_format($row['stok']) ?></span>
			</div>
			<div class="stat-pill">
				<span class="stat-pill-label">Stok Min</span>
				<span class="stat-pill-val <?= $row['stok'] <= $row['stok_min'] ? 'danger' : 'ok' ?>"><?= number_format($row['stok_min']) ?></span>
			</div>
		</div>

		<div class="form-card filter-card">
			<div class="form-card-header">
				<h4><i class="bi bi-calendar"></i> Filter Tanggal</h4>
			</div>
			<form method="get" class="filter-form" style="display:flex;gap:1em;align-items:end;">
				<input type="hidden" name="produk_id" value="<?= $produk_id ?>">
				<div class="filter-group">
					<label class="form-label">Dari</label>
					<input type="date" name="tgl_awal" class="form-control" value="<?= htmlspecialchars($tgl_awal ?? '') ?>">
				</div>
				<div class="filter-group">
					<label class="form-label">Sampai</label>
					<input type="date" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir ?? '') ?>">
				</div>
				<div class="filter-actions">
					<button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Tampilkan</button>
				</div>
			</form>
		</div>

		<div class="table-card">
			<div class="table-header">
				<h4><i class="bi bi-clipboard-check"></i> Mutasi Stok</h4>
			</div>
			<div class="table-wrap">
				<table>
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>Jenis</th>
							<th style="text-align:center;">Masuk</th>
							<th style="text-align:center;">Keluar</th>
							<th style="text-align:center;">NG</th>
							<th>Keterangan</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($listMutasi)): ?>
						<tr>
							<td colspan="6" class="empty-state">
								<i class="bi bi-inboxes"></i>
								<span>Tidak ada mutasi stok.</span>
							</td>
						</tr>
						<?php else: ?>
						<?php foreach ($listMutasi as $m): ?>
						<tr>
							<td><?= htmlspecialchars($m['tanggal'] ?? '') ?></td>
							<td><?= tipeMutasi($m['tipe']) ?></td>
							<td style="text-align:center;"><?= $m['tipe'] === 'masuk' ? number_format($m['jumlah']) : '-' ?></td>
							<td style="text-align:center;"><?= $m['tipe'] === 'keluar' ? number_format($m['jumlah']) : '-' ?></td>
							<td style="text-align:center;"><?= $m['tipe'] === 'ng' ? number_format($m['jumlah']) : '-' ?></td>
							<td><?= htmlspecialchars($m['keterangan'] ?? '') ?></td>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

	</div>
</main>

</body>
</html>
