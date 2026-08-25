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
    'hr'     => ['label' => t('SEC_MODULE_HR'),     'icon' => 'fa-users',                'color' => '#6f42c1'],
    'water'  => ['label' => t('SEC_MODULE_WATER'),  'icon' => 'fa-water',                'color' => '#0e7490'],
    'exam'   => ['label' => t('SEC_MODULE_EXAM'),   'icon' => 'fa-archive',              'color' => '#b45309'],
    'mobile' => ['label' => t('SEC_MODULE_MOBILE'), 'icon' => 'fa-plane-arrival',        'color' => '#1a56db'],
    'dsd'    => ['label' => 'Distribuição Serviço Docente', 'icon' => 'fa-chalkboard-teacher', 'color' => '#0e7a55'],
    'gases'  => ['label' => 'Gases Especiais',             'icon' => 'fa-wind',               'color' => '#0891b2'],
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

<?php
/* ── Configuração de email / modo teste ──────────────────────────── */
$_isLocalhost = defined('HTTP_DIR') && strpos(HTTP_DIR, 'localhost') !== false;
$_testMode    = $_isLocalhost || (defined('MAIL_TEST_MODE') && MAIL_TEST_MODE === true);
$_testRecip   = (defined('MAIL_TEST_RECIPIENTS') && !empty(MAIL_TEST_RECIPIENTS))
                    ? implode(', ', MAIL_TEST_RECIPIENTS)
                    : '(não definido)';
$_configFile  = '/deqbwww.php';
?>
<div class="mt-4 mb-2" id="mail-config">
  <h5 class="mb-3" style="font-size:.95rem;font-weight:600">
    <i class="fas fa-envelope me-2 text-muted"></i>Configuração de email
  </h5>

  <div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
      <?php if ($_testMode): ?>
        <span class="badge" style="background:#7c2615">MODO TESTE ACTIVO</span>
      <?php else: ?>
        <span class="badge bg-success">MODO PRODUÇÃO — emails reais</span>
      <?php endif; ?>
      <small class="text-muted ms-1">Estado actual</small>
    </div>
    <div class="card-body" style="font-size:.84rem">

      <?php if ($_testMode): ?>
      <p class="mb-2">
        Os emails <strong>não chegam aos destinatários reais</strong>.
        São interceptados e enviados para:
        <code><?= htmlspecialchars($_testRecip) ?></code>
      </p>
      <p class="mb-0 text-muted">
        O corpo do email é também guardado em <code>hr/email_dev/</code> e registado em <code>hr/email_dev.log</code>.
      </p>
      <?php else: ?>
      <p class="mb-0">Os emails são enviados directamente aos destinatários reais.</p>
      <?php endif; ?>

      <hr class="my-3">

      <p class="mb-1"><strong>Para alterar o modo</strong>, editar o ficheiro <code><?= $_configFile ?></code> no servidor:</p>
      <table class="table table-sm table-bordered mb-2" style="font-size:.82rem;max-width:560px">
        <thead class="table-light"><tr><th>Situação</th><th>Definições em <code>deqbwww.php</code></th></tr></thead>
        <tbody>
          <tr>
            <td>🔴 <strong>Modo teste</strong> (emails interceptados)</td>
            <td><code>define('MAIL_TEST_MODE', true);</code></td>
          </tr>
          <tr>
            <td>🟢 <strong>Modo produção</strong> (emails reais)</td>
            <td><code>define('MAIL_TEST_MODE', false);</code></td>
          </tr>
        </tbody>
      </table>
      <p class="mb-1"><strong>Destinatários de teste</strong> — array na mesma linha:</p>
      <pre class="mb-0" style="font-size:.8rem;background:#f8f9fa;padding:8px 12px;border-radius:4px;border:1px solid #dee2e6">define('MAIL_TEST_RECIPIENTS', [
    'fmartins@fe.up.pt',
    'jfeyo@fe.up.pt',
    'deqbdir@fe.up.pt',
]);</pre>
      <p class="mt-2 mb-0 text-muted" style="font-size:.78rem">
        <i class="fas fa-info-circle me-1"></i>
        Em localhost o modo teste está <strong>sempre activo</strong> independentemente do valor de <code>MAIL_TEST_MODE</code>
        (detectado por <code>HTTP_DIR</code> conter "localhost").
      </p>
    </div>
  </div>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
