<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Pengeluaran.php';
require_once '../../../../src/functions.php';

// Pastikan prefix sesuai dengan format di database
$tahun = date('Y');
$prefixOUT = "OUT-" . $tahun . "-";

$stmtKode = $pdo->prepare("
    SELECT MAX(CAST(SUBSTRING(nomor_pengeluaran, 10) AS UNSIGNED)) 
    FROM pengeluaran 
    WHERE nomor_pengeluaran LIKE ?
");
$stmtKode->execute([$prefixOUT . '%']);
$maxUrutan = $stmtKode->fetchColumn();

// Jika belum ada data atau mau start dari 006
$urutanBerikutnya = ($maxUrutan !== null) ? (int)$maxUrutan + 1 : 6;

// Force minimal ke 006 jika data ternyata di bawah itu
if ($urutanBerikutnya < 6) {
    $urutanBerikutnya = 6;
}

$autoNomorPengeluaran = $prefixOUT . str_pad($urutanBerikutnya, 3, '0', STR_PAD_LEFT);
// --------------------------------------------------------------------------

$pengeluaranModel = new Pengeluaran($pdo);

// Ambil data untuk dropdown
$spkList    = $pdo->query("SELECT id, nomor_spk FROM spk ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$produkList = $pdo->query("SELECT id, nama, stok, stok_available, stok_reserved FROM produk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$userList   = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$produkMap  = array_column($produkList, null, 'id');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data  = [
        'nomor_pengeluaran' => trim($_POST['nomor_pengeluaran'] ?? ''),
        'spk_id'  => $_POST['spk_id']  ?: null,
        'tanggal' => $_POST['tanggal'] ?? '',
        'status'  => $_POST['status']  ?? 'draft',
        'pic'     => $_POST['pic']     ?? '',
        'notes'   => trim($_POST['notes'] ?? ''),
    ];
    $items = $_POST['items'] ?? [];
    if (!$data['nomor_pengeluaran']) $errors[] = 'Nomor pengeluaran wajib diisi.';
    if (!$data['tanggal'])           $errors[] = 'Tanggal wajib diisi.';
    if (!$data['pic'])               $errors[] = 'PIC wajib dipilih.';
    if (empty($items))               $errors[] = 'Minimal satu item harus ditambahkan.';
    foreach ($items as $item) {
        $produk_id = (int)($item['produk_id'] ?? 0);
        $qty_diminta = (int)($item['qty'] ?? 0);
        
        if ($qty_diminta <= 0) continue; // Skip empty rows
        
        $pr = $produkMap[$produk_id] ?? null;
        if (!$pr) { $errors[] = 'Produk tidak valid.'; break; }
        
        // FIX: Check stok_available (tidak di-booking), bukan total stok
        $stok_tersedia = (int)($pr['stok_available'] ?? $pr['stok'] ?? 0);
        
        if ($qty_diminta > $stok_tersedia) {
            $stok_reserved = (int)($pr['stok_reserved'] ?? 0);
            $errors[] = sprintf(
                'Qty melebihi stok tersedia untuk "%s":<br>' .
                '• Total Stok: %d pcs<br>' .
                '• Di-booking PO: %d pcs<br>' .
                '• Tersedia: %d pcs<br>' .
                '• Diminta: %d pcs',
                $pr['nama'],
                (int)($pr['stok'] ?? 0),
                $stok_reserved,
                $stok_tersedia,
                $qty_diminta
            );
            break;
        }
    }
    
    // Check apakah ada item yang diinput
    $hasItems = false;
    foreach ($items as $item) {
        if ((int)($item['qty'] ?? 0) > 0) {
            $hasItems = true;
            break;
        }
    }
    if (!$hasItems) $errors[] = 'Minimal satu item harus diinput dengan qty > 0.';
    if (!$errors) {
        try { $pengeluaranModel->add($data, $items); header('Location: ../index.php?success=1'); exit; }
        catch (Exception $e) { $errors[] = $e->getMessage(); }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Pengeluaran | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/pengeluaran.css" rel="stylesheet">
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
      margin-bottom: 24px;
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
    
    .stok-display {
      text-align: center;
      font-weight: 600;
      color: var(--accent);
      font-size: 0.95rem;
      padding: 4px 8px;
      background: var(--accent-bg);
      border-radius: 4px;
      min-width: 60px;
      display: inline-block;
    }
    
    .stok-display.critical {
      color: #dc2626;
      background: rgba(220, 38, 38, 0.1);
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
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #d97706, #b45309);
      box-shadow: 0 6px 16px rgba(245, 158, 11, 0.3);
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

    .alert-error {
      background: rgba(220, 38, 38, 0.1);
      border: 1px solid rgba(220, 38, 38, 0.3);
      color: #991b1b;
      padding: 12px 16px;
      border-radius: 8px;
      display: flex;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .alert-error ul {
      margin: 0;
      padding-left: 20px;
      list-style: none;
    }

    .alert-error li {
      position: relative;
      padding-left: 8px;
      margin-bottom: 4px;
    }

    .alert-error li:before {
      content: "•";
      position: absolute;
      left: -8px;
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
          <a href="../index.php">Pengeluaran</a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah</span>
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
        <h1 class="page-title-lg">Tambah Pengeluaran</h1>
        <p class="page-subtitle">Catat pengeluaran barang dari gudang.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post" id="pengeluaranForm">
      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-box-seam"></i> Data Pengeluaran
        </div>

        <?php if ($errors): ?>
        <div class="alert-error">
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
            <label class="form-label">Nomor Pengeluaran <span class="required">*</span></label>
            <input type="text" name="nomor_pengeluaran" class="form-control"
                   value="<?= htmlspecialchars($_POST['nomor_pengeluaran'] ?? $autoNomorPengeluaran) ?>" readonly>
          </div>
          <div>
            <label class="form-label">Tanggal <span class="required">*</span></label>
            <input type="date" name="tanggal" class="form-control"
                   value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
              <?php foreach (['draft'=>'Draft','picking'=>'Picking','packing'=>'Packing','shipped'=>'Shipped','completed'=>'Completed'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($_POST['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div>
            <label class="form-label">Nomor SPK <span class="required">*</span></label>
            <select name="spk_id" class="form-control" id="spkSelect" required>
              <option value="">— Pilih SPK —</option>
              <?php foreach ($spkList as $spk): ?>
                <option value="<?= $spk['id'] ?>" <?= ($_POST['spk_id'] ?? '') == $spk['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($spk['nomor_spk']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">PIC <span class="required">*</span></label>
            <select name="pic" id="picSelect" class="form-control" required>
              <option value="">— Pilih PIC —</option>
              <?php foreach ($userList as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($_POST['pic'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($u['username']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control" style="min-height: 80px; border-radius: 8px; padding: 10px;"
                    placeholder="Catatan tambahan (opsional)"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-list-ul"></i> Daftar Item
        </div>

        <div id="noSpkWarning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #92400e; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
          <i class="bi bi-info-circle"></i> <strong>Pilih SPK terlebih dahulu</strong> untuk auto-load produk dari PO
        </div>

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--border);">
          <table class="items-table">
            <thead>
              <tr>
                <th class="row-num">#</th>
                <th>Kode</th>
                <th>Produk</th>
                <th style="width: 100px;">Qty Order</th>
                <th style="width: 120px;">Stok</th>
                <th style="width: 120px;">Qty Keluar</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              <tr id="emptyRow" style="background: var(--surface2);">
                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text3);">
                  <i class="bi bi-inbox" style="font-size: 1.8rem; display: block; margin-bottom: 10px;"></i>
                  Pilih SPK untuk menampilkan produk dari PO
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="form-actions-bottom" style="margin-top: 28px;">
          <button type="submit" class="btn-primary">
            <i class="bi bi-check-lg"></i> Simpan Pengeluaran
          </button>
          <a href="../index.php" class="btn-outline">
            <i class="bi bi-x-lg"></i> Batal
          </a>
        </div>
      </div>
    </form>

  </div>
</main>

</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const tbody = document.getElementById('itemsBody');
  const spkSelect = document.getElementById('spkSelect');
  const noSpkWarning = document.getElementById('noSpkWarning');
  const picSelect = document.getElementById('picSelect');
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function loadSpkItems(spkId) {
    if (!spkId) {
      tbody.innerHTML = '<tr id="emptyRow" style="background: var(--surface2);"><td colspan="7" style="text-align: center; padding: 40px; color: var(--text3);"><i class="bi bi-inbox" style="font-size: 1.8rem; display: block; margin-bottom: 10px;"></i>Pilih SPK untuk menampilkan produk dari PO</td></tr>';
      noSpkWarning.style.display = 'block';
      // Reset PIC
      picSelect.value = '';
      picSelect.style.pointerEvents = 'auto';
      picSelect.style.backgroundColor = '#ffffff';
      return;
    }
    
    // FETCH 1: Load Items
    fetch(`load_spk_items.php?spk_id=${spkId}`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.items && data.items.length > 0) {
          noSpkWarning.style.display = 'none';
          tbody.innerHTML = '';
          data.items.forEach((item, idx) => {
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
              <td class="row-num">${idx + 1}</td>
              <td style="font-weight: 600; color: var(--accent);">${escapeHtml(item.kode_produk || 'N/A')}</td>
              <td>${escapeHtml(item.nama)}</td>
              <td style="text-align: center; font-weight: 600;">${item.qty_order}</td>
              <td style="text-align: center;"><span class="stok-display">${item.stok_available} pcs</span></td>
              <td>
                <input type="hidden" name="items[${idx}][produk_id]" value="${item.produk_id}">
                <input type="number" name="items[${idx}][qty]" class="form-control" value="${item.qty_order}" max="${item.qty_order}" min="1" required>
              </td>
              <td><button type="button" class="btn-icon" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
          });
        }
      });

    // FETCH 2: Auto-Fill PIC (Jembatan ke get_spk_pic.php)
    fetch(`get_spk_pic.php?spk_id=${spkId}`)
      .then(res => res.json())
      .then(data => {
        if(data.pic_id) {
            picSelect.value = data.pic_id;
            picSelect.style.pointerEvents = 'none'; // Kunci biar gak bisa ganti manual
            picSelect.style.backgroundColor = '#f3f4f6';
        }
      });
  }

  // Event Listener Utama
  spkSelect.addEventListener('change', function() {
    loadSpkItems(this.value);
  });

  // Init kalau ada data pas reload (Edit Mode)
  if (spkSelect.value) loadSpkItems(spkSelect.value);

  // Dark mode toggle
  const html = document.documentElement;
  const themeBtn = document.getElementById('themeToggle');
  themeBtn?.addEventListener('click', () => {
      const isDark = html.getAttribute('data-theme') === 'dark';
      html.setAttribute('data-theme', isDark ? 'light' : 'dark');
      themeBtn.querySelector('i').className = isDark ? 'bi bi-moon' : 'bi bi-sun';
  });

  // Menu toggle
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.querySelector('.sidebar');
  menuBtn?.addEventListener('click', () => sidebar.classList.toggle('active'));
});
</script>
