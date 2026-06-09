<?php
require_once __DIR__ . '/../config.php';

class SPK {

    public static function all($filter = []) {
        global $pdo;
        
        // 1. Kueri dasar dengan WHERE 1=1 agar filter mudah disambung
        $sql = "SELECT spk.*, po.nomor_po, customers.perusahaan, users.username as pic_username 
                FROM spk 
                LEFT JOIN po ON spk.po_id = po.id 
                LEFT JOIN customers ON po.customer_id = customers.id 
                LEFT JOIN users ON spk.pic = users.id
                WHERE 1=1";
        
        $params = [];

        // 2. Tambahkan filter jika ada
        if (!empty($filter['tanggal'])) {
            $sql .= " AND spk.tanggal = :tanggal";
            $params['tanggal'] = $filter['tanggal'];
        }
        if (!empty($filter['status'])) {
            $sql .= " AND spk.status = :status";
            $params['status'] = $filter['status'];
        }
        if (!empty($filter['pic'])) {
            $sql .= " AND spk.pic = :pic";
            $params['pic'] = $filter['pic'];
        }
        if (!empty($filter['search'])) {
            $sql .= " AND (spk.nomor_spk LIKE :search OR po.nomor_po LIKE :search OR customers.perusahaan LIKE :search)";
            $params['search'] = '%' . $filter['search'] . '%';
        }

        // 3. SATU KALI ORDER BY DI PALING BAWAH
        $sql .= " ORDER BY spk.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        global $pdo;
        
        $sql = "SELECT spk.*, 
                       po.nomor_po, 
                       customers.id as customer_id,
                       customers.nama as customer_nama, 
                       customers.perusahaan, 
                       users.username AS pic_username,
                       spk.pic AS pic_id
                FROM spk
                LEFT JOIN po ON spk.po_id = po.id
                LEFT JOIN customers ON po.customer_id = customers.id
                LEFT JOIN users ON spk.pic = users.id
                WHERE spk.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data) {
        global $pdo;
        
        $sql = "INSERT INTO spk (nomor_spk, po_id, tanggal, deadline, pic, status, notes, progress) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_spk'],
            $data['po_id'],
            $data['tanggal'],
            $data['deadline'],
            $data['pic_id'] ?? null,
            $data['status'],
            $data['notes'],
            $data['progress'] ?? 0
        ]);
        
        $spkId = $pdo->lastInsertId();
        
        // Auto-copy items from PO to SPK
        if (!empty($data['po_id'])) {
            $poItems = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
            $poItems->execute([$data['po_id']]);
            $items = $poItems->fetchAll(\PDO::FETCH_ASSOC);
            
            if ($items) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO spk_items 
                    (spk_id, produk_id, nama_barang, stok_gudang, qty_po, qty_schedule, qty_preparation, qty_outstanding, note) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $item) {
                    $insertStmt->execute([
                        $spkId,
                        $item['produk_id'] ?? null,
                        $item['nama_barang'] ?? '',
                        $item['stok_gudang'] ?? 0,
                        $item['qty_po'] ?? 0,
                        $item['qty_schedule'] ?? 0,
                        $item['qty_preparation'] ?? 0,
                        $item['qty_outstanding'] ?? 0,
                        $item['note'] ?? ''
                    ]);
                }
            }
        }
        
        return $spkId;
    }

    public static function update($id, $data) {
        global $pdo;
        
        $sql = "UPDATE spk SET nomor_spk=?, po_id=?, tanggal=?, deadline=?, pic=?, status=?, notes=?, progress=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_spk'],
            $data['po_id'],
            $data['tanggal'],
            $data['deadline'],
            $data['pic_id'] ?? null,
            $data['status'],
            $data['notes'],
            $data['progress'] ?? 0,
            $id
        ]);
    }

    // --- FUNGSI DELETE YANG SUDAH DIPERBAIKI ---
    public static function delete($id) {
        global $pdo;
        
        try {
            $pdo->beginTransaction();

            // 1. Cek apakah SPK sudah masuk tahap Pengeluaran di Gudang
            $stmtCekPengeluaran = $pdo->prepare("SELECT COUNT(id) FROM pengeluaran WHERE spk_id = ?");
            $stmtCekPengeluaran->execute([$id]);
            if ($stmtCekPengeluaran->fetchColumn() > 0) {
                throw new Exception("GAGAL: SPK tidak bisa dihapus karena sudah diproses menjadi Pengeluaran oleh Gudang.");
            }

            // 2. Cek apakah SPK masuk tahap Penerimaan (Opsional, buat jaga-jaga)
            $stmtCekPenerimaan = $pdo->prepare("SELECT COUNT(id) FROM penerimaan WHERE spk_id = ?");
            $stmtCekPenerimaan->execute([$id]);
            if ($stmtCekPenerimaan->fetchColumn() > 0) {
                throw new Exception("GAGAL: SPK tidak bisa dihapus karena terkait dengan data Penerimaan di Gudang.");
            }

            // 3. Hapus item-item SPK terlebih dahulu (Anak Data)
            $stmtItems = $pdo->prepare("DELETE FROM spk_items WHERE spk_id = ?");
            $stmtItems->execute([$id]);

            // 4. Hapus data SPK (Induk Data)
            $stmtSpk = $pdo->prepare("DELETE FROM spk WHERE id = ?");
            $stmtSpk->execute([$id]);

            $pdo->commit();
        } catch (Exception $e) {
            // Batalkan semua query jika ada yang gagal
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Lempar pesan error ke halaman delete.php
            throw $e;
        }
    }
    // -------------------------------------------

    public static function getItems($spkId) {
        global $pdo;
        
        // Get items with PIC username (for edit form)
        $sql = "SELECT spk_items.*, 
                       COALESCE(users.username, '—') AS pic_username
                FROM spk_items
                LEFT JOIN users ON spk_items.pic_id = users.id
                WHERE spk_items.spk_id = ?
                ORDER BY spk_items.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getItemsSimple($spkId) {
        global $pdo;
        
        // Get items without JOIN (for print/simple display)
        $sql = "SELECT * FROM spk_items WHERE spk_id = ? ORDER BY id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function syncItemsFromPO($spkId) {
        global $pdo;
        
        // Get SPK data to get PO ID
        $spkStmt = $pdo->prepare("SELECT po_id FROM spk WHERE id = ?");
        $spkStmt->execute([$spkId]);
        $spk = $spkStmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$spk || empty($spk['po_id'])) {
            return false;
        }
        
        // Delete existing items for this SPK
        $deleteStmt = $pdo->prepare("DELETE FROM spk_items WHERE spk_id = ?");
        $deleteStmt->execute([$spkId]);
        
        // Copy items from PO
        $poItems = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
        $poItems->execute([$spk['po_id']]);
        $items = $poItems->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            return false;
        }
        
        $insertStmt = $pdo->prepare("
            INSERT INTO spk_items 
            (spk_id, produk_id, nama_barang, stok_gudang, qty_po, qty_schedule, qty_preparation, qty_outstanding, note) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $insertStmt->execute([
                $spkId,
                $item['produk_id'] ?? null,
                $item['nama_barang'] ?? '',
                $item['stok_gudang'] ?? 0,
                $item['qty_po'] ?? 0,
                $item['qty_schedule'] ?? 0,
                $item['qty_preparation'] ?? 0,
                $item['qty_outstanding'] ?? 0,
                $item['note'] ?? ''
            ]);
        }
        
        return true;
    }

    public static function updateItemPic($itemId, $picId) {
        global $pdo;
        
        $sql = "UPDATE spk_items SET pic_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$picId, $itemId]);
    }

    public static function getItemPicIds($spkId) {
        global $pdo;
        
        $sql = "SELECT DISTINCT pic_id FROM spk_items WHERE spk_id = ? AND pic_id IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spkId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'pic_id');
    }
}
?>