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

    <div class="nav-group">
      <span class="nav-title">Marketing</span>
      <button class="dropdown-btn">
        <span class="dd-left"><i class="bi bi-briefcase"></i> Menu Marketing</span>
        <i class="bi bi-chevron-right arrow"></i>
      </button>
      <div class="dropdown">
        <a href="/Inventaris/public/marketing/produk/index.php"><i class="bi bi-box"></i> Produk</a>
        <a href="/Inventaris/public/marketing/customer/index.php"><i class="bi bi-people"></i> Customer</a>
        <a href="/Inventaris/public/marketing/po/index.php"><i class="bi bi-file-earmark-text"></i>Pesanan PCB</a>
        <a href="/Inventaris/public/marketing/laporan_order/index.php"><i class="bi bi-journal-text"></i> Laporan Pesanan PCB</a>
        <a href="/Inventaris/public/marketing/user/index.php"><i class="bi bi-person-lines-fill"></i> Users</a>
        <a href="/Inventaris/public/marketing/spk/index.php"><i class="bi bi-file-earmark-check"></i> SPK</a>
      </div>
    </div>

    <div class="nav-group">
      <span class="nav-title">Gudang</span>
      <button class="dropdown-btn">
        <span class="dd-left"><i class="bi bi-box-seam"></i> Menu Warehouse</span>
        <i class="bi bi-chevron-right arrow"></i>
      </button>
      <div class="dropdown">
        <a href="/Inventaris/public/gudang/penerimaan/index.php"><i class="bi bi-box-arrow-in-down"></i> Penerimaan Barang</a>
        <a href="/Inventaris/public/gudang/verif/finish-good/index.php"><i class="bi bi-check-circle"></i> Finish Good</a>
        <a href="/Inventaris/public/gudang/stok/index.php"><i class="bi bi-archive"></i> Stok Barang</a>
        <a href="/Inventaris/public/gudang/pengeluaran/index.php"><i class="bi bi-box-arrow-up"></i> Pengeluaran Barang</a>
        <a href="/Inventaris/public/gudang/surat_jln/index.php"><i class="bi bi-truck"></i> Surat Jalan</a>
        <a href="/Inventaris/public/gudang/laporan_persediaan/index.php"><i class="bi bi-clipboard-data"></i> Laporan Persediaan</a>
      </div>
    </div>

    <div class="nav-group">
      <span class="nav-title">Manager</span>
      <button class="dropdown-btn">
        <span class="dd-left"><i class="bi bi-bar-chart"></i> Menu Manager</span>
        <i class="bi bi-chevron-right arrow"></i>
      </button>
      <div class="dropdown">
        <a href="/Inventaris/public/marketing/po/index.php"><i class="bi bi-file-earmark-text"></i> Order dari Customer</a>
        <a href="/Inventaris/public/gudang/laporan_persediaan/index.php"><i class="bi bi-clipboard-data"></i> Laporan Persediaan</a>
        <a href="/Inventaris/public/marketing/laporan_order/index.php"><i class="bi bi-journal-text"></i> Laporan Order</a>
      </div>
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
    .nav-group .dropdown {
      font-size: 14px;
    }
    .nav-group .dropdown a {
      padding: 8px 12px;
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
    .nav-group .dropdown a {
      font-size: 13px;
      padding: 6px 8px;
    }
  }
</style>
<script>
  // ── Theme ─────────────────────────────────────────────────
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
 
  // ── Sidebar mobile ────────────────────────────────────────
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  // Burger menu: support dynamically loaded buttons
  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
  }
  // Attach event for existing menu-btn
  document.querySelectorAll('.menu-btn').forEach(btn => {
    btn.removeEventListener('click', openSidebar); // Remove old if any
    btn.addEventListener('click', openSidebar);
  });
  // Support dynamically added menu-btn
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
 
  // ── Dropdown ─────────────────────────────────────────────
  document.querySelectorAll('.dropdown-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      // Untuk nested dropdown (Verifikasi Barang), cegah bubbling ke parent
      if (btn.classList.contains('verif-btn')) e.stopPropagation();
      const dd   = btn.nextElementSibling;
      const open = dd?.classList.contains('open');
      // Jika nested, hanya toggle dropdown di level ini
      if (btn.classList.contains('verif-btn')) {
        dd?.classList.toggle('open');
        btn.classList.toggle('open');
        return;
      }
      // Untuk dropdown utama, tutup semua kecuali yang diklik
      // HANYA tutup dropdown di level utama (nav-group > .dropdown), jangan nested
      const parentNavGroup = btn.closest('.nav-group');
      parentNavGroup?.querySelectorAll(':scope > .dropdown').forEach(d => d.classList.remove('open'));
      parentNavGroup?.querySelectorAll(':scope > .dropdown-btn').forEach(b => b.classList.remove('open'));
      if (!open) { dd?.classList.add('open'); btn.classList.add('open'); }
    });
  });
 
  // ── Auto-open dropdown & highlight active link ─────────────────────
  // Ambil URL sekarang, buang query parameter (kayak ?id=1) biar menu tetep aktif pas di halaman detail/edit
  const currentUrl = window.location.href.split('?')[0]; 

  let dropdownOpened = false;
  document.querySelectorAll('.dropdown a').forEach(a => {
    if (dropdownOpened) return; // Jika sudah ada yang dibuka, skip sisanya
    const linkUrl = a.href.split('?')[0];
    if (currentUrl === linkUrl || currentUrl.startsWith(linkUrl.replace('/index.php', ''))) {
      a.classList.add('active');

      // Temukan nav-group terdekat (Marketing, Gudang, Manager)
      const navGroup = a.closest('.nav-group');
      // Tutup semua dropdown utama dulu
      document.querySelectorAll('.nav-group > .dropdown').forEach(d => d.classList.remove('open'));
      document.querySelectorAll('.nav-group > .dropdown-btn').forEach(b => b.classList.remove('open'));

      // Buka hanya dropdown di grup yang sesuai
      if (navGroup) {
        let parentDropdown = a.closest('.dropdown');
        while (parentDropdown && navGroup.contains(parentDropdown)) {
          parentDropdown.classList.add('open');
          const parentBtn = parentDropdown.previousElementSibling;
          if (parentBtn && parentBtn.classList.contains('dropdown-btn')) {
            parentBtn.classList.add('open');
          }
          parentDropdown = parentDropdown.parentElement.closest('.dropdown');
        }
        dropdownOpened = true; // Set flag agar tidak buka di grup lain
      }
    }
  });
</script>