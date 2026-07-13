<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Gráficos';
$activePage = 'graficos';
$db = getDB();
$al = getAnoLetivoAtivo();

$filterCarrId = (isset($_GET['carreira_id']) && $_GET['carreira_id'] !== '')
    ? (int)$_GET['carreira_id'] : null;
$filterCatId  = (isset($_GET['categoria_id']) && $_GET['categoria_id'] !== '')
    ? (int)$_GET['categoria_id'] : null;
$wCarr = $filterCarrId !== null ? 'AND car.id = ?' : '';
$wCat  = $filterCatId  !== null ? 'AND cat.id = ?' : '';

// Carreiras a omitir dos gráficos
$EXCL_CARR = 'Outras Unidades';

// ── Agregados globais (não filtrados) ─────────────────────
$byCarreira = $db->prepare("
    SELECT car.designacao AS label, car.ordem,
        ROUND(SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                   + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef), 2) AS h_slef,
        ROUND(SUM(d.turmas_OT*d.horas_OT*d.semanas/13), 2) AS h_ot,
        ROUND(SUM(d.h_tese), 2) AS h_tese,
        COUNT(DISTINCT d.docente_id) AS n_doc
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    WHERE d.ano_letivo_id = ? AND d.dsd_por_docente = 1
      AND (car.designacao IS NULL OR car.designacao != ?)
    GROUP BY car.id, car.designacao, car.ordem
    ORDER BY car.ordem
");
$byCarreira->execute([$al['id'], $al['id'], $EXCL_CARR]);
$carrData = $byCarreira->fetchAll();

