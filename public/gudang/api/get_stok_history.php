<?php
/**
 * Stock Audit Trail API
 * 
 * Endpoint untuk melihat history perubahan stok
 * Used by: Kartu Stok, Stock Analytics, Audit Trail
 * 
 * Query Parameters:
 * - produk_id: ID produk (required)
 * - limit: Jumlah record (default: 50, max: 500)
 * - offset: Pagination offset (default: 0)
 * - start_date: Filter dari tanggal (YYYY-MM-DD)
 * - end_date: Filter sampai tanggal (YYYY-MM-DD)
 * - tipe_transaksi: Filter by transaction type (po_reserve, pengeluaran, verifikasi, etc)
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 123,
 *       "produk_id": 5,
 *       "produk_nama": "PCB Controller",
 *       "tipe_transaksi": "po_reserve",
 *       "qty_change": -100,
 *       "stok_before": 500,
 *       "stok_after": 400,
 *       "reference_type": "po",
 *       "reference_id": 12,
 *       "keterangan": "PO-001 dibuat",
 *       "created_by_name": "John Doe",
 *       "created_at": "2026-06-06 10:30:00"
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

try {
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'],
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get parameters
    $produk_id = (int)($_GET['produk_id'] ?? 0);
    $limit = min((int)($_GET['limit'] ?? 50), 500);
    $offset = (int)($_GET['offset'] ?? 0);
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $tipe_transaksi = $_GET['tipe_transaksi'] ?? null;
    
    if (!$produk_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'produk_id parameter is required'
        ]);
        exit;
    }
    
    // Build query
    $sql = "
        SELECT 
            sl.id,
            sl.produk_id,
            p.nama as produk_nama,
            p.kode_produk,
            sl.tipe_transaksi,
            sl.qty_change,
            sl.stok_before,
            sl.stok_after,
            sl.stok_reserved_before,
            sl.stok_reserved_after,
            sl.reference_type,
            sl.reference_id,
            sl.keterangan,
            u.username as created_by_name,
            sl.created_at
        FROM stok_log sl
        LEFT JOIN produk p ON sl.produk_id = p.id
        LEFT JOIN users u ON sl.created_by = u.id
        WHERE sl.produk_id = ?
    ";
    
    $params = [$produk_id];
    
    if ($tipe_transaksi) {
        $sql .= " AND sl.tipe_transaksi = ?";
        $params[] = $tipe_transaksi;
    }
    
    if ($start_date) {
        $sql .= " AND DATE(sl.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $sql .= " AND DATE(sl.created_at) <= ?";
        $params[] = $end_date;
    }
    
    $sql .= " ORDER BY sl.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM stok_log WHERE produk_id = ?";
    $countParams = [$produk_id];
    
    if ($tipe_transaksi) {
        $countSql .= " AND tipe_transaksi = ?";
        $countParams[] = $tipe_transaksi;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock history retrieved successfully',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $logs,
        'pagination' => [
            'count' => count($logs),
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
