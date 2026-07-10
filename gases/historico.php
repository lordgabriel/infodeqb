<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/supabase.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? [];
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);
if (!$isGasAdmin) { header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit; }

$gases    = GASES_DEF;
$gasId    = preg_replace('/[^a-z0-9]/', '', $_GET['gas_id'] ?? array_key_first($gases));
$gasAtual = $gases[$gasId] ?? reset($gases);
if (!isset($gases[$gasId])) { $gasId = array_key_first($gases); }

$pageTitle = 'Histórico — Gases Especiais';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title">
      <a href="index.php" class="text-muted me-2" style="font-size:.8em;"><i class="fas fa-arrow-left"></i></a>
      Histórico de Leituras
    </h1>
  </div>
  <a href="ronda.php" class="btn btn-primary btn-sm ms-auto">
    <i class="fas fa-plus me-1"></i> Nova Ronda
  </a>
</div>

<!-- Selector de gás -->
<div class="d-flex flex-wrap gap-2 mb-4">
  <?php foreach ($gases as $gid => $g): ?>
  <a href="historico.php?gas_id=<?php echo $gid; ?>"
     class="btn btn-sm <?php echo $gid === $gasId ? 'btn-' . $g['cor_bs'] : 'btn-outline-secondary'; ?>"
     <?php if ($gid === $gasId) echo gas_btn_style($g['cor_bs']); ?>>
    <span style="font-family:monospace;"><?php echo htmlspecialchars($g['symbol']); ?></span>
    <span class="ms-1"><?php echo htmlspecialchars($g['name']); ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Gráfico -->
<div class="card shadow-sm mb-4" style="border-radius:var(--iq-r2);">
  <div class="card-header card-header-transparent d-flex align-items-center justify-content-between">
    <div>
      <span class="badge bg-<?php echo $gasAtual['cor_bs']; ?> me-2" style="font-family:monospace;"<?php echo gas_badge_style($gasAtual['cor_bs']); ?>><?php echo htmlspecialchars($gasAtual['symbol']); ?></span>
      <strong><?php echo htmlspecialchars($gasAtual['name']); ?></strong>
      <span class="text-muted ms-2" style="font-size:.85rem;">Máx. <?php echo $gasAtual['max_pressure']; ?> <?php echo $gasAtual['unit']; ?></span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <label class="form-label mb-0 me-1 text-muted" style="font-size:.83rem;">Últimas</label>
      <select id="limite-select" class="form-select form-select-sm" style="width:auto;">
        <option value="20">20</option>
        <option value="50" selected>50</option>
        <option value="100">100</option>
        <option value="200">200</option>
      </select>
    </div>
  </div>
  <div class="card-body">
    <div style="position:relative;height:260px;">
      <canvas id="gas-chart"></canvas>
    </div>
  </div>
</div>

