<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once '../../../../src/auth.php';
require_once '../../../../src/models/Pesanan.php';
require_once '../../../../src/models/StokTracking.php';
require_once '../../../../src/config.php';

header('Content-Type: application/json');

$pesanan_id = $_POST['pesanan_id'] ?? null;
$action = $_POST['action'] ?? null; // 'reserve' atau 'unreserve'

if (!$pesanan_id || !$action) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing parameters']));
}

try {
    global $pdo;
    
    // Get PO data
    $pesanan = Pesanan::find($pesanan_id);
    if (!$pesanan) {
        throw new Exception('PO tidak ditemukan');
    }
    
    // Get PO items
    $stmt = $pdo->prepare("SELECT * FROM pesanan_items WHERE pesanan_id = ?");
    $stmt->execute([$pesanan_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        throw new Exception('PO tidak memiliki items');
    }
    
    if ($action === 'reserve') {
        // RESERVE STOK - Kurangi stok barang saat PO approved
        $pdo->beginTransaction();
        
        $stokTracking = new StokTracking($pdo);
        
        foreach ($items as $item) {
            if (!$item['barang_id'] || $item['qty_available'] <= 0) {
                continue; // Skip jika qty_available = 0 (semua pending produksi)
            }
            
            // Reserve qty_available dari stok
            $stokTracking->reserveStok(
                $item['barang_id'],
                $item['qty_available'], // Reserve qty_available saja
                'PO',
                $pesanan_id,
                $_SESSION['user']['id'],
                "Reserve untuk PO #{$pesanan['nomor_pesanan']} - {$item['nama_material']}"
            );
        }
        
        // Update PO status_stok ke 'reserved'
        $updateStmt = $pdo->prepare("UPDATE pesanan SET status_stok = 'reserved' WHERE id = ?");
        $updateStmt->execute([$pesanan_id]);
        
        // Update pesanan_items.is_reserved = 'yes'
        $updateItemsStmt = $pdo->prepare("UPDATE pesanan_items SET is_reserved = 'yes' WHERE pesanan_id = ?");
        $updateItemsStmt->execute([$pesanan_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stok berhasil di-reserve untuk PO ini',
            'status' => 'reserved'
        ]);
        
    } else if ($action === 'unreserve') {
        // UNRESERVE STOK - Kembalikan stok jika PO dibatalkan
        $pdo->beginTransaction();
        
        $stokTracking = new StokTracking($pdo);
        
        foreach ($items as $item) {
            if (!$item['barang_id'] || $item['qty_available'] <= 0) {
                continue;
            }
            
            // Unreserve qty_available
            $stokTracking->unreserveStok(
                $item['barang_id'],
                $item['qty_available'],
                'PO',
                $pesanan_id,
                $_SESSION['user']['id'],
                "Unreserve: PO #{$pesanan['nomor_pesanan']} dibatalkan"
            );
        }
        
        // Update PO status_stok ke 'draft'
        $updateStmt = $pdo->prepare("UPDATE pesanan SET status_stok = 'draft' WHERE id = ?");
        $updateStmt->execute([$pesanan_id]);
        
        // Update pesanan_items.is_reserved = 'no'
        $updateItemsStmt = $pdo->prepare("UPDATE pesanan_items SET is_reserved = 'no' WHERE pesanan_id = ?");
        $updateItemsStmt->execute([$pesanan_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Stok berhasil di-unreserve',
            'status' => 'draft'
        ]);
    } else {
        throw new Exception('Action tidak valid');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
