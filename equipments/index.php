<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/permissions.php';

$userIdNum = (int)preg_replace('/\D/', '', $_SESSION['Code'] ?? '');

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$labsDoUser = getLabsDoUtilizador($pdo, $userIdNum, $isAdmin);
$podeGerir  = !empty($labsDoUser);

// ── Dados ─────────────────────────────────────────────────────────
$labs = $pdo->query(
    'SELECT lab_id, designacao FROM infodeqb_labs_ensino ORDER BY designacao'
)->fetchAll(PDO::FETCH_ASSOC);

$equipamentos = $pdo->query(
    'SELECT e.equipment_id, e.Laboratorio, e.Equipamento, e.Marca,
            e.Modelo, e.AnoAquisicao, e.Quantidade,
            COALESCE(l.designacao, e.Laboratorio) AS lab_nome
     FROM infodeqb_equipmentdeq e
     LEFT JOIN infodeqb_labs_ensino l ON l.lab_id = e.Laboratorio
     ORDER BY lab_nome ASC, e.Equipamento ASC'
)->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

// Agrupar por lab
$porLab = [];
foreach ($equipamentos as $eq) {
    $porLab[$eq['lab_nome']][] = $eq;
}
ksort($porLab);

$pageTitle = 'Equipamentos';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:10px">
  <h1 class="mr-auto mb-0">
    <i class="fas fa-microscope fa-sm me-2 text-muted"></i>
    <?= t('EQUIP_TITLE') ?>
  </h1>
  <?php if ($podeGerir): ?>
  <a href="edit_equipment.php" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i><?= t('EQUIP_ADD') ?>
  </a>
  <?php endif; ?>
</div>

<?php /* ── Filtros ───────────────────────────────────────────────── */ ?>
<div class="card mb-4 shadow-sm">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap align-items-center" style="gap:10px">
      <div style="flex:1;min-width:200px">
        <div class="input-group input-group-sm">
          <div class="input-group-prepend">
            <span class="input-group-text bg-white">
              <i class="fas fa-search fa-xs text-muted"></i>
            </span>
          </div>
          <input type="text" id="searchInput" class="form-control border-left-0"
                 placeholder="<?= t('EQUIP_SEARCH') ?>" autocomplete="off">
        </div>
      </div>
      <div style="min-width:220px">
        <select id="filterLab" class="form-control form-control-sm">
          <option value=""><?= t('EQUIP_ALL_LABS') ?></option>
          <?php foreach ($labs as $l): ?>
          <option value="<?= htmlspecialchars($l['designacao']) ?>">
            <?= htmlspecialchars($l['designacao']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <span class="text-muted small" id="filterCount"></span>
    </div>
  </div>
</div>

<?php /* ── Equipamentos agrupados por lab ─────────────────────────── */ ?>
<div id="equipList">
<?php foreach ($porLab as $labNome => $eqs):
  $labId = $eqs[0]['Laboratorio'] ?? '';
  $podeAdicionarAqui = $isAdmin || in_array($labId, $labsDoUser);
?>
<div class="lab-group mb-4" data-lab="<?= htmlspecialchars($labNome) ?>">
  <div class="d-flex align-items-center mb-0 px-3 py-2 rounded-top"
       style="background:var(--iq-blue-light)">
    <h6 class="mb-0 font-weight-bold me-auto" style="font-size:.88rem;color:var(--iq-blue)">
      <i class="fas fa-flask fa-xs me-1"></i><?= htmlspecialchars($labNome) ?>
    </h6>
    <span class="badge" style="background:var(--iq-blue);color:#fff"><?= count($eqs) ?></span>
    <?php if ($podeAdicionarAqui): ?>
    <a href="edit_equipment.php?lab=<?= urlencode($labId) ?>"
       title="Adicionar equipamento a este laboratório"
       class="ml-2" style="color:var(--iq-blue)">
      <i class="fas fa-plus-square fa-xs"></i>
    </a>
    <?php endif; ?>
  </div>
  <div class="card shadow-sm" style="border-top:none;border-radius:0 0 .375rem .375rem">
    <div class="card-body p-0">
      <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
        <thead class="">
          <tr>
            <th style="width:20em">Equipamento</th>
            <th style="width:14em">Marca / Modelo</th>
            <th class="text-center" style="width:5em">Ano</th>
            <th class="text-center" style="width:4em"><?= t('EQUIP_QTY') ?></th>
            <th style="width:4em" class="text-center"><?= t('ACTIONS') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($eqs as $eq): ?>
          <tr class="equip-row"
              data-nome="<?= htmlspecialchars(mb_strtolower($eq['Equipamento'] ?? ''), ENT_QUOTES) ?>"
              data-marca="<?= htmlspecialchars(mb_strtolower($eq['Marca'] ?? ''), ENT_QUOTES) ?>">
            <td class="align-middle font-weight-bold">
              <?= htmlspecialchars($eq['Equipamento'] ?? '') ?>
            </td>
            <td class="align-middle text-muted" style="font-size:.78rem">
              <?= htmlspecialchars(trim(($eq['Marca'] ?? '') . ' ' . ($eq['Modelo'] ?? ''))) ?>
            </td>
            <td class="align-middle text-center">
              <?= htmlspecialchars($eq['AnoAquisicao'] ?? '') ?>
            </td>
            <td class="align-middle text-center">
              <?= (int)($eq['Quantidade'] ?? 1) ?>
            </td>
            <td class="align-middle text-center">
              <a href="equipment_details.php?id=<?= (int)$eq['equipment_id'] ?>"
                title="Ver detalhe">
                <i class="fas fa-eye fa-s"></i>
              </a>
             <a href="edit_equipment.php?id=<?= (int)$eq['equipment_id'] ?>"
                  title="Editar">
                <i class="fas fa-edit fa-s"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<script>
var totalEquip = <?= array_sum(array_map('count', $porLab)) ?>;

function updateFilter() {
    var search = $('#searchInput').val().toLowerCase().trim();
    var lab    = $('#filterLab').val();
    var visible = 0;

    $('.lab-group').each(function () {
        var labName = $(this).data('lab');
        if (lab && labName !== lab) { $(this).hide(); return; }

        var anyRow = false;
        $(this).find('.equip-row').each(function () {
            var nome  = $(this).data('nome')  || '';
            var marca = $(this).data('marca') || '';
            var match = !search || nome.indexOf(search) > -1 || marca.indexOf(search) > -1;
            $(this).toggle(match);
            if (match) { anyRow = true; visible++; }
        });
        $(this).toggle(anyRow);
    });

    $('#filterCount').text(visible + ' de ' + totalEquip);
}

$(document).ready(function () {
    updateFilter();
    $('#searchInput').on('keyup', updateFilter);
    $('#filterLab').on('change', updateFilter);
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
