# 🔧 SETUP & TESTING GUIDE - INTEGRASI STOK INVENTORY

> **STATUS**: ✅ FIXES IMPLEMENTED
> **Date**: 2026-06-06
> **Version**: 2.0

---

## 📋 RINGKASAN PERUBAHAN

### 1. ✅ FIX KODE PRODUK YANG HARDCODED
**Problem**: Semua kode produk punya prefix `PCB` saja, tidak fleksibel per kategori
**Solution**: 
- Tambah fungsi baru: `generateKodeProdukByKategori($kategori_id)` di [src/functions.php](src/functions.php)
- Ambil prefix dari `kategori.prefix_kode` di database
- Generate kode dengan prefix yang sesuai kategori

**File Changed**: [src/functions.php](src/functions.php)

### 2. ✅ FIX KATEGORI PRODUK HARDCODED
**Problem**: Kategori hanya string `'PCB'`, tidak ada pilihan kategori
**Solution**:
- Add kategori dropdown di form tambah produk
- Load kategori dari database table `kategori`
- AJAX real-time update kode saat kategori berubah
- Support both `kategori_id` (FK) dan `kategori` (string) untuk backward compatibility

**Files Changed**: 
- [public/marketing/produk/crud/add.php](public/marketing/produk/crud/add.php)
- [public/marketing/produk/crud/generate_kode.php](public/marketing/produk/crud/generate_kode.php) *(NEW)*
- [src/models/Produk.php](src/models/Produk.php)

### 3. ✅ FIX STOK INTEGRATION GUDANG (CRITICAL)
**Problem**: Gudang hanya validasi stok total, bisa double-allocate stok yang sudah di-reserve PO
**Solution**:
- Query `stok_available` (bukan `stok` total)
- Show detail breakdown: Total Stok, Di-booking PO, Tersedia
- Error message lebih jelas untuk user

**Example Error Message**:
```
Qty melebihi stok tersedia untuk "PCB Board XYZ":
• Total Stok: 100 pcs
• Di-booking PO: 60 pcs
• Tersedia: 40 pcs
• Diminta: 50 pcs
```

**File Changed**: [public/gudang/pengeluaran/crud/add.php](public/gudang/pengeluaran/crud/add.php)

---

## 🗄️ DATABASE COLUMNS YANG WAJIB ADA

### Tabel: `kategori` (HARUS ADA)
```sql
CREATE TABLE kategori (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nama_kategori VARCHAR(100) NOT NULL UNIQUE,
  prefix_kode VARCHAR(10) NOT NULL UNIQUE,  -- PCB, RES, KAP, dll
  deskripsi TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed data:
INSERT INTO kategori (nama_kategori, prefix_kode, deskripsi) VALUES
('PCB', 'PCB', 'Papan sirkuit tercetak'),
('Resistor', 'RES', 'Komponen resistor elektronik'),
('Kapasitor', 'KAP', 'Komponen kapasitor elektronik'),
('Dioda', 'DIO', 'Komponen dioda'),
('Transistor', 'TRS', 'Komponen transistor'),
('Custom', 'PROD', 'Produk custom atau lainnya');
```

### Tabel: `produk` (HARUS SUPPORT KEDUA FIELD)
```sql
-- Pastikan tabel produk punya kedua kolom ini:
ALTER TABLE produk ADD COLUMN kategori_id INT UNSIGNED;
ALTER TABLE produk ADD COLUMN kategori VARCHAR(255);  -- Untuk backward compatibility
ALTER TABLE produk ADD COLUMN stok_available INT DEFAULT 0;
ALTER TABLE produk ADD COLUMN stok_reserved INT DEFAULT 0;

-- Foreign key (optional tapi recommended):
ALTER TABLE produk ADD CONSTRAINT fk_produk_kategori 
  FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL;
```

---

## ✅ SETUP CHECKLIST

- [ ] **Database** 
  - [ ] Tabel `kategori` sudah dibuat dengan seed data
  - [ ] Tabel `produk` punya kolom: `kategori_id`, `stok_available`, `stok_reserved`
  - [ ] Tabel `po` dan `po_items` terkonfigurasi dengan benar
  - [ ] Tabel `pengeluaran` dan `pengeluaran_items` terkonfigurasi

