<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/Produk.php';
require_once '../../../../src/functions.php';

$errors = [];
$autoKodeProduk = generateAutoCode('PRODUK');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'kode' => trim($_POST['kode'] ?? ''),
        'nama'        => trim($_POST['nama'] ?? ''),
        'kategori'    => 'PCB',
        'stok'        => (int)($_POST['stok'] ?? 0),
        'harga'       => (int)($_POST['harga'] ?? 0),
        'status'      => $_POST['status'] ?? 'aktif',
    ];
    
    if (!$data['kode']) $errors[] = 'Kode produk wajib diisi.';
    if (!$data['nama']) $errors[] = 'Nama produk wajib diisi.';
    
    if (!$errors) {
        Produk::create($data);
        header('Location: ../index.php?created=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Produk PCB | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/produk.css" rel="stylesheet">
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
          <a href="../index.php">Produk PCB</a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah Produk</span>
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
        <h1 class="page-title-lg">Tambah Produk</h1>
        <p class="page-subtitle">Isi formulir berikut untuk menambahkan produk baru.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-box-seam"></i> Data Produk</h4>
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
                <label class="form-label">Kode Produk <span class="required">*</span></label>
                <input type="text" name="kode" class="form-control"
                       value="<?= htmlspecialchars($_POST['kode'] ?? $autoKodeProduk) ?>" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Kategori</label>
                <input type="text" class="form-control" value="PCB" readonly>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Nama Produk <span class="required">*</span></label>
              <input type="text" name="nama" class="form-control" placeholder="Nama lengkap produk"
                     value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Harga (Rp)</label>
                <input type="number" name="harga" class="form-control" placeholder="0" min="0"
                       value="<?= (int)($_POST['harga'] ?? 0) ?>" onchange="this.value = this.value.replace(/\D/g, '');">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">
                  Preview: Rp <?= number_format((int)($_POST['harga'] ?? 0), 0, ',', '.') ?>
                </small>
              </div>
              <div class="form-group">
                <label class="form-label">Stok Awal (pcs)</label>
                <input type="number" name="stok" class="form-control" placeholder="0" min="0"
                       value="<?= (int)($_POST['stok'] ?? 0) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php foreach (['aktif' => 'Aktif', 'nonaktif' => 'Tidak Aktif'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= (($_POST['status'] ?? 'aktif') === $val) ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>


            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Produk</button>
              <a href="../index.php" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <div class="form-side">
        <div class="form-card info-card">
          <div class="form-card-header">
            <h4><i class="bi bi-info-circle"></i> Panduan</h4>
          </div>
          <ul class="info-list">
            <li><i class="bi bi-dot"></i> Kode produk otomatis dihasilkan format PCB-NNN.</li>
            <li><i class="bi bi-dot"></i> Kategori produk adalah PCB.</li>
            <li><i class="bi bi-dot"></i> Stok awal dapat diubah melalui modul gudang.</li>
            <li><i class="bi bi-dot"></i> Produk Tidak Aktif tidak akan muncul di PO baru.</li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
// Format harga dengan separator
const hargaInput = document.querySelector('input[name="harga"]');
if (hargaInput) {
  hargaInput.addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    const formatted = new Intl.NumberFormat('id-ID').format(value);
    const preview = document.querySelector('[style*="margin-top"]');
    if (preview) {
      preview.innerHTML = 'Preview: Rp ' + formatted;
    }
  });
}
</script>

</body>
</html>
