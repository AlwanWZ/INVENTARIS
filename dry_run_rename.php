<?php
$dir = 'c:\laragon\www\Inventaris';

$searchTerms = [
    'produk' => 'barang',
    'Produk' => 'Barang',
    'PRODUK' => 'BARANG',
    'kode_produk' => 'kode_barang',
    'po_items' => 'pesanan_items',
    'po_id' => 'pesanan_id',
    'produk_id' => 'barang_id',
    'nomor_po' => 'nomor_pesanan',
    'po' => 'pesanan',
    'PO' => 'Pesanan',
    'marketing/po' => 'marketing/pesanan',
    'marketing/produk' => 'marketing/barang'
];

$regexes = [
    // Database tables and columns
    '/\bproduk\b/' => 'barang',
    '/\bkode_produk\b/' => 'kode_barang',
    '/\bpo\b/' => 'pesanan',
    '/\bnomor_po\b/' => 'nomor_pesanan',
    '/\bpo_items\b/' => 'pesanan_items',
    '/\bpo_id\b/' => 'pesanan_id',
    '/\bproduk_id\b/' => 'barang_id',
    
    // PHP variables & array keys
    '/\$produk\b/' => '$barang',
    '/\$po\b/' => '$pesanan',
    '/\$produks\b/' => '$barangs', // though bad plural, let's catch it
    '/\$pos\b/' => '$pesanans',
    
    // Classes
    '/\bclass Produk\b/' => 'class Barang',
    '/\bclass PO\b/' => 'class Pesanan',
    '/\bProduk::/' => 'Barang::',
    '/\bPO::/' => 'Pesanan::',
    '/\bnew Produk\b/' => 'new Barang',
    '/\bnew PO\b/' => 'new Pesanan',
    
    // File includes/requires
    '/(require_once|include_once|require|include)[^;]+Produk\.php/' => function($m) { return str_replace('Produk.php', 'Barang.php', $m[0]); },
    '/(require_once|include_once|require|include)[^;]+PO\.php/' => function($m) { return str_replace('PO.php', 'Pesanan.php', $m[0]); },
    
    // URLs / paths
    '/marketing\/po\b/' => 'marketing/pesanan',
    '/marketing\/produk\b/' => 'marketing/barang',
];

$files_to_modify = [];
$total_changes = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    
    // Skip vendor, node_modules, git, etc
    if (strpos($path, '\\vendor\\') !== false) continue;
    if (strpos($path, '\\node_modules\\') !== false) continue;
    if (strpos($path, '\\.git\\') !== false) continue;
    if (strpos($path, '\\.vscode\\') !== false) continue;
    
    // Only text files
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), ['php', 'html', 'js', 'md', 'css'])) continue;
    
    $content = file_get_contents($path);
    $new_content = $content;
    
    // We apply regexes one by one and check if changed
    $changed = false;
    $file_changes = [];
    foreach ($regexes as $pattern => $replacement) {
        if (preg_match($pattern, $new_content)) {
            $changed = true;
            if (is_callable($replacement)) {
                $new_content = preg_replace_callback($pattern, $replacement, $new_content);
            } else {
                $new_content = preg_replace($pattern, $replacement, $new_content);
            }
            $file_changes[] = $pattern;
        }
    }
    
    if ($changed) {
        $files_to_modify[] = [
            'file' => str_replace($dir, '', $path),
            'changes' => $file_changes
        ];
        $total_changes++;
    }
}

file_put_contents('c:\laragon\www\Inventaris\dry_run_results.json', json_encode([
    'total_files_to_modify' => $total_changes,
    'files' => $files_to_modify
], JSON_PRETTY_PRINT));
echo "Dry run complete. Check dry_run_results.json";
