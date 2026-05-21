<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';
$verifModel = new Verifikasi($pdo);
$id = $_GET['id'] ?? null;
if ($id) {
  $verifModel->delete($id);
}
header('Location: index.php?msg=delete-success');
exit;
