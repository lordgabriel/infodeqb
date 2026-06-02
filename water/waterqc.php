<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';

date_default_timezone_set('Europe/Lisbon');

// Admins podem inserir registos (global + local)
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
$isAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsWater);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Sem filtro PHP — os gráficos carregam via AJAX com parâmetros de data

// Flash de operações POST
$flashMsg  = '';
$flashType = 'success';

// ── Inserção de leitura diária (apenas admins) ────────────────────
if (!empty($_POST) && $isAdmin && ($_POST['_acao'] ?? '') === 'inserir') {
    try {
        $pdo->beginTransaction();
        $dia = $_POST['dia'];

        // Destilada — water_type = 2
        $condDest = $_POST['cond_dest'] !== '' ? (float)$_POST['cond_dest'] : null;
        $phDest   = $_POST['ph_dest']   !== '' ? (float)$_POST['ph_dest']   : null;
        if ($condDest !== null || $phDest !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_ph_cond (dia, water_type, condutivity, pH)
                 VALUES (?, 2, ?, ?)'
            )->execute([$dia, $condDest, $phDest]);
        }
        $tocDest = $_POST['toc_dest'] !== '' ? (float)$_POST['toc_dest'] : null;
        $tcDest  = $_POST['tc_dest']  !== '' ? (float)$_POST['tc_dest']  : null;
        $icDest  = $_POST['ic_dest']  !== '' ? (float)$_POST['ic_dest']  : null;
        if ($tocDest !== null || $tcDest !== null || $icDest !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_toc (dia, water_type, TOC, TC, IC)
                 VALUES (?, 2, ?, ?, ?)'
            )->execute([$dia, $tocDest, $tcDest, $icDest]);
        }

        // Purificada — water_type = 3
        $condPur = $_POST['cond_pur'] !== '' ? (float)$_POST['cond_pur'] : null;
        $phPur   = $_POST['ph_pur']   !== '' ? (float)$_POST['ph_pur']   : null;
        if ($condPur !== null || $phPur !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_ph_cond (dia, water_type, condutivity, pH)
                 VALUES (?, 3, ?, ?)'
            )->execute([$dia, $condPur, $phPur]);
        }
        $tocPur = $_POST['toc_pur'] !== '' ? (float)$_POST['toc_pur'] : null;
        $tcPur  = $_POST['tc_pur']  !== '' ? (float)$_POST['tc_pur']  : null;
        $icPur  = $_POST['ic_pur']  !== '' ? (float)$_POST['ic_pur']  : null;
        if ($tocPur !== null || $tcPur !== null || $icPur !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_toc (dia, water_type, TOC, TC, IC)
                 VALUES (?, 3, ?, ?, ?)'
            )->execute([$dia, $tocPur, $tcPur, $icPur]);
        }

        $pdo->commit();
        $flashMsg  = 'Leitura de ' . htmlspecialchars($dia) . ' registada com sucesso.';
        $flashType = 'success';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg  = 'Erro ao inserir registo: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

Database::disconnect();

$pageTitle = 'Qualidade da Água';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto">
    <i class="fas fa-tint fa-sm mr-2 text-muted"></i>
    <?= t('WATER_QC_TITLE') ?>
  </h1>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert"
     style="font-size:.85rem">
  <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
  <?= $flashMsg ?>
  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php /* ── Formulário de inserção (apenas admins) ─────────────────── */ ?>
