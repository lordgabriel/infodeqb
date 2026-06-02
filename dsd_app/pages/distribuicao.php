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
        if (abs($d)<0.01) $partes[]="<span style='color:var(--green)'>$t ✓</span>";
        elseif ($d>0)     $partes[]="<span style='color:var(--red)'>$t −".fmt($d,1)."</span>";
        else              $partes[]="<span style='color:var(--red)'>$t +".fmt(-$d,1)."</span>";
    }
    if (!$has) return '';
    if (abs($total)<0.01) $res="<span style='color:var(--green);font-weight:700'>✅ OK</span>";
    elseif ($total>0)     $res="<span style='color:var(--orange);font-weight:700'>⚠️ Falta ".fmt($total,1)."</span>";
    else                  $res="<span style='color:var(--red);font-weight:700'>⚠️ Excesso ".fmt(-$total,1)."</span>";
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
   <div id="modal-title" class="modal-title">➕ Adicionar Serviço</div>
   <button class="modal-close" onclick="closeModal()">✕</button>
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
       <input type="checkbox" id="f-dsd" checked onchange="updateDsdStyle()"> ✅ Conta no DSD
      </label>
      <label id="lbl-reg" style="display:flex;align-items:center;gap:6px;cursor:pointer;
             background:var(--gray-100);border:2px solid var(--gray-300);border-radius:6px;
             padding:5px 12px;font-weight:600;font-size:13px;white-space:nowrap;transition:all .15s">
       <input type="checkbox" id="f-reg" onchange="updateRegStyle()"> ⭐ Regente (R)
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
   <button type="button" class="btn btn-primary" onclick="saveAllLines()" id="btn-save-all">💾 Guardar tudo</button>
   <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   <button type="button" class="btn btn-secondary" onclick="openCopyPanel()" id="btn-copy-dist"
           style="margin-left:auto" title="Copiar esta distribuição para outras UCs partilhadas">
     📋 Copiar para…
   </button>
   <span id="save-status" style="font-size:12px;color:var(--gray-500)"></span>
  </div>

  <!-- Painel de cópia -->
  <div id="copy-panel" style="display:none;padding:14px 18px;border-top:2px solid var(--blue);background:var(--blue-light)">
    <div style="font-weight:600;margin-bottom:10px;color:var(--blue-dark)">📋 Copiar distribuição para UCs partilhadas</div>
    <p style="font-size:12px;color:var(--gray-600);margin-bottom:10px">
      Selecciona as UCs destino. A distribuição será copiada com <strong>DSD ❌</strong> (não duplica horas)
      e substituirá toda a distribuição existente nessas UCs.
    </p>
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
      <select id="copy-plano-filter" onchange="filterCopyList()" style="min-width:120px;font-size:12px">
        <option value="">Todos os planos</option>
      </select>
      <input type="text" id="copy-search" oninput="filterCopyList()" placeholder="🔍 Pesquisar UC…" style="flex:1;font-size:12px">
    </div>
    <div id="copy-uc-list" style="display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto;margin-bottom:12px">
      <div style="color:var(--gray-400);font-size:12px">Selecciona primeiro uma UC acima.</div>
    </div>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn btn-primary btn-sm" onclick="executeCopy()">📋 Copiar agora</button>
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
  <div class="page-title">📋 Distribuição de Serviço Docente</div>
  <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?> &mdash; <?= count($rows) ?> registos</div>
 </div>
 <div style="display:flex;gap:10px">
  <a href="ocorrencias.php" class="btn btn-secondary">📅 Ocorrências</a>
  <a href="../reports/por-docente.php" class="btn btn-secondary">📊 Relatório</a>
  <button class="btn btn-primary" onclick="openModal()">➕ Adicionar</button>
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
  <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍</button>
  <a href="distribuicao.php" class="btn btn-secondary btn-sm" style="align-self:flex-end">✕</a>
 </form>
 <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <input type="text" id="search-uc" placeholder="🔍 Pesquisar UC…" oninput="searchUC(this.value)"
         style="flex:1;max-width:400px">
  <label style="display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;
                background:var(--gray-100);border:1px solid var(--gray-300);border-radius:6px;padding:4px 10px">
    <input type="checkbox" id="filtro-sem-sd" onchange="searchUC(document.getElementById('search-uc').value)">
    ⚠️ Só UCs sem serviço
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
      ⚠️ Sem serviço (<?= count($ucsSemSD) ?>)
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
             style="padding:2px 8px;font-size:11px">✏️ UC</button>
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
  <td style="text-align:center"><?= $r['regente'] ? '⭐' : '' ?></td>
  <td style="text-align:center"><?= $r['dsd_por_docente'] ? '✅' : '❌' ?></td>
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
   <button type="button" class="btn btn-secondary btn-xs" onclick="openModal(<?= (int)$r['id'] ?>)">✏️</button>
   <form method="post" style="display:inline">
    <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
    <button type="submit" class="btn btn-danger btn-xs" data-confirm="Remover?">🗑️</button>
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

