<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/supabase.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? [];
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);
if (!$isGasAdmin) { header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit; }

$gasId  = preg_replace('/[^a-z0-9]/', '', $_GET['gas_id'] ?? ''); // modo single-gas
$gases  = GASES_DEF;
$gasAtual = isset($gases[$gasId]) ? $gases[$gasId] : null;

// Última leitura de cada gás via Supabase (uma chamada com todos os ids)
$ultimasMap = [];
foreach ($gases as $gid => $g) {
    $res = supabase_request('GET', 'gas_logs', [
        'gas_id=eq.' . $gid,
        'order=timestamp.desc',
        'limit=1',
        'select=pressure,timestamp',
    ]);
    if (!empty($res['data'])) {
        $ultimasMap[$gid] = $res['data'][0];
    }
}

$pageTitle = $gasAtual ? 'Registar ' . $gasAtual['name'] : t('GASES_RONDA_TITLE');
$mainClass  = 'iq-hr-page';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title">
      <a href="index.php" class="text-muted me-2" style="font-size:.8em;"><i class="fas fa-arrow-left"></i></a>
      <?php echo $gasAtual ? 'Registar ' . htmlspecialchars($gasAtual['name']) : 'Nova Ronda'; ?>
    </h1>
    <p class="iq-page-sub"><?php echo $gasAtual ? 'Leitura individual' : 'Registo de todos os gases de uma vez'; ?></p>
  </div>
</div>

<div id="flash-msg" class="alert d-none mb-3" role="alert" style="display:block!important;"></div>

