<?php
require_once __DIR__ . '/src/config.php';
$stmt = $pdo->query("SELECT id, nama, stok, stok_reserved, stok_available FROM barang");
$barang = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($barang as $b) {
    // Perbaiki stok yang salah!
    $correct_available = max(0, $b['stok'] - $b['stok_reserved']);
    if ($b['stok_available'] != $correct_available) {
        $pdo->exec("UPDATE barang SET stok_available = {$correct_available} WHERE id = {$b['id']}");
        echo "Barang {$b['id']} fixed: {$correct_available}\n";
    }
}
echo "Selesai.\n";
