<?php
session_start();
require_once '../../../src/auth.php';
$conn = new mysqli('localhost', 'root', '', 'inventaris');
if ($conn->connect_error) die('Koneksi gagal: ' . $conn->connect_error);

$currentUser = $_SESSION['user']['username'] ?? '';
$users = [];
$result = $conn->query("SELECT * FROM users WHERE username != '" . $conn->real_escape_string($currentUser) . "' ORDER BY id DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $users[] = $row;
}
$conn->close();
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Users | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/users.css" rel="stylesheet">
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Users</span>
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

    <?php if (isset($_GET['success'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> User berhasil ditambahkan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> User berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert-warn"><i class="bi bi-trash"></i> User berhasil dihapus.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Users</h1>
        <p class="page-subtitle">Kelola semua user yang terdaftar dalam sistem.</p>
      </div>
      <a href="crud/add.php" class="btn-primary">
        <i class="bi bi-plus-lg"></i> Tambah User
      </a>
    </div>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Users</span>
        <span class="stat-pill-val"><?= count($users) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Marketing</span>
        <span class="stat-pill-val role-marketing"><?= count(array_filter($users, fn($u) => $u['role'] === 'marketing')) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Manager</span>
        <span class="stat-pill-val role-manager"><?= count(array_filter($users, fn($u) => $u['role'] === 'manager')) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Gudang</span>
        <span class="stat-pill-val role-gudang"><?= count(array_filter($users, fn($u) => $u['role'] === 'gudang')) ?></span>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-person-lines-fill"></i> Daftar Users</h4>
        <div class="table-actions">
          <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Cari username atau role...">
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table id="usersTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Username</th>
              <th>Role</th>
              <th>Password</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
            <tr>
              <td colspan="5" class="empty-state">
                <i class="bi bi-people"></i>
                <span>Belum ada user. <a href="crud/add.php">Tambah sekarang</a></span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($users as $i => $u):
              $roleCls = match($u['role']) { 'marketing' => 'role-marketing', 'manager' => 'role-manager', 'gudang' => 'role-gudang', default => '' };
            ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td>
                <div class="user-cell">
                  <div class="user-cell-avatar"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                  <span class="fw-mid"><?= htmlspecialchars($u['username']) ?></span>
                </div>
              </td>
              <td><span class="badge <?= $roleCls ?>"><?= htmlspecialchars($u['role']) ?></span></td>
              <td><span class="hash-preview"><?= htmlspecialchars(substr($u['password'], 0, 30)) ?>…</span></td>
              <td>
                <div class="action-btns">
                  <!-- ROW 1: VIEW & EDIT -->
                  <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <a href="crud/detail.php?id=<?= $u['id'] ?>" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
                    <a href="crud/edit.php?id=<?= $u['id'] ?>" class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
                  </div>
                  <!-- ROW 2: DELETE -->
                  <div>
                    <button type="button" class="btn-icon danger" title="Hapus"
                            onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="table-footer">
        <span class="text-muted" id="tableCount">Menampilkan <?= count($users) ?> data</span>
      </div>
    </div>

  </div>
</main>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <h3>Hapus User?</h3>
    <p>User <strong id="deleteTarget"></strong> akan dihapus permanen.</p>
    <div class="modal-actions">
      <form method="post" action="crud/delete.php" id="deleteForm">
        <input type="hidden" name="id" id="deleteId">
        <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      </form>
      <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
    </div>
  </div>
</div>

<script>
  // Search
  document.getElementById('searchInput').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    let n = 0;
    document.querySelectorAll('#usersTable tbody tr').forEach(r => {
      const show = r.textContent.toLowerCase().includes(q);
      r.style.display = show ? '' : 'none';
      if (show) n++;
    });
    document.getElementById('tableCount').textContent = `Menampilkan ${n} data`;
  });

  // Delete modal
  const modal = document.getElementById('deleteModal');
  function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteTarget').textContent = name;
    modal.classList.add('show');
  }
  document.getElementById('cancelDelete').addEventListener('click', () => modal.classList.remove('show'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
