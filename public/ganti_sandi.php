<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /Inventaris/public/login.php');
    exit;
}

// 🚀 FIX: Path cuma lompat 1 kali ke folder src/
require_once '../src/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pw'])) {
    $userId       = $_SESSION['user']['id'];
    $sandiLama    = $_POST['sandi_lama'] ?? '';
    $sandiBaru    = $_POST['sandi_baru'] ?? '';
    $sandiConfirm = $_POST['sandi_confirm'] ?? '';

    if (empty($sandiLama) || empty($sandiBaru) || empty($sandiConfirm)) {
        $errors[] = "Semua kolom kata sandi wajib diisi!";
    } elseif (strlen($sandiBaru) < 6) {
        $errors[] = "Kata sandi baru minimal harus terdiri dari 6 karakter.";
    } elseif ($sandiBaru !== $sandiConfirm) {
        $errors[] = "Konfirmasi kata sandi baru tidak cocok dengan sandi baru!";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $dbPassword = $stmt->fetchColumn();

        // Smart Check: Support Password Hash baru maupun teks polos lama
        $isOldValid = false;
        if (password_verify($sandiLama, $dbPassword)) {
            $isOldValid = true;
        } elseif ($sandiLama === $dbPassword) {
            $isOldValid = true;
        }

        if (!$isOldValid) {
            $errors[] = "Kata sandi saat ini (Sandi Lama) yang Anda masukkan salah!";
        } else {
            try {
                $newHashedPassword = password_hash($sandiBaru, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdate->execute([$newHashedPassword, $userId]);
                $success = true;
            } catch (Exception $e) {
                $errors[] = "Gagal memperbarui sandi: " . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ganti Kata Sandi | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <style>
    .settings-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start; }
    .form-section-header { font-weight: 700; color: var(--text); border-bottom: 2px solid var(--accent-bg); padding-bottom: 12px; margin-bottom: 18px; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
    .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--text); }
    .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); font-size: 0.95rem; }
    .form-control:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15); }
    .tips-list { list-style: none; padding: 0; margin: 12px 0 0 0; }
    .tips-list li { position: relative; padding-left: 20px; margin-bottom: 12px; font-size: 0.85rem; color: var(--text2); line-height: 1.4; }
    .tips-list li::before { content: "•"; position: absolute; left: 4px; color: #059669; font-size: 1.2rem; top: -4px; }
    
    @media (max-width: 768px) {
      .settings-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- 🚀 FIX: Path cuma lompat 1 kali ke folder templates/ -->
<?php include '../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR STANDAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Pengaturan Akun</span>
        </div>
      </div>
      <div class="top-right">
        <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
        <div class="user-box">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            <span class="user-role"><?= htmlspecialchars(ucfirst($_SESSION['user']['role'])) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- NOTIFIKASI -->
    <?php if (!empty($errors)): ?>
      <div class="alert-error" style="margin-bottom: 24px;">
        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($errors[0]) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert-success" style="margin-bottom: 24px;">
        <i class="bi bi-check-circle"></i> Kata sandi Anda berhasil diperbarui! Silakan gunakan sandi baru pada sesi login berikutnya.
      </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Ganti Kata Sandi</h1>
        <p class="page-subtitle">Kelola kata sandi akun <strong><?= htmlspecialchars($_SESSION['user']['username']) ?></strong> secara berkala.</p>
      </div>
    </div>

    <!-- GRID LAYOUT -->
    <div class="settings-grid">
      
      <!-- KIRI: FORM GANTI SANDI -->
      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-shield-lock"></i> Formulir Kata Sandi Baru
        </div>

        <form method="post">
          <div class="form-group">
            <label class="form-label">Kata Sandi Saat Ini <span class="required">*</span></label>
            <input type="password" name="sandi_lama" class="form-control" required placeholder="Masukkan kata sandi lama Anda">
          </div>

          <div class="form-group">
            <label class="form-label">Kata Sandi Baru <span class="required">*</span></label>
            <input type="password" name="sandi_baru" class="form-control" required placeholder="Minimal 6 karakter">
          </div>

          <div class="form-group" style="margin-bottom: 28px;">
            <label class="form-label">Konfirmasi Kata Sandi Baru <span class="required">*</span></label>
            <input type="password" name="sandi_confirm" class="form-control" required placeholder="Ketik ulang kata sandi baru">
          </div>

          <div style="display: flex; justify-content: flex-end;">
            <button type="submit" name="change_pw" class="btn-primary">
              <i class="bi bi-check2-all"></i> Simpan Perubahan
            </button>
          </div>
        </form>
      </div>

      <!-- KANAN: PETUNJUK KEAMANAN -->
      <div class="form-card" style="background: var(--surface2); border-color: var(--border);">
        <div class="form-section-header" style="border-bottom-color: var(--border);">
          <i class="bi bi-lightbulb" style="color: #059669;"></i> Petunjuk Keamanan
        </div>
        <p style="font-size: 0.85rem; color: var(--text); margin: 0;">Untuk menjaga keamanan data inventory perusahaan, pastikan sandi Anda memenuhi kriteria berikut:</p>
        <ul class="tips-list">
          <li>Gunakan kombinasi <strong>huruf dan angka</strong> yang tidak mudah ditebak.</li>
          <li>Hindari menggunakan nama depan, tanggal lahir, atau nama perusahaan.</li>
          <li>Jangan pernah mencatat kata sandi di kertas atau menempelkannya di monitor Anda.</li>
          <li>Ganti kata sandi Anda secara berkala minimal <strong>3 bulan sekali</strong>.</li>
        </ul>
      </div>

    </div>

  </div>
</main>

<script>
  // Script untuk Dark Mode Toggle
  const htmlEl = document.documentElement;
  const themeBtn = document.getElementById('themeToggle');
  themeBtn?.addEventListener('click', () => {
    const isDark = htmlEl.getAttribute('data-theme') === 'dark';
    htmlEl.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    themeBtn.querySelector('i').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
  });

  // Script untuk Sidebar Mobile
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  document.getElementById('menuBtn')?.addEventListener('click', () => {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
  });
</script>
</body>
</html>