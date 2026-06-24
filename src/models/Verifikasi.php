<?php
class Verifikasi {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    public function getAll($jenis, $search = '', $status = '') {
        $conditions = ['v.jenis = ?'];
        $params = [$jenis];
        
        if (!empty($search)) {
            $conditions[] = "(p.nomor_penerimaan LIKE ? OR s.nomor_spk LIKE ? OR pr.nama LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($status)) {
            $conditions[] = "v.status = ?";
            $params[] = strtolower($status);
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // PENTING: Ambil nomor_spk kalau penerimaan_id kosong
        $sql = "SELECT v.*, 
                COALESCE(p.nomor_penerimaan, s.nomor_spk) AS nomor_penerimaan, 
                COALESCE(p.tanggal, s.tanggal) AS tanggal_penerimaan, 
                u.username AS pic_name,
                COALESCE(SUM(vi.qty_ok), 0) AS total_ok
                FROM verifikasi v
                LEFT JOIN penerimaan p ON v.penerimaan_id = p.id
                LEFT JOIN spk s ON v.spk_id = s.id
                LEFT JOIN users u ON v.pic = u.id
                LEFT JOIN verifikasi_items vi ON v.id = vi.verifikasi_id
                LEFT JOIN produk pr ON vi.produk_id = pr.id
                WHERE $whereClause
                GROUP BY v.id
                ORDER BY v.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['status'] = strtolower($row['status'] ?? 'draft');
        }
        
        return $results;
    }

    public function getById($id) {
        $sql = "SELECT v.*, 
                COALESCE(p.nomor_penerimaan, s.nomor_spk) AS nomor_penerimaan, 
                COALESCE(p.tanggal, s.tanggal) AS tanggal_penerimaan, 
                u.username AS pic_name
                FROM verifikasi v
                LEFT JOIN penerimaan p ON v.penerimaan_id = p.id
                LEFT JOIN spk s ON v.spk_id = s.id
                LEFT JOIN users u ON v.pic = u.id
                WHERE v.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $result['status'] = strtolower($result['status'] ?? 'draft');
        }
        
