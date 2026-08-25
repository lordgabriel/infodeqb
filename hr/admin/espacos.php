<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/common.php';
require_once __DIR__ . '/auth.php';

date_default_timezone_set('Europe/Lisbon');
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$msg = ''; $msgType = 'success';

// ── POST handlers ─────────────────────────────────────────────────────────────
if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    // Guardar espaço (insert ou update)
    if ($action === 'save_gab') {
        $id       = (int)($_POST['id'] ?? 0);
        $gabid    = trim($_POST['gabid']    ?? '');
        $deqid    = trim($_POST['deqid']    ?? '');
        $edificio = trim($_POST['edificio'] ?? '');
        $piso     = trim($_POST['piso']     ?? '');
        $nomept   = trim($_POST['nomegab']  ?? '');
        $nomeen   = trim($_POST['nomegab_en'] ?? '');
        $resp     = (int)($_POST['responsavel'] ?? 0);
        $visible  = isset($_POST['visible']) ? 1 : 0;

        if (!$gabid || !$deqid || !$nomept) {
            $msg = 'Os campos gabid, deqid e nome (PT) são obrigatórios.'; $msgType = 'danger';
        } else {
            if ($id) {
                $pdo->prepare(
                    "UPDATE infodeqb_rds_gabinetes
                     SET gabid=?,deqid=?,edificio=?,piso=?,nomegab=?,nomegab_en=?,responsavel=?,visible=?
                     WHERE id=?"
                )->execute([$gabid,$deqid,$edificio,$piso,$nomept,$nomeen,$resp,$visible,$id]);
                $msg = 'Espaço actualizado.';
            } else {
                $pdo->prepare(
                    "INSERT INTO infodeqb_rds_gabinetes (gabid,deqid,edificio,piso,nomegab,nomegab_en,responsavel,visible)
                     VALUES (?,?,?,?,?,?,?,?)"
                )->execute([$gabid,$deqid,$edificio,$piso,$nomept,$nomeen,$resp,$visible]);
                $msg = 'Espaço criado.';
            }
        }
        header('Location: espacos.php?tab=gab&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }

    // Eliminar espaço
    if ($action === 'delete_gab') {
        $id = (int)($_POST['id'] ?? 0);
        $q  = $pdo->prepare("SELECT COUNT(*) FROM infodeqb_rds_registo_acessos WHERE lab_id=(SELECT deqid FROM infodeqb_rds_gabinetes WHERE id=?)");
        $q->execute([$id]);
        if ((int)$q->fetchColumn() > 0) {
            $msg = 'Não é possível eliminar — este espaço tem registos de acesso associados.'; $msgType = 'danger';
        } else {
            $pdo->prepare("DELETE FROM infodeqb_rds_gabinetes WHERE id=?")->execute([$id]);
            $msg = 'Espaço eliminado.';
        }
        header('Location: espacos.php?tab=gab&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }

    // Guardar responsável (insert ou update)
    if ($action === 'save_resp') {
        $codigo = (int)($_POST['codigo'] ?? 0);
        $nome   = trim($_POST['respespaco'] ?? '');
        $sigla  = trim($_POST['sigla']      ?? '');
        $isNew  = isset($_POST['is_new']);

        if (!$codigo || !$nome) {
            $msg = 'Código e nome são obrigatórios.'; $msgType = 'danger';
        } elseif ($isNew) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM infodeqb_rds_responsaveis WHERE Codigo=?");
            $chk->execute([$codigo]);
            if ((int)$chk->fetchColumn() > 0) {
                $msg = 'Já existe um responsável com esse código.'; $msgType = 'danger';
            } else {
                $pdo->prepare("INSERT INTO infodeqb_rds_responsaveis (Codigo,respespaco,Sigla) VALUES (?,?,?)")
                    ->execute([$codigo,$nome,$sigla]);
                $msg = 'Responsável criado.';
            }
        } else {
            $pdo->prepare("UPDATE infodeqb_rds_responsaveis SET respespaco=?,Sigla=? WHERE Codigo=?")
                ->execute([$nome,$sigla,$codigo]);
            $msg = 'Responsável actualizado.';
        }
        header('Location: espacos.php?tab=resp&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }

    // Eliminar responsável
    if ($action === 'delete_resp') {
        $codigo = (int)($_POST['codigo'] ?? 0);
        $q = $pdo->prepare("SELECT COUNT(*) FROM infodeqb_rds_gabinetes WHERE responsavel=?");
        $q->execute([$codigo]);
        if ((int)$q->fetchColumn() > 0) {
            $msg = 'Não é possível eliminar — este responsável está associado a um ou mais espaços.'; $msgType = 'danger';
        } else {
            $pdo->prepare("DELETE FROM infodeqb_rds_responsaveis WHERE Codigo=?")->execute([$codigo]);
            $msg = 'Responsável eliminado.';
        }
        header('Location: espacos.php?tab=resp&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }
}

// ── Dados ─────────────────────────────────────────────────────────────────────
$gabs = $pdo->query(
    "SELECT g.*, r.respespaco AS resp_nome
     FROM infodeqb_rds_gabinetes g
     LEFT JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
     ORDER BY g.edificio, g.piso, g.nomegab"
)->fetchAll(PDO::FETCH_ASSOC);

$resps = $pdo->query(
    "SELECT r.*, COUNT(g.id) AS n_espacos
     FROM infodeqb_rds_responsaveis r
     LEFT JOIN infodeqb_rds_gabinetes g ON g.responsavel = r.Codigo
     GROUP BY r.Codigo ORDER BY r.respespaco"
)->fetchAll(PDO::FETCH_ASSOC);

$activeTab = $_GET['tab'] ?? 'gab';
$flashMsg  = $_GET['msg'] ?? '';
$flashType = $_GET['t']   ?? 'success';

$pageTitle = 'Espaços e Responsáveis';
$mainClass = 'iq-hr-page';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-door-open fa-sm me-2 text-muted"></i>Espaços e Responsáveis</h1>
  <div class="iq-page-header-actions">
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>Voltar
    </a>
  </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-header" style="padding-bottom:0">
    <ul class="nav nav-tabs card-header-tabs" id="mainTabs">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'gab' ? 'active' : '' ?>" href="espacos.php?tab=gab">
          <i class="fas fa-door-open me-1"></i>Espaços
          <span class="badge bg-secondary ms-1"><?= count($gabs) ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'resp' ? 'active' : '' ?>" href="espacos.php?tab=resp">
          <i class="fas fa-user-tie me-1"></i>Responsáveis
          <span class="badge bg-secondary ms-1"><?= count($resps) ?></span>
        </a>
      </li>
    </ul>
  </div>
  <div class="card-body">

  <?php if ($activeTab === 'gab'): ?>
  <!-- ── TAB ESPAÇOS ────────────────────────────────────────────────────── -->
  <div class="d-flex align-items-center justify-content-between mb-2">
    <!-- Filtros -->
    <div class="d-flex gap-2 flex-wrap align-items-center" style="font-size:.84rem">
      <?php
      $edificios = array_unique(array_column($gabs, 'edificio'));
      $pisos     = array_unique(array_column($gabs, 'piso'));
      sort($edificios); sort($pisos);
      $respNomes = array();
      foreach ($gabs as $_g) { if (!empty($_g['resp_nome'])) $respNomes[$_g['resp_nome']] = true; }
      ksort($respNomes);
      ?>
      <select id="fEdificio" class="form-select form-select-sm" style="width:auto" onchange="filtrarGabs()">
        <option value="">Edifício</option>
        <?php foreach ($edificios as $e): ?><option><?= htmlspecialchars($e) ?></option><?php endforeach; ?>
      </select>
      <select id="fPiso" class="form-select form-select-sm" style="width:auto" onchange="filtrarGabs()">
        <option value="">Piso</option>
        <?php foreach ($pisos as $p): ?><option><?= htmlspecialchars($p) ?></option><?php endforeach; ?>
      </select>
      <input type="text" id="fNome" class="form-control form-control-sm" style="width:160px"
             placeholder="Nome..." oninput="filtrarGabs()">
      <select id="fResp" class="form-select form-select-sm" style="width:auto" onchange="filtrarGabs()">
        <option value="">Responsável</option>
        <?php foreach (array_keys($respNomes) as $rn): ?><option><?= htmlspecialchars($rn) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-secondary" onclick="limparFiltros()">
        <i class="fas fa-times fa-xs"></i>
      </button>
    </div>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalGab" onclick="gabNew()">
      <i class="fas fa-plus me-1"></i>Novo espaço
    </button>
  </div>

  <table class="table table-sm table-bordered table-hover" id="tblGabs" style="font-size:.85rem">
    <thead class="table-light">
      <tr>
        <th>gabid</th><th>deqid</th>
        <th>Nome (PT)</th><th>Nome (EN)</th><th>Responsável</th>
        <th class="text-center" title="Visível no formulário de pedido">Vis.</th>
        <th style="width:1%">Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $curEdif = null; $curPiso = null;
    foreach ($gabs as $g):
      if ($g['edificio'] !== $curEdif || $g['piso'] !== $curPiso):
        if ($g['edificio'] !== $curEdif):
          $curEdif = $g['edificio'];
    ?>
    <tr class="gab-group-header" data-edificio="<?= htmlspecialchars($g['edificio']) ?>"
        data-piso="<?= htmlspecialchars($g['piso']) ?>">
      <td colspan="7" style="background:#2475ba;color:#fff;font-weight:600;padding:4px 8px;font-size:.8rem;">
        <i class="fas fa-building fa-xs me-1"></i><?= htmlspecialchars($g['edificio']) ?>
      </td>
    </tr>
    <?php endif; $curPiso = $g['piso']; ?>
    <tr class="gab-group-header" data-edificio="<?= htmlspecialchars($g['edificio']) ?>"
        data-piso="<?= htmlspecialchars($g['piso']) ?>">
      <td colspan="7" style="background:#dde6f0;color:#2c3e50;font-weight:600;padding:3px 16px;font-size:.78rem;">
        <i class="fas fa-layer-group fa-xs me-1"></i><?= htmlspecialchars($g['piso']) ?>
      </td>
    </tr>
    <?php endif; ?>
    <tr class="gab-row <?= !$g['visible'] ? 'text-muted' : '' ?>"
        data-edificio="<?= htmlspecialchars($g['edificio']) ?>"
        data-piso="<?= htmlspecialchars($g['piso']) ?>"
        data-nome="<?= htmlspecialchars(mb_strtolower($g['nomegab'] . ' ' . $g['nomegab_en'], 'UTF-8')) ?>"
        data-resp="<?= htmlspecialchars($g['resp_nome'] ?? '') ?>">
      <td><?= htmlspecialchars($g['gabid']) ?></td>
      <td><?= htmlspecialchars($g['deqid']) ?></td>
      <td><?= htmlspecialchars($g['nomegab']) ?></td>
      <td><?= htmlspecialchars($g['nomegab_en']) ?></td>
      <td><?= htmlspecialchars($g['resp_nome'] ?? '—') ?></td>
      <td class="text-center"><?= $g['visible'] ? '✓' : '<span class="text-danger">✗</span>' ?></td>
      <td style="white-space:nowrap">
        <button class="btn btn-xs btn-outline-primary"
                onclick="gabEdit(<?= htmlspecialchars(json_encode($g)) ?>)"
                data-bs-toggle="modal" data-bs-target="#modalGab">
          <i class="fas fa-edit fa-xs"></i>
        </button>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Eliminar este espaço?')">
          <input type="hidden" name="action" value="delete_gab">
          <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
          <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash fa-xs"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php else: ?>
  <!-- ── TAB RESPONSÁVEIS ──────────────────────────────────────────────── -->
  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalResp" onclick="respNew()">
      <i class="fas fa-plus me-1"></i>Novo responsável
    </button>
  </div>
  <table class="table table-sm table-bordered table-hover" style="font-size:.85rem">
    <thead class="table-light">
      <tr><th>Código UP</th><th>Nome completo</th><th>Sigla</th><th class="text-center">Espaços</th><th style="width:1%">Ações</th></tr>
    </thead>
    <tbody>
    <?php foreach ($resps as $r): ?>
    <tr>
      <td>up<?= htmlspecialchars($r['Codigo']) ?></td>
      <td><?= htmlspecialchars($r['respespaco']) ?></td>
      <td><?= htmlspecialchars($r['Sigla'] ?? '') ?></td>
      <td class="text-center"><?= (int)$r['n_espacos'] ?></td>
      <td style="white-space:nowrap">
        <button class="btn btn-xs btn-outline-primary"
                onclick="respEdit(<?= htmlspecialchars(json_encode($r)) ?>)"
                data-bs-toggle="modal" data-bs-target="#modalResp">
          <i class="fas fa-edit fa-xs"></i>
        </button>
        <?php if ((int)$r['n_espacos'] === 0): ?>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Eliminar este responsável?')">
          <input type="hidden" name="action" value="delete_resp">
          <input type="hidden" name="codigo" value="<?= (int)$r['Codigo'] ?>">
          <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash fa-xs"></i></button>
        </form>
        <?php else: ?>
        <button class="btn btn-xs btn-outline-danger" disabled title="Tem espaços associados">
          <i class="fas fa-trash fa-xs"></i>
        </button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  </div><!-- /.card-body -->
</div><!-- /.card -->

<!-- ── Modal Espaço ──────────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalGab" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="save_gab">
        <input type="hidden" name="id"     id="gab_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="modalGabTitle">Novo espaço</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-sm-3">
              <label class="form-label form-label-sm">gabid <span class="text-danger">*</span></label>
              <input type="text" name="gabid" id="gab_gabid" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-3">
              <label class="form-label form-label-sm">deqid <span class="text-danger">*</span></label>
              <input type="text" name="deqid" id="gab_deqid" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-3">
              <label class="form-label form-label-sm">Edifício</label>
              <input type="text" name="edificio" id="gab_edificio" class="form-control form-control-sm">
            </div>
            <div class="col-sm-3">
              <label class="form-label form-label-sm">Piso</label>
              <input type="text" name="piso" id="gab_piso" class="form-control form-control-sm">
            </div>
            <div class="col-sm-6">
              <label class="form-label form-label-sm">Nome (PT) <span class="text-danger">*</span></label>
              <input type="text" name="nomegab" id="gab_nomegab" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label form-label-sm">Nome (EN)</label>
              <input type="text" name="nomegab_en" id="gab_nomegab_en" class="form-control form-control-sm">
            </div>
            <div class="col-sm-9">
              <label class="form-label form-label-sm">Responsável</label>
              <select name="responsavel" id="gab_responsavel" class="form-select form-select-sm">
                <option value="0">— Sem responsável —</option>
                <?php foreach ($resps as $r): ?>
                <option value="<?= (int)$r['Codigo'] ?>"><?= htmlspecialchars($r['respespaco']) ?> (up<?= $r['Codigo'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-3 d-flex align-items-end">
              <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" name="visible" id="gab_visible" value="1" checked>
                <label class="form-check-label form-label-sm" for="gab_visible">Visível</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal Responsável ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalResp" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="save_resp">
        <input type="hidden" name="is_new" id="resp_is_new" value="1">
        <div class="modal-header">
          <h5 class="modal-title" id="modalRespTitle">Novo responsável</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-sm-4">
              <label class="form-label form-label-sm">Código UP <span class="text-danger">*</span></label>
              <input type="number" name="codigo" id="resp_codigo" class="form-control form-control-sm" required min="1">
              <div class="form-text">Só o número (ex: 123456)</div>
            </div>
            <div class="col-sm-8">
              <label class="form-label form-label-sm">Nome completo <span class="text-danger">*</span></label>
              <input type="text" name="respespaco" id="resp_nome" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-4">
              <label class="form-label form-label-sm">Sigla</label>
              <input type="text" name="sigla" id="resp_sigla" class="form-control form-control-sm">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function filtrarGabs() {
  var fEdif = document.getElementById('fEdificio').value;
  var fPiso = document.getElementById('fPiso').value;
  var fNome = document.getElementById('fNome').value.toLowerCase();
  var fResp = document.getElementById('fResp').value;

  var visibleGroups = {};
  document.querySelectorAll('#tblGabs .gab-row').forEach(function(row) {
    var edificio = row.dataset.edificio;
    var piso     = row.dataset.piso;
    var nome     = row.dataset.nome;
    var resp     = row.dataset.resp;

    var match = (!fEdif || edificio === fEdif)
             && (!fPiso || piso === fPiso)
             && (!fNome || nome.indexOf(fNome) >= 0)
             && (!fResp || resp === fResp);

    row.style.display = match ? '' : 'none';
    if (match) visibleGroups[edificio + '|' + piso] = true;
  });

  document.querySelectorAll('#tblGabs .gab-group-header').forEach(function(h) {
    var key = h.dataset.edificio + '|' + h.dataset.piso;
    h.style.display = visibleGroups[key] ? '' : 'none';
  });
}

function limparFiltros() {
  document.getElementById('fEdificio').value = '';
  document.getElementById('fPiso').value     = '';
  document.getElementById('fNome').value     = '';
  document.getElementById('fResp').value     = '';
  filtrarGabs();
}

function gabNew() {
  document.getElementById('modalGabTitle').textContent = 'Novo espaço';
  document.getElementById('gab_id').value      = '0';
  document.getElementById('gab_gabid').value   = '';
  document.getElementById('gab_deqid').value   = '';
  document.getElementById('gab_edificio').value= '';
  document.getElementById('gab_piso').value    = '';
  document.getElementById('gab_nomegab').value = '';
  document.getElementById('gab_nomegab_en').value = '';
  document.getElementById('gab_responsavel').value = '0';
  document.getElementById('gab_visible').checked   = true;
  document.getElementById('gab_gabid').removeAttribute('readonly');
  document.getElementById('gab_deqid').removeAttribute('readonly');
}
function gabEdit(g) {
  document.getElementById('modalGabTitle').textContent = 'Editar espaço';
  document.getElementById('gab_id').value         = g.id;
  document.getElementById('gab_gabid').value      = g.gabid;
  document.getElementById('gab_deqid').value      = g.deqid;
  document.getElementById('gab_edificio').value   = g.edificio;
  document.getElementById('gab_piso').value       = g.piso;
  document.getElementById('gab_nomegab').value    = g.nomegab;
  document.getElementById('gab_nomegab_en').value = g.nomegab_en;
  document.getElementById('gab_responsavel').value= g.responsavel;
  document.getElementById('gab_visible').checked  = g.visible == 1;
}
function respNew() {
  document.getElementById('modalRespTitle').textContent = 'Novo responsável';
  document.getElementById('resp_is_new').value  = '1';
  document.getElementById('resp_codigo').value  = '';
  document.getElementById('resp_nome').value    = '';
  document.getElementById('resp_sigla').value   = '';
  document.getElementById('resp_codigo').removeAttribute('readonly');
}
function respEdit(r) {
  document.getElementById('modalRespTitle').textContent = 'Editar responsável';
  document.getElementById('resp_is_new').value  = '';
  document.getElementById('resp_codigo').value  = r.Codigo;
  document.getElementById('resp_nome').value    = r.respespaco;
  document.getElementById('resp_sigla').value   = r.Sigla || '';
  document.getElementById('resp_codigo').setAttribute('readonly', 'readonly');
}
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
