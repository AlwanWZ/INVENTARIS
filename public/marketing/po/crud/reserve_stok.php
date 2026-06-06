<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once '../../../../src/auth.php';
require_once '../../../../src/models/PO.php';
require_once '../../../../src/models/StokTracking.php';
require_once '../../../../src/config.php';

header('Content-Type: application/json');

$po_id = $_POST['po_id'] ?? null;
$action = $_POST['action'] ?? null; // 'reserve' atau 'unreserve'

if (!$po_id || !$action) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing parameters']));
}

try {
    global $pdo;
    
    // Get PO data
    $po = PO::find($po_id);
    if (!$po) {
        throw new Exception('PO tidak ditemukan');
    }
    
    // Get PO items
    $stmt = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        throw new Exception('PO tidak memiliki items');
    }
    
    if ($action === 'reserve') {
        // RESERVE STOK - Kurangi stok produk saat PO approved
        $pdo->beginTransaction();
        
        $stokTracking = new StokTracking($pdo);
        
        foreach ($items as $item) {
            if (!$item['produk_id'] || $item['qty_available'] <= 0) {
                continue; // Skip jika qty_available = 0 (semua pending produksi)
            }
            
            // Reserve qty_available dari stok
            $stokTracking->reserveStok(
                $item['produk_id'],
                $item['qty_available'], // Reserve qty_available saja
                'PO',
                $po_id,
                $_SESSION['user']['id'],
                "Reserve untuk PO #{$po['nomor_po']} - {$item['nama_material']}"
            );
        }
        
        // Update PO status_stok ke 'reserved'
        $updateStmt = $pdo->prepare("UPDATE po SET status_stok = 'reserved' WHERE id = ?");
        $updateStmt->execute([$po_id]);
        
        // Update po_items.is_reserved = 'yes'
        $updateItemsStmt = $pdo->prepare("UPDATE po_items SET is_reserved = 'yes' WHERE po_id = ?");
        $updateItemsStmt->execute([$po_id]);
        
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
            if (!$item['produk_id'] || $item['qty_available'] <= 0) {
                continue;
            }
            
            // Unreserve qty_available
            $stokTracking->unreserveStok(
                $item['produk_id'],
                $item['qty_available'],
                'PO',
                $po_id,
                $_SESSION['user']['id'],
                "Unreserve: PO #{$po['nomor_po']} dibatalkan"
            );
        }
        
        // Update PO status_stok ke 'draft'
        $updateStmt = $pdo->prepare("UPDATE po SET status_stok = 'draft' WHERE id = ?");
        $updateStmt->execute([$po_id]);
        
        // Update po_items.is_reserved = 'no'
        $updateItemsStmt = $pdo->prepare("UPDATE po_items SET is_reserved = 'no' WHERE po_id = ?");
        $updateItemsStmt->execute([$po_id]);
        
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
