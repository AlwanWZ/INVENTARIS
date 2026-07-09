<?php
class Verifikasi {
    private $pdo;
    
    public function __construct($pdo) { 
        $this->pdo = $pdo; 
    }

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
                LEFT JOIN barang pr ON vi.barang_id = pr.id
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
     * Tambah Verifikasi BM Baru (AMAN DARI ERROR ACTIVE TRANSACTION)
     */
    public function add($data, $items) {
        // 1. Buat penanda apakah fungsi ini yang membuka transaksi atau menumpang
        $isRootTransaction = false;
        
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            // Cek apakah transaksi belum terbuka. Jika belum, baru kita buka!
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }

            $status = strtolower($data['status'] ?? 'draft');
            
            // Simpan Header Verifikasi
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
                $qtyMasuk = (int)($item['qty_masuk'] ?? $item['qty_diterima'] ?? $item['qty'] ?? 0);
                $qtyOk    = (int)($item['qty_ok'] ?? $item['qty'] ?? 0);
                
                // Simpan Item Verifikasi
                $sql = "INSERT INTO verifikasi_items (verifikasi_id, barang_id, qty_masuk, qty_ok, keterangan) VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $verif_id, 
                    $item['barang_id'], 
                    $qtyMasuk, 
                    $qtyOk, 
                    $item['keterangan'] ?? ''
                ]);
                
                // OTOMATIS TAMBAH STOK JIKA DIREKAM SEBAGAI VERIFIED / APPROVED / SELESAI
                if (in_array($status, ['verified', 'approved', 'selesai', 'completed'])) {
                    $result = $stokTracking->addStok(
                        $item['barang_id'],
                        $qtyOk,
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
            
            // Hanya commit jika fungsi INI yang tadi membuka transaksinya
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return $verif_id;
            
        } catch (\Exception $e) {
            // Hanya rollback jika fungsi INI yang tadi membuka transaksinya
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getItems($verif_id) {
        $sql = "SELECT vi.*, pr.nama AS produk_nama, pr.satuan AS produk_satuan,
                COALESCE(vi.qty_masuk, si.qty_po, pi.qty_diterima, 0) AS qty_masuk 
                FROM verifikasi_items vi 
                LEFT JOIN barang pr ON vi.barang_id = pr.id 
                LEFT JOIN verifikasi v ON vi.verifikasi_id = v.id
                LEFT JOIN penerimaan_items pi ON v.penerimaan_id = pi.penerimaan_id AND vi.barang_id = pi.barang_id
                LEFT JOIN spk_items si ON v.spk_id = si.spk_id AND vi.barang_id = si.barang_id
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
        $isRootTransaction = false;
        
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
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
            
            // Daftar status yang dianggap "Aktif / Stok Fisik Bertambah"
            $activeStatuses = ['verified', 'approved', 'selesai', 'completed'];
            
            // LOGIC SAKTI 1: Jika berubah dari DRAFT ke VERIFIED (Stok Fisik Bertambah)
            if ($statusChanged && !in_array($oldStatus, $activeStatuses) && in_array($newStatus, $activeStatuses)) {
                $currentItems = $this->getItems($data['id']);
                foreach ($currentItems as $item) {
                    $result = $stokTracking->addStok(
                        $item['barang_id'],
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
            elseif ($statusChanged && in_array($oldStatus, $activeStatuses) && !in_array($newStatus, $activeStatuses)) {
                $currentItems = $this->getItems($data['id']);
                foreach ($currentItems as $item) {
                    $result = $stokTracking->reduceStok(
                        $item['barang_id'],
                        (int)($item['qty_ok'] ?? 0),
                        'verifikasi_rollback',
                        $data['id'],
                        $data['pic'] ?? null,
                        "Pembalikan Status (Rollback) FG ke Draft"
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
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return true;
            
        } catch (\Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete($id) {
        $isRootTransaction = false;
        
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $isRootTransaction = true;
            }
            
            $oldData = $this->getById($id);
            $oldStatus = strtolower($oldData['status'] ?? 'draft');
            
            if (in_array($oldStatus, ['verified', 'approved', 'selesai', 'completed'])) {
                require_once __DIR__ . '/StokTracking.php';
                $stokTracking = new StokTracking($this->pdo);
                $currentItems = $this->getItems($id);
                foreach ($currentItems as $item) {
                    $stokTracking->reduceStok(
                        $item['barang_id'],
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
            
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return true;
            
        } catch (\Exception $e) {
            if ($isRootTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>