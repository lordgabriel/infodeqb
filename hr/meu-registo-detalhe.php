<?php
/**
 * InfoDEQB / HR — Detalhe de um registo (vista do utilizador, só leitura)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$codigoNum = preg_replace('/[^0-9]/', '', $_SESSION['Code'] ?? '');
$autoid    = (int)($_GET['id'] ?? 0);

if (!$codigoNum || !$autoid) {
    header('Location: meu-registo.php'); exit;
}

// Carregar registo — só mostra se pertence ao utilizador autenticado
$q = $pdo->prepare(
    'SELECT r.*, c.nome, c.email, c.emailalt, c.telefone,
            g.grupo_pro, g.grupo_pro AS grupo_nome,
            cat.categoria AS categoria_nome,
            IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS respespaco
     FROM infodeqb_rds_registo r
     JOIN infodeqb_rds_colaborador c       ON c.codigo   = r.codigo
     JOIN infodeqb_rds_grupo g             ON g.grupoid  = r.grupo
     LEFT JOIN infodeqb_rds_categoria cat  ON cat.categoriaid = r.categoria
     LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo   = r.responsavel
     WHERE r.autoid = ? AND r.codigo = ? AND r.deleted = 0'
);
$q->execute([$autoid, $codigoNum]);
$reg = $q->fetch(PDO::FETCH_ASSOC);

if (!$reg) {
    header('Location: meu-registo.php'); exit;
}

// Labs — carregar da tabela relacional
$labNomes = array();
$deqids = getRegistoAcessos($pdo, $autoid);
if ($deqids) {
    $gMap = getGabMap($pdo);
    foreach ($deqids as $did) $labNomes[] = isset($gMap[$did]) ? $gMap[$did] : $did;
}

$sBadge = array(
    'Ativo'    => 'badge-success',
    'Pendente' => 'badge-warning',
    'Novo'     => 'badge-info',
    'Inativo'  => 'badge-secondary',
);
$sBadge = isset($sBadge[$reg['status']]) ? $sBadge[$reg['status']] : 'badge-secondary';

$pageTitle = 'Detalhe do registo';
$mainClass = 'iq-hr-page';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center" style="gap:.75rem">
  <h1 class="mr-auto"><i class="fas fa-file-alt fa-sm me-2 text-muted"></i>Detalhe do registo</h1>
  <a href="meu-registo.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Voltar
  </a>
</div>

<!-- ── Estado ──────────────────────────────────────────────────── -->
<div class="d-flex align-items-center mb-3" style="gap:.5rem">
  <span class="badge <?= $sBadge ?>" style="font-size:.85rem;padding:.35em .65em">
    <?= htmlspecialchars($reg['status']) ?>
  </span>
  <?php
    $dataEstado = null;
    switch ($reg['status']) {
        case 'Novo':     $dataEstado = $reg['dataregisto']; break;
        case 'Ativo':    $dataEstado = $reg['dataativo'];   break;
        case 'Inativo':  $dataEstado = $reg['datainativo']; break;
        case 'Pendente': $dataEstado = $reg['datacica'];    break;
    }
    if ($dataEstado):
  ?>
  <small class="text-muted">desde <?= htmlspecialchars(substr($dataEstado, 0, 10)) ?></small>
  <?php endif; ?>
</div>

<div class="row">

  <!-- Coluna esquerda -->
  <div class="col-md-6">

    <!-- Dados Pessoais -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <i class="fas fa-user me-2 text-muted"></i><strong>Dados Pessoais</strong>
      </div>
      <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0" style="font-size:.85rem">
          <tr><th style="width:45%">Código FEUP</th><td><?= htmlspecialchars($reg['codigo']) ?></td></tr>
          <tr><th>Nome</th><td><?= htmlspecialchars($reg['nome']) ?></td></tr>
          <tr><th>Email FEUP</th><td><?= htmlspecialchars($reg['email'] ?? '—') ?></td></tr>
          <tr><th>Email alternativo</th><td><?= htmlspecialchars($reg['emailalt'] ?? '—') ?></td></tr>
          <tr><th>Telefone</th><td><?php
            list($tfI, $tfN) = parsePhone($reg['telefone'] ?? '');
            echo $tfN ? htmlspecialchars($tfI . ' ' . $tfN) : '—';
          ?></td></tr>
        </table>
      </div>
    </div>

    <!-- Período e Afiliação -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <i class="fas fa-calendar me-2 text-muted"></i><strong>Período e Afiliação</strong>
      </div>
      <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0" style="font-size:.85rem">
          <tr><th style="width:45%">Data início</th><td><?= htmlspecialchars($reg['datainicio']) ?></td></tr>
          <tr><th>Data fim</th><td><?= htmlspecialchars($reg['datafim']) ?></td></tr>
          <tr><th>Unidade I&D</th><td><?= htmlspecialchars($reg['unidade'] ?? '—') ?></td></tr>
          <tr><th>Posto de trabalho</th><td><?= htmlspecialchars($reg['local_trabalho'] ?? '—') ?></td></tr>
          <tr><th>Extensão FEUP</th><td><?= htmlspecialchars($reg['extensao'] ?? '—') ?></td></tr>
        </table>
      </div>
    </div>

  </div><!-- /col esquerda -->

  <!-- Coluna direita -->
  <div class="col-md-6">

    <!-- Classificação -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <i class="fas fa-tag me-2 text-muted"></i><strong>Classificação</strong>
      </div>
      <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0" style="font-size:.85rem">
          <tr><th style="width:45%">Grupo profissional</th><td><?= htmlspecialchars($reg['grupo_nome']) ?></td></tr>
          <tr><th>Categoria</th><td><?= htmlspecialchars($reg['categoria_nome'] ?? '—') ?></td></tr>
          <tr>
            <th>Responsável</th>
            <td><?= htmlspecialchars(
                  !empty($reg['respespaco']) ? $reg['respespaco'] :
                  (!empty($reg['outroresponsavel']) ? $reg['outroresponsavel'] : '—')
                ) ?></td>
          </tr>
          <?php if (!empty($reg['curso'])): ?>
          <tr><th>Curso</th><td><?= htmlspecialchars($reg['curso']) ?></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <!-- Acessos -->
    <div class="card mb-3">
      <div class="card-header py-2">
        <i class="fas fa-key me-2 text-muted"></i><strong>Acessos</strong>
      </div>
      <div class="card-body py-2">
        <table class="table table-sm table-borderless mb-0" style="font-size:.85rem">
          <tr>
            <th style="width:45%">Acesso DEQ</th>
            <td>
              <?php if ($reg['acessodeq']): ?>
                <span class="badge badge-success">Sim</span>
              <?php else: ?>
                <span class="text-muted">Não</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th>Laboratórios / Gabinetes</th>
            <td>
              <?php if ($labNomes): ?>
                <?php foreach ($labNomes as $nome): ?>
                  <span class="badge badge-light border me-1 mb-1"
                        style="font-size:.8rem;font-weight:normal">
                    <?= htmlspecialchars($nome) ?>
                  </span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </div>
    </div>

  </div><!-- /col direita -->

</div><!-- /row -->

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
