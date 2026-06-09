# 🎨 QUICK VISUAL REFERENCE - STOK SYSTEM

## 1️⃣ KODE PRODUK STRUCTURE

```
PCB-001
│   │
│   └─ SEQUENCE (001, 002, 003, ...)
│      Per kategori terpisah
│
└─ PREFIX (dari kategori)
   PCB  → Papan Sirkuit
   RES  → Resistor
   KAP  → Kapasitor
   DIO  → Dioda
   TRS  → Transistor
   PROD → Custom/Default
```

---

## 2️⃣ NOMOR PESANAN (PO) STRUCTURE

```
PO-1126-001
│  │  │  │
│  │  │  └─ SEQUENCE Per bulan
│  │  │     (001, 002, 003, ...)
│  │  │
│  │  └─ TAHUN (2 digit)
│  │     26 = 2026, 27 = 2027
│  │
│  └──── BULAN (2 digit)
│        01 = Jan, 11 = Nov, 12 = Des
│
└────── PREFIX
        Selalu "PO"
```

---

## 3️⃣ STOK STATE DIAGRAM

```
┌─────────────────────────────────────────────────────────┐
│                   PRODUK DIBUAT (Initial)               │
├─────────────────────────────────────────────────────────┤
│  Total Stok:  100 pcs (Input)                          │
│  Reserved:      0 pcs (Default)                        │
│  Available:   100 pcs (Formula: 100-0)                 │
└─────────────────────────────────────────────────────────┘
                          │
                ┌─────────┴────────┐
                ▼                  ▼
        ┌──────────────┐    ┌──────────────┐
        │ PO DIBUAT    │    │ PO DIBUAT    │
        │ (Draft)      │    │ (Approved)   │
        ├──────────────┤    ├──────────────┤
        │ STOK TIDAK   │    │ STOK         │
        │ BERUBAH      │    │ RESERVED ↓  │
        │              │    │              │
        │ Total:100    │    │ Total:100    │
        │ Reserved:0   │    │ Reserved:60  │
        │ Available:100│    │ Available:40 │
        └──────────────┘    └──────────────┘
                                    │
                                    ▼
                            ┌──────────────┐
                            │ OUTPUT       │
                            │ PENGELUARAN  │
                            ├──────────────┤
                            │ (Completed)  │
                            │              │
                            │ Total:50     │
                            │ Reserved:10  │
                            │ Available:40 │
                            │              │
                            │ Formula:     │
                            │ 50 - 10 = 40 │
                            └──────────────┘
```

---

## 4️⃣ STOK JENIS-JENIS

```
┌─────────────────────────────────────────────────┐
│  Total Stok = Semua barang di gudang (Fisik)   │
├─────────────────────────────────────────────────┤
│ Contoh: 100 pcs                                │
│ Ini = gabung yang di-reserve + yang available  │
└─────────────────────────────────────────────────┘

         ↓ DIBAGI MENJADI ↓

  ┌──────────────────┬──────────────────┐
  │                  │                  │
  ▼                  ▼                  ▼
STOK RESERVED  STOK AVAILABLE  (gabungnya = TOTAL)
(Di-Reserve    (Bisa dijual/
 PO)           digunakan)

 60 pcs    +      40 pcs    =   100 pcs (TOTAL)
```

---

## 5️⃣ GUDANG VALIDASI LOGIC

```
                    ┌──────────────────┐
                    │ WAREHOUSE INPUT  │
                    │ Qty: ? pcs       │
                    └────────┬─────────┘
                             │
                    ┌────────▼────────┐
                    │ CEK KONDISI     │
                    │ stok_available  │
                    │  >= qty ?       │
                    └────────┬────────┘
                             │
                  ┌──────────┴──────────┐
                  │                    │
                 YES                  NO
                  │                    │
                  ▼                    ▼
            ┌────────────┐      ┌──────────────┐
            │ ✓ ALLOWED  │      │ ✗ REJECTED   │
            │ Output qty │      │ Show Error:  │
            │ pcs        │      │ • Total: X   │
            └────────────┘      │ • Reserved:Y │
                                │ • Available:Z│
                                │ • Qty Want:W │
                                └──────────────┘
```

---

## 6️⃣ ALUR SISTEM LENGKAP (TIMELINE)

