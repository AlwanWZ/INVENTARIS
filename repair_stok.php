<?php
/**
 * =====================================================
 * SCRIPT REPAIR: Fix Stok Data Inconsistencies
 * =====================================================
 * 
 * FORMULA: stok_available = stok - stok_reserved
 * 
 * Run this ONCE to fix all data yang rusak/inconsistent
 */

require_once __DIR__ . '/src/config.php';

echo "🔧 STARTING STOK DATA REPAIR...\n\n";

try {
    // 1. Update semua produk berdasarkan formula UTAMA
    $sql = "UPDATE produk 
            SET stok_available = GREATEST(0, stok - stok_reserved),
                updated_at = NOW()
            WHERE stok_available != GREATEST(0, stok - stok_reserved)
            OR stok_available IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $affected = $stmt->rowCount();
    echo "✅ Updated $affected produk dengan formula:\n";
    echo "   stok_available = MAX(0, stok - stok_reserved)\n\n";

    // 2. Verifikasi hasil repair
    $sqlVerify = "SELECT 
                    id, 
                    nama,
                    stok,
                    stok_reserved,
                    stok_available,
                    (stok - stok_reserved) as expected_available
                FROM produk
                ORDER BY id";
    
    $stmt = $pdo->prepare($sqlVerify);
    $stmt->execute();
    $produkList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 VERIFICATION HASIL REPAIR:\n";
    echo str_repeat("=", 100) . "\n";
    printf("%-3s | %-30s | %-8s | %-12s | %-12s | %-12s | %s\n", 
        "ID", "Nama Produk", "Stok", "Reserved", "Available", "Expected", "Status");
    echo str_repeat("-", 100) . "\n";

    $allOk = true;
    foreach ($produkList as $p) {
        $expected = max(0, $p['stok'] - $p['stok_reserved']);
        $status = ($p['stok_available'] == $expected) ? '✅ OK' : '❌ MISMATCH';
        
        if ($p['stok_available'] != $expected) {
            $allOk = false;
        }

        printf("%-3d | %-30s | %-8d | %-12d | %-12d | %-12d | %s\n",
            $p['id'],
            substr($p['nama'], 0, 28),
            $p['stok'],
            $p['stok_reserved'],
            $p['stok_available'],
            $expected,
            $status
        );
    }
    
    echo str_repeat("=", 100) . "\n";
    
    if ($allOk) {
        echo "\n✨ SEMUA DATA STOK SUDAH SINKRON!\n";
        echo "\n📋 RINGKASAN INVENTORY:\n";
        
        $sqlSummary = "SELECT 
                        COUNT(*) as total,
                        SUM(stok) as total_stok_fisik,
                        SUM(stok_reserved) as total_reserved,
                        SUM(stok_available) as total_available
                        FROM produk";
        
        $stmt = $pdo->prepare($sqlSummary);
        $stmt->execute();
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "   Total Produk: " . $summary['total'] . "\n";
        echo "   Stok Fisik Total: " . ($summary['total_stok_fisik'] ?? 0) . " pcs\n";
        echo "   Stok Reserved: " . ($summary['total_reserved'] ?? 0) . " pcs\n";
        echo "   Stok Available: " . ($summary['total_available'] ?? 0) . " pcs\n";
    } else {
        echo "\n⚠️  ADA DATA YANG MASIH MISMATCH. PERIKSA KEMBALI!\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ REPAIR COMPLETE!\n";
?>
