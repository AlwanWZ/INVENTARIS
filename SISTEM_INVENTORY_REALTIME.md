# 📊 Sistem Inventory Realtime - Dokumentasi

## 🎯 Tujuan Sistem

Membuat sistem inventory yang **saling terkait, realtime, dan otomatis** dengan alur:

```
Customer Order (PO) 
  → Reserve Stok
    → Produksi (SPK)
      → Barang Masuk (Verifikasi)
        → Stok Nambah
          → Siap Kirim
            → Pengeluaran (Surat Jalan)
              → Stok Berkurang
```

---

## 🔄 Alur Inventory Terintegrasi

### 1️⃣ **CUSTOMER ORDER (PO) - Reserve Stok**

**Skenario:**
- Customer pesan 145 pcs
- Stok tersedia hanya 130 pcs
- Sistem RESERVE stok otomatis

**Proses:**
```
PO Status: Draft → Approved (saat approve)
  ↓
StokTracking::reserveStok(produk_id, qty)
  ├─ Check: stok_available >= qty?
  ├─ JA: stok_reserved += qty, stok_available -= qty
  ├─ TIDAK: Lempar error "Stok tidak cukup"
  └─ Log: Audit trail ke tabel stok_log
```

**Database Changes:**
```sql
-- Kolom baru di tabel produk:
stok = 130           -- Total stok fisik
stok_reserved = 145  -- Stok yang di-reserve untuk PO
stok_available = -15 -- Stok siap jual (130 - 145 = -15) ❌ MINUS!

-- Kolom di tabel po_items:
is_reserved = 'yes'  -- Flag item sudah di-reserve
```

**Code:**
```php
// Di PO CRUD approve atau update status:
$result = PO::reserveStok($po_id, $user_id);
if ($result['success']) {
    // Redirect dengan sukses message
} else {
    // Tampilkan error: stok tidak cukup
}
```

---

### 2️⃣ **PRODUKSI SELESAI - Barang Masuk (Verifikasi)**

**Skenario:**
- Produksi selesai 130 pcs
- Barang masuk gudang
- Verifikasi status: Draft → Verified

**Proses:**
```
Verifikasi Status: Draft → Verified (saat approve)
  ↓
FOR EACH item:
  StokTracking::addStok(produk_id, qty_ok)
    ├─ stok += qty_ok (130 + 130 = 260)
    ├─ stok_available += qty_ok (tergantung reserve)
    └─ Log: verifikasi_add
```

**Database Changes:**
```sql
-- Sebelum verifikasi:
stok = 130, stok_reserved = 145, stok_available = -15

-- Setelah verifikasi 130 pcs:
stok = 260, stok_reserved = 145, stok_available = 115
```

**Result:**
- Total stok sekarang 260 pcs
- Reserve tetap 145 (belum dikirim)
- Available untuk jual: 260 - 145 = 115 pcs ✅

---

### 3️⃣ **SIAP KIRIM - Pengeluaran (Surat Jalan)**

**Skenario:**
- PO customer siap dikirim (145 pcs)
- Buat pengeluaran + surat jalan
- Pengeluaran status: Draft → Completed

**Proses:**
```
Pengeluaran Status: Draft → Completed (saat finalize)
  ↓
FOR EACH item:
  StokTracking::reduceStok(produk_id, qty)
    ├─ Check: stok >= qty?
    ├─ stok -= qty (260 - 145 = 115)
    ├─ stok_available -= qty
    ├─ stok_reserved -= qty (unreserve otomatis?)
    └─ Log: pengeluaran_sub
```

**Database Changes:**
```sql
-- Sebelum pengeluaran:
stok = 260, stok_reserved = 145, stok_available = 115

-- Setelah pengeluaran 145 pcs shipped:
stok = 115, stok_reserved = 0, stok_available = 115
```

**Result:**
- Stok berkurang jadi 115 pcs
- Reserve cleared (customer sudah dapat barangnya)
- Available untuk customer lain: 115 pcs ✅

---

## 📋 Tabel-Tabel Database Baru/Update

### 1. Update `produk` table
```sql
ALTER TABLE produk ADD COLUMN stok_reserved INT DEFAULT 0;
ALTER TABLE produk ADD COLUMN stok_available INT DEFAULT 0;
ALTER TABLE produk ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Formula: stok_available = stok - stok_reserved
UPDATE produk SET stok_available = (stok - COALESCE(stok_reserved, 0));
```

### 2. Update `po_items` table
```sql
ALTER TABLE po_items ADD COLUMN reserved_qty INT DEFAULT 0;
ALTER TABLE po_items ADD COLUMN is_reserved ENUM('no','yes') DEFAULT 'no';
```

