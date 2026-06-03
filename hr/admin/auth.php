<?php
/**
 * Verificação de acesso admin para o módulo HR.
 * Inclui este ficheiro no topo dos scripts em hr/admin/ que precisam de proteção.
 * Redireciona para denied.php se o utilizador não for administrador.
 *
 * Pré-requisito: session_start() e require deqbwww.php já chamados.
 */
require_once ROOT_DIR . '/infodeqb/inc/admins.php'; // define $isAdmin, $_iqAdminsHr

$_isHrAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsHr);
if (!$_isHrAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}
unset($_isHrAdmin);
