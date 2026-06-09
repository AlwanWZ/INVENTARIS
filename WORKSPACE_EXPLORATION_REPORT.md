# 📋 Workspace Exploration Report - Inventaris System

**Generated:** 2026-06-06  
**Database:** inventaris (MySQL)  
**Host:** localhost  
**User:** root

---

## 1️⃣ DATABASE SCHEMA

### Database Configuration

**File:** [src/config.php](src/config.php)  
**Alternative Config:** [config/database.php](config/database.php)

```php
$host = 'localhost';
$db   = 'inventaris';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
```

### Key Tables Structure

#### **A. PRODUK Table**

**Core Columns:**
- `id` (INT, PRIMARY KEY)
- `kode_produk` (VARCHAR) - Product code (auto-generated)
- `nama` (VARCHAR) - Product name
- `kategori` (VARCHAR) - Category (usually 'PCB' for this system)
- `stok` (INT) - Total physical stock
- `stok_reserved` (INT) - Stock reserved for PO (DEFAULT 0)
- `stok_available` (INT) - Available stock for sale (DEFAULT 0)
- `stok_min` (INT) - Minimum stock threshold
- `satuan` (VARCHAR) - Unit of measure (pcs, sheet, roll, etc.)
- `harga` (INT) - Price per unit
- `status` (VARCHAR) - 'aktif' or 'nonaktif'
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Key Formula:**
```
stok_available = stok - stok_reserved
```

**Inventory Rules:**

**RULE 1 - Create Product:**
```
When creating new product:
  stok_available = stok (user input)
  stok_reserved = 0
```

**RULE 1b - Update Product (Edit Stock):**
```
When editing product stok field:
  stok_available = stok_baru - stok_reserved
  (NEVER update stok_available directly - always recalculate!)
```

---

#### **B. PO (Purchase Order) Table**

**Core Columns:**
- `id` (INT, PRIMARY KEY)
- `nomor_po` (VARCHAR UNIQUE) - PO number (auto-generated, format: PCB-NNN or PO-MMYY-NNN)
- `tanggal` (DATE) - Order date
- `customer_id` (INT FOREIGN KEY) → customers.id
- `status` (VARCHAR) - 'draft', 'approved', 'completed', 'canceled'
- `status_stok` (ENUM) - Stok tracking status: 'draft', 'reserved', 'partial', 'ready', 'completed'
- `approval_date` (DATETIME) - When PO was approved
- `notes` (TEXT)
- `created_at` (TIMESTAMP)

---

#### **C. PO_ITEMS Table**

**Core Columns:**
- `id` (INT, PRIMARY KEY)
- `po_id` (INT FOREIGN KEY) → po.id
- `produk_id` (INT FOREIGN KEY) → produk.id
- `kode_material` (VARCHAR) - Material code
- `nama_material` (VARCHAR) - Material name
- `uom` (VARCHAR) - Unit of measure
- `qty` (INT) - Quantity ordered
- `qty_available` (INT) - Qty immediately available (no backorder)
- `qty_pending` (INT) - Qty on backorder
- `harga_satuan` (FLOAT/DECIMAL) - Unit price
- `diskon` (FLOAT) - Discount percentage (0-100)
- `amount` (DECIMAL) - Total amount after discount
- `reserved_qty` (INT DEFAULT 0) - Added by DATABASE_STOK_TRACKING.sql
- `is_reserved` (ENUM 'no'/'yes' DEFAULT 'no') - Flag for reserved items
- `keterangan` (TEXT)
- `created_at` (TIMESTAMP)

**Amount Calculation:**
```
subtotal = qty * harga_satuan
discount_amount = subtotal * (diskon / 100)
amount = subtotal - discount_amount
```

---

#### **D. STOK_LOG Table** (Audit Trail)

**Added by:** [DATABASE_STOK_TRACKING.sql](DATABASE_STOK_TRACKING.sql)

```sql
CREATE TABLE stok_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produk_id INT NOT NULL,
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
    FOREIGN KEY (produk_id) REFERENCES produk(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX (produk_id, created_at)
);
```

---

#### **E. Other Related Tables**

- **customers** - Customer information (perusahaan, nomor_customer, etc.)
- **spk_items** - SPK (Surat Perintah Kerja) items for production
- **pengeluaran** - Outgoing stock (with stok_before, stok_after tracking)
- **verifikasi** - Verification of incoming goods
- **surat_jalan** - Delivery letter
- **penerimaan** - Stock receiving