<script>
// Cascade carreira → docente no form de filtro da página
function fltCascadeDocente() {
  var carrId  = document.getElementById('flt-dist-carr').value;
  var sel     = document.getElementById('flt-dist-doc');
  var current = sel.value;

  // Mostrar/esconder conforme carreira
  Array.from(sel.options).forEach(function(o) {
    if (!o.value) return;
    o.hidden = carrId !== '' && o.dataset.carr !== carrId;
  });

  // Se a opção seleccionada ficou escondida, reset
  var curOpt = sel.querySelector('option[value="' + current + '"]');
  if (curOpt && curOpt.hidden) sel.value = '';

  // Reordenar as options visíveis alfabeticamente
  var opts = Array.from(sel.options).filter(function(o) { return !!o.value; });
  opts.sort(function(a, b) {
    return a.text.localeCompare(b.text, 'pt', { sensitivity: 'base' });
  });
  opts.forEach(function(o) { sel.appendChild(o); }); // move para o fim (mantém ordem)

  // Restaurar selecção
  sel.value = curOpt && !curOpt.hidden ? current : '';
}
// Aplicar cascade no carregamento (caso carreira venha por GET)
fltCascadeDocente();

function togglePlanoSD(id) {
  const el = document.getElementById(id);
  const icon = document.getElementById('icon-' + id);
  const open = el.style.display === 'none';
  el.style.display = open ? '' : 'none';
  if (icon) icon.textContent = open ? '▼' : '▶';
}
</script>

<div id="panel-uc" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:280px;max-width:400px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-uc-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-uc').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)">✕</button>
  </div>
  <div id="panel-uc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-uc-edit" href="#" class="btn btn-secondary btn-xs">✏️ Editar ocorrência</a>
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
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)">✕</button>
  </div>
  <div id="panel-doc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-doc-edit" href="#" class="btn btn-secondary btn-xs">✏️ Editar docente</a>
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

function positionPanel(panel, e) {
  panel.style.display = 'block';
  const r = panel.getBoundingClientRect();
  let x = e.clientX + 10, y = e.clientY + 10;
  if (x + r.width  > window.innerWidth)  x = e.clientX - r.width - 10;
  if (y + r.height > window.innerHeight) y = e.clientY - r.height - 10;
  panel.style.left = x + 'px';
  panel.style.top  = y + 'px';
}

function showUcInfo(ocorId, e) {
  e.preventDefault();
  const oc = ocorData[ocorId];
  if (!oc) return;
  const bal = balData[ocorId];
  const need = bal ? parseFloat(bal.need) : 0;
  const done = bal ? parseFloat(bal.done) : 0;
  const diff = need - done;
  const col = Math.abs(diff)<0.01 ? 'var(--green)' : diff>0 ? 'var(--orange)' : 'var(--red)';
  const tipos = [['T','T'],['TP','TP'],['L','L'],['Sem','Sem'],['OT','OT']];
  let rows = tipos.map(([k,l]) => {
    const nT = parseFloat(oc['n_turmas_'+k])||0;
    const hT = parseFloat(oc['horas_'+k])||0;
    return nT||hT ? `<tr><td style="color:var(--gray-500)">${l}</td><td>${fmtN(nT,1)} turmas</td><td>${fmtN(hT,2)} h/sem</td></tr>` : '';
  }).join('');
  document.getElementById('panel-uc-title').textContent = oc.designacao;
  document.getElementById('panel-uc-body').innerHTML =
    `<table style="width:100%;border-collapse:collapse">${rows}</table>`
    + `<div style="margin-top:8px">Estudantes: <b>${oc.estudantes}</b> | F SLEf: <b>${fmtN(oc.f_slef,2)}</b> | Semanas: <b>${fmtN(oc.semanas,1)}</b></div>`
    + `<div style="margin-top:4px;color:${col};font-weight:600">Nec: ${fmtN(need,2)} | Atr: ${fmtN(done,2)} | ${Math.abs(diff)<0.01?'✅ OK':diff>0?'Falta '+fmtN(diff,2):'Excesso '+fmtN(-diff,2)}</div>`;
  document.getElementById('panel-uc-edit').href = 'ocorrencia-form.php?id=' + ocorId + '&back_scroll=' + Math.round(window.scrollY);
  positionPanel(document.getElementById('panel-uc'), e);
  document.getElementById('panel-doc').style.display = 'none';
}

