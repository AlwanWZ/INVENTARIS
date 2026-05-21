<?php
require_once '../src/auth.php';
require_once '../src/models/Produk.php';
require_once '../src/models/Customer.php';
require_once '../src/models/PO.php';
require_once '../src/models/User.php';
require_once '../app/Models/Kategori.php';
checkLogin();

// KPI Data
$produkList = Produk::all();
$produkCount = count($produkList);
$customerCount = count(Customer::getAll());
$poList = PO::all();
$poCount = count($poList);
$userCount = count(User::getAll());
$kategoriList = array_unique(array_map(function($p){return $p['kategori'];}, $produkList));
$kategoriCount = count($kategoriList);

$spkCount = 0;
if (file_exists('../src/models/SPK.php')) {
    require_once '../src/models/SPK.php';
    $spkModel = new SPK($pdo); 
    $spkCount = count($spkModel->all()); 
}

$totalStok = array_sum(array_map(function($p){return $p['stok'];}, $produkList));
$lowStockCount = count(array_filter($produkList, function($p){return $p['stok'] <= 10;}));
$totalFG = $totalStok;

// Pengiriman bulan ini (Surat Jalan)
require_once '../src/models/SuratJalan.php';
$sjModel = new SuratJalan($pdo);
$bulanIni = date('Y-m-01');
$sjBulanIni = $sjModel->getAll(['date_from' => $bulanIni]);
$totalPengiriman = count($sjBulanIni);

// Chart Data: Orders per Month
$ordersPerMonth = array_fill(1, 12, 0);
foreach ($poList as $po) {
  $month = (int)date('n', strtotime($po['tanggal']));
  $ordersPerMonth[$month]++;
}

// Note: NG items removed from system - showing FG only

// Tabel aktivitas: 5 aktivitas terakhir
$aktivitas = [];
// PO
foreach (array_slice($poList,0,5) as $po) {
  $aktivitas[] = [
    'tanggal' => $po['tanggal'],
    'desc' => 'Marketing membuat PO <b>' . htmlspecialchars($po['nomor_po']) . '</b>'
  ];
}
// Penerimaan
require_once '../src/models/Penerimaan.php';
$penerimaanModel = new Penerimaan($pdo);
$penerimaanList = $penerimaanModel->getAll('', '', '', '');
foreach (array_slice($penerimaanList,0,5) as $p) {
  $aktivitas[] = [
    'tanggal' => $p['tanggal'],
    'desc' => 'Gudang menerima <b>' . htmlspecialchars($p['nomor_penerimaan']) . '</b>'
  ];
}
// Surat Jalan
foreach (array_slice($sjBulanIni,0,5) as $sj) {
  $aktivitas[] = [
    'tanggal' => $sj['tanggal_kirim'],
    'desc' => 'Gudang membuat Surat Jalan <b>' . htmlspecialchars($sj['nomor_sj']) . '</b>'
  ];
}
// Gabung, urutkan, ambil 5 terbaru
usort($aktivitas, function($a,$b){ return strtotime($b['tanggal'])-strtotime($a['tanggal']); });
$aktivitas = array_slice($aktivitas,0,5);
?>

<!doctype html>
<html lang="id" data-theme="light" id="htmlRoot">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Marketing | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<?php include '../templates/nav.php'; ?>

