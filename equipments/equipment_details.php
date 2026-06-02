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

// ── POST: gerir acessos específicos (apenas admin global) ─────────
$flashMsg = ''; $flashType = 'success';
if (!empty($_POST) && $isAdmin) {
    $acao = $_POST['_acao'] ?? '';
    if ($acao === 'grant_access') {
        $tc = (int)preg_replace('/\D/', '', trim($_POST['target_code'] ?? ''));
        if ($tc) {
            try {
                $pdo->prepare(
                    'INSERT IGNORE INTO infodeqb_equipmentdeq_access (user_id, equipment_id, granted_by)
                     VALUES (?,?,?)'
                )->execute([$tc, $equipId, $userIdNum]);
                $_SESSION['_equip_flash'] = ['Acesso concedido ao utilizador ' . $tc . '.', 'success'];
            } catch (Exception $e) {
                $_SESSION['_equip_flash'] = ['Erro ao conceder acesso.', 'danger'];
            }
        } else {
            $_SESSION['_equip_flash'] = ['Código UP inválido.', 'warning'];
        }
    } elseif ($acao === 'revoke_access') {
        $pdo->prepare('DELETE FROM infodeqb_equipmentdeq_access WHERE id = ?')
            ->execute([(int)$_POST['access_id']]);
        $_SESSION['_equip_flash'] = ['Acesso revogado.', 'success'];
    }
    header('Location: equipment_details.php?id=' . $equipId); exit;
}
if (isset($_SESSION['_equip_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_equip_flash'];
    unset($_SESSION['_equip_flash']);
}

// ── Equipamento ───────────────────────────────────────────────────
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

// Acessos específicos (admin global)
$acessosEspecificos = [];
if ($isAdmin) {
    $s = $pdo->prepare('SELECT * FROM infodeqb_equipmentdeq_access WHERE equipment_id=? ORDER BY granted_at DESC');
    $s->execute([$equipId]);
    $acessosEspecificos = $s->fetchAll(PDO::FETCH_ASSOC);
}
Database::disconnect();

// ── Imagem ────────────────────────────────────────────────────────
$imgUrl = null;
$imgDir = __DIR__ . '/img/' . $eq['Laboratorio'] . '/';
foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
    if (file_exists($imgDir . $eq['equipment_id'] . '.' . $ext)) {
        $imgUrl = HTTP_DIR . '/infodeqb/equipments/img/'
                . rawurlencode($eq['Laboratorio']) . '/' . $eq['equipment_id'] . '.' . $ext;
        break;
    }
}

function fmtField($v) {
    if (empty($v)) return null;
    return nl2br(htmlspecialchars(strip_tags((string)$v), ENT_QUOTES));
}

$pageTitle = htmlspecialchars($eq['Equipamento'] ?? 'Equipamento') . ' — Equipamentos';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:8px">
  <div class="mr-auto">
    <h1><?= htmlspecialchars($eq['Equipamento'] ?? '') ?></h1>
    <small class="text-muted">
      <i class="fas fa-flask fa-xs mr-1"></i><?= htmlspecialchars($eq['lab_nome'] ?? '') ?>
      <?php if ($eq['Marca']): ?>
        &nbsp;·&nbsp;<?= htmlspecialchars($eq['Marca']) ?>
      <?php endif; ?>
    </small>
  </div>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left mr-1"></i>Lista
  </a>
  <?php if ($podeEditar): ?>
  <a href="edit_equipment.php?id=<?= $equipId ?>" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-edit mr-1"></i><?= t('EDIT') ?>
  </a>
  <a href="delete.php?id=<?= $equipId ?>" class="btn btn-outline-danger btn-sm"
     onclick="return confirm('Eliminar este equipamento permanentemente?')">
    <i class="fas fa-trash mr-1"></i><?= t('DELETE') ?>
  </a>
  <?php endif; ?>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3"
     role="alert" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
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
            'Marca'          => $eq['Marca'],
            'Modelo'         => $eq['Modelo'],
            'Ano aquisição'  => $eq['AnoAquisicao'],
            'Quantidade'     => $eq['Quantidade'],
            'Responsável'    => $eq['Responsavel'],
            'Técnico'        => $eq['Tecnico'],
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

  </div>
</div>

<?php foreach ([
    'Descrição'               => $eq['Descricao'],
    'Condições de utilização' => $eq['Condicoes'],
    'Horário'                 => $eq['Horario'],
    'Tipo de amostras'        => $eq['Amostras'],
    'Operação'                => $eq['Operacao'],
    'Custo'                   => $eq['Custo'],
    'Procedimento'            => $eq['Procedimento'],
    'Observações'             => $eq['Observacoes'],
] as $titulo => $conteudo):
    if (empty($conteudo)) continue; ?>
<div class="card shadow-sm mb-3">
  <div class="card-header py-2"><strong><?= htmlspecialchars($titulo) ?></strong></div>
  <div class="card-body py-2" style="font-size:.85rem"><?= fmtField($conteudo) ?></div>
</div>
<?php endforeach; ?>

<?php /* ── Acessos específicos (admin global) ─────────────────── */ ?>
<?php if ($isAdmin): ?>
<div class="card shadow-sm mb-4 border-warning">
  <div class="card-header py-2 d-flex align-items-center">
    <i class="fas fa-key text-warning mr-2"></i>
    <strong class="mr-auto"><?= t('EQUIP_ACCESS_TITLE') ?></strong>
    <small class="text-muted">só visível ao admin global</small>
  </div>
  <div class="card-body">
    <?php if ($acessosEspecificos): ?>
    <table class="table table-sm mb-3" style="font-size:.83rem">
      <thead class="thead-light">
        <tr><th>Utilizador (UP)</th><th>Concedido por</th><th>Data</th><th style="width:4em"></th></tr>
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
        <i class="fas fa-key mr-1"></i><?= t('EQUIP_GRANT') ?>
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
