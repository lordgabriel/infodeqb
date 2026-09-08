<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);
if (!$isGasAdmin) { header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit; }

/* ── AJAX: apagar ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apagar_hist') {
    header('Content-Type: application/json; charset=utf-8');
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    if (empty($ids)) { echo json_encode(['ok' => false, 'erro' => 'Nenhum ID.']); exit; }
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM infodeqb_encomenda_gases WHERE id IN ($ph)")->execute(array_values($ids));
    Database::disconnect();
    echo json_encode(['ok' => true, 'apagados' => count($ids)]);
    exit;
}

/* ── Carrega histórico ──────────────────────────────────────────────── */
$rows = [];
$dbErro = '';
try {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    /* Garante que a tabela existe */
    $pdo->exec("CREATE TABLE IF NOT EXISTS `infodeqb_encomenda_gases` (
        `id`           int(11)                      NOT NULL AUTO_INCREMENT,
        `utilizador`   varchar(50)                  NOT NULL,
        `nome`         varchar(200)                 DEFAULT NULL,
        `tipo`         enum('garrafas','azoto')     NOT NULL DEFAULT 'garrafas',
        `conta`        varchar(50)                  DEFAULT NULL,
        `local_entrega` varchar(200)               DEFAULT NULL,
        `contacto`     varchar(200)                 DEFAULT NULL,
        `cc`           varchar(300)                 DEFAULT NULL,
        `az_volume`    int(11)                      DEFAULT NULL,
        `az_lab`       varchar(50)                  DEFAULT NULL,
        `cheias_json`  text                         DEFAULT NULL,
        `vazias_json`  text                         DEFAULT NULL,
        `assunto`      varchar(300)                 DEFAULT NULL,
        `corpo`        text                         DEFAULT NULL,
        `enviado_em`   datetime                     DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_utilizador` (`utilizador`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $mostrarTudo = isset($_GET['all']);
    $whereDate   = $mostrarTudo ? '' : "AND enviado_em >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $rows = $pdo->query(
        "SELECT id, utilizador, nome, tipo, conta, local_entrega, contacto, assunto,
                cheias_json, vazias_json, az_volume, az_lab, enviado_em
         FROM infodeqb_encomenda_gases
         WHERE 1=1 $whereDate
         ORDER BY enviado_em DESC LIMIT 500"
    )->fetchAll(PDO::FETCH_ASSOC);
    Database::disconnect();
} catch (Exception $e) {
    $dbErro = $e->getMessage();
}

/* ── Agrupar por utilizador (mais recente primeiro) ─────────────────── */
$byUser      = [];
$userLastDate = [];
foreach ($rows as $r) {
    $uid = $r['utilizador'];
    if (!isset($byUser[$uid])) {
        $byUser[$uid]      = ['nome' => $r['nome'] ?? $uid, 'rows' => []];
        $userLastDate[$uid] = $r['enviado_em'] ?? '';
    }
    $byUser[$uid]['rows'][] = $r;
    if (($r['enviado_em'] ?? '') > $userLastDate[$uid]) $userLastDate[$uid] = $r['enviado_em'];
}
// Ordenar grupos por data mais recente DESC
uksort($byUser, function($a, $b) use ($userLastDate) {
    return strcmp($userLastDate[$b] ?? '', $userLastDate[$a] ?? '');
});

$pageTitle = t('GENC_HIST_TITLE') . ' — ' . t('NAV_GASES');
$mainClass = 'iq-main';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>
<style>
.hist-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.hist-table th {
  background: var(--iq-gray-100, #f3f4f6);
  color: var(--iq-muted);
  font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em;
  padding:.55rem .75rem; border-bottom:2px solid var(--iq-border); white-space:nowrap;
}
.hist-table td { padding:.5rem .75rem; border-bottom:1px solid var(--iq-border); vertical-align:middle; }
.hist-table tr.data-row:hover td { background: var(--iq-gray-50, #f9fafb); }
.hist-tipo-badge {
  display:inline-block; font-size:.62rem; font-weight:700; text-transform:uppercase;
  letter-spacing:.06em; border-radius:3px; padding:1px 5px;
}
.hist-tipo-garrafas { background:#dbeafe; color:#1d4ed8; }
.hist-tipo-azoto    { background:#e0f2fe; color:#0369a1; }
.chk-all, .row-chk { cursor:pointer; }
.group-header td {
  background: var(--iq-gray-50, #f9fafb);
  border-top: 2px solid var(--iq-border);
  border-bottom: 1px solid var(--iq-border);
  padding: .4rem .75rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--iq-text);
  cursor: pointer;
  user-select: none;
}
.group-header td:hover { background: var(--iq-gray-100, #f3f4f6); }
.group-toggle {
  display:inline-block; color:var(--iq-muted); margin-right:.4rem;
  transition: transform .18s;
}
tbody.user-group.collapsed .group-toggle { transform: rotate(-90deg); }
tbody.user-group.collapsed tr.data-row { display: none; }
.group-count {
  display:inline-block; font-size:.65rem; font-weight:700;
  background: var(--iq-blue-light,#dbeafe); color: var(--iq-blue,#2563eb);
  border-radius: 10px; padding: 1px 7px; margin-left:.4rem;
}
.filter-bar { display:flex; flex-wrap:wrap; gap:.55rem; align-items:flex-end; margin-bottom:.75rem; }
.filter-bar label { font-size:.72rem; font-weight:600; color:var(--iq-muted); text-transform:uppercase; letter-spacing:.05em; display:block; margin-bottom:.2rem; }
.filter-bar .form-control, .filter-bar .form-select { font-size:.82rem; padding:.28rem .55rem; }
.no-results { font-size:.85rem; color:var(--iq-muted); padding:.75rem; text-align:center; display:none; }
.periodo-aviso { font-size:.8rem; color:var(--iq-muted); margin-bottom:.65rem; }
</style>

<div class="container-fluid px-3 px-md-4">
  <div class="iq-page-header">
    <div>
      <h1 class="iq-page-title">
        <i class="fas fa-history me-2" style="color:var(--iq-blue)"></i><?= t('GENC_HIST_TITLE') ?>
      </h1>
      <p class="iq-page-sub"><?= t('GENC_HIST_SUB') ?></p>
    </div>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-outline-danger" id="btn-apagar-sel" style="display:none" onclick="apagarSelecionados()">
        <i class="fas fa-trash me-1"></i><?= t('GENC_APAGAR_SEL') ?>
      </button>
      <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i><?= t('GENC_VOLTAR') ?>
      </a>
    </div>
  </div>

  <?php if ($dbErro): ?>
    <div class="alert alert-danger"><strong>Erro BD:</strong> <?= htmlspecialchars($dbErro) ?></div>
  <?php elseif (empty($rows)): ?>
    <div class="alert alert-info"><?= t('GENC_SEM_REG') ?></div>
  <?php else: ?>

  <!-- Aviso de período -->
  <?php
    $nEnc = count($rows);
    $encLabel = $nEnc === 1 ? t('GENC_ENC_SINGULAR') : t('GENC_ENC_PLURAL');
  ?>
  <?php if (!$mostrarTudo): ?>
  <div class="periodo-aviso">
    <i class="fas fa-clock fa-xs me-1"></i><?= t('GENC_90DIAS_AVD') ?>
    (<?= $nEnc ?> <?= $encLabel ?>).
    <a href="?all=1" class="ms-1"><?= t('GENC_MOSTRAR_TUDO') ?></a>
  </div>
  <?php else: ?>
  <div class="periodo-aviso">
    <i class="fas fa-list fa-xs me-1"></i><?= t('GENC_TODAS_ENC') ?>
    (<?= $nEnc ?>).
    <a href="?" class="ms-1"><?= t('GENC_90DIAS_LINK') ?></a>
  </div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="filter-bar mb-3">
    <div>
      <label><?= t('GENC_F_USER') ?></label>
      <select class="form-select" id="f-user" onchange="applyFilters()">
        <option value=""><?= t('GENC_F_TODOS') ?></option>
        <?php foreach (array_keys($byUser) as $uid): ?>
        <option value="<?= htmlspecialchars($uid) ?>">
          <?= htmlspecialchars($byUser[$uid]['nome'] ?? $uid) ?>
          (<?= htmlspecialchars($uid) ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label><?= t('GENC_F_DATA_DE') ?></label>
      <input type="date" class="form-control" id="f-date-from" onchange="applyFilters()">
    </div>
    <div>
      <label><?= t('GENC_F_DATA_ATE') ?></label>
      <input type="date" class="form-control" id="f-date-to" onchange="applyFilters()">
    </div>
    <div>
      <label><?= t('GENC_F_GAS') ?></label>
      <input type="text" class="form-control" id="f-gas" placeholder="<?= t('GENC_F_GAS_PH') ?>" oninput="applyFilters()" style="min-width:140px">
    </div>
    <div style="align-self:flex-end">
      <button class="btn btn-sm btn-outline-secondary" onclick="limparFiltros()">
        <i class="fas fa-times fa-xs me-1"></i><?= t('GENC_LIMPAR') ?>
      </button>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="hist-table">
        <thead>
          <tr>
            <th><input type="checkbox" class="chk-all" onchange="toggleAll(this)"></th>
            <th><?= t('GENC_COL_DATA') ?></th>
            <th><?= t('GENC_COL_TIPO') ?></th>
            <th><?= t('GENC_COL_ASSUNTO_GAS') ?></th>
            <th></th>
          </tr>
        </thead>
        <?php foreach ($byUser as $uid => $ug): ?>
        <?php $userNome = $ug['nome'] ?? $uid; ?>
        <tbody class="user-group" data-user="<?= htmlspecialchars($uid) ?>">
          <tr class="group-header" onclick="toggleGroup(this)">
            <td colspan="5">
              <span class="group-toggle"><i class="fas fa-chevron-down fa-xs"></i></span>
              <i class="fas fa-user fa-xs me-1" style="color:var(--iq-muted)"></i>
              <?= htmlspecialchars($userNome) ?>
              <span style="font-weight:400;color:var(--iq-muted);font-size:.72rem"> — <?= htmlspecialchars($uid) ?></span>
              <span class="group-count" id="gc-<?= htmlspecialchars($uid) ?>"><?= count($ug['rows']) ?></span>
            </td>
          </tr>
          <?php foreach ($ug['rows'] as $h): ?>
          <?php
            $gasArr = [];
            $cheias = json_decode($h['cheias_json'] ?? '[]', true) ?: [];
            $vazias = json_decode($h['vazias_json'] ?? '[]', true) ?: [];
            foreach (array_merge($cheias, $vazias) as $g) {
                if (!empty($g['gas'])) $gasArr[] = $g['gas'];
            }
            $gasAttr = htmlspecialchars(mb_strtolower(implode('|', $gasArr)));
            $dateAttr = substr($h['enviado_em'] ?? '', 0, 10);
          ?>
          <tr class="data-row"
            data-id="<?= (int)$h['id'] ?>"
            data-user="<?= htmlspecialchars($uid) ?>"
            data-date="<?= $dateAttr ?>"
            data-gases="<?= $gasAttr ?>">
            <td><input type="checkbox" class="row-chk" value="<?= (int)$h['id'] ?>" onchange="onChkChange()"></td>
            <td style="white-space:nowrap;font-size:.8rem"><?= htmlspecialchars(substr($h['enviado_em'] ?? '', 0, 16)) ?></td>
            <td><span class="hist-tipo-badge hist-tipo-<?= $h['tipo'] ?>"><?= $h['tipo'] === 'azoto' ? t('GENC_BADGE_AZOTO') : t('GENC_BADGE_GARR') ?></span></td>
            <td>
              <div style="font-size:.8rem"><?= htmlspecialchars($h['assunto'] ?? '—') ?></div>
              <?php if (!empty($gasArr)): ?>
              <div style="font-size:.72rem;color:var(--iq-muted);margin-top:2px"><?= htmlspecialchars(implode(' · ', array_unique($gasArr))) ?></div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <button class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:.18rem .45rem"
                onclick="showDetalhe(<?= (int)$h['id'] ?>)" title="Ver detalhe">
                <i class="fas fa-eye"></i>
              </button>
              <button class="btn btn-xs btn-outline-danger" style="font-size:.72rem;padding:.18rem .4rem"
                onclick="apagarUm(<?= (int)$h['id'] ?>)" title="Apagar">
                <i class="fas fa-times"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endforeach; ?>
      </table>
      <div class="no-results" id="no-results"><?= t('GENC_SEM_FILTRO') ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal detalhe -->
<div class="modal fade" id="modalDetalhe" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-envelope-open-text me-2"></i><?= t('GENC_DETALHE') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-2" style="font-size:.78rem" id="detalhe-meta"></p>
        <div id="detalhe-corpo"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('GENC_FECHAR') ?></button>
      </div>
    </div>
  </div>
</div>

<style>
.iq-toast {
  position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%) translateY(8px);
  background:var(--iq-gray-800,#1f2937); color:#fff; padding:.45rem 1rem;
  border-radius:20px; font-size:.8rem; opacity:0; transition:opacity .22s,transform .22s;
  pointer-events:none; white-space:nowrap; z-index:2000;
}
.iq-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
</style>
<div class="iq-toast" id="toast"></div>

<script>
var _T = <?= json_encode([
  'conta_th'    => t('GENC_CONTA'),
  'local_th'    => t('GENC_LOCAL'),
  'contacto_th' => t('GENC_CONTACTO'),
  'badge_azoto' => t('GENC_BADGE_AZOTO'),
  'col_gas'     => t('GENC_COL_GAS'),
  'cheias_th'   => t('GENC_CHEIAS_PEDIDAS'),
  'vazias_th'   => t('GENC_VAZIAS_LEVANTAR'),
  'sem_gas'     => t('GENC_JS_SEM_GAS'),
  'apagar_um'   => t('GENC_JS_APAGAR_UM'),
  'apagar_n'    => t('GENC_JS_APAGAR_N'),
  'apagado_1'   => t('GENC_JS_APAGADO_1'),
  'apagado_n'   => t('GENC_JS_APAGADO_N'),
  'erro_rede'   => t('GENC_JS_ERRO_REDE2'),
]) ?>;

var _rows = <?= json_encode(array_column($rows, null, 'id')) ?>;

function esc(s) {
  return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderDetalheGases(h) {
  var html = '';
  html += '<table class="table table-sm mb-3" style="font-size:.82rem">';
  html += '<tr><th style="width:38%">' + esc(_T.conta_th) + '</th><td>' + esc(h.conta || '—') + '</td></tr>';
  html += '<tr><th>' + esc(_T.local_th) + '</th><td>' + esc(h.local_entrega || '—') + '</td></tr>';
  if (h.contacto) html += '<tr><th>' + esc(_T.contacto_th) + '</th><td>' + esc(h.contacto) + '</td></tr>';
  html += '</table>';
  if (h.tipo === 'azoto') {
    html += '<p style="margin:0"><strong>' + esc(_T.badge_azoto) + ':</strong> ' + esc(h.az_volume || '?') + ' L';
    if (h.az_lab) html += ' — Lab.&nbsp;' + esc(h.az_lab);
    html += '</p>';
  } else {
    var cheias = [], vazias = [], gases = {}, order = [];
    try { cheias = JSON.parse(h.cheias_json || '[]'); } catch(e) {}
    try { vazias = JSON.parse(h.vazias_json || '[]'); } catch(e) {}
    cheias.forEach(function(r) {
      if (!gases[r.gas]) { gases[r.gas] = {c: 0, v: 0}; order.push(r.gas); }
      gases[r.gas].c += (parseInt(r.qty, 10) || 0);
    });
    vazias.forEach(function(r) {
      if (!gases[r.gas]) { gases[r.gas] = {c: 0, v: 0}; order.push(r.gas); }
      gases[r.gas].v += (parseInt(r.qty, 10) || 0);
    });
    if (order.length) {
      html += '<table class="table table-sm table-bordered" style="font-size:.82rem">';
      html += '<thead class="table-light"><tr>';
      html += '<th>' + esc(_T.col_gas) + '</th>';
      html += '<th class="text-center" style="white-space:nowrap">' + esc(_T.cheias_th) + '</th>';
      html += '<th class="text-center" style="white-space:nowrap">' + esc(_T.vazias_th) + '</th>';
      html += '</tr></thead><tbody>';
      order.forEach(function(g) {
        var c = gases[g].c, v = gases[g].v;
        html += '<tr><td>' + esc(g) + '</td>';
        html += '<td class="text-center">' + (c ? c : '<span style="color:#9ca3af">—</span>') + '</td>';
        html += '<td class="text-center">' + (v ? v : '<span style="color:#9ca3af">—</span>') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
    } else {
      html += '<p class="text-muted">' + esc(_T.sem_gas) + '</p>';
    }
  }
  return html;
}

/* ── Grupos colapsáveis ──────────────────────────────────────────────── */
function toggleGroup(headerRow) {
  var tbody = headerRow.closest('tbody');
  if (tbody) tbody.classList.toggle('collapsed');
}

function initCollapse() {
  var groups = document.querySelectorAll('tbody.user-group');
  groups.forEach(function(g, i) {
    if (i > 0) g.classList.add('collapsed');
  });
}

/* ── Filtros ─────────────────────────────────────────────────────────── */
function hasActiveFilter() {
  return !!(document.getElementById('f-user').value ||
            document.getElementById('f-date-from').value ||
            document.getElementById('f-date-to').value ||
            document.getElementById('f-gas').value.trim());
}

function applyFilters() {
  var fUser     = document.getElementById('f-user').value;
  var fDateFrom = document.getElementById('f-date-from').value;
  var fDateTo   = document.getElementById('f-date-to').value;
  var fGas      = document.getElementById('f-gas').value.trim().toLowerCase();
  var filtering = !!(fUser || fDateFrom || fDateTo || fGas);
  var totalVisible = 0;

  document.querySelectorAll('tbody.user-group').forEach(function(tbody) {
    var uid = tbody.dataset.user;
    var visible = 0;

    tbody.querySelectorAll('tr.data-row').forEach(function(tr) {
      var show = true;
      if (fUser     && tr.dataset.user !== fUser)            show = false;
      if (fDateFrom && tr.dataset.date < fDateFrom)          show = false;
      if (fDateTo   && tr.dataset.date > fDateTo)            show = false;
      if (fGas      && tr.dataset.gases.indexOf(fGas) === -1) show = false;
      tr.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    var gc = document.getElementById('gc-' + uid);
    if (gc) gc.textContent = visible;

    var header = tbody.querySelector('tr.group-header');
    var showGroup = visible > 0;
    if (header) header.style.display = showGroup ? '' : 'none';
    tbody.style.display = showGroup ? '' : 'none';

    // Expandir grupos com resultados quando há filtro activo
    if (filtering && showGroup) tbody.classList.remove('collapsed');

    totalVisible += visible;
  });

  var noRes = document.getElementById('no-results');
  if (noRes) noRes.style.display = totalVisible === 0 ? '' : 'none';
  onChkChange();
}

function limparFiltros() {
  document.getElementById('f-user').value     = '';
  document.getElementById('f-date-from').value = '';
  document.getElementById('f-date-to').value   = '';
  document.getElementById('f-gas').value        = '';

  // Repor visibilidade de todas as linhas e grupos
  document.querySelectorAll('tr.data-row').forEach(function(tr) { tr.style.display = ''; });
  document.querySelectorAll('tbody.user-group').forEach(function(g)   { g.style.display = ''; });
  document.querySelectorAll('tr.group-header').forEach(function(h)    { h.style.display = ''; });

  // Actualizar contagens
  document.querySelectorAll('tbody.user-group').forEach(function(tbody) {
    var uid   = tbody.dataset.user;
    var count = tbody.querySelectorAll('tr.data-row').length;
    var gc    = document.getElementById('gc-' + uid);
    if (gc) gc.textContent = count;
  });

  var noRes = document.getElementById('no-results');
  if (noRes) noRes.style.display = 'none';

  // Restaurar colapso inicial
  initCollapse();
  onChkChange();
}

function visibleRows() {
  return Array.from(document.querySelectorAll('tr.data-row')).filter(function(tr) {
    return tr.style.display !== 'none' && !tr.closest('tbody').classList.contains('collapsed');
  });
}

function toggleAll(chk) {
  visibleRows().forEach(function(tr) {
    var cb = tr.querySelector('.row-chk');
    if (cb) cb.checked = chk.checked;
  });
  onChkChange();
}

function onChkChange() {
  var checked = document.querySelectorAll('.row-chk:checked').length;
  var visible = visibleRows().length;
  document.getElementById('btn-apagar-sel').style.display = checked > 0 ? '' : 'none';
  var chkAll = document.querySelector('.chk-all');
  chkAll.indeterminate = checked > 0 && checked < visible;
  chkAll.checked = visible > 0 && checked === visible;
}

document.addEventListener('DOMContentLoaded', initCollapse);

function showDetalhe(id) {
  var h = _rows[id];
  if (!h) return;
  var meta = esc(h.nome || h.utilizador || '') + ' · ' + (h.enviado_em || '').substr(0, 16);
  document.getElementById('detalhe-meta').textContent = meta;
  document.getElementById('detalhe-corpo').innerHTML = renderDetalheGases(h);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhe')).show();
}

function apagarUm(id) {
  if (!confirm(_T.apagar_um)) return;
  _apagar([id]);
}

function apagarSelecionados() {
  var ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(function(c) { return parseInt(c.value); });
  if (!ids.length) return;
  if (!confirm(_T.apagar_n.replace('%d', ids.length))) return;
  _apagar(ids);
}

function _apagar(ids) {
  var fd = new FormData();
  fd.append('action', 'apagar_hist');
  fd.append('ids', ids.join(','));
  fetch(window.location.pathname, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok) { showToast(res.erro || 'Erro'); return; }
      ids.forEach(function(id) {
        var tr = document.querySelector('tr.data-row[data-id="' + id + '"]');
        if (tr) tr.remove();
        delete _rows[id];
      });
      applyFilters();
      showToast(ids.length > 1 ? _T.apagado_n.replace('%d', ids.length) : _T.apagado_1);
    })
    .catch(function() { showToast(_T.erro_rede); });
}

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 2800);
}
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
