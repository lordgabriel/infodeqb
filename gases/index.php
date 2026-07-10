<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/supabase.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? [];
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);
if (!$isGasAdmin) { header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit; }

$gases = GASES_DEF;
$pageTitle = 'Gases Especiais';

include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title"><i class="fas fa-wind me-2 text-primary"></i>Gases Especiais</h1>
    <p class="iq-page-sub">Monitorização de pressão de garrafas de gás</p>
  </div>
  <div class="d-flex gap-2 ms-auto">
    <a href="ronda.php" class="btn btn-primary">
      <i class="fas fa-clipboard-list me-1"></i> Nova Ronda
    </a>
    <a href="relatorio.php" class="btn btn-outline-secondary">
      <i class="fas fa-chart-line me-1"></i> Relatórios
    </a>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="iq-stat">
      <div class="iq-stat-value"><?php echo count($gases); ?></div>
      <div class="iq-stat-label">Gases monitorizados</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="iq-stat" id="stat-alerta">
      <div class="iq-stat-value">—</div>
      <div class="iq-stat-label">Em nível crítico</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="iq-stat iq-stat-blue" id="stat-semana">
      <div class="iq-stat-value">—</div>
      <div class="iq-stat-label">Leituras (7 dias)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="iq-stat">
      <div class="iq-stat-value"><?php echo date('d/m'); ?></div>
      <div class="iq-stat-label">Hoje</div>
    </div>
  </div>
</div>

<!-- Gas cards grid -->
<div class="row g-3" id="gas-grid">
<?php foreach ($gases as $gasId => $g): ?>
  <div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-gas-card="<?php echo $gasId; ?>">
    <div class="card h-100 shadow-sm" style="border-radius:var(--iq-r2);" id="card-<?php echo $gasId; ?>">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="badge bg-<?php echo $g['cor_bs']; ?> fs-6 px-2 py-1" style="min-width:3rem;text-align:center;font-family:monospace;"<?php echo gas_badge_style($g['cor_bs']); ?>>
            <?php echo htmlspecialchars($g['symbol']); ?>
          </span>
          <div>
            <div class="fw-semibold" style="font-size:.95rem;"><?php echo htmlspecialchars($g['name']); ?></div>
            <div class="text-muted" style="font-size:.75rem;">Máx. <?php echo $g['max_pressure']; ?> <?php echo $g['unit']; ?></div>
          </div>
          <span class="ms-auto badge bg-danger d-none" id="badge-critico-<?php echo $gasId; ?>">
            <i class="fas fa-exclamation-triangle"></i> Crítico
          </span>
        </div>

        <!-- Dados carregados via JS -->
        <div id="data-<?php echo $gasId; ?>">
          <div class="text-muted py-2" style="font-size:.85rem;">
            <span class="spinner-border spinner-border-sm me-1"></span> A carregar…
          </div>
        </div>
      </div>
      <div class="card-footer bg-transparent border-0 pt-0 d-flex gap-2 justify-content-end">
        <a href="historico.php?gas_id=<?php echo $gasId; ?>" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-history fa-xs"></i> Histórico
        </a>
        <a href="ronda.php?gas_id=<?php echo $gasId; ?>" class="btn btn-sm btn-outline-primary">
          <i class="fas fa-plus fa-xs"></i> Registar
        </a>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<script>
(function () {
  var SB_URL = '<?php echo SUPABASE_URL; ?>';
  var SB_KEY = '<?php echo SUPABASE_KEY; ?>';

  var GASES = <?php echo json_encode($gases); ?>;

  var alertCount   = 0;
  var semanaCount  = 0;
  var loaded       = 0;
  var total        = Object.keys(GASES).length;

  function sbGet(path) {
    return fetch(SB_URL + '/rest/v1/' + path, {
      headers: { 'apikey': SB_KEY, 'Authorization': 'Bearer ' + SB_KEY }
    }).then(function(r) { return r.json(); });
  }

  function pct(pressure, max) { return Math.min(100, Math.round(pressure / max * 100)); }
  function barColor(p) { return p < 10 ? 'bg-danger' : (p < 30 ? 'bg-warning' : 'bg-success'); }

  // Carregar última leitura de cada gás em paralelo
  Object.keys(GASES).forEach(function(gasId) {
    var g = GASES[gasId];
    sbGet('gas_logs?gas_id=eq.' + gasId + '&order=timestamp.desc&limit=1')
      .then(function(rows) {
        loaded++;
        var el = document.getElementById('data-' + gasId);
        var card = document.getElementById('card-' + gasId);

        if (!rows || !rows.length) {
          el.innerHTML = '<div class="text-muted py-2" style="font-size:.85rem;"><i class="fas fa-info-circle me-1"></i>Sem leituras registadas</div>';
        } else {
          var r   = rows[0];
          var p   = pct(r.pressure, g.max_pressure);
          var bc  = barColor(p);
          var d   = new Date(r.timestamp);
          var fmt = d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT', {hour:'2-digit',minute:'2-digit'});

          if (p < 10) {
            alertCount++;
            card.classList.add('border-danger');
            document.getElementById('badge-critico-' + gasId).classList.remove('d-none');
          }

          el.innerHTML =
            '<div class="d-flex align-items-baseline gap-1 mb-2">' +
              '<span class="fw-bold" style="font-size:2rem;line-height:1;">' + parseFloat(r.pressure).toFixed(1) + '</span>' +
              '<span class="text-muted">' + g.unit + '</span>' +
              '<span class="ms-auto text-muted" style="font-size:.78rem;">' + p + '%</span>' +
            '</div>' +
            '<div class="progress mb-2" style="height:8px;border-radius:4px;">' +
              '<div class="progress-bar ' + bc + '" style="width:' + p + '%"></div>' +
            '</div>' +
            '<div class="text-muted" style="font-size:.75rem;">' + fmt + ' · ' + escHtml(r.user_name) +
              (r.is_new_bottle ? ' · <span class="badge bg-success-subtle text-success"><i class="fas fa-exchange-alt fa-xs"></i> Nova garrafa</span>' : '') +
            '</div>';
        }

        // Quando todos carregados, actualizar stats
        if (loaded === total) updateStats();
      });
  });

  // Leituras dos últimos 7 dias para badge
  var d7 = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
  sbGet('gas_logs?timestamp=gte.' + d7 + 'T00:00:00&select=id').then(function(rows) {
    semanaCount = rows ? rows.length : 0;
    document.getElementById('stat-semana').querySelector('.iq-stat-value').textContent = semanaCount;
  });

  function updateStats() {
    var statAlerta = document.getElementById('stat-alerta');
    statAlerta.querySelector('.iq-stat-value').textContent = alertCount;
    if (alertCount > 0) statAlerta.classList.add('iq-stat-red');
    else                statAlerta.classList.add('iq-stat-green');
  }

  function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
})();
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
