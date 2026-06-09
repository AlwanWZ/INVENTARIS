# 📊 Stock Integration System Documentation

## Overview
The Inventaris system implements a **unified stock tracking** system where Marketing and Gudang modules share a single `produk` table with real-time inventory management. Stock changes are automatically synchronized across all operations.

---

## 🏗️ Architecture

### Stock Columns in `produk` Table

| Column | Meaning | Formula |
|--------|---------|---------|
| `stok` | Physical stock in warehouse | Manual entry during receipt |
| `stok_reserved` | Stock booked by POs (not yet shipped) | Auto-managed by PO operations |
| `stok_available` | Available stock for new orders | `stok - stok_reserved` |

### Stock Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    PRODUCT LIFECYCLE                        │
└─────────────────────────────────────────────────────────────┘

1. CREATE PRODUCT (Marketing)
   ├─ Set initial stok (physical inventory)
   ├─ stok_available = stok
   └─ stok_reserved = 0

2. CREATE PO (Marketing)
   ├─ RESERVE stok via StokTracking::reserveStok()
   ├─ stok_available -= qty
   └─ stok_reserved += qty

3. RECEIVE GOODS (Gudang - Penerimaan)
   ├─ ADD stok via StokTracking::addStok()
   ├─ UNRESERVE PO via StokTracking::unreserveStok()
   ├─ stok += qty_received
   ├─ stok_available += qty_received
   └─ stok_reserved -= qty_received

4. SHIP GOODS (Gudang - Pengeluaran)
   ├─ REDUCE stok via StokTracking::reduceStok()
   ├─ stok -= qty_shipped
   └─ stok_available -= qty_shipped

5. DELETE OPERATIONS (Automatic Rollback)
   ├─ Delete PO → unreserveStok() restores stok_available
   ├─ Delete Penerimaan → reduceStok() + reserveStok() rollback
   └─ Delete Pengeluaran → addStok() restores inventory
```

---

## 🔄 Module Operations & Stock Impact

### 1. **Marketing Module - Product Creation**
**File:** `public/marketing/produk/crud/add.php`

```php
// Initial state
Produk::create($data);
// Result: stok = user input, stok_available = stok, stok_reserved = 0
```

**Stock Changes:** ✅ stok increases (manual entry)

---

### 2. **Marketing Module - PO Creation**
**File:** `public/marketing/po/crud/add.php`

When PO is created with items:

```php
PO::createWithItems($dataPO, $dataItems);
// For each item:
// - StokTracking::reserveStok(produk_id, qty)
```

**Stock Changes:** 
- `stok_available` ↓ (decreases by qty)
- `stok_reserved` ↑ (increases by qty)
- `stok` → NO CHANGE (physical stock unchanged)

**Example:**
```
Initial: stok=1000, stok_reserved=0, stok_available=1000
PO for 200 units
Result:  stok=1000, stok_reserved=200, stok_available=800
```

---

### 3. **Gudang Module - Penerimaan (Goods Receipt)**
**File:** `public/gudang/penerimaan/index.php`

When Penerimaan status = 'completed':

```php
// Two operations:
1. StokTracking::addStok(produk_id, qty_diterima)
   // Increases physical inventory

2. StokTracking::unreserveStok(produk_id, qty_diterima)  
   // Releases PO reservation
```

**Stock Changes:**
- `stok` ↑ (increases by qty_received)
- `stok_available` ↑ (increases by qty_received)
- `stok_reserved` ↓ (decreases by qty_received)

**Example:**
```
Before receipt (had PO for 200):
  stok=0, stok_reserved=200, stok_available=-200

Receive 200 units:
  +addStok(200)       → stok=200, stok_available=0
  +unreserveStok(200) → stok_reserved=0, stok_available=0

Final: stok=200, stok_reserved=0, stok_available=200 ✓
```

---

### 4. **Gudang Module - Pengeluaran (Goods Shipment)**
**File:** `public/gudang/pengeluaran/crud/add.php`

When Pengeluaran status = 'completed':

```php
StokTracking::reduceStok(produk_id, qty_keluar)
```

**Stock Changes:**
- `stok` ↓ (decreases by qty_shipped)
- `stok_available` ↓ (decreases by qty_shipped)
- `stok_reserved` → NO CHANGE (already unreserved at receipt)

**Example:**
```
Before shipment:
  stok=500, stok_reserved=0, stok_available=500

Ship 300 units:
  Result: stok=200, stok_reserved=0, stok_available=200 ✓
```

---

## 📡 API Endpoints for Stock Integration

### Real-time Stock Endpoint
**URL:** `/Inventaris/public/gudang/api/get_stok_realtime.php`

**Parameters:**
- `produk_id=<id>` - Get single product
- `produk_ids=1,2,3` - Get multiple products
- No params - Get all active products

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "nama": "PCB Controller",
      "kode": "PCB-001",
      "stok": 500,
      "stok_reserved": 100,
      "stok_available": 400,
      "status_stok": "OK|LOW_STOCK|OUT_OF_STOCK",
      "persen_fill": 80
    }
  ]
}
```

**Usage in Marketing PO Form:**
```javascript
// When adding PO item, check available stock
fetch('/Inventaris/public/gudang/api/get_stok_realtime.php?produk_id=' + produk_id)
  .then(r => r.json())
  .then(data => {
    if (data.data[0].stok_available < qty_order) {
      alert('⚠️ Stock tidak cukup! Available: ' + data.data[0].stok_available);
    }
  });
```

---

### Stock History Endpoint
**URL:** `/Inventaris/public/gudang/api/get_stok_history.php`

