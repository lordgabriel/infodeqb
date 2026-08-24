<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/supabase.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? [];
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);
if (!$isGasAdmin) { header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit; }

$gases = GASES_DEF;
$pageTitle = 'Relatórios — Gases Especiais';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<style>
/* ── Gas toggle buttons ── */
.gas-toggle {
    border: 1px solid #dee2e6; border-radius: 4px;
    padding: 4px 10px; font-size: .82rem; cursor: pointer;
    transition: all .15s; font-family: monospace; background: #fff; color: #6c757d;
}
</style>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title">
      <a href="index.php" class="text-muted me-2" style="font-size:.8em;"><i class="fas fa-arrow-left"></i></a>
      Relatórios
    </h1>
    <p class="iq-page-sub">Evolução da pressão e exportação de dados</p>
  </div>
</div>

<!-- Barra de filtro de período (estilo água) -->
<div class="wq-filter-bar">
  <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px;">
    <div class="d-flex flex-wrap align-items-center" style="gap:6px;">
      <button class="wq-pill" data-preset="semana">Esta semana</button>
      <button class="wq-pill active" data-preset="mes">Este mês</button>
      <button class="wq-pill" data-preset="ano">Este ano</button>
      <button class="wq-pill" data-preset="7d">Últimos 7 dias</button>
      <button class="wq-pill" data-preset="15d">Últimos 15 dias</button>
      <button class="wq-pill" data-preset="30d">Últimos 30 dias</button>
      <button class="wq-pill" data-preset="custom">
        <i class="fas fa-calendar-alt fa-xs me-1"></i>Personalizado
      </button>
    </div>
    <button class="btn btn-sm btn-outline-success" id="btn-csv">
      <i class="fas fa-file-csv me-1"></i> Exportar CSV
    </button>
  </div>
  <div class="wq-nav">
    <button class="wq-nav-btn" id="wqPrev" title="Período anterior"><i class="fas fa-chevron-left"></i></button>
    <span class="wq-period-label" id="wqLabel">—</span>
    <button class="wq-nav-btn" id="wqNext" title="Período seguinte" disabled><i class="fas fa-chevron-right"></i></button>
  </div>
  <div class="wq-range-inputs" id="wqCustomRange">
    <label class="small fw-bold mb-0">De</label>
    <input type="date" id="wqDe"  class="form-control form-control-sm" style="width:auto;">
    <span class="text-muted">—</span>
    <label class="small fw-bold mb-0">Até</label>
    <input type="date" id="wqAte" class="form-control form-control-sm" style="width:auto;">
    <button class="btn btn-primary btn-sm" id="wqApply"><i class="fas fa-check me-1"></i>Aplicar</button>
  </div>
</div>

<!-- Selectores de gás -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <span class="text-muted me-1" style="font-size:.82rem;">Gases:</span>
  <?php foreach ($gases as $gid => $g): ?>
  <button type="button"
          class="btn btn-sm gas-toggle"
          data-gas-id="<?php echo $gid; ?>"
          data-cor="<?php echo $g['cor_bs']; ?>">
    <?php echo htmlspecialchars($g['symbol']); ?>
  </button>
  <?php endforeach; ?>
  <span class="vr mx-1"></span>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-todos" style="font-size:.78rem;">Todos</button>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-limpar" style="font-size:.78rem;">Limpar</button>
</div>

<!-- Gráfico consolidado -->
<div class="card shadow-sm mb-4" style="border-radius:var(--iq-r2);">
  <div class="card-body">
    <div style="position:relative;height:360px;">
      <canvas id="chart-all"></canvas>
    </div>
  </div>
</div>

