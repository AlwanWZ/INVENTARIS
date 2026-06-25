<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';

// Pastiin tidak ada cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$verifModel = new Verifikasi($pdo);

// 1. Ambil data dari SPK
try {
    $spkList = $pdo->query("SELECT id, nomor_spk, tanggal, status FROM spk ORDER BY id DESC")
                   ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $spkList = [];
}

$errors = [];
$items  = [];
$isSubmit = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']);

// 2. Tarik items berdasarkan spk_id yang dipilih (Auto-fill)
$selectedSpk = $_POST['spk_id'] ?? $_GET['spk_id'] ?? '';
if ($selectedSpk) {
    /* * PERBAIKAN DI SINI: 
     * Mengubah qty_schedule/qty_po menjadi kolom 'qty'.
     * CATATAN: Pastikan kolom 'qty' ini sesuai dengan yang ada di tabel 'spk_items' DB kamu. 
     * Jika namanya beda (misal: 'jumlah' atau 'target_qty'), ubah tulisan 'si.qty' di bawah.
     */
    $stmt = $pdo->prepare("SELECT si.id, si.spk_id, si.produk_id, 
                                  COALESCE(si.qty_po, 0) as qty_diterima, 
                                  pr.nama AS produk_nama 
                           FROM spk_items si 
                           LEFT JOIN produk pr ON si.produk_id = pr.id 
                           WHERE si.spk_id = ? AND si.produk_id IS NOT NULL
                           ORDER BY si.id");
    $stmt->execute([$selectedSpk]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isSubmit) {
    $data = [
        'spk_id'        => $_POST['spk_id'] ?? null,
        'penerimaan_id' => null,                      // Kosongkan untuk Finish Good
        'tanggal'       => $_POST['tanggal']       ?? date('Y-m-d'),
        'pic'           => $_SESSION['user']['id'] ?? null,
        'status'        => 'draft',
        'jenis'         => 'finish_good',
        'keterangan'    => trim($_POST['keterangan'] ?? ''),
    ];
    $data['items'] = $_POST['items'] ?? [];

    if (!$data['spk_id'])  $errors[] = 'SPK wajib dipilih.';
    if (!$data['tanggal']) $errors[] = 'Tanggal wajib diisi.';
    
    $filteredItems = [];
    foreach ($data['items'] as $i => $item) {
        $qty_ok    = (int)($item['qty_ok']    ?? 0);
        $qty_masuk = (int)($item['qty_masuk'] ?? 0);
        
        if ($qty_ok > 0) {
            if ($qty_ok > $qty_masuk) {
                $errors[] = 'Qty OK tidak boleh melebihi target produksi untuk produk ke-'.($i+1).'.';
            } else {
                $filteredItems[] = $item;
            }
        }
    }
    $data['items'] = $filteredItems;
    
    if (empty($data['items'])) $errors[] = 'Minimal 1 item produk dengan Qty OK > 0 harus diisi.';
    
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
  <title>Tambah Finish Good (SPK) | InventorySys</title>
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
          <a href="../index.php">Finish Good</a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah dari SPK</span>
        </div>
      </div>
      <div class="top-right">
        <button id="themeToggle" class="theme-btn"><i class="bi bi-moon"></i></button>
        <div class="user-box">
          <div class="user-avatar"><?= strtoupper(substr($_SESSION['user']['username'] ?? 'U', 0, 1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'User') ?></span>
            <span class="user-role">Gudang</span>
          </div>
        </div>
      </div>
    </div>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Barang Masuk (Finish Good)</h1>
        <p class="page-subtitle">Tarik otomatis data produk dari SPK produksi ke Gudang.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
      <div class="form-layout">
        <div class="form-main">

          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-clipboard-check"></i> Data SPK (Surat Perintah Kerja)</h4>
            </div>

            <?php if ($errors): ?>
            <div class="alert-error" style="margin:16px 22px 0;">
              <i class="bi bi-exclamation-circle"></i>
              <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <div class="po-form">
              <div class="form-group">
                <label class="form-label">Pilih Nomor SPK <span class="required">*</span></label>
                <select name="spk_id" class="form-control" onchange="this.form.submit()" required>
                  <option value="">— Ketik atau Pilih Nomor SPK —</option>
                  <?php foreach ($spkList as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSpk == $s['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($s['nomor_spk']) ?> (<?= $s['tanggal'] ?>) - <?= $s['status'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="form-hint" style="font-size:0.78rem;color:var(--text3);">Daftar produk akan otomatis muncul sesuai SPK yang dipilih.</span>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Tanggal Masuk Gudang <span class="required">*</span></label>
                  <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                </div>
              </div>  
            </div>
          </div>

          <?php if ($items): ?>
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-list-check"></i> List Produk Finish Good</h4>
              <span class="count-badge"><?= count($items) ?> produk</span>
            </div>
            <div class="table-wrap">
              <table class="item-table">
                <thead>
                  <tr>
                    <th>Produk / Barang</th>
                    <th class="col-center">Target SPK</th>
                    <th class="col-center">Jml Masuk <span style="color:#16a34a">✓</span></th>
                    <th>Catatan (Opsional)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $i => $item): 
                        $defaultQty = $isSubmit ? (int)($_POST['items'][$i]['qty_ok'] ?? 0) : (int)$item['qty_diterima']; 
                        $defaultKet = $isSubmit ? htmlspecialchars($_POST['items'][$i]['keterangan'] ?? '') : '';
                  ?>
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
                             value="<?= $defaultQty ?>"
                             required>
                    </td>
                    <td>
                      <input type="text" name="items[<?= $i ?>][keterangan]" class="form-control"
                             value="<?= $defaultKet ?>"
                             placeholder="Keterangan...">
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="form-actions-bottom">
            <button type="submit" name="save" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan (Draft)</button>
            <a href="../index.php" class="btn-outline">Batal</a>
          </div>
          <?php endif; ?>

        </div>

        <div class="form-side">
          <div class="form-card info-card">
            <div class="form-card-header"><h4><i class="bi bi-info-circle"></i> Info Sistem</h4></div>
            <ul class="info-list">
              <li><i class="bi bi-dot"></i> <b>Direct SPK</b>: Pilih SPK, sistem otomatis menarik list produk.</li>
              <li><i class="bi bi-dot"></i> <b>Otomatis</b>: <i>Jml Masuk</i> akan terisi otomatis penuh sesuai target produksi. Bisa lu kurangi jika hasilnya kurang.</li>
              <li><i class="bi bi-dot"></i> <b>Stok</b>: Disimpan sebagai <b>Draft</b> dulu. Stok gudang bertambah saat status diubah jadi <b>Selesai</b>.</li>
            </ul>
          </div>
        </div>
      </div>
    </form>

  </div>
</main>

</body>
</html>