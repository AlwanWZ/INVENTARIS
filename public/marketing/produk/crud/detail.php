<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/Produk.php';

$produk = Produk::find($_GET['id'] ?? null);
if (!$produk) { header('Location: ../index.php'); exit; }

$statusCls = match($produk['status']) {
    'aktif', 'Aktif' => 'ok',
    default => 'warn'
};
$stokCls   = (isset($produk['stok']) && $produk['stok'] === 0) ? 'danger' : ((isset($produk['stok']) && $produk['stok'] < 20) ? 'warn' : 'ok');
$stokLabel = (isset($produk['stok']) && $produk['stok'] === 0) ? 'Habis' : ((isset($produk['stok']) && $produk['stok'] < 20) ? 'Menipis' : 'Tersedia');
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Produk PCB | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/produk.css" rel="stylesheet">
</head>
<body>

<?php include '../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="index.php">Produk</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($produk['kode_produk'] ?? '-') ?></span>
        </div>
      </div>
      <div class="top-right">
        <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
        <div class="user-box">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            <span class="user-role">Marketing</span>
          </div>
        </div>
      </div>
    </div>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Detail Produk</h1>
        <p class="page-subtitle">
          <strong><?= htmlspecialchars($produk['nama'] ?? '-') ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($produk['status'] ?? '-') ?></span>
        </p>
      </div>
      <div class="header-actions">
        <a href="edit.php?id=<?= $produk['id'] ?>" class="btn-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">

      <div class="detail-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-box-seam"></i> Informasi Produk</h4>
            <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($produk['status'] ?? '-') ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Kode Produk</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($produk['kode_produk'] ?? '-') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-val"><span class="badge <?= $statusCls ?>"><?= htmlspecialchars($produk['status'] ?? '-') ?></span></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Nama Produk</span>
              <span class="detail-val"><?= htmlspecialchars($produk['nama'] ?? '-') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Kategori</span>
              <span class="detail-val"><?= htmlspecialchars($produk['kategori'] ?? '-') ?></span>
            </div>
            <?php if (!empty($produk['deskripsi'] ?? '')): ?>
            <div class="detail-item detail-item-full">
              <span class="detail-label">Deskripsi</span>
              <span class="detail-val"><?= htmlspecialchars($produk['deskripsi'] ?? '-') ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="detail-side">

        <!-- Stok & Harga -->
        <div class="form-card total-card">
          <div class="form-card-header">
            <h4><i class="bi bi-cash-stack"></i> Harga &amp; Stok</h4>
          </div>
          <div class="total-display">
            <span class="total-label">Harga Satuan</span>
            <span class="total-val">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></span>
          </div>
          <div class="total-divider"></div>
          <div class="total-meta">
            <span>Stok Tersedia</span>
            <span class="fw-mid"><?= $produk['stok'] ?> pcs</span>
          </div>
          <div class="total-meta">
            <span>Kondisi Stok</span>
            <span class="badge <?= $stokCls ?>"><?= $stokLabel ?></span>
          </div>
          <div class="total-meta">
            <span>ID Produk</span>
            <span class="text-muted">#<?= $produk['id'] ?></span>
          </div>
        </div>

        <!-- Aksi -->
        <div class="form-card action-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="danger-body">
            <a href="edit.php?id=<?= $produk['id'] ?>" class="btn-primary full" style="margin-bottom:8px;">
              <i class="bi bi-pencil"></i> Edit Produk
            </a>
            <a href="../index.php" class="btn-outline full">
              <i class="bi bi-arrow-left"></i> Kembali ke Daftar
            </a>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>


</body>
</html>
