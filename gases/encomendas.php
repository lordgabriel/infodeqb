<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? array();
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser ?? '', $_iqAdminsGases);

$jsonFile = __DIR__ . '/data/encomendas.json';
$cfg = json_decode(file_get_contents($jsonFile), true) ?: array();

$email       = htmlspecialchars($cfg['email']       ?? 'gases@fe.up.pt');
$contrato    = htmlspecialchars($cfg['contrato']    ?? '');
$instrExtra  = htmlspecialchars($cfg['instrucoes_extra'] ?? '');
$fDesc       = htmlspecialchars($cfg['ficheiro']['descricao']       ?? '');
$fData       = htmlspecialchars($cfg['ficheiro']['data_atualizacao'] ?? '');
$fNome       = $cfg['ficheiro']['nome'] ?? 'Encomenda_gases_especiais.xlsx';
$gasesG      = $cfg['gases_garrafa']    ?? array();
$gasesL      = $cfg['gases_liquefeitos'] ?? array();

$pageTitle = 'Encomenda de Gases Especiais';
$mainClass = 'iq-main';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <div>
    <h1 class="iq-page-title">
      <i class="fas fa-flask me-2 text-primary"></i>Encomenda de Gases Especiais
    </h1>
    <p class="iq-page-sub"><?= $contrato ?></p>
  </div>
  <?php if ($isGasAdmin): ?>
  <div class="ms-auto">
    <a href="admin-encomendas.php" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-pen me-1"></i>Editar conteúdo
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- ── Como encomendar + Ficheiro ───────────────────────────────────── -->
<div class="row g-3 mb-4">

  <div class="col-lg-7">
    <div class="card h-100 border-primary" style="border-width:2px!important">
      <div class="card-header bg-primary text-white" style="font-weight:600">
        <i class="fas fa-info-circle me-2"></i>Como encomendar
      </div>
      <div class="card-body">
        <ol class="mb-3" style="line-height:2">
          <li>Descarregue o <strong>ficheiro de encomenda</strong> (botão à direita).</li>
          <li>Preencha os campos: conta Air Liquide, departamento, local de entrega, responsável pela receção, CCO e gás(es) pretendido(s).</li>
          <li>Envie o ficheiro preenchido para o endereço de email indicado abaixo.</li>
        </ol>
        <?php if ($instrExtra): ?>
        <p class="text-muted" style="font-size:.9rem"><?= nl2br($instrExtra) ?></p>
        <?php endif; ?>
        <div class="mt-3">
          <div class="text-muted mb-1" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em">Endereço de encomenda</div>
          <a href="mailto:<?= $email ?>" class="btn btn-lg btn-primary" style="font-family:monospace;letter-spacing:.04em">
            <i class="fas fa-envelope me-2"></i><?= $email ?>
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header" style="font-weight:600">
        <i class="fas fa-file-excel me-2 text-success"></i>Ficheiro de encomenda
      </div>
      <div class="card-body d-flex flex-column justify-content-between">
        <p class="text-muted mb-3"><?= nl2br($fDesc) ?></p>
        <div>
          <a href="<?= HTTP_DIR ?>/infodeqb/files/<?= htmlspecialchars($fNome) ?>"
             class="btn btn-success btn-lg"
             download="<?= htmlspecialchars($fNome) ?>">
            <i class="fas fa-download me-2"></i>Descarregar ficheiro (.xlsx)
          </a>
          <?php if ($fData): ?>
          <div class="text-muted mt-2" style="font-size:.78rem">
            <i class="fas fa-info-circle me-1"></i>Atualizado em <?= $fData ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Gases abrangidos ──────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span style="font-weight:600"><i class="fas fa-list me-2"></i>Gases abrangidos pelo contrato</span>
    <span class="text-muted" style="font-size:.82rem">Preços sem IVA · Concurso 2023</span>
  </div>
  <div class="card-body p-0">

    <!-- Gases em garrafa -->
    <div class="px-3 pt-3 pb-1">
      <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">
        <i class="fas fa-wind me-1"></i>Gases em garrafa
      </h6>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="tbl-gases">
        <thead class="table-light">
          <tr>
            <th>Gás</th><th>Pureza</th><th>Designação comercial</th><th>Garrafa</th>
            <th class="text-end">Prazo (dias)</th><th class="text-end">Preço/garrafa (s/IVA)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($gasesG as $g): ?>
          <?php $longo = (int)($g['prazo'] ?? 0) >= 30; ?>
          <tr>
            <td><?= htmlspecialchars($g['gas'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['pureza'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['designacao'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['garrafa'] ?? '') ?></td>
            <td class="text-end <?= $longo ? 'text-danger' : '' ?>"
                <?= $longo ? 'title="Prazo longo — planear com antecedência"' : '' ?>>
              <?= (int)($g['prazo'] ?? 0) ?><?= $longo ? ' ⚠' : '' ?>
            </td>
            <td class="text-end"><?= htmlspecialchars($g['preco'] ?? '') ?>&nbsp;€</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Gases liquefeitos -->
    <div class="px-3 pt-3 pb-1 mt-2">
      <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">
        <i class="fas fa-tint me-1"></i>Gases liquefeitos
      </h6>
    </div>
    <div class="table-responsive pb-3">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Produto</th><th>Pureza</th><th>Recipiente</th>
            <th class="text-end">Prazo (dias)</th><th class="text-end">Preço unitário (s/IVA)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($gasesL as $g): ?>
          <tr>
            <td><?= htmlspecialchars($g['gas'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['pureza'] ?? '') ?></td>
            <td><?= htmlspecialchars($g['recipiente'] ?? '') ?></td>
            <td class="text-end"><?= (int)($g['prazo'] ?? 0) ?></td>
            <td class="text-end"><?= htmlspecialchars($g['preco'] ?? '') ?>&nbsp;€/<?= htmlspecialchars($g['unidade'] ?? 'un') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="px-3 pb-3">
      <small class="text-muted">
        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
        Prazos assinalados com ⚠ são prazos longos (≥ 30 dias úteis) — planear as encomendas com antecedência.
        Os prazos podem ser prorrogados a requerimento do fornecedor, devidamente fundamentado.
      </small>
    </div>
  </div>
</div>

<script>
$(function () {
  $('#tbl-gases').DataTable({
    paging: false, info: false,
    language: { search: 'Filtrar:' },
    order: [[0, 'asc']]
  });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