---

## 2️⃣ AUTO CODE GENERATION FUNCTIONS

**File:** [src/functions.php](src/functions.php)

### Function: `generateAutoCode($codeType)`

Maps code types to table/field configurations:

```php
$codeMap = [
    'PCB'    => ['table' => 'po', 'prefix' => 'PCB', 'field' => 'nomor_po'],
    'PRODUK' => ['table' => 'produk', 'prefix' => 'PCB', 'field' => 'kode_produk'],
    'PROD'   => ['table' => 'produk', 'prefix' => 'PROD', 'field' => 'kode_produk'],
    'MRK'    => ['table' => 'customers', 'prefix' => 'MRK', 'field' => 'nomor_customer'],
    'SPK'    => ['table' => 'spk', 'prefix' => 'SPK', 'field' => 'nomor_spk'],
    'USR'    => ['table' => 'users', 'prefix' => 'USR', 'field' => 'username'],
    'MNG'    => ['table' => 'users', 'prefix' => 'MNG', 'field' => 'username'],
    'GDG'    => ['table' => 'users', 'prefix' => 'GDG', 'field' => 'username'],
    'SJ'     => ['table' => 'surat_jalan', 'prefix' => 'SJ', 'field' => 'nomor_sj'],
    'PNR'    => ['table' => 'penerimaan', 'prefix' => 'PNR', 'field' => 'nomor_penerimaan'],
    'PNG'    => ['table' => 'pengeluaran', 'prefix' => 'PNG', 'field' => 'nomor_pengeluaran'],
    'VRF'    => ['table' => 'verifikasi', 'prefix' => 'VRF', 'field' => 'nomor_verifikasi'],
];
```

**Format:** `PREFIX-NNN` (3-digit sequence)

**Examples:**
- `generateAutoCode('PRODUK')` → `PCB-001`, `PCB-002`, etc.
- `generateAutoCode('SPK')` → `SPK-001`, `SPK-002`, etc.

### Function: `getNextCode($prefix, $table, $field)`

Core logic for auto-code generation:
1. Query MAX number with matching prefix
2. Increment by 1
3. Format with leading zeros (3 digits)

```php
$sql = "SELECT MAX(CAST(SUBSTRING($field, LOCATE('-', $field) + 1) AS UNSIGNED)) as max_num 
        FROM $table 
        WHERE $field LIKE ?";
return sprintf('%s-%03d', $prefix, $nextNum);
```

### Function: `generatePONumber()`

**Special format for PO:** `PO-MMYY-NNN`

- **MM** = Month (01-12)
- **YY** = Year (2-digit, e.g., 26 for 2026)
- **NNN** = Sequence within that month

**Examples:**
- `PO-1126-001` (November 2026, 1st PO)
- `PO-1126-002` (November 2026, 2nd PO)
- `PO-1201-001` (December 2026, 1st PO)

---

## 3️⃣ PRODUCT CREATION PAGE

**File:** [public/marketing/produk/crud/add.php](public/marketing/produk/crud/add.php)

### Form Fields

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `kode` (auto) | TEXT | ✅ | Read-only, auto-generated via `generateAutoCode('PRODUK')` |
| `nama` | TEXT | ✅ | Product name |
| `harga` | NUMBER | ❌ | Price in Rp |
| `stok` | NUMBER | ❌ | Initial physical stock (becomes stok_available) |
| `stok_min` | NUMBER | ❌ | Minimum stock threshold (default: 10) |
| `satuan` | TEXT | ✅ | Unit of measure (pcs, sheet, roll, etc.) |
| `status` | SELECT | ❌ | 'aktif' or 'nonaktif' (default: aktif) |
| `kategori` | TEXT | ❌ | Hard-coded to 'PCB' (read-only) |

### Validation Rules

```php
if (!$data['kode']) $errors[] = 'Kode produk wajib diisi.';
if (!$data['nama']) $errors[] = 'Nama produk wajib diisi.';
if ($data['stok'] < 0) $errors[] = 'Stok tidak boleh negatif.';
```

### Processing Logic

**On Form Submission:**

