<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/permissions.php';

$userIdNum = (int)preg_replace('/\D/', '', $_SESSION['Code'] ?? '');
$equipId   = (int)($_GET['id'] ?? 0);
if (!$equipId) { header('Location: index.php'); exit; }

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Equipamento (antes dos POST para usar $podeEditar) ────────────────
$stmt = $pdo->prepare(
    'SELECT e.*, COALESCE(l.designacao, e.Laboratorio) AS lab_nome
     FROM infodeqb_equipmentdeq e
     LEFT JOIN infodeqb_labs_ensino l ON l.lab_id = e.Laboratorio
     WHERE e.equipment_id = ?'
);
$stmt->execute([$equipId]);
$eq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$eq) { header('Location: index.php'); exit; }

$podeEditar = podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq);

// ── POST ──────────────────────────────────────────────────────────────
if (!empty($_POST)) {
    $acao = $_POST['_acao'] ?? '';

    // Acessos específicos (apenas admin global)
    if ($isAdmin && $acao === 'grant_access') {
        $tc = (int)preg_replace('/\D/', '', trim($_POST['target_code'] ?? ''));
        if ($tc) {
            try {
                $pdo->prepare(
                    'INSERT IGNORE INTO infodeqb_equipmentdeq_access (user_id, equipment_id, granted_by)
                     VALUES (?,?,?)'
                )->execute([$tc, $equipId, $userIdNum]);
                $_SESSION['_equip_flash'] = [t('EQUIP_ACCESS_GRANTED_MSG'), 'success'];
            } catch (Exception $e) {
                $_SESSION['_equip_flash'] = [t('EQUIP_ERROR_GRANT'), 'danger'];
            }
        } else {
            $_SESSION['_equip_flash'] = [t('EQUIP_INVALID_CODE'), 'warning'];
        }
        header('Location: equipment_details.php?id=' . $equipId); exit;
    }

    if ($isAdmin && $acao === 'revoke_access') {
        $pdo->prepare('DELETE FROM infodeqb_equipmentdeq_access WHERE id = ?')
            ->execute([(int)$_POST['access_id']]);
        $_SESSION['_equip_flash'] = [t('EQUIP_ACCESS_REVOKED'), 'success'];
        header('Location: equipment_details.php?id=' . $equipId); exit;
    }

}

$flashMsg = ''; $flashType = 'success';
if (isset($_SESSION['_equip_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_equip_flash'];
    unset($_SESSION['_equip_flash']);
}

// Acessos específicos (admin global)
$acessosEspecificos = [];
if ($isAdmin) {
    $s = $pdo->prepare(
        'SELECT * FROM infodeqb_equipmentdeq_access WHERE equipment_id=? ORDER BY granted_at DESC'
    );
    $s->execute([$equipId]);
    $acessosEspecificos = $s->fetchAll(PDO::FETCH_ASSOC);
}

// Documentos
$sDocs = $pdo->prepare(
    'SELECT * FROM infodeqb_equipment_docs WHERE equipment_id=? ORDER BY id ASC'
);
$sDocs->execute([$equipId]);
$docsList = $sDocs->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

// ── Imagem ────────────────────────────────────────────────────────────
$imgUrl = null;
$imgDir = __DIR__ . '/img/' . $eq['Laboratorio'] . '/';
foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
    if (file_exists($imgDir . $eq['equipment_id'] . '.' . $ext)) {
        $imgUrl = HTTP_DIR . '/infodeqb/equipments/img/'
                . rawurlencode($eq['Laboratorio']) . '/' . $eq['equipment_id'] . '.' . $ext;
        break;
    }
}

// ── Helper ────────────────────────────────────────────────────────────
function fmtField($v) {
    if (empty($v)) return null;
    $s = (string)$v;
    if ($s !== strip_tags($s)) {
        return strip_tags($s, '<a><br><b><strong><em><i><p><ul><ol><li><span>');
    }
    return nl2br(htmlspecialchars($s, ENT_QUOTES));
}

function docIcon($ext) {
    $map = [
        'pdf'  => 'fa-file-pdf text-danger',
        'doc'  => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'xls'  => 'fa-file-excel text-success',
        'xlsx' => 'fa-file-excel text-success',
        'txt'  => 'fa-file-alt text-muted',
    ];
    return isset($map[$ext]) ? $map[$ext] : 'fa-file text-muted';
}

