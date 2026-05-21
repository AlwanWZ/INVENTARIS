<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/models/Customer.php';
require_once '../../../../src/models/Produk.php';
require_once '../../../../src/functions.php';

$errors = [];
$success = false;
$autoNomorPo = generatePONumber(); // Generate PO: PO-MMYY-urutan

// Get all customers & products
$customerList = Customer::getAll();
$produkList = Produk::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataPO = [
        'nomor_po'    => trim($_POST['nomor_po'] ?? ''),
        'tanggal'     => $_POST['tanggal'] ?? '',
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'status'      => $_POST['status'] ?? 'draft',
        'notes'       => trim($_POST['notes'] ?? ''),
    ];
    
    // Validate master data
    if (!$dataPO['nomor_po']) $errors[] = 'Nomor Pesanan wajib diisi.';
    if (!$dataPO['tanggal'])  $errors[] = 'Tanggal wajib diisi.';
    if (!$dataPO['customer_id']) $errors[] = 'Customer wajib dipilih.';

    // Get items data
    $dataItems = [];
    $itemCount = 0;

    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $idx => $item) {
            // Skip empty rows
            if (empty($item['qty']) && empty($item['harga_satuan'])) {
                continue;
            }

            $itemCount++;

            // Validate each item
            if (empty($item['qty']) || $item['qty'] <= 0) {
                $errors[] = "Baris #$itemCount: Qty wajib diisi dan harus lebih dari 0.";
                continue;
            }
            if (empty($item['harga_satuan'])) {
                $errors[] = "Baris #$itemCount: Harga satuan wajib diisi.";
                continue;
            }

            // Get produk data untuk auto-fill kode & nama
            $produk = !empty($item['produk_id']) ? Produk::find($item['produk_id']) : null;

            $dataItems[] = [
                'produk_id'     => intval($item['produk_id'] ?? 0) ?: null,
                'kode_material' => $produk['kode_produk'] ?? trim($item['kode_material'] ?? ''),
                'nama_material' => $produk['nama'] ?? trim($item['nama_material'] ?? ''),
                'uom'           => trim($item['uom'] ?? 'pcs'),
                'qty'           => intval($item['qty']),
                'harga_satuan'  => intval(str_replace(['.', ','], '', $item['harga_satuan'] ?? 0)),
                'diskon'        => floatval($item['diskon'] ?? 0),
                'keterangan'    => trim($item['keterangan'] ?? ''),
            ];
        }
    }

    // Validate bahwa ada minimal 1 item jika ada error atau tidak ada items
    if (empty($dataItems)) {
        $errors[] = 'Minimal harus ada 1 item dalam pesanan.';
    }

    // If no errors, create PO with items
    if (!$errors) {
        try {
            $poId = PO::createWithItems($dataPO, $dataItems);
            $success = true;
            echo "<script>
                alert('Pesanan berhasil dibuat!');
                window.location.href = '../index.php?success=1';
            </script>";
            exit;
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan pesanan: ' . $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buat Pesanan Baru | InventorySys</title>
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
    
    .items-table .text-subtotal {
      font-weight: 600;
      color: var(--accent);
      text-align: right;
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
    
    .btn-add-row {
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
      color: #2563eb;
      border: 1.5px dashed #2563eb;
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.25s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn-add-row:hover {
      background: #2563eb;
      color: #fff;
      border-color: #2563eb;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }
    
    .grand-total-box {
      background: linear-gradient(135deg, var(--surface) 0%, var(--surface2) 100%);
      padding: 18px 20px;
      border-radius: 10px;
      border: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .grand-total-label {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text2);
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    .grand-total-val {
      font-size: 1.6rem;
      font-weight: 800;
      color: #10b981;
      font-family: 'Inter', monospace;
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
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: #fff;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #1d4ed8, #1e40af);
      box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
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
      .grand-total-box { flex-direction: column; gap: 12px; align-items: flex-start; }
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
          <a href="../index.php">Purchase Order</a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah Order</span>
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

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg"><i class="bi bi-file-earmark-arrow-down" style="margin-right: 10px; color: var(--accent);"></i>Buat Pesanan Baru</h1>
        <p class="page-subtitle">Input data Purchase Order (PO) beserta rincian barangnya dengan mudah dan cepat.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali ke Daftar</a>
    </div>

    <?php if ($errors): ?>
      <div class="alert-error" style="margin-bottom: 20px;">
        <i class="bi bi-exclamation-circle"></i>
        <ul style="margin: 0; padding-left: 20px;">
          <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" id="poForm">
      <div class="form-card" style="margin-bottom: 24px;">
        <div class="form-section-header">
          <i class="bi bi-file-earmark-text"></i> Data Utama Order
        </div>
        
        <div class="form-grid-3">
          <div class="form-group">
            <label class="form-label">Nomor PO <span class="required">*</span></label>
            <input type="text" name="nomor_po" class="form-control" value="<?= htmlspecialchars(($_POST['nomor_po'] ?? $autoNomorPo) ?: '') ?>" readonly style="background: var(--bg-body); cursor: not-allowed;">
            <small style="color: var(--text3); font-size: 0.8rem;">Format: PO-MMYY-NNN (otomatis, misal: PO-1126-001)</small>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal <span class="required">*</span></label>
            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars(($_POST['tanggal'] ?? date('Y-m-d')) ?: '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status <span class="required">*</span></label>
            <select name="status" class="form-control" required>
              <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft</option>
              <option value="approved" <?= (($_POST['status'] ?? '') === 'approved') ? 'selected' : '' ?>>Approved</option>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Pilih Customer <span class="required">*</span></label>
            <select name="customer_id" class="form-control" required>
              <option value="">-- Pilih Customer --</option>
              <?php foreach ($customerList as $cust): ?>
                <option value="<?= $cust['id'] ?>" <?= (intval($_POST['customer_id'] ?? 0) === $cust['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars(($cust['perusahaan'] ?? $cust['nama'] ?? '') ?: 'Unknown') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Catatan Tambahan</label>
            <input type="text" name="notes" class="form-control" placeholder="Instruksi pengiriman dll..." value="<?= htmlspecialchars(($_POST['notes'] ?? '') ?: '') ?>">
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-box-seam"></i> Rincian Barang
        </div>

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--border);">
          <table class="items-table">
            <thead>
              <tr>
                <th style="width: 4%; text-align: center;">#</th>
                <th style="width: 28%;">Pilih Produk <span class="required">*</span></th>
                <th style="width: 12%;">Qty <span class="required">*</span></th>
                <th style="width: 18%;">Harga Satuan <span class="required">*</span></th>
                <th style="width: 10%;">Diskon (%)</th>
                <th style="width: 18%; text-align: right;">Subtotal</th>
                <th style="width: 10%; text-align: center;">Aksi</th>
              </tr>
            </thead>
            <tbody id="itemsBody">
            </tbody>
          </table>
        </div>

        <button type="button" class="btn-add-row" id="addItemBtn">
          <i class="bi bi-plus-circle"></i> Tambah Baris Barang
        </button>

        <div class="grand-total-box">
          <span class="grand-total-label">Total Pembayaran</span>
          <span class="grand-total-val" id="grandTotalText">Rp 0</span>
        </div>

        <div class="form-actions-bottom">
          <a href="../index.php" class="btn-outline">
            <i class="bi bi-x-circle"></i> Batal
          </a>
          <button type="submit" class="btn-primary">
            <i class="bi bi-save"></i> Simpan Purchase Order
          </button>
        </div>
      </div>

    </form>
  </div>
</main>

<script>
  // Tunggu DOM siap sebelum initialize
  document.addEventListener('DOMContentLoaded', function() {
    // Data Master Produk dari PHP
    const produkList = <?php echo json_encode($produkList ?? []); ?>;
    const tbody = document.getElementById('itemsBody');
    const addItemBtn = document.getElementById('addItemBtn');
    const poForm = document.getElementById('poForm');
    let rowCount = 0;

    // Format ke Rupiah
    const formatRp = (angka) => {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    };
    
    // Bersihin string ke Angka Murni
    const parseAngka = (str) => {
      return parseInt(str.toString().replace(/[^0-9]/g, ''), 10) || 0;
    };

    // Fungsi hitung Subtotal & Grand Total
    function hitungTotal() {
      let grandTotal = 0;
      document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseInt(row.querySelector('.input-qty').value) || 0;
        const harga = parseAngka(row.querySelector('.input-harga').value);
        const diskon = parseFloat(row.querySelector('.input-diskon').value) || 0;
        
        const subtotalKotor = qty * harga;
        const potongan = subtotalKotor * (diskon / 100);
        const subtotalBersih = subtotalKotor - potongan;
        
        row.querySelector('.text-subtotal').textContent = formatRp(subtotalBersih);
        grandTotal += subtotalBersih;
      });
      document.getElementById('grandTotalText').textContent = formatRp(grandTotal);
    }

    // Bikin Elemen Baris Baru
    function createRow() {
      const tr = document.createElement('tr');
      tr.className = 'item-row';
      const currentRowCount = rowCount;
      
      tr.innerHTML = `
        <td class="row-num">${currentRowCount + 1}</td>
        <td>
          <select name="items[${currentRowCount}][produk_id]" class="form-control select-produk" required>
            <option value="">-- Pilih Produk --</option>
            ${produkList.map(p => `<option value="${p.id}" data-harga="${p.harga || 0}" data-satuan="${p.satuan || 'pcs'}" data-kode="${p.kode_produk || p.kode || ''}" data-nama="${p.nama || ''}">${p.nama} (${p.kode_produk || p.kode || ''})</option>`).join('')}
          </select>
          <input type="hidden" name="items[${currentRowCount}][kode_material]" class="hidden-kode">
          <input type="hidden" name="items[${currentRowCount}][nama_material]" class="hidden-nama">
          <input type="hidden" name="items[${currentRowCount}][uom]" class="hidden-uom" value="pcs">
        </td>
        <td><input type="number" name="items[${currentRowCount}][qty]" class="form-control input-qty" min="1" required placeholder="0"></td>
        <td><input type="text" name="items[${currentRowCount}][harga_satuan]" class="form-control input-harga" required placeholder="Rp 0"></td>
        <td><input type="number" name="items[${currentRowCount}][diskon]" class="form-control input-diskon" min="0" max="100" step="0.01" value="0" placeholder="0"></td>
        <td class="text-subtotal">Rp 0</td>
        <td style="text-align: center;">
          <button type="button" class="btn-icon btn-delete" tabindex="-1" title="Hapus Baris"><i class="bi bi-trash"></i></button>
        </td>
      `;
      
      // EVENT LISTENERS UNTUK BARIS INI
      // 1. Saat Pilih Produk
      const selectProduk = tr.querySelector('.select-produk');
      selectProduk.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
          tr.querySelector('.hidden-kode').value = opt.dataset.kode;
          tr.querySelector('.hidden-nama').value = opt.dataset.nama;
          tr.querySelector('.hidden-uom').value = opt.dataset.satuan;
          tr.querySelector('.input-harga').value = parseInt(opt.dataset.harga).toLocaleString('id-ID');
          hitungTotal();
        }
      });

      // 2. Saat QTY, Harga, atau Diskon berubah
      const hitungTriggers = tr.querySelectorAll('.input-qty, .input-harga, .input-diskon');
      hitungTriggers.forEach(input => {
        input.addEventListener('input', function() {
          if(this.classList.contains('input-harga')) {
            // Auto format titik pas ngetik harga
            let val = this.value.replace(/[^0-9]/g, '');
            if(val) this.value = parseInt(val).toLocaleString('id-ID');
          }
          hitungTotal();
        });
        
        // Format harga saat blur
        if(input.classList.contains('input-harga')) {
          input.addEventListener('blur', function() {
            let val = this.value.replace(/[^0-9]/g, '');
            if(val) this.value = parseInt(val).toLocaleString('id-ID');
          });
        }
      });

      // 3. Tombol Hapus Baris
      const deleteBtn = tr.querySelector('.btn-delete');
      deleteBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const rows = document.querySelectorAll('.item-row');
        if(rows.length > 1) {
          tr.remove();
          hitungTotal();
          // Benerin urutan nomor
          document.querySelectorAll('.row-num').forEach((td, idx) => td.textContent = idx + 1);
        } else {
          alert('Pesanan harus memiliki minimal 1 barang!');
        }
      });

      tbody.appendChild(tr);
      rowCount++;
    }

    // Event listener untuk Tombol Tambah Baris
    if (addItemBtn) {
      addItemBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        createRow();
      });
    }

    // Inisiasi 1 baris kosong pas pertama buka
    createRow();

    // Validasi sebelum submit form
    if (poForm) {
      poForm.addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('.item-row');
        if(rows.length === 0) {
          e.preventDefault();
          alert('Keranjang pesanan masih kosong!');
          return false;
        }
      });
    }

    // Dark mode toggle bawaan Celebit
    const html = document.documentElement;
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        themeBtn.querySelector('i').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
      });
    }
  });
</script>

</body>
</html>