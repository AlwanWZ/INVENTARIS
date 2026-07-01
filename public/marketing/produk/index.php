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
  
  <style>
    /* Styling untuk Quick View Combobox */
    .customer-selector-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px;
      margin-bottom: 22px;
      box-shadow: var(--shadow);
    }
    .selector-header {
      margin-bottom: 12px;
    }
    .customer-combobox {
      width: 100%;
      padding: 12px 14px;
      font-family: 'Roboto', sans-serif;
      font-size: 0.95rem;
      color: var(--text);
      background: var(--bg);
      border: 1px solid var(--border2);
      border-radius: var(--radius);
      outline: none;
      cursor: pointer;
      transition: border-color var(--trans), box-shadow var(--trans);
    }
    .customer-combobox:hover {
      border-color: var(--border);
    }
    .customer-combobox:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(232, 98, 26, 0.12);
      background: var(--surface);
    }
    .customer-detail-box {
      animation: slideDown 0.2s ease-out;
    }
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Styling tambahan untuk Filter Dropdown & Search Button */
    .filter-select {
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--bg);
      color: var(--text);
      font-family: inherit;
      outline: none;
      cursor: pointer;
    }
    .table-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .search-group {
      display: flex;
      align-items: stretch;
    }
    .search-group input {
      border-radius: var(--radius) 0 0 var(--radius);
      border-right: none;
      padding: 8px 12px;
      border: 1px solid var(--border);
      outline: none;
    }
    .search-group input:focus {
      border-color: var(--accent);
    }
    .search-group button {
      background-color: #0d9488;
      color: white;
      border: none;
      padding: 0 14px;
      border-radius: 0 var(--radius) var(--radius) 0;
      cursor: pointer;
      transition: background 0.2s;
    }
    .search-group button:hover {
      background-color: #0f766e;
    }
  </style>
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
          <span>Barang</span>
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

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Daftar Barang </h1>
        <p class="page-subtitle">Kelola semua barang yang tersedia dalam sistem.</p>
      </div>
      <a href="crud/add.php" class="btn-primary">
        <i class="bi bi-plus-lg"></i> Tambah Barang
      </a>
    </div>

    <div class="customer-selector-card">
      <div class="selector-header">
        <h3 style="margin: 0; font-size: 0.95rem; color: var(--text); font-weight: 700;">Quick View Barang</h3>
      </div>
      <select id="productComboBox" class="customer-combobox" onchange="handleProductSelect(this)">
        <option value="">-- Pilih Barang untuk lihat detail --</option>
        <?php foreach ($produkList as $p): ?>
        <option value="<?= $p['id'] ?>" 
                data-code="<?= htmlspecialchars($p['kode_produk'] ?? $p['kode'] ?? '-') ?>" 
                data-name="<?= htmlspecialchars($p['nama']) ?>" 
                data-category="<?= htmlspecialchars($p['nama_kategori'] ?? 'PCB') ?>" 
                data-price="<?= htmlspecialchars($p['harga'] ?? 0) ?>" 
                data-stock="<?= htmlspecialchars($p['stok_available'] ?? $p['stok'] ?? 0) ?>" 
                data-unit="<?= htmlspecialchars($p['satuan'] ?? 'pcs') ?>" 
                data-status="<?= $p['status'] ?? 'aktif' ?>">
          <?= htmlspecialchars($p['kode_produk'] ?? $p['kode'] ?? '-') ?> - <?= htmlspecialchars($p['nama']) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <div id="productDetail" class="customer-detail-box" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg2); border-radius: var(--radius); border: 1px solid var(--border);">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Kode Produk</span>
            <p id="detProdCode" style="margin: 4px 0 0; color: var(--text2); font-weight: 800;">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Harga Jual</span>
            <p id="detProdPrice" style="margin: 4px 0 0; color: #0d9488; font-weight: 800; font-family: monospace; font-size: 1.1rem;">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Kategori</span>
            <p id="detProdCategory" style="margin: 4px 0 0; color: var(--text2);">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Sisa Stok Jual</span>
            <p id="detProdStock" style="margin: 4px 0 0; color: var(--text2); font-weight: 800;">-</p>
          </div>
          <div style="grid-column: span 2;">
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Status</span>
            <p id="detProdStatus" style="margin: 4px 0 0; color: var(--text2);"><span class="badge ok">Aktif</span></p>
          </div>
        </div>
      </div>
    </div>

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

    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-box-seam"></i> Daftar Barang</h4>
        <div class="table-actions">
          <select id="filterStatus" class="filter-select">
            <option value="all">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
          
          <div class="search-group">
            <input type="text" id="searchInput" placeholder="Cari nama atau kode...">
            <button type="button" id="searchBtn" title="Cari Data">
              <i class="bi bi-search"></i>
            </button>
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
              <th>Stok (Bisa Dijual)</th>
              <th>Harga</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($produkList)): ?>
              <tr>
                <td colspan="7" class="empty-state">
                  <i class="bi bi-box-seam"></i>
                  <span>Belum ada barang. <a href="crud/add.php">Tambah sekarang</a></span>
                </td>
              </tr>
            <?php else: ?>

              <?php foreach ($produkList as $i => $p):
                $rawStatus = strtolower($p['status'] ?? 'aktif');
                $statusCls = match($rawStatus) {
                  'aktif' => 'ok',
                  default => 'warn'
                };
                
                $stokAktif = $p['stok_available'] ?? $p['stok'] ?? 0;
                $stokFisik = $p['stok'] ?? 0;
                $stokBooking = $p['stok_reserved'] ?? 0;
                $batasMin = $p['stok_min'] ?? 10;
                $satuan = htmlspecialchars($p['satuan'] ?? 'pcs');
              ?>
              
              <tr data-status="<?= $rawStatus ?>">
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="fw-mid"><?= htmlspecialchars($p['kode_produk'] ?? $p['kode'] ?? '-') ?></td>
                <td style="font-weight: 500; color: #111827;"><?= htmlspecialchars($p['nama'] ?? '-') ?></td>
            
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
// Menunggu semua DOM (HTML) ter-load sempurna baru jalankan fungsi JS
document.addEventListener('DOMContentLoaded', function() {
  
  // ==========================================
  // LOGIC QUICK VIEW COMBOBOX
  // ==========================================
  const productComboBox = document.getElementById('productComboBox');
  if(productComboBox) {
    productComboBox.addEventListener('change', function() {
      const select = this;
      const selectedOption = select.options[select.selectedIndex];
      const detailBox = document.getElementById('productDetail');
      
      if (!select.value) {
        detailBox.style.display = 'none';
        return;
      }
      
      const code = selectedOption.dataset.code;
      const category = selectedOption.dataset.category;
      const price = parseInt(selectedOption.dataset.price) || 0;
      const stock = selectedOption.dataset.stock;
      const unit = selectedOption.dataset.unit;
      const status = selectedOption.dataset.status;
      
      const formatRp = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
      
      document.getElementById('detProdCode').textContent = code;
      document.getElementById('detProdCategory').textContent = category;
      document.getElementById('detProdPrice').textContent = formatRp;
      document.getElementById('detProdStock').textContent = `${stock} ${unit}`;
      
      const statusBadge = document.getElementById('detProdStatus');
      const badgeClass = status.toLowerCase() === 'aktif' ? 'ok' : 'warn';
      const statusLabel = status.toLowerCase() === 'aktif' ? 'Aktif' : 'Nonaktif';
      statusBadge.innerHTML = `<span class="badge ${badgeClass}">${statusLabel}</span>`;
      
      detailBox.style.display = 'block';
    });
  }

  // ==========================================
  // LOGIC PENCARIAN & FILTER TABEL YANG BARU
  // ==========================================
  const searchInput = document.getElementById('searchInput');
  const searchBtn = document.getElementById('searchBtn'); // Ambil elemen tombol
  const filterStatus = document.getElementById('filterStatus');
  const tableCount = document.getElementById('tableCount');
  const tableRows = document.querySelectorAll('#produkTable tbody tr');

  function filterTableData() {
    const query = searchInput ? searchInput.value.toLowerCase() : '';
    const statusVal = filterStatus ? filterStatus.value.toLowerCase() : 'all';
    let visible = 0;

    tableRows.forEach(row => {
      // Abaikan baris kosong (empty state)
      if (row.querySelector('.empty-state')) return;
      
      // Ambil teks dari baris dan status dari atribut data-status
      const textMatch = row.textContent.toLowerCase().includes(query);
      const rowStatus = row.getAttribute('data-status') || '';
      
      // Cek apakah cocok dengan filter dropdown
      const statusMatch = (statusVal === 'all' || rowStatus === statusVal);

      // Tampilkan jika teks DAN status cocok
      if (textMatch && statusMatch) {
        row.style.display = '';
        visible++;
      } else {
        row.style.display = 'none';
      }
    });

    // Update tulisan jumlah data di bawah tabel
    if (tableCount) {
      tableCount.textContent = `Menampilkan ${visible} data`;
    }
  }

  // 1. Eksekusi filter setiap kali ada ketikan (real-time)
  if (searchInput) {
    searchInput.addEventListener('input', filterTableData);
  }
  
  // 2. Eksekusi filter jika pengguna menekan tombol cari secara manual
  if (searchBtn) {
    searchBtn.addEventListener('click', filterTableData);
  }

  // 3. Eksekusi filter saat pengguna memilih status dari dropdown
  if (filterStatus) {
    filterStatus.addEventListener('change', filterTableData);
  }

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

}); // Penutup DOMContentLoaded
</script>

</body>
</html>