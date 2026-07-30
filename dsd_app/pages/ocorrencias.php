<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Ocorrências';
$activePage = 'ocorrencias';
$db = getDB();
$al = getAnoLetivoAtivo();
$fp = $_GET['fp'] ?? $_POST['fp'] ?? '';
$fu = $_GET['fu'] ?? $_POST['fu'] ?? '';

// ── Criar ocorrência ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_uc_id'])) {
    $ucId = (int)$_POST['criar_uc_id'];
    // Redirect to form in "new" mode - only creates on save
    header('Location: ocorrencia-form.php?new=1&uc_id=' . $ucId); exit;
}

// ── Apagar individual ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->prepare("DELETE FROM infodeqb_dsd_uc_ocorrencia WHERE id=?")->execute([(int)$_POST['delete_id']]);
    flash('Ocorrência removida.');
    $qs = http_build_query(array_filter(['fp'=>$fp,'fu'=>$fu]));
    header('Location: ocorrencias.php' . ($qs ? '?'.$qs : '')); exit;
}

// ── Apagar múltiplas ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'])) {
    $ids = array_filter(array_map('intval', (array)$_POST['delete_ids']));
    foreach ($ids as $did)
        $db->prepare("DELETE FROM infodeqb_dsd_uc_ocorrencia WHERE id=?")->execute([$did]);
    flash(count($ids) . ' ocorrência(s) removida(s).');
    $qs = http_build_query(array_filter(['fp'=>$fp,'fu'=>$fu]));
    header('Location: ocorrencias.php' . ($qs ? '?'.$qs : '')); exit;
}

