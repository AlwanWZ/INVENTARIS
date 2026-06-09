<?php
require_once __DIR__ . '/../config.php';

class StokLog {
    public static function record($produk_id, $tipe_transaksi, $qty_change, $stok_before, $stok_after, $res_before, $res_after, $ref_type, $ref_id, $keterangan) {
        global $pdo;
        // Ambil ID user yang lagi login, default 1 kalau gak ada session
        $user_id = $_SESSION['user']['id'] ?? 1; 
        
        $sql = "INSERT INTO stok_log (produk_id, tipe_transaksi, qty_change, stok_before, stok_after, stok_reserved_before, stok_reserved_after, reference_type, reference_id, keterangan, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $produk_id, $tipe_transaksi, $qty_change, 
            $stok_before, $stok_after, 
            $res_before, $res_after, 
            $ref_type, $ref_id, $keterangan, $user_id
        ]);
    }
}
?>