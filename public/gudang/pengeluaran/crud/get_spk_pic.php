<?php
require_once '../../../../src/config.php';
$spk_id = (int)$_GET['spk_id'];
$stmt = $pdo->prepare("SELECT pic FROM spk WHERE id = ?");
$stmt->execute([$spk_id]);
echo json_encode(['pic_id' => $stmt->fetchColumn()]);