<?php
/**
 * AJAX Endpoint: Generate Kode Produk
 * Menerima: kategori_id
 * Return: JSON {success: bool, kode: string, message: string}
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once '../../../../src/auth.php';
require_once '../../../../src/functions.php';

$kategori_id = (int)($_GET['kategori_id'] ?? 0);

if ($kategori_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Kategori ID tidak valid']));
}

try {
    // Generate kode berdasarkan kategori
    $kode = generateKodeProdukByKategori($kategori_id);
    
    echo json_encode([
        'success' => true,
        'kode' => $kode,
        'message' => 'Kode berhasil di-generate'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
