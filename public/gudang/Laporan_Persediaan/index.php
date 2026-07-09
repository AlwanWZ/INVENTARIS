<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['gudang', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';

$search      = $_GET['search']   ?? '';
$kategori    = $_GET['kategori'] ?? '';
$filterStok  = $_GET['filter_stok'] ?? 'all'; 
$hasFilter   = $search || $kategori || ($filterStok !== 'all');

$listKategori = $pdo->query("SELECT DISTINCT kategori as id, kategori as nama_kategori FROM barang WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 🚀 EXPORT EXCEL (KOLOM KATEGORI DIHAPUS)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="laporan_persediaan.xls"');
        echo "<table border='1'>";
        echo "<tr>
                <th>No</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Satuan</th>
                <th>Harga Pokok Produksi (HPP)</th>
                <th>Harga Jual (Rp)</th>
                <th>Stok Fisik</th>
                <th>Qty Tersedia</th>
                <th>Jumlah Harga HPP (Rp)</th>
                <th>Jumlah Harga Jual (Rp)</th>
                <th>Stok Min</th>
                <th>Status</th>
              </tr>";
        
        $sql = "SELECT p.id, COALESCE(p.kode, '') AS kode, COALESCE(p.kode_barang, '') AS kode_produk_alt, p.nama, p.satuan, p.harga, p.harga AS harga_jual, p.stok, p.stok_available, COALESCE(p.stok_min, 10) AS stok_min, p.kategori AS nama_kategori FROM barang p WHERE 1=1";
        $params = [];
        
        if ($search) {
                $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ? OR p.kode_barang LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
        }
        if ($kategori) {
                $sql .= " AND p.kategori = ?";
                $params[] = $kategori;
        }
        if ($filterStok === 'available') {
                $sql .= " AND (COALESCE(p.stok_available, p.stok, 0) > 0)";
        } elseif ($filterStok === 'empty') {
                $sql .= " AND (COALESCE(p.stok_available, p.stok, 0) <= 0)";
        }
        
        $sql .= " ORDER BY p.nama ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $listProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($listProduk as $i => $row) {
                $stok_fisik = $row['stok'] ?? 0;
                $stok_tersedia = $row['stok_available'] ?? 0;
                $hpp = $row['harga'] ?? 0;
                $harga_jual = $row['harga_jual'] ?? 0;
                
                $jumlahHargaHpp = $stok_tersedia * $hpp; 
                $jumlahHargaJual = $stok_tersedia * $harga_jual; 
                
                $status = ($stok_tersedia <= 0) ? 'Habis' : (($stok_tersedia <= $row['stok_min']) ? 'Kritis' : 'OK');
                $tampilKode = !empty($row['kode']) ? $row['kode'] : $row['kode_produk_alt'];

                echo "<tr>";
                echo "<td>".($i+1)."</td>";
                echo "<td>".htmlspecialchars($tampilKode)."</td>";
                echo "<td>".htmlspecialchars($row['nama'] ?? '')."</td>";
                echo "<td>".htmlspecialchars($row['satuan'] ?? '')."</td>";
                echo "<td>".$hpp."</td>";
                echo "<td>".$harga_jual."</td>";
                echo "<td>".$stok_fisik."</td>";
                echo "<td>".$stok_tersedia."</td>";
                echo "<td>".$jumlahHargaHpp."</td>";
                echo "<td>".$jumlahHargaJual."</td>";
                echo "<td>".$row['stok_min']."</td>";
                echo "<td>".$status."</td>";
                echo "</tr>";
        }
        echo "</table>";
        exit;
}

// ==========================================
// 🚀 QUERY DASHBOARD UTAMA
// ==========================================
$sql = "SELECT p.id, COALESCE(p.kode, '') AS kode, COALESCE(p.kode_barang, '') AS kode_produk_alt, p.nama, p.satuan, p.harga, p.harga AS harga_jual, p.stok, p.stok_available, COALESCE(p.stok_min, 10) AS stok_min, p.kategori AS nama_kategori FROM barang p WHERE 1=1";
$params = [];
if ($search) {
        $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ? OR p.kode_barang LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
}
if ($kategori) {
        $sql .= " AND p.kategori = ?";
        $params[] = $kategori;
}
if ($filterStok === 'available') {
        $sql .= " AND (COALESCE(p.stok_available, p.stok, 0) > 0)";
} elseif ($filterStok === 'empty') {
        $sql .= " AND (COALESCE(p.stok_available, p.stok, 0) <= 0)";
}

$sql .= " ORDER BY p.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Summary Data ---
$totalProduk = count($listProduk);
$totalFG     = array_sum(array_column($listProduk, 'stok'));
$kritisItems = array_filter($listProduk, fn($p) => $p['stok'] <= $p['stok_min']);

