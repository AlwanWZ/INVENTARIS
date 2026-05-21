# Sistem Inventaris Barang

Proyek ini adalah aplikasi inventaris barang sederhana berbasis PHP.

## Struktur Folder
- `app/Controllers`: Logika aplikasi (Barang, Kategori)
- `app/Models`: Model data
- `app/Views`: Tampilan HTML
- `config/`: Konfigurasi database
- `public/`: Entry point aplikasi
- `storage/`: Tempat file upload atau cache

## Fitur
- CRUD Barang
- CRUD Kategori
- Laporan stok

## Instalasi
1. Pastikan PHP dan MySQL sudah terpasang.
2. Buat database `inventaris`.
3. Sesuaikan konfigurasi di `config/database.php`.
4. Jalankan aplikasi dengan membuka `public/index.php` di browser.

## Penggunaan
- Akses halaman barang: `index.php?page=barang`
- Akses halaman kategori: `index.php?page=kategori`

## Catatan
- Struktur kode masih sederhana, silakan kembangkan sesuai kebutuhan.
