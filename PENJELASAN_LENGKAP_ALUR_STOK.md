# 📊 PENJELASAN ALUR INTEGRASI STOK - UNTUK USER

> Dokumen ini menjelaskan **ARTI KODE**, **ALUR SISTEM**, dan **CARA KERJA INTEGRASI STOK**

---

## 🎯 BAGIAN 1: ARTI KODE & NOMOR DOKUMEN

### A. Kode Produk (Format: `PREFIX-NNN`)

Kode produk adalah **identitas unik** untuk setiap barang di gudang.

#### Format Breakdown:
```
Contoh: PCB-001 | RES-042 | KAP-128
         ↓      ↓       ↓
        PREFIX SEPARATOR  SEQUENCE
```

#### Penjelasan Tiap Bagian:

| Bagian | Arti | Contoh |
|--------|------|--------|
| **PREFIX** | Singkatan kategori produk | PCB = Papan Sirkuit, RES = Resistor, KAP = Kapasitor |
| **Separator** | Pemisah | `-` (dash/minus) |
| **SEQUENCE** | Nomor urut per kategori | 001, 002, 042, dst |

#### Contoh Lengkap:

| Kode | Kategori | Arti |
|------|----------|------|
| **PCB-001** | PCB | Papan Sirkuit urutan ke-1 |
| **PCB-002** | PCB | Papan Sirkuit urutan ke-2 |
| **RES-001** | Resistor | Resistor urutan ke-1 |
| **KAP-042** | Kapasitor | Kapasitor urutan ke-42 |
| **DIO-128** | Dioda | Dioda urutan ke-128 |
| **PROD-001** | Custom | Produk custom urutan ke-1 |

**KEY POINT**: Setiap kategori punya prefix berbeda, jadi mudah identify jenis barang hanya dari kode!

---

### B. Nomor Pesanan (PO) - Format: `PO-MMYY-NNN`

Nomor pesanan adalah **invoice** dari customer yang mau beli.

#### Format Breakdown:
```
Contoh: PO-1126-001  |  PO-1201-042  |  PO-0226-010
         ↓   ↓  ↓      ↓   ↓   ↓       ↓   ↓   ↓
        PREFIX BULAN URUTAN  TAHUN URUTAN  BULAN URUTAN
```

#### Penjelasan:

| Bagian | Arti | Contoh |
|--------|------|--------|
| **PO** | Prefix (Purchase Order) | Selalu `PO` |
| **MM** | Bulan | 01=Jan, 11=Nov, 12=Des |
| **YY** | Tahun (2 digit) | 26=2026, 27=2027 |
| **NNN** | Nomor urut per bulan-tahun | 001, 002, 010, dst |

#### Contoh Lengkap:

| Nomor PO | Arti | Waktu |
|----------|------|-------|
| **PO-1126-001** | Pesanan ke-1 November 2026 | Nov 2026 |
| **PO-1126-002** | Pesanan ke-2 November 2026 | Nov 2026 |
| **PO-1201-001** | Pesanan ke-1 Desember 2026 | Des 2026 |
| **PO-0226-042** | Pesanan ke-42 Februari 2026 | Feb 2026 |

**KEY POINT**: Dari nomor PO, bisa langsung tahu kapan pesanan dibuat! Berguna untuk sorting & tracking.

---

### C. Nomor Dokumen Lainnya

| Dokumen | Format | Arti | Contoh |
|---------|--------|------|--------|
| **SPK** | `SPK-MMYY-NNN` | Surat Perintah Kerja (Produksi) | SPK-1126-001 |
| **Surat Jalan** | `SJ-MMYY-NNN` | Invoice pengiriman | SJ-1126-005 |
| **Pengeluaran** | `PNG-NNN` | Barang keluar gudang | PNG-001, PNG-042 |
| **Penerimaan** | `PNR-NNN` | Barang masuk gudang | PNR-001 |
| **Verifikasi** | `VRF-NNN` | QC/Check kualitas | VRF-001 |

---

## 🔄 BAGIAN 2: ALUR INTEGRASI STOK DARI A-Z

### Skenario: Customer Memesan PCB, Warehouse Kirim, Stok Berkurang

