# 🔄 Stock Integration - Quick Reference

## Real-time Stock Status in Marketing

### Current Situation
✅ Both Marketing and Gudang share the same `produk` table  
✅ Stock columns automatically synchronized  
✅ All operations logged for audit trail  

### What Happens When

#### Scenario 1: Create Product (Marketing)
```
Marketing: Product Barang create
  → Produk::create(['stok' => 500])
  → Result: stok=500, reserved=0, available=500 ✓
```

#### Scenario 2: Create PO (Marketing)
```
Marketing: New PO with 200 units
  → PO::createWithItems($po, $items)
    → StokTracking::reserveStok(produk_id, 200)
      → UPDATE produk SET 
        stok_available = 300,      ← 500 - 200
        stok_reserved = 200        ← 0 + 200
  → Result: stok=500, reserved=200, available=300 ✓
  → Log: "PO-0126-001 reserve 200 pcs" in stok_log
```

#### Scenario 3: Receive Goods (Gudang - Penerimaan)
```
Gudang: Goods Receipt for 200 units (mark as completed)
  → Penerimaan::add(['status' => 'completed'])
    → StokTracking::addStok(produk_id, 200)
      → UPDATE produk SET 
        stok = 200,                ← 0 + 200
        stok_available = 200       ← 0 + 200
    → StokTracking::unreserveStok(produk_id, 200)
      → UPDATE produk SET 
        stok_reserved = 0,         ← 200 - 200
        stok_available = 200       ← 200 (no change, balanced!)
  → Result: stok=200, reserved=0, available=200 ✓
  → Logs: 
    - "Penerimaan receive 200 pcs"
    - "Unreserve PO 200 pcs"
```

#### Scenario 4: Ship Goods (Gudang - Pengeluaran)
```
Gudang: Goods Shipment 150 units (mark as completed)
  → Pengeluaran::add(['status' => 'completed'])
    → StokTracking::reduceStok(produk_id, 150)
      → UPDATE produk SET 
        stok = 50,                 ← 200 - 150
        stok_available = 50        ← 200 - 150
  → Result: stok=50, reserved=0, available=50 ✓
  → Log: "Pengeluaran ship 150 pcs"
```

#### Scenario 5: Delete Pengeluaran (Rollback)
```
Gudang: Delete Pengeluaran that was completed
  → Penerimaan::delete(id)
    → StokTracking::reduceStok(produk_id, 150)
      → UPDATE produk SET 
        stok = 200,                ← 50 + 150 (restore)
        stok_available = 200       ← 50 + 150
  → Result: stok=200, reserved=0, available=200 ✓
  → Log: "Rollback - Pengeluaran deleted 150 pcs"
```

---

## API Usage from Frontend

### JavaScript Example: Check Stock Before PO
```javascript
// In Marketing PO form, when selecting product
const produk_id = document.getElementById('produk_select').value;

fetch(`/Inventaris/public/gudang/api/get_stok_realtime.php?produk_id=${produk_id}`)
  .then(r => r.json())
  .then(data => {
    if (data.success && data.data.length > 0) {
      const stok = data.data[0];
      
      // Show available stock
      document.getElementById('stok_available').textContent = stok.stok_available;
      
      // Validate PO quantity
      const qty = parseInt(document.getElementById('qty_order').value);
      if (qty > stok.stok_available) {
        alert('⚠️ Stock tidak cukup! Tersedia: ' + stok.stok_available + ' pcs');
        return false;
      }
      
      // Show status badge
      let badge_class = 'badge-ok';
      if (stok.status_stok === 'LOW_STOCK') badge_class = 'badge-warning';
      if (stok.status_stok === 'OUT_OF_STOCK') badge_class = 'badge-danger';
      
      document.getElementById('status_badge').className = badge_class;
      document.getElementById('status_badge').textContent = stok.status_stok;
    }
  });
```

### JavaScript Example: Show Stock History
```javascript
// In Kartu Stok page, show product history
const produk_id = new URLSearchParams(location.search).get('produk_id');

fetch(`/Inventaris/public/gudang/api/get_stok_history.php?produk_id=${produk_id}&limit=100`)
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const logs = data.data;
      
      // Create table rows
      logs.forEach(log => {
        const row = `
          <tr>
            <td>${log.created_at}</td>
            <td>${log.tipe_transaksi}</td>
            <td>${log.qty_change > 0 ? '+' : ''}${log.qty_change}</td>
            <td>${log.stok_before} → ${log.stok_after}</td>
            <td>${log.keterangan}</td>
            <td>${log.created_by_name}</td>
          </tr>
        `;
        document.getElementById('history_table').innerHTML += row;
      });
    }
  });
```