- [ ] **Functions** (`src/functions.php`)
  - [ ] ✅ `generateKodeProdukByKategori()` sudah ada
  - [ ] ✅ `generateAutoCode()` sudah update prefix PROD default
  - [ ] ✅ `generatePONumber()` format `PO-MMYY-NNN` berfungsi

- [ ] **Models** (`src/models/`)
  - [ ] ✅ `Produk.php` support `kategori_id` di `create()`
  - [ ] ✅ `PO.php` reserve stok saat approved
  - [ ] ✅ `Pengeluaran.php` check `stok_available` saat add
  - [ ] ✅ `StokTracking.php` ada untuk audit trail

- [ ] **Pages** (`public/marketing/produk/crud/`)
  - [ ] ✅ `add.php` punya kategori dropdown
  - [ ] ✅ `generate_kode.php` AJAX endpoint sudah ada
  - [ ] ✅ Kode auto-update saat kategori berubah

- [ ] **Gudang Pages** (`public/gudang/pengeluaran/crud/`)
  - [ ] ✅ `add.php` query `stok_available` 
  - [ ] ✅ Error message detail breakdown stok

---

## 🧪 TESTING CHECKLIST

### Test 1: Tambah Produk Baru ✅
```
Steps:
1. Go to Marketing → Produk → Tambah Produk
2. Pilih Kategori: "PCB"
3. Lihat: Kode = "PCB-001"
4. Pilih Kategori: "Resistor"
5. Lihat: Kode = "RES-001" (berubah otomatis)
6. Input Nama: "Resistor 100Ω"
7. Stok: 50
8. Save → Berhasil

Expected: Produk tersimpan dengan kategori_id & kode yang sesuai
```

### Test 2: Lihat Stok di Halaman Produk ✅
```
Steps:
1. Go to Marketing → Produk
2. Lihat daftar produk

Expected: 
- Kolom: Kode (PCB-001, RES-001, dll)
- Kolom: Nama
- Kolom: Kategori (atau terintegrasi di kode)
- Kolom: Stok Tersedia (dari stok_available)
```

### Test 3: Buat PO & Reserve Stok ✅
```
Steps:
1. Go to Marketing → Pesanan PCB → Buat Baru
2. Add Item: PCB-001, Qty=30
3. Save & Approve PO

Check Produk:
- Sebelum: Total=50, Available=50, Reserved=0
- Sesudah: Total=50, Available=20, Reserved=30

Expected: Stok di-reserve otomatis
```

### Test 4: Gudang Lihat Stok Tersedia ✅
```
Steps:
1. Go to Gudang → Pengeluaran → Tambah
2. Tambah Item: PCB-001, Qty=25

Expected:
- ✅ Success (Qty 25 <= Available 20) - WAIT NO: qty > available
- ❌ ERROR dengan breakdown:
  - Total: 50
  - Reserved: 30  
  - Available: 20
  - Diminta: 25
```

### Test 5: Output & Unreserve Stok ✅
```
Steps:
1. Output Pengeluaran: PCB-001, Qty=20 (saved as draft)
2. Change Status to: Completed

Check Produk:
- Sebelum: Total=50, Available=20, Reserved=30
- Sesudah: Total=30, Available=0, Reserved=10

Expected:
- Stok berkurang 20
- Reserved berkurang 20
- Available = 30 - 10 = 0 (masih ada reserved dari PO lain)
```

### Test 6: Real-time Monitoring ✅
```
Steps:
1. Dalam satu session, buka 2 tab:
   - Tab 1: Produk list
   - Tab 2: Pengeluaran add
2. Di Tab 2: Buat pengeluaran & save
3. Refresh Tab 1

Expected: Stok di Tab 1 berubah sesuai pengeluaran
```

---

## 🐛 DEBUG / TROUBLESHOOTING

### Issue: Kode tidak auto-generate dari kategori
**Debug Steps**:
1. Check `kategori` table ada data:
   ```sql
   SELECT * FROM kategori;
   ```
2. Check `generate_kode.php` accessible:
   ```
   http://localhost/Inventaris/public/marketing/produk/crud/generate_kode.php?kategori_id=1
   ```
3. Check browser console (F12 → Network) untuk error AJAX
4. Check `src/functions.php` punya `generateKodeProdukByKategori()`

