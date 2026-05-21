<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/models/SPK.php';

$spk = SPK::find($_GET['id'] ?? null);
if (!$spk) { header('Location: ../index.php'); exit; }

// Auto-sync items jika kosong atau berbeda dengan PO
$items = SPK::getItems($spk['id']);
if (empty($items) && !empty($spk['po_id'])) {
    SPK::syncItemsFromPO($spk['id']);
    $items = SPK::getItems($spk['id']);
}

$late    = $spk['status'] !== 'completed' && strtotime($spk['deadline']) < time();
$prog    = (int)$spk['progress'];

function badgeCls($s) {
    return match($s) { 'on_progress' => 'ok', 'completed' => 'ok', 'cancelled' => 'warn', default => 'neutral' };
}
function badgeLabel($s) {
    return match($s) { 'on_progress' => 'On Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', default => 'Draft' };
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail SPK | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/spk.css" rel="stylesheet">
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
          <a href="../index.php">SPK</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($spk['nomor_spk']) ?></span>
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

    <?php if ($late): ?>
    <div class="alert-late">
      <i class="bi bi-exclamation-triangle"></i>
      SPK ini melewati deadline pada <strong><?= htmlspecialchars($spk['deadline']) ?></strong> dan belum selesai.
    </div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Detail SPK</h1>
        <p class="page-subtitle">Informasi lengkap SPK <strong><?= htmlspecialchars($spk['nomor_spk']) ?></strong></p>
      </div>
      <div class="header-actions">
        <a href="edit.php?id=<?= $spk['id'] ?>" class="btn-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">

      <!-- MAIN -->
      <div class="detail-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-file-earmark-check"></i> Informasi SPK</h4>
            <span class="badge <?= badgeCls($spk['status']) ?>"><?= badgeLabel($spk['status']) ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Nomor SPK</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($spk['nomor_spk']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Nomor PO</span>
              <span class="detail-val"><?= htmlspecialchars($spk['nomor_po']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Customer</span>
              <span class="detail-val"><?= htmlspecialchars($spk['perusahaan']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">PIC</span>
              <span class="detail-val"><?= htmlspecialchars($spk['pic']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Tanggal</span>
              <span class="detail-val"><?= htmlspecialchars($spk['tanggal']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Deadline</span>
              <span class="detail-val <?= $late ? 'late-text' : '' ?>">
                <?= htmlspecialchars($spk['deadline']) ?>
                <?php if ($late): ?> <i class="bi bi-exclamation-triangle"></i><?php endif; ?>
              </span>
            </div>
            <?php if (!empty($spk['notes'])): ?>
            <div class="detail-item detail-item-full">
              <span class="detail-label">Notes</span>
              <span class="detail-val"><?= nl2br(htmlspecialchars($spk['notes'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- SIDE -->
      <div class="detail-side">

        <!-- Progress card -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-activity"></i> Progress</h4>
            <span class="prog-pct"><?= $prog ?>%</span>
          </div>
          <div class="prog-body">
            <div class="prog-bar-lg">
              <div class="prog-fill-lg <?= $prog === 100 ? 'done' : '' ?>" style="width:<?= $prog ?>%"></div>
            </div>
            <div class="prog-meta">
              <span class="text-muted">Status</span>
              <span class="badge <?= badgeCls($spk['status']) ?>"><?= badgeLabel($spk['status']) ?></span>
            </div>
            <div class="prog-meta">
              <span class="text-muted">Deadline</span>
              <span class="<?= $late ? 'late-text' : '' ?>"><?= htmlspecialchars($spk['deadline']) ?></span>
            </div>
          </div>
        </div>

        <!-- Tindakan -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="action-body">
            <a href="edit.php?id=<?= $spk['id'] ?>" class="btn-primary full">
              <i class="bi bi-pencil"></i> Edit SPK
            </a>
            <a href="../index.php" class="btn-outline full">
              <i class="bi bi-arrow-left"></i> Kembali ke Daftar
            </a>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

</body>
</html>