```php
$stok_input = (int)($_POST['stok'] ?? 0);

// RULE 1: New product
$data = [
    'kode'      => trim($_POST['kode'] ?? ''),
    'nama'      => trim($_POST['nama'] ?? ''),
    'stok'      => $stok_input,
    'stok_min'  => (int)($_POST['stok_min'] ?? 10),
    'satuan'    => trim($_POST['satuan'] ?? 'pcs'),
    'harga'     => (int)($_POST['harga'] ?? 0),
    'status'    => $_POST['status'] ?? 'aktif',
];

Produk::create($data);  // Insert to database
// In Produk::create():
//   stok_available = stok_input
//   stok_reserved = 0
```

---

## 4️⃣ CATEGORY STRUCTURE

### Category Implementation

**Status:** Categories are currently hardcoded as a simple string field, not a separate lookup table.

**Current Usage:**
- All products in this system use category: `'PCB'` (hard-coded)
- Stored in `produk.kategori` VARCHAR field

### Category Files

**Old/Legacy Models:**
- [app/Models/Kategori.php](app/Models/Kategori.php) - Simple class (not actively used)
  ```php
  class Kategori {
      public $id;
      public $nama;
      public function __construct($id, $nama) { ... }
  }
  ```

**In Product Pages:**
- Displayed as read-only field with value "PCB"
- [public/marketing/produk/index.php](public/marketing/produk/index.php) - Lists kategori in table
- [public/marketing/produk/crud/add.php](public/marketing/produk/crud/add.php) - Shows kategori (read-only)
- [public/marketing/produk/crud/detail.php](public/marketing/produk/crud/detail.php) - Displays kategori

**Dokumentasi References:**
- [DOKUMENTASI_LAPORAN.md](DOKUMENTASI_LAPORAN.md) mentions:
  - Tabel 'produk' & 'kategori'
  - kategori_id (INT, Foreign Key)
  - nama_kategori (VARCHAR)

⚠️ **Note:** The documentation references a `kategori` table with kategori_id foreign key, but the current implementation uses a simple `kategori` VARCHAR field. This appears to be a design that was planned but not yet fully implemented.

---

## 5️⃣ DATABASE INITIALIZATION/MIGRATION FILES

### Migration/Schema Files

#### **A. DATABASE_STOK_TRACKING.sql**

**Location:** [DATABASE_STOK_TRACKING.sql](DATABASE_STOK_TRACKING.sql)

**Purpose:** Adds stok tracking columns and creates audit trail infrastructure

**What it does:**

1. **ALTER produk table** - Adds stok tracking columns:
   ```sql
   ALTER TABLE produk ADD COLUMN stok_reserved INT DEFAULT 0 AFTER stok;
   ALTER TABLE produk ADD COLUMN stok_available INT DEFAULT 0 AFTER stok_reserved;
   ALTER TABLE produk ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
   ```

2. **UPDATE existing data:**
   ```sql
   UPDATE produk SET stok_available = (stok - COALESCE(stok_reserved, 0));
   ```

3. **ALTER po_items table:**
   ```sql
   ALTER TABLE po_items ADD COLUMN reserved_qty INT DEFAULT 0 AFTER qty;
   ALTER TABLE po_items ADD COLUMN is_reserved ENUM('no','yes') DEFAULT 'no' AFTER reserved_qty;
   ```

4. **ALTER spk_items table:**
   ```sql
   ALTER TABLE spk_items ADD COLUMN stok_available INT DEFAULT 0;
   ALTER TABLE spk_items ADD COLUMN status_produksi ENUM('pending','on_progress','completed','failed');
   ```

5. **ALTER pengeluaran table:**
   ```sql
   ALTER TABLE pengeluaran ADD COLUMN stok_before INT DEFAULT 0;
   ALTER TABLE pengeluaran ADD COLUMN stok_after INT DEFAULT 0;
   ```

6. **CREATE stok_log table** - Full audit trail for all stok changes

7. **ALTER po table:**
   ```sql
   ALTER TABLE po ADD COLUMN status_stok ENUM('draft','reserved','partial','ready','completed') DEFAULT 'draft';
   ALTER TABLE po ADD COLUMN approval_date DATETIME;
   ```

8. **CREATE VIEWs for real-time reporting:**
   - `v_stok_realtime` - Live inventory status
   - `v_po_dengan_stok` - PO monitoring with stok details

**Installation:**
```bash
mysql -u root -p inventaris < DATABASE_STOK_TRACKING.sql
```

