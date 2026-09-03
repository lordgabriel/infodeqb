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
    'hr'      => ['label' => t('SEC_MODULE_HR'),      'icon' => 'fa-users',             'color' => '#6f42c1'],
    'hr_list' => ['label' => t('SEC_MODULE_HR_LIST'), 'icon' => 'fa-list-ul',           'color' => '#374151'],
    'water'   => ['label' => t('SEC_MODULE_WATER'),   'icon' => 'fa-water',             'color' => '#0e7490'],
    'exam'    => ['label' => t('SEC_MODULE_EXAM'),    'icon' => 'fa-archive',           'color' => '#b45309'],
    'mobile'  => ['label' => t('SEC_MODULE_MOBILE'),  'icon' => 'fa-plane-arrival',     'color' => '#1a56db'],
    'dsd'     => ['label' => t('SEC_MODULE_DSD'),     'icon' => 'fa-chalkboard-teacher','color' => '#0e7a55'],
    'gases'   => ['label' => t('SEC_MODULE_GASES'),   'icon' => 'fa-wind',              'color' => '#0891b2'],
    'servdoc' => ['label' => 'Serviço Docente',       'icon' => 'fa-graduation-cap',    'color' => '#0f766e'],
];

$flashMsg = ''; $flashType = 'success';

// ── Controlo dos formulários ──────────────────────────────────────────────
$_formStateFile        = ROOT_DIR . '/infodeqb/areas/.form_state';
$_servdocFormStateFile = ROOT_DIR . '/infodeqb/servdoc/.form_state';

if (!empty($_POST)) {
    $acao = $_POST['_acao'] ?? '';
    if ($acao === 'areas_toggle') {
        $current = (is_file($_formStateFile) && trim(file_get_contents($_formStateFile)) === 'closed')
                   ? 'closed' : 'open';
        $newState = ($current === 'closed') ? 'open' : 'closed';
        file_put_contents($_formStateFile, $newState);
        $_SESSION['_sec_flash'] = [
            $newState === 'closed'
                ? t('AREAS_FORM_CLOSE_BTN') . ' — OK'
                : t('AREAS_FORM_OPEN_BTN') . ' — OK',
            'success'
        ];
        header('Location: admin-sections.php?tab=forms'); exit;
    } elseif ($acao === 'servdoc_toggle') {
        $current = (is_file($_servdocFormStateFile) && trim(file_get_contents($_servdocFormStateFile)) === 'closed')
                   ? 'closed' : 'open';
        $newState = ($current === 'closed') ? 'open' : 'closed';
        file_put_contents($_servdocFormStateFile, $newState);
        $_SESSION['_sec_flash'] = [
            $newState === 'closed' ? 'Serviço Docente — submissões fechadas' : 'Serviço Docente — submissões abertas',
            'success'
        ];
        header('Location: admin-sections.php?tab=forms'); exit;
    } elseif ($acao === 'add') {
        $mod  = $_POST['module'] ?? '';
        $code = trim($_POST['user_code'] ?? '');
        $name = trim($_POST['user_name'] ?? '');
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
                unset($_SESSION['_iq_section_admins'], $_SESSION['_iq_section_admins_ts']);
                $_SESSION['_sec_flash'] = [t('SEC_ADMIN_ADDED'), 'success'];
            } catch (Exception $e) {
                $_SESSION['_sec_flash'] = ['Erro ao adicionar.', 'danger'];
            }
        }
        header('Location: admin-sections.php?tab=admins'); exit;

    } elseif ($acao === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM infodeqb_section_admins WHERE id = ?')->execute([$id]);
        unset($_SESSION['_iq_section_admins'], $_SESSION['_iq_section_admins_ts']);
        $_SESSION['_sec_flash'] = [t('SEC_ADMIN_REMOVED'), 'success'];
        header('Location: admin-sections.php?tab=admins'); exit;
    }
}

$_activeTab = in_array($_GET['tab'] ?? '', ['admins', 'forms', 'email', 'equip']) ? $_GET['tab'] : 'admins';

