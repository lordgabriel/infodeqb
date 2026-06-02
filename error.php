<?php
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
$pageTitle = t('DENIED_TITLE');
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-exclamation-triangle fa-sm mr-2 text-muted"></i><?= t('DENIED_TITLE') ?></h1>
</div>

<div class="alert alert-warning" role="alert">
  <div>
    <strong><?= t('DENIED_MSG') ?></strong><br>
    Em caso de dúvida contacte Luís Martins
    (<a href="mailto:fmartins@fe.up.pt">fmartins@fe.up.pt</a> | Ext.: 3613).<br>
    <small>Será redirecionado para a página inicial em 20 segundos.</small>
  </div>
  <a href="<?php echo HTTP_DIR; ?>/infodeqb/" class="btn btn-sm btn-secondary ml-3 flex-shrink-0"><?= t('NAV_HOME') ?></a>
</div>

<meta http-equiv="refresh" content="20;URL='<?php echo HTTP_DIR; ?>/infodeqb/'">

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
