<?php
class VerifikasiItem {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getByVerifikasi($verif_id) {
        $sql = "SELECT vi.*, pr.nama AS produk_nama FROM verifikasi_items vi LEFT JOIN produk pr ON vi.produk_id = pr.id WHERE vi.verifikasi_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$verif_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
