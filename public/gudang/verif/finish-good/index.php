<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Verifikasi.php'; 

$verifModel = new Verifikasi($pdo);
$search    = $_GET['search'] ?? '';
$status    = $_GET['status'] ?? '';
$hasFilter = $search || $status;

// Mengambil data barang masuk (Finish Good)
$list      = $verifModel->getAll('finish_good', $search, $status);

$statusOptions = ['' => 'Semua Status', 'draft' => 'Draft', 'verified' => 'Selesai (Masuk Stok)'];

// Ubah label biar lebih gampang dipahami orang gudang
function fgBadgeCls($s)  { return match($s) { 'verified' => 'ok', default => 'warn' }; }
function fgBadgeLabel($s){ return match($s) { 'verified' => 'Stok Bertambah', default => 'Draft' }; }
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Finish Good (Barang Masuk) | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/verifikasi.css" rel="stylesheet">
</head>
<body>
<?php include '../../../../templates/nav.php'; ?>
<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Finish Good</span>
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

    <?php if (isset($_GET['success'])): ?><div class="alert-success"><i class="bi bi-check-circle"></i> Data Finish Good berhasil disimpan. Stok otomatis bertambah!</div>
    <?php elseif (isset($_GET['updated'])): ?><div class="alert-success"><i class="bi bi-check-circle"></i> Data Finish Good berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?><div class="alert-warn"><i class="bi bi-trash"></i> Data Finish Good berhasil dihapus dan stok telah disesuaikan ulang.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Finish Good (Barang Masuk)</h1>
        <p class="page-subtitle">Pencatatan barang jadi dari divisi produksi. Data yang tersimpan akan otomatis menambah stok gudang.</p>
      </div>
      <a href="crud/add.php" class="btn-primary"><i class="bi bi-box-arrow-in-down"></i> Tambah Barang Masuk</a>
    </div>

    <div class="stat-row">
      <div class="stat-pill"><span class="stat-pill-label">Total Transaksi</span><span class="stat-pill-val"><?= count($list) ?></span></div>
      <div class="stat-pill"><span class="stat-pill-label">Masuk Stok</span><span class="stat-pill-val ok"><?= count(array_filter($list, fn($r) => $r['status']==='verified')) ?></span></div>
      <div class="stat-pill"><span class="stat-pill-label">Draft</span><span class="stat-pill-val warn"><?= count(array_filter($list, fn($r) => $r['status']==='draft')) ?></span></div>
    </div>

    <div class="form-card filter-card">
      <div class="form-card-header">
        <h4><i class="bi bi-funnel"></i> Filter & Pencarian</h4>
        <?php if ($hasFilter): ?><a href="index.php" class="btn-ghost-xs"><i class="bi bi-x"></i> Reset</a><?php endif; ?>
      </div>
      <form method="get" action="index.php" class="filter-form">
        <div class="filter-group filter-search">
          <label class="form-label">Cari Data</label>
          <input type="text" name="search" class="form-control" placeholder="No. Dokumen / PIC... (Tekan Enter)" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group">
          <label class="form-label">Status Masuk</label>
          <select name="status" class="form-control" onchange="this.form.submit()">
            <?php foreach ($statusOptions as $k => $v): ?>
              <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="bi bi-search"></i> Cari</button>
        </div>
      </form>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-boxes"></i> Histori Barang Masuk <span class="count-badge"><?= count($list) ?></span></h4>
        </div>
      <div class="table-wrap">
        <table id="verifTable">
          <thead>
            <tr>
              <th>No</th>
              <th>No. Finish Good</th>
              <th>Tanggal Masuk</th>
              <th>PIC Gudang</th>
              <th>Jml Item Masuk</th>
              <th>Status Stok</th>
              <th style="text-align: center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($list)): ?>
            <tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i><span>Belum ada histori barang masuk atau data tidak ditemukan. <a href="index.php">Reset Filter</a></span></td></tr>
            <?php else: ?>
            <?php foreach ($list as $i => $row): ?>
            <tr>
              <td class="text-muted"><?= $i+1 ?></td>
              <td class="fw-mid"><?= htmlspecialchars($row['nomor_penerimaan'] ?? 'FG-'.$row['id']) ?></td>
              <td class="text-muted"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
              <td style="font-weight: 500; color: #111827;"><?= htmlspecialchars($row['pic_name']) ?></td>
              
              <td style="font-weight: 700; color: #059669;"><?= (int)($row['total_ok'] ?? 0) ?> <span style="font-weight: normal; font-size: 0.75rem; color:#888;">Unit</span></td>
              
              <td><span class="badge <?= fgBadgeCls($row['status']) ?>"><?= fgBadgeLabel($row['status']) ?></span></td>
              <td style="text-align: center;">
                <div class="action-btns" style="justify-content: center; gap: 6px;">
                  <a href="crud/detail.php?id=<?= $row['id'] ?>" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
                  <a href="crud/edit.php?id=<?= $row['id'] ?>" class="btn-icon edit" title="Edit Data"><i class="bi bi-pencil"></i></a>
                  <button type="button" class="btn-icon danger" onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nomor_penerimaan'], ENT_QUOTES) ?>')" title="Hapus Data"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($list)): ?>
      <div class="table-footer"><span class="text-muted" id="tableCount">Menampilkan <?= count($list) ?> data barang masuk</span></div>
      <?php endif; ?>
    </div>

  </div>
</main>

<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <h3>Hapus Data Barang Masuk?</h3>
    <p>Data Finish Good <strong id="deleteTarget"></strong> akan dihapus permanen. Jika stok sudah bertambah, sistem akan otomatis mengurangi kembali stok tersebut.</p>
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
  // Script pencarian lokal (JS) dihapus sejalan dengan UI di atas.
  
  // Modal Konfirmasi Hapus
  const modal = document.getElementById('deleteModal');
  function confirmDelete(id, label) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteTarget').textContent = label || 'FG-' + id;
    modal.classList.add('show');
  }
  document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if(e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>