---

## PHP Code: Using Helper Functions

### In PO Form Handler
```php
<?php
require_once 'src/functions.php';

// Check if stock is available
if (!hasEnoughStock($produk_id, $qty_order)) {
    $error = 'Stock tidak cukup! Available: ' . getStokInfo($produk_id)['stok_available'];
}

// Get current stock before saving
$stok_info = getStokInfo($produk_id);
echo "Stok tersedia: " . $stok_info['stok_available'] . " pcs";

// Check stock status
$status = getStockStatus($produk_id);
if ($status === 'LOW_STOCK') {
    echo '⚠️ Stock WARNING: Stok mendekati habis!';
}
?>
```

### In Custom Reports
```php
<?php
require_once 'src/functions.php';

// Get stock history for report
$history = getStockHistory($produk_id, 500);  // Get last 500 transactions

// Print audit trail
foreach ($history as $log) {
    printf(
        "%s | %s | %s | Before: %d → After: %d | By: %s\n",
        $log['created_at'],
        $log['tipe_transaksi'],
        $log['keterangan'],
        $log['stok_before'],
        $log['stok_after'],
        $log['created_by_name']
    );
}

// Calculate stock percentage
$persen = getStockPercentage($produk_id);
echo "Stock fill: " . $persen . "%";
?>
```

---

## Transaction Type Reference

| Type | Trigger | stok | stok_reserved | stok_available |
|------|---------|------|---------------|----------------|
| `po_reserve` | PO created | — | ↑ | ↓ |
| `po_unreserve` | PO cancelled or goods received | — | ↓ | ↑ |
| `pengeluaran_sub` | Goods shipped | ↓ | — | ↓ |
| `verifikasi_add` | Production verified | ↑ | — | ↑ |
| `penerimaan_receive` | Goods received | ↑ | — | ↑ |
| `adjustment` | Manual correction | ± | — | ± |
| `*_delete` | Document deleted (rollback) | Reversed | Reversed | Reversed |

---

## Error Handling

### Try-Catch Pattern
```php
try {
    $result = reserveStock($produk_id, 200, $po_id, $user_id);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Success
    echo "Stock reserved: " . $result['message'];
    
} catch (Exception $e) {
    error_log("Stock operation failed: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

## Common Patterns

### Pattern 1: Validate Before PO
```php
// Always check before creating PO item
$stok = getStokInfo($produk_id);
if ($qty_order > $stok['stok_available']) {
    return false; // Can't reserve
}
```

### Pattern 2: Rollback on Error
```php
// PDO transaction ensures rollback on error
$pdo->beginTransaction();
try {
    // Do operations
    addStock($produk_id, 100);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack(); // Stock changes reversed automatically
    throw $e;
}
```

### Pattern 3: Audit Trail
```php
// Check what happened to a product's stock
$history = getStockHistory($produk_id, 50);
foreach ($history as $entry) {
    // entry contains: tipe_transaksi, qty_change, reference_id, created_by, etc
}
```

---

## Dashboard Integration

### Show Stock Alert Card
```html
<div id="stock-alerts" class="alert-container"></div>

<script>
fetch('/Inventaris/public/gudang/api/get_stok_realtime.php')
  .then(r => r.json())
  .then(data => {
    // Filter products with low/no stock
    const alerts = data.data.filter(p => 
      p.status_stok === 'LOW_STOCK' || 
      p.status_stok === 'OUT_OF_STOCK'
    );
    
    // Display alerts
    alerts.forEach(p => {
      const icon = p.status_stok === 'OUT_OF_STOCK' ? '🔴' : '🟡';
      const html = `
        <div class="alert-item">
          ${icon} ${p.nama} - Stock: ${p.stok_available} pcs
        </div>
      `;
      document.getElementById('stock-alerts').innerHTML += html;
    });
  });
</script>
```

---

## Troubleshooting

### Stock showing negative
❌ **Problem:** `stok_available` is negative
✅ **Solution:** Use adjustment function
```php
adjustmentStok($produk_id, -stok_available); // Correct to 0
```

### Formula mismatch
❌ **Problem:** `stok_available ≠ stok - stok_reserved`
✅ **Solution:** Verify all transactions completed
```php
$stok = getStokInfo($produk_id);
$expected = $stok['stok'] - $stok['stok_reserved'];
if ($stok['stok_available'] != $expected) {
  // Data inconsistency detected
}
```

---

**Last Updated:** 2026-06-06
