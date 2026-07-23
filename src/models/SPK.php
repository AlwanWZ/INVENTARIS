<?php

require_once __DIR__ . '/../config.php';

class SPK {

    // =========================================================================
    // 1. MENGAMBIL SEMUA DATA SPK (DENGAN FILTER & SEARCH)
    // =========================================================================
    public static function all($filter = []) {
        global $pdo;

        $sql = "SELECT spk.*, pesanan.nomor_pesanan, customers.perusahaan, users.username as pic_username
                FROM spk
                LEFT JOIN pesanan ON spk.pesanan_id = pesanan.id
                LEFT JOIN customers ON pesanan.customer_id = customers.id
                LEFT JOIN users ON spk.pic = users.id
                WHERE 1=1";

        $params = [];

        if (!empty($filter['tanggal'])) {
            $sql .= " AND spk.tanggal = :tanggal";
            $params['tanggal'] = $filter['tanggal'];
        }

        if (!empty($filter['status'])) {
            $sql .= " AND spk.status = :status";
            // PERBAIKAN: Kirim status murni apa adanya, jangan dimanipulasi
            $params['status'] = trim($filter['status']);
        }

        if (!empty($filter['pic'])) {
            $sql .= " AND spk.pic = :pic";
            $params['pic'] = $filter['pic'];
        }

        if (!empty($filter['search'])) {
            $sql .= " AND (spk.nomor_spk LIKE :search1 OR pesanan.nomor_pesanan LIKE :search2 OR customers.perusahaan LIKE :search3)";
            $searchTerm = '%' . $filter['search'] . '%';

            $params['search1'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
        }

        $sql .= " ORDER BY spk.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 1B. MENGAMBIL SEMUA DATA SPK BESERTA ITEM (UNTUK DAFTAR ITEM DI INDEX)
    // =========================================================================
    public static function allItems($filter = []) {
        global $pdo;

        $sql = "SELECT spk.*, pesanan.nomor_pesanan, customers.perusahaan, users.username as pic_username,
                       si.nama_barang as item_nama, si.qty_schedule as item_qty, si.qty_outstanding as item_sisa,
                       b.ukuran as item_ukuran
                FROM spk
                JOIN spk_items si ON spk.id = si.spk_id
                LEFT JOIN barang b ON si.barang_id = b.id
                LEFT JOIN pesanan ON spk.pesanan_id = pesanan.id
                LEFT JOIN customers ON pesanan.customer_id = customers.id
                LEFT JOIN users ON spk.pic = users.id
                WHERE 1=1";

        $params = [];

        if (!empty($filter['tanggal'])) {
            $sql .= " AND spk.tanggal = :tanggal";
            $params['tanggal'] = $filter['tanggal'];
        }

        if (!empty($filter['status'])) {
            $sql .= " AND spk.status = :status";
            $params['status'] = trim($filter['status']);
        }

        if (!empty($filter['pic'])) {
            $sql .= " AND spk.pic = :pic";
            $params['pic'] = $filter['pic'];
        }

        if (!empty($filter['search'])) {
            $sql .= " AND (spk.nomor_spk LIKE :search1 OR pesanan.nomor_pesanan LIKE :search2 OR customers.perusahaan LIKE :search3 OR si.nama_barang LIKE :search4)";
            $searchTerm = '%' . $filter['search'] . '%';

            $params['search1'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
            $params['search4'] = $searchTerm;
        }

        $sql .= " ORDER BY spk.id DESC, si.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 2. MENGAMBIL 1 DATA SPK BERDASARKAN ID
    // =========================================================================
    public static function find($id) {
        global $pdo;

        $sql = "SELECT spk.*,
                pesanan.nomor_pesanan,
                customers.id as customer_id,
                customers.nama as customer_nama,
                customers.perusahaan,
                users.username AS pic_username,
                spk.pic AS pic_id
                FROM spk
                LEFT JOIN pesanan ON spk.pesanan_id = pesanan.id
                LEFT JOIN customers ON pesanan.customer_id = customers.id
                LEFT JOIN users ON spk.pic = users.id
                WHERE spk.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 3. MEMBUAT SPK BARU & AUTO-COPY BARANG DARI PO KE SPK_ITEMS
    // =========================================================================
    public static function create($data) {
        global $pdo;

        // Paksa PDO memunculkan error kalau ada SQL yang salah ketik/gagal
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->beginTransaction();

            // PERBAIKAN: Hapus strtolower dan str_replace. Biarkan formatnya sesuai form/database.
            $status_db = trim($data['status'] ?? 'draft');

            // 1. Simpan Header SPK
            $sql = "INSERT INTO spk (nomor_spk, pesanan_id, customer_id, tanggal, deadline, pic, status, notes, progress) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_spk'],
                $data['pesanan_id'],
                $data['customer_id'] ?? null,
                $data['tanggal'],
                $data['deadline'],
                $data['pic_id'] ?? null,
                $status_db,
                $data['notes'],
                $data['progress'] ?? 0
            ]);

            $spkId = $pdo->lastInsertId();

            // 2. Insert items dari input
            if (!empty($data['items']) && is_array($data['items'])) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO spk_items (
                        spk_id, pic_id, barang_id, nama_barang,
                        stok_gudang, stok_available, qty_po, qty_schedule,
                        qty_preparation, qty_outstanding, status_produksi, note
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $itemCount = 0;
                foreach ($data['items'] as $item) {
                    $barang_id = (int)($item['barang_id'] ?? 0);
                    $qty_schedule = (int)($item['qty_schedule'] ?? 0);
                    $note = $item['note'] ?? '';
                    $namaBarang = $item['nama_barang'] ?? 'Tanpa Nama';
                    
                    if ($barang_id <= 0 || $qty_schedule <= 0) continue;
                    
                    // Ambil detail PO qty dan stok
                    $stmtInfo = $pdo->prepare("
                        SELECT pi.qty as qty_po, b.stok, b.stok_available 
                        FROM pesanan_items pi 
                        JOIN barang b ON pi.barang_id = b.id
                        WHERE pi.pesanan_id = ? AND pi.barang_id = ?
                    ");
                    $stmtInfo->execute([$data['pesanan_id'], $barang_id]);
                    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                    
                    $qty_po = (int)($info['qty_po'] ?? 0);
                    $stok_gudang = (int)($info['stok'] ?? 0);
                    $stok_available = (int)($info['stok_available'] ?? 0);

                    $insertStmt->execute([
                        $spkId,
                        $data['pic_id'] ?? null,
                        $barang_id,
                        $namaBarang,
                        $stok_gudang,
                        $stok_available,
                        $qty_po,
                        $qty_schedule,
                        0,
                        $qty_schedule, // Awalnya outstanding = schedule
                        'pending',
                        $note
                    ]);
                    $itemCount++;
                }

                if ($itemCount === 0) {
                    throw new Exception("Tidak ada item yang valid untuk diproduksi dalam SPK ini.");
                }
            } else {
                throw new Exception("Barang SPK tidak boleh kosong!");
            }

            $pdo->commit();
            return $spkId;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // 4. MENGUPDATE DATA HEADER SPK
    // =========================================================================
    public static function update($id, $data) {
        global $pdo;

        // PERBAIKAN: Hapus strtolower dan str_replace.
        $status_db = trim($data['status'] ?? 'draft');

        $sql = "UPDATE spk SET nomor_spk=?, pesanan_id=?, customer_id=?, tanggal=?, deadline=?, pic=?, status=?, notes=?, progress=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_spk'],
            $data['pesanan_id'],
            $data['customer_id'] ?? null,
            $data['tanggal'],
            $data['deadline'],
            $data['pic_id'] ?? null,
            $status_db,
            $data['notes'],
            $data['progress'] ?? 0,
            $id
        ]);
        
        // Update items
        if (!empty($data['items']) && is_array($data['items'])) {
            $stmtUpdateItem = $pdo->prepare("UPDATE spk_items SET qty_schedule=?, pic_id=? WHERE id=? AND spk_id=?");
            foreach ($data['items'] as $itemId => $itemData) {
                $qty = (int)($itemData['qty_schedule'] ?? 0);
                $picId = !empty($itemData['pic_id']) ? (int)$itemData['pic_id'] : null;
                $stmtUpdateItem->execute([$qty, $picId, $itemId, $id]);
            }
        }
    }

    // =========================================================================
    // 5. MENGHAPUS SPK DENGAN AMAN (SAFE DELETE)
    // =========================================================================
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

            // 2. Cek apakah SPK masuk tahap Penerimaan
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // 6. MENGAMBIL DAFTAR BARANG DALAM 1 SPK (DENGAN NAMA PIC)
    // =========================================================================
    public static function getItems($spkId) {
        global $pdo;

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

    // =========================================================================
    // 7. MENGAMBIL DAFTAR BARANG DALAM 1 SPK (MODE SIMPLE / PRINT)
    // =========================================================================
    public static function getItemsSimple($spkId) {
        global $pdo;

        $sql = "SELECT * FROM spk_items WHERE spk_id = ? ORDER BY id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spkId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 8. SINKRONISASI ULANG BARANG DARI PO KE SPK (JIKA PO DIEDIT)
    // =========================================================================
    public static function syncItemsFromPO($spkId) {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $spkStmt = $pdo->prepare("SELECT pesanan_id, pic FROM spk WHERE id = ?");
            $spkStmt->execute([$spkId]);
            $spk = $spkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$spk || empty($spk['pesanan_id'])) {
                $pdo->rollBack();
                return false;
            }

            // Hapus item lama di SPK ini
            $deleteStmt = $pdo->prepare("DELETE FROM spk_items WHERE spk_id = ?");
            $deleteStmt->execute([$spkId]);

            // Salin ulang dari PO
            $poItems = $pdo->prepare("SELECT * FROM pesanan_items WHERE pesanan_id = ?");
            $poItems->execute([$spk['pesanan_id']]);
            $items = $poItems->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($items)) {
                $pdo->commit();
                return false;
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO spk_items (
                    spk_id, pic_id, barang_id, nama_barang,
                    stok_gudang, stok_available, qty_po, qty_schedule,
                    qty_preparation, qty_outstanding, status_produksi, note
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $namaBarang = $item['nama_material'] ?? $item['nama_barang'] ?? 'Tanpa Nama';
                $qty = (int)($item['qty'] ?? 0);
                $stok = (int)($item['qty_available'] ?? 0);
                $note = $item['keterangan'] ?? '';

                $insertStmt->execute([
                    $spkId,
                    $spk['pic'] ?? null,
                    $item['barang_id'] ?? null,
                    $namaBarang,
                    $stok,
                    $stok,
                    $qty,
                    $qty,
                    0,
                    $qty,
                    'pending',
                    $note
                ]);
            }

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================================
    // 9. UPDATE PIC KHUSUS UNTUK 1 ITEM BARANG DI SPK
    // =========================================================================
    public static function updateItemPic($itemId, $picId) {
        global $pdo;

        $sql = "UPDATE spk_items SET pic_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$picId, $itemId]);
    }

    // =========================================================================
    // 10. MENGAMBIL DAFTAR ID PIC YANG TERLIBAT DALAM 1 SPK
    // =========================================================================
    public static function getItemPicIds($spkId) {
        global $pdo;

        $sql = "SELECT DISTINCT pic_id FROM spk_items WHERE spk_id = ? AND pic_id IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spkId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'pic_id');
    }

    // =========================================================================
    // 11. OTOMATIS MENYELESAIKAN SPK SAAT MASUK FINISH GOOD
    // =========================================================================
    public static function complete($spkId) {
        global $pdo;

        // Bikin penanda apakah fungsi ini yang buka transaksi atau bukan
        $needCommit = false;
        
        try {
            // Cek apakah transaksi SUDAH DIBUKA oleh file lain (misal: finish_good/add.php)
            // Kalau belum buka, baru kita buka di sini
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $needCommit = true;
            }

            // 1. Ubah status SPK (induk) menjadi 'completed' dan progress jadi 100%
            $stmtSpk = $pdo->prepare("UPDATE spk SET status = 'completed', progress = 100 WHERE id = ?");
            $stmtSpk->execute([$spkId]);

            // 2. Ubah juga status barang (spk_items) jadi 'selesai' dan habiskan qty_outstanding
            // (Catatan: diubah jadi 'selesai' atau 'approved' menghindari error 1265 Data truncated)
            $stmtItems = $pdo->prepare("UPDATE spk_items SET status_produksi = 'selesai', qty_outstanding = 0 WHERE spk_id = ?");
            $stmtItems->execute([$spkId]);

            // Kalau fungsi ini yang tadi buka transaksi, maka dia juga yang harus tutup (commit)
            if ($needCommit && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return true;
            
        } catch (Exception $e) {
            // Rollback hanya jika fungsi ini yang membuka transaksi
            if ($needCommit && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
?>