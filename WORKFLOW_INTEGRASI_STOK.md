# 📊 WORKFLOW INTEGRASI STOK BARANG - LENGKAP

## 🎯 Alur Sistem Inventory Real-time

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SISTEM INVENTORY TERINTEGRASI                         │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────┐
│  1️⃣  PRODUK DITAMBAH        │
│  (Marketing/Admin)          │
└──────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────┐
    │ Kode: PCB-001               │  ← Auto-generate dari kategori
    │ Nama: PCB Board XYZ         │
    │ Kategori: PCB / Resistor    │  ← Pilih dari kategori table
    │ Stok: 100 pcs               │
    │ Stok_reserved: 0            │
    │ Stok_available: 100 ✓       │  ← Formula: stok - stok_reserved
    │                             │
    │ Database: produk table      │
    └─────────────────────────────┘
         │
         │
         ▼
┌──────────────────────────────────────────────┐
│  2️⃣  PESANAN PCB DIBUAT (Marketing)         │
│  Nomor: PO-1126-001 (auto: bulan-tahun)    │
│  Customer: PT ABC                           │
│  Status: Draft → Pending → Approved         │
└──────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │ PO ITEM:                            │
    │ - PCB-001: Qty = 50                 │
    │ - PCB-002: Qty = 30                 │
    │ po_items table                      │
    └─────────────────────────────────────┘
         │
         │ (PO Approved/Reserve Stok)
         ▼
┌──────────────────────────────────────────────┐
│  3️⃣  STOK DI-RESERVE (Automatic)            │
│  Status: Approved → Stok dikurangi           │
└──────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │ UPDATE produk table:                │
    │                                     │
    │ Produk PCB-001:                     │
    │ • stok_reserved: 0 → 50             │
    │ • stok_available: 100 → 50 ✓        │
    │                                     │
    │ Produk PCB-002:                     │
    │ • stok_reserved: 0 → 30             │
    │ • stok_available: 80 → 50 ✓         │
    │                                     │
    │ Formula: stok_available = stok - stok_reserved
    └─────────────────────────────────────┘
         │
         │
         ▼
┌──────────────────────────────────────────────┐
│  4️⃣  WAREHOUSE CEK STOK (Gudang)             │
│  Saat buat pengeluaran, validasi:            │
└──────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │ SEBELUM: Hanya cek stok total       │
    │ ❌ SALAH: WHERE stok >= qty         │
    │                                     │
    │ SESUDAH: Cek stok_available ✓      │
    │ ✅ BENAR: WHERE stok_available >= qty
    │                                     │
    │ Pengeluaran hanya bisa output qty   │
    │ yang tersedia (tidak dibooking)     │
    └─────────────────────────────────────┘
         │
         │ (Output Pengeluaran - Completed)
         ▼
┌──────────────────────────────────────────────┐
│  5️⃣  STOK BERKURANG (Automatic)              │
│  Status: Pengeluaran = Completed             │
└──────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │ UPDATE produk table:                │
    │                                     │
    │ Pengeluaran 50 pcs PCB-001:         │
    │ • stok: 100 → 50                    │
    │ • stok_available: 50 → 0 (jika PO) │
    │ • stok_reserved: 50 → 0 (unreserve)│
    │                                     │
    │ Logic:                              │
    │ 1. Kurangi stok fisik               │
    │ 2. Unreserve dari PO (jika ada)     │
    │ 3. Recalc stok_available            │
    └─────────────────────────────────────┘
         │
         │
         ▼
┌──────────────────────────────────────────────┐
│  6️⃣  REAL-TIME MONITORING                    │
│  Semua user lihat stok terakhir              │
└──────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────┐
    │ Produk Daftar / Stok:               │
    │                                     │
    │ PCB-001: Total=50, Reserved=0 ✓     │
    │ PCB-002: Total=20, Reserved=30 ⚠️   │
    │ (Negatif = over-booked)             │
    │                                     │
    │ UPDATE setiap transaksi             │
    └─────────────────────────────────────┘
