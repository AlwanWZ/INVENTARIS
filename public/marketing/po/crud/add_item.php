<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/models/Produk.php';

$po = PO::find($_GET['po_id'] ?? null);
if (!$po) { header('Location: ../index.php'); exit; }

$produkList = Produk::all();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get produk data for auto-fill
    $produk = $_POST['produk_id'] ? Produk::find($_POST['produk_id']) : null;
    
    $data = [
        'po_id'         => $po['id'],
        'produk_id'     => intval($_POST['produk_id'] ?? 0),
        'kode_material' => $produk['kode_produk'] ?? trim($_POST['kode_material'] ?? ''),
        'nama_material' => $produk['nama'] ?? trim($_POST['nama_material'] ?? ''),
        'uom'           => $_POST['uom'] ?? 'pcs',
        'qty'           => intval($_POST['qty'] ?? 0),
        'harga_satuan'  => intval(str_replace(['.', ','], '', $_POST['harga_satuan'] ?? $produk['harga'] ?? 0)),
        'diskon'        => floatval($_POST['diskon'] ?? 0),
        'keterangan'    => trim($_POST['keterangan'] ?? ''),
    ];
    
    if (!$data['qty']) $errors[] = 'Qty wajib diisi dan harus > 0.';
    if (!$data['harga_satuan']) $errors[] = 'Harga satuan wajib diisi.';
    if (!$data['kode_material']) $errors[] = 'Kode material wajib diisi.';
    if (!$data['nama_material']) $errors[] = 'Nama material wajib diisi.';
    
    if (!$errors) {
        PO::addItem($data);
        header('Location: detail.php?id=' . $po['id'] . '&item_added=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Item Pesanan | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/po.css" rel="stylesheet">
</head>
<body>

<?php include '../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Pesanan PCB</a>
          <i class="bi bi-chevron-right"></i>
          <a href="detail.php?id=<?= $po['id'] ?>"><?= htmlspecialchars($po['nomor_po']) ?></a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah Item</span>
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
        <h1 class="page-title-lg">Tambah Item Pesanan</h1>
        <p class="page-subtitle">Tambahkan produk/PCB ke pesanan <strong><?= htmlspecialchars($po['nomor_po']) ?></strong> dari <strong><?= htmlspecialchars($po['perusahaan'] ?? '-') ?></strong></p>
      </div>
      <a href="detail.php?id=<?= $po['id'] ?>" class="btn-ghost-sm">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
    </div>

    <!-- FORM LAYOUT -->
    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-box-seam"></i> Data Item</h4>
          </div>

          <?php if ($errors): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle"></i>
              <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>

          <form method="post" class="po-form" id="addItemForm">
            <div class="form-group">
              <label class="form-label">Pilih Produk PCB <span class="required">*</span></label>
              <select name="produk_id" class="form-control" id="produkSelect" onchange="autofillProduk()">
                <option value="">-- Pilih Produk --</option>
                <?php foreach ($produkList as $prod): ?>
                  <option value="<?= $prod['id'] ?>" data-kode="<?= htmlspecialchars($prod['kode_produk']) ?>" data-nama="<?= htmlspecialchars($prod['nama']) ?>" data-harga="<?= $prod['harga'] ?>">
                    <?= htmlspecialchars($prod['kode_produk'] . ' - ' . $prod['nama']) ?>
                    (Rp <?= number_format($prod['harga'], 0, ',', '.') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small style="color: #6c757d;">Atau bisa input manual di bawah</small>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Kode Material <span class="required">*</span></label>
                <input type="text" name="kode_material" class="form-control" id="kodeMaterial" placeholder="A001, PCB-A, dll" value="<?= htmlspecialchars($_POST['kode_material'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Nama Material <span class="required">*</span></label>
                <input type="text" name="nama_material" class="form-control" id="namaMaterial" placeholder="PCB Type A, PCB Merah, dll" value="<?= htmlspecialchars($_POST['nama_material'] ?? '') ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">UOM <span class="required">*</span></label>
                <input type="text" name="uom" class="form-control" value="<?= htmlspecialchars($_POST['uom'] ?? 'pcs') ?>" placeholder="pcs, unit, sheet">
              </div>
              <div class="form-group">
                <label class="form-label">Qty <span class="required">*</span></label>
                <input type="number" name="qty" class="form-control" min="1" value="<?= intval($_POST['qty'] ?? '') ?>" placeholder="100" required onchange="calculateAmount()">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Harga Satuan <span class="required">*</span></label>
                <input type="text" name="harga_satuan" class="form-control" id="hargaSatuan" placeholder="500000" value="<?= $_POST['harga_satuan'] ?? '' ?>" required onchange="calculateAmount()">
                <small style="color: #6c757d;">Dalam format angka (contoh: 500000)</small>
              </div>
              <div class="form-group">
                <label class="form-label">Diskon (%)</label>
                <input type="number" name="diskon" class="form-control" min="0" max="100" step="0.01" value="<?= floatval($_POST['diskon'] ?? 0) ?>" placeholder="0" onchange="calculateAmount()">
                <small style="color: #6c757d;">Diskon dalam persen (0-100)</small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Keterangan (Opsional)</label>
              <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan tentang item ini..."><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Total Amount</label>
              <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 4px; border: 1px solid #ddd; font-size: 1.1rem; font-weight: 500; color: #333;">
                Rp <span id="totalAmount">0</span>
              </div>
              <small style="color: #6c757d;">Otomatis hitung: (Qty × Harga Satuan) - Diskon</small>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Tambah Item
              </button>
              <a href="detail.php?id=<?= $po['id'] ?>" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <!-- SIDE PANEL -->
      <div class="form-side">
        <div class="form-card info-card">
          <div class="form-card-header">
            <h4><i class="bi bi-info-circle"></i> Panduan</h4>
          </div>
          <div style="padding: 0 1rem;">
            <p><strong>Cara Menambah Item:</strong></p>
            <ol style="font-size: 0.9rem; color: #495057; margin-bottom: 1rem; padding-left: 1.25rem; line-height: 1.8;">
              <li>Pilih produk dari list (atau input manual)</li>
              <li>Isi kode & nama material</li>
              <li>Tentukan UOM (pcs, unit, sheet, dll)</li>
              <li>Isi quantity yang dipesan</li>
              <li>Harga satuan akan otomatis dari produk</li>
              <li>Tambahkan diskon jika ada (%)</li>
              <li>Total amount otomatis terhitung</li>
            </ol>

            <p><strong>Catatan:</strong></p>
            <ul class="info-list" style="font-size: 0.9rem;">
              <li><i class="bi bi-dot"></i> Harga satuan bisa diedit sesuai deal</li>
              <li><i class="bi bi-dot"></i> Diskon dihitung per item</li>
              <li><i class="bi bi-dot"></i> Total pesanan = sum semua items</li>
              <li><i class="bi bi-dot"></i> Bisa tambah multiple items ke 1 PO</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
function autofillProduk() {
  const select = document.getElementById('produkSelect');
  const option = select.options[select.selectedIndex];
  
  if (option.value) {
    document.getElementById('kodeMaterial').value = option.getAttribute('data-kode');
    document.getElementById('namaMaterial').value = option.getAttribute('data-nama');
    document.getElementById('hargaSatuan').value = option.getAttribute('data-harga');
    calculateAmount();
  }
}

function calculateAmount() {
  const qty = parseInt(document.querySelector('input[name="qty"]').value) || 0;
  const harga = parseInt(document.querySelector('input[name="harga_satuan"]').value.replace(/\D/g, '')) || 0;
  const diskon = parseFloat(document.querySelector('input[name="diskon"]').value) || 0;
  
  const subtotal = qty * harga;
  const diskonAmount = subtotal * (diskon / 100);
  const total = subtotal - diskonAmount;
  
  document.getElementById('totalAmount').textContent = new Intl.NumberFormat('id-ID').format(Math.round(total));
}

// Hitung saat form muncul
window.addEventListener('load', calculateAmount);
</script>

</body>
</html>
