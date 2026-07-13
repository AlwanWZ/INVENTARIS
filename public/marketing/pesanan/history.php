<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
	echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
	exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/Pesanan.php';

// Mengambil semua Pesanan dan memfilter hanya untuk status riwayat/final
$allPos = Pesanan::all();
$historyList = array_filter($allPos, function($pesanan) {
    // Menampilkan pesanan yang sudah disetujui, selesai, atau ditolak
    return in_array($pesanan['status'], ['approved', 'completed', 'rejected']);
});

// Hitung total harga untuk masing-masing Pesanan di dalam riwayat
$historyList = array_map(function($pesanan) {
    $pesanan['total'] = Pesanan::calculateTotal($pesanan['id']);
    return $pesanan;
}, $historyList);
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Riwayat Pesanan PCB | InventorySys</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/pesanan.css" rel="stylesheet">
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
          <a href="index.php">Pesanan PCB</a>
          <i class="bi bi-chevron-right"></i>
          <span>Riwayat Pesanan</span>
        </div>
      </div>

      <div class="top-right">
        <button id="themeToggle" class="theme-btn">
          <i class="bi bi-moon"></i>
        </button>

        <div class="user-box">
          <div class="user-avatar">
            <?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?>
          </div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            <span class="user-role">Marketing</span>
          </div>
        </div>
      </div>
    </div>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Riwayat Pesanan PCB</h1>
        <p class="page-subtitle">Arsip log data pesanan yang telah diproses (Approved, Completed, dan Rejected) untuk pemantauan performa penjualan.</p>
      </div>
      
      <div class="page-header-actions">
        <a href="index.php" class="btn-history" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.2rem; background: #ffffff; border: 1px solid #dcdcdc; border-radius: 6px; color: #444444; font-size: 0.9rem; font-weight: 500; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s;">
          <i class="bi bi-arrow-left"></i> Kembali ke Pesanan
        </a>
      </div>
    </div>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Arsip</span>
        <span class="stat-pill-val"><?= count($historyList) ?></span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Completed</span>
        <span class="stat-pill-val ok" style="color: #28a745;">
          <?= count(array_filter($historyList, fn($p) => ($p['status'] === 'completed' || $p['status'] === 'approved'))) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Rejected</span>
        <span class="stat-pill-val danger">
          <?= count(array_filter($historyList, fn($p) => $p['status'] === 'rejected')) ?>
        </span>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-clock-history"></i> Log Arsip Pesanan</h4>

        <div class="table-actions">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Cari riwayat nomor pesanan, customer...">
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table id="historyTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nomor Pesanan</th>
              <th>Customer</th>
              <th>Item Pesanan</th>
              <th>Tanggal Pesanan</th>
              <th>Pengiriman</th>
              <th>Status Akhir</th>
              <th>Total Pendapatan</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($historyList)): ?>
              <tr>
                <td colspan="9" class="empty-state">
                  <i class="bi bi-archive"></i>
                  <span>Tidak ada riwayat pesanan yang ditemukan.</span>
                </td>
              </tr>
            <?php else: ?>

              <?php 
              $idx = 1;
              foreach ($historyList as $pesanan): 
                  $status = $pesanan['status'] ?? 'completed'; 
                  $badge = match($status) {
                      'approved', 'completed' => 'ok',
                      'rejected'              => 'danger',
                      default                 => 'neutral'
                  };
              ?>
              <tr>
                <td><?= $idx++ ?></td>

                <td class="fw-mid">
                  <?= htmlspecialchars($pesanan['nomor_pesanan']) ?>
                </td>

                <td>
                  <small><?= htmlspecialchars($pesanan['perusahaan'] ?? '-') ?></small>
                </td>

                <td>
                  <small style="color: #666;">
                    <?php 
                      $items = Pesanan::getItems($pesanan['id']);
                      if (empty($items)) {
                        echo '<em>Tidak ada data item</em>';
                      } else {
                        $itemNames = array_map(fn($it) => htmlspecialchars($it['nama_material'] ?? '') . ' (' . intval($it['qty']) . ' pcs)', $items);
                        echo implode(', ', $itemNames);
                      }
                    ?>
                  </small>
                </td>

                <td class="text-muted" style="font-size: 0.85rem;">
                  <div><?= htmlspecialchars($pesanan['tanggal']) ?></div>
                  <?php if (!empty($pesanan['created_at'])): ?>
                    <small style="color: #aaa;">
                      <?= date('H:i', strtotime($pesanan['created_at'])) ?> WIB
                    </small>
                  <?php endif; ?>
                </td>

                <td class="text-muted" style="font-size: 0.85rem;">
                  <?php if (!empty($pesanan['tanggal_pengiriman'])): ?>
                    <span style="color: #007bff; font-weight: 500;">
                      <?= htmlspecialchars($pesanan['tanggal_pengiriman']) ?>
                    </span>
                  <?php else: ?>
                    <span style="color: #ccc;">—</span>
                  <?php endif; ?>
                </td>

                <td>
                  <span class="badge <?= $badge ?>">
                    <?= htmlspecialchars((string)$status) ?>
                  </span>
                </td>

                <td class="fw-mid" style="font-size: 0.95rem;">
                  Rp <?= number_format($pesanan['total'], 0, ',', '.') ?>
                </td>

                <td>
                  <div class="action-btns">
                    <a href="crud/detail.php?id=<?= $pesanan['id'] ?>" class="btn-icon" title="Lihat detail & riwayat item" style="color: #007bff;">
                      <i class="bi bi-eye"></i> Detail
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="table-footer">
        Menampilkan <?= count($historyList) ?> arsip pesanan selesai
      </div>
    </div>

  </div>
</main>

<script>
// Realtime Search Filter
document.getElementById('searchInput').addEventListener('input', function() {
  const q = this.value.toLowerCase();

  document.querySelectorAll('#historyTable tbody tr').forEach(row => {
    // Jangan sembunyikan jika baris data kosong
    if(!row.querySelector('.empty-state')) {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    }
  });
});

// Hover Effect matching the standard theme
const btnBack = document.querySelector('.btn-history');
if(btnBack) {
  btnBack.addEventListener('mouseover', () => { btnBack.style.background = '#f5f5f5'; });
  btnBack.addEventListener('mouseout', () => { btnBack.style.background = '#ffffff'; });
}
</script>

</body>
</html>