<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($customer) ? 'Edit' : 'Tambah' ?> Customer | InventorySys</title>
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
          <a href="index.php">Customer</a>
          <i class="bi bi-chevron-right"></i>
          <?php if (isset($customer)): ?>
            <a href="detail.php?id=<?= $customer['id'] ?>"><?= htmlspecialchars($customer['nama']) ?></a>
            <i class="bi bi-chevron-right"></i>
            <span>Edit</span>
          <?php else: ?>
            <span>Tambah Customer</span>
          <?php endif; ?>
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

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg"><?= isset($customer) ? 'Edit Customer' : 'Tambah Customer' ?></h1>
        <p class="page-subtitle"><?= isset($customer) ? 'Perbarui data customer <strong>' . htmlspecialchars($customer['nama']) . '</strong>.' : 'Isi formulir berikut untuk menambahkan customer baru.' ?></p>
      </div>
      <a href="<?= isset($customer) ? 'detail.php?id=' . $customer['id'] : 'index.php' ?>" class="btn-ghost-sm">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
    </div>

    <?php if ($success): ?>
    <div class="alert-success" style="margin-bottom:18px;">
      <i class="bi bi-check-circle"></i>
      Data customer berhasil disimpan.
      <a href="index.php" style="margin-left:8px;font-weight:600;color:var(--green);">Lihat daftar →</a>
    </div>
    <?php endif; ?>

    <div class="form-layout">

      <!-- FORM UTAMA -->
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-person-vcard"></i> Data Customer</h4>
            <?php if (isset($customer)): ?>
              <span class="badge <?= $customer['status'] === 'aktif' ? 'ok' : 'warn' ?>">
                <?= $customer['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
              </span>
            <?php endif; ?>
          </div>

          <?php if (!empty($errors)): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>

          <form method="post" class="po-form" autocomplete="off">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nama <span class="required">*</span></label>
                <input type="text" name="nama" class="form-control"
                       placeholder="Nama lengkap customer"
                       value="<?= htmlspecialchars($customer['nama'] ?? $_POST['nama'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php
                    $curStatus = $customer['status'] ?? $_POST['status'] ?? 'aktif';
                  ?>
                  <option value="aktif"    <?= $curStatus === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
                  <option value="nonaktif" <?= $curStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Perusahaan</label>
              <input type="text" name="perusahaan" class="form-control"
                     placeholder="Nama perusahaan (opsional)"
                     value="<?= htmlspecialchars($customer['perusahaan'] ?? $_POST['perusahaan'] ?? '') ?>">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       placeholder="contoh@email.com"
                       value="<?= htmlspecialchars($customer['email'] ?? $_POST['email'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">No HP</label>
                <input type="text" name="no_hp" class="form-control"
                       placeholder="+62 812 xxxx xxxx"
                       value="<?= htmlspecialchars($customer['no_hp'] ?? $_POST['no_hp'] ?? '') ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Kota</label>
              <input type="text" name="kota" class="form-control"
                     placeholder="Kota domisili"
                     value="<?= htmlspecialchars($customer['kota'] ?? $_POST['kota'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label class="form-label">Alamat</label>
              <textarea name="alamat" class="form-control form-textarea"
                        placeholder="Alamat lengkap (opsional)"><?= htmlspecialchars($customer['alamat'] ?? $_POST['alamat'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary">
                <i class="bi bi-check-lg"></i>
                <?= isset($customer) ? 'Simpan Perubahan' : 'Simpan Customer' ?>
              </button>
              <a href="<?= isset($customer) ? 'detail.php?id=' . $customer['id'] : 'index.php' ?>" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <!-- SIDE PANEL -->
      <div class="form-side">

        <?php if (isset($customer)): ?>
        <!-- Data sebelumnya (edit mode) -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clock-history"></i> Data Sebelumnya</h4>
          </div>
          <div class="side-info-list">
            <div class="side-info-item">
              <span class="side-info-label">Nama</span>
              <span class="side-info-val fw-mid"><?= htmlspecialchars($customer['nama']) ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Perusahaan</span>
              <span class="side-info-val"><?= htmlspecialchars($customer['perusahaan'] ?: '—') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Email</span>
              <span class="side-info-val"><?= htmlspecialchars($customer['email'] ?: '—') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Kota</span>
              <span class="side-info-val"><?= htmlspecialchars($customer['kota'] ?: '—') ?></span>
            </div>
            <div class="side-info-item">
              <span class="side-info-label">Status</span>
              <span class="side-info-val">
                <span class="badge <?= $customer['status'] === 'aktif' ? 'ok' : 'warn' ?>">
                  <?= $customer['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </span>
            </div>
          </div>
        </div>

        <!-- Zona berbahaya -->
        <div class="form-card danger-card">
          <div class="form-card-header">
            <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
          </div>
          <div class="danger-body">
            <p>Hapus customer ini secara permanen. Tindakan tidak dapat dibatalkan.</p>
            <button type="button" class="btn-danger" id="deleteBtn">
              <i class="bi bi-trash"></i> Hapus Customer
            </button>
          </div>
        </div>

        <?php else: ?>
        <!-- Panduan (add mode) -->
        <div class="form-card info-card">
          <div class="form-card-header">
            <h4><i class="bi bi-info-circle"></i> Panduan</h4>
          </div>
          <ul class="info-list">
            <li><i class="bi bi-dot"></i> Hanya nama yang wajib diisi, kolom lain opsional.</li>
            <li><i class="bi bi-dot"></i> Email harus valid jika diisi.</li>
            <li><i class="bi bi-dot"></i> Kode customer dibuat otomatis oleh sistem.</li>
            <li><i class="bi bi-dot"></i> Customer <em>Nonaktif</em> tidak muncul di pilihan PO baru.</li>
          </ul>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- DELETE MODAL (edit mode only) -->
    <?php if (isset($customer)): ?>
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus Customer?</h3>
        <p>Customer <strong><?= htmlspecialchars($customer['nama']) ?></strong> akan dihapus permanen dan tidak bisa dikembalikan.</p>
        <div class="modal-actions">
          <form method="post" action="delete.php">
            <input type="hidden" name="id" value="<?= $customer['id'] ?>">
            <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>

<?php if (isset($customer)): ?>
<script>
  const modal  = document.getElementById('deleteModal');
  document.getElementById('deleteBtn')?.addEventListener('click',  () => modal.classList.add('show'));
  document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
<?php endif; ?>
</body>
</html>