// ── Dados ─────────────────────────────────────────────────────
$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem, sigla")->fetchAll();
$ocs = $db->prepare("
    SELECT o.id, o.uc_id, o.ano_letivo_id, o.estudantes, o.f_slef,
           o.outros_planos, o.semanas,
           o.n_turmas_T, o.n_turmas_TP, o.n_turmas_L,
           COALESCE(o.n_turmas_Sem, 0) AS n_turmas_Sem,
           o.n_turmas_OT,
           o.horas_T, o.horas_TP, o.horas_L,
           COALESCE(o.horas_Sem, 0) AS horas_Sem,
           o.horas_OT,
           u.designacao AS uc_nome, u.codigo, u.semestre,
           p.sigla AS plano_sigla
    FROM infodeqb_dsd_uc_ocorrencia o
    JOIN infodeqb_dsd_uc u ON o.uc_id = u.id
    LEFT JOIN infodeqb_dsd_plano_estudo p ON COALESCE(o.plano_id, u.plano_id) = p.id
    WHERE o.ano_letivo_id = ?
    ORDER BY p.ordem, u.semestre, u.designacao
");
$ocs->execute([$al['id']]);
$ocsList = $ocs->fetchAll();

$ucsSem = $db->prepare("
    SELECT u.id, u.designacao, p.sigla
    FROM infodeqb_dsd_uc u
    LEFT JOIN infodeqb_dsd_plano_estudo p ON u.plano_id = p.id
    WHERE u.ativo = 1 AND NOT EXISTS (
        SELECT 1 FROM infodeqb_dsd_uc_ocorrencia o
        WHERE o.uc_id = u.id AND o.ano_letivo_id = ?
    )
    ORDER BY p.ordem, u.designacao
");
$ucsSem->execute([$al['id']]);
$semOcor = $ucsSem->fetchAll();

// Ocorrências sem SD atribuído
$allDistOcors = $db->prepare("SELECT DISTINCT ocorrencia_id FROM infodeqb_dsd_distribuicao WHERE ano_letivo_id=? AND ocorrencia_id IS NOT NULL");
$allDistOcors->execute([$al['id']]);
$ocorsWithSD = [];
foreach ($allDistOcors->fetchAll(PDO::FETCH_COLUMN) as $oid) $ocorsWithSD[(int)$oid] = true;
$semSD = array_filter($ocsList, function($o) use ($ocorsWithSD) {
    return !isset($ocorsWithSD[(int)$o['id']]);
});

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-calendar-alt me-1"></i>Ocorrências</div>
    <div class="page-sub"><?= count($ocsList) ?> ocorrências · <?= esc($al['designacao']) ?></div>
  </div>
</div>

<div style="display:flex;gap:12px;align-items:flex-start">

<!-- Coluna esquerda: treeviews -->
<div style="width:240px;flex-shrink:0;position:sticky;top:70px;display:flex;flex-direction:column;gap:10px">

<?php if ($semSD):
  // Group by plano
  $semSdByPlano = [];
  foreach ($semSD as $o) {
      $p = $o['plano_sigla'] ?? '–';
      $semSdByPlano[$p][] = $o;
  }
?>
<div class="card" style="margin-bottom:12px;border-left:4px solid var(--red)">
  <div style="font-weight:600;font-size:13px;color:var(--red);margin-bottom:10px">
    <i class="fas fa-exclamation-triangle me-1"></i><?= count($semSD) ?> UC(s) com ocorrência mas sem serviço atribuído
  </div>
  <?php foreach ($semSdByPlano as $plano => $ucs): ?>
  <div style="margin-bottom:4px">
    <div onclick="toggleOcPlanoSD('ocpsd-<?= md5($plano) ?>')"
         style="display:flex;align-items:center;gap:6px;cursor:pointer;
                padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;
                background:var(--gray-100);color:var(--gray-700)">
      <span id="icon-ocpsd-<?= md5($plano) ?>" style="font-size:10px">▶</span>
      <span class="badge badge-blue" style="font-size:10px"><?= esc($plano) ?></span>
      <span style="font-weight:400;color:var(--gray-500)">(<?= count($ucs) ?>)</span>
    </div>
    <div id="ocpsd-<?= md5($plano) ?>" style="display:none;padding:4px 0 4px 16px">
      <?php foreach ($ucs as $o): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;
                  padding:3px 6px;font-size:12px;border-bottom:1px solid var(--gray-100)">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px"
              title="<?= esc($o['uc_nome']) ?>"><?= esc($o['uc_nome']) ?></span>
        <span style="display:flex;gap:3px;flex-shrink:0;margin-left:6px">
          <a href="ocorrencia-form.php?id=<?= (int)$o['id'] ?>#linhas-servico"
             class="btn btn-primary btn-xs">+ SD</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="delete_id" value="<?= (int)$o['id'] ?>">
            <button class="btn btn-danger btn-xs"
                    data-confirm="Apagar esta ocorrência?"
                    title="Apagar ocorrência"><i class="fas fa-trash"></i></button>
          </form>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<script>
function toggleOcPlanoSD(id) {
  const el = document.getElementById(id);
  const icon = document.getElementById('icon-' + id);
  const open = el.style.display === 'none';
  el.style.display = open ? '' : 'none';
  if (icon) icon.textContent = open ? '▼' : '▶';
}
</script>
<?php endif; ?>

<?php if ($semOcor):
  $byPlanoOcor = [];
  foreach ($semOcor as $u) $byPlanoOcor[$u['sigla'] ?? '–'][] = $u;
?>
<div class="card" style="padding:10px 12px;border-left:4px solid var(--orange)">
  <div style="font-weight:600;font-size:12px;color:var(--orange);margin-bottom:8px">
    <i class="fas fa-plus me-1"></i>Sem ocorrência (<?= count($semOcor) ?>)
  </div>
  <?php foreach ($byPlanoOcor as $plano => $ucs): ?>
  <div style="margin-bottom:4px">
    <div onclick="toggleOcPlano('opsd-<?= md5($plano) ?>')"
         style="display:flex;align-items:center;gap:5px;cursor:pointer;
                padding:3px 6px;border-radius:4px;font-size:12px;font-weight:600;
                background:var(--gray-100);color:var(--gray-700)">
      <span id="icon-opsd-<?= md5($plano) ?>" style="font-size:10px">▶</span>
      <span class="badge badge-blue" style="font-size:10px"><?= esc($plano) ?></span>
      <span style="font-weight:400;color:var(--gray-500)">(<?= count($ucs) ?>)</span>
    </div>
    <div id="opsd-<?= md5($plano) ?>" style="display:none;padding:4px 0 4px 16px">
      <?php foreach ($ucs as $u): ?>
      <div style="padding:3px 6px;font-size:11px;border-bottom:1px solid var(--gray-100);
                  display:flex;align-items:center;justify-content:space-between;gap:4px">
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
              title="<?= esc($u['designacao']) ?>"><?= esc($u['designacao']) ?></span>
        <form method="post" style="flex-shrink:0">
          <input type="hidden" name="criar_uc_id" value="<?= $u['id'] ?>">
          <button class="btn btn-primary btn-xs" type="submit">+ Criar</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<script>
function toggleOcPlano(id) {
  const el = document.getElementById(id);
  const icon = document.getElementById('icon-' + id);
  const open = el.style.display === 'none';
  el.style.display = open ? '' : 'none';
  if (icon) icon.textContent = open ? '▼' : '▶';
}
</script>
<?php endif; ?>

</div><!-- end left column -->

<!-- Coluna direita: filtros + tabela -->
<div style="flex:1;min-width:0">
<div class="card" style="padding:10px 16px;margin-bottom:12px">
  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="form-group" style="margin:0;min-width:140px">
      <label>Plano</label>
      <select id="filtro-plano" onchange="filtrarOcor()">
        <option value="">Todos</option>
        <?php
        $planosUsados = array_unique(array_column($ocsList, 'plano_sigla'));
        sort($planosUsados);
        foreach ($planosUsados as $ps): if (!$ps) continue; ?>
        <option value="<?= esc($ps) ?>" <?= $fp===$ps?'selected':'' ?>><?= esc($ps) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px">
      <label>Pesquisar UC</label>
      <input type="text" id="filtro-uc" placeholder="Nome da UC…" oninput="filtrarOcor()"
             value="<?= esc($fu) ?>" style="width:100%">
    </div>
    <button type="button" class="btn btn-secondary btn-sm"
            onclick="document.getElementById('filtro-plano').value='';
                     document.getElementById('filtro-uc').value='';
                     filtrarOcor()"><i class="fas fa-times me-1"></i>Limpar</button>
    <span style="border-left:1px solid var(--gray-200);margin:0 4px"></span>
    <button type="button" class="btn btn-secondary btn-sm" onclick="colapsarTodos()">⊟ Planos</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="expandirTodos()">⊞ Planos</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="colapsarSemestres()">⊟ Semestres</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="expandirSemestres()">⊞ Semestres</button>
    <span id="filtro-count" style="font-size:12px;color:var(--gray-500);align-self:center"></span>
  </div>
</div>

<!-- Bulk action bar -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;min-height:32px">
  <form method="post" id="bulk-form">
    <input type="hidden" name="fp" id="h-fp" value="<?= esc($fp) ?>">
    <input type="hidden" name="fu" id="h-fu" value="<?= esc($fu) ?>">
    <button type="button" class="btn btn-danger btn-sm" onclick="apagarSelecionados()"
            id="btn-apagar" style="display:none"><i class="fas fa-trash me-1"></i>Apagar seleccionados</button>
  </form>
  <span id="sel-count" style="font-size:12px;color:var(--gray-500)"></span>
</div>

<div class="card" style="padding:0">
<div class="table-wrap">
<table class="data-table" id="tbl-ocor" style="font-size:12px">
  <thead>
    <tr>
      <th style="width:28px;text-align:center">
        <input type="checkbox" id="chk-all" onchange="toggleAll(this)">
      </th>
      <th>UC</th>
      <th style="text-align:right">Alunos</th>
      <th style="text-align:right">F</th>
      <th style="text-align:center">T</th>
      <th style="text-align:center">TP</th>
      <th style="text-align:center">L</th>
      <th style="text-align:center">S</th>
      <th style="text-align:center">OT</th>
      <th style="text-align:right">Nec.</th>
      <th style="text-align:right">Atr.</th>
      <th style="text-align:right">Δ</th>
      <th style="text-align:center">Ações</th>
    </tr>
  </thead>
  <tbody>
  <?php
  // Group by plano → semestre
  $tree = [];
  foreach ($ocsList as $o) {
      $p   = $o['plano_sigla'] ?? '–';
      $sem = $o['semestre'] ?: 'A';
      $tree[$p][$sem][] = $o;
  }
  foreach ($tree as $plano => $sems):
  ?>
  <!-- Plano header -->
  <tr class="section-plano" data-plano="<?= esc(strtolower($plano)) ?>"
      style="background:var(--gray-200);cursor:pointer"
      onclick="togglePlanoOcor('pl-<?= md5($plano) ?>')">
    <td colspan="13" style="padding:6px 10px;font-weight:700;font-size:13px;color:var(--gray-800)">
      <span id="icon-pl-<?= md5($plano) ?>">▼</span>
      <span class="badge badge-blue" style="margin-left:4px"><?= esc($plano) ?></span>
      <span style="font-weight:400;font-size:11px;margin-left:6px;color:var(--gray-500)">
        <?= array_sum(array_map('count', $sems)) ?> ocorrência(s)
      </span>
    </td>
  </tr>
  <?php foreach ($sems as $sem => $ocs): ?>
  <!-- Semestre subheader -->
  <?php $semKey = 'sem-'.md5($plano.'-'.$sem); ?>
  <tr class="section-sem pl-<?= md5($plano) ?>" data-plano="<?= esc(strtolower($plano)) ?>"
      style="background:var(--gray-100);cursor:pointer"
      onclick="toggleSemOcor('<?= $semKey ?>')">
    <td colspan="13" style="padding:4px 10px 4px 24px;font-size:11px;font-weight:600;color:var(--gray-600)">
      <span id="icon-<?= $semKey ?>" style="font-size:10px">▼</span>
      <?= $sem === 'A' ? 'Anual' : $sem.'º Semestre' ?>
      <span style="font-weight:400">(<?= count($ocs) ?>)</span>
    </td>
  </tr>
  <?php foreach ($ocs as $o):
    try {
      $necess = horasNecessarias($o);
      $atribs = horasAtribuidas($db, (int)$o['id']);
    } catch (Throwable $e) {
      $necess = ['total'=>0]; $atribs = ['total'=>0];
    }
    $falta = $necess['total'] - $atribs['total'];
    $corFalta = abs($falta) < 0.01 ? 'var(--green)' : ($falta > 0 ? 'var(--red)' : 'var(--blue)');
    $tipoVals = [];
    foreach (['T','TP','L','Sem','OT'] as $k) {
        $nT = (float)$o['n_turmas_'.$k];
        $hT = (float)$o['horas_'.$k];
        $tipoVals[$k] = $nT > 0 ? fmt($nT,1).'×'.fmt($hT,1) : '–';
    }
  ?>
  <tr class="pl-<?= md5($plano) ?> <?= $semKey ?>"
      data-plano="<?= esc(strtolower($plano)) ?>"
      data-sem="<?= esc(strtolower($sem)) ?>"
      data-uc="<?= esc(strtolower($o['uc_nome'])) ?>">
    <td style="text-align:center;padding-left:24px">
      <input type="checkbox" class="row-chk" value="<?= $o['id'] ?>" onchange="updateSel()">
    </td>
    <td>
      <a href="#" onclick="showDist(<?= (int)$o['id'] ?>, <?= htmlspecialchars(json_encode($o['uc_nome']), ENT_QUOTES) ?>, event)"
         style="font-weight:600;text-decoration:none;color:inherit;border-bottom:1px dotted var(--gray-400)">
        <?= esc($o['uc_nome']) ?>
      </a>
      <?php if ($o['outros_planos']): ?>
        <span style="font-size:10px;color:var(--orange)"><i class="fas fa-exclamation-triangle me-1"></i><?= esc($o['outros_planos']) ?></span>
      <?php endif; ?>
    </td>
    <td class="num"><?= (int)$o['estudantes'] ?: '–' ?></td>
    <td class="num"><?= fmt((float)$o['f_slef'], 2) ?></td>
    <td class="num" style="font-size:11px"><?= $tipoVals['T'] ?></td>
    <td class="num" style="font-size:11px"><?= $tipoVals['TP'] ?></td>
    <td class="num" style="font-size:11px"><?= $tipoVals['L'] ?></td>
    <td class="num" style="font-size:11px"><?= $tipoVals['Sem'] ?></td>
    <td class="num" style="font-size:11px"><?= $tipoVals['OT'] ?></td>
    <td class="num"><strong><?= fmt($necess['total'], 1) ?></strong></td>
    <td class="num"><?= fmt($atribs['total'], 1) ?></td>
    <td class="num" style="color:<?= $corFalta ?>;font-weight:600">
      <?= abs($falta) < 0.01 ? '<i class="fas fa-check-circle"></i>' : ($falta > 0 ? '−' : '+') . fmt(abs($falta), 1) ?>
    </td>
    <td style="text-align:center;white-space:nowrap">
      <a href="ocorrencia-form.php?id=<?= $o['id'] ?>&back_url=<?= urlencode('ocorrencias.php?fp='.urlencode($fp).'&fu='.urlencode($fu)) ?>"
         class="btn btn-secondary btn-xs"><i class="fas fa-edit"></i></a>
      <form method="post" style="display:inline">
        <input type="hidden" name="delete_id" value="<?= $o['id'] ?>">
        <input type="hidden" name="fp" value="<?= esc($fp) ?>">
        <input type="hidden" name="fu" value="<?= esc($fu) ?>">
        <button class="btn btn-danger btn-xs"
                data-confirm="Remover esta ocorrência e todas as distribuições associadas?"><i class="fas fa-trash"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; // ocs ?>
  <?php endforeach; // sems ?>
  <?php endforeach; // tree ?>
  <?php if (!$ocsList): ?>
  <tr><td colspan="13" style="text-align:center;padding:30px;color:var(--gray-400)">
    Nenhuma ocorrência neste ano letivo.
  </td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
</div>

</div><!-- end right column -->
</div><!-- end flex layout -->

<script>
function togglePlanoOcor(id) {
  const rows = document.querySelectorAll('.' + id);
  const icon = document.getElementById('icon-' + id);
  const visible = rows.length && rows[0].style.display !== 'none';
  rows.forEach(r => r.style.display = visible ? 'none' : '');
  if (icon) icon.textContent = visible ? '▶' : '▼';
}

function toggleSemOcor(id) {
  const rows = document.querySelectorAll('.' + id);
  const icon = document.getElementById('icon-' + id);
  const visible = rows.length && rows[0].style.display !== 'none';
  rows.forEach(r => r.style.display = visible ? 'none' : '');
  if (icon) icon.textContent = visible ? '▶' : '▼';
}

function colapsarTodos() {
  document.querySelectorAll('.section-plano').forEach(tr => {
    const m = tr.getAttribute('onclick')?.match(/'([^']+)'/);
    if (!m) return;
    document.querySelectorAll('.' + m[1]).forEach(r => r.style.display = 'none');
    const icon = document.getElementById('icon-' + m[1]);
    if (icon) icon.textContent = '▶';
  });
}

function expandirTodos() {
  document.querySelectorAll('.section-plano').forEach(tr => {
    const m = tr.getAttribute('onclick')?.match(/'([^']+)'/);
    if (!m) return;
    document.querySelectorAll('.' + m[1]).forEach(r => r.style.display = '');
    const icon = document.getElementById('icon-' + m[1]);
    if (icon) icon.textContent = '▼';
  });
  // Also expand semestres
  document.querySelectorAll('.section-sem').forEach(tr => {
    const m = tr.getAttribute('onclick')?.match(/'([^']+)'/);
    if (!m) return;
    document.querySelectorAll('.' + m[1]).forEach(r => r.style.display = '');
    const icon = document.getElementById('icon-' + m[1]);
    if (icon) icon.textContent = '▼';
  });
}