---

#### **B. Other Documentation Files**

| File | Purpose |
|------|---------|
| [SISTEM_INVENTORY_REALTIME.md](SISTEM_INVENTORY_REALTIME.md) | Complete documentation of real-time inventory system, alur integrasi PO→SPK→Verifikasi→Pengeluaran |
| [DOKUMENTASI_LAPORAN.md](DOKUMENTASI_LAPORAN.md) | Report system documentation with database queries |
| [DOKUMENTASI_STOK_INVENTORY.php](DOKUMENTASI_STOK_INVENTORY.php) | Stock inventory documentation in PHP comments |
| [IMPLEMENTASI_CHECKLIST.md](IMPLEMENTASI_CHECKLIST.md) | Implementation checklist for database setup |
| [STRICT_VALIDATION_GUIDE.md](STRICT_VALIDATION_GUIDE.md) | Strict validation rules documentation |
| [CHECKLIST_STOK.php](CHECKLIST_STOK.php) | Database validation checklist script |

---

## 6️⃣ KEY MODEL CLASSES

### Produk Model

**File:** [src/models/Produk.php](src/models/Produk.php)

**Methods:**
- `Produk::all()` - Get all products
- `Produk::find($id)` - Get product by ID
- `Produk::create($data)` - Create new product
- `Produk::update($id, $data)` - Update product (recalculates stok_available)
- `Produk::delete($id)` - Delete product
- `Produk::getWithStokInfo($id)` - Get product with full stock info
- `Produk::checkStokAvailable($produkId, $qty)` - Validate stock availability

**Create Logic (RULE 1):**
```php
$stok_available = $stok;        // stok input from user
$stok_reserved = 0;              // No reservation yet
```

**Update Logic (RULE 1b):**
```php
// NEVER update stok_available directly from input
$stok_baru = (int)($data['stok'] ?? $produk['stok']);
$stok_reserved = (int)($produk['stok_reserved'] ?? 0);
$stok_available = $stok_baru - $stok_reserved;  // RECALCULATE
```

---

### PO Model

**File:** [src/models/PO.php](src/models/PO.php)

**Key Methods:**
- `PO::all()` - Get all POs with customer info
- `PO::find($id)` - Get single PO
- `PO::create($data)` - Create basic PO
- `PO::createWithItems($dataPO, $dataItems)` - **MOST IMPORTANT** - Create PO with items in transaction
- `PO::getItems($poId)` - Get PO items
- `PO::calculateTotal($poId)` - Sum all item amounts
- `PO::addItem($data)` - Add item to PO
- `PO::updateItem($id, $data)` - Update PO item
- `PO::deleteItem($id)` - Delete PO item
- `PO::reserveStok($poId, $userId)` - Reserve stock when PO approved
- `PO::unreserveStok($poId, $userId)` - Unreserve stock when PO canceled
- `PO::getPOWithStok($poId)` - Get PO with real-time stock info

**RULE 3 - createWithItems Transaction:**
```php
BEGIN TRANSACTION
  1. INSERT into po (master)
  2. For each item:
     a. INSERT into po_items
     b. UPDATE produk:
        - stok_reserved = stok_reserved + qty
        - stok_available = stok_available - qty
     c. Log to stok_log
COMMIT or ROLLBACK
```

---

### StokTracking Model

**File:** [src/models/StokTracking.php](src/models/StokTracking.php)

**Key Methods:**
- `reserveStok()` - Reserve stock for PO approval
- `unreserveStok()` - Unreserve when PO canceled
- `addStok()` - Add stock when goods arrive (verifikasi)
- `reduceStok()` - Reduce stock on shipment (pengeluaran)
- `adjustmentStok()` - Manual stock adjustment
- `getStokRealtime()` - Get current stock status
- `getStokLog()` - Get audit trail

---

## 7️⃣ INVENTORY FLOW DIAGRAM

