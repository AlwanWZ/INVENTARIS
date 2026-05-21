<?php
class Penerimaan {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // List penerimaan dengan relasi
    public function getAll($search = '', $status = '', $dateFrom = '', $dateTo = '') {
        $sql = "SELECT p.*, po.nomor_po, spk.nomor_spk, u.username as pic_name
                FROM penerimaan p
                LEFT JOIN po ON p.po_id = po.id
                LEFT JOIN spk ON p.spk_id = spk.id
                LEFT JOIN users u ON p.pic = u.id
                WHERE 1";
        $params = [];
        if ($search) {
            $sql .= " AND (p.nomor_penerimaan LIKE :search OR po.nomor_po LIKE :search OR spk.nomor_spk LIKE :search)";
            $params['search'] = "%$search%";
        }
        if ($status) {
            $sql .= " AND p.status = :status";
            $params['status'] = $status;
        }
        if ($dateFrom) {
            $sql .= " AND p.tanggal >= :dateFrom";
            $params['dateFrom'] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND p.tanggal <= :dateTo";
            $params['dateTo'] = $dateTo;
        }
        $sql .= " ORDER BY p.tanggal DESC, p.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary() {
        $sql = "SELECT COUNT(*) as total,
                       SUM(status = 'completed') as completed,
                       SUM(status != 'completed') as pending
                FROM penerimaan";
        return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT p.*, po.nomor_po, spk.nomor_spk, u.username as pic_name
                FROM penerimaan p
                LEFT JOIN po ON p.po_id = po.id
                LEFT JOIN spk ON p.spk_id = spk.id
                LEFT JOIN users u ON p.pic = u.id
                WHERE p.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getItems($penerimaan_id) {
        $sql = "SELECT pi.*, pr.nama
            FROM penerimaan_items pi
            LEFT JOIN produk pr ON pi.produk_id = pr.id
            WHERE pi.penerimaan_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$penerimaan_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($data, $items) {
        $this->pdo->beginTransaction();
        $sql = "INSERT INTO penerimaan (nomor_penerimaan, po_id, spk_id, tanggal, status, pic, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_penerimaan'],
            $data['po_id'],
            $data['spk_id'],
            $data['tanggal'],
            $data['status'],
            $data['pic'],
            $data['notes']
        ]);
        $penerimaan_id = $this->pdo->lastInsertId();
        foreach ($items as $item) {
            $sql = "INSERT INTO penerimaan_items (penerimaan_id, produk_id, qty_order, qty_diterima) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $penerimaan_id,
                $item['produk_id'],
                $item['qty_order'],
                $item['qty_diterima']
            ]);
        }
        $this->pdo->commit();
        return $penerimaan_id;
    }

    public function update($id, $data, $items) {
        $this->pdo->beginTransaction();
        $sql = "UPDATE penerimaan SET nomor_penerimaan=?, po_id=?, spk_id=?, tanggal=?, status=?, pic=?, notes=? WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_penerimaan'],
            $data['po_id'],
            $data['spk_id'],
            $data['tanggal'],
            $data['status'],
            $data['pic'],
            $data['notes'],
            $id
        ]);
        $this->pdo->prepare("DELETE FROM penerimaan_items WHERE penerimaan_id=?")->execute([$id]);
        foreach ($items as $item) {
            $sql = "INSERT INTO penerimaan_items (penerimaan_id, produk_id, qty_order, qty_diterima) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $id,
                $item['produk_id'],
                $item['qty_order'],
                $item['qty_diterima']
            ]);
        }
        $this->pdo->commit();
    }

    public function delete($id) {
        $this->pdo->beginTransaction();
        $this->pdo->prepare("DELETE FROM penerimaan_items WHERE penerimaan_id=?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM penerimaan WHERE id=?")->execute([$id]);
        $this->pdo->commit();
    }
}
