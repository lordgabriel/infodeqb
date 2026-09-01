<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? array();
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser ?? '', $_iqAdminsGases);

if (!$isGasAdmin) {
    http_response_code(403);
    die('Acesso negado.');
}

$jsonFile = __DIR__ . '/data/encomendas.json';
$filesDir = ROOT_DIR . '/infodeqb/files';
$flash    = '';
$flashType = 'success';

/* ── POST handler ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg = json_decode(file_get_contents($jsonFile), true) ?: array();

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'save_info') {
        $cfg['email']           = trim($_POST['email'] ?? '');
        $cfg['contrato']        = trim($_POST['contrato'] ?? '');
        $cfg['instrucoes_extra'] = trim($_POST['instrucoes_extra'] ?? '');
        file_put_contents($jsonFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $flash = 'Informações gerais guardadas.';

    } elseif ($acao === 'save_ficheiro') {
        $cfg['ficheiro']['descricao']       = trim($_POST['descricao'] ?? '');
        $cfg['ficheiro']['data_atualizacao'] = trim($_POST['data_atualizacao'] ?? '');

        // Upload de ficheiro Excel
        if (!empty($_FILES['ficheiro_xlsx']['name'])) {
            $up = $_FILES['ficheiro_xlsx'];
            $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('xlsx', 'xls'), true)) {
                $flash = 'Ficheiro inválido — só são aceites .xlsx e .xls.';
                $flashType = 'danger';
            } elseif ($up['error'] !== UPLOAD_ERR_OK) {
                $flash = 'Erro no upload (código ' . $up['error'] . ').';
                $flashType = 'danger';
            } else {
                $novoNome = 'Encomenda_gases_especiais.' . $ext;
                move_uploaded_file($up['tmp_name'], $filesDir . '/' . $novoNome);
                $cfg['ficheiro']['nome'] = $novoNome;
                $flash = 'Ficheiro e descrição guardados.';
            }
        } else {
            $flash = 'Descrição e data guardadas (ficheiro não substituído).';
        }
        if ($flashType !== 'danger') {
            file_put_contents($jsonFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

    } elseif ($acao === 'save_gases') {
        $raw = $_POST['gases_garrafa_json'] ?? '[]';
        $gasesG = json_decode($raw, true);
        if (!is_array($gasesG)) { $gasesG = array(); }
        $cfg['gases_garrafa'] = $gasesG;

        $rawL = $_POST['gases_liquefeitos_json'] ?? '[]';
        $gasesL = json_decode($rawL, true);
        if (!is_array($gasesL)) { $gasesL = array(); }
        $cfg['gases_liquefeitos'] = $gasesL;

        file_put_contents($jsonFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $flash = 'Tabela de gases guardada.';
    }
}

/* ── Recarrega config para a view ─────────────────────────────────── */
$cfg  = json_decode(file_get_contents($jsonFile), true) ?: array();
$gasesGJson  = json_encode($cfg['gases_garrafa']    ?? array(), JSON_UNESCAPED_UNICODE);
$gasesLJson  = json_encode($cfg['gases_liquefeitos'] ?? array(), JSON_UNESCAPED_UNICODE);

$pageTitle = 'Admin — Encomenda de Gases';
$mainClass = 'iq-main';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title">
      <i class="fas fa-cog me-2 text-secondary"></i>Editar — Encomenda de Gases Especiais
    </h1>
  </div>
  <div class="ms-auto">
    <a href="encomendas.php" class="btn btn-sm btn-outline-primary">
      <i class="fas fa-eye me-1"></i>Ver página pública
    </a>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
  <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
  <?= htmlspecialchars($flash) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Card 1: Informações gerais ─────────────────────────────────── -->
