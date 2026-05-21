<?php
// Database config
$host = 'localhost';
$db   = 'inventaris';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Untuk debug koneksi:
    // echo "Koneksi database berhasil!";
} catch (PDOException $e) {
    echo "Koneksi database gagal: " . $e->getMessage();
    exit;
}
