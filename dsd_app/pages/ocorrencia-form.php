<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Ocorrência';
$activePage = 'ocorrencias';
$db = getDB();
$al = getAnoLetivoAtivo();

$id    = (int)($_GET['id']    ?? 0);
$isNew = isset($_GET['new']) && !$id;
$ucId  = (int)($_GET['uc_id'] ?? 0);
$ocor  = [];

if ($isNew && $ucId) {
    // New mode: load UC data only, no insert yet
    $stmt = $db->prepare("
        SELECT u.*, u.plano_id AS uc_plano_id,
               u.h_T AS uc_h_T, u.h_TP AS uc_h_TP, u.h_L AS uc_h_L,
               u.h_Sem AS uc_h_Sem, u.h_OT AS uc_h_OT,
               u.designacao AS uc_nome
        FROM infodeqb_dsd_uc u WHERE u.id=?
    ");
    $stmt->execute([$ucId]);
    $ucRow = $stmt->fetch() ?: [];
    if (!$ucRow) { flash('UC não encontrada.', 'error'); header('Location: ocorrencias.php'); exit; }
    // Build a fake $ocor from UC defaults
    $ocor = [
        'id'          => 0,
        'uc_id'       => $ucId,
        'plano_id'    => $ucRow['plano_id'],
        'uc_plano_id' => $ucRow['plano_id'],
        'uc_nome'     => $ucRow['designacao'],
        'codigo'      => $ucRow['codigo'],
        'semestre'    => $ucRow['semestre'],
        'ano'         => $ucRow['ano'],
        'estudantes'  => 0,
        'f_slef'      => 1,
        'outros_planos' => '',
        'observacoes' => '',
        'n_dist'      => 0,
        'horas_T'     => $ucRow['h_T'],   'n_turmas_T'   => 0,
        'horas_TP'    => $ucRow['h_TP'],  'n_turmas_TP'  => 0,
        'horas_L'     => $ucRow['h_L'],   'n_turmas_L'   => 0,
        'horas_Sem'   => $ucRow['h_Sem'], 'n_turmas_Sem' => 0,
        'horas_OT'    => $ucRow['h_OT'],  'n_turmas_OT'  => 0,
        'uc_h_T'      => $ucRow['h_T'],   'uc_h_TP'      => $ucRow['h_TP'],
        'uc_h_L'      => $ucRow['h_L'],   'uc_h_Sem'     => $ucRow['h_Sem'],
        'uc_h_OT'     => $ucRow['h_OT'],
    ];
} elseif ($id) {
    $stmt = $db->prepare("
        SELECT o.*, u.designacao AS uc_nome, u.codigo, u.semestre, u.ano,
               u.plano_id AS uc_plano_id,
               u.h_T AS uc_h_T, u.h_TP AS uc_h_TP, u.h_L AS uc_h_L,
               u.h_Sem AS uc_h_Sem, u.h_OT AS uc_h_OT,
               (SELECT COUNT(*) FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id = o.id) AS n_dist
        FROM infodeqb_dsd_uc_ocorrencia o
        JOIN infodeqb_dsd_uc u ON o.uc_id = u.id
        WHERE o.id=?
    ");
    $stmt->execute([$id]);
    $ocor = $stmt->fetch() ?: [];
    if (!$ocor) { flash('Ocorrência não encontrada.', 'error'); header('Location: ocorrencias.php'); exit; }
}

// ── Linhas de serviço: remover ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_line_id'])) {
    $db->prepare("DELETE FROM infodeqb_dsd_distribuicao WHERE id=? AND ocorrencia_id=?")
       ->execute([(int)$_POST['delete_line_id'], $id]);
    flash('Linha de serviço removida.');
    header('Location: ocorrencia-form.php?id=' . $id . '#linhas-servico'); exit;
}

// ── Linhas de serviço: adicionar / atualizar ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['docente_id'])) {
    $docId      = (int)$_POST['docente_id'];
    $editLineId = (int)($_POST['edit_line_id'] ?? 0);
    $dsdPD      = isset($_POST['dsd_por_docente']) ? 1 : 0;
    $regente    = isset($_POST['regente']) ? 1 : 0;
    $semanas    = num($_POST['semanas'] ?? 13);
    $hTese      = num($_POST['h_tese'] ?? 0);

    $numCols = ['turmas_T','horas_T','turmas_TP','horas_TP','turmas_L','horas_L',
                'turmas_Sem','horas_Sem','turmas_OT','horas_OT'];
    $vals = [];
    foreach ($numCols as $c) $vals[$c] = num($_POST[$c] ?? 0);

    if (!$id || !$docId) {
        flash('Docente é obrigatório.', 'error');
    } else {
        $fields = array_merge([
            'ano_letivo_id'   => $al['id'],
            'ocorrencia_id'   => $id,
            'uc_id'           => $ocor['uc_id'],
            'docente_id'      => $docId,
            'dsd_por_docente' => $dsdPD,
            'regente'         => $regente,
            'semanas'         => $semanas,
        ], $vals, ['h_tese' => $hTese]);

        try {
            if ($editLineId) {
                $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($fields)));
                $db->prepare("UPDATE infodeqb_dsd_distribuicao SET $sets WHERE id=?")
                   ->execute([...array_values($fields), $editLineId]);
                flash('Linha de serviço atualizada.');
            } else {
                $cols = implode(',', array_keys($fields));
                $phs  = implode(',', array_fill(0, count($fields), '?'));
                $db->prepare("INSERT INTO infodeqb_dsd_distribuicao ($cols) VALUES ($phs)")
                   ->execute(array_values($fields));
                flash('Linha de serviço adicionada.');
            }
        } catch (Exception $e) {
            flash('Erro: ' . $e->getMessage(), 'error');
        }
        header('Location: ocorrencia-form.php?id=' . $id . '#linhas-servico'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['n_turmas_T'])) {
    $fields = [
        'plano_id'      => ($_POST['plano_id'] ?? '') !== '' ? (int)$_POST['plano_id'] : null,
        'estudantes'    => (int)num($_POST['estudantes'] ?? 0),
        'f_slef'        => num($_POST['f_slef'] ?? 1),
        'outros_planos' => trim($_POST['outros_planos'] ?? ''),
        'semanas'       => 13,
        'n_turmas_T'    => num($_POST['n_turmas_T'] ?? 0),
        'n_turmas_TP'   => num($_POST['n_turmas_TP'] ?? 0),
        'n_turmas_L'    => num($_POST['n_turmas_L'] ?? 0),
        'n_turmas_Sem'  => num($_POST['n_turmas_Sem'] ?? 0),
        'n_turmas_OT'   => num($_POST['n_turmas_OT'] ?? 0),
        'horas_T'       => num($_POST['horas_T'] ?? 0),
        'horas_TP'      => num($_POST['horas_TP'] ?? 0),
        'horas_L'       => num($_POST['horas_L'] ?? 0),
        'horas_Sem'     => num($_POST['horas_Sem'] ?? 0),
        'horas_OT'      => num($_POST['horas_OT'] ?? 0),
        'observacoes'   => trim($_POST['observacoes'] ?? ''),
    ];
    // Validação: pelo menos um n_turmas > 0
    $totalTurmas = floatval($fields['n_turmas_T'])  + floatval($fields['n_turmas_TP'])
                 + floatval($fields['n_turmas_L'])  + floatval($fields['n_turmas_Sem'])
                 + floatval($fields['n_turmas_OT']);
    if ($totalTurmas <= 0) {
        flash('Tens de definir pelo menos um tipo de turmas.', 'error');
    } else {
    try {
        $ucIdPost = (int)($_POST['uc_id'] ?? $id);
        if (!$id && $ucIdPost) {
            // New mode: INSERT
            $cols = implode(',', array_keys($fields));
            $vals = implode(',', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO infodeqb_dsd_uc_ocorrencia (uc_id, ano_letivo_id, $cols) VALUES (?,?,$vals)")
               ->execute([$ucIdPost, $al['id'], ...array_values($fields)]);
            flash('Ocorrência criada.');
        } else {
            // Edit mode: UPDATE
            $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($fields)));
            $db->prepare("UPDATE infodeqb_dsd_uc_ocorrencia SET $sets WHERE id=?")
               ->execute([...array_values($fields), $id]);
            flash('Ocorrência atualizada.');
        }
        header('Location: ocorrencias.php'); exit;
    } catch (Exception $e) {
        flash('Erro: ' . $e->getMessage(), 'error');
    }
    }
}

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem, sigla")->fetchAll();

