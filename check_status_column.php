<?php
require_once __DIR__ . '/src/config.php';

echo "🔍 Checking PO table schema...\n\n";

$result = $pdo->query('DESC po')->fetchAll(PDO::FETCH_ASSOC);

foreach ($result as $col) {
    if ($col['Field'] === 'status') {
        echo "Column: " . $col['Field'] . "\n";
        echo "Type: " . $col['Type'] . "\n";
        echo "Null: " . $col['Null'] . "\n";
        echo "Key: " . $col['Key'] . "\n";
        echo "Default: " . ($col['Default'] ?? 'NULL') . "\n";
        echo "Extra: " . ($col['Extra'] ?? 'none') . "\n";
    }
}

echo "\n";
echo "Status options dari edit.php:\n";
echo "- draft (5 chars)\n";
echo "- pending_review (14 chars) ← PROBLEM?\n";
echo "- approved (8 chars)\n";
echo "- rejected (8 chars)\n";
?>
