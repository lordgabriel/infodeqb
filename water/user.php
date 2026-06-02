<?php
/**
 * AJAX endpoint — devolve <option> de utilizadores para um dado responsável
 * POST: resp (int), user (int, opcional — marca como selected)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';

require_once ROOT_DIR . '/infodeqb/inc/admins.php';
if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsWater))) {
    http_response_code(403);
    exit;
}

$respId      = (int)($_POST['resp'] ?? 0);
$selectedUser = (int)($_POST['user'] ?? 0);

if (!$respId) exit;

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sth = $pdo->prepare(
    'SELECT userid, user FROM infodeqb_water_users WHERE idresp = ? AND ativo = 1 ORDER BY user'
);
$sth->execute([$respId]);

while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
    $sel = ($row['userid'] == $selectedUser) ? ' selected' : '';
    echo '<option value="' . (int)$row['userid'] . '"' . $sel . '>'
       . htmlspecialchars($row['user']) . '</option>';
}

Database::disconnect();
