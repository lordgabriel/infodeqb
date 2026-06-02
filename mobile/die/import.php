<?php
require 'auth.php';
require 'db.php';

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (empty($_FILES['excel']['tmp_name'])) {
    die('Ficheiro inválido');
}

$spreadsheet = IOFactory::load($_FILES['excel']['tmp_name']);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

$stmt = $pdo->prepare("
INSERT INTO infodeqb_company_contacts
(nome,empresa,email,pais,contacto_feup,criado_por)
VALUES (?,?,?,?,?,?)
");

foreach ($rows as $i => $r) {
    if ($i === 0) continue; // cabeçalho
    if (empty($r[0]) || empty($r[2])) continue;

    $stmt->execute([
        trim($r[0]), // Nome
        trim($r[1]),
        trim($r[2]),
        trim($r[3]),
        trim($r[4]),
        $_SESSION['username']
    ]);
}

header('Location: index.php');
