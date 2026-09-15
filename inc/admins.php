<?php
/**
 * Controlo de acesso centralizado — InfoDEQB
 *
 * Variáveis expostas após inclusão:
 *   $isAdmin            — true se admin global
 *   $_iqCurrentUser     — código normalizado up356946@up.pt
 *   $_iqAdminsHr        — admins locais HR
 *   $_iqAdminsWater     — admins locais Água
 *   $_iqAdminsExam      — admins locais Exames
 *   $_iqAdminsMobile    — admins locais Mobilidade
 *
 * Gestão via UI: /admin-sections.php (apenas admin global)
 */

$_iqCurrentUser = $_SESSION['Code'] ?? '';

// ── Admins globais (hardcoded — nunca geridos pela UI) ────────────
$isAdmin = in_array($_iqCurrentUser, [
    'up356946@up.pt',   // Luís Martins
    
]);

// ── Admins de secção — carregar da BD (cache em sessão 5 min) ─────
$_cacheKey = '_iq_section_admins';
$_cacheTs  = '_iq_section_admins_ts';

if (!isset($_SESSION[$_cacheKey]) || (time() - ($_SESSION[$_cacheTs] ?? 0)) > 300) {
    try {
        $_admPdo = Database::connect();
        $_admRows = $_admPdo->query(
            'SELECT module, user_code FROM infodeqb_section_admins ORDER BY module, user_code'
        )->fetchAll(PDO::FETCH_ASSOC);
        $_admByModule = [];
        foreach ($_admRows as $_r) $_admByModule[$_r['module']][] = $_r['user_code'];
        $_SESSION[$_cacheKey] = $_admByModule;
        $_SESSION[$_cacheTs]  = time();
    } catch (Exception $_e) {
        $_SESSION[$_cacheKey] = [];
    }
}

$_iqAdminsHr     = $_SESSION[$_cacheKey]['hr']      ?? [];
$_iqAdminsHrList = $_SESSION[$_cacheKey]['hr_list'] ?? [];
$_iqAdminsWater  = $_SESSION[$_cacheKey]['water']  ?? [];
$_iqAdminsExam   = $_SESSION[$_cacheKey]['exam']   ?? [];
$_iqAdminsMobile  = $_SESSION[$_cacheKey]['mobile']  ?? [];
$_iqAdminsDsd     = $_SESSION[$_cacheKey]['dsd']     ?? [];
$_iqAdminsGases   = $_SESSION[$_cacheKey]['gases']   ?? [];
$_iqAdminsErasmus = $_SESSION[$_cacheKey]['erasmus'] ?? [];

unset($_cacheKey, $_cacheTs, $_admRows, $_admByModule, $_r, $_e);
