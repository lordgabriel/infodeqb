<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

// Admins locais de exames (além do admin global)
$isExamAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsExam);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Opções de ano letivo ──────────────────────────────────────────
$ano       = (int)date('Y');
$option_ano = '';
for ($i = $ano - 6; $i <= $ano + 1; $i++) {
    $option_ano .= '<option value="' . $i . '/' . ($i+1) . '">' . $i . '/' . ($i+1) . '</option>';
}

// ── Flash ─────────────────────────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';
if (isset($_SESSION['_exam_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_exam_flash'];
    unset($_SESSION['_exam_flash']);
}

// ── POST: acções admin ────────────────────────────────────────────
if (!empty($_POST) && $isExamAdmin) {
    $acao = $_POST['_acao'] ?? '';

    if ($acao === 'admin_atualizar') {
        $pdo->prepare(
            'UPDATE infodeqb_exam_archive SET curso=?,ano_letivo=?,unidade_curricular=?,tipologia=?,num_caixa=?,num_armario=? WHERE autoid=?'
        )->execute([
            trim($_POST['editcurso'] ?? ''),
            trim($_POST['editano']   ?? ''),
            trim($_POST['edituc']    ?? ''),
            trim($_POST['editipologia'] ?? ''),
            trim($_POST['editcaixa']    ?? ''),
            trim($_POST['editarmario']  ?? ''),
            (int)$_POST['editid'],
        ]);
        $_SESSION['_exam_flash'] = ['Registo actualizado.', 'success'];
        header('Location: index.php'); exit;

    } elseif ($acao === 'admin_apagar') {
        $pdo->prepare('DELETE FROM infodeqb_exam_archive WHERE autoid=?')
            ->execute([(int)$_POST['deleteid']]);
        $_SESSION['_exam_flash'] = ['Registo eliminado.', 'success'];
        header('Location: index.php'); exit;

    } elseif ($acao === 'admin_imprimir') {
        $_SESSION['ticket'] = trim($_POST['ticketid'] ?? '');
        header('Location: admin/printticket.php'); exit;
    }
}

// ── POST: acções utilizador ───────────────────────────────────────
if (!empty($_POST) && ($_POST['_acao'] ?? '') !== '') {
    $acao = $_POST['_acao'];

    if ($acao === 'inserir') {
        if (!isset($_SESSION['uid'])) $_SESSION['uid'] = uniqid('Ticket#');
        $_SESSION['name'] = trim($_POST['name'] ?? '');
        try {
            $pdo->prepare(
                'INSERT INTO infodeqb_exam_archive (request_id,data,docente,curso,ano_letivo,unidade_curricular,tipologia,status)
                 VALUES (?,?,?,?,?,?,?,0)'
            )->execute([
                $_SESSION['uid'],
                date('Y-m-d'),
                trim($_POST['name']  ?? ''),
                trim($_POST['curso'] ?? ''),
                trim($_POST['ano']   ?? ''),
                trim($_POST['uc']    ?? ''),
                trim($_POST['tipo']  ?? ''),
            ]);
        } catch (Exception $e) {
            $flashMsg  = 'Erro ao adicionar linha.';
            $flashType = 'danger';
        }
        header('Location: index.php'); exit;

    } elseif ($acao === 'atualizar') {
        $pdo->prepare(
            'UPDATE infodeqb_exam_archive SET curso=?,ano_letivo=?,unidade_curricular=?,tipologia=? WHERE autoid=?'
        )->execute([
            trim($_POST['editcurso']    ?? ''),
            trim($_POST['editano']      ?? ''),
            trim($_POST['edituc']       ?? ''),
            trim($_POST['editipologia'] ?? ''),
            (int)$_POST['editid'],
        ]);
        header('Location: index.php'); exit;

    } elseif ($acao === 'apagar_linha') {
        $pdo->prepare('DELETE FROM infodeqb_exam_archive WHERE autoid=?')
            ->execute([(int)$_POST['deleteid']]);
        $check = $pdo->prepare('SELECT COUNT(*) FROM infodeqb_exam_archive WHERE request_id=? AND status=0');
        $check->execute([$_SESSION['uid'] ?? '']);
        if (!(int)$check->fetchColumn()) {
            unset($_SESSION['uid'], $_SESSION['name']);
        }
        header('Location: index.php'); exit;

    } elseif ($acao === 'submeter') {
        $uid = $_SESSION['uid'] ?? '';
        if ($uid) {
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM infodeqb_exam_archive WHERE request_id=? AND status=0');
            $cnt->execute([$uid]);
            if ((int)$cnt->fetchColumn() > 0) {
                $pdo->prepare('UPDATE infodeqb_exam_archive SET status=1 WHERE request_id=?')
                    ->execute([$uid]);
                $_SESSION['ticket'] = $uid;
                unset($_SESSION['uid'], $_SESSION['name']);
                header('Location: success.php'); exit;
            } else {
                $flashMsg  = 'Adicione pelo menos uma linha antes de submeter.';
                $flashType = 'warning';
            }
        }
    }
}

// ── Carregar linhas pendentes do utilizador actual ────────────────
$pendentes = [];
if (isset($_SESSION['uid'])) {
    $sthP = $pdo->prepare('SELECT * FROM infodeqb_exam_archive WHERE request_id=? AND status=0 ORDER BY autoid');
    $sthP->execute([$_SESSION['uid']]);
    $pendentes = $sthP->fetchAll(PDO::FETCH_ASSOC);
    if (empty($pendentes)) {
        unset($_SESSION['uid'], $_SESSION['name']);
    }
}

// ── Carregar e agrupar registos para admin ────────────────────────
$ticketsAgrupados = [];
$nArquivados = $nPorArquivar = 0;
if ($isExamAdmin) {
    $sthA = $pdo->query(
        'SELECT * FROM infodeqb_exam_archive WHERE status=1 ORDER BY data DESC, request_id ASC, autoid ASC'
    );
    foreach ($sthA->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ticketsAgrupados[$row['request_id']][] = $row;
    }
    foreach ($ticketsAgrupados as $rows) {
        // Arquivado = TODAS as linhas têm nº caixa; por arquivar = basta uma sem caixa
        $nComCaixa = count(array_filter($rows, function($r) { return !empty($r['num_caixa']); }));
        if ($nComCaixa === count($rows)) $nArquivados++;
        else $nPorArquivar++;
    }
}

Database::disconnect();

$pageTitle = 'Arquivo de Exames';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<style>
/* ── Passos numerados (processo de submissão) ──────────────────── */
.iq-steps { list-style: none; margin: 0; padding: 0; counter-reset: step; }
.iq-steps li { counter-increment: step; display: flex; gap: .65rem; align-items: flex-start; padding: .32rem 0; font-size: .84rem; color: var(--iq-text); }
.iq-steps li::before {
  content: counter(step);
  flex-shrink: 0; width: 20px; height: 20px; margin-top: .05rem; border-radius: 50%;
  background: var(--iq-blue-light); color: var(--iq-blue-dark);
  font-family: var(--iq-font-mono); font-size: .66rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
/* ── Cartão de ticket ───────────────────────────────────────────── */
.ticket-card { border-left: 3px solid var(--iq-border2); }
.ticket-card.tk-pending { border-left-color: var(--iq-amber); }
.ticket-card.tk-done    { border-left-color: #0e9f6e; }
.ticket-head { display: flex; align-items: center; gap: .75rem; cursor: pointer; background: var(--iq-gray-50); }
.ticket-main { flex: 1; min-width: 0; overflow: hidden; }
.ticket-docente { font-weight: 650; font-size: .87rem; color: var(--iq-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ticket-meta { font-size: .73rem; color: var(--iq-muted); display: flex; gap: .4rem; align-items: center; margin-top: 1px; }
</style>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title"><i class="fas fa-archive me-2 text-primary"></i><?= t('EXAM_TITLE') ?></h1>
    <p class="iq-page-sub">Autos de incorporação e arquivo físico de provas de avaliação</p>
  </div>
  <div class="d-flex gap-2 ms-auto">
    <?php if ($isExamAdmin): ?>
    <button class="btn btn-primary btn-sm" id="btnNovoAuto"
            data-bs-toggle="collapse" data-bs-target="#formPanel">
      <i class="fas fa-plus me-1"></i><?= t('EXAM_NEW_AUTO') ?>
    </button>
    <?php endif; ?>
    <a href="files/Auto_Entrega_Eliminacao.doc" class="btn btn-outline-secondary btn-sm" download>
      <i class="fas fa-file-download me-1"></i>Auto de Entrega para Eliminação
    </a>
  </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':($flashType==='warning'?'exclamation-triangle':'exclamation-circle') ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php /* ════════ FORMULÁRIO ══════════════════════════════════════ */ ?>
<?php $formPanelOpen = $isExamAdmin ? '<div class="collapse mb-4" id="formPanel">' : ''; ?>
<?php echo $formPanelOpen; ?>
<div class="card <?= $isExamAdmin ? 'border-primary' : 'mb-4' ?>">
  <div class="card-header py-2 d-flex align-items-center <?= $isExamAdmin ? 'bg-primary text-white' : '' ?>">
    <strong class="mr-auto">
      <i class="fas fa-file-alt me-2 <?= $isExamAdmin ? '' : 'text-muted' ?>"></i>
      <?= $isExamAdmin ? t('EXAM_NEW_AUTO') : t('EXAM_AUTO_TITLE') ?>
    </strong>
    <?php if (isset($_SESSION['uid'])): ?>
    <span class="code <?= $isExamAdmin ? 'ms-2' : 'ms-1' ?>" style="<?= $isExamAdmin ? 'color:#fff' : '' ?>"><?= htmlspecialchars($_SESSION['uid']) ?></span>
    <?php endif; ?>
    <?php if ($isExamAdmin): ?>
    <button type="button" class="btn btn-sm btn-outline-light py-0 ms-2" data-bs-toggle="collapse" data-bs-target="#formPanel">
      <i class="fas fa-times fa-xs"></i>
    </button>
    <?php endif; ?>
  </div>
  <div class="card-body">

    <form method="post" class="mb-3">
      <input type="hidden" name="_acao" value="inserir">
      <div class="row g-2">
        <div class="col-12 col-md-4 form-group mb-2">
          <label class="small font-weight-bold"><?= t('EXAM_TEACHER') ?> <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control form-control-sm" required
                 placeholder="Nome do docente"
                 value="<?= htmlspecialchars($_SESSION['name'] ?? '') ?>"
                 <?= isset($_SESSION['name']) ? 'readonly' : '' ?>>
          <?php if (isset($_SESSION['name'])): ?>
          <div class="form-text" style="font-size:.72rem"><i class="fas fa-lock fa-xs me-1"></i>Fixo para este auto</div>
          <?php endif; ?>
        </div>
        <div class="col-6 col-md-3 form-group mb-2">
          <label class="small font-weight-bold">Curso <span class="text-danger">*</span></label>
          <input type="text" name="curso" class="form-control form-control-sm" required placeholder="Designação">
        </div>
        <div class="col-6 col-md-2 form-group mb-2">
          <label class="small font-weight-bold">Ano Letivo <span class="text-danger">*</span></label>
          <select name="ano" class="form-control form-control-sm" required>
            <option disabled selected value="">Seleccione</option>
            <?= $option_ano ?>
          </select>
        </div>
        <div class="col-9 col-md-2 form-group mb-2">
          <label class="small font-weight-bold"><?= t('EXAM_TYPOLOGY') ?> <span class="text-danger">*</span></label>
          <input type="text" name="tipo" class="form-control form-control-sm" required placeholder="Ex: Testes">
        </div>
        <div class="col-3 col-md-1 form-group mb-2 d-flex align-items-end">
          <button type="submit" class="btn btn-info btn-sm btn-block w-100" title="Adicionar linha">
            <i class="fas fa-plus"></i>
          </button>
        </div>
        <div class="col-12 form-group mb-0">
          <label class="small font-weight-bold"><?= t('EXAM_COURSE_UNIT') ?> <span class="text-danger">*</span></label>
          <input type="text" name="uc" class="form-control form-control-sm" required placeholder="Nome por extenso">
        </div>
      </div>
    </form>

    <?php if (!empty($pendentes)): ?>
    <div class="table-responsive mb-3">
      <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
        <thead class="">
          <tr>
            <th>Curso</th><th style="width:7em">Ano Letivo</th>
            <th>Unidade Curricular</th><th style="width:9em">Tipologia</th>
            <th style="width:5em" class="text-center">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pendentes as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['curso']) ?></td>
            <td><?= htmlspecialchars($row['ano_letivo']) ?></td>
            <td><?= htmlspecialchars($row['unidade_curricular']) ?></td>
            <td><?= htmlspecialchars($row['tipologia']) ?></td>
            <td class="text-center text-nowrap">
              <a href="#" class="btn-edit-user text-info me-1"
                 data-bs-toggle="modal" data-bs-target="#modalEditUser"
                 data-id="<?= (int)$row['autoid'] ?>"
                 data-curso="<?= htmlspecialchars($row['curso'], ENT_QUOTES) ?>"
                 data-ano="<?= htmlspecialchars($row['ano_letivo'], ENT_QUOTES) ?>"
                 data-uc="<?= htmlspecialchars($row['unidade_curricular'], ENT_QUOTES) ?>"
                 data-tipologia="<?= htmlspecialchars($row['tipologia'], ENT_QUOTES) ?>">
                <i class="fas fa-edit fa-xs"></i>
              </a>
              <a href="#" class="btn-del-user text-danger"
                 data-bs-toggle="modal" data-bs-target="#modalDelUser"
                 data-id="<?= (int)$row['autoid'] ?>">
                <i class="fas fa-trash fa-xs"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex align-items-center justify-content-between">
      <small class="text-muted"><?= count($pendentes) ?> linha(s) adicionada(s)</small>
      <form method="post">
        <input type="hidden" name="_acao" value="submeter">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-paper-plane me-1"></i><?= t('EXAM_SUBMIT_PDF') ?>
        </button>
      </form>
    </div>
    <?php else: ?>
    <p class="text-muted small mb-0">
      <i class="fas fa-info-circle me-1"></i>Adicione linhas acima para criar o auto de incorporação.
    </p>
    <?php endif; ?>

  </div>
</div>

<div class="card <?= $isExamAdmin ? '' : 'mb-4' ?>" style="<?= $isExamAdmin ? 'margin-top:1rem' : '' ?>">
  <div class="card-header py-2">
    <i class="fas fa-list-ol fa-sm me-2 text-muted"></i><strong>Como funciona</strong>
  </div>
  <div class="card-body">
    <ol class="iq-steps mb-3">
      <li>Organizar os elementos de avaliação por ano letivo e por unidade curricular.</li>
      <li>Preencher o formulário acima. Cada linha pode referenciar mais que uma UC mas <strong>apenas um ano letivo</strong>.</li>
      <li>Clicar em <strong>Submeter</strong> — será gerado um PDF com o auto de incorporação que deve ser impresso.</li>
      <li>Entregar os elementos devidamente identificados no secretariado juntamente com o auto gerado.</li>
      <li>Após boa recepção, o auto será carimbado, assinado e devolvido.</li>
      <li>No final do ano N, os arquivos do ano letivo N‑6/N‑5 são enviados para o Serviço de Arquivo da FEUP para eliminação.</li>
    </ol>
    <div class="alert alert-warning mb-0" style="display:block;font-size:.83rem">
      <i class="fas fa-exclamation-triangle me-2"></i>
      Só devem ser entregues documentos dentro do prazo de arquivo obrigatório (5 anos). Caso esse prazo tenha sido ultrapassado, preencha o
      <a href="files/Auto_Entrega_Eliminacao.doc" class="font-weight-bold" download>Auto de Entrega para Eliminação</a>
      e solicite a eliminação ao Serviço de Arquivo.
    </div>
  </div>
</div>
<?php if ($isExamAdmin) echo '</div>'; /* fecha #formPanel */ ?>

<?php if ($isExamAdmin): ?>
<?php /* ════════ DASHBOARD ADMIN ═══════════════════════════════ */ ?>

<?php /* ── Badges de estado ──────────────────────────────────── */ ?>
<div class="iq-stat-grid mb-3">
  <div class="iq-stat iq-stat-blue stat-badge" data-filter="" style="cursor:pointer" title="Ver todos">
    <div class="iq-stat-icon"><i class="fas fa-list"></i></div>
    <div><div class="iq-stat-value"><?= count($ticketsAgrupados) ?></div><div class="iq-stat-label">Total de autos</div></div>
  </div>
  <div class="iq-stat iq-stat-yellow stat-badge" data-filter="por-arquivar" style="cursor:pointer" title="Filtrar por arquivar">
    <div class="iq-stat-icon"><i class="fas fa-clock"></i></div>
    <div><div class="iq-stat-value"><?= $nPorArquivar ?></div><div class="iq-stat-label">Por arquivar</div></div>
  </div>
  <div class="iq-stat iq-stat-green stat-badge" data-filter="arquivado" style="cursor:pointer" title="Filtrar arquivados">
    <div class="iq-stat-icon"><i class="fas fa-box"></i></div>
    <div><div class="iq-stat-value"><?= $nArquivados ?></div><div class="iq-stat-label">Arquivados</div></div>
  </div>
</div>

<?php /* ── Filtros ──────────────────────────────────────────── */ ?>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap align-items-end" style="gap:10px">
      <div style="flex:1;min-width:180px">
        <label class="small font-weight-bold d-block mb-1">Docente</label>
        <input type="text" id="filterDocente" class="form-control form-control-sm"
               placeholder="Pesquisar docente…">
      </div>
      <div style="min-width:130px">
        <label class="small font-weight-bold d-block mb-1">De</label>
        <input type="date" id="filterDe" class="form-control form-control-sm">
      </div>
      <div style="min-width:130px">
        <label class="small font-weight-bold d-block mb-1">Até</label>
        <input type="date" id="filterAte" class="form-control form-control-sm">
      </div>
      <div>
        <label class="d-block" style="font-size:.01rem">&nbsp;</label>
        <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-times me-1"></i><?= t('CLEAR') ?>
        </button>
      </div>
      <div class="ml-auto d-flex align-items-end" style="gap:6px">
        <button type="button" id="btnToggleAll" class="btn btn-outline-secondary btn-sm" data-state="expanded">
          <i class="fas fa-compress-alt fa-xs me-1"></i>Colapsar tudo
        </button>
        <span class="text-muted small ms-2" id="countVisible"></span>
      </div>
    </div>
  </div>
</div>

<?php /* ── Lista de tickets ─────────────────────────────────── */ ?>
<?php if (empty($ticketsAgrupados)): ?>
<div class="alert alert-secondary" style="display:block;font-size:.85rem">
  Sem autos de incorporação submetidos.
</div>
<?php else: ?>
<div id="ticketsList">
<?php $ti = 0; foreach ($ticketsAgrupados as $ticket => $rows):
  $ti++;
  $first    = $rows[0];
  $nLinhas  = count($rows);
  // Arquivado = TODAS as linhas têm nº caixa
  $nComCaixaT = count(array_filter($rows, function($r) { return !empty($r['num_caixa']); }));
  $temCaixa = ($nComCaixaT === $nLinhas);
  $status   = $temCaixa ? 'arquivado' : 'por-arquivar';
  $bodyId   = 'tb-' . $ti;
?>
<div class="card mb-2 ticket-card <?= $temCaixa ? 'tk-done' : 'tk-pending' ?>"
     data-status="<?= $status ?>"
     data-docente="<?= htmlspecialchars(mb_strtolower($first['docente']), ENT_QUOTES) ?>"
     data-data="<?= htmlspecialchars($first['data']) ?>">
  <div class="card-header ticket-head py-2"
       data-bs-toggle="collapse" data-bs-target="#<?= $bodyId ?>">
    <i class="fas fa-chevron-down fa-xs text-muted ticket-chevron"></i>
    <div class="ticket-main">
      <div class="ticket-docente"><?= htmlspecialchars($first['docente']) ?></div>
      <div class="ticket-meta">
        <span class="code"><?= htmlspecialchars($ticket) ?></span>
        <span>·</span>
        <span><?= htmlspecialchars($first['data']) ?></span>
      </div>
    </div>
    <span class="badge badge-<?= $temCaixa ? 'success' : 'warning' ?> me-2" style="font-size:.72rem">
      <?= $temCaixa ? '<i class="fas fa-box fa-xs me-1"></i>' . t('infodeqb_exam_archiveD') : '<i class="fas fa-clock fa-xs me-1"></i>' . t('EXAM_TO_ARCHIVE') ?>
    </span>
    <span class="badge badge-secondary me-2"><?= $nLinhas ?> linha<?= $nLinhas>1?'s':'' ?></span>
    <form method="post" class="d-inline" onclick="event.stopPropagation()">
      <input type="hidden" name="_acao"    value="admin_imprimir">
      <input type="hidden" name="ticketid" value="<?= htmlspecialchars($ticket) ?>">
      <button type="submit" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-print fa-xs me-1"></i><?= t('PRINT') ?>
      </button>
    </form>
  </div>
  <div class="collapse show ticket-body" id="<?= $bodyId ?>">
    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
      <thead class="">
        <tr>
          <th>Curso</th><th style="width:7em">Ano Letivo</th>
          <th>Unidade Curricular</th><th style="width:9em">Tipologia</th>
          <th style="width:5em" class="text-center">Caixa</th>
          <th style="width:5em" class="text-center">Armário</th>
          <th style="width:5em" class="text-center">Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['curso']) ?></td>
          <td><?= htmlspecialchars($row['ano_letivo']) ?></td>
          <td><?= htmlspecialchars($row['unidade_curricular']) ?></td>
          <td><?= htmlspecialchars($row['tipologia']) ?></td>
          <td class="text-center"><?= htmlspecialchars($row['num_caixa'] ?? '') ?></td>
          <td class="text-center"><?= htmlspecialchars($row['num_armario'] ?? '') ?></td>
          <td class="text-center text-nowrap">
            <a href="#" class="btn-edit-admin text-info me-1"
               data-bs-toggle="modal" data-bs-target="#modalEditAdmin"
               data-id="<?= (int)$row['autoid'] ?>"
               data-curso="<?= htmlspecialchars($row['curso'], ENT_QUOTES) ?>"
               data-ano="<?= htmlspecialchars($row['ano_letivo'], ENT_QUOTES) ?>"
               data-uc="<?= htmlspecialchars($row['unidade_curricular'], ENT_QUOTES) ?>"
               data-tipologia="<?= htmlspecialchars($row['tipologia'], ENT_QUOTES) ?>"
               data-caixa="<?= htmlspecialchars($row['num_caixa'] ?? '', ENT_QUOTES) ?>"
               data-armario="<?= htmlspecialchars($row['num_armario'] ?? '', ENT_QUOTES) ?>">
              <i class="fas fa-edit fa-xs"></i></a>
            <a href="#" class="btn-del-admin text-danger"
               data-bs-toggle="modal" data-bs-target="#modalDelAdmin"
               data-id="<?= (int)$row['autoid'] ?>">
              <i class="fas fa-trash fa-xs"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
</div><!-- /#ticketsList -->
<?php endif; ?>

<!-- Modal: editar linha (admin) -->
<div class="modal fade" id="modalEditAdmin" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="_acao"  value="admin_atualizar">
      <input type="hidden" name="editid" id="adminEditId">
      <div class="modal-header">
        <h5 class="modal-title">Editar linha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="small font-weight-bold">Curso</label>
          <input type="text" name="editcurso" id="adminEditCurso" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="small font-weight-bold">Ano Letivo</label>
          <select name="editano" id="adminEditAno" class="form-control" required><?= $option_ano ?></select>
        </div>
        <div class="form-group">
          <label class="small font-weight-bold">Unidades Curriculares</label>
          <input type="text" name="edituc" id="adminEditUc" class="form-control">
        </div>
        <div class="form-group">
          <label class="small font-weight-bold">Tipologia</label>
          <input type="text" name="editipologia" id="adminEditTipologia" class="form-control">
        </div>
        <div class="row">
          <div class="col form-group">
            <label class="small font-weight-bold">Nº Caixa</label>
            <input type="text" name="editcaixa" id="adminEditCaixa" class="form-control">
          </div>
          <div class="col form-group mb-0">
            <label class="small font-weight-bold">Nº Armário</label>
            <input type="text" name="editarmario" id="adminEditArmario" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('SAVE') ?></button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal: eliminar linha (admin) -->
<div class="modal fade" id="modalDelAdmin" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="_acao"    value="admin_apagar">
      <input type="hidden" name="deleteid" id="adminDelId">
      <div class="modal-header">
        <h5 class="modal-title">Eliminar linha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><?= t('CONFIRM_DELETE') ?></p>
        <p class="text-danger small mb-0"><?= t('IRREVERSIBLE') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
        <button type="submit" class="btn btn-danger"><?= t('DELETE') ?></button>
      </div>
    </form>
  </div></div>
</div>

<hr class="my-4">
<?php endif; // $isExamAdmin ?>

<!-- Modal: editar linha (utilizador) -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao"  value="atualizar">
        <input type="hidden" name="editid" id="userEditId">
        <div class="modal-header">
          <h5 class="modal-title">Editar linha</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="small font-weight-bold">Curso</label>
            <input type="text" name="editcurso" id="userEditCurso" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="small font-weight-bold">Ano Letivo</label>
            <select name="editano" id="userEditAno" class="form-control" required>
              <?= $option_ano ?>
            </select>
          </div>
          <div class="form-group">
            <label class="small font-weight-bold">Unidades Curriculares</label>
            <input type="text" name="edituc" id="userEditUc" class="form-control">
          </div>
          <div class="form-group mb-0">
            <label class="small font-weight-bold">Tipologia</label>
            <input type="text" name="editipologia" id="userEditTipologia" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('SAVE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: eliminar linha (utilizador) -->
<div class="modal fade" id="modalDelUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao"    value="apagar_linha">
        <input type="hidden" name="deleteid" id="userDelId">
        <div class="modal-header">
          <h5 class="modal-title">Eliminar linha</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p><?= t('CONFIRM_DELETE') ?></p>
          <p class="text-danger small mb-0"><?= t('IRREVERSIBLE') ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-danger"><?= t('DELETE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    <?php if ($isExamAdmin): ?>

    // ── Filtro de badges + data + docente ─────────────────────────
    var activeStatus = '';

    function updateCount() {
        var n = $('.ticket-card:visible').length;
        $('#countVisible').text(n + ' de <?= count($ticketsAgrupados) ?> autos');
    }

    function applyFilters() {
        var docente = $('#filterDocente').val().toLowerCase().trim();
        var de      = $('#filterDe').val();
        var ate     = $('#filterAte').val();

        $('.ticket-card').each(function () {
            var $c     = $(this);
            var status = $c.data('status');
            var doc    = ($c.data('docente') || '').toLowerCase();
            var data   = $c.data('data') || '';
            var show   = true;

            if (activeStatus && status !== activeStatus) show = false;
            if (docente && doc.indexOf(docente) === -1)  show = false;
            if (de  && data < de)  show = false;
            if (ate && data > ate) show = false;

            $c.toggle(show);
        });
        updateCount();
    }

    // Badges clicáveis
    $('.stat-badge').on('click', function () {
        var filter = $(this).data('filter');
        activeStatus = (activeStatus === filter) ? '' : filter;
        $('.stat-badge').removeClass('border-primary').css('box-shadow','');
        if (activeStatus) {
            $(this).addClass('border-primary').css('box-shadow','0 0 0 2px #0b6e7333');
        }
        applyFilters();
    });

    // Filtros de texto e data
    $('#filterDocente').on('keyup', applyFilters);
    $('#filterDe, #filterAte').on('change', applyFilters);

    // Limpar filtros
    $('#clearFilters').on('click', function () {
        activeStatus = '';
        $('#filterDocente').val('');
        $('#filterDe').val('');
        $('#filterAte').val('');
        $('.stat-badge').removeClass('border-primary').css('box-shadow','');
        applyFilters();
    });

    updateCount();

    // ── Colapso individual: rodar chevron ─────────────────────────
    $(document).on('hide.bs.collapse', '.ticket-body', function () {
        $(this).closest('.ticket-card').find('.ticket-chevron')
               .css('transform','rotate(-90deg)');
    }).on('show.bs.collapse', '.ticket-body', function () {
        $(this).closest('.ticket-card').find('.ticket-chevron')
               .css('transform','rotate(0deg)');
    });

    // ── Colapsar / Expandir tudo ──────────────────────────────────
    $('#btnToggleAll').on('click', function () {
        var $btn = $(this);
        var state = $btn.data('state');
        if (state === 'expanded') {
            $('.ticket-card:visible .ticket-body').collapse('hide');
            $btn.data('state','collapsed')
                .html('<i class="fas fa-expand-alt fa-xs me-1"></i>Expandir tudo');
        } else {
            $('.ticket-card:visible .ticket-body').collapse('show');
            $btn.data('state','expanded')
                .html('<i class="fas fa-compress-alt fa-xs me-1"></i>Colapsar tudo');
        }
    });

    // ── Modais admin ──────────────────────────────────────────────
    $(document).on('click', '.btn-edit-admin', function () {
        var d = $(this).data();
        $('#adminEditId').val(d.id);
        $('#adminEditCurso').val(d.curso);
        $('#adminEditAno').val(d.ano);
        $('#adminEditUc').val(d.uc);
        $('#adminEditTipologia').val(d.tipologia);
        $('#adminEditCaixa').val(d.caixa);
        $('#adminEditArmario').val(d.armario);
    });
    $(document).on('click', '.btn-del-admin', function () {
        $('#adminDelId').val($(this).data('id'));
    });

    // ── Botão "Novo auto": actualizar chevron ─────────────────────
    $('#formPanel').on('show.bs.collapse', function () {
        $('#btnNovoAuto').html('<i class="fas fa-times me-1"></i><?= t('CLOSE') ?>');
    }).on('hide.bs.collapse', function () {
        $('#btnNovoAuto').html('<i class="fas fa-plus me-1"></i><?= t('EXAM_NEW_AUTO') ?>');
    });

    <?php endif; ?>

    // ── Modais utilizador ─────────────────────────────────────────
    $(document).on('click', '.btn-edit-user', function () {
        var d = $(this).data();
        $('#userEditId').val(d.id);
        $('#userEditCurso').val(d.curso);
        $('#userEditAno').val(d.ano);
        $('#userEditUc').val(d.uc);
        $('#userEditTipologia').val(d.tipologia);
    });
    $(document).on('click', '.btn-del-user', function () {
        $('#userDelId').val($(this).data('id'));
    });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
