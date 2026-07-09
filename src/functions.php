<?php
/**
 * Generate nomor otomatis dengan prefix dan sequence
 * Format: PREFIX-NNN (misal: PCB-001, PROD-002)
 */
function getNextCode($prefix, $table = 'pesanan', $field = 'nomor_pesanan') {
    global $pdo;
    
    try {
        // Query untuk mendapatkan nomor terakhir dengan prefix ini
        // Support both field names with - dan tanpa - separator
        $sql = "SELECT MAX(
                  CASE 
                    WHEN $field LIKE '%-%%' THEN CAST(SUBSTRING($field, LOCATE('-', $field) + 1) AS UNSIGNED)
                    ELSE CAST(RIGHT($field, 3) AS UNSIGNED)
                  END
                ) as max_num 
                FROM $table 
                WHERE $field LIKE ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = ($result['max_num'] ?? 0) + 1;
        
        // Format dengan leading zeros (3 digit)
        return sprintf('%s-%03d', $prefix, $nextNum);
    } catch (Exception $e) {
        // Fallback: return prefix-001 jika ada error
        error_log("getNextCode error: " . $e->getMessage());
        return $prefix . '-001';
    }
}

/**
 * Generate kode barang dengan prefix dari kategori
 * Ambil prefix dari kategori table, lalu generate sequence
 * 
 * @param int $kategori_id - ID kategori barang
 * @return string - Kode barang format: PREFIX-NNN (misal: PCB-001, RES-042)
 */
function generateKodeProdukByKategori($kategori_id = null) {
    global $pdo;
    
    try {
        $prefix = 'PROD'; // Default prefix
        
        // Jika kategori_id ada, ambil prefix dari kategori table
        if ($kategori_id) {
            $stmt = $pdo->prepare('SELECT prefix_kode FROM kategori WHERE id = ? LIMIT 1');
            $stmt->execute([$kategori_id]);
            $kategori = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($kategori && $kategori['prefix_kode']) {
                $prefix = strtoupper($kategori['prefix_kode']);
            }
        }
        
        // Generate code dengan prefix yang sudah ditentukan
        return getNextCode($prefix, 'barang', 'kode_barang');
    } catch (Exception $e) {
        // Fallback: gunakan default
        return 'PROD-001';
    }
}

/**
 * Generate nomor otomatis untuk tabel tertentu dengan mapping tabel ke field
 */
function generateAutoCode($codeType) {
    if ($codeType === 'SPK') {
        return generateSPKNumber();
    }
    
    $codeMap = [
        'PCB' => ['table' => 'pesanan', 'prefix' => 'PCB', 'field' => 'nomor_pesanan'],
        'PRODUK' => ['table' => 'barang', 'prefix' => 'PROD', 'field' => 'kode_barang'],  // Default PROD jika kategori tidak dipilih
        'PROD' => ['table' => 'barang', 'prefix' => 'PROD', 'field' => 'kode_barang'],
        'MRK' => ['table' => 'customers', 'prefix' => 'MRK', 'field' => 'nomor_customer'],
        'USR' => ['table' => 'users', 'prefix' => 'USR', 'field' => 'username'],
        'MNG' => ['table' => 'users', 'prefix' => 'MNG', 'field' => 'username'],
        'GDG' => ['table' => 'users', 'prefix' => 'GDG', 'field' => 'username'],
        'SJ' => ['table' => 'surat_jalan', 'prefix' => 'SJ', 'field' => 'nomor_sj'],
        'PNR' => ['table' => 'penerimaan', 'prefix' => 'PNR', 'field' => 'nomor_penerimaan'],
        'PNG' => ['table' => 'pengeluaran', 'prefix' => 'PNG', 'field' => 'nomor_pengeluaran'],
        'VRF' => ['table' => 'verifikasi', 'prefix' => 'VRF', 'field' => 'nomor_verifikasi'],
    ];
    
    if (!isset($codeMap[$codeType])) {
        return null;
    }
    
    $config = $codeMap[$codeType];
    return getNextCode($config['prefix'], $config['table'], $config['field']);
}