function colapsarSemestres() {
  document.querySelectorAll('.section-sem').forEach(tr => {
    const m = tr.getAttribute('onclick')?.match(/'([^']+)'/);
    if (!m) return;
    document.querySelectorAll('.' + m[1]).forEach(r => r.style.display = 'none');
    const icon = document.getElementById('icon-' + m[1]);
    if (icon) icon.textContent = '▶';
  });
}

function expandirSemestres() {
  document.querySelectorAll('.section-sem').forEach(tr => {
    if (tr.style.display === 'none') return; // skip if plano is collapsed
    const m = tr.getAttribute('onclick')?.match(/'([^']+)'/);
    if (!m) return;
    document.querySelectorAll('.' + m[1]).forEach(r => r.style.display = '');
    const icon = document.getElementById('icon-' + m[1]);
    if (icon) icon.textContent = '▼';
  });
}
  const plano = document.getElementById('filtro-plano').value.toLowerCase();
  const uc    = document.getElementById('filtro-uc').value.toLowerCase();
  document.getElementById('h-fp').value = document.getElementById('filtro-plano').value;
  document.getElementById('h-fu').value = document.getElementById('filtro-uc').value;
  let visible = 0;

  document.querySelectorAll('#tbl-ocor tbody tr').forEach(tr => {
    if (tr.classList.contains('section-plano') || tr.classList.contains('section-sem')) {
      // Handle section headers based on filter
      if (!plano && !uc) { tr.style.display = ''; return; }
      const trPlano = tr.dataset.plano || '';
      const matchPlano = !plano || trPlano === plano;
      tr.style.display = matchPlano ? '' : 'none';
      return;
    }
    if (!tr.dataset.uc) return;
    const matchPlano = !plano || (tr.dataset.plano || '') === plano;
    const matchUC    = !uc    || (tr.dataset.uc || '').includes(uc);
    const show = matchPlano && matchUC;
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
    if (!show) { const c = tr.querySelector('.row-chk'); if (c) c.checked = false; }
  });

  const total = <?= count($ocsList) ?>;
  document.getElementById('filtro-count').textContent =
    (plano || uc) ? visible + ' de ' + total + ' UC(s)' : '';
  updateSel();
}