```

---

## 📋 TABEL REFERENSI - KODE & KATEGORI

### Kode Produk Format: `PREFIX-NNN`

| Kategori | Kode Prefix | Contoh | Penggunaan |
|----------|-------------|--------|-----------|
| PCB | PCB | PCB-001 | Board PCB standar |
| Resistor | RES | RES-001 | Elektronik resistor |
| Kapasitor | KAP | KAP-001 | Elektronik kapasitor |
| Dioda | DIO | DIO-001 | Elektronik dioda |
| Transistor | TRS | TRS-001 | Elektronik transistor |
| Custom | Custom | PROD-001 | Produk custom |

### Nomor Dokumen Format: `PREFIX-MMYY-NNN`

| Dokumen | Prefix | Contoh | Keterangan |
|---------|--------|--------|-----------|
| Pesanan PCB | PO | PO-1126-001 | Per bulan-tahun |
| Surat Jalan | SJ | SJ-1126-001 | Invoice pengiriman |
| Pengeluaran | PNG | PNG-001 | Keluar dari gudang |
| Penerimaan | PNR | PNR-001 | Masuk ke gudang |
| SPK | SPK | SPK-1126-001 | Surat Perintah Kerja |
| Verifikasi | VRF | VRF-001 | QC/Verifikasi hasil |

---

## 🔄 STOK STATE MACHINE

```
┌─────────────┐
│   PRODUK    │ (awal)
│  CREATED    │
│ Stok: 100   │
│ Reserved: 0 │
│ Available:100
└─────────────┘
      │
      │ PO Dibuat & Approve
      ▼
┌─────────────────────────┐
│   PO APPROVED           │
│ Qty PO: 50              │
│                         │
│ Stok: 100 (tidak ubah)  │
│ Reserved: 50 ↑          │
│ Available: 50 ↓         │
└─────────────────────────┘
      │
      │ Pengeluaran Output (50 pcs)
      ▼
┌──────────────────────────┐
│   AFTER OUTPUT           │
│                          │
│ Stok: 50 ↓ (fisik keluar)│
│ Reserved: 0 ↓ (clear)    │
│ Available: 50 ✓ (50-0)   │
└──────────────────────────┘
```

---

## 🛠️ VALIDASI LEVEL

### Level 1: CREATE PRODUK
- ✅ Kode: Required, auto-generate
- ✅ Nama: Required
- ✅ Kategori: Required, dari kategori table
- ✅ Stok: ≥ 0
- ✅ Stok_available = stok (formula awal)
- ✅ Stok_reserved = 0

### Level 2: CREATE PO
- ✅ Nomor PO: Auto-generate `PO-MMYY-NNN`
- ✅ Customer: Required
- ✅ Items: Minimal 1
- ✅ Per item: `stok_available >= qty` (WAJIB)
- ✅ Saat approve: AUTO RESERVE stok

### Level 3: CREATE PENGELUARAN (Gudang)
- ✅ Nomor: Auto-generate `PNG-NNN`
- ✅ Items: Minimal 1
- ✅ Per item: `stok_available >= qty` (bukan stok total!)
- ✅ Saat complete: AUTO KURANG stok + unreserve

---

## 📝 DATABASE FIELDS

### Produk Table
```sql
kode_produk        VARCHAR(20)    -- Misal: PCB-001
nama               VARCHAR(255)   -- Nama produk
kategori_id        INT UNSIGNED FK kategori.id  -- Kategori
stok               INT            -- Total stok fisik
stok_reserved      INT            -- Di-booking PO
stok_available     INT            -- stok - stok_reserved
stok_min           INT            -- Batas minimum untuk alert
satuan             VARCHAR(20)    -- pcs, meter, dll
harga              DECIMAL        -- Harga jual
status             ENUM(aktif|nonaktif)
```

### PO Table
```sql
nomor_po           VARCHAR(20)    -- Misal: PO-1126-001
tanggal            DATE
customer_id        INT FK customers.id
status             ENUM(draft|pending|approved|rejected|completed)
status_stok        ENUM(unreserved|reserved|partial|completed)
```

### PO_Items Table
```sql
po_id              INT FK po.id
produk_id          INT FK produk.id
qty                INT            -- Qty order
qty_available      INT            -- Qty available (before backorder)
qty_pending        INT            -- Qty pending produksi
harga_satuan       DECIMAL
is_reserved        BOOLEAN        -- TRUE saat PO approved
```

### Pengeluaran Table
```sql
nomor_pengeluaran  VARCHAR(20)
spk_id             INT FK spk.id
tanggal            DATE
status             ENUM(draft|completed|cancelled)
pic                INT FK users.id
```

### Pengeluaran_Items Table
```sql
pengeluaran_id     INT FK pengeluaran.id
produk_id          INT FK produk.id
qty                INT            -- Output qty
```

### Kategori Table
```sql
id                 INT PRIMARY KEY
nama_kategori      VARCHAR(100)   -- PCB, Resistor, dll
prefix_kode        VARCHAR(10)    -- PCB, RES, KAP, dll
deskripsi          TEXT
```

---

## ⚠️ COMMON ISSUES & FIXES

### Issue 1: Kode Produk Hardcoded PCB
```php
// ❌ SEBELUM (functions.php line 34)
'PRODUK' => ['table' => 'produk', 'prefix' => 'PCB', 'field' => 'kode_produk'],

