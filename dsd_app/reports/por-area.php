<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Por Área Científica';
$activePage = 'por-area';
$db = getDB();
$al = getAnoLetivoAtivo();

// Estratégia: usar área principal por defeito. Se "?todas=1", uma UC aparece em
// todas as áreas a que pertence (útil para análise; cuidado que h_slef "soma" várias vezes).
$todasAreas = isset($_GET['todas']);

$joinArea = $todasAreas
    ? "LEFT JOIN infodeqb_dsd_uc_area uac ON uac.uc_id = uc.id"
    : "LEFT JOIN infodeqb_dsd_uc_area uac ON uac.uc_id = uc.id AND uac.principal = 1";

$rows = $db->prepare("
    SELECT
        COALESCE(ac.sigla, '(sem área)') AS area,
        ac.designacao AS area_nome,
        doc.nome AS docente,
        car.designacao AS carreira, car.ordem AS ord_car,
        pe.sigla AS plano,
        uc.designacao AS uc_nome, uc.semestre,
        d.dsd_por_docente,
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
    $joinArea
    LEFT JOIN infodeqb_dsd_area_cientifica ac ON ac.id = uac.area_id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(o.plano_id, uc.plano_id) = pe.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    WHERE d.ano_letivo_id = ?
    ORDER BY area, car.ordem, doc.nome
");
$rows->execute([$al['id'], $al['id']]);
$data = $rows->fetchAll();

$byArea = [];
foreach ($data as $r) {
    $a = $r['area'];
    if (!isset($byArea[$a])) $byArea[$a] = ['rows' => [], 'tot' => 0, 'nome' => $r['area_nome']];
    $byArea[$a]['rows'][] = $r;
    $byArea[$a]['tot']   += (float)$r['h_slef_uc'];
}
ksort($byArea);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-flask me-1"></i>Relatório por Área Científica</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?>
      &mdash; <?= $todasAreas ? 'a contar UCs em <strong>todas</strong> as suas áreas' : 'a contar UCs só na área <strong>principal</strong>' ?>
    </div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="?<?= $todasAreas ? '' : 'todas=1' ?>" class="btn btn-secondary btn-sm">
      <?= $todasAreas ? 'Só área principal' : 'Todas as áreas' ?>
    </a>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('area-')">⊟ Colapsar</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('area-')">⊞ Expandir</button>
    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir / PDF</button>
  </div>
</div>

<!-- Summary cards -->
<div class="stats-grid">
<?php foreach ($byArea as $area => $aData): ?>
<div class="stat-card">
  <div class="stat-icon"><i class="fas fa-flask"></i></div>
  <div>
    <div class="stat-value" style="font-size:20px"><?= fmt($aData['tot'], 1) ?></div>
    <div class="stat-label">
      <strong><?= esc($area) ?></strong>
      <?php if ($aData['nome']): ?><span style="color:var(--gray-400)"> — <?= esc($aData['nome']) ?></span><?php endif; ?>
    </div>
    <div style="font-size:11px;color:var(--gray-400);margin-top:2px"><?= count($aData['rows']) ?> registos</div>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="card">
<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr>
      <th>Área</th>
      <th>Docente</th>
      <th>Carreira</th>
      <th>Plano</th>
      <th>UC</th>
      <th>Sem.</th>
      <th style="text-align:center">DSD</th>
      <th style="text-align:right">h/s</th>
      <th style="text-align:right">F SLEf</th>
      <th style="text-align:right">H SLEf</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($byArea as $area => $aData):
    $tot = 0;
    $key = md5($area);
  ?>
  <tr class="row-section" style="cursor:pointer" data-collapse-trigger="area-<?= $key ?>">
    <td colspan="10">
      <span class="collapse-icon">▾</span> <i class="fas fa-flask me-1"></i><strong><?= esc($area) ?></strong>
      <?php if ($aData['nome']): ?> — <span style="color:var(--gray-500);font-weight:400"><?= esc($aData['nome']) ?></span><?php endif; ?>
    </td>
  </tr>
  <?php foreach ($aData['rows'] as $r): $tot += $r['h_slef_uc']; ?>
  <tr data-collapse-group="area-<?= $key ?>">
    <td style="padding-left:20px;font-size:11px;color:var(--gray-400)"><?= esc($area) ?></td>
    <td><strong><?= esc($r['docente']) ?></strong></td>
    <td style="font-size:12px"><?= esc($r['carreira']) ?></td>
    <td><span class="badge badge-blue"><?= esc($r['plano'] ?? '–') ?></span></td>
    <td><?= esc($r['uc_nome']) ?></td>
    <td><span class="badge badge-gray"><?= esc($r['semestre']) ?></span></td>
    <td style="text-align:center"><?= $r['dsd_por_docente'] ? '<i class="fas fa-check-circle"></i>' : '<span style="color:var(--orange)"><i class="fas fa-times-circle"></i></span>' ?></td>
    <td class="num"><?= fmt((float)$r['hs'], 2) ?></td>
    <td class="num"><?= fmt((float)$r['f_slef'], 2) ?></td>
    <td class="num" style="color:<?= $r['dsd_por_docente'] ? 'var(--blue)' : 'var(--gray-400)' ?>;font-weight:600">
      <?= fmt((float)$r['h_slef_uc'], 2) ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <tr class="row-total" data-collapse-group="area-<?= $key ?>">
    <td colspan="9" style="text-align:right">Total <?= esc($area) ?></td>
    <td class="num" style="color:var(--blue);font-size:14px"><?= fmt($tot, 2) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$byArea): ?>
  <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--gray-400)">
    Sem dados de distribuição neste ano letivo.
  </td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>