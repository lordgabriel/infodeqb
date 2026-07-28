<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Por Carreira e Categoria';
$activePage = 'rel-carreira';
$db = getDB();
$al = getAnoLetivoAtivo();

$planos = $db->prepare("
    SELECT DISTINCT pe.id, pe.sigla, pe.ordem
    FROM infodeqb_dsd_plano_estudo pe
    JOIN infodeqb_dsd_uc uc ON uc.plano_id=pe.id
    JOIN infodeqb_dsd_uc_ocorrencia o ON o.uc_id=uc.id
    JOIN infodeqb_dsd_distribuicao d ON d.ocorrencia_id=o.id
    WHERE d.ano_letivo_id=?
    ORDER BY pe.ordem, pe.sigla
");
$planos->execute([$al['id']]);
$planos = $planos->fetchAll();

$rows = $db->prepare("
    SELECT car.designacao AS carreira, car.ordem AS ord_car,
           cat.designacao AS categoria, cat.ordem AS ord_cat,
           doc.id AS doc_id, doc.nome AS docente,
           pe.id AS plano_id,
           SUM(CASE WHEN d.dsd_por_docente=1 THEN
               (d.turmas_T*d.horas_T+d.turmas_TP*d.horas_TP+d.turmas_L*d.horas_L
                +d.turmas_Sem*d.horas_Sem)*d.semanas/13*o.f_slef ELSE 0 END) AS h_slef
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id=o.id
    JOIN infodeqb_dsd_uc uc ON o.uc_id=uc.id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON uc.plano_id=pe.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id=doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id=car.id
    LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id=cat.id
    WHERE d.ano_letivo_id=?
    GROUP BY car.id, cat.id, doc.id, pe.id
    ORDER BY car.ordem, cat.ordem, doc.nome
");
$rows->execute([$al['id'], $al['id']]);
$data = $rows->fetchAll();

// Build tree: car → cat → doc → plano
$tree = [];
foreach ($data as $r) {
    $car = $r['carreira'] ?: '(sem carreira)';
    $cat = $r['categoria'] ?: '(sem categoria)';
    $did = $r['doc_id'];
    $pid = $r['plano_id'];
    if (!isset($tree[$car])) $tree[$car] = ['ord'=>(int)$r['ord_car'],'cats'=>[],'total'=>0];
    if (!isset($tree[$car]['cats'][$cat])) $tree[$car]['cats'][$cat] = ['ord'=>(int)$r['ord_cat'],'docs'=>[],'total'=>0];
    if (!isset($tree[$car]['cats'][$cat]['docs'][$did]))
        $tree[$car]['cats'][$cat]['docs'][$did] = ['nome'=>$r['docente'],'planos'=>[],'total'=>0];
    $tree[$car]['cats'][$cat]['docs'][$did]['planos'][$pid] = ($tree[$car]['cats'][$cat]['docs'][$did]['planos'][$pid]??0) + (float)$r['h_slef'];
    $tree[$car]['cats'][$cat]['docs'][$did]['total'] += (float)$r['h_slef'];
    $tree[$car]['cats'][$cat]['total'] += (float)$r['h_slef'];
    $tree[$car]['total'] += (float)$r['h_slef'];
}
uasort($tree, function($a,$b) { return $a['ord']<=>$b['ord']; });

$nP = count($planos);
$totCols = 1 + $nP + 1; // categoria/docente + planos + total

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-chart-bar me-1"></i>Por Carreira e Categoria</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?> — H SLEf</div>
  </div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('carr-')">⊟ Carreiras</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('cat-');expandAll('carr-')">↕ Categorias</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('')">⊞ Tudo</button>
    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button>
  </div>
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table class="data-table" style="font-size:12px">
  <thead>
    <tr>
      <th>Carreira / Categoria / Docente</th>
      <?php foreach ($planos as $p): ?>
      <th style="text-align:right;border-left:1px solid var(--gray-200)"><?= esc($p['sigla']) ?></th>
      <?php endforeach; ?>
      <th style="text-align:right;border-left:2px solid var(--gray-300)">Total SLEf</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $grandTotal = 0;
  $grandPlanos = array_fill_keys(array_column($planos,'id'), 0);
  foreach ($tree as $carreira => $carrData):
    $carrKey = 'carr-'.md5($carreira);
    $carrPlanos = array_fill_keys(array_column($planos,'id'), 0);
    foreach ($carrData['cats'] as $cat) foreach ($cat['docs'] as $doc)
        foreach ($doc['planos'] as $pid => $h) $carrPlanos[$pid] = ($carrPlanos[$pid]??0)+$h;
    $grandTotal += $carrData['total'];
    foreach ($carrPlanos as $pid => $h) $grandPlanos[$pid] = ($grandPlanos[$pid]??0)+$h;
  ?>
  <!-- CARREIRA -->
  <tr style="background:var(--rpt-hdr)" data-collapse-trigger="<?= $carrKey ?>">
    <td style="color:#fff;font-weight:700;padding:8px 14px">
      <span class="collapse-icon">▾</span> <?= esc($carreira) ?>
      <span class="collapse-summary" style="display:none;font-size:11px;opacity:.8;margin-left:10px"><?= fmt($carrData['total'],2) ?> h SLEf</span>
    </td>
    <?php foreach ($planos as $p): ?>
    <td class="num" style="color:#fff;border-left:1px solid rgba(255,255,255,.2)">
      <?= $carrPlanos[$p['id']] > 0.005 ? fmt($carrPlanos[$p['id']],2) : '' ?>
    </td>
    <?php endforeach; ?>
    <td class="num" style="color:#fff;font-weight:700;border-left:2px solid rgba(255,255,255,.3)"><?= fmt($carrData['total'],2) ?></td>
  </tr>

  <?php foreach ($carrData['cats'] as $categoria => $catData):
    $catKey = 'cat-'.md5($carreira.$categoria);
    $catPlanos = array_fill_keys(array_column($planos,'id'), 0);
    foreach ($catData['docs'] as $doc) foreach ($doc['planos'] as $pid => $h) $catPlanos[$pid] = ($catPlanos[$pid]??0)+$h;
  ?>
  <!-- CATEGORIA -->
  <tr style="background:#e0eaff" data-collapse-group="<?= $carrKey ?>" data-collapse-trigger="<?= $catKey ?>">
    <td style="padding-left:16px;font-weight:600;color:var(--iq-blue-dark)">
      <span class="collapse-icon">▾</span> <?= esc($categoria) ?>
      <span class="collapse-summary" style="display:none;font-size:11px;color:var(--blue);margin-left:8px"><?= fmt($catData['total'],2) ?> h</span>
    </td>
    <?php foreach ($planos as $p): ?>
    <td class="num" style="color:var(--iq-blue-dark);border-left:1px solid var(--gray-200)">
      <?= $catPlanos[$p['id']] > 0.005 ? fmt($catPlanos[$p['id']],2) : '' ?>
    </td>
    <?php endforeach; ?>
    <td class="num" style="color:var(--iq-blue-dark);font-weight:700;border-left:2px solid var(--gray-300)"><?= fmt($catData['total'],2) ?></td>
  </tr>

  <?php foreach ($catData['docs'] as $did => $doc): ?>
  <!-- DOCENTE -->
  <tr data-collapse-group="<?= $carrKey ?> <?= $catKey ?>">
    <td style="padding-left:30px;font-size:12px"><?= esc($doc['nome']) ?></td>
    <?php foreach ($planos as $p): ?>
    <td class="num" style="border-left:1px solid var(--gray-100)">
      <?= isset($doc['planos'][$p['id']]) && $doc['planos'][$p['id']] > 0.005 ? fmt($doc['planos'][$p['id']],2) : '' ?>
    </td>
    <?php endforeach; ?>
    <td class="num" style="color:var(--blue);border-left:2px solid var(--gray-200)"><?= fmt($doc['total'],2) ?></td>
  </tr>
  <?php endforeach; ?>

  <!-- Subtotal categoria -->
  <tr style="background:var(--iq-blue-light);font-weight:600" data-collapse-group="<?= $carrKey ?>">
    <td style="padding-left:16px;color:var(--iq-blue-dark);font-size:11px">Total <?= esc($categoria) ?></td>
    <?php foreach ($planos as $p): ?>
    <td class="num" style="color:var(--iq-blue-dark);border-left:1px solid var(--gray-200)">
      <?= $catPlanos[$p['id']] > 0.005 ? fmt($catPlanos[$p['id']],2) : '' ?>
    </td>
    <?php endforeach; ?>
    <td class="num" style="color:var(--iq-blue-dark);font-weight:700;border-left:2px solid var(--gray-300)"><?= fmt($catData['total'],2) ?></td>
  </tr>

  <?php endforeach; ?>

  <!-- Total carreira -->
  <tr style="background:var(--rpt-sub)">
    <td style="color:#fff;font-weight:700;padding:6px 14px">Total <?= esc($carreira) ?></td>
    <?php foreach ($planos as $p): ?>
    <td class="num" style="color:#fff;font-weight:700;border-left:1px solid rgba(255,255,255,.2)">
      <?= $carrPlanos[$p['id']] > 0.005 ? fmt($carrPlanos[$p['id']],2) : '' ?>
    </td>
    <?php endforeach; ?>
    <td class="num" style="color:#fff;font-weight:700;border-left:2px solid rgba(255,255,255,.3)"><?= fmt($carrData['total'],2) ?></td>
  </tr>
  <tr style="height:4px"><td colspan="<?= $totCols ?>"></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
  <tr style="background:var(--rpt-hdr)">
    <td style="color:#fff;font-weight:700;padding:8px 14px">TOTAL GERAL</td>
    <?php foreach ($planos as $p): ?>
    <td class="num" style="color:#fff;font-weight:700;border-left:1px solid rgba(255,255,255,.2)">
      <?= $grandPlanos[$p['id']] > 0.005 ? fmt($grandPlanos[$p['id']],2) : '' ?>
    </td>
    <?php endforeach; ?>
    <td class="num" style="color:#fff;font-weight:700;font-size:13px;border-left:2px solid rgba(255,255,255,.3)"><?= fmt($grandTotal,2) ?></td>
  </tr>
  </tfoot>
</table>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
