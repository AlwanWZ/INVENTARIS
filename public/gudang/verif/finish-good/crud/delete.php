<?php
session_start();
require_once '../../../../../src/auth.php';
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';

$verifModel = new Verifikasi($pdo);
$id = $_GET['id'] ?? null;

if ($id) {
    try {
        // Eksekusi fungsi delete dari model
        $verifModel->delete($id);
    } catch (Exception $e) {
        // Jika gagal hapus karena alasan database/stok, kembalikan dengan pesan error
        header('Location: ../index.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// PERBAIKAN: Tambahkan '../' agar kembali ke index.php di luar folder crud/
header('Location: ../index.php?msg=delete-success');
exit;