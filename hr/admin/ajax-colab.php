<?php
/**
 * Endpoint AJAX: devolver nome/email de um colaborador por código UP.
 * Usado pelo modal "Novo Registo (Admin)" em index.php.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once __DIR__ . '/auth.php';  // só admins

header('Content-Type: application/json; charset=utf-8');

$codigo = trim($_GET['codigo'] ?? '');
if ($codigo === '') {
    echo json_encode(null);
    exit;
}

$pdo = Database::connect();
$sth = $pdo->prepare('SELECT nome, email FROM infodeqb_rds_colaborador WHERE codigo = ? LIMIT 1');
$sth->execute([$codigo]);
$row = $sth->fetch(PDO::FETCH_ASSOC);

echo json_encode($row ?: null);
