<?php
session_start();
require_once '../../../../src/auth.php';
$conn = new mysqli('localhost', 'root', '', 'inventaris');
if ($conn->connect_error) die('Koneksi gagal: ' . $conn->connect_error);

$id = (int)($_GET['id'] ?? 0);
$user = null;
$errors = [];
$success = false;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$user) { header('Location: ../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $role     = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$username) $errors[] = 'Username wajib diisi.';
    if (!$role)     $errors[] = 'Role wajib dipilih.';
    if ($password && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';

    if (!$errors) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, role=?, password=? WHERE id=?");
            $stmt->bind_param('sssi', $username, $role, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
            $stmt->bind_param('ssi', $username, $role, $id);
        }
        if ($stmt->execute()) {
            $success = true;
            $user['username'] = $username;
            $user['role']     = $role;
            if ($password) $user['password'] = $hash;
        } else {
            $errors[] = 'Gagal memperbarui user. Username mungkin sudah digunakan.';
        }
        $stmt->close();
    }
}
$conn->close();
$roleCls = match($user['role']) { 'marketing' => 'role-marketing', 'manager' => 'role-manager', 'gudang' => 'role-gudang', default => '' };
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit User | InventorySys</title>
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
          <a href="detail.php?id=<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></a>
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
        <h1 class="page-title-lg">Edit User</h1>
        <p class="page-subtitle">
          Mengedit <strong><?= htmlspecialchars($user['username']) ?></strong>
          &mdash; <span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span>
        </p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-pencil-square"></i> Data User</h4>
            <span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span>
          </div>

          <?php if ($errors): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>

          <?php if ($success): ?>
          <div class="alert-success">
            <i class="bi bi-check-circle"></i> User berhasil diperbarui.
          </div>
          <?php endif; ?>

          <form method="post" class="user-form">
            <div class="form-group">
              <label class="form-label">Username <span class="required">*</span></label>
              <input type="text" name="username" class="form-control"
                     value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Role <span class="required">*</span></label>
              <select name="role" class="form-control" required>
                <?php foreach (['marketing' => 'Marketing', 'manager' => 'Manager', 'gudang' => 'Gudang'] as $val => $label): ?>
                  <option value="<?= $val ?>" <?= $user['role'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Password Baru <span class="text-muted" style="font-weight:400;">(kosongkan jika tidak diubah)</span></label>
              <div class="input-wrap">
                <input type="password" name="password" class="form-control" id="pwdInput" placeholder="Minimal 6 karakter">
                <button type="button" class="toggle-pwd" id="togglePwd">
                  <i class="bi bi-eye" id="pwdIcon"></i>
                </button>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
              <a href="../index.php" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <div class="form-side">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clock-history"></i> Data Sebelumnya</h4>
          </div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">Username</span>
              <span class="side-info-val fw-mid"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Role</span>
              <span class="side-info-val"><span class="badge <?= $roleCls ?>"><?= htmlspecialchars($user['role']) ?></span></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">ID</span>
              <span class="side-info-val text-muted">#<?= $user['id'] ?></span>
            </div>
          </div>
        </div>

        <div class="form-card danger-card">
          <div class="form-card-header">
            <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
          </div>
          <div class="danger-body">
            <p>Hapus user ini secara permanen. Tindakan ini tidak dapat dibatalkan.</p>
            <button type="button" class="btn-danger" id="deleteBtn">
              <i class="bi bi-trash"></i> Hapus User
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus User?</h3>
        <p>User <strong><?= htmlspecialchars($user['username']) ?></strong> akan dihapus permanen.</p>
        <div class="modal-actions">
          <form method="post" action="../crud/delete.php">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>

  </div>
</main>

<?php include '../../../../templates/nav-script.php'; ?>
<script>
  // Password toggle
  document.getElementById('togglePwd').addEventListener('click', function () {
    const inp  = document.getElementById('pwdInput');
    const icon = document.getElementById('pwdIcon');
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  // Delete modal
  const modal = document.getElementById('deleteModal');
  document.getElementById('deleteBtn').addEventListener('click',    () => modal.classList.add('show'));
  document.getElementById('cancelDelete').addEventListener('click', () => modal.classList.remove('show'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
