<?php
class Verifikasi {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    public function getAll($jenis, $search = '', $status = '') {
        $conditions = ['v.jenis = ?'];
        $params = [$jenis];
        
        if (!empty($search)) {
            $conditions[] = "(p.nomor_penerimaan LIKE ? OR pr.nama LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($status)) {
            $conditions[] = "v.status = ?";
            $params[] = strtolower($status);
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT v.*, p.nomor_penerimaan, p.tanggal AS tanggal_penerimaan, u.username AS pic_name,
                COALESCE(SUM(vi.qty_ok), 0) AS total_ok
                FROM verifikasi v
                LEFT JOIN penerimaan p ON v.penerimaan_id = p.id
                LEFT JOIN users u ON v.pic = u.id
                LEFT JOIN verifikasi_items vi ON v.id = vi.verifikasi_id
                LEFT JOIN produk pr ON vi.produk_id = pr.id
                WHERE $whereClause
                GROUP BY v.id
                ORDER BY v.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize status to lowercase for consistency
        foreach ($results as &$row) {
            $row['status'] = strtolower($row['status'] ?? 'draft');
        }
        
        return $results;
    }

    public function getById($id) {
        $sql = "SELECT v.*, p.nomor_penerimaan, p.tanggal AS tanggal_penerimaan, u.username AS pic_name
                FROM verifikasi v
                LEFT JOIN penerimaan p ON v.penerimaan_id = p.id
                LEFT JOIN users u ON v.pic = u.id
                WHERE v.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Normalize status to lowercase
        if ($result) {
            $result['status'] = strtolower($result['status'] ?? 'draft');
        }
        
        return $result;
    }

    public function add($data, $items) {
        try {
            $this->pdo->beginTransaction();
            // Ensure status is lowercase
            $status = strtolower($data['status'] ?? 'draft');
            $sql = "INSERT INTO verifikasi (penerimaan_id, tanggal, pic, status, jenis) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['penerimaan_id'], $data['tanggal'], $data['pic'], $status, $data['jenis']
            ]);
            $verif_id = $this->pdo->lastInsertId();
            foreach ($items as $item) {
                $sql = "INSERT INTO verifikasi_items (verifikasi_id, produk_id, qty_ok, keterangan) VALUES (?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $verif_id, $item['produk_id'], (int)($item['qty_ok'] ?? 0), $item['keterangan'] ?? ''
                ]);
                // Update stok produk jika status bukan draft
                if ($status !== 'draft') {
                    $updateStok = $this->pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
                    $updateStok->execute([
                        (int)($item['qty_ok'] ?? 0),
                        $item['produk_id']
                    ]);
                }
            }
            $this->pdo->commit();
            return $verif_id;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getItems($verif_id) {
        $sql = "SELECT vi.*, pr.nama AS produk_nama, pi.qty_diterima AS qty_masuk 
                FROM verifikasi_items vi 
                LEFT JOIN produk pr ON vi.produk_id = pr.id 
                LEFT JOIN verifikasi v ON vi.verifikasi_id = v.id
                LEFT JOIN penerimaan_items pi ON v.penerimaan_id = pi.penerimaan_id AND vi.produk_id = pi.produk_id
                WHERE vi.verifikasi_id = ?
                ORDER BY vi.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$verif_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cast numeric fields
        foreach ($results as &$row) {
            $row['id'] = (int)$row['id'];
            $row['qty_ok'] = (int)($row['qty_ok'] ?? 0);
            $row['qty_masuk'] = (int)($row['qty_masuk'] ?? 0);
        }
        
        return $results;
    }

    public function update($data, $items = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Get old status to compare
            $oldData = $this->getById($data['id']);
            $oldStatus = strtolower($oldData['status'] ?? 'draft');
            $newStatus = strtolower($data['status'] ?? $oldStatus);
            $statusChanged = ($oldStatus !== $newStatus);
            
            // Update verifikasi header
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['tanggal'])) {
                $updateFields[] = 'tanggal = ?';
                $updateParams[] = $data['tanggal'];
            }
            
            if (isset($data['status'])) {
                $updateFields[] = 'status = ?';
                $updateParams[] = strtolower($data['status']); // Pastiin huruf kecil buat ENUM
            }
            
            if (!empty($updateFields)) {
                $updateParams[] = $data['id'];
                $sql = "UPDATE verifikasi SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($updateParams);
            }
            
            // Update Stok Barang (Kalau pindah dari draft ke verified atau sebaliknya)
            if ($statusChanged && $oldStatus === 'draft' && $newStatus === 'verified') {
                $currentItems = $this->getItems($data['id']);
                foreach ($currentItems as $item) {
                    $updateStok = $this->pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
                    $updateStok->execute([(int)($item['qty_ok'] ?? 0), $item['produk_id']]);
                }
            } elseif ($statusChanged && $oldStatus === 'verified' && $newStatus === 'draft') {
                $currentItems = $this->getItems($data['id']);
                foreach ($currentItems as $item) {
                    $updateStok = $this->pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                    $updateStok->execute([(int)($item['qty_ok'] ?? 0), $item['produk_id']]);
                }
            }
            
            // Update verifikasi items (Pake ID Item langsung, ga usah nebak urutan)
            if (!empty($items)) {
                foreach ($items as $item) {
                    // Pastikan id dari form edit beneran masuk
                    if (isset($item['id']) && !empty($item['id'])) {
                        $updateItem = $this->pdo->prepare("UPDATE verifikasi_items SET qty_ok = ?, keterangan = ? WHERE id = ?");
                        $updateItem->execute([
                            (int)($item['qty_ok'] ?? 0),
                            $item['keterangan'] ?? '',
                            $item['id']
                        ]);
                    }
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM verifikasi_items WHERE verifikasi_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM verifikasi WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>