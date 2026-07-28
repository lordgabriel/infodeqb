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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <a href="distribuicao.php?ocorrencia=<?= $id ?>" class="btn btn-success" style="margin-left:auto">
      <i class="fas fa-plus me-1"></i>Atribuir Docente a esta Ocorrência →
    </a>
  </div>
</form>
</div>

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