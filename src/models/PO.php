<?php
require_once __DIR__ . '/../config.php';

class PO {

    public static function all() {
        global $pdo;

        $sql = "SELECT 
                    po.*,
                    customers.perusahaan
                FROM po
                LEFT JOIN customers 
                    ON po.customer_id = customers.id
                ORDER BY po.tanggal DESC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        global $pdo;

        $sql = "SELECT 
                    po.*,
                    customers.perusahaan
                FROM po
                LEFT JOIN customers 
                    ON po.customer_id = customers.id
                WHERE po.id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data) {
        global $pdo;

        $sql = "INSERT INTO po 
                (nomor_po, tanggal, customer_id, status, notes) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['nomor_po'],
            $data['tanggal'],
            $data['customer_id'],
            $data['status'],
            $data['notes'] ?? null
        ]);
    }

    /**
     * Create PO with multiple items using PDO Transaction
     * 
     * RULE 3: Pemotongan stok WAJIB di dalam transaction
     * Saat PO berhasil di-insert, UPDATE produk table:
     * - stok_reserved = stok_reserved + qty
     * - stok_available = stok_available - qty
     * 
     * @param array $dataPO - Master data: nomor_po, tanggal, customer_id, status, notes
     * @param array $dataItems - Array of items: [['produk_id' => 1, 'qty' => 10, 'qty_available' => 10, ...], ...]
     * @return int|false - PO ID jika sukses, false jika gagal
     */
    public static function createWithItems($dataPO, $dataItems = []) {
        global $pdo;
        require_once __DIR__ . '/Produk.php';

        try {
            // START TRANSACTION
            $pdo->beginTransaction();

            // 1. INSERT ke tabel po (Master)
            $sqlPO = "INSERT INTO po 
                      (nomor_po, tanggal, customer_id, status, notes, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmtPO = $pdo->prepare($sqlPO);
            $stmtPO->execute([
                $dataPO['nomor_po'],
                $dataPO['tanggal'],
                $dataPO['customer_id'],
                $dataPO['status'] ?? 'draft',
                $dataPO['notes'] ?? null
            ]);

            // Get the last inserted PO ID
            $poId = $pdo->lastInsertId();

            // 2. INSERT items ke tabel po_items + UPDATE stok produk (RULE 3)
            if (!empty($dataItems) && is_array($dataItems)) {
                $sqlItem = "INSERT INTO po_items 
                           (po_id, produk_id, kode_material, nama_material, uom, qty, qty_available, qty_pending, harga_satuan, diskon, amount, keterangan, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmtItem = $pdo->prepare($sqlItem);
                
                $sqlUpdateStok = "UPDATE produk 
                                  SET stok_reserved = stok_reserved + ?,
                                      stok_available = stok_available - ?
                                  WHERE id = ?";
                $stmtUpdateStok = $pdo->prepare($sqlUpdateStok);

                foreach ($dataItems as $item) {
                    // Validate item data
                    if (empty($item['qty'])) {
                        throw new Exception("Qty wajib diisi untuk setiap item");
                    }
                    if (empty($item['harga_satuan'])) {
                        throw new Exception("Harga Satuan wajib diisi untuk setiap item");
                    }
                    if (empty($item['produk_id'])) {
                        throw new Exception("Produk ID wajib diisi untuk setiap item");
                    }

                    $produk_id = (int)$item['produk_id'];
                    $qty = (int)$item['qty'];
                    $harga_satuan = (float)$item['harga_satuan'];
                    
                    // Calculate amount = (qty * harga_satuan) - ((qty * harga_satuan) * (diskon/100))
                    $subtotal = $qty * $harga_satuan;
                    $diskon = floatval($item['diskon'] ?? 0);
                    $diskonAmount = $subtotal * ($diskon / 100);
                    $amount = $subtotal - $diskonAmount;
                    
                    // qty_available = qty (semua order STRICT, tidak boleh backorder saat create)
                    $qty_available = $qty;
                    $qty_pending = 0;

                    // INSERT PO Item
                    $stmtItem->execute([
                        $poId,
                        $produk_id,
                        $item['kode_material'] ?? '',
                        $item['nama_material'] ?? '',
                        $item['uom'] ?? 'pcs',
                        $qty,
                        $qty_available,
                        $qty_pending,
                        $harga_satuan,
                        $diskon,
                        $amount,
                        $item['keterangan'] ?? null
                    ]);
                    
                    // RULE 3: UPDATE stok produk
                    // stok_reserved += qty, stok_available -= qty
                    $stmtUpdateStok->execute([
                        $qty,              // stok_reserved + qty
                        $qty,              // stok_available - qty
                        $produk_id
                    ]);
                }
            }

            // COMMIT TRANSACTION
            $pdo->commit();

            return $poId;

        } catch (Exception $e) {
            // ROLLBACK jika ada error
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update($id, $data) {
        global $pdo;

        $sql = "UPDATE po SET 
                    nomor_po = ?, 
                    tanggal = ?, 
                    customer_id = ?, 
                    status = ?, 
                    notes = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['nomor_po'],
            $data['tanggal'],
            $data['customer_id'] ?? $data['customer'] ?? null,
            $data['status'],
            $data['notes'] ?? null,
            $id
        ]);
    }

    public static function delete($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM po WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItems($poId) {
        global $pdo;

        $sql = "SELECT * FROM po_items WHERE po_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function calculateTotal($poId) {
        global $pdo;

        $sql = "SELECT SUM(amount) AS total FROM po_items WHERE po_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] ?? 0;
    }

    // ===== ITEM MANAGEMENT =====

    public static function addItem($data) {
        global $pdo;

        // Calculate amount = (qty * harga_satuan) - ((qty * harga_satuan) * (diskon/100))
        $subtotal = $data['qty'] * $data['harga_satuan'];
        $diskonAmount = $subtotal * ($data['diskon'] / 100);
        $amount = $subtotal - $diskonAmount;
        
        // BACKORDER LOGIC: Calculate ready vs pending
        $qty_available = $data['qty_available'] ?? $data['qty'];
        $qty_pending = $data['qty_pending'] ?? 0;

        $sql = "INSERT INTO po_items 
                (po_id, produk_id, kode_material, nama_material, uom, qty, qty_available, qty_pending, harga_satuan, diskon, amount, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['po_id'],
            $data['produk_id'] ?? null,
            $data['kode_material'] ?? '',
            $data['nama_material'] ?? '',
            $data['uom'] ?? 'pcs',
            $data['qty'],
            $qty_available,
            $qty_pending,
            $data['harga_satuan'],
            $data['diskon'],
            $amount,
            $data['keterangan'] ?? null
        ]);
    }

    public static function updateItem($id, $data) {
        global $pdo;

        // Recalculate amount
        $subtotal = $data['qty'] * $data['harga_satuan'];
        $diskonAmount = $subtotal * ($data['diskon'] / 100);
        $amount = $subtotal - $diskonAmount;

        $sql = "UPDATE po_items SET 
                    kode_material = ?, 
                    nama_material = ?, 
                    uom = ?, 
                    qty = ?, 
                    harga_satuan = ?, 
                    diskon = ?, 
                    amount = ?, 
                    keterangan = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['kode_material'] ?? '',
            $data['nama_material'] ?? '',
            $data['uom'] ?? 'pcs',
            $data['qty'],
            $data['harga_satuan'],
            $data['diskon'],
            $amount,
            $data['keterangan'] ?? null,
            $id
        ]);
    }

    public static function deleteItem($id) {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM po_items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItem($id) {
        global $pdo;

        $sql = "SELECT * FROM po_items WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ============ STOK INTEGRATION ============
     * Auto reserve/unreserve stok saat PO status berubah
     */

    /**
     * Reserve stok saat PO di-approve
     * Kurangi stok_available, nambah stok_reserved
     */
    public static function reserveStok($poId, $userId = null) {
        global $pdo;
        require_once __DIR__ . '/StokTracking.php';
        
        $stokTracking = new StokTracking($pdo);
        
        try {
            $pdo->beginTransaction();
            
            // Get PO items
            $items = self::getItems($poId);
            if (empty($items)) {
                throw new Exception("PO tidak memiliki items");
            }
            
            $reserved_count = 0;
            $errors = [];
            
            foreach ($items as $item) {
                if (empty($item['produk_id'])) continue;
                
                $result = $stokTracking->reserveStok(
                    $item['produk_id'],
                    $item['qty'],
                    'po',
                    $poId,
                    $userId,
                    "Reserve untuk PO #{$poId}"
                );
                
                if ($result['success']) {
                    // Update po_items is_reserved = yes
                    $stmt = $pdo->prepare("UPDATE po_items SET is_reserved = 'yes' WHERE id = ?");
                    $stmt->execute([$item['id']]);
                    $reserved_count++;
                } else {
                    $errors[] = $result['message'];
                }
            }
            
            // Update PO status
            $stmt = $pdo->prepare("
                UPDATE po 
                SET status_stok = 'reserved', approval_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$poId]);
            
            if (!empty($errors)) {
                throw new Exception(implode("; ", $errors));
            }
            
            $pdo->commit();
            return ['success' => true, 'message' => "Stok berhasil di-reserve untuk {$reserved_count} items"];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Unreserve stok saat PO di-cancel/reject
     */
    public static function unreserveStok($poId, $userId = null) {
        global $pdo;
        require_once __DIR__ . '/StokTracking.php';
        
        $stokTracking = new StokTracking($pdo);
        
        try {
            $pdo->beginTransaction();
            
            // Get PO items yang sudah di-reserve
            $items = $pdo->prepare("
                SELECT * FROM po_items 
                WHERE po_id = ? AND is_reserved = 'yes'
            ");
            $items->execute([$poId]);
            $items = $items->fetchAll();
            
            $unreserved_count = 0;
            $errors = [];
            
            foreach ($items as $item) {
                if (empty($item['produk_id'])) continue;
                
                $result = $stokTracking->unreserveStok(
                    $item['produk_id'],
                    $item['qty'],
                    'po_cancel',
                    $poId,
                    $userId,
                    "Unreserve karena PO dibatalkan"
                );
                
                if ($result['success']) {
                    $stmt = $pdo->prepare("UPDATE po_items SET is_reserved = 'no' WHERE id = ?");
                    $stmt->execute([$item['id']]);
                    $unreserved_count++;
                } else {
                    $errors[] = $result['message'];
                }
            }
            
            // Update PO status
            $stmt = $pdo->prepare("UPDATE po SET status_stok = 'draft' WHERE id = ?");
            $stmt->execute([$poId]);
            
            if (!empty($errors)) {
                throw new Exception(implode("; ", $errors));
            }
            
            $pdo->commit();
            return ['success' => true, 'message' => "Reserve stok berhasil dibatalkan untuk {$unreserved_count} items"];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get PO dengan informasi stok realtime
     */
    public static function getPOWithStok($poId) {
        global $pdo;
        
        $sql = "
            SELECT 
                po.*,
                c.nama AS customer_name,
                c.perusahaan,
                GROUP_CONCAT(
                    CONCAT(
                        poi.nama_material, ' (',poi.qty, 'pcs), ',
                        'Avail: ', COALESCE(p.stok_available, 0)
                    ) SEPARATOR ' | '
                ) AS items_detail
            FROM po
            LEFT JOIN customers c ON po.customer_id = c.id
            LEFT JOIN po_items poi ON po.id = poi.po_id
            LEFT JOIN produk p ON poi.produk_id = p.id
            WHERE po.id = ?
            GROUP BY po.id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poId]);
        return $stmt->fetch();
    }
}