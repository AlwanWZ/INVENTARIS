<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';
require_once '../../../../../src/models/SPK.php';

// Pastikan tidak ada cache browser
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$verifModel = new Verifikasi($pdo);

// 1. Ambil data dari SPK (Hanya tampilkan yang belum selesai / bukan cancelled)
try {
    $spkList = $pdo->query("SELECT id, nomor_spk, tanggal, status FROM spk WHERE status NOT IN ('completed', 'cancelled') ORDER BY id DESC")
                   ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $spkList = [];
}

$errors = [];
$items  = [];
$isSubmit = $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_draft']) || isset($_POST['save_complete']));

// 2. Tarik items berdasarkan spk_id yang dipilih (Auto-fill)
$selectedSpk = $_POST['spk_id'] ?? $_GET['spk_id'] ?? '';
if ($selectedSpk) {
    $stmt = $pdo->prepare("SELECT si.id, si.spk_id, COALESCE(si.produk_id, 0) as produk_id, 
                                  COALESCE(si.qty_outstanding, si.qty_po, 0) as qty_diterima, 
                                  COALESCE(pr.nama, si.nama_barang, 'Barang SPK') AS produk_nama,
                                  COALESCE(pr.satuan, 'pcs') AS produk_satuan
                           FROM spk_items si 
                           LEFT JOIN produk pr ON si.produk_id = pr.id 
                           WHERE si.spk_id = ? AND COALESCE(si.qty_outstanding, si.qty_po, 0) > 0
                           ORDER BY si.id");
    $stmt->execute([$selectedSpk]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($isSubmit) {
    $isComplete = isset($_POST['save_complete']);

    $userId = $_SESSION['user']['id'] ?? null;
    $data = [
        'spk_id'        => $_POST['spk_id'] ?? null,
        'penerimaan_id' => null,
        'tanggal'       => $_POST['tanggal'] ?? date('Y-m-d'),
        'pic'           => $userId,
        'pic_id'        => $userId,
        'user_id'       => $userId,
        
        // 🔥 PASTIKAN INI 'approved' BUKAN 'completed' AGAR TIDAK ERROR 1265 (Data Truncated)
        'status'        => $isComplete ? 'approved' : 'draft',
        
        'jenis'         => 'finish_good',
        'keterangan'    => trim($_POST['keterangan'] ?? ''),
    ];
    $data['items'] = $_POST['items'] ?? [];

    if (!$data['spk_id'])  $errors[] = 'SPK wajib dipilih.';
    if (!$data['tanggal']) $errors[] = 'Tanggal wajib diisi.';
    
    $filteredItems = [];
    foreach ($data['items'] as $i => $item) {
        $qty_ok    = (int)($item['qty_ok']    ?? $item['qty'] ?? 0);
        $qty_masuk = (int)($item['qty_masuk'] ?? 0);
        
        if ($qty_ok > 0) {
            if ($qty_ok > $qty_masuk) {
                $errors[] = 'Qty Masuk tidak boleh melebihi sisa target SPK pada baris ke-'.($i+1).'.';
            } else {
                $item['qty']    = $qty_ok;
                $item['qty_ok'] = $qty_ok;
                $item['jumlah'] = $qty_ok;
                $filteredItems[] = $item;
            }
        }
    }
    $data['items'] = $filteredItems;
    
    if (empty($data['items'])) $errors[] = 'Minimal 1 item produk dengan Jumlah Masuk > 0 harus diisi.';
    
    if (!$errors) {
        try {
            // 1. Simpan data verifikasi
            $verif_id = $verifModel->add($data, $data['items']);
            
            // 2. Jika sukses dan user milih langsung "SELESAI"
            if ($isComplete && $verif_id) {
                
                // 🔥 SOLUSI: KITA TIDAK PAKAI $pdo->beginTransaction() DI SINI AGAR TIDAK BENTROK!
                foreach ($data['items'] as $item) {
                    $qty_add     = (int)($item['qty_ok'] ?? $item['qty'] ?? 0);
                    $prod_id     = (int)($item['produk_id'] ?? 0);
                    $spk_item_id = (int)($item['spk_item_id'] ?? $item['id'] ?? 0);
                    
                    if ($prod_id > 0 && $qty_add > 0) {
                        $pdo->exec("UPDATE produk SET stok = stok + $qty_add, stok_available = stok_available + $qty_add WHERE id = $prod_id");
                    }
                    
                    if ($qty_add > 0) {
                        if ($spk_item_id > 0) {
                            $pdo->exec("UPDATE spk_items SET qty_outstanding = GREATEST(0, COALESCE(qty_outstanding, qty_po, 0) - $qty_add) WHERE id = $spk_item_id");
                        } else if ($prod_id > 0) {
                            $pdo->exec("UPDATE spk_items SET qty_outstanding = GREATEST(0, COALESCE(qty_outstanding, qty_po, 0) - $qty_add) WHERE spk_id = {$data['spk_id']} AND produk_id = $prod_id");
                        }
                    }
                }
                
                // Cek apakah target SPK sudah terpenuhi semua (outstanding habis)
                $cekSisa = $pdo->query("SELECT SUM(COALESCE(qty_outstanding, 0)) FROM spk_items WHERE spk_id = {$data['spk_id']}")->fetchColumn();
                if ($cekSisa <= 0) {
                    // Update header SPK langsung
                    $pdo->exec("UPDATE spk SET status = 'completed', progress = 100 WHERE id = {$data['spk_id']}");
                    $pdo->exec("UPDATE spk_items SET status_produksi = 'selesai', qty_outstanding = 0 WHERE spk_id = {$data['spk_id']}");
                }
            }

            // Jika semua lancar, langsung pindah halaman (Success)
            header('Location: ../index.php?success=1');
            exit;
            
        } catch (Exception $e) {
            // 🔥 SOLUSI: Hapus $pdo->rollback() untuk menghindari bentrok transaksi dengan model Verifikasi
            $errors[] = 'Gagal memproses data Finish Good: ' . $e->getMessage();
        }
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

    <form method="post" id="fgForm">
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
                      <?= htmlspecialchars($s['nomor_spk']) ?> (<?= $s['tanggal'] ?>) - Status: <?= strtoupper($s['status']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="form-hint" style="font-size:0.78rem;color:var(--text3);">Hanya menampilkan SPK dengan status On Progress / Draft.</span>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Tanggal Masuk Gudang <span class="required">*</span></label>
                  <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Catatan Tambahan</label>
                  <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Penerimaan kloter pertama..." value="<?= htmlspecialchars($_POST['keterangan'] ?? '') ?>">
                </div>
              </div>  
            </div>
          </div>

          <?php if ($selectedSpk && empty($items)): ?>
          <div class="alert-warn" style="margin-top:16px;">
            <i class="bi bi-exclamation-triangle"></i> Seluruh produk pada SPK ini sudah selesai diproduksi dan diterima gudang (Qty Outstanding = 0).
          </div>
          <?php endif; ?>

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
                    <th class="col-center">Sisa Target SPK</th>
                    <th class="col-center" style="width: 160px;">Jml Masuk <span style="color:#16a34a">✓</span></th>
                    <th>Catatan Item (Opsional)</th>
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
                      <?php if (!empty($item['produk_satuan'])): ?>
                        <span class="badge neutral" style="font-size:0.7rem;"><?= htmlspecialchars($item['produk_satuan']) ?></span>
                      <?php endif; ?>
                      <input type="hidden" name="items[<?= $i ?>][id]"          value="<?= (int)$item['id'] ?>">
                      <input type="hidden" name="items[<?= $i ?>][spk_item_id]" value="<?= (int)$item['id'] ?>">
                      <input type="hidden" name="items[<?= $i ?>][produk_id]"   value="<?= (int)($item['produk_id'] ?? 0) ?>">
                      <input type="hidden" name="items[<?= $i ?>][qty_masuk]"   value="<?= (int)$item['qty_diterima'] ?>">
                    </td>
                    <td class="col-center text-muted" style="font-weight: 600;">
                      <?= number_format((int)$item['qty_diterima']) ?>
                    </td>
                    <td>
                      <input type="number" name="items[<?= $i ?>][qty_ok]"
                             class="form-control qty-input col-center" min="0" max="<?= (int)$item['qty_diterima'] ?>"
                             value="<?= $defaultQty ?>"
                             style="font-weight:700; border-color:#22c55e;"
                             required>
                    </td>
                    <td>
                      <input type="text" name="items[<?= $i ?>][keterangan]" class="form-control"
                             value="<?= $defaultKet ?>"
                             placeholder="Kondisi barang...">
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="form-actions-bottom" style="display:flex; gap:12px; align-items:center;">
            <button type="submit" name="save_complete" class="btn-primary" style="background-color:#16a34a;" onclick="return confirm('Simpan dan langsung tambahkan stok ke gudang? (Jika seluruh target terpenuhi, SPK otomatis Completed)')">
              <i class="bi bi-check-all"></i> Simpan & Selesai (Masuk Stok)
            </button>
            <button type="submit" name="save_draft" class="btn-secondary">
              <i class="bi bi-file-earmark"></i> Simpan Draft
            </button>
            <a href="../index.php" class="btn-outline" style="margin-left:auto;">Batal</a>
          </div>
          <?php endif; ?>

        </div>

        <div class="form-side">
          <div class="form-card info-card">
            <div class="form-card-header"><h4><i class="bi bi-info-circle"></i> Info Sistem</h4></div>
            <ul class="info-list">
              <li><i class="bi bi-dot"></i> <b>Direct SPK</b>: Pilih SPK, sistem otomatis menarik list sisa produk yang belum selesai.</li>
              <li><i class="bi bi-dot"></i> <b>Simpan & Selesai</b>: Stok gudang <b>langsung bertambah</b>. Jika semua kuantitas terpenuhi, status SPK otomatis berubah jadi <b>Completed</b>.</li>
              <li><i class="bi bi-dot"></i> <b>Simpan Draft</b>: Data dicatat tanpa mengubah stok produk maupun status SPK.</li>
            </ul>
          </div>
        </div>
      </div>
    </form>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Validasi sederhana agar input jumlah tidak minus atau melebihi batas
    const inputs = document.querySelectorAll('.qty-input');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            const max = parseInt(this.getAttribute('max')) || 0;
            let val = parseInt(this.value) || 0;
            if (val < 0) val = 0;
            if (val > max) {
                alert('Jumlah masuk tidak boleh melebihi sisa target SPK (' + max + ')!');
                val = max;
            }
            this.value = val;
        });
    });
});
</script>

</body>
</html>