<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!$isAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$modules = [
    'hr'     => ['label' => t('SEC_MODULE_HR'),     'icon' => 'fa-users',           'color' => '#6f42c1'],
    'water'  => ['label' => t('SEC_MODULE_WATER'),  'icon' => 'fa-water',           'color' => '#0e7490'],
    'exam'   => ['label' => t('SEC_MODULE_EXAM'),   'icon' => 'fa-archive',         'color' => '#b45309'],
    'mobile' => ['label' => t('SEC_MODULE_MOBILE'), 'icon' => 'fa-plane-arrival',   'color' => '#1a56db'],
];

$flashMsg = ''; $flashType = 'success';

if (!empty($_POST)) {
    $acao = $_POST['_acao'] ?? '';
    if ($acao === 'add') {
        $mod  = $_POST['module'] ?? '';
        $code = trim($_POST['user_code'] ?? '');
        $name = trim($_POST['user_name'] ?? '');
        // Normalizar formato up356946@up.pt
        if (!empty($code) && strpos($code, '@') === false) {
            $code = 'up' . preg_replace('/\D/', '', $code) . '@up.pt';
        }
        if (!isset($modules[$mod]) || empty($code)) {
            $_SESSION['_sec_flash'] = [t('SEC_ADMIN_INVALID'), 'warning'];
        } else {
            try {
                $pdo->prepare(
                    'INSERT IGNORE INTO infodeqb_section_admins (module, user_code, user_name, added_by) VALUES (?,?,?,?)'
                )->execute([$mod, $code, $name ?: null, $_iqCurrentUser]);
                // Limpar cache de sessão
                unset($_SESSION['_iq_section_admins'], $_SESSION['_iq_section_admins_ts']);
                $_SESSION['_sec_flash'] = [t('SEC_ADMIN_ADDED'), 'success'];
            } catch (Exception $e) {
                $_SESSION['_sec_flash'] = ['Erro ao adicionar.', 'danger'];
            }
        }
        header('Location: admin-sections.php'); exit;

    } elseif ($acao === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM infodeqb_section_admins WHERE id = ?')->execute([$id]);
        unset($_SESSION['_iq_section_admins'], $_SESSION['_iq_section_admins_ts']);
        $_SESSION['_sec_flash'] = [t('SEC_ADMIN_REMOVED'), 'success'];
        header('Location: admin-sections.php'); exit;
    }
}

if (isset($_SESSION['_sec_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_sec_flash'];
    unset($_SESSION['_sec_flash']);
}

// Carregar todos os admins de secção
$rows = $pdo->query(
    'SELECT * FROM infodeqb_section_admins ORDER BY module, user_code'
)->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por módulo
$byModule = [];
foreach ($rows as $r) $byModule[$r['module']][] = $r;

Database::disconnect();

$pageTitle = 'Gestão de Admins de Secção';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto">
    <i class="fas fa-user-cog fa-sm me-2 text-muted"></i>
    <?= t('SEC_ADMIN_TITLE') ?>
  </h1>
  <a href="<?= HTTP_DIR ?>/infodeqb/" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Dashboard
  </a>
</div>

<div class="alert alert-secondary" style="display:block;font-size:.84rem">
  <i class="fas fa-info-circle me-1"></i>
  Os <strong>admins globais</strong> estão definidos directamente em <code>inc/admins.php</code> e não são geridos aqui.
  Esta página gere apenas admins de módulos específicos.
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">
<?php foreach ($modules as $modKey => $modInfo): ?>
<div class="col-md-6 mb-4">
  <div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center">
      <i class="fas <?= $modInfo['icon'] ?> me-2" style="color:<?= $modInfo['color'] ?>"></i>
      <strong class="mr-auto"><?= htmlspecialchars($modInfo['label']) ?></strong>
      <span class="badge badge-secondary"><?= count($byModule[$modKey] ?? []) ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (!empty($byModule[$modKey])): ?>
      <table class="table table-sm mb-0" style="font-size:.83rem">
        <thead class="">
          <tr>
            <th>Código UP</th>
            <th>Nome</th>
            <th>Adicionado em</th>
            <th style="width:3em"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($byModule[$modKey] as $a): ?>
          <tr>
            <td class="align-middle">
              <code style="font-size:.78rem"><?= htmlspecialchars($a['user_code']) ?></code>
            </td>
            <td class="align-middle"><?= htmlspecialchars($a['user_name'] ?? '—') ?></td>
            <td class="align-middle" style="font-size:.75rem"><?= substr($a['added_at'], 0, 10) ?></td>
            <td class="align-middle">
              <form method="post" class="d-inline">
                <input type="hidden" name="_acao" value="remove">
                <input type="hidden" name="id"    value="<?= (int)$a['id'] ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger"
                        onclick="return confirm('Remover este admin?')">
                  <i class="fas fa-times fa-xs"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-muted small p-3 mb-0"><?= t('SEC_ADMIN_NONE') ?></p>
      <?php endif; ?>
    </div>
    <!-- Adicionar novo admin -->
    <div class="card-footer py-2">
      <form method="post" class="d-flex align-items-end" style="gap:6px">
        <input type="hidden" name="_acao"  value="add">
        <input type="hidden" name="module" value="<?= $modKey ?>">
        <div>
          <label class="small font-weight-bold mb-1 d-block">Código UP</label>
          <input type="text" name="user_code" class="form-control form-control-sm"
                 placeholder="up356946 ou up356946@up.pt"
                 style="width:190px" required>
        </div>
        <div>
          <label class="small font-weight-bold mb-1 d-block">Nome <small class="text-muted">(opcional)</small></label>
          <input type="text" name="user_name" class="form-control form-control-sm"
                 placeholder="Nome do utilizador" style="width:190px">
        </div>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="fas fa-plus me-1"></i><?= t('ADD') ?>
        </button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
