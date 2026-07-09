<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';

$search    = trim($_GET['search'] ?? '');
$status    = $_GET['status'] ?? '';
$hasFilter = $search || $status;

// 🚀 CUSTOM QUERY TINGKAT DEWA (TANPA pic_name)
$sql = "SELECT p.id, p.nomor_pengeluaran, p.tanggal, p.status,
               s.nomor_spk,
               sj.id AS sj_id, sj.nomor_sj, sj.driver, sj.kendaraan,
               COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), '—') AS customer_nama
        FROM pengeluaran p
        LEFT JOIN spk s ON p.spk_id = s.id
        LEFT JOIN pesanan ON s.pesanan_id = pesanan.id
        LEFT JOIN customers c ON pesanan.customer_id = c.id
        LEFT JOIN surat_jalan sj ON sj.pengeluaran_id = p.id
        WHERE 1=1";

$params = [];
if ($search) {
    $sql .= " AND (p.nomor_pengeluaran LIKE ? OR s.nomor_spk LIKE ? OR sj.driver LIKE ? OR sj.nomor_sj LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusOptions = [
    '' => 'Semua Status',
    'draft' => 'Draft',
    'picking' => 'Picking',
    'packing' => 'Packing',
    'shipped' => 'Shipped',
    'completed' => 'Completed',
];

