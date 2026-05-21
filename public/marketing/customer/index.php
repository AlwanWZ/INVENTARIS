<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'marketing') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../src/auth.php';
require_once '../../../src/models/Customer.php';

$search    = trim($_GET['search'] ?? '');
$customers = Customer::getAll($search);
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Customer | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/customer.css" rel="stylesheet">
</head>
<body>

<?php include '../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <span>Customer</span>
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

    <!-- NOTIFIKASI -->
    <?php if (isset($_GET['created'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> Customer berhasil ditambahkan.</div>
    <?php elseif (isset($_GET['updated'])): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> Customer berhasil diperbarui.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert-warn"><i class="bi bi-trash"></i> Customer berhasil dihapus.</div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Customer</h1>
        <p class="page-subtitle">Kelola data customer untuk kebutuhan PO dan transaksi.</p>
      </div>
      <a href="add.php" class="btn-primary"><i class="bi bi-plus-lg"></i> Tambah Customer</a>
    </div>

    <!-- CUSTOMER SELECTOR COMBOBOX -->
    <div class="customer-selector-card">
      <div class="selector-header">
        <h3 style="margin: 0; font-size: 0.95rem; color: var(--text); font-weight: 700;">Pilih Customer</h3>
      </div>
      <select id="customerComboBox" class="customer-combobox" onchange="handleCustomerSelect(this)">
        <option value="">-- Pilih Customer --</option>
        <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>" data-code="<?= htmlspecialchars($c['kode_customer']) ?>" data-name="<?= htmlspecialchars($c['nama']) ?>" data-company="<?= htmlspecialchars($c['perusahaan']) ?>" data-email="<?= htmlspecialchars($c['email']) ?>" data-phone="<?= htmlspecialchars($c['no_hp']) ?>" data-city="<?= htmlspecialchars($c['kota']) ?>" data-status="<?= $c['status'] ?>">
          <?= htmlspecialchars($c['nama']) ?> - <?= htmlspecialchars($c['perusahaan']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div id="customerDetail" class="customer-detail-box" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg2); border-radius: var(--radius); border: 1px solid var(--border);">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Kode</span>
            <p id="detailCode" style="margin: 4px 0 0; color: var(--text2);">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Email</span>
            <p id="detailEmail" style="margin: 4px 0 0; color: var(--text2);">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">No HP</span>
            <p id="detailPhone" style="margin: 4px 0 0; color: var(--text2);">-</p>
          </div>
          <div>
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Kota</span>
            <p id="detailCity" style="margin: 4px 0 0; color: var(--text2);">-</p>
          </div>
          <div style="grid-column: span 2;">
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Perusahaan</span>
            <p id="detailCompany" style="margin: 4px 0 0; color: var(--text2);">-</p>
          </div>
          <div style="grid-column: span 2;">
            <span style="font-size: 0.72rem; color: var(--text3); text-transform: uppercase; font-weight: 700;">Status</span>
            <p id="detailStatus" style="margin: 4px 0 0; color: var(--text2);"><span class="badge ok">Aktif</span></p>
          </div>
        </div>
      </div>
    </div>

    <!-- STAT ROW -->
    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-pill-label">Total Customer</span>
        <span class="stat-pill-val"><?= count($customers) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Aktif</span>
        <span class="stat-pill-val ok"><?= count(array_filter($customers, fn($c) => $c['status'] === 'aktif')) ?></span>
      </div>
      <div class="stat-pill">
        <span class="stat-pill-label">Nonaktif</span>
        <span class="stat-pill-val warn"><?= count(array_filter($customers, fn($c) => $c['status'] === 'nonaktif')) ?></span>
      </div>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card">
      <div class="table-header">
        <h4><i class="bi bi-people"></i> Daftar Customer</h4>
        <div class="table-actions">
          <form method="get" class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="search" class="search-input"
                   placeholder="Cari nama atau perusahaan..."
                   value="<?= htmlspecialchars($search) ?>">
          </form>
        </div>
      </div>

      <div class="table-wrap">
        <table id="customerTable">
          <thead>
            <tr>
              <th>No</th>
              <th>Kode</th>
              <th>Nama</th>
              <th>Perusahaan</th>
              <th>Email</th>
              <th>No HP</th>
              <th>Kota</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($customers)): ?>
            <tr>
              <td colspan="9" class="empty-state">
                <i class="bi bi-people"></i>
                <span><?= $search ? 'Tidak ada hasil untuk "' . htmlspecialchars($search) . '".' : 'Belum ada customer.' ?> <a href="add.php">Tambah sekarang</a></span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($customers as $i => $c):
              $sCls = $c['status'] === 'aktif' ? 'ok' : 'warn';
              $sLabel = $c['status'] === 'aktif' ? 'Aktif' : 'Nonaktif';
            ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td class="fw-mid"><?= htmlspecialchars($c['kode_customer']) ?></td>
              <td><?= htmlspecialchars($c['nama']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($c['perusahaan']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($c['email']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($c['no_hp']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($c['kota']) ?></td>
              <td><span class="badge <?= $sCls ?>"><?= $sLabel ?></span></td>
              <td>
                <div class="action-btns">
                  <!-- ROW 1: VIEW & EDIT -->
                  <div style="display: flex; gap: 0.5rem;">
                    <a href="detail.php?id=<?= $c['id'] ?>" class="btn-icon" title="Detail"><i class="bi bi-eye"></i></a>
                    <a href="edit.php?id=<?= $c['id'] ?>" class="btn-icon edit" title="Edit"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="delete.php" style="display: inline;">
                      <input type="hidden" name="id" value="<?= $c['id'] ?>">
                      <button type="submit" class="btn-icon delete" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus customer ini?');"><i class="bi bi-trash"></i></button>
                    </form>
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
        <span class="text-muted">Menampilkan <?= count($customers) ?> data<?= $search ? ' untuk "' . htmlspecialchars($search) . '"' : '' ?></span>
      </div>
    </div>

  </div>
</main>

<style>
  .customer-selector-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 22px;
    box-shadow: var(--shadow);
  }
  
  .selector-header {
    margin-bottom: 12px;
  }
  
  .customer-combobox {
    width: 100%;
    padding: 12px 14px;
    font-family: 'Roboto', sans-serif;
    font-size: 0.95rem;
    color: var(--text);
    background: var(--bg);
    border: 1px solid var(--border2);
    border-radius: var(--radius);
    outline: none;
    cursor: pointer;
    transition: border-color var(--trans), box-shadow var(--trans);
  }
  
  .customer-combobox:hover {
    border-color: var(--border);
  }
  
  .customer-combobox:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(232, 98, 26, 0.12);
    background: var(--surface);
  }
  
  .customer-detail-box {
    animation: slideDown 0.2s ease-out;
  }
  
  @keyframes slideDown {
    from {
      opacity: 0;
      transform: translateY(-8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>

<script>
  function handleCustomerSelect(select) {
    const selectedOption = select.options[select.selectedIndex];
    const detailBox = document.getElementById('customerDetail');
    
    if (!select.value) {
      detailBox.style.display = 'none';
      return;
    }
    
    // Get data from selected option attributes
    const code = selectedOption.dataset.code;
    const name = selectedOption.dataset.name;
    const company = selectedOption.dataset.company;
    const email = selectedOption.dataset.email;
    const phone = selectedOption.dataset.phone;
    const city = selectedOption.dataset.city;
    const status = selectedOption.dataset.status;
    
    // Update detail box
    document.getElementById('detailCode').textContent = code;
    document.getElementById('detailEmail').textContent = email || '-';
    document.getElementById('detailPhone').textContent = phone || '-';
    document.getElementById('detailCity').textContent = city || '-';
    document.getElementById('detailCompany').textContent = company;
    
    const statusBadge = document.getElementById('detailStatus');
    const badgeClass = status === 'aktif' ? 'ok' : 'warn';
    const statusLabel = status === 'aktif' ? 'Aktif' : 'Nonaktif';
    statusBadge.innerHTML = `<span class="badge ${badgeClass}">${statusLabel}</span>`;
    
    // Show detail box with animation
    detailBox.style.display = 'block';
  }
</script>

</body>
</html>