<?php if ($isAdmin): ?>
<div class="card mb-4 border-primary">
  <div class="card-header py-2 d-flex align-items-center"
       style="cursor:pointer" data-toggle="collapse" data-target="#formInsert">
    <i class="fas fa-plus-circle text-primary mr-2"></i>
    <strong class="mr-auto text-primary"><?= t('WATER_LOG_TITLE') ?></strong>
    <i class="fas fa-chevron-down fa-xs text-muted"></i>
  </div>
  <div id="formInsert" class="collapse <?= $flashType === 'success' && $flashMsg ? '' : '' ?>">
    <div class="card-body">
      <form method="post" class="needs-validation" novalidate>
        <input type="hidden" name="_acao" value="inserir">

        <div class="form-row mb-3">
          <div class="col-md-2">
            <label class="font-weight-bold">Data <span class="text-danger">*</span></label>
            <input type="date" name="dia" class="form-control" required
                   value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="row">
          <!-- Destilada -->
          <div class="col-md-6">
            <div class="card border-0 bg-light mb-3 p-3">
              <h6 class="font-weight-bold mb-3">
                <i class="fas fa-flask fa-sm mr-1 text-success"></i> Água Destilada
              </h6>
              <div class="form-row">
                <div class="col form-group mb-2">
                  <label class="small mb-1">Condutividade (µS/cm)</label>
                  <input type="number" step="0.001" name="cond_dest"
                         class="form-control form-control-sm" placeholder="ex: 0.5">
                </div>
                <div class="col form-group mb-2">
                  <label class="small mb-1">pH</label>
                  <input type="number" step="0.01" min="0" max="14" name="ph_dest"
                         class="form-control form-control-sm" placeholder="ex: 6.8">
                </div>
              </div>
              <div class="form-row">
                <div class="col form-group mb-0">
                  <label class="small mb-1">TOC</label>
                  <input type="number" step="0.001" name="toc_dest"
                         class="form-control form-control-sm">
                </div>
                <div class="col form-group mb-0">
                  <label class="small mb-1">TC</label>
                  <input type="number" step="0.001" name="tc_dest"
                         class="form-control form-control-sm">
                </div>
                <div class="col form-group mb-0">
                  <label class="small mb-1">IC</label>
                  <input type="number" step="0.001" name="ic_dest"
                         class="form-control form-control-sm">
                </div>
              </div>
            </div>
          </div>

          <!-- Purificada -->
          <div class="col-md-6">
            <div class="card border-0 bg-light mb-3 p-3">
              <h6 class="font-weight-bold mb-3">
                <i class="fas fa-flask fa-sm mr-1 text-primary"></i> Água Purificada
              </h6>
              <div class="form-row">
                <div class="col form-group mb-2">
                  <label class="small mb-1">Condutividade (µS/cm)</label>
                  <input type="number" step="0.001" name="cond_pur"
                         class="form-control form-control-sm" placeholder="ex: 0.1">
                </div>
                <div class="col form-group mb-2">
                  <label class="small mb-1">pH</label>
                  <input type="number" step="0.01" min="0" max="14" name="ph_pur"
                         class="form-control form-control-sm" placeholder="ex: 6.5">
                </div>
              </div>
              <div class="form-row">
                <div class="col form-group mb-0">
                  <label class="small mb-1">TOC</label>
                  <input type="number" step="0.001" name="toc_pur"
                         class="form-control form-control-sm">
                </div>
                <div class="col form-group mb-0">
                  <label class="small mb-1">TC</label>
                  <input type="number" step="0.001" name="tc_pur"
                         class="form-control form-control-sm">
                </div>
                <div class="col form-group mb-0">
                  <label class="small mb-1">IC</label>
                  <input type="number" step="0.001" name="ic_pur"
                         class="form-control form-control-sm">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-1">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save mr-1"></i> <?= t('WATER_SAVE_READING') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
