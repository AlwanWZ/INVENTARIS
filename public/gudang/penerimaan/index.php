<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';
require_once '../../../src/models/Penerimaan.php';

$penerimaanModel = new Penerimaan($pdo);

$search   = $_GET['search']    ?? '';
$status   = $_GET['status']    ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$list    = $penerimaanModel->getAll($search, $status, $dateFrom, $dateTo);
$summary = $penerimaanModel->getSummary();
$hasFilter = $search || $status || $dateFrom || $dateTo;

function badgeCls($s) {
    return match($s) {
        'completed' => 'ok',
        'checked'   => 'blue',
        'received'  => 'purple',
        default     => 'warn',
    };
}
function badgeLabel($s) {
    return match($s) {
        'completed' => 'Completed',
        'checked'   => 'Checked',
        'received'  => 'Received',
        default     => 'Draft',
    };
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Penerimaan Barang | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/penerimaan.css" rel="stylesheet">
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Penerimaan Barang</span>
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

    <!-- NOTIFIKASI -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> Penerimaan berhasil disimpan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> Penerimaan berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert-warn"><i class="bi bi-trash"></i> Penerimaan berhasil dihapus.</div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Penerimaan Barang</h1>
        <p class="page-subtitle">Kelola dan monitoring penerimaan barang masuk gudang.</p>
      </div>
      <a href="crud/add.php" class="btn-primary"><i class="bi bi-plus-lg"></i> Tambah Penerimaan</a>
    </div>

    <!-- STAT ROW -->
    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total</span>
        <span class="stat-pill-val"><?= $summary['total'] ?? 0 ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Completed</span>
        <span class="stat-pill-val ok"><?= $summary['completed'] ?? 0 ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Pending</span>
        <span class="stat-pill-val warn"><?= $summary['pending'] ?? 0 ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Draft</span>
        <span class="stat-pill-val neutral"><?= $summary['draft'] ?? 0 ?></span>
      </div>
    </div>

    <!-- FILTER CARD -->
    <div class="form-card filter-card">
      <div class="form-card-header">
        <h4><i class="bi bi-funnel"></i> Filter</h4>
        <?php if ($hasFilter): ?>
          <a href="index.php" class="btn-ghost-xs"><i class="bi bi-x"></i> Reset</a>
        <?php endif; ?>
      </div>
      <form method="get" class="filter-form">
        <div class="filter-group filter-search">
          <label class="form-label">Cari</label>
          <input type="text" name="search" class="form-control"
                 placeholder="No. penerimaan / PO / SPK..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="">Semua Status</option>
            <option value="draft"     <?= $status === 'draft'     ? 'selected' : '' ?>>Draft</option>
            <option value="received"  <?= $status === 'received'  ? 'selected' : '' ?>>Received</option>
            <option value="checked"   <?= $status === 'checked'   ? 'selected' : '' ?>>Checked</option>
            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="form-label">Dari Tanggal</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="filter-group">
          <label class="form-label">Sampai Tanggal</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
        </div>
      </form>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-box-arrow-in-down"></i> Daftar Penerimaan
          <span class="count-badge"><?= count($list) ?></span>
        </h4>
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" id="tableSearch" class="search-input" placeholder="Cari di tabel...">
        </div>
      </div>

      <div class="table-wrap">
        <table id="penerimaanTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nomor Penerimaan</th>
              <th>PO / SPK</th>
              <th>Tanggal</th>
              <th>Status</th>
              <th>PIC</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($list)): ?>
            <tr>
              <td colspan="7" class="empty-state">
                <i class="bi bi-box-arrow-in-down"></i>
                <span>Belum ada data penerimaan. <a href="crud/add.php">Tambah sekarang</a></span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($list as $i => $row): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-mid"><?= htmlspecialchars($row['nomor_penerimaan']) ?></td>
              <td class="text-muted">
                <?= $row['nomor_po'] ? htmlspecialchars($row['nomor_po']) : htmlspecialchars($row['nomor_spk'] ?? '—') ?>
              </td>
              <td class="text-muted"><?= htmlspecialchars($row['tanggal']) ?></td>
              <td><span class="badge <?= badgeCls($row['status']) ?>"><?= badgeLabel($row['status']) ?></span></td>
              <td><?= htmlspecialchars($row['pic_name']) ?></td>
              <td>
                <div class="action-btns">
                  <a href="crud/detail.php?id=<?= $row['id'] ?>" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
                  <a href="crud/edit.php?id=<?= $row['id'] ?>"   class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
                  <button type="button" class="btn-icon danger" title="Hapus"
                          onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nomor_penerimaan'], ENT_QUOTES) ?>')">
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
      <div class="table-footer">
        <span class="text-muted" id="tableCount">Menampilkan <?= count($list) ?> data</span>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <h3>Hapus Penerimaan?</h3>
    <p>Data <strong id="deleteTarget"></strong> akan dihapus permanen.</p>
    <div class="modal-actions">
      <form method="post" action="crud/delete.php" id="deleteForm">
        <input type="hidden" name="id" id="deleteId">
        <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      </form>
      <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
    </div>
  </div>
</div>

<script>
  // Search
  const si = document.getElementById('tableSearch');
  const tc = document.getElementById('tableCount');
  si?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    let v = 0;
    document.querySelectorAll('#penerimaanTable tbody tr').forEach(r => {
      const m = r.textContent.toLowerCase().includes(q);
      r.style.display = m ? '' : 'none';
      if (m) v++;
    });
    if (tc) tc.textContent = `Menampilkan ${v} data`;
  });

  // Delete modal
  const modal     = document.getElementById('deleteModal');
  const cancelBtn = document.getElementById('cancelDelete');
  function confirmDelete(id, label) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteTarget').textContent = label;
    modal.classList.add('show');
  }
  cancelBtn?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
