<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/models/Customer.php';

$po = PO::find($_GET['id'] ?? null);
if (!$po) { header('Location: ../index.php'); exit; }

// Get all customers for dropdown
$customerList = Customer::getAll();

// Handle quick actions (approve/reject)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'approve') {
        $data = $po;
        $data['status'] = 'approved';
        PO::update($po['id'], $data);
        header('Location: ../index.php?approved=1');
        exit;
    } elseif ($action === 'reject') {
        $data = $po;
        $data['status'] = 'rejected';
        PO::update($po['id'], $data);
        header('Location: ../index.php?rejected=1');
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nomor_po'    => trim($_POST['nomor_po'] ?? ''),
        'tanggal'     => $_POST['tanggal'] ?? '',
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'status'      => $_POST['status'] ?? 'draft',
        'notes'       => trim($_POST['notes'] ?? ''),
    ];
    if (!$data['nomor_po']) $errors[] = 'Nomor Pesanan wajib diisi.';
    if (!$data['tanggal'])  $errors[] = 'Tanggal wajib diisi.';
    if (!$data['customer_id']) $errors[] = 'Customer wajib dipilih.';

    if (!$errors) {
        PO::update($po['id'], $data);
        header('Location: ../index.php?updated=1');
        exit;
    }

    // Merge POST ke $po agar form tetap tampil nilai baru saat ada error
    $po = array_merge($po, $data);
}
$statusCls = match($po['status']) { 'Disetujui' => 'ok', 'Proses' => 'warn', default => 'neutral' };
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Pesanan PCB | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/po.css" rel="stylesheet">
</head>
<body>

<?php include '../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Pesanan PCB</a>
          <i class="bi bi-chevron-right"></i>
          <a href="detail.php?id=<?= $po['id'] ?>"><?= htmlspecialchars($po['nomor_po']) ?></a>
          <i class="bi bi-chevron-right"></i>
          <span>Edit</span>
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

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Terima Order dari Customer</h1>
        <p class="page-subtitle">
          Order <strong><?= htmlspecialchars($po['nomor_po']) ?></strong>
          dari <strong><?= htmlspecialchars($po['perusahaan'] ?? '-') ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($po['status']) ?></span>
        </p>
      </div>
      <div class="header-actions">
        <a href="detail.php?id=<?= $po['id'] ?>" class="btn-ghost-sm">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
      </div>
    </div>

    <!-- FORM LAYOUT -->
    <div class="form-layout">

      <!-- MAIN FORM -->
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-file-earmark-check"></i> Edit Pesanan PCB</h4>
            <span class="badge <?= match($po['status']) { 'approved' => 'ok', 'rejected' => 'danger', default => 'neutral' } ?>"><?= htmlspecialchars($po['status']) ?></span>
          </div>

          <?php if ($errors): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle"></i>
              <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>

          <form method="post" class="po-form" id="editForm">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nomor Pesanan PCB <span class="required">*</span></label>
                <input type="text" name="nomor_po" class="form-control"
                       value="<?= htmlspecialchars($po['nomor_po']) ?>" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal" class="form-control"
                       value="<?= htmlspecialchars($po['tanggal']) ?>" required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Pilih Customer <span class="required">*</span></label>
              <select name="customer_id" class="form-control" required>
                <option value="">-- Pilih Customer --</option>
                <?php foreach ($customerList as $cust): ?>
                  <option value="<?= $cust['id'] ?>" <?= intval($po['customer_id'] ?? 0) === $cust['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cust['perusahaan'] ?? $cust['nama']) ?>
                    <?php if ($cust['perusahaan'] && $cust['nama']): ?>
                      (<?= htmlspecialchars($cust['nama']) ?>)
                    <?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan (Opsional)</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan atau informasi khusus tentang pesanan ini..."><?= htmlspecialchars($_POST['notes'] ?? $po['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <option value="draft" <?= $po['status'] === 'draft' ? 'selected' : '' ?>>Draft (Baru)</option>
                  <option value="pending_review" <?= $po['status'] === 'pending_review' ? 'selected' : '' ?>>Pending Review (Tunggu Manager)</option>
                  <option value="approved" <?= $po['status'] === 'approved' ? 'selected' : '' ?>>Approved (Disetujui)</option>
                  <option value="rejected" <?= $po['status'] === 'rejected' ? 'selected' : '' ?>>Rejected (Ditolak)</option>
                </select>
                <small style="color: #6c757d;">Ubah status pesanan sesuai progress</small>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary">
                <i class="bi bi-check-lg"></i> Simpan Perubahan
              </button>
              <a href="detail.php?id=<?= $po['id'] ?>" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <!-- SIDE PANEL -->
      <div class="form-side">

        <!-- Info PO sebelum diedit -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clock-history"></i> Data Sebelumnya</h4>
          </div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">Nomor PO</span>
              <span class="side-info-val"><?= htmlspecialchars($po['nomor_po']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Customer / Perusahaan</span>
              <span class="side-info-val"><?= htmlspecialchars($po['customer'] ?? $po['perusahaan'] ?? '-') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Tanggal</span>
              <span class="side-info-val"><?= htmlspecialchars($po['tanggal']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Status</span>
              <span class="side-info-val"><span class="badge <?= $statusCls ?>"><?= htmlspecialchars($po['status']) ?></span></span>
            </div>
          </div>
        </div>

        <!-- Aksi Bahaya -->
        <div class="form-card danger-card">
          <div class="form-card-header">
            <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
          </div>
          <div class="danger-body">
            <p>Hapus PO ini secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
            <button type="button" class="btn-danger" id="deleteBtn">
              <i class="bi bi-trash"></i> Hapus PO
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- DELETE CONFIRM MODAL -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon">
          <i class="bi bi-exclamation-triangle"></i>
        </div>
        <h3>Hapus Purchase Order?</h3>
        <p>PO <strong><?= htmlspecialchars($po['nomor_po']) ?></strong> akan dihapus permanen dan tidak bisa dikembalikan.</p>
        <div class="modal-actions">
          <form method="post" action="delete.php">
            <input type="hidden" name="id" value="<?= $po['id'] ?>">
            <button type="submit" class="btn-danger">
              <i class="bi bi-trash"></i> Ya, Hapus
            </button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
  // Delete modal
  const modal     = document.getElementById('deleteModal');
  const deleteBtn = document.getElementById('deleteBtn');
  const cancelBtn = document.getElementById('cancelDelete');
  deleteBtn?.addEventListener('click', () => modal.classList.add('show'));
  cancelBtn?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
