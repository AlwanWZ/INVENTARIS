# ✅ Implementasi Checklist - Sistem Inventory Realtime

## Phase 1: Database Setup
- [ ] Backup database `inventaris`
- [ ] Run migration script: `DATABASE_STOK_TRACKING.sql`
- [ ] Verify kolom baru di tabel `produk`:
  - [ ] `stok_reserved` (INT)
  - [ ] `stok_available` (INT)
  - [ ] `updated_at` (TIMESTAMP)
- [ ] Verify kolom baru di tabel `po`:
  - [ ] `status_stok` (ENUM)
  - [ ] `approval_date` (DATETIME)
- [ ] Verify tabel baru:
  - [ ] `stok_log` (dengan audit trail)
- [ ] Verify views:
  - [ ] `v_stok_realtime`
  - [ ] `v_po_dengan_stok`

## Phase 2: Model Implementation
- [x] `StokTracking.php` - CREATED ✅
  - [x] `reserveStok()` method
  - [x] `unreserveStok()` method
  - [x] `addStok()` method
  - [x] `reduceStok()` method
  - [x] `adjustmentStok()` method
  - [x] `getStokRealtime()` method
  - [x] `getStokLog()` method

- [x] `PO.php` - UPDATED ✅
  - [x] `reserveStok()` static method
  - [x] `unreserveStok()` static method
  - [x] `getPOWithStok()` method

- [x] `Verifikasi.php` - UPDATED ✅
  - [x] `add()` method - gunakan StokTracking
  - [x] `update()` method - gunakan StokTracking dengan status handling

- [x] `Pengeluaran.php` - UPDATED ✅
  - [x] `add()` method - gunakan StokTracking
  - [x] `update()` method - gunakan StokTracking dengan status handling

## Phase 3: UI Update - PO Module
- [ ] **PO Index** (`public/marketing/po/index.php`)
  - [ ] Tambah kolom "Stok Status" dengan icon/badge
  - [ ] Tampilkan warning jika ada items yang stok kurang
  - [ ] Tambah status badge: "RESERVED", "PARTIAL", "READY"

- [ ] **PO Detail** (`public/marketing/po/crud/detail.php`)
  - [ ] Tampilkan tabel items dengan kolom stok_available
  - [ ] Tampilkan warning per item jika stok < qty order
  - [ ] Add tombol "APPROVE & RESERVE" (if status = draft)
  - [ ] Show reserved status dengan detail

- [ ] **PO Edit** (`public/marketing/po/crud/edit.php`)
  - [ ] Add submit button untuk "Approve & Reserve"
  - [ ] Handle PO::reserveStok() call
  - [ ] Display error jika stok tidak cukup

## Phase 4: UI Update - Verifikasi Module
- [ ] **Verifikasi Index** (`public/gudang/verif/finish-good/index.php`)
  - [ ] Tambah kolom "Stok Impact"
  - [ ] Tampilkan qty_ok dan stok_after untuk preview

- [ ] **Verifikasi Detail** (`public/gudang/verif/finish-good/crud/detail.php`)
  - [ ] Show stok current vs stok after approval
  - [ ] Approval button should trigger stok update
  - [ ] Show stok_log entries untuk produk ini

## Phase 5: UI Update - Pengeluaran Module
- [ ] **Pengeluaran Index** (`public/gudang/pengeluaran/index.php`)
  - [ ] Tampilkan stok status per item
  - [ ] Warning jika ada item yang stok tidak cukup

- [ ] **Pengeluaran Detail/Edit** (`public/gudang/pengeluaran/crud/edit.php`)
  - [ ] Show "Complete Shipment" button (if status = draft)
  - [ ] Confirm dialog: "Will reduce stok by X pcs"
  - [ ] Handle Pengeluaran::update() dengan status completion

## Phase 6: Dashboard Realtime
- [ ] **Dashboard Main** (`public/dashboard.php`)
  - [ ] Add widget "Stok Realtime"
  - [ ] Show top 5 produk dengan stok paling rendah
  - [ ] Show produk dengan stok RESERVED
  - [ ] Show produk OUT_OF_STOCK

- [ ] Query dari view `v_stok_realtime`
  - [ ] Display stok, stok_reserved, stok_available
  - [ ] Color-code: GREEN (OK), YELLOW (LOW), RED (OUT)

