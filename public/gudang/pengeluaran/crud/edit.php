<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
  exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Pengeluaran.php';

$pengeluaranModel = new Pengeluaran($pdo);
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: ../index.php'); exit; }

// Ambil Data Pengeluaran
$data = $pengeluaranModel->getById($id);
if (!$data) { header('Location: ../index.php'); exit; }
$items = $pengeluaranModel->getItems($id);

// Ambil Data Surat Jalan yang nempel sama Pengeluaran ini
$stmtSJ = $pdo->prepare("SELECT * FROM surat_jalan WHERE pengeluaran_id = ?");
$stmtSJ->execute([$id]);
$dataSJ = $stmtSJ->fetch(PDO::FETCH_ASSOC);

// Ambil List Sumber buat nampilin label doang
$sumberList = $pdo->query("
    (
        SELECT 'SPK' as tipe, s.id as spk_id, NULL as pesanan_id, s.nomor_spk as nomor_ref, p.nomor_pesanan,
               COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), 'Customer Belum Diset') as perusahaan
        FROM spk s
        JOIN pesanan p ON s.pesanan_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
    )
    UNION
    (
        SELECT 'Pesanan' as tipe, NULL as spk_id, p.id as pesanan_id, p.nomor_pesanan as nomor_ref, p.nomor_pesanan,
               COALESCE(NULLIF(c.perusahaan, ''), NULLIF(c.nama, ''), 'Customer Belum Diset') as perusahaan
        FROM pesanan p
        LEFT JOIN customers c ON p.customer_id = c.id
    )
")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $tanggal   = $_POST['tanggal'] ?? '';
    $driver    = trim($_POST['driver'] ?? '');
    $kendaraan = trim($_POST['kendaraan'] ?? '');
    $catatan   = trim($_POST['catatan'] ?? '');

    if (!$tanggal) $errors[] = 'Tanggal wajib diisi.';
    if (!$driver) $errors[] = 'Driver wajib diisi.';
    if (!$kendaraan) $errors[] = 'Kendaraan wajib diisi.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update Pengeluaran
            $stmtUpd = $pdo->prepare("UPDATE pengeluaran SET tanggal = ?, notes = ? WHERE id = ?");
            $stmtUpd->execute([$tanggal, $catatan, $id]);

            // 2. Update Surat Jalan
            if ($dataSJ) {
                $stmtSjUpd = $pdo->prepare("UPDATE surat_jalan SET tanggal_kirim = ?, driver = ?, kendaraan = ?, catatan = ? WHERE pengeluaran_id = ?");
                $stmtSjUpd->execute([$tanggal, $driver, $kendaraan, $catatan, $id]);
            }

            $pdo->commit();
            header('Location: ../index.php?updated=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Pengeluaran & SJ | Inventory</title>
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
    .form-actions-bottom { margin-top: 28px; display: flex; gap: 12px; justify-content: flex-end; }
    .btn-primary, .btn-outline { padding: 11px 22px; font-size: 0.95rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
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
          <a href="../index.php">Pengeluaran</a>
          <i class="bi bi-chevron-right"></i>
          <span>Edit Data</span>
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
        <h1 class="page-title-lg">Edit Pengeluaran & Surat Jalan</h1>
        <p class="page-subtitle">Ubah info pengiriman. SPK dan Barang dikunci untuk menjaga validitas stok gudang.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
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
            <label class="form-label">Sumber Yang Dipilih (Terkunci)</label>
            <select class="form-control" disabled style="background:#f3f4f6;">
                <?php foreach ($sumberList as $s): ?>
                  <?php if ($data['spk_id'] && $s['tipe'] === 'SPK' && $s['spk_id'] == $data['spk_id']): ?>
                      <option selected>[SPK] <?= htmlspecialchars($s['nomor_ref']) ?> | (<?= htmlspecialchars($s['perusahaan']) ?>)</option>
                  <?php elseif ($data['pesanan_id'] && !$data['spk_id'] && $s['tipe'] === 'Pesanan' && $s['pesanan_id'] == $data['pesanan_id']): ?>
                      <option selected>[Pesanan] <?= htmlspecialchars($s['nomor_ref']) ?> | (<?= htmlspecialchars($s['perusahaan']) ?>)</option>
                  <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <small style="color:var(--text3); font-size:0.75rem;">Hapus transaksi jika ingin mengganti sumber.</small>
          </div>
          <div>
            <label class="form-label">Tanggal Kirim <span class="required">*</span></label>
            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($data['tanggal']) ?>" required>
          </div>
          <div>
            <label class="form-label">Nomor Dokumen</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($data['nomor_pengeluaran']) ?> & <?= htmlspecialchars($dataSJ['nomor_sj'] ?? '') ?>" disabled style="background:#f3f4f6; color:#6b7280; font-family:monospace;">
          </div>
        </div>

        <div class="form-grid-2" style="margin-top: 16px;">
          <div>
            <label class="form-label">Nama Driver <span class="required">*</span></label>
            <input type="text" name="driver" class="form-control" value="<?= htmlspecialchars($dataSJ['driver'] ?? '') ?>" required>
          </div>
          <div>
            <label class="form-label">Kendaraan / Plat Nomor <span class="required">*</span></label>
            <input type="text" name="kendaraan" class="form-control" value="<?= htmlspecialchars($dataSJ['kendaraan'] ?? '') ?>" required>
          </div>
        </div>

        <div style="margin-top: 16px;">
          <label class="form-label">Catatan Pengiriman</label>
          <textarea name="catatan" class="form-control" style="min-height: 80px;"><?= htmlspecialchars($dataSJ['catatan'] ?? $data['notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-card">
        <div class="form-section-header">
          <i class="bi bi-box-seam"></i> Rincian Barang (Terkunci)
        </div>

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid var(--border);">
          <table class="items-table">
            <thead>
              <tr>
                <th class="row-num" style="width: 50px;">#</th>
                <th>Nama Produk</th>
                <th style="width: 150px; text-align:center;">Qty Keluar</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i => $item): ?>
              <tr class="item-row">
                <td class="row-num" style="text-align:center;"><?= $i + 1 ?></td>
                <td style="font-weight: 500; color: var(--text);"><?= htmlspecialchars($item['produk_nama'] ?? 'Produk ID: '.$item['barang_id']) ?></td>
                <td style="text-align:center;">
                  <input type="number" class="form-control" style="text-align:center; font-weight:bold; color:#059669; background:#f3f4f6;" value="<?= $item['qty'] ?>" disabled>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="form-actions-bottom">
          <a href="../index.php" class="btn-outline">
            <i class="bi bi-x-lg"></i> Batal
          </a>
          <button type="submit" name="update" class="btn-primary">
            <i class="bi bi-save"></i> Simpan Perubahan
          </button>
        </div>
      </div>
    </form>

  </div>
</main>
<script>
  const html = document.documentElement;
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const isDark = html.getAttribute('data-theme') === 'dark';
      html.setAttribute('data-theme', isDark ? 'light' : 'dark');
      themeBtn.querySelector('i').className = isDark ? 'bi bi-sun' : 'bi bi-moon';
    });
  }
</script>
</body>
</html>