// ✅ SESUDAH
// Ambil kategori_id dari form, query prefix dari kategori table
$kategoriId = $_POST['kategori_id'];
$kategori = $pdo->query("SELECT prefix_kode FROM kategori WHERE id=$kategoriId")->fetch();
$autoKode = generateAutoCode($kategori['prefix_kode'] ?? 'PROD');
```

### Issue 2: Kategori Hardcoded String
```php
// ❌ SEBELUM (produk add.php line 20)
'kategori' => $_POST['kategori'] ?? 'PCB',

// ✅ SESUDAH
'kategori_id' => (int)($_POST['kategori_id'] ?? 0),  // FK to kategori table
```

### Issue 3: Gudang Validasi Stok Total
```php
// ❌ SEBELUM (pengeluaran add.php line 34)
if ((int)$item['qty'] > (int)$pr['stok']) {
    $errors[] = 'Qty melebihi stok...';
}

// ✅ SESUDAH
// Query stok_available dari produk
$pr = $pdo->query("SELECT stok_available FROM produk WHERE id=".$item['produk_id'])->fetch();
if ((int)$item['qty'] > (int)($pr['stok_available'] ?? 0)) {
    $errors[] = 'Qty melebihi stok tersedia...';
}
```

---

## 📊 CONTOH SKENARIO REAL

### Skenario 1: Normal Order → Production → Delivery

```
Day 1 - Produk masuk ke sistem:
├─ PCB-001: Stok=100, Reserved=0, Available=100

Day 2 - Customer buat PO:
├─ PO-1126-001: Pesan 60 pcs PCB-001
├─ Qty check: Available(100) >= Qty(60) ✓ OK
├─ Status: Draft (belum reserve)

Day 3 - Manager approve PO:
├─ PO-1126-001: Status = Approved
├─ AUTO RESERVE:
│  ├─ PCB-001: Reserved=0→60, Available=100→40
├─ Log: "Reserve 60 pcs untuk PO-1126-001"

Day 4 - Produksi selesai:
├─ Warehouse siap deliver (SPK)
├─ Create Pengeluaran: 60 pcs PCB-001
├─ Qty check: Available(40) >= Qty(60) ❌ ERROR
│  └─ Hanya 40 pcs tersedia (60 di-reserve PO lain)

Day 4 (fix) - Output hanya 40 pcs:
├─ Pengeluaran PNG-001: 40 pcs PCB-001
├─ Status: Completed
├─ AUTO REDUCE & UNRESERVE:
│  ├─ PCB-001: Stok=100→60, Reserved=60→20, Available=40→40
├─ Sisa PO-1126-001: Partial delivery (40/60)
```

### Skenario 2: Over-booked Stok

```
Kondisi:
├─ PCB-002: Stok=30, Reserved=0, Available=30

Kasus over-book:
├─ PO-1126-001: Pesan 20 pcs (APPROVED)
│  └─ PCB-002: Reserved=20, Available=10
├─ PO-1126-002: Pesan 20 pcs (APPROVED)
│  └─ ❌ FAILED - Only 10 available! Error: "Qty melebihi stok"

Solusi:
├─ PO-1126-002: Set qty=10 saja (backorder)
│  └─ PCB-002: Reserved=30, Available=0
├─ Admin atau PO team harus restock
```

---

## 🔍 TESTING CHECKLIST

- [ ] **Test 1**: Buat produk → Kode auto-generate dengan prefix kategori
- [ ] **Test 2**: Lihat produk di PO form → Tampil stok_available (bukan total)
- [ ] **Test 3**: Buat PO dengan qty > available → ERROR & msg jelas
- [ ] **Test 4**: Approve PO → Stok_available berkurang otomatis
- [ ] **Test 5**: Buat pengeluaran dengan qty > available → ERROR
- [ ] **Test 6**: Complete pengeluaran → Stok & reserved otomatis update
- [ ] **Test 7**: Real-time report → Lihat reserved & available per produk
- [ ] **Test 8**: Over-booked scenario → Validasi prevent double-allocate

---

## 📚 DOKUMENTASI KODE

Lihat file:
- [src/functions.php](src/functions.php) - `generateAutoCode()` & `generatePONumber()`
- [src/models/Produk.php](src/models/Produk.php) - Produk model + formula
- [src/models/PO.php](src/models/PO.php) - PO model + reserve logic
- [src/models/Pengeluaran.php](src/models/Pengeluaran.php) - Pengeluaran + stok reduction
- [src/models/StokTracking.php](src/models/StokTracking.php) - Audit trail

---

**Last Updated**: 2026-06-06  
**Version**: 2.0 (Integrated Stok System)
