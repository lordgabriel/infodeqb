<?php
// Redirecionar para a página principal — funcionalidade integrada em index.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
header('Location: ' . HTTP_DIR . '/infodeqb/servdoc/index.php');
exit;
