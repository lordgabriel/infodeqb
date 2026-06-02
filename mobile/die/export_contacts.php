<?php
require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php'; 
include $_SERVER['DOCUMENT_ROOT'].'/infodeqb/session.php';
$pdo = Database::connect();

if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso restrito');
}

// Buscar todos os contactos
$stmt = $pdo->query("SELECT empresa, nome, email FROM infodeqb_company_contacts ORDER BY empresa, nome");
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Forçar download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=contactos.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Empresa', 'Nome', 'Email']); // cabeçalho

foreach($contacts as $c) {
    fputcsv($output, [$c['empresa'], $c['nome'], $c['email']]);
}
fclose($output);
exit;
