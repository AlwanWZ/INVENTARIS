<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';
require_once '../../../src/models/PO.php';
require_once '../../../src/models/Customer.php';

// 🚀 LOGIKA POST UNTUK MANAGER APPROVE/REJECT DENGAN ALASAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if ($_SESSION['user']['role'] === 'manager') {
        $poId = (int)$_POST['po_id'];
        $newStatus = $_POST['new_status']; // 'approved' atau 'rejected'
        $alasanReject = trim($_POST['alasan_reject'] ?? '');
        
        if (in_array($newStatus, ['approved', 'rejected'])) {
            if ($newStatus === 'rejected') {
                // Simpan status REJECTED beserta alasannya
                $stmt = $pdo->prepare("UPDATE po SET status = ?, alasan_reject = ? WHERE id = ?");
                $stmt->execute([$newStatus, $alasanReject, $poId]);
            } else {
                // Simpan status APPROVED dan bersihkan alasan reject lama (jika ada)
                $stmt = $pdo->prepare("UPDATE po SET status = ?, alasan_reject = NULL WHERE id = ?");
                $stmt->execute([$newStatus, $poId]);
            }
            header("Location: index.php?updated=1");
            exit;
        }
    }
}

// --- Filters ---
$filter = [
    'period'   => $_GET['period']   ?? '',
    'from'     => $_GET['from']     ?? '',
    'to'       => $_GET['to']       ?? '',
    'customer' => $_GET['customer'] ?? '',
    'status'   => $_GET['status']   ?? '',
    'search'   => $_GET['search']   ?? '',
];

// Logika filter periode cepat
if ($filter['period'] === 'this_week') {
    $filter['from'] = date('Y-m-d', strtotime('monday this week'));
    $filter['to'] = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter['period'] === 'this_month') {
    $filter['from'] = date('Y-m-01');
    $filter['to'] = date('Y-m-t');
}

$customers = Customer::getAll();

