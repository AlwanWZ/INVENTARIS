<?php
require_once __DIR__ . '/src/config.php';

echo "🔧 Fixing PO status ENUM...\n\n";

try {
    // ALTER TABLE po untuk tambah pending_review ke ENUM
    $sql = "ALTER TABLE po 
            MODIFY COLUMN status ENUM('draft','pending_review','approved','rejected','completed') 
            DEFAULT 'draft'";
    
    $pdo->exec($sql);
    
    echo "✅ ENUM status updated successfully!\n\n";
    
    // Verify hasil
    $result = $pdo->query('DESC po')->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($result as $col) {
        if ($col['Field'] === 'status') {
            echo "Column status info:\n";
            echo "- Type: " . $col['Type'] . "\n";
            echo "- Default: " . ($col['Default'] ?? 'NULL') . "\n";
            echo "- Null: " . $col['Null'] . "\n";
        }
    }
    
    echo "\n✨ Status ENUM now supports:\n";
    echo "   ✓ draft\n";
    echo "   ✓ pending_review (NEW)\n";
    echo "   ✓ approved\n";
    echo "   ✓ rejected\n";
    echo "   ✓ completed\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ FIXED! edit.php should work now.\n";
?>
