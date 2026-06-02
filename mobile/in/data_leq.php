<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile))) {
    http_response_code(403); echo json_encode([]); exit;
}

$filter = isset($_POST['anoletivo']) ? $_POST['anoletivo']
        : ($_SESSION['mobile_anofilter'] ?? '%');

$pdo = Database::connect();
$sth = $pdo->prepare('SELECT ucs FROM infodeqb_registo_mobilidade WHERE anoletivo LIKE ? ORDER BY nome');
$sth->execute([$filter]);
$result = $sth->fetchAll(PDO::FETCH_ASSOC);

$sth1 = $pdo->prepare('SELECT codigo, sigla FROM infodeqb_unidades_curriculares WHERE curso = "L.EQ" ORDER BY codigo');
$sth1->execute();
$result1 = $sth1->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

$allUcs = '';
foreach ($result as $row) $allUcs .= $row['ucs'] . ';';
$listaucs = array_filter(explode(';', $allUcs));

$siglaMap = array_column($result1, 'sigla', 'codigo');
$mapped = [];
foreach ($listaucs as $cod) {
    if (isset($siglaMap[$cod])) $mapped[] = $siglaMap[$cod];
}

$counts = array_count_values($mapped);
$data = [];
foreach ($counts as $UC => $Inscritos) $data[] = compact('UC', 'Inscritos');

header('Content-Type: application/json');
echo json_encode($data);
