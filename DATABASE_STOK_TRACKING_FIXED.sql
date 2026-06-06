-- =============================================================================
-- INVENTARIS STOK TRACKING - Database Structure (CORRECTED)
-- Integrasi Realtime untuk PO → SPK → Verifikasi → Pengeluaran
-- =============================================================================

-- Update stok_available = stok - stok_reserved untuk data existing
UPDATE produk SET stok_available = (stok - COALESCE(stok_reserved, 0));

-- 1. Tambah tracking ke tabel po_items (untuk reserve info)
ALTER TABLE po_items ADD COLUMN reserved_qty INT DEFAULT 0 AFTER qty;
ALTER TABLE po_items ADD COLUMN is_reserved ENUM('no','yes') DEFAULT 'no' AFTER reserved_qty;

-- 2. Tambah tracking ke tabel spk_items
ALTER TABLE spk_items ADD COLUMN stok_available INT DEFAULT 0 AFTER stok_gudang;
ALTER TABLE spk_items ADD COLUMN status_produksi ENUM('pending','on_progress','completed','failed') DEFAULT 'pending' AFTER qty_outstanding;

-- 3. Tracking ke tabel pengeluaran
ALTER TABLE pengeluaran ADD COLUMN stok_before INT DEFAULT 0 AFTER status;
ALTER TABLE pengeluaran ADD COLUMN stok_after INT DEFAULT 0 AFTER stok_before;

-- 4. Tambah ke tabel po
ALTER TABLE po ADD COLUMN status_stok ENUM('draft','reserved','partial','ready','completed') DEFAULT 'draft' AFTER status;
ALTER TABLE po ADD COLUMN approval_date DATETIME AFTER status_stok;

-- 5. Tabel Stok Log (Audit Trail)
CREATE TABLE stok_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produk_id INT NOT NULL,
    tipe_transaksi ENUM('po_reserve','po_unreserve','verifikasi_add','pengeluaran_sub','adjustment') DEFAULT 'adjustment',
    qty_change INT,
    stok_before INT,
    stok_after INT,
    stok_reserved_before INT,
    stok_reserved_after INT,
    reference_type VARCHAR(50),
    reference_id INT,
    keterangan TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produk_id) REFERENCES produk(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX (produk_id),
    INDEX (created_at),
    INDEX (tipe_transaksi)
);

-- 6. View untuk Dashboard Real-Time Stok
CREATE OR REPLACE VIEW v_stok_realtime AS
SELECT 
    p.id,
    p.kode_produk,
    p.nama,
    p.kategori,
    p.stok,
    p.stok_reserved,
    p.stok_available,
    CASE 
        WHEN p.stok_available <= 0 THEN 'OUT_OF_STOCK'
        WHEN p.stok_available < 50 THEN 'LOW_STOCK'
        ELSE 'OK'
    END AS status_stok,
    COALESCE(SUM(CASE WHEN poi.is_reserved = 'yes' THEN poi.qty ELSE 0 END), 0) AS on_order,
    p.updated_at
FROM produk p
LEFT JOIN po_items poi ON p.id = poi.produk_id
GROUP BY p.id;

-- 7. View untuk Monitoring PO dengan stok detail
CREATE OR REPLACE VIEW v_po_dengan_stok AS
SELECT 
    po.id,
    po.nomor_po,
    po.tanggal,
    po.status,
    po.status_stok,
    c.nama AS customer,
    GROUP_CONCAT(
        CONCAT(
            poi.nama_material, ' (', poi.qty, 'pcs) - ',
            'Avail: ', COALESCE(p.stok_available, 0)
        ) 
        SEPARATOR ' | '
    ) AS items_detail,
    SUM(poi.qty) AS total_qty,
    COUNT(CASE WHEN COALESCE(p.stok_available, 0) >= poi.qty THEN 1 END) AS items_ready,
    COUNT(poi.id) AS total_items
FROM po
LEFT JOIN po_items poi ON po.id = poi.po_id
LEFT JOIN produk p ON poi.produk_id = p.id
LEFT JOIN customers c ON po.customer_id = c.id
GROUP BY po.id;

-- Done!