**Parameters:**
- `produk_id=<id>` (required)
- `limit=50` (default, max 500)
- `start_date=2026-06-01`
- `end_date=2026-06-30`
- `tipe_transaksi=po_reserve|pengeluaran_sub|verifikasi_add`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1234,
      "produk_id": 5,
      "tipe_transaksi": "po_reserve",
      "qty_change": -200,
      "stok_before": 500,
      "stok_after": 300,
      "stok_reserved_before": 0,
      "stok_reserved_after": 200,
      "reference_type": "po",
      "reference_id": 15,
      "keterangan": "PO-0126-001",
      "created_by_name": "Marketing User",
      "created_at": "2026-06-06 10:30:00"
    }
  ]
}
```

---

## ✅ Validation Rules

### PO Creation Validation
```php
// In Marketing PO form
if ($qty_order > $stok_available) {
  // ERROR: Cannot reserve stock that doesn't exist
  throw new Exception('Stok tidak cukup!');
}
```

### Pengeluaran Validation
```php
// In Gudang Pengeluaran form
if ($qty_keluar > $stok_available) {
  // ERROR: Cannot ship more than available
  throw new Exception('Qty Keluar tidak boleh melebihi Stok!');
}
```

### Penerimaan Validation
```php
// In Gudang Penerimaan form
if ($qty_diterima > $qty_order) {
  // WARNING: Received more than ordered
  // Still allowed but shows "↑ Lebih X pcs" status
}
```

---

## 🔍 Debugging Stock Issues

### Check Stock Values
```php
// Use real-time API
GET /Inventaris/public/gudang/api/get_stok_realtime.php?produk_id=5

// Expected response shows current state
{
  "stok": 500,           // Physical inventory
  "stok_reserved": 100,  // Booked by PO
  "stok_available": 400  // Available = 500 - 100
}
```

### Verify Stock Calculation
```
Formula must always be: stok_available = stok - stok_reserved

If NOT equal:
  1. Check for incomplete transactions (draft PO, pending receipts)
  2. Review stok_log for recent changes
  3. Check for failed operations that need rollback
```

### Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| stok_available is negative | Bug or transaction failure | Use StokTracking::adjustmentStok() to correct |
| stok_available doesn't match formula | Incomplete update | Check stok_log for last change, manually recalculate |
| Can't create PO (stok_reserved error) | stok_available < qty_order | Receive goods or reduce PO quantity |
| Stok doesn't increase after Penerimaan | Status not 'completed' | Check Penerimaan status, ensure save as completed |

---

## 🔐 Security & Access Control

All API endpoints require authentication:
- ✅ Allowed roles: `marketing`, `gudang`, `manager`, `admin`
- ❌ Public access denied with HTTP 403

---

## 📝 Transaction Log Format

Every stock change is logged in `stok_log` table:

```
Columns:
- produk_id: Which product
- tipe_transaksi: po_reserve, po_unreserve, pengeluaran_sub, verifikasi_add, etc
- qty_change: Positive (increase) or negative (decrease)
- stok_before/stok_after: Stock value before and after
- stok_reserved_before/after: Reserved stock before and after
- reference_type: Source (po, pengeluaran, penerimaan, etc)
- reference_id: ID of the source document
- created_by: User who triggered change
- created_at: Timestamp
```

---

## 🎯 Integration Checklist

- ✅ StokTracking class fully implemented
- ✅ Penerimaan model integrated with StokTracking
- ✅ Real-time stock API endpoint created
- ✅ Stock history API endpoint created
- ✅ Validation rules in place
- ⏳ Marketing form integration (pending: add stock check)
- ⏳ Dashboard stock alerts (pending: implement)
- ⏳ Stock analytics dashboard (pending: implement)

---

## 📞 API Usage Examples

### Get all products stock
```bash
curl "http://localhost/Inventaris/public/gudang/api/get_stok_realtime.php"
```

### Get specific product stock
```bash
curl "http://localhost/Inventaris/public/gudang/api/get_stok_realtime.php?produk_id=5"
```

### Get stock history for product
```bash
curl "http://localhost/Inventaris/public/gudang/api/get_stok_history.php?produk_id=5&limit=100"
```

### Filter by date range
```bash
curl "http://localhost/Inventaris/public/gudang/api/get_stok_history.php?produk_id=5&start_date=2026-06-01&end_date=2026-06-30"
```

### Filter by transaction type
```bash
curl "http://localhost/Inventaris/public/gudang/api/get_stok_history.php?produk_id=5&tipe_transaksi=po_reserve"
```

---

## 🔄 Integration with Marketing Module

To display stock in Marketing product list, add to `public/marketing/produk/index.php`:

```html
<!-- Show stock status badge -->
<td>
  <div class="stock-status" data-produk-id="{{ produk.id }}">
    <span class="loading">Loading...</span>
  </div>
</td>

<script>
// Fetch real-time stock for each product
document.querySelectorAll('[data-produk-id]').forEach(el => {
  const produk_id = el.dataset.produkId;
  fetch(`/Inventaris/public/gudang/api/get_stok_realtime.php?produk_id=${produk_id}`)
    .then(r => r.json())
    .then(data => {
      const stok = data.data[0];
      el.innerHTML = `
        <div class="stok-info">
          <span class="badge ${stok.status_stok.toLowerCase()}">${stok.stok_available} pcs</span>
          <small>${stok.status_stok === 'OK' ? '✓ Tersedia' : '⚠ ' + stok.status_stok}</small>
        </div>
      `;
    });
});
</script>
```

---

## 📚 Related Documentation

- [Penerimaan Model](src/models/Penerimaan.php)
- [StokTracking Model](src/models/StokTracking.php)
- [Produk Model](src/models/Produk.php)
- [PO Model](src/models/PO.php)
- [Gudang Stok Index](public/gudang/stok/index.php)

---

**Last Updated:** 2026-06-06  
**System Version:** 2.0 (Integrated Stock System)
