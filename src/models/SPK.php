<?php

require_once __DIR__ . '/../config.php';

class SPK {

    // =========================================================================
    // 1. MENGAMBIL SEMUA DATA SPK (DENGAN FILTER & SEARCH)
    // =========================================================================
    public static function all($filter = []) {
        global $pdo;

        $sql = "SELECT spk.*, po.nomor_po, customers.perusahaan, users.username as pic_username
                FROM spk
                LEFT JOIN po ON spk.po_id = po.id
                LEFT JOIN customers ON po.customer_id = customers.id
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
            $sql .= " AND (spk.nomor_spk LIKE :search1 OR po.nomor_po LIKE :search2 OR customers.perusahaan LIKE :search3)";
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
    // 2. MENGAMBIL 1 DATA SPK BERDASARKAN ID
    // =========================================================================
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
            $sql = "INSERT INTO spk (nomor_spk, po_id, customer_id, tanggal, deadline, pic, status, notes, progress) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['nomor_spk'],
                $data['po_id'],
                $data['customer_id'] ?? null,
                $data['tanggal'],
                $data['deadline'],
                $data['pic_id'] ?? null,
                $status_db,
                $data['notes'],
                $data['progress'] ?? 0
            ]);

            $spkId = $pdo->lastInsertId();

            // 2. Salin barang dari PO ke SPK_ITEMS
            if (!empty($data['po_id'])) {
                $poItems = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
                $poItems->execute([$data['po_id']]);
                $items = $poItems->fetchAll(\PDO::FETCH_ASSOC);

                if (empty($items)) {
                    throw new Exception("Data barang pada Pesanan (PO) tersebut kosong di tabel po_items!");
                }

                $insertStmt = $pdo->prepare("
                    INSERT INTO spk_items (
                        spk_id, pic_id, produk_id, nama_barang,
                        stok_gudang, stok_available, qty_po, qty_schedule,
                        qty_preparation, qty_outstanding, status_produksi, note
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $itemCount = 0;
                foreach ($items as $item) {
                    $namaBarang = $item['nama_material'] ?? $item['nama_barang'] ?? 'Tanpa Nama';
                    $qty = (int)($item['qty'] ?? 0);
                    $stok = (int)($item['qty_available'] ?? 0);
                    $note = $item['keterangan'] ?? '';

                    if ($qty <= 0) {
                        throw new Exception("Ada barang di PO yang jumlah (qty)-nya bernilai 0!");
                    }

                    $insertStmt->execute([
                        $spkId,
                        $data['pic_id'] ?? null,
                        $item['produk_id'] ?? null,
                        $namaBarang,
                        $stok,
                        $stok,
                        $qty,
                        $qty,
                        0,
                        $qty,
                        'pending', // PASTIKAN nilai ENUM 'pending' benar-benar ada di tabel spk_items
                        $note
                    ]);
                    $itemCount++;
                }

                // 3. Jebakan Pengaman Terakhir
                $cekMasuk = $pdo->prepare("SELECT COUNT(*) FROM spk_items WHERE spk_id = ?");
                $cekMasuk->execute([$spkId]);
                if ($cekMasuk->fetchColumn() < $itemCount) {
                    throw new Exception("Gagal menyalin barang ke tabel spk_items karena penolakan dari database!");
                }
            } else {
                throw new Exception("PO ID tidak boleh kosong!");
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

        $sql = "UPDATE spk SET nomor_spk=?, po_id=?, customer_id=?, tanggal=?, deadline=?, pic=?, status=?, notes=?, progress=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nomor_spk'],
            $data['po_id'],
            $data['customer_id'] ?? null,
            $data['tanggal'],
            $data['deadline'],
            $data['pic_id'] ?? null,
            $status_db,
            $data['notes'],
            $data['progress'] ?? 0,
            $id
        ]);
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

            $spkStmt = $pdo->prepare("SELECT po_id, pic FROM spk WHERE id = ?");
            $spkStmt->execute([$spkId]);
            $spk = $spkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$spk || empty($spk['po_id'])) {
                $pdo->rollBack();
                return false;
            }

            // Hapus item lama di SPK ini
            $deleteStmt = $pdo->prepare("DELETE FROM spk_items WHERE spk_id = ?");
            $deleteStmt->execute([$spkId]);

            // Salin ulang dari PO
            $poItems = $pdo->prepare("SELECT * FROM po_items WHERE po_id = ?");
            $poItems->execute([$spk['po_id']]);
            $items = $poItems->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($items)) {
                $pdo->commit();
                return false;
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO spk_items (
                    spk_id, pic_id, produk_id, nama_barang,
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
                    $item['produk_id'] ?? null,
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