<main class="main">
    <div class="content">

      <div class="topbar">
        <div class="top-left">
          <button class="menu-btn" style="margin-right:12px;"><i class="bi bi-list"></i></button>
          <h4 class="page-title">Dashboard</h4>
        </div>
        <div class="top-right">
          <form class="quick-search" style="display:inline-block; margin-right:18px;">
            <input type="text" id="quickSearchInput" placeholder="Cari menu/data..." style="padding:6px 12px; border-radius:8px; border:1px solid #eee; outline:none; font-size:0.97rem;">
          </form>
          <button id="notifBtn" class="theme-btn" style="margin-right:10px;" aria-label="Notifikasi">
            <i class="bi bi-bell"></i>
            <span id="notifDot" style="display:inline-block;width:8px;height:8px;background:#e8621a;border-radius:50%;position:absolute;margin-left:-10px;margin-top:-8px;"></span>
          </button>
          <button id="themeToggle" class="theme-btn" aria-label="Toggle tema">
            <i class="bi bi-moon"></i>
          </button>
          <div class="user-box">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
          </div>
        </div>
      </div>

      <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="kpi-card"><div class="kpi-icon orange"><i class="bi bi-box-seam"></i></div><div class="kpi-body"><span class="kpi-label">Total Produk</span><div class="kpi-val"><?= $produkCount ?></div><span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i> Produk aktif</span></div></div>
        <div class="kpi-card"><div class="kpi-icon blue"><i class="bi bi-tags"></i></div><div class="kpi-body"><span class="kpi-label">Kategori Barang</span><div class="kpi-val"><?= $kategoriCount ?></div><span class="kpi-trend"><i class="bi bi-tag"></i> Kategori aktif</span></div></div>
        <div class="kpi-card"><div class="kpi-icon green"><i class="bi bi-people"></i></div><div class="kpi-body"><span class="kpi-label">Total Customer</span><div class="kpi-val"><?= $customerCount ?></div><span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i> Customer terdaftar</span></div></div>
        <div class="kpi-card"><div class="kpi-icon purple"><i class="bi bi-file-earmark-text"></i></div><div class="kpi-body"><span class="kpi-label">Total PO</span><div class="kpi-val"><?= $poCount ?></div><span class="kpi-trend"><i class="bi bi-clipboard-data"></i> Order masuk</span></div></div>
        <div class="kpi-card"><div class="kpi-icon teal"><i class="bi bi-clipboard-data"></i></div><div class="kpi-body"><span class="kpi-label">Total SPK</span><div class="kpi-val"><?= $spkCount ?></div><span class="kpi-trend"><i class="bi bi-clipboard-check"></i> Semua divisi</span></div></div>
        <div class="kpi-card"><div class="kpi-icon red"><i class="bi bi-exclamation-triangle"></i></div><div class="kpi-body"><span class="kpi-label">Stok Menipis (&le;10)</span><div class="kpi-val"><?= $lowStockCount ?></div><span class="kpi-trend warn"><i class="bi bi-arrow-down"></i> Perlu reorder</span></div></div>
        <div class="kpi-card"><div class="kpi-icon green"><i class="bi bi-stack"></i></div><div class="kpi-body"><span class="kpi-label">Total Stok Barang</span><div class="kpi-val"><?= $totalStok ?></div><span class="kpi-trend"><i class="bi bi-box-seam"></i> Semua produk</span></div></div>

        <div class="kpi-card"><div class="kpi-icon blue"><i class="bi bi-truck"></i></div><div class="kpi-body"><span class="kpi-label">Pengiriman Bulan Ini</span><div class="kpi-val"><?= $totalPengiriman ?></div><span class="kpi-trend"><i class="bi bi-calendar-event"></i> Surat Jalan</span></div></div>
      </div>

      <div class="info-general" style="margin:24px 0 0 0; padding:18px 22px; background:linear-gradient(90deg,#fffbe7 60%,#e8621a10 100%); border-radius:12px; border:1px solid #ffe6a0; color:#7c5a00; font-size:1rem; display:flex; align-items:center; gap:18px;">
        <i class="bi bi-info-circle" style="font-size:1.7rem;color:#e8621a;"></i>
        <div>
          <b>Inventory</b> — Dashboard ini menampilkan statistik umum sistem inventaris yang dapat diakses semua role. Tidak ada data sensitif yang ditampilkan. <br>
          <span style="font-size:0.97rem;color:#b98a00;">Tips: Gunakan menu di samping untuk akses fitur lengkap sesuai hak akses Anda.</span>
        </div>
      </div>

      <div style="display:flex;gap:24px;margin:28px 0 0 0;flex-wrap:wrap;align-items:stretch;">
        
        <div class="chart-card">
          <h4><i class="bi bi-bar-chart-fill"></i> Order Masuk per Bulan</h4>
          <div style="position: relative; height: 300px; width: 100%; display: flex; align-items: center; justify-content: center;">
            <canvas id="orderChart" width="600" height="300"></canvas>
          </div>
          <div id="orderChartEmpty" style="display:none;padding:40px 20px;color:#d97706;text-align:center;font-size:1rem;">Belum ada data order bulan ini.</div>
        </div>
        


      </div>

      <div class="table-card" style="margin-top:24px;">
        <div class="table-header">
          <h4><i class="bi bi-lightning-charge" style="color:#e8621a"></i> 5 Aktivitas/Transaksi Terakhir</h4>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Aktivitas</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($aktivitas as $a): ?>
              <tr>
                <td><?= date('d M Y', strtotime($a['tanggal'])) ?></td>
                <td><?= $a['desc'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

<script>
  // Initialize charts after full load to ensure canvases and Chart.js are ready
  window.addEventListener('load', function () {
    // Grafik Bar Order Masuk
    const rawOrderData = <?= json_encode(array_values($ordersPerMonth)) ?>;
    const orderChartEl = document.getElementById('orderChart');
    const orderChartEmptyEl = document.getElementById('orderChartEmpty');
    
    if (orderChartEl && typeof Chart !== 'undefined') {
      const numericOrderData = rawOrderData.map(v => Number(v) || 0);
      const totalOrders = numericOrderData.reduce((a, b) => a + b, 0);
      if (totalOrders === 0) {
        if (orderChartEl.parentElement) orderChartEl.parentElement.style.display = 'none';
        if (orderChartEmptyEl) orderChartEmptyEl.style.display = 'block';
      } else {
        try {
          const ctx = orderChartEl.getContext('2d');
          new Chart(ctx, {
            type: 'bar',
            data: {
              labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
              datasets: [{
                label: 'Order Masuk',
                data: numericOrderData,
                backgroundColor: 'rgba(232,98,26,0.85)',
                borderRadius: 6,
                maxBarThickness: 40
              }]
            },
            options: {
              maintainAspectRatio: false,
              responsive: true,
              plugins: { legend: { display: false } },
              scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { stepSize: 2 } }
              }
            }
          });
        } catch (err) {
          console.error('Error creating order Chart:', err);
        }
      }
    }

    // NG chart removed - system now shows good items only

    // Theme, Notif, Quick Search, Dropdown, Active Link scripts
    const html = document.getElementById('htmlRoot');
    const themeBtn = document.getElementById('themeToggle');
    const themeIcon = themeBtn?.querySelector('i');
    function applyTheme(dark) {
      if (!html) return;
      html.setAttribute('data-theme', dark ? 'dark' : 'light');
      if (themeIcon) themeIcon.className = dark ? 'bi bi-sun' : 'bi bi-moon';
      localStorage.setItem('theme', dark ? 'dark' : 'light');
      document.body.style.background = dark ? '#0e0f11' : '#f5f4f1';
    }
    let darkMode = localStorage.getItem('theme') === 'dark';
    applyTheme(darkMode);
    if (themeBtn) themeBtn.addEventListener('click', () => {
      darkMode = !darkMode;
      applyTheme(darkMode);
    });
  
    const notifBtn = document.getElementById('notifBtn');
    const notifDot = document.getElementById('notifDot');
    if (notifBtn) notifBtn.addEventListener('click', () => {
      alert('Tidak ada notifikasi baru.');
      if (notifDot) notifDot.style.display = 'none';
    });
  
    const quickSearchInput = document.getElementById('quickSearchInput');
    if (quickSearchInput) quickSearchInput.addEventListener('input', function() {
      const val = this.value.toLowerCase();
      document.querySelectorAll('.nav-item, .nav-group .dropdown a').forEach(el => {
        if (val && el.textContent.toLowerCase().includes(val)) {
          el.style.background = '#ffe6a0';
        } else {
          el.style.background = '';
        }
      });
    });
  
  document.querySelectorAll('.dropdown-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const dd   = btn.nextElementSibling;
      const open = dd.classList.contains('open');
      document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('open'));
      document.querySelectorAll('.dropdown-btn').forEach(b => b.classList.remove('open'));
      if (!open) { dd.classList.add('open'); btn.classList.add('open'); }
    });
  });
  
  document.querySelectorAll('.dropdown a').forEach(a => {
    if (a.href === window.location.href) {
      a.classList.add('active');
      a.closest('.dropdown')?.classList.add('open');
      a.closest('.dropdown')?.previousElementSibling?.classList.add('open');
    }
  });
</script>

</body>
</html>