## Phase 7: Reports & Analytics
- [ ] **Stok Tracking Report** (BARU)
  - [ ] List all produk dengan status stok realtime
  - [ ] Filter by status (OK, LOW_STOCK, OUT_OF_STOCK)
  - [ ] Export to PDF/Excel

- [ ] **Audit Trail Report** (BARU)
  - [ ] Show stok_log entries per produk
  - [ ] Filter by tanggal, tipe transaksi
  - [ ] Show who made changes (created_by)

- [ ] **PO Fulfillment Report** (UPDATE)
  - [ ] Show PO dengan status_stok
  - [ ] Identify bottlenecks (stok kurang)
  - [ ] Recommendation untuk order produksi

## Phase 8: Testing
- [ ] **Skenario 1: Order Cukup Stok**
  - [ ] PO order 100 pcs (stok 150)
  - [ ] Approve → reserve otomatis ✅
  - [ ] Stok: available jadi 50 ✅

- [ ] **Skenario 2: Order Kurang Stok**
  - [ ] PO order 200 pcs (stok 150)
  - [ ] Approve → error "Stok tidak cukup" ✅
  - [ ] Status tetap draft ✅

- [ ] **Skenario 3: Produksi Selesai**
  - [ ] Verifikasi 100 pcs OK
  - [ ] Status verified → stok nambah ✅
  - [ ] Audit log tercatat ✅

- [ ] **Skenario 4: Shipment**
  - [ ] Pengeluaran 150 pcs
  - [ ] Status completed → stok kurang ✅
  - [ ] Reserve cleared ✅

- [ ] **Skenario 5: Rollback**
  - [ ] Change pengeluaran dari completed → draft
  - [ ] Stok add back ✅
  - [ ] Audit log tercatat ✅

- [ ] **Skenario 6: Over-Stock Adjustment**
  - [ ] Manual adjustment +50 pcs
  - [ ] Stok update dengan reason ✅
  - [ ] Audit log tercatat ✅

## Phase 9: Documentation
- [ ] Update README.md dengan workflow stok baru
- [ ] Create user guide PDF
- [ ] Create training video script
- [ ] Document API endpoints (if needed)

## Phase 10: Deployment & Training
- [ ] Deploy ke production
- [ ] Run database backup
- [ ] Train user di marketing
- [ ] Train user di gudang
- [ ] Monitor first week untuk bugs

---

## 📊 Estimation Timeline

| Phase | Task | Est. Hours | Status |
|-------|------|-----------|--------|
| 1 | Database Setup | 1 | ⏳ TODO |
| 2 | Model Implementation | 0 | ✅ DONE |
| 3 | PO UI Update | 3 | ⏳ TODO |
| 4 | Verifikasi UI Update | 2 | ⏳ TODO |
| 5 | Pengeluaran UI Update | 2 | ⏳ TODO |
| 6 | Dashboard | 2 | ⏳ TODO |
| 7 | Reports | 3 | ⏳ TODO |
| 8 | Testing | 4 | ⏳ TODO |
| 9 | Documentation | 2 | ⏳ TODO |
| 10 | Deployment | 2 | ⏳ TODO |
| | **TOTAL** | **21** | |

---

## 🚀 Quick Start

1. **Database Migration:**
   ```bash
   mysql -u root -p inventaris < DATABASE_STOK_TRACKING.sql
   ```

2. **Verify Models Created:**
   - Check: `src/models/StokTracking.php` exists ✅
   - Check: `src/models/PO.php` updated ✅
   - Check: `src/models/Verifikasi.php` updated ✅
   - Check: `src/models/Pengeluaran.php` updated ✅

3. **Run Test Scenario:**
   - See Phase 8 above

4. **Monitor Errors:**
   ```
   Check browser console & server logs for:
   - Exception: "Stok tidak cukup"
   - Missing class: StokTracking
   - Query errors in stok_log insert
   ```

---

## 📞 Support

If you encounter issues:

1. Check `stok_log` table untuk debug:
   ```sql
   SELECT * FROM stok_log ORDER BY created_at DESC LIMIT 10;
   ```

2. Check `v_stok_realtime` untuk current state:
   ```sql
   SELECT * FROM v_stok_realtime WHERE id = [produk_id];
   ```

3. Review transaction logs untuk rollback status:
   ```sql
   SELECT * FROM stok_log WHERE reference_type = 'po_unreserve' ORDER BY created_at DESC;
   ```

---

**Last Updated:** 2026-06-04
**Version:** 1.0
**Next Review:** After Phase 8 Testing