.wq-filter-bar { background:#f8f9fa; border:1px solid #e3e6ea; border-radius:8px; padding:12px 16px; margin-bottom:1.2rem; }
.wq-pill {
    background:#fff; border:1px solid #dee2e6; border-radius:20px;
    padding:5px 14px; font-size:.82rem; font-weight:500; color:#495057;
    cursor:pointer; transition:all .12s; white-space:nowrap; line-height:1.4; outline:none;
}
.wq-pill:hover:not(:disabled) { border-color:#0d6efd; color:#0d6efd; background:#e9f0ff; }
.wq-pill.active { background:#0d6efd; border-color:#0d6efd; color:#fff; }
.wq-nav { display:flex; align-items:center; gap:10px; margin-top:10px; }
.wq-nav-btn {
    width:30px; height:30px; border-radius:50%; border:1px solid #dee2e6;
    background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center;
    font-size:.8rem; color:#495057; transition:all .12s; outline:none; flex-shrink:0;
}
.wq-nav-btn:hover:not(:disabled) { border-color:#0d6efd; color:#0d6efd; background:#e9f0ff; }
.wq-nav-btn:disabled { opacity:.35; cursor:default; }
.wq-period-label { font-size:.88rem; font-weight:600; color:#212529; min-width:0; }
.wq-period-sub  { font-size:.75rem; color:#6c757d; margin-left:4px; }
.wq-range-inputs { display:none; align-items:center; flex-wrap:wrap; gap:8px; margin-top:10px; }
.wq-range-inputs.open { display:flex; }
</style>

<div class="wq-filter-bar">
  <!-- Linha 1: pills de preset + botão exportar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
  <div class="d-flex flex-wrap align-items-center" style="gap:6px">
    <button class="wq-pill" data-preset="semana"><?= t('PERIOD_THIS_WEEK') ?></button>
    <button class="wq-pill active" data-preset="mes"><?= t('PERIOD_THIS_MONTH') ?></button>
    <button class="wq-pill" data-preset="ano"><?= t('PERIOD_THIS_YEAR') ?></button>
    <button class="wq-pill" data-preset="7d"><?= t('PERIOD_LAST_7') ?></button>
    <button class="wq-pill" data-preset="15d"><?= t('PERIOD_LAST_15') ?></button>
    <button class="wq-pill" data-preset="30d"><?= t('PERIOD_LAST_30') ?></button>
    <button class="wq-pill" data-preset="custom">
      <i class="fas fa-calendar-alt fa-xs mr-1"></i><?= t('PERIOD_CUSTOM') ?>
    </button>
  </div>
  <a id="btnExport" href="#" target="_blank"
     class="btn btn-sm btn-outline-success" style="white-space:nowrap">
    <i class="fas fa-file-excel mr-1"></i><?= t('WATER_EXPORT') ?>
  </a>
  </div><!-- fecha d-flex outer -->

  <!-- Linha 2: setas de navegação + label do período -->
  <div class="wq-nav">
    <button class="wq-nav-btn" id="wqPrev" title="Período anterior">
      <i class="fas fa-chevron-left"></i>
    </button>
    <span class="wq-period-label" id="wqLabel">—</span>
    <button class="wq-nav-btn" id="wqNext" title="Período seguinte" disabled>
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>

  <!-- Linha 3: campos de data personalizada (ocultos) -->
  <div class="wq-range-inputs" id="wqCustomRange">
    <label class="small font-weight-bold mb-0"><?= t('PERIOD_FROM') ?></label>
    <input type="date" id="wqDe"  class="form-control form-control-sm" style="width:auto">
    <span class="text-muted">—</span>
    <label class="small font-weight-bold mb-0"><?= t('PERIOD_TO') ?></label>
    <input type="date" id="wqAte" class="form-control form-control-sm" style="width:auto">
    <button class="btn btn-primary btn-sm" id="wqApply">
      <i class="fas fa-check mr-1"></i><?= t('APPLY') ?>
    </button>
  </div>
</div>

<?php /* ── Gráficos ─────────────────────────────────────────────── */ ?>
<div class="row">
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm mr-1 text-muted"></i>
        Condutividade <small class="text-muted">(µS/cm)</small>
      </div>
      <div class="card-body py-3">
        <canvas id="cond"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="cond-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm mr-1 text-muted"></i>
        pH
      </div>
      <div class="card-body py-3">
        <canvas id="ph"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="ph-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm mr-1 text-muted"></i>
        TOC / TC / IC — Destilada
      </div>
      <div class="card-body py-3">
        <canvas id="TCdest"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="tcdest-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm mr-1 text-muted"></i>
        TOC / TC / IC — Purificada
      </div>
      <div class="card-body py-3">
        <canvas id="TCpur"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="tcpur-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js v4 + adaptador de datas (uma única inclusão) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<script>
// ── Paleta de cores ───────────────────────────────────────────────
var C = {
    dest:  'rgb(75, 192, 192)',
    pur:   'rgb(54, 162, 235)',
    toc:   'rgb(255, 99, 132)',
    tc:    'rgb(255, 159, 64)',
    ic:    'rgb(153, 102, 255)',
};

// ── Opções base para gráficos de série temporal ───────────────────
function baseOpts(yLabel) {
    return {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: { bodyFont: { size: 11 } }
        },
        scales: {
            x: {
                type: 'time',
                time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy',
                        displayFormats: { day: 'dd/MM' } },
                title: { display: false }
            },
            y: {
                type: 'linear',
                display: true,
                title: { display: !!yLabel, text: yLabel || '' },
                ticks: { font: { size: 10 } }
            }
        }
    };
}

function dsBase(label, color) {
    return {
        label: label,
        borderColor: color,
        backgroundColor: color.replace('rgb(', 'rgba(').replace(')', ',0.08)'),
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.3,
        fill: false,
        data: []
    };
}

// ── Criar gráficos (vazios; serão preenchidos pelo AJAX) ──────────
var chartCond = new Chart(document.getElementById('cond'), {
    type: 'line',
    data: { datasets: [dsBase('Destilada', C.dest), dsBase('Purificada', C.pur)] },
    options: baseOpts('µS/cm')
});
var chartPH = new Chart(document.getElementById('ph'), {
    type: 'line',
    data: { datasets: [dsBase('Destilada', C.dest), dsBase('Purificada', C.pur)] },
    options: baseOpts('pH')
});
var chartTCdest = new Chart(document.getElementById('TCdest'), {
    type: 'line',
    data: { datasets: [dsBase('TOC', C.toc), dsBase('TC', C.tc), dsBase('IC', C.ic)] },
    options: baseOpts()
});
var chartTCpur = new Chart(document.getElementById('TCpur'), {
    type: 'line',
    data: { datasets: [dsBase('TOC', C.toc), dsBase('TC', C.tc), dsBase('IC', C.ic)] },
    options: baseOpts()
});

// ── Utilitários de data ───────────────────────────────────────────
var today = new Date(); today.setHours(0,0,0,0);
var mesesLPT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

function fmt(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
}
function fmtHuman(iso) {
    var p = iso.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}
function addDays(d, n) { var r = new Date(d); r.setDate(r.getDate() + n); return r; }
function isoToDate(s) { var p = s.split('-'); return new Date(parseInt(p[0]), parseInt(p[1])-1, parseInt(p[2])); }

// ── Estado da navegação ───────────────────────────────────────────
var activePreset  = 'mes';
var periodOffset  = 0;        // 0 = período actual, -1 = um período atrás, ...
var customDe      = null;     // datas base para modo custom
var customAte     = null;
var customDays    = 0;        // duração em dias do range custom

// ── Calcular range com offset ─────────────────────────────────────
function getRangeWithOffset(preset, off) {
    var de, ate, label;

    if (preset === 'mes') {
        var base = new Date(today.getFullYear(), today.getMonth() + off, 1);
        de = fmt(base);
        ate = (off === 0) ? fmt(today)
                          : fmt(new Date(base.getFullYear(), base.getMonth() + 1, 0));
        label = mesesLPT[base.getMonth()] + ' ' + base.getFullYear();

    } else if (preset === 'semana') {
        var dow = today.getDay();
        var startCur = addDays(today, -(dow === 0 ? 6 : dow - 1)); // Monday
        var start = addDays(startCur, off * 7);
        var end   = addDays(start, 6);
        if (off === 0 && end > today) end = new Date(today);
        de = fmt(start); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === 'ano') {
        var year = today.getFullYear() + off;
        de  = year + '-01-01';
        ate = (off === 0) ? fmt(today) : year + '-12-31';
        label = String(year);

    } else if (preset === '7d') {
        var end = addDays(today, off * 7);
        if (end > today) end = new Date(today);
        de = fmt(addDays(end, -6)); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === '15d') {
        var end = addDays(today, off * 15);
        if (end > today) end = new Date(today);
        de = fmt(addDays(end, -14)); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === '30d') {
        var end = addDays(today, off * 30);
        if (end > today) end = new Date(today);
        de = fmt(addDays(end, -29)); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === 'custom' && customDe && customAte) {
        var step    = customDays * off;
        var newAte  = addDays(isoToDate(customAte), step);
        if (newAte > today) newAte = new Date(today);
        var newDe   = addDays(newAte, -(customDays - 1));
        de = fmt(newDe); ate = fmt(newAte);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);
    }

    return { de: de, ate: ate, label: label || '' };
}

// ── Aplicar preset + offset ───────────────────────────────────────
function applyNav() {
    var range = getRangeWithOffset(activePreset, periodOffset);

    // Label no filtro
    $('#wqLabel').text(range.label);

    // Seta direita: desactivada quando estamos no período actual (offset=0)
    $('#wqNext').prop('disabled', periodOffset >= 0);

    // Botão exportar: URL sempre sincronizada com o período visível
    $('#btnExport').attr('href', 'waterqc_export.php?de=' + range.de + '&ate=' + range.ate);

    loadCharts(range.de, range.ate);
}

// ── Carregar dados via AJAX ───────────────────────────────────────
function loadCharts(de, ate) {
    ['cond-empty','ph-empty','tcdest-empty','tcpur-empty'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('d-none');
    });
    [chartCond, chartPH, chartTCdest, chartTCpur].forEach(function(ch) {
        ch.data.datasets.forEach(function(ds) { ds.data = []; });
    });

    $.getJSON('waterqcdata.php', { de: de, ate: ate }, function (resp) {
    var phcond = resp.phcond || [];
    var toc    = resp.toc    || [];

    // Separar pH/condutividade por tipo
    var destCond = [], purCond = [], destPH = [], purPH = [];
    phcond.forEach(function (r) {
        var pt = { x: r.dia };
        if (r.tipo == 2) {
            destCond.push({ x: r.dia, y: r.condutivity });
            destPH.push(  { x: r.dia, y: r.pH });
        } else {
            purCond.push({ x: r.dia, y: r.condutivity });
            purPH.push(  { x: r.dia, y: r.pH });
        }
    });

    chartCond.data.datasets[0].data = destCond;
    chartCond.data.datasets[1].data = purCond;
    chartCond.update();
    if (!destCond.length && !purCond.length)
        document.getElementById('cond-empty').classList.remove('d-none');

    chartPH.data.datasets[0].data = destPH;
    chartPH.data.datasets[1].data = purPH;
    chartPH.update();
    if (!destPH.length && !purPH.length)
        document.getElementById('ph-empty').classList.remove('d-none');

    // Separar TOC/TC/IC por tipo
    var d_toc = [], d_tc = [], d_ic = [];
    var p_toc = [], p_tc = [], p_ic = [];
    toc.forEach(function (r) {
        if (r.tipo == 2) {
            d_toc.push({ x: r.dia, y: r.TOC });
            d_tc.push( { x: r.dia, y: r.TC });
            d_ic.push( { x: r.dia, y: r.IC });
        } else {
            p_toc.push({ x: r.dia, y: r.TOC });
            p_tc.push( { x: r.dia, y: r.TC });
            p_ic.push( { x: r.dia, y: r.IC });
        }
    });

    chartTCdest.data.datasets[0].data = d_toc;
    chartTCdest.data.datasets[1].data = d_tc;
    chartTCdest.data.datasets[2].data = d_ic;
    chartTCdest.update();
    if (!d_toc.length && !d_tc.length && !d_ic.length)
        document.getElementById('tcdest-empty').classList.remove('d-none');

    chartTCpur.data.datasets[0].data = p_toc;
    chartTCpur.data.datasets[1].data = p_tc;
    chartTCpur.data.datasets[2].data = p_ic;
    chartTCpur.update();
    if (!p_toc.length && !p_tc.length && !p_ic.length)
        document.getElementById('tcpur-empty').classList.remove('d-none');
    }); // end $.getJSON
} // end loadCharts

$(document).ready(function () {

    // ── Inicializar com "Este mês" ────────────────────────────────
    activePreset  = 'mes';
    periodOffset  = 0;
    applyNav();

    // ── Clique nos pills ──────────────────────────────────────────
    $('.wq-pill').on('click', function () {
        $('.wq-pill').removeClass('active');
        $(this).addClass('active');
        activePreset = $(this).data('preset');
        periodOffset = 0;

        if (activePreset === 'custom') {
            $('#wqCustomRange').addClass('open');
            // Pre-preencher com o mês actual
            var base = getRangeWithOffset('mes', 0);
            $('#wqDe').val(base.de);
            $('#wqAte').val(base.ate);
            customDe   = base.de; customAte  = base.ate;
            customDays = Math.round((isoToDate(base.ate) - isoToDate(base.de)) / 86400000) + 1;
            applyNav();
        } else {
            $('#wqCustomRange').removeClass('open');
            applyNav();
        }
    });

    // ── Seta esquerda (período anterior) ─────────────────────────
    $('#wqPrev').on('click', function () {
        periodOffset--;
        applyNav();
    });

    // ── Seta direita (período seguinte) ──────────────────────────
    $('#wqNext').on('click', function () {
        if (periodOffset >= 0) return;
        periodOffset++;
        applyNav();
    });

    // ── Calendário personalizado — Aplicar ────────────────────────
    $('#wqApply').on('click', function () {
        var de  = $('#wqDe').val();
        var ate = $('#wqAte').val();
        if (!de || !ate) return;
        if (de > ate) { var t = de; de = ate; ate = t; }
        customDe   = de;
        customAte  = ate;
        customDays = Math.round((isoToDate(ate) - isoToDate(de)) / 86400000) + 1;
        periodOffset = 0;
        applyNav();
    });

    $('#wqDe, #wqAte').on('keydown', function (e) {
        if (e.key === 'Enter') $('#wqApply').trigger('click');
    });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
