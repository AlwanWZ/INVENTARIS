<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * DOKUMENTASI IMPLEMENTASI: 3 PILAR STOK INVENTORY
 * ═══════════════════════════════════════════════════════════════════
 * 
 * KOLOM DI TABEL PRODUK:
 * 1. stok          → Stok Fisik di gudang (manual input user)
 * 2. stok_reserved → Stok yang di-booking via PO (AUTO dari sistem)
 * 3. stok_available→ Stok yang bisa dijual (AUTO: stok - stok_reserved)
 * 
 * FORMULA UTAMA (WAJIB DIPATUHI DIMANAPUN):
 * stok_available = stok - stok_reserved
 * Jangan pernah negatif!
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

// ════════════════════════════════════════════════════════════════
// 1️⃣  MODUL PRODUK - CRUD MANAGEMENT
// ════════════════════════════════════════════════════════════════

/**
 * FILE: produk/crud/add.php
 * 
 * ALUR:
 * Input: stok_fisik = 100
 *   ↓
 * Backend validation:
 *   - Cek input valid
 *   - Panggil Produk::create($data)
 *   ↓
 * Di Produk::create():
 *   - stok_available = input_stok (sebab belum ada booking)
 *   - stok_reserved = 0
 *   - INSERT ke database
 *   ↓
 * RESULT:
 *   - stok = 100
 *   - stok_reserved = 0
 *   - stok_available = 100 ✅
 */

/**
 * FILE: produk/crud/edit.php
 * 
 * ALUR:
 * Input: stok_fisik_baru = 150 (sebelumnya 100)
 *   ↓
 * Backend validation:
 *   - Cek input valid
 *   - Panggil Produk::update($data)
 *   ↓
 * Di Produk::update():
 *   - Ambil stok_reserved dari database (JANGAN dari input!)
 *   - Hitung: stok_available = 150 - stok_reserved
 *   - UPDATE database
 *   ↓
 * RESULT (jika sebelumnya ada PO booking 20):
 *   - stok = 150 (updated)
 *   - stok_reserved = 20 (tetap, dari PO)
 *   - stok_available = 150 - 20 = 130 ✅
 */


// ════════════════════════════════════════════════════════════════
// 2️⃣  MODUL PO (PURCHASE ORDER) - BOOKING SYSTEM
// ════════════════════════════════════════════════════════════════

/**
 * FILE: po/crud/add.php
 * 
 * ALUR VALIDASI (RULE 2 - STRICT):
 * 
 * User mau PO qty=50, Produk PCB A
 * Check: stok_available >= 50?
 *   ✅ Jika YES → Lanjut create PO
 *   ❌ Jika NO  → Error "Qty melebihi stok tersedia"
 * 
 * Contoh:
 *   - stok = 100, stok_reserved = 0, stok_available = 100
 *   - PO qty = 50
 *   - Check: 100 >= 50? YES ✅
 *   - Lanjut proses
 */

/**
 * FILE: po/crud/add.php Backend (PO::createWithItems)
 * 
 * ALUR TRANSAKSI (RULE 3 - AUTO UPDATE):
 * 
 * User submit order 50 pcs PCB A
 *   ↓
 * Inside Transaction:
 *   1. INSERT po header
 *   2. INSERT po_items (qty=50)
 *   3. UPDATE produk:
 *      - stok_reserved = stok_reserved + 50
 *      - stok_available = stok_available - 50
 *      WHERE produk_id = ?
 *   4. COMMIT atau ROLLBACK
 *   ↓
 * RESULT (contoh):
 *   BEFORE:
 *     stok = 100, stok_reserved = 0, stok_available = 100
 *   
 *   AFTER PO created qty=50:
 *     stok = 100 (unchanged!)
 *     stok_reserved = 0 + 50 = 50
 *     stok_available = 100 - 50 = 50 ✅
 */


// ════════════════════════════════════════════════════════════════
// 3️⃣  SKENARIO REAL: STEP BY STEP
// ════════════════════════════════════════════════════════════════