<!-- Resumo tabela -->
<div class="card shadow-sm" style="border-radius:var(--iq-r2);">
  <div class="card-header card-header-transparent"><strong>Resumo por gás (no período)</strong></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Gás</th><th>Leituras</th><th>Pressão média</th>
            <th>Mín.</th><th>Máx.</th><th>Última leitura</th><th>Trocas</th>
          </tr>
        </thead>
        <tbody id="resumo-tbody">
          <tr><td colspan="7" class="text-center py-4 text-muted">
            <span class="spinner-border spinner-border-sm me-2"></span>A carregar…
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1/dist/chartjs-adapter-luxon.umd.min.js"></script>
<script>
$(function () {
  var BASE  = '<?php echo HTTP_DIR; ?>/infodeqb/gases';
  var GASES = <?php echo json_encode($gases); ?>;

  var COLOR_BS = {
    'dark':'#343a40','primary':'#0d6efd','info':'#0dcaf0',
    'success':'#198754','secondary':'#6c757d','warning':'#ffc107','danger':'#dc3545',
    'orange':'#fd7e14'
  };
  function gasColor(g, a) {
    var hex = COLOR_BS[g.cor_bs] || '#0d6efd';
    var r=parseInt(hex.slice(1,3),16), gr=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
    return 'rgba('+r+','+gr+','+b+','+(a||1)+')';
  }

  var chart = null, rawRows = [];
  var currentDe = '', currentAte = '';

  // Mapa de cores Bootstrap → hex
  var COLOR_HEX = {
    'dark':'#343a40','primary':'#0d6efd','info':'#0dcaf0',
    'success':'#198754','secondary':'#6c757d','warning':'#ffc107','danger':'#dc3545',
    'orange':'#fd7e14'
  };
  var TEXT_HEX = {
    'dark':'#fff','primary':'#fff','info':'#000',
    'success':'#fff','secondary':'#fff','warning':'#000','danger':'#fff',
    'orange':'#000'
  };

  function setToggleActive(btn, active) {
    var cor = btn.data('cor');
    if (active) {
      btn.css({ background: COLOR_HEX[cor]||'#6c757d', color: TEXT_HEX[cor]||'#fff',
                'border-color': COLOR_HEX[cor]||'#6c757d', opacity:'1', filter:'' });
      btn.data('active', '1');
    } else {
      btn.css({ background:'#fff', color:'#adb5bd', 'border-color':'#e9ecef',
                opacity:'.45', filter:'saturate(0)' });
      btn.data('active', '0');
    }
  }

  // Iniciar todos activos
  $('.gas-toggle').each(function() { setToggleActive($(this), true); });

  $('.gas-toggle').on('click', function() {
    var active = $(this).data('active') === '1';
    setToggleActive($(this), !active);
    renderChart();
    renderResumo();
  });

  $('#btn-todos').on('click', function() {
    $('.gas-toggle').each(function() { setToggleActive($(this), true); });
    renderChart(); renderResumo();
  });

  $('#btn-limpar').on('click', function() {
    $('.gas-toggle').each(function() { setToggleActive($(this), false); });
    renderChart(); renderResumo();
  });

  // ── Utilitários de data ─────────────────────────────────────────
  var today = new Date(); today.setHours(0,0,0,0);
  var meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
               'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  function fmt(d) {
    return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
  }
  function fmtH(iso) { var p=iso.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
  function addDays(d,n) { var r=new Date(d); r.setDate(r.getDate()+n); return r; }
  function isoToDate(s) { var p=s.split('-'); return new Date(parseInt(p[0]),parseInt(p[1])-1,parseInt(p[2])); }

  // ── Estado navegação ───────────────────────────────────────────
  var activePreset = 'mes', periodOffset = 0;
  var customDe = null, customAte = null, customDays = 0;

  function getRange(preset, off) {
    var de, ate, label;
    if (preset === 'mes') {
      var base = new Date(today.getFullYear(), today.getMonth() + off, 1);
      de = fmt(base);
      ate = (off===0) ? fmt(today) : fmt(new Date(base.getFullYear(), base.getMonth()+1, 0));
      label = meses[base.getMonth()] + ' ' + base.getFullYear();
    } else if (preset === 'semana') {
      var dow = today.getDay();
      var start = addDays(today, -(dow===0?6:dow-1) + off*7);
      var end   = addDays(start, 6);
      if (off===0 && end>today) end = new Date(today);
      de=fmt(start); ate=fmt(end); label=fmtH(de)+' – '+fmtH(ate);
    } else if (preset === 'ano') {
      var yr = today.getFullYear()+off;
      de=yr+'-01-01'; ate=(off===0)?fmt(today):yr+'-12-31'; label=String(yr);
    } else if (preset === '7d') {
      var e=addDays(today,off*7); if(e>today)e=new Date(today);
      de=fmt(addDays(e,-6)); ate=fmt(e); label=fmtH(de)+' – '+fmtH(ate);
    } else if (preset === '15d') {
      var e=addDays(today,off*15); if(e>today)e=new Date(today);
      de=fmt(addDays(e,-14)); ate=fmt(e); label=fmtH(de)+' – '+fmtH(ate);
    } else if (preset === '30d') {
      var e=addDays(today,off*30); if(e>today)e=new Date(today);
      de=fmt(addDays(e,-29)); ate=fmt(e); label=fmtH(de)+' – '+fmtH(ate);
    } else if (preset==='custom' && customDe && customAte) {
      var step=customDays*off;
      var na=addDays(isoToDate(customAte),step); if(na>today)na=new Date(today);
      var nd=addDays(na,-(customDays-1));
      de=fmt(nd); ate=fmt(na); label=fmtH(de)+' – '+fmtH(ate);
    }
    return { de:de||fmt(today), ate:ate||fmt(today), label:label||'' };
  }

  function applyNav() {
    var range = getRange(activePreset, periodOffset);
    $('#wqLabel').text(range.label);
    $('#wqNext').prop('disabled', periodOffset >= 0);
    currentDe = range.de; currentAte = range.ate;
    loadData(range.de, range.ate);
  }

  // ── Carregar dados ─────────────────────────────────────────────
  function loadData(de, ate) {
    $('#resumo-tbody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>A carregar…</td></tr>');
    $.getJSON(BASE + '/ajax.php', { acao:'chart_data', de:de, ate:ate }, function(data) {
      if (!data.ok) return;
      rawRows = data.rows;
      renderChart();
      renderResumo();
    });
  }

  function activeGasIds() {
    var ids = [];
    $('.gas-toggle').each(function() {
      if ($(this).data('active') === '1') ids.push($(this).data('gas-id'));
    });
    return ids;
  }

  function renderChart() {
    var active = activeGasIds();
    var datasets = [];
    Object.keys(GASES).forEach(function(gid) {
      if (active.indexOf(gid) === -1) return;
      var g = GASES[gid];
      var rows = rawRows.filter(function(r){ return r.gas_id===gid; });
      if (!rows.length) return;
      datasets.push({
        label: g.symbol,
        data: rows.map(function(r){ return {x:r.timestamp, y:parseFloat(r.pressure)}; }),
        borderColor: gasColor(g), backgroundColor: gasColor(g, 0.05),
        pointRadius: 3, tension: 0.3, fill: false,
      });
    });
    if (chart) chart.destroy();
    chart = new Chart(document.getElementById('chart-all').getContext('2d'), {
      type: 'line',
      data: { datasets: datasets },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins: {
          legend: { position:'top', labels:{boxWidth:12,font:{size:12}} },
          tooltip: { callbacks:{ label:function(c){ return c.dataset.label+': '+c.parsed.y+' bar'; } } },
        },
        scales: {
          x: { type:'time', time:{unit:'day',tooltipFormat:'dd/MM/yyyy HH:mm'}, grid:{display:false} },
          y: { min:0, ticks:{callback:function(v){return v+' bar';}}, grid:{color:'rgba(0,0,0,.05)'} },
        },
      },
    });
  }

  function renderResumo() {
    var active = activeGasIds();
    var html = '';
    Object.keys(GASES).forEach(function(gid) {
      if (active.indexOf(gid)===-1) return;
      var g = GASES[gid];
      var rows = rawRows.filter(function(r){ return r.gas_id===gid; });
      if (!rows.length) {
        html += '<tr><td><span class="badge bg-'+g.cor_bs+'" style="font-family:monospace;">'+esc(g.symbol)+'</span> '+esc(g.name)+'</td>';
        html += '<td colspan="6" class="text-muted">Sem dados no período</td></tr>';
        return;
      }
      var ps = rows.map(function(r){ return parseFloat(r.pressure); });
      var media = ps.reduce(function(a,b){return a+b;},0)/ps.length;
      var trocas = rows.filter(function(r){ return r.is_new_bottle; }).length;
      var ultima = rows[rows.length-1];
      var d = new Date(ultima.timestamp);
      html += '<tr>';
      html += '<td><span class="badge bg-'+g.cor_bs+'" style="font-family:monospace;">'+esc(g.symbol)+'</span> '+esc(g.name)+'</td>';
      html += '<td>'+rows.length+'</td>';
      html += '<td>'+media.toFixed(1)+' '+g.unit+'</td>';
      html += '<td>'+Math.min.apply(null,ps).toFixed(1)+' '+g.unit+'</td>';
      html += '<td>'+Math.max.apply(null,ps).toFixed(1)+' '+g.unit+'</td>';
      html += '<td style="font-size:.85rem;">'+d.toLocaleDateString('pt-PT')+' '+d.toLocaleTimeString('pt-PT',{hour:'2-digit',minute:'2-digit'})+'</td>';
      html += '<td>'+(trocas?'<span class="badge bg-success">'+trocas+'</span>':'0')+'</td>';
      html += '</tr>';
    });
    if (!html) html = '<tr><td colspan="7" class="text-center py-4 text-muted">Sem dados no período seleccionado.</td></tr>';
    $('#resumo-tbody').html(html);
  }

  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // ── Eventos pills ──────────────────────────────────────────────
  $('.wq-pill').on('click', function() {
    $('.wq-pill').removeClass('active');
    $(this).addClass('active');
    activePreset = $(this).data('preset');
    periodOffset = 0;
    if (activePreset === 'custom') {
      $('#wqCustomRange').addClass('open');
      var base = getRange('mes', 0);
      $('#wqDe').val(base.de); $('#wqAte').val(base.ate);
      customDe=base.de; customAte=base.ate;
      customDays = Math.round((isoToDate(base.ate)-isoToDate(base.de))/86400000)+1;
      applyNav();
    } else {
      $('#wqCustomRange').removeClass('open');
      applyNav();
    }
  });

  $('#wqPrev').on('click', function(){ periodOffset--; applyNav(); });
  $('#wqNext').on('click', function(){ if(periodOffset>=0)return; periodOffset++; applyNav(); });

  $('#wqApply').on('click', function() {
    var de=$('#wqDe').val(), ate=$('#wqAte').val();
    if (!de||!ate) return;
    if (de>ate){var t=de;de=ate;ate=t;}
    customDe=de; customAte=ate;
    customDays=Math.round((isoToDate(ate)-isoToDate(de))/86400000)+1;
    periodOffset=0; applyNav();
  });
  $('#wqDe,#wqAte').on('keydown', function(e){ if(e.key==='Enter') $('#wqApply').trigger('click'); });

  $('#btn-csv').on('click', function() {
    window.location = BASE + '/ajax.php?acao=export_csv&de=' + currentDe + '&ate=' + currentAte;
  });

  // ── Arrancar ───────────────────────────────────────────────────
  applyNav();
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
