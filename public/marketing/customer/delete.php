<?php
session_start();
require_once '../../../src/auth.php';
require_once '../../../src/models/Customer.php';

$id = (int)($_POST['id'] ?? 0);
if ($id) Customer::delete($id);
header('Location: index.php');
exit;
