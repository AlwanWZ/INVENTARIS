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
     * @param array $dataPO - Master data: nomor_po, tanggal, customer_id, status, notes
     * @param array $dataItems - Array of items: [['produk_id' => 1, 'qty' => 10, 'harga_satuan' => 5000, ...], ...]
     * @return int|false - PO ID jika sukses, false jika gagal
     */
    public static function createWithItems($dataPO, $dataItems = []) {
        global $pdo;

        try {
            // Start transaction
            $pdo->beginTransaction();

            // 1. Insert ke tabel po (Master)
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

            // 2. Insert items ke tabel po_items (Detail)
            if (!empty($dataItems) && is_array($dataItems)) {
                $sqlItem = "INSERT INTO po_items 
                           (po_id, produk_id, kode_material, nama_material, uom, qty, harga_satuan, diskon, amount, keterangan, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmtItem = $pdo->prepare($sqlItem);

                foreach ($dataItems as $item) {
                    // Validate item data
                    if (empty($item['qty']) || empty($item['harga_satuan'])) {
                        throw new Exception("Qty dan Harga Satuan wajib diisi untuk setiap item");
                    }

                    // Calculate amount = (qty * harga_satuan) - ((qty * harga_satuan) * (diskon/100))
                    $subtotal = $item['qty'] * $item['harga_satuan'];
                    $diskon = floatval($item['diskon'] ?? 0);
                    $diskonAmount = $subtotal * ($diskon / 100);
                    $amount = $subtotal - $diskonAmount;

                    // Execute insert untuk setiap item
                    $stmtItem->execute([
                        $poId,
                        $item['produk_id'] ?? null,
                        $item['kode_material'] ?? '',
                        $item['nama_material'] ?? '',
                        $item['uom'] ?? 'pcs',
                        $item['qty'],
                        $item['harga_satuan'],
                        $diskon,
                        $amount,
                        $item['keterangan'] ?? null
                    ]);
                }
            }

            // Commit transaction
            $pdo->commit();

            return $poId;

        } catch (Exception $e) {
            // Rollback jika ada error
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

        $sql = "INSERT INTO po_items 
                (po_id, produk_id, kode_material, nama_material, uom, qty, harga_satuan, diskon, amount, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            $data['po_id'],
            $data['produk_id'] ?? null,
            $data['kode_material'] ?? '',
            $data['nama_material'] ?? '',
            $data['uom'] ?? 'pcs',
            $data['qty'],
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
}