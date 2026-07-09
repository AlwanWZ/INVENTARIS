<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';

$errors = [];

// --- LOGIKA NOMOR PENGELUARAN OTOMATIS ---
$prefixPengeluaran = "OUT-" . date('ymd') . "-";
$stmtOut = $pdo->prepare("SELECT nomor_pengeluaran FROM pengeluaran WHERE nomor_pengeluaran LIKE ? ORDER BY id DESC LIMIT 1");
$stmtOut->execute([$prefixPengeluaran . '%']);
$lastOut = $stmtOut->fetchColumn();

if ($lastOut) {
    $urutanOut = (int)substr($lastOut, -3) + 1;
    $autoNomorPengeluaran = $prefixPengeluaran . str_pad($urutanOut, 3, '0', STR_PAD_LEFT);
} else {
    $autoNomorPengeluaran = $prefixPengeluaran . '001';
}

// --- LOGIKA NOMOR SJ OTOMATIS ---
$prefixSJ = "SJ-" . date('Y') . "-";
$stmtSJ = $pdo->prepare("SELECT nomor_sj FROM surat_jalan WHERE nomor_sj LIKE ? ORDER BY id DESC LIMIT 1");
$stmtSJ->execute([$prefixSJ . '%']);
$lastSJ = $stmtSJ->fetchColumn();

if ($lastSJ) {
    $urutanSJ = (int)substr($lastSJ, strlen($prefixSJ)) + 1;
    $autoNomorSJ = $prefixSJ . str_pad($urutanSJ, 3, '0', STR_PAD_LEFT);
} else {
    $autoNomorSJ = $prefixSJ . '001';
}

// Ambil daftar sumber (SPK selesai ATAU Pesanan tanpa SPK)
$sumberKirim = $pdo->query("
    (
        SELECT 'SPK' as tipe, s.id as spk_id, s.pesanan_id, s.nomor_spk as nomor_ref, p.nomor_pesanan,
               COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), 'Customer Belum Diset') as perusahaan,
               c.id as customer_id, c.alamat as alamat_kirim
        FROM spk s
        JOIN pesanan p ON s.pesanan_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE s.status IN ('selesai', 'completed', 'Completed')
    )
    UNION
    (
        SELECT 'PO' as tipe, NULL as spk_id, p.id as pesanan_id, p.nomor_pesanan as nomor_ref, p.nomor_pesanan,
               COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), 'Customer Belum Diset') as perusahaan,
               c.id as customer_id, c.alamat as alamat_kirim
        FROM pesanan p
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE p.status != 'cancelled' 
          AND NOT EXISTS (SELECT 1 FROM spk s WHERE s.pesanan_id = p.id)
    )
    ORDER BY nomor_ref DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Load items kalau user pilih sumber
$items = [];
$selectedSumber = $_POST['sumber'] ?? '';
$selectedSpkId = null;
$selectedPesananId = null;

