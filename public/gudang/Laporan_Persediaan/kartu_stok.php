<?php
session_start();
require_once '../../../src/auth.php';
require_once '../../../src/config.php';

$produk_id = $_GET['produk_id'] ?? null;
if (!$produk_id) {
        header('Location: index.php');
        exit;
}

$produk = $pdo->prepare("SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE p.id = ?");
$produk->execute([$produk_id]);
$row = $produk->fetch(PDO::FETCH_ASSOC);
if (!$row) {
        echo '<div style="padding:2em">Produk tidak ditemukan.</div>';
        exit;
}


// Ambil range tanggal mutasi jika filter belum dipilih
$tgl_awal = $_GET['tgl_awal'] ?? null;
$tgl_akhir = $_GET['tgl_akhir'] ?? null;
if (!$tgl_awal || !$tgl_akhir) {
    $range = $pdo->prepare("SELECT MIN(created_at) as min_tgl, MAX(created_at) as max_tgl FROM stok_log WHERE produk_id = ?");
    $range->execute([$produk_id]);
    $r = $range->fetch(PDO::FETCH_ASSOC);
    if (!$tgl_awal) $tgl_awal = $r['min_tgl'] ? date('Y-m-d', strtotime($r['min_tgl'])) : date('Y-m-01');
    if (!$tgl_akhir) $tgl_akhir = $r['max_tgl'] ? date('Y-m-d', strtotime($r['max_tgl'])) : date('Y-m-d');
}

$mutasi = $pdo->prepare("
    SELECT 
        sl.id, sl.tipe_transaksi, sl.qty_change, sl.stok_before, sl.stok_after, 
        sl.stok_reserved_before, sl.stok_reserved_after, sl.reference_type, 
        sl.reference_id, sl.keterangan, sl.created_by, sl.created_at,
        u.username as created_by_name
    FROM stok_log sl
    LEFT JOIN users u ON sl.created_by = u.id
    WHERE sl.produk_id = ? AND DATE(sl.created_at) BETWEEN ? AND ? 
    ORDER BY sl.created_at ASC, sl.id ASC
");
$mutasi->execute([$produk_id, $tgl_awal, $tgl_akhir]);
$listMutasi = $mutasi->fetchAll(PDO::FETCH_ASSOC);

function tipeMutasi($tipe) {
    $mapping = [
        'po_reserve' => 'PO Reserve',
        'po_unreserve' => 'PO Unreserve',
        'verifikasi_add' => 'Penerimaan (Masuk)',
        'pengeluaran_sub' => 'Pengeluaran (Keluar)',
        'adjustment' => 'Adjustment',
    ];
    return $mapping[$tipe] ?? ucfirst($tipe);
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Stok | InventorySys</title>
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
                    <a href="/Inventaris/public/gudang/stok/index.php">Stok Barang</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/Inventaris/public/gudang/Laporan_Persediaan/index.php">Laporan Persediaan</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Kartu Stok</span>
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

        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title-lg">Kartu Stok</h1>
                <p class="page-subtitle">Mutasi stok produk: <b><?= htmlspecialchars($row['nama'] ?? '') ?></b></p>
            </div>
            <a href="javascript:history.back()" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <div class="stat-row">
            <div class="stat-pill">
                <span class="stat-pill-label">Kode</span>
                <span class="stat-pill-val"><?= htmlspecialchars($row['kode_produk'] ?? $row['kode'] ?? '') ?></span>
            </div>
            <div class="stat-pill">
                <span class="stat-pill-label">Kategori</span>
                <span class="stat-pill-val"><?= htmlspecialchars($row['nama_kategori'] ?? $row['kategori'] ?? 'Tanpa Kategori') ?></span>
            </div>
            <div class="stat-pill">
                <span class="stat-pill-label">Satuan</span>
                <span class="stat-pill-val"><?= htmlspecialchars($row['satuan'] ?? 'pcs') ?></span>
            </div>
            
            <div class="stat-pill">
                <span class="stat-pill-label">Fisik (Gudang)</span>
                <span class="stat-pill-val" style="color: #4b5563;"><?= number_format((int)$row['stok']) ?></span>
            </div>
            <div class="stat-pill">
                <span class="stat-pill-label">Dibooking (PO)</span>
                <span class="stat-pill-val" style="color: #dc3545;"><?= number_format((int)$row['stok_reserved']) ?></span>
            </div>
            <div class="stat-pill" style="border: 1px solid #10b981; background: #f0fdf4;">
                <span class="stat-pill-label" style="color: #059669;">Bisa Dijual (Avail)</span>
                <span class="stat-pill-val" style="color: #059669; font-weight: 800; font-size: 1.1rem;"><?= number_format((int)$row['stok_available']) ?></span>
            </div>
        </div>

        <div class="form-card filter-card">
            <div class="form-card-header">
                <h4><i class="bi bi-calendar"></i> Filter Tanggal</h4>
            </div>
            <form method="get" class="filter-form" style="display:flex;gap:1em;align-items:end;">
                <input type="hidden" name="produk_id" value="<?= htmlspecialchars($produk_id) ?>">
                <div class="filter-group">
                    <label class="form-label">Dari</label>
                    <input type="date" name="tgl_awal" class="form-control" value="<?= htmlspecialchars($tgl_awal ?? '') ?>">
                </div>
                <div class="filter-group">
                    <label class="form-label">Sampai</label>
                    <input type="date" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir ?? '') ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Tampilkan</button>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h4><i class="bi bi-clipboard-check"></i> Mutasi Stok</h4>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe Transaksi</th>
                            <th style="text-align:center;">Qty Change</th>
                            <th style="text-align:center;">Stok Before</th>
                            <th style="text-align:center;">Stok After</th>
                            <th style="text-align:center;">Reserved</th>
                            <th>Keterangan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listMutasi)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="bi bi-inboxes"></i>
                                <span>Tidak ada mutasi stok.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($listMutasi as $m): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                            <td><span class="badge" style="background:#f3f4f6; color:#374151; font-weight:600; padding:4px 8px; border-radius:4px; font-size:0.8rem; border:1px solid #e5e7eb;"><?= tipeMutasi($m['tipe_transaksi']) ?></span></td>
                            <td style="text-align:center; font-weight:700; font-size:1.05rem; color: <?= $m['qty_change'] > 0 ? '#059669' : ($m['qty_change'] < 0 ? '#dc3545' : '#4b5563') ?>">
                                <?= $m['qty_change'] > 0 ? '+' : '' ?><?= number_format((int)$m['qty_change']) ?>
                            </td>
                            <td style="text-align:center; color:#6b7280; font-size:0.95rem;"><?= number_format((int)$m['stok_before']) ?></td>
                            <td style="text-align:center; font-weight:700; color:#111827; font-size:0.95rem;"><?= number_format((int)$m['stok_after']) ?></td>
                            <td style="text-align:center; color:#dc3545; font-size:0.9em; font-weight:600;"><?= number_format((int)$m['stok_reserved_after']) ?></td>
                            <td style="font-size:0.9rem; color:#4b5563;"><?= htmlspecialchars($m['keterangan'] ?? '-') ?></td>
                            <td><span style="font-size:0.8rem; background:#f3f4f6; padding:2px 6px; border-radius:4px; font-weight:500; color:#374151; border:1px solid #d1d5db;"><?= htmlspecialchars($m['created_by_name'] ?? 'Sistem') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<script>
// Dark mode toggle bawaan
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
if (themeBtn) {
    themeBtn.addEventListener('click', () => {
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        themeBtn.querySelector('i').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
    });
}
</script>

</body>
</html>