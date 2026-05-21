<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/models/SPK.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/models/User.php';

$spk = SPK::find($_GET['id'] ?? null);
if (!$spk) { header('Location: ../index.php'); exit; }

$errors = [];
$poList = PO::all();
$users  = User::getAll();
$items  = SPK::getItems($spk['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nomor_spk' => trim($_POST['nomor_spk'] ?? ''),
        'po_id'     => !empty($_POST['po_id']) ? (int)$_POST['po_id'] : '',
        'tanggal'   => $_POST['tanggal']   ?? '',
        'deadline'  => $_POST['deadline']  ?? '',
        'pic_id'    => !empty($_POST['pic_id']) ? (int)$_POST['pic_id'] : null,
        'status'    => $_POST['status']    ?? 'draft',
        'notes'     => trim($_POST['notes'] ?? ''),
        'progress'  => (int)($_POST['progress'] ?? 0),
    ];
    if (!$data['nomor_spk']) $errors[] = 'Nomor SPK wajib diisi.';
    if (!$data['po_id'])     $errors[] = 'PO wajib dipilih.';
    if (!$data['tanggal'])   $errors[] = 'Tanggal wajib diisi.';
    if (!$data['deadline'])  $errors[] = 'Deadline wajib diisi.';
    if (!$data['pic_id'])    $errors[] = 'PIC wajib dipilih.';
    
    // Validate that selected pic_id exists in users table (refresh list to catch deleted users)
    if ($data['pic_id']) {
        $currentUsers = User::getAll();
        if (!array_filter($currentUsers, fn($u) => $u['id'] == $data['pic_id'])) {
            $errors[] = 'PIC yang dipilih tidak valid atau telah dihapus.';
        }
    }
    
    // Handle item PIC updates
    if (!empty($_POST['item_pic'])) {
        foreach ($_POST['item_pic'] as $itemId => $picId) {
            $picId = !empty($picId) ? (int)$picId : null;
            SPK::updateItemPic($itemId, $picId);
        }
    }
    
    if (!$errors) {
        // Jika PO berubah, sync items dari PO baru
        $oldPoId = $spk['po_id'];
        $newPoId = $data['po_id'];
        
        SPK::update($spk['id'], $data);
        
        // Auto-sync items jika PO berubah
        if ($oldPoId != $newPoId && !empty($newPoId)) {
            SPK::syncItemsFromPO($spk['id']);
            $items = SPK::getItems($spk['id']);
        }
        
        header('Location: ../index.php?updated=1');
        exit;
    }
    $spk = array_merge($spk, $data);
    $items = SPK::getItems($spk['id']);
}

// Show all users in main PIC dropdown for flexibility
// Users can assign PICs to items in the table below
$filteredUsers = $users;

