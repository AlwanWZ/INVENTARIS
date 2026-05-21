<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/SuratJalan.php';

$suratJalanModel = new SuratJalan($pdo);
$id    = $_GET['id'] ?? '';
$data  = $suratJalanModel->getById($id);
if (!$data) { header('Location: ../index.php?msg=notfound'); exit; }
$items = $suratJalanModel->getItems($id);

function badgeCls($s) { return match($s) { 'diterima' => 'ok', 'dikirim' => 'blue', default => 'warn' }; }
function badgeLabel($s) { return match($s) { 'diterima' => 'Diterima', 'dikirim' => 'Dikirim', default => 'Draft' }; }

$totalQty = array_sum(array_column($items, 'qty'));
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Surat Jalan | Inventory</title>
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
          <span><?= htmlspecialchars($data['nomor_sj']) ?></span>
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

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'edit-success'): ?>
    <div class="alert-success"><i class="bi bi-check-circle"></i> Surat Jalan berhasil diperbarui.</div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Detail Surat Jalan</h1>
        <p class="page-subtitle">
          <strong><?= htmlspecialchars($data['nomor_sj']) ?></strong>
          &mdash; <span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span>
        </p>
      </div>
      <div class="header-actions">
        <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
      </div>
    </div>

    <div class="detail-layout">

      <!-- MAIN -->
      <div class="detail-main">

        <!-- Info SJ -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-truck"></i> Informasi Surat Jalan</h4>
            <span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Nomor SJ</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($data['nomor_sj']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Tanggal Kirim</span>
              <span class="detail-val"><?= htmlspecialchars($data['tanggal_kirim']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Customer</span>
              <span class="detail-val"><?= htmlspecialchars($data['customer_nama']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">No. Pengeluaran</span>
              <span class="detail-val"><?= htmlspecialchars($data['nomor_pengeluaran'] ?? '—') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Driver</span>
              <span class="detail-val"><?= htmlspecialchars($data['driver']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Kendaraan</span>
              <span class="detail-val"><?= htmlspecialchars($data['kendaraan']) ?></span>
            </div>
            <?php if (!empty($data['catatan'])): ?>
            <div class="detail-item detail-item-full">
              <span class="detail-label">Catatan</span>
              <span class="detail-val"><?= nl2br(htmlspecialchars($data['catatan'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Daftar barang -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-list-ul"></i> Daftar Barang</h4>
            <span class="count-badge"><?= count($items) ?> item</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Produk</th>
                  <th class="col-right">Qty</th>
                  <th class="col-right">Satuan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                  <td class="fw-mid"><?= htmlspecialchars($item['produk_nama'] ?? '-') ?></td>
                  <td class="col-right fw-mid"><?= htmlspecialchars($item['qty'] ?? 0) ?></td>
                  <td class="col-right text-muted"><?= htmlspecialchars($item['satuan'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td class="fw-mid">Total</td>
                  <td class="col-right fw-mid"><?= $totalQty ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>

      <!-- SIDE -->
      <div class="detail-side">

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-clipboard-data"></i> Ringkasan</h4>
          </div>
          <div class="summary-body">
            <div class="summary-total">
              <span class="summary-label">Total Item</span>
              <span class="summary-val"><?= count($items) ?> jenis</span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-row">
              <span>Total Qty</span>
              <span class="fw-mid"><?= $totalQty ?></span>
            </div>
            <div class="summary-row">
              <span>Status</span>
              <span class="badge <?= badgeCls($data['status']) ?>"><?= badgeLabel($data['status']) ?></span>
            </div>
            <div class="summary-row">
              <span>Customer</span>
              <span class="text-muted" style="font-size:0.82rem;text-align:right;"><?= htmlspecialchars($data['customer_nama']) ?></span>
            </div>
          </div>
        </div>

        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="action-body">
            <a href="edit.php?id=<?= $data['id'] ?>" class="btn-primary full">
              <i class="bi bi-pencil"></i> Edit Surat Jalan
            </a>
            <a href="print_list.php?id=<?= $data['id'] ?>" class="btn-secondary full" target="_blank">
              <i class="bi bi-printer"></i> Cetak Surat Jalan
            </a>
            <button type="button" class="btn-danger-outline full" id="deleteBtn">
              <i class="bi bi-trash"></i> Hapus
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- DELETE MODAL -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus Surat Jalan?</h3>
        <p>Surat Jalan <strong><?= htmlspecialchars($data['nomor_sj']) ?></strong> akan dihapus permanen.</p>
        <div class="modal-actions">
          <form method="post" action="delete.php">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
          </form>
          <button type="button" class="btn-ghost-sm" id="cancelDelete">Batal</button>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
  const modal = document.getElementById('deleteModal');
  document.getElementById('deleteBtn')?.addEventListener('click',  () => modal.classList.add('show'));
  document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
  modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
