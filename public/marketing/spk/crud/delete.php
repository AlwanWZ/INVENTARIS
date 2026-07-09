<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/models/SPK.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        try {
            SPK::delete($id);
            header('Location: ../index.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $errorMsg = addslashes($e->getMessage());
            echo "<script>alert('{$errorMsg}'); window.location.href='../index.php';</script>";
            exit;
        }
    }
}
header('Location: ../index.php');