function showDocInfo(docId, e) {
  e.preventDefault();
  const doc = docSnap[docId];
  if (!doc) return;
  // Get dist rows for this docente
  const distRows = Object.values(rowData).filter(r => r.docente_id == docId);
  let total_slef = distRows.reduce((s,r) => s + (parseFloat(r.h_slef_uc)||0), 0);
  document.getElementById('panel-doc-title').textContent = doc.nome;
  document.getElementById('panel-doc-body').innerHTML =
    `<div>Carreira: <b>${doc.carreira||'–'}</b></div>`
    + `<div>UCs neste ano: <b>${distRows.length}</b></div>`
    + `<div>Total H SLEf (ano): <b style="color:var(--blue)">${fmtN(total_slef,2)}</b> h/sem</div>`;
  document.getElementById('panel-doc-edit').href = 'docente-form.php?id=' + docId + '&back_scroll=' + Math.round(window.scrollY);
  positionPanel(document.getElementById('panel-doc'), e);
  document.getElementById('panel-uc').style.display = 'none';
}

// Close panels on click outside
document.addEventListener('click', e => {
  if (!e.target.closest('#panel-uc,#panel-doc,[onclick*="showUcInfo"],[onclick*="showDocInfo"]')) {
    document.getElementById('panel-uc').style.display = 'none';
    document.getElementById('panel-doc').style.display = 'none';
  }
});

const balData  = <?= json_encode($balData, JSON_HEX_QUOT|JSON_HEX_TAG) ?>;
const preOcor  = <?= json_encode($preOcor ?: null) ?>;

let pendingLines = [];
let editingIdx   = -1;
let deletedIds   = [];

const g   = id => document.getElementById(id);
const fmtN = (v, d=2) => (parseFloat(v)||0).toFixed(d);

// ── Filters ───────────────────────────────────────────────────
function filterOcs() {
  const plano = g('f-plano-filter').value;
  const cur   = g('f-ocor').value;
  let visible = 0;
  Array.from(g('f-ocor').options).forEach(o => {
    if (!o.value) return;
    const show = !plano || o.dataset.plano === plano;
    o.hidden = !show;
    if (show) visible++;
  });
  if (g('f-ocor').selectedOptions[0]?.hidden) {
    g('f-ocor').value = ''; onOcorChange();
  }
}

function filterDocentes() {
  const car = g('f-carreira').value;
  const cur = g('f-doc').value;
  Array.from(g('f-doc').options).forEach(o => {
    if (!o.value) return;
    o.hidden = !!(car && o.dataset.carreira !== car);
  });
  if (g('f-doc').selectedOptions[0]?.hidden) g('f-doc').value = '';
}

// ── UC change ─────────────────────────────────────────────────
function onOcorChange() {
  const ocId = g('f-ocor').value;
  const oc   = ocorData[ocId];
  const bal  = balData[ocId];
  if (oc) {
    const need = bal ? parseFloat(bal.need) : 0;
    const done = bal ? parseFloat(bal.done) : 0;
    const diff = need - done;
    const color = Math.abs(diff) < 0.01 ? 'var(--green)' : diff > 0 ? 'var(--orange)' : 'var(--red)';
    g('oc-info').innerHTML =
      `F SLEf: <b>${fmtN(oc.f_slef,2)}</b> | Semanas: <b>${fmtN(oc.semanas,1)}</b> | Estudantes: <b>${oc.estudantes}</b>` +
      ` &nbsp;|&nbsp; Necessário: <b>${fmtN(need,2)}</b> h/sem &nbsp; Atribuído: <b>${fmtN(done,2)}</b> h/sem` +
      ` &nbsp; <span style="color:${color};font-weight:600">${diff > 0.01 ? 'Falta: '+fmtN(diff,2) : diff < -0.01 ? 'Excesso: '+fmtN(-diff,2) : '✅ OK'}</span>`;
    // Pre-fill horas from ocorrencia
    ['T','TP','L','Sem','OT'].forEach(k => {
      if ((parseFloat(g('f-t'+k).value)||0) === 0) {
        const nT = parseFloat(oc['n_turmas_'+k]) || 0;
        const hO = parseFloat(oc['horas_'+k])   || 0;
        if (hO > 0) g('f-h'+k).value = hO;
      }
    });
  } else {
    g('oc-info').innerHTML = '';
  }
  refreshLineCalc();
}

