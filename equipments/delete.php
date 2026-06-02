<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/permissions.php';

$userIdNum = (int)preg_replace('/\D/', '', $_SESSION['Code'] ?? '');
$equipId   = (int)($_GET['id'] ?? 0);

if (!$equipId) { header('Location: index.php'); exit; }

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('SELECT * FROM infodeqb_equipmentdeq WHERE equipment_id = ?');
$stmt->execute([$equipId]);
$eq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eq || !podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq)) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

// Eliminar imagens associadas
$imgDir = __DIR__ . '/img/' . $eq['Laboratorio'] . '/';
foreach (glob($imgDir . $equipId . '.*') ?: [] as $img) {
    unlink($img);
}

$pdo->prepare('DELETE FROM infodeqb_equipmentdeq WHERE equipment_id = ?')->execute([$equipId]);
Database::disconnect();

$_SESSION['_equip_flash'] = ['Equipamento eliminado.', 'success'];
header('Location: index.php');
exit;
