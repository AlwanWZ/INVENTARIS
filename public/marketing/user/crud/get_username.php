<?php
// Pastikan path ke auth.php sesuai jika ingin file ini juga diproteksi session
// require_once '../../../../src/auth.php'; 

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'inventaris');

if ($conn->connect_error) {
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

$role = $_GET['role'] ?? '';
$prefixes = [
    'marketing' => 'MRK',
    'manager'   => 'MNG',
    'gudang'    => 'GDG'
];

$prefix = $prefixes[$role] ?? '';

if (!$prefix) {
    echo json_encode(['username' => '']);
    exit;
}

// Ambil jumlah user berdasarkan role
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = ?");
$stmt->bind_param('s', $role);
$stmt->execute();
$result = $stmt->get_result();

$count = ($result && $row = $result->fetch_assoc()) ? (int)$row['total'] : 0;
$stmt->close();

// Buat format username (contoh: MRK-001)
$nextNum = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
$username = $prefix . '-' . $nextNum;

// Kembalikan data dalam format JSON agar bisa dibaca oleh JavaScript di frontend
echo json_encode(['username' => $username]);

$conn->close();
?>