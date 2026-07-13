<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/Barang.php';
require_once '../../../../src/functions.php';

$errors = [];

// Load kategori list (Aman meskipun tabel kategori sudah dihapus)
$kategoriList = [];
try {
    $kategoriList = $pdo->query("SELECT id, nama_kategori, prefix_kode FROM kategori ORDER BY nama_kategori")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Abaikan jika tabel kategori tidak ditemukan
}

$kategoriIdSelected = !empty($kategoriList) ? (int)$kategoriList[0]['id'] : 0;

// =======================================================
// 🚀 LOGIKA AUTO-GENERATE KODE (SUPER AMAN VIA PHP)
// =======================================================
$maxUrutan = 0;

try {
    // 1. Ambil semua kode yang depannya PCB- dari tabel 'barang' (kolom kode_barang)
    $stmtKode = $pdo->query("SELECT kode_barang FROM barang WHERE kode_barang LIKE 'PCB-%'");
    $listKode = $stmtKode->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Fallback: Kalau kosong, coba cari di kolom lama 'kode_produk' atau 'kode'
    if (empty($listKode)) {
        try {
            $stmtKode2 = $pdo->query("SELECT kode_produk FROM barang WHERE kode_produk LIKE 'PCB-%'");
            $listKode = $stmtKode2->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $ex) {
            try {
                $stmtKode3 = $pdo->query("SELECT kode FROM barang WHERE kode LIKE 'PCB-%'");
                $listKode = $stmtKode3->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $ex2) {}
        }
    }

    // 3. Ekstrak angkanya dan cari yang paling besar
    if (!empty($listKode)) {
        foreach ($listKode as $k) {
            // Buang tulisan 'PCB-' dan ambil sisa angkanya
            $angka = (int) str_replace('PCB-', '', $k);
            if ($angka > $maxUrutan) {
                $maxUrutan = $angka;
            }
        }
    }
} catch (Exception $e) {
    // Abaikan error database jika kolom/tabel belum siap
}

