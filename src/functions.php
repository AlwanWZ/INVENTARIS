<?php
/**
 * Generate nomor otomatis dengan prefix dan sequence
 * Format: PREFIX-NNN (misal: PCB-001, PROD-002)
 */
function getNextCode($prefix, $table = 'po', $field = 'nomor_po') {
    global $pdo;
    
    try {
        // Query untuk mendapatkan nomor terakhir dengan prefix ini
        $sql = "SELECT MAX(CAST(SUBSTRING($field, LOCATE('-', $field) + 1) AS UNSIGNED)) as max_num 
                FROM $table 
                WHERE $field LIKE ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$prefix . '-%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = ($result['max_num'] ?? 0) + 1;
        
        // Format dengan leading zeros (3 digit)
        return sprintf('%s-%03d', $prefix, $nextNum);
    } catch (Exception $e) {
        // Fallback: return prefix-001 jika ada error
        return $prefix . '-001';
    }
}

/**
 * Generate nomor otomatis untuk tabel tertentu dengan mapping tabel ke field
 */
function generateAutoCode($codeType) {
    $codeMap = [
        'PCB' => ['table' => 'po', 'prefix' => 'PCB', 'field' => 'nomor_po'],
        'PRODUK' => ['table' => 'produk', 'prefix' => 'PCB', 'field' => 'kode_produk'],
        'PROD' => ['table' => 'produk', 'prefix' => 'PROD', 'field' => 'kode_produk'],
        'MRK' => ['table' => 'customers', 'prefix' => 'MRK', 'field' => 'nomor_customer'],
        'SPK' => ['table' => 'spk', 'prefix' => 'SPK', 'field' => 'nomor_spk'],
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
                FROM po 
                WHERE nomor_po LIKE ?";
        
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
?>
