<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!$isAdmin) { http_response_code(403); exit('Acesso negado.'); }

$userIdNum = (int)preg_replace('/\D/', '', $_SESSION['Code'] ?? '');

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$msg = ''; $msgType = 'success';

// ── POST handlers ─────────────────────────────────────────────────────────────
if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_lab_resp') {
        $labId  = trim($_POST['lab_id']  ?? '');
        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$labId || !$userId) {
            $msg = 'Lab e código UP são obrigatórios.'; $msgType = 'danger';
        } else {
            $chk = $pdo->prepare("SELECT id FROM infodeqb_lab_responsibles WHERE user_id=? AND lab_id=?");
            $chk->execute([$userId, $labId]);
            if ($chk->fetchColumn()) {
                $msg = 'Já existe esta associação.'; $msgType = 'warning';
            } else {
                $pdo->prepare("INSERT INTO infodeqb_lab_responsibles (user_id,lab_id) VALUES (?,?)")
                    ->execute([$userId, $labId]);
                $msg = 'Responsável adicionado.';
            }
        }
        header('Location: admin-permissions.php?tab=lab&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }

    if ($action === 'del_lab_resp') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM infodeqb_lab_responsibles WHERE id=?")->execute([$id]);
        $msg = 'Removido.';
        header('Location: admin-permissions.php?tab=lab&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }

    if ($action === 'add_eq_access') {
        $eqId   = (int)($_POST['equipment_id'] ?? 0);
        $userId = (int)($_POST['user_id']      ?? 0);
        if (!$eqId || !$userId) {
            $msg = 'Equipamento e código UP são obrigatórios.'; $msgType = 'danger';
        } else {
            $chk = $pdo->prepare("SELECT id FROM infodeqb_equipmentdeq_access WHERE user_id=? AND equipment_id=?");
            $chk->execute([$userId, $eqId]);
            if ($chk->fetchColumn()) {
                $msg = 'Já existe este acesso.'; $msgType = 'warning';
            } else {
                $pdo->prepare(
                    "INSERT INTO infodeqb_equipmentdeq_access (user_id,equipment_id,granted_by,granted_at)
                     VALUES (?,?,?,NOW())"
                )->execute([$userId, $eqId, $userIdNum]);
                $msg = 'Acesso concedido.';
            }
        }
        header('Location: admin-permissions.php?tab=eq&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }

    if ($action === 'del_eq_access') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM infodeqb_equipmentdeq_access WHERE id=?")->execute([$id]);
        $msg = 'Removido.';
        header('Location: admin-permissions.php?tab=eq&msg=' . urlencode($msg) . '&t=' . $msgType); exit;
    }
}

// ── Dados ─────────────────────────────────────────────────────────────────────
$labs = $pdo->query(
    "SELECT lab_id, designacao FROM infodeqb_labs_ensino ORDER BY lab_id"
)->fetchAll(PDO::FETCH_ASSOC);

$labResps = $pdo->query(
    "SELECT lr.id, lr.user_id, lr.lab_id, l.designacao
     FROM infodeqb_lab_responsibles lr
     LEFT JOIN infodeqb_labs_ensino l ON l.lab_id = lr.lab_id
     ORDER BY lr.lab_id, lr.user_id"
)->fetchAll(PDO::FETCH_ASSOC);

$equipamentos = $pdo->query(
    "SELECT equipment_id, Equipamento, Laboratorio FROM infodeqb_equipmentdeq ORDER BY Laboratorio, Equipamento"
)->fetchAll(PDO::FETCH_ASSOC);

$eqAccess = $pdo->query(
    "SELECT a.id, a.user_id, a.equipment_id, a.granted_by, a.granted_at,
            e.Equipamento, e.Laboratorio,
            COALESCE(l.designacao, e.Laboratorio) AS lab_nome
     FROM infodeqb_equipmentdeq_access a
     LEFT JOIN infodeqb_equipmentdeq e ON e.equipment_id = a.equipment_id
     LEFT JOIN infodeqb_labs_ensino l  ON l.lab_id = e.Laboratorio
     ORDER BY e.Laboratorio, e.Equipamento, a.user_id"
)->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

$activeTab = $_GET['tab'] ?? 'lab';
$flashMsg  = $_GET['msg'] ?? '';
$flashType = $_GET['t']   ?? 'success';

$pageTitle = 'Permissões de equipamentos';
$mainClass = 'iq-hr-page';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-key fa-sm me-2 text-muted"></i>Permissões de equipamentos</h1>
  <div class="iq-page-header-actions">
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>Voltar
    </a>
  </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-header" style="padding-bottom:0">
    <ul class="nav nav-tabs card-header-tabs">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'lab' ? 'active' : '' ?>" href="?tab=lab">
          <i class="fas fa-flask me-1"></i>Por laboratório
          <span class="badge bg-secondary ms-1"><?= count($labResps) ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'eq' ? 'active' : '' ?>" href="?tab=eq">
          <i class="fas fa-microscope me-1"></i>Por equipamento
          <span class="badge bg-secondary ms-1"><?= count($eqAccess) ?></span>
        </a>
      </li>
    </ul>
  </div>
  <div class="card-body">

  <?php if ($activeTab === 'lab'): ?>
  <!-- ── TAB POR LAB ───────────────────────────────────────────────────── -->
  <form method="post" class="row g-2 mb-3 align-items-end p-2 bg-light rounded border">
    <input type="hidden" name="action" value="add_lab_resp">
    <div class="col-sm-5">
      <label class="form-label form-label-sm">Laboratório</label>
      <select name="lab_id" class="form-select form-select-sm" required>
        <option value="">— escolher —</option>
        <?php foreach ($labs as $l): ?>
        <option value="<?= htmlspecialchars($l['lab_id']) ?>">
          <?= htmlspecialchars($l['lab_id']) ?> · <?= htmlspecialchars($l['designacao']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-4">
      <label class="form-label form-label-sm">Código UP <span class="text-muted">(só o número)</span></label>
      <input type="number" name="user_id" class="form-control form-control-sm" placeholder="ex: 123456" required min="1">
    </div>
    <div class="col-sm-3">
      <button type="submit" class="btn btn-sm btn-success w-100">
        <i class="fas fa-plus me-1"></i>Adicionar
      </button>
    </div>
  </form>

  <table class="table table-sm table-bordered table-hover" style="font-size:.85rem">
    <thead class="table-light">
      <tr><th>Lab</th><th>Designação</th><th>Código UP</th><th style="width:1%"></th></tr>
    </thead>
    <tbody>
    <?php if (empty($labResps)): ?>
    <tr><td colspan="4" class="text-center text-muted">Nenhum responsável configurado.</td></tr>
    <?php endif; ?>
    <?php foreach ($labResps as $lr): ?>
    <tr>
      <td><code><?= htmlspecialchars($lr['lab_id']) ?></code></td>
      <td><?= htmlspecialchars($lr['designacao'] ?? '—') ?></td>
      <td>up<?= htmlspecialchars($lr['user_id']) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Remover este responsável?')">
          <input type="hidden" name="action" value="del_lab_resp">
          <input type="hidden" name="id" value="<?= (int)$lr['id'] ?>">
          <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash fa-xs"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php else: ?>
  <!-- ── TAB POR EQUIPAMENTO ─────────────────────────────────────────── -->
  <p class="form-text mb-2" style="font-size:.8rem">
    <i class="fas fa-info-circle me-1 text-muted"></i>Use esta tab para conceder acesso a um equipamento específico fora do laboratório do responsável.
  </p>
  <form method="post" class="row g-2 mb-3 align-items-end p-2 bg-light rounded border">
    <input type="hidden" name="action" value="add_eq_access">
    <div class="col-sm-6">
      <label class="form-label form-label-sm">Equipamento</label>
      <select name="equipment_id" class="form-select form-select-sm" required>
        <option value="">— escolher —</option>
        <?php
        $curLab = null;
        foreach ($equipamentos as $eq):
            if ($eq['Laboratorio'] !== $curLab):
                if ($curLab !== null) echo '</optgroup>';
                echo '<optgroup label="' . htmlspecialchars($eq['Laboratorio']) . '">';
                $curLab = $eq['Laboratorio'];
            endif;
        ?>
        <option value="<?= (int)$eq['equipment_id'] ?>"><?= htmlspecialchars($eq['Equipamento']) ?></option>
        <?php endforeach; ?>
        <?php if ($curLab !== null) echo '</optgroup>'; ?>
      </select>
    </div>
    <div class="col-sm-3">
      <label class="form-label form-label-sm">Código UP <span class="text-muted">(só o número)</span></label>
      <input type="number" name="user_id" class="form-control form-control-sm" placeholder="ex: 123456" required min="1">
    </div>
    <div class="col-sm-3">
      <button type="submit" class="btn btn-sm btn-success w-100">
        <i class="fas fa-plus me-1"></i>Conceder acesso
      </button>
    </div>
  </form>

  <table class="table table-sm table-bordered table-hover" style="font-size:.85rem">
    <thead class="table-light">
      <tr><th>Equipamento</th><th>Lab</th><th>Código UP</th><th>Concedido em</th><th style="width:1%"></th></tr>
    </thead>
    <tbody>
    <?php if (empty($eqAccess)): ?>
    <tr><td colspan="5" class="text-center text-muted">Nenhum acesso específico configurado.</td></tr>
    <?php endif; ?>
    <?php foreach ($eqAccess as $a): ?>
    <tr>
      <td><?= htmlspecialchars($a['Equipamento'] ?? '—') ?></td>
      <td><code><?= htmlspecialchars($a['Laboratorio'] ?? '') ?></code> <span class="text-muted small"><?= htmlspecialchars($a['lab_nome'] ?? '') ?></span></td>
      <td>up<?= htmlspecialchars($a['user_id']) ?></td>
      <td class="text-muted small"><?= htmlspecialchars($a['granted_at'] ? substr($a['granted_at'], 0, 10) : '') ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Revogar este acesso?')">
          <input type="hidden" name="action" value="del_eq_access">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash fa-xs"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  </div><!-- /.card-body -->
</div><!-- /.card -->

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