// --- Query (Ditambahkan po.alasan_reject) ---
function getFilteredPOs($filter) {
    global $pdo;
    $sql = "SELECT po.*, customers.perusahaan, 
                   (SELECT SUM(qty * harga_satuan) FROM po_items WHERE po_items.po_id = po.id) AS total
            FROM po
            LEFT JOIN customers ON po.customer_id = customers.id
            WHERE 1=1";
    $params = [];
    if ($filter['from'])     { $sql .= " AND po.tanggal >= :from";          $params['from']     = $filter['from']; }
    if ($filter['to'])       { $sql .= " AND po.tanggal <= :to";            $params['to']       = $filter['to']; }
    if ($filter['customer']) { $sql .= " AND po.customer_id = :customer";   $params['customer'] = $filter['customer']; }
    if ($filter['status'])   { $sql .= " AND po.status = :status";          $params['status']   = $filter['status']; }
    if ($filter['search'])   {
        $sql .= " AND (po.nomor_po LIKE :search OR customers.perusahaan LIKE :search)";
        $params['search'] = "%{$filter['search']}%";
    }
    $sql .= " ORDER BY po.tanggal DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pos = getFilteredPOs($filter);

// --- Summary ---
$totalPO         = count($pos);
$totalTransaksi  = array_sum(array_map(fn($p) => $p['total'] ?? 0, $pos));
$approvedCount   = count(array_filter($pos, fn($p) => strtolower($p['status']) === 'approved'));
$completedCount  = count(array_filter($pos, fn($p) => strtolower($p['status']) === 'completed'));
$draftCount      = count(array_filter($pos, fn($p) => strtolower($p['status']) === 'draft'));
$rejectedCount   = count(array_filter($pos, fn($p) => strtolower($p['status']) === 'rejected'));

// --- Chart data ---
$byMonth = [];
foreach ($pos as $p) {
    $month = substr($p['tanggal'], 0, 7);
    if (!isset($byMonth[$month])) $byMonth[$month] = ['count' => 0, 'total' => 0];
    $byMonth[$month]['count']++;
    $byMonth[$month]['total'] += $p['total'] ?? 0;
}
ksort($byMonth);
$chartLabels = json_encode(array_map(fn($m) => date('M Y', strtotime($m . '-01')), array_keys($byMonth)));
$chartCounts = json_encode(array_column(array_values($byMonth), 'count'));
$chartTotals = json_encode(array_column(array_values($byMonth), 'total'));

$byCustomer = [];
foreach ($pos as $p) {
    $name = $p['perusahaan'] ?: 'Unknown';
    if (!isset($byCustomer[$name])) $byCustomer[$name] = 0;
    $byCustomer[$name] += $p['total'] ?? 0;
}
arsort($byCustomer);
$topCustomers = array_slice($byCustomer, 0, 5, true);

function formatRp($n) {
    return 'Rp ' . number_format($n ?? 0, 0, ',', '.');
}
define('BADGE_OK', 'ok');
define('BADGE_WARN', 'warn');
define('BADGE_NEUTRAL', 'neutral');

function badgeCls($s) {
    return match(strtolower($s)) { 
        'approved','completed' => BADGE_OK, 
        'rejected' => BADGE_WARN, 
        default => BADGE_NEUTRAL 
    };
}

$hasFilter = array_filter($filter);
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Pesanan PCB | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/laporan.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Laporan Pesanan PCB</span>
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

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert-success" style="margin-bottom: 20px;">
        <i class="bi bi-check-circle"></i> Status keputusan manager berhasil diproses!
      </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Laporan Pesanan PCB</h1>
        <p class="page-subtitle">Rekap dan analisis transaksi Pesanan PCB<?= $hasFilter ? ' — <strong>Filter aktif</strong>' : '' ?></p>
      </div>
      <button class="btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak
      </button>
    </div>

    <div class="form-card filter-card">
      <div class="form-card-header">
        <h4><i class="bi bi-funnel"></i> Filter Laporan</h4>
        <?php if ($hasFilter): ?>
          <a href="index.php" class="btn-ghost-xs"><i class="bi bi-x"></i> Reset</a>
        <?php endif; ?>
      </div>
      <form method="get" class="filter-form">
        <div class="filter-group">
          <label class="form-label">Periode Waktu</label>
          <select name="period" class="form-control" onchange="this.form.submit()">
            <option value="">Pilih Rentang / Kustom</option>
            <option value="this_week" <?= $filter['period'] === 'this_week' ? 'selected' : '' ?>>Minggu Ini</option>
            <option value="this_month" <?= $filter['period'] === 'this_month' ? 'selected' : '' ?>>Bulan Ini</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="form-label">Dari Tgl</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filter['from']) ?>" <?= $filter['period'] ? 'readonly style="opacity:0.6;"' : '' ?>>
        </div>
        <div class="filter-group">
          <label class="form-label">Sampai Tgl</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filter['to']) ?>" <?= $filter['period'] ? 'readonly style="opacity:0.6;"' : '' ?>>
        </div>
        <div class="filter-group">
          <label class="form-label">Customer</label>
          <select name="customer" class="form-control">
            <option value="">Semua Customer</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $filter['customer'] == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['perusahaan']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="">Semua Status</option>
            <option value="draft"     <?= $filter['status'] === 'draft'     ? 'selected' : '' ?>>Draft</option>
            <option value="approved"  <?= $filter['status'] === 'approved'  ? 'selected' : '' ?>>Approved</option>
            <option value="completed" <?= $filter['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="rejected"  <?= $filter['status'] === 'rejected'  ? 'selected' : '' ?>>Rejected</option>
          </select>
        </div>
        <div class="filter-group filter-search">
          <label class="form-label">Cari</label>
          <input type="text" name="search" class="form-control" placeholder="No. PO / perusahaan..." value="<?= htmlspecialchars($filter['search']) ?>">
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
        </div>
      </form>
    </div>

    <div class="section-label">Ringkasan</div>
    <div class="kpi-row">
      <div class="kpi-card"><div class="kpi-icon orange"><i class="bi bi-file-earmark-text"></i></div><div class="kpi-body"><span class="kpi-label">Total Pesanan PCB</span><span class="kpi-val"><?= $totalPO ?></span></div></div>
      <div class="kpi-card"><div class="kpi-icon blue"><i class="bi bi-cash-stack"></i></div><div class="kpi-body"><span class="kpi-label">Total Nilai Transaksi</span><span class="kpi-val kpi-val-sm"><?= formatRp($totalTransaksi) ?></span></div></div>
      <div class="kpi-card"><div class="kpi-icon green"><i class="bi bi-check-circle"></i></div><div class="kpi-body"><span class="kpi-label">Approved</span><span class="kpi-val"><?= $approvedCount ?></span><span class="kpi-sub"><?= $completedCount ?> completed</span></div></div>
      <div class="kpi-card"><div class="kpi-icon red"><i class="bi bi-x-circle"></i></div><div class="kpi-body"><span class="kpi-label">Rejected</span><span class="kpi-val"><?= $rejectedCount ?></span></div></div>
      <div class="kpi-card"><div class="kpi-icon purple"><i class="bi bi-hourglass-split"></i></div><div class="kpi-body"><span class="kpi-label">Draft</span><span class="kpi-val"><?= $draftCount ?></span></div></div>
    </div>

    <?php if (!empty($byMonth)): ?>
    <div class="section-label">Visualisasi</div>
    <div class="charts-row">
      <div class="form-card chart-card"><div class="form-card-header"><h4><i class="bi bi-bar-chart-line"></i> Jumlah Pesanan PCB per Bulan</h4></div><div class="chart-wrap"><canvas id="chartCount"></canvas></div></div>
      <div class="form-card chart-card"><div class="form-card-header"><h4><i class="bi bi-trophy"></i> Top 5 Pelanggan</h4></div><div class="top-customer-list">
          <?php $maxVal = max(array_values($topCustomers) ?: [1]); $rank = 1; foreach ($topCustomers as $name => $val): $pct = $maxVal > 0 ? round($val / $maxVal * 100) : 0; ?>
          <div class="tc-item"><div class="tc-rank"><?= $rank++ ?></div><div class="tc-body"><div class="tc-name-row"><span class="tc-name"><?= htmlspecialchars($name) ?></span><span class="tc-val"><?= formatRp($val) ?></span></div><div class="tc-bar-bg"><div class="tc-bar-fill" style="width:<?= $pct ?>%"></div></div></div></div>
          <?php endforeach; ?>
        </div></div>
    </div>
    <?php endif; ?>

    <div class="section-label">Detail Data</div>
    <div class="form-card">
      <div class="form-card-header">
        <h4><i class="bi bi-table"></i> Data Pesanan PCB <span class="count-badge"><?= $totalPO ?></span></h4>
        <div class="search-wrap"><i class="bi bi-search"></i><input type="text" id="tableSearch" class="search-input" placeholder="Cari nomor pesanan atau pelanggan..."></div>
      </div>
      <div class="table-wrap">
        <table id="poTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nomor Pesanan</th>
              <th>Pelanggan</th>
              <th>Tanggal</th>
              <th>Status</th>
              <th class="col-right">Total</th>
              <th class="col-center" style="width: 150px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pos)): ?>
            <tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i><span>Tidak ada pesanan PCB.</span></td></tr>
            <?php else: ?>
            <?php foreach ($pos as $i => $po): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-mid"><?= htmlspecialchars($po['nomor_po']) ?></td>
              <td><?= htmlspecialchars($po['perusahaan'] ?? '—') ?></td>
              <td class="text-muted"><?= htmlspecialchars($po['tanggal']) ?></td>
              
              <td>
                <span class="badge <?= badgeCls($po['status']) ?>"><?= htmlspecialchars(ucfirst($po['status'])) ?></span>
                <?php if (strtolower($po['status']) === 'rejected' && !empty($po['alasan_reject'])): ?>
                    <div style="font-size: 0.75rem; color: #e11d48; margin-top: 4px; max-width: 180px; font-style: italic; font-weight: 500; line-height: 1.2;">
                        ❌ Alasan: <?= htmlspecialchars($po['alasan_reject']) ?>
                    </div>
                <?php endif; ?>
              </td>
              
              <td class="col-right fw-mid"><?= formatRp($po['total']) ?></td>
              
              <td class="col-center">
                <?php if ($_SESSION['user']['role'] === 'manager' && strtolower($po['status']) === 'draft'): ?>
                    <form method="post" style="display: flex; gap: 6px; justify-content: center;" id="form-po-<?= $po['id'] ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                        <input type="hidden" name="alasan_reject" id="alasan-input-<?= $po['id'] ?>" value="">
                        
                        <button type="submit" name="new_status" value="approved" class="btn-icon" style="background:#ecfdf5; color:#059669; border:1px solid #34d399;" title="Approve Pesanan" onclick="return confirm('Apakah Anda yakin menyetujui pesanan ini?');">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        
                        <button type="button" class="btn-icon" style="background:#fff1f2; color:#e11d48; border:1px solid #fda4af;" title="Reject Pesanan" onclick="mintaAlasanReject(<?= $po['id'] ?>)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        
                        <button type="submit" name="new_status" value="rejected" id="btn-submit-reject-<?= $po['id'] ?>" style="display:none;"></button>
                    </form>
                <?php else: ?>
                    <span style="font-size: 0.75rem; color: #9ca3af;"><i class="bi bi-shield-check"></i> Terkunci</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row"><td colspan="5" class="fw-mid">Total Keseluruhan Pesanan PCB</td><td class="col-right fw-mid"><?= formatRp($totalTransaksi) ?></td><td></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
// 🚀 JAVASCRIPT MANDRAGUNA UNTUK POP-UP ALASAN REJECT
function mintaAlasanReject(poId) {
    const alasan = prompt("⚠️ MASUKKAN ALASAN PENOLAKAN PESANAN:\n(Alasan ini akan langsung tampil di layar Marketing)");
    
    if (alasan === null) return; // Jika klik 'Batal' di prompt, hentikan proses.
    
    if (alasan.trim() === "") {
        alert("❌ GAGAL! Alasan penolakan wajib diisi agar divisi Marketing tahu letak kesalahannya.");
        return;
    }
    
    // Masukkan teks alasan ke input hidden, lalu tembak submit gaibnya!
    document.getElementById('alasan-input-' + poId).value = alasan;
    document.getElementById('btn-submit-reject-' + poId).click();
}

// Search & Chart scripts tetap berjalan normal di bawah ini...
const tableSearch = document.getElementById('tableSearch');
const tableCount  = document.getElementById('tableCount');
const poTable     = document.getElementById('poTable');
tableSearch?.addEventListener('input', function () {
  const q = this.value.toLowerCase(); let visible = 0;
  poTable.querySelectorAll('tbody tr:not(.total-row)').forEach(row => {
    const match = row.textContent.toLowerCase().includes(q); row.style.display = match ? '' : 'none'; if (match) visible++;
  });
  if (tableCount) tableCount.textContent = `Menampilkan ${visible} data`;
});
<?php if (!empty($byMonth)): ?>
const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
const gridColor  = () => isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
const labelColor = () => isDark() ? '#9ca3af' : '#888580';
const accent     = '#e8621a';
function chartDefaults() {
  return { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { color: gridColor() }, ticks: { color: labelColor() } }, y: { grid: { color: gridColor() }, ticks: { color: labelColor() } } } };
}
const labels = <?= $chartLabels ?>; const counts = <?= $chartCounts ?>;
const ctxCount = document.getElementById('chartCount');
let chartCount = new Chart(ctxCount, { type: 'bar', data: { labels, datasets: [{ label: 'Jumlah Pesanan', data: counts, backgroundColor: accent + 'cc', borderColor: accent, borderWidth: 1.5, borderRadius: 6 }] }, options: chartDefaults() });
<?php endif; ?>
</script>
</body>
</html>