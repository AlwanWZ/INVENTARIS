<?php
session_start();
// Hak akses sudah disesuaikan agar marketing dan manager bisa cetak
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['gudang', 'admin', 'sales', 'marketing', 'manager'])) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.close();</script>";
    exit;
}
require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/config.php';

// Fungsi untuk konversi angka ke kalimat (Terbilang) - Fixed PHP 8.1+ Deprecated Error
function terbilang($angka) {
    $angka = abs((float)$angka);
    $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $terbilang = "";

    if ($angka < 12) {
        // Casting ke int untuk indeks array agar tidak error di PHP 8.1+
        $terbilang = " " . $baca[(int)$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " Belas";
    } else if ($angka < 100) {
        // Gunakan floor untuk pembagian dan fmod untuk modulo
        $terbilang = terbilang(floor($angka / 10)) . " Puluh" . terbilang(fmod($angka, 10));
    } else if ($angka < 200) {
        $terbilang = " Seratus" . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang(floor($angka / 100)) . " Ratus" . terbilang(fmod($angka, 100));
    } else if ($angka < 2000) {
        $terbilang = " Seribu" . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang(floor($angka / 1000)) . " Ribu" . terbilang(fmod($angka, 1000));
    } else if ($angka < 1000000000) {
        $terbilang = terbilang(floor($angka / 1000000)) . " Juta" . terbilang(fmod($angka, 1000000));
    } else if ($angka < 1000000000000) {
        $terbilang = terbilang(floor($angka / 1000000000)) . " Miliar" . terbilang(fmod($angka, 1000000000));
    } else if ($angka < 1000000000000000) {
        $terbilang = terbilang(floor($angka / 1000000000000)) . " Triliun" . terbilang(fmod($angka, 1000000000000));
    }
    
    return $terbilang;
}

$poId = $_GET['id'] ?? null;

if (!$poId) {
    echo "<script>alert('ID Pesanan tidak ditemukan!'); window.close();</script>";
    exit;
}

global $pdo;
// UPDATE QUERY: Mengambil kolom c.perusahaan dengan alias customer_pt
$stmtPo = $pdo->prepare("
    SELECT po.*, c.nama AS customer_nama, c.perusahaan AS customer_pt, c.alamat AS customer_alamat, c.no_hp AS customer_no_hp 
    FROM po 
    LEFT JOIN customers c ON po.customer_id = c.id 
    WHERE po.id = ?
");
$stmtPo->execute([$poId]);
$po = $stmtPo->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    echo "<script>alert('Data Pesanan tidak ditemukan!'); window.close();</script>";
    exit;
}

// Ambil PO items
$stmtItems = $pdo->prepare("
    SELECT poi.*, p.stok, p.stok_available, p.stok_reserved
    FROM po_items poi
    LEFT JOIN produk p ON poi.produk_id = p.id
    WHERE poi.po_id = ?
");
$stmtItems->execute([$po['id']]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Mapping data dari database
$tanggal_pesanan   = $po['tanggal'] ?? date('Y-m-d');
$creator           = $po['created_by'] ?? $_SESSION['user']['username'] ?? '-';
$customer_nama     = $po['customer_nama'] ?? $po['customer'] ?? 'Nama Customer';
$customer_pt       = $po['customer_pt'] ?? 'Nama Perusahaan'; // Data baru perusahaan
$alamat_customer   = $po['customer_alamat'] ?? 'Alamat tidak ditemukan'; 
$telepon_customer  = $po['customer_no_hp'] ?? '-'; 
$catatan_po        = $po['notes'] ?? ''; 

// Kalkulasi Total Terlebih Dahulu agar bisa di-terbilang-kan
$subtotal_all = 0;
foreach ($items as $row) {
    $qty = $row['qty'] ?? 0;
    $price = $row['harga_satuan'] ?? 0;
    $subtotal_all += $row['amount'] ?? ($qty * $price);
}

$ppn_value = 0.11 * $subtotal_all; // PPN 11%
$grand_total = $subtotal_all + $ppn_value;

// Generate Terbilang
$terbilang_str = trim(terbilang(round($grand_total))) . " Rupiah";
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Pesanan - <?= htmlspecialchars($customer_pt) ?> (<?= htmlspecialchars($customer_nama) ?>)</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; margin: 0; padding: 0; color: #000; }
    .page { width: 210mm; min-height: 297mm; padding: 15mm; margin: auto; background: #fff; box-sizing: border-box; }
    
    /* Kop Surat */
    .kop-surat { display: flex; align-items: center; margin-bottom: 5px; }
    .kop-logo { width: 140px; margin-right: 20px; }
    .kop-text { flex-grow: 1; text-align: left; }
    .kop-text h1 { margin: 0 0 5px 0; font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; }
    .kop-text p { margin: 0; font-size: 8.5pt; line-height: 1.3; }
    
    .garis-tebal { border: 0; border-bottom: 3px solid #000; margin-bottom: 2px; }
    .garis-tipis { border: 0; border-bottom: 1px solid #000; margin-bottom: 20px; }
    
    /* Meta Info */
    .meta-container { display: flex; justify-content: space-between; margin-bottom: 25px; line-height: 1.4; }
    .meta-left { width: 55%; }
    .meta-right { width: 40%; }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 3px 5px; vertical-align: top; }
    .meta-table td.label { width: 100px; font-weight: bold; }
    .meta-table td.colon { width: 10px; }
    
    /* Tabel Item */
    .item-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9.5pt; }
    .item-table th, .item-table td { border: 1px solid #000; padding: 6px; }
    .item-table th { background-color: #f0f0f0; text-align: center; font-weight: bold; }
    .item-table td.center { text-align: center; }
    .item-table td.right { text-align: right; }
    
    /* Summary */
    .summary-container { display: flex; justify-content: space-between; margin-bottom: 20px; }
    .notes-box { width: 55%; font-size: 9pt; line-height: 1.4; }
    .notes-text { border: 1px solid #000; padding: 8px; min-height: 60px; margin-top: 5px; }
    .tax-box { width: 40%; }
    .tax-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    .tax-table td { padding: 4px; }
    .tax-table td.right { text-align: right; }
    .tax-table tr.grand-total { font-weight: bold; border-top: 2px solid #000; font-size: 10.5pt; }
    
    /* Info Tambahan */
    .additional-info { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 10px 0; margin-bottom: 30px; font-size: 9.5pt; line-height: 1.5; }
    .info-row { display: flex; margin-bottom: 5px; }
    .info-label { width: 150px; font-weight: bold; }
    .info-colon { width: 15px; }
    .info-value { flex-grow: 1; }
    
    /* Tanda Tangan */
    .ttd-container { display: flex; justify-content: flex-end; text-align: center; margin-top: 20px; }
    .ttd-box { width: 300px; font-size: 9.5pt; }
    .ttd-space { height: 65px; position: relative; }
    
    /* Trik Elemen Input Halaman Cetak */
    .bisa-edit {
        border: 1px dashed #fd7e14;
        background: #fffcf5;
        padding: 2px 5px;
        font-family: Arial, sans-serif;
        font-size: inherit;
        color: #000;
        width: 100%;
        box-sizing: border-box;
    }
    textarea.bisa-edit { resize: none; height: 60px; }

    /* Fix untuk Dropdown saat diprint agar panahnya hilang */
    select.bisa-edit {
        cursor: pointer;
    }

    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      @page { size: A4 portrait; margin: 10mm; }
      .page { box-shadow: none; margin: 0; padding: 0; }
      .bisa-edit {
          border: none !important;
          background: transparent !important;
          padding: 0 !important;
          outline: none !important;
      }
      /* Menghilangkan tanda panah pada dropdown saat diprint */
      select.bisa-edit {
          -webkit-appearance: none;
          -moz-appearance: none;
          appearance: none;
      }
    }
  </style>
</head>
<body>

<div style="text-align:center; padding: 10px; background: #e9ecef;" class="no-print">
  <small style="color: #666; display:block; margin-bottom:5px;"> Tips: Anda bisa mengubah teks berwarna krem atau memilih dropdown sebelum mencetak.</small>
  <button onclick="window.print()" style="padding:10px 20px; background:#fd7e14; color:#fff; border:none; cursor:pointer; font-weight:bold; border-radius: 5px;">
     PRINT DOKUMEN PESANAN
  </button>
</div>

<div class="page">
  <div class="kop-surat">
    <img src="/Inventaris/public/assets/img/celebit-logo.png" alt="Logo Celebit" class="kop-logo" onerror="this.style.display='none'">
    <div class="kop-text">
      <h1>PT. CELEBIT CIRCUIT TECHNOLOGY INDONESIA</h1>
      <p>BANDUNG FACTORY : JL.BUAH DUA RT.01/RW.04 RANCAEKEK - BANDUNG-INDONESIA<br>
      TEL 62-22-7798 561/7798542, FAX: 62-22-7798 562 E-MAIL: celebit@celebit.id</p>
    </div>
  </div>
  <hr class="garis-tebal">
  <hr class="garis-tipis">

  <div class="meta-container">
    <div class="meta-left">
      <table class="meta-table">
        <tr>
          <td class="label">Customer</td><td class="colon">:</td>
          <td><strong><?= htmlspecialchars($customer_pt) ?></strong> (<?= htmlspecialchars($customer_nama) ?>)</td>
        </tr>
        <tr>
          <td class="label">Address</td><td class="colon">:</td>
          <td><?= htmlspecialchars($alamat_customer) ?></td>
        </tr>
        <tr>
          <td class="label">Tel.</td><td class="colon">:</td>
          <td><?= htmlspecialchars($telepon_customer) ?></td>
        </tr>
      </table>
    </div>
    <div class="meta-right">
      <table class="meta-table">
        <tr>
          <td class="label">Date</td><td class="colon">:</td>
          <td><?= htmlspecialchars($tanggal_pesanan) ?></td>
        </tr>
        <tr>
          <td class="label">Creator</td><td class="colon">:</td>
          <td><?= htmlspecialchars($creator) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <table class="item-table">
    <thead>
      <tr>
        <th style="width: 5%;">No.</th>
        <th style="width: 18%;">Material Code</th>
        <th style="width: 37%;">Material Description</th>
        <th style="width: 12%;">Quantity</th>
        <th style="width: 8%;">Unit</th>
        <th style="width: 10%;">Price (Rp)</th>
        <th style="width: 10%;">Subtotal (Rp)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
      <tr>
        <td colspan="7" class="center" style="font-style: italic; color: #666;">Belum ada item pesanan.</td>
      </tr>
      <?php else: ?>
        <?php foreach ($items as $i => $row): 
          $qty = $row['qty'] ?? 0;
          $price = $row['harga_satuan'] ?? 0;
          $subtotal = $row['amount'] ?? ($qty * $price);
        ?>
        <tr>
          <td class="center"><?= $i + 1 ?></td>
          <td class="center"><?= htmlspecialchars($row['kode_material'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['nama_material'] ?? '-') ?></td>
          <td class="center"><?= number_format($qty, 0, ',', '.') ?></td>
          <td class="center"><?= htmlspecialchars($row['uom'] ?? 'PCS') ?></td>
          <td class="right"><?= number_format($price, 0, ',', '.') ?></td>
          <td class="right"><?= number_format($subtotal, 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="summary-container">
    <div class="notes-box">
      <strong>Notes :</strong>
      <div class="notes-text" style="padding:0;">
        <textarea class="bisa-edit" placeholder="Ketik catatan tambahan disini..."><?= htmlspecialchars($catatan_po) ?></textarea>
      </div>
      <br>
      <strong>Num in Words :</strong>
      <div style="margin-top: 5px;">
        <textarea class="bisa-edit" style="font-style: italic; font-weight: bold; height: 35px; resize: none; overflow: hidden;"><?= htmlspecialchars($terbilang_str) ?></textarea>
      </div>
    </div>
    
    <div class="tax-box">
      <table class="tax-table">
        <tr>
          <td>SubTotal</td>
          <td class="right"><?= number_format($subtotal_all, 0, ',', '.') ?></td>
        </tr>
        <tr>
          <td>PPN (11%)</td>
          <td class="right"><?= number_format($ppn_value, 0, ',', '.') ?></td>
        </tr>
        <tr class="grand-total">
          <td>GrandTotal</td>
          <td class="right"><?= number_format($grand_total, 0, ',', '.') ?></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="additional-info">
    <div class="info-row">
      <div class="info-label">Payment Terms</div><div class="info-colon">:</div>
      <div class="info-value">
          <select class="bisa-edit">
              <option value="Prepayment, Cash on Delivery">Prepayment, Cash on Delivery (COD)</option>
              <option value="Cash in Advance">Cash in Advance (CIA)</option>
              <option value="Net 15 Days">Net 15 Days</option>
              <option value="Net 30 Days">Net 30 Days</option>
              <option value="Net 45 Days">Net 45 Days</option>
              <option value="Net 60 Days">Net 60 Days</option>
          </select>
      </div>
    </div>
    <div class="info-row">
      <div class="info-label">Country of Origin</div><div class="info-colon">:</div>
      <div class="info-value">
          <input type="text" class="bisa-edit" value="Indonesia">
      </div>
    </div>
    <div class="info-row">
      <div class="info-label">Delivery To</div><div class="info-colon">:</div>
      <div class="info-value">
          <input type="text" class="bisa-edit" value="<?= htmlspecialchars($customer_pt) ?>, <?= htmlspecialchars($alamat_customer) ?>">
      </div>
    </div>
  </div>

  <div class="ttd-container">
    <div class="ttd-box">
      <p>Confirmed by<br><strong><?= htmlspecialchars($customer_pt) ?></strong></p>
      <div class="ttd-space"></div>
      <p><input type="text" class="bisa-edit" style="text-align: center; font-weight: bold; text-decoration: underline;" value="Brady Huang" placeholder="Nama Penandatangan"></p>
      <p style="margin: 2px 0 0 0;"><input type="text" class="bisa-edit" style="text-align: center; color: #444; font-size: 9pt;" value="Direktur Utama" placeholder="Jabatan"></p>
    </div>
  </div>

</div>

</body>
</html>