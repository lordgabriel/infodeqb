<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Tabela 1 – DSD';
$activePage = 'tabelas';
$db = getDB();
$al = getAnoLetivoAtivo();

$rows = $db->prepare("
    SELECT
        car.designacao AS carreira, car.ordem AS ord_car,
        cat.designacao AS categoria,
        doc.id AS doc_id, doc.nome AS docente,
        da.h_slef AS ref_slef,
        uc.semestre, pe.sigla AS plano,
        uc.designacao AS uc_nome, o.outros_planos,
        d.regente, d.dsd_por_docente,
        d.semanas,
        d.turmas_T,  d.horas_T,
        d.turmas_TP, d.horas_TP,
        d.turmas_L,  d.horas_L,
        d.turmas_Sem, d.horas_Sem,
        d.turmas_OT, d.horas_OT,
        d.h_tese, d.observacoes AS obs,
        o.f_slef,
        (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
         + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas / 13 AS hs,
        CASE WHEN d.dsd_por_docente = 1 THEN
          (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
           + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef
        ELSE 0 END AS h_slef_uc
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_uc uc ON o.uc_id = uc.id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(o.plano_id, uc.plano_id) = pe.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id = cat.id
    WHERE d.ano_letivo_id = ?
    ORDER BY car.ordem, doc.nome, uc.semestre, pe.ordem, uc.designacao
");
$rows->execute([$al['id'], $al['id']]);
$data = $rows->fetchAll();

$tree = [];
foreach ($data as $r) {
    $c = $r['carreira'] ?: '(outros)';
    $d = $r['doc_id'];
    if (!isset($tree[$c])) $tree[$c] = ['ord'=>$r['ord_car'],'docs'=>[],'tot_slef'=>0];
    if (!isset($tree[$c]['docs'][$d])) {
        $tree[$c]['docs'][$d] = ['nome'=>$r['docente'],'categoria'=>$r['categoria'],
                                  'ref_slef'=>$r['ref_slef'],'rows'=>[],'tot_slef'=>0,'tot_tese'=>0];
    }
    $tree[$c]['docs'][$d]['rows'][] = $r;
    $tree[$c]['docs'][$d]['tot_slef'] += (float)$r['h_slef_uc'];
    $tree[$c]['docs'][$d]['tot_tese'] += (float)$r['h_tese'];
    $tree[$c]['tot_slef'] += (float)$r['h_slef_uc'];
}
uasort($tree, function($a,$b) { return $a['ord'] <=> $b['ord']; });

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-table me-1"></i>Tabela 1 – Distribuição de Serviço Docente</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('carr-')">⊟ Carreiras</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('doc-');expandAll('carr-')">↕ Docentes</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('sem-');expandAll('doc-')">↕ Semestres</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('')">⊞ Tudo</button>
    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir / PDF</button>
  </div>
</div>

<div class="card">
<div class="table-wrap">
<table class="data-table" id="tbl-tab1" style="font-size:12px">
  <thead>
    <tr>
      <th rowspan="2">Plano</th>
      <th rowspan="2">Unidade Curricular</th>
      <th rowspan="2" style="text-align:center">R</th>
      <th rowspan="2" style="text-align:center">DSD</th>
      <th rowspan="2" style="text-align:right">Sem.</th>
      <th colspan="2" style="text-align:center;border-left:1px solid var(--gray-300)">Teóricas</th>
      <th colspan="2" style="text-align:center;border-left:1px solid var(--gray-300)">Teórico-Práticas</th>
      <th colspan="2" style="text-align:center;border-left:1px solid var(--gray-300)">Laboratoriais</th>
      <th colspan="2" style="text-align:center;border-left:1px solid var(--gray-300)">Orient. Tutorial</th>
      <th style="text-align:right;border-left:1px solid var(--gray-300)">h/s</th>
      <th style="text-align:right">F SLEf</th>
      <th style="text-align:right">H SLEf</th>
    </tr>
    <tr>
      <th style="text-align:right;border-left:1px solid var(--gray-300)">T</th><th style="text-align:right">h</th>
      <th style="text-align:right;border-left:1px solid var(--gray-300)">T</th><th style="text-align:right">h</th>
      <th style="text-align:right;border-left:1px solid var(--gray-300)">T</th><th style="text-align:right">h</th>
      <th style="text-align:right;border-left:1px solid var(--gray-300)">T</th><th style="text-align:right">h</th>
      <th style="border-left:1px solid var(--gray-300)"></th><th></th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php
  $grandSlef = 0;
  foreach ($tree as $carreira => $carrData):
    $carrKey = 'carr-'.md5($carreira);
    $grandSlef += $carrData['tot_slef'];
  ?>
  <!-- CARREIRA -->
  <tr style="background:var(--rpt-hdr)" data-collapse-trigger="<?= $carrKey ?>">
    <td colspan="13" style="color:#fff;font-weight:700;font-size:13px;padding:10px 14px">
      <span class="collapse-icon">▾</span> <?= esc($carreira) ?>
    </td>
    <td colspan="3" style="text-align:right;color:#fff;font-weight:700;font-size:13px">
      <?= fmt($carrData['tot_slef'],2) ?> h SLEf
    </td>
  </tr>

  <?php foreach ($carrData['docs'] as $docId => $docData):
    $docKey = 'doc-'.$docId;
    $bySem = [];
    foreach ($docData['rows'] as $r2) $bySem[$r2['semestre'] ?: 'A'][] = $r2;
  ?>
  <!-- DOCENTE -->
  <tr style="background:#f1f5f9" data-collapse-group="<?= $carrKey ?>" data-collapse-trigger="<?= $docKey ?>">
    <td colspan="13" style="font-weight:700;padding:8px 14px">
      <span class="collapse-icon">▾</span> <?= esc($docData['nome']) ?>
      <span style="font-size:11px;font-weight:400;color:var(--gray-500);margin-left:8px"><?= esc($docData['categoria'] ?? '') ?></span>
      <span class="collapse-summary" style="display:none;font-size:11px;color:var(--blue);margin-left:12px">
        <?= fmt($docData['tot_slef'],2) ?> h SLEf
      </span>
    </td>
    <td colspan="2" style="text-align:right;color:var(--gray-500);font-size:11px">
      Ref: <?= $docData['ref_slef'] ? fmt((float)$docData['ref_slef'],1) : '–' ?>
    </td>
    <td class="num" style="font-weight:700;color:var(--blue)"><?= fmt($docData['tot_slef'],2) ?></td>
  </tr>

  <?php foreach (['1S','2S','A'] as $sem):
    if (empty($bySem[$sem])) continue;
    $semKey = 'sem-'.$docId.'-'.$sem;
    $semSlef = 0; $semHs = 0;
    $semLabel = $sem === '1S' ? '1º Semestre' : ($sem === '2S' ? '2º Semestre' : 'Anual');
  ?>
  <!-- SEMESTRE -->
  <tr style="background:#e0eaff" data-collapse-group="<?= $carrKey ?> <?= $docKey ?>" data-collapse-trigger="<?= $semKey ?>">
    <td colspan="16" style="padding-left:28px;font-size:11px;font-weight:600;color:var(--iq-blue-dark)">
      <span class="collapse-icon">▾</span> <?= $semLabel ?>
      <span class="collapse-summary" style="display:none;color:var(--blue);margin-left:8px"></span>
    </td>
  </tr>

  <?php foreach ($bySem[$sem] as $r):
    $semSlef += (float)$r['h_slef_uc'];
    $semHs   += (float)$r['hs'];
  ?>
  <tr data-collapse-group="<?= $carrKey ?> <?= $docKey ?> <?= $semKey ?>">
    <td><span class="badge badge-blue" style="font-size:10px"><?= esc($r['plano'] ?? '–') ?></span></td>
    <td>
      <?= $r['regente'] ? '<strong>' : '' ?><?= esc($r['uc_nome']) ?><?= $r['regente'] ? '<sup style="color:var(--blue)"> R</sup></strong>' : '' ?>
      <?php if ($r['outros_planos']): ?><div style="font-size:10px;color:var(--orange)"><i class="fas fa-exclamation-triangle me-1"></i><?= esc($r['outros_planos']) ?></div><?php endif; ?>
    </td>
    <td style="text-align:center"><?= $r['regente'] ? '<i class="fas fa-star"></i>' : '' ?></td>
    <td style="text-align:center"><?= $r['dsd_por_docente'] ? '<i class="fas fa-check-circle"></i>' : '<span style="color:var(--orange)"><i class="fas fa-times-circle"></i></span>' ?></td>
    <td class="num"><?= fmt((float)$r['semanas'],2) ?></td>
    <td class="num" style="border-left:1px solid var(--gray-200)"><?= $r['turmas_T']  > 0 ? fmt((float)$r['turmas_T'],1)  : '' ?></td>
    <td class="num"><?= $r['horas_T']  > 0 ? fmt((float)$r['horas_T'],1)  : '' ?></td>
    <td class="num" style="border-left:1px solid var(--gray-200)"><?= $r['turmas_TP'] > 0 ? fmt((float)$r['turmas_TP'],1) : '' ?></td>
    <td class="num"><?= $r['horas_TP'] > 0 ? fmt((float)$r['horas_TP'],1) : '' ?></td>
    <td class="num" style="border-left:1px solid var(--gray-200)"><?= $r['turmas_L']  > 0 ? fmt((float)$r['turmas_L'],1)  : '' ?></td>
    <td class="num"><?= $r['horas_L']  > 0 ? fmt((float)$r['horas_L'],1)  : '' ?></td>
    <td class="num" style="border-left:1px solid var(--gray-200)"><?= $r['turmas_OT'] > 0 ? fmt((float)$r['turmas_OT'],1) : '' ?></td>
    <td class="num"><?= $r['horas_OT'] > 0 ? fmt((float)$r['horas_OT'],1) : '' ?></td>
    <td class="num" style="border-left:1px solid var(--gray-200);font-weight:600"><?= fmt((float)$r['hs'],2) ?></td>
    <td class="num" style="color:var(--gray-400)"><?= fmt((float)$r['f_slef'],2) ?></td>
    <td class="num" style="color:<?= $r['dsd_por_docente'] ? 'var(--blue)' : 'var(--gray-400)' ?>;font-weight:600">
      <?= fmt((float)$r['h_slef_uc'],2) ?>
    </td>
  </tr>
  <?php endforeach; ?>

  <!-- Subtotal semestre -->
  <tr style="background:var(--iq-blue-light)" data-collapse-group="<?= $carrKey ?> <?= $docKey ?>">
    <td colspan="13" style="text-align:right;font-size:11px;font-weight:600;color:var(--iq-blue-dark);padding-right:8px">
      Subtotal <?= $semLabel ?>
    </td>
    <td class="num" style="font-weight:600"><?= fmt($semHs,2) ?></td>
    <td></td>
    <td class="num" style="color:var(--iq-blue-dark);font-weight:700"><?= fmt($semSlef,2) ?></td>
  </tr>
  <?php endforeach; ?>

  <!-- Total docente -->
  <tr style="background:#e2e8f0;font-weight:700" data-collapse-group="<?= $carrKey ?>">
    <td colspan="13" style="text-align:right;padding-right:8px;color:var(--gray-700)">
      ↳ Total <?= esc($docData['nome']) ?>
    </td>
    <td class="num" style="color:var(--gray-500)"><?= $docData['ref_slef'] ? fmt((float)$docData['ref_slef'],1) : '' ?></td>
    <td></td>
    <td class="num" style="color:var(--blue);font-size:13px"><?= fmt($docData['tot_slef'],2) ?></td>
  </tr>
  <?php endforeach; ?>

  <!-- Total carreira -->
  <tr style="background:var(--rpt-sub)">
    <td colspan="15" style="text-align:right;color:#fff;font-weight:700;padding:7px 14px">
      Total <?= esc($carreira) ?>
    </td>
    <td class="num" style="color:#fff;font-size:13px;font-weight:700"><?= fmt($carrData['tot_slef'],2) ?></td>
  </tr>

  <?php endforeach; ?>
  </tbody>
  <tfoot>
  <tr style="background:var(--rpt-hdr)">
    <td colspan="15" style="text-align:right;color:#fff;font-weight:700;padding:10px 14px">
      TOTAL GERAL H efectivas
    </td>
    <td class="num" style="color:#fff;font-size:15px;font-weight:700"><?= fmt($grandSlef,2) ?></td>
  </tr>
  </tfoot>
</table>
</div>
</div>

<div style="font-size:12px;color:var(--gray-400);margin-top:8px;padding:0 4px">
  <strong>Legenda:</strong> R = Regente &nbsp;|&nbsp; DSD <i class="fas fa-check-circle me-1"></i>= conta nas horas do docente &nbsp;|&nbsp;
  DSD <i class="fas fa-times-circle me-1"></i>= UC partilhada, não duplica &nbsp;|&nbsp; F SLEf = fator SLEf &nbsp;|&nbsp; H SLEf = h/semana × F SLEf
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
