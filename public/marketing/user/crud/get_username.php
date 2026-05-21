<?php
// Endpoint: get_username.php
require_once '../../../../src/functions.php';

header('Content-Type: application/json');
if (!isset($_GET['role'])) {
    echo json_encode(['username' => '']);
    exit;
}
$role = $_GET['role'];
$prefixMap = ['marketing'=>'MRK','manager'=>'MNG','gudang'=>'GDG'];
$prefix = $prefixMap[$role] ?? '';

if (!$prefix) {
    echo json_encode(['username' => '']);
    exit;
}

// Gunakan helper function untuk generate auto code
$username = getNextCode($prefix, 'users', 'username');
echo json_encode(['username' => $username]);
