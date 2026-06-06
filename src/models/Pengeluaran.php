<?php
class Pengeluaran {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // List pengeluaran dengan relasi
    public function getAll($search = '', $status = '') {
        $sql = "SELECT p.*, spk.nomor_spk, u.username as pic_name
                FROM pengeluaran p
                LEFT JOIN spk ON p.spk_id = spk.id
                LEFT JOIN users u ON p.pic = u.id
                WHERE 1";
        $params = [];
        if ($search) {
            $sql .= " AND (p.nomor_pengeluaran LIKE :search OR spk.nomor_spk LIKE :search)";
            $params['search'] = "%$search%";
        }
        if ($status) {
            $sql .= " AND p.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY p.tanggal DESC, p.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT p.*, spk.nomor_spk, u.username as pic_name
                FROM pengeluaran p
                LEFT JOIN spk ON p.spk_id = spk.id
                LEFT JOIN users u ON p.pic = u.id
                WHERE p.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getItems($pengeluaran_id) {
        $sql = "SELECT pi.*, pr.nama, pr.stok
                FROM pengeluaran_items pi
                LEFT JOIN produk pr ON pi.produk_id = pr.id
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
            $sql = "INSERT INTO pengeluaran (nomor_pengeluaran, spk_id, tanggal, status, pic, notes) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_pengeluaran'],
                $data['spk_id'],
                $data['tanggal'],
                $data['status'],
                $data['pic'],
                $data['notes']
            ]);
            $pengeluaran_id = $this->pdo->lastInsertId();
            
            foreach ($items as $item) {
                // Validasi stok
                $produk = $this->pdo->query("SELECT stok FROM produk WHERE id=" . (int)$item['produk_id'])->fetch();
                if (!$produk || $item['qty'] > $produk['stok']) {
                    throw new Exception('Stok produk tidak cukup untuk produk ID ' . $item['produk_id']);
                }
                
                $sql = "INSERT INTO pengeluaran_items (pengeluaran_id, produk_id, qty) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $pengeluaran_id,
                    $item['produk_id'],
                    $item['qty']
                ]);
                
                // KURANG stok jika status completed (MENGGUNAKAN STOKTRACKING)
                if ($data['status'] === 'completed') {
                    $result = $stokTracking->reduceStok(
                        $item['produk_id'],
                        $item['qty'],
                        'pengeluaran',
                        $pengeluaran_id,
                        $data['pic'] ?? null,
                        "Pengeluaran ke customer - " . ($data['nomor_pengeluaran'] ?? 'No Ref')
                    );
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
            $sql = "UPDATE pengeluaran SET nomor_pengeluaran=?, spk_id=?, tanggal=?, status=?, pic=?, notes=? WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_pengeluaran'],
                $data['spk_id'],
                $data['tanggal'],
                $data['status'],
                $data['pic'],
                $data['notes'],
                $id
            ]);
            
            // Get old items untuk potential rollback
            $oldItems = $this->getItems($id);
            
            // Hapus item lama
            $this->pdo->prepare("DELETE FROM pengeluaran_items WHERE pengeluaran_id=?")->execute([$id]);
            
            foreach ($items as $item) {
                $produk = $this->pdo->query("SELECT stok FROM produk WHERE id=" . (int)$item['produk_id'])->fetch();
                if (!$produk) {
                    throw new Exception('Produk ID ' . $item['produk_id'] . ' tidak ditemukan');
                }
                
                $sql = "INSERT INTO pengeluaran_items (pengeluaran_id, produk_id, qty) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $id,
                    $item['produk_id'],
                    $item['qty']
                ]);
                
                // Handle stok changes based on status transition
                if ($oldStatus !== 'completed' && $newStatus === 'completed') {
                    // Draft/pending → Completed: REDUCE stok
                    $result = $stokTracking->reduceStok(
                        $item['produk_id'],
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
                    // Completed → Draft/pending: ADD BACK stok (rollback)
                    $result = $stokTracking->addStok(
                        $item['produk_id'],
                        $item['qty'],
                        'pengeluaran_rollback',
                        $id,
                        $data['pic'] ?? null,
                        "Rollback pengeluaran"
                    );
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
        $this->pdo->beginTransaction();
        $this->pdo->prepare("DELETE FROM pengeluaran_items WHERE pengeluaran_id=?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM pengeluaran WHERE id=?")->execute([$id]);
        $this->pdo->commit();
    }
}
