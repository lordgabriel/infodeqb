<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Anos Letivos';
$activePage = 'anos';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'criar') {
        $designacao = trim($_POST['designacao'] ?? '');
        if ($designacao) {
            $db->exec("UPDATE infodeqb_dsd_ano_letivo SET ativo=0");
            $db->prepare("INSERT INTO infodeqb_dsd_ano_letivo (designacao, ativo) VALUES (?,1)")->execute([$designacao]);
            flash("Ano letivo '$designacao' criado e activado.");
        }
        header('Location: anos-letivos.php'); exit;
    }

    if ($_POST['action'] === 'ativar') {
        $db->exec("UPDATE infodeqb_dsd_ano_letivo SET ativo=0");
        $db->prepare("UPDATE infodeqb_dsd_ano_letivo SET ativo=1 WHERE id=?")->execute([(int)$_POST['id']]);
        flash('Ano letivo activado.');
        header('Location: anos-letivos.php'); exit;
    }

    if ($_POST['action'] === 'apagar') {
        $delId = (int)$_POST['id'];
        // Check directly in DB, not session
        $chk = $db->prepare("SELECT id FROM infodeqb_dsd_ano_letivo WHERE id=? AND ativo=1");
        $chk->execute([$delId]);
        if ($chk->fetch()) {
            flash('Não é possível apagar o ano letivo activo. Active outro primeiro.', 'error');
            header('Location: anos-letivos.php'); exit;
        }
        $db->prepare("DELETE FROM infodeqb_dsd_distribuicao WHERE ano_letivo_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM infodeqb_dsd_uc_ocorrencia WHERE ano_letivo_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM infodeqb_dsd_docente_ano WHERE ano_letivo_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM infodeqb_dsd_ano_letivo WHERE id=?")->execute([$delId]);
        flash("Ano letivo apagado com todos os dados associados.");
        header('Location: anos-letivos.php'); exit;
    }

    if ($_POST['action'] === 'copiar') {
        $fromId = (int)$_POST['from_id'];
        $toId   = (int)$_POST['to_id'];
        $modo   = $_POST['modo'] ?? 'tudo';
        $ucIds  = $_POST['uc_ids'] ?? [];
        $incluiDist = isset($_POST['inclui_distribuicao']);

        if (!$fromId || !$toId || $fromId === $toId) {
            flash('Seleccione anos letivos válidos e diferentes.', 'error');
            header('Location: anos-letivos.php'); exit;
        }

        $wOcor = $modo === 'tudo' ? '' : ('AND o.uc_id IN (' . implode(',', array_map('intval', $ucIds)) . ')');

        // 1) Copiar ocorrências
        $rowsOcor = $db->prepare("
            SELECT o.uc_id, o.plano_id, o.estudantes, o.f_slef, o.outros_planos, o.semanas,
                   o.n_turmas_T, o.n_turmas_TP, o.n_turmas_L, o.n_turmas_Sem, o.n_turmas_OT,
                   o.horas_T, o.horas_TP, o.horas_L, o.horas_Sem, o.horas_OT, o.observacoes
            FROM infodeqb_dsd_uc_ocorrencia o
            WHERE o.ano_letivo_id = ? $wOcor
        ");
        $rowsOcor->execute([$fromId]);
        $ocs = $rowsOcor->fetchAll();

        $insOc = $db->prepare("
            INSERT IGNORE INTO infodeqb_dsd_uc_ocorrencia
            (uc_id, plano_id, ano_letivo_id, estudantes, f_slef, outros_planos, semanas,
             n_turmas_T, n_turmas_TP, n_turmas_L, n_turmas_Sem, n_turmas_OT,
             horas_T, horas_TP, horas_L, horas_Sem, horas_OT, observacoes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $nOc = 0;
        foreach ($ocs as $o) {
            $insOc->execute([
                $o['uc_id'], $o['plano_id'], $toId, $o['estudantes'], $o['f_slef'],
                $o['outros_planos'], $o['semanas'],
                $o['n_turmas_T'], $o['n_turmas_TP'], $o['n_turmas_L'], $o['n_turmas_Sem'], $o['n_turmas_OT'],
                $o['horas_T'], $o['horas_TP'], $o['horas_L'], $o['horas_Sem'], $o['horas_OT'],
                $o['observacoes']
            ]);
            $nOc++;
        }

        // 2) Copiar distribuições (opcional)
        $nDist = 0;
        if ($incluiDist) {
            $rowsDist = $db->prepare("
                SELECT d.docente_id, d.rotulo, d.dsd_por_docente, d.regente, d.semanas,
                       d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
                       d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem,
                       d.turmas_OT, d.horas_OT, d.h_tese, d.observacoes,
                       o_src.uc_id
                FROM infodeqb_dsd_distribuicao d
                JOIN infodeqb_dsd_uc_ocorrencia o_src ON d.ocorrencia_id = o_src.id
                WHERE o_src.ano_letivo_id = ? $wOcor
            ");
            $rowsDist->execute([$fromId]);

            $findOc  = $db->prepare("SELECT id, uc_id FROM infodeqb_dsd_uc_ocorrencia WHERE ano_letivo_id=? AND uc_id=? LIMIT 1");
            $insDist = $db->prepare("
                INSERT INTO infodeqb_dsd_distribuicao
                (ano_letivo_id, ocorrencia_id, uc_id, docente_id, rotulo, dsd_por_docente, regente,
                 semanas, turmas_T, horas_T, turmas_TP, horas_TP,
                 turmas_L, horas_L, turmas_Sem, horas_Sem,
                 turmas_OT, horas_OT, h_tese, observacoes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            foreach ($rowsDist as $r) {
                $findOc->execute([$toId, $r['uc_id']]);
                $oNew = $findOc->fetch();
                if (!$oNew) continue;
                // Rótulo automático sequencial para o ano destino
                $nStmt = $db->prepare("SELECT COUNT(*) FROM infodeqb_dsd_distribuicao WHERE ocorrencia_id=? AND docente_id=?");
                $nStmt->execute([$oNew['id'], $r['docente_id']]);
                $n = (int)$nStmt->fetchColumn();
                // Se o rótulo original era NULL e não existe nenhum → copiar sem rótulo
                // Se já existe → atribuir sequencial
                $novoRotulo = $n === 0 ? $r['rotulo'] : (string)($n + 1);
                $insDist->execute([
                    $toId, $oNew['id'], $oNew['uc_id'], $r['docente_id'], $novoRotulo,
                    $r['dsd_por_docente'], $r['regente'], $r['semanas'],
                    $r['turmas_T'], $r['horas_T'], $r['turmas_TP'], $r['horas_TP'],
                    $r['turmas_L'], $r['horas_L'], $r['turmas_Sem'], $r['horas_Sem'],
                    $r['turmas_OT'], $r['horas_OT'], $r['h_tese'], $r['observacoes']
                ]);
                $nDist++;
            }
        }
        // 3) Copiar snapshots de docentes (opcional)
        $nDocSnap = 0;
        if (isset($_POST['inclui_docentes'])) {
            $db->prepare("
                INSERT INTO infodeqb_dsd_docente_ano
                    (docente_id, ano_letivo_id, carreira_id, categoria_id, deti, h_slef, ref_ecdu, observacoes, ativo)
                SELECT docente_id, ?, carreira_id, categoria_id, deti, h_slef, ref_ecdu, observacoes, ativo
                FROM infodeqb_dsd_docente_ano WHERE ano_letivo_id=?
                ON DUPLICATE KEY UPDATE
                    carreira_id=VALUES(carreira_id), categoria_id=VALUES(categoria_id),
                    deti=VALUES(deti), h_slef=VALUES(h_slef),
                    ref_ecdu=VALUES(ref_ecdu), observacoes=VALUES(observacoes), ativo=VALUES(ativo)
            ")->execute([$toId, $fromId]);
            $nDocSnap = $db->query("SELECT COUNT(*) FROM infodeqb_dsd_docente_ano WHERE ano_letivo_id=$toId")->fetchColumn();
        }
        $msg = "$nOc ocorrências";
        if ($nDist) $msg .= ", $nDist distribuições";
        if ($nDocSnap) $msg .= ", $nDocSnap snapshots de docentes";
        $msg .= ' copiados para o ano destino.';
        flash($msg);
        header('Location: anos-letivos.php'); exit;
    }
}

$anos = $db->query("SELECT * FROM infodeqb_dsd_ano_letivo ORDER BY id DESC")->fetchAll();

$stats = [];
foreach ($anos as $a) {
    $st = $db->prepare("
        SELECT
          (SELECT COUNT(*) FROM infodeqb_dsd_uc_ocorrencia WHERE ano_letivo_id=?) AS n_ocs,
          (SELECT COUNT(*) FROM infodeqb_dsd_distribuicao WHERE ano_letivo_id=?) AS n_dist,
          (SELECT COUNT(DISTINCT docente_id) FROM infodeqb_dsd_distribuicao WHERE ano_letivo_id=?) AS n_doc,
          (SELECT COUNT(*) FROM infodeqb_dsd_docente_ano WHERE ano_letivo_id=?) AS n_doc_snap
    ");
    $st->execute([$a['id'], $a['id'], $a['id'], $a['id']]);
    $stats[$a['id']] = $st->fetch();
}

$ucs = $db->query("
    SELECT u.id, u.designacao, p.sigla
    FROM infodeqb_dsd_uc u LEFT JOIN infodeqb_dsd_plano_estudo p ON u.plano_id=p.id
    WHERE u.ativo=1 ORDER BY p.ordem, u.designacao
")->fetchAll();

// Sugerir próximo ano letivo (n+1 a partir do mais recente)
$ultimoAno = $anos[0]['designacao'] ?? '';
$sugestao = '';
if (preg_match('/^(\d{4})\/(\d{4})$/', $ultimoAno, $m)) {
    $sugestao = ($m[1]+1) . '/' . ($m[2]+1);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-calendar-alt me-1"></i>Anos Letivos</div>
    <div class="page-sub">Gestão do histórico</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<div class="card">
  <div class="card-title">Anos Letivos Registados</div>
  <div class="table-wrap">
  <table class="data-table">
    <thead><tr>
      <th>Ano Letivo</th>
      <th style="text-align:center">Docentes<br><span style='font-size:10px;color:var(--gray-400)'>snapshot</span></th>
      <th style="text-align:center">Ocorrências</th>
      <th style="text-align:center">Distribuições</th>
      <th style="text-align:center">Estado / Ações</th>
    </tr></thead>
    <tbody>
    <?php foreach ($anos as $a): $s = $stats[$a['id']]; ?>
    <tr>
      <td><strong><?= esc($a['designacao']) ?></strong></td>
      <td style="text-align:center">
        <?= (int)$s['n_doc'] ?>
        <?php if ($s['n_doc_snap'] > 0): ?>
          <div style='font-size:10px;color:var(--green)'><i class="fas fa-check me-1"></i><?= (int)$s['n_doc_snap'] ?> snap</div>
        <?php else: ?>
          <div style='font-size:10px;color:var(--orange)'>sem snapshot</div>
        <?php endif; ?>
      </td>
      <td style="text-align:center"><?= (int)$s['n_ocs'] ?></td>
      <td style="text-align:center"><?= (int)$s['n_dist'] ?></td>
      <td style="text-align:center">
        <?php if ($a['ativo']): ?>
          <span class="badge badge-green"><i class="fas fa-check-circle me-1"></i>Activo</span>
        <?php else: ?>
          <div style="display:flex;gap:6px;justify-content:center">
            <form method="post">
              <input type="hidden" name="action" value="ativar">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="btn btn-secondary btn-xs">Activar</button>
            </form>
            <form method="post"
                  onsubmit="return confirm('Apagar este ano letivo e todos os dados associados (ocorrências, distribuições, snapshots)? Esta acção é irreversível.')">
              <input type="hidden" name="action" value="apagar">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <hr style="margin:16px 0;border-color:var(--gray-200)">
  <div class="card-title" style="margin-bottom:10px">Criar Novo Ano Letivo</div>
  <form method="post">
    <input type="hidden" name="action" value="criar">
    <div class="form-group" style="margin-bottom:10px">
      <label>Designação</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($sugestao): ?>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer">
          <input type="radio" name="designacao_tipo" value="sugestao" checked
                 onchange="document.getElementById('desig-custom').style.display='none';
                           document.getElementById('desig-val').value=this.dataset.val"
                 data-val="<?= esc($sugestao) ?>">
          <?= esc($sugestao) ?> <span style="font-size:11px;color:var(--gray-400)">(sugerido)</span>
        </label>
        <?php endif; ?>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer">
          <input type="radio" name="designacao_tipo" value="custom" <?= $sugestao ? '' : 'checked' ?>
                 onchange="document.getElementById('desig-custom').style.display='block'">
          Outro
        </label>
      </div>
      <div id="desig-custom" style="margin-top:8px;<?= $sugestao ? 'display:none' : '' ?>">
        <input type="text" id="desig-input" placeholder="ex: 2028/2029"
               oninput="document.getElementById('desig-val').value=this.value">
      </div>
      <input type="hidden" name="designacao" id="desig-val" value="<?= esc($sugestao) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Criar e Activar</button>
  </form>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-clipboard-list me-1"></i>Copiar entre Anos</div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px">
    Copia ocorrências (turmas, horas, estudantes) e, opcionalmente, a distribuição de docentes.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="copiar">
    <div class="form-grid">
      <div class="form-group">
        <label>DE (ano origem)</label>
        <select name="from_id" required>
          <option value="">— Seleccionar —</option>
          <?php foreach ($anos as $a): ?>
            <option value="<?= $a['id'] ?>"><?= esc($a['designacao']) ?><?= $a['ativo'] ? ' (activo)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>PARA (ano destino)</label>
        <select name="to_id" required>
          <option value="">— Seleccionar —</option>
          <?php foreach ($anos as $a): ?>
            <option value="<?= $a['id'] ?>"><?= esc($a['designacao']) ?><?= $a['ativo'] ? ' (activo)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div id="dest-status"></div>

    <div class="form-group" style="margin:14px 0 8px">
      <label>O que copiar?</label>
      <div style="display:flex;gap:16px;margin-top:6px">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:400">
          <input type="radio" name="modo" value="tudo" checked onchange="document.getElementById('uc-select').style.display='none'">
          Todas as UCs
        </label>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:400">
          <input type="radio" name="modo" value="parcial" onchange="document.getElementById('uc-select').style.display='block'">
          Só UCs seleccionadas
        </label>
      </div>
    </div>

    <div id="uc-select" style="display:none;margin-bottom:14px">
      <div style="max-height:200px;overflow-y:auto;border:1px solid var(--gray-300);border-radius:6px;padding:8px">
        <?php foreach ($ucs as $u): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:3px 0;font-weight:400;font-size:12px">
          <input type="checkbox" name="uc_ids[]" value="<?= $u['id'] ?>">
          <span class="badge badge-blue" style="font-size:10px"><?= esc($u['sigla'] ?? '–') ?></span>
          <?= esc($u['designacao']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-weight:500;font-size:13px">
      <input type="checkbox" name="inclui_distribuicao" value="1">
      Incluir também a distribuição de docentes (não só as ocorrências)
    </label>
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-weight:500;font-size:13px">
      <input type="checkbox" name="inclui_docentes" value="1" checked>
      Incluir dados de docentes (carreira, categoria, DETI, H SLEf) — copia snapshot do ano origem
    </label>

    <div class="form-actions" style="border-top:none;padding-top:0">
      <button type="submit" class="btn btn-success"
              data-confirm="Confirma? Ocorrências existentes no destino não serão duplicadas; distribuições serão acrescentadas.">
        <i class="fas fa-clipboard-list me-1"></i>Copiar
      </button>
    </div>
  </form>
</div>

</div>

<script>
const statsData = <?= json_encode($stats, JSON_HEX_QUOT) ?>;
const anosData  = <?= json_encode(array_column($anos, null, 'id'), JSON_HEX_QUOT) ?>;

function updateDestStatus() {
  const toId = document.querySelector('[name=to_id]')?.value;
  const el = document.getElementById('dest-status');
  if (!el || !toId || !statsData[toId]) { if(el) el.innerHTML=''; return; }
  const s = statsData[toId];
  const al = anosData[toId];
  const snap = parseInt(s.n_doc_snap) || 0;
  el.innerHTML = 
    '<div style="margin-top:8px;padding:8px 12px;background:var(--gray-50);border-radius:6px;font-size:12px">'
    + '<strong>' + (al?.designacao || toId) + '</strong> tem actualmente: '
    + '<span style="color:var(--blue)">' + s.n_ocs + ' ocorrências</span>, '
    + '<span style="color:var(--green)">' + s.n_dist + ' distribuições</span>'
    + (snap > 0 ? ', <span style="color:var(--green)"><i class="fas fa-check me-1"></i>' + snap + ' snapshots docentes</span>' 
               : ', <span style="color:var(--orange)"><i class="fas fa-exclamation-triangle me-1"></i>sem snapshot de docentes</span>')
    + '</div>';
}

document.querySelector('[name=to_id]')?.addEventListener('change', updateDestStatus);
document.querySelector('[name=from_id]')?.addEventListener('change', updateDestStatus);
window.addEventListener('DOMContentLoaded', updateDestStatus);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>