if (isset($_SESSION['_sec_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_sec_flash'];
    unset($_SESSION['_sec_flash']);
}

$_areasFormClosed    = (is_file($_formStateFile) && trim(file_get_contents($_formStateFile)) === 'closed');
$_servdocFormClosed  = (is_file($_servdocFormStateFile) && trim(file_get_contents($_servdocFormStateFile)) === 'closed');

// Carregar todos os admins de secção
$rows = $pdo->query(
    'SELECT * FROM infodeqb_section_admins ORDER BY module, user_code'
)->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por módulo
$byModule = [];
foreach ($rows as $r) $byModule[$r['module']][] = $r;

Database::disconnect();

$pageTitle = t('SEC_ADMIN_PAGE_TITLE');
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<?php
$_isLocalhost = defined('HTTP_DIR') && strpos(HTTP_DIR, 'localhost') !== false;
$_testMode    = $_isLocalhost || (defined('MAIL_TEST_MODE') && MAIL_TEST_MODE === true);
$_testRecip   = (defined('MAIL_TEST_RECIPIENTS') && !empty(MAIL_TEST_RECIPIENTS))
                    ? implode(', ', MAIL_TEST_RECIPIENTS) : '(não definido)';
$_configFile  = '/deqbwww.php';
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

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php /* ── Tabs ── */ ?>
<ul class="nav nav-tabs mb-4" id="adminTabs">
  <li class="nav-item">
    <a class="nav-link <?= $_activeTab === 'admins' ? 'active' : '' ?>"
       href="?tab=admins">
      <i class="fas fa-users-cog me-1"></i>Admins de módulos
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $_activeTab === 'forms' ? 'active' : '' ?>"
       href="?tab=forms">
      <i class="fas fa-toggle-on me-1"></i>Formulários
      <?php if ($_areasFormClosed || $_servdocFormClosed): ?>
      <span class="badge" style="background:#7c2615;font-size:.7rem;vertical-align:middle">Closed</span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $_activeTab === 'email' ? 'active' : '' ?>"
       href="?tab=email">
      <i class="fas fa-envelope me-1"></i>Email
      <?php if ($_testMode): ?>
      <span class="badge" style="background:#7c2615;font-size:.7rem;vertical-align:middle">TESTE</span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $_activeTab === 'equip' ? 'active' : '' ?>"
       href="?tab=equip">
      <i class="fas fa-microscope me-1"></i>Equipamentos
    </a>
  </li>
</ul>

<?php /* ════════════════════════════════════════
         TAB: ADMINS
         ════════════════════════════════════════ */ ?>
<?php if ($_activeTab === 'admins'): ?>

<div class="alert alert-secondary mb-4" style="display:block;font-size:.84rem">
  <i class="fas fa-info-circle me-1"></i>
  Os <strong>admins globais</strong> estão definidos directamente em <code>inc/admins.php</code> e não são geridos aqui.
  Esta página gere apenas admins de módulos específicos.
</div>

<div class="d-flex justify-content-end mb-2" style="gap:.5rem">
  <button class="btn btn-xs btn-outline-secondary" id="expandAllBtn" onclick="
    document.querySelectorAll('#modAccordion .accordion-collapse:not(.show)').forEach(function(el){
      bootstrap.Collapse.getOrCreateInstance(el).show();
    });
  "><i class="fas fa-expand-alt fa-xs me-1"></i>Expandir tudo</button>
  <button class="btn btn-xs btn-outline-secondary" id="collapseAllBtn" onclick="
    document.querySelectorAll('#modAccordion .accordion-collapse.show').forEach(function(el){
      bootstrap.Collapse.getOrCreateInstance(el).hide();
    });
  "><i class="fas fa-compress-alt fa-xs me-1"></i>Colapsar tudo</button>
</div>

<div class="accordion" id="modAccordion">
<?php foreach ($modules as $modKey => $modInfo):
  $_modAdmins = $byModule[$modKey] ?? [];
  $_modCount  = count($_modAdmins);
  $_modOpen   = $_modCount > 0;
  $_accId     = 'acc-' . $modKey;
?>
<div class="accordion-item border mb-2" style="border-radius:6px;overflow:hidden">
  <h2 class="accordion-header" id="hd-<?= $_accId ?>">
    <button class="accordion-button <?= $_modOpen ? '' : 'collapsed' ?> py-2"
            type="button"
            data-bs-toggle="collapse" data-bs-target="#<?= $_accId ?>"
            aria-expanded="<?= $_modOpen ? 'true' : 'false' ?>"
            aria-controls="<?= $_accId ?>"
            style="font-size:.88rem;gap:.5rem">
      <i class="fas <?= $modInfo['icon'] ?> fa-sm" style="color:<?= $modInfo['color'] ?>;width:1.1em"></i>
      <span class="fw-semibold"><?= htmlspecialchars($modInfo['label']) ?></span>
      <span class="badge <?= $_modCount > 0 ? 'bg-primary' : 'badge-secondary' ?> ms-1" style="font-size:.72rem"><?= $_modCount ?></span>
    </button>
  </h2>
  <div id="<?= $_accId ?>" class="accordion-collapse collapse <?= $_modOpen ? 'show' : '' ?>"
       aria-labelledby="hd-<?= $_accId ?>">
    <div class="accordion-body p-0">
      <?php if (!empty($_modAdmins)): ?>
      <table class="table table-sm mb-0" style="font-size:.83rem">
        <thead class="table-light">
          <tr>
            <th>Código UP</th>
            <th>Nome</th>
            <th>Adicionado em</th>
            <th style="width:3em"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($_modAdmins as $a): ?>
          <tr>
            <td class="align-middle"><code style="font-size:.78rem"><?= htmlspecialchars($a['user_code']) ?></code></td>
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
      <p class="text-muted small px-3 pt-3 pb-2 mb-0"><?= t('SEC_ADMIN_NONE') ?></p>
      <?php endif; ?>
      <div class="border-top px-3 py-2" style="background:var(--iq-gray-50,#f9fafb)">
        <form method="post" class="d-flex flex-wrap align-items-end" style="gap:6px">
          <input type="hidden" name="_acao"  value="add">
          <input type="hidden" name="module" value="<?= $modKey ?>">
          <div>
            <label class="small fw-semibold mb-1 d-block">Código UP</label>
            <input type="text" name="user_code" class="form-control form-control-sm"
                   placeholder="up356946 ou up356946@up.pt" style="width:190px" required>
          </div>
          <div>
            <label class="small fw-semibold mb-1 d-block">Nome <small class="text-muted">(opcional)</small></label>
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
</div>
<?php endforeach; ?>
</div>

<?php /* ════════════════════════════════════════
         TAB: FORMULÁRIOS
         ════════════════════════════════════════ */ ?>
<?php elseif ($_activeTab === 'forms'): ?>

<?php
$_formCards = [
    [
        'icon'       => 'fa-sitemap',
        'title'      => t('AREAS_TITLE'),
        'acao'       => 'areas_toggle',
        'closed'     => $_areasFormClosed,
        'msg_open'   => 'As submissões estão abertas. Os utilizadores podem preencher e actualizar as suas respostas.',
        'msg_closed' => 'As submissões estão encerradas. Os utilizadores vêem as suas respostas em modo de leitura.',
        'btn_open'   => t('AREAS_FORM_OPEN_BTN'),
        'btn_close'  => t('AREAS_FORM_CLOSE_BTN'),
    ],
    [
        'icon'       => 'fa-chalkboard-teacher',
        'title'      => t('SERVDOC_TITLE'),
        'acao'       => 'servdoc_toggle',
        'closed'     => $_servdocFormClosed,
        'msg_open'   => 'As submissões de preferências de serviço docente estão abertas.',
        'msg_closed' => 'As submissões de preferências de serviço docente estão encerradas.',
        'btn_open'   => 'Abrir submissões',
        'btn_close'  => 'Fechar submissões',
    ],
];
foreach ($_formCards as $_fc):
?>
<div class="card shadow-sm mb-4" style="max-width:540px">
  <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
    <i class="fas <?= $_fc['icon'] ?> me-2 text-muted"></i>
    <strong class="mr-auto"><?= htmlspecialchars($_fc['title']) ?></strong>
    <?php if ($_fc['closed']): ?>
      <span class="badge" style="background:#7c2615"><?= t('AREAS_FORM_STATUS_CLOSED') ?></span>
    <?php else: ?>
      <span class="badge bg-success"><?= t('AREAS_FORM_STATUS_OPEN') ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body" style="font-size:.84rem">
    <p class="mb-3 text-muted"><?= $_fc['closed'] ? $_fc['msg_closed'] : $_fc['msg_open'] ?></p>
    <form method="post">
      <input type="hidden" name="_acao" value="<?= htmlspecialchars($_fc['acao']) ?>">
      <?php if ($_fc['closed']): ?>
      <button type="submit" class="btn btn-sm btn-success"
              onclick="return confirm('<?= htmlspecialchars($_fc['btn_open']) ?>?')">
        <i class="fas fa-lock-open me-1"></i><?= htmlspecialchars($_fc['btn_open']) ?>
      </button>
      <?php else: ?>
      <button type="submit" class="btn btn-sm btn-outline-warning"
              onclick="return confirm('<?= htmlspecialchars($_fc['btn_close']) ?>?')">
        <i class="fas fa-lock me-1"></i><?= htmlspecialchars($_fc['btn_close']) ?>
      </button>
      <?php endif; ?>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php /* ════════════════════════════════════════
         TAB: EMAIL
         ════════════════════════════════════════ */ ?>
<?php elseif ($_activeTab === 'email'): ?>

<div class="card shadow-sm" id="mail-config">
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
      São interceptados e enviados para: <code><?= htmlspecialchars($_testRecip) ?></code>
    </p>
    <p class="mb-0 text-muted">
      O corpo do email é também guardado em <code>hr/email_dev/</code> e registado em <code>hr/email_dev.log</code>.
    </p>
    <?php else: ?>
    <p class="mb-0">Os emails são enviados directamente aos destinatários reais.</p>
    <?php endif; ?>

    <hr class="my-3">

    <p class="mb-1"><strong>Para alterar o modo</strong>, editar <code><?= $_configFile ?></code> no servidor:</p>
    <table class="table table-sm mb-2" style="font-size:.82rem;max-width:560px">
      <thead class="table-light"><tr><th>Situação</th><th>Definição em <code>deqbwww.php</code></th></tr></thead>
      <tbody>
        <tr><td>🔴 <strong>Modo teste</strong></td><td><code>define('MAIL_TEST_MODE', true);</code></td></tr>
        <tr><td>🟢 <strong>Modo produção</strong></td><td><code>define('MAIL_TEST_MODE', false);</code></td></tr>
      </tbody>
    </table>
    <p class="mb-1"><strong>Destinatários de teste:</strong></p>
    <pre class="mb-0" style="font-size:.8rem;background:#f8f9fa;padding:8px 12px;border-radius:4px;border:1px solid #dee2e6">define('MAIL_TEST_RECIPIENTS', [
    'fmartins@fe.up.pt',
    'jfeyo@fe.up.pt',
    'deqbdir@fe.up.pt',
]);</pre>
    <p class="mt-2 mb-0 text-muted" style="font-size:.78rem">
      <i class="fas fa-info-circle me-1"></i>
      Em localhost o modo teste está <strong>sempre activo</strong> independentemente de <code>MAIL_TEST_MODE</code>.
    </p>
  </div>
</div>

<?php /* ════════════════════════════════════════
         TAB: EQUIPAMENTOS
         ════════════════════════════════════════ */ ?>
<?php elseif ($_activeTab === 'equip'): ?>

<?php
// Carregar contagens para o resumo
$_pdo2 = Database::connect();
$_labRespCount = (int)$_pdo2->query("SELECT COUNT(*) FROM infodeqb_lab_responsibles")->fetchColumn();
$_eqAccessCount = (int)$_pdo2->query("SELECT COUNT(*) FROM infodeqb_equipmentdeq_access")->fetchColumn();
Database::disconnect();
?>

<div class="alert alert-secondary mb-4" style="display:block;font-size:.84rem">
  <i class="fas fa-info-circle me-1"></i>
  As permissões de edição de equipamentos são geridas numa página dedicada. Aqui pode ver o resumo e aceder à gestão.
</div>

<div class="row" style="max-width:700px">
  <div class="col-sm-6 mb-3">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex align-items-start" style="gap:12px">
        <div style="font-size:1.6rem;color:#0891b2;line-height:1">
          <i class="fas fa-flask"></i>
        </div>
        <div>
          <div class="fw-semibold" style="font-size:.88rem">Por laboratório</div>
          <div class="text-muted" style="font-size:.8rem">Responsáveis com acesso a todos os equipamentos de um lab</div>
          <div class="mt-1">
            <span class="badge bg-primary"><?= $_labRespCount ?> associações</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 mb-3">
    <div class="card shadow-sm h-100">
      <div class="card-body d-flex align-items-start" style="gap:12px">
        <div style="font-size:1.6rem;color:#7c3aed;line-height:1">
          <i class="fas fa-microscope"></i>
        </div>
        <div>
          <div class="fw-semibold" style="font-size:.88rem">Por equipamento</div>
          <div class="text-muted" style="font-size:.8rem">Excepções: acesso a equipamentos específicos fora do lab</div>
          <div class="mt-1">
            <span class="badge bg-secondary"><?= $_eqAccessCount ?> acessos</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="d-flex gap-2 mt-1">
  <a href="<?= HTTP_DIR ?>/infodeqb/equipments/admin-permissions.php?tab=lab"
     class="btn btn-primary btn-sm">
    <i class="fas fa-key me-1"></i>Gerir permissões por laboratório
  </a>
  <a href="<?= HTTP_DIR ?>/infodeqb/equipments/admin-permissions.php?tab=eq"
     class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-microscope me-1"></i>Gerir acessos por equipamento
  </a>
</div>

<?php endif; ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
