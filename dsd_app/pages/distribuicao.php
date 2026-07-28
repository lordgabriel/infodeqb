<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Distribuição';
$activePage = 'distribuicao';
$db = getDB();
$al = getAnoLetivoAtivo();

// ── Apagar registo ────────────────────────────────────────
// ── Handle batch save from modal ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_json'])) {
    $batch      = json_decode($_POST['batch_json'], true) ?: [];
    $deleteIds  = json_decode($_POST['delete_ids'] ?? '[]', true) ?: [];
    $al = getAnoLetivoAtivo();
    $saved = 0;

    // Delete removed lines first
    foreach ($deleteIds as $did) {
        $did = (int)$did;
        if ($did > 0) $db->prepare("DELETE FROM infodeqb_dsd_distribuicao WHERE id=?")->execute([$did]);
    }
    foreach ($batch as $line) {
        $ocId  = (int)($line['ocorrencia_id'] ?? 0);
        $docId = (int)($line['docente_id'] ?? 0);
        if (!$ocId || !$docId) continue;
        $editId = (int)($line['edit_id'] ?? 0);

        $ucRow = $db->prepare("SELECT uc_id FROM infodeqb_dsd_uc_ocorrencia WHERE id=?")->execute([$ocId])
               ? $db->query("SELECT uc_id FROM infodeqb_dsd_uc_ocorrencia WHERE id=$ocId")->fetchColumn()
               : null;
        $ucIdRow = $db->prepare("SELECT uc_id FROM infodeqb_dsd_uc_ocorrencia WHERE id=?");
        $ucIdRow->execute([$ocId]);
        $ucId = $ucIdRow->fetchColumn();

        $fields = [
            'ano_letivo_id'   => $al['id'],
            'ocorrencia_id'   => $ocId,
            'uc_id'           => $ucId,
            'docente_id'      => $docId,
            'dsd_por_docente' => (int)($line['dsd'] ?? 1),
            'regente'         => (int)($line['reg'] ?? 0),
            'semanas'         => (float)($line['semanas'] ?? 13),
            'turmas_T'        => (float)($line['tT'] ?? 0),
            'horas_T'         => ((float)($line['tT']??0)>0 ? (float)($line['hT']??0) : 0),
            'turmas_TP'       => (float)($line['tTP'] ?? 0),
            'horas_TP'        => ((float)($line['tTP']??0)>0 ? (float)($line['hTP']??0) : 0),
            'turmas_L'        => (float)($line['tL'] ?? 0),
            'horas_L'         => ((float)($line['tL']??0)>0 ? (float)($line['hL']??0) : 0),
            'turmas_Sem'      => (float)($line['tSem'] ?? 0),
            'horas_Sem'       => ((float)($line['tSem']??0)>0 ? (float)($line['hSem']??0) : 0),
            'turmas_OT'       => (float)($line['tOT'] ?? 0),
            'horas_OT'        => ((float)($line['tOT']??0)>0 ? (float)($line['hOT']??0) : 0),
            'h_tese'          => (float)($line['htese'] ?? 0),
            'observacoes'     => $line['obs'] ?? null,
        ];

        if ($editId) {
            unset($fields['rotulo'], $fields['ano_letivo_id'], $fields['ocorrencia_id'], $fields['uc_id'], $fields['docente_id']);
            $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($fields)));
            $db->prepare("UPDATE infodeqb_dsd_distribuicao SET $sets WHERE id=?")
               ->execute([...array_values($fields), $editId]);
        } else {
            // Auto-rotulo
            $nQ = $db->prepare("SELECT COUNT(*) FROM infodeqb_dsd_distribuicao WHERE ocorrencia_id=? AND docente_id=?");
            $nQ->execute([$ocId, $docId]);
            $n = (int)$nQ->fetchColumn();
            if ($n > 0) $fields['rotulo'] = (string)($n + 1);
            $cols = implode(',', array_keys($fields));
            $phs  = implode(',', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO infodeqb_dsd_distribuicao ($cols) VALUES ($phs)")
               ->execute(array_values($fields));
        }
        $saved++;
    }
    flash("$saved linha(s) gravadas.");
    $scroll = (int)($_POST['scroll_y'] ?? 0);
    header('Location: distribuicao.php?scroll=' . $scroll); exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->prepare("DELETE FROM infodeqb_dsd_distribuicao WHERE id=?")->execute([(int)$_POST['delete_id']]);
    flash('Registo removido.');
    $scroll = (int)($_POST['scroll_y'] ?? 0);
    header('Location: distribuicao.php?scroll=' . $scroll); exit;
}

