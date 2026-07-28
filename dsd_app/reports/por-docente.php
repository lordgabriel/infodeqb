<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
// Relatório público — acessível sem autenticação quando ACCESS_PUBLIC_REPORTS=true

$pageTitle  = 'Relatório por Docente';
$activePage = 'rel-docente';
$db = getDB();
$al = getAnoLetivoAtivo();

$filterDocente  = $_GET['docente']  ?? '';
$filterSem      = $_GET['sem']      ?? '';
$filterCarreira = $_GET['carreira'] ?? '';

$carreiras = $db->query("SELECT * FROM infodeqb_dsd_carreira ORDER BY ordem")->fetchAll();

$docentesStmt = $db->prepare("
    SELECT d.id, d.nome, c.designacao AS carreira
    FROM infodeqb_dsd_docente d
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=d.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira c ON da.carreira_id = c.id
    WHERE da.ativo = 1
    ORDER BY c.ordem, d.nome
");
$docentesStmt->execute([$al['id']]);
$docentesList = $docentesStmt->fetchAll();

$params = [$al['id']];
$wDoc  = $filterDocente  ? 'AND doc.id = ?'         : ''; if ($filterDocente)  $params[] = (int)$filterDocente;
$wSem  = $filterSem      ? 'AND uc.semestre = ?'    : ''; if ($filterSem)      $params[] = $filterSem;
$wCarr = $filterCarreira ? 'AND car.designacao = ?' : ''; if ($filterCarreira) $params[] = $filterCarreira;

$rows = $db->prepare("
    SELECT d.id AS dist_id, d.ocorrencia_id, d.docente_id, d.dsd_por_docente, d.rotulo,
           doc.id AS doc_id, doc.nome AS docente,
           da.h_slef AS h_slef,
           da.ref_ecdu AS doc_ecdu,
           car.designacao AS carreira, cat.designacao AS categoria,
           uc.semestre, pe.sigla AS plano, uc.designacao AS uc_nome,
           o.outros_planos, o.f_slef,
           d.regente,
           d.semanas,
           d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
           d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem,
           d.turmas_OT, d.horas_OT,
           d.turmas_OT * d.horas_OT * d.semanas / 13 AS h_ot_norm,
           d.h_tese, d.observacoes AS dist_obs,
           (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
            + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas / 13 AS hs,
           (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
            + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas AS ht,
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
    WHERE d.ano_letivo_id = ? AND d.dsd_por_docente = 1 $wDoc $wSem $wCarr
    ORDER BY car.ordem, doc.nome, uc.semestre, pe.ordem, uc.designacao
");
$params = array_merge([$al['id']], $params);
$rows->execute($params);
$data = $rows->fetchAll();

$byDocente = [];
foreach ($data as $r) {
    $k = $r['doc_id'];
    if (!isset($byDocente[$k])) {
        $byDocente[$k] = ['info'=>$r, 'ucs'=>[],
            'tot_hs'=>0, 'tot_ht'=>0, 'tot_slef'=>0, 'tot_OT'=>0, 'tot_tese'=>0];
    }
    $byDocente[$k]['ucs'][]      = $r;
    $byDocente[$k]['tot_hs']    += (float)$r['hs'];
    $byDocente[$k]['tot_ht']    += (float)$r['ht'];
    $byDocente[$k]['tot_slef']  += (float)$r['h_slef_uc'];
    $byDocente[$k]['tot_OT']    += (float)$r['h_ot_norm'];
    $byDocente[$k]['tot_tese']  += (float)$r['h_tese'];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-file-alt me-1"></i>Relatório por Docente</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('doc-')">⊟ Colapsar docentes</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('doc-')">⊞ Expandir docentes</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('sem-')">⊟ Colapsar semestres</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('sem-')">⊞ Expandir semestres</button>
    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button>
    <a href="por-ciclo.php" class="btn btn-secondary">Por Ciclo →</a>
  </div>
</div>

<div class="card" style="padding:12px 18px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;min-width:180px">
      <label>Carreira</label>
      <select name="carreira">
        <option value="">Todas</option>
        <?php foreach ($carreiras as $cr): ?>
          <option value="<?= esc($cr['designacao']) ?>" <?= $filterCarreira === $cr['designacao'] ? 'selected' : '' ?>>
            <?= esc($cr['designacao']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:2;min-width:220px">
      <label>Docente</label>
      <select name="docente">
        <option value="">Todos</option>
        <?php foreach ($docentesList as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $filterDocente == $d['id'] ? 'selected' : '' ?>><?= esc($d['nome']) ?></option>
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
    <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fas fa-search"></i></button>
    <a href="por-docente.php" class="btn btn-secondary btn-sm" style="align-self:flex-end"><i class="fas fa-times"></i></a>
  </form>
</div>

<?php foreach ($byDocente as $dk => $bloco):
    $info     = $bloco['info'];
    $totEquiv = $bloco['tot_hs'] + $bloco['tot_tese'];
    $pct      = $info['h_slef'] > 0 ? min(150, ($bloco['tot_slef'] / $info['h_slef']) * 100) : null;
    $pcls     = $pct === null ? 'progress-warn' : ($pct >= 100 ? 'progress-over' : ($pct >= 80 ? 'progress-ok' : 'progress-warn'));
?>
<div class="card" style="page-break-inside:avoid;padding:14px 20px">
  <div data-collapse-trigger="doc-<?= $dk ?>" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:10px;cursor:pointer">
    <div>
      <div style="font-size:16px;font-weight:700;color:var(--gray-900)">
        <span class="collapse-icon">▾</span> <?= esc($info['docente']) ?>
      </div>
      <div style="margin-top:4px">
        <span class="badge badge-blue"><?= esc($info['carreira']) ?></span>
        <?php if ($info['categoria']): ?><span class="badge badge-gray"><?= esc($info['categoria']) ?></span><?php endif; ?>
        <?php if (!empty($info['depto'])): ?><span class="badge badge-gray"><?= esc($info['depto']) ?></span><?php endif; ?>
      </div>
      <div class="collapse-summary" style="display:none;margin-top:6px;font-size:12px;color:var(--gray-600)">
        SLEf: <strong style="color:var(--blue)"><?= fmt($bloco['tot_slef'], 2) ?></strong>
        &nbsp;|&nbsp; Equiv: <strong style="color:var(--blue-dark)"><?= fmt($totEquiv, 2) ?></strong>
        &nbsp;|&nbsp; Ref: <?= fmt((float)$info['h_slef'], 2) ?>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:12px;color:var(--gray-500)">H SLEf / Ref</div>
      <div style="font-size:20px;font-weight:700;color:var(--blue)">
        <?= fmt($bloco['tot_slef'], 2) ?>
        <span style="font-size:14px;color:var(--gray-400)">/ <?= fmt((float)$info['h_slef'], 2) ?></span>
      </div>
      <?php if ($pct === null): ?>
        <div style="font-size:11px;color:var(--gray-400)">sem referência</div>
      <?php else: ?>
        <div class="progress-wrap" style="width:160px;margin-top:4px">
          <div class="progress-bar <?= $pcls ?>" style="width:<?= min(100, $pct) ?>%"></div>
        </div>
        <div style="font-size:11px;color:var(--gray-400)"><?= fmt($pct, 1) ?>%</div>
      <?php endif; ?>
    </div>
  </div>

  <div data-collapse-group="doc-<?= $dk ?>">
  <div class="table-wrap">
  <table class="data-table" style="font-size:12px">
    <thead>
      <tr>
        <th>Plano</th><th>Unidade Curricular</th>
        <th style="text-align:center">R</th>
        <th style="text-align:right">Sem.</th>
        <th style="text-align:right">T/h T</th>
        <th style="text-align:right">T/h TP</th>
        <th style="text-align:right">T/h L</th>
        <th style="text-align:right">T/h OT</th>
        <th style="text-align:right">h/s</th>
        <th style="text-align:right">H Total</th>
        <th style="text-align:right">H SLEf</th>
        <th style="text-align:right">H Tese</th>
        <th style="text-align:right">H Equiv.</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $semGroups = [];
    foreach ($bloco['ucs'] as $u) $semGroups[$u['semestre'] ?: 'A'][] = $u;

    // Pré-calcular subtotais por semestre
    $semTotais = [];
    foreach ($semGroups as $sem => $ucsSem) {
        $sT = ['hs'=>0,'ht'=>0,'slef'=>0,'OT'=>0,'tese'=>0,'equiv'=>0];
        foreach ($ucsSem as $u) {
            $sT['hs']    += (float)$u['hs'];
            $sT['ht']    += (float)$u['ht'];
            $sT['slef']  += (float)$u['h_slef_uc'];
            $sT['OT']    += (float)$u['h_ot_norm'];
            $sT['tese']  += (float)$u['h_tese'];
            $sT['equiv'] += (float)$u['hs'] + (float)$u['h_tese'];
        }
        $semTotais[$sem] = $sT;
    }

    foreach (['1S','2S','A'] as $sem):
      if (empty($semGroups[$sem])) continue;
      $semId = "sem-{$dk}-{$sem}";
      $semLabel = $sem === '1S' ? '1º Semestre' : ($sem === '2S' ? '2º Semestre' : 'Anual');
      $sT = $semTotais[$sem];
    ?>
    <!-- Cabeçalho do semestre (clicável para colapsar) -->
    <tr style="background:var(--gray-100);cursor:pointer" data-collapse-trigger="<?= $semId ?>">
      <td colspan="13" style="font-weight:600;color:var(--gray-700);font-size:11px;padding:6px 12px">
        <span class="collapse-icon">▾</span> <?= $semLabel ?>
        <span class="collapse-summary" style="display:none;margin-left:12px;font-weight:400;color:var(--gray-500)">
          (<?= count($semGroups[$sem]) ?> UCs) &middot;
          h/s <strong><?= fmt($sT['hs'], 2) ?></strong> &middot;
          H Total <strong><?= fmt($sT['ht'], 2) ?></strong> &middot;
          SLEf <strong style="color:var(--blue)"><?= fmt($sT['slef'], 2) ?></strong>
          <?php if ($sT['tese'] > 0): ?>
            &middot; Tese <strong style="color:var(--orange)"><?= fmt($sT['tese'], 2) ?></strong>
          <?php endif; ?>
          &middot; Equiv <strong style="color:var(--blue-dark)"><?= fmt($sT['equiv'], 2) ?></strong>
        </span>
      </td>
    </tr>

    <?php foreach ($semGroups[$sem] as $u): ?>
    <tr data-collapse-group="<?= $semId ?>">
      <td><span class="badge badge-blue" style="font-size:10px"><?= esc($u['plano'] ?? '–') ?></span></td>
      <td>
        <?= $u['regente'] ? '<strong>' : '' ?><?= esc($u['uc_nome']) ?><?= $u['regente'] ? '<sup style="color:var(--blue)"> R</sup></strong>' : '' ?>
        <?php if (!empty($u['rotulo'])): ?>
          <span style="font-size:10px;color:var(--blue);margin-left:4px;background:var(--blue-light);padding:1px 5px;border-radius:3px">
            <?= esc($u['rotulo']) ?>
          </span>
        <?php endif; ?>
        <?php if ($u['outros_planos']): ?><div style="font-size:10px;color:var(--gray-400)"><?= esc($u['outros_planos']) ?></div><?php endif; ?>
      </td>
      <td style="text-align:center"><?= $u['regente'] ? '<i class="fas fa-star"></i>' : '' ?></td>
      <td class="num"><?= fmt((float)$u['semanas'], 2) ?></td>
      <td class="num"><?= $u['turmas_T']  > 0 ? fmt((float)$u['turmas_T'], 2).'/'.fmt((float)$u['horas_T'], 2)  : '–' ?></td>
      <td class="num"><?= $u['turmas_TP'] > 0 ? fmt((float)$u['turmas_TP'], 2).'/'.fmt((float)$u['horas_TP'], 2) : '–' ?></td>
      <td class="num"><?= $u['turmas_L']  > 0 ? fmt((float)$u['turmas_L'], 2).'/'.fmt((float)$u['horas_L'], 2)  : '–' ?></td>
      <td class="num"><?= $u['turmas_OT'] > 0 ? fmt((float)$u['turmas_OT'], 2).'/'.fmt((float)$u['horas_OT'], 2) : '–' ?></td>
      <td class="num"><strong><?= fmt((float)$u['hs'], 2) ?></strong></td>
      <td class="num"><?= fmt((float)$u['ht'], 2) ?></td>
      <td class="num" style="color:var(--blue);font-weight:600"><?= fmt((float)$u['h_slef_uc'], 2) ?></td>
      <td class="num" style="color:var(--orange)"><?= $u['h_tese'] > 0 ? fmt((float)$u['h_tese'], 2) : '–' ?></td>
      <td class="num" style="font-weight:600;color:var(--blue-dark)"><?= fmt((float)$u['hs'] + (float)$u['h_tese'], 2) ?></td>
    </tr>
    <?php endforeach; ?>

    <!-- Subtotal sempre visível -->
    <tr style="background:var(--blue-light);font-weight:600" data-collapse-subtotal="<?= $semId ?>">
      <td colspan="8" style="text-align:right;font-size:11px;color:var(--blue-dark)">
        Subtotal <?= $sem === '1S' ? '1S' : ($sem === '2S' ? '2S' : 'Anual') ?>
      </td>
      <td class="num" style="color:var(--blue-dark)"><?= fmt($sT['hs'], 2) ?></td>
      <td class="num" style="color:var(--blue-dark)"><?= fmt($sT['ht'], 2) ?></td>
      <td class="num" style="color:var(--blue);font-weight:700"><?= fmt($sT['slef'], 2) ?></td>
      <td class="num" style="color:var(--orange)"><?= $sT['tese'] > 0 ? fmt($sT['tese'], 2) : '–' ?></td>
      <td class="num" style="color:var(--blue-dark);font-weight:700"><?= fmt($sT['equiv'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
    <tr class="row-total">
      <td colspan="8" style="text-align:right;font-weight:700">TOTAL GERAL</td>
      <td class="num"><?= fmt($bloco['tot_hs'], 2) ?></td>
      <td class="num"><?= fmt($bloco['tot_ht'], 2) ?></td>
      <td class="num" style="color:var(--blue);font-size:14px;font-weight:700"><?= fmt($bloco['tot_slef'], 2) ?></td>
      <td class="num" style="color:var(--orange)"><?= $bloco['tot_tese'] > 0 ? fmt($bloco['tot_tese'], 2) : '–' ?></td>
      <td class="num" style="color:var(--blue-dark);font-size:14px;font-weight:700"><?= fmt($totEquiv, 2) ?></td>
    </tr>
    <?php if ($bloco['tot_OT'] > 0 || $bloco['tot_tese'] > 0): ?>
    <tr style="background:var(--gray-50);font-size:11px">
      <td colspan="10" style="text-align:right;color:var(--gray-500)">
        H equiv. = SLEf <?= fmt($bloco['tot_slef'], 2) ?> + OT <?= fmt($bloco['tot_OT'], 2) ?> + Tese <?= fmt($bloco['tot_tese'], 2) ?>
      </td>
      <td colspan="3"></td>
    </tr>
    <?php endif; ?>
    <tr>
      <td colspan="10" style="text-align:right;font-size:12px;color:var(--gray-500)">Ref. H SLEf</td>
      <td class="num" style="color:var(--gray-500)"><?= fmt((float)$info['h_slef'], 2) ?></td>
      <td colspan="2"></td>
    </tr>
    </tfoot>
  </table>
  </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($byDocente)): ?>
<div class="card" style="text-align:center;color:var(--gray-400);padding:40px">
  Sem dados para os filtros selecionados.
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>