<?php
session_start();

// ── Detecção e carregamento de língua ─────────────────────────────
// Prioridade: ?lang= GET > $_SESSION['lang'] > Accept-Language > pt
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt','en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$_iqLang = $_SESSION['lang']
    ?? (substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'pt', 0, 2) === 'en' ? 'en' : 'pt');

$_iqLangFile = $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/lang/lang.' . $_iqLang . '.php';
if (!file_exists($_iqLangFile)) {
    $_iqLangFile = $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/lang/lang.pt.php';
    $_iqLang = 'pt';
}
include $_iqLangFile;
$GLOBALS['_lang'] = $lang ?? [];

/**
 * Tradução com substituição de parâmetros.
 * t('SAVE')           → "Guardar" / "Save"
 * t('N_RECORDS', 3)   → "3 registos" / "3 records"
 * t('HELLO', 'Luís')  → "Olá, Luís!" / "Hello, Luís!"
 */
function t(string $key, ...$params): string {
    $str = $GLOBALS['_lang'][$key] ?? $key;
    if (!empty($params)) {
        $str = vsprintf($str, $params);
    }
    return $str;
}

unset($_iqLangFile);

// ============================================================
// 🏠 DESENVOLVIMENTO LOCAL (localhost)
// ============================================================
if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
    if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
        return;
    }
    $redirect = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . HTTP_DIR . '/infodeqb/local_login.php?redirect=' . urlencode($redirect));
    exit;
}

// ============================================================
// 🔐 AUTENTICAÇÃO FEDERADA NORMAL (Shibboleth)
// ============================================================
if (!isset($_SERVER['eppn'])) {
    header('Location: https://deq.fe.up.pt/Shibboleth.sso/Login?target=https://deq.fe.up.pt/infodeqb/');
    exit;
}

$_SESSION['user']        = $_SERVER['eppn'];
$_SESSION['DisplayName'] = $_SERVER['DisplayName'];
$_SESSION['CommonName']  = $_SERVER['CommonName'];
$_upCode                 = 'up' . substr($_SERVER['eppn'], 2, strpos($_SERVER['eppn'], '@') - 2);
$_SESSION['Code']        = $_upCode . '@up.pt';
unset($_upCode);