// ── Inserir / Editar ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ocorrencia_id'])) {
    $ocorrenciaId = (int)$_POST['ocorrencia_id'];
    $docId   = (int)$_POST['docente_id'];
    $rotulo  = trim($_POST['rotulo'] ?? '');
    $dsdPD   = isset($_POST['dsd_por_docente']) ? 1 : 0;
    $regente = isset($_POST['regente']) ? 1 : 0;
    $semanas = num($_POST['semanas'] ?? 13);
    $hTese   = num($_POST['h_tese'] ?? 0);
    $obs     = trim($_POST['observacoes'] ?? '');

    $numCols = ['turmas_T','horas_T','turmas_TP','horas_TP','turmas_L','horas_L',
                'turmas_Sem','horas_Sem','turmas_OT','horas_OT'];
    $vals = [];
    foreach ($numCols as $c) $vals[$c] = num($_POST[$c] ?? 0);

    if (!$ocorrenciaId || !$docId) {
        flash('Ocorrência e Docente são obrigatórios.', 'error');
    } else {
        $stmt = $db->prepare("SELECT uc_id FROM infodeqb_dsd_uc_ocorrencia WHERE id=?");
        $stmt->execute([$ocorrenciaId]);
        $ocor = $stmt->fetch();

        if (!$ocor) {
            flash('Ocorrência inválida.', 'error');
        } else {
            $editId = (int)($_POST['edit_id'] ?? 0);
            $fields = array_merge([
                'ano_letivo_id'   => $al['id'],
                'ocorrencia_id'   => $ocorrenciaId,
                'uc_id'           => $ocor['uc_id'],
                'docente_id'      => $docId,
                'rotulo'          => $rotulo ?: null,
                'dsd_por_docente' => $dsdPD,
                'regente'         => $regente,
                'semanas'         => $semanas,
            ], $vals, [
                'h_tese'      => $hTese,
                'observacoes' => $obs,
            ]);

            try {
                if ($editId) {
                    $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($fields)));
                    $db->prepare("UPDATE infodeqb_dsd_distribuicao SET $sets WHERE id=?")
                       ->execute([...array_values($fields), $editId]);
                    flash('Distribuição atualizada.');
                } else {
                    $cols = implode(',', array_keys($fields));
                    $phs  = implode(',', array_fill(0, count($fields), '?'));
                    $db->prepare("INSERT INTO infodeqb_dsd_distribuicao ($cols) VALUES ($phs)")
                       ->execute(array_values($fields));
                    flash('Distribuição adicionada.');
                }
            } catch (Exception $e) {
                flash('Erro: ' . $e->getMessage(), 'error');
            }
            header('Location: distribuicao.php'); exit;
        }
    }
}

