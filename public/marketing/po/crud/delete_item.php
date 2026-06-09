<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';

$item = PO::getItem($_GET['id'] ?? null);
if (!$item) {
    header('Location: ../index.php');
    exit;
}

$po = PO::find($item['po_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Kembalikan stok (Unreserve item ini saja)
        $stmtUpdateStok = $pdo->prepare("UPDATE produk SET stok_reserved = stok_reserved - ?, stok_available = stok_available + ? WHERE id = ?");
        $stmtUpdateStok->execute([$item['qty'], $item['qty'], $item['produk_id']]);

        // 2. Hapus item
        PO::deleteItem($item['id']);

        $pdo->commit();
        header('Location: detail.php?id=' . $item['po_id'] . '&item_deleted=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Gagal menghapus item: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hapus Item | InventorySys</title>
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

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Pesanan PCB</a>
          <i class="bi bi-chevron-right"></i>
          <span>Hapus Item</span>
        </div>
      </div>
      <div class="top-right">
        <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
        <div class="user-box">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <div style="max-width: 600px; margin: 3rem auto;">
      <div class="form-card">
        <div class="form-card-header">
          <h4 style="color: #dc3545;"><i class="bi bi-exclamation-triangle"></i> Konfirmasi Hapus Item</h4>
        </div>

        <div style="padding: 2rem; text-align: center;">
          <i class="bi bi-trash" style="font-size: 3rem; color: #dc3545; margin-bottom: 1rem; display: block;"></i>

          <p style="margin-bottom: 2rem; color: #495057;">
            Apakah Anda yakin ingin menghapus item ini dari pesanan?
          </p>

          <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 4px; margin-bottom: 2rem; text-align: left;">
            <div style="margin-bottom: 1rem;">
              <strong>Pesanan:</strong> <?= htmlspecialchars($po['nomor_po']) ?>
            </div>
            <div style="margin-bottom: 1rem;">
              <strong>Item:</strong> <?= htmlspecialchars($item['nama_material']) ?> (<?= $item['qty'] ?> <?= htmlspecialchars($item['uom']) ?>)
            </div>
            <div>
              <strong>Total Item:</strong> Rp <?= number_format($item['amount'], 0, ',', '.') ?>
            </div>
          </div>

          <form method="post" style="display: inline;">
            <button type="submit" class="btn-danger" style="padding: 0.75rem 2rem;">
              <i class="bi bi-trash"></i> Hapus Item
            </button>
          </form>
          <a href="detail.php?id=<?= $item['po_id'] ?>" class="btn-outline" style="padding: 0.75rem 2rem; margin-left: 1rem;">
            <i class="bi bi-arrow-left"></i> Batal
          </a>
        </div>
      </div>
    </div>

  </div>
</main>

</body>
</html>