<!-- Tabela -->
<div class="card shadow-sm" style="border-radius:var(--iq-r2);">
  <div class="card-header card-header-transparent">
    <strong>Registos</strong>
    <span id="log-count" class="badge bg-secondary ms-2">—</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Data/Hora</th>
            <th>Pressão</th>
            <th style="width:6rem;text-align:center;">Garrafa</th>
            <th>Notas</th>
            <th>Utilizador</th>
            <?php if ($isGasAdmin): ?><th style="width:5rem;"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody id="log-tbody">
          <tr><td colspan="<?php echo $isGasAdmin ? 6 : 5; ?>" class="text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span>A carregar…
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($isGasAdmin): ?>
<div class="modal fade" id="modal-editar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar leitura</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-id">
        <div class="mb-3">
          <label class="form-label fw-semibold">Data/Hora</label>
          <input type="datetime-local" class="form-control" id="edit-data">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Pressão (<?php echo htmlspecialchars($gasAtual['unit']); ?>)</label>
          <input type="number" step="0.1" min="0" class="form-control" id="edit-pressao">
        </div>
        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="edit-nova-garrafa">
          <label class="form-check-label" for="edit-nova-garrafa">Nova garrafa</label>
        </div>
        <div>
          <label class="form-label fw-semibold">Notas</label>
          <input type="text" class="form-control" id="edit-notas">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-guardar-edit">Guardar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="flash-msg" class="alert d-none mt-3" role="alert" style="display:block!important;"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function () {
  var GAS_ID   = '<?php echo $gasId; ?>';
  var GAS_MAX  = <?php echo (float)$gasAtual['max_pressure']; ?>;
  var GAS_UNIT = '<?php echo addslashes($gasAtual['unit']); ?>';
  var IS_ADMIN = <?php echo $isGasAdmin ? 'true' : 'false'; ?>;
  var BASE     = '<?php echo HTTP_DIR; ?>/infodeqb/gases';
  var chart    = null;

  function loadData(limite) {
    fetch(BASE + '/ajax.php?acao=historico&gas_id=' + GAS_ID + '&limite=' + limite)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok) return;
        document.getElementById('log-count').textContent = data.rows.length;
        renderChart(data.rows);
        renderTable(data.rows);
      });
  }

  function renderChart(rows) {
    var rev    = rows.slice().reverse();
    var labels = rev.map(function(r) {
      var d = new Date(r.timestamp);
      return d.toLocaleDateString('pt-PT',{day:'2-digit',month:'2-digit'}) + ' ' +
             d.toLocaleTimeString('pt-PT',{hour:'2-digit',minute:'2-digit'});
    });
    var data   = rev.map(function(r) { return parseFloat(r.pressure); });
    var colors = data.map(function(p) {
      var pc = p / GAS_MAX;
      return pc < 0.10 ? 'rgba(220,53,69,.8)' : (pc < 0.30 ? 'rgba(255,193,7,.8)' : 'rgba(13,110,253,.8)');
    });
    if (chart) chart.destroy();
    chart = new Chart(document.getElementById('gas-chart').getContext('2d'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Pressão (' + GAS_UNIT + ')',
          data: data,
          borderColor: 'rgba(13,110,253,.8)',
          backgroundColor: 'rgba(13,110,253,.06)',
          pointBackgroundColor: colors,
          pointRadius: 4,
          tension: 0.3,
          fill: true,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function(c) { return c.parsed.y + ' ' + GAS_UNIT + ' (' + Math.round(c.parsed.y/GAS_MAX*100) + '%)'; } } },
        },
        scales: {
          y: { min:0, max:GAS_MAX, ticks:{ callback:function(v){ return v+' '+GAS_UNIT; } }, grid:{color:'rgba(0,0,0,.05)'} },
          x: { grid:{display:false}, ticks:{maxRotation:45,font:{size:11}} },
        },
      },
    });
  }

  function renderTable(rows) {
    var cols = IS_ADMIN ? 6 : 5;
    if (!rows.length) {
      document.getElementById('log-tbody').innerHTML =
        '<tr><td colspan="' + cols + '" class="text-center py-4 text-muted">Sem leituras registadas.</td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      var d   = new Date(r.timestamp);
      var p   = Math.round(parseFloat(r.pressure) / GAS_MAX * 100);
      var bc  = p < 10 ? 'danger' : (p < 30 ? 'warning' : 'success');
      html += '<tr>';
      html += '<td style="font-size:.85rem;">' + d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT',{hour:'2-digit',minute:'2-digit',second:'2-digit'}) + '</td>';
      html += '<td><strong>' + parseFloat(r.pressure).toFixed(1) + '</strong> ' + GAS_UNIT + ' <span class="badge bg-' + bc + '-subtle text-' + bc + ' ms-1">' + p + '%</span></td>';
      html += '<td style="text-align:center;">' + (r.is_new_bottle ? '<span class="badge bg-success"><i class="fas fa-exchange-alt fa-xs"></i> Trocada</span>' : '—') + '</td>';
      html += '<td class="text-muted" style="font-size:.85rem;">' + esc(r.notes || '') + '</td>';
      html += '<td class="text-muted" style="font-size:.82rem;">' + esc(r.user_name) + '</td>';
      if (IS_ADMIN) {
        html += '<td class="text-end">';
        html += '<button class="btn btn-xs btn-outline-secondary me-1" onclick=\'openEdit(' + JSON.stringify(r) + ')\'><i class="fas fa-pencil-alt fa-xs"></i></button>';
        html += '<button class="btn btn-xs btn-outline-danger" onclick="confirmDelete(\'' + r.id.replace(/'/g,"\\'") + '\')"><i class="fas fa-trash fa-xs"></i></button>';
        html += '</td>';
      }
      html += '</tr>';
    });
    document.getElementById('log-tbody').innerHTML = html;
  }

  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  loadData(50);
  document.getElementById('limite-select').addEventListener('change', function() { loadData(parseInt(this.value)); });

  window.openEdit = function(r) {
    document.getElementById('edit-id').value      = r.id;
    document.getElementById('edit-data').value    = r.timestamp.substring(0,16).replace(' ','T');
    document.getElementById('edit-pressao').value = r.pressure;
    document.getElementById('edit-nova-garrafa').checked = !!r.is_new_bottle;
    document.getElementById('edit-notas').value   = r.notes || '';
    new bootstrap.Modal(document.getElementById('modal-editar')).show();
  };

  window.confirmDelete = function(id) {
    if (!confirm('Apagar esta leitura?')) return;
    var fd = new FormData();
    fd.append('acao','apagar_log'); fd.append('id', id);
    fetch(BASE + '/ajax.php', {method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(data){
        flash(data.msg, data.ok ? 'success' : 'danger');
        if (data.ok) loadData(parseInt(document.getElementById('limite-select').value));
      });
  };

  if (IS_ADMIN) {
    document.getElementById('btn-guardar-edit').addEventListener('click', function() {
      var fd = new FormData();
      fd.append('acao','editar_log');
      fd.append('id',           document.getElementById('edit-id').value);
      fd.append('timestamp',    document.getElementById('edit-data').value);
      fd.append('pressure',     document.getElementById('edit-pressao').value);
      fd.append('notes',        document.getElementById('edit-notas').value);
      if (document.getElementById('edit-nova-garrafa').checked) fd.append('is_new_bottle','1');
      fetch(BASE + '/ajax.php', {method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(data){
          bootstrap.Modal.getInstance(document.getElementById('modal-editar')).hide();
          flash(data.msg, data.ok ? 'success' : 'danger');
          if (data.ok) loadData(parseInt(document.getElementById('limite-select').value));
        });
    });
  }

  function flash(msg, type) {
    var el = document.getElementById('flash-msg');
    el.className = 'alert alert-' + type + ' mt-3';
    el.style.display = 'block';
    el.textContent = msg;
    setTimeout(function(){ el.className = 'alert d-none mt-3'; }, 4000);
  }
})();
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