// ── Filtros ───────────────────────────────────────────────
$filterPlano   = $_GET['plano']   ?? '';
$filterDocente = $_GET['docente'] ?? '';
$filterSem     = $_GET['sem']     ?? '';
$filterCarrId  = (int)($_GET['carreira_id'] ?? 0) ?: null;
$preOcor       = (int)($_GET['ocorrencia'] ?? 0);

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
function calcFaltaUC(array $info, array $docentes): array {
    foreach (['T','TP','L','Sem','OT'] as $t) {
        $nec = (float)($info["n_turmas_$t"]??0) * (float)($info["oc_horas_$t"]??0);
        $atr = 0;
        foreach ($docentes as $d)
            $atr += (float)($d["turmas_$t"]??0) * (float)($d["horas_$t"]??0) * (float)($d['semanas']??13) / 13;
        $out[$t] = ['nec'=>$nec,'atr'=>$atr,'falta'=>$nec-$atr];
    }
    return $out;
}
function badgeFalta(array $f): string {
    $partes=[]; $total=0; $has=false;
    foreach (['T','TP','L','Sem','OT'] as $t) {
        $total += $f[$t]['falta'];
        if ($f[$t]['nec']<=0 && $f[$t]['atr']<=0) continue;
        $has=true; $d=$f[$t]['falta'];
        if (abs($d)<0.01) $partes[]="<span style='color:var(--green)'>$t <i class='fas fa-check'></i></span>";
        elseif ($d>0)     $partes[]="<span style='color:var(--red)'>$t −".fmt($d,1)."</span>";
        else              $partes[]="<span style='color:var(--red)'>$t +".fmt(-$d,1)."</span>";
    }
    if (!$has) return '';
    if (abs($total)<0.01) $res="<span style='color:var(--green);font-weight:700'><i class='fas fa-check-circle me-1'></i>OK</span>";
    elseif ($total>0)     $res="<span style='color:var(--orange);font-weight:700'><i class='fas fa-exclamation-triangle me-1'></i>Falta ".fmt($total,1)."</span>";
    else                  $res="<span style='color:var(--red);font-weight:700'><i class='fas fa-exclamation-triangle me-1'></i>Excesso ".fmt(-$total,1)."</span>";
    return $res.' <span style="color:var(--gray-400);font-size:10px">('.implode(' · ',$partes).')</span>';
}

$editReg = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM infodeqb_dsd_distribuicao WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editReg = $stmt->fetch() ?: [];
}

// Pré-calcular para JS: necessárias e atribuídas em h/sem por ocorrência
$ocorJS = [];
foreach ($ocorrencias as $o) {
    $atribs = horasAtribuidas($db, (int)$o['id']);
    $ocorJS[$o['id']] = [
        'plano'    => $o['plano_sigla'],
        'fslef'    => (float)$o['f_slef'],
        'h'        => [
            'T'  => (float)$o['horas_T'],
            'TP' => (float)$o['horas_TP'],
            'L'  => (float)$o['horas_L'],
            'Sem'=> (float)$o['horas_Sem'],
            'OT' => (float)$o['horas_OT'],
        ],
        // Necessárias em h/sem: nº turmas × h/sem (ocorrência tem sempre 13 semanas)
        'need' => [
            'T'  => (float)$o['n_turmas_T']   * (float)$o['horas_T'],
            'TP' => (float)$o['n_turmas_TP']  * (float)$o['horas_TP'],
            'L'  => (float)$o['n_turmas_L']   * (float)$o['horas_L'],
            'Sem'=> (float)$o['n_turmas_Sem'] * (float)$o['horas_Sem'],
            'OT' => (float)$o['n_turmas_OT']  * (float)$o['horas_OT'],
        ],
        // Atribuídas em h/sem (já vêm normalizadas pela função horasAtribuidas)
        'done' => $atribs,
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;}
.modal-box{background:#fff;border-radius:12px;width:760px;max-width:96vw;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--gray-200);position:sticky;top:0;background:#fff;z-index:1;}
.modal-title{font-size:15px;font-weight:700;}
.modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--gray-400);line-height:1;padding:0 4px;}
.modal-body{padding:18px 20px;}
.tipo-grid{width:100%;border-collapse:collapse;font-size:12px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:10px;}
.tipo-grid th{background:var(--gray-50);padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);border-bottom:1px solid var(--gray-200);}
.tipo-grid td{padding:4px 8px;border-top:1px solid var(--gray-100);}
.tipo-grid input{width:65px;padding:3px 5px;border:1px solid var(--gray-300);border-radius:4px;text-align:right;font-size:12px;}
.tipo-grid .eq{background:var(--gray-50);}
.tipo-grid .meta{color:var(--gray-400);font-size:11px;text-align:right}
.tipo-grid .miss-ok{color:var(--green);font-weight:600}
.tipo-grid .miss-ko{color:var(--red);font-weight:600}
.tipo-grid .miss-exc{color:var(--orange);font-weight:600}
.calc-bar{background:var(--blue-light);border-radius:6px;padding:7px 12px;font-size:12px;margin-bottom:12px;}
</style>

