<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Balanço de Horas';
$activePage = 'rel-falta';
$db = getDB();
$al = getAnoLetivoAtivo();

$filterPlano = $_GET['plano'] ?? '';
$filterSem   = $_GET['sem']   ?? '';
$mostrarTudo = isset($_GET['tudo']);

$params = [$al['id']];
$where = ["o.ano_letivo_id = ?"];
if ($filterPlano) { $where[] = "pe.sigla = ?";    $params[] = $filterPlano; }
if ($filterSem)   { $where[] = "uc.semestre = ?"; $params[] = $filterSem; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$rows = $db->prepare("
    SELECT o.*, uc.designacao AS uc_nome, uc.semestre,
           pe.sigla AS plano, pe.ordem AS plano_ordem,
           (SELECT COUNT(*) FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id = o.id) AS n_dist
    FROM infodeqb_dsd_uc_ocorrencia o
    JOIN infodeqb_dsd_uc uc ON o.uc_id = uc.id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(o.plano_id, uc.plano_id) = pe.id
    $whereSQL
    ORDER BY pe.ordem, uc.semestre, uc.designacao
");
$rows->execute($params);
$ocs = $rows->fetchAll();

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem")->fetchAll();

$grupos = [];
$totGeral = ['need'=>['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0,'total'=>0],
             'done'=>['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0,'total'=>0]];

foreach ($ocs as $o) {
    $need  = horasNecessarias($o);
    $done  = horasAtribuidas($db, (int)$o['id']);
    $falta = horasEmFalta($need, $done);

    foreach (['T','TP','L','Sem','OT','total'] as $k) {
        $totGeral['need'][$k] += $need[$k];
        $totGeral['done'][$k] += $done[$k];
    }

    $temFalta = abs($falta['total']) > 0.01;
    if (!$mostrarTudo && !$temFalta) continue;

    $planoKey = $o['plano'] ?? '(sem plano)';
    if (!isset($grupos[$planoKey])) {
        $grupos[$planoKey] = [
            'linhas' => [],
            'sub' => [
                'need'=>['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0,'total'=>0],
                'done'=>['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0,'total'=>0],
            ],
        ];
    }
    foreach (['T','TP','L','Sem','OT','total'] as $k) {
        $grupos[$planoKey]['sub']['need'][$k] += $need[$k];
        $grupos[$planoKey]['sub']['done'][$k] += $done[$k];
    }
    $grupos[$planoKey]['linhas'][] = compact('o','need','done','falta','temFalta');
}

$totLinhas = 0;
foreach ($grupos as $g) $totLinhas += count($g['linhas']);

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.hf-table { font-size:12px; width:100%; border-collapse:collapse; }
.hf-table th, .hf-table td { padding:4px 6px; }
.hf-table thead th { white-space:nowrap; }
.hf-table thead tr.hf-h1 th {
  background:var(--gray-100); border-bottom:1px solid var(--gray-300);
  font-size:11px; text-transform:uppercase; letter-spacing:.04em;
}
.hf-table thead tr.hf-h2 th {
  background:var(--gray-50); border-bottom:1px solid var(--gray-200);
  font-size:10px; color:var(--gray-500); font-weight:600;
}
.hf-table td.num { text-align:right; font-variant-numeric: tabular-nums; }
.hf-table td.nec  { color:var(--gray-500); }
.hf-table td.atr  { color:var(--gray-700); }
.hf-table td.falta { font-weight:700; }
.hf-table td.falta.ok  { color:var(--green); }
.hf-table td.falta.ko  { color:var(--red); }
.hf-table td.falta.exc { color:var(--blue); }
.hf-table td.dash { color:var(--gray-300); text-align:right; }
.hf-table .tipo-sep       { border-left:2px solid var(--gray-300); }
.hf-table .tipo-sep-light { border-left:1px solid var(--gray-200); }
.hf-table tbody tr:hover td { background:var(--gray-50); }

.hf-plano-row { background:var(--blue-light); color:var(--blue-dark); cursor:pointer; }
.hf-plano-row:hover { background:#cfe3fd; }
.hf-plano-row td { padding:9px 10px; font-weight:700; }
.hf-plano-row td.num { color:var(--blue-dark); }
.hf-plano-row td.nec, .hf-plano-row td.atr { color:var(--gray-600); font-weight:500; }
.hf-plano-row td.falta.ok  { color:var(--green); }
.hf-plano-row td.falta.ko  { color:var(--red); }
.hf-plano-row td.falta.exc { color:var(--blue); }
.hf-plano-row td.tipo-sep       { border-left:2px solid var(--gray-300); }
.hf-plano-row td.tipo-sep-light { border-left:1px solid var(--gray-200); }
.hf-plano-row .badge { background:var(--blue) !important; color:#fff !important; }
</style>

<div class="page-header">
  <div>
    <div class="page-title">⚠️ Balanço de Horas</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?> &mdash;
      <?= $totLinhas ?> ocorrência<?= $totLinhas === 1 ? '' : 's' ?>
      em <?= count($grupos) ?> plano<?= count($grupos) === 1 ? '' : 's' ?>
      <?= $mostrarTudo ? '(tudo)' : '(só com falta)' ?>
      &middot; valores em <strong>h/sem</strong>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('plano-')">⊟ Colapsar planos</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('plano-')">⊞ Expandir planos</button>
    <a href="?<?= $mostrarTudo ? '' : 'tudo=1' ?>" class="btn btn-secondary btn-sm">
      <?= $mostrarTudo ? 'Só com falta' : 'Mostrar tudo' ?>
    </a>
    <button class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir</button>
  </div>
</div>

<!-- Resumo global -->
<div class="card" style="background:linear-gradient(135deg, var(--blue-light), #fff);border-left:4px solid var(--blue)">
  <div class="card-title">📊 Resumo Global por Tipologia <span style="font-size:11px;font-weight:400;color:var(--gray-500)">(h/sem)</span></div>
  <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;font-size:12px">
    <?php foreach (['T','TP','L','Sem','OT','total'] as $k):
      $n = $totGeral['need'][$k]; $d = $totGeral['done'][$k]; $f = $n - $d;
      $cor = abs($f) < 0.01 ? 'var(--green)' : ($f > 0 ? 'var(--red)' : 'var(--blue)');
      $pct = $n > 0 ? min(100, ($d / $n) * 100) : 0;
    ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:6px;padding:10px;text-align:center">
      <div style="font-weight:600;color:var(--gray-600);text-transform:uppercase;font-size:11px">
        <?= $k === 'total' ? 'TOTAL' : $k ?>
      </div>
      <div style="font-size:18px;font-weight:700;margin:4px 0;color:var(--gray-900)"><?= fmt($n, 1) ?></div>
      <div class="progress-wrap" style="margin:4px 0">
        <div class="progress-bar <?= $pct >= 100 ? 'progress-ok' : 'progress-warn' ?>" style="width:<?= $pct ?>%"></div>
      </div>
      <div style="font-size:11px;color:var(--gray-500)">Atrib: <?= fmt($d, 1) ?></div>
      <div style="font-size:11px;font-weight:600;color:<?= $cor ?>">
        <?= abs($f) < 0.01 ? '✅' : ($f > 0 ? '−' . fmt($f, 1) : '+' . fmt(-$f, 1)) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Filtros -->
<div class="card" style="padding:12px 18px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?php if ($mostrarTudo): ?><input type="hidden" name="tudo" value="1"><?php endif; ?>
    <div class="form-group" style="margin:0;min-width:160px">
      <label>Plano</label>
      <select name="plano">
        <option value="">Todos</option>
        <?php foreach ($planos as $p): ?>
          <option value="<?= esc($p['sigla']) ?>" <?= $filterPlano === $p['sigla'] ? 'selected' : '' ?>>
            <?= esc($p['sigla']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label>Semestre</label>
      <select name="sem">
        <option value="">Ambos</option>
        <option value="1S" <?= $filterSem === '1S' ? 'selected' : '' ?>>1S</option>
        <option value="2S" <?= $filterSem === '2S' ? 'selected' : '' ?>>2S</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filtrar</button>
    <a href="horas-em-falta.php" class="btn btn-secondary btn-sm" style="align-self:flex-end">✕</a>
  </form>
</div>

<!-- Tabela -->
<div class="card" style="padding:0">
<table class="data-table hf-table">
  <thead>
    <tr class="hf-h1">
      <th rowspan="2" style="text-align:left">Unidade Curricular</th>
      <th rowspan="2" style="width:48px;text-align:center">Sem</th>
      <th rowspan="2" style="width:36px;text-align:center" title="Nº docentes atribuídos">Doc</th>
      <?php foreach (['T'=>'T','TP'=>'TP','L'=>'L','Sem'=>'Sem','OT'=>'OT'] as $k => $label): ?>
        <th colspan="3" class="tipo-sep" style="text-align:center"><?= $label ?></th>
      <?php endforeach; ?>
      <th rowspan="2" class="tipo-sep" style="text-align:right;width:70px">Δ total</th>
      <th rowspan="2" style="width:75px"></th>
    </tr>
    <tr class="hf-h2">
      <?php for ($i = 0; $i < 5; $i++): ?>
        <th class="tipo-sep" style="text-align:right;width:50px">Nec</th>
        <th class="tipo-sep-light" style="text-align:right;width:50px">Atr</th>
        <th class="tipo-sep-light" style="text-align:right;width:55px">Δ</th>
      <?php endfor; ?>
    </tr>
  </thead>
  <tbody>
  <?php if (!$grupos): ?>
    <tr><td colspan="20" style="text-align:center;padding:40px;color:var(--green);font-weight:600">
      ✅ Sem horas em falta para os filtros selecionados!
    </td></tr>
  <?php else: foreach ($grupos as $planoKey => $g):
    $planoId = 'plano-' . md5($planoKey);
    $sub = $g['sub'];
    $subFalta = [];
    foreach (['T','TP','L','Sem','OT','total'] as $k) {
        $subFalta[$k] = $sub['need'][$k] - $sub['done'][$k];
    }
    $clsSubTot = abs($subFalta['total']) < 0.01 ? 'ok' : ($subFalta['total'] > 0 ? 'ko' : 'exc');
  ?>
  <!-- Cabeçalho do plano (clicável + subtotal sempre visível) -->
  <tr class="hf-plano-row" data-collapse-trigger="<?= $planoId ?>">
    <td colspan="3">
      <span class="collapse-icon">▾</span>
      <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;font-size:11px;margin-right:6px"><?= esc($planoKey) ?></span>
      <?= count($g['linhas']) ?> UC<?= count($g['linhas']) === 1 ? '' : 's' ?>
    </td>
    <?php foreach (['T','TP','L','Sem','OT'] as $k):
      $n = $sub['need'][$k]; $d = $sub['done'][$k]; $f = $subFalta[$k];
      $cls = abs($f) < 0.01 ? 'ok' : ($f > 0 ? 'ko' : 'exc');
      $hasData = ($n > 0 || $d > 0);
    ?>
      <td class="num nec tipo-sep"><?= $hasData ? fmt($n, 1) : '–' ?></td>
      <td class="num atr tipo-sep-light"><?= $hasData ? fmt($d, 1) : '' ?></td>
      <td class="num falta <?= $cls ?> tipo-sep-light"><?= $hasData ? (abs($f) < 0.01 ? '✓' : ($f > 0 ? '−' : '+') . fmt(abs($f), 1)) : '' ?></td>
    <?php endforeach; ?>
    <td class="num falta <?= $clsSubTot ?> tipo-sep" style="font-size:13px">
      <?= abs($subFalta['total']) < 0.01 ? '✅' : (($subFalta['total'] > 0 ? '−' : '+') . fmt(abs($subFalta['total']), 1)) ?>
    </td>
    <td></td>
  </tr>

  <?php foreach ($g['linhas'] as $L):
    $o = $L['o']; $need = $L['need']; $done = $L['done']; $falta = $L['falta'];
    $clsTot = abs($falta['total']) < 0.01 ? 'ok' : ($falta['total'] > 0 ? 'ko' : 'exc');
  ?>
  <tr data-collapse-group="<?= $planoId ?>">
    <td>
      <strong><?= esc($o['uc_nome']) ?></strong>
      <?php if ($o['outros_planos']): ?>
        <span style="font-size:10px;color:var(--orange);margin-left:4px">⚠️ <?= esc($o['outros_planos']) ?></span>
      <?php endif; ?>
    </td>
    <td style="text-align:center"><span class="badge badge-gray" style="font-size:10px"><?= esc($o['semestre']) ?></span></td>
    <td style="text-align:center"><?= (int)$o['n_dist'] ?></td>
    <?php foreach (['T','TP','L','Sem','OT'] as $k):
      $n = $need[$k]; $d = $done[$k]; $f = $falta[$k];
      $cls = abs($f) < 0.01 ? 'ok' : ($f > 0 ? 'ko' : 'exc');
      $hasData = ($n > 0 || $d > 0);
    ?>
      <td class="num nec tipo-sep"><?= $hasData ? fmt($n, 1) : '<span class="dash">–</span>' ?></td>
      <td class="num atr tipo-sep-light"><?= $hasData ? fmt($d, 1) : '' ?></td>
      <td class="num falta <?= $cls ?> tipo-sep-light"><?= $hasData ? (abs($f) < 0.01 ? '✓' : ($f > 0 ? '−' : '+') . fmt(abs($f), 1)) : '' ?></td>
    <?php endforeach; ?>
    <td class="num falta <?= $clsTot ?> tipo-sep" style="font-size:13px">
      <?= abs($falta['total']) < 0.01 ? '✅' : (($falta['total'] > 0 ? '−' : '+') . fmt(abs($falta['total']), 1)) ?>
    </td>
    <td style="text-align:center;white-space:nowrap;padding:4px">
      <a href="../pages/distribuicao.php?ocorrencia=<?= $o['id'] ?>" class="btn btn-primary btn-xs" title="Atribuir docente">➕</a>
      <a href="../pages/ocorrencia-form.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-xs" title="Editar ocorrência">✏️</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<p style="font-size:11px;color:var(--gray-500);margin-top:10px">
  <strong>Legenda:</strong>
  <strong>Nec</strong> = necessárias &middot;
  <strong>Atr</strong> = atribuídas &middot;
  <strong>Δ</strong> = Nec − Atr (h/sem). <strong>−</strong> = falta atribuir, <strong>+</strong> = excesso.
  <span style="color:var(--green);font-weight:600">Verde</span> = ok,
  <span style="color:var(--red);font-weight:600">vermelho</span> = falta atribuir,
  <span style="color:var(--blue);font-weight:600">azul</span> = excesso.
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>