// ── Line calc ─────────────────────────────────────────────────
function refreshLineCalc() {
  const sem   = parseFloat(g('f-semanas').value)||13;
  const ocId  = g('f-ocor').value;
  const oc    = ocorData[ocId];
  const fslef = oc ? parseFloat(oc.f_slef)||1 : 1;
  let hs=0, hslef=0;
  ['T','TP','L','Sem','OT'].forEach(k => {
    const t = parseFloat(g('f-t'+k).value)||0;
    const h = parseFloat(g('f-h'+k).value)||0;
    const contrib = t*h*sem/13;
    hs += contrib;
    if (k !== 'OT') hslef += contrib;
  });
  hslef *= fslef;
  const bal = balData[g('f-ocor').value];
  let balHtml = '';
  if (bal) {
    const need = parseFloat(bal.need)||0;
    // Calculate done from pending lines (live) instead of stale balData.done
    const ocId2 = g('f-ocor').value;
    const pendDone = pendingLines
      .filter(l => String(l.ocorrencia_id) === String(ocId2))
      .reduce((s, l) => {
        const sem2 = parseFloat(l.semanas)||13;
        return s + ['T','TP','L','Sem'].reduce((ss,k) =>
          ss + (parseFloat(l['t'+k])||0)*(parseFloat(l['h'+k])||0)*sem2/13, 0);
      }, 0);
    const done = pendingLines.filter(l => String(l.ocorrencia_id) === String(ocId2)).length > 0
      ? pendDone * (oc ? parseFloat(oc.f_slef)||1 : 1)
      : parseFloat(bal.done)||0;
    const diff = need - done;
    const col  = Math.abs(diff)<0.01 ? 'var(--green)' : diff>0 ? 'var(--red)' : 'var(--blue)';
    balHtml = ` | UC: Nec <b>${fmtN(need,2)}</b> Atr <b>${fmtN(done,2)}</b> <span style="color:${col};font-weight:600">${Math.abs(diff)<0.01?'✅':diff>0?'−'+fmtN(diff,2):'+'+fmtN(-diff,2)}</span>`;
  }
  g('line-calc').innerHTML = `H/s: <b>${fmtN(hs,2)}</b> | H SLEf: <b style="color:var(--blue)">${fmtN(hslef,2)}</b>${balHtml}`;
  // Also update the oc-info balance if there are pending lines
  if (pendingLines.length > 0) updateBalFromPending();
}

// ── Line editor ───────────────────────────────────────────────
function resetLineEditor() {
  g('f-doc').value     = '';
  g('f-carreira').value= '';
  g('f-semanas').value = 13;
  g('f-dsd').checked   = true;
  g('f-reg').checked   = false;
  ['T','TP','L','Sem','OT'].forEach(k => { g('f-t'+k).value=0; g('f-h'+k).value=0; });
  g('f-htese').value = 0;
  editingIdx = -1;
  g('line-mode-label').textContent = 'Nova linha';
  g('btn-add-line').textContent    = '+ Adicionar linha';
  g('btn-cancel-edit').style.display = 'none';
  filterDocentes();
}

function getCurrentLine() {
  return {
    ocorrencia_id: g('f-ocor').value,
    docente_id:    g('f-doc').value,
    docente_nome:  g('f-doc').selectedOptions[0]?.text || '?',
    semanas:       g('f-semanas').value,
    dsd:           g('f-dsd').checked ? 1 : 0,
    reg:           g('f-reg').checked ? 1 : 0,
    tT: g('f-tT').value,  hT: g('f-hT').value,
    tTP:g('f-tTP').value, hTP:g('f-hTP').value,
    tL: g('f-tL').value,  hL: g('f-hL').value,
    tSem:g('f-tSem').value, hSem:g('f-hSem').value,
    tOT:g('f-tOT').value, hOT:g('f-hOT').value,
    htese:g('f-htese').value,
    edit_id: 0,
  };
}

function addLine() {
  const ocId = g('f-ocor').value;
  const docId= g('f-doc').value;
  if (!ocId)  { alert('Seleciona uma UC.'); return; }
  if (!docId) { alert('Seleciona um docente.'); return; }
  const line = getCurrentLine();
  if (editingIdx >= 0) {
    line.edit_id = pendingLines[editingIdx].edit_id || 0;
    pendingLines[editingIdx] = line;
  } else {
    pendingLines.push(line);
  }
  resetLineEditor();
  renderLines();
  updateBalFromPending();
  onOcorChange();
}

function cancelEditLine() { resetLineEditor(); }

function editLine(idx) {
  const line = pendingLines[idx];
  g('f-ocor').value    = line.ocorrencia_id;
  g('f-doc').value     = line.docente_id;
  g('f-semanas').value = line.semanas;
  g('f-dsd').checked   = line.dsd == 1;
  g('f-reg').checked   = line.reg == 1;
  resetCheckboxStyles();
  g('f-tT').value=line.tT;   g('f-hT').value=line.hT;
  g('f-tTP').value=line.tTP; g('f-hTP').value=line.hTP;
  g('f-tL').value=line.tL;   g('f-hL').value=line.hL;
  g('f-tSem').value=line.tSem; g('f-hSem').value=line.hSem;
  g('f-tOT').value=line.tOT; g('f-hOT').value=line.hOT;
  g('f-htese').value=line.htese;
  editingIdx = idx;
  g('line-mode-label').textContent = 'A editar linha ' + (idx+1);
  g('btn-add-line').textContent    = '✓ Actualizar linha';
  g('btn-cancel-edit').style.display = '';
  onOcorChange(); refreshLineCalc();
}

