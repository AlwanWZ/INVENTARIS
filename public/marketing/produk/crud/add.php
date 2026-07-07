<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/Produk.php';
require_once '../../../../src/functions.php';

$errors = [];

// Load kategori list
$kategoriList = [];
try {
    $kategoriList = $pdo->query("SELECT id, nama_kategori, prefix_kode FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$kategoriIdSelected = !empty($kategoriList) ? (int)$kategoriList[0]['id'] : 1;

// =======================================================
// 🚀 LOGIKA AUTO-GENERATE KODE (SUPER AMAN VIA PHP)
// =======================================================
$maxUrutan = 0;

try {
    // 1. Ambil semua kode yang depannya PCB- dari kolom 'kode'
    $stmtKode = $pdo->query("SELECT kode FROM produk WHERE kode LIKE 'PCB-%'");
    $listKode = $stmtKode->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Kalau ternyata kosong, coba cari di kolom 'kode_produk' (jaga-jaga nama kolom beda)
    if (empty($listKode)) {
        $stmtKode2 = $pdo->query("SELECT kode_produk FROM produk WHERE kode_produk LIKE 'PCB-%'");
        $listKode = $stmtKode2->fetchAll(PDO::FETCH_COLUMN);
    }

    // 3. Ekstrak angkanya dan cari yang paling gede
    foreach ($listKode as $k) {
        // Buang tulisan 'PCB-' dan ambil sisa angkanya
        $angka = (int) str_replace('PCB-', '', $k);
        if ($angka > $maxUrutan) {
            $maxUrutan = $angka;
        }
    }
} catch (Exception $e) {
    // Abaikan error database jika kolom tidak ditemukan
}

// 4. Tambah 1 dari angka terbesar yang ketemu
$urutanBaru = $maxUrutan + 1;
// 5. Format jadi PCB-001, PCB-002, dst
$autoKodeBarang = 'PCB-' . str_pad($urutanBaru, 3, '0', STR_PAD_LEFT);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stok_input = (int)($_POST['stok'] ?? 0);
    
    $data = [
        'kode'        => trim($_POST['kode'] ?? ''),
        'nama'        => trim($_POST['nama'] ?? ''),
        'kategori_id' => $kategoriIdSelected,
        'stok'        => $stok_input,
        'stok_min'    => (int)($_POST['stok_min'] ?? 10),
        'satuan'      => trim($_POST['satuan'] ?? 'pcs'),
        'harga'       => (int)($_POST['harga'] ?? 0),
        'harga_jual'  => (float)($_POST['harga_jual'] ?? 0),
        'status'      => $_POST['status'] ?? 'aktif',
    ];
    
    if (!$data['kode']) $errors[] = 'Kode barang wajib diisi.';
    if (!$data['nama']) $errors[] = 'Nama barang wajib diisi.';
    if ($data['kategori_id'] <= 0) $errors[] = 'Kategori tidak valid di database.';
    if ($data['stok'] < 0) $errors[] = 'Stok tidak boleh negatif.';
    
    if (!$errors) {
        try {
            Produk::create($data);
            echo "<script>
                alert('✅ Barang berhasil ditambahkan!');
                window.location.href = '../index.php?success=1';
            </script>";
            exit;
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan barang: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Barang PCB | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/produk.css" rel="stylesheet">
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
          <a href="../index.php">Barang PCB</a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah Barang</span>
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
        <h1 class="page-title-lg">Tambah Barang</h1>
        <p class="page-subtitle">Isi formulir berikut untuk menambahkan barang PCB baru.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-box-seam"></i> Data Barang</h4>
          </div>

          <?php if ($errors): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>    

          <form method="post" class="po-form" id="produkForm">
            <input type="hidden" name="kategori_id" value="<?= $kategoriIdSelected ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Kode Barang <span class="required">*</span></label>
                <input type="text" name="kode" id="kodeInput" class="form-control"
                       value="<?= htmlspecialchars($_POST['kode'] ?? $autoKodeBarang) ?>" readonly style="background: var(--bg-body); cursor: not-allowed; font-weight: 800; color: #0d9488;">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">Format: PCB-NNN (Otomatis)</small>
              </div>
              <div class="form-group">
                <label class="form-label">Nama Barang <span class="required">*</span></label>
                <input type="text" name="nama" class="form-control" placeholder="Nama lengkap barang"
                       value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required autofocus>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">HPP (Rp)</label>
                <input type="text" name="harga" id="hargaInput" class="form-control" placeholder="0" value="<?= (int)($_POST['harga'] ?? 0) ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                <small id="hargaPreview" class="text-muted" style="display:block;margin-top:.25rem;font-weight:600;color:#059669!important;">
                    Preview HPP : Rp <?= number_format((int)($_POST['harga'] ?? 0),0,',','.') ?>
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Harga Jual (Rp)</label>
                <input type="text" name="harga_jual" id="hargaJualInput" class="form-control" placeholder="0" value="<?= (int)($_POST['harga_jual'] ?? 0) ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                <small id="hargaJualPreview" class="text-muted" style="display:block;margin-top:.25rem;font-weight:600;color:#2563eb!important;">
                    Preview Harga Jual : Rp <?= number_format((int)($_POST['harga_jual'] ?? 0),0,',','.') ?>
                </small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Stok Fisik Awal</label>
                <input type="number" name="stok" class="form-control" placeholder="0" min="0" value="<?= (int)($_POST['stok'] ?? 0) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Batas Stok Minimum</label>
                <input type="number" name="stok_min" class="form-control" placeholder="10" min="0" value="<?= (int)($_POST['stok_min'] ?? 10) ?>">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">Peringatan 'Kritis' jika stok di bawah angka ini.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Satuan (UOM)</label>
                <input type="text" name="satuan" class="form-control" placeholder="pcs, sheet, roll..." value="<?= htmlspecialchars($_POST['satuan'] ?? 'pcs') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php foreach (['aktif' => 'Aktif', 'nonaktif' => 'Tidak Aktif'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= (($_POST['status'] ?? 'aktif') === $val) ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Barang</button>
              <a href="../index.php" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

      <div class="form-side">
        <div class="form-card info-card">
          <div class="form-card-header">
            <h4><i class="bi bi-info-circle"></i> Panduan</h4>
          </div>
          <ul class="info-list">
            <li><i class="bi bi-dot"></i> <strong>Kategori & Kode:</strong> Sistem telah otomatis menetapkan kategori PCB dan nomor urut berdasarkan database terakhir.</li>
            <li><i class="bi bi-dot"></i> <strong>Stok Fisik Awal</strong> akan langsung menjadi Stok Tersedia (Available) karena belum ada pesanan.</li>
            <li><i class="bi bi-dot"></i> Barang dengan status <strong>Tidak Aktif</strong> tidak akan muncul saat membuat pesanan (PO).</li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
function applyCurrencyFormat(inputId, previewId, labelPrefix) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    if (input && preview) {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            
            if (value === '') {
                preview.innerHTML = `Preview ${labelPrefix} : Rp 0`;
                return;
            }

            const formatted = new Intl.NumberFormat('id-ID').format(value);
            preview.innerHTML = `Preview ${labelPrefix} : Rp ${formatted}`;
        });
    }
}

applyCurrencyFormat('hargaInput', 'hargaPreview', 'HPP');
applyCurrencyFormat('hargaJualInput', 'hargaJualPreview', 'Harga Jual');
</script>

</body>
</html>