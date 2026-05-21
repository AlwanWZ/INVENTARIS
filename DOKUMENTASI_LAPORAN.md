# 📊 DOKUMENTASI SISTEM LAPORAN - InventorySys

## Pendahuluan

Sistem InventorySys memiliki **3 laporan utama** yang dirancang untuk monitoring dan analisis operasional bisnis. Setiap laporan mengambil data real-time dari database dan menyajikannya dalam bentuk yang mudah dipahami dengan visualisasi grafik dan ringkasan KPI.

---

## 🗂️ Daftar Laporan

| No | Laporan | Module | Akses | Lokasi File |
|----|---------|--------|-------|-------------|
| 1 | **Laporan Purchase Order (PO)** | Marketing | Marketing, Manager | `/public/marketing/laporan_order/` |
| 2 | **Laporan Persediaan** | Gudang | Gudang, Manager | `/public/gudang/laporan_persediaan/` |
| 3 | **Laporan Produksi (Setoran Barang Jadi)** | Gudang | Gudang, Manager | `/public/gudang/laporan_produksi/` |

---

---

## 1️⃣ LAPORAN PURCHASE ORDER (PO)

### 📍 Lokasi & Akses
- **File**: `public/marketing/laporan_order/index.php`
- **URL**: `/Inventaris/public/marketing/laporan_order/index.php`
- **Role**: Marketing, Manager
- **Export**: `public/marketing/laporan_order/export.php`

### 📋 Tujuan/Fungsi
Laporan ini menampilkan rekapitulasi semua Purchase Order (PO) yang telah dibuat oleh Marketing. Laporan ini membantu untuk:
- Memantau jumlah dan nilai PO dalam periode tertentu
- Menganalisis tren PO per bulan
- Melihat customer dengan volume PO tertinggi (Top 5 Customer)
- Memantau status approval PO (Draft, Approved, Completed, Rejected)

### 🔄 Alur Data

```
User (Marketing/Manager)
    ↓
Dashboard/Menu → Laporan Order
    ↓
laporan_order/index.php (Load data & render)
    ↓
Database: Tabel 'po' & 'customers'
    ↓
Display dengan Filter & Visualisasi
```

### 📊 Sumber Data (Tabel Database)

#### Tabel Utama: `po`
```
po
├── id (INT, Primary Key)
├── nomor_po (VARCHAR) - Nomor unik PO
├── tanggal (DATE) - Tanggal pembuatan PO
├── customer_id (INT, Foreign Key) → customers.id
├── status (VARCHAR) - Draft, Approved, Completed, Rejected
├── total (DECIMAL) - Nilai total PO
└── notes (TEXT) - Catatan tambahan
```

#### Tabel Relasi: `customers`
```
customers
├── id (INT, Primary Key)
├── perusahaan (VARCHAR) - Nama perusahaan customer
├── telepon (VARCHAR)
├── email (VARCHAR)
├── alamat (TEXT)
└── ...fields lainnya
```

### 📈 Field yang Ditampilkan

#### Di Halaman Laporan (index.php)
| Field | Sumber | Deskripsi |
|-------|--------|-----------|
| Nomor PO | po.nomor_po | ID unik PO |
| Perusahaan/Customer | customers.perusahaan | Nama customer |
| Tanggal | po.tanggal | Kapan PO dibuat |
| Status | po.status | Status PO saat ini |
| Total | po.total | Nilai nominal PO |

#### Di Ringkasan/KPI
- **Total PO**: Jumlah seluruh PO
- **Total Nilai Transaksi**: Sum semua po.total
- **Approved**: Count where status = 'approved'
- **Completed**: Count where status = 'completed'  
- **Rejected**: Count where status = 'rejected'
- **Draft**: Count where status = 'draft'

#### Di Grafik
- **Bar Chart**: Jumlah PO per Bulan (group by YYYY-MM)
- **Top 5 Customer**: Pelanggan dengan nilai PO terbesar

