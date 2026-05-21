<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/config.php';
require_once '../../../src/models/SuratJalan.php';

$suratJalanModel = new SuratJalan($pdo);

$status      = $_GET['status']      ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$date_from   = $_GET['date_from']   ?? '';
$date_to     = $_GET['date_to']     ?? '';
$filters = compact('status', 'customer_id', 'date_from', 'date_to');

// Tambahkan validasi untuk memastikan Surat Jalan hanya bisa dibuat satu per satu
$existingSJ = $pdo->prepare("SELECT COUNT(*) FROM surat_jalan WHERE pengeluaran_id = :pengeluaran_id");
$existingSJ->execute(['pengeluaran_id' => $filters['pengeluaran_id'] ?? null]);
if ($existingSJ->fetchColumn() > 0) {
    echo "<script>alert('Surat Jalan untuk pengeluaran ini sudah ada!'); window.location.href='/Inventaris/public/gudang/surat_jln/index.php';</script>";
    exit;
}

$list      = $suratJalanModel->getAll($filters);
$customers = $pdo->query("SELECT id, nama FROM customers ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$hasFilter = $status || $customer_id || $date_from || $date_to;

$statusOptions = ['' => 'Semua Status', 'draft' => 'Draft', 'dikirim' => 'Dikirim', 'diterima' => 'Diterima'];

function badgeCls($s) {
    return match($s) { 'diterima' => 'ok', 'dikirim' => 'blue', default => 'warn' };
}
function badgeLabel($s) {
    return match($s) { 'diterima' => 'Diterima', 'dikirim' => 'Dikirim', default => 'Draft' };
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Surat Jalan | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/surat_jln.css" rel="stylesheet">
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
          <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Surat Jalan</span>
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
    <?php if (isset($_GET['msg'])): ?>
      <?php if ($_GET['msg'] === 'add-success'): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Surat Jalan berhasil dibuat.</div>
      <?php elseif ($_GET['msg'] === 'edit-success'): ?>
      <div class="alert-success"><i class="bi bi-check-circle"></i> Surat Jalan berhasil diperbarui.</div>
      <?php elseif ($_GET['msg'] === 'deleted'): ?>
      <div class="alert-warn"><i class="bi bi-trash"></i> Surat Jalan berhasil dihapus.</div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Surat Jalan</h1>
        <p class="page-subtitle">Daftar surat jalan pengiriman barang ke customer.</p>
      </div>
      <a href="crud/add.php" class="btn-primary"><i class="bi bi-plus-lg"></i> Buat Surat Jalan</a>
    </div>

    <!-- STAT ROW -->
    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total</span>
        <span class="stat-pill-val"><?= count($list) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Dikirim</span>
        <span class="stat-pill-val blue"><?= count(array_filter($list, fn($s) => $s['status'] === 'dikirim')) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Diterima</span>
        <span class="stat-pill-val ok"><?= count(array_filter($list, fn($s) => $s['status'] === 'diterima')) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Draft</span>
        <span class="stat-pill-val warn"><?= count(array_filter($list, fn($s) => $s['status'] === 'draft')) ?></span>
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
        <div class="filter-group">
          <label class="form-label">Dari Tanggal</label>
          <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="filter-group">
          <label class="form-label">Sampai Tanggal</label>
          <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="filter-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach ($statusOptions as $k => $v): ?>
              <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-control">
            <option value="">Semua Customer</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
        </div>
      </form>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
      <div class="table-header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1em;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:0.5em;">
            <h4 style="margin:0;"><i class="bi bi-truck"></i> Daftar Surat Jalan
              <span class="count-badge"><?= count($list) ?></span>
            </h4>
            <!-- <a href="crud/print_list.php?<?= http_build_query(array_filter(['status'=>$status,'customer_id'=>$customer_id,'date_from'=>$date_from,'date_to'=>$date_to])) ?>" target="_blank" class="btn-outline print-all" style="margin-left:0.5em;"><i class="bi bi-printer"></i> Print Daftar</a> -->
          </div>
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="tableSearch" class="search-input" placeholder="Cari di tabel...">
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table id="sjTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nomor SJ</th>
              <th>Tanggal Kirim</th>
              <th>Customer</th>
              <th>Pengeluaran</th>
              <th>Driver</th>
              <th>Kendaraan</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($list)): ?>
            <tr>
              <td colspan="9" class="empty-state">
                <i class="bi bi-truck"></i>
                <span>Belum ada surat jalan. <a href="crud/add.php">Buat sekarang</a></span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($list as $i => $row): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-mid"><?= htmlspecialchars($row['nomor_sj'] ?? '-') ?></td>
              <td class="text-muted"><?= htmlspecialchars($row['tanggal_kirim'] ?? '-') ?></td>
              <td><?= htmlspecialchars($row['customer_nama'] ?? '-') ?></td>
              <td class="text-muted"><?= htmlspecialchars($row['pengeluaran_id'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['driver'] ?? '-') ?></td>
              <td class="text-muted"><?= htmlspecialchars($row['kendaraan'] ?? '-') ?></td>
              <td><span class="badge <?= badgeCls($row['status']) ?>"><?= badgeLabel($row['status']) ?></span></td>
              <td>
                <div class="action-btns">
                  <a href="crud/detail.php?id=<?= $row['id'] ?>" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
                  <a href="crud/edit.php?id=<?= $row['id'] ?>"   class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
                  <a href="crud/print_list.php?id=<?= $row['id'] ?>"  class="btn-icon print" title="Cetak" target="_blank"><i class="bi bi-printer"></i></a>
                  <button type="button" class="btn-icon danger" title="Hapus"
                          onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nomor_sj'], ENT_QUOTES) ?>')">
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
    <h3>Hapus Surat Jalan?</h3>
    <p>Surat Jalan <strong id="deleteTarget"></strong> akan dihapus permanen.</p>
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
  si?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    let v = 0;
    document.querySelectorAll('#sjTable tbody tr').forEach(r => {
      const m = r.textContent.toLowerCase().includes(q);
      r.style.display = m ? '' : 'none';
      if (m) v++;
    });
    if (tc) tc.textContent = `Menampilkan ${v} data`;
  });

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
