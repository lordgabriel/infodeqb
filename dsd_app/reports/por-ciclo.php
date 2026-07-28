<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
// Relatório público — acessível sem autenticação quando ACCESS_PUBLIC_REPORTS=true

$pageTitle  = 'Relatório por Ciclo de Estudos';
$activePage = 'rel-ciclo';
$db = getDB();
$al = getAnoLetivoAtivo();

$filterPlano = $_GET['plano'] ?? '';
$filterSem   = $_GET['sem']   ?? '';
$filterAno   = $_GET['ano']   ?? '';

$params = [$al['id']];
$wPlano = ''; if ($filterPlano) { $wPlano = 'AND pe.sigla = ?';    $params[] = $filterPlano; }
$wSem   = ''; if ($filterSem)   { $wSem   = 'AND uc.semestre = ?'; $params[] = $filterSem; }
$wAno   = ''; if ($filterAno)   { $wAno   = 'AND uc.ano = ?';      $params[] = $filterAno; }

$rows = $db->prepare("
    SELECT
        pe.sigla AS plano, pe.ordem AS plano_ordem,
        o.id AS ocor_id, uc.id AS uc_id, uc.designacao AS uc_nome, uc.semestre,
        uc.ano AS uc_ano,
        o.outros_planos, uc.tipo,
        o.f_slef, o.estudantes,
        o.n_turmas_T, o.n_turmas_TP, o.n_turmas_L, o.n_turmas_Sem, o.n_turmas_OT,
        o.horas_T  AS oc_horas_T,  o.horas_TP AS oc_horas_TP,
        o.horas_L  AS oc_horas_L,  o.horas_Sem AS oc_horas_Sem, o.horas_OT AS oc_horas_OT,
        doc.nome AS docente, d.regente, d.dsd_por_docente, d.rotulo,
        car.designacao AS carreira, car.ordem AS ord_car,
        d.semanas,
        d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
        d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem,
        d.turmas_OT, d.horas_OT, d.h_tese, d.observacoes AS dist_obs,
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
    WHERE d.ano_letivo_id = ? $wPlano $wSem $wAno
    ORDER BY pe.ordem, uc.ano, uc.semestre, uc.designacao, doc.nome
");
$params = array_merge([$al['id']], $params);
$rows->execute($params);
$data = $rows->fetchAll();

// Estrutura: plano → ano → semestre → uc → docentes
$tree = [];
foreach ($data as $r) {
    $p   = $r['plano'] ?? '–';
    $ano = $r['uc_ano'] ?: '(sem ano)';
    $sem = $r['semestre'] ?: 'A';
    $u   = $r['uc_id'];

    if (!isset($tree[$p])) {
        $tree[$p] = ['ordem' => $r['plano_ordem'], 'anos' => [],
                     'tot_ht' => 0, 'tot_slef' => 0, 'tot_tese' => 0, 'n_ucs' => 0];
    }
    if (!isset($tree[$p]['anos'][$ano])) {
        $tree[$p]['anos'][$ano] = ['sems' => [],
                                   'tot_ht' => 0, 'tot_slef' => 0, 'tot_tese' => 0, 'n_ucs' => 0];
    }
    if (!isset($tree[$p]['anos'][$ano]['sems'][$sem])) {
        $tree[$p]['anos'][$ano]['sems'][$sem] = ['ucs' => [],
                                                  'tot_ht' => 0, 'tot_slef' => 0, 'tot_tese' => 0];
    }
    if (!isset($tree[$p]['anos'][$ano]['sems'][$sem]['ucs'][$u])) {
        $tree[$p]['anos'][$ano]['sems'][$sem]['ucs'][$u] = [
            'info' => $r, 'docentes' => [],
            'tot_ht' => 0, 'tot_slef' => 0, 'tot_tese' => 0
        ];
        $tree[$p]['n_ucs']++;
        $tree[$p]['anos'][$ano]['n_ucs']++;
    }

    $tree[$p]['anos'][$ano]['sems'][$sem]['ucs'][$u]['docentes'][]   = $r;
    $tree[$p]['anos'][$ano]['sems'][$sem]['ucs'][$u]['tot_ht']      += (float)$r['ht'];
    $tree[$p]['anos'][$ano]['sems'][$sem]['ucs'][$u]['tot_slef']    += (float)$r['h_slef_uc'];
    $tree[$p]['anos'][$ano]['sems'][$sem]['ucs'][$u]['tot_tese']    += (float)$r['h_tese'];

    $tree[$p]['anos'][$ano]['sems'][$sem]['tot_ht']   += (float)$r['ht'];
    $tree[$p]['anos'][$ano]['sems'][$sem]['tot_slef'] += (float)$r['h_slef_uc'];
    $tree[$p]['anos'][$ano]['sems'][$sem]['tot_tese'] += (float)$r['h_tese'];

    $tree[$p]['anos'][$ano]['tot_ht']   += (float)$r['ht'];
    $tree[$p]['anos'][$ano]['tot_slef'] += (float)$r['h_slef_uc'];
    $tree[$p]['anos'][$ano]['tot_tese'] += (float)$r['h_tese'];

    $tree[$p]['tot_ht']   += (float)$r['ht'];
    $tree[$p]['tot_slef'] += (float)$r['h_slef_uc'];
    $tree[$p]['tot_tese'] += (float)$r['h_tese'];
}
uasort($tree, function($a, $b) { return $a['ordem'] <=> $b['ordem']; });

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem")->fetchAll();

// Lista de anos curriculares distintos para o filtro
$anosFiltro = $db->query("SELECT DISTINCT ano FROM infodeqb_dsd_uc WHERE ano IS NOT NULL AND ano <> '' ORDER BY ano")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-graduation-cap me-1"></i>Relatório por Ciclo de Estudos</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('plano-')">⊟ Planos</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('plano-')">⊞ Planos</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('ano-')">⊟ Anos</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('ano-')">⊞ Anos</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('sem-')">⊟ Semestres</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('sem-')">⊞ Semestres</button>
    <button class="btn btn-secondary btn-sm" onclick="collapseAll('uc-')">⊟ UCs</button>
    <button class="btn btn-secondary btn-sm" onclick="expandAll('uc-')">⊞ UCs</button>
    <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir / PDF</button>
  </div>
</div>

<div class="card" style="padding:12px 18px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="margin:0;min-width:160px">
      <label>Plano de Estudos</label>
      <select name="plano">
        <option value="">Todos</option>
        <?php foreach ($planos as $p): ?>
          <option value="<?= esc($p['sigla']) ?>" <?= $filterPlano === $p['sigla'] ? 'selected' : '' ?>>
            <?= esc($p['sigla']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:120px">
      <label>Ano</label>
      <select name="ano">
        <option value="">Todos</option>
        <?php foreach ($anosFiltro as $a): ?>
          <option value="<?= esc($a) ?>" <?= $filterAno === $a ? 'selected' : '' ?>><?= esc($a) ?></option>
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
    <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fas fa-search me-1"></i>Filtrar</button>
    <a href="por-ciclo.php" class="btn btn-secondary" style="align-self:flex-end"><i class="fas fa-times"></i></a>
  </form>
</div>

<?php
function sumLabel(string $label, array $tot, bool $highlight = false): string {
    $color = $highlight ? 'color:var(--blue-dark)' : 'color:var(--gray-600)';
    $out = "<span style='font-size:11px;font-weight:400;$color;margin-left:12px'>$label · ";
    $out .= "H Total <strong>" . fmt($tot['tot_ht'], 1) . "</strong>";
    $out .= " &middot; SLEf <strong style='color:var(--blue)'>" . fmt($tot['tot_slef'], 2) . "</strong>";
    if (($tot['tot_tese'] ?? 0) > 0) {
        $out .= " &middot; Tese <strong style='color:var(--orange)'>" . fmt($tot['tot_tese'], 2) . "</strong>";
    }
    $out .= "</span>";
    return $out;
}

/**
 * Calcula necessárias / atribuídas / falta por tipologia (h/sem) para uma UC,
 * usando dados da ocorrência (vindos da query como n_turmas_* e oc_horas_*) e os docentes.
 */
function calcFaltaUC(array $info, array $docentes): array {
    $tipos = ['T','TP','L','Sem','OT'];
    $out = [];
    foreach ($tipos as $t) {
        $nec = (float)($info["n_turmas_$t"] ?? 0) * (float)($info["oc_horas_$t"] ?? 0);
        $atr = 0;
        foreach ($docentes as $d) {
            $atr += (float)($d["turmas_$t"] ?? 0)
                  * (float)($d["horas_$t"] ?? 0)
                  * (float)($d['semanas'] ?? 13) / 13;
        }
        $out[$t] = ['nec' => $nec, 'atr' => $atr, 'falta' => $nec - $atr];
    }
    return $out;
}

/**
 * Compõe o badge de estado das horas a mostrar no cabeçalho da UC.
 * Devolve string HTML vazia se a UC não tem turmas definidas nem atribuídas.
 */
function badgeFalta(array $f): string {
    $tipos = ['T','TP','L','Sem','OT'];
    $partes = [];
    $totalFalta = 0; $hasData = false;
    foreach ($tipos as $t) {
        $totalFalta += $f[$t]['falta'];
        if ($f[$t]['nec'] <= 0 && $f[$t]['atr'] <= 0) continue;
        $hasData = true;
        $delta = $f[$t]['falta'];
        if (abs($delta) < 0.01) {
            $partes[] = "<span style='color:var(--green);font-weight:600'>$t <i class='fas fa-check'></i></span>";
        } elseif ($delta > 0) {
            $partes[] = "<span style='color:var(--red);font-weight:600'>$t −" . fmt($delta, 1) . "</span>";
        } else {
            $partes[] = "<span style='color:var(--red);font-weight:600'>$t +" . fmt(-$delta, 1) . "</span>";
        }
    }
    if (!$hasData) return '';

    if (abs($totalFalta) < 0.01) {
        $resumo = "<span style='color:var(--green);font-weight:700'><i class='fas fa-check-circle me-1'></i>OK</span>";
    } elseif ($totalFalta > 0) {
        $resumo = "<span style='color:var(--red);font-weight:700'><i class='fas fa-exclamation-triangle me-1'></i>Falta " . fmt($totalFalta, 1) . " h/sem</span>";
    } else {
        $resumo = "<span style='color:var(--red);font-weight:700'><i class='fas fa-exclamation-triangle me-1'></i>Excesso " . fmt(-$totalFalta, 1) . " h/sem</span>";
    }
    return $resumo . " <span style='color:var(--gray-400);font-size:10px'>(" . implode(' · ', $partes) . ")</span>";
}

foreach ($tree as $planoSigla => $planoData):
    $planoId = 'plano-' . md5($planoSigla);
?>
<div class="card" style="page-break-inside:avoid;padding:14px 20px">

  <!-- Nível 1: Plano de estudos -->
  <div data-collapse-trigger="<?= $planoId ?>" style="cursor:pointer;border-bottom:2px solid var(--blue);padding-bottom:8px;margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div style="font-size:18px;font-weight:700;color:var(--blue-dark)">
        <span class="collapse-icon">▾</span>
        <span class="badge badge-blue" style="font-size:14px;vertical-align:middle"><?= esc($planoSigla) ?></span>
        Plano de Estudos
        <span class="collapse-summary" style="display:none">
          <?= sumLabel($planoData['n_ucs'] . ' UCs', $planoData, true) ?>
        </span>
      </div>
      <div style="font-size:12px;color:var(--gray-500)">
        <?= $planoData['n_ucs'] ?> UCs &middot;
        H Total: <strong><?= fmt($planoData['tot_ht'], 1) ?></strong> &middot;
        SLEf: <strong style="color:var(--blue)"><?= fmt($planoData['tot_slef'], 2) ?></strong>
      </div>
    </div>
  </div>

  <div data-collapse-group="<?= $planoId ?>">

  <?php
  // Ordenar anos: tenta numericamente
  $anosOrdenados = $planoData['anos'];
  uksort($anosOrdenados, function($a, $b) {
      $na = (int)preg_replace('/\D/', '', $a);
      $nb = (int)preg_replace('/\D/', '', $b);
      if ($na && $nb) return $na <=> $nb;
      return strcmp($a, $b);
  });

  foreach ($anosOrdenados as $anoLabel => $anoData):
    $anoId = 'ano-' . md5($planoSigla . '|' . $anoLabel);
  ?>
  <!-- Nível 2: Ano curricular -->
  <div style="margin-bottom:14px">
    <div data-collapse-trigger="<?= $anoId ?>"
         style="cursor:pointer;background:var(--gray-100);border-left:4px solid var(--blue);
                padding:8px 14px;border-radius:6px;display:flex;justify-content:space-between;align-items:center">
      <div style="font-weight:700;color:var(--gray-900);font-size:14px">
        <span class="collapse-icon">▾</span> <i class="fas fa-book me-1"></i><?= esc($anoLabel) ?>
        <span class="collapse-summary" style="display:none">
          <?= sumLabel($anoData['n_ucs'] . ' UCs', $anoData, true) ?>
        </span>
      </div>
      <div style="font-size:12px;color:var(--gray-600)">
        <?= $anoData['n_ucs'] ?> UCs &middot;
        SLEf: <strong style="color:var(--blue)"><?= fmt($anoData['tot_slef'], 2) ?></strong>
      </div>
    </div>

    <div data-collapse-group="<?= $anoId ?>">

    <?php foreach (['1S','2S','A'] as $sem):
      if (empty($anoData['sems'][$sem])) continue;
      $semData = $anoData['sems'][$sem];
      $semId = 'sem-' . md5($planoSigla . '|' . $anoLabel . '|' . $sem);
      $semLabel = $sem === 'A' ? 'Anual' : ($sem === '1S' ? '1º Semestre' : '2º Semestre');
    ?>
    <!-- Nível 3: Semestre -->
    <div style="margin:10px 0 0 16px">
      <div data-collapse-trigger="<?= $semId ?>"
           style="cursor:pointer;background:var(--blue-light);padding:6px 12px;border-radius:4px;
                  display:flex;justify-content:space-between;align-items:center">
        <div style="font-weight:600;color:var(--blue-dark);font-size:13px">
          <span class="collapse-icon">▾</span> <i class="fas fa-calendar-alt me-1"></i><?= $semLabel ?>
          <span class="collapse-summary" style="display:none">
            <?= sumLabel(count($semData['ucs']) . ' UCs', $semData, true) ?>
          </span>
        </div>
        <div style="font-size:11px;color:var(--blue-dark)">
          SLEf: <strong><?= fmt($semData['tot_slef'], 2) ?></strong>
        </div>
      </div>

      <div data-collapse-group="<?= $semId ?>">

      <?php foreach ($semData['ucs'] as $uid => $ucBloco):
        $info = $ucBloco['info'];
        $ucId = 'uc-' . $uid;
        $faltaUC = calcFaltaUC($info, $ucBloco['docentes']);
        $badge   = badgeFalta($faltaUC);
      ?>
      <!-- Nível 4: UC -->
      <div style="margin:8px 0 0 16px">
        <div data-collapse-trigger="<?= $ucId ?>"
             style="cursor:pointer;background:var(--gray-50);border:1px solid var(--gray-200);
                    border-radius:5px;padding:6px 12px">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:13px">
              <span class="collapse-icon">▾</span> <strong><?= esc($info['uc_nome']) ?></strong>
              <span class="badge badge-gray" style="margin-left:6px;font-size:10px"><?= esc($info['tipo']) ?></span>
              <?php if ($info['outros_planos']): ?>
                <span style="font-size:10px;color:var(--orange);margin-left:6px"><i class="fas fa-exclamation-triangle me-1"></i><?= esc($info['outros_planos']) ?></span>
              <?php endif; ?>
              <span class="collapse-summary" style="display:none;font-size:11px;color:var(--gray-500);margin-left:8px">
                · <?= count($ucBloco['docentes']) ?> docente(s) · SLEf <strong style="color:var(--blue)"><?= fmt($ucBloco['tot_slef'], 2) ?></strong>
              </span>
            </div>
            <div style="font-size:11px;color:var(--gray-500)">
              F SLEf: <?= fmt((float)$info['f_slef'], 2) ?> &middot;
              Alunos: <?= $info['estudantes'] > 0 ? (int)$info['estudantes'] : '—' ?> &middot;
              SLEf: <strong style="color:var(--blue)"><?= fmt($ucBloco['tot_slef'], 2) ?></strong>
            </div>
          </div>
          <?php if ($badge !== '' && !ACCESS_PUBLIC_REPORTS): ?>
          <div style="margin-top:4px;padding-top:4px;border-top:1px dashed var(--gray-200);font-size:11px">
            <?= $badge ?>
          </div>
          <?php endif; ?>
        </div>

        <div data-collapse-group="<?= $ucId ?>">
        <div class="table-wrap" style="margin-top:4px">
        <table class="data-table" style="font-size:11.5px">
          <thead>
            <tr>
              <th>Docente</th>
              <th>Carreira</th>
              <th style="text-align:center">R</th>
              <th style="text-align:center">DSD</th>
              <th style="text-align:right">Sem.</th>
              <th style="text-align:right">T.T</th><th style="text-align:right">h.T</th>
              <th style="text-align:right">T.TP</th><th style="text-align:right">h.TP</th>
              <th style="text-align:right">T.L</th><th style="text-align:right">h.L</th>
              <th style="text-align:right">T.OT</th><th style="text-align:right">h.OT</th>
              <th style="text-align:right">h/s</th>
              <th style="text-align:right">H Total</th>
              <th style="text-align:right">H SLEf</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($ucBloco['docentes'] as $d): ?>
          <tr>
            <td><strong><?= esc($d['docente']) ?></strong>
              <?php if (!empty($d['rotulo'])): ?>
                <span style="font-size:10px;color:var(--blue);margin-left:4px;background:var(--blue-light);padding:1px 5px;border-radius:3px"><?= esc($d['rotulo']) ?></span>
              <?php endif; ?>
              <div style="font-size:10px;color:var(--gray-400)"><?= esc($d['depto'] ?? '') ?></div>
            </td>
            <td style="font-size:11px"><?= esc($d['carreira']) ?></td>
            <td style="text-align:center"><?= $d['regente'] ? '<i class="fas fa-star"></i>' : '' ?></td>
            <td style="text-align:center"><?= $d['dsd_por_docente'] ? '<i class="fas fa-check-circle"></i>' : '<span style="color:var(--orange)"><i class="fas fa-times-circle"></i></span>' ?></td>
            <td class="num"><?= fmt((float)$d['semanas'], 2) ?></td>
            <td class="num"><?= fmt((float)$d['turmas_T'], 2) ?></td><td class="num"><?= fmt((float)$d['horas_T'], 2) ?></td>
            <td class="num"><?= fmt((float)$d['turmas_TP'], 2) ?></td><td class="num"><?= fmt((float)$d['horas_TP'], 2) ?></td>
            <td class="num"><?= fmt((float)$d['turmas_L'], 2) ?></td><td class="num"><?= fmt((float)$d['horas_L'], 2) ?></td>
            <td class="num"><?= fmt((float)$d['turmas_OT'], 2) ?></td><td class="num"><?= fmt((float)$d['horas_OT'], 2) ?></td>
            <td class="num"><strong><?= fmt((float)$d['hs'], 3) ?></strong></td>
            <td class="num"><?= fmt((float)$d['ht'], 2) ?></td>
            <td class="num" style="color:<?= $d['dsd_por_docente'] ? 'var(--blue)' : 'var(--gray-400)' ?>;font-weight:600">
              <?= fmt((float)$d['h_slef_uc'], 4) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
          <tr class="row-total">
            <td colspan="14" style="text-align:right">Total UC</td>
            <td class="num"><?= fmt($ucBloco['tot_ht'], 2) ?></td>
            <td class="num" style="color:var(--blue)"><?= fmt($ucBloco['tot_slef'], 2) ?></td>
          </tr>
          </tfoot>
        </table>
        </div>
        </div>
      </div>
      <?php endforeach; // UCs ?>

      </div>

      <!-- Subtotal Semestre (sempre visível) -->
      <div data-collapse-subtotal="<?= $semId ?>"
           style="background:var(--blue-light);border-radius:4px;padding:5px 14px;margin-top:6px;
                  text-align:right;font-size:11px;color:var(--blue-dark);font-weight:600">
        Subtotal <?= $semLabel ?> ·
        H Total <?= fmt($semData['tot_ht'], 1) ?> ·
        SLEf <strong><?= fmt($semData['tot_slef'], 2) ?></strong>
        <?php if ($semData['tot_tese'] > 0): ?>
          · Tese <strong style="color:var(--orange)"><?= fmt($semData['tot_tese'], 2) ?></strong>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; // semestres ?>

    </div>
    <!-- Subtotal Ano (sempre visível) -->
    <div data-collapse-subtotal="<?= $anoId ?>"
         style="background:var(--gray-100);border-left:4px solid var(--blue);border-radius:4px;
                padding:6px 14px;margin-top:8px;text-align:right;font-size:12px;color:var(--gray-800);font-weight:600">
      Subtotal <?= esc($anoLabel) ?> ·
      <?= $anoData['n_ucs'] ?> UCs ·
      H Total <?= fmt($anoData['tot_ht'], 1) ?> ·
      SLEf <strong style="color:var(--blue)"><?= fmt($anoData['tot_slef'], 2) ?></strong>
      <?php if ($anoData['tot_tese'] > 0): ?>
        · Tese <strong style="color:var(--orange)"><?= fmt($anoData['tot_tese'], 2) ?></strong>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; // anos ?>

  </div>
  <!-- Subtotal Plano (sempre visível) -->
  <div data-collapse-subtotal="<?= $planoId ?>"
       style="background:var(--blue);color:#fff;border-radius:6px;padding:8px 16px;
              margin-top:10px;text-align:right;font-size:13px;font-weight:700">
    Total <?= esc($planoSigla) ?> ·
    <?= $planoData['n_ucs'] ?> UCs ·
    H Total <?= fmt($planoData['tot_ht'], 1) ?> ·
    SLEf <?= fmt($planoData['tot_slef'], 2) ?>
    <?php if ($planoData['tot_tese'] > 0): ?>
      · Tese <?= fmt($planoData['tot_tese'], 2) ?>
    <?php endif; ?>
  </div>

</div>
<?php endforeach; // planos ?>

<?php if (empty($tree)): ?>
<div class="card" style="text-align:center;color:var(--gray-400);padding:40px">
  Sem dados de distribuição para os filtros selecionados.
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>