```
TIMELINE: PCB-001, Stok Awal 100 pcs

┌─ 10:00 ─────────────────────────────────────────┐
│ PRODUK CREATED                                   │
│ Stok=100 | Reserved=0 | Available=100           │
└──────────────────────────────────────────────────┘
                        │
┌─ 11:00 ──────────────┘                          ┐
│ PO-1126-001 CREATED (Draft)                      │
│ Qty PO=60, Status=Draft                          │
│ Stok=100 | Reserved=0 | Available=100           │
│ (TIDAK BERUBAH - masih draft)                    │
└──────────────────────────────────────────────────┘
                        │
┌─ 14:00 ──────────────┘                          ┐
│ PO-1126-001 APPROVED ⚡ TRIGGER                  │
│ Auto Reserve Stok:                              │
│ Reserved: 0 → 60 ↑                              │
│ Available: 100 → 40 ↓                           │
│ FORMULA: 100 - 60 = 40                          │
│                                                  │
│ Stok=100 | Reserved=60 | Available=40           │
└──────────────────────────────────────────────────┘
                        │
┌─ 16:00 ──────────────┘                          ┐
│ PNG-001 OUTPUT (Complete) ⚡ TRIGGER            │
│ Output Qty=50                                   │
│ Auto Reduce & Unreserve:                        │
│ • Stok: 100 → 50 ↓ (output)                     │
│ • Reserved: 60 → 10 ↓ (unreserve)              │
│ FORMULA: 50 - 10 = 40                           │
│                                                  │
│ Stok=50 | Reserved=10 | Available=40            │
│                                                  │
│ Catatan: 10 dari PO lain yg belum output        │
└──────────────────────────────────────────────────┘
```

---

## 7️⃣ KATEGORI MAPPING TABLE

```
┌────────────┬──────────┬─────────────────────┐
│ Kategori   │ Prefix   │ Contoh Kode         │
├────────────┼──────────┼─────────────────────┤
│ PCB        │ PCB      │ PCB-001, PCB-042    │
│ Resistor   │ RES      │ RES-001, RES-128    │
│ Kapasitor  │ KAP      │ KAP-001, KAP-256    │
│ Dioda      │ DIO      │ DIO-001, DIO-053    │
│ Transistor │ TRS      │ TRS-001, TRS-099    │
│ Custom     │ PROD     │ PROD-001, PROD-512  │
└────────────┴──────────┴─────────────────────┘
```

---

## 8️⃣ DATABASE FIELD RELATIONSHIPS

```
KATEGORI TABLE:
┌─ id: 1
├─ nama_kategori: "PCB"
└─ prefix_kode: "PCB" ◄─┐
                        │
PRODUK TABLE:           │
┌─ id: 1                │ (FK Link)
├─ kategori_id: 1 ─────┘
├─ kode_produk: "PCB-001" ◄─ Generated from prefix
├─ stok: 100
├─ stok_reserved: 0
└─ stok_available: 100 ◄─ Formula: 100-0

PO TABLE:               PO_ITEMS TABLE:
┌─ id: 1         ┌─────┬─ po_id: 1
└─ nomor_po: ◄───┘     ├─ produk_id: 1 ◄───┐
                       ├─ qty: 60           │ (FK Link)
                       └─ is_reserved: true │ to PRODUK
```

---

## 9️⃣ ERROR SCENARIO DIAGRAM

```
SCENARIO: Warehouse coba output qty terlalu banyak

  Stok Available: 40 pcs
  Qty Diminta:    50 pcs  ← TOO MUCH!
                  │
                  ▼
        ┌──────────────────┐
        │ ✗ VALIDATION     │
        │   FAILED         │
        └──────┬───────────┘
               │
        ┌──────▼──────────────────────────┐
        │ ERROR MESSAGE:                   │
        │ ┌────────────────────────────┐  │
        │ │ Total Stok: 100 pcs        │  │
        │ │ Reserved: 60 pcs           │  │
        │ │ Available: 40 pcs ◄ ONLY!  │  │
        │ │ Diminta: 50 pcs ◄ TOO MUCH │  │
        │ └────────────────────────────┘  │
        └────────────────────────────────┘
               │
        ┌──────▼─────────────┐
        │ USER MUST CHOOSE:  │
        ├───────────────────┤
        │ A) Reduce qty → 40│
        │ B) Cancel output  │
        │ C) Wait for more  │
        │    stok (restock) │
        └───────────────────┘
```

---

## 🔟 QUICK DECISION TREE

```
START: Warehouse mau output barang

             │
             ▼
     ┌──────────────┐
     │ Check Field  │
     │ stok_available
     └──────┬───────┘
            │
      ┌─────┴─────┐
     YES         NO
      │           │
      ▼           ▼
   ✓ ALLOWED   ✗ ERROR
   Process     Show Detail:
   output      -Total
               -Reserved
               -Available
               -Diminta
               
      │           │
      └─────┬─────┘
            │
            ▼
      Refresh system
      to see updated stok
```

---

**Last Updated**: 2026-06-06  
**For Quick Reference**: Print or bookmark this page!