$byTipo = $db->prepare("
    SELECT
        ROUND(SUM(d.turmas_T*d.horas_T*d.semanas/13), 2)     AS h_T,
        ROUND(SUM(d.turmas_TP*d.horas_TP*d.semanas/13), 2)   AS h_TP,
        ROUND(SUM(d.turmas_L*d.horas_L*d.semanas/13), 2)     AS h_L,
        ROUND(SUM(d.turmas_Sem*d.horas_Sem*d.semanas/13), 2) AS h_Sem,
        ROUND(SUM(d.turmas_OT*d.horas_OT*d.semanas/13), 2)   AS h_OT,
        ROUND(SUM(d.h_tese), 2) AS h_Tese
    FROM infodeqb_dsd_distribuicao d
    WHERE d.ano_letivo_id = ?
");
$byTipo->execute([$al['id']]);
$tipoData = $byTipo->fetch();

$necessRes = $db->prepare("
    SELECT
        ROUND(SUM(o.n_turmas_T   * o.horas_T   * o.semanas), 2) AS T,
        ROUND(SUM(o.n_turmas_TP  * o.horas_TP  * o.semanas), 2) AS TP,
        ROUND(SUM(o.n_turmas_L   * o.horas_L   * o.semanas), 2) AS L,
        ROUND(SUM(o.n_turmas_Sem * o.horas_Sem * o.semanas), 2) AS Sem,
        ROUND(SUM(o.n_turmas_OT  * o.horas_OT  * o.semanas), 2) AS OT
    FROM infodeqb_dsd_uc_ocorrencia o
    WHERE o.ano_letivo_id = ?
");
$necessRes->execute([$al['id']]);
$necTipo = $necessRes->fetch() ?: ['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0];

$atribRes = $db->prepare("
    SELECT
        ROUND(SUM(d.turmas_T   * d.horas_T   * d.semanas), 2) AS T,
        ROUND(SUM(d.turmas_TP  * d.horas_TP  * d.semanas), 2) AS TP,
        ROUND(SUM(d.turmas_L   * d.horas_L   * d.semanas), 2) AS L,
        ROUND(SUM(d.turmas_Sem * d.horas_Sem * d.semanas), 2) AS Sem,
        ROUND(SUM(d.turmas_OT  * d.horas_OT  * d.semanas), 2) AS OT
    FROM infodeqb_dsd_distribuicao d
    WHERE d.ano_letivo_id = ?
");
$atribRes->execute([$al['id']]);
$atrTipo = $atribRes->fetch() ?: ['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0];

// ── Por docente – filtrado por carreira ───────────────────
$byDocStmt = $db->prepare("
    SELECT doc.nome,
        COALESCE(cat.designacao, '(sem categoria)') AS categoria,
        COALESCE(cat.id, 0)    AS categoria_id,
        COALESCE(cat.ordem, 99) AS cat_ordem,
        COALESCE(car.id, 0)         AS carr_id,
        COALESCE(car.designacao, '(sem carreira)') AS carreira,
        COALESCE(car.ordem, 99) AS carr_ordem,
        COALESCE(da.deti, 1.00) AS deti,
        doc.nome_curto,
        ROUND(SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                   + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas / 13), 2) AS h_s,
        ROUND(SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                   + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef), 2) AS h_slef
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id = cat.id
    WHERE d.ano_letivo_id = ? AND d.dsd_por_docente = 1 $wCarr $wCat
      AND (car.designacao IS NULL OR car.designacao != ?)
    GROUP BY doc.id, doc.nome, cat.id, cat.designacao, cat.ordem,
             car.id, car.designacao, car.ordem, da.deti
    HAVING h_s > 0 OR h_slef > 0
    ORDER BY carr_ordem, cat_ordem, h_slef DESC
");
$docParams = [$al['id'], $al['id']];
if ($filterCarrId !== null) $docParams[] = $filterCarrId;
if ($filterCatId  !== null) $docParams[] = $filterCatId;
$docParams[] = $EXCL_CARR;
$byDocStmt->execute($docParams);
$docData = $byDocStmt->fetchAll();

// ── Top docentes (tabela, filtrado) ──────────────────────
$topDoc = $db->prepare("
    SELECT doc.nome,
        ROUND(SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                   + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef), 2) AS h_slef,
        ROUND(SUM(d.turmas_OT*d.horas_OT*d.semanas/13), 2) AS h_ot,
        ROUND(SUM(d.h_tese), 2) AS h_tese,
        da.h_slef AS ref
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car  ON da.carreira_id  = car.id
    LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id = cat.id
    WHERE d.ano_letivo_id = ? AND d.dsd_por_docente = 1 $wCarr $wCat
      AND (car.designacao IS NULL OR car.designacao != ?)
    GROUP BY doc.id, doc.nome, da.h_slef
    ORDER BY h_slef DESC LIMIT 25
");
$topParams = [$al['id'], $al['id']];
if ($filterCarrId !== null) $topParams[] = $filterCarrId;
if ($filterCatId  !== null) $topParams[] = $filterCatId;
$topParams[] = $EXCL_CARR;
$topDoc->execute($topParams);
$topDocData = $topDoc->fetchAll();

$carreiras  = $db->prepare("SELECT * FROM infodeqb_dsd_carreira WHERE designacao != ? ORDER BY ordem");
$carreiras->execute([$EXCL_CARR]);
$carreiras  = $carreiras->fetchAll();
$categorias = $db->query("SELECT id, designacao, carreira_id, ordem FROM infodeqb_dsd_categoria ORDER BY carreira_id, ordem")->fetchAll();

// Nomes para o título do gráfico
$filterCarrName = '';
if ($filterCarrId !== null) {
    foreach ($carreiras as $c) {
        if ((int)$c['id'] === $filterCarrId) { $filterCarrName = $c['designacao']; break; }
    }
}
$filterCatName = '';
if ($filterCatId !== null) {
    foreach ($categorias as $c) {
        if ((int)$c['id'] === $filterCatId) { $filterCatName = $c['designacao']; break; }
    }
}

$chartHeight = max(300, count($docData) * 34);

// Preparar array para JSON
$jsDocData = [];
foreach ($docData as $r) {
    $jsDocData[] = [
        'nome'      => $r['nome'],
        'label'     => $r['nome_curto'] ?: $r['nome'],
        'categoria' => $r['categoria'],
        'cat_id'    => (int)$r['categoria_id'],
        'carr_id'   => (int)$r['carr_id'],
        'carreira'  => $r['carreira'],
        'deti'      => (float)$r['deti'],
        'h_s'       => (float)$r['h_s'],
        'h_slef'    => (float)$r['h_slef'],
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">📈 Gráficos</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?></div>
  </div>
</div>

<div class="card" style="padding:10px 16px;margin-bottom:16px">
  <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0;min-width:200px">
      <label>Carreira</label>
      <select name="carreira_id" id="flt-carreira">
        <option value="">Todas</option>
        <?php foreach ($carreiras as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $filterCarrId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= esc($c['designacao']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:200px">
      <label>Categoria</label>
      <select name="categoria_id" id="flt-categoria">
        <option value="">Todas</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
                  data-carreira="<?= (int)$c['carreira_id'] ?>"
                  <?= $filterCatId === (int)$c['id'] ? 'selected' : '' ?>>
            <?= esc($c['designacao']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrar</button>
    <?php if ($filterCarrId !== null || $filterCatId !== null): ?>
      <a href="graficos.php" class="btn btn-secondary btn-sm">✕ Limpar</a>
    <?php endif; ?>
  </form>
</div>

<!-- ── Gráfico principal: H/s e H SLEf por docente ──────── -->
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
    <div class="card-title" style="margin:0">
      H/s e H SLEf por Docente
      <?php
        $titleParts = array_filter([$filterCarrName, $filterCatName]);
        echo $titleParts ? ' — ' . esc(implode(' · ', $titleParts)) : '';
      ?>
    </div>
    <button onclick="exportarGrafico()" class="btn btn-secondary btn-sm">⬇️ Exportar PNG</button>
  </div>

  <!-- Legenda custom -->
  <div id="chart-docentes-legend" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;font-size:12px;align-items:center"></div>

  <div style="overflow-x:auto">
    <div style="position:relative;height:600px;min-width:<?= max(600, count($docData) * 42) ?>px">
      <canvas id="chart-docentes"></canvas>
    </div>
  </div>
</div>

<!-- ── Gráficos agregados ─────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

  <div class="card">
    <div class="card-title">H SLEf por Carreira</div>
    <canvas id="chart-carreira" height="260"></canvas>
  </div>

  <div class="card">
    <div class="card-title">Distribuição por Tipo de Horas</div>
    <canvas id="chart-tipo" height="260"></canvas>
  </div>

  <div class="card" style="grid-column:1/-1">
    <div class="card-title">Cobertura: Necessárias vs Atribuídas por Tipologia</div>
    <canvas id="chart-cobertura" height="180"></canvas>
  </div>

</div>

<!-- ── Tabela top docentes ────────────────────────────────── -->
<div class="card">
  <div class="card-title">📊 Top Docentes – H SLEf
    <?= $filterCarrName ? '(' . esc($filterCarrName) . ')' : '' ?>
  </div>
  <div class="table-wrap">
  <table class="data-table" style="font-size:12px">
    <thead>
      <tr>
        <th>#</th><th>Docente</th>
        <th style="text-align:right">H SLEf</th>
        <th style="text-align:right">H OT</th>
        <th style="text-align:right">H Tese</th>
        <th style="text-align:right">H Equiv.</th>
        <th style="text-align:right">Ref.</th>
        <th style="min-width:120px">Ocupação</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($topDocData as $i => $d):
      $equiv = (float)$d['h_slef'] + (float)$d['h_ot'] + (float)$d['h_tese'];
      $pct   = $d['ref'] > 0 ? min(150, ($d['h_slef'] / $d['ref']) * 100) : null;
      $pcls  = $pct === null ? 'progress-warn' : ($pct >= 100 ? 'progress-over' : ($pct >= 80 ? 'progress-ok' : 'progress-warn'));
    ?>
    <tr>
      <td style="color:var(--gray-400)"><?= $i + 1 ?></td>
      <td><strong><?= esc($d['nome']) ?></strong></td>
      <td class="num" style="color:var(--blue);font-weight:600"><?= fmt((float)$d['h_slef'], 2) ?></td>
      <td class="num" style="color:var(--gray-500)"><?= $d['h_ot'] > 0 ? fmt((float)$d['h_ot'], 2) : '–' ?></td>
      <td class="num" style="color:var(--orange)"><?= $d['h_tese'] > 0 ? fmt((float)$d['h_tese'], 2) : '–' ?></td>
      <td class="num" style="font-weight:700"><?= fmt($equiv, 2) ?></td>
      <td class="num" style="color:var(--gray-400)"><?= $d['ref'] > 0 ? fmt((float)$d['ref'], 2) : '–' ?></td>
      <td>
        <?php if ($pct === null): ?>
          <span style="font-size:11px;color:var(--gray-400)">sem ref.</span>
        <?php else: ?>
        <div style="display:flex;align-items:center;gap:6px">
          <div class="progress-wrap" style="flex:1">
            <div class="progress-bar <?= $pcls ?>" style="width:<?= min(100, $pct) ?>%"></div>
          </div>
          <span style="font-size:11px;color:var(--gray-500);white-space:nowrap"><?= fmt($pct, 0) ?>%</span>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── Paleta base (cores por categoria) ─────────────────────
var CAT_PALETTE = [
  '#1d4ed8', // azul
  '#0f766e', // verde-azulado
  '#7c3aed', // violeta
  '#b45309', // castanho-âmbar
  '#be185d', // rosa
  '#0369a1', // azul-céu
  '#15803d', // verde
  '#c2410c', // laranja-escuro
];
var DETI_COLOR = '#b6650c'; // âmbar – ETI ≠ 1 (igual em todas as carreiras)
var GRAY_COLOR = 'rgba(156,163,175,0.35)'; // segmento H/s – H SLEf

// Fallback para docentes sem nome_curto: "João Silva Ferreira" → "João S. F."
var SKIP_WORDS = {'de':1,'da':1,'do':1,'das':1,'dos':1,'e':1};
function abrevNome(nome) {
    var parts = nome.trim().split(/\s+/);
    if (parts.length <= 2) return nome;
    var result = [parts[0]];
    for (var i = 1; i < parts.length; i++) {
        var w = parts[i];
        result.push(SKIP_WORDS[w.toLowerCase()] ? w : w[0] + '.');
    }
    return result.join(' ');
}
function chartLabel(d) {
    return d.label !== d.nome ? d.label : abrevNome(d.nome);
}

// ── Dados por docente ─────────────────────────────────────
var docData = <?= json_encode($jsDocData, JSON_UNESCAPED_UNICODE) ?>;

// Mapa de cores por (carr_id + '_' + cat_id)
// – cat_id > 0 → label = nome da categoria
// – cat_id = 0 → label = nome da carreira (sem categoria atribuída)
var colorKeyMap   = {};  // chave → cor hex
var colorLabelMap = {};  // chave → label legenda
var colorPalIdx   = 0;

function colorKey(d) { return d.carr_id + '_' + d.cat_id; }

docData.forEach(function(d) {
    var k = colorKey(d);
    colorLabelMap[k] = d.cat_id > 0 ? d.categoria : d.carreira;
    if (!colorKeyMap.hasOwnProperty(k)) {
        colorKeyMap[k] = CAT_PALETTE[colorPalIdx % CAT_PALETTE.length];
        colorPalIdx++;
    }
});

function hexToRgba(hex, alpha) {
    var r = parseInt(hex.slice(1,3), 16);
    var g = parseInt(hex.slice(3,5), 16);
    var b = parseInt(hex.slice(5,7), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
}

function barColor(d, alpha) {
    var base = (d.deti !== 1) ? DETI_COLOR : colorKeyMap[colorKey(d)];
    return hexToRgba(base, alpha);
}

// ── Gráfico de barras por docente ─────────────────────────
new Chart(document.getElementById('chart-docentes'), {
    type: 'bar',
    data: {
        labels: docData.map(function(d) { return chartLabel(d); }),
        datasets: [
            {
                label: 'H SLEf',
                data: docData.map(function(d) { return d.h_slef; }),
                backgroundColor: docData.map(function(d) { return barColor(d, 0.88); }),
                borderColor:     docData.map(function(d) { return barColor(d, 1); }),
                borderWidth: 1,
                stack: 'main',
            },
            {
                label: 'H/s – H SLEf',
                data: docData.map(function(d) { return Math.max(0, d.h_s - d.h_slef); }),
                backgroundColor: GRAY_COLOR,
                borderColor: 'rgba(156,163,175,0.5)',
                borderWidth: 1,
                stack: 'main',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        devicePixelRatio: window.devicePixelRatio || 1,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: function(items) {
                        var d = docData[items[0].dataIndex];
                        return d.nome + (d.deti !== 1 ? '  (ETI ' + d.deti + ')' : '');
                    },
                    afterTitle: function(items) {
                        var d = docData[items[0].dataIndex];
                        return d.categoria + ' · ' + d.carreira;
                    },
                    label: function(item) {
                        var d = docData[item.dataIndex];
                        if (item.datasetIndex === 0) {
                            return ' H SLEf: ' + d.h_slef.toFixed(1) + 'h';
                        }
                        return ' H/s: ' + d.h_s.toFixed(1) + 'h  (Δ ' + Math.max(0, d.h_s - d.h_slef).toFixed(1) + 'h)';
                    }
                }
            }
        },
        scales: {
            x: {
                stacked: true,
                ticks: { font: { size: 11 }, maxRotation: 90, minRotation: 90 }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                title: { display: true, text: 'h/semana (ref. 13 semanas)' }
            }
        }
    }
});

// ── Legenda custom ────────────────────────────────────────
(function() {
    var el = document.getElementById('chart-docentes-legend');

    function chip(color, label, style) {
        var span = document.createElement('span');
        span.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:4px;' + (style || 'background:#f3f4f6');
        var sw = document.createElement('span');
        sw.style.cssText = 'display:inline-block;width:14px;height:10px;border-radius:2px;flex-shrink:0;background:' + color;
        span.appendChild(sw);
        span.appendChild(document.createTextNode(label));
        return span;
    }

    function divider() {
        var d = document.createElement('span');
        d.style.cssText = 'display:inline-block;width:1px;height:18px;background:#d1d5db;align-self:center';
        return d;
    }

    // Chave das barras
    el.appendChild(chip('rgba(80,80,80,0.88)', 'H SLEf'));
    el.appendChild(chip(GRAY_COLOR, 'H/s – H SLEf (OT + ajuste f)'));
    el.appendChild(divider());

    // Grupos (carreira × categoria)
    Object.keys(colorKeyMap).forEach(function(k) {
        el.appendChild(chip(colorKeyMap[k], colorLabelMap[k]));
    });

    // ETI ≠ 1
    el.appendChild(divider());
    el.appendChild(chip(DETI_COLOR, 'ETI ≠ 1', 'background:#f6e6d2;border:1px solid #e8c691'));
})();

// ── Exportar gráfico + legenda como PNG ──────────────────
function exportarGrafico() {
    var src = document.getElementById('chart-docentes');
    var dpr = window.devicePixelRatio || 1;

    // Itens da legenda
    var items = [];
    items.push({ color: 'rgba(80,80,80,0.88)', text: 'H SLEf' });
    items.push({ color: 'rgba(156,163,175,0.65)', text: 'H/s – H SLEf (OT + ajuste f)' });
    Object.keys(colorKeyMap).forEach(function(k) {
        items.push({ color: colorKeyMap[k], text: colorLabelMap[k] });
    });
    items.push({ color: DETI_COLOR, text: 'ETI ≠ 1' });

    // Layout em grelha de 3 colunas — sem medir texto, coluna de largura fixa
    var COLS   = 3;
    var pad    = Math.round(16 * dpr);
    var rowH   = Math.round(24 * dpr);
    var swW    = Math.round(14 * dpr);
    var swH    = Math.round(9  * dpr);
    var gp     = Math.round(5  * dpr);
    var colW   = Math.floor((src.width - pad * 2) / COLS);
    var nRows  = Math.ceil(items.length / COLS);
    var legH   = pad + nRows * rowH + pad;
    var fs     = Math.round(11 * dpr);

    // Canvas final
    var out = document.createElement('canvas');
    out.width  = src.width;
    out.height = src.height + legH;
    var ctx = out.getContext('2d');

    // Fundo branco
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, out.width, out.height);

    // Gráfico
    ctx.drawImage(src, 0, 0);

    // Legenda
    ctx.font         = fs + 'px sans-serif';
    ctx.textBaseline = 'middle';
    items.forEach(function(item, i) {
        var col = i % COLS;
        var row = Math.floor(i / COLS);
        var x   = pad + col * colW;
        var y   = src.height + pad + row * rowH + rowH / 2;
        ctx.fillStyle = item.color;
        ctx.fillRect(x, y - swH / 2, swW, swH);
        ctx.fillStyle = '#374151';
        ctx.fillText(item.text, x + swW + gp, y);
    });

    // Download
    var partes = ['dsd'];
    <?php if ($filterCarrName): ?>
    partes.push(<?= json_encode(preg_replace('/[^a-zA-Z0-9]+/', '-', $filterCarrName)) ?>);
    <?php endif; ?>
    <?php if ($filterCatName): ?>
    partes.push(<?= json_encode(preg_replace('/[^a-zA-Z0-9]+/', '-', $filterCatName)) ?>);
    <?php endif; ?>
    partes.push('<?= date('Y-m-d') ?>');

    var a = document.createElement('a');
    a.href     = out.toDataURL('image/png');
    a.download = partes.join('_') + '.png';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ── Cascade filtro carreira → categoria ──────────────────
(function() {
    var selCarr = document.getElementById('flt-carreira');
    var selCat  = document.getElementById('flt-categoria');
    var allOpts = Array.from(selCat.querySelectorAll('option[data-carreira]'));

    function cascade() {
        var carrId = selCarr.value;
        var currentVal = selCat.value;
        allOpts.forEach(function(o) {
            o.hidden = carrId !== '' && o.dataset.carreira !== carrId;
        });
        // se a opção seleccionada ficou escondida, reset
        var sel = selCat.querySelector('option[value="' + currentVal + '"]');
        if (sel && sel.hidden) selCat.value = '';
    }

    selCarr.addEventListener('change', function() {
        selCat.value = '';
        cascade();
    });
    cascade(); // aplicar no carregamento
})();

// ── Gráfico H SLEf por Carreira ───────────────────────────
var COLORS = ['#0b6e73','#0e9f6e','#d97706','#e02424','#7e3af2','#1c64f2','#057a55','#b45309'];

new Chart(document.getElementById('chart-carreira'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($carrData, 'label')) ?>,
        datasets: [
            { label: 'H SLEf', data: <?= json_encode(array_map(function($r) { return (float)$r['h_slef']; }, $carrData)) ?>,
              backgroundColor: '#0b6e73' },
            { label: 'H OT',   data: <?= json_encode(array_map(function($r) { return (float)$r['h_ot']; },   $carrData)) ?>,
              backgroundColor: '#d97706' },
            { label: 'H Tese', data: <?= json_encode(array_map(function($r) { return (float)$r['h_tese']; }, $carrData)) ?>,
              backgroundColor: '#0e9f6e' },
        ]
    },
    options: { responsive:true, plugins:{legend:{position:'top'}},
        scales:{ x:{stacked:true}, y:{stacked:true, beginAtZero:true} } }
});

// ── Donut por tipo de horas ───────────────────────────────
new Chart(document.getElementById('chart-tipo'), {
    type: 'doughnut',
    data: {
        labels: ['T – Teóricas','TP – Teórico-Práticas','L – Laboratoriais','S – Seminários','OT – Orient. Tutorial','Tese'],
        datasets: [{
            data: [
                <?= (float)$tipoData['h_T'] ?>,
                <?= (float)$tipoData['h_TP'] ?>,
                <?= (float)$tipoData['h_L'] ?>,
                <?= (float)$tipoData['h_Sem'] ?>,
                <?= (float)$tipoData['h_OT'] ?>,
                <?= (float)$tipoData['h_Tese'] ?>
            ],
            backgroundColor: COLORS,
        }]
    },
    options: { responsive:true, plugins:{legend:{position:'right'}} }
});

// ── Cobertura necessárias vs atribuídas ───────────────────
new Chart(document.getElementById('chart-cobertura'), {
    type: 'bar',
    data: {
        labels: ['T','TP','L','Sem','OT'],
        datasets: [
            { label: 'Necessárias',
              data: [<?= (float)$necTipo['T'] ?>, <?= (float)$necTipo['TP'] ?>, <?= (float)$necTipo['L'] ?>, <?= (float)$necTipo['Sem'] ?>, <?= (float)$necTipo['OT'] ?>],
              backgroundColor: '#9ca3af' },
            { label: 'Atribuídas',
              data: [<?= (float)$atrTipo['T'] ?>, <?= (float)$atrTipo['TP'] ?>, <?= (float)$atrTipo['L'] ?>, <?= (float)$atrTipo['Sem'] ?>, <?= (float)$atrTipo['OT'] ?>],
              backgroundColor: '#0b6e73' },
        ]
    },
    options: { responsive:true, plugins:{legend:{position:'top'}},
        scales:{ y:{ beginAtZero:true } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