/**
 * SKENARIO: PCB A - Dari Create Produk hingga Deliver
 * 
 * STEP 1: Marketing create Produk PCB A
 *   Input: stok_awal = 100 pcs
 *   Backend: Produk::create(['stok' => 100])
 *   DB State:
 *     stok = 100
 *     stok_reserved = 0
 *     stok_available = 100
 * 
 * STEP 2: Customer A mau PO 30 pcs
 *   Frontend: Validasi qty=30 vs stok_available=100 → PASS ✅
 *   Backend: PO::createWithItems() transaction
 *   SQL Update: UPDATE produk SET stok_reserved=0+30, stok_available=100-30
 *   DB State:
 *     stok = 100 (tetap, ini stok fisik)
 *     stok_reserved = 30 (qty di-PO)
 *     stok_available = 70 (sisa untuk order lain)
 * 
 * STEP 3: Customer B juga mau PO 60 pcs
 *   Frontend: Validasi qty=60 vs stok_available=70 → PASS ✅
 *   Backend: PO::createWithItems() transaction
 *   SQL Update: UPDATE produk SET stok_reserved=30+60, stok_available=70-60
 *   DB State:
 *     stok = 100 (tetap)
 *     stok_reserved = 90 (total di-PO)
 *     stok_available = 10 (sisa untuk order)
 * 
 * STEP 4: Customer C mau PO 20 pcs
 *   Frontend: Validasi qty=20 vs stok_available=10 → FAIL ❌
 *   Error: "Qty melebihi stok tersedia! Max: 10 pcs"
 *   PO tidak dibuat
 * 
 * STEP 5: Marketing add stok fisik +50 pcs (gudang terima barang baru)
 *   Input di edit.php: stok_fisik_baru = 100 + 50 = 150
 *   Backend: Produk::update(['stok' => 150])
 *   Calc: stok_available = 150 - 90 = 60
 *   DB State:
 *     stok = 150 (updated)
 *     stok_reserved = 90 (tetap)
 *     stok_available = 60 ✅
 * 
 * STEP 6: Sekarang Customer C bisa PO 20 pcs lagi
 *   Frontend: Validasi qty=20 vs stok_available=60 → PASS ✅
 *   Backend: PO::createWithItems() transaction
 *   SQL Update: UPDATE produk SET stok_reserved=90+20, stok_available=60-20
 *   DB State:
 *     stok = 150 (tetap)
 *     stok_reserved = 110
 *     stok_available = 40
 * 
 * STEP 7: Shipment Customer A (30 pcs delivered)
 *   Backend: Pengeluaran::complete() mengurangi stok fisik
 *   SQL Update: UPDATE produk SET stok=150-30, stok_reserved=110-30, stok_available=90-30
 *   DB State:
 *     stok = 120 (berkurang, shipped)
 *     stok_reserved = 80 (berkurang)
 *     stok_available = 40 (tetap, karena rumusnya = 120 - 80)
 */


// ════════════════════════════════════════════════════════════════
// 4️⃣  CHEAT SHEET: KAPAN KOLOM BERUBAH
// ════════════════════════════════════════════════════════════════

/**
 * KOLOM PERUBAHAN WORKFLOW
 * 
 * 1️⃣  stok (STOK FISIK)
 *     Berubah saat:
 *     - Produk baru di-create (manual input)
 *     - Edit produk, user ubah stok fisik (manual input)
 *     - Pengeluaran/Shipment terjadi (berkurang saat kirim)
 *     - Penerimaan/Goods Receipt terjadi (bertambah saat terima)
 * 
 * 2️⃣  stok_reserved (STOK YANG DIBOOKING)
 *     Berubah saat:
 *     - PO dibuat (bertambah = qty PO)
 *     - PO dihapus (berkurang)
 *     - Pengeluaran/Shipment terjadi (berkurang saat kirim)
 *     - JANGAN manual input, HANYA sistem!
 * 
 * 3️⃣  stok_available (STOK YANG BISA DIJUAL)
 *     Berubah saat:
 *     - PO dibuat (berkurang = qty PO)
 *     - PO dihapus (bertambah)
 *     - Edit stok fisik (recalculate)
 *     - FORMULA: stok - stok_reserved
 *     - JANGAN manual input, SELALU CALCULATED!
 */


// ════════════════════════════════════════════════════════════════
// 5️⃣  KODE IMPLEMENTASI (RINGKAS)
// ════════════════════════════════════════════════════════════════

/**
 * Produk::create() - Saat buat produk baru
 * 
 * $stok_input = 100;
 * $data = [
 *     'stok' => $stok_input,
 *     // stok_available dan stok_reserved di-handle otomatis
 * ];
 * 
 * SQL INSERT:
 * INSERT INTO produk 
 * (stok, stok_reserved, stok_available) 
 * VALUES (100, 0, 100)
 */

/**
 * Produk::update() - Saat edit produk
 * 
 * $stok_baru = 150;
 * $stok_reserved = 30; // dari database
 * $stok_available = $stok_baru - $stok_reserved; // = 120
 * 
 * SQL UPDATE:
 * UPDATE produk 
 * SET stok = 150, stok_available = 120
 * WHERE id = ?
 * (stok_reserved TIDAK di-update!)
 */

/**
 * PO::createWithItems() - Saat buat PO
 * 
 * DALAM TRANSACTION:
 * 
 * INSERT po_items (qty=50)
 * 
 * UPDATE produk
 * SET stok_reserved = stok_reserved + 50,
 *     stok_available = stok_available - 50
 * WHERE produk_id = ?
 * 
 * COMMIT atau ROLLBACK
 */

?>
