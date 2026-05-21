<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Pengeluaran.php';

$pengeluaranModel = new Pengeluaran($pdo);
$id    = $_GET['id'] ?? null;
if (!$id) { header('Location: ../index.php'); exit; }
$data  = $pengeluaranModel->getById($id);
$items = $pengeluaranModel->getItems($id);
if (!$data) { header('Location: ../index.php'); exit; }

$spkList    = $pdo->query("SELECT id, nomor_spk FROM spk ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$produkList = $pdo->query("SELECT id, nama, stok FROM produk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$userList   = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataUpdate = [
        'nomor_pengeluaran' => trim($_POST['nomor_pengeluaran'] ?? ''),
        'spk_id'  => $_POST['spk_id']  ?: null,
        'tanggal' => $_POST['tanggal'] ?? '',
        'status'  => $_POST['status']  ?? 'draft',
        'pic'     => $_POST['pic']     ?? '',
        'notes'   => trim($_POST['notes'] ?? ''),
    ];
    $itemsUpdate = $_POST['items'] ?? [];
    if (!$dataUpdate['nomor_pengeluaran']) $errors[] = 'Nomor pengeluaran wajib diisi.';
    if (!$dataUpdate['tanggal'])          $errors[] = 'Tanggal wajib diisi.';
    if (!$dataUpdate['pic'])              $errors[] = 'PIC wajib dipilih.';
    foreach ($itemsUpdate as $item) {
        $pr = array_values(array_filter($produkList, fn($p) => $p['id'] == $item['produk_id']));
        $pr = $pr[0] ?? null;
        if (!$pr) { $errors[] = 'Produk tidak valid.'; break; }
        if ((int)$item['qty'] > (int)$pr['stok']) {
            $errors[] = 'Qty keluar melebihi stok produk "' . $pr['nama'] . '" (stok: ' . $pr['stok'] . ').';
            break;
        }
    }
    if (!$errors) {
        try {
            $pengeluaranModel->update($id, $dataUpdate, $itemsUpdate);
            header('Location: ../index.php?updated=1');
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    $data = array_merge($data, $dataUpdate);
}

$statusCls   = match($data['status']) { 'completed' => 'ok', 'shipped' => 'blue', 'packing' => 'purple', 'picking' => 'teal', default => 'warn' };
$statusLabel = match($data['status']) { 'completed' => 'Completed', 'shipped' => 'Shipped', 'packing' => 'Packing', 'picking' => 'Picking', default => 'Draft' };
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Pengeluaran | InventorySys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@700;800&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/pengeluaran.css" rel="stylesheet">
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
          <a href="detail.php?id=<?= $data['id'] ?>"><?= htmlspecialchars($data['nomor_pengeluaran']) ?></a>
          <i class="bi bi-chevron-right"></i>
          <span>Edit</span>
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
        <h1 class="page-title-lg">Edit Pengeluaran</h1>
        <p class="page-subtitle">
          Mengedit <strong><?= htmlspecialchars($data['nomor_pengeluaran']) ?></strong>
          &mdash; <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
        </p>
      </div>
      <a href="detail.php?id=<?= $data['id'] ?>" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
      <div class="form-layout">

        <div class="form-main">

          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-pencil-square"></i> Data Pengeluaran</h4>
              <span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span>
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
                  <label class="form-label">Nomor Pengeluaran <span class="required">*</span></label>
                  <input type="text" name="nomor_pengeluaran" class="form-control"
                         value="<?= htmlspecialchars($data['nomor_pengeluaran']) ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal <span class="required">*</span></label>
                  <input type="date" name="tanggal" class="form-control"
                         value="<?= htmlspecialchars($data['tanggal']) ?>" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nomor SPK</label>
                  <select name="spk_id" class="form-control">
                    <option value="">— Pilih SPK —</option>
                    <?php foreach ($spkList as $spk): ?>
                      <option value="<?= $spk['id'] ?>" <?= $data['spk_id'] == $spk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($spk['nomor_spk']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">PIC <span class="required">*</span></label>
                  <select name="pic" class="form-control" required>
                    <option value="">— Pilih PIC —</option>
                    <?php foreach ($userList as $u): ?>
                      <option value="<?= $u['id'] ?>" <?= $data['pic'] == $u['id'] ? 'selected' : '' ?>>
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
                    <?php foreach (['draft'=>'Draft','picking'=>'Picking','packing'=>'Packing','shipped'=>'Shipped','completed'=>'Completed'] as $v => $l): ?>
                      <option value="<?= $v ?>" <?= $data['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control form-textarea"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <!-- Item table -->
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-list-ul"></i> Daftar Item</h4>
              <button type="button" class="btn-ghost-xs" onclick="addItemRow()">
                <i class="bi bi-plus"></i> Tambah Item
              </button>
            </div>
            <div class="table-wrap">
              <table class="item-table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th class="col-center">Stok Tersedia</th>
                    <th class="col-center">Qty Keluar</th>
                    <th class="col-center"></th>
                  </tr>
                </thead>
                <tbody id="itemRows">
                  <?php foreach ($items as $idx => $item): ?>
                  <tr>
                    <td>
                      <select name="items[<?= $idx ?>][produk_id]" class="form-control" required onchange="updateStok(this, <?= $idx ?>)">
                        <option value="">— Pilih Produk —</option>
                        <?php foreach ($produkList as $pr): ?>
                          <option value="<?= $pr['id'] ?>" <?= $item['produk_id'] == $pr['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pr['nama']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <?php
                      $stokItem = $item['stok'] ?? 0;
                      $stokCls  = $stokItem < 10 ? 'kritis' : ($stokItem < 30 ? 'rendah' : 'aman');
                    ?>
                    <td class="col-center"><span class="stok-badge <?= $stokCls ?>" id="stok-<?= $idx ?>"><?= $stokItem ?></span></td>
                    <td><input type="number" name="items[<?= $idx ?>][qty]" class="form-control qty-input" min="1" value="<?= $item['qty'] ?>" required></td>
                    <td class="col-center"><button type="button" class="btn-icon danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="item-footer">
              <button type="button" class="btn-outline" onclick="addItemRow()">
                <i class="bi bi-plus"></i> Tambah Item
              </button>
            </div>
          </div>

          <div class="form-actions-bottom">
            <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
            <a href="detail.php?id=<?= $data['id'] ?>" class="btn-outline">Batal</a>
          </div>

        </div>

        <!-- SIDE -->
        <div class="form-side">
          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-clock-history"></i> Data Sebelumnya</h4>
            </div>
            <div class="side-info-list">
              <div class="side-info-item">
                <span class="side-info-label">Nomor</span>
                <span class="side-info-val fw-mid"><?= htmlspecialchars($data['nomor_pengeluaran']) ?></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Tanggal</span>
                <span class="side-info-val"><?= htmlspecialchars($data['tanggal']) ?></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Status</span>
                <span class="side-info-val"><span class="badge <?= $statusCls ?>"><?= $statusLabel ?></span></span>
              </div>
              <div class="side-info-item">
                <span class="side-info-label">Jml Item</span>
                <span class="side-info-val fw-mid"><?= count($items) ?></span>
              </div>
            </div>
          </div>

          <div class="form-card danger-card">
            <div class="form-card-header">
              <h4><i class="bi bi-shield-exclamation"></i> Zona Berbahaya</h4>
            </div>
            <div class="danger-body">
              <p>Hapus data pengeluaran ini secara permanen. Tindakan tidak dapat dibatalkan.</p>
              <button type="button" class="btn-danger" id="deleteBtn">
                <i class="bi bi-trash"></i> Hapus Pengeluaran
              </button>
            </div>
          </div>
        </div>

      </div>
    </form>

    <!-- DELETE MODAL -->
    <div class="modal-overlay" id="deleteModal">
      <div class="modal-box">
        <div class="modal-icon"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Hapus Pengeluaran?</h3>
        <p>Data <strong><?= htmlspecialchars($data['nomor_pengeluaran']) ?></strong> akan dihapus permanen.</p>
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

<?php include '../../../../templates/nav-script.php'; ?>
<script>
const produkStok = {};
<?php foreach ($produkList as $pr): ?>
produkStok[<?= $pr['id'] ?>] = <?= (int)$pr['stok'] ?>;
<?php endforeach; ?>

const produkOptions = `<option value="">— Pilih Produk —</option><?php foreach ($produkList as $pr): ?><option value="<?= $pr['id'] ?>"><?= addslashes(htmlspecialchars($pr['nama'])) ?></option><?php endforeach; ?>`;
let rowIdx = <?= count($items) ?>;

function updateStok(sel, idx) {
  const val = sel.value;
  const el  = document.getElementById('stok-' + idx);
  if (!el) return;
  if (val && produkStok[val] !== undefined) {
    const s = produkStok[val];
    el.textContent = s;
    el.className = 'stok-badge ' + (s < 10 ? 'kritis' : s < 30 ? 'rendah' : 'aman');
  } else {
    el.textContent = '—';
    el.className = 'stok-badge';
  }
}

function addItemRow() {
  const tbody = document.getElementById('itemRows');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="items[${rowIdx}][produk_id]" class="form-control" required onchange="updateStok(this,${rowIdx})">${produkOptions}</select></td>
    <td class="col-center"><span class="stok-badge" id="stok-${rowIdx}">—</span></td>
    <td><input type="number" name="items[${rowIdx}][qty]" class="form-control qty-input" min="1" placeholder="0" required></td>
    <td class="col-center"><button type="button" class="btn-icon danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
  `;
  tbody.appendChild(tr);
  rowIdx++;
}

function removeRow(btn) {
  const rows = document.getElementById('itemRows').querySelectorAll('tr');
  if (rows.length > 1) btn.closest('tr').remove();
}

const modal = document.getElementById('deleteModal');
document.getElementById('deleteBtn')?.addEventListener('click',  () => modal.classList.add('show'));
document.getElementById('cancelDelete')?.addEventListener('click', () => modal.classList.remove('show'));
modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
</script>
</body>
</html>
