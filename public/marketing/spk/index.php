<?php
session_start();
require_once '../../../src/auth.php';
// Batasi akses hanya untuk role marketing
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'marketing') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/models/SPK.php';
require_once '../../../src/models/User.php';

$filter = [
    'tanggal' => $_GET['tanggal'] ?? '',
    'status'  => $_GET['status']  ?? '',
    'pic'     => $_GET['pic']     ?? '',
    'search'  => $_GET['search']  ?? '',
];
$users   = User::getAll();

// Memanggil method all() yang sudah kita perbaiki query-nya di SPK.php
$spks    = SPK::all($filter);

$totalSPK   = count($spks);
$onProgress = count(array_filter($spks, fn($s) => $s['status'] === 'on_progress'));
$completed  = count(array_filter($spks, fn($s) => $s['status'] === 'completed'));
$late       = count(array_filter($spks, fn($s) => $s['status'] !== 'completed' && strtotime($s['deadline']) < time()));
$hasFilter  = array_filter($filter);

function badgeCls($s) {
    return match($s) { 'on_progress' => 'ok', 'completed' => 'ok', 'cancelled' => 'warn', default => 'neutral' };
}
function badgeLabel($s) {
    return match($s) { 'on_progress' => 'On Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', default => 'Draft' };
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar SPK | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/spk.css" rel="stylesheet">
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
          <span>SPK</span>
        </div>
      </div>
      <div class="top-right">
        <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
        <div class="user-box">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            <span class="user-role">Marketing</span>
          </div>
        </div>
      </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> SPK berhasil ditambahkan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> SPK berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert-warn"><i class="bi bi-trash"></i> SPK berhasil dihapus.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Surat Perintah Kerja</h1>
        <p class="page-subtitle">List dan monitoring SPK perusahaan.</p>
      </div>
      <a href="crud/add.php" class="btn-primary"><i class="bi bi-plus-lg"></i> Tambah SPK</a>
    </div>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total SPK</span>
        <span class="stat-pill-val"><?= $totalSPK ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">On Progress</span>
        <span class="stat-pill-val ok"><?= $onProgress ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Completed</span>
        <span class="stat-pill-val ok"><?= $completed ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Terlambat</span>
        <span class="stat-pill-val warn"><?= $late ?></span>
      </div>
    </div>

    <div class="form-card filter-card">
      <div class="form-card-header">
        <h4><i class="bi bi-funnel"></i> Filter Server (Database)</h4>
        <?php if ($hasFilter): ?>
          <a href="index.php" class="btn-ghost-xs"><i class="bi bi-x"></i> Reset</a>
        <?php endif; ?>
      </div>
      
      <form method="get" action="index.php" id="filterForm" class="filter-form">
        <div class="filter-group">
          <label class="form-label">Tanggal</label>
          <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($filter['tanggal']) ?>" onchange="this.form.submit()">
        </div>
        <div class="filter-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="draft"       <?= $filter['status'] === 'draft'       ? 'selected' : '' ?>>Draft</option>
            <option value="on_progress" <?= $filter['status'] === 'on_progress' ? 'selected' : '' ?>>On Progress</option>
            <option value="completed"   <?= $filter['status'] === 'completed'   ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled"   <?= $filter['status'] === 'cancelled'   ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="form-label">PIC</label>
          <select name="pic" class="form-control" onchange="this.form.submit()">
            <option value="">Semua PIC</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= $filter['pic'] == $u['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group filter-search">
          <label class="form-label">Cari Data</label>
          <input type="text" name="search" class="form-control"
                 placeholder="No. SPK / Pesanan / customer..."
                 value="<?= htmlspecialchars($filter['search']) ?>">
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-primary"><i class="bi bi-funnel"></i> Terapkan</button>
        </div>
      </form>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-file-earmark-check"></i> Data SPK
          <span class="count-badge"><?= $totalSPK ?></span>
        </h4>
        <div class="search-wrap" title="Pencarian cepat di tabel yang sedang tampil">
          <i class="bi bi-search"></i>
          <input type="text" id="searchInput" class="search-input" placeholder="Quick find di tabel ini...">
        </div>
      </div>

      <div class="table-wrap">
        <table id="spkTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Nomor SPK</th>
              <th>Nomor Pesanan</th>
              <th>Customer</th>
              <th>Tanggal</th>
              <th>Deadline</th>
              <th>PIC</th>
              <th>Progress</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($spks)): ?>
            <tr>
              <td colspan="10" class="empty-state">
                <i class="bi bi-file-earmark-check"></i>
                <span>Tidak ada data SPK yang sesuai. <a href="index.php">Reset Filter</a> atau <a href="crud/add.php">Tambah baru</a></span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($spks as $i => $spk):
              $spk['nomor_spk'] = $spk['nomor_spk'] ?? '-';
              $spk['nomor_pesanan']  = $spk['nomor_pesanan'] ?? '-';
              $spk['perusahaan'] = $spk['perusahaan'] ?? '-';
              $spk['pic_username'] = $spk['pic_username'] ?? '-';
              
              $isLate = $spk['status'] !== 'completed' && strtotime($spk['deadline']) < time();
              $prog   = (int)$spk['progress'];
            ?>
            <tr class="<?= $isLate ? 'row-late' : '' ?>">
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-mid"><?= htmlspecialchars($spk['nomor_spk']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($spk['nomor_pesanan']) ?></td>
              <td><?= htmlspecialchars($spk['perusahaan']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($spk['tanggal']) ?></td>
              <td class="<?= $isLate ? 'late-text' : 'text-muted' ?>">
                <?= htmlspecialchars($spk['deadline']) ?>
                <?php if ($isLate): ?><i class="bi bi-exclamation-triangle" title="Terlambat"></i><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($spk['pic_username']) ?></td>
              <td>
                <div class="progress-wrap">
                  <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $prog ?>%"></div>
                  </div>
                  <span class="progress-label"><?= $prog ?>%</span>
                </div>
              </td>
              <td><span class="badge <?= badgeCls($spk['status']) ?>"><?= badgeLabel($spk['status']) ?></span></td>
              <td>
                <div class="action-btns">
                  <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                    <a href="crud/detail.php?id=<?= $spk['id'] ?>" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
                    <a href="crud/edit.php?id=<?= $spk['id'] ?>"   class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
                    <a href="crud/print.php?id=<?= $spk['id'] ?>" target="_blank" class="btn-icon" title="Print" style="color:#6c757d;">
                      <i class="bi bi-printer"></i>
                    </a>
                  </div>
                  <div>
                    <button type="button" class="btn-icon danger" title="Hapus"
                            onclick="confirmDelete(<?= $spk['id'] ?>, '<?= htmlspecialchars($spk['nomor_spk'], ENT_QUOTES) ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (!empty($spks)): ?>
      <div class="table-footer">
        <span class="text-muted" id="tableCount">Menampilkan <?= $totalSPK ?> data</span>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <h3>Hapus SPK?</h3>
    <p>SPK <strong id="deleteTarget"></strong> akan dihapus permanen.</p>
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
  // PERBAIKAN 3: JS Local Search yang lebih solid
  const si = document.getElementById('searchInput');
  const tc = document.getElementById('tableCount');
  
  if (si) {
    si.addEventListener('input', function () {
      const q = this.value.toLowerCase();
      let v = 0;
      
      document.querySelectorAll('#spkTable tbody tr').forEach(r => {
        // Jangan ikut menyembunyikan kolom "kosong / empty-state"
        if (r.querySelector('.empty-state')) return;
        
        const m = r.textContent.toLowerCase().includes(q);
        r.style.display = m ? '' : 'none';
        if (m) v++;
      });
      
      if (tc) tc.textContent = `Menampilkan ${v} data (filter lokal)`;
    });
  }

  // Delete modal logic
  const modal      = document.getElementById('deleteModal');
  const cancelBtn  = document.getElementById('cancelDelete');
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