if ($selectedSumber) {
    $parts = explode('-', $selectedSumber);
    $tipe = $parts[0];
    $id = (int)$parts[1];

    if ($tipe === 'SPK') {
        $selectedSpkId = $id;
        // Cari pesanan_id dari SPK
        $stmtP = $pdo->prepare("SELECT pesanan_id FROM spk WHERE id = ?");
        $stmtP->execute([$id]);
        $selectedPesananId = $stmtP->fetchColumn();
    } else {
        $selectedPesananId = $id;
    }

    if ($selectedPesananId) {
        $stmt = $pdo->prepare("
            SELECT pi.barang_id, pi.qty, pi.qty_dikirim, pr.nama AS produk_nama, pr.satuan 
            FROM pesanan_items pi
            JOIN barang pr ON pi.barang_id = pr.id 
            WHERE pi.pesanan_id = ? AND pi.qty > COALESCE(pi.qty_dikirim, 0)
        ");
        $stmt->execute([$selectedPesananId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    
    // 1. Data untuk tabel pengeluaran
    $dataPengeluaran = [
        'nomor_pengeluaran' => trim($_POST['nomor_pengeluaran'] ?? $autoNomorPengeluaran),
        'tanggal'           => $_POST['tanggal_kirim'] ?? date('Y-m-d'),
        'spk_id'            => $_POST['spk_id_hidden'] ?: null,
        'pesanan_id'        => $_POST['pesanan_id_hidden'] ?: null,
        'status'            => 'completed', 
        'keterangan'        => trim($_POST['catatan'] ?? ''),
        'created_by'        => $_SESSION['user']['id'] ?? null
    ];
    
    // 2. Data untuk Surat Jalan
    $dataSJ = [
        'nomor_sj'      => trim($_POST['nomor_sj'] ?? $autoNomorSJ),
        'tanggal_kirim' => $dataPengeluaran['tanggal'],
        'driver'        => trim($_POST['driver'] ?? ''),
        'kendaraan'     => trim($_POST['kendaraan'] ?? ''),
        'catatan'       => $dataPengeluaran['keterangan'],
        'customer_id'   => null,
        'alamat_kirim'  => ''
    ];

    $itemsToSave = $_POST['items'] ?? [];

    if (!$dataPengeluaran['spk_id'] && !$dataPengeluaran['pesanan_id']) $errors[] = 'Anda harus memilih SPK/Pesanan yang akan dikirim.';
    if (!$dataSJ['driver'])          $errors[] = 'Driver wajib diisi.';
    if (!$dataSJ['kendaraan'])       $errors[] = 'Kendaraan wajib diisi.';
    if (empty($itemsToSave))         $errors[] = 'Tidak ada item barang yang akan dikirim.';

    // Cari info customer dari sumber yang dipilih
    if ($dataPengeluaran['spk_id'] || $dataPengeluaran['pesanan_id']) {
        foreach ($sumberKirim as $s) {
            if (($dataPengeluaran['spk_id'] && $s['tipe'] === 'SPK' && $s['spk_id'] == $dataPengeluaran['spk_id']) ||
                ($dataPengeluaran['pesanan_id'] && $s['tipe'] === 'PO' && $s['pesanan_id'] == $dataPengeluaran['pesanan_id'])) {
                $dataSJ['customer_id'] = $s['customer_id'];
                $dataSJ['alamat_kirim'] = $s['alamat_kirim'];
                break;
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 🚀 FIX: INSERT PENGELUARAN (TIDAK PAKAI pic_name)
            $stmtOut = $pdo->prepare("INSERT INTO pengeluaran (nomor_pengeluaran, tanggal, spk_id, pesanan_id, status) VALUES (?, ?, ?, ?, ?)");
            $stmtOut->execute([
                $dataPengeluaran['nomor_pengeluaran'], 
                $dataPengeluaran['tanggal'], 
                $dataPengeluaran['spk_id'], 
                $dataPengeluaran['pesanan_id'],
                $dataPengeluaran['status']
            ]);
            $pengeluaranId = $pdo->lastInsertId();

            // INSERT ITEMS PENGELUARAN SEKALIGUS POTONG STOK
            $stmtItemOut = $pdo->prepare("INSERT INTO pengeluaran_items (pengeluaran_id, barang_id, qty) VALUES (?, ?, ?)");
            // HANYA MENGURANGI STOK FISIK / STOK AVAILABLE PRODUK JIKA BENAR-BENAR KELUAR
            $stmtUpdateStok = $pdo->prepare("UPDATE barang SET stok_available = stok_available - ?, stok = stok - ? WHERE id = ?");

            foreach ($itemsToSave as $item) {
                $stmtItemOut->execute([$pengeluaranId, $item['barang_id'], $item['qty']]);
                // Potong stok barang
                $stmtUpdateStok->execute([$item['qty'], $item['qty'], $item['barang_id']]);
                
                // Tambah qty_dikirim di pesanan_items
                if ($dataPengeluaran['pesanan_id']) {
                    $stmtUpdatePO = $pdo->prepare("UPDATE pesanan_items SET qty_dikirim = qty_dikirim + ? WHERE pesanan_id = ? AND barang_id = ?");
                    $stmtUpdatePO->execute([$item['qty'], $dataPengeluaran['pesanan_id'], $item['barang_id']]);
                }
            }

            // INSERT SURAT JALAN
            $stmtSj = $pdo->prepare("INSERT INTO surat_jalan (pengeluaran_id, nomor_sj, tanggal_kirim, customer_id, alamat_kirim, driver, kendaraan, catatan, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dikirim')");
            $stmtSj->execute([
                $pengeluaranId, $dataSJ['nomor_sj'], $dataSJ['tanggal_kirim'], $dataSJ['customer_id'], 
                $dataSJ['alamat_kirim'], $dataSJ['driver'], $dataSJ['kendaraan'], $dataSJ['catatan']
            ]);
            $sjId = $pdo->lastInsertId();

            // INSERT SURAT JALAN ITEMS
            $stmtSjItem = $pdo->prepare("INSERT INTO surat_jalan_items (surat_jalan_id, barang_id, qty) VALUES (?, ?, ?)");
            foreach ($itemsToSave as $item) {
                $stmtSjItem->execute([$sjId, $item['barang_id'], $item['qty']]);
            }

            $pdo->commit();
            header('Location: ../index.php?success=1');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal memproses transaksi: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buat Pengeluaran & Surat Jalan | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/surat_jln.css" rel="stylesheet">
  <style>
    .form-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .form-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
    .form-section-header { font-weight: 700; color: var(--text); border-bottom: 2px solid var(--accent-bg); padding-bottom: 12px; margin-bottom: 18px; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
    .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .items-table { width: 100%; border-collapse: collapse; background: var(--bg); }
    .items-table thead { background: var(--surface2); border-top: 1px solid var(--border); border-bottom: 2px solid var(--border); }
    .items-table th { color: var(--text2); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 12px 10px; text-align: left; }
    .items-table td { vertical-align: middle; padding: 10px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
    .items-table .form-control { font-size: 0.9rem; padding: 8px 10px; width: 100%; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); }
    .btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; background: rgba(220, 38, 38, 0.08); color: var(--text3); border: 1px solid rgba(220, 38, 38, 0.2); }
    .btn-icon:hover { background: rgba(220, 38, 38, 0.9); color: #fff; }
    .form-actions-bottom { margin-top: 28px; display: flex; gap: 12px; justify-content: flex-end; }
    .btn-primary, .btn-outline { padding: 11px 22px; font-size: 0.95rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
    .btn-primary:hover { transform: translateY(-2px); }
    .btn-outline { background: var(--surface2); border: 1px solid var(--border); color: var(--text2); }
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
          <a href="../index.php">Pengeluaran Barang</a>
          <i class="bi bi-chevron-right"></i>
          <span>Keluarkan Barang</span>
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
        <h1 class="page-title-lg">Keluarkan Barang & Cetak Surat Jalan</h1>
        <p class="page-subtitle">Pilih SPK untuk memotong stok fisik dan buat Surat Jalan sekaligus.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post" id="sjForm">
      <div class="form-card" style="margin-bottom: 24px;">
        <div class="form-section-header">
          <i class="bi bi-truck"></i> Data Pengiriman & Armada
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
            <label class="form-label">Pilih Sumber (SPK / Pesanan) <span class="required">*</span></label>
            <?php if (empty($sumberKirim)): ?>
              <div class="alert-error">Tidak ada SPK Selesai atau Pesanan siap kirim.</div>
            <?php else: ?>
              <select name="sumber" class="form-control" id="sumberSelect" required>
                <option value="">— Pilih Sumber —</option>
                <?php foreach ($sumberKirim as $s): ?>
                  <?php $val = $s['tipe'] . '-' . ($s['tipe'] === 'SPK' ? $s['spk_id'] : $s['pesanan_id']); ?>
                  <option value="<?= $val ?>" <?= ($selectedSumber == $val) ? 'selected' : '' ?>>
                    [<?= $s['tipe'] ?>] <?= htmlspecialchars($s['nomor_ref']) ?> | PO: <?= htmlspecialchars($s['nomor_pesanan']) ?> | (<?= htmlspecialchars($s['perusahaan']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>          
              <input type="hidden" name="spk_id_hidden" value="<?= $selectedSpkId ?>">
              <input type="hidden" name="pesanan_id_hidden" value="<?= $selectedPesananId ?>">
            <?php endif; ?>
          </div>
          <div>
            <label class="form-label">Tanggal Kirim <span class="required">*</span></label>
            <input type="date" name="tanggal_kirim" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_kirim'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div>
            <label class="form-label">Sistem Auto-Generate</label>
            <input type="text" class="form-control" value="<?= $autoNomorPengeluaran ?> & <?= $autoNomorSJ ?>" disabled style="background:#f3f4f6; color:#6b7280; font-size:0.8rem; font-family:monospace;">
            <input type="hidden" name="nomor_pengeluaran" value="<?= $autoNomorPengeluaran ?>">
            <input type="hidden" name="nomor_sj" value="<?= $autoNomorSJ ?>">
          </div>
        </div>

        <div class="form-grid-2" style="margin-top: 16px;">
          <div>
            <label class="form-label">Nama Driver <span class="required">*</span></label>
            <input type="text" name="driver" class="form-control" placeholder="Cth: Asep / JNE / GoSend" value="<?= htmlspecialchars($_POST['driver'] ?? '') ?>" required>
          </div>
          <div>
            <label class="form-label">Kendaraan / Plat Nomor <span class="required">*</span></label>
            <input type="text" name="kendaraan" class="form-control" placeholder="Cth: D 1234 ABC atau Mobil Box" value="<?= htmlspecialchars($_POST['kendaraan'] ?? '') ?>" required>
          </div>
        </div>

        <div style="margin-top: 16px;">
          <label class="form-label">Catatan Pengiriman</label>
          <textarea name="catatan" class="form-control" style="min-height: 80px;" placeholder="Instruksi jalan, nama penerima, dll..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-box-seam"></i> Rincian Barang (Stok Akan Otomatis Terpotong)
        </div>

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--border);">
          <table class="items-table">
            <thead>
              <tr>
                <th class="row-num" style="width: 50px;">#</th>
                <th>Nama Produk</th>
                <th style="width: 150px; text-align:center;">Qty Keluar</th>
                <th style="width: 100px; text-align:center;">Satuan</th>
                <th style="width: 60px;"></th>
              </tr>
            </thead>
            <tbody id="itemsBody">
              <?php if ($items): ?>
              <?php foreach ($items as $i => $item): 
                  $sisa = $item['qty'] - ($item['qty_dikirim'] ?? 0);
              ?>
              <tr class="item-row">
                <td class="row-num" style="text-align:center;"><?= $i + 1 ?></td>
                <td style="font-weight: 500; color: var(--text);">
                  <?= htmlspecialchars($item['produk_nama'] ?? '-') ?><br>
                  <small style="color:var(--text3); font-weight:normal;">Total Pesanan: <?= $item['qty'] ?> | Sudah Terkirim: <?= (int)$item['qty_dikirim'] ?> | <b>Sisa: <?= $sisa ?></b></small>
                </td>
                <td style="text-align:center;">
                  <input type="number" name="items[<?= $i ?>][qty]" class="form-control" style="text-align:center; font-weight:bold; color:#059669;"
                         min="1" max="<?= $sisa ?>" value="<?= $sisa ?>" required>
                  <input type="hidden" name="items[<?= $i ?>][barang_id]" value="<?= $item['barang_id'] ?>">
                </td>
                <td style="text-align: center; color: var(--text3);"><?= htmlspecialchars($item['satuan'] ?? 'pcs') ?></td>
                <td style="text-align: center;">
                  <button type="button" class="btn-icon btn-delete" title="Keluarkan dari daftar kirim"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text3);">
                  <i class="bi bi-inbox" style="font-size: 1.8rem; display: block; margin-bottom: 10px;"></i>
                  Pilih pesanan SPK di atas untuk memuat daftar barang.
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="form-actions-bottom">
          <a href="../index.php" class="btn-outline">
            <i class="bi bi-x-lg"></i> Batal
          </a>
          <button type="submit" name="save" class="btn-primary">
            <i class="bi bi-send-check"></i> Simpan & Potong Stok
          </button>
        </div>
      </div>
    </form>

  </div>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const sumberSelect = document.getElementById('sumberSelect');
    const sjForm = document.getElementById('sjForm');
    const tbody = document.getElementById('itemsBody');
    
    if (sumberSelect) {
      sumberSelect.addEventListener('change', function() {
        if (this.value) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'load_items';
          input.value = '1';
          sjForm.appendChild(input);
          sjForm.submit();
        }
      });
    }

    if (tbody) {
      tbody.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete')) {
          e.preventDefault();
          const rows = document.querySelectorAll('.item-row');
          if (rows.length > 1) {
            e.target.closest('tr').remove();
            document.querySelectorAll('.item-row').forEach((row, idx) => {
              row.querySelector('.row-num').textContent = idx + 1;
            });
          } else {
            alert('Pengeluaran minimal harus memiliki 1 item barang!');
          }
        }
      });
    }
  });
</script>
</body>
</html>