function removeLine(idx) {
  const line = pendingLines[idx];
  if (line.edit_id) deletedIds.push(line.edit_id);
  pendingLines.splice(idx, 1);
  if (editingIdx === idx) resetLineEditor();
  else if (editingIdx > idx) editingIdx--;
  renderLines();
  updateBalFromPending();
}

function renderLines() {
  const tbody = g('lines-body');
  tbody.innerHTML = '';
  if (pendingLines.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--gray-400);padding:16px">Sem linhas — adiciona acima</td></tr>';
    g('btn-save-all').disabled = deletedIds.length === 0;
    return;
  }
  pendingLines.forEach((line, idx) => {
    const tr = document.createElement('tr');
    if (idx === editingIdx) tr.style.background = 'var(--blue-light)';
    const c = (v) => `<td style="text-align:center;font-size:12px">${v}</td>`;
    const f1 = v => (parseFloat(v)||0) > 0 ? fmtN(v,1) : '–';
    const f2 = v => (parseFloat(v)||0) > 0 ? fmtN(v,1) : '–';
    tr.innerHTML =
      `<td style="font-size:12px">${line.docente_nome}</td>` +
      c(fmtN(line.semanas,0)) +
      c(f1(line.tT)+'/'+f2(line.hT)) +
      c(f1(line.tTP)+'/'+f2(line.hTP)) +
      c(f1(line.tL)+'/'+f2(line.hL)) +
      c(f1(line.tSem)+'/'+f2(line.hSem)) +
      c(f1(line.tOT)+'/'+f2(line.hOT)) +
      c(line.dsd ? '✓' : '–') +
      c(line.reg ? 'R' : '–') +
      `<td style="white-space:nowrap;padding:2px 6px">
        <button type="button" class="btn btn-secondary btn-xs" onclick="editLine(${idx})">✏️</button>
        <button type="button" class="btn btn-danger btn-xs" onclick="removeLine(${idx})">🗑</button>
      </td>`;
    tbody.appendChild(tr);
  });
  g('btn-save-all').disabled = false;
}

// ── Save ──────────────────────────────────────────────────────
function saveAllLines() {
  if (!pendingLines.length && !deletedIds.length) { alert('Sem alterações para guardar.'); return; }
  g('f-batch-json').value = JSON.stringify(pendingLines);
  g('f-delete-ids').value = JSON.stringify(deletedIds);
  g('dist-form').submit();
}

// ── Modal ─────────────────────────────────────────────────────
function openModal(editId, ocorId) {
  pendingLines = []; editingIdx = -1; deletedIds = [];
  resetLineEditor();
  const r1 = v => Math.round(parseFloat(v||0)*10)/10;

  if (editId) {
    // Edit existing row
    const reg = rowData[String(editId)];
    if (!reg) { alert('Registo não encontrado.'); return; }
    const _oid = String(reg.ocor_id || reg.ocorrencia_id || '');
    const _o2  = _oid ? ocorData[_oid] : null;
    g('modal-title').textContent = '✏️ Editar Linha' + (_o2 ? ' — ['+(_o2.plano_sigla||'')+'] '+(_o2.designacao||'') : '');
    g('modal-uc-section').style.display = 'none';
    g('f-ocor').value    = reg.ocorrencia_id || reg.ocor_id;
    g('f-doc').value     = reg.docente_id;
    g('f-semanas').value = reg.semanas;
    g('f-dsd').checked   = reg.dsd_por_docente == 1;
    g('f-reg').checked   = reg.regente == 1;
    g('f-tT').value=r1(reg.turmas_T); g('f-hT').value=r1(reg.horas_T);
    g('f-tTP').value=r1(reg.turmas_TP); g('f-hTP').value=r1(reg.horas_TP);
    g('f-tL').value=r1(reg.turmas_L); g('f-hL').value=r1(reg.horas_L);
    g('f-tSem').value=r1(reg.turmas_Sem); g('f-hSem').value=r1(reg.horas_Sem);
    g('f-tOT').value=r1(reg.turmas_OT); g('f-hOT').value=r1(reg.horas_OT);
    g('f-htese').value=reg.h_tese||0;
    const line = getCurrentLine();
    line.edit_id = editId;
    line.docente_nome = reg.docente_nome;
    pendingLines = [line];
    editingIdx = 0;
    g('line-mode-label').textContent = 'A editar registo existente';
    g('btn-add-line').textContent    = '✓ Actualizar';
    g('btn-cancel-edit').style.display = 'none';
    onOcorChange(); refreshLineCalc();
  } else {
    g('modal-title').textContent = '➕ Adicionar Serviço';
    g('modal-uc-section').style.display = '';
    if (ocorId) { g('f-ocor').value = String(ocorId); onOcorChange(); }
    else if (preOcor) { g('f-ocor').value = String(preOcor); onOcorChange(); }
  }

  renderLines();
  resetCheckboxStyles();
  g('modal-dist').style.display = 'flex';
  document.body.style.overflow  = 'hidden';
}

