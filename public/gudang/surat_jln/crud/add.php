<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/SuratJalan.php';
require_once '../../../../src/functions.php';

$autoNomorSJ = generateAutoCode('SJ'); // Auto-generate SJ code
$suratJalanModel = new SuratJalan($pdo);
$pengeluaranList = $pdo->query("SELECT id, nomor_pengeluaran FROM pengeluaran ORDER BY tanggal DESC")->fetchAll(PDO::FETCH_ASSOC);
$errors  = [];
// Load items jika pengeluaran dipilih
$items = [];
if (!empty($_POST['pengeluaran_id'])) {
  $stmt = $pdo->prepare("SELECT pi.*, pr.nama AS produk_nama, pr.satuan FROM pengeluaran_items pi LEFT JOIN produk pr ON pi.produk_id = pr.id WHERE pi.pengeluaran_id = ?");
  $stmt->execute([$_POST['pengeluaran_id']]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
      'nomor_sj'       => trim($_POST['nomor_sj']       ?? ''),
      'tanggal_kirim'  => $_POST['tanggal_kirim']  ?? '',
      'pengeluaran_id' => $_POST['pengeluaran_id'] ?? '',
      'driver'         => trim($_POST['driver']         ?? ''),
      'kendaraan'      => trim($_POST['kendaraan']      ?? ''),
      'catatan'        => trim($_POST['catatan']        ?? ''),
      'items'          => $_POST['items']          ?? [],
    ];
    // Ambil customer_id dan alamat_kirim dari pengeluaran yang dipilih
    $data['customer_id'] = null;
    $data['alamat_kirim'] = '';
    if (!empty($data['pengeluaran_id'])) {
      $stmt = $pdo->prepare("SELECT s.customer_id, c.alamat AS alamat_kirim FROM pengeluaran p LEFT JOIN spk s ON p.spk_id = s.id LEFT JOIN customers c ON s.customer_id = c.id WHERE p.id = ?");
      $stmt->execute([$data['pengeluaran_id']]);
      $pengeluaran = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($pengeluaran) {
        $data['customer_id'] = $pengeluaran['customer_id'];
        $data['alamat_kirim'] = $pengeluaran['alamat_kirim'] ?? '';
      } else {
        $data['alamat_kirim'] = '';
      }
    }
    // created_by dari session user
    $data['created_by'] = $_SESSION['user']['id'] ?? null;
    if (!$data['nomor_sj'])       $errors[] = 'Nomor SJ wajib diisi.';
    if (!$data['tanggal_kirim'])  $errors[] = 'Tanggal kirim wajib diisi.';
    if (!$data['pengeluaran_id']) $errors[] = 'Pengeluaran wajib dipilih.';
    if (!$data['driver'])         $errors[] = 'Driver wajib diisi.';
    if (!$data['kendaraan'])      $errors[] = 'Kendaraan wajib diisi.';
    if (!$data['customer_id'])    $errors[] = 'Customer tidak ditemukan dari pengeluaran.';
    if (!$errors) {
      try {
        $result = $suratJalanModel->add($data, $data['items']);
        if ($result) {
          header('Location: ../index.php?msg=add-success');
          exit;
        }
      } catch (Exception $e) {
        $errors[] = $e->getMessage();
      }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buat Surat Jalan | Inventory</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/surat_jln.css" rel="stylesheet">
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
          <a href="../index.php">Surat Jalan</a>
          <i class="bi bi-chevron-right"></i>
          <span>Buat Surat Jalan</span>
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
        <h1 class="page-title-lg">Buat Surat Jalan</h1>
        <p class="page-subtitle">Isi formulir untuk membuat surat jalan pengiriman.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
      <div class="form-layout">

        <div class="form-main">

          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-file-earmark-plus"></i> Data Surat Jalan</h4>
            </div>

            <?php if ($errors): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle"></i>
              <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>

            <div class="po-form">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nomor Surat Jalan <span class="required">*</span></label>
                    <input type="text" name="nomor_sj" class="form-control"
                       value="<?= htmlspecialchars($_POST['nomor_sj'] ?? $autoNomorSJ) ?>" readonly>
                </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal Kirim <span class="required">*</span></label>
                  <input type="date" name="tanggal_kirim" class="form-control"
                         value="<?= htmlspecialchars($_POST['tanggal_kirim'] ?? date('Y-m-d')) ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Pilih Pengeluaran <span class="required">*</span></label>
                <?php if (empty($pengeluaranList)): ?>
                  <div class="alert-error">Belum ada data pengeluaran. Silakan input pengeluaran terlebih dahulu.</div>
                <?php else: ?>
                  <div class="pengeluaran-select-wrap">
                    <select name="pengeluaran_id" class="form-control" id="pengeluaranSelect">
                      <option value="">— Pilih Pengeluaran —</option>
                      <?php foreach ($pengeluaranList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($_POST['pengeluaran_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($p['nomor_pengeluaran']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" name="load_items" class="btn-ghost-xs">
                      <i class="bi bi-arrow-repeat"></i> Load Barang
                    </button>
                  </div>
                  <p class="form-hint">Pilih pengeluaran lalu klik "Load Barang" untuk menampilkan daftar item.</p>
                <?php endif; ?>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Driver <span class="required">*</span></label>
                  <input type="text" name="driver" class="form-control"
                         placeholder="Nama driver"
                         value="<?= htmlspecialchars($_POST['driver'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Kendaraan <span class="required">*</span></label>
                  <input type="text" name="kendaraan" class="form-control"
                         placeholder="Nopol atau nama kendaraan"
                         value="<?= htmlspecialchars($_POST['kendaraan'] ?? '') ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea name="catatan" class="form-control form-textarea"
                          placeholder="Catatan pengiriman (opsional)"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <!-- Daftar barang -->
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-list-ul"></i> Daftar Barang</h4>
              <?php if ($items): ?>
                <span class="count-badge"><?= count($items) ?> item</span>
              <?php endif; ?>
            </div>
            <div class="table-wrap">
              <table class="item-table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th class="col-right">Qty</th>
                    <th class="col-right">Satuan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($items): ?>
                  <?php foreach ($items as $i => $item): ?>
                  <tr>
                    <td class="fw-mid"><?= htmlspecialchars($item['produk_nama']) ?></td>
                    <td class="col-right">
                      <input type="number" name="items[<?= $i ?>][qty]" class="form-control qty-input"
                             min="1" max="<?= $item['qty'] ?>" value="<?= $item['qty'] ?>" required>
                      <input type="hidden" name="items[<?= $i ?>][produk_id]" value="<?= $item['produk_id'] ?>">
                    </td>
                    <td class="col-right text-muted"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <tr>
                    <td colspan="3" class="empty-state">
                      <i class="bi bi-box"></i>
                      <span>Pilih pengeluaran dan klik "Load Barang" untuk menampilkan daftar item.</span>
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="form-actions-bottom">
            <button type="submit" name="save" class="btn-primary">
              <i class="bi bi-check-lg"></i> Simpan Surat Jalan
            </button>
            <a href="../index.php" class="btn-outline">Batal</a>
          </div>

        </div>

        <div class="form-side">
          <div class="form-card info-card">
            <div class="form-card-header">
              <h4><i class="bi bi-info-circle"></i> Panduan</h4>
            </div>
            <ul class="info-list">
              <li><i class="bi bi-dot"></i> Pilih pengeluaran terlebih dahulu, lalu klik Load Barang.</li>
              <li><i class="bi bi-dot"></i> Qty pengiriman tidak boleh melebihi qty pengeluaran.</li>
              <li><i class="bi bi-dot"></i> Nomor SJ dibuat otomatis — bisa diubah sesuai kebutuhan.</li>
              <li><i class="bi bi-dot"></i> Driver dan kendaraan wajib diisi untuk keperluan pengiriman.</li>
            </ul>
          </div>
        </div>

      </div>
    </form>

  </div>
</main>

</body>
</html>
