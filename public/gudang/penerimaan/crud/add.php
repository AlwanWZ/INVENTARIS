<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Penerimaan.php';
require_once '../../../../src/functions.php';

// Ambil tahun saat ini
$tahun = date('Y');
$prefixRCV = "RCV-" . $tahun . "-";

// Cari kode penerimaan terakhir di tahun ini
$stmtKode = $pdo->prepare("SELECT nomor_penerimaan FROM penerimaan WHERE nomor_penerimaan LIKE ? ORDER BY id DESC LIMIT 1");
$stmtKode->execute([$prefixRCV . '%']);
$lastKode = $stmtKode->fetchColumn();

if ($lastKode) {
    // Ambil 3 digit terakhir (setelah RCV-YYYY-), lalu tambah 1
    $urutan = (int)substr($lastKode, strlen($prefixRCV)) + 1;
    $autoNomorPenerimaan = $prefixRCV . str_pad($urutan, 3, '0', STR_PAD_LEFT);
} else {
    // Kalau belum ada penerimaan sama sekali di tahun ini
    $autoNomorPenerimaan = $prefixRCV . '001';
}

$penerimaanModel = new Penerimaan($pdo);

$poList = $pdo->query("SELECT id, nomor_po FROM po ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Tarik sekalian nama PIC dari SPK
$spkList = $pdo->query("
    SELECT s.id, s.nomor_spk, u.username as pic_name 
    FROM spk s 
    LEFT JOIN users u ON s.pic = u.id 
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$produkList = $pdo->query("SELECT id, nama FROM produk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

// --- LOGIKA BARU: AMBIL DATA ITEMS DARI DATABASE UNTUK AUTO-FILL JS ---
$poItemsData = [];
$spkItemsData = [];

try {
    // Ambil Item dari PO
    $stmtPO = $pdo->query("SELECT po_id, produk_id, qty FROM po_items");
    while ($row = $stmtPO->fetch(PDO::FETCH_ASSOC)) {
        $poItemsData[$row['po_id']][] = [
            'produk_id' => $row['produk_id'],
            'qty_order' => $row['qty']
        ];
    }
} catch (Exception $e) {}

try {
    // Ambil Item dari SPK (Lewat relasi PO)
    $stmtSPK = $pdo->query("
        SELECT s.id as spk_id, pi.produk_id, pi.qty 
        FROM spk s 
        JOIN po_items pi ON s.po_id = pi.po_id
    ");
    while ($row = $stmtSPK->fetch(PDO::FETCH_ASSOC)) {
        $spkItemsData[$row['spk_id']][] = [
            'produk_id' => $row['produk_id'],
            'qty_order' => $row['qty']
        ];
    }
} catch (Exception $e) {}
// ------------------------------------------------------------------------

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tarik PIC otomatis dari SPK di Backend
    $spk_id = $_POST['spk_id'] ?: null;
    $pic_otomatis = null;
    
    if ($spk_id) {
        $stmtPic = $pdo->prepare("SELECT pic FROM spk WHERE id = ?");
        $stmtPic->execute([$spk_id]);
        $pic_otomatis = $stmtPic->fetchColumn();
    }

    $data = [
        'nomor_penerimaan' => trim($_POST['nomor_penerimaan'] ?? ''),
        'po_id'            => $_POST['po_id']  ?: null,
        'spk_id'           => $spk_id,
        'tanggal'          => $_POST['tanggal'] ?? '',
        'status'           => $_POST['status']  ?? 'draft',
        'pic'              => $pic_otomatis, // SET OTOMATIS
        'notes'            => trim($_POST['notes'] ?? ''),
    ];
    
    $items = $_POST['items'] ?? [];
    
    if (!$data['nomor_penerimaan']) $errors[] = 'Nomor penerimaan wajib diisi.';
    if (!$data['tanggal'])         $errors[] = 'Tanggal wajib diisi.';
    if (empty($items))             $errors[] = 'Minimal satu item harus ditambahkan.';
    
    foreach ($items as $item) {
        if ((int)$item['qty_diterima'] > (int)$item['qty_order']) {
            $errors[] = 'Qty diterima tidak boleh melebihi qty order.';
            break;
        }
    }
    
    if (!$errors) {
        $penerimaanModel->add($data, $items);
        header('Location: ../index.php?success=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Penerimaan | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/penerimaan.css" rel="stylesheet">
  <style>
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
    
    .qty-status {
      text-align: center;
      font-weight: 600;
      color: var(--text);
      font-size: 0.9rem;
      padding: 4px 8px;
      background: var(--surface2);
      border-radius: 4px;
      min-width: 50px;
      display: inline-block;
    }
    
    .qty-status.mismatch {
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
      background: linear-gradient(135deg, #06b6d4, #0891b2);
      color: #fff;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #0891b2, #0e7490);
      box-shadow: 0 6px 16px rgba(6, 182, 212, 0.3);
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

    .form-control[readonly] {
        background-color: var(--surface2);
        color: var(--text2);
        cursor: not-allowed;
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
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Penerimaan</a>
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
        <h1 class="page-title-lg">Tambah Penerimaan</h1>
        <p class="page-subtitle">Catat penerimaan barang masuk gudang.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post" id="penerimaanForm">
      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-box-seam"></i> Data Penerimaan
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
            <label class="form-label">Nomor Penerimaan <span class="required">*</span></label>
            <input type="text" name="nomor_penerimaan" class="form-control"
                   value="<?= htmlspecialchars($_POST['nomor_penerimaan'] ?? $autoNomorPenerimaan) ?>" readonly>
          </div>
          <div>
            <label class="form-label">Tanggal <span class="required">*</span></label>
            <input type="date" name="tanggal" class="form-control"
                   value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
              <?php foreach (['draft'=>'Draft','received'=>'Received','checked'=>'Checked','completed'=>'Completed'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($_POST['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid-3">
          <div>
            <label class="form-label">Nomor PO</label>
            <select name="po_id" id="poSelect" class="form-control">
              <option value="">— Pilih PO (opsional) —</option>
              <?php foreach ($poList as $po): ?>
                <option value="<?= $po['id'] ?>" <?= ($_POST['po_id'] ?? '') == $po['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($po['nomor_po']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label class="form-label">Nomor SPK</label>
            <select name="spk_id" id="spkSelect" class="form-control">
              <option value="" data-pic-name="— Pilih SPK Dahulu —">— Pilih SPK (opsional) —</option>
              <?php foreach ($spkList as $spk): ?>
                <option value="<?= $spk['id'] ?>" 
                        data-pic-name="<?= htmlspecialchars($spk['pic_name'] ?? 'Tidak ada PIC') ?>" 
                        <?= ($_POST['spk_id'] ?? '') == $spk['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($spk['nomor_spk']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label">PIC Marketing</label>
            <input type="text" id="picDisplay" class="form-control" value="— Pilih SPK Dahulu —" readonly>
            <small class="text-muted" style="display: block; margin-top: 4px;">Terisi otomatis sesuai SPK</small>
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

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--border);">
          <table class="items-table">
            <thead>
              <tr>
                <th class="row-num">#</th>
                <th>Produk</th>
                <th style="width: 120px;">Qty Order</th>
                <th style="width: 140px;">Qty Diterima</th>
                <th style="width: 80px;">Status</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              </tbody>
          </table>
        </div>

        <button type="button" class="btn-add-row" onclick="addItemRow()" style="margin-top: 16px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(8, 145, 178, 0.05)); color: #06b6d4; border: 1.5px dashed #06b6d4; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.25s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <i class="bi bi-plus-lg"></i> Tambah Item
        </button>

        <div class="form-actions-bottom" style="margin-top: 28px;">
          <button type="submit" class="btn-primary">
            <i class="bi bi-check-lg"></i> Simpan Penerimaan
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
  const produkList = <?php echo json_encode($produkList ?? []); ?>;
  const poItemsData = <?php echo json_encode($poItemsData ?? []); ?>;
  const spkItemsData = <?php echo json_encode($spkItemsData ?? []); ?>;
  
  const tbody = document.getElementById('itemsBody');
  const spkSelect = document.getElementById('spkSelect');
  const poSelect = document.getElementById('poSelect');
  const picDisplay = document.getElementById('picDisplay');
  let rowCount = 0;

  // --- LOGIKA UPDATE ITEM OTOMATIS BERDASARKAN PO / SPK ---
  function populateItems(items) {
      tbody.innerHTML = ''; // Hapus baris yang ada
      rowCount = 0;
      if (items && items.length > 0) {
          items.forEach(item => {
              addItemRow(item.produk_id, item.qty_order);
          });
      } else {
          addItemRow(); // Minimal 1 baris kosong kalau gak ada data
      }
  }

  if (spkSelect && picDisplay) {
      spkSelect.addEventListener('change', function() {
          const spkId = this.value;
          
          // Update nama PIC
          const selectedOption = this.options[this.selectedIndex];
          const picName = selectedOption.getAttribute('data-pic-name') || '— Pilih SPK Dahulu —';
          picDisplay.value = picName;
          
          // Update Items berdasarkan SPK
          if (spkId && spkItemsData[spkId]) {
              populateItems(spkItemsData[spkId]);
          } else if (!spkId && poSelect.value && poItemsData[poSelect.value]) {
              // Jika SPK dikosongkan, cek apakah ada PO yg dipilih
              populateItems(poItemsData[poSelect.value]);
          } else {
              populateItems([]);
          }
      });
  }

  if (poSelect) {
      poSelect.addEventListener('change', function() {
          const poId = this.value;
          // Jika SPK belum dipilih, isi otomatis dari PO
          if (!spkSelect.value) {
              if (poId && poItemsData[poId]) {
                  populateItems(poItemsData[poId]);
              } else {
                  populateItems([]);
              }
          }
      });
  }
  // --------------------------------------------------------

  window.updateQtyStatus = function(input) {
    const tr = input.closest('tr');
    const qtyOrder = parseInt(tr.querySelector('.qty-order').value) || 0;
    const qtyDiterima = parseInt(tr.querySelector('.qty-diterima').value) || 0;
    const statusEl = tr.querySelector('.qty-status');
    
    if (qtyOrder === 0 && qtyDiterima === 0) {
      statusEl.textContent = '—';
      statusEl.className = 'qty-status';
    } else if (qtyDiterima === qtyOrder) {
      statusEl.textContent = '✓ Sesuai';
      statusEl.className = 'qty-status';
    } else if (qtyDiterima < qtyOrder) {
      statusEl.textContent = '⬇ Kurang ' + (qtyOrder - qtyDiterima);
      statusEl.className = 'qty-status mismatch';
    } else {
      statusEl.textContent = '⬆ Lebih ' + (qtyDiterima - qtyOrder);
      statusEl.className = 'qty-status mismatch';
    }
  };

  window.addItemRow = function(selectedProdukId = '', qtyOrderVal = '') {
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    const idx = rowCount;
    
    // Bikin opsi produk dinamis dari JSON
    let productOptions = '<option value="">— Pilih Produk —</option>';
    produkList.forEach(pr => {
        const isSelected = (pr.id == selectedProdukId) ? 'selected' : '';
        productOptions += `<option value="${pr.id}" ${isSelected}>${pr.nama}</option>`;
    });
    
    tr.innerHTML = `
      <td class="row-num">${idx + 1}</td>
      <td>
        <select name="items[${idx}][produk_id]" class="form-control select-produk" required>
          ${productOptions}
        </select>
      </td>
      <td>
        <input type="number" name="items[${idx}][qty_order]" class="form-control qty-order" min="1" placeholder="0" value="${qtyOrderVal}" required onchange="updateQtyStatus(this)">
      </td>
      <td>
        <input type="number" name="items[${idx}][qty_diterima]" class="form-control qty-diterima" min="0" placeholder="0" required onchange="updateQtyStatus(this)">
      </td>
      <td>
        <span class="qty-status" id="status-${idx}">—</span>
      </td>
      <td style="text-align: center;">
        <button type="button" class="btn-icon btn-delete" onclick="removeRow(this)" title="Hapus baris"><i class="bi bi-trash"></i></button>
      </td>
    `;
    
    tbody.appendChild(tr);
    rowCount++;
  };

  window.removeRow = function(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
      btn.closest('tr').remove();
      document.querySelectorAll('.item-row').forEach((row, idx) => {
        row.querySelector('.row-num').textContent = idx + 1;
      });
    } else {
      alert('Minimal harus ada 1 item dalam penerimaan!');
    }
  };

  // Trigger setup awal
  if (spkSelect && spkSelect.value) {
      spkSelect.dispatchEvent(new Event('change'));
  } else if (poSelect && poSelect.value) {
      poSelect.dispatchEvent(new Event('change'));
  } else {
      populateItems([]);
  }

  // Tema & Menu
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