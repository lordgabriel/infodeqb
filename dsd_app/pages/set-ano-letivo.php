<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ano_id'])) {
    $anoId = (int)$_POST['ano_id'];
    $stmt = getDB()->prepare("SELECT * FROM infodeqb_dsd_ano_letivo WHERE id=?");
    $stmt->execute([$anoId]);
    $ano = $stmt->fetch();
    if ($ano) {
        $_SESSION['ano_letivo_id']         = $ano['id'];
        $_SESSION['ano_letivo_designacao'] = $ano['designacao'];
    }
}

$redirect = $_POST['redirect'] ?? '/';
if (!preg_match('#^/#', $redirect)) $redirect = '/';
header('Location: ' . $redirect);
exit;