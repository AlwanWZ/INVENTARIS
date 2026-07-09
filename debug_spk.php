<?php
/**
 * DEBUG: Check SPK Data Integrity
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once 'src/config.php';
require_once 'src/models/SPK.php';

echo '<pre style="background: #f5f5f5; padding: 20px; font-family: monospace; max-width: 1200px; margin: 20px auto;">';
echo "=== DEBUG: SPK DATA CHECK ===\n\n";

try {
    // Get latest SPK
    $spks = SPK::all(['status' => 'aktif']);
    
    if (empty($spks)) {
        $spks = SPK::all();
    }
    
    if (empty($spks)) {
        echo "❌ Tidak ada SPK di database\n";
    } else {
        echo "✅ Ditemukan " . count($spks) . " SPK\n\n";
        
        foreach (array_slice($spks, 0, 3) as $spk) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "SPK ID: " . $spk['id'] . " | Nomor: " . $spk['nomor_spk'] . "\n";
            echo "PO ID: " . ($spk['pesanan_id'] ?? 'NULL') . " | Nomor PO: " . ($spk['nomor_pesanan'] ?? 'NULL') . "\n";
            echo "Customer ID: " . ($spk['customer_id'] ?? 'NULL') . "\n";
            echo "Customer Nama: " . ($spk['customer_nama'] ?? '❌ NULL') . "\n";
            echo "Perusahaan: " . ($spk['perusahaan'] ?? '❌ NULL') . "\n";
            echo "PIC: " . ($spk['pic_username'] ?? 'NULL') . "\n";
            echo "\n";
        }
    }
    
    // Check if customer data exists
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 CUSTOMER TABLE CHECK:\n";
    
    $customers = $pdo->query("SELECT id, nama, perusahaan FROM customers LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($customers)) {
        echo "❌ Tidak ada customer di database\n";
    } else {
        echo "✅ Ditemukan " . count($customers) . " customer\n";
        foreach ($customers as $cust) {
            echo "  ID: {$cust['id']}, Nama: {$cust['nama']}, Perusahaan: {$cust['perusahaan']}\n";
        }
    }
    
    // Check PO-Customer relationship
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 PO-CUSTOMER RELATIONSHIP CHECK:\n";
    
    $pesanans = $pdo->query("
        SELECT pesanan.id, pesanan.nomor_pesanan, pesanan.customer_id, c.nama, c.perusahaan
        FROM pesanan
        LEFT JOIN customers c ON pesanan.customer_id = c.id
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pesanans)) {
        echo "❌ Tidak ada PO di database\n";
    } else {
        echo "✅ Ditemukan " . count($pesanans) . " PO\n";
        foreach ($pesanans as $pesanan) {
            echo "  PO ID: {$pesanan['id']}, Nomor: {$pesanan['nomor_pesanan']}, Customer ID: {$pesanan['customer_id']}, Nama: " . ($pesanan['nama'] ?? 'NULL') . ", Perusahaan: " . ($pesanan['perusahaan'] ?? 'NULL') . "\n";
        }
    }
    
    echo "\n✅ Debug check complete\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo '</pre>';
?>