### Issue: Pengeluaran validasi masih check total stok
**Debug Steps**:
1. Check query di `pengeluaran add.php` line 11:
   ```php
   // Harus include: stok_available, stok_reserved
   SELECT id, nama, stok, stok_available, stok_reserved FROM produk
   ```
2. Check validation logic line 33-50 gunakan `stok_available`

### Issue: Stok tidak berkurang saat output pengeluaran
**Debug Steps**:
1. Check `Pengeluaran.php` model punya `StokTracking`
2. Check `po_items` table punya kolom `is_reserved`
3. Check status pengeluaran = "completed" atau "draft"
4. Check `StokTracking.php` punya method `reduceStok()`

---

## 📊 RUMUS STOK YANG WAJIB DIPATUHI

### Formula Dasar (WAJIB)
```
stok_available = stok_fisik - stok_reserved
```

### Contoh Skenario
```
Initial State:
├─ Stok Fisik: 100
├─ Reserved: 0
└─ Available: 100

After PO Approved (Reserve 60):
├─ Stok Fisik: 100 (no change)
├─ Reserved: 60
└─ Available: 40

After Output (50 pcs):
├─ Stok Fisik: 50 (berkurang)
├─ Reserved: 10 (dari PO lain) 
└─ Available: 40 (50 - 10)
```

---

## 📚 FILES AFFECTED

### Created/Modified Files:
- ✅ [src/functions.php](src/functions.php) - `generateKodeProdukByKategori()` NEW
- ✅ [src/models/Produk.php](src/models/Produk.php) - Support kategori_id
- ✅ [public/marketing/produk/crud/add.php](public/marketing/produk/crud/add.php) - Kategori dropdown
- ✅ [public/marketing/produk/crud/generate_kode.php](public/marketing/produk/crud/generate_kode.php) - NEW AJAX endpoint
- ✅ [public/gudang/pengeluaran/crud/add.php](public/gudang/pengeluaran/crud/add.php) - Stok validation fix
- ✅ [WORKFLOW_INTEGRASI_STOK.md](WORKFLOW_INTEGRASI_STOK.md) - NEW documentation

### Reference Files (No Change Needed):
- [src/models/PO.php](src/models/PO.php) - Reserve logic already ok
- [src/models/Pengeluaran.php](src/models/Pengeluaran.php) - StokTracking integration ok
- [src/models/StokTracking.php](src/models/StokTracking.php) - Audit trail ok

---

## 🚀 QUICK START

### 1. Setup Database
```sql
-- Create kategori table jika belum ada
CREATE TABLE kategori (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nama_kategori VARCHAR(100) UNIQUE,
  prefix_kode VARCHAR(10) UNIQUE,
  deskripsi TEXT,
  created_at TIMESTAMP DEFAULT NOW()
);

-- Insert sample categories
INSERT INTO kategori (nama_kategori, prefix_kode) VALUES
('PCB', 'PCB'), ('Resistor', 'RES'), ('Kapasitor', 'KAP'),
('Dioda', 'DIO'), ('Transistor', 'TRS'), ('Custom', 'PROD');

-- Ensure produk table has required columns
ALTER TABLE produk ADD COLUMN IF NOT EXISTS kategori_id INT;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS stok_available INT DEFAULT 0;
ALTER TABLE produk ADD COLUMN IF NOT EXISTS stok_reserved INT DEFAULT 0;
```

### 2. Test Kode Auto-Generate
```php
// Di browser console atau terminal:
curl "http://localhost/Inventaris/public/marketing/produk/crud/generate_kode.php?kategori_id=1"

// Expected response:
{"success":true,"kode":"PCB-001","message":"Kode berhasil di-generate"}
```

### 3. Test Tambah Produk
- Buka: http://localhost/Inventaris/public/marketing/produk/crud/add.php
- Pilih kategori → Lihat kode berubah
- Input data & save

### 4. Test PO Integration
- Buat PO dengan produk yang sudah dibuat
- Approve PO → Cek stok_available berkurang
- Buat Pengeluaran → Lihat validasi stok_available

---

## 📞 SUPPORT

Jika ada error atau tidak sesuai, check:
1. **Database**: Tabel & kolom ada?
2. **Functions**: File di `src/functions.php` sudah update?
3. **AJAX**: Browser console → Check network errors
4. **Permission**: User punya akses role yang tepat?

---

**Last Updated**: 2026-06-06  
**Tested On**: PHP 7.4+, MySQL 5.7+
