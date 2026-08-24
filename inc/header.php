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

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/all.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.11.2/css/v4-shims.css">
  <!-- DataTables (tema Bootstrap 5) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <!-- InfoDEQB design system -->
  <link href="<?php echo $_base; ?>/css/infodeq.css" rel="stylesheet">

  <!-- jQuery — necessário para scripts inline e DataTables nas páginas -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <!-- Alpine.js — reactividade leve (user dropdown, etc.) -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>

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
        <li><a href="<?php echo $_base; ?>/booking" target="_blank"><?php echo t('NAV_BOOKING'); ?> <i class="fa fa-external-link fa-xs ms-1"></i></a></li>
        <li><a href="<?php echo $_base; ?>/water/waterqc.php"><?php echo t('NAV_WATER_QUALITY'); ?></a></li>
        <?php
        $_iqNavGasesAdmin = $isAdmin || in_array($_iqCurrentUser ?? '', $_SESSION['_iq_section_admins']['gases'] ?? []);
        if ($_iqNavWaterAdmin || $_iqNavGasesAdmin): ?>
        <li class="iq-tn-sep"></li>
        <span class="iq-tn-grp"><?php echo t('NAV_ADMIN'); ?></span>
        <?php if ($_iqNavWaterAdmin): ?>
        <li><a href="<?php echo $_base; ?>/water/index.php"><?php echo t('NAV_WATER_ADMIN'); ?></a></li>
        <?php endif; ?>
        <?php if ($_iqNavGasesAdmin): ?>
        <li><a href="<?php echo $_base; ?>/gases/"><?php echo t('NAV_GASES'); ?></a></li>
        <?php endif; ?>
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

  <!-- User — Bootstrap 5 dropdown nativo (sem Alpine.js, sem jQuery) -->
  <div class="iq-tn-user dropdown">
    <button class="iq-tn-user-btn dropdown-toggle" type="button"
            id="iqUserDropBtn"
            data-bs-toggle="dropdown"
            aria-expanded="false">
      <span class="iq-tn-avatar"><?php echo htmlspecialchars($_initials); ?></span>
      <span class="iq-tn-user-name"><?php echo htmlspecialchars($_displayName ?: t('DASH_WELCOME')); ?></span>
      <i class="fas fa-chevron-down fa-xs" style="opacity:.55;margin-left:2px"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end iq-tn-user-dropdown"
         aria-labelledby="iqUserDropBtn">
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
<main class="iq-main<?= isset($mainClass) ? ' ' . htmlspecialchars($mainClass) : '' ?>">

