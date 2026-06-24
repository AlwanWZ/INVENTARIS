<?php
session_start();

// Validasi akses (opsional, pastikan cuma role tertentu yang bisa hapus kalau perlu)
if (!isset($_SESSION['user'])) {
    header('Location: /Inventaris/public/dashboard.php');
    exit;
}

require_once '../../../../src/auth.php';
require_once '../../../../src/models/User.php';

// Eksekusi jika requestnya POST dan ID-nya ada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    // Manggil fungsi delete yang udah lu tambahin di User.php tadi
    User::delete($id);
    
    // Tendang balik ke halaman index dengan notif sukses
    header('Location: ../index.php?deleted=1');
    exit;
}

// Kalau ada orang iseng buka file ini langsung via URL (bukan dari tombol), tendang balik ke index
header('Location: ../index.php');
exit;