<div id="modal-dist" class="modal-overlay" style="display:none">
 <div class="modal-box" style="max-width:860px;width:95vw">
  <div class="modal-header">
   <div id="modal-title" class="modal-title"><i class="fas fa-plus me-1"></i>Adicionar Serviço</div>
   <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body" style="padding:0">

  <!-- UC selector (hidden in edit mode) -->
  <div id="modal-uc-section" style="padding:14px 18px;border-bottom:1px solid var(--gray-200);background:var(--gray-50)">
   <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0;min-width:130px">
     <label>Plano</label>
     <select id="f-plano-filter" onchange="filterOcs()">
      <option value="">Todos</option>
      <?php foreach ($planos as $p): ?>
       <option value="<?= esc($p['sigla']) ?>"><?= esc($p['sigla']) ?></option>
      <?php endforeach; ?>
     </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px">
     <label>Unidade Curricular *</label>
     <select id="f-ocor" onchange="onOcorChange()">
      <option value="">— Selecionar —</option>
      <?php foreach ($ocorrencias as $o): ?>
       <option value="<?= $o['id'] ?>" data-plano="<?= esc($o['plano_sigla'] ?? '') ?>">
        [<?= esc($o['plano_sigla'] ?? '–') ?>] <?= esc($o['designacao']) ?> (<?= esc($o['semestre']) ?>)
       </option>
      <?php endforeach; ?>
     </select>
    </div>
   </div>
   <div id="oc-info" style="margin-top:8px;font-size:12px;color:var(--gray-500)"></div>
  </div>

  <!-- Line editor -->
  <div style="padding:14px 18px;border-bottom:1px solid var(--gray-200)">
   <div style="font-size:12px;font-weight:600;color:var(--gray-500);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
    <span id="line-mode-label">Nova linha</span>
   </div>
   <div style="display:grid;grid-template-columns:180px 1fr;gap:10px;align-items:end;margin-bottom:10px">
    <div class="form-group" style="margin:0">
     <label>Carreira</label>
     <select id="f-carreira" onchange="filterDocentes()">
      <option value="">Todas</option>
      <?php foreach ($carreiras as $car): ?>
       <option value="<?= esc($car['designacao']) ?>"><?= esc($car['designacao']) ?></option>
      <?php endforeach; ?>
     </select>
    </div>
    <div class="form-group" style="margin:0">
     <label>Docente *</label>
     <select id="f-doc" onchange="refreshLineCalc()">
      <option value="">— Selecionar —</option>
      <?php foreach ($docentes as $d): ?>
       <option value="<?= $d['id'] ?>" data-carreira="<?= esc($d['carreira'] ?? '') ?>">
        <?= esc($d['nome']) ?>
       </option>
      <?php endforeach; ?>
     </select>
    </div>
   </div>
   <!-- Row 1: semanas + DSD/R -->
   <div style="display:flex;gap:10px;align-items:end;margin-bottom:8px">
    <div class="form-group" style="margin:0;width:90px">
     <label style="font-size:11px">Semanas</label>
     <input type="number" id="f-semanas" step="0.5" min="0" max="30" value="13">
    </div>
    <div class="form-group" style="margin:0">
     <label>&nbsp;</label>
     <div style="display:flex;gap:10px">
      <label id="lbl-dsd" style="display:flex;align-items:center;gap:6px;cursor:pointer;
             background:var(--blue-light);border:2px solid var(--blue);border-radius:6px;
             padding:5px 12px;font-weight:600;font-size:13px;white-space:nowrap;transition:all .15s">
       <input type="checkbox" id="f-dsd" checked onchange="updateDsdStyle()"> <i class="fas fa-check-circle me-1"></i>Conta no DSD
      </label>
      <label id="lbl-reg" style="display:flex;align-items:center;gap:6px;cursor:pointer;
             background:var(--gray-100);border:2px solid var(--gray-300);border-radius:6px;
             padding:5px 12px;font-weight:600;font-size:13px;white-space:nowrap;transition:all .15s">
       <input type="checkbox" id="f-reg" onchange="updateRegStyle()"> <i class="fas fa-star me-1"></i>Regente (R)
      </label>
     </div>
    </div>
   </div>
   <!-- Row 2: turmas/horas per tipo -->
   <div style="display:grid;grid-template-columns:repeat(5,1fr) 80px;gap:8px;align-items:end">
    <?php foreach ([["T","T – Teóricas"],["TP","TP – T.Práticas"],["L","L – Laboratoriais"],["Sem","S – Seminários"],["OT","OT – Equiv."]] as [$k,$lbl]): ?>
    <div class="form-group" style="margin:0">
     <label style="font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= $lbl ?>"><?= $lbl ?></label>
     <div style="display:flex;gap:4px">
      <div style="flex:1;min-width:0">
       <div style="font-size:10px;color:var(--gray-400);text-align:center">Turmas</div>
       <input type="number" id="f-t<?= $k ?>" step="0.5" min="0" value="0" style="width:100%;text-align:right">
      </div>
      <div style="flex:1;min-width:0">
       <div style="font-size:10px;color:var(--gray-400);text-align:center">H/sem</div>
       <input type="number" id="f-h<?= $k ?>" step="0.5" min="0" value="0" style="width:100%;text-align:right">
      </div>
     </div>
    </div>
    <?php endforeach; ?>
    <div class="form-group" style="margin:0">
     <label style="font-size:11px">H Tese</label>
     <input type="number" id="f-htese" step="0.5" min="0" value="0">
    </div>
   </div>
   <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
    <button type="button" class="btn btn-primary btn-sm" onclick="addLine()" id="btn-add-line">+ Adicionar linha</button>
    <button type="button" class="btn btn-secondary btn-sm" id="btn-cancel-edit" style="display:none" onclick="cancelEditLine()">Cancelar edição</button>
    <div id="line-calc" style="font-size:12px;color:var(--blue);margin-left:8px"></div>
   </div>
  </div>

  <!-- Accumulated lines table -->
  <div style="padding:10px 18px;max-height:300px;overflow-y:auto">
   <table class="data-table" id="lines-table" style="margin:0;font-size:13px">
    <thead>
     <tr>
      <th>Docente</th>
      <th style="text-align:center">Sem.</th>
      <th style="text-align:center">T/H.T</th>
      <th style="text-align:center">T/H.TP</th>
      <th style="text-align:center">T/H.L</th>
      <th style="text-align:center">T/H.Sem</th>
      <th style="text-align:center">T/H.OT</th>
      <th style="text-align:center">DSD</th>
      <th style="text-align:center">R</th>
      <th></th>
     </tr>
    </thead>
    <tbody id="lines-body">
     <tr id="lines-empty"><td colspan="10" style="text-align:center;color:var(--gray-400);padding:16px">Sem linhas — adiciona acima</td></tr>
    </tbody>
   </table>
  </div>

  <!-- Footer -->
  <div style="padding:12px 18px;border-top:1px solid var(--gray-200);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
   <button type="button" class="btn btn-primary" onclick="saveAllLines()" id="btn-save-all"><i class="fas fa-save me-1"></i>Guardar tudo</button>
   <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button type="button" class="btn btn-secondary" onclick="openCopyPanel()" id="btn-copy-dist"
           style="margin-left:auto" title="Copiar esta distribuição para outras UCs partilhadas">
     <i class="fas fa-clipboard-list me-1"></i>Copiar para…
   </button>
   <span id="save-status" style="font-size:12px;color:var(--gray-500)"></span>
  </div>

  <!-- Painel de cópia -->
  <div id="copy-panel" style="display:none;padding:14px 18px;border-top:2px solid var(--blue);background:var(--blue-light)">
    <div style="font-weight:600;margin-bottom:10px;color:var(--blue-dark)"><i class="fas fa-clipboard-list me-1"></i>Copiar distribuição para UCs partilhadas</div>
    <p style="font-size:12px;color:var(--gray-600);margin-bottom:10px">
      Selecciona as UCs destino. A distribuição será copiada com <strong>DSD <i class="fas fa-times-circle"></i></strong> (não duplica horas)
      e substituirá toda a distribuição existente nessas UCs.
    </p>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      <select id="copy-plano-filter" onchange="filterCopyList()" style="min-width:120px;font-size:12px">
        <option value="">Todos os planos</option>
      </select>
      <input type="text" id="copy-search" oninput="filterCopyList()" placeholder="Pesquisar UC…" style="flex:1;font-size:12px">
    </div>
    <div id="copy-uc-list" style="display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto;margin-bottom:12px">
      <div style="color:var(--gray-400);font-size:12px">Selecciona primeiro uma UC acima.</div>
    </div>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn btn-primary btn-sm" onclick="executeCopy()"><i class="fas fa-clipboard-list me-1"></i>Copiar agora</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('copy-panel').style.display='none'">Cancelar</button>
    </div>
  </div>

  <!-- Hidden form for actual submission -->
  <form method="post" id="dist-form" style="display:none">
   <input type="hidden" name="batch_json" id="f-batch-json">
   <input type="hidden" name="delete_ids" id="f-delete-ids">
  </form>

  </div>
 </div>