### 🔍 Filter yang Tersedia

Pengguna dapat memfilter laporan dengan:

```php
$filter = [
    'from'     => $_GET['from']     ?? '',      // Dari tanggal
    'to'       => $_GET['to']       ?? '',      // Sampai tanggal
    'customer' => $_GET['customer'] ?? '',      // ID customer
    'status'   => $_GET['status']   ?? '',      // Status PO
    'search'   => $_GET['search']   ?? '',      // Search nomor PO atau nama customer
];
```

### 🛠️ Query Database

```php
function getFilteredPOs($filter) {
    global $pdo;
    
    $sql = "SELECT po.*, customers.perusahaan
            FROM po
            LEFT JOIN customers ON po.customer_id = customers.id
            WHERE 1=1";
    
    $params = [];
    
    if ($filter['from']) {
        $sql .= " AND po.tanggal >= :from";
        $params['from'] = $filter['from'];
    }
    
    if ($filter['to']) {
        $sql .= " AND po.tanggal <= :to";
        $params['to'] = $filter['to'];
    }
    
    if ($filter['customer']) {
        $sql .= " AND po.customer_id = :customer";
        $params['customer'] = $filter['customer'];
    }
    
    if ($filter['status']) {
        $sql .= " AND po.status = :status";
        $params['status'] = $filter['status'];
    }
    
    if ($filter['search']) {
        $sql .= " AND (po.nomor_po LIKE :search OR customers.perusahaan LIKE :search)";
        $params['search'] = "%{$filter['search']}%";
    }
    
    $sql .= " ORDER BY po.tanggal DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### 💾 Export Excel

- **File**: `public/marketing/laporan_order/export.php`
- **Format**: Tab-separated values (.xls)
- **Isi**: Tabel PO dengan kolom: No, Nomor PO, Customer, Tanggal, Status, Total
- **Cara Akses**: Click tombol "Download Excel" → auto generate file

---

---

## 2️⃣ LAPORAN PERSEDIAAN (STOK BARANG)

### 📍 Lokasi & Akses
- **File**: `public/gudang/laporan_persediaan/index.php`
- **URL**: `/Inventaris/public/gudang/laporan_persediaan/index.php`
- **Role**: Gudang, Manager
- **Export**: Excel (built-in di halaman)

### 📋 Tujuan/Fungsi
Laporan ini menampilkan rekapitulasi stok barang di gudang. Fungsi utama:
- Memantau jumlah stok setiap produk (FG = Finish Good, OK)
- Memantau stok barang cacat (NG = Not Good)
- Mengidentifikasi produk dengan stok kritis atau habis
- Tracking stok minimum per produk
- Export data untuk keperluan administratif

### 🔄 Alur Data

```
User (Gudang/Manager)
    ↓
Dashboard/Menu Gudang → Laporan Persediaan
    ↓
laporan_persediaan/index.php (Load & filter)
    ↓
Database: Tabel 'produk' & 'kategori'
    ↓
Calculate Summary (FG, NG, Kritis)
    ↓
