<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Distribuição';
$activePage = 'distribuicao';
$db = getDB();
$al = getAnoLetivoAtivo();

// ── Apagar registo ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->prepare("DELETE FROM infodeqb_dsd_distribuicao WHERE id=?")->execute([(int)$_POST['delete_id']]);
    flash('Registo removido.');
    $scroll = (int)($_POST['scroll_y'] ?? 0);
    header('Location: distribuicao.php?scroll=' . $scroll); exit;
}

// ── Filtros ───────────────────────────────────────────────
$filterPlano   = $_GET['plano']   ?? '';
$filterDocente = $_GET['docente'] ?? '';
$filterSem     = $_GET['sem']     ?? '';
$filterCarrId  = (int)($_GET['carreira_id'] ?? 0) ?: null;

$where = ["d.ano_letivo_id = ?"];
$params = [$al['id']];
if ($filterPlano)   { $where[] = "pe.sigla = ?";      $params[] = $filterPlano; }
if ($filterDocente) { $where[] = "doc.id = ?";         $params[] = (int)$filterDocente; }
if ($filterCarrId)  { $where[] = "da.carreira_id = ?"; $params[] = $filterCarrId; }
if ($filterSem)     { $where[] = "uc.semestre = ?";    $params[] = $filterSem; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$rowsStmt = $db->prepare("
    SELECT d.*, o.id AS ocor_id, o.f_slef, o.outros_planos,
           uc.designacao AS uc_nome, uc.semestre,
           pe.sigla AS plano,
           doc.nome AS docente_nome, dep.sigla AS depto,
           car.designacao AS carreira,
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
    LEFT JOIN infodeqb_dsd_departamento dep ON doc.departamento_id = dep.id
    $whereSQL
    ORDER BY pe.ordem, uc.designacao, car.ordem, doc.nome, d.id
");
$rowsStmt->execute(array_merge([$al['id']], $params));
$rows = $rowsStmt->fetchAll();

$ocs = $db->prepare("
    SELECT o.id, o.uc_id, o.f_slef, o.outros_planos, o.semanas, o.estudantes,
           o.n_turmas_T, o.n_turmas_TP, o.n_turmas_L, o.n_turmas_Sem, o.n_turmas_OT,
           o.horas_T, o.horas_TP, o.horas_L, o.horas_Sem, o.horas_OT,
           uc.designacao, uc.semestre,
           pe.sigla AS plano_sigla
    FROM infodeqb_dsd_uc_ocorrencia o
    JOIN infodeqb_dsd_uc uc ON o.uc_id = uc.id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(o.plano_id, uc.plano_id) = pe.id
    WHERE o.ano_letivo_id = ?
    ORDER BY pe.ordem, uc.designacao
");
$ocs->execute([$al['id']]);
$ocorrencias = $ocs->fetchAll();

$docenteQ = $db->prepare("
    SELECT d.id, d.nome, c.designacao AS carreira, COALESCE(da2.carreira_id, 0) AS carreira_id
    FROM infodeqb_dsd_docente d
    LEFT JOIN infodeqb_dsd_docente_ano da2 ON da2.docente_id=d.id AND da2.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira c ON da2.carreira_id = c.id
    WHERE da2.ativo = 1
    ORDER BY c.ordem, d.nome
");
$docenteQ->execute([$al['id']]);
$docentes = $docenteQ->fetchAll();

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem")->fetchAll();

$carreiras = $db->query("SELECT * FROM infodeqb_dsd_carreira ORDER BY ordem")->fetchAll();

// Balance per ocorrencia (need vs done)
$balStmt = $db->prepare("
    SELECT o.id,
        (o.n_turmas_T*o.horas_T + o.n_turmas_TP*o.horas_TP + o.n_turmas_L*o.horas_L
         + o.n_turmas_Sem*o.horas_Sem + o.n_turmas_OT*o.horas_OT) * o.semanas / 13 AS need,
        COALESCE((SELECT SUM((d.turmas_T*d.horas_T+d.turmas_TP*d.horas_TP+d.turmas_L*d.horas_L
             +d.turmas_Sem*d.horas_Sem+d.turmas_OT*d.horas_OT)*d.semanas/13)
            FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id=o.id),0) AS done
    FROM infodeqb_dsd_uc_ocorrencia o WHERE o.ano_letivo_id=?
");
$balStmt->execute([$al['id']]);
$balData = [];
foreach ($balStmt->fetchAll() as $b) $balData[$b['id']] = $b;

// ── Controlo de horas por UC ──────────────────────────────────
// Formula partilhada com o resto do dsd_app: horasNecessarias()/horasAtribuidasFromRows()
// em includes/config.php. Aqui fica só a renderização do badge.
function badgeFalta(array $nec, array $atr): string {
    $partes=[]; $total=0; $has=false;
    foreach (['T','TP','L','Sem','OT'] as $t) {
        $n = $nec[$t] ?? 0; $a = $atr[$t] ?? 0; $falta = $n - $a;
        $total += $falta;
        if ($n<=0 && $a<=0) continue;
        $has=true;
        if (abs($falta)<0.01) $partes[]="<span style='color:var(--green)'>$t <i class='fas fa-check'></i></span>";
        elseif ($falta>0)     $partes[]="<span style='color:var(--red)'>$t −".fmt($falta,1)."</span>";
        else                  $partes[]="<span style='color:var(--red)'>$t +".fmt(-$falta,1)."</span>";
    }
    if (!$has) return '';
    if (abs($total)<0.01) $res="<span style='color:var(--green);font-weight:700'><i class='fas fa-check-circle me-1'></i>OK</span>";
    elseif ($total>0)     $res="<span style='color:var(--orange);font-weight:700'><i class='fas fa-exclamation-triangle me-1'></i>Falta ".fmt($total,1)."</span>";
    else                  $res="<span style='color:var(--red);font-weight:700'><i class='fas fa-exclamation-triangle me-1'></i>Excesso ".fmt(-$total,1)."</span>";
    return $res.' <span style="color:var(--gray-400);font-size:10px">('.implode(' · ',$partes).')</span>';
}


require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
 <div>
  <div class="page-title"><i class="fas fa-clipboard-list me-1"></i>Distribuição de Serviço Docente</div>
  <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?> &mdash; <?= count($rows) ?> registos</div>
 </div>
 <div style="display:flex;gap:10px">
  <a href="ocorrencias.php" class="btn btn-secondary"><i class="fas fa-calendar-alt me-1"></i>Ocorrências</a>
  <a href="../reports/por-docente.php" class="btn btn-secondary"><i class="fas fa-chart-bar me-1"></i>Relatório</a>
 </div>
</div>

<div class="card" style="padding:10px 16px;margin-bottom:12px">
 <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
  <div class="form-group" style="margin:0;min-width:130px">
   <label>Plano</label>
   <select name="plano">
    <option value="">Todos</option>
    <?php foreach ($planos as $p): ?>
     <option value="<?= esc($p['sigla']) ?>" <?= $filterPlano === $p['sigla'] ? 'selected' : '' ?>><?= esc($p['sigla']) ?></option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="form-group" style="margin:0;min-width:160px">
   <label>Carreira</label>
   <select name="carreira_id" id="flt-dist-carr" onchange="fltCascadeDocente()">
    <option value="">Todas</option>
    <?php foreach ($carreiras as $c): ?>
     <option value="<?= (int)$c['id'] ?>" <?= $filterCarrId === (int)$c['id'] ? 'selected' : '' ?>><?= esc($c['designacao']) ?></option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="form-group" style="margin:0;flex:2;min-width:180px">
   <label>Docente</label>
   <select name="docente" id="flt-dist-doc">
    <option value="">Todos</option>
    <?php foreach ($docentes as $d): ?>
     <option value="<?= $d['id'] ?>"
             data-carr="<?= (int)$d['carreira_id'] ?>"
             <?= $filterDocente == $d['id'] ? 'selected' : '' ?>>
      <?= esc($d['nome']) ?>
     </option>
    <?php endforeach; ?>
   </select>
  </div>
  <div class="form-group" style="margin:0">
   <label>Sem.</label>
   <select name="sem" style="width:80px">
    <option value="">Ambos</option>
    <option value="1S" <?= $filterSem === '1S' ? 'selected' : '' ?>>1S</option>
    <option value="2S" <?= $filterSem === '2S' ? 'selected' : '' ?>>2S</option>
   </select>
  </div>
  <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fas fa-search"></i></button>
  <a href="distribuicao.php" class="btn btn-secondary btn-sm" style="align-self:flex-end"><i class="fas fa-times"></i></a>
 </form>
 <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <input type="text" id="search-uc" placeholder="Pesquisar UC…" oninput="searchUC(this.value)"
         style="flex:1;max-width:400px">
  <label style="display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;
                background:var(--gray-100);border:1px solid var(--gray-300);border-radius:6px;padding:4px 10px">
    <input type="checkbox" id="filtro-sem-sd" onchange="searchUC(document.getElementById('search-uc').value)">
    <i class="fas fa-exclamation-triangle me-1"></i>Só UCs sem serviço
  </label>
  <span id="search-count" style="font-size:12px;color:var(--gray-500)"></span>
 </div>
</div>

<?php
// UCs with occurrences but NO distribution assigned
// Use all distribution IDs (unfiltered) to check
$allDistOcors = $db->prepare("SELECT DISTINCT ocorrencia_id FROM infodeqb_dsd_distribuicao WHERE ano_letivo_id=? AND ocorrencia_id IS NOT NULL");
$allDistOcors->execute([$al['id']]);
$ocorsWithSD = [];
foreach ($allDistOcors->fetchAll(PDO::FETCH_COLUMN) as $oid) {
    $ocorsWithSD[(int)$oid] = true;
}

$ucsSemSD = [];
foreach ($ocorrencias as $oc) {
    if ($filterPlano && ($oc['plano_sigla'] ?? '') !== $filterPlano) continue;
    if (!isset($ocorsWithSD[(int)$oc['id']])) $ucsSemSD[] = $oc;
}

// Group by plano
$semSdByPlano = [];
foreach ($ucsSemSD as $oc) {
    $p = $oc['plano_sigla'] ?? '–';
    $semSdByPlano[$p][] = $oc;
}
?>

<div style="display:flex;gap:12px;align-items:flex-start">

<?php if ($ucsSemSD): ?>
<!-- Treeview sem serviço (coluna esquerda) -->
<div style="width:240px;flex-shrink:0;position:sticky;top:70px">
  <div class="card" style="padding:10px 12px;border-left:4px solid var(--orange)">
    <div style="font-weight:600;font-size:12px;color:var(--orange);margin-bottom:8px">
      <i class="fas fa-exclamation-triangle me-1"></i>Sem serviço (<?= count($ucsSemSD) ?>)
    </div>
    <?php foreach ($semSdByPlano as $plano => $ucs): ?>
    <div style="margin-bottom:4px">
      <div onclick="togglePlanoSD('psd-<?= md5($plano) ?>')"
           style="display:flex;align-items:center;gap:5px;cursor:pointer;
                  padding:3px 6px;border-radius:4px;font-size:12px;font-weight:600;
                  background:var(--gray-100);color:var(--gray-700)">
        <span id="icon-psd-<?= md5($plano) ?>" style="font-size:10px">▶</span>
        <span class="badge badge-blue" style="font-size:10px"><?= esc($plano) ?></span>
        <span style="font-weight:400;color:var(--gray-500)">(<?= count($ucs) ?>)</span>
      </div>
      <div id="psd-<?= md5($plano) ?>" style="display:none;padding-left:10px;margin-top:2px">
        <?php foreach ($ucs as $oc): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:2px 4px;font-size:11px;border-bottom:1px solid var(--gray-100)">
          <a href="ocorrencia-form.php?id=<?= (int)$oc['id'] ?>#linhas-servico"
             style="color:var(--gray-700);text-decoration:none;
                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px"
             title="<?= esc($oc['designacao']) ?>">
            <?= esc($oc['designacao']) ?>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Tabela principal (coluna direita) -->
<div style="flex:1;min-width:0">

<div class="card" style="padding:0">
<div class="table-wrap">
<table class="data-table" id="tbl-dist" style="font-size:12px">
 <thead>
  <tr>
   <th>Plano / UC</th><th>Sem.</th><th>Docente</th>
   <th style="text-align:center">R</th><th style="text-align:center">DSD</th>
   <th style="text-align:right">Sem.</th>
   <th style="text-align:right">T/h.T</th><th style="text-align:right">T/h.TP</th>
   <th style="text-align:right">T/h.L</th><th style="text-align:right">T/h.Sem</th><th style="text-align:right">T/h.OT</th>
   <th style="text-align:right">H/s</th><th style="text-align:right">H SLEf</th><th style="text-align:right">H Tese</th>
   <th style="text-align:center;width:65px">Ações</th>
  </tr>
 </thead>
 <tbody>
 <?php $prevOcor = null; foreach ($rows as $r):
   $isNewUC = $r['ocor_id'] !== $prevOcor; $prevOcor = $r['ocor_id'];
   if ($isNewUC): ?>
 <tr class="row-section" data-uc-name="<?= esc(strtolower($r['uc_nome'])) ?>" data-has-sd="1">
  <td colspan="14" style="padding:5px 12px">
   <span class="badge badge-blue" style="font-size:10px"><?= esc($r['plano'] ?? '–') ?></span>
   &nbsp;<strong><a href="#" onclick="showUcInfo(<?= (int)$r['ocor_id'] ?>,event)" style="color:inherit;text-decoration:none;border-bottom:1px dotted var(--gray-400)"><?= esc($r['uc_nome']) ?></a></strong>
   &nbsp;<span class="badge badge-gray" style="font-size:10px"><?= esc($r['semestre']) ?></span>
<?php if ($r['outros_planos']): ?>
    &nbsp;<span style="font-size:10px;opacity:.7"><?= esc($r['outros_planos']) ?></span>
   <?php endif; ?>
   <span style="float:right;display:flex;align-items:center;gap:10px">
     <?php
     $ucDocs = array_filter($rows, function($x) use ($r) { return $x['ocor_id'] === $r['ocor_id']; });
     $ocRow = null;
     foreach ($ocorrencias as $oo) if ($oo['id']==$r['ocor_id']) { $ocRow = $oo; break; }
     $badge = $ocRow ? badgeFalta(horasNecessarias($ocRow), horasAtribuidasFromRows($ucDocs)) : '';
     ?>
     <?php if ($badge): ?><span style="font-size:11px"><?= $badge ?></span><?php endif; ?>
     <span style="font-weight:400;font-size:11px;opacity:.7">F SLEf: <?= fmt((float)$r['f_slef'], 2) ?></span>
     <a href="ocorrencia-form.php?id=<?= (int)$r['ocor_id'] ?>#linhas-servico" class="btn btn-primary btn-xs"
        style="padding:2px 8px;font-size:11px"><i class="fas fa-edit me-1"></i>Serviço</a>
   </span>
  </td>
 </tr>
 <?php endif; ?>
 <tr>
  <td></td>
  <td><span class="badge badge-gray" style="font-size:10px"><?= esc($r['semestre']) ?></span></td>
  <td>
   <strong><a href="#" onclick="showDocInfo(<?= (int)$r['docente_id'] ?>,event)" style="color:inherit;text-decoration:none;border-bottom:1px dotted var(--gray-400)"><?= esc($r['docente_nome']) ?></a></strong>
  </td>
  <td style="text-align:center"><?= $r['regente'] ? '<i class="fas fa-star"></i>' : '' ?></td>
  <td style="text-align:center"><?= $r['dsd_por_docente'] ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>' ?></td>
  <td class="num"><?= fmt((float)$r['semanas'], 2) ?></td>
  <td class="num"><?= $r['turmas_T']  ? fmt((float)$r['turmas_T'],1).'/'.fmt((float)$r['horas_T'],1)  : '–' ?></td>
  <td class="num"><?= $r['turmas_TP'] ? fmt((float)$r['turmas_TP'],1).'/'.fmt((float)$r['horas_TP'],1) : '–' ?></td>
  <td class="num"><?= $r['turmas_L']  ? fmt((float)$r['turmas_L'],1).'/'.fmt((float)$r['horas_L'],1)  : '–' ?></td>
  <td class="num"><?= $r['turmas_Sem'] ? fmt((float)$r['turmas_Sem'],1).'/'.fmt((float)$r['horas_Sem'],1) : '–' ?></td>
  <td class="num"><?= $r['turmas_OT'] ? fmt((float)$r['turmas_OT'],1).'/'.fmt((float)$r['horas_OT'],1) : '–' ?></td>
  <td class="num"><strong><?= fmt((float)$r['hs'], 2) ?></strong></td>
  <td class="num" style="color:<?= $r['dsd_por_docente'] ? 'var(--blue)' : 'var(--gray-400)' ?>;font-weight:600">
   <?= fmt((float)$r['h_slef_uc'], 2) ?>
  </td>
  <td class="num" style="color:var(--orange)"><?= $r['h_tese'] > 0 ? fmt((float)$r['h_tese'], 2) : '–' ?></td>
  <td style="text-align:center;white-space:nowrap;padding:4px 6px">
   <a href="ocorrencia-form.php?id=<?= (int)$r['ocor_id'] ?>#servico-<?= (int)$r['id'] ?>" class="btn btn-secondary btn-xs"><i class="fas fa-edit"></i></a>
   <form method="post" style="display:inline">
    <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
    <button type="submit" class="btn btn-danger btn-xs" data-confirm="Remover?"><i class="fas fa-trash"></i></button>
   </form>
  </td>
 </tr>
 <?php
 // Acumular totais por UC e globais
 $totUC_hs    = ($totUC_hs    ?? 0) + (float)$r['hs'];
 $totUC_slef  = ($totUC_slef  ?? 0) + (float)$r['h_slef_uc'];
 $totUC_tese  = ($totUC_tese  ?? 0) + (float)$r['h_tese'];
 $totAll_hs   = ($totAll_hs   ?? 0) + (float)$r['hs'];
 $totAll_slef = ($totAll_slef ?? 0) + (float)$r['h_slef_uc'];
 $totAll_tese = ($totAll_tese ?? 0) + (float)$r['h_tese'];

 // Detectar fim da UC: próxima linha é de outra UC ou é a última
 $nextR = $rows[array_search($r, $rows, true) + 1] ?? null;
 $isLastOfUC = !$nextR || $nextR['ocor_id'] !== $r['ocor_id'];
 if ($isLastOfUC && isset($totUC_hs)):
 ?>
 <tr style="background:var(--gray-50);font-size:12px;border-top:1px solid var(--gray-200)">
  <td colspan="11" style="text-align:right;padding:4px 8px;color:var(--gray-500)">Total UC:</td>
  <td class="num" style="font-weight:700"><?= fmt($totUC_hs, 2) ?></td>
  <td class="num" style="font-weight:700;color:var(--blue)"><?= fmt($totUC_slef, 2) ?></td>
  <td class="num" style="color:var(--orange)"><?= $totUC_tese > 0 ? fmt($totUC_tese,2) : '' ?></td>
  <td></td>
 </tr>
 <?php $totUC_hs = $totUC_slef = $totUC_tese = 0; endif; ?>
 <?php endforeach; ?>
 <?php if ($rows): ?>
 <tr style="background:var(--blue);color:#fff;font-size:13px">
  <td colspan="11" style="text-align:right;padding:6px 8px;font-weight:600">Total Geral:</td>
  <td class="num" style="font-weight:700;color:#fff"><?= fmt($totAll_hs ?? 0, 2) ?></td>
  <td class="num" style="font-weight:700;color:#fff"><?= fmt($totAll_slef ?? 0, 2) ?></td>
  <td class="num" style="color:#fff"><?= ($totAll_tese ?? 0) > 0 ? fmt($totAll_tese,2) : '' ?></td>
  <td></td>
 </tr>
 <?php endif; ?>
 <?php if (!$rows): ?>
 <tr><td colspan="14" style="text-align:center;color:var(--gray-400);padding:30px">Sem registos.</td></tr>
 <?php endif; ?>
 </tbody>
</table>
</div>
</div>

</div><!-- end main column -->
</div><!-- end flex layout -->


<!-- Painel de info flutuante (partilhado entre UC e docente) -->
<div id="panel-info" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:260px;max-width:400px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-info-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-info').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)"><i class="fas fa-times"></i></button>
  </div>
  <div id="panel-info-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-info-edit" href="#" class="btn btn-secondary btn-xs"><i class="fas fa-edit me-1"></i>Editar</a>
    <button onclick="document.getElementById('panel-info').style.display='none'"
            class="btn btn-secondary btn-xs">Fechar</button>
  </div>
</div>

<script>
const rowData  = <?= json_encode(array_column($rows, null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const ocorData  = <?= json_encode(array_column($ocorrencias, null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const docSnap     = <?= json_encode(array_column(
  array_map(function($d) { return ['id'=>$d['id'],'nome'=>$d['nome'],'carreira'=>$d['carreira']]; }, $docentes),
  null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const balData  = <?= json_encode($balData, JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const savedScroll = <?= (int)($_GET['scroll'] ?? 0) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/distribuicao.js?v=<?= filemtime(__DIR__ . '/../assets/js/distribuicao.js') ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