<form id="form-ronda">

  <div class="card shadow-sm mb-3" style="border-radius:var(--iq-r2);">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data e hora</label>
          <input type="datetime-local" class="form-control" id="data_hora" name="data_hora"
                 value="<?php echo date('Y-m-d\TH:i'); ?>">
        </div>
        <div class="col-md-8 text-muted" style="font-size:.85rem;">
          <i class="fas fa-user me-1"></i> A registar como:
          <strong><?php echo htmlspecialchars($_SESSION['DisplayName'] ?? $_SESSION['CommonName'] ?? $_iqCurrentUser); ?></strong>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm" style="border-radius:var(--iq-r2);">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th style="width:2.5rem;"></th>
              <th>Gás</th>
              <th style="width:9rem;">Última leitura</th>
              <th style="width:11rem;">Nova pressão</th>
              <th style="width:8rem;text-align:center;">Nova garrafa</th>
              <th>Notas</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($gases as $gid => $g):
              $show = (!$gasId || $gid === $gasId);
              $ultima = $ultimasMap[$gid] ?? null;
            ?>
            <tr class="gas-row<?php echo $show ? '' : ' d-none'; ?>" data-gas-id="<?php echo $gid; ?>">
              <td>
                <span class="badge bg-<?php echo $g['cor_bs']; ?>" style="font-family:monospace;font-size:.75rem;"<?php echo gas_badge_style($g['cor_bs']); ?>>
                  <?php echo htmlspecialchars($g['symbol']); ?>
                </span>
              </td>
              <td>
                <div class="fw-medium"><?php echo htmlspecialchars($g['name']); ?></div>
                <div class="text-muted" style="font-size:.75rem;">Máx. <?php echo $g['max_pressure']; ?> <?php echo $g['unit']; ?></div>
              </td>
              <td class="text-muted" style="font-size:.85rem;">
                <?php if ($ultima): ?>
                  <?php echo number_format((float)$ultima['pressure'], 1); ?> <?php echo $g['unit']; ?><br>
                  <span style="font-size:.75rem;"><?php echo date('d/m H:i', strtotime($ultima['timestamp'])); ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.1" min="0"
                         class="form-control pressao-input"
                         placeholder="—"
                         data-gas-id="<?php echo $gid; ?>"
                         data-max="<?php echo $g['max_pressure']; ?>">
                  <span class="input-group-text text-muted" style="font-size:.8rem;"><?php echo $g['unit']; ?></span>
                </div>
              </td>
              <td style="text-align:center;">
                <div class="form-check d-inline-block">
                  <input class="form-check-input" type="checkbox"
                         id="ng_<?php echo $gid; ?>" value="1">
                  <label class="form-check-label" for="ng_<?php echo $gid; ?>">
                    <span class="badge bg-success-subtle text-success" style="font-size:.75rem;">
                      <i class="fas fa-exchange-alt fa-xs"></i> Trocada
                    </span>
                  </label>
                </div>
              </td>
              <td>
                <input type="text" class="form-control form-control-sm notas-input" placeholder="Observações…">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
      <?php if (!$gasId): ?>
      <span class="text-muted" style="font-size:.85rem;">
        <span id="filled-count">0</span> de <?php echo count($gases); ?> gases preenchidos
      </span>
      <?php else: ?>
      <a href="ronda.php" class="btn btn-link text-decoration-none text-muted p-0">
        <i class="fas fa-list me-1"></i> Ver ronda completa
      </a>
      <?php endif; ?>
      <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="button" class="btn btn-primary" id="btn-guardar">
          <i class="fas fa-save me-1"></i>
          <?php echo $gasId ? 'Guardar leitura' : 'Finalizar ronda'; ?>
        </button>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var BASE = '<?php echo HTTP_DIR; ?>/infodeqb/gases';

  function updateCount() {
    var filled = 0;
    document.querySelectorAll('.pressao-input').forEach(function(inp) {
      if (inp.value.trim() !== '') filled++;
    });
    var el = document.getElementById('filled-count');
    if (el) el.textContent = filled;
  }

  document.querySelectorAll('.pressao-input').forEach(function(inp) {
    inp.addEventListener('input', function() {
      updateCount();
      var max = parseFloat(inp.dataset.max);
      var val = parseFloat(inp.value);
      inp.classList.toggle('is-invalid', !isNaN(val) && val > max);
    });
  });

  document.getElementById('btn-guardar').addEventListener('click', function() {
    var btn = this;
    var dataHora = document.getElementById('data_hora').value;
    var leituras = [];

    document.querySelectorAll('.gas-row').forEach(function(row) {
      var inp = row.querySelector('.pressao-input');
      if (!inp || inp.value.trim() === '') return;
      var gasId   = row.dataset.gasId;
      var ngEl    = row.querySelector('input[type=checkbox]');
      var notasEl = row.querySelector('.notas-input');
      leituras.push({
        gas_id:       gasId,
        pressao:      inp.value,
        nova_garrafa: ngEl && ngEl.checked ? '1' : '0',
        notas:        notasEl ? notasEl.value : '',
      });
    });

    if (!leituras.length) { showFlash('Preencha pelo menos uma leitura.', 'warning'); return; }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar…';

    var fd = new FormData();
    fd.append('acao', 'guardar_ronda');
    fd.append('data_hora', dataHora);
    leituras.forEach(function(l, i) {
      fd.append('leituras[' + i + '][gas_id]',       l.gas_id);
      fd.append('leituras[' + i + '][pressao]',      l.pressao);
      fd.append('leituras[' + i + '][nova_garrafa]', l.nova_garrafa);
      fd.append('leituras[' + i + '][notas]',        l.notas);
    });

    fetch(BASE + '/ajax.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          showFlash(data.msg, 'success');
          setTimeout(function() { window.location = 'index.php'; }, 1000);
        } else {
          showFlash(data.msg || 'Erro ao guardar.', 'danger');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-save me-1"></i> <?php echo $gasId ? "Guardar leitura" : "Finalizar ronda"; ?>';
        }
      })
      .catch(function() {
        showFlash('Erro de comunicação com o servidor.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> <?php echo $gasId ? "Guardar leitura" : "Finalizar ronda"; ?>';
      });
  });

  function showFlash(msg, type) {
    var el = document.getElementById('flash-msg');
    el.className = 'alert alert-' + type + ' mb-3';
    el.style.display = 'block';
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
})();
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