Display Table + Export
```

### 📊 Sumber Data (Tabel Database)

#### Tabel Utama: `produk`
```
produk
├── id (INT, Primary Key)
├── kode (VARCHAR) - Kode unik produk
├── nama (VARCHAR) - Nama produk
├── kategori_id (INT, Foreign Key) → kategori.id
├── satuan (VARCHAR) - Unit (pcs, box, kg, dll)
├── stok (INT) - Stok barang jadi (FG/OK)
├── stok_ng (INT) - Stok barang cacat (Not Good)
├── stok_min (INT) - Minimal stok sebelum kritis
├── harga (DECIMAL)
└── ...fields lainnya
```

#### Tabel Relasi: `kategori`
```
kategori
├── id (INT, Primary Key)
├── nama_kategori (VARCHAR) - Kategori produk
└── ...fields lainnya
```

### 📈 Field yang Ditampilkan

#### Di Tabel Utama
| Field | Sumber | Deskripsi |
|-------|--------|-----------|
| No | - | Urutan nomor |
| Kode | produk.kode | ID unik produk |
| Nama Produk | produk.nama | Nama lengkap produk |
| Kategori | kategori.nama_kategori | Kategori produk |
| Satuan | produk.satuan | Unit measurement |
| Stok FG | produk.stok | Jumlah barang yang OK |
| Stok NG | produk.stok_ng | Jumlah barang cacat |
| Stok Min | produk.stok_min | Minimal stok sebelum kritis |
| Status | (calculated) | Habis/Kritis/OK |

#### Di Ringkasan/KPI
- **Jenis Produk**: Count produk
- **Total Stok FG**: Sum(produk.stok)
- **Total Stok NG**: Sum(produk.stok_ng)
- **Stok Kritis**: Count(produk where stok <= stok_min)

#### Status Perhitungan:
```php
if ($stok <= 0)        → 'Habis' (danger)
elseif ($stok <= min)  → 'Kritis' (danger)
elseif ($stok <= min*3)→ 'Caution' (warning)
else                   → 'OK' (normal)
```

### 🔍 Filter yang Tersedia

```php
$search   = $_GET['search']   ?? '';    // Cari nama/kode produk
$kategori = $_GET['kategori'] ?? '';   // Filter by kategori_id
```

### 🛠️ Query Database

```php
$sql = "SELECT p.id, p.kode, p.nama, p.satuan, p.stok, p.stok_ng, 
               COALESCE(p.stok_min, 10) AS stok_min, k.nama_kategori 
        FROM produk p 
        LEFT JOIN kategori k ON p.kategori_id = k.id 
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($kategori) {
    $sql .= " AND p.kategori_id = ?";
    $params[] = $kategori;
}

$sql .= " ORDER BY p.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### 💾 Export Excel

- **Tombol**: "Export Excel" di halaman
- **Format**: `.xls` (HTML table)
- **Isi**: Kolom: No, Kode, Nama Produk, Kategori, Satuan, Stok FG, Stok NG, Stok Min, Status
- **Auto-download**: Langsung download tanpa konfirmasi

---

---

## 3️⃣ LAPORAN PRODUKSI (SETORAN BARANG JADI)

### 📍 Lokasi & Akses
- **File**: `public/gudang/laporan_produksi/index.php`
- **URL**: `/Inventaris/public/gudang/laporan_produksi/index.php`
- **Role**: Gudang, Manager
- **Cetak**: Built-in print functionality

### 📋 Tujuan/Fungsi
Laporan ini menampilkan riwayat setoran barang jadi (Finish Good = FG) dari produksi/supplier ke gudang. Fungsi:
- Monitoring jumlah barang yang diterima dan lolos QC
- Tracking barang cacat (NG) dalam setiap setoran
- Calculate pass rate (tingkat kelulusan QC)
- Identifikasi PIC (Person in Charge) per setoran
- Analisis tren produksi per periode

### 🔄 Alur Data

```
User (Gudang/Manager)
    ↓
Dashboard/Menu Gudang → Laporan Produksi
    ↓
laporan_produksi/index.php (Load with date range)
    ↓
Database: verifikasi, verifikasi_items, penerimaan, produk, users
    ↓
Calculate KPI (Total, OK, NG, Pass Rate)
    ↓
Display dengan filter tanggal & print option
```

### 📊 Sumber Data (Tabel Database)

#### Tabel Utama: `verifikasi`
```
verifikasi
├── id (INT, Primary Key)
├── penerimaan_id (INT, FK) → penerimaan.id
├── jenis (VARCHAR) - 'finish_good' atau 'not_good'
├── tanggal (DATE) - Tanggal verifikasi
├── pic (INT, FK) → users.id (Person In Charge)
└── ...fields lainnya
```

