<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/StokLog.php'; // Panggil CCTV Mutasi

class PO {

    public static function all() {
        global $pdo;
        // MENGUBAH ORDER BY menjadi po.id DESC agar yang terbaru selalu di atas
        $sql = "SELECT po.*, customers.perusahaan 
                FROM po 
                LEFT JOIN customers ON po.customer_id = customers.id 
                ORDER BY po.id DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        global $pdo;
        $sql = "SELECT po.*, customers.perusahaan FROM po LEFT JOIN customers ON po.customer_id = customers.id WHERE po.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data) {
        global $pdo;
        $sql = "INSERT INTO po (nomor_po, tanggal, customer_id, status, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$data['nomor_po'], $data['tanggal'], $data['customer_id'], $data['status'], $data['notes'] ?? null]);
    }

    public static function createWithItems($dataPO, $dataItems = []) {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $sqlPO = "INSERT INTO po (nomor_po, tanggal, customer_id, status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmtPO = $pdo->prepare($sqlPO);
            $stmtPO->execute([$dataPO['nomor_po'], $dataPO['tanggal'], $dataPO['customer_id'], $dataPO['status'] ?? 'draft', $dataPO['notes'] ?? null]);
            $poId = $pdo->lastInsertId();

            if (!empty($dataItems) && is_array($dataItems)) {
                $sqlItem = "INSERT INTO po_items (po_id, produk_id, kode_material, nama_material, uom, qty, qty_available, qty_pending, harga_satuan, diskon, amount, keterangan, is_reserved, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'yes', NOW())";
                $stmtItem = $pdo->prepare($sqlItem);
                
                $stmtCekStok = $pdo->prepare("SELECT stok, stok_reserved, stok_available FROM produk WHERE id = ?");
                $stmtUpdateStok = $pdo->prepare("UPDATE produk SET stok_reserved = stok_reserved + ?, stok_available = stok_available - ? WHERE id = ?");

                foreach ($dataItems as $item) {
                    if (empty($item['qty']) || empty($item['harga_satuan']) || empty($item['produk_id'])) {
                        throw new Exception("Data item tidak lengkap!");
                    }

                    $produk_id = (int)$item['produk_id'];
                    $qty = (int)$item['qty'];
                    
                    $subtotal = $qty * (float)$item['harga_satuan'];
                    $diskonAmount = $subtotal * ((float)($item['diskon'] ?? 0) / 100);
                    $amount = $subtotal - $diskonAmount;

                    $stmtItem->execute([
                        $poId, $produk_id, $item['kode_material'] ?? '', $item['nama_material'] ?? '',
                        $item['uom'] ?? 'pcs', $qty, $qty, 0, $item['harga_satuan'], $item['diskon'] ?? 0, 
                        $amount, $item['keterangan'] ?? null
                    ]);
                    
                    $stmtCekStok->execute([$produk_id]);
                    $prod = $stmtCekStok->fetch(PDO::FETCH_ASSOC);

                    if ($prod['stok_available'] < $qty) {
                        throw new Exception("Stok untuk {$item['nama_material']} tidak mencukupi.");
                    }

                    $res_before = $prod['stok_reserved'];
                    $res_after = $res_before + $qty;

                    $stmtUpdateStok->execute([$qty, $qty, $produk_id]);

                    StokLog::record(
                        $produk_id, 'po_reserve', $qty, 
                        $prod['stok'], $prod['stok'], 
                        $res_before, $res_after, 
                        'PO', $poId, "Booking stok untuk PO #" . $dataPO['nomor_po']
                    );
                }
            }

            $pdo->commit();
            return $poId;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update($id, $data) {
        global $pdo;
        $sql = "UPDATE po SET nomor_po = ?, tanggal = ?, customer_id = ?, status = ?, notes = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$data['nomor_po'], $data['tanggal'], $data['customer_id'] ?? null, $data['status'], $data['notes'] ?? null, $id]);
    }

    public static function delete($id) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM po WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItems($poId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function calculateTotal($poId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM po_items WHERE po_id = ?");
        $stmt->execute([$poId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public static function addItem($data) {
        global $pdo;
        $subtotal = $data['qty'] * $data['harga_satuan'];
        $amount = $subtotal - ($subtotal * ($data['diskon'] / 100));
        
        $sql = "INSERT INTO po_items (po_id, produk_id, kode_material, nama_material, uom, qty, qty_available, qty_pending, harga_satuan, diskon, amount, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['po_id'], $data['produk_id'] ?? null, $data['kode_material'] ?? '', $data['nama_material'] ?? '',
            $data['uom'] ?? 'pcs', $data['qty'], $data['qty_available'] ?? $data['qty'], $data['qty_pending'] ?? 0,
            $data['harga_satuan'], $data['diskon'], $amount, $data['keterangan'] ?? null
        ]);
    }

    public static function updateItem($id, $data) {
        global $pdo;
        $subtotal = $data['qty'] * $data['harga_satuan'];
        $amount = $subtotal - ($subtotal * ($data['diskon'] / 100));

        $sql = "UPDATE po_items SET kode_material=?, nama_material=?, uom=?, qty=?, harga_satuan=?, diskon=?, amount=?, keterangan=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['kode_material'] ?? '', $data['nama_material'] ?? '', $data['uom'] ?? 'pcs', 
            $data['qty'], $data['harga_satuan'], $data['diskon'], $amount, $data['keterangan'] ?? null, $id
        ]);
    }

    public static function deleteItem($id) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM po_items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItem($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM po_items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function unreserveStok($poId, $userId = null) {
        global $pdo;
        try {
            $pdo->beginTransaction();
            $items = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ? AND is_reserved = 'yes'");
            $items->execute([$poId]);
            $items = $items->fetchAll();
            
            $stmtCekStok = $pdo->prepare("SELECT stok, stok_reserved FROM produk WHERE id = ?");
            $stmtUpdateStok = $pdo->prepare("UPDATE produk SET stok_reserved = stok_reserved - ?, stok_available = stok_available + ? WHERE id = ?");
            $stmtUpdateItem = $pdo->prepare("UPDATE po_items SET is_reserved = 'no' WHERE id = ?");
            
            foreach ($items as $item) {
                if (empty($item['produk_id'])) continue;
                $stmtCekStok->execute([$item['produk_id']]);
                $prod = $stmtCekStok->fetch(PDO::FETCH_ASSOC);
                
                $res_before = $prod['stok_reserved'];
                $res_after = $res_before - $item['qty'];

                $stmtUpdateStok->execute([$item['qty'], $item['qty'], $item['produk_id']]);
                $stmtUpdateItem->execute([$item['id']]);

                StokLog::record(
                    $item['produk_id'], 'po_unreserve', -$item['qty'], 
                    $prod['stok'], $prod['stok'], 
                    $res_before, $res_after, 
                    'PO_Cancel', $poId, "Unreserve stok karena PO dibatalkan"
                );
            }
            $stmt = $pdo->prepare("UPDATE po SET status_stok = 'draft' WHERE id = ?");
            $stmt->execute([$poId]);
            $pdo->commit();
            return ['success' => true, 'message' => "Reserve stok berhasil dibatalkan."];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function getPOWithStok($poId) {
        global $pdo;
        $sql = "SELECT po.*, c.nama AS customer_name, c.perusahaan,
                GROUP_CONCAT(CONCAT(poi.nama_material, ' (',poi.qty, 'pcs), Avail: ', COALESCE(p.stok_available, 0)) SEPARATOR ' | ') AS items_detail
                FROM po
                LEFT JOIN customers c ON po.customer_id = c.id
                LEFT JOIN po_items poi ON po.id = poi.po_id
                LEFT JOIN produk p ON poi.produk_id = p.id
                WHERE po.id = ? GROUP BY po.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poId]);
        return $stmt->fetch();
    }
}
?>