<?php
/**
 * Cabeçalho comum a todas as páginas da infodeq.
 *
 * Pré-requisitos (já incluídos pela página chamante):
 *   require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
 *   include ROOT_DIR.'/infodeqb/session.php';
 *
 * Variável opcional:
 *   $pageTitle  — título da página (default: 'InfoDEQB')
 */
if (!isset($pageTitle)) {
    $pageTitle = 'InfoDEQB';
}
$_base = HTTP_DIR . '/infodeqb';

// Garantir que t() está disponível mesmo que session.php não tenha sido incluído
if (!function_exists('t')) {
    if (!isset($lang) || !is_array($lang)) {
        $_hLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'pt', 0, 2) === 'en' ? 'en' : 'pt';
        @include __DIR__ . '/../lang/lang.' . $_hLang . '.php';
        $GLOBALS['_lang'] = $lang ?? [];
        unset($_hLang);
    } else {
        $GLOBALS['_lang'] = $lang;
    }
    function t(string $key, ...$params): string {
        $str = $GLOBALS['_lang'][$key] ?? $key;
        if (!empty($params)) $str = vsprintf($str, $params);
        return $str;
    }
}

// Flags de papel para o menu — não sobrescreve $isAdmin já definido pela página
if (!isset($isAdmin)) {
    require_once ROOT_DIR . '/infodeqb/inc/admins.php';
}
// Admin de cada módulo (para visibilidade das entradas Admin no menu)
$_iqNavHrAdmin    = $isAdmin || in_array($_iqCurrentUser ?? '', [
    'up247821@up.pt','up448105@up.pt','up444525@up.pt',
    'up239595@up.pt','up232433@up.pt','up701282@up.pt',
]);
$_iqNavWaterAdmin = $isAdmin || in_array($_iqCurrentUser ?? '', ['up248679@up.pt']);
$_iqNavExamAdmin  = $isAdmin; // ajustar quando os admins de exames forem definidos

// Iniciais do utilizador para o avatar
$_displayName = $_SESSION['DisplayName'] ?? $_SESSION['CommonName'] ?? '';
$_nameParts   = preg_split('/\s+/', trim($_displayName));
$_initials    = '';
if (count($_nameParts) >= 2) {
    $_initials = mb_strtoupper(mb_substr($_nameParts[0], 0, 1) . mb_substr(end($_nameParts), 0, 1));
} elseif (count($_nameParts) === 1 && $_nameParts[0] !== '') {
    $_initials = mb_strtoupper(mb_substr($_nameParts[0], 0, 2));
} else {
    $_initials = '?';
}