// Recalcula balanço por tipologia a partir das linhas pendentes
function updateBalFromPending() {
  const ocId = g('f-ocor').value;
  if (!ocId || !ocorData[ocId]) return;
  const oc = ocorData[ocId];
  const tipos = ['T','TP','L','Sem','OT'];

  // Sum pending lines for this UC
  const pendDone = {};
  tipos.forEach(k => pendDone[k] = 0);
  pendingLines.filter(l => String(l.ocorrencia_id) === String(ocId)).forEach(l => {
    const sem = parseFloat(l.semanas) || 13;
    tipos.forEach(k => {
      pendDone[k] += (parseFloat(l['t'+k])||0) * (parseFloat(l['h'+k])||0) * sem / 13;
    });
  });

  // Need per tipo
  let html = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">';
  let totalNeed = 0, totalDone = 0;
  tipos.forEach(k => {
    const need = (parseFloat(oc['n_turmas_'+k])||0) * (parseFloat(oc['horas_'+k])||0);
    if (need <= 0 && pendDone[k] <= 0) return;
    const done = pendDone[k];
    const diff = need - done;
    const col = Math.abs(diff) < 0.01 ? 'var(--green)' : diff > 0 ? 'var(--orange)' : 'var(--red)';
    html += `<span style="font-size:11px;background:var(--gray-100);padding:2px 8px;border-radius:4px">
      <b>${k}</b>: ${fmtN(need,1)}→<b style="color:${col}">${fmtN(done,1)}</b>
      <span style="color:${col}">${Math.abs(diff)<0.01?'✅':diff>0?'−'+fmtN(diff,1):'+'+fmtN(-diff,1)}</span>
    </span>`;
    totalNeed += need; totalDone += done;
  });
  html += '</div>';

  const totalDiff = totalNeed - totalDone;
  const totalCol = Math.abs(totalDiff)<0.01?'var(--green)':totalDiff>0?'var(--orange)':'var(--red)';
  g('oc-info').innerHTML =
    `F SLEf: <b>${fmtN(oc.f_slef,2)}</b> | Semanas: <b>${fmtN(oc.semanas||13,1)}</b> | Estudantes: <b>${oc.estudantes||0}</b>`
    + ` &nbsp;|&nbsp; Nec: <b>${fmtN(totalNeed,2)}</b> Atr (pendente): <b>${fmtN(totalDone,2)}</b>`
    + ` <span style="color:${totalCol};font-weight:600">${Math.abs(totalDiff)<0.01?'✅ OK':totalDiff>0?'Falta: '+fmtN(totalDiff,2):'Excesso: '+fmtN(-totalDiff,2)}</span>`
    + html;
}

function updateDsdStyle() {
  const on = g("f-dsd").checked;
  const lbl = g("lbl-dsd");
  lbl.style.background = on ? "var(--blue-light)" : "var(--gray-100)";
  lbl.style.borderColor = on ? "var(--blue)" : "var(--gray-300)";
  lbl.style.color = on ? "var(--blue-dark)" : "var(--gray-400)";
}
function updateRegStyle() {
  const on = g("f-reg").checked;
  const lbl = g("lbl-reg");
  lbl.style.background = on ? "#fef9c3" : "var(--gray-100)";
  lbl.style.borderColor = on ? "#ca8a04" : "var(--gray-300)";
  lbl.style.color = on ? "#92400e" : "var(--gray-400)";
}
function resetCheckboxStyles() {
  updateDsdStyle(); updateRegStyle();
}

function openCopyPanel() {
  const ocId = g('f-ocor').value;
  if (!ocId || !pendingLines.length) {
    alert('Guarda primeiro as linhas antes de copiar.'); return;
  }
  const panel = g('copy-panel');
  const list  = g('copy-uc-list');
  panel.style.display = '';

  // Load other occurrences from same year, excluding current
  list.innerHTML = '<div style="color:var(--gray-400);font-size:12px">A carregar…</div>';
  fetch('ajax-dist.php?list_ocors=1&exclude=' + ocId + '&_=' + Date.now())
    .then(r => r.json())
    .then(data => {
      if (!data.ocors || !data.ocors.length) {
        list.innerHTML = '<div style="color:var(--gray-400);font-size:12px">Sem outras ocorrências neste ano.</div>';
        return;
      }
      // Populate plano filter
      const planos = [...new Set(data.ocors.map(o => o.plano).filter(Boolean))].sort();
      const sel = document.getElementById('copy-plano-filter');
      sel.innerHTML = '<option value="">Todos os planos</option>' +
        planos.map(p => `<option value="${p}">${p}</option>`).join('');
      // Store all ocors for filtering
      window._copyOcors = data.ocors;
      renderCopyList();
    })
    .catch(() => { document.getElementById('copy-uc-list').innerHTML = '<div style="color:var(--red)">Erro ao carregar.</div>'; });
}