#### Tabel Detail: `verifikasi_items`
```
verifikasi_items
├── id (INT, Primary Key)
├── verifikasi_id (INT, FK) → verifikasi.id
├── produk_id (INT, FK) → produk.id
├── qty_ok (INT) - Jumlah barang yang lolos
├── qty_ng (INT) - Jumlah barang cacat
└── ...fields lainnya
```

#### Tabel Relasi: `penerimaan`
```
penerimaan
├── id (INT, Primary Key)
├── nomor_penerimaan (VARCHAR) - No dokumen
├── tanggal (DATE)
└── ...fields lainnya
```

#### Tabel Relasi: `produk`
```
produk
├── id (INT, Primary Key)
├── nama (VARCHAR)
└── ...fields lainnya
```

#### Tabel Relasi: `users`
```
users
├── id (INT, Primary Key)
├── username (VARCHAR) - Nama user/PIC
└── ...fields lainnya
```

### 📈 Field yang Ditampilkan

#### Di Tabel Utama
| Field | Sumber | Deskripsi |
|-------|--------|-----------|
| Tanggal | verifikasi.tanggal | Tanggal setoran |
| No. Dokumen | penerimaan.nomor_penerimaan | Nomor dokumen penerimaan |
| Nama Barang | produk.nama | Nama produk |
| Qty Disetor | vi.qty_ok + vi.qty_ng | Total yang diterima |
| Qty OK | verifikasi_items.qty_ok | Jumlah lolos |
| Qty NG | verifikasi_items.qty_ng | Jumlah cacat |
| Pass Rate | (Qty OK / Qty Disetor) * 100 | Persentase kelulusan |
| PIC | users.username | Siapa yang verificate |
| Detail | - | Link ke detail page |

#### Di Ringkasan/KPI
- **Total Disetor**: Sum(qty_ok + qty_ng) untuk periode
- **Lolos QC (FG)**: Sum(qty_ok)
- **Cacat (NG)**: Sum(qty_ng)
- **Pass Rate**: (Total OK / Total Disetor) * 100

#### Status Warna:
```
Pass Rate >= 95% → Green (OK)
Pass Rate >= 80% → Yellow (Warning)
Pass Rate <  80% → Red (Danger)
```

### 🔍 Filter yang Tersedia

```php
$tgl_awal  = $_GET['tgl_awal']  ?? date('Y-m-01');  // Dari tanggal
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');   // Sampai tanggal
```

**Default**: Tampil dari awal bulan s/d hari ini

### 🛠️ Query Database

```php
$sql = "
    SELECT
        v.id,
        p.nomor_penerimaan,
        v.tanggal,
        u.username        AS pic_name,
        pr.nama           AS produk_nama,
        vi.qty_ok + vi.qty_ng AS qty_disetor,
        vi.qty_ok,
        vi.qty_ng
    FROM verifikasi v
    LEFT JOIN penerimaan p  ON v.penerimaan_id = p.id
    LEFT JOIN users u       ON v.pic = u.id
    JOIN verifikasi_items vi ON vi.verifikasi_id = v.id
    LEFT JOIN produk pr     ON vi.produk_id = pr.id
    WHERE v.jenis = 'finish_good'
      AND v.tanggal BETWEEN ? AND ?
    ORDER BY v.tanggal DESC, v.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tgl_awal, $tgl_akhir]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### 🖨️ Print

- **Tombol**: "Cetak" di halaman
- **Fungsi**: Menggunakan `window.print()` browser native
- **Output**: Format halaman dengan header, filter tersembunyi

---

---

## 🔄 MODEL STRUKTUR KESELURUHAN

```
SISTEM LAPORAN InventorySys
│
├─ LAPORAN PO
│  ├─ Model: PO.php
│  ├─ DB Tables: po, customers
│  ├─ Input: Date range, Customer, Status
│  ├─ Output: Table, Bar Chart, Top 5 Customer
│  └─ File: public/marketing/laporan_order/index.php
│
├─ LAPORAN PERSEDIAAN
│  ├─ Model: Direct query (config.php)
│  ├─ DB Tables: produk, kategori
│  ├─ Input: Search, Kategori
│  ├─ Output: Table dengan status stok, Summary KPI
│  └─ File: public/gudang/laporan_persediaan/index.php
│
└─ LAPORAN PRODUKSI
   ├─ Model: Direct query (config.php)
   ├─ DB Tables: verifikasi, verifikasi_items, penerimaan, produk, users
   ├─ Input: Date range (Tgl Awal - Tgl Akhir)
   ├─ Output: Table setoran, Summary KPI, Pass Rate analysis
   └─ File: public/gudang/laporan_produksi/index.php
