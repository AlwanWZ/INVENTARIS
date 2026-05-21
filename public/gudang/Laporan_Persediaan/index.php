<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['gudang', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';

$search    = $_GET['search']   ?? '';
$kategori  = $_GET['kategori'] ?? '';
$hasFilter = $search || $kategori;

$listKategori = $pdo->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);

// Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="laporan_persediaan.xls"');
        echo "<table border='1'>";
        echo "<tr><th>No</th><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Satuan</th><th>Stok</th><th>Stok Min</th><th>Status</th></tr>";
        $sql = "SELECT p.id, p.kode, p.nama, p.satuan, p.stok, COALESCE(p.stok_min, 10) AS stok_min, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE 1=1";
        $params = [];
        if ($search) {
                $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
        }
        if ($kategori) {
                $sql .= " AND p.kategori_id = ?";
                $params[] = $kategori;
        }
        $sql .= " ORDER BY p.nama ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $listProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($listProduk as $i => $row) {
                $status = ($row['stok'] <= 0) ? 'Habis' : (($row['stok'] <= $row['stok_min']) ? 'Kritis' : 'OK');
                echo "<tr>";
                echo "<td>".($i+1)."</td>";
                echo "<td>".htmlspecialchars($row['kode'] ?? '')."</td>";
                echo "<td>".htmlspecialchars($row['nama'] ?? '')."</td>";
                echo "<td>".htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori')."</td>";
                echo "<td>".htmlspecialchars($row['satuan'] ?? '')."</td>";
                echo "<td>".number_format($row['stok'])."</td>";
                echo "<td>".number_format($row['stok_min'])."</td>";
                echo "<td>".$status."</td>";
                echo "</tr>";
        }
        echo "</table>";
        exit;
}

$sql = "SELECT p.id, p.kode, p.nama, p.satuan, p.stok, COALESCE(p.stok_min, 10) AS stok_min, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE 1=1";
$params = [];
if ($search) {
        $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
}
if ($kategori) {
        $sql .= " AND p.kategori_id = ?";
        $params[] = $kategori;
}
$sql .= " ORDER BY p.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Summary Data ---
$totalProduk = count($listProduk);
$totalFG     = array_sum(array_column($listProduk, 'stok'));
$kritisItems = array_filter($listProduk, fn($p) => $p['stok'] <= $p['stok_min']);
$maxStok     = $totalProduk > 0 ? max(array_column($listProduk, 'stok')) : 1;

// --- Chart Data: Stok per Kategori ---
$byKategori = [];
foreach ($listProduk as $p) {
    $kat = $p['nama_kategori'] ?: 'Tanpa Kategori';
    if (!isset($byKategori[$kat])) $byKategori[$kat] = 0;
    $byKategori[$kat] += $p['stok'];
}
arsort($byKategori);
$chartKatLabels = json_encode(array_keys($byKategori));
$chartKatData   = json_encode(array_values($byKategori));