function badgeCls($s) {
    return match($s) { 'completed' => 'ok', 'shipped' => 'blue', 'packing' => 'purple', 'picking' => 'teal', default => 'warn' };
}
function badgeLabel($s) {
    return match($s) { 'completed' => 'Completed', 'shipped' => 'Shipped', 'packing' => 'Packing', 'picking' => 'Picking', default => 'Draft' };
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pengeluaran Barang & Surat Jalan | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/pengeluaran.css" rel="stylesheet">
  <style>
    .sj-badge { font-size: 0.7rem; background: #e0f2fe; color: #0f766e; padding: 2px 6px; border-radius: 4px; border: 1px solid #99f6e4; margin-top: 4px; display: inline-block; }
    
    /* Tambahan agar tombol aksi rapi */
    .action-btns-flex {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
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
          <span>Pengeluaran & Surat Jalan</span>
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

    <?php if (isset($_GET['success'])): ?><div class="alert-success"><i class="bi bi-check-circle"></i> Transaksi Pengeluaran berhasil diproses & Stok otomatis terpotong!</div>
    <?php elseif (isset($_GET['deleted'])): ?><div class="alert-warn"><i class="bi bi-trash"></i> Data berhasil dihapus.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Pengeluaran Barang</h1>
        <p class="page-subtitle">Satu pintu untuk mengeluarkan barang (memotong stok) dan mencetak Surat Jalan.</p>
      </div>
      <a href="crud/add.php" class="btn-primary"><i class="bi bi-plus-lg"></i> Proses Kirim Barang</a>
    </div>

    <div class="stat-row">
      <div class="stat-pill"><span class="stat-pill-label">Total Transaksi</span><span class="stat-pill-val"><?= count($list) ?></span></div>
      <div class="stat-pill"><span class="stat-pill-label">Completed</span><span class="stat-pill-val ok"><?= count(array_filter($list, fn($p) => $p['status']==='completed')) ?></span></div>
      <div class="stat-pill"><span class="stat-pill-label">Shipped</span><span class="stat-pill-val blue"><?= count(array_filter($list, fn($p) => $p['status']==='shipped')) ?></span></div>
      <div class="stat-pill"><span class="stat-pill-label">Draft</span><span class="stat-pill-val warn"><?= count(array_filter($list, fn($p) => $p['status']==='draft')) ?></span></div>
    </div>

    <div class="form-card filter-card">
      <div class="form-card-header">
        <h4><i class="bi bi-funnel"></i> Filter Pencarian</h4>
        <?php if ($hasFilter): ?><a href="index.php" class="btn-ghost-xs"><i class="bi bi-x"></i> Reset</a><?php endif; ?>
      </div>
      <form method="get" class="filter-form">
        <div class="filter-group filter-search">
          <label class="form-label">Cari Data</label>
          <input type="text" name="search" class="form-control" placeholder="No. Pengeluaran / SPK / Driver / SJ..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach ($statusOptions as $k => $v): ?>
              <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Terapkan Filter</button>
        </div>
      </form>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-truck"></i> Histori Pengiriman <span class="count-badge"><?= count($list) ?></span></h4>
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" id="tableSearch" class="search-input" placeholder="Cari cepat di tabel...">
        </div>
      </div>
      <div class="table-wrap">
        <table id="pengeluaranTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Pengiriman & SPK</th>
              <th>Tujuan (Customer)</th>
              <th>Tanggal</th>
              <th>Kurir / Armada</th>
              <th>Status</th>
              <th style="text-align: center; min-width: 150px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($list)): ?>
            <tr><td colspan="7" class="empty-state"><i class="bi bi-box-arrow-up"></i><span>Belum ada data pengeluaran. <a href="crud/add.php">Kirim barang sekarang</a></span></td></tr>
            <?php else: ?>
            <?php foreach ($list as $i => $row): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              
              <td>
                <div class="fw-mid"><?= htmlspecialchars($row['nomor_pengeluaran']) ?></div>
                <div style="font-size: 0.75rem; color: var(--text3); margin-top:2px;">SPK: <?= htmlspecialchars($row['nomor_spk'] ?? '—') ?></div>
                <?php if ($row['nomor_sj']): ?>
                  <div class="sj-badge"><i class="bi bi-receipt"></i> <?= htmlspecialchars($row['nomor_sj']) ?></div>
                <?php endif; ?>
              </td>

              <td style="font-weight: 500; color: #111827;"><?= htmlspecialchars($row['customer_nama']) ?></td>
              <td class="text-muted"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
              
              <td>
                <div style="font-weight: 600; color: #2563eb;"><?= htmlspecialchars($row['driver'] ?? '—') ?></div>
                <div style="font-size: 0.75rem; color: var(--text3);"><i class="bi bi-car-front"></i> <?= htmlspecialchars($row['kendaraan'] ?? '—') ?></div>
              </td>

              <td><span class="badge <?= badgeCls($row['status']) ?>"><?= badgeLabel($row['status']) ?></span></td>
              
              <td style="text-align: center;">
                <div class="action-btns action-btns-flex">
                  
                  <a href="crud/detail.php?id=<?= $row['id'] ?>" class="btn-icon" title="Detail Pengeluaran & Surat Jalan" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe;">
                    <i class="bi bi-eye-fill"></i>
                  </a>
                  
                  <?php if ($row['sj_id']): ?>
                  <a href="../surat_jln/crud/print_list.php?id=<?= $row['sj_id'] ?>" target="_blank" class="btn-icon" title="Cetak Surat Jalan" style="background: #f0fdfa; color: #0d9488; border: 1px solid #ccfbf1;">
                    <i class="bi bi-printer-fill"></i>
                  </a>
                  <?php endif; ?>

                  <a href="crud/edit.php?id=<?= $row['id'] ?>" class="btn-icon" title="Edit Pengeluaran" style="background: #fffbeb; color: #d97706; border: 1px solid #fde68a;">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <button type="button" class="btn-icon danger" onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nomor_pengeluaran'], ENT_QUOTES) ?>')" title="Hapus Pengeluaran & Surat Jalan">
                    <i class="bi bi-trash"></i>
                  </button>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($list)): ?>
      <div class="table-footer"><span class="text-muted" id="tableCount">Menampilkan <?= count($list) ?> data pengiriman</span></div>
      <?php endif; ?>
    </div>

  </div>
</main>

<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <h3>Hapus Pengiriman?</h3>
    <p>Data <strong id="deleteTarget"></strong> beserta Surat Jalannya akan dihapus permanen. Stok barang yang sudah terpotong mungkin perlu disesuaikan manual.</p>
    <div class="modal-actions">
      <form method="post" action="crud/delete.php">
        <input type="hidden" name="id" id="deleteId">
        <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      </form>
      <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
    </div>
  </div>
</div>

<script>
  const si = document.getElementById('tableSearch');
  const tc = document.getElementById('tableCount');
  si?.addEventListener('input', function() {
    const q = this.value.toLowerCase(); let v = 0;
    document.querySelectorAll('#pengeluaranTable tbody tr').forEach(r => {
      const m = r.textContent.toLowerCase().includes(q); r.style.display = m ? '' : 'none'; if(m) v++;
    });
    if(tc) tc.textContent = `Menampilkan ${v} data`;
  });
  
  const modal = document.getElementById('deleteModal');
  function confirmDelete(id, label) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteTarget').textContent = label;
    modal.classList.add('show');
  }
  document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if(e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>