</div>

<div class="page-header">
 <div>
  <div class="page-title"><i class="fas fa-clipboard-list me-1"></i>Distribuição de Serviço Docente</div>
  <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?> &mdash; <?= count($rows) ?> registos</div>
 </div>
 <div style="display:flex;gap:10px">
  <a href="ocorrencias.php" class="btn btn-secondary"><i class="fas fa-calendar-alt me-1"></i>Ocorrências</a>
  <a href="../reports/por-docente.php" class="btn btn-secondary"><i class="fas fa-chart-bar me-1"></i>Relatório</a>
  <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus me-1"></i>Adicionar</button>
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
          <a href="#" onclick="openModal(null,<?= (int)$oc['id'] ?>);return false;"
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
     $ocInfo = array_combine(['n_turmas_T','n_turmas_TP','n_turmas_L','n_turmas_Sem','n_turmas_OT',
                              'oc_horas_T','oc_horas_TP','oc_horas_L','oc_horas_Sem','oc_horas_OT'],
               array_fill(0,10,0));
     foreach ($ocorrencias as $oo) if ($oo['id']==$r['ocor_id']) {
         foreach(['T','TP','L','Sem','OT'] as $tt) {
             $ocInfo['n_turmas_'.$tt]=$oo['n_turmas_'.$tt];
             $ocInfo['oc_horas_'.$tt]=$oo['horas_'.$tt];
         }
     }
     $badge = badgeFalta(calcFaltaUC($ocInfo, $ucDocs));
     ?>
     <?php if ($badge): ?><span style="font-size:11px"><?= $badge ?></span><?php endif; ?>
     <span style="font-weight:400;font-size:11px;opacity:.7">F SLEf: <?= fmt((float)$r['f_slef'], 2) ?></span>
     <button type="button" class="btn btn-secondary btn-xs"
             onclick="editUC(<?= (int)$r['ocor_id'] ?>)"
             style="padding:2px 8px;font-size:11px"><i class="fas fa-edit me-1"></i>UC</button>
     <button type="button" class="btn btn-primary btn-xs"
             onclick="openModal(null, <?= (int)$r['ocor_id'] ?>)"
             style="padding:2px 8px;font-size:11px">+ Serviço</button>
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
   <button type="button" class="btn btn-secondary btn-xs" onclick="openModal(<?= (int)$r['id'] ?>)"><i class="fas fa-edit"></i></button>
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


