<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>InventorySys — Sistem Inventaris Perusahaan</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link href="assets/css/landing.css" rel="stylesheet">
</head>
<body>

  <nav class="navbar" id="navbar">
    <div class="container nav-inner">
      <a href="#" class="brand">
        <img src="assets/img/logo.svg" alt="Logo" style="height:32px;width:32px;margin-right:8px;vertical-align:middle;">
        <span class="brand-icon" style="display:none;">⬡</span> Inventory
      </a>
      <div class="nav-links">
        <a href="#features" class="nav-link">Fitur</a>
        <a href="#how" class="nav-link">Cara Kerja</a>
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
          <svg class="icon-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <svg class="icon-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="login.php" class="btn-nav">Masuk</a>
      </div>
    </div>
  </nav>

  <section class="hero">
    <div class="container hero-inner">
      <div class="hero-text">
        <div class="hero-badge"><span class="badge-dot"></span> Sistem Aktif &amp; Siap Digunakan</div>
        <h1 class="hero-title">Inventaris Lebih Cerdas,<br><em>Operasional Lebih Lancar</em></h1>
        <p class="hero-desc">Kelola PO, SPK, stok gudang, dan distribusi dalam satu sistem terintegrasi untuk efisiensi nyata setiap divisi.</p>
        <div class="hero-actions">
          <a href="login.php" class="btn-primary">Mulai Sekarang <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
          <a href="#features" class="btn-ghost">Lihat Fitur</a>
        </div>
        <div class="hero-stats">
          <div class="stat"><span class="stat-num">12+</span><span class="stat-label">Modul</span></div>
          <div class="stat-div"></div>
          <div class="stat"><span class="stat-num">99%</span><span class="stat-label">Uptime</span></div>
          <div class="stat-div"></div>
          <div class="stat"><span class="stat-num">Real-time</span><span class="stat-label">Update Stok</span></div>
        </div>
      </div>
      <div class="hero-visual">
        <div class="mockup">
          <div class="mockup-bar"><span class="dot r"></span><span class="dot y"></span><span class="dot g"></span><span class="mockup-label">Dashboard Inventaris</span></div>
          <div class="kpi-row">
            <div class="kpi"><span class="kpi-l">PO Aktif</span><span class="kpi-v">48</span><span class="kpi-t up">▲ 12%</span></div>
            <div class="kpi"><span class="kpi-l">Stok Menipis</span><span class="kpi-v warn">7</span><span class="kpi-t warn">⚠ Reorder</span></div>
            <div class="kpi"><span class="kpi-l">Pengiriman</span><span class="kpi-v">23</span><span class="kpi-t up">▲ On time</span></div>
          </div>
          <div class="chart-wrap">
            <div class="chart-ttl">Transaksi 7 Hari Terakhir</div>
            <div class="bars"><div class="b" style="height:45%"><s>Sen</s></div><div class="b" style="height:62%"><s>Sel</s></div><div class="b" style="height:38%"><s>Rab</s></div><div class="b hi" style="height:80%"><s>Kam</s></div><div class="b" style="height:70%"><s>Jum</s></div><div class="b" style="height:55%"><s>Sab</s></div><div class="b" style="height:30%"><s>Min</s></div></div>
          </div>
          <div class="tbl"><div class="tr hd"><span>No. PO</span><span>Status</span><span>Total</span></div><div class="tr"><span>PO-2024-081</span><span class="st ok">Disetujui</span><span>Rp 14.2jt</span></div><div class="tr"><span>PO-2024-082</span><span class="st pnd">Proses</span><span>Rp 8.7jt</span></div><div class="tr"><span>PO-2024-083</span><span class="st ok">Disetujui</span><span>Rp 21.0jt</span></div></div>
        </div>
      </div>
    </div>
  </section>




  <!-- Company Info Section with Logo and Description -->
  <div class="company-info enhanced-company-info">
    <div class="container company-info-inner" style="justify-content: center;">
      <img src="assets/img/celebit-logo.png" alt="Celebit Logo" style="width: 500px; height: 150px; display: block; margin: 0 auto; background: transparent; box-shadow: none; border: none; border-radius: 0;">
    </div>
  </div>

  <div class="strip">
    <div class="container divisi-section">
      <span class="divisi-label">Untuk Divisi</span>
      <div class="divisi-card-row">
        <div class="divisi-card">Marketing</div>
        <div class="divisi-card">Warehouse</div>
        <div class="divisi-card">Manager</div>
      </div>
    </div>
  </div>

  <section class="features" id="features">
    <div class="container">
      <div class="sec-hd"><span class="sec-tag">Fitur Unggulan</span><h2>Semua dalam Satu Platform</h2></div>
      <div class="feat-grid">
        <div class="feat large"><div class="fi orange"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div><h3>Manajemen PO &amp; SPK</h3><p>Buat, ajukan, dan pantau Purchase Order serta SPK dengan alur persetujuan digital bertahap dan riwayat revisi lengkap.</p></div>
        <div class="feat"><div class="fi blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div><h3>Kontrol Stok</h3><p>Pantau barang masuk dan keluar secara real-time dengan notifikasi otomatis saat stok mendekati batas minimum.</p></div>
        <div class="feat"><div class="fi green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div><h3>Laporan Terintegrasi</h3><p>Ekspor laporan ke PDF dan Excel. Data lengkap untuk mendukung evaluasi dan pengambilan keputusan.</p></div>
        <div class="feat"><div class="fi purple"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><h3>Audit Trail</h3><p>Setiap perubahan tercatat otomatis. Lacak aktivitas pengguna secara transparan dan akuntabel.</p></div>
        <div class="feat large"><div class="fi orange"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><h3>Manajemen Distribusi</h3><p>Kelola pengiriman ke berbagai lokasi dengan tracking langsung, bukti terima digital, dan konfirmasi penerimaan.</p></div>
      </div>
    </div>
  </section>

  <section class="how" id="how">
    <div class="container">
      <div class="sec-hd"><span class="sec-tag">Cara Kerja</span><h2>Tiga Langkah Menuju Operasional Optimal</h2></div>
      <div class="steps">
        <div class="step"><div class="step-n">01</div><h3>Login &amp; Konfigurasi</h3><p>Atur peran pengguna, data master barang, dan alur persetujuan sesuai struktur perusahaan.</p></div>
        <div class="step-arr">→</div>
        <div class="step"><div class="step-n">02</div><h3>Kelola Transaksi</h3><p>Buat PO, SPK, atau permintaan barang. Sistem otomatis mengarahkan ke pihak berwenang.</p></div>
        <div class="step-arr">→</div>
        <div class="step"><div class="step-n">03</div><h3>Monitor &amp; Analisis</h3><p>Pantau aktivitas gudang dari dashboard dan ekspor laporan untuk evaluasi.</p></div>
      </div>
    </div>
  </section>

  <section class="cta-sec"><div class="container"><div class="cta-box"><h2>Siap Mengoptimalkan Operasional?</h2><p>Sistem inventaris untuk efisiensi nyata — mulai hari ini.</p><a href="login.php" class="btn-primary large">Login ke Sistem <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a></div></div></section>

  <footer class="footer"><div class="container"><div class="foot-top"><div class="foot-brand-col"><div class="foot-brand"><span class="brand-icon">⬡</span> Inventory</div><p class="foot-desc">Sistem inventaris untuk operasional perusahaan yang lebih efisien dan terintegrasi.</p><div class="foot-social"><a href="#" aria-label="LinkedIn"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg></a><a href="#" aria-label="Email"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></a></div></div><div class="foot-col"><h6>Menu</h6><ul><li><a href="#">Beranda</a></li><li><a href="#features">Fitur</a></li><li><a href="#how">Cara Kerja</a></li><li><a href="login.php">Login</a></li></ul></div><div class="foot-col"><h6>Kontak</h6><p>PT. Celebit Circuit Technology Indonesia</p><p>info@company.com</p><p>+62 812 3456 7890</p></div></div><div class="foot-btm" style="text-align:center;"><p>© 2026 Celebit. All rights reserved.</p></div></div></footer>

  <script>
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => navbar.classList.toggle('scrolled', window.scrollY > 40), { passive: true });

    const btn = document.getElementById('themeToggle');
    const html = document.documentElement;
    const saved = localStorage.getItem('theme');
    if (saved === 'dark') html.setAttribute('data-theme', 'dark');
    btn.addEventListener('click', () => {
      const isDark = html.getAttribute('data-theme') === 'dark';
      html.setAttribute('data-theme', isDark ? 'light' : 'dark');
      localStorage.setItem('theme', isDark ? 'light' : 'dark');
    });

    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => { const t = document.querySelector(a.getAttribute('href')); if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); } });
    });
  </script>
</body>
</html>
