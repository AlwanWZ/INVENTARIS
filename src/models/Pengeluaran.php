<?php
class Pengeluaran {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // List pengeluaran dengan relasi
    public function getAll($search = '', $status = '') {
    $sql = "
        SELECT
            p.*,
            spk.nomor_spk,
            pesanan.nomor_pesanan,

            sj.id AS sj_id,
            sj.nomor_sj,
            sj.driver,
            sj.kendaraan,

            COALESCE(
                NULLIF(c.perusahaan,''),
                NULLIF(c.nama,''),
                '-'
            ) AS customer_nama

        FROM pengeluaran p

        LEFT JOIN spk
            ON p.spk_id = spk.id
            
        LEFT JOIN pesanan
            ON p.pesanan_id = pesanan.id

        LEFT JOIN surat_jalan sj
            ON sj.pengeluaran_id = p.id

        LEFT JOIN customers c
            ON sj.customer_id = c.id

        WHERE 1
    ";

    $params = [];

    if ($search) {
        $sql .= " AND (
            p.nomor_pengeluaran LIKE :search
            OR spk.nomor_spk LIKE :search
            OR sj.nomor_sj LIKE :search
            OR sj.driver LIKE :search
        )";

        $params['search'] = "%$search%";
    }

    if ($status) {
        $sql .= " AND p.status = :status";
        $params['status'] = $status;
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

  public function getById($id) {
    $sql = "
        SELECT
            p.*,
            spk.nomor_spk,
            pesanan.nomor_pesanan,

            sj.id AS sj_id,
            sj.nomor_sj,
            sj.driver,
            sj.kendaraan,
            sj.tanggal_kirim,
            sj.alamat_kirim,
            sj.status AS status_sj,

            COALESCE(
                NULLIF(c.perusahaan,''),
                NULLIF(c.nama,''),
                '-'
            ) AS customer_nama

        FROM pengeluaran p

        LEFT JOIN spk
            ON p.spk_id = spk.id
            
        LEFT JOIN pesanan
            ON p.pesanan_id = pesanan.id

        LEFT JOIN surat_jalan sj
            ON sj.pengeluaran_id = p.id

        LEFT JOIN customers c
            ON sj.customer_id = c.id

        WHERE p.id = ?
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    public function getItems($pengeluaran_id) {
        $sql = "SELECT pi.*, pr.nama, pr.stok
                FROM pengeluaran_items pi
                LEFT JOIN barang pr ON pi.barang_id = pr.id
                WHERE pi.pengeluaran_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$pengeluaran_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($data, $items) {
        try {
            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);
            
            $this->pdo->beginTransaction();
            $sql = "INSERT INTO pengeluaran (nomor_pengeluaran, spk_id, pesanan_id, tanggal, status, pic, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_pengeluaran'],
                $data['spk_id'] ?? null,
                $data['pesanan_id'] ?? null,
                $data['tanggal'],
                $data['status'],
                $data['pic'],
                $data['notes']
            ]);
            $pengeluaran_id = $this->pdo->lastInsertId();
            
            // Get pesanan_id for sync if missing but spk_id is present
            $syncPesananId = $data['pesanan_id'] ?? null;
            if (!$syncPesananId && !empty($data['spk_id'])) {
                $spkData = $this->pdo->query("SELECT pesanan_id FROM spk WHERE id = " . (int)$data['spk_id'])->fetch();
                if ($spkData) $syncPesananId = $spkData['pesanan_id'];
            }
            
            foreach ($items as $item) {
                // Validasi stok
                $barang = $this->pdo->query("SELECT stok FROM barang WHERE id=" . (int)$item['barang_id'])->fetch();
                if (!$barang || $item['qty'] > $barang['stok']) {
                    throw new Exception('Stok barang tidak cukup untuk barang ID ' . $item['barang_id']);
                }
                
                $sql = "INSERT INTO pengeluaran_items (pengeluaran_id, barang_id, qty) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $pengeluaran_id,
                    $item['barang_id'],
                    $item['qty']
                ]);
                
                // KURANG stok jika status completed (MENGGUNAKAN STOKTRACKING)
                if ($data['status'] === 'completed') {
                    if ($syncPesananId) {
                        $stmtUpdatePO = $this->pdo->prepare("UPDATE pesanan_items SET qty_dikirim = qty_dikirim + ? WHERE pesanan_id = ? AND barang_id = ?");
                        $stmtUpdatePO->execute([$item['qty'], $syncPesananId, $item['barang_id']]);
                        
                        // FULFILL STOK (karena pesanan_id ada, stok_available sudah dipotong di awal, jadi jangan dipotong lagi)
                        $result = $stokTracking->fulfillStok(
                            $item['barang_id'],
                            $item['qty'],
                            'pengeluaran',
                            $pengeluaran_id,
                            $data['pic'] ?? null,
                            "Pengeluaran ke customer (PO) - " . ($data['nomor_pengeluaran'] ?? 'No Ref')
                        );
                    } else {
                        // REDUCE STOK BIASA (karena tidak terikat PO langsung, potong stok & stok_available)
                        $result = $stokTracking->reduceStok(
                            $item['barang_id'],
                            $item['qty'],
                            'pengeluaran',
                            $pengeluaran_id,
                            $data['pic'] ?? null,
                            "Pengeluaran ke customer - " . ($data['nomor_pengeluaran'] ?? 'No Ref')
                        );
                    }
                    
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                }
            }
            $this->pdo->commit();
            return $pengeluaran_id;
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
            
