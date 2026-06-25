<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['marketing', 'manager'])) {
    echo "<script>alert('Akses Ditolak!'); window.location.href='../index.php';</script>";
    exit;
}
require_once '../../../src/config.php';

$pesan = '';

// 🚀 LOGIKA SUPER NUKE (BYPASS SEGALA MACAM TABEL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuke_id'])) {
    $nukeId = (int)$_POST['nuke_id'];
    
    try {
        $pdo->beginTransaction();

        // 1. DAFTAR SEMUA KEMUNGKINAN TABEL ANAK (Ejaan udah dikoreksi!)
        $tabel_anak = [
            'log_stok',          // <-- INI YANG BENAR
            'stok_log',          // Jaga-jaga kalau ada
            'verifikasi_items',
            'po_items',
            'spk_items',
            'pengeluaran_items',
            'surat_jalan_items',
            'penerimaan_items',
            'finish_good_items'
        ];

        // Eksekusi bom ke setiap tabel anak satu per satu
        foreach ($tabel_anak as $tabel) {
            try {
                $pdo->prepare("DELETE FROM {$tabel} WHERE produk_id = ?")->execute([$nukeId]);
            } catch (Exception $e) {
                // Cuekin kalau tabelnya nggak ada
            }
        }

        // 2. SETELAH SEMUA ANAKNYA RATA, PENGGAL TABEL UTAMA!
        $stmtDel = $pdo->prepare("DELETE FROM produk WHERE id = ?");
        $stmtDel->execute([$nukeId]);

        $pdo->commit();
        $pesan = "<div style='color:#059669; background:#ecfdf5; padding:15px; border-radius:8px; margin-bottom:20px; border: 1px solid #34d399;'>✅ SUKSES BRUTAL! Produk Gaib beserta seluruh keturunannya di semua tabel resmi dimusnahkan!</div>";

    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan = "<div style='color:#dc3545; background:#fff5f5; padding:15px; border-radius:8px; margin-bottom:20px; border: 1px solid #f87171;'>❌ MASIH GAGAL: " . $e->getMessage() . "</div>";
    }
}

// AMBIL SEMUA PRODUK
$stmt = $pdo->query("SELECT * FROM produk ORDER BY id DESC");
$semuaProduk = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembersih Gaib Produk</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #1f2937; color: white; }
        .btn-nuke { background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-nuke:hover { background: #b91c1c; }
    </style>
</head>
<body>

<div class="container">
    <h1>☣️ Pembersih Produk Gaib (Super Nuke)</h1>
    <p>Halaman ini akan memaksa database menghapus produk dan menyapu bersih histori di <strong>semua tabel</strong>.</p>
    
    <a href="index.php" style="display:inline-block; margin-bottom: 20px; text-decoration: none; color: #2563eb;">&larr; Kembali ke halaman Produk normal</a>

    <?= $pesan ?>

    <table>
        <thead>
            <tr>
                <th>ID DB</th>
                <th>Kode</th>
                <th>Nama Produk</th>
                <th>Status</th>
                <th>Stok Tercatat</th>
                <th>Aksi Eksekusi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($semuaProduk as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['kode'] ?? $p['kode_produk'] ?? '') ?></td>
                <td><strong><?= htmlspecialchars($p['nama'] ?? '') ?></strong></td>
                <td><?= htmlspecialchars($p['status'] ?? 'null') ?></td>
                <td><?= $p['stok'] ?? 0 ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Bantai produk ini tanpa ampun dari SEMUA tabel?');">
                        <input type="hidden" name="nuke_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-nuke">☢️ SUPER NUKE!</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>