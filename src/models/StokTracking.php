<?php
/**
 * StokTracking Model
 * 
 * Mengelola semua transaksi stok secara terpusat dan realtime:
 * - Reserve stok saat PO dibuat
 * - Unreserve saat PO dibatalkan
 * - Kurang stok saat pengeluaran
 * - Nambah stok saat verifikasi
 * - Audit trail lengkap
 * 
 * Alur Otomatis:
 * 1. Customer buat PO → reserve stok
 * 2. Produksi selesai → stok nambah, unreserve PO qty
 * 3. Siap kirim → stok kurang (pengeluaran)
 */

class StokTracking {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * RESERVE stok saat PO dibuat/approved
     * Kurangi stok_available, nambah stok_reserved
     */
    public function reserveStok($produk_id, $qty, $reference_type = 'po', $reference_id = null, $created_by = null, $keterangan = '') {
        try {
            $this->pdo->beginTransaction();
            
            // 1. Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM produk 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$produk_id]);
            $produk = $stmt->fetch();
            
            if (!$produk) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // 2. Validate stok_available >= qty
            if ($produk['stok_available'] < $qty) {
                throw new Exception(
                    "Stok tidak cukup untuk " . htmlspecialchars($reference_type) . 
                    ". Dibutuhkan: {$qty} pcs, Tersedia: {$produk['stok_available']} pcs"
                );
            }
            
            // 3. Update produk stok
            $stok_before = $produk['stok_available'];
            $stok_after = $produk['stok_available'] - $qty;
            $stok_reserved_new = $produk['stok_reserved'] + $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE produk 
                SET stok_reserved = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_reserved_new, $stok_after, $produk_id]);
            
            // 4. Log transaksi
            $this->logStok(
                $produk_id,
                'po_reserve',
                $qty,
                $produk['stok_available'],
                $stok_after,
                $produk['stok_reserved'],
                $stok_reserved_new,
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            $this->pdo->commit();
            return ['success' => true, 'message' => "Stok berhasil di-reserve: {$qty} pcs"];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * UNRESERVE stok (batalkan reserve)
     * Nambah stok_available, kurangi stok_reserved
     */
    public function unreserveStok($produk_id, $qty, $reference_type = 'po', $reference_id = null, $created_by = null, $keterangan = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM produk 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$produk_id]);
            $produk = $stmt->fetch();
            
            if (!$produk) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate stok_reserved >= qty
            if ($produk['stok_reserved'] < $qty) {
                throw new Exception("Reserve stok tidak valid");
            }
            
