<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

$validado = $_SESSION['user'];
$emailuser = $_SESSION['user'] . "@fe.up.pt";

$id = $_REQUEST['id'];
$id1 = $_REQUEST['id1'];
if (null == $id) {
    header("Location: ../index.php");
}

// print_r ($_POST);

$categoria = null;
$grupo = null;
$os = null;

$rs = '';
$WorkrespError = null;

// Retrieve Language
$sites = array(
        'en' => 'en',
        'pt' => 'pt'
);
$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
// Set default language if a '$lang' version of site is not available
if (! isset($sites[$language])) {
    $language = 'en';
}
include "../lang/lang." . $sites[$language] . ".php";

if (! isset($id, $id1)) {
    // header("Location: ../index.php");
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "SELECT infodeqb_rds_registo.* FROM infodeqb_rds_registo  left JOIN infodeqb_rds_grupo ON infodeqb_rds_registo.grupo=infodeqb_rds_grupo.grupoid left join infodeqb_rds_responsaveis on infodeqb_rds_registo.responsavel=infodeqb_rds_responsaveis.codigo where infodeqb_rds_registo.codigo = ? and infodeqb_rds_registo.autoid = ? and infodeqb_rds_registo.deleted=0  ";

$q = $pdo->prepare($sql);
$q->execute(array(
        $id,
        $id1
));

// load the original record into an array
$data = $q->fetch(PDO::FETCH_ASSOC);

// print_r ($data);

$removeKeys = [
        'autoid',
        'updatedate',
        'dataativo',
        'datainativo',
        'datanotificacao',
        'status'
];

foreach ($removeKeys as $key) {
    unset($data[$key]);
}
$data['status'] = 'N.A.';
echo '<br><br>';
print_r($data);

$colunas = array_keys($data);
$colunas_str = implode(', ', $colunas);
$marcadores = ':' . implode(', :', $colunas);

// $data['curso']='teste';

$sql_duplicate = "INSERT INTO `infodeqb_rds_registo`($colunas_str) VALUES ($marcadores)";
$stmt = $pdo->prepare($sql_duplicate);

foreach ($colunas as $coluna) {
    $stmt->bindValue(":$coluna", $data[$coluna]);
}

$stmt->execute();
Database::disconnect();
header("Location: detail.php?id=$id");