function filterCopyList() {
  // Save checked values before re-render
  const checked = new Set([...document.querySelectorAll('.copy-dest:checked')].map(x => x.value));
  renderCopyList();
  // Restore checked state
  document.querySelectorAll('.copy-dest').forEach(cb => {
    if (checked.has(cb.value)) cb.checked = true;
  });
}

function renderCopyList() {
  const list   = document.getElementById('copy-uc-list');
  const plano  = document.getElementById('copy-plano-filter').value;
  const search = (document.getElementById('copy-search')?.value || '').toLowerCase();
  const checked = new Set([...document.querySelectorAll('.copy-dest:checked')].map(x => x.value));

  const selected = (window._copyOcors || []).filter(o => checked.has(String(o.id)));
  const filtered = (window._copyOcors || []).filter(o =>
    !checked.has(String(o.id)) &&
    (!plano  || o.plano === plano) &&
    (!search || o.nome.toLowerCase().includes(search))
  );

  if (!selected.length && !filtered.length) {
    list.innerHTML = '<div style="color:var(--gray-400);font-size:12px">Sem UCs encontradas.</div>';
    return;
  }

  const renderItem = (o, isSel) =>
    `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:5px 8px;
            background:${isSel ? '#eff6ff' : '#fff'};
            border:1px solid ${isSel ? 'var(--blue)' : 'var(--gray-200)'};
            border-radius:6px;font-size:13px">
      <input type="checkbox" class="copy-dest" value="${o.id}" ${isSel ? 'checked' : ''} onchange="renderCopyList()">
      <span class="badge badge-blue" style="font-size:10px">${o.plano}</span>
      ${o.nome}
      ${o.n_dist > 0 ? '<span style="font-size:10px;color:var(--orange);margin-left:4px">⚠️ tem '+o.n_dist+' linha(s)</span>' : ''}
    </label>`;

  list.innerHTML =
    selected.map(o => renderItem(o, true)).join('') +
    (selected.length && filtered.length ? '<hr style="margin:4px 0;border-color:var(--gray-200)">' : '') +
    filtered.map(o => renderItem(o, false)).join('');
}

function executeCopy() {
  const ocId = g('f-ocor').value;
  const dests = [...document.querySelectorAll('.copy-dest:checked')].map(c => c.value);
  if (!dests.length) { alert('Selecciona pelo menos uma UC destino.'); return; }
  if (!confirm('Substituir toda a distribuição nas ' + dests.length + ' UC(s) seleccionadas?\nAs linhas existentes serão apagadas.')) return;

  // Build copy payload: pendingLines with dsd=0
  const copyLines = pendingLines.map(l => ({...l, dsd: 0}));

  g('save-status').textContent = 'A copiar…';
  fetch('ajax-copy-dist.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({src_ocor: ocId, dest_ocors: dests, lines: copyLines,
                          ano_letivo_id: <?= (int)$al['id'] ?>})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      g('save-status').textContent = '✅ Copiado para ' + d.copied + ' UC(s)';
      setTimeout(() => window.location.reload(), 800);
    } else {
      g('save-status').textContent = '❌ ' + d.error;
    }
  })
  .catch(() => g('save-status').textContent = '❌ Erro ao copiar');
}

function closeModal() {
  g('modal-dist').style.display = 'none';
  document.body.style.overflow  = '';
  pendingLines = []; editingIdx = -1;
}

