<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';

// Linguagem
$sites    = array('en' => 'en', 'pt' => 'pt');
$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'pt', 0, 2);
if (!isset($sites[$language])) $language = 'pt';
include __DIR__ . '/lang/lang.' . $sites[$language] . '.php';

if (!isset($_SESSION['registo'])) {
    header('Location: index.php');
    exit;
}

// ── Dados básicos da sessão ──────────────────────────────────────────
$reg      = $_SESSION['registo'];
$codigo   = $reg['codigo']           ?? '';
$nome     = $reg['nome']             ?? '';
$email    = $reg['email']            ?? '';
$emailalt = $reg['emailalt']         ?? '';
$datainicio = date('d-m-Y', strtotime($reg['datainicio'] ?? 'now'));
$datafim    = date('d-m-Y', strtotime($reg['datafim']   ?? 'now'));
$acessodeq  = $_SESSION['acessodeq'] ?? 0;
$grupo    = $reg['grupo']    ?? 0;
$categoria= $reg['categoria']?? 0;
$curso    = $reg['curso']    ?? '';

// ── Telefone: reconstituir indicativo + número ───────────────────────
$telNum = preg_replace('/[^0-9]/', '', trim($reg['telefone_numero'] ?? ''));
$telInd = trim($reg['telefone_indicativo'] ?? '+351');
$telefone = $telNum ? ($telInd . ' ' . $telNum) : ($reg['telefone'] ?? '');

// ── BD ───────────────────────────────────────────────────────────────
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Responsável de trabalho
$respNome = '';
if (!empty($reg['responsavel']) && $reg['responsavel'] != '0') {
    $r = $pdo->prepare('SELECT respespaco FROM infodeqb_rds_responsaveis WHERE Codigo=?');
    $r->execute([$reg['responsavel']]);
    $respNome = $r->fetchColumn() ?: $reg['outroresponsavel'] ?? '';
} else {
    $respNome = $reg['outroresponsavel'] ?? '';
}

// Grupo e Categoria
$grupNome = $pdo->prepare('SELECT grupo_pro FROM infodeqb_rds_grupo WHERE grupoid=?');
$grupNome->execute([$grupo]);
$grupNome = $grupNome->fetchColumn() ?: '';

$catNome = $pdo->prepare('SELECT categoria FROM infodeqb_rds_categoria WHERE categoriaid=?');
$catNome->execute([$categoria]);
$catNome = $catNome->fetchColumn() ?: '';

// ── Espaços agrupados por responsável ────────────────────────────────
// deqids: a partir da sessão (deqid guardado em formato "E-101; E-102; ...")
$deqidsRaw = $_SESSION['deqid'] ?? '';
$deqids    = array_values(array_filter(array_map('trim', explode(';', $deqidsRaw))));

// Acesso DEQ (porta norte) como primeiro item se activo
if ($acessodeq == 1) {
    array_unshift($deqids, 'DEQ');
}

// Para cada deqid: obter nome do gabinete e responsável
$byResp = array(); // ['resp_nome' => ['lab_nome', ...]]

foreach ($deqids as $deqid) {
    if ($deqid === 'DEQ') {
        $byResp['(Porta Norte / Acesso DEQ)'][] = 'Acesso Porta Norte DEQ';
        continue;
    }
    $q = $pdo->prepare(
        'SELECT g.nomegab, r.respespaco AS resp_nome
         FROM infodeqb_rds_gabinetes g
         LEFT JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
         WHERE g.deqid = ? LIMIT 1'
    );
    $q->execute([$deqid]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $resp = !empty($row['resp_nome']) ? $row['resp_nome'] : '(sem responsável)';
        $byResp[$resp][] = $row['nomegab'] ?: $deqid;
    }
}

Database::disconnect();

$pageTitle = t('HR_SUCCESS_TITLE');
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-check-circle fa-sm me-2 text-success"></i>Registo submetido</h1>
</div>

<div class="alert alert-success mb-4" style="font-size:.9rem">
  <i class="fas fa-check me-2"></i><?= $lang['SUCCESS'] ?? 'O seu registo foi submetido com sucesso e será analisado pelo secretariado.' ?>
</div>

<!-- ── Dados pessoais ─────────────────────────────────────── -->
<div class="card mb-3">
  <div class="card-header py-2">
    <i class="fas fa-user fa-sm me-2 text-muted"></i><strong><?= $lang['RECORD'] ?? 'Dados do registo' ?></strong>
  </div>
  <div class="card-body py-2">
    <table class="table table-sm table-borderless mb-0" style="font-size:.88rem;max-width:560px">
      <tr><th style="width:40%;color:#6b7280"><?= $lang['FEUP_CODE']   ?? 'Código FEUP' ?></th><td><?= htmlspecialchars($codigo) ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['NAME']        ?? 'Nome' ?></th><td><?= htmlspecialchars($nome) ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['EMAIL']       ?? 'Email' ?></th><td><?= htmlspecialchars($email) ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['ALT_EMAIL']   ?? 'Email alt.' ?></th><td><?= htmlspecialchars($emailalt ?: 'N.A.') ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['PHONE_PRINT'] ?? 'Telefone' ?></th><td><?= htmlspecialchars($telefone ?: '—') ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['CATEGORY']    ?? 'Categoria' ?></th><td><?= htmlspecialchars($catNome . ($grupNome ? ' (' . $grupNome . ')' : '')) ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['BEGIN_DATE']  ?? 'Início' ?></th><td><?= htmlspecialchars($datainicio) ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['END_DATE']    ?? 'Fim' ?></th><td><?= htmlspecialchars($datafim) ?></td></tr>
      <tr><th style="color:#6b7280"><?= $lang['WORK_RESP']   ?? 'Responsável' ?></th><td><?= htmlspecialchars($respNome ?: '—') ?></td></tr>
      <?php if ($curso): ?>
      <tr><th style="color:#6b7280">Curso</th><td><?= htmlspecialchars($curso) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- ── Acessos pedidos agrupados por responsável ─────────── -->
<?php if (!empty($byResp)): ?>
<div class="card mb-4">
  <div class="card-header py-2">
    <i class="fas fa-key fa-sm me-2 text-muted"></i><strong><?= $lang['REQUESTED_ACCESS'] ?? 'Acessos pedidos' ?></strong>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" style="font-size:.86rem">
      <thead>
        <tr>
          <th style="width:40%"><?= $lang['WORKSPACE_RESP'] ?? 'Responsável' ?></th>
          <th>Espaços / Gabinetes</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($byResp as $resp => $labs): ?>
        <tr>
          <td class="align-top fw-semibold"><?= htmlspecialchars($resp) ?></td>
          <td>
            <?php foreach ($labs as $lab): ?>
              <span class="badge badge-secondary me-1 mb-1" style="font-size:.78rem;font-weight:normal">
                <?= htmlspecialchars($lab) ?>
              </span>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="d-flex gap-2">
  <a href="meu-registo.php" class="btn btn-primary btn-sm">
    <i class="fas fa-id-card me-1"></i>Ver o meu registo
  </a>
  <a href="<?= HTTP_DIR ?>/infodeqb/" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-home me-1"></i>Dashboard
  </a>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