        return $result;
    }

    /**
     * Tambah Verifikasi BM Baru
     */
    public function add($data, $items) {
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            $this->pdo->beginTransaction();
            $status = strtolower($data['status'] ?? 'draft');
            
            // Perhatikan penambahan spk_id disini
            $sql = "INSERT INTO verifikasi (penerimaan_id, spk_id, tanggal, pic, status, jenis) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['penerimaan_id'] ?? null, 
                $data['spk_id'] ?? null, 
                $data['tanggal'], 
                $data['pic'], 
                $status, 
                $data['jenis']
            ]);
            $verif_id = $this->pdo->lastInsertId();
            
            foreach ($items as $item) {
                // Simpan juga qty_masuk biar data aman
                $sql = "INSERT INTO verifikasi_items (verifikasi_id, produk_id, qty_masuk, qty_ok, keterangan) VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $verif_id, 
                    $item['produk_id'], 
                    (int)($item['qty_masuk'] ?? 0), 
                    (int)($item['qty_ok'] ?? 0), 
                    $item['keterangan'] ?? ''
                ]);
                
                // OTOMATIS TAMBAH STOK JIKA DIREKAM SEBAGAI VERIFIED
                if ($status === 'verified' || $status === 'approved') {
                    $result = $stokTracking->addStok(
                        $item['produk_id'],
                        (int)($item['qty_ok'] ?? 0),
                        'verifikasi_bm',
                        $verif_id,
                        $data['pic'] ?? null,
                        "Finish Good dari SPK Selesai QC"
                    );
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                }
            }
            $this->pdo->commit();
            return $verif_id;
        } catch (\Exception $e) {
            // Perbaikan pengecekan transaksi aktif sebelum rollback
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getItems($verif_id) {
        $sql = "SELECT vi.*, pr.nama AS produk_nama, 
                COALESCE(si.qty_schedule, pi.qty_diterima, vi.qty_masuk) AS qty_masuk 
                FROM verifikasi_items vi 
                LEFT JOIN produk pr ON vi.produk_id = pr.id 
                LEFT JOIN verifikasi v ON vi.verifikasi_id = v.id
                LEFT JOIN penerimaan_items pi ON v.penerimaan_id = pi.penerimaan_id AND vi.produk_id = pi.produk_id
                LEFT JOIN spk_items si ON v.spk_id = si.spk_id AND vi.produk_id = si.produk_id
                WHERE vi.verifikasi_id = ?
                ORDER BY vi.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$verif_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['id'] = (int)$row['id'];
            $row['qty_ok'] = (int)($row['qty_ok'] ?? 0);
            $row['qty_masuk'] = (int)($row['qty_masuk'] ?? 0);
        }
        
        return $results;
    }

    public function update($data, $items = []) {
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            $this->pdo->beginTransaction();
            
            $oldData = $this->getById($data['id']);
            $oldStatus = strtolower($oldData['status'] ?? 'draft');
            $newStatus = strtolower($data['status'] ?? $oldStatus);
            $statusChanged = ($oldStatus !== $newStatus);
            
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['tanggal'])) {
                $updateFields[] = 'tanggal = ?';
                $updateParams[] = $data['tanggal'];
            }
            
            if (isset($data['status'])) {
                $updateFields[] = 'status = ?';
                $updateParams[] = strtolower($data['status']);
            }
            
            if (!empty($updateFields)) {
                $updateParams[] = $data['id'];
                $sql = "UPDATE verifikasi SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($updateParams);
            }
            
            // LOGIC SAKTI 1: Jika berubah dari DRAFT ke VERIFIED (Stok Fisik Bertambah)
            if ($statusChanged && $oldStatus === 'draft' && ($newStatus === 'verified' || $newStatus === 'approved')) {
                $currentItems = $this->getItems($data['id']);
                foreach ($currentItems as $item) {
                    $result = $stokTracking->addStok(
                        $item['produk_id'],
                        (int)($item['qty_ok'] ?? 0),
                        'verifikasi_bm',
                        $data['id'],
                        $data['pic'] ?? null,
                        "Pembaruan Status: Draft -> Verified FG"
                    );
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                }
            } 
            // LOGIC SAKTI 2: Jika dibatalkan kembali dari VERIFIED ke DRAFT (Stok ditarik kembali)
            elseif ($statusChanged && ($oldStatus === 'verified' || $oldStatus === 'approved') && $newStatus === 'draft') {
                $currentItems = $this->getItems($data['id']);
                foreach ($currentItems as $item) {
                    $result = $stokTracking->reduceStok(
                        $item['produk_id'],
                        (int)($item['qty_ok'] ?? 0),
                        'verifikasi_rollback',
                        $data['id'],
                        $data['pic'] ?? null,
                        "Pembalikaan Status (Rollback) FG ke Draft"
                    );
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                }
            }
            
            if (!empty($items)) {
                foreach ($items as $item) {
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
            // Perbaikan pengecekan transaksi aktif sebelum rollback
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete($id) {
        try {
            $this->pdo->beginTransaction();
            $oldData = $this->getById($id);
            $oldStatus = strtolower($oldData['status'] ?? 'draft');
            
            if ($oldStatus === 'verified' || $oldStatus === 'approved') {
                require_once __DIR__ . '/StokTracking.php';
                $stokTracking = new StokTracking($this->pdo);
                $currentItems = $this->getItems($id);
                foreach ($currentItems as $item) {
                    $stokTracking->reduceStok(
                        $item['produk_id'],
                        (int)($item['qty_ok'] ?? 0),
                        'verifikasi_deleted',
                        $id,
                        $_SESSION['user']['id'] ?? null,
                        "Penghapusan Dokumen Verifikasi FG #" . $id
                    );
                }
            }

            $this->pdo->prepare("DELETE FROM verifikasi_items WHERE verifikasi_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM verifikasi WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            // Perbaikan pengecekan transaksi aktif sebelum rollback
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>