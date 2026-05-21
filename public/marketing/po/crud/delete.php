<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? null);
$po = $id ? PO::find($id) : null;

if (!$po) {
    header('Location: ../index.php');
    exit;
}

// Konfirmasi delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (PO::delete($id)) {
        header('Location: ../index.php?deleted=1');
        exit;
    } else {
        header('Location: ../index.php?deleted=0');
        exit;
    }
}

// Tampilkan form konfirmasi
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hapus Pesanan PCB | InventorySys</title>
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
          <span>Hapus Pesanan</span>
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
        <h1 class="page-title-lg">Hapus Pesanan</h1>
        <p class="page-subtitle">Konfirmasi penghapusan pesanan ini secara permanen.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <!-- KONFIRMASI -->
    <div class="form-layout">
      <div class="form-main">
        <!-- WARNING SECTION -->
        <div class="form-card" style="border: 2px solid #dc3545; background: #fff5f5;">
          <div class="form-card-header">
            <h4 style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i> Konfirmasi Penghapusan Pesanan</h4>
          </div>
          <p style="margin-bottom: 1.5rem; color: #555; line-height: 1.6;">
            Anda akan menghapus pesanan secara permanen. Tindakan ini <strong>TIDAK DAPAT DIBATALKAN</strong> dan akan menghapus semua data pesanan dari sistem.
          </p>

          <!-- DETAIL SECTION -->
          <div style="background: white; padding: 1.25rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #ffe0e0;">
            <h5 style="margin-bottom: 1rem; color: #333; font-size: 0.95rem;">Data Pesanan yang akan Dihapus:</h5>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Nomor Pesanan</span>
                <span class="detail-val fw-mid" style="color: #dc3545; font-size: 1.1rem;"><?= htmlspecialchars($po['nomor_po'] ?? '-') ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Customer / Perusahaan</span>
                <span class="detail-val"><?= htmlspecialchars($po['perusahaan'] ?? $po['customer'] ?? '-') ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Tanggal Pesanan</span>
                <span class="detail-val"><?= htmlspecialchars($po['tanggal'] ?? '-') ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-val"><?= htmlspecialchars($po['status'] ?? '-') ?></span>
              </div>
            </div>
          </div>

          <!-- ACTION BUTTONS -->
          <div style="display: flex; gap: 1rem;">
            <form method="post" style="flex: 1;">
              <input type="hidden" name="id" value="<?= $id ?>">
              <button type="submit" class="btn-danger" style="width: 100%;">
                <i class="bi bi-trash-fill"></i> Hapus Pesanan Selamanya
              </button>
            </form>
            <a href="../index.php" class="btn-outline" style="flex: 1; display: flex; align-items: center; justify-content: center;">
              <i class="bi bi-x-lg"></i> Batal
            </a>
          </div>
        </div>
      </div>

      <div class="form-side">
        <!-- CHECKLIST BEFORE DELETE -->
        <div class="form-card" style="border-left: 4px solid #dc3545;">
          <div class="form-card-header">
            <h4><i class="bi bi-clipboard-check"></i> Sebelum Menghapus</h4>
          </div>
          <ul class="info-list" style="font-size: 0.9rem;">
            <li style="margin-bottom: 0.75rem;"><i class="bi bi-check2-square"></i> <strong>Pastikan</strong> status pesanan adalah Draft</li>
            <li style="margin-bottom: 0.75rem;"><i class="bi bi-check2-square"></i> <strong>Verifikasi</strong> tidak ada items/produk dalam pesanan</li>
            <li style="margin-bottom: 0.75rem;"><i class="bi bi-check2-square"></i> <strong>Confirm</strong> data pesanan di atas sudah benar</li>
          </ul>
        </div>

        <!-- WARNING CARD -->
        <div class="form-card" style="background: #fff3cd; border-left: 4px solid #ff9800;">
          <div class="form-card-header">
            <h4 style="color: #ff9800;"><i class="bi bi-exclamation-circle-fill"></i> Peringatan</h4>
          </div>
          <ul class="info-list" style="font-size: 0.85rem;">
            <li><i class="bi bi-exclamation-circle"></i> Tidak ada backup otomatis</li>
            <li><i class="bi bi-exclamation-circle"></i> Data tidak dapat dipulihkan</li>
            <li><i class="bi bi-exclamation-circle"></i> Hanya admin yang bisa membatalkan</li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
document.getElementById('menuBtn')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('active');
});

document.getElementById('themeToggle')?.addEventListener('click', () => {
  const html = document.documentElement;
  html.dataset.theme = html.dataset.theme === 'light' ? 'dark' : 'light';
  localStorage.setItem('theme', html.dataset.theme);
});
</script>

</body>
</html>
