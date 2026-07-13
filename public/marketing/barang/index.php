<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/Barang.php';

// PERBAIKAN: Penamaan variabel disesuaikan menjadi $barangList
$barangList = Barang::all();

// LOGIKA BARU: Pakai stok_available (atau stok) dan bandingkan dengan stok_min dari database
$stokMenipis = array_filter($barangList, function($p) {
    $stokAktif = $p['stok_available'] ?? $p['stok'] ?? 0;
    $batasMin = $p['stok_min'] ?? 10;
    return $stokAktif > 0 && $stokAktif <= $batasMin;
});

$stokHabis = array_filter($barangList, function($p) {
    $stokAktif = $p['stok_available'] ?? $p['stok'] ?? 0;
    return $stokAktif <= 0;
});
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Barang PCB | InventorySys</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <!-- Fallback: Coba load barang.css atau produk.css jika belum ter-rename -->
  <link href="/Inventaris/public/assets/css/marketing-css/barang.css" rel="stylesheet" onerror="this.onerror=null;this.href='/Inventaris/public/assets/css/marketing-css/produk.css';">
  
  <style>
    /* 🛡️ JURUS PENGAMAN UI (Biar tetap keren meski CSS terpisah gagal load) */
    .stat-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .stat-pill { background: var(--surface, #ffffff); border: 1px solid var(--border, #e2e8f0); padding: 0.8rem 1.2rem; border-radius: 12px; display: flex; flex-direction: column; min-width: 130px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .stat-pill-label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-pill-val { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem; }
    .stat-pill-val.ok { color: #10b981; }
    .stat-pill-val.warn { color: #f59e0b; }
    .stat-pill-val.danger { color: #ef4444; }
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .btn-primary { background: #0d9488; color: #fff; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; border: none; transition: all 0.2s; }
    .btn-primary:hover { background: #0f766e; color: #fff; transform: translateY(-1px); }

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
    .table-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }
    .filter-select {
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--bg);
      color: var(--text);
      font-family: inherit;
      font-size: 0.88rem;
      outline: none;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    .filter-select:focus {
      border-color: var(--accent);
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
      font-size: 0.88rem;
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
    .btn-reset-filter {
      padding: 8px 12px;
      border-radius: var(--radius);
      background: #f3f4f6;
      color: #4b5563;
      border: 1px solid #d1d5db;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s;
    }
    .btn-reset-filter:hover {
      background: #e5e7eb;
      color: #1f2937;
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
      <div class="alert-success"><i class="bi bi-check-circle"></i> Barang berhasil ditambahkan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Barang berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Barang berhasil dihapus.</div>
    <?php endif; ?>

    <?php if (!empty($stokHabis)): ?>
      <div style="background: #fee; border-left: 4px solid #dc3545; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #721c24;">
        <strong style="font-size: 1.1rem;">🔴 STOK HABIS (Atau Full Dibooking)!</strong>
        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
          <?php foreach ($stokHabis as $barang): ?>
            <div>• <strong><?= htmlspecialchars($barang['nama']) ?></strong> (<?= htmlspecialchars($barang['kode_barang'] ?? $barang['kode'] ?? '-') ?>)</div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($stokMenipis)): ?>
      <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #856404;">
        <strong style="font-size: 1.1rem;">⚠️ STOK MENIPIS - PERLU RESTOK!</strong>
        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
          <?php foreach ($stokMenipis as $barang): 
            $sisa = $barang['stok_available'] ?? $barang['stok'] ?? 0;
            $satuan = $barang['satuan'] ?? 'pcs';
          ?>
            <div>• <strong><?= htmlspecialchars($barang['nama']) ?></strong> - Tersisa: <strong><?= $sisa ?> <?= htmlspecialchars($satuan) ?></strong> (Batas: <?= $barang['stok_min'] ?? 10 ?>)</div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Daftar Barang</h1>
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
      <select id="productComboBox" class="customer-combobox">
        <option value="">-- Pilih Barang untuk lihat detail --</option>
        <?php foreach ($barangList as $p): ?>
        <option value="<?= $p['id'] ?>" 
                data-code="<?= htmlspecialchars($p['kode_barang'] ?? $p['kode'] ?? '-') ?>" 
                data-name="<?= htmlspecialchars($p['nama']) ?>" 
                data-category="<?= htmlspecialchars($p['nama_kategori'] ?? 'PCB') ?>" 
                data-ukuran="<?= htmlspecialchars($p['ukuran'] ?? '-') ?>" 
                data-price="<?= htmlspecialchars($p['harga'] ?? 0) ?>" 
                data-stock="<?= htmlspecialchars($p['stok_available'] ?? $p['stok'] ?? 0) ?>" 
                data-unit="<?= htmlspecialchars($p['satuan'] ?? 'pcs') ?>" 
                data-status="<?= $p['status'] ?? 'aktif' ?>">
          <?= htmlspecialchars($p['kode_barang'] ?? $p['kode'] ?? '-') ?> - <?= htmlspecialchars($p['nama']) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <div id="productDetail" class="customer-detail-box" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg2, #f8fafc); border-radius: var(--radius); border: 1px solid var(--border);">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <span style="font-size: 0.72rem; color: var(--text3, #64748b); text-transform: uppercase; font-weight: 700;">Kode Barang</span>
            <p id="detProdCode" style="margin: 4px 0 0; color: var(--text2, #334155); font-weight: 800;">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3, #64748b); text-transform: uppercase; font-weight: 700;">Harga Jual</span>
            <p id="detProdPrice" style="margin: 4px 0 0; color: #0d9488; font-weight: 800; font-family: monospace; font-size: 1.1rem;">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3, #64748b); text-transform: uppercase; font-weight: 700;">Kategori</span>
            <p id="detProdCategory" style="margin: 4px 0 0; color: var(--text2, #334155);">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3, #64748b); text-transform: uppercase; font-weight: 700;">Ukuran</span>
            <p id="detProdUkuran" style="margin: 4px 0 0; color: var(--text2, #334155);">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3, #64748b); text-transform: uppercase; font-weight: 700;">Sisa Stok Jual</span>
            <p id="detProdStock" style="margin: 4px 0 0; color: var(--text2, #334155); font-weight: 800;">-</p>
          </div>
          <div style="grid-column: span 2;">
            <span style="font-size: 0.72rem; color: var(--text3, #64748b); text-transform: uppercase; font-weight: 700;">Status</span>
            <p id="detProdStatus" style="margin: 4px 0 0; color: var(--text2, #334155);"><span class="badge ok">Aktif</span></p>
          </div>
        </div>
      </div>
    </div>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Barang</span>
        <span class="stat-pill-val"><?= count($barangList) ?></span>
      </div>

      <div class="stat-pill">
        <span class="stat-pill-label">Aktif</span>
        <span class="stat-pill-val ok">
          <?= count(array_filter($barangList, fn($p) => isset($p['status']) && strtolower($p['status']) === 'aktif')) ?>
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
      <div class="table-header" style="flex-wrap: wrap; gap: 12px;">
        <h4><i class="bi bi-box-seam"></i> Daftar Barang</h4>
        
        <!-- ========================================== -->
        <!-- 🚀 MULTI-FILTER SECTION                   -->
        <!-- ========================================== -->
        <div class="table-actions">
          <!-- 1. Filter Kondisi Stok -->
          <select id="filterStok" class="filter-select" title="Filter Kondisi Stok">
            <option value="all">Semua Kondisi Stok</option>
            <option value="habis">🔴 Stok Habis / 0</option>
            <option value="menipis">⚠️ Stok Menipis (&le; Min)</option>
            <option value="aman">🟢 Stok Aman (&gt; Min)</option>
          </select>

          <!-- 2. Filter Status -->
          <select id="filterStatus" class="filter-select" title="Filter Status Barang">
            <option value="all">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>

          <!-- 3. Sort By -->
          <select id="sortBy" class="filter-select" title="Urutkan Data">
            <option value="default">Urutkan: Default</option>
            <option value="stok-desc">Stok Terbanyak</option>
            <option value="stok-asc">Stok Sedikit (Kritis)</option>
            <option value="harga-desc">Harga Tertinggi</option>
            <option value="harga-asc">Harga Terendah</option>
          </select>
          
          <!-- 4. Pencarian Teks -->
          <div class="search-group">
            <input type="text" id="searchInput" placeholder="Cari nama atau kode...">
            <button type="button" id="searchBtn" title="Cari Data">
              <i class="bi bi-search"></i>
            </button>
          </div>

          <!-- Tombol Reset Filter -->
          <button type="button" id="resetBtn" class="btn-reset-filter" title="Reset Semua Filter" style="display: none;">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
          </button>
        </div>
      </div>

      <div class="table-wrap">
        <!-- PERBAIKAN: ID disesuaikan menjadi barangTable -->
        <table id="barangTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Kode Barang</th>
              <th>Nama Barang</th>
              <th>Ukuran</th>
              <th>Stok (Bisa Dijual)</th>
              <th>Harga</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($barangList)): ?>
              <tr>
                <td colspan="7" class="empty-state">
                  <i class="bi bi-box-seam"></i>
                  <span>Belum ada barang. <a href="crud/add.php">Tambah sekarang</a></span>
                </td>
              </tr>
            <?php else: ?>

              <?php foreach ($barangList as $i => $p):
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

                // Klasifikasi kondisi stok untuk filter JS
                if ($stokAktif <= 0) {
                    $kategoriStok = 'habis';
                } elseif ($stokAktif <= $batasMin) {
                    $kategoriStok = 'menipis';
                } else {
                    $kategoriStok = 'aman';
                }
              ?>
              
              <tr data-status="<?= $rawStatus ?>" 
                  data-stok-type="<?= $kategoriStok ?>" 
                  data-stok-val="<?= $stokAktif ?>" 
                  data-price-val="<?= (int)($p['harga'] ?? 0) ?>">
                <td class="text-muted row-number"><?= $i + 1 ?></td>
                <td class="fw-mid"><?= htmlspecialchars($p['kode_barang'] ?? $p['kode'] ?? '-') ?></td>
                <td style="font-weight: 500; color: #111827;"><?= htmlspecialchars($p['nama'] ?? '-') ?></td>
                <td><?= htmlspecialchars($p['ukuran'] ?? '-') ?></td>
            
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
                      <a href="crud/delete.php?id=<?= $p['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Hapus barang ini?')">
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
          Menampilkan <?= count($barangList) ?> data
        </span>
      </div>
    </div>

  </div>
</main>

<script>
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
      
      const code     = selectedOption.getAttribute('data-code');
      const name     = selectedOption.getAttribute('data-name');
      const category = selectedOption.getAttribute('data-category');
      const ukuran   = selectedOption.getAttribute('data-ukuran') || '-';
      const price    = selectedOption.getAttribute('data-price');
      const stock    = selectedOption.getAttribute('data-stock');
      const unit     = selectedOption.getAttribute('data-unit');
      const status   = selectedOption.getAttribute('data-status');

      const formatRp = 'Rp ' + parseInt(price).toLocaleString('id-ID');

      document.getElementById('detProdCode').textContent = code;
      document.getElementById('detProdCategory').textContent = category;
      document.getElementById('detProdUkuran').textContent = ukuran;
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
  // 🚀 LOGIC MULTI-FILTER & SORTING TABEL
  // ==========================================
  const searchInput = document.getElementById('searchInput');
  const searchBtn   = document.getElementById('searchBtn');
  const filterStok  = document.getElementById('filterStok');
  const filterStatus= document.getElementById('filterStatus');
  const sortBy      = document.getElementById('sortBy');
  const resetBtn    = document.getElementById('resetBtn');
  const tableCount  = document.getElementById('tableCount');
  // PERBAIKAN: Target selektor disesuaikan jadi #barangTable
  const tbody       = document.querySelector('#barangTable tbody');
  const tableRows   = tbody ? Array.from(tbody.querySelectorAll('tr:not(.empty-state)')) : [];

  function filterAndSortTable() {
    if (!tbody) return;
    
    const query      = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const stokVal    = filterStok ? filterStok.value : 'all';
    const statusVal  = filterStatus ? filterStatus.value.toLowerCase() : 'all';
    const sortVal    = sortBy ? sortBy.value : 'default';

    let visibleCount = 0;
    let visibleRows  = [];

    // 1. FILTER DATA
    tableRows.forEach(row => {
      const textContent = row.textContent.toLowerCase();
      const rowStatus   = row.getAttribute('data-status') || '';
      const rowStokType = row.getAttribute('data-stok-type') || '';

      const textMatch   = textContent.includes(query);
      const statusMatch = (statusVal === 'all' || rowStatus === statusVal);
      const stokMatch   = (stokVal === 'all' || rowStokType === stokVal);

      if (textMatch && statusMatch && stokMatch) {
        row.style.display = '';
        visibleRows.push(row);
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    // 2. SORTING (PENGURUTAN) DATA YANG TAMPIL
    if (sortVal !== 'default' && visibleRows.length > 1) {
      visibleRows.sort((a, b) => {
        const stokA  = parseInt(a.getAttribute('data-stok-val')) || 0;
        const stokB  = parseInt(b.getAttribute('data-stok-val')) || 0;
        const priceA = parseInt(a.getAttribute('data-price-val')) || 0;
        const priceB = parseInt(b.getAttribute('data-price-val')) || 0;

        if (sortVal === 'stok-desc') return stokB - stokA;
        if (sortVal === 'stok-asc')  return stokA - stokB;
        if (sortVal === 'harga-desc') return priceB - priceA;
        if (sortVal === 'harga-asc')  return priceA - priceB;
        return 0;
      });

      visibleRows.forEach(row => tbody.appendChild(row));
    }

    // 3. PERBARUI NOMOR URUT (No.) PADA BARIS YANG TAMPIL
    visibleRows.forEach((row, idx) => {
      const cellNo = row.querySelector('.row-number');
      if (cellNo) cellNo.textContent = idx + 1;
    });

    // 4. UPDATE TEKS JUMLAH DATA
    if (tableCount) {
      tableCount.textContent = `Menampilkan ${visibleCount} data`;
    }

    // 5. TAMPILKAN / SEMBUNYIKAN TOMBOL RESET
    const isFiltered = query !== '' || stokVal !== 'all' || statusVal !== 'all' || sortVal !== 'default';
    if (resetBtn) {
      resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }
  }

  // Pasang Event Listener untuk setiap filter
  if (searchInput)  searchInput.addEventListener('input', filterAndSortTable);
  if (searchBtn)    searchBtn.addEventListener('click', filterAndSortTable);
  if (filterStok)   filterStok.addEventListener('change', filterAndSortTable);
  if (filterStatus) filterStatus.addEventListener('change', filterAndSortTable);
  if (sortBy)       sortBy.addEventListener('change', filterAndSortTable);

  // Aksi Tombol Reset
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (searchInput)  searchInput.value = '';
      if (filterStok)   filterStok.value = 'all';
      if (filterStatus) filterStatus.value = 'all';
      if (sortBy)       sortBy.value = 'default';
      filterAndSortTable();
    });
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