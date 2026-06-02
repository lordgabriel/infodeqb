<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$isMobileAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Estatísticas de mobilidade ───────────────────────────────────
$stTotal = (int)$pdo->query('SELECT COUNT(*) FROM infodeqb_registo_mobilidade')->fetchColumn();
$stAnoAtual = $pdo->query(
    'SELECT anoletivo, COUNT(*) AS n FROM infodeqb_registo_mobilidade
     GROUP BY anoletivo ORDER BY anoletivo DESC LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

$porAno = $pdo->query(
    'SELECT anoletivo, COUNT(*) AS n FROM infodeqb_registo_mobilidade
     GROUP BY anoletivo ORDER BY anoletivo DESC LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);

$topPaises = $pdo->query(
    'SELECT pais, COUNT(*) AS n FROM infodeqb_registo_mobilidade
     GROUP BY pais ORDER BY n DESC LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);

$porPrograma = $pdo->query(
    'SELECT programa, COUNT(*) AS n FROM infodeqb_registo_mobilidade
     WHERE programa != \'-\'
     GROUP BY programa ORDER BY n DESC'
)->fetchAll(PDO::FETCH_ASSOC);

// ── Estatísticas DIE ─────────────────────────────────────────────
$dieTotal   = (int)$pdo->query('SELECT COUNT(*) FROM infodeqb_company_contacts')->fetchColumn();
$diePaises  = (int)$pdo->query('SELECT COUNT(DISTINCT pais) FROM infodeqb_company_contacts WHERE pais IS NOT NULL')->fetchColumn();

Database::disconnect();

$pageTitle = 'Mobilidade & Contactos DIE';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:10px">
  <div class="mr-auto">
    <h1><i class="fas fa-globe fa-sm me-2 text-muted"></i><?= t('MOBILE_TITLE') ?></h1>
    <small class="text-muted"><?= t('MOBILE_SUBTITLE') ?></small>
  </div>
  <?php if ($isMobileAdmin): ?>
  <a href="<?= HTTP_DIR ?>/infodeqb/mobile/in/add.php" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i><?= t('MOBILE_NEW_RECORD') ?>
  </a>
  <?php endif; ?>
</div>

<?php /* ── Badges de resumo ──────────────────────────────────────── */ ?>
<div class="d-flex flex-wrap mb-4" style="gap:12px">
  <a href="<?= HTTP_DIR ?>/infodeqb/mobile/in/" class="card flex-fill shadow-sm text-center text-decoration-none" style="min-width:130px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><i class="fas fa-plane-arrival fa-xs me-1"></i><?= t('MOBILE_STUDENTS_IN') ?></div>
      <div class="h4 mb-0 font-weight-bold text-primary"><?= $stTotal ?></div>
      <div class="text-muted" style="font-size:.72rem">total histórico</div>
    </div>
  </a>
  <div class="card flex-fill shadow-sm text-center" style="min-width:130px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><i class="fas fa-calendar fa-xs me-1"></i><?= htmlspecialchars($stAnoAtual['anoletivo'] ?? '') ?></div>
      <div class="h4 mb-0 font-weight-bold"><?= $stAnoAtual['n'] ?? 0 ?></div>
      <div class="text-muted" style="font-size:.72rem">ano letivo actual</div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm text-center" style="min-width:130px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><i class="fas fa-flag fa-xs me-1"></i>Países de origem</div>
      <div class="h4 mb-0 font-weight-bold"><?= count($topPaises) ?>+</div>
      <div class="text-muted" style="font-size:.72rem">representados</div>
    </div>
  </div>
  <a href="<?= HTTP_DIR ?>/infodeqb/mobile/die/" class="card flex-fill shadow-sm text-center text-decoration-none" style="min-width:130px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><i class="fas fa-building fa-xs me-1"></i><?= t('MOBILE_DIE') ?></div>
      <div class="h4 mb-0 font-weight-bold text-success"><?= $dieTotal ?></div>
      <div class="text-muted" style="font-size:.72rem"><?= $diePaises ?> países</div>
    </div>
  </a>
</div>

<?php /* ── Gráficos + tabelas ──────────────────────────────────── */ ?>
<div class="row mb-4">
  <div class="col-md-5">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <strong>Estudantes por ano letivo</strong>
      </div>
      <div class="card-body">
        <canvas id="chartAnos" style="max-height:220px"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <strong>Top países de origem</strong>
      </div>
      <div class="card-body">
        <canvas id="chartPaises" style="max-height:220px"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-header py-2"><strong>Por programa</strong></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.83rem">
          <thead class="">
            <tr><th>Programa</th><th class="text-end" style="width:4em">Nº</th></tr>
          </thead>
          <tbody>
          <?php foreach ($porPrograma as $prog): ?>
            <tr>
              <td><?= htmlspecialchars($prog['programa']) ?></td>
              <td class="text-end font-weight-bold"><?= $prog['n'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card shadow-sm">
      <div class="card-header py-2 d-flex align-items-center">
        <strong class="mr-auto">Acesso rápido</strong>
      </div>
      <div class="card-body">
        <div class="row" style="gap-y:10px">
          <div class="col-6 mb-3">
            <a href="<?= HTTP_DIR ?>/infodeqb/mobile/in/" class="card border-primary text-decoration-none h-100">
              <div class="card-body py-3 text-center">
                <i class="fas fa-plane-arrival fa-2x text-primary mb-2 d-block"></i>
                <div class="font-weight-bold">Mobilidade IN</div>
                <small class="text-muted">Consultar e gerir registos</small>
              </div>
            </a>
          </div>
          <?php if ($isMobileAdmin): ?>
          <div class="col-6 mb-3">
            <a href="<?= HTTP_DIR ?>/infodeqb/mobile/in/add.php" class="card border-success text-decoration-none h-100">
              <div class="card-body py-3 text-center">
                <i class="fas fa-plus-circle fa-2x text-success mb-2 d-block"></i>
                <div class="font-weight-bold">Novo registo</div>
                <small class="text-muted">Adicionar estudante</small>
              </div>
            </a>
          </div>
          <div class="col-6 mb-3">
            <a href="<?= HTTP_DIR ?>/infodeqb/mobile/in/edit.php" class="card border-warning text-decoration-none h-100">
              <div class="card-body py-3 text-center">
                <i class="fas fa-edit fa-2x text-warning mb-2 d-block"></i>
                <div class="font-weight-bold">Editar registos</div>
                <small class="text-muted">Lista para edição</small>
              </div>
            </a>
          </div>
          <?php endif; ?>
          <div class="col-6 mb-3">
            <a href="<?= HTTP_DIR ?>/infodeqb/mobile/die/" class="card border-secondary text-decoration-none h-100">
              <div class="card-body py-3 text-center">
                <i class="fas fa-address-book fa-2x text-secondary mb-2 d-block"></i>
                <div class="font-weight-bold">Contactos DIE</div>
                <small class="text-muted">Empresas e instituições</small>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var anosLabels = <?= json_encode(array_reverse(array_column($porAno, 'anoletivo'))) ?>;
var anosData   = <?= json_encode(array_reverse(array_column($porAno, 'n'))) ?>;
var paisesLabels = <?= json_encode(array_column($topPaises, 'pais')) ?>;
var paisesData   = <?= json_encode(array_column($topPaises, 'n')) ?>;

new Chart(document.getElementById('chartAnos'), {
    type: 'bar',
    data: {
        labels: anosLabels,
        datasets: [{ label: 'Estudantes', data: anosData,
            backgroundColor: 'rgba(59,130,246,.7)', borderColor: 'rgb(59,130,246)',
            borderWidth: 1, borderRadius: 4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
               scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('chartPaises'), {
    type: 'bar',
    data: {
        labels: paisesLabels,
        datasets: [{ label: 'Estudantes', data: paisesData,
            backgroundColor: 'rgba(16,185,129,.7)', borderColor: 'rgb(16,185,129)',
            borderWidth: 1, borderRadius: 4 }]
    },
    options: { indexAxis: 'y', responsive: true,
               plugins: { legend: { display: false } },
               scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
