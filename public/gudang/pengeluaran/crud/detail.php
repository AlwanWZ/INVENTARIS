<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Pengeluaran.php';

$pengeluaranModel = new Pengeluaran($pdo);
$id    = $_GET['id'] ?? null;
if (!$id) { header('Location: ../index.php'); exit; }
$data  = $pengeluaranModel->getById($id);
$items = $pengeluaranModel->getItems($id);
if (!$data) { header('Location: ../index.php'); exit; }

function badgeCls($s) {
    return match($s) { 'completed' => 'ok', 'shipped' => 'blue', 'packing' => 'purple', 'picking' => 'teal', default => 'warn' };
}
function badgeLabel($s) {
    return match($s) { 'completed' => 'Completed', 'shipped' => 'Shipped', 'packing' => 'Packing', 'picking' => 'Picking', default => 'Draft' };
}

$totalQty = array_sum(array_column($items, 'qty'));
$lowStok  = array_filter($items, fn($i) => ($i['stok'] - $i['qty']) < 10);
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Pengeluaran | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/pengeluaran.css" rel="stylesheet">
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
          <a href="../index.php">Pengeluaran</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($data['nomor_pengeluaran']) ?></span>
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

    <?php if (!empty($lowStok)): ?>
    <div class="alert-late">
      <i class="bi bi-exclamation-triangle"></i>
      <strong><?= count($lowStok) ?> produk</strong> memiliki sisa stok di bawah 10 unit setelah pengeluaran ini.
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Detail Pengeluaran</h1>
        <p class="page-subtitle">
          <strong><?= htmlspecialchars($data['nomor_pengeluaran']) ?></strong>
          &mdash; <span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span>
        </p>
      </div>
      <div class="header-actions">
        <a href="edit.php?id=<?= $data['id'] ?>" class="btn-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">
      <div class="detail-main">

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-box-arrow-up"></i> Informasi Pengeluaran</h4>
            <span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Nomor Pengeluaran</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($data['nomor_pengeluaran']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Tanggal</span>
              <span class="detail-val"><?= htmlspecialchars($data['tanggal']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Nomor SPK</span>
              <span class="detail-val"><?= htmlspecialchars($data['nomor_spk'] ?: '—') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">PIC</span>
              <span class="detail-val"><?= htmlspecialchars($data['pic_name']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-val"><span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span></span>
            </div>
            <?php if (!empty($data['notes'])): ?>
            <div class="detail-item detail-item-full">
              <span class="detail-label">Catatan</span>
              <span class="detail-val"><?= nl2br(htmlspecialchars($data['notes'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-list-ul"></i> Daftar Item</h4>
            <span class="count-badge"><?= count($items) ?> item</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="col-right">Stok Sebelum</th>
                  <th class="col-right">Qty Keluar</th>
                  <th class="col-right">Sisa Stok</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item):
                  $sisa = $item['stok'] - $item['qty'];
                ?>
                <tr>
                  <td><span class="fw-mid"><?= htmlspecialchars($item['nama']) ?></span></td>
                  <td class="col-right text-muted"><?= $item['stok'] ?></td>
                  <td class="col-right fw-mid"><?= $item['qty'] ?></td>
                  <td class="col-right <?= $sisa < 10 ? 'stok-danger' : 'stok-ok' ?>">
                    <?= $sisa ?>
                    <?php if ($sisa < 10): ?><i class="bi bi-exclamation-circle"></i><?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td class="fw-mid">Total</td>
                  <td class="col-right">—</td>
                  <td class="col-right fw-mid"><?= $totalQty ?></td>
                  <td class="col-right">—</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>

      <div class="detail-side">

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clipboard-data"></i> Ringkasan</h4>
          </div>
          <div class="summary-body">
            <div class="summary-total">
              <span class="summary-label">Total Item</span>
              <span class="summary-val"><?= count($items) ?> jenis</span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-row">
              <span>Total Qty Keluar</span>
              <span class="fw-mid"><?= $totalQty ?></span>
            </div>
            <div class="summary-row">
              <span>Stok Kritis</span>
              <span class="fw-mid <?= count($lowStok) > 0 ? 'stok-danger' : 'stok-ok' ?>"><?= count($lowStok) ?> produk</span>
            </div>
            <div class="summary-row">
              <span>Status</span>
              <span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span>
            </div>
          </div>
        </div>

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="action-body">
            <a href="edit.php?id=<?= $data['id'] ?>" class="btn-primary full"><i class="bi bi-pencil"></i> Edit Pengeluaran</a>
            <a href="../index.php" class="btn-outline full"><i class="bi bi-arrow-left"></i> Kembali ke Daftar</a>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

<?php include '../../../../templates/nav-script.php'; ?>
</body>
</html>
