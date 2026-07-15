<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
	echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
	exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/Pesanan.php';

$pesananList = array_map(function($pesanan) {
    $pesanan['total'] = Pesanan::calculateTotal($pesanan['id']); 
    $totalQty = (int)($pesanan['total_qty'] ?? 0);
    $totalKirim = (int)($pesanan['total_dikirim'] ?? 0);
    
    if ($totalQty > 0 && $totalKirim >= $totalQty) {
        $pesanan['status_kirim'] = 'Selesai';
    } elseif ($totalKirim > 0) {
        $pesanan['status_kirim'] = 'Sebagian';
    } else {
        $pesanan['status_kirim'] = 'Belum';
    }
    return $pesanan;
}, Pesanan::all());
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pesanan | InventorySys</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

  <!-- Load CSS Utama -->
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <!-- Fallback: Coba load pesanan.css atau po.css -->
  <link href="/Inventaris/public/assets/css/marketing-css/pesanan.css" rel="stylesheet" onerror="this.onerror=null;this.href='/Inventaris/public/assets/css/marketing-css/po.css';">
  <link href="/Inventaris/public/assets/css/gudang-css/penerimaan.css" rel="stylesheet">

  <style>
    /* 🛡️ JURUS PENGAMAN UI & PENYEIMBANG TAMPILAN */
    body { font-family: 'Inter', sans-serif; }

    /* Page Header */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .page-header-left { flex: 1; min-width: 250px; }
    .page-title-lg { margin: 0 0 0.25rem 0; font-size: 1.5rem; font-weight: 800; color: #0f172a; }
    .page-subtitle { margin: 0; color: #64748b; font-size: 0.875rem; }
    
    .btn-primary { background: #0d9488; color: #fff; padding: 0.65rem 1.25rem; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; border: none; transition: all 0.2s; box-shadow: 0 2px 4px rgba(13,148,136,0.2); }
    .btn-primary:hover { background: #0f766e; color: #fff; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(13,148,136,0.3); }

    /* Grid Statistik Yang Seimbang (3 Kolom Sama Rata) */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
    .stat-pill { background: #ffffff; border: 1px solid #e2e8f0; padding: 1.25rem 1.5rem; border-radius: 12px; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden; }
    .stat-pill:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.06); }
    .stat-pill-label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .stat-pill-val { font-size: 1.75rem; font-weight: 800; line-height: 1; }
    
    /* Aksen Warna Kartu Statistik */
    .stat-pill.danger-card { border-left: 4px solid #ef4444; }
    .stat-pill.danger-card .stat-pill-val { color: #ef4444; }
    .stat-pill.warning-card { border-left: 4px solid #f97316; }
    .stat-pill.warning-card .stat-pill-val { color: #f97316; }
    .stat-pill.success-card { border-left: 4px solid #10b981; }
    .stat-pill.success-card .stat-pill-val { color: #10b981; }

    /* Table Actions & Header Seimbang */
    .table-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
    .table-header h4 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem; }
    .table-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
    
    .filter-select { padding: 0.5rem 2rem 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 0.875rem; color: #334155; background-color: #fff; cursor: pointer; }
    .filter-select:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
    
    .search-wrap { position: relative; display: flex; align-items: center; }
    .search-wrap i { position: absolute; left: 12px; color: #94a3b8; font-size: 0.9rem; }
    .search-wrap input { padding: 0.5rem 0.75rem 0.5rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; width: 260px; outline: none; transition: all 0.2s; }
    .search-wrap input:focus { border-color: #0d9488; width: 300px; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }

    /* Penyesuaian Tabel & Tombol Aksi */
    #pesananTable { width: 100%; border-collapse: collapse; }
    #pesananTable th { background: #f8fafc; color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
    #pesananTable td { padding: 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; }
    #pesananTable tbody tr:hover { background-color: #f8fafc; }
    
    .action-btns { display: flex; gap: 0.35rem; align-items: center; }
    .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; text-decoration: none; transition: background 0.2s; font-size: 1rem; }
    .btn-icon:hover { background: #f1f5f9; }
    .btn-icon.blue { color: #0284c7; }
    .btn-icon.green { color: #16a34a; }
    .btn-icon.orange { color: #ea580c; }
    .btn-icon.red { color: #dc2626; }

    /* Progres Kirim Mini-Layout */
    .progress-box { display: flex; flex-direction: column; gap: 2px; font-size: 0.8rem; }
    .progress-item { display: flex; justify-content: space-between; min-width: 100px; }
  </style>
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- Topbar -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Pesanan</span>
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

    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Daftar Pesanan</h1>
        <p class="page-subtitle">Kelola pesanan dari customer - lihat detail items (qty, harga) dan track status produksi.</p>
      </div>
      
      <div class="page-header-actions">
        <a href="crud/add.php" class="btn-primary">
          <i class="bi bi-plus-lg"></i> Buat Pesanan Baru
        </a>
      </div>
    </div>

    <!-- STATISTIK (DIBUNGKUS DENGAN STAT-ROW AGAR SEIMBANG) -->
    <div class="stat-row">
      <div class="stat-pill danger-card">
        <span class="stat-pill-label">Belum Kirim</span>
        <span class="stat-pill-val">
          <?= count(array_filter($pesananList, fn($p) => $p['status_kirim'] === 'Belum')) ?>
        </span>
      </div>

      <div class="stat-pill warning-card">
        <span class="stat-pill-label">Kirim Sebagian</span>
        <span class="stat-pill-val">
          <?= count(array_filter($pesananList, fn($p) => $p['status_kirim'] === 'Sebagian')) ?>
        </span>
      </div>

      <div class="stat-pill success-card">
        <span class="stat-pill-label">Selesai Kirim</span>
        <span class="stat-pill-val">
          <?= count(array_filter($pesananList, fn($p) => $p['status_kirim'] === 'Selesai')) ?>
        </span>
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-file-earmark-text"></i> Daftar Pesanan</h4>

        <div class="table-actions">
          <select id="filterKirim" class="filter-select">
            <option value="">Semua Status Kirim</option>
            <option value="Belum">Belum Kirim</option>
            <option value="Sebagian">Sebagian</option>
            <option value="Selesai">Selesai</option>
          </select>
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Cari nomor pesanan, customer...">
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table id="pesananTable">
          <thead>
            <tr>
              <th width="5%">No</th>
              <th width="15%">Nomor Pesanan</th>
              <th width="15%">Customer</th>
              <th width="20%">Item Pesanan</th>
              <th width="12%">Dibuat</th>
              <th width="13%">Progres Kirim</th>
              <th width="10%">Status Kirim</th>
              <th width="10%">Total</th>
              <th width="10%">Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($pesananList)): ?>
              <tr>
                <td colspan="9" class="empty-state" style="text-align: center; padding: 3rem 1rem;">
                  <i class="bi bi-file-earmark-x" style="font-size: 2.5rem; color: #cbd5e1; display: block; margin-bottom: 0.5rem;"></i>
                  <span style="color: #64748b;">Belum ada pesanan. <a href="crud/add.php" style="color: #0d9488; font-weight: 600; text-decoration: none;">Buat Pesanan Baru</a></span>
                </td>
              </tr>
            <?php else: ?>

              <?php foreach ($pesananList as $i => $pesanan): ?>
              <tr>
                <td><?= $i + 1 ?></td>

                <td style="font-weight: 600; color: #0f172a;">
                  <?= htmlspecialchars($pesanan['nomor_pesanan']) ?>
                </td>

                <td>
                  <?= htmlspecialchars($pesanan['perusahaan'] ?? '-') ?>
                </td>

                <td>
                  <span style="color: #475569; font-size: 0.85rem; line-height: 1.4; display: block;">
                    <?php 
                      $items = Pesanan::getItems($pesanan['id']);
                      if (empty($items)) {
                        echo '<em style="color: #94a3b8;">Belum ada items</em>';
                      } else {
                        $itemNames = array_map(function($it) {
                            $nama = $it['nama_barang'] ?? $it['nama_material'] ?? 'Item';
                            $ukuran = !empty($it['ukuran']) ? ' - ' . $it['ukuran'] : '';
                            return htmlspecialchars($nama) . htmlspecialchars($ukuran) . ' (' . intval($it['qty']) . ' pcs)';
                        }, $items);
                        echo implode(', ', $itemNames);
                      }
                    ?>
                  </span>
                </td>

                <td style="font-size: 0.85rem;">
                  <div style="color: #334155; font-weight: 500;"><?= htmlspecialchars($pesanan['tanggal']) ?></div>
                  <?php if (!empty($pesanan['created_at'])): ?>
                    <small style="color: #94a3b8;">
                      <?= date('H:i', strtotime($pesanan['created_at'])) ?> WIB
                    </small>
                  <?php endif; ?>
                </td>

                <td>
                  <?php 
                    $tQty = (int)($pesanan['total_qty'] ?? 0);
                    $tKirim = (int)($pesanan['total_dikirim'] ?? 0);
                    $tSisa = $tQty - $tKirim;
                  ?>
                  <div class="progress-box">
                    <div class="progress-item" style="font-weight:600; color:#0f172a;">
                      <span>Pesan:</span> <span><?= $tQty ?></span>
                    </div>
                    <div class="progress-item" style="color:#10b981;">
                      <span>Terkirim:</span> <span><?= $tKirim ?></span>
                    </div>
                    <div class="progress-item" style="color:#ef4444;">
                      <span>Sisa:</span> <span><?= $tSisa ?></span>
                    </div>
                  </div>
                </td>

                <td class="status-kirim-cell">
                  <?php 
                    $bgKirim = match($pesanan['status_kirim']) {
                        'Selesai' => 'ok',
                        'Sebagian' => 'neutral',
                        default => 'danger'
                    };
                  ?>
                  <span class="badge <?= $bgKirim ?>">
                    <?= htmlspecialchars($pesanan['status_kirim']) ?>
                  </span>
                </td>

                <td style="font-weight: 600; color: #0f172a; font-size: 0.9rem;">
                  Rp <?= number_format($pesanan['total'], 0, ',', '.') ?>
                </td>

                <td>
                  <div class="action-btns">
                    <a href="crud/detail.php?id=<?= $pesanan['id'] ?>" class="btn-icon blue" title="Lihat detail">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="crud/edit.php?id=<?= $pesanan['id'] ?>" class="btn-icon green" title="Edit pesanan">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <a href="crud/print.php?id=<?= $pesanan['id'] ?>" class="btn-icon orange" title="Cetak Pesanan" target="_blank">
                      <i class="bi bi-printer"></i>
                    </a>
                    <a href="crud/delete.php?id=<?= $pesanan['id'] ?>" class="btn-icon red" title="Hapus" onclick="return confirm('Hapus pesanan ini?')">
                      <i class="bi bi-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="table-footer" style="padding: 1rem; color: #64748b; font-size: 0.85rem; border-top: 1px solid #e2e8f0;">
        Menampilkan <strong><?= count($pesananList) ?></strong> pesanan
      </div>
    </div>

  </div>
</main>

<script>
const searchInput = document.getElementById('searchInput');
const filterKirim = document.getElementById('filterKirim');

function filterTable() {
  const q = searchInput.value.toLowerCase();
  const f = filterKirim.value.toLowerCase();

  document.querySelectorAll('#pesananTable tbody tr').forEach(row => {
    if (row.classList.contains('empty-state')) return;
    
    const textMatches = row.textContent.toLowerCase().includes(q);
    const statusCell = row.querySelector('.status-kirim-cell');
    const statusMatches = f === '' || (statusCell && statusCell.textContent.toLowerCase().includes(f));
    
    row.style.display = (textMatches && statusMatches) ? '' : 'none';
  });
}

searchInput.addEventListener('input', filterTable);
filterKirim.addEventListener('change', filterTable);
</script>

</body>
</html>