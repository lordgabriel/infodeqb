<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('Location: ' . HTTP_DIR . '/infodeqb/error.php');
    exit();
}

require_once ROOT_DIR . '/infodeqb/inc/admins.php';

// Admins locais desta página (além do admin global)
$_areasLocalAdmins = [];
$isAreasAdmin = $isAdmin || in_array($_iqCurrentUser, $_areasLocalAdmins);

if (!$isAreasAdmin) {
    $pdo = Database::connect();
    $sth = $pdo->prepare('SELECT id FROM infodeqb_docentes_investigadores_perm WHERE codigo = ?');
    $sth->execute([$_iqCurrentUser !== '' ? preg_replace('/\D/', '', $_iqCurrentUser) : preg_replace('/\D/', '', $_SESSION['user'] ?? '')]);
    $chk = $sth->fetch(PDO::FETCH_ASSOC);
    Database::disconnect();
    if (!$chk) {
        header('Location: ' . HTTP_DIR . '/infodeqb/error.php');
        exit();
    }
}

$codigo_inquirido = preg_replace('/\D/', '', $_SESSION['Code'] ?? $_SESSION['user'] ?? '');
$nomeUtilizador   = $_SESSION['CommonName'] ?? $_SESSION['DisplayName'] ?? '';

// ── POST: guardar infodeqb_respostas ───────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';

