<?php
class Customer {
    private static function getPDO() {
        $host = 'localhost';
        $db   = 'inventaris';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        return new PDO($dsn, $user, $pass, $opt);
    }

    public static function getAll($search = '') {
        $pdo = self::getPDO();
        $sql = "SELECT * FROM customers";
        $params = [];
        if ($search) {
            $sql .= " WHERE nama LIKE :s OR perusahaan LIKE :s";
            $params[':s'] = "%$search%";
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find($id) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($data) {
        $pdo = self::getPDO();
        // Generate kode_customer
        $stmt = $pdo->query("SELECT COUNT(*) FROM customers");
        $count = $stmt->fetchColumn();
        $kode = 'CUST-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        // Validasi unik
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE kode_customer = ?");
        $stmt->execute([$kode]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Kode customer sudah digunakan.');
        }
        $sql = "INSERT INTO customers (kode_customer, nama, perusahaan, email, no_hp, alamat, kota, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $kode,
            $data['nama'],
            $data['perusahaan'],
            $data['email'],
            $data['no_hp'],
            $data['alamat'],
            $data['kota'],
            $data['status']
        ]);
        return $pdo->lastInsertId();
    }

    public static function update($id, $data) {
        $pdo = self::getPDO();
        $sql = "UPDATE customers SET nama=?, perusahaan=?, email=?, no_hp=?, alamat=?, kota=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nama'],
            $data['perusahaan'],
            $data['email'],
            $data['no_hp'],
            $data['alamat'],
            $data['kota'],
            $data['status'],
            $id
        ]);
    }

    public static function delete($id) {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
    }
}
