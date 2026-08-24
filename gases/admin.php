<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/supabase.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? [];
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);

if (!$isGasAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$gases = GASES_DEF;
$pageTitle = 'Admin — Gases Especiais';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title">
      <a href="index.php" class="text-muted me-2" style="font-size:.8em;"><i class="fas fa-arrow-left"></i></a>
      Administração — Gases Especiais
    </h1>
    <p class="iq-page-sub">Consultar e gerir todos os registos de leitura</p>
  </div>
</div>

<!-- Catálogo de gases (só-leitura — gerido na app móvel) -->
<div class="card shadow-sm mb-4" style="border-radius:var(--iq-r2);">
  <div class="card-header card-header-transparent d-flex align-items-center justify-content-between">
    <strong>Catálogo de gases</strong>
    <span class="badge bg-secondary">Gerido na app móvel</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead class="table-light">
          <tr><th>ID</th><th>Gás</th><th>Símbolo</th><th>Pressão máx.</th><th>Unidade</th></tr>
        </thead>
        <tbody>
          <?php foreach ($gases as $gid => $g): ?>
          <tr>
            <td class="text-muted" style="font-family:monospace;"><?php echo $gid; ?></td>
            <td class="fw-medium"><?php echo htmlspecialchars($g['name']); ?></td>
            <td><span class="badge bg-<?php echo $g['cor_bs']; ?>" style="font-family:monospace;"<?php echo gas_badge_style($g['cor_bs']); ?>><?php echo htmlspecialchars($g['symbol']); ?></span></td>
            <td><?php echo $g['max_pressure']; ?></td>
            <td class="text-muted"><?php echo htmlspecialchars($g['unit']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Últimas 200 leituras (todos os gases) -->
<div class="card shadow-sm" style="border-radius:var(--iq-r2);">
  <div class="card-header card-header-transparent d-flex align-items-center justify-content-between">
    <strong>Todas as leituras recentes</strong>
    <span id="all-count" class="badge bg-secondary">—</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0 table-sm">
        <thead class="table-light">
          <tr>
            <th>Data/Hora</th><th>Gás</th><th>Pressão</th>
            <th style="text-align:center;">Garrafa</th><th>Notas</th><th>Utilizador</th>
            <th style="width:3rem;"></th>
          </tr>
        </thead>
        <tbody id="all-tbody">
          <tr><td colspan="7" class="text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span>A carregar…
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="flash-msg" class="alert d-none mt-3" style="display:block!important;"></div>

<script>
(function(){
  var BASE  = '<?php echo HTTP_DIR; ?>/infodeqb/gases';
  var GASES = <?php echo json_encode($gases); ?>;
  // Cores não-nativas do Bootstrap (espelho de GAS_CUSTOM_COLORS em supabase.php)
  var GAS_CUSTOM_BG   = { orange: '#fd7e14' };
  var GAS_CUSTOM_TEXT = { orange: '#000' };
  function gasBadgeStyle(cor) {
    if (GAS_CUSTOM_BG[cor]) return ';background-color:' + GAS_CUSTOM_BG[cor] + ';color:' + GAS_CUSTOM_TEXT[cor];
    return '';
  }

  function load() {
    fetch(BASE + '/ajax.php?acao=chart_data&de=2000-01-01&ate=<?php echo date('Y-m-d'); ?>')
      .then(function(r){return r.json();})
      .then(function(data){
        if (!data.ok) return;
        var rows = data.rows.reverse(); // mais recente primeiro
        document.getElementById('all-count').textContent = rows.length;
        var html = '';
        rows.forEach(function(r) {
          var g = GASES[r.gas_id] || {name:r.gas_id,symbol:r.gas_id,cor_bs:'secondary',max_pressure:200,unit:'bar'};
          var d = new Date(r.timestamp);
          var p = Math.round(parseFloat(r.pressure)/g.max_pressure*100);
          var bc= p<10?'danger':(p<30?'warning':'success');
          html += '<tr>';
          html += '<td style="font-size:.82rem;">'+d.toLocaleDateString('pt-PT')+' '+d.toLocaleTimeString('pt-PT',{hour:'2-digit',minute:'2-digit'})+'</td>';
          html += '<td><span class="badge bg-'+g.cor_bs+'" style="font-family:monospace'+gasBadgeStyle(g.cor_bs)+'">'+esc(g.symbol)+'</span></td>';
          html += '<td><strong>'+parseFloat(r.pressure).toFixed(1)+'</strong> '+g.unit+' <span class="badge bg-'+bc+'-subtle text-'+bc+'">'+p+'%</span></td>';
          html += '<td style="text-align:center;">'+(r.is_new_bottle?'<span class="badge bg-success">✓</span>':'—')+'</td>';
          html += '<td class="text-muted" style="font-size:.82rem;">'+esc(r.notes||'')+'</td>';
          html += '<td class="text-muted" style="font-size:.82rem;">'+esc(r.user_name)+'</td>';
          html += '<td><button class="btn btn-xs btn-outline-danger" onclick="del(\''+r.id.replace(/'/g,"\\'")+'\')" title="Apagar"><i class="fas fa-trash fa-xs"></i></button></td>';
          html += '</tr>';
        });
        if (!html) html = '<tr><td colspan="7" class="text-center py-4 text-muted">Sem registos.</td></tr>';
        document.getElementById('all-tbody').innerHTML = html;
      });
  }

  window.del = function(id) {
    if (!confirm('Apagar esta leitura?')) return;
    var fd = new FormData();
    fd.append('acao','apagar_log'); fd.append('id',id);
    fetch(BASE+'/ajax.php',{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(data){
        var el = document.getElementById('flash-msg');
        el.className = 'alert alert-'+(data.ok?'success':'danger')+' mt-3';
        el.style.display='block'; el.textContent=data.msg;
        if (data.ok) load();
      });
  };

  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  load();
})();
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
