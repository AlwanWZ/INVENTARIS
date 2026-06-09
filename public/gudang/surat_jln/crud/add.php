<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/SuratJalan.php';
require_once '../../../../src/functions.php';

$errors = [];

// --- LOGIKA NOMOR SJ OTOMATIS ---
$tahun = date('Y');
$prefixSJ = "SJ-" . $tahun . "-";
$stmtSJ = $pdo->prepare("SELECT nomor_sj FROM surat_jalan WHERE nomor_sj LIKE ? ORDER BY id DESC LIMIT 1");
$stmtSJ->execute([$prefixSJ . '%']);
$lastSJ = $stmtSJ->fetchColumn();

if ($lastSJ) {
    $urutan = (int)substr($lastSJ, strlen($prefixSJ)) + 1;
    $autoNomorSJ = $prefixSJ . str_pad($urutan, 3, '0', STR_PAD_LEFT);
} else {
    $autoNomorSJ = $prefixSJ . '001';
}
// --------------------------------

$suratJalanModel = new SuratJalan($pdo);

// --- PERBAIKAN KUERI CUSTOMER TINGKAT DEWA ---
// Masalah kemarin: customer_id ternyata nempel di PO, bukan di SPK.
// Solusi: Kita pakai COALESCE(po.customer_id, s.customer_id) biar nyari di dua-duanya.
$pengeluaranList = $pdo->query("
    SELECT p.id, p.nomor_pengeluaran, 
           COALESCE(s.nomor_spk, 'Tanpa SPK') as nomor_spk, 
           COALESCE(po.nomor_po, 'Tanpa PO') as nomor_po, 
           COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), 'Customer Belum Diset') as perusahaan 
    FROM pengeluaran p
    LEFT JOIN spk s ON p.spk_id = s.id
    LEFT JOIN po ON s.po_id = po.id
    LEFT JOIN customers c ON c.id = COALESCE(po.customer_id, s.customer_id)
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
// ---------------------------------------------