```
╔════════════════════════════════════════════════════════════════════════════╗
║                    ALUR INTEGRASI STOK LENGKAP                             ║
╚════════════════════════════════════════════════════════════════════════════╝

STEP 1: PRODUK DITAMBAHKAN KE SISTEM
─────────────────────────────────────
Admin/Marketing buka: Produk → Tambah Produk

Form Input:
┌─ Kategori: PCB ─────────→ Auto-generate Kode: PCB-001
├─ Nama: "PCB Board XYZ"
├─ Stok: 100 pcs
├─ Harga: Rp 150.000
└─ Status: Aktif

Database State (PRODUK TABLE):
┌────────────────────────────┐
│ id=1, kode_produk=PCB-001  │
│ nama=PCB Board XYZ         │
│ kategori_id=1 (PCB)        │
│ stok=100 (Total)           │  ← ASLI dari input
│ stok_reserved=0 (Di-booking)
│ stok_available=100 ✓ (Tersedia)
│ Formula: 100 - 0 = 100     │
└────────────────────────────┘

KEY: stok_available = stok - stok_reserved


STEP 2: CUSTOMER BUAT PESANAN (PO)
──────────────────────────────────
Marketing buka: Pesanan → Tambah Pesanan

Form Input:
┌─ Nomor: PO-1126-001 (auto-generate)
├─ Customer: PT ABC
├─ Item 1: PCB-001, Qty=60, Harga=150.000
└─ Status: Draft (belum terproses)

Database State:
┌──────────────────┐   ┌──────────────────────┐
│   PO TABLE       │   │  PO_ITEMS TABLE      │
├──────────────────┤   ├──────────────────────┤
│ nomor_po=PO-1126 │   │ po_id=1              │
│ customer=PT ABC  │   │ produk_id=1          │
│ status=Draft     │   │ qty=60 (order qty)   │
│ tanggal=6/6/26   │   │ qty_available=60     │
└──────────────────┘   │ is_reserved=false    │
                       └──────────────────────┘


STEP 3: MANAGER APPROVE PESANAN
──────────────────────────────
Manager buka: Pesanan → Detail → Approve Button

Action: APPROVE
└─ Status: Draft → Approved

⚡ TRIGGERED EVENT: AUTO RESERVE STOK ⚡

Database Update:
┌─────────────────────────────────┐
│ PO-1126-001: Status = Approved  │
│                                 │
│ AUTOMATIC TRIGGER:              │
│ UpdateProduk(PCB-001):          │
│ • stok_reserved: 0 → 60 ↑       │
│ • stok_available: 100 → 40 ↓    │
│ Formula: 100 - 60 = 40          │
└─────────────────────────────────┘

Result:
┌─────────────────────────┐
│ PCB-001 Status Sekarang │
├─────────────────────────┤
│ Total Stok: 100 pcs     │
│ Reserved (PO): 60 pcs   │
│ Available: 40 pcs ✓     │
└─────────────────────────┘

KEY: Stok di-reserve otomatis untuk PO yang diapprove


STEP 4: WAREHOUSE SIAP KIRIM
────────────────────────────
Warehouse buka: Pengeluaran → Tambah Pengeluaran

Form Input:
┌─ Nomor: PNG-001 (auto-generate)
├─ Produk: PCB-001
├─ Qty: 50 pcs
└─ Status: Draft

✅ VALIDASI PENTING:

Saat warehouse input qty, sistem check:
┌──────────────────────────────────────┐
│ CEK: Available (50 pcs) >= Qty (50)? │
├──────────────────────────────────────┤
│ ✓ YES → Bisa di-output                │
│ ✗ NO → ERROR dengan breakdown detail │
└──────────────────────────────────────┘

Detail Breakdown yang ditampilkan jika ERROR:
┌────────────────────────────────────────┐
│ ERROR: Qty melebihi stok tersedia      │
│ Untuk: PCB Board XYZ                  │
│                                        │
│ • Total Stok: 100 pcs (fisik gudang)  │
│ • Di-booking PO: 60 pcs (PO lain)     │
│ • Tersedia: 40 pcs ← ONLY THIS        │
│ • Diminta: 50 pcs ← TOO MUCH          │
└────────────────────────────────────────┘

KEY: Gudang TIDAK BISA output stok yang sudah di-reserve!


STEP 5: OUTPUT PENGELUARAN (SELESAI)
────────────────────────────────────
Warehouse ubah: Status = Draft → Completed

⚡ TRIGGERED EVENT: KURANGI STOK ⚡

Database Update:
┌─────────────────────────────────┐
│ PNG-001: Status = Completed     │
│                                 │
│ AUTOMATIC TRIGGER:              │
│ UpdateProduk(PCB-001):          │
│ • stok: 100 → 50 ↓ (output 50)  │
│ • stok_reserved: 60 → 10 ↓      │
│   (unreserve 50 dari PO-1126)   │
│ • stok_available: 40 → 40 ✓     │
│   Formula: 50 - 10 = 40         │
└─────────────────────────────────┘

Result Akhir:
┌───────────────────────────┐
│ PCB-001 Status AKHIR      │
├───────────────────────────┤
│ Total Stok: 50 pcs        │
│ Reserved: 10 pcs (PO lain)│
│ Available: 40 pcs ✓       │
└───────────────────────────┘

KEY: Stok berkurang, reserved juga berkurang (unreserve)
```

