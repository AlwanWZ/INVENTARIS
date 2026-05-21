<?php
session_start();
require_once '../../../../src/auth.php';
require_once '../../../../src/models/User.php';

include '../../../../templates/header.php';
include '../../../../templates/nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    User::delete((int)$_POST['id']);
    header('Location: ../index.php?deleted=1');
    exit;
}

header('Location: ../index.php');
exit;
?>
<?php include '../../../../templates/footer.php'; ?>
exit;
