<?php
/**
 * Exportação de dados de qualidade da água para CSV/Excel
 * GET: de (YYYY-MM-DD), ate (YYYY-MM-DD)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';

if (empty($_SESSION['user'])) {
    http_response_code(403); exit('Acesso negado.');
}

$de  = preg_replace('/[^0-9\-]/', '', $_GET['de']  ?? '');
$ate = preg_replace('/[^0-9\-]/', '', $_GET['ate'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  $de  = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) $ate = date('Y-m-d');
if ($de > $ate) { $t = $de; $de = $ate; $ate = $t; }

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── pH e Condutividade ────────────────────────────────────────────
$stmtPC = $pdo->prepare(
    'SELECT DATE_FORMAT(dia, "%Y-%m-%d") AS data, water_type, condutivity, pH
     FROM infodeqb_waterqc_ph_cond
     WHERE dia >= ? AND dia <= ?
     ORDER BY dia ASC, water_type ASC'
);
$stmtPC->execute([$de, $ate]);

// ── TOC / TC / IC ────────────────────────────────────────────────
$stmtTC = $pdo->prepare(
    'SELECT DATE_FORMAT(dia, "%Y-%m-%d") AS data, water_type, TOC, TC, IC
     FROM infodeqb_waterqc_toc
     WHERE dia >= ? AND dia <= ?
     ORDER BY dia ASC, water_type ASC'
);
$stmtTC->execute([$de, $ate]);

Database::disconnect();

// ── Agregar por data ──────────────────────────────────────────────
$rows = [];

foreach ($stmtPC->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = $r['data'];
    if (!isset($rows[$d])) $rows[$d] = [];
    if ($r['water_type'] == 2) {
        $rows[$d]['cond_dest'] = $r['condutivity'];
        $rows[$d]['ph_dest']   = $r['pH'];
    } else {
        $rows[$d]['cond_pur']  = $r['condutivity'];
        $rows[$d]['ph_pur']    = $r['pH'];
    }
}

foreach ($stmtTC->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = $r['data'];
    if (!isset($rows[$d])) $rows[$d] = [];
    if ($r['water_type'] == 2) {
        $rows[$d]['toc_dest'] = $r['TOC'];
        $rows[$d]['tc_dest']  = $r['TC'];
        $rows[$d]['ic_dest']  = $r['IC'];
    } else {
        $rows[$d]['toc_pur']  = $r['TOC'];
        $rows[$d]['tc_pur']   = $r['TC'];
        $rows[$d]['ic_pur']   = $r['IC'];
    }
}

ksort($rows);

// ── Formatar número (ponto → vírgula para Excel europeu) ──────────
function fmtNum($v) {
    if ($v === null || $v === '') return '';
    return str_replace('.', ',', rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.'));
}

// ── Output CSV ────────────────────────────────────────────────────
$filename = 'qualidade_agua_' . $de . '_' . $ate . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

echo "\xEF\xBB\xBF"; // UTF-8 BOM — necessário para Excel abrir com acentos

$out = fopen('php://output', 'w');

// Cabeçalho com info do período
fputcsv($out, ['Qualidade da Água — ' . $de . ' a ' . $ate], ';');
fputcsv($out, [], ';');

// Cabeçalho da tabela
fputcsv($out, [
    'Data',
    'Cond. Destilada (µS/cm)',
    'pH Destilada',
    'Cond. Purificada (µS/cm)',
    'pH Purificada',
    'TOC Destilada',
    'TC Destilada',
    'IC Destilada',
    'TOC Purificada',
    'TC Purificada',
    'IC Purificada',
], ';');

foreach ($rows as $date => $r) {
    fputcsv($out, [
        $date,
        fmtNum($r['cond_dest'] ?? ''),
        fmtNum($r['ph_dest']   ?? ''),
        fmtNum($r['cond_pur']  ?? ''),
        fmtNum($r['ph_pur']    ?? ''),
        fmtNum($r['toc_dest']  ?? ''),
        fmtNum($r['tc_dest']   ?? ''),
        fmtNum($r['ic_dest']   ?? ''),
        fmtNum($r['toc_pur']   ?? ''),
        fmtNum($r['tc_pur']    ?? ''),
        fmtNum($r['ic_pur']    ?? ''),
    ], ';');
}

fclose($out);
