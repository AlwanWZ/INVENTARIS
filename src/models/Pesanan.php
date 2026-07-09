<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/StokLog.php';

class Pesanan {

    public static function all() {
        global $pdo;
        // PERBAIKAN: Tabel diganti jadi 'pesanan'
        $sql = "SELECT pesanan.*, customers.perusahaan,
                (SELECT SUM(qty) FROM pesanan_items WHERE pesanan_id = pesanan.id) as total_qty,
                (SELECT SUM(qty_dikirim) FROM pesanan_items WHERE pesanan_id = pesanan.id) as total_dikirim
                FROM pesanan 
                LEFT JOIN customers ON pesanan.customer_id = customers.id 
                ORDER BY pesanan.id DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        global $pdo;
        // PERBAIKAN: Tabel diganti jadi 'pesanan'
        $sql = "SELECT pesanan.*, customers.perusahaan 
                FROM pesanan 
                LEFT JOIN customers ON pesanan.customer_id = customers.id 
                WHERE pesanan.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data) {
        global $pdo;
        // PERBAIKAN: Tabel diganti jadi 'pesanan' & kolom jadi 'nomor_pesanan'
        $sql = "INSERT INTO pesanan (nomor_pesanan, tanggal, tanggal_pengiriman, customer_id, status, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['nomor_pesanan'] ?? $data['nomor_po'], // Support nama input baru maupun lama
            $data['tanggal'],
            $data['tanggal_pengiriman'] ?? null,
            $data['customer_id'],
            $data['status'],
            $data['notes'] ?? null
        ]);
    }

    public static function createWithItems($dataPO, $dataItems = []) {
        global $pdo;

        try {
            $pdo->beginTransaction();

            // PERBAIKAN: Tabel diganti jadi 'pesanan' & kolom jadi 'nomor_pesanan'
            $sqlPO = "INSERT INTO pesanan (nomor_pesanan, tanggal, tanggal_pengiriman, customer_id, status, notes, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmtPO = $pdo->prepare($sqlPO);
            $stmtPO->execute([
                $dataPO['nomor_pesanan'] ?? $dataPO['nomor_po'],
                $dataPO['tanggal'],
                $dataPO['tanggal_pengiriman'] ?? null,
                $dataPO['customer_id'],
                $dataPO['status'] ?? 'draft',
                $dataPO['notes'] ?? null
            ]);
            $poId = $pdo->lastInsertId();

            if (!empty($dataItems) && is_array($dataItems)) {
                $sqlItem = "INSERT INTO pesanan_items 
                            (pesanan_id, barang_id, kode_material, nama_material, uom, qty, qty_available, qty_pending, 
                             harga_satuan, diskon, amount, keterangan, is_reserved, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'yes', NOW())";
                $stmtItem = $pdo->prepare($sqlItem);

                $stmtCekStok    = $pdo->prepare("SELECT stok, stok_reserved, stok_available FROM barang WHERE id = ?");
                $stmtUpdateStok = $pdo->prepare("UPDATE barang SET stok_reserved = stok_reserved + ?, stok_available = stok_available - ? WHERE id = ?");

                foreach ($dataItems as $item) {
                    if (empty($item['qty']) || empty($item['harga_satuan']) || empty($item['barang_id'])) {
                        throw new Exception("Data item tidak lengkap!");
                    }

                    $barang_id    = (int)$item['barang_id'];
                    $qty          = (int)$item['qty'];
                    $subtotal     = $qty * (float)$item['harga_satuan'];
                    $diskonAmount = $subtotal * ((float)($item['diskon'] ?? 0) / 100);
                    $amount       = $subtotal - $diskonAmount;

                    $stmtItem->execute([
                        $poId, $barang_id,
                        $item['kode_material'] ?? '', $item['nama_material'] ?? '',
                        $item['uom'] ?? 'pcs', $qty, $qty, 0,
                        $item['harga_satuan'], $item['diskon'] ?? 0,
                        $amount, $item['keterangan'] ?? null
                    ]);

                    $stmtCekStok->execute([$barang_id]);
                    $prod = $stmtCekStok->fetch(PDO::FETCH_ASSOC);

                    // VALIDASI STOK DIHAPUS 
                    // Mengizinkan pesanan melebihi stok (stok_available akan menjadi minus/backorder)
                    // Nantinya bagian produksi akan menambalnya melalui SPK.

                    $res_before = $prod['stok_reserved'];
                    $res_after  = $res_before + $qty;

                    $stmtUpdateStok->execute([$qty, $qty, $barang_id]);

                    $nomorRef = $dataPO['nomor_pesanan'] ?? $dataPO['nomor_po'] ?? $poId;
                    StokLog::record(
                        $barang_id, 'po_reserve', $qty,
                        $prod['stok'], $prod['stok'],
                        $res_before, $res_after,
                        'PO', $poId, "Booking stok untuk Pesanan #" . $nomorRef
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
        // PERBAIKAN: Tabel diganti jadi 'pesanan' & kolom jadi 'nomor_pesanan'
        $sql = "UPDATE pesanan 
                SET nomor_pesanan = ?, tanggal = ?, tanggal_pengiriman = ?, customer_id = ?, status = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['nomor_pesanan'] ?? $data['nomor_po'],
            $data['tanggal'],
            $data['tanggal_pengiriman'] ?? null,
            $data['customer_id'] ?? null,
            $data['status'],
            $data['notes'] ?? null,
            $id
        ]);
    }

    public static function delete($id) {
        global $pdo;
        // PERBAIKAN: Tabel diganti jadi 'pesanan'
        $stmt = $pdo->prepare("DELETE FROM pesanan WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItems($poId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM pesanan_items WHERE pesanan_id = ?");
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function calculateTotal($poId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM pesanan_items WHERE pesanan_id = ?");
        $stmt->execute([$poId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public static function addItem($data) {
        global $pdo;
        $subtotal = $data['qty'] * $data['harga_satuan'];
        $amount   = $subtotal - ($subtotal * ($data['diskon'] / 100));

        $sql = "INSERT INTO pesanan_items 
                (pesanan_id, barang_id, kode_material, nama_material, uom, qty, qty_available, qty_pending, 
                 harga_satuan, diskon, amount, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['pesanan_id'], $data['barang_id'] ?? null,
            $data['kode_material'] ?? '', $data['nama_material'] ?? '',
            $data['uom'] ?? 'pcs', $data['qty'],
            $data['qty_available'] ?? $data['qty'], $data['qty_pending'] ?? 0,
            $data['harga_satuan'], $data['diskon'], $amount,
            $data['keterangan'] ?? null
        ]);
    }

    public static function updateItem($id, $data) {
        global $pdo;
        $subtotal = $data['qty'] * $data['harga_satuan'];
        $amount   = $subtotal - ($subtotal * ($data['diskon'] / 100));

        $sql = "UPDATE pesanan_items 
                SET kode_material=?, nama_material=?, uom=?, qty=?, harga_satuan=?, diskon=?, amount=?, keterangan=? 
                WHERE id=?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['kode_material'] ?? '', $data['nama_material'] ?? '',
            $data['uom'] ?? 'pcs', $data['qty'],
            $data['harga_satuan'], $data['diskon'], $amount,
            $data['keterangan'] ?? null, $id
        ]);
    }

    public static function deleteItem($id) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM pesanan_items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItem($id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM pesanan_items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function unreserveStok($poId, $userId = null) {
        global $pdo;
        try {
            $pdo->beginTransaction();

            $items = $pdo->prepare("SELECT * FROM pesanan_items WHERE pesanan_id = ? AND is_reserved = 'yes'");
            $items->execute([$poId]);
            $items = $items->fetchAll();

            $stmtCekStok    = $pdo->prepare("SELECT stok, stok_reserved FROM barang WHERE id = ?");
            $stmtUpdateStok = $pdo->prepare("UPDATE barang SET stok_reserved = stok_reserved - ?, stok_available = stok_available + ? WHERE id = ?");
            $stmtUpdateItem = $pdo->prepare("UPDATE pesanan_items SET is_reserved = 'no' WHERE id = ?");

            foreach ($items as $item) {
                if (empty($item['barang_id'])) continue;
                $stmtCekStok->execute([$item['barang_id']]);
                $prod = $stmtCekStok->fetch(PDO::FETCH_ASSOC);

                $res_before = $prod['stok_reserved'];
                $res_after  = $res_before - $item['qty'];

                $stmtUpdateStok->execute([$item['qty'], $item['qty'], $item['barang_id']]);
                $stmtUpdateItem->execute([$item['id']]);

                StokLog::record(
                    $item['barang_id'], 'po_unreserve', -$item['qty'],
                    $prod['stok'], $prod['stok'],
                    $res_before, $res_after,
                    'PO_Cancel', $poId, "Unreserve stok karena Pesanan dibatalkan"
                );
            }

            // PERBAIKAN: Tabel diganti jadi 'pesanan'
            $stmt = $pdo->prepare("UPDATE pesanan SET status_stok = 'draft', updated_at = NOW() WHERE id = ?");
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
        // PERBAIKAN: Tabel diganti jadi 'pesanan'
        $sql = "SELECT pesanan.*, c.nama AS customer_name, c.perusahaan,
                GROUP_CONCAT(CONCAT(poi.nama_material, ' (', poi.qty, 'pcs), Avail: ', COALESCE(p.stok_available, 0)) SEPARATOR ' | ') AS items_detail
                FROM pesanan
                LEFT JOIN customers c ON pesanan.customer_id = c.id
                LEFT JOIN pesanan_items poi ON pesanan.id = poi.pesanan_id
                LEFT JOIN barang p ON poi.barang_id = p.id
                WHERE pesanan.id = ? GROUP BY pesanan.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poId]);
        return $stmt->fetch();
    }
}
?>