### 3. Update `po` table
```sql
ALTER TABLE po ADD COLUMN status_stok ENUM('draft','reserved','partial','ready','completed') DEFAULT 'draft';
ALTER TABLE po ADD COLUMN approval_date DATETIME;
```

### 4. Tabel `stok_log` (Audit Trail)
```sql
CREATE TABLE stok_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produk_id INT,
    tipe_transaksi ENUM('po_reserve','po_unreserve','verifikasi_add','pengeluaran_sub','adjustment'),
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
    INDEX (produk_id, created_at)
);
```

---

## 🔌 Class: StokTracking

Terletak di: `src/models/StokTracking.php`

**Method Utama:**

```php
class StokTracking {
    
    // Reserve stok saat PO approve
    public function reserveStok($produk_id, $qty, $reference_type, $reference_id, $created_by, $keterangan)
    
    // Unreserve stok saat PO cancel
    public function unreserveStok($produk_id, $qty, $reference_type, $reference_id, $created_by, $keterangan)
    
    // Nambah stok saat barang masuk
    public function addStok($produk_id, $qty, $reference_type, $reference_id, $created_by, $keterangan)
    
    // Kurang stok saat shipment
    public function reduceStok($produk_id, $qty, $reference_type, $reference_id, $created_by, $keterangan)
    
    // Koreksi stok manual
    public function adjustmentStok($produk_id, $qty_change, $keterangan, $created_by)
    
    // Get stok realtime
    public function getStokRealtime($produk_id)
    
    // Get audit trail
    public function getStokLog($produk_id, $limit = 50)
}
```

---

## 📌 Implementasi di CRUD Pages

### PO - Approve Order (Reserve Stok)

**File:** `public/marketing/po/crud/edit.php` atau perlu halaman `approve.php` baru

```php
<?php
require_once '../../../src/models/PO.php';

if ($_POST['action'] === 'approve') {
    $po_id = (int)$_POST['po_id'];
    
    $result = PO::reserveStok($po_id, $_SESSION['user']['id']);
    
    if ($result['success']) {
        header('Location: index.php?msg=po_approved');
    } else {
        // Tampilkan error: stok tidak cukup
        header('Location: detail.php?id=' . $po_id . '&error=' . urlencode($result['message']));
    }
}
?>
```

### Verifikasi - Approve Penerimaan (Add Stok)

**File:** `public/gudang/verif/finish-good/crud/edit.php`

```php
<?php
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Verifikasi.php';

$verifikasiModel = new Verifikasi($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'approve') {
    try {
        $data = [
            'id' => $_POST['verif_id'],
            'status' => 'verified',
            'pic' => $_SESSION['user']['id']
        ];
        
        $verifikasiModel->update($data);
        // StokTracking dipanggil otomatis di model
        
        header('Location: index.php?msg=verified');
    } catch (Exception $e) {
        header('Location: detail.php?id=' . $data['id'] . '&error=' . urlencode($e->getMessage()));
    }
}
?>
```

### Pengeluaran - Complete Shipment (Reduce Stok)

**File:** `public/gudang/pengeluaran/crud/edit.php`

```php
<?php
require_once '../../../../../src/config.php';
require_once '../../../../../src/models/Pengeluaran.php';

$pengeluaranModel = new Pengeluaran($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'complete') {
    try {
        $id = $_POST['pengeluaran_id'];
        $data = [
            'status' => 'completed'
            // ... other data
        ];
        
        $pengeluaranModel->update($id, $data, $_POST['items']);
        // StokTracking dipanggil otomatis di model
        
        header('Location: index.php?msg=completed');
    } catch (Exception $e) {
        header('Location: detail.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    }
}
?>
```

---

## 📊 View Dashboard Realtime

**View:** `v_stok_realtime`

```sql
CREATE VIEW v_stok_realtime AS
SELECT 
    p.id,
    p.kode_produk,
    p.nama,
    p.stok,
    p.stok_reserved,
    p.stok_available,
    CASE 
        WHEN p.stok_available <= 0 THEN 'OUT_OF_STOCK'
        WHEN p.stok_available < 50 THEN 'LOW_STOCK'
        ELSE 'OK'
    END AS status,
    COALESCE(SUM(CASE WHEN poi.is_reserved = 'yes' THEN poi.qty ELSE 0 END), 0) AS on_order,
    p.updated_at
FROM produk p
LEFT JOIN po_items poi ON p.id = poi.produk_id
GROUP BY p.id;
```

**Display di Dashboard:**
```
Produk: PCB Board v3
├─ Total Stok: 260 pcs
├─ On Order (Reserved): 145 pcs  ⚠️
├─ Available: 115 pcs ✅
├─ Status: OK
└─ Last Updated: 2026-06-04 14:30:45
```

---

## 🔔 Alert & Warning