/**
 * Generate nomor SPK dengan format: SPK-YY-{urutan}
 * Contoh: SPK-26-001, SPK-26-002, SPK-26-003, dst
 * YY = 2 digit tahun (26 untuk 2026)
 * {urutan} = nomor urut SPK di tahun tersebut (001, 002, 003, dst)
 */
function generateSPKNumber() {
    global $pdo;
    
    try {
        // Dapatkan tahun saat ini
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $year = $now->format('y');        // 2 digit tahun (26 untuk 2026)
        
        // Query untuk mendapatkan urutan SPK terakhir di tahun ini
        $sql = "SELECT COUNT(*) + 1 as next_sequence 
                FROM spk 
                WHERE nomor_spk LIKE ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['SPK-' . $year . '-%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sequence = $result['next_sequence'] ?? 1;
        
        // Format dengan 3 digit leading zeros
        return sprintf('SPK-%s-%03d', $year, $sequence);
    } catch (Exception $e) {
        // Fallback: return dengan format dasar jika ada error
        $year = date('y');
        return 'SPK-' . $year . '-001';
    }
}

/**
 * Generate nomor PO dengan format: PO-MMYY-{urutan}
 * Contoh: PO-1126-001, PO-1126-002, PO-1201-001, dst
 * MMYY = bulan+tahun (1126 = November 2026, 1201 = December 2026)
 * {urutan} = nomor urut PO di bulan-tahun tersebut (001, 002, 003, dst)
 */
function generatePONumber() {
    global $pdo;
    
    try {
        // Dapatkan bulan dan tahun saat ini
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $month = $now->format('m');       // 01-12
        $year = $now->format('y');        // 2 digit tahun (26 untuk 2026)
        $monthYear = $month . $year;      // Format: 1126, 1201, dst
        
        // Query untuk mendapatkan urutan PO terakhir di bulan-tahun ini
        $sql = "SELECT COUNT(*) + 1 as next_sequence 
                FROM pesanan 
                WHERE nomor_pesanan LIKE ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['PO-' . $monthYear . '-%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sequence = $result['next_sequence'] ?? 1;
        
        // Format dengan 3 digit leading zeros
        return sprintf('PO-%s-%03d', $monthYear, $sequence);
    } catch (Exception $e) {
        // Fallback: return dengan format dasar jika ada error
        $month = date('m');
        $year = date('y');
        return 'PO-' . $month . $year . '-001';
    }
}

/**
 * ========================================
 * STOCK MANAGEMENT HELPER FUNCTIONS
 * ========================================
 * Convenience functions untuk stock operations
 */

/**
 * Get real-time stock info for a product
 * 
 * @param int $barang_id - Product ID
 * @param PDO $pdo - Database connection (optional, uses global if not provided)
 * @return array - Stock info with status
 * 
 * Example:
 * $stok = getStokInfo(5);
 * // Returns: ['id' => 5, 'nama' => 'PCB', 'stok' => 500, 'stok_available' => 400, 'status_stok' => 'OK']
 */
function getStokInfo($barang_id, $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    
    try {
        require_once __DIR__ . '/models/StokTracking.php';
        $stokTracking = new StokTracking($pdo);
        return $stokTracking->getStokRealtime((int)$barang_id);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Reserve stock when PO is created
 * 
 * @param int $barang_id - Product ID
 * @param int $qty - Quantity to reserve
 * @param int|null $reference_id - PO ID
 * @param int|null $user_id - User ID
 * @param PDO $pdo - Database connection
 * @return array - Result with success flag and message
 */
function reserveStock($barang_id, $qty, $reference_id = null, $user_id = null, $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    
    try {
        require_once __DIR__ . '/models/StokTracking.php';
        $stokTracking = new StokTracking($pdo);
        return $stokTracking->reserveStok(
            (int)$barang_id,
            (int)$qty,
            'pesanan',
            $reference_id,
            $user_id,
            'PO reserve'
        );
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Unreserve stock when PO is cancelled or goods are received
 * 
 * @param int $barang_id - Product ID
 * @param int $qty - Quantity to unreserve
 * @param int|null $reference_id - PO ID or Penerimaan ID
 * @param int|null $user_id - User ID
 * @param PDO $pdo - Database connection
 * @return array - Result with success flag and message
 */
function unreserveStock($barang_id, $qty, $reference_id = null, $user_id = null, $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    
    try {
        require_once __DIR__ . '/models/StokTracking.php';
        $stokTracking = new StokTracking($pdo);
        return $stokTracking->unreserveStok(
            (int)$barang_id,
            (int)$qty,
            'pesanan',
            $reference_id,
            $user_id,
            'Unreserve'
        );
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Add stock when goods are received
 * 
 * @param int $barang_id - Product ID
 * @param int $qty - Quantity received
 * @param int|null $reference_id - Penerimaan ID
 * @param int|null $user_id - User ID
 * @param string $notes - Additional notes
 * @param PDO $pdo - Database connection
 * @return array - Result with success flag and message
 */
function addStock($barang_id, $qty, $reference_id = null, $user_id = null, $notes = '', $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    
    try {
        require_once __DIR__ . '/models/StokTracking.php';
        $stokTracking = new StokTracking($pdo);
        return $stokTracking->addStok(
            (int)$barang_id,
            (int)$qty,
            'penerimaan',
            $reference_id,
            $user_id,
            $notes ?? 'Barang masuk'
        );
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Reduce stock when goods are shipped
 * 
 * @param int $barang_id - Product ID
 * @param int $qty - Quantity shipped
 * @param int|null $reference_id - Pengeluaran ID
 * @param int|null $user_id - User ID
 * @param string $notes - Additional notes
 * @param PDO $pdo - Database connection
 * @return array - Result with success flag and message
 */
function reduceStock($barang_id, $qty, $reference_id = null, $user_id = null, $notes = '', $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    
    try {
        require_once __DIR__ . '/models/StokTracking.php';
        $stokTracking = new StokTracking($pdo);
        return $stokTracking->reduceStok(
            (int)$barang_id,
            (int)$qty,
            'pengeluaran',
            $reference_id,
            $user_id,
            $notes ?? 'Barang keluar'
        );
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get stock history/audit log for a product
 * 
 * @param int $barang_id - Product ID
 * @param int $limit - Number of records to fetch
 * @param PDO $pdo - Database connection
 * @return array - History records
 */
function getStockHistory($barang_id, $limit = 50, $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    
    try {
        require_once __DIR__ . '/models/StokTracking.php';
        $stokTracking = new StokTracking($pdo);
        return $stokTracking->getStokLog((int)$barang_id, (int)$limit);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check if product has sufficient available stock
 * 
 * @param int $barang_id - Product ID
 * @param int $qty_needed - Quantity needed
 * @param PDO $pdo - Database connection
 * @return bool - True if sufficient stock, false otherwise
 */
function hasEnoughStock($barang_id, $qty_needed, $pdo = null) {
    $stok = getStokInfo($barang_id, $pdo);
    if (!isset($stok['stok_available'])) {
        return false;
    }
    return (int)$stok['stok_available'] >= (int)$qty_needed;
}

/**
 * Get stock status for display (OK, LOW_STOCK, OUT_OF_STOCK)
 * 
 * @param int $barang_id - Product ID
 * @param PDO $pdo - Database connection
 * @return string - Status code
 */
function getStockStatus($barang_id, $pdo = null) {
    $stok = getStokInfo($barang_id, $pdo);
    return $stok['status_stok'] ?? 'UNKNOWN';
}

/**
 * Get stock percentage fill (0-100)
 * 
 * @param int $barang_id - Product ID
 * @param PDO $pdo - Database connection
 * @return int - Percentage (0-100)
 */
function getStockPercentage($barang_id, $pdo = null) {
    $stok = getStokInfo($barang_id, $pdo);
    if (!isset($stok['stok']) || $stok['stok'] == 0) {
        return 0;
    }
    return round(($stok['stok_available'] / $stok['stok']) * 100);
}
?>

