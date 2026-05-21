<?php
session_start();
require_once '../../../src/auth.php';
require_once '../../../src/models/Customer.php';

$customer = Customer::find($_GET['id'] ?? null);
if (!$customer) { header('Location: index.php'); exit; }

$statusCls   = $customer['status'] === 'aktif' ? 'ok' : 'warn';
$statusLabel = $customer['status'] === 'aktif' ? 'Aktif' : 'Nonaktif';
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Customer | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/customer.css" rel="stylesheet">
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="index.php">Customer</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($customer['nama']) ?></span>
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
        <h1 class="page-title-lg">Detail Customer</h1>
        <p class="page-subtitle">
          <strong><?= htmlspecialchars($customer['nama']) ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
        </p>
      </div>
      <div class="header-actions">
        <a href="edit.php?id=<?= $customer['id'] ?>" class="btn-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">

      <!-- INFO UTAMA -->
      <div class="detail-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-person-vcard"></i> Informasi Customer</h4>
            <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Kode Customer</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($customer['kode_customer']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-val"><span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Nama</span>
              <span class="detail-val"><?= htmlspecialchars($customer['nama']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Perusahaan</span>
              <span class="detail-val"><?= htmlspecialchars($customer['perusahaan'] ?: '—') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Email</span>
              <span class="detail-val">
                <?php if ($customer['email']): ?>
                  <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" class="link-accent"><?= htmlspecialchars($customer['email']) ?></a>
                <?php else: ?>—<?php endif; ?>
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">No HP</span>
              <span class="detail-val"><?= htmlspecialchars($customer['no_hp'] ?: '—') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Kota</span>
              <span class="detail-val"><?= htmlspecialchars($customer['kota'] ?: '—') ?></span>
            </div>
            <?php if ($customer['alamat']): ?>
            <div class="detail-item detail-item-full">
              <span class="detail-label">Alamat</span>
              <span class="detail-val"><?= nl2br(htmlspecialchars($customer['alamat'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- SIDE -->
      <div class="detail-side">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="danger-body">
            <a href="edit.php?id=<?= $customer['id'] ?>" class="btn-primary full" style="margin-bottom:8px;">
              <i class="bi bi-pencil"></i> Edit Customer
            </a>
            <a href="index.php" class="btn-outline full">
              <i class="bi bi-arrow-left"></i> Kembali ke Daftar
            </a>
          </div>
        </div>

        <div class="form-card cust-meta-card">
          <div class="form-card-header">
            <h4><i class="bi bi-info-circle"></i> Info</h4>
          </div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">ID</span>
              <span class="side-info-val text-muted">#<?= $customer['id'] ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Kode</span>
              <span class="side-info-val fw-mid"><?= htmlspecialchars($customer['kode_customer']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Status</span>
              <span class="side-info-val"><span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span></span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</main>


</body>
</html>
