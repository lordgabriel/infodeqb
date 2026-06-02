<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Resumo de Horas';
$activePage = 'rel-resumo';
$db = getDB();
$al = getAnoLetivoAtivo();

$rows = $db->prepare("
    SELECT
        car.designacao AS carreira, car.ordem AS ord_car,
        cat.designacao AS categoria,
        doc.id, doc.nome,
        da.h_slef AS ref_slef,
        da.ref_ecdu AS ref_ecdu,
        SUM(CASE WHEN d.dsd_por_docente = 1 THEN
            (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
             + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef
            ELSE 0 END
        ) AS h_slef_total,
        SUM(d.h_tese) AS h_tese_total,
        SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
             + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas) AS h_totais,
        SUM(d.turmas_T*d.horas_T*d.semanas/13)   AS h_T,
        SUM(d.turmas_TP*d.horas_TP*d.semanas/13) AS h_TP,
        SUM(d.turmas_L*d.horas_L*d.semanas/13)   AS h_L,
        SUM(d.turmas_OT*d.horas_OT*d.semanas/13) AS h_OT,
        COUNT(DISTINCT d.ocorrencia_id) AS n_ucs
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id = cat.id
    WHERE d.ano_letivo_id = ?
    GROUP BY doc.id, doc.nome, da.h_slef, da.ref_ecdu,
             car.designacao, car.ordem, cat.designacao
    ORDER BY car.ordem, doc.nome
");
$rows->execute([$al['id'], $al['id']]);
$data = $rows->fetchAll();

$byCarreira = [];
foreach ($data as $r) {
    $byCarreira[$r['carreira'] ?? '(sem carreira)'][] = $r;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">📊 Resumo de Horas Totais por Docente</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?></div>
  </div>
  <button class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir / PDF</button>
</div>

<div class="card">
<div class="table-wrap">
<table class="data-table" id="tbl-resumo">
  <thead>
    <tr>
      <th>Docente</th>
      <th>Categoria</th>
      <th>Dept.</th>
      <th style="text-align:center">UCs</th>
      <th style="text-align:right">H T</th>
      <th style="text-align:right">H TP</th>
      <th style="text-align:right">H L</th>
      <th style="text-align:right">H OT</th>
      <th style="text-align:right">H Total</th>
      <th style="text-align:right">H Efetivas</th>
      <th style="text-align:right">H Equiv.</th>
      <th style="text-align:right">Ref. SLEf</th>
      <th style="text-align:right">Δ</th>
      <th>Ocupação</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $grandTot = ['ht'=>0, 'slef'=>0, 'tese'=>0];
  foreach ($byCarreira as $carreira => $docentes):
    $carrTot = ['ht'=>0, 'slef'=>0, 'tese'=>0];
  ?>
  <tr class="row-section">
    <td colspan="14"><?= esc($carreira) ?></td>
  </tr>
  <?php foreach ($docentes as $d):
    $slefEfet = (float)$d['h_slef_total'];
    $refSlef  = (float)$d['ref_slef'];
    $semRef   = $refSlef > 0;
    $delta    = $semRef ? $slefEfet - $refSlef : null;
    $pct      = $semRef ? min(150, ($slefEfet / $refSlef) * 100) : null;
    $pcls     = $pct === null ? 'progress-warn' : ($pct >= 100 ? 'progress-over' : ($pct >= 80 ? 'progress-ok' : 'progress-warn'));

    $carrTot['ht']    += (float)$d['h_totais'];
    $carrTot['slef']  += (float)$d['h_slef_total'];
    $carrTot['tese']  += (float)$d['h_tese_total'];
    $grandTot['ht']   += (float)$d['h_totais'];
    $grandTot['slef'] += (float)$d['h_slef_total'];
    $grandTot['tese'] += (float)$d['h_tese_total'];
  ?>
  <tr>
    <td><strong><?= esc($d['nome']) ?></strong></td>
    <td style="font-size:11px"><?= esc($d['categoria'] ?? '') ?></td>
    <td><span class="badge badge-gray"><?= esc($d['depto'] ?? '') ?></span></td>
    <td style="text-align:center"><?= (int)$d['n_ucs'] ?></td>
    <td class="num"><?= fmt((float)$d['h_T'], 1) ?></td>
    <td class="num"><?= fmt((float)$d['h_TP'], 1) ?></td>
    <td class="num"><?= fmt((float)$d['h_L'], 1) ?></td>
    <td class="num"><?= fmt((float)$d['h_OT'], 1) ?></td>
    <td class="num"><?= fmt((float)$d['h_totais'], 1) ?></td>
    <td class="num" style="color:var(--blue);font-weight:600"><?= fmt($slefEfet, 2) ?></td>
    <td class="num" style="color:var(--orange)"><?= $d['h_tese_total'] > 0 ? fmt((float)$d['h_tese_total'], 2) : '–' ?></td>
    <td class="num" style="color:var(--gray-500)"><?= fmt($refSlef, 1) ?></td>
    <td class="num" style="color:<?= $delta === null ? 'var(--gray-400)' : ($delta >= 0 ? 'var(--green)' : 'var(--red)') ?>;font-weight:600">
      <?= $delta === null ? 's/ref' : (($delta >= 0 ? '+' : '') . fmt($delta, 2)) ?>
    </td>
    <td style="min-width:120px">
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
  <tr style="background:var(--gray-100);font-weight:600">
    <td colspan="8" style="text-align:right;font-size:12px;color:var(--gray-600)">
      Subtotal <?= esc($carreira) ?>
    </td>
    <td class="num"><?= fmt($carrTot['ht'], 1) ?></td>
    <td class="num" style="color:var(--blue)"><?= fmt($carrTot['slef'], 2) ?></td>
    <td class="num"><?= $carrTot['tese'] > 0 ? fmt($carrTot['tese'], 2) : '–' ?></td>
    <td colspan="3"></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
  <tr class="row-total">
    <td colspan="8" style="text-align:right">TOTAL GERAL</td>
    <td class="num"><?= fmt($grandTot['ht'], 1) ?></td>
    <td class="num" style="color:var(--blue);font-size:15px"><?= fmt($grandTot['slef'], 2) ?></td>
    <td class="num"><?= $grandTot['tese'] > 0 ? fmt($grandTot['tese'], 2) : '–' ?></td>
    <td colspan="3"></td>
  </tr>
  </tfoot>
</table>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>