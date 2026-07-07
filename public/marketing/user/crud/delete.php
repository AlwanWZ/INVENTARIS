<?php
session_start();

// Validasi akses
if (!isset($_SESSION['user'])) {
    header('Location: /Inventaris/public/dashboard.php');
    exit;
}

require_once '../../../../src/auth.php';
require_once '../../../../src/models/User.php';

// Eksekusi jika requestnya POST dan ID-nya ada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    try {
        // Manggil fungsi delete
        User::delete($id);
        
        // Tendang balik ke halaman index dengan notif sukses
        header('Location: ../index.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        // Tangkap error Foreign Key Constraint (SQLSTATE 23000)
        if ($e->getCode() == '23000') {
            // Balikin ke index bawa parameter error
            header('Location: ../index.php?error=has_relation');
            exit;
        } else {
            // Kalau error database lain
            header('Location: ../index.php?error=db_failed');
            exit;
        }
    }
}

// Kalau ada orang iseng buka file ini langsung via URL
header('Location: ../index.php');
exit;