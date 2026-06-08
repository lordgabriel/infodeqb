<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
require_once ROOT_DIR . '/infodeqb/hr/common.php';
include ROOT_DIR . '/infodeqb/session.php';
date_default_timezone_set('Europe/Lisbon');

require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';
require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

if (empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}
$id = $_GET['id'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Dados pessoais do colaborador ──────────────────────────────────
$qColab = $pdo->prepare('SELECT * FROM infodeqb_rds_colaborador WHERE codigo = ?');
$qColab->execute([$id]);
$colab = $qColab->fetch(PDO::FETCH_ASSOC);

if (!$colab) {
    header('Location: index.php');
    exit;
}

// ── Registos — filtrar por status se pedido (?status=Novo, etc.) ──
$statusFiltro   = isset($_GET['status']) ? trim($_GET['status']) : null;
$statusPermitidos = array('Novo','Ativo','Pendente','Inativo');
if (!in_array($statusFiltro, $statusPermitidos)) {
    $statusFiltro = null; // ignorar valores inválidos
}

if ($statusFiltro) {
    $qRegs = $pdo->prepare(
        'SELECT r.*, g.grupo_pro, resp.respespaco
         FROM infodeqb_rds_registo r
         INNER JOIN infodeqb_rds_grupo g           ON r.grupo       = g.grupoid
         LEFT  JOIN infodeqb_rds_responsaveis resp ON r.responsavel = resp.codigo
         WHERE r.codigo = ? AND r.deleted = 0 AND r.status = ?
         ORDER BY r.datafim DESC'
    );
    $qRegs->execute([$id, $statusFiltro]);
} else {
    $qRegs = $pdo->prepare(
        'SELECT r.*, g.grupo_pro, resp.respespaco
         FROM infodeqb_rds_registo r
         INNER JOIN infodeqb_rds_grupo g           ON r.grupo       = g.grupoid
         LEFT  JOIN infodeqb_rds_responsaveis resp ON r.responsavel = resp.codigo
         WHERE r.codigo = ? AND r.deleted = 0
         ORDER BY
           CASE r.status WHEN "Ativo" THEN 1 WHEN "Pendente" THEN 2
                         WHEN "Novo"  THEN 3 WHEN "Inativo"  THEN 4 ELSE 5 END,
           r.datafim DESC'
    );
    $qRegs->execute([$id]);
}
$registos = $qRegs->fetchAll(PDO::FETCH_ASSOC);

// Carregar validações para todos os registos deste colaborador
$valPorRegisto = array();
if (!empty($registos)) {
    $rids = array_map('intval', array_column($registos, 'autoid'));
    $phs  = implode(',', array_fill(0, count($rids), '?'));
    $qValReg = $pdo->prepare(
        "SELECT id, registo_id, deq_id, gab_nome, resp_codigo, resp_nome,
                status, nota, respondido_em
         FROM infodeqb_rds_validacao WHERE registo_id IN ($phs) ORDER BY id"
    );
    $qValReg->execute($rids);
    foreach ($qValReg->fetchAll(PDO::FETCH_ASSOC) as $vr) {
        $valPorRegisto[$vr['registo_id']][] = $vr;
    }
}

// Flash messages de validação
$valInfo = isset($_SESSION['val_info']) ? $_SESSION['val_info'] : null;
unset($_SESSION['val_info']);

Database::disconnect();

$pageTitle = 'Detalhe do Colaborador';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:.5rem">
  <h1 class="mr-auto mb-0">
    <i class="fas fa-user fa-sm me-2 text-muted"></i><?= htmlspecialchars($colab['nome']) ?>
  </h1>
  <a href="index.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Voltar
  </a>
</div>

<!-- ══ Cartão: Dados Pessoais ══════════════════════════════════════ -->
<div class="card mb-4">
  <div class="card-header d-flex align-items-center">
    <i class="fas fa-id-card me-2 text-muted"></i>
    <strong class="mr-auto">Dados Pessoais</strong>
    <a href="edituser.php?id=<?= htmlspecialchars($id) ?>"
       class="btn btn-sm btn-outline-secondary me-2" title="Editar dados pessoais">
      <i class="fas fa-edit me-1"></i> Editar
    </a>
    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
            data-bs-target="#modalDeleteColab" title="Apagar colaborador">
      <i class="fas fa-trash-alt"></i>
    </button>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-2 col-sm-4 mb-2">
        <small class="text-muted d-block">Número FEUP</small>
        <strong><?= htmlspecialchars($colab['codigo']) ?></strong>
      </div>
      <div class="col-md-4 col-sm-8 mb-2">
        <small class="text-muted d-block">Nome</small>
        <strong><?= htmlspecialchars($colab['nome']) ?></strong>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <small class="text-muted d-block">E-mail FEUP</small>
        <?= htmlspecialchars($colab['email'] ?? '—') ?>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <small class="text-muted d-block">E-mail alternativo</small>
        <?= htmlspecialchars($colab['emailalt'] ?? '—') ?>
      </div>
      <div class="col-md-2 col-sm-4 mb-2">
        <small class="text-muted d-block">Telefone</small>
        <?php
        list($tfInd, $tfNum) = parsePhone($colab['telefone'] ?? '');
        echo $tfNum ? htmlspecialchars($tfInd . ' ' . $tfNum) : '—';
        ?>
      </div>
    </div>
  </div>
</div>

<!-- ══ Cartão: Registos ════════════════════════════════════════════ -->
<div class="card mb-4">
  <div class="card-header d-flex align-items-center">
    <i class="fas fa-list me-2 text-muted"></i>
    <strong class="mr-auto">
      Registos
      <?php if ($statusFiltro): ?>
        <span class="badge badge-info ms-1"><?= htmlspecialchars($statusFiltro) ?></span>
      <?php endif; ?>
    </strong>
    <?php if ($statusFiltro): ?>
      <a href="detail.php?id=<?= urlencode($id) ?>"
         class="btn btn-xs btn-outline-secondary">
        <i class="fas fa-list fa-xs me-1"></i>Ver todos
      </a>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
      <thead class="">
        <tr>
          <th class="text-muted" style="width:4%">ID</th>
          <th style="width:20%">Categoria</th>
          <th style="width:8%">Acesso DEQ</th>
          <th>Outros Acessos</th>
          <th style="width:8%">Estado</th>
          <th style="width:8%">Início</th>
          <th style="width:8%">Fim</th>
          <th style="width:10%" class="text-center">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($valInfo)): ?>
        <tr>
          <td colspan="8" class="py-1 px-3">
            <div class="alert alert-info alert-sm mb-0 py-1 px-2" style="font-size:.85rem;">
              <i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($valInfo) ?>
            </div>
          </td>
        </tr>
        <?php endif; ?>
        <?php if (empty($registos)): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-4">Sem registos</td>
        </tr>
        <?php else: foreach ($registos as $row):
          $sBadgeMap = array(
              'Ativo'    => 'badge-success',
              'Pendente' => 'badge-warning',
              'Novo'     => 'badge-info',
              'Inativo'  => 'badge-secondary',
          );
          $sBadge = isset($sBadgeMap[$row['status']]) ? $sBadgeMap[$row['status']] : 'badge-secondary';

          // Validações para este registo
          $rVals     = isset($valPorRegisto[$row['autoid']]) ? $valPorRegisto[$row['autoid']] : array();
          $rNVal     = count($rVals);
          $rTodosOk  = false; $rBloqueado = false;
          if ($rNVal > 0) {
              $rNOk  = count(array_filter($rVals, function($v){ return $v['status']==='Validado'; }));
              $rNPend = count(array_filter($rVals, function($v){ return $v['status']==='Pendente'; }));
              $rNRej  = count(array_filter($rVals, function($v){ return $v['status']==='Rejeitado'; }));
              $rTodosOk  = ($rNOk === $rNVal);
              $rBloqueado = ($rNPend > 0 || $rNRej > 0);
          }
          $temLabs = count(getRegistoAcessos($pdo, (int)$row['autoid'])) > 0;
        ?>
        <tr>
          <td class="text-muted small align-middle"><?= (int)$row['autoid'] ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['grupo_pro']) ?></td>
          <td class="align-middle"><?= $row['acessodeq'] == 1 ? 'Sim' : 'Não' ?></td>
          <td class="align-middle"><small><?= htmlspecialchars($row['acessos'] ?? '') ?></small></td>
          <td class="align-middle">
            <span class="badge <?= $sBadge ?>"><?= htmlspecialchars($row['status']) ?></span>
          </td>
          <td class="align-middle"><?= htmlspecialchars($row['datainicio']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['datafim']) ?></td>
          <td class="text-center text-nowrap align-middle">
            <a href="edit.php?id=<?= $row['codigo'] ?>&id1=<?= $row['autoid'] ?>"
               title="Editar" class="btn btn-xs btn-outline-secondary">
              <i class="fas fa-edit"></i>
            </a>
            <button type="button" class="btn btn-xs btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#modalDel<?= (int)$row['autoid'] ?>"
                    title="Apagar">
              <i class="fas fa-trash-alt"></i>
            </button>
          </td>
        </tr>
        <?php if ($row['status'] === 'Novo' && $temLabs): ?>
        <tr>
          <td colspan="8" style="background:#f8f9fa;padding:6px 16px 10px 16px;border-top:none;">
            <small class="d-block mb-1 text-muted font-weight-bold">
              <i class="fas fa-user-check me-1"></i>Validações de espaço
              <?php if ($rTodosOk): ?>
                <span class="badge badge-success ms-1">Todas validadas ✓</span>
              <?php elseif ($rBloqueado): ?>
                <span class="badge badge-warning ms-1">A aguardar / com rejeição</span>
              <?php else: ?>
                <span class="badge badge-secondary ms-1">Não solicitadas</span>
              <?php endif; ?>
            </small>

            <?php if ($rNVal > 0): ?>
            <table class="table table-xs table-bordered mb-1" style="font-size:.8rem;background:#fff;">
              <thead class="">
                <tr><th>Espaço</th><th>Responsável</th><th>Estado</th><th>Nota</th><th style="width:1%">Ações</th></tr>
              </thead>
              <tbody>
              <?php
              $vBadgeMap = array('Pendente'=>'badge-warning','Validado'=>'badge-success','Rejeitado'=>'badge-danger');
              $vIconMap  = array('Pendente'=>'⏳','Validado'=>'✅','Rejeitado'=>'❌');
              foreach ($rVals as $vv):
              ?>
              <tr>
                <td><?= htmlspecialchars($vv['gab_nome'] ?? $vv['deq_id']) ?></td>
                <td><?= htmlspecialchars($vv['resp_nome'] ?? '') ?>
                    <small class="text-muted">(up<?= htmlspecialchars($vv['resp_codigo']) ?>)</small></td>
                <td><span class="badge <?= isset($vBadgeMap[$vv['status']]) ? $vBadgeMap[$vv['status']] : 'badge-secondary' ?>">
                    <?= (isset($vIconMap[$vv['status']]) ? $vIconMap[$vv['status']] : '') . ' ' . $vv['status'] ?></span>
                    <?php if ($vv['respondido_em']): ?>
                      <br><small class="text-muted"><?= htmlspecialchars(substr($vv['respondido_em'],0,16)) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($vv['nota'] ?? '—') ?></td>
                <td style="white-space:nowrap">
                  <?php if ($vv['status'] !== 'Validado'): ?>
                  <form method="post" action="validacao-action.php" style="display:inline">
                    <input type="hidden" name="val_acao"  value="reabrir">
                    <input type="hidden" name="val_id"    value="<?= (int)$vv['id'] ?>">
                    <input type="hidden" name="redirect"
                           value="detail.php?id=<?= urlencode($id) ?><?= $statusFiltro ? '&amp;status=' . urlencode($statusFiltro) : '' ?>">
                    <button class="btn btn-xs btn-outline-primary" title="Reenviar novo link"
                            onclick="return confirm('Reenviar pedido de validação ao responsável?')">
                      <i class="fas fa-redo fa-xs"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                  <form method="post" action="validacao-action.php" style="display:inline">
                    <input type="hidden" name="val_acao"  value="cancelar">
                    <input type="hidden" name="val_id"    value="<?= (int)$vv['id'] ?>">
                    <input type="hidden" name="redirect"
                           value="detail.php?id=<?= urlencode($id) ?><?= $statusFiltro ? '&amp;status=' . urlencode($statusFiltro) : '' ?>">
                    <button class="btn btn-xs btn-outline-danger" title="Apagar esta validação"
                            onclick="return confirm('Apagar esta validação?')">
                      <i class="fas fa-trash fa-xs"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>

            <!-- Botão principal: muda conforme estado das validações -->
            <?php if ($rTodosOk): ?>
            <form method="post" action="validacao-action.php">
              <input type="hidden" name="val_acao"   value="solicitar_acessos">
              <input type="hidden" name="registo_id" value="<?= (int)$row['autoid'] ?>">
              <input type="hidden" name="redirect"
                     value="detail.php?id=<?= urlencode($id) ?><?= $statusFiltro ? '&amp;status=' . urlencode($statusFiltro) : '' ?>">
              <button class="btn btn-xs btn-success"
                      onclick="return confirm('Enviar pedido de acessos ao SIGARRA?')">
                <i class="fas fa-paper-plane fa-xs me-1"></i>Solicitar acessos ao SIGARRA
              </button>
            </form>
            <?php else: ?>
            <form method="post" action="validacao-action.php">
              <input type="hidden" name="val_acao"    value="solicitar_registo">
              <input type="hidden" name="registo_id"  value="<?= (int)$row['autoid'] ?>">
              <input type="hidden" name="redirect"
                     value="detail.php?id=<?= urlencode($id) ?><?= $statusFiltro ? '&amp;status=' . urlencode($statusFiltro) : '' ?>">
              <button class="btn btn-xs btn-outline-warning"
                      onclick="return confirm('Enviar pedido de validação aos responsáveis dos espaços?')">
                <i class="fas fa-user-check fa-xs me-1"></i>
                <?= $rNVal === 0 ? 'Solicitar validações' : 'Solicitar validações em falta' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══ Modais: apagar registo individual ══════════════════════════ -->
