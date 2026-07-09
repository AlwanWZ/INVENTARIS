<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Barang.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? null);

// Cari data barang untuk ditampilin di form
$stmtCari = $pdo->prepare("SELECT * FROM barang WHERE id = ?");
$stmtCari->execute([$id]);
$barang = $stmtCari->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    header('Location: ../index.php');
    exit;
}

$errorMessage = '';

// 🚀 KONFIRMASI HARD DELETE VIA POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Mulai Transaksi (Kalau gagal satu, gagal semua)
        $pdo->beginTransaction();

        // 1. SAPU BERSIH ANAKNYA DULU (Riwayat stok lama dan baru)
        $pdo->prepare("DELETE FROM log_stok WHERE barang_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stok_log WHERE barang_id = ?")->execute([$id]);
        
        // (Bisa tambahin delete tabel lain di sini kalau ada histori abal-abal yang mau dihapus)

        // 2. BARU EKSEKUSI HARD DELETE BAPAKNYA (Tabel barang)
        $stmtDel = $pdo->prepare("DELETE FROM barang WHERE id = ?");
        $stmtDel->execute([$id]);
        
        // Simpan perubahan permanen
        $pdo->commit();
        
        header('Location: ../index.php?deleted=1');
        exit;
        
    } catch (PDOException $e) {
        // Batalkan semua penghapusan kalau tiba-tiba ada error
        $pdo->rollBack();
        
        // Tangkap kalau database nolak karena barang udah dipakai di transaksi PENTING (PO / Pengeluaran / SPK)
        if ($e->getCode() == '23000') {
            $errorMessage = "⚠️ GAGAL! Produk ini <strong>tidak bisa dihapus permanen</strong> karena masih nyangkut di riwayat transaksi penting (Pesanan / SPK / Barang Keluar). Hapus transaksi yang bersangkutan terlebih dahulu!";
        } else {
            $errorMessage = "⚠️ Error Database: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hapus Produk | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/barang.css" rel="stylesheet">
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
          <span>Hapus Produk</span>
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
        <h1 class="page-title-lg">Hapus Produk</h1>
        <p class="page-subtitle">Konfirmasi penghapusan barang ini secara permanen dari database.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <?php if ($errorMessage): ?>
    <div style="background:#fff5f5; color:#dc3545; padding:15px; border-radius:8px; border:1px solid #ffcdd2; margin-bottom: 20px; font-weight: 500; line-height: 1.5;">
        <?= $errorMessage ?>
    </div>
    <?php endif; ?>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card" style="border: 2px solid #dc3545; background: #fff5f5;">
          <div class="form-card-header">
            <h4 style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i> Konfirmasi Penghapusan Produk</h4>
          </div>
          <p style="margin-bottom: 1.5rem; color: #555; line-height: 1.6;">
            Anda akan menghapus barang secara permanen. Tindakan ini <strong>TIDAK DAPAT DIBATALKAN</strong> dan akan menghapus riwayat log stok beserta data barang dari sistem.
          </p>

          <div style="background: white; padding: 1.25rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #ffe0e0;">
            <h5 style="margin-bottom: 1rem; color: #333; font-size: 0.95rem;">Data Produk yang akan Dihapus:</h5>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Kode Produk</span>
                <span class="detail-val fw-mid" style="color: #dc3545; font-size: 1.1rem;">
                  <?= htmlspecialchars($barang['kode'] ?: $barang['kode_barang'] ?? '-') ?>
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Nama Produk</span>
                <span class="detail-val"><?= htmlspecialchars($barang['nama'] ?? '-') ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Kategori</span>
                <span class="detail-val"><?= htmlspecialchars($barang['kategori'] ?? '-') ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">HPP (Harga Pokok)</span>
                <span class="detail-val">Rp <?= number_format((int)($barang['harga'] ?? 0), 0, ',', '.') ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Stok Fisik Gudang</span>
                <span class="detail-val"><?= (int)($barang['stok'] ?? 0) ?> <?= htmlspecialchars($barang['satuan'] ?? 'pcs') ?></span>
              </div>
            </div>
          </div>

          <div style="display: flex; gap: 1rem;">
            <form method="post" style="flex: 1;">
              <input type="hidden" name="id" value="<?= $id ?>">
              <button type="submit" class="btn-danger" style="width: 100%;">
                <i class="bi bi-trash-fill"></i> Hapus Produk Selamanya
              </button>
            </form>
            <a href="../index.php" class="btn-outline" style="flex: 1; display: flex; align-items: center; justify-content: center;">
              <i class="bi bi-x-lg"></i> Batal
            </a>
          </div>
        </div>
      </div>

      <div class="form-side">
        <div class="form-card" style="border-left: 4px solid #dc3545;">
          <div class="form-card-header">
            <h4><i class="bi bi-clipboard-check"></i> Sebelum Menghapus</h4>
          </div>
          <ul class="info-list" style="font-size: 0.9rem;">
            <li style="margin-bottom: 0.75rem;"><i class="bi bi-check2-square"></i> <strong>Pastikan</strong> tidak ada PO/Pesanan yang menggunakan barang ini</li>
            <li style="margin-bottom: 0.75rem;"><i class="bi bi-check2-square"></i> <strong>Verifikasi</strong> stok barang sudah dikosongkan (0)</li>
            <li style="margin-bottom: 0.75rem;"><i class="bi bi-check2-square"></i> <strong>Confirm</strong> data barang di atas sudah benar</li>
          </ul>
        </div>

        <div class="form-card" style="background: #fff3cd; border-left: 4px solid #ff9800;">
          <div class="form-card-header">
            <h4 style="color: #ff9800;"><i class="bi bi-exclamation-circle-fill"></i> Peringatan</h4>
          </div>
          <ul class="info-list" style="font-size: 0.85rem;">
            <li><i class="bi bi-exclamation-circle"></i> Tidak ada backup otomatis</li>
            <li><i class="bi bi-exclamation-circle"></i> Data musnah dari database</li>
            <li><i class="bi bi-exclamation-circle"></i> Jika error, tandanya barang sedang digunakan transaksi.</li>
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