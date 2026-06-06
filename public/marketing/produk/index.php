<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/Produk.php';
$produkList = Produk::all();

// LOGIKA BARU: Pakai stok_available (atau stok) dan bandingkan dengan stok_min dari database
$stokMenipis = array_filter($produkList, function($p) {
    $stokAktif = $p['stok_available'] ?? $p['stok'] ?? 0;
    $batasMin = $p['stok_min'] ?? 10;
    return $stokAktif > 0 && $stokAktif <= $batasMin;
});

$stokHabis = array_filter($produkList, function($p) {
    $stokAktif = $p['stok_available'] ?? $p['stok'] ?? 0;
    return $stokAktif <= 0;
});
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

    <?php if (isset($_GET['success'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Produk berhasil ditambahkan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Produk berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Produk berhasil dihapus.</div>
    <?php endif; ?>

    <?php if (!empty($stokHabis)): ?>
      <div style="background: #fee; border-left: 4px solid #dc3545; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #721c24;">
        <strong style="font-size: 1.1rem;">🔴 STOK HABIS (Atau Full Dibooking)!</strong>
        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
          <?php foreach ($stokHabis as $produk): ?>
            <div>• <strong><?= htmlspecialchars($produk['nama']) ?></strong> (<?= htmlspecialchars($produk['kode_produk'] ?? $produk['kode']) ?>)</div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($stokMenipis)): ?>
      <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #856404;">
        <strong style="font-size: 1.1rem;">⚠️ STOK MENIPIS - PERLU RESTOK!</strong>
        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
          <?php foreach ($stokMenipis as $produk): 
            $sisa = $produk['stok_available'] ?? $produk['stok'] ?? 0;
            $satuan = $produk['satuan'] ?? 'pcs';
          ?>
            <div>• <strong><?= htmlspecialchars($produk['nama']) ?></strong> - Tersisa: <strong><?= $sisa ?> <?= htmlspecialchars($satuan) ?></strong> (Batas: <?= $produk['stok_min'] ?? 10 ?>)</div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Produk</span>
        <span class="stat-pill-val"><?= count($produkList) ?></span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Aktif</span>
        <span class="stat-pill-val ok">
          <?= count(array_filter($produkList, fn($p) => isset($p['status']) && strtolower($p['status']) === 'aktif')) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Stok Habis</span>
        <span class="stat-pill-val danger"><?= count($stokHabis) ?></span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Stok Menipis</span>
        <span class="stat-pill-val warn"><?= count($stokMenipis) ?></span>
      </div>
    </div>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Produk PCB</h1>
        <p class="page-subtitle">Kelola semua produk PCB yang tersedia dalam sistem.</p>
      </div>
      <a href="crud/add.php" class="btn-primary">
        <i class="bi bi-plus-lg"></i> Tambah Produk
      </a>
    </div>

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
              <th>Stok (Bisa Dijual)</th>
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
                $statusCls = match(strtolower($p['status'] ?? '')) {
                  'aktif' => 'ok',
                  default => 'warn'
                };
                
                // Ambil data stok sesuai database
                $stokAktif = $p['stok_available'] ?? $p['stok'] ?? 0;
                $stokFisik = $p['stok'] ?? 0;
                $stokBooking = $p['stok_reserved'] ?? 0;
                $batasMin = $p['stok_min'] ?? 10;
                $satuan = htmlspecialchars($p['satuan'] ?? 'pcs');
              ?>

              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="fw-mid"><?= htmlspecialchars($p['kode_produk'] ?? $p['kode'] ?? '-') ?></td>
                <td style="font-weight: 500; color: #111827;"><?= htmlspecialchars($p['nama'] ?? '-') ?></td>
                <td class="text-muted"><?= htmlspecialchars($p['kategori'] ?? '-') ?></td>
                
                <td>
                  <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                    <span><?= $stokAktif ?> <?= $satuan ?></span>
                    <?php if ($stokAktif <= 0): ?>
                      <span class="badge danger" style="padding: 2px 6px; font-size: 0.7rem;">Habis</span>
                    <?php elseif ($stokAktif <= $batasMin): ?>
                      <span class="badge warn" style="padding: 2px 6px; font-size: 0.7rem;">Menipis</span>
                    <?php endif; ?>
                  </div>
                  <?php if ($stokBooking > 0): ?>
                  <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">
                    Fisik: <?= $stokFisik ?> | Dibooking: <span style="color:#dc3545; font-weight:bold;"><?= $stokBooking ?></span>
                  </div>
                  <?php endif; ?>
                </td>

                <td class="fw-mid" style="color: #059669;">
                  Rp <?= number_format((int)($p['harga'] ?? 0), 0, ',', '.') ?>
                </td>
                
                <td>
                  <span class="badge <?= $statusCls ?>">
                    <?= htmlspecialchars(ucfirst($p['status'] ?? '-')) ?>
                  </span>
                </td>
                
                <td>
                  <div class="action-btns">
                    <div style="display: flex; gap: 0.5rem;">
                      <a href="crud/detail.php?id=<?= $p['id'] ?>" class="btn-icon" title="Detail">
                        <i class="bi bi-eye"></i>
                      </a>
                      <a href="crud/edit.php?id=<?= $p['id'] ?>" class="btn-icon edit" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="crud/delete.php?id=<?= $p['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Hapus produk ini?')">
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

// Dark mode toggle
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
if (themeBtn) {
  themeBtn.addEventListener('click', () => {
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    themeBtn.querySelector('i').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
  });
}
</script>

</body>
</html>