function editUC(ocorId) {
  pendingLines = []; editingIdx = -1; deletedIds = [];
  resetLineEditor();

  // Pre-select the UC
  g('f-ocor').value = String(ocorId);
  g('modal-uc-section').style.display = '';
  g('f-plano-filter').value = '';
  filterOcs();
  onOcorChange();

  const _oc = ocorData[String(ocorId)];
  const _plano  = _oc ? (_oc.plano_sigla || '') : '';
  const _ucNome = _oc ? (_oc.designacao  || '') : '';
  g('modal-title').textContent = '✏️ ' + (_plano ? '['+_plano+'] ' : '') + (_ucNome || 'Editar Serviço da UC');

  // Show modal immediately with loading state (avoids carreira-filter blindspot in rowData)
  g('lines-body').innerHTML = '<tr id="lines-empty"><td colspan="10" style="text-align:center;color:var(--gray-400);padding:16px">A carregar…</td></tr>';
  g('btn-save-all').disabled = true;
  resetCheckboxStyles();
  g('modal-dist').style.display = 'flex';
  document.body.style.overflow  = 'hidden';

  // Fetch ALL lines for this UC from server (unfiltered by carreira)
  fetch('ajax-dist.php?edit_lines=1&ocor_id=' + ocorId + '&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
      pendingLines = [];
      (data.rows || []).forEach(function(reg) {
        pendingLines.push({
          ocorrencia_id: ocorId,
          docente_id:    reg.docente_id,
          docente_nome:  reg.docente_nome,
          semanas:       reg.semanas,
          dsd:           reg.dsd_por_docente,
          reg:           reg.regente,
          tT:  reg.turmas_T,  hT:  reg.horas_T,
          tTP: reg.turmas_TP, hTP: reg.horas_TP,
          tL:  reg.turmas_L,  hL:  reg.horas_L,
          tSem:reg.turmas_Sem,hSem:reg.horas_Sem,
          tOT: reg.turmas_OT, hOT: reg.horas_OT,
          htese: reg.h_tese || 0,
          edit_id: reg.id,
        });
      });
      renderLines();
      updateBalFromPending();
    })
    .catch(function() {
      g('lines-body').innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--red);padding:16px">Erro ao carregar linhas.</td></tr>';
    });
}

// ── Input listeners ───────────────────────────────────────────
['f-tT','f-hT','f-tTP','f-hTP','f-tL','f-hL','f-tSem','f-hSem','f-tOT','f-hOT','f-semanas'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', refreshLineCalc);
});

// ── Scroll position ─────────────────────────────────────────
const savedScroll = <?= (int)($_GET['scroll'] ?? 0) ?>;
if (savedScroll > 0) window.scrollTo({top: savedScroll, behavior: 'instant'});

// Save scroll position before submitting forms
document.addEventListener('submit', e => {
  const form = e.target;
  let inp = form.querySelector('[name=scroll_y]');
  if (!inp) { inp = document.createElement('input'); inp.type='hidden'; inp.name='scroll_y'; form.appendChild(inp); }
  inp.value = Math.round(window.scrollY);
});

// ── UC search ─────────────────────────────────────────────────
function searchUC(q) {
  q = q.toLowerCase();
  const semSD = document.getElementById('filtro-sem-sd')?.checked;
  const rows = document.querySelectorAll('#tbl-dist tbody tr');
  let ucVisible = 0, headerVisible = false;
  rows.forEach(tr => {
    if (tr.dataset.ucName !== undefined) {
      // UC header row — has service (data-has-sd="1")
      const matchQ = !q || tr.dataset.ucName.includes(q);
      // If semSD filter: hide UCs that HAVE service
      headerVisible = matchQ && !semSD;
      tr.style.display = headerVisible ? '' : 'none';
      if (headerVisible) ucVisible++;
    } else {
      tr.style.display = headerVisible ? '' : 'none';
    }
  });
  // Show/hide the sem-SD block
  // Treeview is always visible - no action needed
  const el = document.getElementById('search-count');
  if (el) el.textContent = (q && !semSD) ? ucVisible + ' UC(s)' : '';
}

<?php if ($editReg): ?>
window.addEventListener('DOMContentLoaded', () => openModal(<?= (int)$editReg['id'] ?>));
<?php elseif ($preOcor): ?>
window.addEventListener('DOMContentLoaded', () => openModal(null, <?= (int)$preOcor ?>));
<?php elseif (isset($_GET['edit_uc'])): ?>
window.addEventListener('DOMContentLoaded', () => editUC(<?= (int)$_GET['edit_uc'] ?>));
<?php endif; ?>
</script>

<!-- UC info panel -->
<div id="panel-uc" style="display:none;position:fixed;z-index:1100;background:#fff;border:1px solid var(--gray-200);
  border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px;min-width:280px;max-width:400px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong id="panel-uc-title" style="font-size:14px"></strong>
    <button onclick="document.getElementById('panel-uc').style.display='none'"
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)">✕</button>
  </div>
  <div id="panel-uc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px;display:flex;gap:8px">
    <a id="panel-uc-edit" href="#" class="btn btn-secondary btn-xs">✏️ Editar ocorrência</a>
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
            style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--gray-400)">✕</button>
  </div>
  <div id="panel-doc-body" style="font-size:13px;line-height:1.8"></div>
  <div style="margin-top:12px">
    <a id="panel-doc-edit" href="#" class="btn btn-secondary btn-xs">✏️ Editar docente</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
