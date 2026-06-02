<?php
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
$pageTitle = t('DENIED_TITLE');
include ROOT_DIR . '/infodeqb/inc/header.php';

// Determina a página anterior. Se não existir (ex: acesso direto), vai para a raiz por segurança.
$url_anterior = $_SERVER['HTTP_REFERER'] ?? (HTTP_DIR . '/infodeqb/');
?>

<div class="iq-page-header">
  <h1><i class="fas fa-lock fa-sm mr-2 text-muted"></i><?= t('DENIED_TITLE') ?></h1>
</div>

<div class="alert alert-warning" role="alert">
  <div>
    <strong><?= t('DENIED_MSG') ?></strong><br>
    <small>Será redirecionado para a página anterior em 5 segundos.</small>
  </div>
  <a href="<?php echo htmlspecialchars($url_anterior); ?>" class="btn btn-sm btn-secondary ml-3 flex-shrink-0"><?= t('BACK') ?></a>
</div>

<meta http-equiv="refresh" content="5;URL='<?php echo htmlspecialchars($url_anterior); ?>'">

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>