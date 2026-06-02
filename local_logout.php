<?php
/**
 * Logout local para desenvolvimento em localhost.
 */
session_start();
session_unset();
session_destroy();

require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
header('Location: ' . HTTP_DIR . '/infodeqb/local_login.php');
exit;
