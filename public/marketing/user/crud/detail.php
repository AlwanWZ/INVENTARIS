<?php
session_start();
require_once '../../../../src/auth.php';
$conn = new mysqli('localhost', 'root', '', 'inventaris');
if ($conn->connect_error) die('Koneksi gagal: ' . $conn->connect_error);

$id = (int)($_GET['id'] ?? 0);
$user = null;
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$conn->close();
if (!$user) { header('Location: ../index.php'); exit; }

$roleCls = match($user['role']) { 'marketing' => 'role-marketing', 'manager' => 'role-manager', 'gudang' => 'role-gudang', default => '' };
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail User | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/users.css" rel="stylesheet">
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
          <a href="../index.php">Users</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($user['username']) ?></span>
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
        <h1 class="page-title-lg">Detail User</h1>
        <p class="page-subtitle">
          <strong><?= htmlspecialchars($user['username']) ?></strong>
          &mdash; <span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span>
        </p>
      </div>
      <div class="header-actions">
        <a href="edit.php?id=<?= $user['id'] ?>" class="btn-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">
      <div class="detail-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-person-circle"></i> Informasi User</h4>
            <span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Username</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Role</span>
              <span class="detail-val"><span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span></span>
            </div>
            <div class="detail-item detail-item-full">
              <span class="detail-label">Password Hash</span>
              <span class="detail-val hash-preview"><?= htmlspecialchars($user['password']) ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="detail-side">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-person-badge"></i> Profil</h4>
          </div>
          <div class="user-profile-card">
            <div class="user-profile-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <div class="user-profile-name"><?= htmlspecialchars($user['username']) ?></div>
            <span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span>
          </div>
          <div class="total-divider"></div>
          <div class="total-meta">
            <span>ID</span>
            <span class="text-muted">#<?= $user['id'] ?></span>
          </div>
        </div>

        <div class="form-card action-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="danger-body">
            <a href="edit.php?id=<?= $user['id'] ?>" class="btn-primary full" style="margin-bottom:8px;">
              <i class="bi bi-pencil"></i> Edit User
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
