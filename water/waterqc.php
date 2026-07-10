<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';

date_default_timezone_set('Europe/Lisbon');

// Admins podem inserir registos (global + local)
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
$isAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsWater);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Sem filtro PHP — os gráficos carregam via AJAX com parâmetros de data

// Flash de operações POST
$flashMsg  = '';
$flashType = 'success';

// ── Inserção de leitura diária (apenas admins) ────────────────────
if (!empty($_POST) && $isAdmin && ($_POST['_acao'] ?? '') === 'inserir') {
    try {
        $pdo->beginTransaction();
        $dia = $_POST['dia'];

        // Destilada — water_type = 2
        $condDest = $_POST['cond_dest'] !== '' ? (float)$_POST['cond_dest'] : null;
        $phDest   = $_POST['ph_dest']   !== '' ? (float)$_POST['ph_dest']   : null;
        if ($condDest !== null || $phDest !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_ph_cond (dia, water_type, condutivity, pH)
                 VALUES (?, 2, ?, ?)'
            )->execute([$dia, $condDest, $phDest]);
        }
        $tocDest = $_POST['toc_dest'] !== '' ? (float)$_POST['toc_dest'] : null;
        if ($tocDest !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_toc (dia, water_type, TOC)
                 VALUES (?, 2, ?)'
            )->execute([$dia, $tocDest]);
        }

        // Purificada — water_type = 3
        $condPur = $_POST['cond_pur'] !== '' ? (float)$_POST['cond_pur'] : null;
        $phPur   = $_POST['ph_pur']   !== '' ? (float)$_POST['ph_pur']   : null;
        if ($condPur !== null || $phPur !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_ph_cond (dia, water_type, condutivity, pH)
                 VALUES (?, 3, ?, ?)'
            )->execute([$dia, $condPur, $phPur]);
        }
        $tocPur = $_POST['toc_pur'] !== '' ? (float)$_POST['toc_pur'] : null;
        if ($tocPur !== null) {
            $pdo->prepare(
                'INSERT INTO infodeqb_waterqc_toc (dia, water_type, TOC)
                 VALUES (?, 3, ?)'
            )->execute([$dia, $tocPur]);
        }

        $pdo->commit();
        $flashMsg  = 'Leitura de ' . htmlspecialchars($dia) . ' registada com sucesso.';
        $flashType = 'success';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg  = 'Erro ao inserir registo: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

