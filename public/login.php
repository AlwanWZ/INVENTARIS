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
      --bg:      #f5f4f1;
      --surface: #ffffff;
      --border:  rgba(0,0,0,0.10);
      --border2: rgba(0,0,0,0.16);
      --accent:  #e8621a;
      --accent2: #f97316;
      --text:    #1a1714;
      --text2:   #4b4843;
      --text3:   #888580;
      --error-bg:   rgba(220,38,38,0.08);
      --error-bd:   rgba(220,38,38,0.22);
      --error-text: #b91c1c;
      --radius:  13px;
      --shadow:  0 4px 24px rgba(0,0,0,0.09);
      --trans:   0.16s ease;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Roboto', sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      -webkit-font-smoothing: antialiased;
    }
    .login-wrap {
      width: 100%;
      max-width: 400px;
    }

    /* Back link */
    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text3);
      text-decoration: none;
      margin-bottom: 24px;
      transition: color var(--trans);
    }
    .btn-back:hover { color: var(--text); }
    .btn-back svg { flex-shrink: 0; }

    /* Card */
    .login-card {
      background: var(--surface);
      border-radius: 20px;
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      padding: 40px 36px 36px;
    }

    /* Header */
    .login-header { text-align: center; margin-bottom: 28px; }
    .login-logo {
      width: 48px; height: 48px;
      background: rgba(232,98,26,0.10);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px;
      color: var(--accent);
    }
    .login-brand {
      font-family: 'Inter', sans-serif;
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -0.01em;
      margin-bottom: 4px;
    }
    .login-brand span { color: var(--accent); }
    .login-sub {
      font-size: 0.88rem;
      color: var(--text3);
    }

    /* Error */
    .error-msg {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--error-bg);
      border: 1px solid var(--error-bd);
      color: var(--error-text);
      border-radius: var(--radius);
      padding: 10px 14px;
      font-size: 0.88rem;
      margin-bottom: 20px;
    }

    /* Form */
    .form-group { margin-bottom: 18px; }
    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text2);
      margin-bottom: 7px;
    }
    .form-control {
      width: 100%;
      padding: 11px 14px;
      font-family: 'Roboto', sans-serif;
      font-size: 0.93rem;
      color: var(--text);
      background: var(--bg);
      border: 1px solid var(--border2);
      border-radius: var(--radius);
      outline: none;
      transition: border-color var(--trans), box-shadow var(--trans);
    }
    .form-control:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(232,98,26,0.12);
      background: #fff;
    }
    .form-control::placeholder { color: var(--text3); }

    /* Password field */
    .input-wrap { position: relative; }
    .input-wrap .form-control { padding-right: 42px; }
    .toggle-pwd {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer;
      color: var(--text3);
      display: flex; align-items: center;
      padding: 2px;
      transition: color var(--trans);
    }
    .toggle-pwd:hover { color: var(--text2); }

    /* Submit */
    .btn-submit {
      width: 100%;
      padding: 12px;
      margin-top: 6px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      font-family: 'Inter', sans-serif;
      font-weight: 700;
      font-size: 0.97rem;
      cursor: pointer;
      transition: background var(--trans);
    }
    .btn-submit:hover { background: var(--accent2); }

    /* Footer note */
    .login-note {
      text-align: center;
      font-size: 0.8rem;
      color: var(--text3);
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="login-wrap">

    <a href="/Inventaris/public/index.php" class="btn-back">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Kembali ke Beranda
    </a>

    <div class="login-card">
      <div class="login-header">
        <div class="login-logo" style="background:transparent;box-shadow:none;">
          <img src="assets/img/celebit-logo.png" alt="CELEBIT" style="height:48px;width:auto;display:block;margin:0 auto;">
        </div>
        <div class="login-brand"><span>⬡</span> Inventory</div>
        <div class="login-sub">Masuk untuk mengakses dashboard Anda</div>
      </div>

      <?php if ($error): ?>
        <div class="error-msg">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php if ($show_reset_form): ?>
        <!-- Form Update Password -->
        <form method="post" autocomplete="off">
          <input type="hidden" name="action" value="update_password">
          <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($reset_user_id); ?>">
          
          <div style="background:rgba(59,130,246,0.10); border:1px solid rgba(59,130,246,0.22); border-radius:13px; padding:12px; margin-bottom:20px; font-size:0.9rem; color:var(--text2);">
            <strong>Buat Password Baru</strong><br>
            Akun <strong><?php echo htmlspecialchars($reset_username); ?></strong> memerlukan password baru untuk keamanan.
          </div>

          <div class="form-group">
            <label for="new_password" class="form-label">Password Baru</label>
            <div class="input-wrap">
              <input type="password" class="form-control" id="new_password" name="new_password"
                     placeholder="Masukkan password baru (min. 6 karakter)" required autofocus>
              <button type="button" class="toggle-pwd" id="toggleNewPwd" aria-label="Tampilkan password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm_password" class="form-label">Konfirmasi Password</label>
            <div class="input-wrap">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                     placeholder="Ulangi password baru" required>
              <button type="button" class="toggle-pwd" id="toggleConfirmPwd" aria-label="Tampilkan password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
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
                   placeholder="Masukkan username" required autofocus
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <div class="input-wrap">
              <input type="password" class="form-control" id="password" name="password"
                     placeholder="Masukkan password" required>
              <button type="button" class="toggle-pwd" id="togglePwd" aria-label="Tampilkan password">
                <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-submit">Masuk</button>
        </form>

        <p class="login-note">Hubungi team marketing jika lupa password.</p>
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
