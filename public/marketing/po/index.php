<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
	echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
	exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/PO.php';

$poList = array_map(function($po) {
    $po['total'] = PO::calculateTotal($po['id']); // Assuming calculateTotal is a method in the PO model
    return $po;
}, PO::all());
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pesanan PCB | InventorySys</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/po.css" rel="stylesheet">
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
          <span>Pesanan PCB</span>
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

    <!-- HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Pesanan PCB</h1>
        <p class="page-subtitle">Kelola pesanan PCB dari customer - lihat detail items (qty, PCB, harga) dan track status produksi.</p>
      </div>
      <a href="crud/add.php" class="btn-primary">
        <i class="bi bi-plus-lg"></i> Buat Pesanan Baru
      </a>
    </div>

    <!-- STATS -->
    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Pesanan</span>
        <span class="stat-pill-val"><?= count($poList) ?></span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Draft</span>
        <span class="stat-pill-val neutral">
          <?= count(array_filter($poList, fn($p) => $p['status'] === 'draft')) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Pending</span>
        <span class="stat-pill-val" style="color: #ff9800;">
          <?= count(array_filter($poList, fn($p) => $p['status'] === 'pending_review')) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Approved</span>
        <span class="stat-pill-val ok">
          <?= count(array_filter($poList, fn($p) => $p['status'] === 'approved')) ?>
        </span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Rejected</span>
        <span class="stat-pill-val danger">
          <?= count(array_filter($poList, fn($p) => $p['status'] === 'rejected')) ?>
        </span>
      </div>
    </div>

    <!-- TABLE -->
    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-file-earmark-text"></i> Daftar Pesanan PCB</h4>

        <div class="table-actions">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Cari nomor pesanan, customer, atau PCB...">
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table id="poTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nomor Pesanan</th>
              <th>Customer</th>
              <th>Item Pesanan</th>
              <th>Tanggal</th>
              <th>Status</th>
              <th>Total</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($poList)): ?>
              <tr>
                <td colspan="8" class="empty-state">
                  <i class="bi bi-file-earmark"></i>
                  <span>Belum ada pesanan PCB. <a href="crud/add.php" style="color: #007bff; font-weight: 500;">Buat Pesanan Baru</a></span>
                </td>
              </tr>
            <?php else: ?>

              <?php foreach ($poList as $i => $po): 

                $status = $po['status'];
                $badge = match($status) {
                  'approved' => 'ok',
                  'completed' => 'ok',
                  'rejected' => 'danger',
                  'draft' => 'neutral',
                  default => 'neutral'
                };
              ?>

              <tr>
                <td><?= $i + 1 ?></td>

                <td class="fw-mid">
                  <?= htmlspecialchars($po['nomor_po']) ?>
                </td>

                <td>
                  <small><?= htmlspecialchars($po['perusahaan'] ?? '-') ?></small>
                </td>

                <td>
                  <small style="color: #666;">
                    <?php 
                      $items = PO::getItems($po['id']);
                      if (empty($items)) {
                        echo '<em>Belum ada items</em>';
                      } else {
                        $itemNames = array_map(fn($it) => htmlspecialchars($it['nama_material'] ?? '') . ' (' . intval($it['qty']) . ' pcs)', $items);
                        echo implode(', ', $itemNames);
                      }
                    ?>
                  </small>
                </td>

                <td class="text-muted" style="font-size: 0.9rem;">
                  <?= htmlspecialchars($po['tanggal']) ?>
                </td>

                <td>
                  <span class="badge <?= $badge ?>">
                    <?= htmlspecialchars($status) ?>
                  </span>
                </td>

                <td class="fw-mid" style="font-size: 0.95rem;">
                  Rp <?= number_format($po['total'], 0, ',', '.') ?>
                </td>

                <td>
                  <div class="action-btns" style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a href="crud/detail.php?id=<?= $po['id'] ?>" class="btn-icon" title="Lihat detail & items" style="color: #007bff;">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="crud/edit.php?id=<?= $po['id'] ?>" class="btn-icon" title="Edit pesanan" style="color: #28a745;">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <?php if ($po['status'] === 'draft'): ?>
                    <a href="crud/delete.php?id=<?= $po['id'] ?>" class="btn-icon" title="Hapus" style="color: #dc3545;" onclick="return confirm('Hapus pesanan ini?')">
                      <i class="bi bi-trash"></i>
                    </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>

              <?php endforeach; ?>

            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="table-footer">
        Menampilkan <?= count($poList) ?> pesanan
      </div>
    </div>

  </div>
</main>

<script>
document.getElementById('searchInput').addEventListener('input', function() {
  const q = this.value.toLowerCase();

  document.querySelectorAll('#poTable tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>

</body>
</html>