<?php foreach ($registos as $row): ?>
<div class="modal fade" id="modalDel<?= (int)$row['autoid'] ?>" tabindex="-1" role="dialog"
     aria-labelledby="modalDelLabel<?= (int)$row['autoid'] ?>" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form action="delete.php" method="post">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDelLabel<?= (int)$row['autoid'] ?>">Apagar registo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Está prestes a apagar este registo. Esta ação é irreversível.</p>
          <p>Pretende continuar?</p>
        </div>
        <div class="modal-footer">
          <input type="hidden" name="id"  value="<?= htmlspecialchars($colab['codigo']) ?>">
          <input type="hidden" name="id1" value="<?= (int)$row['autoid'] ?>">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
          <button type="submit" class="btn btn-danger">Sim, apagar</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- ══ Modal: apagar colaborador completo ═════════════════════════ -->
<div class="modal fade" id="modalDeleteColab" tabindex="-1" role="dialog"
     aria-labelledby="modalDeleteColabLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form action="delete.php" method="post">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDeleteColabLabel">Apagar colaborador</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Está prestes a apagar o registo de <strong><?= htmlspecialchars($colab['nome']) ?></strong>
             e todos os seus registos associados. Esta ação é irreversível.</p>
          <p>Pretende continuar?</p>
        </div>
        <div class="modal-footer">
          <input type="hidden" name="id"  value="<?= htmlspecialchars($colab['codigo']) ?>">
          <input type="hidden" name="id1" value="">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
          <button type="submit" class="btn btn-danger">Sim, apagar tudo</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
