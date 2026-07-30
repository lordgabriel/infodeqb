<?php
/**
 * DSD sub-app header — integração com InfoDEQB.
 *
 * Pré-requisito (executado pela página chamante antes deste include):
 *   session_start();
 *   require_once __DIR__ . '/../includes/config.php';
 *   $pageTitle  = '...';
 *   $activePage = '...';   // home | distribuicao | ocorrencias | rel-docente | rel-ciclo | admin
 */

// ── Constantes InfoDEQB ──────────────────────────────────────────
if (!defined('HTTP_DIR')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
}

// ── Autenticação ─────────────────────────────────────────────────
if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        $redirect = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . HTTP_DIR . '/infodeqb/local_login.php?redirect=' . urlencode($redirect));
        exit;
    }
} else {
    if (!isset($_SERVER['eppn'])) {
        header('Location: https://deq.fe.up.pt/Shibboleth.sso/Login?target=https://deq.fe.up.pt/infodeqb/dsd_app/');
        exit;
    }
    $_SESSION['user']        = $_SERVER['eppn'];
    $_SESSION['DisplayName'] = isset($_SERVER['DisplayName']) ? $_SERVER['DisplayName'] : '';
    $_SESSION['CommonName']  = isset($_SERVER['CommonName'])  ? $_SERVER['CommonName']  : '';
    // Normalizar Code para formato up356946@up.pt (igual ao session.php principal)
    if (empty($_SESSION['Code']) || strpos($_SESSION['Code'], '@') === false) {
        $_upNum = substr($_SERVER['eppn'], 2, strpos($_SERVER['eppn'], '@') - 2);
        $_SESSION['Code'] = 'up' . $_upNum . '@up.pt';
        unset($_upNum);
    }
}

// ── Controlo de acesso: apenas admin global e admin DSD ──────────
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
$_dsdAccess = $isAdmin || !empty($_iqAdminsDsd) && in_array($_iqCurrentUser, $_iqAdminsDsd);
if (!$_dsdAccess) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}
unset($_dsdAccess);

// ── CSS extra: app.css do dsd_app ────────────────────────────────
$extraCss = BASE_URL . '/assets/css/app.css?v=' . filemtime(dirname(__DIR__) . '/assets/css/app.css');

// ── Ano letivo para o selector ───────────────────────────────────
$_dsdAnosNav = getDB()->query('SELECT * FROM infodeqb_dsd_ano_letivo ORDER BY id DESC')->fetchAll();
$_dsdAlAtivo = getAnoLetivoAtivo();
$_ap         = $activePage ?? '';

// ── Sub-nav (emitido pelo header da infodeq entre </nav> e <main>) ──
ob_start();
?>
<nav class="dsd-subnav">
  <div class="dsd-subnav-inner">

    <div class="dsd-subnav-brand">DSD</div>

    <ul class="dsd-subnav-links">
      <li><a href="<?= BASE_URL ?>/"
             class="<?= $_ap === 'home'        ? 'active' : '' ?>">Início</a></li>
      <li><a href="<?= BASE_URL ?>/pages/distribuicao.php"
             class="<?= $_ap === 'distribuicao' ? 'active' : '' ?>">Distribuição</a></li>
      <li><a href="<?= BASE_URL ?>/pages/ocorrencias.php"
             class="<?= $_ap === 'ocorrencias'  ? 'active' : '' ?>">Ocorrências</a></li>
      <li class="dsd-subnav-sep"></li>
      <li><a href="<?= BASE_URL ?>/reports/por-docente.php"
             class="<?= $_ap === 'rel-docente'  ? 'active' : '' ?>">Relatório por docente</a></li>
      <li><a href="<?= BASE_URL ?>/reports/por-ciclo.php"
             class="<?= $_ap === 'rel-ciclo'    ? 'active' : '' ?>">Por ciclo</a></li>
      <li class="dsd-subnav-sep"></li>
      <li><a href="<?= BASE_URL ?>/pages/admin.php"
             class="<?= $_ap === 'admin'        ? 'active' : '' ?>">Administração</a></li>
    </ul>

    <div class="dsd-subnav-year">
      <?php if (count($_dsdAnosNav) > 1): ?>
      <form method="post" action="<?= BASE_URL ?>/pages/set-ano-letivo.php" id="_dsdYearForm">
        <input type="hidden" name="redirect" value="<?= esc($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <select name="ano_id" onchange="document.getElementById('_dsdYearForm').submit()">
          <?php foreach ($_dsdAnosNav as $_an): ?>
          <option value="<?= $_an['id'] ?>" <?= $_an['id'] == $_dsdAlAtivo['id'] ? 'selected' : '' ?>>
            <?= esc($_an['designacao']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php else: ?>
      <span><?= esc($_dsdAlAtivo['designacao']) ?></span>
      <?php endif; ?>
    </div>

  </div>
</nav>
<?php
$subNav = ob_get_clean();

// ── Header InfoDEQB ───────────────────────────────────────────────
include ROOT_DIR . '/infodeqb/inc/header.php';

// ── Flash messages ────────────────────────────────────────────────
$_dsdFlash = getFlash();
if ($_dsdFlash): ?>
<div class="alert alert-<?= esc($_dsdFlash['type']) ?> mb-3"
     style="display:flex;align-items:center;justify-content:space-between;padding:.65rem 1rem;border-radius:6px;font-size:.85rem">
  <span><?= esc($_dsdFlash['msg']) ?></span>
  <button style="background:none;border:none;font-size:1.2rem;cursor:pointer;opacity:.55;line-height:1;margin-left:.5rem"
          onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<div class="dsd-page">
