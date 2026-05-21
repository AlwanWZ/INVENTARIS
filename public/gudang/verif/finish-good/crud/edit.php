<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';

$verifModel = new Verifikasi($pdo);
$id = $_GET['id'] ?? null;

if (!$id) {
  header('Location: ../index.php');
  exit;
}

$data = $verifModel->getById($id);
if (!$data) {
  header('Location: ../index.php');
  exit;
}

$items = $verifModel->getItems($id);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ambil data untuk tabel INDUK (verifikasi)
    $update = [
      'id'      => $id,
      'tanggal' => $_POST['tanggal'] ?? $data['tanggal'],
      'status'  => strtolower($_POST['status'] ?? $data['status'])
    ];

    // 2. Ambil data untuk tabel ANAK (verifikasi_items)
    $updateItems = $_POST['items'] ?? [];
    
    // Validasi QTY
    foreach ($updateItems as $i => $item) {
      $qty_ok = (int)($item['qty_ok'] ?? 0);
      $qty_masuk = (int)($item['qty_masuk'] ?? 0);
      if ($qty_ok > $qty_masuk) {
          $errors[] = "Qty OK tidak boleh melebihi Qty Masuk untuk baris ke-".($i+1);
      }
    }

    // 3. Kalau aman, lempar ke Model buat di-save
    if (!$errors) {
      $verifModel->update($update, $updateItems);
      header('Location: detail.php?id=' . $id . '&updated=1');
      exit;
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Verifikasi Finish Good</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/verifikasi.css" rel="stylesheet">
</head>
<body>

<?php include '../../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Verifikasi Finish Good</a>
          <i class="bi bi-chevron-right"></i>
          <a href="detail.php?id=<?= $id ?>">Detail</a>
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
        <h1 class="page-title-lg">Edit Verifikasi Finish Good</h1>
        <p class="page-subtitle">Edit data verifikasi produk jadi</p>
      </div>
      <a href="detail.php?id=<?= $id ?>" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post" class="form-layout">
      <div class="form-main">
        
        <!-- KARTU DATA INDUK -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-pencil"></i> Data Verifikasi</h4>
          </div>
          <div class="po-form">
            <div class="form-group">
              <label class="form-label">Tanggal</label>
              <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($data['tanggal'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-control" required>
                <option value="draft" <?= ($data['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="verified" <?= ($data['status'] ?? '') === 'verified' ? 'selected' : '' ?>>Verified</option>
              </select>
            </div>
            <!-- NOTE: Kolom keterangan induk sengaja dibuang karena emang di DB ga ada -->
          </div>
        </div>

        <!-- KARTU DATA ITEM -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-list-check"></i> Item Produk</h4>
            <span class="count-badge"><?= count($items) ?> produk</span>
          </div>
          <div class="table-wrap">
            <table class="item-table">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="col-center">Qty Masuk</th>
                  <th class="col-center">Qty OK</th>
                  <th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                  <td class="fw-mid">
                    <?= htmlspecialchars($item['produk_nama'] ?? 'Item') ?>
                    <!-- Hidden Input Penting Biar Nggak Nyasar -->
                    <input type="hidden" name="items[<?= $i ?>][id]" value="<?= htmlspecialchars($item['id'] ?? '') ?>">
                    <input type="hidden" name="items[<?= $i ?>][qty_masuk]" value="<?= htmlspecialchars($item['qty_masuk'] ?? '') ?>">
                  </td>
                  
                  <td class="col-center text-muted"><?= htmlspecialchars($item['qty_masuk'] ?? '') ?></td>
                  
                  <td>
                    <input type="number" name="items[<?= $i ?>][qty_ok]" min="0" max="<?= htmlspecialchars($item['qty_masuk'] ?? '') ?>" class="form-control qty-input" value="<?= htmlspecialchars($item['qty_ok'] ?? '') ?>" required>
                  </td>
                  
                  <td>
                    <input type="text" name="items[<?= $i ?>][keterangan]" class="form-control" value="<?= htmlspecialchars($item['keterangan'] ?? '') ?>" placeholder="Keterangan per item...">
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="form-actions-bottom">
          <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan</button>
          <a href="detail.php?id=<?= $id ?>" class="btn-outline">Batal</a>
        </div>

        <?php if ($errors): ?>
        <div class="alert-error" style="margin-top:16px;">
          <i class="bi bi-exclamation-circle"></i>
          <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        
      </div>
    </form>
  </div>
</main>
</body>
</html>