---

## 📊 BAGIAN 3: VISUALISASI STATE PRODUK SEPANJANG WAKTU

### Product Journey: PCB-001

```
┌──────────────────────────────────────────────────────────────────┐
│                   PERJALANAN PRODUK PCB-001                       │
└──────────────────────────────────────────────────────────────────┘

Timeline:
─────────

[06 June 2026, 10:00] CREATED
   Stok=100 | Reserved=0 | Available=100
   Status: Baru masuk sistem


[06 June 2026, 11:00] PO DIBUAT (PO-1126-001, Qty=60)
   Stok=100 | Reserved=0 | Available=100
   Status: Draft - belum ada efek


[06 June 2026, 14:00] PO APPROVED
   Stok=100 | Reserved=60 | Available=40 ↓
   Status: Siap produksi


[06 June 2026, 16:00] OUTPUT PENGELUARAN (PNG-001, Qty=50)
   Stok=50 ↓ | Reserved=10 ↓ | Available=40 ✓
   Status: Sudah dikirim ke customer


[06 June 2026, 16:30] SELESAI
   Stok=50 | Reserved=10 | Available=40
   Catatan: Masih ada 50 pcs untuk PO lain yang belum output
```

---

## ⚠️ BAGIAN 4: SKENARIO PROBLEM & SOLUSI

### Skenario A: Over-Booking (MENCEGAH DOUBLE ALLOCATE)

```
SITUATION:
──────────
PCB-002 (Stok Terbatas):
• Total: 30 pcs
• PO-1126-001: Pesan 20 pcs → Reserved=20, Available=10
• PO-1126-002: Pesan 20 pcs → ???

Step 1: PO-1126-002 Masuk
   Qty Check: Available(10) >= Qty(20)?
   Result: ❌ NO → REJECTED!
   Error: "Hanya 10 pcs tersedia, Anda pesan 20 pcs"

Step 2: Admin Adjust
   Option 1: Kurangi qty PO-1126-002 jadi 10 pcs (partial)
   Option 2: Tunggu restock lebih dulu

Result: TIDAK BISA DOUBLE ALLOCATE! ✓
```

### Skenario B: Stok Berkurang Unexpected

```
PROBLEM:
────────
Warehouse bilang stok PCB-001 tinggal 20 pcs, 
padahal tadi 40 pcs?

DEBUGGING:
──────────
Check di system:
• Total Stok: 100 pcs ← Physical (unchanged)
• Reserved: 80 pcs ← Di-booking PO baru
• Available: 20 pcs ← Yang tersisa

Kesimpulan: Bukan stok berkurang, tapi di-reserve PO baru!
Solution: Cek PO mana yang baru di-approve
```

### Skenario C: Stok Negatif (SHOULD NOT HAPPEN)

```
SCENARIO:
─────────
Somehow stok = -10?

ROOT CAUSE:
───────────
Validasi di pengeluaran tidak work dengan benar

FIX:
────
Pastikan pengeluaran add.php check stok_available:
✓ Stok_available >= Qty diminta

Jika ada negative stok, lakukan:
UPDATE produk SET stok_available = stok - stok_reserved
WHERE stok_available < 0;
```

---

## 💾 BAGIAN 5: DATABASE FIELDS YANG PENTING

### Produk Table
```sql
┌──────────────────────────────────────────────┐
│            KOLOM STOK (WAJIB ADA)            │
├──────────────────────────────────────────────┤
│ stok                (INT) - Stok fisik total │
│ stok_reserved       (INT) - Di-booking PO    │
│ stok_available      (INT) - Tersedia        │
│ stok_min            (INT) - Batas min alert  │
│ kategori_id         (INT FK) - Kategori     │
│ kategori            (VARCHAR) - Backup      │
└──────────────────────────────────────────────┘

FORMULA WAJIB (Always):
─────────────────────
stok_available = stok - stok_reserved

Contoh:
stok_available = 100 - 60 = 40
```

### PO Table
```sql
┌──────────────────────────────────────────────┐
│         KOLOM PESANAN (WAJIB ADA)           │
├──────────────────────────────────────────────┤
│ nomor_po            (VARCHAR) - PO-1126-001 │
│ customer_id         (INT FK) - Customer     │
│ status              (ENUM) - draft/approved │
│ status_stok         (ENUM) - reserved/ok   │
│ tanggal             (DATE) - Tanggal pesan  │
└──────────────────────────────────────────────┘

Status PO Flow:
Draft → Pending Review → Approved → Completed
         ↓
      (Reserve stok saat approve)
```