<div class="card mb-4">
  <div class="card-header fw-semibold">
    <i class="fas fa-info-circle me-2 text-primary"></i>Card "Como encomendar" — informações gerais
  </div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="acao" value="save_info">
      <div class="mb-3">
        <label class="form-label fw-semibold">Email de encomenda</label>
        <input type="email" name="email" class="form-control" style="max-width:320px"
               value="<?= htmlspecialchars($cfg['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Nota de contrato <small class="text-muted">(subtítulo da página)</small></label>
        <input type="text" name="contrato" class="form-control"
               value="<?= htmlspecialchars($cfg['contrato'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Instruções adicionais <small class="text-muted">(opcional — aparece após os 3 passos)</small></label>
        <textarea name="instrucoes_extra" class="form-control" rows="3"
                  placeholder="Ex: Para gases com prazo longo, contactar diretamente..."><?= htmlspecialchars($cfg['instrucoes_extra'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
    </form>
  </div>
</div>

<!-- ── Card 2: Ficheiro de encomenda ──────────────────────────────── -->
<div class="card mb-4">
  <div class="card-header fw-semibold">
    <i class="fas fa-file-excel me-2 text-success"></i>Card "Ficheiro de encomenda"
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="acao" value="save_ficheiro">
      <div class="mb-3">
        <label class="form-label fw-semibold">Descrição do ficheiro</label>
        <textarea name="descricao" class="form-control" rows="2"><?= htmlspecialchars($cfg['ficheiro']['descricao'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Data de atualização <small class="text-muted">(texto livre, ex: abril de 2025)</small></label>
        <input type="text" name="data_atualizacao" class="form-control" style="max-width:260px"
               value="<?= htmlspecialchars($cfg['ficheiro']['data_atualizacao'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Substituir ficheiro Excel</label>
        <input type="file" name="ficheiro_xlsx" class="form-control" accept=".xlsx,.xls" style="max-width:420px">
        <?php if (!empty($cfg['ficheiro']['nome'])): ?>
        <div class="text-muted mt-1" style="font-size:.8rem">
          Ficheiro actual: <code><?= htmlspecialchars($cfg['ficheiro']['nome']) ?></code>
        </div>
        <?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
    </form>
  </div>
</div>

<!-- ── Card 3: Tabela de gases ────────────────────────────────────── -->
<div class="card mb-4">
  <div class="card-header fw-semibold d-flex align-items-center gap-2">
    <span><i class="fas fa-list me-2"></i>Tabela de gases</span>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-outline-primary" onclick="addRow('tbl-edit-g','garrafa')">
        <i class="fas fa-plus me-1"></i>Adicionar gás em garrafa
      </button>
      <button class="btn btn-sm btn-outline-secondary" onclick="addRow('tbl-edit-l','liquido')">
        <i class="fas fa-plus me-1"></i>Adicionar gás liquefeito
      </button>
    </div>
  </div>
  <div class="card-body">
    <form method="post" id="form-gases">
      <input type="hidden" name="acao" value="save_gases">
      <input type="hidden" name="gases_garrafa_json"    id="gases-garrafa-json">
      <input type="hidden" name="gases_liquefeitos_json" id="gases-liquefeitos-json">

      <!-- Gases em garrafa -->
      <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">Gases em garrafa</h6>
      <div class="table-responsive mb-4">
        <table class="table table-sm table-bordered align-middle" id="tbl-edit-g">
          <thead class="table-light">
            <tr>
              <th>Gás</th><th>Pureza</th><th>Designação comercial</th>
              <th>Garrafa</th><th style="width:90px">Prazo (dias)</th>
              <th style="width:120px">Preço (€, s/IVA)</th><th style="width:48px"></th>
            </tr>
          </thead>
          <tbody id="tbody-garrafa"></tbody>
        </table>
      </div>

      <!-- Gases liquefeitos -->
      <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">Gases liquefeitos</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered align-middle" id="tbl-edit-l">
          <thead class="table-light">
            <tr>
              <th>Gás/Produto</th><th>Pureza</th><th>Recipiente</th>
              <th style="width:90px">Prazo (dias)</th>
              <th style="width:110px">Preço</th><th style="width:90px">Unidade</th>
              <th style="width:48px"></th>
            </tr>
          </thead>
          <tbody id="tbody-liquefeito"></tbody>
        </table>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar tabela de gases</button>
    </form>
  </div>
</div>

<script>
var gasesG = <?= $gasesGJson ?>;
var gasesL = <?= $gasesLJson ?>;

function inp(val, name, cls) {
  cls = cls || '';
  val = val === undefined ? '' : val;
  return '<input type="text" class="form-control form-control-sm ' + cls + '" value="' + htmlEsc(String(val)) + '" data-field="' + name + '">';
}
function htmlEsc(s) {
  return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function delBtn() {
  return '<button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)"><i class="fas fa-trash"></i></button>';
}

function renderGarrafa(tbody, rows) {
  var html = '';
  (rows || []).forEach(function(g) {
    html += '<tr>' +
      '<td>' + inp(g.gas,         'gas') + '</td>' +
      '<td>' + inp(g.pureza,      'pureza') + '</td>' +
      '<td>' + inp(g.designacao,  'designacao') + '</td>' +
      '<td>' + inp(g.garrafa,     'garrafa') + '</td>' +
      '<td>' + inp(g.prazo,       'prazo', 'text-end') + '</td>' +
      '<td>' + inp(g.preco,       'preco', 'text-end') + '</td>' +
      '<td>' + delBtn() + '</td>' +
    '</tr>';
  });
  tbody.innerHTML = html;
}

function renderLiquefeito(tbody, rows) {
  var html = '';
  (rows || []).forEach(function(g) {
    html += '<tr>' +
      '<td>' + inp(g.gas,        'gas') + '</td>' +
      '<td>' + inp(g.pureza,     'pureza') + '</td>' +
      '<td>' + inp(g.recipiente, 'recipiente') + '</td>' +
      '<td>' + inp(g.prazo,      'prazo', 'text-end') + '</td>' +
      '<td>' + inp(g.preco,      'preco', 'text-end') + '</td>' +
      '<td>' + inp(g.unidade,    'unidade') + '</td>' +
      '<td>' + delBtn() + '</td>' +
    '</tr>';
  });
  tbody.innerHTML = html;
}

function collectGarrafa() {
  var rows = [];
  document.querySelectorAll('#tbody-garrafa tr').forEach(function(tr) {
    var obj = {};
    tr.querySelectorAll('[data-field]').forEach(function(inp) { obj[inp.dataset.field] = inp.value; });
    if (obj.gas) rows.push(obj);
  });
  return rows;
}
function collectLiquefeito() {
  var rows = [];
  document.querySelectorAll('#tbody-liquefeito tr').forEach(function(tr) {
    var obj = {};
    tr.querySelectorAll('[data-field]').forEach(function(inp) { obj[inp.dataset.field] = inp.value; });
    if (obj.gas) rows.push(obj);
  });
  return rows;
}

function addRow(tableId, tipo) {
  var tbody = document.querySelector('#' + tableId + ' tbody');
  var tr = document.createElement('tr');
  if (tipo === 'garrafa') {
    tr.innerHTML =
      '<td>' + inp('', 'gas') + '</td>' +
      '<td>' + inp('', 'pureza') + '</td>' +
      '<td>' + inp('', 'designacao') + '</td>' +
      '<td>' + inp('', 'garrafa') + '</td>' +
      '<td>' + inp('', 'prazo', 'text-end') + '</td>' +
      '<td>' + inp('', 'preco', 'text-end') + '</td>' +
      '<td>' + delBtn() + '</td>';
  } else {
    tr.innerHTML =
      '<td>' + inp('', 'gas') + '</td>' +
      '<td>' + inp('', 'pureza') + '</td>' +
      '<td>' + inp('', 'recipiente') + '</td>' +
      '<td>' + inp('', 'prazo', 'text-end') + '</td>' +
      '<td>' + inp('', 'preco', 'text-end') + '</td>' +
      '<td>' + inp('L', 'unidade') + '</td>' +
      '<td>' + delBtn() + '</td>';
  }
  tbody.appendChild(tr);
  tr.querySelector('input').focus();
}

function delRow(btn) {
  if (confirm('Remover esta linha?')) btn.closest('tr').remove();
}

document.getElementById('form-gases').addEventListener('submit', function() {
  document.getElementById('gases-garrafa-json').value    = JSON.stringify(collectGarrafa());
  document.getElementById('gases-liquefeitos-json').value = JSON.stringify(collectLiquefeito());
});

// Render inicial
renderGarrafa(   document.querySelector('#tbody-garrafa'),   gasesG);
renderLiquefeito(document.querySelector('#tbody-liquefeito'), gasesL);
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
