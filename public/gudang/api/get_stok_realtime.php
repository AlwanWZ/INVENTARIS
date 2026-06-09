<?php
/**
 * Real-time Stock Information API
 * 
 * Endpoint untuk menampilkan stok real-time produk
 * Used by: Marketing module, Dashboard, PO form
 * 
 * Query Parameters:
 * - produk_id: ID produk (single)
 * - produk_ids: Comma-separated IDs (multiple)
 * - type: 'single', 'multiple', 'all'
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "nama": "PCB Controller",
 *       "kode": "PCB-001",
 *       "stok": 500,
 *       "stok_reserved": 100,
 *       "stok_available": 400,
 *       "status_stok": "OK|LOW_STOCK|OUT_OF_STOCK",
 *       "persen_fill": 80
 *     }
 *   ]
 * }
 */

session_start();
header('Content-Type: application/json');

// Allow access from Marketing and Manager roles
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager', 'gudang', 'admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

require_once '../../../src/config.php';
require_once '../../../src/models/StokTracking.php';

try {
    // Use global $pdo from config.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    $stokTracking = new StokTracking($pdo);
    
    // Determine request type
    $produk_id = $_GET['produk_id'] ?? null;
    $produk_ids = $_GET['produk_ids'] ?? null;
    $type = $_GET['type'] ?? 'single';
    
    $data = [];
    
    if ($produk_id) {
        // Get single product stock
        $stok = $stokTracking->getStokRealtime((int)$produk_id);
        if ($stok) {
            $persen_fill = 0;
            if (isset($stok['stok']) && (int)$stok['stok'] > 0) {
                $available = isset($stok['stok_available']) ? (int)$stok['stok_available'] : 0;
                $persen_fill = round(($available / (int)$stok['stok']) * 100);
            }
            $stok['persen_fill'] = $persen_fill;
            $data = [$stok];
        }
    } elseif ($produk_ids) {
        // Get multiple products stock
        $ids = array_map('intval', explode(',', $produk_ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nama,
                kode_produk as kode,
                stok,
                stok_reserved,
                stok_available,
                CASE 
                    WHEN stok_available <= 0 THEN 'OUT_OF_STOCK'
                    WHEN stok_available < 50 THEN 'LOW_STOCK'
                    ELSE 'OK'
                END AS status_stok
            FROM produk
            WHERE id IN ($placeholders)
            ORDER BY nama ASC
        ");
        $stmt->execute($ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $persen_fill = 0;
            if (isset($product['stok']) && (int)$product['stok'] > 0) {
                $available = isset($product['stok_available']) ? (int)$product['stok_available'] : 0;
                $persen_fill = round(($available / (int)$product['stok']) * 100);
            }
            $product['persen_fill'] = $persen_fill;
            $data[] = $product;
        }
    } else {
        // Get all products stock (limited)
        $stmt = $pdo->query("
            SELECT 
                id,
                nama,
                kode_produk as kode,
                stok,
                stok_reserved,
                stok_available,
                CASE 
                    WHEN stok_available <= 0 THEN 'OUT_OF_STOCK'
                    WHEN stok_available < 50 THEN 'LOW_STOCK'
                    ELSE 'OK'
                END AS status_stok
            FROM produk
            WHERE status = 'aktif'
            ORDER BY nama ASC
            LIMIT 100
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $persen_fill = 0;
            if (isset($product['stok']) && (int)$product['stok'] > 0) {
                $available = isset($product['stok_available']) ? (int)$product['stok_available'] : 0;
                $persen_fill = round(($available / (int)$product['stok']) * 100);
            }
            $product['persen_fill'] = $persen_fill;
            $data[] = $product;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock data retrieved successfully',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data,
        'count' => count($data)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
