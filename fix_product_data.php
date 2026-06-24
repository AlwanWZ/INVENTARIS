<?php
/**
 * MAINTENANCE SCRIPT: Clean Up Product Data
 * - Generate kode_produk untuk produk yang kosong
 * - Set kategori default untuk produk tanpa kategori
 * - Remove duplicate produk
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once 'src/config.php';
require_once 'src/functions.php';

// Security check - allow if user is logged in and has role
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'manager', 'gudang', 'marketing'])) {
    die('⛔ Unauthorized Access - Login required');
}

echo '<pre style="background: #f5f5f5; padding: 20px; font-family: monospace;">';
echo "=== PRODUCT DATA CLEANUP UTILITY ===\n\n";

try {
    // Step 1: Find products without kode_produk
    echo "📋 STEP 1: Produk tanpa kode_produk\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("SELECT id, nama, kategori_id FROM produk WHERE kode_produk IS NULL OR kode_produk = '' ORDER BY id");
    $noKodeProduks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($noKodeProduks)) {
        echo "✅ Semua produk sudah memiliki kode_produk\n\n";
    } else {
        echo "❌ Ditemukan " . count($noKodeProduks) . " produk tanpa kode:\n";
        foreach ($noKodeProduks as $p) {
            echo "  - ID: {$p['id']}, Nama: {$p['nama']}, Kategori ID: {$p['kategori_id']}\n";
        }
        echo "\n";
        
        // Generate kode untuk setiap produk
        foreach ($noKodeProduks as $p) {
            $kode = generateKodeProdukByKategori($p['kategori_id']);
            
            // Update produk dengan kode yang di-generate
            $updateStmt = $pdo->prepare("UPDATE produk SET kode_produk = ? WHERE id = ?");
            $updateStmt->execute([$kode, $p['id']]);
            echo "  ✓ ID {$p['id']}: Generated kode = {$kode}\n";
        }
        echo "\n✅ Selesai! Semua produk kini memiliki kode_produk\n\n";
    }
    
    // Step 2: Find products without kategori_id
    echo "📋 STEP 2: Produk tanpa kategori\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("SELECT p.id, p.nama, p.kode_produk FROM produk p WHERE p.kategori_id IS NULL OR p.kategori_id = 0 ORDER BY p.id");
    $noKatProduks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($noKatProduks)) {
        echo "✅ Semua produk sudah memiliki kategori\n\n";
    } else {
        echo "❌ Ditemukan " . count($noKatProduks) . " produk tanpa kategori:\n";
        
        // Get default kategori (usually the first one)
        $defaultKatStmt = $pdo->query("SELECT id FROM kategori ORDER BY id LIMIT 1");
        $defaultKat = $defaultKatStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$defaultKat) {
            echo "  ⚠️  Tidak ada kategori di database! Buat kategori dulu.\n";
        } else {
            $defaultKatId = $defaultKat['id'];
            
            foreach ($noKatProduks as $p) {
                echo "  - ID: {$p['id']}, Kode: {$p['kode_produk']}, Nama: {$p['nama']}\n";
                
                $updateStmt = $pdo->prepare("UPDATE produk SET kategori_id = ? WHERE id = ?");
                $updateStmt->execute([$defaultKatId, $p['id']]);
                echo "    ✓ Assigned kategori ID = {$defaultKatId}\n";
            }
            echo "\n✅ Selesai! Semua produk kini memiliki kategori\n\n";
        }
    }
    
    // Step 3: Find duplicate produk (same nama)
    echo "📋 STEP 3: Duplikat produk (same nama)\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("
        SELECT nama, COUNT(*) as cnt, GROUP_CONCAT(id) as ids
        FROM produk
        GROUP BY nama
        HAVING COUNT(*) > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✅ Tidak ada produk duplikat\n\n";
    } else {
        echo "⚠️  Ditemukan " . count($duplicates) . " produk dengan nama duplikat:\n";
        foreach ($duplicates as $dup) {
            echo "  - Nama: {$dup['nama']}\n";
            echo "    IDs: {$dup['ids']}\n";
        }
        echo "\n💡 Review dan hapus duplikat secara manual jika diperlukan\n\n";
    }
    
    // Step 4: Final Status Report
    echo "📊 FINAL STATUS\n";
    echo str_repeat("-", 50) . "\n";
    
    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM produk");
    $totalProduk = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $validStmt = $pdo->query("
        SELECT COUNT(*) as valid 
        FROM produk 
        WHERE (kode_produk IS NOT NULL AND kode_produk != '') 
          AND (kategori_id IS NOT NULL AND kategori_id > 0)
    ");
    $validProduk = $validStmt->fetch(PDO::FETCH_ASSOC)['valid'];
    
    echo "Total Produk: {$totalProduk}\n";
    echo "Produk Valid: {$validProduk}\n";
    echo "Status: " . ($validProduk === $totalProduk ? "✅ CLEAN" : "⚠️  INCOMPLETE") . "\n";
    echo "\n";
    
    echo "=== END OF CLEANUP ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}

echo '</pre>';

// Add button to go back
echo '<br><br><a href="public/gudang/stok/index.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">← Back to Stok</a>';
?>
