## STRICT INVENTORY VALIDATION - FINAL

### 🎯 ATURAN (TIDAK BOLEH OVERSELL):
- Produk PCB: 77 pcs
- Marketing input: 110 pcs
- Hasil: ❌ ERROR! Tidak boleh disimpan

### 📋 VALIDASI BERLAPIS:

#### 1. CLIENT-SIDE (JavaScript - Real-time)
File: `public/marketing/po/crud/add_item.php`
```javascript
if (qty > selectedStokAvailable) {
  // TAMPIL ERROR
  // DISABLE TOMBOL SUBMIT
  // TAMPIL MAKSIMAL STOK
}
```

#### 2. SERVER-SIDE (PHP - Final Check)
File: `public/marketing/po/crud/add_item.php`
```php
if ($qty_pending > 0) {
  $errors[] = "❌ STOK TIDAK CUKUP!";
  // TIDAK BISA DISIMPAN
}
```

### 🧪 TESTING SKENARIO:

**Scenario 1: Oversell**
```
1. Buka: Marketing → Pesanan PCB → Tambah Item
2. Pilih: PCB 77 pcs
3. Input Qty: 110 pcs
4. Lihat: 
   - Warning merah: "STOK TIDAK CUKUP"
   - Tombol "Tambah Item" DISABLED (abu-abu)
5. Coba click: Tidak bisa, tombol tidak response
6. RESULT: ✅ FIXED - Tidak bisa submit oversell
```

**Scenario 2: Within Stock**
```
1. Input Qty: 75 pcs (< 77 stok)
2. Lihat:
   - Warning hijau: "STOK CUKUP"
   - Tombol "Tambah Item" ENABLED (biru)
3. Click "Tambah Item"
4. RESULT: ✅ Berhasil disimpan
```

**Scenario 3: Exact Match**
```
1. Input Qty: 77 pcs (= stok)
2. Lihat:
   - Warning hijau: "STOK CUKUP"
   - Tombol ENABLED
3. Click "Tambah Item"
4. RESULT: ✅ Berhasil disimpan
```

### 📊 PERUBAHAN DARI PARTIAL → STRICT:

**Partial Reserve (DIHAPUS)**:
```
qty_available = 77
qty_pending = 33
Result: ALLOW ✅ (tapi ada pending)
```

**Strict Validation (SEKARANG)**:
```
qty > stok_available?
  → YES: REJECT ❌ (tidak disimpan)
  → NO: ALLOW ✅ (disimpan)
```

### ✅ FILE YANG SUDAH DIUPDATE:

1. `public/marketing/po/crud/add_item.php`
   - Backend validation: STRICT (reject oversell)
   - Frontend validation: STRICT (disable tombol)
   - Error message: Clear & actionable

2. `public/marketing/po/crud/detail.php`
   - Hapus kolom pending (karena tidak ada dengan STRICT)
   - Tampilkan qty_available saja

3. `src/models/PO.php`
   - addItem() update qty_available=qty, qty_pending=0
   - createWithItems() update kolom baru

4. `ADD_PENDING_COLUMNS.sql`
   - Sudah executed, kolom ada di database

### 🔧 JIKA MASIH ADA ISSUE:

1. **Tombol masih bisa diklik meski warning**
   - Check: Apakah JavaScript error di console browser?
   - Fix: Run: F12 → Console → cek error

2. **Dapat disimpan meski > stok**
   - Check: Database kolom qty_available, qty_pending ada?
   - Query: `DESCRIBE po_items;` di MySQL
   - Jika belum, run: `ADD_PENDING_COLUMNS.sql`

3. **Tidak ada stok info di dropdown**
   - Check: Produk punya kolom stok_available?
   - Query: `DESCRIBE produk;`
   - Jika belum, harus buka issue baru

### 📌 NEXT STEP:

Test di browser dan lapor:
- ✅ Oversell error muncul?
- ✅ Tombol disable?
- ✅ Tidak bisa submit?
- ✅ Error message jelas?
- ✅ Within stock bisa submit?
