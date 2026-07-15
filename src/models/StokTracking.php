<?php
/**
 * StokTracking Model (SAFE TRANSACTION VERSION)
 * 
 * Mengelola semua transaksi stok secara terpusat dan realtime:
 * - Reserve stok saat PO dibuat
 * - Unreserve saat PO dibatalkan
 * - Kurang stok saat pengeluaran
 * - Nambah stok saat verifikasi
 * - Audit trail lengkap
 * - Imun dari error "There is already an active transaction"
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
    public function reserveStok($barang_id, $qty, $reference_type = 'pesanan', $reference_id = null, $created_by = null, $keterangan = '') {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            // 1. Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM barang 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // 2. Validate stok_available >= qty
            if ($barang['stok_available'] < $qty) {
                throw new Exception(
                    "Stok tidak cukup untuk " . htmlspecialchars($reference_type) . 
                    ". Dibutuhkan: {$qty} pcs, Tersedia: {$barang['stok_available']} pcs"
                );
            }
            
            // 3. Update barang stok
            $stok_before = $barang['stok_available'];
            $stok_after = $barang['stok_available'] - $qty;
            $stok_reserved_new = $barang['stok_reserved'] + $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE barang 
                SET stok_reserved = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_reserved_new, $stok_after, $barang_id]);
            
            // 4. Log transaksi
            $this->logStok(
                $barang_id,
                'po_reserve',
                $qty,
                $barang['stok_available'],
                $stok_after,
                $barang['stok_reserved'],
                $stok_reserved_new,
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Stok berhasil di-reserve: {$qty} pcs"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * UNRESERVE stok (batalkan reserve)
     * Nambah stok_available, kurangi stok_reserved
     */
    public function unreserveStok($barang_id, $qty, $reference_type = 'pesanan', $reference_id = null, $created_by = null, $keterangan = '') {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM barang 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate stok_reserved >= qty
            if ($barang['stok_reserved'] < $qty) {
                throw new Exception("Reserve stok tidak valid");
            }
            
            // Update
            $stok_available_before = $barang['stok_available'];
            $stok_available_after = $barang['stok_available'] + $qty;
            $stok_reserved_new = $barang['stok_reserved'] - $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE barang 
                SET stok_reserved = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_reserved_new, $stok_available_after, $barang_id]);
            
            // Log
            $this->logStok(
                $barang_id,
                'po_unreserve',
                -$qty,
                $stok_available_before,
                $stok_available_after,
                $barang['stok_reserved'],
                $stok_reserved_new,
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Reserve stok berhasil dibatalkan: {$qty} pcs"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * NAMBAH stok saat verifikasi (barang masuk dari produksi)
     */
    public function addStok($barang_id, $qty, $reference_type = 'verifikasi', $reference_id = null, $created_by = null, $keterangan = '') {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM barang 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Update
            $stok_before = $barang['stok'];
            $stok_after = $barang['stok'] + $qty;
            $stok_available_after = $barang['stok_available'] + $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE barang 
                SET stok = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_available_after, $barang_id]);
            
            // Log
            $this->logStok(
                $barang_id,
                'verifikasi_add',
                $qty,
                $stok_before,
                $stok_after,
                $barang['stok_reserved'],
                $barang['stok_reserved'],
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Stok berhasil ditambah: {$qty} pcs"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * MEMENUHI stok yang sudah di-reserve (saat barang dikirim dari PO)
     * Ini hanya memotong stok fisik dan stok_reserved.
     * Stok_available TIDAK dipotong lagi karena sudah dipotong saat reserve.
     */
    public function fulfillStok($barang_id, $qty, $reference_type = 'pengeluaran', $reference_id = null, $created_by = null, $keterangan = '') {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM barang 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate
            if ($barang['stok'] < $qty) {
                throw new Exception(
                    "Stok tidak cukup untuk pengeluaran. " .
                    "Dibutuhkan: {$qty} pcs, Tersedia: {$barang['stok']} pcs"
                );
            }
            
            // Update
            $stok_before = $barang['stok'];
            $stok_after = $barang['stok'] - $qty;
            // stok_available TETAP SAMA karena sudah dipotong saat PO dibuat
            $stok_available_after = $barang['stok_available'];
            // stok_reserved dikurangi karena sudah terpenuhi
            $stok_reserved_new = max(0, $barang['stok_reserved'] - $qty);
            
            $updateStmt = $this->pdo->prepare("
                UPDATE barang 
                SET stok = ?,
                    stok_reserved = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_reserved_new, $barang_id]);
            
            // Log
            $this->logStok(
                $barang_id,
                'adjustment',
                -$qty,
                $stok_before,
                $stok_after,
                $barang['stok_reserved'],
                $stok_reserved_new,
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Pengeluaran (Fulfill PO) berhasil: {$qty} pcs"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * MEMBATALKAN pemenuhan stok (Rollback Pengeluaran dari PO)
     * Ini menambah kembali stok fisik dan stok_reserved.
     * Stok_available TIDAK ditambah karena masih berstatus di-booking oleh PO.
     */
    public function unfulfillStok($barang_id, $qty, $reference_type = 'pengeluaran_rollback', $reference_id = null, $created_by = null, $keterangan = '') {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            $stmt = $this->pdo->prepare("SELECT stok, stok_reserved, stok_available FROM barang WHERE id = ? FOR UPDATE");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            $stok_before = $barang['stok'];
            $stok_after = $barang['stok'] + $qty;
            // stok_available tetap
            $stok_available_after = $barang['stok_available'];
            // stok_reserved dikembalikan
            $stok_reserved_new = $barang['stok_reserved'] + $qty;
            
            $updateStmt = $this->pdo->prepare("UPDATE barang SET stok = ?, stok_reserved = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$stok_after, $stok_reserved_new, $barang_id]);
            
            $this->logStok($barang_id, 'adjustment', $qty, $stok_before, $stok_after, $barang['stok_reserved'], $stok_reserved_new, $reference_type, $reference_id, $keterangan, $created_by);
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Rollback Pengeluaran PO berhasil: {$qty} pcs"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * KURANG stok saat pengeluaran (shipment ke customer)
     */
    public function reduceStok($barang_id, $qty, $reference_type = 'pengeluaran', $reference_id = null, $created_by = null, $keterangan = '') {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM barang 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate
            if ($barang['stok'] < $qty) {
                throw new Exception(
                    "Stok tidak cukup untuk pengeluaran. " .
                    "Dibutuhkan: {$qty} pcs, Tersedia: {$barang['stok']} pcs"
                );
            }
            
            // Update
            $stok_before = $barang['stok'];
            $stok_after = $barang['stok'] - $qty;
            $stok_available_after = $barang['stok_available'] - $qty;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE barang 
                SET stok = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_available_after, $barang_id]);
            
            // Log
            $this->logStok(
                $barang_id,
                'pengeluaran_sub',
                -$qty,
                $stok_before,
                $stok_after,
                $barang['stok_reserved'],
                $barang['stok_reserved'],
                $reference_type,
                $reference_id,
                $keterangan,
                $created_by
            );
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Stok berhasil dikurangi: {$qty} pcs"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * ADJUSTMENT stok manual (untuk koreksi/selisih)
     */
    public function adjustmentStok($barang_id, $qty_change, $keterangan = '', $created_by = null) {
        $isRootTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            // Get current stok
            $stmt = $this->pdo->prepare("
                SELECT stok, stok_reserved, stok_available 
                FROM barang 
                WHERE id = ? 
                FOR UPDATE
            ");
            $stmt->execute([$barang_id]);
            $barang = $stmt->fetch();
            
            if (!$barang) {
                throw new Exception("Produk tidak ditemukan");
            }
            
            // Validate
            $stok_after = $barang['stok'] + $qty_change;
            if ($stok_after < 0) {
                throw new Exception("Adjustment akan membuat stok negatif");
            }
            
            // Update
            $stok_before = $barang['stok'];
            $stok_available_after = $barang['stok_available'] + $qty_change;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE barang 
                SET stok = ?,
                    stok_available = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$stok_after, $stok_available_after, $barang_id]);
            
            // Log
            $this->logStok(
                $barang_id,
                'adjustment',
                $qty_change,
                $stok_before,
                $stok_after,
                $barang['stok_reserved'],
                $barang['stok_reserved'],
                'adjustment',
                null,
                $keterangan,
                $created_by
            );
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => "Stok berhasil disesuaikan"];
            
        } catch (Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get stok realtime untuk barang
     */
    public function getStokRealtime($barang_id) {
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
            FROM barang
            WHERE id = ?
        ");
        $stmt->execute([$barang_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get stok history/audit trail
     */
    public function getStokLog($barang_id, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT 
                sl.*,
                u.username AS created_by_name,
                p.nama AS produk_nama
            FROM stok_log sl
            LEFT JOIN users u ON sl.created_by = u.id
            LEFT JOIN barang p ON sl.barang_id = p.id
            WHERE sl.barang_id = ?
            ORDER BY sl.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$barang_id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Private: Log setiap transaksi stok
     */
    private function logStok($barang_id, $tipe, $qty_change, $stok_before, $stok_after, 
                             $stok_reserved_before, $stok_reserved_after, 
                             $reference_type, $reference_id, $keterangan, $created_by) {
        $stmt = $this->pdo->prepare("
            INSERT INTO stok_log 
            (barang_id, tipe_transaksi, qty_change, stok_before, stok_after, 
             stok_reserved_before, stok_reserved_after, reference_type, reference_id, 
             keterangan, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $barang_id, $tipe, $qty_change, $stok_before, $stok_after,
            $stok_reserved_before, $stok_reserved_after, $reference_type, $reference_id,
            $keterangan, $created_by
        ]);
    }
}