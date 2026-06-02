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
    header('Location: distribuicao.php'); exit;
}

// ── Inserir / Editar ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ocorrencia_id'])) {
    $ocorrenciaId = (int)$_POST['ocorrencia_id'];
    $docId   = (int)$_POST['docente_id'];
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
        // Buscar uc_id da ocorrência (para compatibilidade com colunas legacy)
        $stmt = $db->prepare("SELECT uc_id FROM infodeqb_dsd_uc_ocorrencia WHERE id=?");
        $stmt->execute([$ocorrenciaId]);
        $ocor = $stmt->fetch();

        if (!$ocor) {
            flash('Ocorrência inválida.', 'error');
        } else {
            $editId = (int)($_POST['edit_id'] ?? 0);

            // Rótulo automático: se o mesmo docente já existe nesta ocorrência,
            // atribui sequencial ("2", "3"...) sem precisar que o utilizador escreva nada
            $rotulo = null;
            if (!$editId) {
                $nStmt = $db->prepare("SELECT COUNT(*) FROM infodeqb_dsd_distribuicao WHERE ocorrencia_id=? AND docente_id=?");
                $nStmt->execute([$ocorrenciaId, $docId]);
                $n = (int)$nStmt->fetchColumn();
                if ($n > 0) $rotulo = (string)($n + 1);
            }

            $fields = array_merge([
                'ano_letivo_id'   => $al['id'],
                'ocorrencia_id'   => $ocorrenciaId,
                'uc_id'           => $ocor['uc_id'],
                'docente_id'      => $docId,
                'rotulo'          => $rotulo,
                'dsd_por_docente' => $dsdPD,
                'regente'         => $regente,
                'semanas'         => $semanas,
            ], $vals, [
                'h_tese'      => $hTese,
                'observacoes' => $obs,
            ]);

            try {
                if ($editId) {
                    // Na edição não alterar o rótulo
                    unset($fields['rotulo']);
                    $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
                    $db->prepare("UPDATE infodeqb_dsd_distribuicao SET $sets WHERE id=?")
                       ->execute([...array_values($fields), $editId]);
                    flash('Distribuição atualizada.');
                } else {
                    $cols = implode(',', array_keys($fields));
                    $phs  = implode(',', array_fill(0, count($fields), '?'));
                    $db->prepare("INSERT INTO infodeqb_dsd_distribuicao ($cols) VALUES ($phs)")
                       ->execute(array_values($fields));
                    flash('Distribuição adicionada.' . ($rotulo ? " (rótulo automático: $rotulo)" : ''));
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
$preOcor       = (int)($_GET['ocorrencia'] ?? 0);  // vindo de ocorrencia-form

$where = ["d.ano_letivo_id = ?"];
$params = [$al['id']];
if ($filterPlano)   { $where[] = "pe.sigla = ?";   $params[] = $filterPlano; }
if ($filterDocente) { $where[] = "doc.id = ?";     $params[] = (int)$filterDocente; }
if ($filterSem)     { $where[] = "uc.semestre = ?"; $params[] = $filterSem; }
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
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON uc.plano_id = pe.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    LEFT JOIN infodeqb_dsd_departamento dep ON doc.departamento_id = dep.id
    $whereSQL
    ORDER BY pe.ordem, uc.designacao, car.ordem, doc.nome
");
$rowsStmt->execute($params);
$rows = $rowsStmt->fetchAll();

// ── Listas para selects ───────────────────────────────────
// Ocorrências do ano ativo (substitui o select de UCs)
$ocs = $db->prepare("
    SELECT o.id, o.uc_id, o.f_slef, uc., o.outros_planos,
           o.n_turmas_T, o.n_turmas_TP, o.n_turmas_L, o.n_turmas_Sem, o.n_turmas_OT,
           o.horas_T, o.horas_TP, o.horas_L, o.horas_Sem, o.horas_OT,
           o.semanas,
           uc.designacao, uc.semestre,
           pe.sigla AS plano_sigla
    FROM infodeqb_dsd_uc_ocorrencia o
    JOIN infodeqb_dsd_uc uc ON o.uc_id = uc.id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON uc.plano_id = pe.id
    WHERE o.ano_letivo_id = ?
    ORDER BY pe.ordem, uc.designacao
");
$ocs->execute([$al['id']]);
$ocorrencias = $ocs->fetchAll();

$docentes = $db->query("
    SELECT d.id, d.nome, c.designacao AS carreira
    FROM infodeqb_dsd_docente d
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=d.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira c ON da.carreira_id = c.id
    WHERE da.ativo = 1
    ORDER BY c.ordem, d.nome
")->fetchAll();

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem")->fetchAll();

// ── Edição (vindo de ?edit=ID) ───────────────────────────
$editReg = [];
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM infodeqb_dsd_distribuicao WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editReg = $stmt->fetch() ?: [];
}

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:flex;align-items:center;justify-content:center;}
.modal-box{background:#fff;border-radius:12px;width:720px;max-width:96vw;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--gray-200);position:sticky;top:0;background:#fff;z-index:1;}
.modal-title{font-size:15px;font-weight:700;}
.modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:var(--gray-400);line-height:1;padding:0 4px;}
.modal-body{padding:18px 20px;}
.tipo-grid{width:100%;border-collapse:collapse;font-size:12px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:10px;}
.tipo-grid th{background:var(--gray-50);padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);border-bottom:1px solid var(--gray-200);}
.tipo-grid td{padding:4px 8px;border-top:1px solid var(--gray-100);}
.tipo-grid input{width:65px;padding:3px 5px;border:1px solid var(--gray-300);border-radius:4px;text-align:right;font-size:12px;}
.tipo-grid .eq{background:var(--gray-50);}
.tipo-grid .meta{color:var(--gray-400);font-size:11px}
.tipo-grid .falta-ok{color:var(--green);font-weight:600}
.tipo-grid .falta-ko{color:var(--red);font-weight:600}
.calc-bar{background:var(--blue-light);border-radius:6px;padding:7px 12px;font-size:12px;margin-bottom:12px;}
</style>

<!-- MODAL -->
<div id="modal-dist" class="modal-overlay" style="display:none">
 <div class="modal-box">
  <div class="modal-header">
   <div id="modal-title" class="modal-title">➕ Adicionar Distribuição</div>
   <button class="modal-close" onclick="closeModal()">✕</button>
  </div>
  <div class="modal-body">
  <form method="post" id="dist-form">
   <input type="hidden" name="edit_id" id="f-edit-id" value="">

   <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
    <div class="form-group" style="grid-column:1/-1">
     <label>Ocorrência (UC neste ano letivo) *</label>
     <select id="f-plano-filter" style="margin-bottom:6px;font-size:12px" onchange="filterOcs()">
      <option value="">— Filtrar por plano —</option>
      <?php foreach ($planos as $p): ?>
       <option value="<?= esc($p['sigla']) ?>"><?= esc($p['sigla']) ?></option>
      <?php endforeach; ?>
     </select>
     <select name="ocorrencia_id" id="f-ocor" required>
      <option value="">— Selecionar ocorrência —</option>
      <?php foreach ($ocorrencias as $o): ?>
       <option value="<?= $o['id'] ?>"
               data-plano="<?= esc($o['plano_sigla'] ?? '') ?>"
               data-fslef="<?= esc((string)$o['f_slef']) ?>"
               data-semanas="<?= esc((string)$o['semanas']) ?>"
               data-ht="<?= esc((string)$o['horas_T']) ?>"
               data-htp="<?= esc((string)$o['horas_TP']) ?>"
               data-hl="<?= esc((string)$o['horas_L']) ?>"
               data-hs="<?= esc((string)$o['horas_Sem']) ?>"
               data-hot="<?= esc((string)$o['horas_OT']) ?>">
        [<?= esc($o['plano_sigla'] ?? '–') ?>] <?= esc($o['designacao']) ?>
        (<?= esc($o['semestre']) ?>)
       </option>
      <?php endforeach; ?>
     </select>
     <div id="ocor-info" style="display:none;margin-top:6px;padding:8px;background:var(--gray-50);border-radius:6px;font-size:11px;color:var(--gray-600)"></div>
    </div>

    <div class="form-group" style="grid-column:1/-1">
     <label>Docente *</label>
     <select name="docente_id" id="f-doc" required>
      <option value="">— Selecionar Docente —</option>
      <?php foreach ($docentes as $d): ?>
       <option value="<?= $d['id'] ?>"><?= esc($d['nome']) ?> (<?= esc($d['carreira']) ?>)</option>
      <?php endforeach; ?>
     </select>
    </div>

    <div class="form-group">
     <label>Semanas</label>
     <input type="number" id="semanas" name="semanas" step="any" min="0" max="30" value="13">
    </div>

    <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end;gap:8px">
     <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
      <input type="checkbox" name="dsd_por_docente" id="f-dsd" value="1" checked> Conta no DSD
     </label>
     <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400">
      <input type="checkbox" name="regente" id="f-reg" value="1"> Regente (R)
     </label>
    </div>
   </div>

   <table class="tipo-grid">
    <thead>
     <tr>
      <th>Tipo</th>
      <th style="text-align:center">Turmas</th>
      <th style="text-align:center">H/semana</th>
      <th style="text-align:right">Necessário</th>
      <th style="text-align:right">Já atribuído</th>
      <th style="text-align:right">Falta</th>
     </tr>
    </thead>
    <tbody>
     <?php
     $tipos = [
       ['T',   'T – Teóricas'],
       ['TP',  'TP – Teórico-Práticas'],
       ['L',   'L – Laboratoriais'],
       ['Sem', 'S – Seminários'],
       ['OT',  'OT – Orient. Tutorial (equiv.)', 'eq'],
     ];
     foreach ($tipos as $t):
       [$k, $label] = $t; $cls = $t[2] ?? '';
     ?>
     <tr class="<?= $cls ?>">
      <td><?= $label ?></td>
      <td style="text-align:center"><input type="number" id="turmas_<?= $k ?>" name="turmas_<?= $k ?>" step="any" min="0" value="0"></td>
      <td style="text-align:center"><input type="number" id="horas_<?= $k ?>" name="horas_<?= $k ?>" step="any" min="0" value="0"></td>
      <td class="num meta" id="need_<?= $k ?>">–</td>
      <td class="num meta" id="done_<?= $k ?>">–</td>
      <td class="num meta" id="miss_<?= $k ?>">–</td>
     </tr>
     <?php endforeach; ?>
     <tr class="eq">
      <td>H Tese / Orientação <span style="font-size:10px;color:var(--orange)">(equiv.)</span></td>
      <td></td>
      <td style="text-align:center"><input type="number" id="h_tese" name="h_tese" step="any" min="0" value="0"></td>
      <td colspan="3"></td>
     </tr>
    </tbody>
   </table>

   <div class="form-group" style="margin-bottom:10px">
    <label>Observações</label>
    <input type="text" name="observacoes" id="f-obs" value="">
   </div>

   <div class="calc-bar">
    <strong>Cálculo deste registo:</strong>
    H/s = <strong id="hs_calc">–</strong> &nbsp;|&nbsp;
    H Total = <strong id="ht_calc">–</strong> &nbsp;|&nbsp;
    H SLEf = <strong id="hslef_calc" style="color:var(--blue)">–</strong>
   </div>

   <div style="display:flex;gap:10px">
    <button type="submit" class="btn btn-primary">💾 Guardar</button>
    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
   </div>
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
  <div class="form-group" style="margin:0;flex:2;min-width:180px">
   <label>Docente</label>
   <select name="docente">
    <option value="">Todos</option>
    <?php foreach ($docentes as $d): ?>
     <option value="<?= $d['id'] ?>" <?= $filterDocente == $d['id'] ? 'selected' : '' ?>><?= esc($d['nome']) ?></option>
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
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table class="data-table" id="tbl-dist" style="font-size:12px">
 <thead>
  <tr>
   <th>Plano / UC</th><th>Sem.</th><th>Docente</th>
   <th style="text-align:center">R</th><th style="text-align:center">DSD</th>
   <th style="text-align:right">Sem.</th>
   <th style="text-align:right">T/h.T</th><th style="text-align:right">T/h.TP</th>
   <th style="text-align:right">T/h.L</th><th style="text-align:right">T/h.OT</th>
   <th style="text-align:right">h/s</th>
   <th style="text-align:right">H SLEf</th><th style="text-align:right">H Tese</th>
   <th style="text-align:center;width:65px">Ações</th>
  </tr>
 </thead>
 <tbody>
 <?php $prevUC = null; foreach ($rows as $r):
   $isNewUC = $r['uc_nome'] !== $prevUC; $prevUC = $r['uc_nome'];
   if ($isNewUC): ?>
 <tr class="row-section">
  <td colspan="14" style="padding:5px 12px">
   <span class="badge badge-blue" style="font-size:10px"><?= esc($r['plano'] ?? '–') ?></span>
   &nbsp;<strong><?= esc($r['uc_nome']) ?></strong>
   &nbsp;<span class="badge badge-gray" style="font-size:10px"><?= esc($r['semestre']) ?></span>
   <?php if ($r['outros_planos']): ?>
    &nbsp;<span style="font-size:10px;opacity:.7"><?= esc($r['outros_planos']) ?></span>
   <?php endif; ?>
   <span style="float:right;font-weight:400;font-size:11px;opacity:.7">F SLEf: <?= fmt((float)$r['f_slef'], 2) ?></span>
  </td>
 </tr>
 <?php endif; ?>
 <tr>
  <td></td>
  <td><span class="badge badge-gray" style="font-size:10px"><?= esc($r['semestre']) ?></span></td>
  <td><strong><?= esc($r['docente_nome']) ?></strong>
   <?php if ($r['depto']): ?><span style="font-size:10px;color:var(--gray-400);margin-left:4px"><?= esc($r['depto']) ?></span><?php endif; ?></td>
  <td style="text-align:center"><?= $r['regente'] ? '⭐' : '' ?>