**Stok Tidak Cukup:**
```
❌ ERROR: Stok tidak cukup untuk po_reserve
   Dibutuhkan: 145 pcs
   Tersedia: 130 pcs
   Kurang: 15 pcs
```

**Low Stock:**
```
⚠️ WARNING: Stok hampir habis
   Produk: PCB Board v3
   Stok: 30 pcs (< 50 pcs threshold)
   Segera order ke produksi!
```

**Out of Stock:**
```
🚫 CRITICAL: Stok habis
   Produk: PCB Board v2
   Tidak bisa memenuhi PO baru
```

---

## 🧪 Test Scenario

### Skenario Real: Customer Order 145 pcs, Stok 130 pcs

**Step 1: Buat PO (Draft)**
```
PO Created (nomor_po = "PO-2026-001")
├─ Item: PCB Board v3 × 145 pcs
├─ Status: draft
└─ Stok: 260 → 260 (belum di-reserve)
```

**Step 2: Approve PO → Reserve Stok**
```
PO Approve → PO::reserveStok()
├─ Check: stok_available (130) >= qty (145)? NO
├─ Error: "Stok tidak cukup. Tersedia: 130, Dibutuhkan: 145, Kurang: 15"
└─ PO tetap draft, tidak bisa approved
```

**Step 3: Produksi Selesai 130 pcs**
```
Verifikasi Received (130 pcs OK)
├─ Status changed: draft → verified
├─ addStok(produk_id, 130)
└─ Stok: 130 → 260 pcs ✅
```

**Step 4: Approve PO (sudah ada stok)**
```
PO Approve → PO::reserveStok()
├─ Check: stok_available (260) >= qty (145)? YES
├─ Reserve: 
│  ├─ stok_reserved: 0 → 145
│  └─ stok_available: 260 → 115
├─ Log: po_reserve
└─ PO Status: draft → approved ✅
```

**Step 5: Create SPK dari PO**
```
SPK Created dari PO
├─ Auto-copy items dari PO
├─ SPK Items: PCB Board v3 × 145 pcs
└─ Stok: 260 (stok_reserved: 145, stok_available: 115)
```

**Step 6: Produksi Mulai → SPK on_progress**
```
SPK Status: draft → on_progress
└─ Stok tetap: 260 (masih reserved)
```

**Step 7: Create Pengeluaran + Surat Jalan**
```
Pengeluaran Created (draft)
├─ Item: PCB Board v3 × 145 pcs
├─ Status: draft
└─ Stok: 260 (tidak berkurang sampai completed)
```

**Step 8: Finalize Pengeluaran → Shipment**
```
Pengeluaran Complete → reduceStok()
├─ Check: stok (260) >= qty (145)? YES
├─ Kurang stok:
│  ├─ stok: 260 → 115
│  ├─ stok_reserved: 145 → 0
│  └─ stok_available: 115 → 115
├─ Log: pengeluaran_sub
└─ Surat Jalan: generated ✅
```

**Final Result:**
```
PCB Board v3
├─ Stok Awal: 130 pcs
├─ +Produksi: 130 pcs
├─ = Total: 260 pcs
├─ -Customer Order: 145 pcs
├─ = Stok Akhir: 115 pcs ✅
└─ Audit Log: 5 transaksi tercatat
```

---

## 📝 SQL untuk Migrasi Database

Jalankan script ini di database:

```sql
-- File: DATABASE_STOK_TRACKING.sql
-- Buka file ini dan run di MySQL/Database tool
```

---

## ⚠️ Important Notes

1. **Backup Database dulu!** Sebelum run migration script
2. **Semua transaksi stok tercatat** di tabel `stok_log` untuk audit trail
3. **Stok bisa minus** saat over-order, tapi sistem akan error saat shipment
4. **Reserve automatic unreserve** jika PO dibatalkan
5. **Rollback support** jika status pengeluaran diubah dari completed ke draft

---

## 🔗 File yang Berubah

- ✅ `src/models/StokTracking.php` (BARU)
- ✅ `src/models/PO.php` (UPDATED - add reserve/unreserve methods)
- ✅ `src/models/Verifikasi.php` (UPDATED - use StokTracking)
- ✅ `src/models/Pengeluaran.php` (UPDATED - use StokTracking)
- 📝 `DATABASE_STOK_TRACKING.sql` (MIGRATION SCRIPT)

---

## 🎯 Next Steps

1. ✅ Backup database
2. ✅ Run `DATABASE_STOK_TRACKING.sql`
3. ✅ Test semua scenario di atas
4. ✅ Update UI untuk show stok realtime dengan status badge
5. ✅ Add notifications saat stok kurang/habis
6. ✅ Train user tentang alur inventory baru

---

**Created:** 2026-06-04
**Version:** 1.0
**Status:** Ready for Testing
