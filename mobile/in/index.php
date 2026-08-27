<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$isMobileAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile);

if (!$isMobileAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Filtro de ano letivo ──────────────────────────────────────────
$anos = $pdo->query(
    'SELECT DISTINCT anoletivo FROM infodeqb_registo_mobilidade ORDER BY anoletivo DESC'
)->fetchAll(PDO::FETCH_COLUMN);

$anoAtual = !empty($anos) ? $anos[0] : '%';

if (!empty($_POST) && isset($_POST['anoletivo'])) {
    $_SESSION['mobile_anofilter'] = $_POST['anoletivo'];
}
if (!isset($_SESSION['mobile_anofilter'])) {
    $_SESSION['mobile_anofilter'] = $anoAtual;
}
$filtro = $_SESSION['mobile_anofilter'];

// ── Registos filtrados ────────────────────────────────────────────
$sth = $pdo->prepare(
    'SELECT id, nome, universidade, pais, programa, anoletivo,
            tipocontrato, duracao, inicio, fim, ucs, obs
     FROM infodeqb_registo_mobilidade
     WHERE anoletivo LIKE ?
     ORDER BY nome ASC'
);
$sth->execute([$filtro]);
$registos = $sth->fetchAll(PDO::FETCH_ASSOC);

// ── Stats do filtro actual ─────────────────────────────────────────
$nTotal = count($registos);

Database::disconnect();

$pageTitle = t('MOBILE_IN_TITLE');
$mainClass  = 'iq-hr-page';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:10px">
  <div class="mr-auto">
    <h1><i class="fas fa-plane-arrival fa-sm me-2 text-muted"></i><?= t('MOBILE_IN_TITLE') ?></h1>
    <small class="text-muted">Estudantes de mobilidade incoming registados</small>
  </div>
  <a href="<?= HTTP_DIR ?>/infodeqb/mobile/" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-th-large me-1"></i><?= t('MOBILE_DASHBOARD') ?>
  </a>
  <?php if ($isMobileAdmin): ?>
  <a href="edit.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-list me-1"></i><?= t('MOBILE_EDIT_RECORDS') ?>
  </a>
  <a href="add.php" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i><?= t('MOBILE_NEW_RECORD') ?>
  </a>
  <?php endif; ?>
</div>

<?php /* ── Filtro ───────────────────────────────────────────────── */ ?>
<div class="d-flex align-items-center mb-4" style="gap:10px">
  <form method="post" class="d-flex align-items-center" style="gap:8px">
    <label class="small font-weight-bold mb-0">Ano letivo:</label>
    <select name="anoletivo" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
      <?php foreach ($anos as $a): ?>
      <option value="<?= htmlspecialchars($a) ?>" <?= $filtro === $a ? 'selected' : '' ?>>
        <?= htmlspecialchars($a) ?>
      </option>
      <?php endforeach; ?>
      <option value="%" <?= $filtro === '%' ? 'selected' : '' ?>>Todos</option>
    </select>
  </form>
  <span class="badge badge-secondary ms-2"><?= $nTotal ?> estudante<?= $nTotal != 1 ? 's' : '' ?></span>
</div>

<?php /* ── Gráficos por UC ──────────────────────────────────────── */ ?>
<div class="row mb-4">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-bar fa-xs me-1 text-muted"></i>
        <strong>L.EQ — Inscritos por UC</strong>
      </div>
      <div class="card-body py-3">
        <canvas id="graphLeq" style="max-height:220px"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="leq-empty">Sem dados para este ano letivo.</p>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-bar fa-xs me-1 text-muted"></i>
        <strong>M.EQ — Inscritos por UC</strong>
      </div>
      <div class="card-body py-3">
        <canvas id="graphMeq" style="max-height:220px"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="meq-empty">Sem dados para este ano letivo.</p>
      </div>
    </div>
  </div>
</div>

<?php /* ── Tabela de estudantes ──────────────────────────────────── */ ?>
<div class="card shadow-sm mb-4">
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" id="tblMobile" style="font-size:.83rem">
      <thead class="">
        <tr>
          <th>Nome</th>
          <th>Universidade</th>
          <th style="width:8em">País</th>
          <th>Programa</th>
          <th style="width:7em">Tipo</th>
          <th style="width:6em">Ano Letivo</th>
          <th style="width:6em">Duração</th>
          <th style="width:5em">Início</th>
          <th style="width:5em">Fim</th>
          <th>UCs</th>
          <?php if ($isMobileAdmin): ?>
          <th style="width:4em" class="text-center"><?= t('ACTIONS') ?></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($registos as $row): ?>
        <tr>
          <td class="align-middle font-weight-bold"><?= htmlspecialchars($row['nome']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['universidade']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['pais']) ?></td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($row['programa']) ?></td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($row['tipocontrato']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['anoletivo']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['duracao']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['inicio']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['fim']) ?></td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($row['ucs']) ?></td>
          <?php if ($isMobileAdmin): ?>
          <td class="align-middle text-center">
            <a href="detail.php?id=<?= (int)$row['id'] ?>" class="text-info" title="Editar">
              <i class="fas fa-edit fa-xs"></i>
            </a>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var anoFiltro = <?= json_encode($filtro) ?>;

function makeBarChart(id, labels, data, emptyId) {
    if (!data.length) {
        document.getElementById(emptyId).classList.remove('d-none');
        return;
    }
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{ label: 'Inscritos', data: data,
                backgroundColor: 'rgba(59,130,246,.7)', borderColor: 'rgb(59,130,246)',
                borderWidth: 1, borderRadius: 3 }]
        },
        options: { responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

$.post('data_leq.php', { anoletivo: anoFiltro }, function(d) {
    if (typeof d === 'string') d = JSON.parse(d);
    makeBarChart('graphLeq', d.map(function(r){ return r.UC; }),
                              d.map(function(r){ return r.Inscritos; }), 'leq-empty');
});

$.post('data_meq.php', { anoletivo: anoFiltro }, function(d) {
    if (typeof d === 'string') d = JSON.parse(d);
    makeBarChart('graphMeq', d.map(function(r){ return r.UC; }),
                              d.map(function(r){ return r.Inscritos; }), 'meq-empty');
});

$(document).ready(function () {
    $('#tblMobile').DataTable({
        paging: false,
        order: [[0, 'asc']],
        language: { search: '', searchPlaceholder: 'Pesquisar…',
                    info: '_TOTAL_ estudantes', infoFiltered: '(de _MAX_)',
                    zeroRecords: 'Sem resultados' },
        dom: '<"px-3 pt-3 pb-2"f>t',
        <?php if ($isMobileAdmin): ?>
        columnDefs: [{ targets: [-1], orderable: false }]
        <?php endif; ?>
    });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
