<?php
session_start();
require_once '../../../src/config.php';

// FILTER
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';

// QUERY DINAMIS
$where = [];
$params = [];

if ($from) {
  $where[] = "po.tanggal >= ?";
  $params[] = $from;
}
if ($to) {
  $where[] = "po.tanggal <= ?";
  $params[] = $to;
}
if ($status && $status !== 'Semua') {
  $where[] = "po.status = ?";
  $params[] = $status;
}

$sql = "
SELECT po.*, customers.nama_perusahaan 
FROM po
JOIN customers ON po.customer_id = customers.id
";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY po.tanggal DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// SUMMARY
$totalPO = count($data);
$totalUang = array_sum(array_column($data, 'total'));

$approved = count(array_filter($data, fn($d) => $d['status'] === 'approved'));
$rejected = count(array_filter($data, fn($d) => $d['status'] === 'rejected'));

// TOP CUSTOMER
$topCustomer = [];
foreach ($data as $d) {
  $topCustomer[$d['nama_perusahaan']] = ($topCustomer[$d['nama_perusahaan']] ?? 0) + $d['total'];
}
arsort($topCustomer);
$topName = array_key_first($topCustomer);
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Laporan Order</title>
  <link rel="stylesheet" href="/Inventaris/public/assets/css/marketing-css/laporan.css">
</head>
<body>

<h2>Laporan Purchase Order</h2>

<!-- FILTER -->
<form method="GET" class="filter">
  <input type="date" name="from" value="<?= $from ?>">
  <input type="date" name="to" value="<?= $to ?>">

  <select name="status">
    <option>Semua</option>
    <option value="approved">Approved</option>
    <option value="completed">Completed</option>
    <option value="rejected">Rejected</option>
  </select>

  <button type="submit">Filter</button>
</form>

<!-- SUMMARY -->
<div class="summary">
  <div>Total PO: <?= $totalPO ?></div>
  <div>Total Revenue: Rp <?= number_format($totalUang,0,',','.') ?></div>
  <div>Approved: <?= $approved ?></div>
  <div>Rejected: <?= $rejected ?></div>
  <div>Top Customer: <?= $topName ?? '-' ?></div>
</div>

<!-- EXPORT -->
<a href="export.php?from=<?= $from ?>&to=<?= $to ?>&status=<?= $status ?>" class="btn-export">
  Download Excel
</a>

<!-- TABLE -->
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Nomor PO</th>
      <th>Customer</th>
      <th>Tanggal</th>
      <th>Status</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $i => $d): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= $d['nomor_po'] ?></td>
      <td><?= $d['nama_perusahaan'] ?></td>
      <td><?= $d['tanggal'] ?></td>
      <td><?= $d['status'] ?></td>
      <td>Rp <?= number_format($d['total'],0,',','.') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>
