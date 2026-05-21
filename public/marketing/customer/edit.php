<?php
session_start();
require_once '../../../src/auth.php';
require_once '../../../src/models/Customer.php';

$id       = (int)($_GET['id'] ?? 0);
$customer = Customer::find($id);
if (!$customer) { header('Location: index.php'); exit; }

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nama'       => trim($_POST['nama'] ?? ''),
        'perusahaan' => trim($_POST['perusahaan'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'no_hp'      => trim($_POST['no_hp'] ?? ''),
        'alamat'     => trim($_POST['alamat'] ?? ''),
        'kota'       => trim($_POST['kota'] ?? ''),
        'status'     => $_POST['status'] ?? 'aktif',
    ];
    if (!$data['nama']) $errors[] = 'Nama wajib diisi.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (!$errors) {
        Customer::update($id, $data);
        header('Location: index.php?updated=1');
        exit;
    }
    // Merge agar form tampilkan nilai POST saat error
    $customer = array_merge($customer, $data);
}

include 'form.php';