```

---

## 📁 FILE SUMMARY

```
project/
│
├─ src/
│  ├─ config.php              → Database connection
│  ├─ auth.php                → Authentication
│  └─ models/
│     ├─ PO.php               → Model untuk PO
│     ├─ Produk.php           → Model untuk Produk
│     ├─ Customer.php         → Model untuk Customer
│     ├─ User.php             → Model untuk User
│     └─ ... (model lainnya)
│
├─ public/
│  │
│  ├─ dashboard.php           → Main dashboard
│  │
│  ├─ marketing/
│  │  └─ laporan_order/
│  │     ├─ index.php         → Laporan PO (Main)
│  │     └─ export.php        → Export Excel PO
│  │
│  └─ gudang/
│     ├─ laporan_persediaan/
│     │  └─ index.php         → Laporan Persediaan (Main)
│     │
│     └─ laporan_produksi/
│        └─ index.php         → Laporan Produksi (Main)
│
└─ templates/
   ├─ nav.php                 → Sidebar navigation
   ├─ header.php              → Page header
   └─ footer.php              → Page footer
```

---

## 🎯 QUICK START - MENGAKSES LAPORAN

### Untuk Marketing:
1. Login dengan role **marketing**
2. Sidebar → Marketing → **Laporan Order**
3. Pilih filter (tanggal, customer, status) → **Terapkan**
4. Klik **Cetak** atau **Download Excel** untuk export

### Untuk Gudang:
1. Login dengan role **gudang**
2. Sidebar → Gudang → **Laporan Persediaan** atau **Laporan Produksi**
3. **Laporan Persediaan**: Filter by nama/kategori, export Excel
4. **Laporan Produksi**: Filter by tanggal, lihat pass rate, cetak laporan

### Untuk Manager:
1. Login dengan role **manager**
2. Akses ke semua laporan di atas
3. Gunakan untuk monitoring keseluruhan operasional

---

## 🔗 RELASI DATA SUMMARY

```
customers ←→ po (1:M)
   │
   └→ laporan_order/index.php

produk ←→ kategori (M:1)
   │
   ├→ laporan_persediaan/index.php
   └→ verifikasi_items

verifikasi ←→ verifikasi_items ←→ produk (1:M)
   │
   ├→ penerimaan (M:1)
   ├→ users/PIC (M:1)
   └→ laporan_produksi/index.php
```

---

## ⚠️ CATATAN PENTING

1. **Performa**: Laporan dengan data besar (ribuan record) mungkin lambat. Pertimbangkan pagination atau indexing pada kolom filter.

2. **Real-time**: Semua laporan mengambil data real-time dari database, bukan cached data.

3. **Access Control**: Setiap laporan memiliki pengecekan role di awal file untuk security.

4. **Export**: Hanya format Excel tersedia untuk persediaan. Format lain bisa ditambahkan.

5. **Filter**: Kosongkan filter untuk melihat seluruh data tanpa pembatasan.

---

**Dokumen ini dibuat untuk dokumentasi sistem InventorySys v1.0**
