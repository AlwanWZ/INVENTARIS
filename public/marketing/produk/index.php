<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/Produk.php';
$produkList = Produk::all();
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Produk PCB | InventorySys</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/produk.css" rel="stylesheet">
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Produk PCB</span>
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

    <!-- NOTIFIKASI -->
    <?php if (isset($_GET['success'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Produk berhasil ditambahkan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Produk berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Produk berhasil dihapus.</div>
    <?php endif; ?>

    <!-- STAT ROW -->
    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Produk</span>
        <span class="stat-pill-val"><?= count($produkList) ?></span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Aktif</span>
        <span class="stat-pill-val ok">
          <?= count(array_filter($produkList, fn($p) => isset($p['status']) && ($p['status'] === 'Aktif' || $p['status'] === 'aktif'))) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Tidak Aktif</span>
        <span class="stat-pill-val warn">
          <?= count(array_filter($produkList, fn($p) => isset($p['status']) && ($p['status'] === 'Tidak Aktif' || $p['status'] === 'nonaktif'))) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Stok Habis</span>
        <span class="stat-pill-val danger">
          <?= count(array_filter($produkList, fn($p) => isset($p['stok']) && $p['stok'] == 0)) ?>
        </span>
      </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Produk PCB</h1>
        <p class="page-subtitle">Kelola semua produk PCB yang tersedia dalam sistem.</p>
      </div>
      <a href="crud/add.php" class="btn-primary">
        <i class="bi bi-plus-lg"></i> Tambah Produk
      </a>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-box-seam"></i> Daftar Produk</h4>
        <div class="table-actions">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Cari produk atau kategori...">
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table id="produkTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Kode Produk</th>
              <th>Nama Produk</th>
              <th>Kategori</th>
              <th>Stok</th>
              <th>Harga</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($produkList)): ?>
              <tr>
                <td colspan="8" class="empty-state">
                  <i class="bi bi-box-seam"></i>
                  <span>Belum ada produk. <a href="crud/add.php">Tambah sekarang</a></span>
                </td>
              </tr>
            <?php else: ?>

              <?php foreach ($produkList as $i => $p):
                $statusCls = match($p['status'] ?? '') {
                  'Aktif', 'aktif' => 'ok',
                  default => 'warn'
                };
                $stokCls   = (isset($p['stok']) && $p['stok'] == 0) ? 'danger' : ((isset($p['stok']) && $p['stok'] < 20) ? 'warn' : '');
              ?>

              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="fw-mid"><?= htmlspecialchars($p['kode_produk'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['nama'] ?? '-') ?></td>
                <td class="text-muted"><?= htmlspecialchars($p['kategori'] ?? '-') ?></td>
                <td class="<?= $stokCls ? 'stok-' . $stokCls : '' ?>">
                  <?= isset($p['stok']) ? $p['stok'] . ' pcs' : '-' ?>
                </td>
                <td class="fw-mid">
                  Rp <?= number_format((int)($p['harga'] ?? 0), 0, ',', '.') ?>
                </td>
                <td>
                  <span class="badge <?= $statusCls ?>">
                    <?= htmlspecialchars($p['status'] ?? '-') ?>
                  </span>
                </td>
                <td>
                  <div class="action-btns">
                    <!-- ROW 1: VIEW & EDIT -->
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                      <a href="crud/detail.php?id=<?= $p['id'] ?>" class="btn-icon" title="Detail">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="crud/edit.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>
                    </div>

                    <!-- ROW 2: DELETE -->
                    <div>
                      <a href="crud/delete.php?id=<?= $p['id'] ?>" class="btn-icon" style="color:#dc3545;" title="Hapus" onclick="return confirm('Hapus produk ini?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </div>
                </td>
              </tr>

              <?php endforeach; ?>

            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="table-footer">
        <span class="text-muted" id="tableCount">
          Menampilkan <?= count($produkList) ?> data
        </span>
      </div>
    </div>

  </div>
</main>



<script>
const searchInput = document.getElementById('searchInput');
const tableCount  = document.getElementById('tableCount');

searchInput.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  let visible = 0;

  document.querySelectorAll('#produkTable tbody tr').forEach(row => {
    const match = row.textContent.toLowerCase().includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });

  tableCount.textContent = `Menampilkan ${visible} data`;
});
</script>

</body>
</html>
