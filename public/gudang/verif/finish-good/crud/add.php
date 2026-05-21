<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';

// Pastiin tidak ada cache dengan set header
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$verifModel     = new Verifikasi($pdo);
// Simple query - show all penerimaan
try {
    $penerimaanList = $pdo->query("SELECT id, nomor_penerimaan, tanggal, status FROM penerimaan ORDER BY id DESC")
                           ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $penerimaanList = [];
}

$errors = [];
$items  = [];

// Load items when penerimaan is selected (GET or non-save POST)
$selectedPenerimaan = $_POST['penerimaan_id'] ?? $_GET['penerimaan_id'] ?? '';
if ($selectedPenerimaan) {
    // Query dengan benar ke penerimaan_items dan ambil qty_diterima
    $stmt = $pdo->prepare("SELECT pi.id, pi.penerimaan_id, pi.produk_id, pi.qty_diterima, pr.nama AS produk_nama 
                           FROM penerimaan_items pi 
                           LEFT JOIN produk pr ON pi.produk_id = pr.id 
                           WHERE pi.penerimaan_id = ?
                           ORDER BY pi.id");
    $stmt->execute([$selectedPenerimaan]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
        'penerimaan_id' => $_POST['penerimaan_id'] ?? '',
        'tanggal'       => $_POST['tanggal']       ?? date('Y-m-d'),
        'pic'           => $_SESSION['user']['id'] ?? null,
        'status'        => 'draft',
        'jenis'         => 'finish_good',
        'keterangan'    => trim($_POST['keterangan'] ?? ''),
    ];
    $data['items'] = $_POST['items'] ?? [];

    if (!$data['penerimaan_id']) $errors[] = 'Penerimaan wajib dipilih.';
    if (!$data['tanggal'])       $errors[] = 'Tanggal wajib diisi.';
    
    // Filter items - only include items with qty_ok > 0
    $filteredItems = [];
    foreach ($data['items'] as $i => $item) {
        $qty_ok    = (int)($item['qty_ok']    ?? 0);
        $qty_masuk = (int)($item['qty_masuk'] ?? 0);
        
        if ($qty_ok > 0) {
            if ($qty_ok > $qty_masuk) {
                $errors[] = 'Qty OK tidak boleh melebihi Qty Masuk untuk produk ke-'.($i+1).'.';
            } else {
                $filteredItems[] = $item;
            }
        }
    }
    $data['items'] = $filteredItems;
    
    if (empty($data['items']))   $errors[] = 'Minimal 1 item produk dengan Qty OK > 0 harus diisi.';
    
    if (!$errors) {
        $verif_id = $verifModel->add($data, $data['items']);
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
  <title>Tambah Verifikasi Finish Good | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/verifikasi.css" rel="stylesheet">
</head>
<body>
<?php include '../../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/gudang/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Verifikasi FG</a>
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
        <h1 class="page-title-lg">Tambah Verifikasi Finish Good</h1>
        <p class="page-subtitle">Catat hasil QC penerimaan barang jadi.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
      <div class="form-layout">
        <div class="form-main">

          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-patch-check"></i> Data Verifikasi</h4>
            </div>

            <?php if ($errors): ?>
            <div class="alert-error" style="margin:16px 22px 0;">
              <i class="bi bi-exclamation-circle"></i>
              <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <div class="po-form">
              <div class="form-group">
                <label class="form-label">Pilih Penerimaan <span class="required">*</span></label>
                <select name="penerimaan_id" class="form-control" onchange="this.form.submit()" required>
                  <option value="">— Pilih Nomor Penerimaan —</option>
                  <?php foreach ($penerimaanList as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $selectedPenerimaan == $p['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($p['nomor_penerimaan']) ?> (<?= $p['tanggal'] ?>) - <?= $p['status'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="form-hint" style="font-size:0.78rem;color:var(--text3);">Item akan otomatis dimuat setelah memilih penerimaan.</span>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Tanggal Verifikasi <span class="required">*</span></label>
                  <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                </div>
              </div>  
            </div>
          </div>

          <?php if ($items): ?>
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-list-check"></i> Hasil QC per Produk</h4>
              <span class="count-badge"><?= count($items) ?> produk</span>
            </div>
            <div class="table-wrap">
              <table class="item-table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th class="col-center">Qty Masuk</th>
                    <th class="col-center">Qty OK <span style="color:#16a34a">✓</span></th>
                    <th>Keterangan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $i => $item): ?>
                  <tr>
                    <td>
                      <span class="fw-mid"><?= htmlspecialchars($item['produk_nama']) ?></span>
                      <input type="hidden" name="items[<?= $i ?>][produk_id]"  value="<?= (int)$item['produk_id'] ?>">
                      <input type="hidden" name="items[<?= $i ?>][qty_masuk]"  value="<?= (int)$item['qty_diterima'] ?>">
                    </td>
                    <td class="col-center text-muted"><?= (int)$item['qty_diterima'] ?></td>
                    <td>
                      <input type="number" name="items[<?= $i ?>][qty_ok]"
                             class="form-control qty-input" min="0" max="<?= (int)$item['qty_diterima'] ?>"
                             value="<?= (int)($_POST['items'][$i]['qty_ok'] ?? 0) ?>"
                             required>
                    </td>
                    <td>
                      <input type="text" name="items[<?= $i ?>][keterangan]" class="form-control"
                             value="<?= htmlspecialchars($_POST['items'][$i]['keterangan'] ?? '') ?>"
                             placeholder="Catatan item...">
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($items): ?>
          <div class="form-actions-bottom">
            <button type="submit" name="save" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Verifikasi</button>
            <a href="../index.php" class="btn-outline">Batal</a>
          </div>
          <?php endif; ?>

        </div>

        <div class="form-side">
          <div class="form-card info-card">
            <div class="form-card-header"><h4><i class="bi bi-info-circle"></i> Panduan QC</h4></div>
            <ul class="info-list">
              <li><i class="bi bi-dot"></i> Pilih nomor penerimaan terlebih dahulu.</li>
              <li><i class=\"bi bi-dot\"></i> Qty OK harus sesuai dengan Qty Masuk.</li>
              <li><i class=\"bi bi-dot\"></i> Barang OK akan masuk ke stok Finish Good otomatis.</li>
              <li><i class="bi bi-dot"></i> Status draft bisa diedit sebelum final.</li>
            </ul>
          </div>
        </div>
      </div>
    </form>

  </div>
</main>C

</body>
</html>
