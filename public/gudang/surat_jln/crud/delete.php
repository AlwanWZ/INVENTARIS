<?php
require_once '../../../../src/config.php';
require_once '../../../../src/models/SuratJalan.php';

$suratJalanModel = new SuratJalan($pdo);
$id = $_POST['id'] ?? $_GET['id'] ?? '';
$data = $suratJalanModel->getById($id);
if (!$data) {
    header('Location: ../index.php?msg=notfound');
    exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $suratJalanModel->delete($id);
    if ($result['success']) {
      header('Location: ../index.php?msg=delete-success');
      exit;
    } else {
      if (isset($result['errors'])) {
        $errors = $result['errors'];
      } else {
        $errors[] = 'Gagal menghapus data. Cek relasi data di database.';
      }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hapus Surat Jalan | Inventory</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="/Inventaris/public/assets/css/nav.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/marketing-css/dashboard.css" rel="stylesheet">
  <link href="/Inventaris/public/assets/css/gudang-css/surat_jln.css" rel="stylesheet">
</head>
<body>
<?php include '../../../../templates/nav.php'; ?>
<main class="main">
  <div class="content">
    <div class="page-header">
      <h1 class="page-title">Hapus Surat Jalan</h1>
      <a href="detail.php?id=<?= $id ?>" class="btn-outline"><i class="bi bi-arrow-left"></i> Batal</a>
    </div>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
      </div>
    <?php endif; ?>
    <form method="post" class="form-card">
      <p>Yakin ingin menghapus surat jalan <b><?= htmlspecialchars($data['nomor_sj']) ?></b>?</p>
      <div class="form-actions">
        <button type="submit" class="btn-outline danger"><i class="bi bi-trash"></i> Hapus</button>
        <a href="detail.php?id=<?= $id ?>" class="btn-outline">Batal</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