// --- Top 5 Produk (Stok Terbanyak) ---
$topProduk = $listProduk;
usort($topProduk, function($a, $b) {
    return $b['stok'] <=> $a['stok'];
});
$top5Produk = array_slice($topProduk, 0, 5);

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
            .topbar, .page-header .header-actions, .filter-card, nav, .btn-ghost-sm { display:none !important; }
            .main { margin:0 !important; }
            .table-card { box-shadow:none; border:none; }
        }
    </style>
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
                    <span>Laporan Persediaan</span>
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
        <div class="alert-late" style="margin-bottom: 20px;">
            <i class="bi bi-exclamation-triangle"></i>
            <strong><?= count($kritisItems) ?> produk</strong> stok kritis atau habis. Segera lakukan restocking.
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title-lg">Laporan Persediaan</h1>
                <p class="page-subtitle">Rekapitulasi dan analisis stok barang di gudang.</p>
            </div>
            <div class="header-actions" style="display: flex; gap: 10px;">
                <button class="btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak
                </button>
                <a href="?export=excel&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori) ?>" class="btn-ghost-sm">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </a>
            </div>
        </div>

        <div class="form-card filter-card">
            <div class="form-card-header">
                <h4><i class="bi bi-funnel"></i> Filter Laporan</h4>
                <?php if ($hasFilter): ?>
                <a href="index.php" class="btn-ghost-xs"><i class="bi bi-x"></i> Reset</a>
                <?php endif; ?>
            </div>
            <form method="get" class="filter-form">
                <div class="filter-group filter-search">
                    <label class="form-label">Cari Nama / Kode</label>
                    <input type="text" name="search" class="form-control" placeholder="Cari produk..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-control">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($listKategori as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= $kategori == $kat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat['nama_kategori'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
                </div>
            </form>
        </div>

        <div class="section-label">Ringkasan</div>
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon blue"><i class="bi bi-boxes"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Jenis Produk</span>
                    <span class="kpi-val"><?= number_format($totalProduk) ?></span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon green"><i class="bi bi-check-circle"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Stok FG</span>
                    <span class="kpi-val"><?= number_format($totalFG) ?></span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon orange"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Stok Kritis/Habis</span>
                    <span class="kpi-val"><?= count($kritisItems) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($byKategori)): ?>
        <div class="section-label">Visualisasi</div>
        <div class="charts-row">

            <div class="form-card chart-card">
                <div class="form-card-header">
                    <h4><i class="bi bi-bar-chart-line"></i> Total Stok per Kategori</h4>
                </div>
                <div class="chart-wrap">
                    <canvas id="chartKategori"></canvas>
                </div>
            </div>

            <div class="form-card chart-card">
                <div class="form-card-header">
                    <h4><i class="bi bi-trophy"></i> Top 5 Stok Produk</h4>
                </div>
                <div class="top-customer-list">
                    <?php
                    $maxValProduk = max(array_column($top5Produk, 'stok') ?: [1]);
                    $rank = 1;
                    foreach ($top5Produk as $prod):
                        $val = $prod['stok'];
                        $pct = $maxValProduk > 0 ? round($val / $maxValProduk * 100) : 0;
                    ?>
                    <div class="tc-item">
                        <div class="tc-rank"><?= $rank++ ?></div>
                        <div class="tc-body">
                            <div class="tc-name-row">
                                <span class="tc-name"><?= htmlspecialchars($prod['nama'] ?? '') ?></span>
                                <span class="tc-val"><?= number_format($val) ?> <?= htmlspecialchars($prod['satuan'] ?? '') ?></span>
                            </div>
                            <div class="tc-bar-bg">
                                <div class="tc-bar-fill" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
        <?php endif; ?>

        <div class="section-label">Detail Data</div>
        <div class="table-card">
            <div class="table-header">
                <h4><i class="bi bi-clipboard-data"></i> Daftar Persediaan <span class="count-badge"><?= count($listProduk) ?></span></h4>
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
                            <th class="col-right">Stok</th>
                            <th class="col-right">Stok Min</th>
                            <th class="col-center">Status</th>
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
                        <?php foreach ($listProduk as $i => $row):
                            $cls  = levelCls($row['stok'], $row['stok_min']);
                            $status = ($row['stok'] <= 0) ? 'Habis' : (($row['stok'] <= $row['stok_min']) ? 'Kritis' : 'OK');
                        ?>
                        <tr class="<?= $cls === 'danger' ? 'row-kritis' : '' ?>">
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-mid" style="font-size:0.8rem;"><?= htmlspecialchars($row['kode'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                            <td>
                                <span class="badge neutral"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori') ?></span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($row['satuan'] ?? '') ?></td>
                            <td class="col-right">
                                <?= number_format($row['stok']) ?>
                            </td>
                            <td class="col-right">
                                <?= number_format($row['stok_min']) ?>
                            </td>
                            <td class="col-center">
                                <span class="status-<?= $cls ?>"><?= $status ?></span>
                            </td>
                            <td class="col-center">
                                <a href="kartu_stok.php?produk_id=<?= $row['id'] ?>" class="btn-outline btn-xs"><i class="bi bi-clipboard-check"></i> Kartu Stok</a>
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
    // ── Table search ──────────────────────────────────────────
    const si = document.getElementById('tableSearch');
    const tc = document.getElementById('tableCount');
    si?.addEventListener('input', function () {
        const q = this.value.toLowerCase(); let v = 0;
        document.querySelectorAll('#stokTable tbody tr').forEach(r => {
            const m = r.textContent.toLowerCase().includes(q);
            r.style.display = m ? '' : 'none';
            if (m) v++;
        });
        if (tc) tc.textContent = `Menampilkan ${v} data`;
    });

    // ── Charts (hanya render jika ada data) ──────────────────
    <?php if (!empty($byKategori)): ?>
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    const gridColor  = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
    const labelColor = () => isDark() ? '#9ca3af' : '#888580';
    const accent     = '#2563eb'; // Warna biru biar beda dari Laporan PO yang oren

    function chartDefaults() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: isDark() ? '#1f2226' : '#fff',
            titleColor: isDark() ? '#f1f0ee' : '#1a1714',
            bodyColor:  isDark() ? '#9ca3af'  : '#4b4843',
            borderColor: isDark() ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.10)',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8,
          }
        },
        scales: {
          x: { grid: { color: gridColor() }, ticks: { color: labelColor(), font: { size: 11 } } },
          y: { grid: { color: gridColor() }, ticks: { color: labelColor(), font: { size: 11 } } }
        }
      };
    }

    const labels = <?= $chartKatLabels ?>;
    const dataStok = <?= $chartKatData ?>;

    const ctxCount = document.getElementById('chartKategori');
    let chartKategori = new Chart(ctxCount, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Total Stok',
          data: dataStok,
          backgroundColor: accent + 'cc',
          borderColor: accent,
          borderWidth: 1.5,
          borderRadius: 6,
        }]
      },
      options: { ...chartDefaults(), plugins: { ...chartDefaults().plugins } }
    });

    // Re-render chart saat theme berubah (Light/Dark mode)
    document.getElementById('themeToggle')?.addEventListener('click', () => {
      setTimeout(() => {
        chartKategori.destroy();
        chartKategori = new Chart(ctxCount, {
          type: 'bar',
          data: { labels: labels, datasets: [{ label: 'Total Stok', data: dataStok, backgroundColor: accent + 'cc', borderColor: accent, borderWidth: 1.5, borderRadius: 6 }] },
          options: { ...chartDefaults() }
        });
      }, 50);
    });
    <?php endif; ?>
</script>
</body>
</html>