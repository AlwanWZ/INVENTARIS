<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/config.php';
require_once '../../../../src/models/Pengeluaran.php';
require_once '../../../../src/functions.php';

$autoNomorPengeluaran = generateAutoCode('PNG'); // Auto-generate PNG code
$pengeluaranModel = new Pengeluaran($pdo);
$spkList    = $pdo->query("SELECT id, nomor_spk FROM spk ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$produkList = $pdo->query("SELECT id, nama, stok FROM produk ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$userList   = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$produkMap  = array_column($produkList, null, 'id');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data  = [
        'nomor_pengeluaran' => trim($_POST['nomor_pengeluaran'] ?? ''),
        'spk_id'  => $_POST['spk_id']  ?: null,
        'tanggal' => $_POST['tanggal'] ?? '',
        'status'  => $_POST['status']  ?? 'draft',
        'pic'     => $_POST['pic']     ?? '',
        'notes'   => trim($_POST['notes'] ?? ''),
    ];
    $items = $_POST['items'] ?? [];
    if (!$data['nomor_pengeluaran']) $errors[] = 'Nomor pengeluaran wajib diisi.';
    if (!$data['tanggal'])           $errors[] = 'Tanggal wajib diisi.';
    if (!$data['pic'])               $errors[] = 'PIC wajib dipilih.';
    if (empty($items))               $errors[] = 'Minimal satu item harus ditambahkan.';
    foreach ($items as $item) {
        $pr = $produkMap[$item['produk_id']] ?? null;
        if (!$pr) { $errors[] = 'Produk tidak valid.'; break; }
        if ((int)$item['qty'] > (int)$pr['stok']) {
            $errors[] = 'Qty melebihi stok ' . $pr['nama'] . ' (stok: ' . $pr['stok'] . ').';
            break;
        }
    }
    if (!$errors) {
        try { $pengeluaranModel->add($data, $items); header('Location: ../index.php?success=1'); exit; }
        catch (Exception $e) { $errors[] = $e->getMessage(); }
    }
}
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tambah Pengeluaran | InventorySys</title>
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
        <h1 class="page-title-lg">Tambah Pengeluaran</h1>
        <p class="page-subtitle">Catat pengeluaran barang dari gudang.</p>
      </div>
      <a href="../index.php" class="btn-ghost-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <form method="post">
      <div class="form-layout">
        <div class="form-main">

          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-file-earmark-plus"></i> Data Pengeluaran</h4>
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
                         value="<?= htmlspecialchars($_POST['nomor_pengeluaran'] ?? $autoNomorPengeluaran) ?>" readonly>
                </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Tanggal <span class="required">*</span></label>
                  <input type="date" name="tanggal" class="form-control"
                         value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Nomor SPK</label>
                  <select name="spk_id" class="form-control">
                    <option value="">— Pilih SPK (opsional) —</option>
                    <?php foreach ($spkList as $spk): ?>
                      <option value="<?= $spk['id'] ?>" <?= ($_POST['spk_id'] ?? '') == $spk['id'] ? 'selected' : '' ?>>
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
                      <option value="<?= $u['id'] ?>" <?= ($_POST['pic'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['username']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" style="max-width:220px;">
                  <?php foreach (['draft'=>'Draft','picking'=>'Picking','packing'=>'Packing','shipped'=>'Shipped','completed'=>'Completed'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($_POST['status'] ?? 'draft') === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control form-textarea" placeholder="Catatan tambahan (opsional)"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <div class="form-card">
            <div class="form-card-header">
              <h4><i class="bi bi-list-ul"></i> Daftar Item</h4>
              <button type="button" class="btn-ghost-xs" onclick="addItemRow()"><i class="bi bi-plus"></i> Tambah Item</button>
            </div>
            <div class="table-wrap">
              <table class="item-table">
                <thead>
                  <tr><th>Produk</th><th class="col-center">Stok</th><th class="col-center">Qty Keluar</th><th></th></tr>
                </thead>
                <tbody id="itemRows">
                  <tr>
                    <td>
                      <select name="items[0][produk_id]" class="form-control" required onchange="updateStok(this,0)">
                        <option value="">— Pilih Produk —</option>
                        <?php foreach ($produkList as $pr): ?>
                          <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nama']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td class="col-center"><span class="stok-badge" id="stok-0">—</span></td>
                    <td><input type="number" name="items[0][qty]" class="form-control qty-input" min="1" placeholder="0" required></td>
                    <td><button type="button" class="btn-icon danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="item-footer">
              <button type="button" class="btn-outline" onclick="addItemRow()"><i class="bi bi-plus"></i> Tambah Item</button>
            </div>
          </div>

          <div class="form-actions-bottom">
            <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Simpan Pengeluaran</button>
            <a href="../index.php" class="btn-outline">Batal</a>
          </div>

        </div>

        <div class="form-side">
          <div class="form-card info-card">
            <div class="form-card-header"><h4><i class="bi bi-info-circle"></i> Panduan</h4></div>
            <ul class="info-list">
              <li><i class="bi bi-dot"></i> SPK bersifat opsional.</li>
              <li><i class="bi bi-dot"></i> Qty keluar tidak boleh melebihi stok.</li>
              <li><i class="bi bi-dot"></i> Stok berkurang otomatis setelah disimpan.</li>
              <li><i class="bi bi-dot"></i> Sisa stok &lt;10 ditandai <em>kritis</em>.</li>
            </ul>
          </div>
        </div>
      </div>
    </form>

  </div>
</main>

<?php include '../../../../templates/nav-script.php'; ?>
<script>
const produkStok = {};
<?php foreach ($produkList as $pr): ?>produkStok[<?= $pr['id'] ?>] = <?= (int)$pr['stok'] ?>;<?php endforeach; ?>

const produkOpts = `<option value="">— Pilih Produk —</option><?php foreach ($produkList as $pr): ?><option value="<?= $pr['id'] ?>"><?= addslashes(htmlspecialchars($pr['nama'])) ?></option><?php endforeach; ?>`;
let rowIdx = 1;

function updateStok(sel, idx) {
  const id = sel.value, el = document.getElementById('stok-'+idx);
  const s = produkStok[id];
  el.textContent = id && s !== undefined ? s : '—';
  el.className = 'stok-badge' + (id && s < 10 ? ' stok-badge-low' : '');
}
function addItemRow() {
  const tbody = document.getElementById('itemRows');
  const tr = document.createElement('tr');
  tr.innerHTML = `<td><select name="items[${rowIdx}][produk_id]" class="form-control" required onchange="updateStok(this,${rowIdx})">${produkOpts}</select></td><td class="col-center"><span class="stok-badge" id="stok-${rowIdx}">—</span></td><td><input type="number" name="items[${rowIdx}][qty]" class="form-control qty-input" min="1" placeholder="0" required></td><td><button type="button" class="btn-icon danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>`;
  tbody.appendChild(tr);
  rowIdx++;
}
function removeRow(btn) {
  if (document.getElementById('itemRows').querySelectorAll('tr').length > 1) btn.closest('tr').remove();
}
</script>
</body>
</html>
