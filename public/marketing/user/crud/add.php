<?php
session_start();
require_once '../../../../src/auth.php';
$conn = new mysqli('localhost', 'root', '', 'inventaris');
if ($conn->connect_error) die('Koneksi gagal: ' . $conn->connect_error);

$errors  = [];
$success = false;
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $role     = $_POST['role'] ?? '';
  $password = $_POST['password'] ?? '';
  $prefixes = ['marketing'=>'MRK','manager'=>'MNG','gudang'=>'GDG'];
  $prefix   = $prefixes[$role] ?? '';
  $username = '';
  if (!$role)                 $errors[] = 'Role wajib dipilih.';
  if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
  if ($prefix) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='$role'");
    $count = ($result && $row = $result->fetch_assoc()) ? (int)$row['total'] : 0;
    $nextNum = str_pad($count+1, 3, '0', STR_PAD_LEFT);
    $username = $prefix.'-'.$nextNum;
  }
  if (!$username) $errors[] = 'Username gagal dibuat.';
  if (!$errors) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $username, $hash, $role);
    if ($stmt->execute()) {
      $success = true;
    } else {
      $errors[] = 'Username sudah digunakan atau terjadi kesalahan.';
    }
    $stmt->close();
  }
}
$conn->close();
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah User | InventorySys</title>
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
          <span>Tambah User</span>
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
        <h1 class="page-title-lg">Tambah User</h1>
        <p class="page-subtitle">Isi formulir berikut untuk menambahkan user baru.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-person-plus"></i> Data User</h4>
          </div>

          <?php if ($errors): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>

          <?php if ($success): ?>
          <div class="alert-success">
            <i class="bi bi-check-circle"></i> User berhasil ditambahkan.
            &nbsp;<a href="../index.php" class="alert-link">Lihat daftar user</a>
          </div>
          <?php endif; ?>

          <form method="post" class="user-form">
            <div class="form-group">
              <label class="form-label">Role <span class="required">*</span></label>
              <select name="role" class="form-control" id="roleSelect" required>
                <option value="" disabled selected>-- Pilih Role --</option>
                <?php foreach (['marketing' => 'Marketing', 'manager' => 'Manager', 'gudang' => 'Gudang'] as $val => $label): ?>
                  <option value="<?= $val ?>" <?= ($_POST['role'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Username (otomatis)</label>
              <input type="text" name="username" class="form-control" id="usernameInput" value="<?= htmlspecialchars($username ?? '') ?>" readonly>
            </div>
            <div class="form-group">
              <label class="form-label">Password <span class="required">*</span></label>
              <div class="input-wrap">
                <input type="password" name="password" class="form-control" id="pwdInput"
                       placeholder="Minimal 6 karakter" required>
                <button type="button" class="toggle-pwd" id="togglePwd" aria-label="Tampilkan password">
                  <i class="bi bi-eye" id="pwdIcon"></i>
                </button>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan User</button>
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
            <li><i class="bi bi-dot"></i> Username harus unik dan belum digunakan.</li>
            <li><i class="bi bi-dot"></i> <strong>Marketing</strong> — akses modul PO, SPK, Produk, Customer.</li>
            <li><i class="bi bi-dot"></i> <strong>Gudang</strong> — akses penerimaan, pengeluaran, surat jalan.</li>
            <li><i class="bi bi-dot"></i> <strong>Manager</strong> — akses laporan dan monitoring.</li>
            <li><i class="bi bi-dot"></i> Password minimal 6 karakter.</li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</main>

<?php include '../../../../templates/nav-script.php'; ?>
<script>
  // Toggle password visibility
  document.getElementById('togglePwd').addEventListener('click', function () {
    const inp  = document.getElementById('pwdInput');
    const icon = document.getElementById('pwdIcon');
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  // Auto-generate username on role change
  document.getElementById('roleSelect').addEventListener('change', function () {
    const role = this.value;
    const usernameInput = document.getElementById('usernameInput');
    if (!role) {
      usernameInput.value = '';
      return;
    }
    fetch('get_username.php?role=' + encodeURIComponent(role))
      .then(res => res.json())
      .then(data => {
        usernameInput.value = data.username || '';
      })
      .catch(() => {
        usernameInput.value = '';
      });
  });
</script>
</body>
</html>