// URL de logout
$_logoutUrl = (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']))
    ? $_base . '/local_logout.php'
    : 'https://deq.fe.up.pt/Shibboleth.sso/Logout?return=https://deq.fe.up.pt/infodeqb/';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $_base; ?>/img/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32"  href="<?php echo $_base; ?>/img/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16"  href="<?php echo $_base; ?>/img/favicon/favicon-16x16.png">
  <link rel="manifest" href="<?php echo $_base; ?>/img/favicon/site.webmanifest">

  <title><?php echo htmlspecialchars($pageTitle); ?> — InfoDEQB</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap (grid + base utilities; visuais sobrescritos pelo infodeq.css) -->
  <link href="<?php echo $_base; ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/all.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/v4-shims.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/v/bs4/jszip-2.5.0/dt-1.10.18/af-2.3.3/b-1.5.6/b-colvis-1.5.6/b-flash-1.5.6/b-html5-1.5.6/b-print-1.5.6/cr-1.5.0/fc-3.2.5/fh-3.1.4/kt-2.5.0/r-2.2.2/rg-1.1.0/rr-1.2.4/sc-2.0.0/sl-1.3.0/datatables.min.css">
  <!-- InfoDEQB design system -->
  <link href="<?php echo $_base; ?>/css/infodeq.css" rel="stylesheet">

  <!-- jQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.js"></script>

  <?php if (!empty($extraCss)): ?>
  <!-- CSS extra (sub-apps) -->
  <link rel="stylesheet" href="<?php echo htmlspecialchars($extraCss); ?>">
  <?php endif; ?>
</head>
<body>

<!-- ══════════════════════════════════════════
     NAVBAR HORIZONTAL
     ══════════════════════════════════════════ -->
<nav class="iq-topnav">

  <!-- Brand -->
  <a class="iq-tn-brand" href="<?php echo $_base; ?>/">
    <img src="<?php echo $_base; ?>/img/logo_deq_black.png" alt="DEQB">
    <div>
      <div class="iq-tn-brand-title">InfoDEQB</div>
      <div class="iq-tn-brand-sub"><?php echo t('DASH_DEPT'); ?></div>
    </div>
  </a>

  <!-- Navigation links -->
  <ul class="iq-tn-links">

    <li>
      <a href="<?php echo $_base; ?>/">
        <i class="fas fa-home fa-sm"></i> <?php echo t('NAV_HOME'); ?>
      </a>
    </li>

    <!-- Staff -->
    <li class="iq-tn-has-menu">
      <a href="#"><?php echo t('NAV_STAFF'); ?> <i class="fas fa-chevron-down fa-xs" style="opacity:.6;margin-left:2px"></i></a>
      <ul class="iq-tn-menu">
        <li><a href="<?php echo $_base; ?>/hr/meu-registo.php"><?php echo t('NAV_MY_RECORD'); ?></a></li>
        <li><a href="<?php echo $_base; ?>/hr/list.php"><?php echo t('NAV_STAFF_LIST'); ?></a></li>
        <?php if ($_iqNavHrAdmin): ?>
        <li class="iq-tn-sep"></li>
        <span class="iq-tn-grp"><?php echo t('NAV_ADMIN'); ?></span>
        <li><a href="<?php echo $_base; ?>/hr/admin/index.php"><?php echo t('NAV_STAFF_ADMIN'); ?></a></li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- Resources -->
    <li class="iq-tn-has-menu">
      <a href="#"><?php echo t('NAV_RESOURCES'); ?> <i class="fas fa-chevron-down fa-xs" style="opacity:.6;margin-left:2px"></i></a>
      <ul class="iq-tn-menu">
        <li><a href="<?php echo $_base; ?>/equipments/"><?php echo t('NAV_EQUIPMENT'); ?></a></li>
        <li><a href="<?php echo $_base; ?>/reagentes/"><?php echo t('NAV_REAGENTS'); ?></a></li>
        <li><a href="<?php echo $_base; ?>/booking" target="_blank"><?php echo t('NAV_BOOKING'); ?> <i class="fa fa-external-link fa-xs ml-1"></i></a></li>
        <li><a href="<?php echo $_base; ?>/water/waterqc.php"><?php echo t('NAV_WATER_QUALITY'); ?></a></li>
        <?php if ($_iqNavWaterAdmin): ?>
        <li class="iq-tn-sep"></li>
        <span class="iq-tn-grp"><?php echo t('NAV_ADMIN'); ?></span>
        <li><a href="<?php echo $_base; ?>/water/index.php"><?php echo t('NAV_WATER_ADMIN'); ?></a></li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- Teaching -->
    <li class="iq-tn-has-menu">
      <a href="#"><?php echo t('NAV_TEACHING'); ?> <i class="fas fa-chevron-down fa-xs" style="opacity:.6;margin-left:2px"></i></a>
      <ul class="iq-tn-menu">
        <li><a href="<?php echo $_base; ?>/exams"><?php echo t('NAV_EXAMS'); ?></a></li>
        <li><a href="<?php echo $_base; ?>/mobile/"><?php echo t('NAV_MOBILITY'); ?></a></li>
      </ul>
    </li>

    <!-- Department -->
    <li class="iq-tn-has-menu">
      <a href="#"><?php echo t('NAV_DEPT'); ?> <i class="fas fa-chevron-down fa-xs" style="opacity:.6;margin-left:2px"></i></a>
      <ul class="iq-tn-menu">
        <li><a href="<?php echo $_base; ?>/areas/"><?php echo t('NAV_AREAS'); ?></a></li>
        <li><a href="<?php echo $_base; ?>/servdoc/"><?php echo t('NAV_SERVDOC'); ?></a></li>
        <li><a href="<?php echo $_base; ?>/adi/"><?php echo t('NAV_SPACES'); ?></a></li>
        <?php if ($isAdmin): ?>
        <li class="iq-tn-sep"></li>
        <span class="iq-tn-grp"><?php echo t('NAV_ADMIN'); ?></span>
        <li><a href="<?php echo $_base; ?>/dsd_app/"><?php echo t('NAV_DSD'); ?></a></li>
        <?php endif; ?>
      </ul>
    </li>

  </ul>

  <!-- User -->
  <div class="iq-tn-user" id="iqTnUser">
    <button class="iq-tn-user-btn" onclick="iqToggleTnUser()" type="button">
      <span class="iq-tn-avatar"><?php echo htmlspecialchars($_initials); ?></span>
      <span class="iq-tn-user-name"><?php echo htmlspecialchars($_displayName ?: t('DASH_WELCOME')); ?></span>
      <i class="fas fa-chevron-down fa-xs" style="opacity:.55;margin-left:2px"></i>
    </button>
    <div class="iq-tn-user-dropdown">
      <div class="iq-tn-user-head">
        <div class="name"><?php echo htmlspecialchars($_displayName); ?></div>
        <div class="email"><?php echo htmlspecialchars($_SESSION['user'] ?? ''); ?></div>
      </div>
      <a class="iq-tn-user-item" href="<?php echo htmlspecialchars($_logoutUrl); ?>">
        <i class="fas fa-sign-out-alt"></i> <?php echo t('NAV_LOGOUT'); ?>
      </a>
    </div>
  </div>

</nav>

<?php if (!empty($subNav)) echo $subNav; ?>

<!-- ══════════════════════════════════════════
     CONTEÚDO PRINCIPAL
     ══════════════════════════════════════════ -->
<main class="iq-main">

<script>
function iqToggleTnUser() {
  document.getElementById('iqTnUser').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  var u = document.getElementById('iqTnUser');
  if (u && !u.contains(e.target)) u.classList.remove('open');
});
</script>