function toggleAll(cb) {
  document.querySelectorAll('#tbl-ocor tbody tr').forEach(tr => {
    if (tr.style.display === 'none') return;
    const chk = tr.querySelector('.row-chk');
    if (chk) chk.checked = cb.checked;
  });
  updateSel();
}

function updateSel() {
  const sel = document.querySelectorAll('.row-chk:checked').length;
  document.getElementById('btn-apagar').style.display = sel > 0 ? '' : 'none';
  document.getElementById('sel-count').textContent = sel > 0 ? sel + ' seleccionada(s)' : '';
}

function apagarSelecionados() {
  const chks = document.querySelectorAll('.row-chk:checked');
  if (!chks.length) return;
  if (!confirm('Apagar ' + chks.length + ' ocorrência(s) e todas as distribuições associadas?')) return;
  const form = document.getElementById('bulk-form');
  form.getElementById = null;
  chks.forEach(c => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'delete_ids[]'; inp.value = c.value;
    form.appendChild(inp);
  });
  form.submit();
}

// Apply filter on load if params exist
if ('<?= esc($fp) ?>' || '<?= esc($fu) ?>') filtrarOcor();
</script>

<!-- Painel lateral de distribuição -->
<div id="dist-panel" style="display:none;position:fixed;top:0;right:0;width:560px;max-width:95vw;
     height:100vh;background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:1000;
     flex-direction:column;overflow:hidden">
  <div style="padding:14px 18px;background:var(--rpt-hdr);color:#fff;display:flex;justify-content:space-between;align-items:center;flex-shrink:0">
    <div>
      <div style="font-weight:700;font-size:15px" id="dp-title">Distribuição</div>
      <div style="font-size:12px;opacity:.8" id="dp-sub"></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a id="dp-edit-link" href="#" class="btn btn-secondary btn-sm" style="font-size:12px"><i class="fas fa-edit me-1"></i>Editar UC</a>
      <button onclick="closeDist()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0 4px">×</button>
    </div>
  </div>
  <div id="dp-body" style="overflow-y:auto;flex:1;padding:16px">
    <div style="text-align:center;color:var(--gray-400);padding:40px">A carregar…</div>
  </div>