### Kategori Table (NEW)
```sql
┌──────────────────────────────────────────────┐
│         KATEGORI (BARU, PENTING!)           │
├──────────────────────────────────────────────┤
│ id                  (INT PRIMARY KEY)        │
│ nama_kategori       (VARCHAR) - "PCB"       │
│ prefix_kode         (VARCHAR) - "PCB"       │
│ deskripsi           (TEXT) - Penjelasan     │
└──────────────────────────────────────────────┘

Data Sample:
(1, 'PCB', 'PCB', 'Papan Sirkuit Tercetak')
(2, 'Resistor', 'RES', 'Komponen Resistor')
(3, 'Kapasitor', 'KAP', 'Komponen Kapasitor')
```

---

## 🔗 BAGIAN 6: HUBUNGAN ANTAR TABLE

```
┌─────────────────────────────────────────────────────────────┐
│             DIAGRAM RELASI DATABASE                          │
└─────────────────────────────────────────────────────────────┘

┌──────────────┐
│   kategori   │
│──────────────│
│ id           │
│ nama_kategori│
│ prefix_kode  │
└──────────────┘
      ↓ (1:N)
┌──────────────┐
│   produk     │◄─────────────────┐
│──────────────│                  │
│ id           │                  │
│ kategori_id  │◄ FK             │
│ kode_produk  │                 │
│ stok         │                 │
│ stok_reserved│                 │
│ stok_available│                │
└──────────────┘                 │
      ↑ (1:N)                    │
      │                          │
      ├──────────────────────────┤
      │      │                   │
      │      └──────────────┐    │
      │                    ↓    │
   ┌──────────┐        ┌──────────────┐
   │    po    │        │  po_items    │
   │──────────│        │──────────────│
   │ id       │◄─ FK   │ po_id        │
   │ nomor_po │        │ produk_id    │◄─ FK
   │ status   │        │ qty          │
   └──────────┘        │ is_reserved  │
        ↓              └──────────────┘
        │
   ┌──────────────┐
   │ pengeluaran  │
   │──────────────│
   │ id           │
   │ nomor_png    │
   │ status       │
   └──────────────┘
        ↓ (1:N)
   ┌────────────────────┐
   │ pengeluaran_items  │
   │────────────────────│
   │ pengeluaran_id◄─FK │
   │ produk_id     ◄─FK │
   │ qty            │
   └────────────────────┘

FLOW: Kategori → Produk ← PO_Items ← PO → Pengeluaran
```

---

## ✅ RINGKASAN SINGKAT

| Aspek | Keterangan |
|-------|-----------|
| **Kode Produk** | Identitas produk, format: `PREFIX-NNN` (PCB-001, RES-042) |
| **Nomor PO** | Invoice pesanan, format: `PO-MMYY-NNN` (PO-1126-001) |
| **Stok** | Ada 3 jenis: Total, Reserved, Available |
| **Reserve** | Terjadi otomatis saat PO di-approve |
| **Unreserve** | Terjadi otomatis saat pengeluaran di-complete |
| **Validasi** | Gudang hanya bisa output qty <= stok_available |
| **Kategori** | Baru! Setiap kategori punya prefix unik |
| **Database** | Perlu: produk, kategori, po, po_items, pengeluaran |

---

## 📞 QUICK REFERENCE

**Pertanyaan Umum:**

**Q: Gimana caranya tahu stok tersedia untuk PO?**  
A: Lihat kolom `stok_available` di produk list. Itu = stok - yang sudah di-reserve PO lain.

**Q: Bisa ga warehouse output stok yang di-reserve?**  
A: Tidak! Validasi akan error jika qty > stok_available.

**Q: Kode produk apa bedanya PCB-001 sama PCB-002?**  
A: Sama kategori (PCB), urutan berbeda (001 vs 002). Bisa jadi model berbeda, harga berbeda, etc.

**Q: Stok berkurang gimana caranya?**  
A: Otomatis saat pengeluaran status di-change jadi "Completed".

**Q: Bisa reset stok ke nilai awal?**  
A: Edit produk → ubah stok fisik. Sistem auto-recalc available = stok - reserved.

**Q: PO-1126 apa artinya?**  
A: Pesanan November 2026. (11=November, 26=2026)

---

**Dokumentasi Lengkap**: [WORKFLOW_INTEGRASI_STOK.md](WORKFLOW_INTEGRASI_STOK.md)  
**Testing Guide**: [SETUP_TESTING_GUIDE.md](SETUP_TESTING_GUIDE.md)  
**Last Updated**: 2026-06-06