// Hitung Dual Valuasi (HPP & Harga Jual)
$totalValuasiHpp = 0;
$totalValuasiJual = 0;
foreach ($listProduk as $p) {
    $totalValuasiHpp  += (($p['stok_available'] ?? 0) * ($p['harga'] ?? 0));
    $totalValuasiJual += (($p['stok_available'] ?? 0) * ($p['harga_jual'] ?? 0));
}

// --- Chart Data ---
$byKategori = [];
foreach ($listProduk as $p) {
    $kat = $p['nama_kategori'] ?: 'Tanpa Kategori';
    if (!isset($byKategori[$kat])) $byKategori[$kat] = 0;
    $byKategori[$kat] += ($p['stok_available'] ?? 0);
}
arsort($byKategori);
$chartKatLabels = json_encode(array_keys($byKategori));
$chartKatData   = json_encode(array_values($byKategori));

$topProduk = $listProduk;
usort($topProduk, function($a, $b) {
    return ($b['stok_available'] ?? 0) <=> ($a['stok_available'] ?? 0);
});
$top5Produk = array_slice($topProduk, 0, 5);

function formatRp($n) {
    return 'Rp ' . number_format($n ?? 0, 0, ',', '.');
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Persediaan | Inventory</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
    <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
    <link href="/Inventaris/public/assets/css/marketing-css/laporan.css" rel="stylesheet"> 
    <link href="/Inventaris/public/assets/css/gudang-css/stok-barang.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        @media print {
            .no-print, .topbar, .page-header, .filter-card, nav, .btn-ghost-sm, .section-label, .kpi-row, .charts-row { display:none !important; }
            .main { margin:0 !important; padding: 0 !important; }
            .content { padding: 0 !important; }
            .table-card { box-shadow:none !important; border:none !important; padding: 0 !important; }
            .table-header { display: none !important; }
            .print-header-container { display: block !important; margin-bottom: 20px; }
            body { background: #fff; color: #000; }
            @page { size: A4 landscape; margin: 15mm; }
            table th, table td { color: #000 !important; border-color: #000 !important; }
            .total-row td { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        .print-header-container { display: none; }
        .kop-surat { display: flex; align-items: center; margin-bottom: 5px; color: #000; }
        .kop-logo { width: 140px; margin-right: 20px; }
        .kop-text { flex-grow: 1; text-align: left; }
        .kop-text h1 { margin: 0 0 5px 0; font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; }
        .kop-text p { margin: 0; font-size: 8.5pt; line-height: 1.3; }
        .garis-tebal { border: 0; border-bottom: 3px solid #000; margin-bottom: 2px; }
        .garis-tipis { border: 0; border-bottom: 1px solid #000; margin-bottom: 20px; }
        .judul-surat { text-align: center; margin-bottom: 25px; color: #000; }
        .judul-surat h2 { margin: 0; font-size: 14pt; text-decoration: underline; font-weight: bold; }
        .judul-surat p { margin: 5px 0 0 0; font-size: 11pt; }
        
        /* ttd */
        .ttd-container { display: none; }
        @media print {
            .ttd-container { display: flex !important; justify-content: space-between; margin-top: 40px; text-align: center; font-size: 10pt; color: #000; page-break-inside: avoid; }
            .ttd-box { width: 250px; }
            .ttd-box p { margin: 0; }
            .ttd-space { height: 80px; }
            .ttd-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        }
        .filter-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow); }
        .filter-form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text3); text-transform: uppercase; margin-bottom: 6px; }
        .filter-group .form-control { width: 100%; padding: 10px 12px; font-size: 0.9rem; border: 1px solid var(--border2); border-radius: 8px; background: var(--bg); color: var(--text); }
        .filter-actions { flex-shrink: 0; }
        .filter-actions .btn-primary { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<?php include '../../../templates/nav.php'; ?>

<main class="main">
    <div class="content">
        <!-- HEADER PRINT (CELEBIT KOP) -->
        <div class="print-header-container">
            <div class="kop-surat">
                <img src="/Inventaris/public/assets/img/celebit-logo.png" alt="Logo Celebit" class="kop-logo" onerror="this.style.display='none'">
                <div class="kop-text">
                    <h1>PT. CELEBIT CIRCUIT TECHNOLOGY INDONESIA</h1>
                    <p>BANDUNG FACTORY : JL.BUAH DUA RT.01/RW.04 RANCAEKEK - BANDUNG-INDONESIA<br>
                    TEL 62-22-7798 561/7798542, FAX: 62-22-7798 562 E-MAIL: celebit@celebit.id</p>
                </div>
            </div>
            <hr class="garis-tebal">
            <hr class="garis-tipis">
            
            <div class="judul-surat">
                <h2>LAPORAN PERSEDIAAN GUDANG (FINISH GOOD)</h2>
                <p>Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>
        </div>

        <div class="topbar">
            <div class="top-left">
                <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
                <div class="breadcrumb">
                    <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Laporan Persediaan</span>
                </div>
            </div>
            <div class="top-right">
                <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
                <div class="user-box">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                        <span class="user-role"><?= htmlspecialchars(ucfirst($_SESSION['user']['role'])) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($kritisItems)): ?>
        <div class="alert-late" style="margin-bottom: 20px;">
            <i class="bi bi-exclamation-triangle"></i>
            <strong><?= count($kritisItems) ?> barang</strong> stok kritis atau habis. Segera lakukan restocking.
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title-lg">Laporan Persediaan</h1>
                <p class="page-subtitle">Rekapitulasi persediaan barang berdasarkan stok tersedia dan Harga Pokok Produksi (HPP).</p>
            </div>
            <div class="header-actions" style="display: flex; gap: 10px;">
                <button class="btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak
                </button>
                <a href="?export=excel&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori) ?>&filter_stok=<?= urlencode($filterStok) ?>" class="btn-ghost-sm">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </a>
            </div>
        </div>

        <div class="filter-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="margin:0; font-size:1rem; font-weight:700; color:var(--text);"><i class="bi bi-funnel"></i> Filter Laporan</h4>
                <?php if ($hasFilter): ?>
                    <a href="index.php" class="btn-ghost-sm" style="padding:4px 8px; font-size:0.8rem;"><i class="bi bi-x"></i> Reset</a>
                <?php endif; ?>
            </div>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label>Pencarian</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama atau kode..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <label>Status Stok</label>
                    <select name="filter_stok" class="form-control">
                        <option value="all" <?= $filterStok === 'all' ? 'selected' : '' ?>>Semua Stok</option>
                        <option value="available" <?= $filterStok === 'available' ? 'selected' : '' ?>>Stok Tersedia (&gt; 0)</option>
                        <option value="empty" <?= $filterStok === 'empty' ? 'selected' : '' ?>>Stok Kosong (Habis)</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Terapkan</button>
                </div>
            </form>
        </div>

        <div class="section-label">Ringkasan Nilai Aset Gudang</div>
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon blue"><i class="bi bi-tags"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Valuasi HPP</span>
                    <span class="kpi-val" style="color: #2563eb; font-size: 1.25rem;"><?= formatRp($totalValuasiHpp) ?></span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon orange"><i class="bi bi-boxes"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Kuantitas Fisik</span>
                    <span class="kpi-val" style="font-size: 1.25rem;"><?= number_format($totalFG) ?> <span style="font-size: 0.8rem; color:#888;">Unit</span></span>
                </div>
            </div>
        </div>

        <?php if (!empty($byKategori)): ?>
        <div class="section-label">Visualisasi</div>
        <div class="charts-row">
            <div class="form-card chart-card">
                <div class="form-card-header"><h4><i class="bi bi-bar-chart-line"></i> Total Stok per Kategori</h4></div>
                <div class="chart-wrap"><canvas id="chartKategori"></canvas></div>
            </div>
            <div class="form-card chart-card">
                <div class="form-card-header"><h4><i class="bi bi-trophy"></i> Top 5 Stok Barang</h4></div>
                <div class="top-customer-list">
                    <?php
                    $maxValProduk = max(array_column($top5Produk, 'stok_available') ?: [1]); $rank = 1;
                    foreach ($top5Produk as $prod):
                        $val = $prod['stok_available'] ?? 0; $pct = $maxValProduk > 0 ? round($val / $maxValProduk * 100) : 0;
                    ?>
                    <div class="tc-item">
                        <div class="tc-rank"><?= $rank++ ?></div>
                        <div class="tc-body">
                            <div class="tc-name-row">
                                <span class="tc-name"><?= htmlspecialchars($prod['nama'] ?? '') ?></span>
                                <span class="tc-val"><?= number_format($val) ?> <?= htmlspecialchars($prod['satuan'] ?? '') ?></span>
                            </div>
                            <div class="tc-bar-bg"><div class="tc-bar-fill" style="width:<?= $pct ?>%"></div></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="section-label">Detail Data Persediaan</div>
        <div class="table-card">
            <div class="table-header">
                <h4><i class="bi bi-clipboard-data"></i> Daftar Persediaan <span class="count-badge"><?= count($listProduk) ?></span></h4>
            </div>
            <div class="table-wrap">
                <table id="stokTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th class="col-right">HPP (Rp)</th>
                            <th class="col-right">Stok Fisik</th>
                            <th class="col-right">Qty Tersedia</th>
                            <th class="col-right">Jumlah Harga HPP</th>
                            <th class="col-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listProduk)): ?>
                        <tr><td colspan="10" class="empty-state"><i class="bi bi-inboxes"></i><span>Tidak ada data barang sesuai filter.</span></td></tr>
                        <?php else: ?>
                        <?php foreach ($listProduk as $i => $row):
                            $stok_fisik = $row['stok'] ?? 0;
                            $stok_tersedia = $row['stok_available'] ?? 0;
                            $hpp = $row['harga'] ?? 0;
                            $harga_jual = $row['harga_jual'] ?? 0;
                            
                            $jumlahHargaHpp = $stok_tersedia * $hpp;
                            $jumlahHargaJual = $stok_tersedia * $harga_jual;
                            
                            $cls  = ($stok_tersedia <= 0) ? 'danger' : (($stok_tersedia <= $row['stok_min']) ? 'danger' : (($stok_tersedia <= $row['stok_min']*3) ? 'warn' : 'ok'));
                            $status = ($stok_tersedia <= 0) ? 'Habis' : (($stok_tersedia <= $row['stok_min']) ? 'Kritis' : 'OK');
                            
                            $tampilKodeHtml = !empty($row['kode']) ? $row['kode'] : $row['kode_produk_alt'];
                        ?>
                        <tr class="<?= $cls === 'danger' ? 'row-kritis' : '' ?>">
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-mid" style="font-size:0.8rem;"><?= htmlspecialchars($tampilKodeHtml) ?></td>
                            <td style="font-weight:600; color:var(--text);"><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                            
                            <td class="col-right text-muted"><?= number_format($hpp) ?></td>
                        
                            
                            <td class="col-right text-muted"><?= number_format($stok_fisik) ?></td>
                            <td class="col-right" style="font-weight: 700;"><?= number_format($stok_tersedia) ?> <span style="font-size:0.75rem; color:#888; font-weight:normal;"><?= htmlspecialchars($row['satuan'] ?? '') ?></span></td>
                            
                            <td class="col-right" style="color: #2563eb; font-weight: 600;"><?= number_format($jumlahHargaHpp) ?></td>
                            
                            <td class="col-center"><span class="status-<?= $cls ?>"><?= $status ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="5" class="fw-mid" style="text-align: left; padding-left: 20px;">Total Keseluruhan Valuasi Persediaan</td>
                            <td class="col-right fw-mid" style="color: #2563eb; font-weight:700;"><?= number_format($totalValuasiHpp) ?></td>
                            <td class="col-right fw-mid" style="color: #10b981; font-weight:700;"><?= number_format($totalValuasiJual) ?></td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($listProduk)): ?>
            <div class="table-footer no-print"><span class="text-muted" id="tableCount">Menampilkan <?= count($listProduk) ?> data</span></div>
            <?php endif; ?>
        </div>

        <!-- TTD SECTION PRINT -->
        <div class="ttd-container">
            <div class="ttd-box">
                <p>Dibuat Oleh,<br><strong>Admin Gudang</strong></p>
                <div class="ttd-space"></div>
                <p class="ttd-name">( <?= htmlspecialchars($_SESSION['user']['username'] ?? '.......................') ?> )</p>
            </div>
            
            <div class="ttd-box">
                <p>Mengetahui,<br><strong>Manager Operasional</strong></p>
                <div class="ttd-space"></div>
                <p class="ttd-name">( ...................................... )</p>
            </div>
        </div>
    </div>
</main>

<script>
    <?php if (!empty($byKategori)): ?>
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor  = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
    const labelColor = () => isDark() ? '#9ca3af' : '#888580';
    const accent     = '#2563eb';

    function chartDefaults() {
      return { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: gridColor() }, ticks: { color: labelColor() } }, y: { grid: { color: gridColor() }, ticks: { color: labelColor() } } } };
    }
    const labels = <?= $chartKatLabels ?>; const dataStok = <?= $chartKatData ?>;
    const ctxCount = document.getElementById('chartKategori');
    let chartKategori = new Chart(ctxCount, { type: 'bar', data: { labels: labels, datasets: [{ label: 'Total Stok', data: dataStok, backgroundColor: accent + 'cc', borderColor: accent, borderWidth: 1.5, borderRadius: 6 }] }, options: chartDefaults() });
    <?php endif; ?>

    const htmlEl = document.documentElement;
    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const isDark = htmlEl.getAttribute('data-theme') === 'dark';
        htmlEl.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
    });
</script>
</body>
</html>