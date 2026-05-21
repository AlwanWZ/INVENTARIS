<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/models/SPK.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        SPK::delete($id);
        header('Location: ../index.php?deleted=1');
        exit;
    }
}
header('Location: ../index.php');