            // Update
            $stok_available_before = $produk['stok_available'];
            $stok_available_after = $produk['stok_available'] + $qty;
            $stok_reserved_new = $produk['stok_reserved'] - $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE produk 
                SET stok_reserved = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_reserved_new, $stok_available_after, $produk_id]);
            
            // Log
            $this->logStok(
                $produk_id,
                'po_unreserve',
                -$qty,
                $stok_available_before,
                $stok_available_after,
                $produk['stok_reserved'],
                $stok_reserved_new,
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            $this->pdo->commit();
            return ['success' => true, 'message' => "Reserve stok berhasil dibatalkan: {$qty} pcs"];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * NAMBAH stok saat verifikasi (barang masuk dari produksi)
     */
    public function addStok($produk_id, $qty, $reference_type = 'verifikasi', $reference_id = null, $created_by = null, $keterangan = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM produk 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$produk_id]);
            $produk = $stmt->fetch();
            
            if (!$produk) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Update
            $stok_before = $produk['stok'];
            $stok_after = $produk['stok'] + $qty;
            $stok_available_after = $produk['stok_available'] + $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE produk 
                SET stok = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_available_after, $produk_id]);
            
            // Log
            $this->logStok(
                $produk_id,
                'verifikasi_add',
                $qty,
                $stok_before,
                $stok_after,
                $produk['stok_reserved'],
                $produk['stok_reserved'],
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            $this->pdo->commit();
            return ['success' => true, 'message' => "Stok berhasil ditambah: {$qty} pcs"];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * KURANG stok saat pengeluaran (shipment ke customer)
     */
    public function reduceStok($produk_id, $qty, $reference_type = 'pengeluaran', $reference_id = null, $created_by = null, $keterangan = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM produk 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$produk_id]);
            $produk = $stmt->fetch();
            
            if (!$produk) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate
            if ($produk['stok'] < $qty) {
                throw new Exception(
                    "Stok tidak cukup untuk pengeluaran. " .
                    "Dibutuhkan: {$qty} pcs, Tersedia: {$produk['stok']} pcs"
                );
            }
            
            // Update
            $stok_before = $produk['stok'];
            $stok_after = $produk['stok'] - $qty;
            $stok_available_after = $produk['stok_available'] - $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE produk 
                SET stok = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_available_after, $produk_id]);
            
            // Log
            $this->logStok(
                $produk_id,
                'pengeluaran_sub',
                -$qty,
                $stok_before,
                $stok_after,
                $produk['stok_reserved'],
                $produk['stok_reserved'],
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            $this->pdo->commit();
            return ['success' => true, 'message' => "Stok berhasil dikurangi: {$qty} pcs"];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * ADJUSTMENT stok manual (untuk koreksi/selisih)
     */
    public function adjustmentStok($produk_id, $qty_change, $keterangan = '', $created_by = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM produk 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$produk_id]);
            $produk = $stmt->fetch();
            
            if (!$produk) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate
            $stok_after = $produk['stok'] + $qty_change;
            if ($stok_after < 0) {
                throw new Exception("Adjustment akan membuat stok negatif");
            }
            
            // Update
            $stok_before = $produk['stok'];
            $stok_available_after = $produk['stok_available'] + $qty_change;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE produk 
                SET stok = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_available_after, $produk_id]);
            
            // Log
            $this->logStok(
                $produk_id,
                'adjustment',
                $qty_change,
                $stok_before,
                $stok_after,
                $produk['stok_reserved'],
                $produk['stok_reserved'],
                'adjustment',
                null,
                $keterangan,
                $created_by
            );
            
            $this->pdo->commit();
            return ['success' => true, 'message' => "Stok berhasil disesuaikan"];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get stok realtime untuk produk
     */
    public function getStokRealtime($produk_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                nama,
                stok,
                stok_reserved,
                stok_available,
                CASE 
                    WHEN stok_available <= 0 THEN 'OUT_OF_STOCK'
                    WHEN stok_available < 50 THEN 'LOW_STOCK'
                    ELSE 'OK'
                END AS status_stok
            FROM produk
            WHERE id = ?
        ");
        $stmt->execute([$produk_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get stok history/audit trail
     */
    public function getStokLog($produk_id, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT 
                sl.*,
                u.username AS created_by_name,
                p.nama AS produk_nama
            FROM stok_log sl
            LEFT JOIN users u ON sl.created_by = u.id
            LEFT JOIN produk p ON sl.produk_id = p.id
            WHERE sl.produk_id = ?
            ORDER BY sl.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$produk_id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Private: Log setiap transaksi stok
     */
    private function logStok($produk_id, $tipe, $qty_change, $stok_before, $stok_after, 
                             $stok_reserved_before, $stok_reserved_after, 
                             $reference_type, $reference_id, $keterangan, $created_by) {
        $stmt = $this->pdo->prepare("
            INSERT INTO stok_log 
            (produk_id, tipe_transaksi, qty_change, stok_before, stok_after, 
             stok_reserved_before, stok_reserved_after, reference_type, reference_id, 
             keterangan, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $produk_id, $tipe, $qty_change, $stok_before, $stok_after,
            $stok_reserved_before, $stok_reserved_after, $reference_type, $reference_id,
            $keterangan, $created_by
        ]);
    }
}
