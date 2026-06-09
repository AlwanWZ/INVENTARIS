<?php
session_start();
if (!isset($_SESSION['user'])) {
  echo "<script>alert('Akses ditolak!'); window.close();</script>";
  exit;
}

require_once '../../../../src/config.php';
require_once '../../../../src/models/SPK.php';

$spkId = $_GET['id'] ?? null;

if ($spkId) {
    // 1. Ambil data Induk (SPK)
    $spk = SPK::find($spkId);
    if (!$spk) {
        echo "<script>alert('Data SPK tidak ditemukan!'); window.close();</script>";
        exit;
    }

    // 2. Ambil data Anak (Barang/Tugas yang harus dikerjain) - simple version tanpa JOIN
    $items = SPK::getItemsSimple($spkId);
} else {
    echo "<script>alert('ID SPK tidak ditemukan!'); window.close();</script>";
    exit;
}

// 3. Mapping data Induk ke Variabel Header SPK
$nomor_spk      = $spk['nomor_spk'] ?? '-';
$tanggal        = $spk['tanggal'] ?? date('Y-m-d');
$deadline       = $spk['deadline'] ?? '-';
$customer_nama  = $spk['customer_nama'] ?? 'N/A';
$perusahaan     = $spk['perusahaan'] ?? 'N/A'; 
$pic            = $spk['pic_username'] ?? 'Tim Produksi'; 
$catatan        = $spk['notes'] ?? 'Tidak ada catatan khusus.';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak SPK - <?= htmlspecialchars($nomor_spk) ?></title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; margin: 0; padding: 0; color: #000; }
    .page { width: 210mm; min-height: 297mm; padding: 15mm; margin: auto; background: #fff; box-sizing: border-box;}
    
    /* Kop Surat Celebit */
    .kop-surat { display: flex; align-items: center; margin-bottom: 5px; }
    .kop-logo { width: 140px; margin-right: 20px; } /* Sesuaikan lebar logo jika perlu */
    .kop-text { flex-grow: 1; text-align: left; }
    .kop-text h1 { margin: 0 0 5px 0; font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; }
    .kop-text p { margin: 0; font-size: 8.5pt; line-height: 1.3; }
    
    /* Garis Ganda Kop Surat */
    .garis-tebal { border: 0; border-bottom: 3px solid #000; margin-bottom: 2px; }
    .garis-tipis { border: 0; border-bottom: 1px solid #000; margin-bottom: 20px; }
    
    /* Judul SPK */
    .judul-surat { text-align: center; margin-bottom: 25px; }
    .judul-surat h2 { margin: 0; font-size: 14pt; text-decoration: underline; font-weight: bold; }
    .judul-surat p { margin: 5px 0 0 0; font-size: 11pt; }
    
    /* Info SPK */
    .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
    .info-table td { padding: 5px; vertical-align: top; }
    .info-table td:nth-child(1) { width: 150px; font-weight: bold; }
    .info-table td:nth-child(2) { width: 10px; }
    
    /* Tabel Item/Tugas */
    .tugas-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
    .tugas-table th, .tugas-table td { border: 1px solid #000; padding: 8px; }
    .tugas-table th { background-color: #f0f0f0; text-align: center; }
    .tugas-table td:nth-child(1) { text-align: center; width: 5%; }
    .tugas-table td:nth-child(3) { text-align: center; width: 15%; }
    .tugas-table td:nth-child(4) { width: 25%; }
    
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
      .page { box-shadow: none; }
    }
  </style>
</head>
<body>

<div style="text-align:center; padding: 10px;" class="no-print">
  <button onclick="window.print()" style="padding:10px 20px; background:#10b981; color:#fff; border:none; cursor:pointer; font-weight:bold; border-radius: 5px;">
    <i class="bi bi-printer"></i> PRINT SPK SEKARANG
  </button>
</div>

<div class="page">
  <div class="kop-surat">
    <img src="/Inventaris/public/assets/img/celebit-logo.png" alt="Logo Celebit" class="kop-logo">
    <div class="kop-text">
      <h1>PT. CELEBIT CIRCUIT TECHNOLOGY INDONESIA</h1>
      <p>BANDUNG FACTORY : JL.BUAH DUA RT.01/RW.04 RANCAEKEK - BANDUNG-INDONESIA<br>
      TEL 62-22-7798 561/7798542, FAX: 62-22-7798 562 E-MAIL: celebit@celebit.id</p>
    </div>
  </div>
  <hr class="garis-tebal">
  <hr class="garis-tipis">

  <div class="judul-surat">
    <h2>SURAT PERINTAH KERJA (SPK)</h2>
    <p>Nomor: <?= htmlspecialchars($nomor_spk) ?></p>
  </div>

  <table class="info-table">
    <tr>
      <td>Tanggal SPK</td><td>:</td>
      <td><?= htmlspecialchars($tanggal) ?></td>
    </tr>
    <tr>
      <td>Nama Pelanggan</td><td>:</td>
      <td><strong><?= htmlspecialchars($customer_nama) ?></strong></td>
    </tr>
    <tr>
      <td>Perusahaan Penerima</td><td>:</td>
      <td><?= htmlspecialchars($perusahaan) ?></td>
    </tr>
    <tr>
      <td>Target Selesai (Deadline)</td><td>:</td>
      <td style="color: red; font-weight: bold;"><?= htmlspecialchars($deadline) ?></td>
    </tr>
    <tr>
      <td>PIC / Pelaksana</td><td>:</td>
      <td><?= htmlspecialchars($pic) ?></td>
    </tr>
  </table>

  <p style="font-weight: bold; margin-bottom: 5px;">Rincian Pekerjaan / Produksi:</p>
  <table class="tugas-table">
    <thead>
      <tr>
        <th>No</th>
        <th>Deskripsi Barang / Pekerjaan</th>
        <th>Target (Qty)</th>
        <th>Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
      <tr>
        <td colspan="4" style="text-align: center; font-style: italic; color: #666;">Data item pekerjaan belum ditambahkan.</td>
      </tr>
      <?php else: ?>
        <?php foreach ($items as $i => $row): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($row['nama_barang']) ?></td>
          <td><?= htmlspecialchars($row['qty_po']) ?></td>
          <td><?= htmlspecialchars($row['note'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="catatan-box">
    <strong>Catatan / Instruksi Khusus:</strong>
    <?= nl2br(htmlspecialchars($catatan)) ?>
  </div>

  <p style="font-size: 10pt; text-align: justify;">
    <i>Pekerjaan harap dilaksanakan sesuai dengan spesifikasi dan target waktu yang telah ditentukan. Kegagalan dalam memenuhi standar kualitas atau keterlambatan dapat dikenakan sanksi sesuai prosedur perusahaan.</i>
  </p>

  <div class="ttd-container">
    <div class="ttd-box">
      <p>Pemberi Tugas,<br><strong>Manager Produksi/Marketing</strong></p>
      <div class="ttd-space"></div>
      <p class="ttd-name">( ...................................... )</p>
    </div>
    
    <div class="ttd-box">
      <p>Menerima Tugas,<br><strong>Pelaksana / PIC</strong></p>
      <div class="ttd-space"></div>
      <p class="ttd-name">( <?= htmlspecialchars($pic) ?> )</p>
    </div>
  </div>

</div>

</body>
</html>