<?php
require_once '../../../../src/config.php';
require_once '../../../../src/models/Penerimaan.php';

$penerimaanModel = new Penerimaan($pdo);
$id = $_GET['id'] ?? null;
if ($id) {
    $penerimaanModel->delete($id);
}
header('Location: ../index.php');
exit;
