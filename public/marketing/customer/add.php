<?php
session_start();
require_once '../../../src/auth.php';
require_once '../../../src/models/Customer.php';

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
    try {
        if (!$errors) {
            Customer::create($data);
            header('Location: index.php?created=1');
            exit;
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

include 'form.php';
