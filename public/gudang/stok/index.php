<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';

$search    = $_GET['search'] ?? '';
$hasFilter = !empty($search);

// Get product list for structure (will fetch real-time stock from API)
$sql = "
    SELECT p.id, p.kode, p.nama, p.satuan,
           COALESCE(p.stok_min, 10) AS stok_min,
           k.nama_kategori
    FROM produk p
    LEFT JOIN kategori k ON p.kategori_id = k.id
    WHERE p.status = 'aktif'
";
$params = [];
if ($search) {
    $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY p.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProduk = count($listProduk);
// Stok akan diambil real-time dari API, jadi kita siapkan array kosong untuk agregasi
$totalFG     = 0;  // Will be updated via JavaScript
$kritisItems = [];  // Will be updated via JavaScript
$maxStok     = 1;   // Will be updated via JavaScript

function levelCls($stok, $min) {
    if ($stok <= 0)       return 'danger';
    if ($stok <= $min)    return 'danger';
    if ($stok <= $min*3)  return 'warn';
    return 'ok';
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stok Barang | InventorySys</title>
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
          <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Stok Barang</span>
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

    <?php if (!empty($kritisItems)): ?>
    <div class="alert-late" id="alertKritis" style="display:none;">
      <i class="bi bi-exclamation-triangle"></i>
      <strong id="kritisCount">0 produk</strong> stok kritis atau habis. Segera lakukan restocking.
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Master Stok Gudang</h1>
        <p class="page-subtitle">Pantau ketersediaan stok fisik gudang.</p>
      </div>
      <a href="../laporan_persediaan/kartu_stok.php" class="btn-ghost-sm">
        <i class="bi bi-clock-history"></i> Kartu Stok
      </a>
    </div>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Jenis Produk</span>
        <span class="stat-pill-val"><?= number_format($totalProduk) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Total Stok Tersedia</span>
        <span class="stat-pill-val ok" id="totalStokVal">
          <i class="bi bi-hourglass-split"></i> Loading...
        </span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Stok Kritis</span>
        <span class="stat-pill-val danger" id="kritisVal">
          <i class="bi bi-hourglass-split"></i> Loading...
        </span>
      </div>
    </div>


    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-archive"></i> Daftar Stok <span class="count-badge"><?= count($listProduk) ?></span></h4>
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" id="tableSearch" class="search-input" placeholder="Cari cepat...">
        </div>
      </div>
      <div class="table-wrap">
        <table id="stokTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Kode</th>
              <th>Nama Produk</th>
              <th>Kategori</th>
              <th>Satuan</th>
              <th class="col-right">Fisik</th>
              <th class="col-right">Dipesan</th>
              <th class="col-right">Tersedia</th>
              <th style="min-width:100px;">Indikator</th>
              <th class="col-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($listProduk)): ?>
            <tr>
              <td colspan="10" class="empty-state">
                <i class="bi bi-inboxes"></i>
                <span>Tidak ada data produk.</span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($listProduk as $i => $row): ?>
            <tr class="produk-row" data-produk-id="<?= $row['id'] ?>" data-stok-min="<?= $row['stok_min'] ?>">
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-mid" style="font-size:0.8rem;"><?= htmlspecialchars($row['kode'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['nama'] ?? '') ?></td>
              <td>
                <span class="badge neutral"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori') ?></span>
              </td>
              <td class="text-muted"><?= htmlspecialchars($row['satuan'] ?? '') ?></td>
              <td class="col-right">
                <span class="stok-fisik" style="color: #999; font-weight: 600;">
                  <i class="bi bi-hourglass-split"></i> ...
                </span>
              </td>
              <td class="col-right">
                <span class="stok-dipesan" style="color: #dc3545; font-weight: 600;">
                  <i class="bi bi-hourglass-split"></i> ...
                </span>
              </td>
              <td class="col-right">
                <span class="stok-value" style="color: #999; font-weight: 600;">
                  <i class="bi bi-hourglass-split"></i> ...
                </span>
              </td>
              <td>
                <div class="stok-bar">
                  <div class="stok-bar-fill" style="width: 0%; background-color: #ccc;"></div>
                </div>
                <small class="stok-status" style="color: #999;"><i>Loading...</i></small>
              </td>
              <td class="col-center">
                 <a href="../laporan_persediaan/kartu_stok.php?produk_id=<?= $row['id'] ?>"
                   class="btn-outline"><i class="bi bi-clock-history"></i> Histori</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($listProduk)): ?>
      <div class="table-footer">
        <span class="text-muted" id="tableCount">Menampilkan <?= count($listProduk) ?> data</span>
      </div>
      <?php endif; ?>
    </div>

  </div>

</main>

<script>
/**
 * Real-time Stock Data Loader
 * Fetch stock from API dan update table secara real-time
 */

let allStokData = {};
let maxStokValue = 1;

// Get all product IDs for batch fetch
const produkIds = Array.from(document.querySelectorAll('.produk-row')).map(row => row.dataset.produkId);

// Function to level class based on stock
function getLevelClass(stok, stokMin) {
  if (stok <= 0) return 'danger';
  if (stok <= stokMin) return 'danger';
  if (stok <= stokMin * 3) return 'warn';
  return 'ok';
}

// Function to fetch stock data for multiple products
async function fetchRealtimeStock() {
  if (produkIds.length === 0) return;
  
  try {
    const idsParam = produkIds.join(',');
    const response = await fetch(`/Inventaris/public/gudang/api/get_stok_realtime.php?produk_ids=${idsParam}`, {
      method: 'GET',
      credentials: 'include'  // Include session cookies
    });
    
    if (!response.ok) throw new Error(`API request failed: ${response.status}`);
    
    const result = await response.json();
    
    if (!result.success || !result.data) {
      console.error('API error:', result.message);
      return;
    }
    
    // Store all stock data
    result.data.forEach(item => {
      allStokData[item.id] = item;
    });
    
    // Update UI
    updateTableRows();
    updateStats();
    updateAlerts();
    
  } catch (error) {
    console.error('Error fetching stock:', error);
    // Show error message in first stok value
    document.querySelectorAll('.stok-fisik').forEach(elem => {
      elem.innerHTML = '<span style="color: #dc3545; font-size: 0.85rem;">Err</span>';
    });
  }
}

// Function to update table rows
function updateTableRows() {
  maxStokValue = 1;
  
  document.querySelectorAll('.produk-row').forEach(row => {
    const produkId = row.dataset.produkId;
    const stokData = allStokData[produkId];
    
    if (!stokData) return;
    
    // Update max for percentage calculation
    if (stokData.stok > maxStokValue) {
      maxStokValue = stokData.stok;
    }
    
    const stokFisik = stokData.stok;
    const stokDipesan = stokData.stok_reserved;
    const stokTersedia = stokData.stok_available;
    const stokMin = parseInt(row.dataset.stokMin);
    
    // Use stok_available for status level (not stok fisik)
    const levelClass = getLevelClass(stokTersedia, stokMin);
    const pct = maxStokValue > 0 ? Math.min(100, Math.round((stokFisik / maxStokValue) * 100)) : 0;
    
    // Update row class
    row.className = 'produk-row ' + (levelClass === 'danger' ? 'row-kritis' : '');
    
    // Update stok fisik (column 1)
    const stokFisikElem = row.querySelector('.stok-fisik');
    stokFisikElem.textContent = Number(stokFisik).toLocaleString('id-ID');
    stokFisikElem.style.color = '#333';
    
    // Update stok dipesan (column 2)
    const stokDipesanElem = row.querySelector('.stok-dipesan');
    if (stokDipesan > 0) {
      stokDipesanElem.textContent = Number(stokDipesan).toLocaleString('id-ID');
      stokDipesanElem.style.color = '#dc3545';
    } else {
      stokDipesanElem.textContent = '0';
      stokDipesanElem.style.color = '#999';
    }
    
    // Update stok tersedia (column 3 - based on available, not fisik)
    const stokValueElem = row.querySelector('.stok-value');
    stokValueElem.className = `stok-value stok-${levelClass === 'ok' ? 'fg' : (levelClass === 'warn' ? 'warn' : 'danger')}`;
    stokValueElem.textContent = Number(stokTersedia).toLocaleString('id-ID');
    
    // Update stok bar (based on fisik)
    const barFill = row.querySelector('.stok-bar-fill');
    barFill.style.width = pct + '%';
    barFill.className = `stok-bar-fill ${levelClass}`;
    
    // Update status indicator (based on available)
    const statusElem = row.querySelector('.stok-status');
    statusElem.style.color = '';
    if (levelClass === 'danger') {
      statusElem.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${stokTersedia <= 0 ? 'HABIS' : 'KRITIS'}`;
    } else if (levelClass === 'warn') {
      statusElem.innerHTML = `<i class="bi bi-exclamation-triangle"></i> RENDAH`;
    } else {
      statusElem.innerHTML = `<i class="bi bi-check-circle"></i> OK`;
    }
  });
}

// Function to update stats
function updateStats() {
  let totalStok = 0;
  let totalDipesan = 0;
  let kritisCount = 0;
  
  Object.values(allStokData).forEach(item => {
    totalStok += item.stok;
    totalDipesan += item.stok_reserved;
    const stokMin = parseInt(document.querySelector(`[data-produk-id="${item.id}"]`)?.dataset.stokMin) || 10;
    // Check berdasarkan stok_available, bukan stok fisik
    if (item.stok_available <= stokMin) kritisCount++;
  });
  
  document.getElementById('totalStokVal').textContent = Number(totalStok).toLocaleString('id-ID');
  document.getElementById('kritisVal').textContent = kritisCount;
}

// Function to show/hide alerts
function updateAlerts() {
  let kritisCount = 0;
  
  Object.values(allStokData).forEach(item => {
    const stokMin = parseInt(document.querySelector(`[data-produk-id="${item.id}"]`)?.dataset.stokMin) || 10;
    // Check berdasarkan stok_available, bukan stok fisik
    if (item.stok_available <= stokMin) kritisCount++;
  });
  
  const alertElem = document.getElementById('alertKritis');
  if (alertElem) {
    if (kritisCount > 0) {
      alertElem.style.display = 'flex';
      document.getElementById('kritisCount').textContent = kritisCount + ' produk';
    } else {
      alertElem.style.display = 'none';
    }
  }
}

// Search functionality (updated for new structure)
const si = document.getElementById('tableSearch');
const tc = document.getElementById('tableCount');

si?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  let v = 0;
  
  document.querySelectorAll('#stokTable tbody tr').forEach(r => {
    const m = r.textContent.toLowerCase().includes(q);
    r.style.display = m ? '' : 'none';
    if (m) v++;
  });
  
  if (tc) tc.textContent = `Menampilkan ${v} data`;
});

// Load stock data on page load
document.addEventListener('DOMContentLoaded', () => {
  fetchRealtimeStock();
  
  // Refresh every 10 seconds for real-time updates
  setInterval(fetchRealtimeStock, 10000);
});

// Also refresh when page becomes visible again
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    fetchRealtimeStock();
  }
});
</script>
</body>
</html>