<?php
require_once __DIR__ . '/../src/auth.php';
$error = '';
$show_reset_form = false;
$reset_user_id = null;
$reset_username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process update password
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password && $confirm_password) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) < 6) {
                    $error = 'Password minimal 6 karakter.';
                    $show_reset_form = true;
                    $reset_user_id = $user_id;
                } else {
                    if (update_password($user_id, $new_password)) {
                        $error = '';
                        $_SESSION['user'] = null;
                        echo '<script>alert("Password berhasil diperbarui. Silakan login kembali."); window.location.href = "/Inventaris/public/login.php";</script>';
                        exit;
                    } else {
                        $error = 'Gagal mengupdate password.';
                        $show_reset_form = true;
                        $reset_user_id = $user_id;
                    }
                }
            } else {
                $error = 'Password dan konfirmasi password tidak cocok.';
                $show_reset_form = true;
                $reset_user_id = $user_id;
            }
        } else {
            $error = 'Password dan konfirmasi password wajib diisi.';
            $show_reset_form = true;
            $reset_user_id = $user_id;
        }
    } else {
        // Process login
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username && $password) {
            if (login($username, $password)) {
                // Cek apakah username sama dengan password (default password)
                if ($username === $password) {
                    $show_reset_form = true;
                    $reset_user_id = $_SESSION['user']['id'];
                    $reset_username = $_SESSION['user']['username'];
                    $error = 'Untuk keamanan, Anda harus membuat password baru.';
                } else {
                    $role = $_SESSION['user']['role'];
                    if ($role === 'marketing') {
                        header('Location: /Inventaris/public/dashboard.php'); exit;
                    } elseif ($role === 'gudang') {
                        header('Location: /Inventaris/public/dashboard.php'); exit;
                    } elseif ($role === 'manager') {
                        header('Location: /Inventaris/public/dashboard.php'); exit;
                    } else {
                        $error = 'Role tidak valid.';
                    }
                }
            } else {
                $error = 'Username atau password salah.';
            }
        } else {
            $error = 'Username dan password wajib diisi.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@600;700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:      #0f172a;
      --surface: rgba(255, 255, 255, 0.85);
      --surface-dark: rgba(15, 23, 42, 0.75);
      --border:  rgba(255, 255, 255, 0.2);
      --border2: rgba(0, 0, 0, 0.15);
      --accent:  #e8621a;
      --accent-glow: rgba(232, 98, 26, 0.35);
      --accent2: #f97316;
      --text:    #ffffff;
      --text-dark: #1e293b;
      --text2:   #475569;
      --text3:   #94a3b8;
      --error-bg:   rgba(239, 68, 68, 0.15);
      --error-bd:   rgba(239, 68, 68, 0.3);
      --error-text: #f87171;
      --radius:  14px;
      --shadow:  0 20px 50px rgba(0, 0, 0, 0.5);
      --trans:   0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: 'Roboto', sans-serif;
      background-color: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      -webkit-font-smoothing: antialiased;
      position: relative;
      overflow: hidden;
    }

    /* --- SPECIAL BACKGROUND EFFECT --- */
    .bg-image {
      position: absolute;
      top: -5%; left: -5%;
      width: 110%; height: 110%;
      background-image: url('assets/img/gudang.jpeg'); /* Pastikan path img benar */
      background-size: cover;
      background-position: center;
      z-index: 1;
      animation: kenBurns 25s infinite alternate ease-in-out;
      filter: contrast(1.05) saturate(1.1);
    }

    /* Dark Modern Gradient Overlay dengan sentuhan industrial tone */
    .bg-overlay {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: linear-gradient(135deg, rgba(15, 23, 42, 0.88) 0%, rgba(30, 41, 59, 0.75) 50%, rgba(232, 98, 26, 0.45) 100%);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      z-index: 2;
    }

    @keyframes kenBurns {
      0% { transform: scale(1) rotate(0deg); }
      100% { transform: scale(1.08) rotate(0.5deg); }
    }

    .login-wrap {
      width: 100%;
      max-width: 420px;
      z-index: 10;
      position: relative;
    }

    /* Back link berbahan Kaca / Glass pill */
    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      margin-bottom: 20px;
      padding: 8px 16px;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 50px;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      transition: all var(--trans);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-back:hover { 
      color: #ffffff; 
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-2px);
    }
    .btn-back svg { flex-shrink: 0; }

    /* Card ala Glassmorphism Mewah */
    .login-card {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 24px;
      border: 1px solid rgba(255, 255, 255, 0.6);
      box-shadow: var(--shadow), 0 0 0 1px rgba(232, 98, 26, 0.2);
      padding: 42px 38px 36px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      position: relative;
      overflow: hidden;
    }

    /* Aksen garis menyala di atas kartu */
    .login-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, #e8621a, #f97316, #fbbf24);
    }

    /* Header */
    .login-header { text-align: center; margin-bottom: 30px; }
    .login-logo {
      width: 60px; height: 60px;
      background: rgba(232, 98, 26, 0.08);
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
      color: var(--accent);
      border: 1px solid rgba(232, 98, 26, 0.15);
      box-shadow: 0 8px 20px rgba(232, 98, 26, 0.12);
    }
    .login-brand {
      font-family: 'Inter', sans-serif;
      font-size: 1.55rem;
      font-weight: 800;
      color: var(--text-dark);
      letter-spacing: -0.02em;
      margin-bottom: 6px;
    }
    .login-brand span { color: var(--accent); }
    .login-sub {
      font-size: 0.9rem;
      color: var(--text2);
      font-weight: 400;
    }

    /* Error */
    .error-msg {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--error-bg);
      border: 1px solid var(--error-bd);
      color: var(--error-text);
      border-radius: var(--radius);
      padding: 12px 16px;
      font-size: 0.88rem;
      font-weight: 500;
      margin-bottom: 22px;
      animation: shake 0.4s ease-in-out;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-5px); }
      40%, 80% { transform: translateX(5px); }
    }

    /* Form */
    .form-group { margin-bottom: 20px; }
    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 8px;
    }
    .form-control {
      width: 100%;
      padding: 13px 16px;
      font-family: 'Roboto', sans-serif;
      font-size: 0.95rem;
      color: var(--text-dark);
      background: #ffffff;
      border: 1.5px solid #cbd5e1;
      border-radius: var(--radius);
      outline: none;
      transition: all var(--trans);
      box-shadow: 0 2px 4px rgba(0,0,0,0.02) inset;
    }
    .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 4px var(--accent-glow);
      background: #fff;
    }
    .form-control::placeholder { color: #94a3b8; }

    /* Password field */
    .input-wrap { position: relative; }
    .input-wrap .form-control { padding-right: 44px; }
    .toggle-pwd {
      position: absolute;
      right: 14px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer;
      color: #64748b;
      display: flex; align-items: center;
      padding: 4px;
      transition: color var(--trans);
    }
    .toggle-pwd:hover { color: var(--accent); }

    /* Submit Button dengan efek Glow */
    .btn-submit {
      width: 100%;
      padding: 14px;
      margin-top: 8px;
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      font-family: 'Inter', sans-serif;
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      transition: all var(--trans);
      box-shadow: 0 8px 20px rgba(232, 98, 26, 0.3);
      position: relative;
      overflow: hidden;
    }
    .btn-submit:hover { 
      transform: translateY(-2px);
      box-shadow: 0 12px 25px rgba(232, 98, 26, 0.45);
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    }
    .btn-submit:active {
      transform: translateY(0);
    }

    /* Footer note */
    .login-note {
      text-align: center;
      font-size: 0.82rem;
      color: var(--text2);
      margin-top: 24px;
      font-weight: 500;
    }
  </style>
</head>
<body>
  
  <!-- BACKGROUND IMAGE DENGAN ANIMASI ZOOM & OVERLAY -->
  <div class="bg-image"></div>
  <div class="bg-overlay"></div>

  <div class="login-wrap">

    <a href="/Inventaris/public/index.php" class="btn-back">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Kembali ke Beranda
    </a>

    <div class="login-card">
      <div class="login-header">
        <div class="login-logo" style="background:transparent;box-shadow:none;border:none;">
          <!-- Pastikan path logo Anda sesuai -->
          <img src="assets/img/celebit-logo.png" alt="CELEBIT" style="height:54px;width:auto;display:block;margin:0 auto;filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
        </div>
        <div class="login-brand"><span>⬡</span> Inventory</div>
        <div class="login-sub">Masuk untuk mengakses dashboard Anda</div>
      </div>

      <?php if ($error): ?>
        <div class="error-msg">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php if ($show_reset_form): ?>
        <!-- Form Update Password -->
        <form method="post" autocomplete="off">
          <input type="hidden" name="action" value="update_password">
          <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($reset_user_id); ?>">
          
          <div style="background:rgba(59,130,246,0.10); border:1px solid rgba(59,130,246,0.25); border-radius:14px; padding:14px; margin-bottom:22px; font-size:0.9rem; color:#1e40af; line-height: 1.4;">
            <strong>🔒 Buat Password Baru</strong><br>
            Akun <strong><?php echo htmlspecialchars($reset_username); ?></strong> memerlukan password baru untuk keamanan.
          </div>

          <div class="form-group">
            <label for="new_password" class="form-label">Password Baru</label>
            <div class="input-wrap">
              <input type="password" class="form-control" id="new_password" name="new_password"
                     placeholder="Masukkan password baru (min. 6 karakter)" required autofocus>
              <button type="button" class="toggle-pwd" id="toggleNewPwd" aria-label="Tampilkan password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm_password" class="form-label">Konfirmasi Password</label>
            <div class="input-wrap">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                     placeholder="Ulangi password baru" required>
              <button type="button" class="toggle-pwd" id="toggleConfirmPwd" aria-label="Tampilkan password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit">Perbarui Password</button>
        </form>
      <?php else: ?>
        <!-- Form Login -->
        <form method="post" autocomplete="off">
          <div class="form-group">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username"
                   placeholder="Masukkan username Anda..." required autofocus
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <div class="input-wrap">
              <input type="password" class="form-control" id="password" name="password"
                     placeholder="Masukkan password Anda..." required>
              <button type="button" class="toggle-pwd" id="togglePwd" aria-label="Tampilkan password">
                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-submit">Masuk ke Sistem</button>
        </form>

        <p class="login-note">Hubungi tim administrator atau marketing jika lupa password.</p>
      <?php endif; ?>
    </div>

  </div>
  <script>
    const eyeOpen   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    const eyeClose  = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    
    // Toggle password login
    const toggleBtn = document.getElementById('togglePwd');
    if (toggleBtn) {
      const pwdInput  = document.getElementById('password');
      const eyeIcon   = document.getElementById('eyeIcon');
      toggleBtn.addEventListener('click', () => {
        const show = pwdInput.type === 'password';
        pwdInput.type = show ? 'text' : 'password';
        eyeIcon.innerHTML = show ? eyeClose : eyeOpen;
      });
    }
    
    // Toggle password baru (reset form)
    const toggleNewPwd = document.getElementById('toggleNewPwd');
    if (toggleNewPwd) {
      const newPwdInput = document.getElementById('new_password');
      const iconNewPwd = toggleNewPwd.querySelector('svg');
      toggleNewPwd.addEventListener('click', () => {
        const show = newPwdInput.type === 'password';
        newPwdInput.type = show ? 'text' : 'password';
        iconNewPwd.innerHTML = show ? eyeClose : eyeOpen;
      });
    }
    
    // Toggle konfirmasi password (reset form)
    const toggleConfirmPwd = document.getElementById('toggleConfirmPwd');
    if (toggleConfirmPwd) {
      const confirmPwdInput = document.getElementById('confirm_password');
      const iconConfirmPwd = toggleConfirmPwd.querySelector('svg');
      toggleConfirmPwd.addEventListener('click', () => {
        const show = confirmPwdInput.type === 'password';
        confirmPwdInput.type = show ? 'text' : 'password';
        iconConfirmPwd.innerHTML = show ? eyeClose : eyeOpen;
      });
    }
  </script>
</body>
</html>
