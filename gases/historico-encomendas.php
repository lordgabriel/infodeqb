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
    $rows = $pdo->query(
        "SELECT id, utilizador, nome, tipo, conta, local_entrega, assunto, corpo, enviado_em
         FROM infodeqb_encomenda_gases
         ORDER BY enviado_em DESC LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    Database::disconnect();
} catch (Exception $e) {
    $dbErro = $e->getMessage();
}

$pageTitle = 'Histórico de Encomendas — Gases';
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
.hist-table tr:last-child td { border-bottom:none; }
.hist-table tr:hover td { background: var(--iq-gray-50, #f9fafb); }
.hist-tipo-badge {
  display:inline-block; font-size:.62rem; font-weight:700; text-transform:uppercase;
  letter-spacing:.06em; border-radius:3px; padding:1px 5px;
}
.hist-tipo-garrafas { background:#dbeafe; color:#1d4ed8; }
.hist-tipo-azoto    { background:#e0f2fe; color:#0369a1; }
.chk-all, .row-chk { cursor:pointer; }
</style>

<div class="container-fluid px-3 px-md-4">
  <div class="iq-page-header">
    <div>
      <h1 class="iq-page-title">
        <i class="fas fa-history me-2" style="color:var(--iq-blue)"></i>Histórico de Encomendas
      </h1>
      <p class="iq-page-sub">Air Liquide — todas as encomendas submetidas</p>
    </div>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-sm btn-outline-danger" id="btn-apagar-sel" style="display:none" onclick="apagarSelecionados()">
        <i class="fas fa-trash me-1"></i>Apagar seleccionados
      </button>
      <a href="index.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Voltar
      </a>
    </div>
  </div>

  <?php if ($dbErro): ?>
    <div class="alert alert-danger"><strong>Erro de BD:</strong> <?= htmlspecialchars($dbErro) ?></div>
  <?php elseif (empty($rows)): ?>
    <div class="alert alert-info">Sem encomendas registadas.</div>
  <?php else: ?>
  <div class="card">
    <div class="table-responsive">
      <table class="hist-table">
        <thead>
          <tr>
            <th><input type="checkbox" class="chk-all" onchange="toggleAll(this)"></th>
            <th>Data</th>
            <th>Utilizador</th>
            <th>Tipo</th>
            <th>Assunto</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $h): ?>
          <tr data-id="<?= (int)$h['id'] ?>">
            <td><input type="checkbox" class="row-chk" value="<?= (int)$h['id'] ?>" onchange="onChkChange()"></td>
            <td style="white-space:nowrap"><?= htmlspecialchars(substr($h['enviado_em'] ?? '', 0, 16)) ?></td>
            <td>
              <div><?= htmlspecialchars($h['nome'] ?? '') ?></div>
              <div style="font-size:.75rem;color:var(--iq-muted)"><?= htmlspecialchars($h['utilizador'] ?? '') ?></div>
            </td>
            <td><span class="hist-tipo-badge hist-tipo-<?= $h['tipo'] ?>"><?= $h['tipo'] === 'azoto' ? '❄ azoto' : '🔵 garrafas' ?></span></td>
            <td><?= htmlspecialchars($h['assunto'] ?? '—') ?></td>
            <td style="white-space:nowrap">
              <button class="btn btn-xs btn-outline-secondary me-1" style="font-size:.72rem;padding:.18rem .45rem"
                onclick="showDetalhe(<?= (int)$h['id'] ?>)" title="Ver corpo">
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
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal detalhe -->
<div class="modal fade" id="modalDetalhe" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-envelope-open-text me-2"></i>Corpo da encomenda</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-2" style="font-size:.78rem" id="detalhe-meta"></p>
        <pre id="detalhe-corpo" style="font-size:.85rem;white-space:pre-wrap;word-break:break-word;background:var(--iq-gray-50,#f9fafb);border:1px solid var(--iq-border);border-radius:6px;padding:.75rem 1rem;margin:0"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
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
var _rows = <?= json_encode(array_column($rows, null, 'id')) ?>;

function toggleAll(chk) {
  document.querySelectorAll('.row-chk').forEach(function(c) { c.checked = chk.checked; });
  onChkChange();
}

function onChkChange() {
  var n = document.querySelectorAll('.row-chk:checked').length;
  document.getElementById('btn-apagar-sel').style.display = n > 0 ? '' : 'none';
  var total = document.querySelectorAll('.row-chk').length;
  document.querySelector('.chk-all').indeterminate = n > 0 && n < total;
  document.querySelector('.chk-all').checked = n > 0 && n === total;
}

function showDetalhe(id) {
  var h = _rows[id];
  if (!h) return;
  document.getElementById('detalhe-meta').textContent = (h.assunto || '') + ' · ' + (h.enviado_em || '').substr(0, 16);
  document.getElementById('detalhe-corpo').textContent = h.corpo || '(sem corpo guardado)';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhe')).show();
}

function apagarUm(id) {
  if (!confirm('Apagar esta encomenda?')) return;
  _apagar([id]);
}

function apagarSelecionados() {
  var ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(function(c) { return parseInt(c.value); });
  if (!ids.length) return;
  if (!confirm('Apagar ' + ids.length + ' encomenda(s)?')) return;
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
        var tr = document.querySelector('tr[data-id="' + id + '"]');
        if (tr) tr.remove();
        delete _rows[id];
      });
      onChkChange();
      showToast('Apagado' + (ids.length > 1 ? 's ' + ids.length + ' registos' : ' 1 registo'));
    })
    .catch(function() { showToast('Erro de rede'); });
}

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 2800);
}
</script>
