<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/SPK.php';
require_once '../../../../src/models/Pesanan.php';
require_once '../../../../src/models/User.php';
require_once '../../../../src/functions.php';

$errors = [];

// --- LOGIKA NOMOR SPK OTOMATIS TAHUNAN ---
$tahun = date('Y');
$prefixSPK = "SPK-" . $tahun . "-";
$stmtSPK = $pdo->prepare("SELECT nomor_spk FROM spk WHERE nomor_spk LIKE ? ORDER BY id DESC LIMIT 1");
$stmtSPK->execute([$prefixSPK . '%']);
$lastSPK = $stmtSPK->fetchColumn();

if ($lastSPK) {
    $urutan = (int)substr($lastSPK, strlen($prefixSPK)) + 1;
    $autoNomorSPK = $prefixSPK . str_pad($urutan, 3, '0', STR_PAD_LEFT);
} else {
    $autoNomorSPK = $prefixSPK . '001';
}
// -----------------------------------------

// --- KUERI PO + CUSTOMER ---
$poList = $pdo->query("
    SELECT pesanan.id, pesanan.nomor_pesanan, 
           COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), 'Customer Belum Diset') as perusahaan
    FROM pesanan
    LEFT JOIN customers c ON pesanan.customer_id = c.id
    ORDER BY pesanan.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$users  = User::getAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pesanan_id = !empty($_POST['pesanan_id']) ? (int)$_POST['pesanan_id'] : '';
    $customer_id = null;
    
    if ($pesanan_id) {
        $stmtCust = $pdo->prepare("SELECT customer_id FROM pesanan WHERE id = ?");
        $stmtCust->execute([$pesanan_id]);
        $customer_id = $stmtCust->fetchColumn() ?: null;
        
        // --- UPGRADE 1: CEK APAKAH PO SUDAH PUNYA BARANG ---
        $stmtCekItems = $pdo->prepare("SELECT COUNT(*) FROM pesanan_items WHERE pesanan_id = ?");
        $stmtCekItems->execute([$pesanan_id]);
        if ($stmtCekItems->fetchColumn() <= 0) {
            $errors[] = 'PO yang dipilih belum memiliki daftar barang (PO Items kosong). Silakan lengkapi data barang pada PO tersebut terlebih dahulu!';
        } else {
            // --- UPGRADE 1.5: CEK APAKAH STOK CUKUP UNTUK SEMUA BARANG ---
            // Jika stok barang aman (>= qty pesanan) untuk semua item, tidak perlu SPK.
            $stmtCekStok = $pdo->prepare("
                SELECT pi.barang_id, pi.qty, b.stok_available
                FROM pesanan_items pi
                JOIN barang b ON pi.barang_id = b.id
                WHERE pi.pesanan_id = ?
            ");
            $stmtCekStok->execute([$pesanan_id]);
            $itemsPesanan = $stmtCekStok->fetchAll(PDO::FETCH_ASSOC);

            $butuhSpk = false;
            foreach ($itemsPesanan as $item) {
                // Jika stok_available kurang dari 0 (minus), berarti ada backorder dan kita BUTUH SPK!
                if ($item['stok_available'] < 0) {
                    $butuhSpk = true;
                    break;
                }
            }
            
            if (!$butuhSpk) {
                $errors[] = 'Stok barang untuk pesanan ini masih aman (mencukupi). Anda tidak perlu membuat SPK, silakan proses langsung ke pengeluaran/pengiriman.';
            }
        }
    }

    $data = [
        'nomor_spk'   => trim($_POST['nomor_spk'] ?? ''),
        'pesanan_id'       => $pesanan_id,
        'customer_id' => $customer_id,
        'tanggal'     => $_POST['tanggal']   ?? '',
        'deadline'    => $_POST['deadline']  ?? '',
        'pic_id'      => !empty($_POST['pic_id']) ? (int)$_POST['pic_id'] : null,
        'status'      => $_POST['status']    ?? 'draft', // Mengambil nilai dari value option select
        'notes'       => trim($_POST['notes'] ?? ''),
        'progress'    => (int)($_POST['progress'] ?? 0),
    ];
    
    if (!$data['nomor_spk']) $errors[] = 'Nomor SPK wajib diisi.';
    if (!$data['pesanan_id'])     $errors[] = 'PO wajib dipilih.';
    if (!$data['tanggal'])   $errors[] = 'Tanggal wajib diisi.';
    if (!$data['deadline'])  $errors[] = 'Deadline wajib diisi.';
    if (!$data['pic_id'])    $errors[] = 'PIC wajib dipilih.';
    
    if ($data['pic_id']) {
        $currentUsers = User::getAll();
        if (!array_filter($currentUsers, fn($u) => $u['id'] == $data['pic_id'])) {
            $errors[] = 'PIC yang dipilih tidak valid atau telah dihapus.';
        }
    }
    
    // --- UPGRADE 2: SIMPAN DATA ---
    if (!$errors) {
        try {
            SPK::create($data);
            header('Location: ../index.php?success=1');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan SPK ke database: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah SPK | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/spk.css" rel="stylesheet">
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
          <a href="../index.php">SPK</a>
          <i class="bi bi-chevron-right"></i>
          <span>Tambah SPK</span>
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
        <h1 class="page-title-lg">Tambah SPK</h1>
        <p class="page-subtitle">Isi formulir untuk membuat Surat Perintah Kerja baru.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-layout">
      <div class="form-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-file-earmark-plus"></i> Data SPK</h4>
          </div>

          <?php if ($errors): ?>
          <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <ul style="margin:0; padding-left:20px;">
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <form method="post" class="pesanan-form">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nomor SPK <span class="required">*</span></label>
                <input type="text" name="nomor_spk" class="form-control"
                       value="<?= htmlspecialchars($_POST['nomor_spk'] ?? $autoNomorSPK) ?>" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Pilih Pesanan <span class="required">*</span></label>
                <select name="pesanan_id" class="form-control" required id="poSelect">
                  <option value="">-- Pilih Pesanan --</option>
                  <?php foreach ($poList as $pesanan): ?>
                    <option value="<?= $pesanan['id'] ?>"
                            data-customer="<?= htmlspecialchars($pesanan['perusahaan']) ?>"
                            <?= ($_POST['pesanan_id'] ?? '') == $pesanan['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($pesanan['nomor_pesanan']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Customer (otomatis)</label>
                <input type="text" id="customerField" class="form-control"
                       value="— Pilih Pesanan Dahulu —" readonly
                       placeholder="Terisi otomatis dari Pesanan" style="background-color: #f8f9fa; cursor: not-allowed;">
              </div>
              <div class="form-group">
                <label class="form-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal" class="form-control"
                       value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Deadline <span class="required">*</span></label>
                <input type="date" name="deadline" class="form-control"
                       value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">PIC <span class="required">*</span></label>
                <select name="pic_id" class="form-control" required>
                  <option value="">-- Pilih PIC --</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($_POST['pic_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($u['username']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                  <?php 
                  // CATATAN PENTING: Sesuaikan key array di bawah ini dengan struktur isi ENUM/VARCHAR di database-mu.
                  // Jika database-mu memakai huruf kapital & spasi, ubah key-nya (contoh: 'On Progress' => 'On Progress')
                  $statusOptions = [
                      'draft'       => 'Draft',
                      'on_progress' => 'On Progress',
                      'completed'   => 'Completed',
                      'cancelled'   => 'Cancelled'
                  ];
                  foreach ($statusOptions as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($_POST['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Progress (%)</label>
                <input type="number" name="progress" class="form-control"
                       min="0" max="100" value="<?= (int)($_POST['progress'] ?? 0) ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control form-textarea" style="min-height: 80px; padding: 10px;"
                        placeholder="Catatan tambahan (opsional)"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan SPK</button>
              <a href="../index.php" class="btn-outline">Batal</a>
            </div>
          </form>
        </div>
      </div>

  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const poSelect = document.getElementById('poSelect');
    const customerField = document.getElementById('customerField');

    function syncCustomer() {
        if (poSelect.value === "") {
            customerField.value = "— Pilih Pesanan Dahulu —";
        } else {
            const selectedOpt = poSelect.options[poSelect.selectedIndex];
            customerField.value = selectedOpt.getAttribute('data-customer');
        }
    }

    poSelect.addEventListener('change', syncCustomer);
    syncCustomer();
});
</script>
</body>
</html>