// ── Edição de uma linha existente (apenas admins) ─────────────────
if (!empty($_POST) && $isAdmin && ($_POST['_acao'] ?? '') === 'editar_linha') {
    try {
        $pdo->beginTransaction();
        $dia = $_POST['dia'];

        $numOrNull = function ($v) {
            return ($v !== null && $v !== '') ? (float)$v : null;
        };

        $upsertPC = function ($id, $type, $cond, $ph) use ($pdo, $dia) {
            $hasVal = ($cond !== null || $ph !== null);
            if ($id) {
                if ($hasVal) {
                    $pdo->prepare('UPDATE infodeqb_waterqc_ph_cond SET condutivity=?, pH=? WHERE id=?')
                        ->execute([$cond, $ph, $id]);
                } else {
                    $pdo->prepare('DELETE FROM infodeqb_waterqc_ph_cond WHERE id=?')->execute([$id]);
                }
            } elseif ($hasVal) {
                $pdo->prepare('INSERT INTO infodeqb_waterqc_ph_cond (dia, water_type, condutivity, pH) VALUES (?,?,?,?)')
                    ->execute([$dia, $type, $cond, $ph]);
            }
        };

        $upsertTOC = function ($id, $type, $toc) use ($pdo, $dia) {
            $hasVal = ($toc !== null);
            if ($id) {
                if ($hasVal) {
                    $pdo->prepare('UPDATE infodeqb_waterqc_toc SET TOC=? WHERE id=?')->execute([$toc, $id]);
                } else {
                    $pdo->prepare('DELETE FROM infodeqb_waterqc_toc WHERE id=?')->execute([$id]);
                }
            } elseif ($hasVal) {
                $pdo->prepare('INSERT INTO infodeqb_waterqc_toc (dia, water_type, TOC) VALUES (?,?,?)')
                    ->execute([$dia, $type, $toc]);
            }
        };

        $upsertPC((int)($_POST['id_pc_dest'] ?? 0) ?: null, 2,
                  $numOrNull($_POST['cond_dest'] ?? ''), $numOrNull($_POST['ph_dest'] ?? ''));
        $upsertPC((int)($_POST['id_pc_pur'] ?? 0) ?: null, 3,
                  $numOrNull($_POST['cond_pur'] ?? ''), $numOrNull($_POST['ph_pur'] ?? ''));
        $upsertTOC((int)($_POST['id_toc_dest'] ?? 0) ?: null, 2, $numOrNull($_POST['toc_dest'] ?? ''));
        $upsertTOC((int)($_POST['id_toc_pur'] ?? 0) ?: null, 3, $numOrNull($_POST['toc_pur'] ?? ''));

        $pdo->commit();
        $flashMsg  = 'Registo de ' . htmlspecialchars($dia) . ' actualizado.';
        $flashType = 'success';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg  = 'Erro ao actualizar registo: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

// ── Eliminar todos os registos de um dia (apenas admins) ──────────
if (!empty($_POST) && $isAdmin && ($_POST['_acao'] ?? '') === 'apagar_dia') {
    try {
        $pdo->beginTransaction();
        $dia = $_POST['dia'];
        $pdo->prepare('DELETE FROM infodeqb_waterqc_ph_cond WHERE dia=?')->execute([$dia]);
        $pdo->prepare('DELETE FROM infodeqb_waterqc_toc WHERE dia=?')->execute([$dia]);
        $pdo->commit();
        $flashMsg  = 'Registos de ' . htmlspecialchars($dia) . ' eliminados.';
        $flashType = 'success';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg  = 'Erro ao eliminar registo: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

// ── Edição dos dados de um equipamento (apenas admins) ────────────
if (!empty($_POST) && $isAdmin && ($_POST['_acao'] ?? '') === 'editar_equip') {
    try {
        $id    = (int)$_POST['id'];
        $marca  = trim($_POST['marca']  ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $dataAq = trim($_POST['data_aquisicao'] ?? '');
        if ($dataAq === '') $dataAq = null;

        $pdo->prepare(
            'UPDATE infodeqb_waterqc_equip SET marca=?, modelo=?, data_aquisicao=? WHERE id=?'
        )->execute([$marca ?: null, $modelo ?: null, $dataAq, $id]);

        $flashMsg  = 'Dados do equipamento actualizados.';
        $flashType = 'success';

    } catch (Exception $e) {
        $flashMsg  = 'Erro ao actualizar equipamento: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

// ── Dados dos equipamentos ────────────────────────────────────────
$equipamentos = $pdo->query('SELECT * FROM infodeqb_waterqc_equip ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// ── Listagem para edição (mês seleccionado, apenas admins) ────────
$editRows = [];
$editMes  = '';
$editPrevMes = '';
$editNextMes = '';
$editMesLabel = '';
if ($isAdmin) {
    $editMes = preg_replace('/[^0-9\-]/', '', $_GET['edit_mes'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $editMes)) $editMes = date('Y-m');
    $editDe  = $editMes . '-01';
    $editAte = date('Y-m-t', strtotime($editDe));

    $mesesLPTphp = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $editMesLabel = $mesesLPTphp[(int)date('n', strtotime($editDe)) - 1] . ' ' . date('Y', strtotime($editDe));
    $editPrevMes = date('Y-m', strtotime($editDe . ' -1 month'));
    $editNextMes = date('Y-m', strtotime($editDe . ' +1 month'));

    $qPC = $pdo->prepare(
        "SELECT id, DATE_FORMAT(dia,'%Y-%m-%d') AS dia, water_type, condutivity, pH
         FROM infodeqb_waterqc_ph_cond p
         WHERE dia >= ? AND dia <= ?
           AND id = (SELECT MAX(id) FROM infodeqb_waterqc_ph_cond p2
                     WHERE p2.dia = p.dia AND p2.water_type = p.water_type)
         ORDER BY dia ASC, water_type ASC"
    );
    $qPC->execute([$editDe, $editAte]);
    foreach ($qPC->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = $r['dia'];
        if (!isset($editRows[$d])) $editRows[$d] = [];
        $key = $r['water_type'] == 2 ? 'pc_dest' : 'pc_pur';
        $editRows[$d][$key] = ['id' => $r['id'], 'cond' => $r['condutivity'], 'ph' => $r['pH']];
    }

    $qTOC = $pdo->prepare(
        "SELECT id, DATE_FORMAT(dia,'%Y-%m-%d') AS dia, water_type, TOC
         FROM infodeqb_waterqc_toc p
         WHERE dia >= ? AND dia <= ?
           AND id = (SELECT MAX(id) FROM infodeqb_waterqc_toc p2
                     WHERE p2.dia = p.dia AND p2.water_type = p.water_type)
         ORDER BY dia ASC, water_type ASC"
    );
    $qTOC->execute([$editDe, $editAte]);
    foreach ($qTOC->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d = $r['dia'];
        if (!isset($editRows[$d])) $editRows[$d] = [];
        $key = $r['water_type'] == 2 ? 'toc_dest' : 'toc_pur';
        $editRows[$d][$key] = ['id' => $r['id'], 'toc' => $r['TOC']];
    }

    ksort($editRows);
}

Database::disconnect();

$pageTitle = 'Qualidade da Água';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto">
    <i class="fas fa-tint fa-sm me-2 text-muted"></i>
    <?= t('WATER_QC_TITLE') ?>
  </h1>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert"
     style="font-size:.85rem">
  <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
  <?= $flashMsg ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php /* ── Formulário de inserção (apenas admins) ─────────────────── */ ?>
<?php if ($isAdmin): ?>
<div class="card mb-4 border-primary">
  <div class="card-header py-2 d-flex align-items-center"
       style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#formInsert">
    <i class="fas fa-plus-circle text-primary me-2"></i>
    <strong class="mr-auto text-primary"><?= t('WATER_LOG_TITLE') ?></strong>
    <i class="fas fa-chevron-down fa-xs text-muted"></i>
  </div>
  <div id="formInsert" class="collapse <?= $flashType === 'success' && $flashMsg ? '' : '' ?>">
    <div class="card-body">
      <form method="post" class="needs-validation" novalidate>
        <input type="hidden" name="_acao" value="inserir">

        <div class="form-row mb-3">
          <div class="col-md-2">
            <label class="font-weight-bold">Data <span class="text-danger">*</span></label>
            <input type="date" name="dia" class="form-control" required
                   value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="row">
          <!-- Destilada -->
          <div class="col-md-6">
            <div class="card border-0 bg-light mb-3 p-3">
              <h6 class="font-weight-bold mb-3">
                <i class="fas fa-flask fa-sm me-1 text-success"></i> Água Destilada
              </h6>
              <div class="form-row">
                <div class="col form-group mb-2">
                  <label class="small mb-1">Condutividade (µS/cm)</label>
                  <input type="number" step="0.001" name="cond_dest"
                         class="form-control form-control-sm" placeholder="ex: 0.5">
                </div>
                <div class="col form-group mb-2">
                  <label class="small mb-1">pH</label>
                  <input type="number" step="0.01" min="0" max="14" name="ph_dest"
                         class="form-control form-control-sm" placeholder="ex: 6.8">
                </div>
              </div>
              <div class="form-row">
                <div class="col form-group mb-0">
                  <label class="small mb-1">TOC</label>
                  <input type="number" step="0.001" name="toc_dest"
                         class="form-control form-control-sm">
                </div>
              </div>
            </div>
          </div>

          <!-- Purificada -->
          <div class="col-md-6">
            <div class="card border-0 bg-light mb-3 p-3">
              <h6 class="font-weight-bold mb-3">
                <i class="fas fa-flask fa-sm me-1 text-primary"></i> Água Purificada
              </h6>
              <div class="form-row">
                <div class="col form-group mb-2">
                  <label class="small mb-1">Condutividade (µS/cm)</label>
                  <input type="number" step="0.001" name="cond_pur"
                         class="form-control form-control-sm" placeholder="ex: 0.1">
                </div>
                <div class="col form-group mb-2">
                  <label class="small mb-1">pH</label>
                  <input type="number" step="0.01" min="0" max="14" name="ph_pur"
                         class="form-control form-control-sm" placeholder="ex: 6.5">
                </div>
              </div>
              <div class="form-row">
                <div class="col form-group mb-0">
                  <label class="small mb-1">TOC</label>
                  <input type="number" step="0.001" name="toc_pur"
                         class="form-control form-control-sm">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-1">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save me-1"></i> <?= t('WATER_SAVE_READING') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ── Edição de registos introduzidos (apenas admins) ─────────── */ ?>
<div class="card mb-4 border-secondary">
  <div class="card-header py-2 d-flex align-items-center"
       style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#formEdit">
    <i class="fas fa-pen-to-square text-secondary me-2"></i>
    <strong class="mr-auto text-secondary">Editar registos</strong>
    <i class="fas fa-chevron-down fa-xs text-muted"></i>
  </div>
  <div id="formEdit" class="collapse<?= (isset($_GET['edit_mes']) || in_array($_POST['_acao'] ?? '', ['editar_linha', 'apagar_dia'])) ? ' show' : '' ?>">
    <div class="card-body">

      <div class="d-flex align-items-center justify-content-center mb-3" style="gap:12px">
        <a class="btn btn-sm btn-outline-secondary" href="?edit_mes=<?= htmlspecialchars($editPrevMes) ?>#formEdit">
          <i class="fas fa-chevron-left"></i>
        </a>
        <strong><?= htmlspecialchars($editMesLabel) ?></strong>
        <a class="btn btn-sm btn-outline-secondary" href="?edit_mes=<?= htmlspecialchars($editNextMes) ?>#formEdit">
          <i class="fas fa-chevron-right"></i>
        </a>
      </div>

      <?php if (!$editRows): ?>
        <p class="text-center text-muted small mb-0">Sem registos para este mês.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle" style="font-size:.82rem">
          <thead class="text-center">
            <tr>
              <th rowspan="2" class="align-middle">Data</th>
              <th colspan="3">Água Destilada</th>
              <th colspan="3">Água Purificada</th>
              <th rowspan="2" class="align-middle">Acções</th>
            </tr>
            <tr>
              <th>Cond. (µS/cm)</th><th>pH</th><th>TOC</th>
              <th>Cond. (µS/cm)</th><th>pH</th><th>TOC</th>
            </tr>
          </thead>
          <tbody>
          <?php $editRowN = 0; foreach ($editRows as $dia => $r):
              $editRowN++;
              $fSave = 'wqEditSave' . $editRowN;
              $fDel  = 'wqEditDel'  . $editRowN;
              $pcDest  = $r['pc_dest']  ?? ['id' => 0, 'cond' => null, 'ph' => null];
              $pcPur   = $r['pc_pur']   ?? ['id' => 0, 'cond' => null, 'ph' => null];
              $tocDest = $r['toc_dest'] ?? ['id' => 0, 'toc' => null];
              $tocPur  = $r['toc_pur']  ?? ['id' => 0, 'toc' => null];
          ?>
            <tr>
                <td class="text-nowrap"><?= htmlspecialchars(date('d/m/Y', strtotime($dia))) ?></td>
                <td><input form="<?= $fSave ?>" type="number" step="0.001" name="cond_dest" class="form-control form-control-sm"
                           value="<?= $pcDest['cond']  !== null ? htmlspecialchars($pcDest['cond'])  : '' ?>"></td>
                <td><input form="<?= $fSave ?>" type="number" step="0.01"  name="ph_dest"   class="form-control form-control-sm"
                           value="<?= $pcDest['ph']    !== null ? htmlspecialchars($pcDest['ph'])    : '' ?>"></td>
                <td><input form="<?= $fSave ?>" type="number" step="0.001" name="toc_dest"  class="form-control form-control-sm"
                           value="<?= $tocDest['toc']  !== null ? htmlspecialchars($tocDest['toc'])  : '' ?>"></td>
                <td><input form="<?= $fSave ?>" type="number" step="0.001" name="cond_pur"  class="form-control form-control-sm"
                           value="<?= $pcPur['cond']   !== null ? htmlspecialchars($pcPur['cond'])   : '' ?>"></td>
                <td><input form="<?= $fSave ?>" type="number" step="0.01"  name="ph_pur"    class="form-control form-control-sm"
                           value="<?= $pcPur['ph']     !== null ? htmlspecialchars($pcPur['ph'])     : '' ?>"></td>
                <td><input form="<?= $fSave ?>" type="number" step="0.001" name="toc_pur"   class="form-control form-control-sm"
                           value="<?= $tocPur['toc']   !== null ? htmlspecialchars($tocPur['toc'])   : '' ?>"></td>
                <td class="text-center text-nowrap">
                  <button form="<?= $fSave ?>" type="submit" class="btn btn-sm btn-outline-primary" title="Guardar">
                    <i class="fas fa-save"></i>
                  </button>
                  <button form="<?= $fDel ?>" type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar dia">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
            </tr>
            <tr style="display:none">
              <td colspan="8">
                <form id="<?= $fSave ?>" method="post">
                  <input type="hidden" name="_acao" value="editar_linha">
                  <input type="hidden" name="dia" value="<?= htmlspecialchars($dia) ?>">
                  <input type="hidden" name="id_pc_dest"  value="<?= (int)$pcDest['id']  ?>">
                  <input type="hidden" name="id_pc_pur"   value="<?= (int)$pcPur['id']   ?>">
                  <input type="hidden" name="id_toc_dest" value="<?= (int)$tocDest['id'] ?>">
                  <input type="hidden" name="id_toc_pur"  value="<?= (int)$tocPur['id']  ?>">
                </form>
                <form id="<?= $fDel ?>" method="post"
                      onsubmit="return confirm('Eliminar todos os registos de <?= htmlspecialchars(date('d/m/Y', strtotime($dia))) ?>?');">
                  <input type="hidden" name="_acao" value="apagar_dia">
                  <input type="hidden" name="dia" value="<?= htmlspecialchars($dia) ?>">
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endif; ?>

<div class="row">
<div class="col-lg-9">

<div class="wq-filter-bar">
  <!-- Linha 1: pills de preset + botão exportar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
  <div class="d-flex flex-wrap align-items-center" style="gap:6px">
    <button class="wq-pill" data-preset="semana"><?= t('PERIOD_THIS_WEEK') ?></button>
    <button class="wq-pill active" data-preset="mes"><?= t('PERIOD_THIS_MONTH') ?></button>
    <button class="wq-pill" data-preset="ano"><?= t('PERIOD_THIS_YEAR') ?></button>
    <button class="wq-pill" data-preset="7d"><?= t('PERIOD_LAST_7') ?></button>
    <button class="wq-pill" data-preset="15d"><?= t('PERIOD_LAST_15') ?></button>
    <button class="wq-pill" data-preset="30d"><?= t('PERIOD_LAST_30') ?></button>
    <button class="wq-pill" data-preset="custom">
      <i class="fas fa-calendar-alt fa-xs me-1"></i><?= t('PERIOD_CUSTOM') ?>
    </button>
  </div>
  <a id="btnExport" href="#" target="_blank"
     class="btn btn-sm btn-outline-success" style="white-space:nowrap">
    <i class="fas fa-file-excel me-1"></i><?= t('WATER_EXPORT') ?>
  </a>
  </div><!-- fecha d-flex outer -->

  <!-- Linha 2: setas de navegação + label do período -->
  <div class="wq-nav">
    <button class="wq-nav-btn" id="wqPrev" title="Período anterior">
      <i class="fas fa-chevron-left"></i>
    </button>
    <span class="wq-period-label" id="wqLabel">—</span>
    <button class="wq-nav-btn" id="wqNext" title="Período seguinte" disabled>
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>

  <!-- Linha 3: campos de data personalizada (ocultos) -->
  <div class="wq-range-inputs" id="wqCustomRange">
    <label class="small font-weight-bold mb-0"><?= t('PERIOD_FROM') ?></label>
    <input type="date" id="wqDe"  class="form-control form-control-sm" style="width:auto">
    <span class="text-muted">—</span>
    <label class="small font-weight-bold mb-0"><?= t('PERIOD_TO') ?></label>
    <input type="date" id="wqAte" class="form-control form-control-sm" style="width:auto">
    <button class="btn btn-primary btn-sm" id="wqApply">
      <i class="fas fa-check me-1"></i><?= t('APPLY') ?>
    </button>
  </div>
</div>

<?php /* ── Gráficos ─────────────────────────────────────────────── */ ?>
<div class="row">
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm me-1 text-muted"></i>
        Condutividade <small class="text-muted">(µS/cm)</small>
      </div>
      <div class="card-body py-3">
        <canvas id="cond"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="cond-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm me-1 text-muted"></i>
        pH
      </div>
      <div class="card-body py-3">
        <canvas id="ph"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="ph-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
</div>
<div class="d-flex align-items-center justify-content-center mb-2" style="gap:10px">
  <button class="wq-nav-btn" id="tocPrev" title="Ano anterior">
    <i class="fas fa-chevron-left"></i>
  </button>
  <span class="wq-period-label" id="tocLabel">—</span>
  <button class="wq-nav-btn" id="tocNext" title="Ano seguinte" disabled>
    <i class="fas fa-chevron-right"></i>
  </button>
</div>
<div class="row">
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm me-1 text-muted"></i>
        TOC — Destilada
      </div>
      <div class="card-body py-3">
        <canvas id="TCdest"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="tcdest-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <i class="fas fa-chart-line fa-sm me-1 text-muted"></i>
        TOC — Purificada
      </div>
      <div class="card-body py-3">
        <canvas id="TCpur"></canvas>
        <p class="text-center text-muted small mt-2 d-none" id="tcpur-empty"><?= t('WATER_NO_DATA') ?></p>
      </div>
    </div>
  </div>
</div>

</div><!-- /col-lg-9 -->

<div class="col-lg-3">
  <?php foreach ($equipamentos as $eq): ?>
  <div class="card mb-4 shadow-sm">
    <div class="card-header py-2">
      <i class="fas fa-microchip fa-sm me-1 text-muted"></i>
      <?= htmlspecialchars($eq['nome']) ?>
    </div>
    <div class="card-body py-3">
      <?php if ($isAdmin): ?>
      <form method="post">
        <input type="hidden" name="_acao" value="editar_equip">
        <input type="hidden" name="id" value="<?= (int)$eq['id'] ?>">
        <div class="form-group mb-2">
          <label class="small mb-1 font-weight-bold">Marca</label>
          <input type="text" name="marca" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($eq['marca'] ?? '') ?>">
        </div>
        <div class="form-group mb-2">
          <label class="small mb-1 font-weight-bold">Modelo</label>
          <input type="text" name="modelo" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($eq['modelo'] ?? '') ?>">
        </div>
        <div class="form-group mb-2">
          <label class="small mb-1 font-weight-bold">Data de aquisição</label>
          <input type="date" name="data_aquisicao" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($eq['data_aquisicao'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-save me-1"></i><?= t('WATER_SAVE_READING') ?>
        </button>
      </form>
      <?php else: ?>
      <table class="table table-sm mb-0" style="font-size:.85rem">
        <tr><th class="text-muted">Marca</th><td><?= htmlspecialchars($eq['marca'] ?: '—') ?></td></tr>
        <tr><th class="text-muted">Modelo</th><td><?= htmlspecialchars($eq['modelo'] ?: '—') ?></td></tr>
        <tr><th class="text-muted">Aquisição</th>
          <td><?= $eq['data_aquisicao'] ? htmlspecialchars(date('d/m/Y', strtotime($eq['data_aquisicao']))) : '—' ?></td></tr>
      </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div><!-- /col-lg-3 -->

</div><!-- /row -->

<!-- Chart.js v4 + adaptador de datas (uma única inclusão) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<script>
// ── Paleta de cores ───────────────────────────────────────────────
var C = {
    dest:  'rgb(75, 192, 192)',
    pur:   'rgb(54, 162, 235)',
    toc:   'rgb(255, 99, 132)',
};

// ── Opções base para gráficos de série temporal ───────────────────
function baseOpts(yLabel, unit) {
    unit = unit || 'day';
    var timeCfg = (unit === 'month')
        ? { unit: 'month', tooltipFormat: 'MM/yyyy', displayFormats: { month: 'MM/yyyy' } }
        : { unit: 'day', tooltipFormat: 'dd/MM/yyyy', displayFormats: { day: 'dd/MM' } };
    return {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: { bodyFont: { size: 11 } }
        },
        scales: {
            x: {
                type: 'time',
                time: timeCfg,
                title: { display: false }
            },
            y: {
                type: 'linear',
                display: true,
                title: { display: !!yLabel, text: yLabel || '' },
                ticks: { font: { size: 10 } }
            }
        }
    };
}

function dsBase(label, color) {
    return {
        label: label,
        borderColor: color,
        backgroundColor: color.replace('rgb(', 'rgba(').replace(')', ',0.08)'),
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.3,
        fill: false,
        data: []
    };
}

// ── Criar gráficos (vazios; serão preenchidos pelo AJAX) ──────────
var chartCond = new Chart(document.getElementById('cond'), {
    type: 'line',
    data: { datasets: [dsBase('Destilada', C.dest), dsBase('Purificada', C.pur)] },
    options: baseOpts('µS/cm')
});
var chartPH = new Chart(document.getElementById('ph'), {
    type: 'line',
    data: { datasets: [dsBase('Destilada', C.dest), dsBase('Purificada', C.pur)] },
    options: baseOpts('pH')
});
var chartTCdest = new Chart(document.getElementById('TCdest'), {
    type: 'line',
    data: { datasets: [dsBase('TOC', C.toc)] },
    options: baseOpts(null, 'month')
});
var chartTCpur = new Chart(document.getElementById('TCpur'), {
    type: 'line',
    data: { datasets: [dsBase('TOC', C.toc)] },
    options: baseOpts(null, 'month')
});

// ── Utilitários de data ───────────────────────────────────────────
var today = new Date(); today.setHours(0,0,0,0);
var mesesLPT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

function fmt(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
}
function fmtHuman(iso) {
    var p = iso.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}
function addDays(d, n) { var r = new Date(d); r.setDate(r.getDate() + n); return r; }
function isoToDate(s) { var p = s.split('-'); return new Date(parseInt(p[0]), parseInt(p[1])-1, parseInt(p[2])); }

// ── Estado da navegação ───────────────────────────────────────────
var activePreset  = 'mes';
var periodOffset  = 0;        // 0 = período actual, -1 = um período atrás, ...
var customDe      = null;     // datas base para modo custom
var customAte     = null;
var customDays    = 0;        // duração em dias do range custom

// ── Estado da navegação dos gráficos de TOC (sempre por mês) ─────
var tocOffset = 0;            // 0 = mês actual, -1 = mês anterior, ...

// ── Calcular range com offset ─────────────────────────────────────
function getRangeWithOffset(preset, off) {
    var de, ate, label;

    if (preset === 'mes') {
        var base = new Date(today.getFullYear(), today.getMonth() + off, 1);
        de = fmt(base);
        ate = (off === 0) ? fmt(today)
                          : fmt(new Date(base.getFullYear(), base.getMonth() + 1, 0));
        label = mesesLPT[base.getMonth()] + ' ' + base.getFullYear();

    } else if (preset === 'semana') {
        var dow = today.getDay();
        var startCur = addDays(today, -(dow === 0 ? 6 : dow - 1)); // Monday
        var start = addDays(startCur, off * 7);
        var end   = addDays(start, 6);
        if (off === 0 && end > today) end = new Date(today);
        de = fmt(start); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === 'ano') {
        var year = today.getFullYear() + off;
        de  = year + '-01-01';
        ate = (off === 0) ? fmt(today) : year + '-12-31';
        label = String(year);

    } else if (preset === '7d') {
        var end = addDays(today, off * 7);
        if (end > today) end = new Date(today);
        de = fmt(addDays(end, -6)); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === '15d') {
        var end = addDays(today, off * 15);
        if (end > today) end = new Date(today);
        de = fmt(addDays(end, -14)); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === '30d') {
        var end = addDays(today, off * 30);
        if (end > today) end = new Date(today);
        de = fmt(addDays(end, -29)); ate = fmt(end);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);

    } else if (preset === 'custom' && customDe && customAte) {
        var step    = customDays * off;
        var newAte  = addDays(isoToDate(customAte), step);
        if (newAte > today) newAte = new Date(today);
        var newDe   = addDays(newAte, -(customDays - 1));
        de = fmt(newDe); ate = fmt(newAte);
        label = fmtHuman(de) + ' – ' + fmtHuman(ate);
    }

    return { de: de, ate: ate, label: label || '' };
}

// ── Aplicar preset + offset ───────────────────────────────────────
function applyNav() {
    var range = getRangeWithOffset(activePreset, periodOffset);

    // Label no filtro
    $('#wqLabel').text(range.label);

    // Seta direita: desactivada quando estamos no período actual (offset=0)
    $('#wqNext').prop('disabled', periodOffset >= 0);

    // Botão exportar: URL sempre sincronizada com o período visível
    $('#btnExport').attr('href', 'waterqc_export.php?de=' + range.de + '&ate=' + range.ate);

    loadCondPH(range.de, range.ate);
}

// ── Aplicar navegação anual dos gráficos de TOC ───────────────────
function applyTocNav() {
    var range = getRangeWithOffset('ano', tocOffset);

    $('#tocLabel').text(range.label);
    $('#tocNext').prop('disabled', tocOffset >= 0);

    loadTOC(range.de, range.ate);
}

// ── Carregar Condutividade / pH via AJAX ──────────────────────────
function loadCondPH(de, ate) {
    ['cond-empty','ph-empty'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('d-none');
    });
    [chartCond, chartPH].forEach(function(ch) {
        ch.data.datasets.forEach(function(ds) { ds.data = []; });
    });

    $.getJSON('waterqcdata.php', { de: de, ate: ate }, function (resp) {
    var phcond = resp.phcond || [];

    // Separar pH/condutividade por tipo
    var destCond = [], purCond = [], destPH = [], purPH = [];
    phcond.forEach(function (r) {
        if (r.tipo == 2) {
            destCond.push({ x: r.dia, y: r.condutivity });
            destPH.push(  { x: r.dia, y: r.pH });
        } else {
            purCond.push({ x: r.dia, y: r.condutivity });
            purPH.push(  { x: r.dia, y: r.pH });
        }
    });

    chartCond.data.datasets[0].data = destCond;
    chartCond.data.datasets[1].data = purCond;
    chartCond.update();
    if (!destCond.length && !purCond.length)
        document.getElementById('cond-empty').classList.remove('d-none');

    chartPH.data.datasets[0].data = destPH;
    chartPH.data.datasets[1].data = purPH;
    chartPH.update();
    if (!destPH.length && !purPH.length)
        document.getElementById('ph-empty').classList.remove('d-none');
    }); // end $.getJSON
} // end loadCondPH

// ── Carregar TOC via AJAX (filtro mensal próprio) ──────────────────
function loadTOC(de, ate) {
    ['tcdest-empty','tcpur-empty'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('d-none');
    });
    [chartTCdest, chartTCpur].forEach(function(ch) {
        ch.data.datasets.forEach(function(ds) { ds.data = []; });
    });

    $.getJSON('waterqcdata.php', { de: de, ate: ate }, function (resp) {
    var toc = resp.toc || [];

    // Agregar por mês (média) e separar por tipo
    var sums = { 2: {}, 3: {} };
    toc.forEach(function (r) {
        var mes = r.dia.substring(0, 7); // YYYY-MM
        var tipo = (r.tipo == 2) ? 2 : 3;
        if (!sums[tipo][mes]) sums[tipo][mes] = { total: 0, n: 0 };
        if (r.TOC !== null && r.TOC !== undefined) {
            sums[tipo][mes].total += r.TOC;
            sums[tipo][mes].n++;
        }
    });

    function toMonthlyPoints(sumsByMonth) {
        return Object.keys(sumsByMonth).sort().map(function (mes) {
            var s = sumsByMonth[mes];
            return { x: mes + '-01', y: s.n ? (s.total / s.n) : null };
        });
    }

    var d_toc = toMonthlyPoints(sums[2]);
    var p_toc = toMonthlyPoints(sums[3]);

    chartTCdest.data.datasets[0].data = d_toc;
    chartTCdest.update();
    if (!d_toc.length)
        document.getElementById('tcdest-empty').classList.remove('d-none');

    chartTCpur.data.datasets[0].data = p_toc;
    chartTCpur.update();
    if (!p_toc.length)
        document.getElementById('tcpur-empty').classList.remove('d-none');
    }); // end $.getJSON
} // end loadTOC

$(document).ready(function () {

    // ── Inicializar com "Este mês" ────────────────────────────────
    activePreset  = 'mes';
    periodOffset  = 0;
    applyNav();

    // ── Gráficos de TOC: inicializar com o mês actual ─────────────
    tocOffset = 0;
    applyTocNav();

    // ── Clique nos pills ──────────────────────────────────────────
    $('.wq-pill').on('click', function () {
        $('.wq-pill').removeClass('active');
        $(this).addClass('active');
        activePreset = $(this).data('preset');
        periodOffset = 0;

        if (activePreset === 'custom') {
            $('#wqCustomRange').addClass('open');
            // Pre-preencher com o mês actual
            var base = getRangeWithOffset('mes', 0);
            $('#wqDe').val(base.de);
            $('#wqAte').val(base.ate);
            customDe   = base.de; customAte  = base.ate;
            customDays = Math.round((isoToDate(base.ate) - isoToDate(base.de)) / 86400000) + 1;
            applyNav();
        } else {
            $('#wqCustomRange').removeClass('open');
            applyNav();
        }
    });

    // ── Seta esquerda (período anterior) ─────────────────────────
    $('#wqPrev').on('click', function () {
        periodOffset--;
        applyNav();
    });

    // ── Seta direita (período seguinte) ──────────────────────────
    $('#wqNext').on('click', function () {
        if (periodOffset >= 0) return;
        periodOffset++;
        applyNav();
    });

    // ── Navegação mensal dos gráficos de TOC ──────────────────────
    $('#tocPrev').on('click', function () {
        tocOffset--;
        applyTocNav();
    });
    $('#tocNext').on('click', function () {
        if (tocOffset >= 0) return;
        tocOffset++;
        applyTocNav();
    });

    // ── Calendário personalizado — Aplicar ────────────────────────
    $('#wqApply').on('click', function () {
        var de  = $('#wqDe').val();
        var ate = $('#wqAte').val();
        if (!de || !ate) return;
        if (de > ate) { var t = de; de = ate; ate = t; }
        customDe   = de;
        customAte  = ate;
        customDays = Math.round((isoToDate(ate) - isoToDate(de)) / 86400000) + 1;
        periodOffset = 0;
        applyNav();
    });

    $('#wqDe, #wqAte').on('keydown', function (e) {
        if (e.key === 'Enter') $('#wqApply').trigger('click');
    });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
