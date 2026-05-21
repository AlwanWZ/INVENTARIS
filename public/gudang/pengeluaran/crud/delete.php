<?php
require_once '../../../../src/config.php';
require_once '../../../../src/models/Pengeluaran.php';

$pengeluaranModel = new Pengeluaran($pdo);
$id = $_GET['id'] ?? null;
if ($id) {
    $pengeluaranModel->delete($id);
}
header('Location: ../index.php');
exit;