            // Get old status
            $oldData = $this->getById($id);
            $oldStatus = $oldData['status'] ?? 'draft';
            $newStatus = $data['status'] ?? 'draft';
            
            // Update header
            $sql = "UPDATE pengeluaran SET nomor_pengeluaran=?, spk_id=?, pesanan_id=?, tanggal=?, status=?, pic=?, notes=? WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_pengeluaran'],
                $data['spk_id'] ?? null,
                $data['pesanan_id'] ?? null,
                $data['tanggal'],
                $data['status'],
                $data['pic'],
                $data['notes'],
                $id
            ]);
            
            // Get pesanan_id for sync
            $syncPesananId = $data['pesanan_id'] ?? $oldData['pesanan_id'] ?? null;
            if (!$syncPesananId && !empty($data['spk_id'])) {
                $spkData = $this->pdo->query("SELECT pesanan_id FROM spk WHERE id = " . (int)$data['spk_id'])->fetch();
                if ($spkData) $syncPesananId = $spkData['pesanan_id'];
            }
            
            // Get old items untuk potential rollback
            $oldItems = $this->getItems($id);
            
            // Hapus item lama
            $this->pdo->prepare("DELETE FROM pengeluaran_items WHERE pengeluaran_id=?")->execute([$id]);
            
            foreach ($items as $item) {
                $barang = $this->pdo->query("SELECT stok FROM barang WHERE id=" . (int)$item['barang_id'])->fetch();
                if (!$barang) {
                    throw new Exception('Produk ID ' . $item['barang_id'] . ' tidak ditemukan');
                }
                
                $sql = "INSERT INTO pengeluaran_items (pengeluaran_id, barang_id, qty) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $id,
                    $item['barang_id'],
                    $item['qty']
                ]);
                
                // Handle stok changes based on status transition
                if ($oldStatus !== 'completed' && $newStatus === 'completed') {
                    if ($syncPesananId) {
                        $stmtUpdatePO = $this->pdo->prepare("UPDATE pesanan_items SET qty_dikirim = qty_dikirim + ? WHERE pesanan_id = ? AND barang_id = ?");
                        $stmtUpdatePO->execute([$item['qty'], $syncPesananId, $item['barang_id']]);
                    }
                    
                    // Draft/pending → Completed: REDUCE stok
                    $result = $stokTracking->reduceStok(
                        $item['barang_id'],
                        $item['qty'],
                        'pengeluaran',
                        $id,
                        $data['pic'] ?? null,
                        "Pengeluaran ke customer - " . ($data['nomor_pengeluaran'] ?? 'No Ref')
                    );
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                } elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
                    if ($syncPesananId) {
                        $stmtUpdatePO = $this->pdo->prepare("UPDATE pesanan_items SET qty_dikirim = GREATEST(0, qty_dikirim - ?) WHERE pesanan_id = ? AND barang_id = ?");
                        $stmtUpdatePO->execute([$item['qty'], $syncPesananId, $item['barang_id']]);
                    }
                    
                    // Completed → Draft/pending: ADD BACK stok (rollback)
                    if ($syncPesananId) {
                        $result = $stokTracking->unfulfillStok(
                            $item['barang_id'],
                            $item['qty'],
                            'pengeluaran_rollback',
                            $id,
                            $data['pic'] ?? null,
                            "Rollback pengeluaran (PO)"
                        );
                    } else {
                        $result = $stokTracking->addStok(
                            $item['barang_id'],
                            $item['qty'],
                            'pengeluaran_rollback',
                            $id,
                            $data['pic'] ?? null,
                            "Rollback pengeluaran"
                        );
                    }
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                }
            }
            $this->pdo->commit();
            return $id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

   public function delete($id) {
    try {
        $this->pdo->beginTransaction();

        // Ambil data pengeluaran
        $pengeluaran = $this->getById($id);
        $items = $this->getItems($id);

        // Kembalikan stok jika status completed
        if ($pengeluaran && $pengeluaran['status'] === 'completed') {

            require_once __DIR__ . '/StokTracking.php';
            $stokTracking = new StokTracking($this->pdo);

            $syncPesananId = $pengeluaran['pesanan_id'] ?? null;
            if (!$syncPesananId && !empty($pengeluaran['spk_id'])) {
                $spkData = $this->pdo->query("SELECT pesanan_id FROM spk WHERE id = " . (int)$pengeluaran['spk_id'])->fetch();
                if ($spkData) $syncPesananId = $spkData['pesanan_id'];
            }

            foreach ($items as $item) {
                if ($syncPesananId) {
                    $stmtUpdatePO = $this->pdo->prepare("UPDATE pesanan_items SET qty_dikirim = GREATEST(0, qty_dikirim - ?) WHERE pesanan_id = ? AND barang_id = ?");
                    $stmtUpdatePO->execute([$item['qty'], $syncPesananId, $item['barang_id']]);
                    
                    $result = $stokTracking->unfulfillStok(
                        $item['barang_id'],
                        $item['qty'],
                        'pengeluaran_cancel',
                        $id,
                        null,
                        'Pembatalan Pengeluaran (PO) - ' . ($pengeluaran['nomor_pengeluaran'] ?? '-')
                    );
                } else {
                    $result = $stokTracking->addStok(
                        $item['barang_id'],
                        $item['qty'],
                        'pengeluaran_cancel',
                        $id,
                        null,
                        'Pembatalan Pengeluaran - ' . ($pengeluaran['nomor_pengeluaran'] ?? '-')
                    );
                }

                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
            }
        }

        // Hapus surat jalan items
        $stmt = $this->pdo->prepare("
            DELETE sji
            FROM surat_jalan_items sji
            INNER JOIN surat_jalan sj
                ON sj.id = sji.surat_jalan_id
            WHERE sj.pengeluaran_id = ?
        ");
        $stmt->execute([$id]);

        // Hapus surat jalan
        $stmt = $this->pdo->prepare("
            DELETE FROM surat_jalan
            WHERE pengeluaran_id = ?
        ");
        $stmt->execute([$id]);

        // Hapus pengeluaran items
        $stmt = $this->pdo->prepare("
            DELETE FROM pengeluaran_items
            WHERE pengeluaran_id = ?
        ");
        $stmt->execute([$id]);

        // Hapus pengeluaran
        $stmt = $this->pdo->prepare("
            DELETE FROM pengeluaran
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        $this->pdo->commit();
        return true;

    } catch (Exception $e) {

        $this->pdo->rollBack();
        throw $e;
    }
}
}
?>