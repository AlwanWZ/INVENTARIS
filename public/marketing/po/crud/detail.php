<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location.href='/Inventaris/public/dashboard.php';</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/config.php';

$po = PO::find($_GET['id'] ?? null);
if (!$po) { header('Location: ../index.php'); exit; }

// Get PO items dengan stok info dari produk
global $pdo;
$stmt = $pdo->prepare("
    SELECT 
        poi.*,
        p.stok,
        p.stok_available,
        p.stok_reserved
    FROM po_items poi
    LEFT JOIN produk p ON poi.produk_id = p.id
    WHERE poi.po_id = ?
");
$stmt->execute([$po['id']]);
$poItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate the total dynamically from po_items
$totalAmount = PO::calculateTotal($po['id']);

$statusCls = match($po['status']) {
    'approved' => 'ok',
    'proses'    => 'warn',
    'rejected' => 'danger',
    default     => 'neutral'
};
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detail Pesanan PCB | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/po.css" rel="stylesheet">
</head>
<body>

<?php include '../../../../templates/nav.php'; ?>

<main class="main">
  <div class="content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="top-left">
        <button class="menu-btn" id="menuBtn"><i class="bi bi-list"></i></button>
        <div class="breadcrumb">
          <a href="/Inventaris/public/dashboard.php">Dashboard</a>
          <i class="bi bi-chevron-right"></i>
          <a href="../index.php">Pesanan PCB</a>
          <i class="bi bi-chevron-right"></i>
          <span><?= htmlspecialchars($po['nomor_po']) ?></span>
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

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title-lg">Detail Order</h1>
        <p class="page-subtitle">Order <strong><?= htmlspecialchars($po['nomor_po']) ?></strong> dari <strong><?= htmlspecialchars($po['perusahaan'] ?? '-') ?></strong></p>
      </div>
      <div class="header-actions">
        <a href="../index.php" class="btn-ghost-sm">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
      </div>
    </div>

    <!-- DETAIL LAYOUT -->
    <div class="detail-layout">

      <!-- MAIN INFO -->
      <div class="detail-main">
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-file-earmark-check"></i> Informasi Pesanan PCB</h4>
            <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($po['status']) ?></span>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Nomor Pesanan PCB</span>
              <span class="detail-val fw-mid"><?= htmlspecialchars($po['nomor_po']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Tanggal</span>
              <span class="detail-val"><?= htmlspecialchars($po['tanggal']) ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Customer/Perusahaan</span>
              <span class="detail-val"><?= htmlspecialchars($po['customer'] ?? $po['perusahaan'] ?? '—') ?></span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-val">
                <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($po['status']) ?></span>
              </span>
            </div>
          </div>
        </div>

        <!-- Tambahkan logika untuk mengambil data item PO -->
        <div class="form-card">
          <div class="form-card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h4><i class="bi bi-list"></i> Item Pesanan</h4>
            <a href="add_item.php?po_id=<?= $po['id'] ?>" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
              <i class="bi bi-plus-lg"></i> Tambah Item
            </a>
          </div>

          <?php if (isset($_GET['item_added'])): ?>
            <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
              <i class="bi bi-check-circle" style="color: #4caf50;"></i>
              <span style="color: #2e7d32; margin-left: 0.5rem;">Item berhasil ditambahkan!</span>
            </div>
          <?php endif; ?>

          <?php if (isset($_GET['item_deleted'])): ?>
            <div style="background: #ffebee; border-left: 4px solid #f44336; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
              <i class="bi bi-check-circle" style="color: #f44336;"></i>
              <span style="color: #c62828; margin-left: 0.5rem;">Item berhasil dihapus!</span>
            </div>
          <?php endif; ?>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Kode Material</th>
                  <th>Nama Material</th>
                  <th>UOM</th>
                  <th>Qty Pesanan</th>
                  <th>Stok Tersedia</th>
                  <th>Ready</th>
                  <th>Pending Produksi</th>
                  <th>Harga Satuan</th>
                  <th>Total</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($poItems)): ?>
                  <tr>
                    <td colspan="9" class="empty-state">
                      <i class="bi bi-box"></i>
                      <span>Belum ada item dalam order ini. <a href="add_item.php?po_id=<?= $po['id'] ?>" style="color: #007bff; font-weight: 500;">Tambah Item</a></span>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($poItems as $i => $item): ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td><?= htmlspecialchars($item['kode_material']) ?></td>
                      <td><?= htmlspecialchars($item['nama_material']) ?></td>
                      <td><?= htmlspecialchars($item['uom']) ?></td>
                      <td><strong style="font-size: 1rem;"><?= number_format($item['qty'], 0, ',', '.') ?></strong></td>
                      <td><?= number_format($item['stok_available'] ?? $item['stok'] ?? 0, 0, ',', '.') ?> pcs</td>
                      <td>
                        <?php $qty_ready = $item['qty_available'] ?? min($item['qty'], $item['stok_available'] ?? $item['stok'] ?? 0); ?>
                        <strong style="color: #28a745;"><?= number_format($qty_ready, 0, ',', '.') ?> pcs</strong>
                      </td>
                      <td>
                        <?php $qty_pending = $item['qty_pending'] ?? max(0, $item['qty'] - ($item['stok_available'] ?? $item['stok'] ?? 0)); ?>
                        <?php if ($qty_pending > 0): ?>
                          <strong style="color: #d97706;">⚠️ <?= number_format($qty_pending, 0, ',', '.') ?> pcs</strong>
                        <?php else: ?>
                          <span style="color: #6c757d;">—</span>
                        <?php endif; ?>
                      </td>
                      <td>Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                      <td>Rp <?= number_format($item['amount'], 0, ',', '.') ?></td>
                      <td>
                        <div style="display: flex; gap: 0.4rem;">
                          <a href="delete_item.php?id=<?= $item['id'] ?>" class="btn-icon" title="Hapus item" style="color: #dc3545;">
                            <i class="bi bi-trash"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- SIDE -->
      <div class="detail-side">

        <!-- Ringkasan total -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-cash-stack"></i> Ringkasan</h4>
          </div>
          <div class="summary-body">
            <div class="summary-total">
              <span class="summary-label">Total Order</span>
              <span class="summary-val">Rp <?= number_format($totalAmount, 0, ',', '.') ?></span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-row">
              <span>ID</span>
              <span class="text-muted">#<?= $po['id'] ?></span>
            </div>
            <div class="summary-row">
              <span>Status</span>
              <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($po['status']) ?></span>
            </div>
          </div>
        </div>

        <!-- Tindakan -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-lightning"></i> Tindakan</h4>
          </div>
          <div class="action-body">
            <?php if ($po['status'] === 'draft'): ?>
              <!-- BUTTON 1: APPROVE & RESERVE STOK (NEW) -->
              <button class="btn-success full" id="approveReserveBtn" onclick="approveAndReserve()">
                <i class="bi bi-check-circle"></i> Approve & Reserve Stok
              </button>
              
              <!-- BUTTON 2: REJECT -->
              <a href="edit.php?id=<?= $po['id'] ?>&action=reject" class="btn-danger full" onclick="return confirm('Reject order ini?')">
                <i class="bi bi-x-circle"></i> Reject Order
              </a>
            <?php elseif ($po['status'] === 'approved'): ?>
              <!-- Jika status approved, tampilkan unreserve button -->
              <button class="btn-warning full" id="unreserveBtn" onclick="unreserveStok()">
                <i class="bi bi-arrow-counterclockwise"></i> Batalkan Reservation
              </button>
            <?php endif; ?>
            
            <a href="../index.php" class="btn-outline full">
              <i class="bi bi-arrow-left"></i> Kembali ke Daftar
            </a>
          </div>
        </div>

        <!-- Status Info -->
        <div class="form-card">
          <div class="form-card-header">
            <h4><i class="bi bi-info-circle"></i> Info Stok</h4>
          </div>
          <div style="padding: 1rem; font-size: 0.9rem;">
            <div style="margin-bottom: 0.8rem;">
              <strong>Status Stok:</strong>
              <span class="badge" style="margin-left: 0.5rem;">
                <?php 
                  $stok_status = $po['status_stok'] ?? 'draft';
                  echo htmlspecialchars($stok_status);
                ?>
              </span>
            </div>
            <div style="padding: 0.8rem; background: #f0f7ff; border-left: 3px solid #0d6efd; border-radius: 4px; color: #0c5da7; font-size: 0.85rem; line-height: 1.5;">
              <strong>Catatan:</strong><br>
              • Klik "Approve & Reserve Stok" untuk reserve barang dari stok<br>
              • Stok produk akan berkurang sesuai qty ready<br>
              • Qty pending akan diproduksi
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

<script>
const poId = <?= json_encode($po['id']) ?>;
const poStatus = <?= json_encode($po['status']) ?>;
const poNumber = <?= json_encode($po['nomor_po']) ?>;

async function approveAndReserve() {
  if (!confirm('Approve order ini dan reserve stok?\n\nStok produk akan berkurang sesuai qty ready yang dipesan.')) {
    return;
  }
  
  const btn = document.getElementById('approveReserveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-spinner"></i> Proses...';
  
  try {
    const response = await fetch('reserve_stok.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `po_id=${poId}&action=reserve`
    });
    
    const data = await response.json();
    
    if (data.success) {
      alert('✅ ' + data.message);
      location.reload();
    } else {
      alert('❌ ' + (data.error || 'Gagal reserve stok'));
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-circle"></i> Approve & Reserve Stok';
    }
  } catch (err) {
    alert('❌ Error: ' + err.message);
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle"></i> Approve & Reserve Stok';
  }
}

async function unreserveStok() {
  if (!confirm('Batalkan reservation stok?\n\nStok akan dikembalikan ke stok tersedia.')) {
    return;
  }
  
  const btn = document.getElementById('unreserveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-spinner"></i> Proses...';
  
  try {
    const response = await fetch('reserve_stok.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `po_id=${poId}&action=unreserve`
    });
    
    const data = await response.json();
    
    if (data.success) {
      alert('✅ ' + data.message);
      location.reload();
    } else {
      alert('❌ ' + (data.error || 'Gagal unreserve stok'));
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Batalkan Reservation';
    }
  } catch (err) {
    alert('❌ Error: ' + err.message);
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Batalkan Reservation';
  }
}

// Dark mode toggle
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
