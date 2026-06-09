<?php
class SuratJalan {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll($filters = []) {
        $sql = "SELECT sj.*, p.nomor_pengeluaran, c.nama AS customer_nama, c.perusahaan, u.username AS created_by_name
                FROM surat_jalan sj
                LEFT JOIN pengeluaran p ON sj.pengeluaran_id = p.id
                LEFT JOIN customers c ON sj.customer_id = c.id
                LEFT JOIN users u ON sj.created_by = u.id
                WHERE 1";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND sj.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= " AND sj.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND sj.tanggal_kirim >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND sj.tanggal_kirim <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        $sql .= " ORDER BY sj.tanggal_kirim DESC, sj.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT sj.*, p.nomor_pengeluaran, c.nama AS customer_nama, c.perusahaan, u.username AS created_by_name
                FROM surat_jalan sj
                LEFT JOIN pengeluaran p ON sj.pengeluaran_id = p.id
                LEFT JOIN customers c ON sj.customer_id = c.id
                LEFT JOIN users u ON sj.created_by = u.id
                WHERE sj.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getItems($sj_id) {
        $sql = "SELECT sji.*, pr.nama AS produk_nama, pr.satuan, po.nomor_po 
                FROM surat_jalan_items sji 
                LEFT JOIN produk pr ON sji.produk_id = pr.id 
                LEFT JOIN surat_jalan sj ON sji.surat_jalan_id = sj.id 
                LEFT JOIN pengeluaran p ON sj.pengeluaran_id = p.id 
                LEFT JOIN spk ON p.spk_id = spk.id 
                LEFT JOIN po ON spk.po_id = po.id 
                WHERE sji.surat_jalan_id = ? ORDER BY sji.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$sj_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($data, $items) {
        $this->pdo->beginTransaction();
        $sql = "INSERT INTO surat_jalan (nomor_sj, pengeluaran_id, customer_id, tanggal_kirim, alamat_kirim, driver, kendaraan, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_sj'],
            $data['pengeluaran_id'],
            $data['customer_id'],
            $data['tanggal_kirim'],
            $data['alamat_kirim'],
            $data['driver'],
            $data['kendaraan'],
            $data['status'],
            $data['created_by']
        ]);
        $sj_id = $this->pdo->lastInsertId();
        foreach ($items as $item) {
            $sql = "INSERT INTO surat_jalan_items (surat_jalan_id, produk_id, qty) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $sj_id,
                $item['produk_id'],
                $item['qty']
            ]);
        }
        $this->pdo->commit();
        return $sj_id;
    }

    public function update($id, $data, $items) {
        try {
            $this->pdo->beginTransaction();
            $sql = "UPDATE surat_jalan SET nomor_sj=?, pengeluaran_id=?, customer_id=?, tanggal_kirim=?, alamat_kirim=?, driver=?, kendaraan=?, status=?, penerima=?, waktu_terima=? WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_sj'],
                $data['pengeluaran_id'] ?? null,
                $data['customer_id'] ?? null,
                $data['tanggal_kirim'],
                $data['alamat_kirim'] ?? '',
                $data['driver'],
                $data['kendaraan'],
                $data['status'] ?? 'draft',
                $data['penerima'] ?? '',
                $data['waktu_terima'] ?? null,
                $id
            ]);
            $this->pdo->prepare("DELETE FROM surat_jalan_items WHERE surat_jalan_id=?")->execute([$id]);
            foreach ($items as $item) {
                $sql = "INSERT INTO surat_jalan_items (surat_jalan_id, produk_id, qty) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $id,
                    $item['produk_id'],
                    $item['qty']
                ]);
            }
            $this->pdo->commit();
            return ['success' => true];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM surat_jalan_items WHERE surat_jalan_id=?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM surat_jalan WHERE id=?")->execute([$id]);
            $this->pdo->commit();
            return ['success' => true];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }
}
