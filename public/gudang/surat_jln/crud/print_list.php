<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'gudang') {
  echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.close();</script>";
  exit;
}
require_once '../../../../src/config.php';
require_once '../../../../src/models/SuratJalan.php';

$suratJalanModel = new SuratJalan($pdo);
$suratJalanId = $_GET['id'] ?? null;

if (!$suratJalanId) {
    echo "<script>alert('ID Surat Jalan tidak ditemukan!'); window.close();</script>";
    exit;
}

$suratJalan = $suratJalanModel->getById($suratJalanId);
if (!$suratJalan) {
    echo "<script>alert('Data Surat Jalan tidak ditemukan!'); window.close();</script>";
    exit;
}

$items = $suratJalanModel->getItems($suratJalanId);

// Mapping data ke variabel
$nomor_sj       = $suratJalan['nomor_sj'] ?? '-';
$tanggal_kirim  = $suratJalan['tanggal_kirim'] ?? date('Y-m-d');
$driver         = $suratJalan['driver'] ?? '-';
$kendaraan      = $suratJalan['kendaraan'] ?? '-';
$customer       = $suratJalan['customer_nama'] ?? 'Customer';
$perusahaan     = $suratJalan['perusahaan'] ?? '-';
$alamat_kirim   = $suratJalan['alamat_kirim'] ?? '-';
$catatan        = $suratJalan['catatan'] ?? 'Tidak ada catatan khusus.';
$totalQty       = 0;
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Surat Jalan - <?= htmlspecialchars($nomor_sj) ?></title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; margin: 0; padding: 0; color: #000; }
    .page { width: 210mm; min-height: 297mm; padding: 15mm; margin: auto; background: #fff; box-sizing: border-box; }
    
    /* Kop Surat Celebit */
    .kop-surat { display: flex; align-items: center; margin-bottom: 5px; }
    .kop-logo { width: 140px; margin-right: 20px; }
    .kop-text { flex-grow: 1; text-align: left; }
    .kop-text h1 { margin: 0 0 5px 0; font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; }
    .kop-text p { margin: 0; font-size: 8.5pt; line-height: 1.3; }
    
    /* Garis Ganda Kop Surat */
    .garis-tebal { border: 0; border-bottom: 3px solid #000; margin-bottom: 2px; }
    .garis-tipis { border: 0; border-bottom: 1px solid #000; margin-bottom: 20px; }
    
    /* Judul Surat Jalan */
    .judul-surat { text-align: center; margin-bottom: 25px; }
    .judul-surat h2 { margin: 0; font-size: 14pt; text-decoration: underline; font-weight: bold; }
    .judul-surat p { margin: 5px 0 0 0; font-size: 11pt; }
    
    /* Info Surat Jalan */
    .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .info-table td { padding: 5px; vertical-align: top; }
    .info-table td:nth-child(1) { width: 150px; font-weight: bold; }
    .info-table td:nth-child(2) { width: 10px; }
    
    /* Tabel Item */
    .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
    .item-table th, .item-table td { border: 1px solid #000; padding: 8px; }
    .item-table th { background-color: #f0f0f0; text-align: center; }
    .item-table td:nth-child(1) { text-align: center; width: 5%; }
    .item-table td:nth-child(3) { text-align: center; width: 15%; }
    .item-table td:nth-child(4) { text-align: center; width: 12%; }
    
    /* Catatan Box */
    .catatan-box { border: 1px solid #000; padding: 10px; min-height: 50px; margin-bottom: 30px; font-size: 10pt; }
    .catatan-box strong { display: block; margin-bottom: 5px; }
    
    /* Tanda Tangan */
    .ttd-container { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; font-size: 10pt; }
    .ttd-box { width: 250px; }
    .ttd-box p { margin: 0; }
    .ttd-space { height: 80px; }
    .ttd-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
    
    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      @page { size: A4 portrait; margin: 0; }
      .page { box-shadow: none; margin: 0; }
    }
  </style>
</head>
<body>

<div style="text-align:center; padding: 10px;" class="no-print">
  <button onclick="window.print()" style="padding:10px 20px; background:#10b981; color:#fff; border:none; cursor:pointer; font-weight:bold; border-radius: 5px;">
    <i class="bi bi-printer"></i> PRINT SURAT JALAN SEKARANG
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

  <div class="judul-surat">
    <h2>SURAT JALAN / DELIVERY ORDER</h2>
    <p>Nomor: <?= htmlspecialchars($nomor_sj) ?></p>
  </div>

  <table class="info-table">
    <tr>
      <td>Tanggal Kirim</td><td>:</td>
      <td><?= htmlspecialchars($tanggal_kirim) ?></td>
    </tr>
    <tr>
      <td>Customer / Penerima</td><td>:</td>
      <td><strong><?= htmlspecialchars($customer) ?></strong></td>
    </tr>
    <tr>
      <td>Perusahaan Penerima</td><td>:</td>
      <td><?= htmlspecialchars($perusahaan) ?></td>
    </tr>
    <tr>
      <td>Alamat Kirim</td><td>:</td>
      <td><?= htmlspecialchars($alamat_kirim) ?></td>
    </tr>
    <tr>
      <td>Driver</td><td>:</td>
      <td><?= htmlspecialchars($driver) ?></td>
    </tr>
    <tr>
      <td>Kendaraan</td><td>:</td>
      <td><?= htmlspecialchars($kendaraan) ?></td>
    </tr>
  </table>

  <p style="font-weight: bold; margin-bottom: 5px;">Daftar Barang yang Dikirim:</p>
  <table class="item-table">
    <thead>
      <tr>
        <th>No</th>
        <th>Deskripsi Barang / Part</th>
        <th>PO No</th>
        <th>Qty</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
      <tr>
        <td colspan="4" style="text-align: center; font-style: italic; color: #666;">Data item belum ditambahkan.</td>
      </tr>
      <?php else: ?>
        <?php foreach ($items as $i => $row): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($row['produk_nama'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['nomor_po'] ?? '-') ?></td>
          <td><?= number_format($row['qty'] ?? 0, 0, ',', '.') ?></td>
        </tr>
        <?php $totalQty += $row['qty'] ?? 0; endforeach; ?>
        <tr style="font-weight: bold;">
          <td colspan="3" style="text-align: right; padding-right: 15px;">TOTAL QTY</td>
          <td style="text-align: center;"><?= number_format($totalQty, 0, ',', '.') ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="catatan-box">
    <strong>Catatan / Instruksi Pengiriman:</strong>
    <?= nl2br(htmlspecialchars($catatan)) ?: 'Tidak ada catatan khusus' ?>
  </div>

  <p style="font-size: 10pt; text-align: justify;">
    <i>Barang dikirim dalam kondisi baik dan sesuai dengan daftar di atas. Penerima dimohon untuk melakukan pengecekan dan verifikasi sebelum menandatangani dokumen ini.</i>
  </p>

  <div class="ttd-container">
    <div class="ttd-box">
      <p>Pengirim,<br><strong>PT. CELEBIT</strong></p>
      <div class="ttd-space"></div>
      <p class="ttd-name">( ...................................... )</p>
    </div>
    
    <div class="ttd-box">
      <p>Penerima,<br><strong><?= htmlspecialchars($customer) ?></strong></p>
      <div class="ttd-space"></div>
      <p class="ttd-name">( ...................................... )</p>
    </div>
  </div>

</div>

</body>
</html>