<div id="panel-uc" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:280px;max-width:400px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-uc-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-uc').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)"><i class="fas fa-times"></i></button>
  </div>
  <div id="panel-uc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-uc-edit" href="#" class="btn btn-secondary btn-xs"><i class="fas fa-edit me-1"></i>Editar ocorrência</a>
    <button onclick="document.getElementById('panel-uc').style.display='none'"
            class="btn btn-secondary btn-xs">Fechar</button>
  </div>
</div>

<!-- Docente info panel -->
<div id="panel-doc" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:260px;max-width:360px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-doc-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-doc').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)"><i class="fas fa-times"></i></button>
  </div>
  <div id="panel-doc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-doc-edit" href="#" class="btn btn-secondary btn-xs"><i class="fas fa-edit me-1"></i>Editar docente</a>
    <button onclick="document.getElementById('panel-doc').style.display='none'"
            class="btn btn-secondary btn-xs">Fechar</button>
  </div>
</div>

<script>
const rowData  = <?= json_encode(array_column($rows, null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const ocorData  = <?= json_encode(array_column($ocorrencias, null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const docInfoData = <?= json_encode(array_column($docentes, null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const docSnap     = <?= json_encode(array_column(
  array_map(function($d) { return ['id'=>$d['id'],'nome'=>$d['nome'],'carreira'=>$d['carreira']]; }, $docentes),
  null, 'id'), JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const balData  = <?= json_encode($balData, JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const preOcor  = <?= json_encode($preOcor ?: null) ?>;
const savedScroll = <?= (int)($_GET['scroll'] ?? 0) ?>;
const distAnoLetivoId = <?= (int)$al['id'] ?>;
<?php
$distBootstrap = null;
if ($editReg) $distBootstrap = ['type' => 'edit', 'id' => (int)$editReg['id']];
elseif ($preOcor) $distBootstrap = ['type' => 'new', 'ocorId' => (int)$preOcor];
elseif (isset($_GET['edit_uc'])) $distBootstrap = ['type' => 'editUc', 'ocorId' => (int)$_GET['edit_uc']];
?>
const distBootstrap = <?= json_encode($distBootstrap) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/distribuicao.js?v=<?= filemtime(__DIR__ . '/../assets/js/distribuicao.js') ?>"></script>

<!-- UC info panel -->
<div id="panel-uc" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:280px;max-width:400px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-uc-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-uc').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)"><i class="fas fa-times"></i></button>
  </div>
  <div id="panel-uc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-uc-edit" href="#" class="btn btn-secondary btn-xs"><i class="fas fa-edit me-1"></i>Editar ocorrência</a>
    <button onclick="document.getElementById('panel-uc').style.display='none'"
            class="btn btn-secondary btn-xs">Fechar</button>
  </div>
</div>

<!-- Docente info panel -->
<div id="panel-doc" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:260px;max-width:360px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-doc-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-doc').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)"><i class="fas fa-times"></i></button>
  </div>
  <div id="panel-doc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px">
    <a id="panel-doc-edit" href="#" class="btn btn-secondary btn-xs"><i class="fas fa-edit me-1"></i>Editar docente</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
