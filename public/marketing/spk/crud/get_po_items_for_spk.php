<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

require_once '../../../../src/config.php';

$po_id = (int)($_GET['po_id'] ?? 0);

if (!$po_id) {
    echo json_encode(['success' => false, 'message' => 'PO ID tidak valid']);
    exit;
}

try {
    // Ambil info perusahaan dari Pesanan
    $stmtPO = $pdo->prepare("
        SELECT c.perusahaan 
        FROM pesanan p 
        LEFT JOIN customers c ON p.customer_id = c.id 
        WHERE p.id = ?
    ");
    $stmtPO->execute([$po_id]);
    $poInfo = $stmtPO->fetch(PDO::FETCH_ASSOC);

    // Ambil items Pesanan beserta perhitungan sisa yang belum dikirim
    $sql = "
        SELECT 
            pi.id as po_item_id,
            pi.barang_id,
            pi.nama_material,
            pi.qty as qty_po,
            IFNULL(pi.qty_dikirim, 0) as qty_dikirim,
            b.stok as stok_gudang,
            b.stok_available,
            b.ukuran,
            pi.keterangan as note
        FROM pesanan_items pi
        LEFT JOIN barang b ON pi.barang_id = b.id
        WHERE pi.pesanan_id = ?
        ORDER BY pi.id ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'PO tidak memiliki item']);
        exit;
    }
    
    $finalItems = [];
    foreach ($items as $item) {
        $sisa_pesanan = max(0, (int)$item['qty_po'] - (int)$item['qty_dikirim']);
        
        $item['sisa_pesanan'] = $sisa_pesanan;
        $item['stok_gudang'] = (int)($item['stok_gudang'] ?? 0);
        $item['stok_available'] = (int)($item['stok_available'] ?? 0);
        
        $finalItems[] = $item;
    }
    
    echo json_encode([
        'success' => true,
        'customer' => $poInfo['perusahaan'] ?? '-',
        'items' => $finalItems,
        'message' => 'Items berhasil diload'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
