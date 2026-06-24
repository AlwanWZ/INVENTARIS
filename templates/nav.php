<?php 
$role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : null;
?>

<aside class="nav" id="sidebar">

  <div class="nav-header">
    <a href="#" class="brand">
      <img src="/Inventaris/public/assets/img/celebit-logo.png" alt="CELEBIT" style="background:#fff;height:80px;width:auto;border-radius:12px;padding:6px;box-shadow:0 2px 8px rgba(0,0,0,0.07);">
    </a>
    <button class="nav-close" id="sidebarClose" aria-label="Tutup">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="nav-menu">

    <a href="/Inventaris/public/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
    </a>

    <?php if ($role === 'marketing'): ?>
    <div class="nav-group">
      <span class="nav-title">Menu Marketing</span>
      <a href="/Inventaris/public/marketing/produk/index.php" class="nav-item"><i class="bi bi-box"></i><span>Barang</span></a>
      <a href="/Inventaris/public/marketing/customer/index.php" class="nav-item"><i class="bi bi-people"></i><span>Customer</span></a>
      <a href="/Inventaris/public/marketing/po/index.php" class="nav-item"><i class="bi bi-file-earmark-text"></i><span>Pesanan</span></a>
      <a href="/Inventaris/public/marketing/laporan_order/index.php" class="nav-item"><i class="bi bi-journal-text"></i><span>Laporan Pesanan</span></a>
      <a href="/Inventaris/public/marketing/user/index.php" class="nav-item"><i class="bi bi-person-lines-fill"></i><span>User</span></a>
      <a href="/Inventaris/public/marketing/spk/index.php" class="nav-item"><i class="bi bi-file-earmark-check"></i><span>SPK</span></a>
    </div>
    <?php endif; ?>

    <?php if ($role === 'gudang'): ?>
    <div class="nav-group">
      <span class="nav-title">Menu Gudang</span>
      <a href="/Inventaris/public/gudang/verif/finish-good/index.php" class="nav-item"><i class="bi bi-check-circle"></i><span>Finish Good</span></a>
      <a href="/Inventaris/public/gudang/pengeluaran/index.php" class="nav-item"><i class="bi bi-box-arrow-up"></i><span>Pengeluaran Barang</span></a>
      <a href="/Inventaris/public/gudang/laporan_persediaan/index.php" class="nav-item"><i class="bi bi-clipboard-data"></i><span>Laporan Persediaan</span></a>
    </div>
    <?php endif; ?>

    <?php if ($role === 'manager'): ?>
    <div class="nav-group">
      <span class="nav-title">Menu Manager</span>
      <a href="/Inventaris/public/marketing/po/index.php" class="nav-item"><i class="bi bi-file-earmark-text"></i><span>Pesanan</span></a>
      <a href="/Inventaris/public/gudang/laporan_persediaan/index.php" class="nav-item"><i class="bi bi-clipboard-data"></i><span>Laporan Persediaan</span></a>
      <a href="/Inventaris/public/marketing/laporan_order/index.php" class="nav-item"><i class="bi bi-journal-text"></i><span>Laporan Pesanan</span></a>
    </div>
    <?php endif; ?>

    <!-- 🚀 MENU UNIVERSAL (SEMUA ROLE BISA MENGAKSES INI) -->
    <div class="nav-group" style="margin-top: 25px; padding-top: 12px; border-top: 1px solid var(--border);">
      <span class="nav-title">Akun Saya</span>
      <a href="/Inventaris/public/ganti_sandi.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'ganti_sandi.php' ? 'active' : '' ?>">
        <i class="bi bi-shield-lock"></i><span>Ganti Sandi</span>
      </a>
    </div>

  </div>

  <div class="nav-footer">
    <form method="post" action="/Inventaris/public/logout.php">
      <button type="submit" class="logout-btn">
        <i class="bi bi-box-arrow-left"></i> Logout
      </button>
    </form>
  </div>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
  /* Responsive sidebar for mobile */
  @media (max-width: 768px) {
    .nav {
      width: 80vw;
      min-width: 0;
      max-width: 320px;
      left: -100vw;
      transition: left 0.3s;
      border-radius: 0 16px 16px 0;
      box-shadow: 0 2px 16px rgba(0,0,0,0.12);
      padding: 12px 0;
    }
    .nav.open {
      left: 0;
      z-index: 100;
      position: fixed;
      top: 0;
      height: 100vh;
    }
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.15);
      z-index: 99;
    }
    .sidebar-overlay.show {
      display: block;
    }
    .nav-header .brand img {
      height: 48px;
      border-radius: 8px;
      padding: 2px;
    }
    .nav-header {
      padding: 8px 16px;
    }
    .nav-menu {
      font-size: 15px;
      padding: 0 8px;
    }
    .nav-footer {
      padding: 8px 16px;
    }
    .nav-close {
      font-size: 22px;
      padding: 4px;
    }
  }
  @media (max-width: 480px) {
    .nav {
      width: 96vw;
      max-width: 98vw;
    }
    .nav-header .brand img {
      height: 36px;
    }
    .nav-menu {
      font-size: 13px;
    }
  }
</style>

<script>
  const html      = document.documentElement;
  const themeBtn  = document.getElementById('themeToggle');
  const themeIcon = themeBtn?.querySelector('i');
  function applyTheme(dark) {
    html.setAttribute('data-theme', dark ? 'dark' : 'light');
    if (themeIcon) themeIcon.className = dark ? 'bi bi-sun' : 'bi bi-moon';
    localStorage.setItem('theme', dark ? 'dark' : 'light');
  }
  applyTheme(localStorage.getItem('theme') === 'dark');
  themeBtn?.addEventListener('click', () => applyTheme(html.getAttribute('data-theme') !== 'dark'));
 
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  
  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
  }
  
  document.querySelectorAll('.menu-btn').forEach(btn => {
    btn.removeEventListener('click', openSidebar);
    btn.addEventListener('click', openSidebar);
  });
  
  const observer = new MutationObserver(() => {
    document.querySelectorAll('.menu-btn').forEach(btn => {
      if (!btn.hasAttribute('data-burger-init')) {
        btn.addEventListener('click', openSidebar);
        btn.setAttribute('data-burger-init', 'true');
      }
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
  
  const closeBtn = document.getElementById('sidebarClose');
  const closeNav = () => { sidebar?.classList.remove('open'); overlay?.classList.remove('show'); };
  closeBtn?.addEventListener('click', closeNav);
  overlay?.addEventListener('click', closeNav);
 
  const currentUrl = window.location.href.split('?')[0]; 

  document.querySelectorAll('.nav-menu a').forEach(a => {
    const linkUrl = a.href.split('?')[0];
    if (currentUrl === linkUrl || currentUrl.startsWith(linkUrl.replace('/index.php', ''))) {
      a.classList.add('active');
    }
  });
</script>