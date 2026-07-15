<?php
session_start();
require_once '../../../../src/config.php';

// TANGKAP ID DARI POST (Modal) ATAU GET (URL)
$id = $_POST['id'] ?? $_GET['id'] ?? null;

if ($id) {
    try {
        require_once '../../../../src/models/Pengeluaran.php';
        $pengeluaranModel = new Pengeluaran($pdo);
        
        // Model akan menangani transaksi, pengembalian stok, log stok_tracking,
        // sinkronisasi qty_dikirim ke pesanan_items, dan penghapusan surat_jalan.
        $pengeluaranModel->delete($id);

        header('Location: ../index.php?deleted=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("
            <div style='background:#fff5f5; color:#dc3545; padding:25px; border-radius:10px; font-family:sans-serif; max-width: 600px; margin: 50px auto; border: 2px solid #ffcdd2;'>
                <h2 style='margin-top:0;'><span style='font-size:1.5em;'>⚠️</span> GAGAL MENGHAPUS!</h2>
                <p>Terjadi kesalahan sistem saat menghapus data dan merestore stok.</p>
                <code>Error: " . $e->getMessage() . "</code><br><br>
                <a href='../index.php' style='display:inline-block; background:#dc3545; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; font-weight:bold;'>Kembali</a>
            </div>
        ");
    }
} else {
    // Kalau ID kosong
    die("ID Pengeluaran tidak valid!");
}
?>