// 4. Tambah 1 dari angka terbesar yang ketemu
$urutanBaru = $maxUrutan + 1;
// 5. Format jadi PCB-001, PCB-002, dst
$autoKodeBarang = 'PCB-' . str_pad($urutanBaru, 3, '0', STR_PAD_LEFT);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stok_input = (int)($_POST['stok'] ?? 0);
    
    $data = [
        'kode_barang' => trim($_POST['kode_barang'] ?? $_POST['kode'] ?? ''),
        'nama'        => trim($_POST['nama'] ?? ''),
        'kategori_id' => $kategoriIdSelected > 0 ? $kategoriIdSelected : null,
        'kategori'    => 'PCB', // String default untuk keamanan jika tabel kategori dihapus
        'stok'        => $stok_input,
        'stok_min'    => (int)($_POST['stok_min'] ?? 10),
        'ukuran'      => trim($_POST['ukuran'] ?? ''),
        'satuan'      => trim($_POST['satuan'] ?? 'pcs'),
        'harga'       => (int)($_POST['harga'] ?? 0),
        'harga_jual'  => (float)($_POST['harga_jual'] ?? 0),
        'status'      => $_POST['status'] ?? 'aktif',
    ];
    
    if (!$data['kode_barang']) $errors[] = 'Kode barang wajib diisi.';
    if (!$data['nama']) $errors[] = 'Nama barang wajib diisi.';
    if ($data['stok'] < 0) $errors[] = 'Stok tidak boleh negatif.';
    
    if (!$errors) {
        try {
            Barang::create($data);
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
  
  <!-- Load CSS Utama -->
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <!-- Fallback: Coba load barang.css atau produk.css jika belum ter-rename -->
  <link href="/Inventaris/public/assets/css/marketing-css/barang.css" rel="stylesheet" onerror="this.onerror=null;this.href='/Inventaris/public/assets/css/marketing-css/produk.css';">

  <style>
    /* 🛡️ JURUS PENGAMAN UI (Biar form tetap rapi & berkelas meski CSS eksternal ada masalah load) */
    .form-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 992px) { .form-layout { grid-template-columns: 1fr; } }
    .form-card { background: var(--surface, #ffffff); border: 1px solid var(--border, #e2e8f0); border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
    .form-card-header { border-bottom: 1px solid var(--border, #e2e8f0); padding-bottom: 1rem; margin-bottom: 1.25rem; }
    .form-card-header h4 { margin: 0; font-size: 1.1rem; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 576px) { .form-row { grid-template-columns: 1fr; } }
    .form-group { display: flex; flex-direction: column; margin-bottom: 1rem; }
    .form-label { font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem; }
    .form-control { padding: 0.6rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s; background: var(--bg, #fff); color: var(--text, #0f172a); }
    .form-control:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
    .required { color: #ef4444; }
    .form-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border, #e2e8f0); }
    .btn-primary { background: #0d9488; color: #fff; padding: 0.6rem 1.4rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: background 0.2s; }
    .btn-primary:hover { background: #0f766e; }
    .btn-outline { background: transparent; color: #64748b; border: 1px solid #cbd5e1; padding: 0.6rem 1.4rem; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s; }
    .btn-outline:hover { background: #f1f5f9; color: #0f172a; }
    .info-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem; color: #475569; }
    .info-list li { display: flex; align-items: flex-start; gap: 0.25rem; line-height: 1.4; }
    .info-list i { color: #0d9488; font-size: 1.2rem; line-height: 1; }
    .alert-error { background: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; color: #991b1b; display: flex; gap: 0.75rem; align-items: flex-start; }
    .alert-error ul { margin: 0; padding-left: 1.2rem; }
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

          <!-- PERBAIKAN: class diganti jadi barang-form dan id jadi barangForm -->
          <form method="post" class="barang-form" id="barangForm">
            <input type="hidden" name="kategori_id" value="<?= $kategoriIdSelected ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Kode Barang <span class="required">*</span></label>
                <!-- PERBAIKAN: name menggunakan kode_barang -->
                <input type="text" name="kode_barang" id="kodeInput" class="form-control"
                       value="<?= htmlspecialchars($_POST['kode_barang'] ?? $_POST['kode'] ?? $autoKodeBarang) ?>" readonly style="background: var(--bg-body); cursor: not-allowed; font-weight: 800; color: #0d9488;">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">Format: PCB-NNN (Otomatis dari tabel Barang)</small>
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
                <input type="text" name="harga" id="hargaInput" class="form-control" placeholder="0" value="<?= isset($_POST['harga']) ? (int)$_POST['harga'] : '' ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                <small id="hargaPreview" class="text-muted" style="display:block;margin-top:.25rem;font-weight:600;color:#059669!important;">
                    Preview HPP : Rp <?= number_format((int)($_POST['harga'] ?? 0),0,',','.') ?>
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Harga Jual (Rp)</label>
                <input type="text" name="harga_jual" id="hargaJualInput" class="form-control" placeholder="0" value="<?= isset($_POST['harga_jual']) ? (int)$_POST['harga_jual'] : '' ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                <small id="hargaJualPreview" class="text-muted" style="display:block;margin-top:.25rem;font-weight:600;color:#2563eb!important;">
                    Preview Harga Jual : Rp <?= number_format((int)($_POST['harga_jual'] ?? 0),0,',','.') ?>
                </small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Stok Fisik Awal</label>
                <input type="number" name="stok" class="form-control" placeholder="0" min="0" value="<?= isset($_POST['stok']) ? (int)$_POST['stok'] : '' ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Batas Stok Minimum</label>
                <input type="number" name="stok_min" class="form-control" placeholder="10" min="0" value="<?= (int)($_POST['stok_min'] ?? 10) ?>">
                <small class="text-muted" style="display: block; margin-top: 0.25rem;">Peringatan 'Kritis' jika stok di bawah angka ini.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Ukuran <span class="required">*</span></label>
                <select name="ukuran" class="form-control" required>
                    <option value="">-- Pilih Ukuran --</option>
                    <?php 
                    $ukrOptions = ['284 × 236 mm', '192.43 × 189.8 mm', '192.43 × 190 mm'];
                    $selectedUkr = $_POST['ukuran'] ?? '';
                    foreach ($ukrOptions as $u): ?>
                        <option value="<?= htmlspecialchars($u) ?>" <?= $selectedUkr === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                    <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Satuan (UOM)</label>
                <input type="text" name="satuan" class="form-control" placeholder="pcs, sheet, roll..." value="<?= htmlspecialchars($_POST['satuan'] ?? 'pcs') ?>" required>
              </div>
            </div>
            
            <div class="form-row">
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
            <li><i class="bi bi-dot"></i> <strong>Kategori & Kode:</strong> Sistem telah otomatis menetapkan kategori PCB dan nomor urut berdasarkan tabel Barang terbaru.</li>
            <li><i class="bi bi-dot"></i> <strong>Stok Fisik Awal</strong> akan langsung menjadi Stok Tersedia (Available) karena belum ada pesanan.</li>
            <li><i class="bi bi-dot"></i> Barang dengan status <strong>Tidak Aktif</strong> tidak akan muncul saat membuat pesanan (Pesanan).</li>
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