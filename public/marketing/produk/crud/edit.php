<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/Produk.php';

$produk = Produk::find($_GET['id'] ?? null);
if (!$produk) { header('Location: ../index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStok = (int)($_POST['stok'] ?? 0);
    $stokReserved = (int)($produk['stok_reserved'] ?? 0);
    
    // RULE 1b: LOGIKA PENTING saat edit produk
    // Kalkulasi ulang stok yang bisa dijual (available)
    // stok_available = stok_fisik - stok_reserved
    $stokAvailable = $newStok - $stokReserved;
    if ($stokAvailable < 0) $stokAvailable = 0; // Safety

    $data = [
        'nama'           => trim($_POST['nama'] ?? ''),
        'stok'           => $newStok,
        'stok_min'       => (int)($_POST['stok_min'] ?? 10),
        'satuan'         => trim($_POST['satuan'] ?? 'pcs'),
        'harga'          => (int)($_POST['harga'] ?? 0),
        'status'         => $_POST['status'] ?? 'aktif',
    ];
    
    if (!$data['nama']) $errors[] = 'Nama produk wajib diisi.';
    if ($data['stok'] < 0) $errors[] = 'Stok tidak boleh negatif.';

    if (!$errors) {
        try {
            Produk::update($produk['id'], $data);
            echo "<script>
                alert('✅ Produk berhasil diperbarui!');
                window.location.href = '../index.php?updated=1';
            </script>";
            exit;
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan produk: ' . $e->getMessage();
        }
    }
    $produk = array_merge($produk, $data);
}

$statusCls = match(strtolower($produk['status'] ?? '')) {
    'aktif' => 'ok',
    default => 'warn'
};
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Produk PCB | InventorySys</title>
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
          <a href="detail.php?id=<?= $produk['id'] ?>"><?= htmlspecialchars($produk['kode_produk'] ?? $produk['kode'] ?? '-') ?></a>
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
        <h1 class="page-title-lg">Edit Produk</h1>
        <p class="page-subtitle">
          Mengedit <strong><?= htmlspecialchars($produk['nama']) ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= htmlspecialchars(ucfirst($produk['status'] ?? '-')) ?></span>
        </p>
      </div>
      <a href="detail.php?id=<?= $produk['id'] ?>" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">

      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-pencil-square"></i> Data Produk</h4>
            <span class="badge <?= $statusCls ?>"><?= htmlspecialchars(ucfirst($produk['status'] ?? '-')) ?></span>
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
                <label class="form-label">Kode Produk</label>
                <input type="text" name="kode" class="form-control"
                       value="<?= htmlspecialchars($produk['kode_produk'] ?? $produk['kode'] ?? '') ?>" readonly style="background:#f3f4f6; cursor:not-allowed;">
              </div>
              <div class="form-group">
                <label class="form-label">Kategori</label>
                <input type="text" class="form-control" value="PCB" readonly style="background:#f3f4f6; cursor:not-allowed;">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Nama Produk <span class="required">*</span></label>
              <input type="text" name="nama" class="form-control"
                     value="<?= htmlspecialchars($produk['nama']) ?>" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Harga (Rp)</label>
                <input type="number" name="harga" class="form-control" min="0"
                       value="<?= htmlspecialchars((string)($produk['harga'] ?? 0)) ?>" onchange="this.value = this.value.replace(/\D/g, '');">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">
                  Preview: Rp <?= number_format((int)($produk['harga'] ?? 0), 0, ',', '.') ?>
                </small>
              </div>
              <div class="form-group">
                <label class="form-label">Stok Fisik</label>
                <input type="number" name="stok" class="form-control" min="0"
                       value="<?= htmlspecialchars((string)($produk['stok'] ?? 0)) ?>">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">Perubahan stok fisik akan otomatis menyesuaikan stok yang bisa dijual.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Batas Stok Minimum</label>
                <input type="number" name="stok_min" class="form-control" min="0"
                       value="<?= htmlspecialchars((string)($produk['stok_min'] ?? 10)) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Satuan</label>
                <input type="text" name="satuan" class="form-control"
                       value="<?= htmlspecialchars($produk['satuan'] ?? 'pcs') ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php foreach (['aktif' => 'Aktif', 'nonaktif' => 'Tidak Aktif'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= (strtolower($produk['status'] ?? '') === $val) ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
              <a href="detail.php?id=<?= $produk['id'] ?>" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <div class="form-side">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clock-history"></i> Data Stok Saat Ini</h4>
          </div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">Stok Fisik Total</span>
              <span class="side-info-val fw-mid"><?= (int)($produk['stok'] ?? 0) ?> <?= htmlspecialchars($produk['satuan'] ?? 'pcs') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label" style="color: #dc3545;">Dibooking (Pesanan)</span>
              <span class="side-info-val" style="color: #dc3545; font-weight: bold;"><?= (int)($produk['stok_reserved'] ?? 0) ?> <?= htmlspecialchars($produk['satuan'] ?? 'pcs') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label" style="color: #059669;">Bisa Dijual (Available)</span>
              <span class="side-info-val" style="color: #059669; font-weight: bold;"><?= (int)($produk['stok_available'] ?? 0) ?> <?= htmlspecialchars($produk['satuan'] ?? 'pcs') ?></span>
            </div>
            <hr style="border:0; border-top:1px solid var(--border); margin:10px 0;">
            <div class="side-info-item">
              <span class="side-info-label">Batas Minimum</span>
              <span class="side-info-val"><?= (int)($produk['stok_min'] ?? 10) ?></span>
            </div>
          </div>
        </div>

        <div class="form-card danger-card">
          <div class="form-card-header">
            <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
          </div>
          <div class="danger-body">
            <p>Hapus produk ini secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
            <button type="button" class="btn-danger" id="deleteBtn">
              <i class="bi bi-trash"></i> Hapus Produk
            </button>
          </div>
        </div>

      </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus Produk?</h3>
        <p>Produk <strong><?= htmlspecialchars($produk['nama']) ?></strong> akan dihapus permanen dan tidak bisa dikembalikan.</p>
        <div class="modal-actions">
          <form method="post" action="delete.php">
            <input type="hidden" name="id" value="<?= $produk['id'] ?>">
            <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>

  </div>
</main>


<script>
  const modal  = document.getElementById('deleteModal');
  document.getElementById('deleteBtn').addEventListener('click',  () => modal.classList.add('show'));
  document.getElementById('cancelDelete').addEventListener('click', () => modal.classList.remove('show'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });

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