$items = [];
if (!empty($_POST['pengeluaran_id'])) {
  $stmt = $pdo->prepare("SELECT pi.*, pr.nama AS produk_nama, pr.satuan FROM pengeluaran_items pi LEFT JOIN produk pr ON pi.produk_id = pr.id WHERE pi.pengeluaran_id = ?");
  $stmt->execute([$_POST['pengeluaran_id']]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
      'nomor_sj'       => trim($_POST['nomor_sj']       ?? ''),
      'tanggal_kirim'  => $_POST['tanggal_kirim']  ?? '',
      'pengeluaran_id' => $_POST['pengeluaran_id'] ?? '',
      'driver'         => trim($_POST['driver']         ?? ''),
      'kendaraan'      => trim($_POST['kendaraan']      ?? ''),
      'catatan'        => trim($_POST['catatan']        ?? ''),
      'items'          => $_POST['items']          ?? [],
    ];
    
    // --- PERBAIKAN PENCARIAN ALAMAT CUSTOMER ---
    $data['customer_id'] = null;
    $data['alamat_kirim'] = '';
    if (!empty($data['pengeluaran_id'])) {
      $stmt = $pdo->prepare("
          SELECT COALESCE(po.customer_id, s.customer_id) as customer_id, 
                 c.alamat AS alamat_kirim 
          FROM pengeluaran p 
          LEFT JOIN spk s ON p.spk_id = s.id 
          LEFT JOIN po ON s.po_id = po.id
          LEFT JOIN customers c ON c.id = COALESCE(po.customer_id, s.customer_id)
          WHERE p.id = ?
      ");
      $stmt->execute([$data['pengeluaran_id']]);
      $pengeluaran = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($pengeluaran) {
        $data['customer_id'] = $pengeluaran['customer_id'];
        $data['alamat_kirim'] = $pengeluaran['alamat_kirim'] ?? '';
      }
    }
    
    // created_by dari session user
    $data['created_by'] = $_SESSION['user']['id'] ?? null;
    
    if (!$data['nomor_sj'])       $errors[] = 'Nomor SJ wajib diisi.';
    if (!$data['tanggal_kirim'])  $errors[] = 'Tanggal kirim wajib diisi.';
    if (!$data['pengeluaran_id']) $errors[] = 'Pengeluaran wajib dipilih.';
    if (!$data['driver'])         $errors[] = 'Driver wajib diisi.';
    if (!$data['kendaraan'])      $errors[] = 'Kendaraan wajib diisi.';
    if (!$data['customer_id'])    $errors[] = 'Data Customer tidak ditemukan dari PO/SPK. Pastikan pesanan ini memiliki Customer.';
    
    if (!$errors) {
      try {
        $result = $suratJalanModel->add($data, $data['items']);
        if ($result) {
          header('Location: ../index.php?msg=add-success');
          exit;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buat Surat Jalan | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/surat_jln.css" rel="stylesheet">
  <style>
    /* Styling Master-Detail Form */
    .form-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .form-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
    
    .form-section-header { 
        font-weight: 700; 
        color: var(--text); 
        border-bottom: 2px solid var(--accent-bg); 
        padding-bottom: 12px; 
        margin-bottom: 18px; 
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .items-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--bg);
    }
    
    .items-table thead {
      background: var(--surface2);
      border-top: 1px solid var(--border);
      border-bottom: 2px solid var(--border);
    }
    
    .items-table th {
      color: var(--text2);
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 12px 10px;
      text-align: left;
    }
    
    .items-table td {
      vertical-align: middle;
      padding: 10px;
      border-bottom: 1px solid var(--border);
      font-size: 0.9rem;
    }
    
    .items-table tbody tr:hover {
      background: var(--surface);
    }
    
    .items-table .form-control {
      font-size: 0.9rem;
      padding: 8px 10px;
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: var(--surface);
      color: var(--text);
    }
    
    .items-table .form-control:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 2px var(--accent-bg);
    }
    
    .row-num {
      text-align: center;
      font-weight: 600;
      color: var(--text3);
      width: 35px;
      min-width: 35px;
    }
    
    .btn-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: all 0.2s ease;
      background: rgba(220, 38, 38, 0.08);
      color: var(--text3);
      border: 1px solid rgba(220, 38, 38, 0.2);
    }
    
    .btn-icon:hover {
      background: rgba(220, 38, 38, 0.9);
      color: #fff;
      border-color: rgba(220, 38, 38, 0.9);
    }
    
    .form-actions-bottom {
      margin-top: 28px;
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }
    
    .btn-primary, .btn-outline {
      padding: 11px 22px;
      font-size: 0.95rem;
      font-weight: 600;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #10b981, #059669);
      color: #fff;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #059669, #047857);
      box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
      transform: translateY(-2px);
    }
    
    .btn-outline {
      background: var(--surface2);
      color: var(--text2);
      border: 1px solid var(--border);
    }
    
    .btn-outline:hover {
      background: var(--surface);
      color: var(--text);
      border-color: var(--border2);
    }

    @media (max-width: 768px) {
      .form-grid-3, .form-grid-2 { grid-template-columns: 1fr; }
      .form-actions-bottom { justify-content: stretch; }
      .form-actions-bottom .btn-primary,
      .form-actions-bottom .btn-outline { width: 100%; justify-content: center; }
      .items-table { font-size: 0.8rem; }
      .items-table th, .items-table td { padding: 8px 6px; }
    }
  </style>
</head>
<body>

<?php include '../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Surat Jalan</a>
          <i class="bi bi-chevron-right"></i>
          <span>Buat Surat Jalan</span>
        </div>
      </div>
      <div class="top-right">
        <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
        <div class="user-box">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            <span class="user-role">Gudang</span>
          </div>
        </div>
      </div>
    </div>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Buat Surat Jalan</h1>
        <p class="page-subtitle">Isi formulir untuk membuat surat jalan pengiriman.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post" id="sjForm">
      <div class="form-card" style="margin-bottom: 24px;">
        <div class="form-section-header">
          <i class="bi bi-file-earmark-plus"></i> Data Surat Jalan
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="alert-error" style="margin-bottom: 20px;">
          <i class="bi bi-exclamation-circle"></i>
          <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        
        <div class="form-grid-3">
          <div>
            <label class="form-label">Nomor Surat Jalan <span class="required">*</span></label>
            <input type="text" name="nomor_sj" class="form-control" 
                   value="<?= htmlspecialchars($_POST['nomor_sj'] ?? $autoNomorSJ) ?>" readonly>
          </div>
          <div>
            <label class="form-label">Tanggal Kirim <span class="required">*</span></label>
            <input type="date" name="tanggal_kirim" class="form-control"
                   value="<?= htmlspecialchars($_POST['tanggal_kirim'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div>
            <label class="form-label">Pilih Pengeluaran <span class="required">*</span></label>
            <?php if (empty($pengeluaranList)): ?>
              <div class="alert-error">Belum ada pengeluaran.</div>
            <?php else: ?>
              <select name="pengeluaran_id" class="form-control" id="pengeluaranSelect" required>
                <option value="">— Pilih Pengeluaran —</option>
                <?php foreach ($pengeluaranList as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= ($_POST['pengeluaran_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nomor_pengeluaran']) ?> | 
                    SPK: <?= htmlspecialchars($p['nomor_spk'] ?? '-') ?> | 
                    PO: <?= htmlspecialchars($p['nomor_po'] ?? '-') ?> | 
                    (<?= htmlspecialchars($p['perusahaan'] ?? 'Customer Tidak Ditemukan') ?>)
                  </option>
                <?php endforeach; ?>
              </select>           
            <?php endif; ?>
          </div>
        </div>

        <div class="form-grid-2">
          <div>
            <label class="form-label">Driver <span class="required">*</span></label>
            <input type="text" name="driver" class="form-control"
                   placeholder="Nama driver"
                   value="<?= htmlspecialchars($_POST['driver'] ?? '') ?>" required>
          </div>
          <div>
            <label class="form-label">Kendaraan <span class="required">*</span></label>
            <input type="text" name="kendaraan" class="form-control"
                   placeholder="Nopol atau nama kendaraan"
                   value="<?= htmlspecialchars($_POST['kendaraan'] ?? '') ?>" required>
          </div>
        </div>

        <div>
          <label class="form-label">Catatan</label>
          <textarea name="catatan" class="form-control" style="min-height: 80px; border-radius: 8px; padding: 10px;"
                    placeholder="Catatan pengiriman (opsional)"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-list-ul"></i> Daftar Barang
        </div>

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--border);">
          <table class="items-table">
            <thead>
              <tr>
                <th class="row-num">#</th>
                <th>Nama Produk</th>
                <th style="width: 120px;">Qty</th>
                <th style="width: 100px;">Satuan</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              <?php if ($items): ?>
              <?php foreach ($items as $i => $item): ?>
              <tr class="item-row">
                <td class="row-num"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($item['produk_nama'] ?? '-') ?></td>
                <td>
                  <input type="number" name="items[<?= $i ?>][qty]" class="form-control qty-input"
                         min="1" max="<?= $item['qty'] ?>" value="<?= $item['qty'] ?>" required>
                  <input type="hidden" name="items[<?= $i ?>][produk_id]" value="<?= $item['produk_id'] ?>">
                </td>
                <td style="text-align: center; color: var(--text3);"><?= htmlspecialchars($item['satuan'] ?? 'pcs') ?></td>
                <td style="text-align: center;">
                  <button type="button" class="btn-icon btn-delete" title="Hapus baris"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text3);">
                  <i class="bi bi-inbox" style="font-size: 1.8rem; display: block; margin-bottom: 10px;"></i>
                  Pilih pengeluaran untuk menampilkan daftar barang
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="form-actions-bottom">
          <button type="submit" name="save" class="btn-primary">
            <i class="bi bi-check-lg"></i> Simpan Surat Jalan
          </button>
          <a href="../index.php" class="btn-outline">
            <i class="bi bi-x-lg"></i> Batal
          </a>
        </div>
      </div>

    </form>

  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const pengeluaranSelect = document.getElementById('pengeluaranSelect');
    const sjForm = document.getElementById('sjForm');
    const tbody = document.getElementById('itemsBody');
    
    // Reload items ketika pengeluaran dipilih
    if (pengeluaranSelect) {
      pengeluaranSelect.addEventListener('change', function() {
        if (this.value) {
          // Submit form dengan hidden input untuk load items
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'load_items';
          input.value = '1';
          sjForm.appendChild(input);
          sjForm.submit();
        }
      });
    }

    // Delete row functionality
    if (tbody) {
      tbody.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete')) {
          e.preventDefault();
          e.stopPropagation();
          const rows = document.querySelectorAll('.item-row');
          if (rows.length > 1) {
            e.target.closest('tr').remove();
            // Renumber rows
            document.querySelectorAll('.item-row').forEach((row, idx) => {
              row.querySelector('.row-num').textContent = idx + 1;
            });
          } else {
            alert('Minimal harus ada 1 item dalam surat jalan!');
          }
        }
      });
    }

    // Dark mode toggle
    const html = document.documentElement;
    const themeBtn = document.getElementById('themeToggle');
    const isDark = localStorage.getItem('theme') === 'dark';
    if (isDark) {
      html.setAttribute('data-theme', 'dark');
      if (themeBtn) themeBtn.querySelector('i').className = 'bi bi-sun';
    }
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const newDark = html.getAttribute('data-theme') !== 'dark';
        html.setAttribute('data-theme', newDark ? 'dark' : 'light');
        localStorage.setItem('theme', newDark ? 'dark' : 'light');
        themeBtn.querySelector('i').className = newDark ? 'bi bi-sun' : 'bi bi-moon';
      });
    }

    // Menu toggle
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.querySelector('.sidebar');
    if (menuBtn && sidebar) {
      menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('active');
      });
    }
  });
</script>
</body>
</html>