```
┌─────────────────────────────────────────────────────┐
│ CUSTOMER ORDER (PO)                                 │
│ Status: Draft → Approved                            │
└────────────┬────────────────────────────────────────┘
             │
             ↓
       [Check stok_available >= qty?]
             │
      ┌──────┴──────┐
      ✅ YES         ❌ NO
      │              │
      ↓              ↓
  RESERVE       [Error: Stok
  STOK          tidak cukup]
  - stok_reserved += qty
  - stok_available -= qty
      │
      ↓
┌─────────────────────────────────────────────────────┐
│ PRODUCTION (SPK)                                    │
│ Status: Pending → On Progress → Completed          │
└────────────┬────────────────────────────────────────┘
             │
             ↓
┌─────────────────────────────────────────────────────┐
│ GOODS ARRIVAL (VERIFIKASI)                          │
│ Status: Draft → Verified                            │
│ Action: ADD STOCK                                   │
│ - stok += qty_ok                                    │
│ - stok_available += qty_ok                          │
└────────────┬────────────────────────────────────────┘
             │
             ↓
┌─────────────────────────────────────────────────────┐
│ SHIPMENT (PENGELUARAN)                              │
│ Status: Draft → Completed                           │
│ Action: REDUCE STOCK                                │
│ - stok -= qty                                       │
│ - stok_available -= qty                             │
│ - stok_reserved -= qty (unreserve)                  │
└─────────────────────────────────────────────────────┘
```

---

## 8️⃣ DATABASE CONNECTIONS

### Primary Config

**File:** [src/config.php](src/config.php)
- Uses PDO (PHP Data Objects)
- Error mode: EXCEPTION
- Default fetch mode: ASSOC (associative arrays)

```php
$pdo = new PDO($dsn, $user, $pass, $options);
```

### Alternative Config

**File:** [config/database.php](config/database.php)
- Returns array with connection parameters
- Used by some legacy code paths

---

## 9️⃣ KEY VALIDATION RULES

### Product Creation
✅ Kode produk - required (auto-generated)  
✅ Nama produk - required (not empty)  
✅ Stok - must be >= 0  
❌ Harga - optional (default: 0)  
❌ Stok Min - optional (default: 10)  

### PO Item Addition
✅ Qty - required (> 0)  
✅ Harga Satuan - required (> 0)  
✅ Produk ID - required  
❌ Diskon - optional (0-100%)  

---

## 🔟 FILE STRUCTURE SUMMARY

```
Inventaris/
├── src/
│   ├── config.php                     ← Database connection
│   ├── functions.php                  ← Auto code generation
│   ├── auth.php                       ← Authentication
│   └── models/
│       ├── Produk.php                 ← Product CRUD
│       ├── PO.php                     ← Purchase Order with items
│       ├── StokTracking.php           ← Stock tracking/auditing
│       ├── Customer.php
│       ├── Penerimaan.php
│       ├── Pengeluaran.php
│       ├── SPK.php
│       ├── SuratJalan.php
│       ├── Verifikasi.php
│       ├── VerifikasiItem.php
│       └── User.php
├── public/
│   ├── marketing/
│   │   ├── produk/
│   │   │   └── crud/
│   │   │       ├── add.php            ← Create product
│   │   │       ├── edit.php
│   │   │       ├── delete.php
│   │   │       └── detail.php
│   │   └── po/
│   │       └── crud/
│   │           ├── add.php            ← Create PO
│   │           ├── edit.php
│   │           └── detail.php
│   └── gudang/
│       ├── penerimaan/                ← Incoming stock
│       ├── pengeluaran/               ← Outgoing stock
│       └── verif/                     ← Verification
├── config/
│   └── database.php                   ← Alt DB config
├── DATABASE_STOK_TRACKING.sql         ← Migration script
├── SISTEM_INVENTORY_REALTIME.md       ← System documentation
└── [Other documentation files]
```

---

## SUMMARY TABLE

| Item | Location | Key Points |
|------|----------|-----------|
| **DB Schema** | Multiple sources | produk, po, po_items, stok_log |
| **Auto Code Gen** | src/functions.php | generateAutoCode(), generatePONumber() |
| **Product Add** | public/marketing/produk/crud/add.php | RULE 1: stok_available=stok, stok_reserved=0 |
| **Categories** | Hardcoded as 'PCB' | String field, not separate table |
| **Migration** | DATABASE_STOK_TRACKING.sql | Adds tracking columns and audit trail |
| **Produk Model** | src/models/Produk.php | RULE 1b: recalculate stok_available on update |
| **PO Model** | src/models/PO.php | RULE 3: transactional item insertion with stok update |
| **Stock Tracking** | src/models/StokTracking.php | Reserve/unreserve/add/reduce operations |

