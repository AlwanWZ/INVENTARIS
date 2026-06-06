<?php
/**
 * =============================================
 * MODEL PRODUK - Advanced Inventory System
 * =============================================
 * Pilar Inventory:
 * - stok: Stok fisik di gudang
 * - stok_reserved: Stok yang di-booking via PO (belum dikirim)
 * - stok_available: Stok yang bisa dijual (stok - stok_reserved)
 * 
 * Rule 1: Saat create produk baru:
 *   stok_available = stok, stok_reserved = 0
 * 
 * Rule 1b: Saat edit produk (ubah stok fisik):
 *   stok_available = stok - stok_reserved
 */

require_once __DIR__ . '/../config.php';

class Produk {
    
    /**
     * Get all products
     */
    public static function all() {
        global $pdo;
        $stmt = $pdo->query('
            SELECT * FROM produk 
            ORDER BY id DESC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find product by ID
     */
    public static function find($id) {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT * FROM produk 
            WHERE id = ? 
            LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create new product
     * 
     * Rule 1: stok_available = stok (awal), stok_reserved = 0
     */
    public static function create($data) {
        global $pdo;
        
        // Ambil stok fisik input
        $stok = (int)($data['stok'] ?? 0);
        
        // Rule 1: Awal buat produk, stok_available = stok, stok_reserved = 0
        $stok_available = $stok;
        $stok_reserved = 0;
        
        $sql = 'INSERT INTO produk 
                (kode_produk, nama, kategori, stok, stok_reserved, stok_available, stok_min, satuan, harga, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['kode'] ?? '',
            $data['nama'] ?? '',
            $data['kategori'] ?? 'PCB',
            $stok,
            $stok_reserved,
            $stok_available,
            (int)($data['stok_min'] ?? 10),
            $data['satuan'] ?? 'pcs',
            (int)($data['harga'] ?? 0),
            $data['status'] ?? 'aktif'
        ]);
        
        return $pdo->lastInsertId();
    }

    /**
     * Update product
     * 
     * Rule 1b: Saat edit stok fisik, harus recalculate:
     *   stok_available = stok - stok_reserved (jangan sampai negatif)
     * 
     * FORMULA UTAMA (WAJIB DIPATUHI):
     * stok_available = stok_fisik - stok_reserved
     */
    public static function update($id, $data) {
        global $pdo;
        
        // Ambil current product untuk recalculate
        $produk = self::find($id);
        if (!$produk) {
            throw new Exception("Produk tidak ditemukan");
        }
        
        // Ambil nilai baru stok fisik dari input
        $stok_baru = (int)($data['stok'] ?? $produk['stok']);
        
        // Ambil stok_reserved DARI DATABASE (jangan dari input, ini auto-managed)
        $stok_reserved = (int)($produk['stok_reserved'] ?? 0);
        
        // RULE 1b: Hitung ulang stok_available menggunakan formula
        // stok_available = stok_fisik - stok_reserved
        $stok_available = $stok_baru - $stok_reserved;
        if ($stok_available < 0) {
            $stok_available = 0; // Safety: jangan sampai negatif
        }
        
        $sql = 'UPDATE produk SET 
                nama = ?, 
                stok = ?, 
                stok_available = ?, 
                stok_min = ?, 
                satuan = ?, 
                harga = ?, 
                status = ?,
                updated_at = NOW()
                WHERE id = ?';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nama'] ?? $produk['nama'],
            $stok_baru,
            $stok_available,  // HASIL PERHITUNGAN, bukan dari input
            (int)($data['stok_min'] ?? $produk['stok_min'] ?? 10),
            $data['satuan'] ?? $produk['satuan'] ?? 'pcs',
            (int)($data['harga'] ?? $produk['harga'] ?? 0),
            $data['status'] ?? $produk['status'] ?? 'aktif',
            $id
        ]);
    }

    /**
     * Delete product
     */
    public static function delete($id) {
        global $pdo;
        $stmt = $pdo->prepare('DELETE FROM produk WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Get produk with stok availability info
     */
    public static function getWithStokInfo($id) {
        global $pdo;
        $sql = 'SELECT 
                    id,
                    kode_produk,
                    nama,
                    kategori,
                    stok,
                    stok_reserved,
                    stok_available,
                    stok_min,
                    satuan,
                    harga,
                    status
                FROM produk 
                WHERE id = ? 
                LIMIT 1';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check stok availability for PO validation
     * Rule 2: Validate against stok_available (bukan stok fisik)
     */
    public static function checkStokAvailable($produkId, $qty) {
        global $pdo;
        
        $stmt = $pdo->prepare('
            SELECT stok_available 
            FROM produk 
            WHERE id = ? 
            LIMIT 1
        ');
        $stmt->execute([$produkId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['available' => false, 'message' => 'Produk tidak ditemukan'];
        }
        
        $stok_available = (int)($result['stok_available'] ?? 0);
        
        if ($qty > $stok_available) {
            return [
                'available' => false,
                'stok_available' => $stok_available,
                'message' => "Qty ($qty) melebihi stok tersedia ($stok_available pcs)"
            ];
        }
        
        return ['available' => true, 'stok_available' => $stok_available];
    }
}
?>