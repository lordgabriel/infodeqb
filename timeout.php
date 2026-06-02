<?php
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
$pageTitle = 'Aviso';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-info-circle fa-sm me-2 text-muted"></i>Aviso</h1>
</div>

<div class="alert alert-warning" role="alert">
  <div>
    <strong>O acesso a áreas e subáreas disciplinares do DEQB terminou a 31/03/2025.</strong><br>
    Em caso de dúvida contacte a Direção do Departamento
    (<a href="mailto:deqbdir@fe.up.pt">deqbdir@fe.up.pt</a>).
  </div>
  <a href="<?php echo HTTP_DIR; ?>/infodeqb/" class="btn btn-sm btn-secondary ms-3 flex-shrink-0">Início</a>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
