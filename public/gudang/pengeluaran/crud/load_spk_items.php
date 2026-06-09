<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['gudang', 'manager', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

require_once '../../../../src/config.php';

$spk_id = (int)($_GET['spk_id'] ?? 0);

if (!$spk_id) {
    echo json_encode(['success' => false, 'message' => 'SPK ID tidak valid']);
    exit;
}

try {
    // 1. AMBIL INFO PIC DARI SPK
    $stmtSpk = $pdo->prepare("SELECT s.pic as pic_id, u.username as pic_name FROM spk s LEFT JOIN users u ON s.pic = u.id WHERE s.id = ?");
    $stmtSpk->execute([$spk_id]);
    $spkInfo = $stmtSpk->fetch(PDO::FETCH_ASSOC);

    // 2. AMBIL ITEMS DENGAN MENGHITUNG SISA QTY
    // Kita kurangi qty_order dengan total barang yang sudah masuk di pengeluaran untuk SPK ini
    $sql = "
        SELECT 
            pr.id as produk_id,
            pr.kode_produk,
            pr.nama,
            poi.qty as qty_order,
            (SELECT IFNULL(SUM(pi.qty), 0) 
             FROM pengeluaran_items pi 
             JOIN pengeluaran p ON pi.pengeluaran_id = p.id 
             WHERE p.spk_id = spk.id AND pi.produk_id = poi.produk_id) as qty_sudah_keluar,
            pr.stok_available,
            pr.stok
        FROM spk
        INNER JOIN po ON spk.po_id = po.id
        LEFT JOIN po_items poi ON po.id = poi.po_id
        LEFT JOIN produk pr ON poi.produk_id = pr.id
        WHERE spk.id = ? AND poi.produk_id IS NOT NULL
        ORDER BY poi.id ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$spk_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak memiliki item atau PO belum ada']);
        exit;
    }
    
    $finalItems = [];
    foreach ($items as $item) {
        $sisa_qty = (int)$item['qty_order'] - (int)$item['qty_sudah_keluar'];
        
        // Hanya masukkan item yang masih memiliki sisa untuk dikirim
        if ($sisa_qty > 0) {
            $item['qty_order'] = $sisa_qty; // Tampilkan sisa yang belum dikirim saja
            $item['stok_available'] = (int)($item['stok_available'] ?? 0);
            $finalItems[] = $item;
        }
    }
    
    echo json_encode([
        'success' => true,
        'pic_id' => $spkInfo['pic_id'] ?? '',
        'pic_name' => $spkInfo['pic_name'] ?? 'Tidak ada PIC',
        'items' => $finalItems,
        'message' => 'Items berhasil diload'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}