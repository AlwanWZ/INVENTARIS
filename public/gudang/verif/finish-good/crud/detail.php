<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';

$verifModel = new Verifikasi($pdo);
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: ../index.php'); exit; }
$data  = $verifModel->getById($id);
if (!$data) { header('Location: ../index.php'); exit; }

// Show all items
$items = $verifModel->getItems($id);
$totalOK = array_sum(array_column($items, 'qty_ok'));
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Verifikasi | InventorySys</title>
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
          <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Verifikasi Finish Good</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($data['nomor_penerimaan']) ?></span>
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
        <h1 class="page-title-lg">Detail Verifikasi</h1>
        <p class="page-subtitle">
          Ref. verifikasi: <strong><?= htmlspecialchars($data['nomor_penerimaan']) ?></strong> &mdash; <?= htmlspecialchars($data['tanggal']) ?>
        </p>
      </div>
      <div class="header-actions">
        <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">
      <div class="detail-main">

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-patch-check"></i> Item Verifikasi</h4>
            <span class="count-badge"><?= count($items) ?> produk</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="col-right">Qty Masuk</th>
                  <th class="col-right">Qty OK</th>
                  <th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                  <td class="fw-mid"><?= htmlspecialchars($item['produk_nama'] ?? '-') ?></td>
                  <td class="col-right text-muted"><?= $item['qty_masuk'] ?></td>
                  <td class="col-right qc-ok fw-mid"><?= $item['qty_ok'] ?></td>
                  <td class="text-muted"><?= htmlspecialchars($item['keterangan'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td class="fw-mid" colspan="2" style="text-align: right;">Total OK</td>
                  <td class="col-right qc-ok fw-mid" style="font-size:1.1rem;"><?= $totalOK ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>

      <div class="detail-side">

        <div class="form-card">
          <div class="form-card-header"><h4><i class="bi bi-clipboard-data"></i> Info Penerimaan</h4></div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">Nomor</span>
              <span class="side-info-val fw-mid"><?= htmlspecialchars($data['nomor_penerimaan']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Tanggal</span>
              <span class="side-info-val"><?= htmlspecialchars($data['tanggal']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Status</span>
              <span class="side-info-val fw-mid" style="text-transform: uppercase; color: <?= $data['status'] === 'verified' ? '#10b981' : '#e8621a' ?>;"><?= htmlspecialchars($data['status']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">PIC</span>
              <span class="side-info-val"><?= htmlspecialchars($data['pic_name']) ?></span>
            </div>
          </div>
        </div>

        <div class="form-card">
          <div class="form-card-header"><h4><i class="bi bi-lightning"></i> Tindakan</h4></div>
          <div class="action-body">
            <a href="edit.php?id=<?= $data['id'] ?>" class="btn-warn full"><i class="bi bi-pencil"></i> Edit Verifikasi</a>
            <a href="../index.php" class="btn-outline full"><i class="bi bi-arrow-left"></i> Kembali</a>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

</body>
</html>