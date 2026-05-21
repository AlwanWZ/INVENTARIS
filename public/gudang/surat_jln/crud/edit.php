<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/SuratJalan.php';

$suratJalanModel = new SuratJalan($pdo);
$id   = $_GET['id'] ?? '';
$data = $suratJalanModel->getById($id);
if (!$data) { header('Location: ../index.php?msg=notfound'); exit; }

$pengeluaranList = $pdo->query("SELECT id, nomor_pengeluaran FROM pengeluaran ORDER BY tanggal DESC")->fetchAll(PDO::FETCH_ASSOC);
$items  = $suratJalanModel->getItems($id);
if (!is_array($items)) $items = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'nomor_sj'       => trim($_POST['nomor_sj']       ?? ''),
        'tanggal_kirim'  => $_POST['tanggal_kirim']  ?? '',
        'pengeluaran_id' => $_POST['pengeluaran_id'] ?? '',
        'driver'         => trim($_POST['driver']         ?? ''),
        'kendaraan'      => trim($_POST['kendaraan']      ?? ''),
        'catatan'        => trim($_POST['catatan']        ?? ''),
        'items'          => $_POST['items']          ?? [],
    ];
    if (!$updateData['nomor_sj'])      $errors[] = 'Nomor SJ wajib diisi.';
    if (!$updateData['tanggal_kirim']) $errors[] = 'Tanggal kirim wajib diisi.';
    if (!$updateData['driver'])        $errors[] = 'Driver wajib diisi.';
    if (!$updateData['kendaraan'])     $errors[] = 'Kendaraan wajib diisi.';
    if (!$errors) {
        $result = $suratJalanModel->update($id, $updateData, $updateData['items']);
        if (is_array($result) && isset($result['success']) && $result['success']) {
            // Success: reload $data from DB to ensure all fields are correct
            header('Location: detail.php?id=' . $id . '&msg=edit-success');
            exit;
        } else {
            // Failure: show errors and keep merged data for form
            $errors = is_array($result) && isset($result['errors']) ? $result['errors'] : ['Gagal menyimpan perubahan.'];
        }
    }
    // Always keep $data as array for form repopulation
    if (!is_array($data)) {
      $data = [];
    }
    $data = array_merge($data, $updateData);
}

function badgeCls($s) { return match($s) { 'diterima' => 'ok', 'dikirim' => 'blue', default => 'warn' }; }
function badgeLabel($s) { return match($s) { 'diterima' => 'Diterima', 'dikirim' => 'Dikirim', default => 'Draft' }; }
$statusCls   = badgeCls($data['status']);
$statusLabel = badgeLabel($data['status']);
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Surat Jalan | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/surat_jln.css" rel="stylesheet">
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
          <a href="../index.php">Surat Jalan</a>
          <i class="bi bi-chevron-right"></i>
          <a href="detail.php?id=<?= $data['id'] ?>"><?= htmlspecialchars($data['nomor_sj']) ?></a>
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
            <span class="user-role">Gudang</span>
          </div>
        </div>
      </div>
    </div>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Edit Surat Jalan</h1>
        <p class="page-subtitle">
          Mengedit <strong><?= htmlspecialchars($data['nomor_sj']) ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
        </p>
      </div>
      <a href="detail.php?id=<?= $data['id'] ?>" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
      <div class="form-layout">

        <div class="form-main">
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-pencil-square"></i> Data Surat Jalan</h4>
              <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
            </div>

            <?php if ($errors): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle"></i>
              <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <div class="po-form">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nomor Surat Jalan <span class="required">*</span></label>
                  <input type="text" name="nomor_sj" class="form-control"
                         value="<?= htmlspecialchars($_POST['nomor_sj'] ?? $data['nomor_sj']) ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal Kirim <span class="required">*</span></label>
                  <input type="date" name="tanggal_kirim" class="form-control"
                         value="<?= htmlspecialchars($_POST['tanggal_kirim'] ?? $data['tanggal_kirim']) ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Pengeluaran</label>
                <select name="pengeluaran_id" class="form-control">
                  <option value="">— Pilih Pengeluaran —</option>
                  <?php foreach ($pengeluaranList as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= ($data['pengeluaran_id'] ?? '') == $p['id'] ? 'selected' : '' ?> >
                      <?= htmlspecialchars($p['nomor_pengeluaran']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Driver <span class="required">*</span></label>
                  <input type="text" name="driver" class="form-control"
                         value="<?= htmlspecialchars($_POST['driver'] ?? $data['driver']) ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Kendaraan <span class="required">*</span></label>
                  <input type="text" name="kendaraan" class="form-control"
                         value="<?= htmlspecialchars($_POST['kendaraan'] ?? $data['kendaraan']) ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" class="form-control form-textarea"><?= htmlspecialchars($_POST['catatan'] ?? ($data['catatan'] ?? '')) ?></textarea>
              </div>
            </div>
          </div>

          <!-- Daftar barang -->
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-list-ul"></i> Daftar Barang</h4>
              <span class="count-badge"><?= count($items) ?> item</span>
            </div>
            <div class="table-wrap">
              <table class="item-table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th class="col-right">Qty</th>
                    <th class="col-right">Satuan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $i => $item): ?>
                  <tr>
                    <td class="fw-mid"><?= htmlspecialchars($item['produk_nama'] ?? '-') ?></td>
                    <td class="col-right">
                      <input type="number" name="items[<?= $i ?>][qty]" class="form-control qty-input"
                             min="1" max="<?= $item['qty'] ?>" value="<?= $item['qty'] ?>" required>
                      <input type="hidden" name="items[<?= $i ?>][produk_id]" value="<?= $item['produk_id'] ?>">
                    </td>
                    <td class="col-right text-muted"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="form-actions-bottom">
            <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
            <a href="detail.php?id=<?= $data['id'] ?>" class="btn-outline">Batal</a>
          </div>

        </div>

        <!-- SIDE -->
        <div class="form-side">
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-clock-history"></i> Data Sebelumnya</h4>
            </div>
            <div class="side-info-list">
              <div class="side-info-item">
                <span class="side-info-label">Nomor SJ</span>
                <span class="side-info-val fw-mid"><?= htmlspecialchars($data['nomor_sj']) ?></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Tanggal</span>
                <span class="side-info-val"><?= htmlspecialchars($data['tanggal_kirim'] ?? '-') ?></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Customer</span>
                <span class="side-info-val"><?= htmlspecialchars($data['customer_nama'] ?? '-') ?></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Driver</span>
                <span class="side-info-val"><?= htmlspecialchars($data['driver'] ?? '-') ?></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Status</span>
                <span class="side-info-val"><span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span></span>
              </div>
            </div>
          </div>

          <div class="form-card danger-card">
            <div class="form-card-header">
              <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
            </div>
            <div class="danger-body">
              <p>Hapus surat jalan ini secara permanen. Tindakan tidak dapat dibatalkan.</p>
              <button type="button" class="btn-danger" id="deleteBtn">
                <i class="bi bi-trash"></i> Hapus Surat Jalan
              </button>
            </div>
          </div>
        </div>

      </div>
    </form>

    <!-- DELETE MODAL -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus Surat Jalan?</h3>
        <p>Surat Jalan <strong><?= htmlspecialchars($data['nomor_sj']) ?></strong> akan dihapus permanen.</p>
        <div class="modal-actions">
          <form method="post" action="delete.php">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
  const modal = document.getElementById('deleteModal');
  document.getElementById('deleteBtn')?.addEventListener('click',  () => modal.classList.add('show'));
  document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