// Cálculos atuais (h/sem)
$necess = horasNecessarias($ocor);
$atribs = $id ? horasAtribuidas($db, $id) : ['total'=>0,'T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0];
$falta  = horasEmFalta($necess, $atribs);

// ── Linhas de serviço desta ocorrência ─────────────────────────
$docenteQ = $db->prepare("
    SELECT d.id, d.nome, c.designacao AS carreira
    FROM infodeqb_dsd_docente d
    LEFT JOIN infodeqb_dsd_docente_ano da2 ON da2.docente_id=d.id AND da2.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira c ON da2.carreira_id = c.id
    WHERE da2.ativo = 1
    ORDER BY c.ordem, d.nome
");
$docenteQ->execute([$al['id']]);
$docentes = $docenteQ->fetchAll();

$carreiras = $db->query("SELECT * FROM infodeqb_dsd_carreira ORDER BY ordem")->fetchAll();

$linhas = [];
if ($id) {
    $linhasQ = $db->prepare("
        SELECT d.*, doc.nome AS docente_nome
        FROM infodeqb_dsd_distribuicao d
        JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
        WHERE d.ocorrencia_id = ?
        ORDER BY d.regente DESC, doc.nome
    ");
    $linhasQ->execute([$id]);
    $linhas = $linhasQ->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-calendar-alt me-1"></i>Ocorrência: <?= esc($ocor['uc_nome']) ?></div>
    <div class="page-sub">
      Ano letivo <strong><?= esc($al['designacao']) ?></strong> &mdash;
      <?= esc($ocor['codigo'] ?? '') ?> &mdash;
      <?= esc($ocor['semestre'] ?? '') ?>
    </div>
  </div>
  <a href="ocorrencias.php" class="btn btn-secondary">← Voltar</a>
</div>

<!-- Painel de horas (h/sem) -->
<div class="card" style="background:linear-gradient(135deg, var(--blue-light), #fff);border-left:4px solid var(--blue)">
  <div class="card-title"><i class="fas fa-chart-bar me-1"></i>Estado das Horas <span style="font-size:11px;font-weight:400;color:var(--gray-500)">(h/sem equivalentes)</span></div>
  <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;font-size:12px">
    <?php foreach (['T','TP','L','Sem','OT','total'] as $k):
      $n = $necess[$k] ?? 0; $a = $atribs[$k] ?? 0; $f = $falta[$k] ?? 0;
      $cor = abs($f) < 0.01 ? 'var(--green)' : ($f > 0 ? 'var(--red)' : 'var(--orange)');
    ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:6px;padding:10px;text-align:center">
      <div style="font-weight:600;color:var(--gray-600);text-transform:uppercase;font-size:11px"><?= $k === 'total' ? 'TOTAL' : $k ?></div>
      <div style="font-size:18px;font-weight:700;margin:4px 0"><?= fmt($n, 1) ?></div>
      <div style="font-size:11px;color:var(--gray-500)">Atrib: <?= fmt($a, 1) ?></div>
      <div style="font-size:11px;font-weight:600;color:<?= $cor ?>">
        <?= abs($f) < 0.01 ? '<i class="fas fa-check-circle me-1"></i>OK' : ($f > 0 ? '<i class="fas fa-exclamation-triangle me-1"></i>Falta ' . fmt($f, 1) : '<i class="fas fa-exclamation-triangle me-1"></i>Excesso ' . fmt(-$f, 1)) ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($ocor['n_dist'] ?? 0): ?>
  <p style="font-size:11px;color:var(--gray-500);margin-top:10px">
    <i class="fas fa-lightbulb me-1"></i><em>Necessárias = nº turmas × h/semana</em> &middot;
    <em>Atribuídas = soma das distribuições, normalizadas pelas semanas</em>
  </p>
  <?php endif; ?>
</div>

<div class="card">
<form method="post" id="ocor-form">
<?php if (!$id && $ucId): ?>
<input type="hidden" name="uc_id" value="<?= $ucId ?>">
<?php endif; ?>

  <div class="card-title">Dados da Ocorrência</div>
  <div class="form-grid">

    <div class="form-group">
      <label>Plano de Estudos</label>
      <?php
        $planoAtual = null;
        foreach ($planos as $p) if ($p['id'] == ($ocor['plano_id'] ?? null)) $planoAtual = $p;
        if (!$planoAtual) foreach ($planos as $p) if ($p['id'] == ($ocor['uc_plano_id'] ?? null)) $planoAtual = $p;
      ?>
      <div style="padding:8px 12px;background:var(--gray-50);border:1px solid var(--gray-200);
                  border-radius:6px;font-weight:600">
        <?= esc($planoAtual['sigla'] ?? '–') ?>
        <?php if (!empty($planoAtual['designacao'])): ?>
        <span style="font-weight:400;color:var(--gray-500);margin-left:6px"><?= esc($planoAtual['designacao']) ?></span>
        <?php endif; ?>
      </div>
      <input type="hidden" name="plano_id" value="<?= (int)($ocor['plano_id'] ?? 0) ?>">
      <span class="form-hint">O plano é definido na criação e não pode ser alterado.</span>
    </div>

    <div class="form-group">
      <label>Nº de Estudantes</label>
      <input type="number" name="estudantes" min="0" value="<?= (int)($ocor['estudantes'] ?? 0) ?>">
    </div>

    <div class="form-group">
      <label>F SLEf (fator)</label>
      <input type="number" name="f_slef" step="any" min="0" max="1" data-step="0.01" value="<?= esc((string)($ocor['f_slef'] ?? 1)) ?>">
      <span class="form-hint">1 = 100%. Use &lt;1 em UCs partilhadas.</span>
    </div>

    <div class="form-group full">
      <label>Outros Planos (partilha)</label>
      <input type="text" name="outros_planos" value="<?= esc($ocor['outros_planos'] ?? '') ?>"
             placeholder="ex: (M.EQ + L.BIO)">
    </div>

  </div>

  <hr style="margin:20px 0;border-color:var(--gray-200)">
  <div class="card-title">Turmas e Horas de Contacto</div>
  <p class="form-hint" style="margin-bottom:10px">
    Nº de turmas a abrir e horas semanais de contacto. O total em h/sem corresponde ao serviço letivo necessário num semestre de 13 semanas.
  </p>

  <div class="table-wrap" style="border:1px solid var(--gray-200);border-radius:6px;overflow:hidden">
  <table class="data-table" style="margin:0">
    <thead>
      <tr>
        <th>Tipologia</th>
        <th style="text-align:center;width:130px">Nº Turmas</th>
        <th style="text-align:center;width:130px">H/semana</th>
        <th style="text-align:right;width:130px">Total (h/sem)</th>
      </tr>
    </thead>
    <tbody>
      <?php
$tipos = [
        ['T',   'T – Teóricas',          'uc_h_T'],
        ['TP',  'TP – Teórico-Práticas', 'uc_h_TP'],
        ['L',   'L – Laboratoriais',     'uc_h_L'],
        ['Sem', 'S – Seminários',        'uc_h_Sem'],
        ['OT',  'OT – Orient. Tutorial', 'uc_h_OT'],
      ];
      foreach ($tipos as [$k, $label, $ucField]):
        $nT    = num($ocor["n_turmas_$k"] ?? 0);
        $hT    = num($ocor["horas_$k"]    ?? 0);
        $hUC   = num($ocor[$ucField]       ?? 0);
        $total = $nT * $hT;
        $isDiff = $hUC > 0 && abs($hT - $hUC) > 0.001;
      ?>
      <tr>
        <td><strong><?= $label ?></strong></td>
        <td style="text-align:center">
          <input type="number" name="n_turmas_<?= $k ?>" step="any" min="0" data-step="0.5"
                 value="<?= esc((string)$nT) ?>"
                 style="width:80px;text-align:right" class="ocor-input">
        </td>
        <td style="text-align:center">
          <input type="number" name="horas_<?= $k ?>" step="any" min="0" data-step="0.25"
                 value="<?= esc((string)$hT) ?>"
                 style="width:80px;text-align:right" class="ocor-input"
                 title="<?= $hUC > 0 ? 'Padrão da UC: '.fmt($hUC,2).' h' : '' ?>">
          <?php if ($isDiff): ?>
          <span style="font-size:10px;color:var(--orange)" title="Diferente do padrão da UC (<?= fmt($hUC,2) ?> h)"><i class="fas fa-exclamation-triangle"></i></span>
          <?php elseif ($hUC > 0): ?>
          <span style="font-size:10px;color:var(--gray-400)">(<?= fmt($hUC,2) ?>h)</span>
          <?php endif; ?>
        </td>
        <td class="num" id="tot_<?= $k ?>"><strong><?= fmt($total, 2) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--gray-50);font-weight:700">
        <td colspan="3" style="text-align:right">Total:</td>
        <td class="num" id="tot_GERAL" style="color:var(--blue);font-size:14px">
          <?= fmt($necess['total'], 2) ?> h/sem
        </td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>

  <div class="form-group full" style="margin-top:14px">
    <label>Observações</label>
    <textarea name="observacoes"><?= esc($ocor['observacoes'] ?? '') ?></textarea>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar Ocorrência</button>
    <a href="ocorrencias.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<?php if ($id): ?>
<div class="card" id="linhas-servico">
  <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <span><i class="fas fa-chalkboard-teacher me-1"></i>Linhas de Serviço</span>
    <?php if ($linhas): ?>
    <button type="button" class="btn btn-secondary btn-sm" onclick="openCopyModal()">
      <i class="fas fa-clipboard-list me-1"></i>Copiar para UCs partilhadas
    </button>
    <?php endif; ?>
  </div>

  <?php if ($linhas): ?>
  <?php foreach ($linhas as $l): ?>
  <form method="post" id="lineform<?= (int)$l['id'] ?>"></form>
  <?php endforeach; ?>
  <div class="table-wrap" style="border:1px solid var(--gray-200);border-radius:6px;overflow:hidden;margin-bottom:20px">
  <table class="data-table" style="margin:0;font-size:12px">
    <thead>
      <tr>
        <th>Docente</th>
        <th style="text-align:center;width:70px">Sem.</th>
        <th style="text-align:center">T/H.T</th>
        <th style="text-align:center">T/H.TP</th>
        <th style="text-align:center">T/H.L</th>
        <th style="text-align:center">T/H.Sem</th>
        <th style="text-align:center">T/H.OT</th>
        <th style="text-align:center;width:70px">H Tese</th>
        <th style="text-align:center;width:50px">DSD</th>
        <th style="text-align:center;width:50px">R</th>
        <th style="width:70px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($linhas as $l): $fid = 'lineform' . (int)$l['id']; ?>
    <tr id="servico-<?= (int)$l['id'] ?>" class="linha-servico-row">
      <td><?= esc($l['docente_nome']) ?>
        <input type="hidden" name="edit_line_id" value="<?= (int)$l['id'] ?>" form="<?= $fid ?>">
        <input type="hidden" name="docente_id" value="<?= (int)$l['docente_id'] ?>" form="<?= $fid ?>">
      </td>
      <td><input type="number" name="semanas" step="0.5" min="0" max="30" value="<?= esc((string)$l['semanas']) ?>" form="<?= $fid ?>" style="width:55px;text-align:right"></td>
      <?php foreach (['T','TP','L','Sem','OT'] as $t): ?>
      <td style="white-space:nowrap">
        <input type="number" name="turmas_<?= $t ?>" step="0.5" min="0" value="<?= esc((string)$l["turmas_$t"]) ?>" form="<?= $fid ?>" style="width:44px;text-align:right">/<input type="number" name="horas_<?= $t ?>" step="0.5" min="0" value="<?= esc((string)$l["horas_$t"]) ?>" form="<?= $fid ?>" style="width:44px;text-align:right">
      </td>
      <?php endforeach; ?>
      <td><input type="number" name="h_tese" step="0.5" min="0" value="<?= esc((string)$l['h_tese']) ?>" form="<?= $fid ?>" style="width:55px;text-align:right"></td>
      <td style="text-align:center"><input type="checkbox" name="dsd_por_docente" form="<?= $fid ?>" <?= $l['dsd_por_docente'] ? 'checked' : '' ?>></td>
      <td style="text-align:center"><input type="checkbox" name="regente" form="<?= $fid ?>" <?= $l['regente'] ? 'checked' : '' ?>></td>
      <td style="white-space:nowrap;text-align:center">
        <button type="submit" form="<?= $fid ?>" class="btn btn-primary btn-xs" title="Guardar"><i class="fas fa-save"></i></button>
        <form method="post" style="display:inline">
          <input type="hidden" name="delete_line_id" value="<?= (int)$l['id'] ?>">
          <button type="submit" class="btn btn-danger btn-xs" data-confirm="Remover esta linha de serviço?" title="Remover"><i class="fas fa-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p style="color:var(--gray-400);font-size:13px;margin-bottom:16px">Ainda sem linhas de serviço atribuídas.</p>
  <?php endif; ?>

  <div style="border-top:1px solid var(--gray-200);padding-top:14px">
    <div style="font-weight:600;font-size:13px;margin-bottom:8px"><i class="fas fa-plus me-1"></i>Adicionar linha</div>
    <form method="post">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:8px">
      <div class="form-group" style="margin:0;min-width:150px">
        <label style="font-size:11px">Carreira</label>
        <select id="add-carreira" onchange="filterAddDocentes()">
          <option value="">Todas</option>
          <?php foreach ($carreiras as $c): ?>
          <option value="<?= esc($c['designacao']) ?>"><?= esc($c['designacao']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:220px">
        <label style="font-size:11px">Docente *</label>
        <select name="docente_id" id="add-docente" required>
          <option value="">— Selecionar —</option>
          <?php foreach ($docentes as $d): ?>
          <option value="<?= $d['id'] ?>" data-carreira="<?= esc($d['carreira'] ?? '') ?>"><?= esc($d['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;width:80px">
        <label style="font-size:11px">Semanas</label>
        <input type="number" name="semanas" step="0.5" min="0" max="30" value="13" id="add-semanas" class="add-line-input">
      </div>
      <label style="display:flex;align-items:center;gap:5px;font-size:12px;white-space:nowrap">
        <input type="checkbox" name="dsd_por_docente" checked>Conta no DSD
      </label>
      <label style="display:flex;align-items:center;gap:5px;font-size:12px;white-space:nowrap">
        <input type="checkbox" name="regente"><i class="fas fa-star"></i> Regente (R)
      </label>
    </div>
    <div style="display:grid;grid-template-columns:repeat(5,1fr) 90px;gap:8px;align-items:end;margin-bottom:10px">
      <?php foreach ([["T","T – Teóricas"],["TP","TP – T.Práticas"],["L","L – Laboratoriais"],["Sem","S – Seminários"],["OT","OT – Equiv."]] as [$k,$lbl]): ?>
      <div class="form-group" style="margin:0">
        <label style="font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= $lbl ?>"><?= $lbl ?></label>
        <div style="display:flex;gap:4px">
          <input type="number" name="turmas_<?= $k ?>" step="0.5" min="0" value="0" class="add-line-input" style="width:100%;text-align:right">
          <input type="number" name="horas_<?= $k ?>" step="0.5" min="0" value="0" class="add-line-input" style="width:100%;text-align:right">
        </div>
      </div>
      <?php endforeach; ?>
      <div class="form-group" style="margin:0">
        <label style="font-size:11px">H Tese</label>
        <input type="number" name="h_tese" step="0.5" min="0" value="0" class="add-line-input">
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Adicionar linha</button>
      <span id="add-line-calc" style="font-size:12px;color:var(--blue)"></span>
    </div>
    </form>
  </div>
</div>

<!-- Modal: copiar distribuição para UCs partilhadas -->
<div id="modal-copy" class="modal-overlay" style="display:none">
 <div class="modal-box">
  <div class="modal-header">
   <div class="modal-title"><i class="fas fa-clipboard-list me-1"></i>Copiar para UCs partilhadas</div>
   <button class="modal-close" onclick="closeCopyModal()"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
   <p style="font-size:12px;color:var(--gray-600);margin-bottom:10px">
     Seleciona as UCs destino. A distribuição desta ocorrência será copiada com <strong>DSD <i class="fas fa-times-circle"></i></strong>
     (não duplica horas) e substituirá toda a distribuição existente nessas UCs.
   </p>
   <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
     <select id="copy-plano-filter" onchange="filterCopyList()" style="min-width:120px;font-size:12px">
       <option value="">Todos os planos</option>
     </select>
     <input type="text" id="copy-search" oninput="filterCopyList()" placeholder="Pesquisar UC…" style="flex:1;font-size:12px">
   </div>
   <div id="copy-uc-list" style="display:flex;flex-direction:column;gap:6px;max-height:240px;overflow-y:auto;margin-bottom:12px">
     <div style="color:var(--gray-400);font-size:12px">Seleciona primeiro para carregar.</div>
   </div>
   <div style="display:flex;gap:8px;align-items:center">
     <button type="button" class="btn btn-primary btn-sm" onclick="executeCopyToShared()"><i class="fas fa-clipboard-list me-1"></i>Copiar agora</button>
     <button type="button" class="btn btn-secondary btn-sm" onclick="closeCopyModal()">Cancelar</button>
     <span id="copy-status" style="font-size:12px;color:var(--gray-500)"></span>
   </div>
  </div>
 </div>
</div>
<?php endif; ?>

<script>
function filterAddDocentes() {
  const car = document.getElementById('add-carreira').value;
  const sel = document.getElementById('add-docente');
  Array.from(sel.options).forEach(o => {
    if (!o.value) return;
    o.hidden = !!(car && o.dataset.carreira !== car);
  });
  if (sel.selectedOptions[0]?.hidden) sel.value = '';
}
function refreshAddLineCalc() {
  const el = document.getElementById('add-line-calc');
  if (!el) return;
  const sem = parseFloat(document.getElementById('add-semanas').value) || 13;
  let hs = 0, hslef = 0;
  ['T','TP','L','Sem','OT'].forEach(k => {
    const t = parseFloat(document.querySelector(`[name="turmas_${k}"].add-line-input`)?.value) || 0;
    const h = parseFloat(document.querySelector(`[name="horas_${k}"].add-line-input`)?.value) || 0;
    const contrib = t * h * sem / 13;
    hs += contrib;
    if (k !== 'OT') hslef += contrib;
  });
  el.textContent = 'H/s: ' + hs.toFixed(2) + ' | H SLEf (antes de F SLEf): ' + hslef.toFixed(2);
}
document.querySelectorAll('.add-line-input').forEach(el => el.addEventListener('input', refreshAddLineCalc));
refreshAddLineCalc();

<?php if ($id && $linhas): ?>
// ── Copiar para UCs partilhadas ──────────────────────────────
const copySrcOcorId   = <?= (int)$id ?>;
const copyAnoLetivoId = <?= (int)$al['id'] ?>;
const linhasParaCopia = <?= json_encode(array_map(function($l) {
    return [
        'docente_id' => (int)$l['docente_id'],
        'reg'        => (int)$l['regente'],
        'tT'  => (float)$l['turmas_T'],   'hT'  => (float)$l['horas_T'],
        'tTP' => (float)$l['turmas_TP'],  'hTP' => (float)$l['horas_TP'],
        'tL'  => (float)$l['turmas_L'],   'hL'  => (float)$l['horas_L'],
        'tSem'=> (float)$l['turmas_Sem'], 'hSem'=> (float)$l['horas_Sem'],
        'tOT' => (float)$l['turmas_OT'],  'hOT' => (float)$l['horas_OT'],
        'htese' => (float)$l['h_tese'],
    ];
}, $linhas)) ?>;

function openCopyModal() {
  document.getElementById('modal-copy').style.display = 'flex';
  document.getElementById('copy-status').textContent = '';
  document.getElementById('copy-search').value = '';
  document.getElementById('copy-plano-filter').value = '';

  document.getElementById('copy-uc-list').innerHTML = '<div style="color:var(--gray-400);font-size:12px">A carregar…</div>';
  fetch('ajax-dist.php?list_ocors=1&exclude=' + copySrcOcorId + '&_=' + Date.now())
    .then(r => r.json())
    .then(data => {
      if (!data.ocors || !data.ocors.length) {
        document.getElementById('copy-uc-list').innerHTML = '<div style="color:var(--gray-400);font-size:12px">Sem outras ocorrências neste ano.</div>';
        return;
      }
      const planos = [...new Set(data.ocors.map(o => o.plano).filter(Boolean))].sort();
      const sel = document.getElementById('copy-plano-filter');
      sel.innerHTML = '<option value="">Todos os planos</option>' +
        planos.map(p => `<option value="${p}">${p}</option>`).join('');
      window._copyOcors = data.ocors;
      renderCopyList();
    })
    .catch(() => { document.getElementById('copy-uc-list').innerHTML = '<div style="color:var(--red)">Erro ao carregar.</div>'; });
}

function closeCopyModal() {
  document.getElementById('modal-copy').style.display = 'none';
}

function filterCopyList() {
  const checked = new Set([...document.querySelectorAll('.copy-dest:checked')].map(x => x.value));
  renderCopyList();
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
      ${o.n_dist > 0 ? '<span style="font-size:10px;color:var(--orange);margin-left:4px"><i class="fas fa-exclamation-triangle me-1"></i>tem '+o.n_dist+' linha(s)</span>' : ''}
    </label>`;

  list.innerHTML =
    selected.map(o => renderItem(o, true)).join('') +
    (selected.length && filtered.length ? '<hr style="margin:4px 0;border-color:var(--gray-200)">' : '') +
    filtered.map(o => renderItem(o, false)).join('');
}

function executeCopyToShared() {
  const dests = [...document.querySelectorAll('.copy-dest:checked')].map(c => c.value);
  if (!dests.length) { alert('Seleciona pelo menos uma UC destino.'); return; }
  if (!confirm('Substituir toda a distribuição nas ' + dests.length + ' UC(s) seleccionadas?\nAs linhas existentes serão apagadas.')) return;

  const copyLines = linhasParaCopia.map(l => ({...l, dsd: 0}));

  document.getElementById('copy-status').textContent = 'A copiar…';
  fetch('ajax-copy-dist.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({src_ocor: copySrcOcorId, dest_ocors: dests, lines: copyLines,
                          ano_letivo_id: copyAnoLetivoId})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      document.getElementById('copy-status').innerHTML = '<i class="fas fa-check-circle me-1"></i>Copiado para ' + d.copied + ' UC(s)';
      setTimeout(() => window.location.reload(), 800);
    } else {
      document.getElementById('copy-status').innerHTML = '<i class="fas fa-times-circle me-1"></i>' + d.error;
    }
  })
  .catch(() => document.getElementById('copy-status').innerHTML = '<i class="fas fa-times-circle me-1"></i>Erro ao copiar');
}

if (location.hash.startsWith('#servico-')) {
  const target = document.getElementById(location.hash.slice(1));
  if (target) {
    target.scrollIntoView({block: 'center'});
    target.style.transition = 'background-color .3s';
    target.style.backgroundColor = 'var(--blue-light)';
    setTimeout(() => { target.style.backgroundColor = ''; }, 1800);
  }
}
<?php endif; ?>
</script>

<script>
function recalc() {
  let tot = 0;
  ['T','TP','L','Sem','OT'].forEach(k => {
    const n = parseFloat(document.querySelector(`[name=n_turmas_${k}]`).value) || 0;
    const h = parseFloat(document.querySelector(`[name=horas_${k}]`).value) || 0;
    const t = n * h;
    document.getElementById(`tot_${k}`).innerHTML = '<strong>' + t.toFixed(2) + '</strong>';
    tot += t;
  });
  document.getElementById('tot_GERAL').textContent = tot.toFixed(2) + ' h/sem';
}
document.querySelectorAll('.ocor-input').forEach(el => el.addEventListener('input', recalc));
</script>

<script>
// Apply data-step for ▲▼ keyboard while allowing free typing
document.querySelectorAll('input[data-step]').forEach(inp => {
  inp.addEventListener('keydown', e => {
    if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
    e.preventDefault();
    const step = parseFloat(inp.dataset.step) || 1;
    const val  = parseFloat(inp.value) || 0;
    const min  = parseFloat(inp.min ?? '-Infinity');
    inp.value  = Math.max(min, parseFloat((val + (e.key === 'ArrowUp' ? step : -step)).toFixed(4)));
  });
});
</script>
<script>
document.getElementById('ocor-form').addEventListener('submit', function(e) {
  const inputs = document.querySelectorAll('[name^="n_turmas_"]');
  const allZero = Array.from(inputs).every(i => (parseFloat(i.value) || 0) === 0);
  if (allZero) {
    e.preventDefault();
    alert('Tens de definir pelo menos um tipo de turmas antes de guardar.');
    return;
  }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>