</div>
<div id="dist-overlay" onclick="closeDist()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:999"></div>

<script>
function showDist(ocId, ucNome, evt) {
  evt.preventDefault();
  document.getElementById('dp-title').textContent = ucNome;
  document.getElementById('dp-sub').textContent = 'A carregar distribuição…';
  document.getElementById('dp-edit-link').href = 'ocorrencia-form.php?id=' + ocId +
    '&back_url=<?= urlencode('ocorrencias.php?fp='.urlencode($fp).'&fu='.urlencode($fu)) ?>';
  document.getElementById('dist-panel').style.display = 'flex';
  document.getElementById('dist-overlay').style.display = 'block';

  fetch('ajax-dist.php?ocor_id=' + ocId)
    .then(r => r.json())
    .then(data => {
      document.getElementById('dp-sub').textContent =
        data.rows.length + ' linha(s) · Nec: ' + data.need.toFixed(2) + ' h/sem · Atr: ' + data.done.toFixed(2) + ' h/sem';
      if (!data.rows.length) {
        document.getElementById('dp-body').innerHTML =
          '<p style="text-align:center;color:var(--gray-400);padding:40px">Sem serviço atribuído.</p>';
        return;
      }
      let html = '<table class="data-table" style="font-size:12px;width:100%">' +
        '<thead><tr><th>Docente</th><th style="text-align:center">Sem.</th>' +
        '<th style="text-align:center">T/TP/L/S/OT</th>' +
        '<th style="text-align:right">H/s</th><th style="text-align:right">H SLEf</th>' +
        '<th style="text-align:center">DSD</th><th style="text-align:center">R</th>' +
        '</tr></thead><tbody>';
      let totHs = 0, totSlef = 0;
      data.rows.forEach(r => {
        totHs   += r.hs;
        totSlef += r.h_slef;
        html += `<tr>
          <td>${r.docente}</td>
          <td style="text-align:center">${r.semanas}</td>
          <td style="text-align:center;font-size:11px;font-family:monospace">${r.turmas}</td>
          <td class="num">${r.hs.toFixed(2)}</td>
          <td class="num" style="color:var(--blue)">${r.h_slef.toFixed(2)}</td>
          <td style="text-align:center">${r.dsd ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'}</td>
          <td style="text-align:center">${r.reg ? '<i class="fas fa-star"></i>' : '–'}</td>
        </tr>`;
      });
      html += `<tr style="background:var(--blue-light);font-weight:700">
        <td colspan="3" style="text-align:right">Total</td>
        <td class="num">${totHs.toFixed(2)}</td>
        <td class="num" style="color:var(--blue)">${totSlef.toFixed(2)}</td>
        <td colspan="2"></td>
      </tr></tbody></table>`;
      html += `<div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
        <a href="distribuicao.php?ocor=${ocId}" class="btn btn-secondary btn-sm"><i class="fas fa-chart-bar me-1"></i>Ver na Distribuição</a>
        <button onclick="editDistFromPanel(${ocId})" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Editar Serviço</button>
      </div>`;
      document.getElementById('dp-body').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('dp-body').innerHTML =
        '<p style="color:var(--red);padding:20px">Erro ao carregar distribuição.</p>';
    });
}

function editDistFromPanel(ocId) {
  closeDist();
  const backUrl = encodeURIComponent('ocorrencias.php?fp=<?= urlencode($fp) ?>&fu=<?= urlencode($fu) ?>');
  window.location.href = 'ocorrencia-form.php?id=' + ocId + '&back_url=' + backUrl + '#linhas-servico';
}

function closeDist() {
  document.getElementById('dist-panel').style.display = 'none';
  document.getElementById('dist-overlay').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