if (!empty($_POST) && isset($_POST['valores'])) {
    try {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $ci     = preg_replace('/\D/', '', trim($_POST['codigo_inquirido'] ?? ''));
        $valores = $_POST['valores'];
        $pdo->prepare('DELETE FROM infodeqb_respostas WHERE codigo_inquirido = ?')->execute([$ci]);
        $stmt = $pdo->prepare(
            'INSERT INTO infodeqb_respostas (codigo_inquirido, area_id, subarea_id, valor) VALUES (?,?,?,?)'
        );
        foreach ($valores as $subarea_id => $areas) {
            foreach ($areas as $area_id => $valor) {
                $v = (int)$valor;
                if ($v > 0) $stmt->execute([$ci, (int)$area_id, (int)$subarea_id, $v]);
            }
        }
        Database::disconnect();
        $_SESSION['_areas_flash'] = ['Dados guardados com sucesso.', 'success'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $flashMsg  = 'Erro ao guardar os dados.';
        $flashType = 'danger';
        error_log('areas/index.php: ' . $e->getMessage());
    }
}

if (isset($_SESSION['_areas_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_areas_flash'];
    unset($_SESSION['_areas_flash']);
}

// ── Dados base ────────────────────────────────────────────────────
$pdo      = Database::connect();
$areas    = $pdo->query('SELECT * FROM infodeqb_areas_deqb ORDER BY order_id')->fetchAll(PDO::FETCH_ASSOC);
$infodeqb_subareas = $pdo->query('SELECT * FROM infodeqb_subareas WHERE id != 11 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// infodeqb_respostas do utilizador actual
$sthR = $pdo->prepare('SELECT * FROM infodeqb_respostas WHERE codigo_inquirido = ?');
$sthR->execute([$codigo_inquirido]);
$infodeqb_respostas    = $sthR->fetchAll(PDO::FETCH_ASSOC);
$teminfodeqb_respostas = !empty($infodeqb_respostas);
$respIndex    = [];
foreach ($infodeqb_respostas as $r) $respIndex[$r['subarea_id']][$r['area_id']] = (int)$r['valor'];

// ── Dados admin ───────────────────────────────────────────────────
if ($isAreasAdmin) {
    // Lista de participantes + estado
    $participantes = $pdo->query(
        'SELECT p.codigo, p.nome, p.email,
                MAX(CASE WHEN r.codigo_inquirido IS NOT NULL THEN 1 ELSE 0 END) AS respondeu
         FROM infodeqb_docentes_investigadores_perm p
         LEFT JOIN infodeqb_respostas r ON r.codigo_inquirido = p.codigo
         GROUP BY p.id, p.codigo, p.nome, p.email
         ORDER BY p.nome'
    )->fetchAll(PDO::FETCH_ASSOC);

    $totalPerm  = count($participantes);
    $totalResp  = array_sum(array_column($participantes, 'respondeu'));
    $totalPend  = $totalPerm - $totalResp;

    // ETI total por área (cada % = fracção de 1 ETI)
    $pessoasAreaRaw = $pdo->query(
        'SELECT area_id, SUM(CAST(valor AS DECIMAL(10,2)) / 100) AS eti
         FROM infodeqb_respostas
         GROUP BY area_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $pessoasAreaMap = [];
    foreach ($pessoasAreaRaw as $row) $pessoasAreaMap[$row['area_id']] = round((float)$row['eti'], 2);

    // Área principal de cada pessoa (área com maior % acumulado)
    $areaPrincipalRaw = $pdo->query(
        'SELECT codigo_inquirido, area_id, SUM(CAST(valor AS DECIMAL(10,2))) AS total
         FROM infodeqb_respostas GROUP BY codigo_inquirido, area_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $areaPrincipalTemp = [];
    foreach ($areaPrincipalRaw as $row) {
        $ci = $row['codigo_inquirido'];
        if (!isset($areaPrincipalTemp[$ci]) || $row['total'] > $areaPrincipalTemp[$ci]['total']) {
            $areaPrincipalTemp[$ci] = ['area_id' => $row['area_id'], 'total' => (float)$row['total']];
        }
    }
    // ETI acumulado da área principal (soma do ETI de cada pessoa na sua área dominante)
    $areaPrincipalMap = [];
    foreach ($areaPrincipalTemp as $ci => $ap) {
        $aid = $ap['area_id'];
        $areaPrincipalMap[$aid] = round(($areaPrincipalMap[$aid] ?? 0) + $ap['total'] / 100, 2);
    }

    // Heatmap: ETI por subárea × área (cada pessoa = 1 ETI distribuído pelo %)
    $heatRaw = $pdo->query(
        'SELECT subarea_id, area_id,
                SUM(CAST(valor AS DECIMAL(10,2)) / 100) AS eti
         FROM infodeqb_respostas
         GROUP BY subarea_id, area_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $heatMap = [];
    foreach ($heatRaw as $row) {
        $heatMap[$row['subarea_id']][$row['area_id']] = round((float)$row['eti'], 2);
    }

    // Totais por linha (subárea) e por coluna (área) e total geral
    $heatRowTotals = [];   // [subarea_id] => total ETI
    $heatColTotals = [];   // [area_id]    => total ETI
    $heatGrandTotal = 0.0;
    foreach ($heatMap as $sid => $areaVals) {
        foreach ($areaVals as $aid => $eti) {
            $heatRowTotals[$sid] = round(($heatRowTotals[$sid] ?? 0) + $eti, 2);
            $heatColTotals[$aid] = round(($heatColTotals[$aid] ?? 0) + $eti, 2);
            $heatGrandTotal      = round($heatGrandTotal + $eti, 2);
        }
    }
    // Valor máximo para escala de cor (excluindo totais)
    $heatMax = 0.01;
    foreach ($heatMap as $areaVals) {
        foreach ($areaVals as $eti) { if ($eti > $heatMax) $heatMax = $eti; }
    }

    // Todas as infodeqb_respostas individuais para o modal (indexado por código)
    $todasinfodeqb_respostasRaw = $pdo->query(
        'SELECT codigo_inquirido, area_id, subarea_id, CAST(valor AS UNSIGNED) AS valor
         FROM infodeqb_respostas WHERE CAST(valor AS UNSIGNED) > 0'
    )->fetchAll(PDO::FETCH_ASSOC);
    $todasinfodeqb_respostasMap = [];
    foreach ($todasinfodeqb_respostasRaw as $r) {
        $todasinfodeqb_respostasMap[$r['codigo_inquirido']][$r['subarea_id']][$r['area_id']] = (int)$r['valor'];
    }
}

Database::disconnect();

$pageTitle = 'Áreas Disciplinares';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<style>
/* Grelha de introdução de dados (inputs por célula + totais em JS) —
   propositadamente não é .table/DataTables: precisa de grelha
   completa e não faz sentido paginar/pesquisar uma matriz fixa. */
#tblAreas { font-size: .82rem; border-collapse: collapse; width: 100%; }
#tblAreas th, #tblAreas td {
    border: 1px solid var(--iq-border2); padding: 5px 8px; text-align: center; white-space: nowrap;
}
#tblAreas th { background: var(--iq-gray-50); font-weight: 600; }
#tblAreas td:first-child { text-align: left; white-space: normal; min-width: 160px; }
#tblAreas tr.total-row td { background: var(--iq-gray-200); font-weight: 700; }
#tblAreas input[type=number] {
    width: 56px; text-align: center; padding: 3px 4px;
    border: 1px solid var(--iq-border2); border-radius: 4px; font-size: .8rem;
}
#tblAreas input[type=number]:focus {
    outline: none; border-color: var(--iq-blue); box-shadow: 0 0 0 2px rgba(11,110,115,.15);
}
/* heatmap — escala de intensidade por ETI (degradê petróleo, claro→escuro) */
.hm-cell { font-size: .78rem; font-weight: 600; }
.hm-0  { background: var(--iq-gray-50); color: var(--iq-subtle); }
.hm-low  { background: var(--iq-blue-light); color: var(--iq-blue-dark); }
.hm-mid  { background: #7fc2c5; color: #073f42; }
.hm-high { background: #1f9298; color: #fff; }
.hm-top  { background: var(--iq-blue-dark); color: #fff; }
</style>

<div class="iq-page-header d-flex align-items-center">
  <div class="mr-auto">
    <h1><i class="fas fa-sitemap fa-sm me-2 text-muted"></i><?= t('AREAS_TITLE') ?></h1>
    <small class="text-muted">Associação dos docentes e investigadores de carreira</small>
  </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php if ($isAreasAdmin): ?>
<?php /* ═══════════════════════════════════════════════════════════
       VISTA ADMIN
       ═══════════════════════════════════════════════════════════ */ ?>

<?php /* ── Painel do form — topo da página ──────────────────── */ ?>
<div id="formPanel" class="collapse mb-4">
  <div style="border:2px solid var(--iq-blue);border-radius:8px;overflow:hidden">
    <div class="d-flex align-items-center px-3 py-2" style="background:var(--iq-blue)">
      <i class="fas fa-edit text-white me-2"></i>
      <strong class="text-white me-auto">A minha resposta</strong>
      <?php if ($teminfodeqb_respostas): ?>
      <span class="badge badge-light me-3" style="font-weight:500;font-size:.75rem">
        <i class="fas fa-check me-1 text-success"></i><?= t('AREAS_SUBMITTED') ?>
      </span>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-outline-light py-0"
              data-bs-toggle="collapse" data-bs-target="#formPanel">
        <i class="fas fa-times fa-xs me-1"></i><?= t('CLOSE') ?>
      </button>
    </div>
    <div class="p-3 bg-white">
      <div id="avisoTotal" class="alert alert-warning py-2 mb-3" style="font-size:.84rem;display:none">
        <i class="fas fa-exclamation-triangle me-1"></i>
        O total deve ser <strong>100%</strong>. Actual: <strong id="totalGeral">0</strong>%
      </div>
      <div id="avisoOk" class="alert alert-success py-2 mb-3" style="font-size:.84rem;display:none">
        <i class="fas fa-check-circle me-1"></i>Total correcto: <strong>100%</strong>. Pode submeter.
      </div>
      <form id="formAreas" method="post">
        <input type="hidden" name="codigo_inquirido" value="<?= htmlspecialchars($codigo_inquirido) ?>">
        <div style="overflow-x:auto">
          <table id="tblAreas">
            <thead>
              <tr>
                <th>Subárea</th>
                <?php foreach ($areas as $a): ?><th><?= htmlspecialchars($a['nome']) ?></th><?php endforeach; ?>
                <th style="background:#e9ecef">Subtotal</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($infodeqb_subareas as $sub): ?>
              <tr>
                <td><?= htmlspecialchars($sub['nome']) ?></td>
                <?php foreach ($areas as $a):
                  $val = $respIndex[$sub['id']][$a['id']] ?? 0; ?>
                <td><input type="number"
                           name="valores[<?= (int)$sub['id'] ?>][<?= (int)$a['id'] ?>]"
                           data-linha="<?= (int)$sub['id'] ?>"
                           class="linha-<?= (int)$sub['id'] ?>"
                           step="20" min="0" max="100"
                           value="<?= $val ?>"
                           oninput="onInput(this)"></td>
                <?php endforeach; ?>
                <td class="font-weight-bold" id="sub-<?= (int)$sub['id'] ?>">0</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="total-row">
                <td>Total</td>
                <?php foreach ($areas as $i => $a): ?><td id="col-<?= $i ?>">0</td><?php endforeach; ?>
                <td id="totalGeralCell">0</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-end mt-3" style="gap:10px">
          <button type="button" class="btn btn-outline-secondary btn-sm"
                  data-bs-toggle="collapse" data-bs-target="#formPanel"><?= t('CANCEL') ?></button>
          <button type="button" id="submitBtn" class="btn btn-primary btn-sm" disabled
                  <?= $teminfodeqb_respostas ? 'data-bs-toggle="modal" data-bs-target="#modalConfirm"' : 'onclick="document.getElementById(\'formAreas\').submit()"' ?>>
            <i class="fas fa-paper-plane me-1"></i>
            <?= $teminfodeqb_respostas ? t('AREAS_UPDATE') : t('AREAS_SUBMIT') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ── Estatísticas ────────────────────────────────────────── */ ?>
<div class="d-flex flex-wrap mb-4" style="gap:12px">
  <div class="card flex-fill shadow-sm text-center" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><?= t('AREAS_RESPONDED') ?></div>
      <div class="h4 mb-0 font-weight-bold text-success"><?= $totalResp ?> / <?= $totalPerm ?></div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm text-center" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1">Pendentes</div>
      <div class="h4 mb-0 font-weight-bold <?= $totalPend > 0 ? 'text-warning' : 'text-success' ?>">
        <?= $totalPend ?>
      </div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm text-center" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><?= t('AREAS_RATE') ?></div>
      <div class="h4 mb-0 font-weight-bold">
        <?= $totalPerm > 0 ? round($totalResp / $totalPerm * 100) : 0 ?>%
      </div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm d-flex align-items-center justify-content-center p-3" style="min-width:180px">
    <button class="btn btn-primary btn-sm" id="btnAbrirForm">
      <i class="fas fa-edit me-1"></i>
      <?= $teminfodeqb_respostas ? t('AREAS_FILL_EDIT') : t('AREAS_FILL') ?>
    </button>
  </div>
</div>

<?php /* ── Gráfico + lista ───────────────────────────────────────── */ ?>
<div class="row mb-4">
  <div class="col-md-5">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <strong>ETI por área</strong>
        <small class="text-muted ms-1 d-block" style="font-size:.75rem">
          Barras: ETI total por área &nbsp;|&nbsp; Ponto ●: ETI da área principal
        </small>
      </div>
      <div class="card-body">
        <canvas id="chartAreas"></canvas>
      </div>
    </div>
  </div>

<?php /* ── Lista de participantes ─────────────────────────────── */ ?>
  <div class="col-md-7">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2 d-flex align-items-center">
        <strong class="mr-auto">Participantes</strong>
        <span class="badge badge-secondary"><?= $totalResp ?> / <?= $totalPerm ?></span>
      </div>
      <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead class="" style="position:sticky;top:0;z-index:1">
            <tr>
              <th>Nome</th>
              <th class="text-center" style="width:7em">Estado</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($participantes as $p): ?>
            <tr class="<?= $p['respondeu'] ? 'btn-ver-resposta' : '' ?>"
                style="<?= $p['respondeu'] ? 'cursor:pointer' : '' ?>"
                <?php if ($p['respondeu']): ?>
                data-codigo="<?= htmlspecialchars($p['codigo']) ?>"
                data-nome="<?= htmlspecialchars($p['nome']) ?>"
                data-bs-toggle="modal" data-bs-target="#modalResposta"
                title="Ver resposta de <?= htmlspecialchars($p['nome']) ?>"
                <?php endif; ?>>
              <td class="align-middle">
                <?php if ($p['respondeu']): ?>
                <span class="text-primary" style="font-weight:500">
                  <?= htmlspecialchars($p['nome']) ?>
                </span>
                <?php else: ?>
                <?= htmlspecialchars($p['nome']) ?>
                <?php endif; ?>
                <small class="text-muted d-block" style="font-size:.72rem"><?= htmlspecialchars($p['codigo']) ?></small>
              </td>
              <td class="align-middle text-center">
                <?php if ($p['respondeu']): ?>
                <span class="badge badge-success"><i class="fas fa-check fa-xs me-1"></i>Submetida</span>
                <?php else: ?>
                <span class="badge badge-warning">Pendente</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php /* ── Heatmap: ETI por subárea × área ──────────────────── */ ?>
<div class="card shadow-sm mb-4">
  <div class="card-header py-2">
    <strong>ETI por subárea × área</strong>
    <small class="text-muted ms-1">(cada pessoa = 1 ETI distribuído proporcionalmente pelo %)</small>
  </div>
  <div class="card-body p-0" style="overflow-x:auto">
    <table class="table table-sm mb-0 iq-heatmap" style="font-size:.8rem">
      <thead class="">
        <tr>
          <th style="min-width:160px">Subárea</th>
          <?php foreach ($areas as $a): ?>
          <th class="text-center" style="min-width:65px"><?= htmlspecialchars($a['nome']) ?></th>
          <?php endforeach; ?>
          <th class="text-center iq-heatmap-total font-weight-700" style="min-width:60px">Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($infodeqb_subareas as $sub): ?>
        <tr>
          <td><?= htmlspecialchars($sub['nome']) ?></td>
          <?php foreach ($areas as $a):
            $v   = $heatMap[$sub['id']][$a['id']] ?? 0;
            $pct = $v / $heatMax;
            $cls = $v == 0 ? 'hm-0' : ($pct < .2 ? 'hm-low' : ($pct < .45 ? 'hm-mid' : ($pct < .7 ? 'hm-high' : 'hm-top')));
          ?>
          <td class="text-center hm-cell <?= $cls ?>">
            <?= $v > 0 ? number_format($v, 2) : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="text-center font-weight-bold iq-heatmap-total">
            <?= number_format($heatRowTotals[$sub['id']] ?? 0, 2) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="iq-heatmap-total font-weight-700">
          <td>Total</td>
          <?php foreach ($areas as $a): ?>
          <td class="text-center">
            <?= number_format($heatColTotals[$a['id']] ?? 0, 2) ?>
          </td>
          <?php endforeach; ?>
          <td class="text-center">
            <?= number_format($heatGrandTotal, 2) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php /* ── Modal: resposta individual ──────────────────────────── */ ?>
<div class="modal fade" id="modalResposta" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="modalRespostaTitulo">Resposta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body p-0" id="modalRespostaBody" style="overflow-x:auto"></div>
    </div>
  </div>
</div>

<?php endif; // $isAreasAdmin ?>

<?php /* ═══════════════════════════════════════════════════════════
       FORMULÁRIO — utilizadores normais (mostrado directamente)
       ═══════════════════════════════════════════════════════════ */ ?>
<?php if (!$isAreasAdmin): ?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center mb-3">
      <div style="font-size:.88rem;gap:1.5rem" class="d-flex">
        <span><span class="text-muted">Código:</span> <strong><?= htmlspecialchars($codigo_inquirido) ?></strong></span>
        <span><span class="text-muted">Nome:</span> <strong><?= htmlspecialchars($nomeUtilizador) ?></strong></span>
      </div>
      <?php if ($teminfodeqb_respostas): ?>
      <span class="badge badge-success ms-auto"><i class="fas fa-check me-1"></i><?= t('AREAS_SUBMITTED') ?></span>
      <?php else: ?>
      <span class="badge badge-warning ms-auto"><?= t('AREAS_NO_RESPONSE') ?></span>
      <?php endif; ?>
    </div>
    <div id="avisoTotal" class="alert alert-warning py-2 mb-3" style="font-size:.84rem;display:none">
      <i class="fas fa-exclamation-triangle me-1"></i>
      O total deve ser <strong>100%</strong>. Actual: <strong id="totalGeral">0</strong>%
    </div>
    <div id="avisoOk" class="alert alert-success py-2 mb-3" style="font-size:.84rem;display:none">
      <i class="fas fa-check-circle me-1"></i>Total correcto: <strong>100%</strong>. Pode submeter.
    </div>
    <form id="formAreas" method="post">
      <input type="hidden" name="codigo_inquirido" value="<?= htmlspecialchars($codigo_inquirido) ?>">
      <div style="overflow-x:auto">
        <table id="tblAreas">
          <thead>
            <tr>
              <th>Subárea</th>
              <?php foreach ($areas as $a): ?><th><?= htmlspecialchars($a['nome']) ?></th><?php endforeach; ?>
              <th style="background:#e9ecef">Subtotal</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($infodeqb_subareas as $sub): ?>
            <tr>
              <td><?= htmlspecialchars($sub['nome']) ?></td>
              <?php foreach ($areas as $a):
                $val = $respIndex[$sub['id']][$a['id']] ?? 0; ?>
              <td><input type="number"
                         name="valores[<?= (int)$sub['id'] ?>][<?= (int)$a['id'] ?>]"
                         data-linha="<?= (int)$sub['id'] ?>"
                         class="linha-<?= (int)$sub['id'] ?>"
                         step="20" min="0" max="100"
                         value="<?= $val ?>"
                         oninput="onInput(this)"></td>
              <?php endforeach; ?>
              <td class="font-weight-bold" id="sub-<?= (int)$sub['id'] ?>">0</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td>Total</td>
              <?php foreach ($areas as $i => $a): ?><td id="col-<?= $i ?>">0</td><?php endforeach; ?>
              <td id="totalGeralCell">0</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="d-flex align-items-center justify-content-end mt-3">
        <button type="button" id="submitBtn" class="btn btn-primary btn-sm" disabled
                <?= $teminfodeqb_respostas ? 'data-bs-toggle="modal" data-bs-target="#modalConfirm"' : 'onclick="document.getElementById(\'formAreas\').submit()"' ?>>
          <i class="fas fa-paper-plane me-1"></i>
          <?= $teminfodeqb_respostas ? t('AREAS_UPDATE') : t('AREAS_SUBMIT') ?>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; // !$isAreasAdmin ?>

<?php if ($teminfodeqb_respostas): ?>
<div class="modal fade" id="modalConfirm" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><?= t('AREAS_REPLACE') ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body">
      <p>Já existe uma resposta submetida. Os valores actuais serão substituídos.</p>
      <p class="text-muted small mb-0"><?= t('IRREVERSIBLE') ?></p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
      <button type="button" class="btn btn-primary"
              onclick="bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirm')).hide();document.getElementById('formAreas').submit()">
        <?= t('YES') ?>
      </button>
    </div>
  </div></div>
</div>
<?php endif; ?>

<?php if ($isAreasAdmin): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// ── Dados para o gráfico e modal ──────────────────────────────────
var areaLabels    = <?= json_encode(array_column($areas, 'nome')) ?>;
var areaIds       = <?= json_encode(array_column($areas, 'id')) ?>;
var subareaLabels = <?= json_encode(array_column($infodeqb_subareas, 'nome')) ?>;
var subareaIds    = <?= json_encode(array_column($infodeqb_subareas, 'id')) ?>;
var nTotal        = <?= $totalResp ?>;

// Nº de pessoas com qualquer % nessa área
var dataPessoas = <?= json_encode(array_map(function($a) use ($pessoasAreaMap) {
    return $pessoasAreaMap[$a['id']] ?? 0;
}, $areas)) ?>;

// Nº de pessoas cuja área principal é esta
var dataPrincipal = <?= json_encode(array_map(function($a) use ($areaPrincipalMap) {
    return $areaPrincipalMap[$a['id']] ?? 0;
}, $areas)) ?>;

// Gráfico: barras (quem tem % nessa área) + ponto (área principal)
new Chart(document.getElementById('chartAreas'), {
    data: {
        labels: areaLabels,
        datasets: [
            {
                type: 'bar',
                label: 'Com % nesta área',
                data: dataPessoas,
                backgroundColor: 'rgba(59,130,246,0.65)',
                borderColor: 'rgb(59,130,246)',
                borderWidth: 1,
                borderRadius: 3,
                order: 2,
            },
            {
                type: 'line',
                label: 'Área principal',
                data: dataPrincipal,
                showLine: false,
                pointStyle: 'circle',
                pointRadius: 7,
                pointHoverRadius: 9,
                pointBackgroundColor: 'rgb(239,68,68)',
                pointBorderColor: 'rgb(239,68,68)',
                order: 1,
            }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: function(c) {
                        return c.dataset.label + ': ' + parseFloat(c.raw).toFixed(2) + ' ETI';
                    }
                }
            }
        },
        scales: {
            x: {
                min: 0,
                ticks: { callback: function(v) { return v.toFixed(1); } }
            },
            y: { ticks: { font: { size: 11 } } }
        }
    }
});

// ── Modal de resposta individual ──────────────────────────────────
var todasinfodeqb_respostas = <?= json_encode($todasinfodeqb_respostasMap) ?>;

// Botão "Preencher/Editar resposta": abre collapse e faz scroll para o topo
// Bootstrap 5: usar API nativa em vez de $().collapse('show')
$('#btnAbrirForm').on('click', function () {
    var panelEl = document.getElementById('formPanel');
    if (!panelEl) return;
    if (panelEl.classList.contains('show')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        var bsCollapse = bootstrap.Collapse.getOrCreateInstance(panelEl, { toggle: false });
        panelEl.addEventListener('shown.bs.collapse', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, { once: true });
        bsCollapse.show();
    }
});

$(document).on('click', 'tr.btn-ver-resposta', function () {
    var codigo = $(this).data('codigo');
    var nome   = $(this).data('nome');
    $('#modalRespostaTitulo').text(nome);

    var resp = todasinfodeqb_respostas[codigo] || {};
    var html = '<table class="table table-sm mb-0" style="font-size:.8rem;border-collapse:collapse">';
    html += '<thead class=""><tr><th style="min-width:150px">Subárea</th>';
    areaLabels.forEach(function(a) { html += '<th class="text-center" style="min-width:65px">' + a + '</th>'; });
    html += '<th class="text-center" style="background:#e9ecef;min-width:60px">Total</th></tr></thead><tbody>';

    var colTotals = new Array(areaLabels.length).fill(0);
    var grandTotal = 0;

    subareaIds.forEach(function(sid, si) {
        var rowTotal = 0;
        var cells = '';
        areaIds.forEach(function(aid, ai) {
            var v = (resp[sid] && resp[sid][aid]) ? resp[sid][aid] : 0;
            rowTotal += v;
            colTotals[ai] += v;
            var style = v > 0
                ? 'background:rgba(59,130,246,' + (0.1 + v/100*0.7) + ');color:' + (v >= 40 ? '#fff' : '#1e3a8a') + ';font-weight:600'
                : 'color:#adb5bd';
            cells += '<td class="text-center" style="border:1px solid #dee2e6;' + style + '">' + (v > 0 ? v + '%' : '—') + '</td>';
        });
        grandTotal += rowTotal;
        html += '<tr><td style="border:1px solid #dee2e6">' + subareaLabels[si] + '</td>'
              + cells
              + '<td class="text-center font-weight-bold" style="border:1px solid #dee2e6;background:#f8f9fa">'
              + (rowTotal > 0 ? rowTotal + '%' : '—') + '</td></tr>';
    });

    html += '<tr style="background:#e9ecef;font-weight:700"><td>Total</td>';
    colTotals.forEach(function(t) {
        html += '<td class="text-center" style="border:1px solid #dee2e6">' + (t > 0 ? t + '%' : '—') + '</td>';
    });
    html += '<td class="text-center" style="border:1px solid #dee2e6">' + grandTotal + '%</td></tr>';
    html += '</tbody></table>';

    $('#modalRespostaBody').html(html);
});
</script>
<?php endif; ?>

<script>
var nAreas = <?= count($areas) ?>;

function recalc() {
    var colTotals = new Array(nAreas).fill(0);
    var totalGeral = 0;
    document.querySelectorAll('#tblAreas tbody tr').forEach(function (tr) {
        var lid = (tr.querySelector('[data-linha]') || {}).dataset && tr.querySelector('[data-linha]').dataset.linha;
        if (!lid) return;
        var sub = 0;
        tr.querySelectorAll('input[type=number]').forEach(function (inp, ci) {
            var v = parseInt(inp.value) || 0;
            sub += v; colTotals[ci] += v;
        });
        var el = document.getElementById('sub-' + lid);
        if (el) el.textContent = sub;
        totalGeral += sub;
    });
    colTotals.forEach(function (t, i) {
        var el = document.getElementById('col-' + i);
        if (el) el.textContent = t;
    });
    var gc = document.getElementById('totalGeralCell');
    if (gc) gc.textContent = totalGeral;
    var tg = document.getElementById('totalGeral');
    if (tg) tg.textContent = totalGeral;
    var ok = (totalGeral === 100);
    document.getElementById('avisoTotal').style.display = ok ? 'none' : 'block';
    document.getElementById('avisoOk').style.display   = ok ? 'block' : 'none';
    document.getElementById('submitBtn').disabled = !ok;
}

function onInput(el) {
    var v = parseInt(el.value.replace(/\D/g, ''), 10) || 0;
    el.value = Math.round(v / 20) * 20;
    recalc();
}

document.addEventListener('DOMContentLoaded', recalc);
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