$pageTitle = htmlspecialchars($eq['Equipamento'] ?? 'Equipamento') . ' — Equipamentos';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:8px">
  <div class="mr-auto">
    <h1><?= htmlspecialchars($eq['Equipamento'] ?? '') ?></h1>
    <small class="text-muted">
      <i class="fas fa-flask fa-xs me-1"></i><?= htmlspecialchars($eq['lab_nome'] ?? '') ?>
      <?php if ($eq['Marca']): ?>
        &nbsp;·&nbsp;<?= htmlspecialchars($eq['Marca']) ?>
      <?php endif; ?>
    </small>
  </div>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i><?= t('EQUIP_LIST') ?>
  </a>
  <?php if ($podeEditar): ?>
  <a href="edit_equipment.php?id=<?= $equipId ?>" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-edit me-1"></i><?= t('EDIT') ?>
  </a>
  <a href="delete.php?id=<?= $equipId ?>" class="btn btn-outline-danger btn-sm"
     onclick="return confirm(<?= json_encode(t('EQUIP_CONFIRM_DELETE')) ?>)">
    <i class="fas fa-trash me-1"></i><?= t('DELETE') ?>
  </a>
  <?php endif; ?>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3"
     role="alert" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">
  <?php if ($imgUrl): ?>
  <div class="col-md-4 mb-3">
    <div class="card shadow-sm text-center p-3">
      <img src="<?= htmlspecialchars($imgUrl) ?>"
           alt="<?= htmlspecialchars($eq['Equipamento'] ?? '') ?>"
           style="max-height:240px;object-fit:contain;width:100%">
    </div>
  </div>
  <div class="col-md-8">
  <?php else: ?>
  <div class="col-12">
  <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header py-2"><strong><?= t('EQUIP_IDENTIFICATION') ?></strong></div>
      <div class="card-body py-2">
        <div class="row" style="font-size:.85rem">
          <?php foreach ([
            t('EQUIP_BRAND')    => $eq['Marca'],
            t('EQUIP_MODEL')    => $eq['Modelo'],
            t('EQUIP_ACQ_YEAR') => $eq['AnoAquisicao'],
            t('EQUIP_QTY')      => $eq['Quantidade'],
            t('RESPONSIBLE')    => $eq['Responsavel'],
            t('EQUIP_TECH')     => $eq['Tecnico'],
          ] as $lbl => $val):
            if (empty($val)) continue; ?>
          <div class="col-6 col-md-4 mb-2">
            <div class="text-muted" style="font-size:.73rem"><?= htmlspecialchars($lbl) ?></div>
            <div class="font-weight-bold"><?= htmlspecialchars((string)$val) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if ($docsList): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
        <i class="fas fa-folder-open text-secondary"></i>
        <strong><?= t('EQUIP_DOCS_TITLE') ?></strong>
        <span class="badge badge-secondary ml-1" style="font-size:.75rem"><?= count($docsList) ?></span>
      </div>
      <div class="card-body py-2">
        <ul class="list-unstyled mb-0" style="font-size:.87rem">
          <?php foreach ($docsList as $i => $doc):
            $docExt = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
            $icon   = docIcon($docExt);
            $docUrl = HTTP_DIR . '/infodeqb/equipments/img/'
                    . rawurlencode($doc['laboratorio']) . '/' . rawurlencode($doc['filename']);
            $label  = $doc['descricao'] ?: $doc['filename'];
          ?>
          <li class="d-flex align-items-center py-1 <?= $i < count($docsList) - 1 ? 'border-bottom' : '' ?>">
            <i class="fas <?= $icon ?> mr-2 flex-shrink-0"></i>
            <div>
              <a href="<?= htmlspecialchars($docUrl) ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars($label) ?>
              </a>
              <?php if ($doc['descricao']): ?>
              <div class="text-muted" style="font-size:.76rem"><?= htmlspecialchars($doc['filename']) ?></div>
              <?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php foreach ([
    t('DESCRIPTION')       => $eq['Descricao'],
    t('EQUIP_CONDITIONS')  => $eq['Condicoes'],
    t('EQUIP_SCHEDULE')    => $eq['Horario'],
    t('EQUIP_SAMPLES')     => $eq['Amostras'],
    t('EQUIP_OPERATION')   => $eq['Operacao'],
    t('EQUIP_COST')        => $eq['Custo'],
    t('EQUIP_PROCEDURE')   => $eq['Procedimento'],
    t('OBSERVATIONS')      => $eq['Observacoes'],
] as $titulo => $conteudo):
    if (empty($conteudo)) continue; ?>
<div class="card shadow-sm mb-3">
  <div class="card-header py-2"><strong><?= htmlspecialchars($titulo) ?></strong></div>
  <div class="card-body py-2" style="font-size:.85rem"><?= fmtField($conteudo) ?></div>
</div>
<?php endforeach; ?>

<?php /* ── Acessos específicos (admin global) ─────────────────────── */ ?>
<?php if ($isAdmin): ?>
<div class="card shadow-sm mb-4 border-warning">
  <div class="card-header py-2 d-flex align-items-center">
    <i class="fas fa-key text-warning me-2"></i>
    <strong class="mr-auto"><?= t('EQUIP_ACCESS_TITLE') ?></strong>
    <small class="text-muted"><?= t('EQUIP_ADMIN_ONLY') ?></small>
  </div>
  <div class="card-body">
    <?php if ($acessosEspecificos): ?>
    <table class="table table-sm mb-3" style="font-size:.83rem">
      <thead>
        <tr>
          <th><?= t('EQUIP_ACCESS_USER') ?></th>
          <th><?= t('EQUIP_GRANTED_BY') ?></th>
          <th><?= t('DATE') ?></th>
          <th style="width:4em"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($acessosEspecificos as $ac): ?>
        <tr>
          <td><?= (int)$ac['user_id'] ?></td>
          <td><?= (int)$ac['granted_by'] ?></td>
          <td><?= substr($ac['granted_at'], 0, 16) ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="_acao"     value="revoke_access">
              <input type="hidden" name="access_id" value="<?= (int)$ac['id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-danger"
                      onclick="return confirm('Revogar?')">
                <i class="fas fa-times fa-xs"></i>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted small mb-3"><?= t('EQUIP_NO_ACCESS') ?></p>
    <?php endif; ?>
    <form method="post" class="d-flex align-items-end" style="gap:8px">
      <input type="hidden" name="_acao" value="grant_access">
      <div>
        <label class="small font-weight-bold mb-1 d-block">
          <?= t('EQUIP_ACCESS_GRANT') ?>
        </label>
        <input type="text" name="target_code" class="form-control form-control-sm"
               placeholder="ex: 248679" required style="width:160px">
      </div>
      <button type="submit" class="btn btn-warning btn-sm">
        <i class="fas fa-key me-1"></i><?= t('EQUIP_GRANT') ?>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