$statusCls = match($spk['status']) { 'on_progress' => 'ok', 'completed' => 'ok', 'cancelled' => 'warn', default => 'neutral' };
$statusLabel = match($spk['status']) { 'on_progress' => 'On Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', default => 'Draft' };
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit SPK | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/spk.css" rel="stylesheet">
</head>
<body>
<?php include '../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">SPK</a>
          <i class="bi bi-chevron-right"></i>
          <a href="detail.php?id=<?= $spk['id'] ?>"><?= htmlspecialchars($spk['nomor_spk']) ?></a>
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

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Edit SPK</h1>
        <p class="page-subtitle">Mengedit <strong><?= htmlspecialchars($spk['nomor_spk']) ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
        </p>
      </div>
      <a href="detail.php?id=<?= $spk['id'] ?>" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-pencil-square"></i> Data SPK</h4>
            <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
          </div>

          <?php if ($errors): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>

          <form method="post" class="po-form">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nomor SPK <span class="required">*</span></label>
                <input type="text" name="nomor_spk" class="form-control"
                       value="<?= htmlspecialchars($spk['nomor_spk']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Pilih PO <span class="required">*</span></label>
                <select name="po_id" class="form-control" required id="poSelect">
                  <option value="">-- Pilih PO --</option>
                  <?php foreach ($poList as $po): ?>
                    <option value="<?= $po['id'] ?>"
                            data-customer="<?= htmlspecialchars($po['perusahaan'] ?? '') ?>"
                            <?= $spk['po_id'] == $po['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($po['nomor_po']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Customer (otomatis)</label>
                <input type="text" id="customerField" class="form-control"
                       value="<?= htmlspecialchars($spk['perusahaan'] ?? '') ?>" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal" class="form-control"
                       value="<?= htmlspecialchars($spk['tanggal']) ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Deadline <span class="required">*</span></label>
                <input type="date" name="deadline" class="form-control"
                       value="<?= htmlspecialchars($spk['deadline']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">PIC <span class="required">*</span></label>
                <select name="pic_id" class="form-control" required>
                  <option value="">-- Pilih PIC --</option>
                  <?php foreach ($filteredUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($spk['pic_id'] ?? null) == $u['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($u['username']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php foreach (['draft'=>'Draft','on_progress'=>'On Progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $spk['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Progress (%)</label>
                <input type="number" name="progress" class="form-control"
                       min="0" max="100" value="<?= (int)$spk['progress'] ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control form-textarea"><?= htmlspecialchars($spk['notes'] ?? '') ?></textarea>
            </div>

            <!-- Items Table -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-list-ul"></i> Daftar Items & PIC</label>
              <div style="overflow-x: auto;">
                <table class="table table-sm" style="width:100%; border-collapse: collapse; margin-top: 10px;">
                  <thead style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                    <tr>
                      <th style="padding: 10px; text-align: left; font-weight: 600;">Barang</th>
                      <th style="padding: 10px; text-align: center; font-weight: 600;">Qty PO</th>
                      <th style="padding: 10px; text-align: center; font-weight: 600;">PIC</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($items)): ?>
                      <?php foreach ($items as $item): ?>
                      <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                          <strong><?= htmlspecialchars($item['nama_barang']) ?></strong><br>
                          <small style="color: #999;">ID: <?= $item['produk_id'] ?></small>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                          <?= (int)$item['qty_po'] ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                          <select name="item_pic[<?= $item['id'] ?>]" class="form-control" style="width: 100%; max-width: 150px;">
                            <option value="">-- Pilih --</option>
                            <?php foreach ($users as $u): ?>
                              <option value="<?= $u['id'] ?>" <?= ($item['pic_id'] ?? null) == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['username']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" style="padding: 20px; text-align: center; color: #999;">Tidak ada items</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
              <a href="detail.php?id=<?= $spk['id'] ?>" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <div class="form-side">
        <!-- Data sebelumnya -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clock-history"></i> Data Sebelumnya</h4>
          </div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">Nomor SPK</span>
              <span class="side-info-val fw-mid"><?= htmlspecialchars($spk['nomor_spk']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">PO</span>
              <span class="side-info-val"><?= htmlspecialchars($spk['nomor_po'] ?? '—') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Deadline</span>
              <span class="side-info-val"><?= htmlspecialchars($spk['deadline']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">PIC</span>
              <span class="side-info-val"><?= htmlspecialchars($spk['pic_username'] ?? '—') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Progress</span>
              <span class="side-info-val fw-mid"><?= (int)$spk['progress'] ?>%</span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Status</span>
              <span class="side-info-val"><span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span></span>
            </div>
          </div>
        </div>

        <!-- Zona berbahaya -->
        <div class="form-card danger-card">
          <div class="form-card-header">
            <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
          </div>
          <div class="danger-body">
            <p>Hapus SPK ini secara permanen. Tindakan tidak dapat dibatalkan.</p>
            <button type="button" class="btn-danger" id="deleteBtn">
              <i class="bi bi-trash"></i> Hapus SPK
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- DELETE MODAL -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus SPK?</h3>
        <p>SPK <strong><?= htmlspecialchars($spk['nomor_spk']) ?></strong> akan dihapus permanen.</p>
        <div class="modal-actions">
          <form method="post" action="delete.php">
            <input type="hidden" name="id" value="<?= $spk['id'] ?>">
            <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
  // PO → Customer auto-fill
  document.getElementById('poSelect')?.addEventListener('change', function () {
    document.getElementById('customerField').value =
      this.options[this.selectedIndex].getAttribute('data-customer') || '';
  });

  // Delete modal
  const modal = document.getElementById('deleteModal');
  document.getElementById('deleteBtn')?.addEventListener('click',  () => modal.classList.add('show'));
  document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
