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
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
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
                
                // TAMBAH stok jika status completed (MENGGUNAKAN STOKTRACKING)
                if ($data['status'] === 'completed' && $item['qty_diterima'] > 0) {
                    $result = $stokTracking->addStok(
                        $item['produk_id'],
                        $item['qty_diterima'],
                        'penerimaan',
                        $penerimaan_id,
                        $data['pic'] ?? null,
                        "Barang masuk dari supplier - " . ($data['nomor_penerimaan'] ?? 'No Ref')
                    );
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                    
                    // UNRESERVE stok dari PO yang sudah diterima
                    if (!empty($data['po_id'])) {
                        $result = $stokTracking->unreserveStok(
                            $item['produk_id'],
                            $item['qty_diterima'],
                            'penerimaan',
                            $penerimaan_id,
                            $data['pic'] ?? null,
                            "Unreserve PO - Barang diterima"
                        );
                        // Don't fail if unreserve fails, just log it
                    }
                }
            }
            $this->pdo->commit();
            return $penerimaan_id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update($id, $data, $items) {
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            $this->pdo->beginTransaction();
            
            // Get old data untuk check status change
            $oldData = $this->getById($id);
            $oldStatus = $oldData['status'] ?? 'draft';
            $newStatus = $data['status'] ?? 'draft';
            $isStatusChange = ($oldStatus !== $newStatus && $newStatus === 'completed');
            
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
            
            // Get old items
            $oldItems = $this->getItems($id);
            
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
                
                // Jika status berubah menjadi completed, tambah stok
                if ($isStatusChange && $item['qty_diterima'] > 0) {
                    $result = $stokTracking->addStok(
                        $item['produk_id'],
                        $item['qty_diterima'],
                        'penerimaan',
                        $id,
                        $data['pic'] ?? null,
                        "Barang masuk dari supplier - " . ($data['nomor_penerimaan'] ?? 'No Ref')
                    );
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                    
                    // UNRESERVE stok dari PO
                    if (!empty($data['po_id'])) {
                        $result = $stokTracking->unreserveStok(
                            $item['produk_id'],
                            $item['qty_diterima'],
                            'penerimaan',
                            $id,
                            $data['pic'] ?? null,
                            "Unreserve PO - Barang diterima"
                        );
                    }
                }
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete($id) {
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            $this->pdo->beginTransaction();
            
            // Get items untuk rollback stok jika completed
            $items = $this->getItems($id);
            $penerimaan = $this->getById($id);
            $status = $penerimaan['status'] ?? 'draft';
            
            // Rollback stok jika penerimaan sudah completed
            if ($status === 'completed') {
                foreach ($items as $item) {
                    if ($item['qty_diterima'] > 0) {
                        // Kurang balik stok yang sudah ditambah
                        $stokTracking->reduceStok(
                            $item['produk_id'],
                            $item['qty_diterima'],
                            'penerimaan_delete',
                            $id,
                            null,
                            "Rollback - Penerimaan dihapus"
                        );
                        
                        // Re-reserve stok kembali ke PO
                        if (!empty($penerimaan['po_id'])) {
                            $stokTracking->reserveStok(
                                $item['produk_id'],
                                $item['qty_diterima'],
                                'penerimaan_delete',
                                $id,
                                null,
                                "Re-reserve - Penerimaan dihapus"
                            );
                        }
                    }
                }
            }
            
            // 1. Hapus verifikasi_items (cucu) terlebih dahulu
            $this->pdo->prepare("DELETE FROM verifikasi_items WHERE verifikasi_id IN (SELECT id FROM verifikasi WHERE penerimaan_id = ?)")->execute([$id]);
            
            // 2. Hapus data verifikasi (anak)
            $this->pdo->prepare("DELETE FROM verifikasi WHERE penerimaan_id = ?")->execute([$id]);
            
            // 3. Hapus penerimaan_items (anak asli)
            $this->pdo->prepare("DELETE FROM penerimaan_items WHERE penerimaan_id=?")->execute([$id]);
            
            // 4. Hapus penerimaan (induk)
            $this->pdo->prepare("DELETE FROM penerimaan WHERE id=?")->execute([$id]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>