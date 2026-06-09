<?php
session_start();
require_once '../../../../src/config.php';
require_once '../../../../src/models/Penerimaan.php';

// Aktifkan tampilan error untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// TANGKAP ID DARI POST (Modal) ATAU GET (URL)
$id = $_POST['id'] ?? $_GET['id'] ?? null;

if ($id) {
    try {
        $penerimaanModel = new Penerimaan($pdo);
        
        // Eksekusi hapus di model (sekaligus rollback stok)
        $penerimaanModel->delete($id);
        
        // Kalau sukses, balik ke index dengan trigger notifikasi 'deleted'
        header('Location: ../index.php?deleted=1');
        exit;

    } catch (PDOException $e) {
        // Tangkap kalau database nolak karena Foreign Key
        if ($e->getCode() == '23000') {
            die("
            <div style='background:#fff5f5; color:#dc3545; padding:25px; border-radius:10px; font-family:sans-serif; max-width: 600px; margin: 50px auto; border: 2px solid #ffcdd2; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                <h2 style='margin-top:0;'><span style='font-size:1.5em;'>⚠️</span> GAGAL MENGHAPUS!</h2>
               <p style='font-size:16px; line-height:1.5;'>Penerimaan ini <strong>TIDAK BISA DIHAPUS</strong> karena masih terikat dengan data lain di sistem database (Constraint).</p>
<div style='background:#f8d7da; padding:10px; border-radius:5px; font-family:monospace; font-size:12px; color:#721c24;'>
    <strong>Pesan Asli Database:</strong><br>
    " . htmlspecialchars($e->getMessage()) . "
</div>
            ");
        } else {
            die("Error Database PDO: " . $e->getMessage());
        }
    } catch (Exception $e) {
        die("Error Sistem (Stok Tracking): " . $e->getMessage());
    }
} else {
    // Kalau ID-nya tetap kosong/tidak terkirim
    die("
    <div style='background:#fff3cd; color:#856404; padding:20px; border-radius:8px; font-family:sans-serif; max-width: 600px; margin: 50px auto; border: 1px solid #ffeeba;'>
        <h3>ID Penerimaan tidak valid atau kosong!</h3>
        <p>Sistem tidak menerima ID data yang ingin dihapus. Pastikan form pada modal Anda terkirim dengan benar.</p>
        <a href='../index.php' style='background:#856404; color:white; padding:8px 15px; text-decoration:none; border-radius:5px;'>Kembali</a>
    </div>
    ");
}
?>