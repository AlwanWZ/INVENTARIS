<?php
session_start();
require_once '../../../../src/config.php';

// TANGKAP ID DARI POST (Modal) ATAU GET (URL)
$id = $_POST['id'] ?? $_GET['id'] ?? null;

if ($id) {
    try {
        $pdo->beginTransaction();

        // 1. KEMBALIKAN STOK BARANG (RESTORE STOK KE GUDANG)
        // Kita cari tau dulu barang apa aja dan berapa jumlah yang kemarin dikeluarin
        $stmtItems = $pdo->prepare("SELECT produk_id, qty FROM pengeluaran_items WHERE pengeluaran_id = ?");
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Balikin angkanya ke tabel produk
        $stmtRestore = $pdo->prepare("UPDATE produk SET stok_available = stok_available + ?, stok = stok + ? WHERE id = ?");
        foreach ($items as $item) {
            $stmtRestore->execute([$item['qty'], $item['qty'], $item['produk_id']]);
        }

        // 2. HAPUS SURAT JALAN & ITEMS-NYA
        $stmtSj = $pdo->prepare("SELECT id FROM surat_jalan WHERE pengeluaran_id = ?");
        $stmtSj->execute([$id]);
        $sjId = $stmtSj->fetchColumn();

        if ($sjId) {
            // Hapus anak surat_jalan dulu
            $pdo->prepare("DELETE FROM surat_jalan_items WHERE surat_jalan_id = ?")->execute([$sjId]);
            // Baru hapus bapak surat_jalan
            $pdo->prepare("DELETE FROM surat_jalan WHERE id = ?")->execute([$sjId]);
        }

        // 3. HAPUS PENGELUARAN & ITEMS-NYA
        $pdo->prepare("DELETE FROM pengeluaran_items WHERE pengeluaran_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?")->execute([$id]);

        $pdo->commit();
        header('Location: ../index.php?deleted=1');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
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