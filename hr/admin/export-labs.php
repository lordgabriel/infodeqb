<?php
/**
 * InfoDEQB / HR Admin — Exportar registos com filtro de laboratório
 * POST: tab (new|pendent|expire|active|inactive), labs_json (['E-101','E-102'])
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

// ── Parâmetros ────────────────────────────────────────────────────────
$tab  = $_POST['tab'] ?? 'active';
$validTabs = array('new','pendent','expire','active','inactive');
if (!in_array($tab, $validTabs)) $tab = 'active';

$labs = json_decode($_POST['labs_json'] ?? '[]', true);
if (!is_array($labs)) $labs = array();
$labs = array_values(array_unique(array_filter(array_map('trim', $labs))));

// ── Status e condições extra por separador ────────────────────────────
$statusMap = array(
    'new'      => 'Novo',
    'pendent'  => 'Pendente',
    'expire'   => 'Ativo',
    'active'   => 'Ativo',
    'inactive' => 'Inativo',
);
$status = $statusMap[$tab];

$extraWhere  = '';
$extraParams = array();
if ($tab === 'expire') {
    $extraWhere    = 'AND r.datafim <= ?';
    $extraParams[] = date('Y-m-d', strtotime('+1 month'));
}

// ── JOIN de labs (se houver filtro) ───────────────────────────────────
$labJoin  = '';
$labWhere = '';
$labParams = array();
if (!empty($labs)) {
    $phs      = implode(',', array_fill(0, count($labs), '?'));
    $labJoin  = 'JOIN infodeqb_rds_registo_acessos la ON la.registo_id = r.autoid';
    $labWhere = "AND la.lab_id IN ($phs)";
    $labParams = $labs;
}

// ── Query ─────────────────────────────────────────────────────────────
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
    SELECT DISTINCT
           c.codigo, c.nome, c.email, c.telefone,
           g.grupo_pro,
           r.datainicio, r.datafim, r.status,
           r.acessodeq, r.acessos AS labs_nomes,
           r.unidade, r.local_trabalho,
           r.createdate, r.dataativo, r.datainativo, r.datacica
    FROM infodeqb_rds_colaborador c
    JOIN infodeqb_rds_registo r    ON r.codigo  = c.codigo
    JOIN infodeqb_rds_grupo g      ON g.grupoid = r.grupo
    $labJoin
    WHERE c.deleted != 1
      AND r.deleted != 1
      AND r.status = ?
      $extraWhere
      $labWhere
    ORDER BY c.nome ASC
";

$params = array_merge(array($status), $extraParams, $labParams);
$sth    = $pdo->prepare($sql);
$sth->execute($params);
$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

// ── Colunas especiais por separador ──────────────────────────────────
$tabLabels = array(
    'new'      => 'Novos',
    'pendent'  => 'Pendentes',
    'expire'   => 'A_expirar',
    'active'   => 'Ativos',
    'inactive' => 'Inativos',
);

$exportData = array();
foreach ($rows as $row) {
    $rec = array(
        'Código'              => $row['codigo'],
        'Nome'                => $row['nome'],
        'Email'               => $row['email'],
        'Telefone'            => $row['telefone'],
        'Grupo Profissional'  => $row['grupo_pro'],
        'Início'              => $row['datainicio'],
        'Fim'                 => $row['datafim'],
        'Estado'              => $row['status'],
        'Unidade I&D'         => $row['unidade'],
        'Posto de trabalho'   => $row['local_trabalho'],
        'Acesso DEQ'          => $row['acessodeq'] ? 'Sim' : 'Não',
        'Laboratórios'        => $row['labs_nomes'],
    );
    // Colunas extra por separador
    if ($tab === 'new') {
        $rec['Data do registo'] = $row['createdate'] ? date('Y-m-d', (int)$row['createdate']) : '';
    } elseif ($tab === 'pendent') {
        $rec['Pedido ao CICA'] = $row['datacica'] ?? '';
    } elseif ($tab === 'active') {
        $rec['Ativo desde'] = $row['dataativo'] ? formatDate('Y-m-d', $row['dataativo']) : '';
    } elseif ($tab === 'inactive') {
        $rec['Inativo desde'] = $row['datainativo'] ? formatDate('Y-m-d', $row['datainativo']) : '';
    }
    $exportData[] = $rec;
}

// ── Output ────────────────────────────────────────────────────────────
$labSuffix = !empty($labs) ? '_filtro_' . count($labs) . 'labs' : '';
$filename  = 'HR_' . ($tabLabels[$tab] ?? $tab) . $labSuffix . '_' . date('Ymd') . '.xls';

header('Content-Type: application/vnd.ms-excel;');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');
@ob_end_clean();
ExportFile($exportData);
