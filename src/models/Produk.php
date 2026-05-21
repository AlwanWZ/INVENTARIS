<?php
// Model Produk sederhana, mirip PO.php
require_once __DIR__ . '/../config.php';

class Produk {
    public static function all() {
        global $pdo;
        $stmt = $pdo->query('SELECT * FROM produk ORDER BY id DESC');
        return $stmt->fetchAll();
    }
    public static function find($id) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT * FROM produk WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public static function create($data) {
        global $pdo;
        $stmt = $pdo->prepare('INSERT INTO produk (kode_produk, nama, kategori, stok, harga, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['kode'],
            $data['nama'],
            $data['kategori'],
            $data['stok'] ?? 0,
            $data['harga'] ?? 0,
            $data['status'] ?? 'aktif'
        ]);
        return $pdo->lastInsertId();
    }
    public static function update($id, $data) {
        global $pdo;
        $stmt = $pdo->prepare('UPDATE produk SET kode_produk=?, nama=?, kategori=?, stok=?, harga=?, status=? WHERE id=?');
        $stmt->execute([
            $data['kode'],
            $data['nama'],
            $data['kategori'],
            $data['stok'] ?? 0,
            $data['harga'] ?? 0,
            $data['status'] ?? 'aktif',
            $id
        ]);
    }
    public static function delete($id) {
        global $pdo;
        $stmt = $pdo->prepare('DELETE FROM produk WHERE id=?');
        $stmt->execute([$id]);
    }
}
