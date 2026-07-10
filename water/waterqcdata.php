<?php
/**
 * AJAX endpoint — dados de qualidade da água
 * Parâmetros GET:
 *   de  (YYYY-MM-DD) — início do período
 *   ate (YYYY-MM-DD) — fim do período
 *   (compat legado: ano + mes)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';

if (empty($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

// ── Determinar intervalo de datas ─────────────────────────────────
if (isset($_GET['de'], $_GET['ate'])) {
    // Novo modo: intervalo livre
    $de  = preg_replace('/[^0-9\-]/', '', $_GET['de']  ?? '');
    $ate = preg_replace('/[^0-9\-]/', '', $_GET['ate'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  $de  = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) $ate = date('Y-m-d');

    $pdo = Database::connect();

    $stmtPC = $pdo->prepare(
        'SELECT DATE_FORMAT(dia,"%Y-%m-%d") AS dia, water_type AS tipo,
                condutivity, pH
         FROM infodeqb_waterqc_ph_cond
         WHERE dia >= ? AND dia <= ? ORDER BY dia ASC'
    );
    $stmtPC->execute([$de, $ate]);

    $stmtTC = $pdo->prepare(
        'SELECT DATE_FORMAT(dia,"%Y-%m-%d") AS dia, water_type AS tipo,
                TOC
         FROM infodeqb_waterqc_toc
         WHERE dia >= ? AND dia <= ? ORDER BY dia ASC'
    );
    $stmtTC->execute([$de, $ate]);

} else {
    // Modo legado: ano + mes
    $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
    $mes = (isset($_GET['mes']) && $_GET['mes'] !== '%') ? (int)$_GET['mes'] : '%';

    $pdo = Database::connect();

    if ($mes === '%') {
        $stmtPC = $pdo->prepare(
            'SELECT DATE_FORMAT(dia,"%Y-%m-%d") AS dia, water_type AS tipo,
                    condutivity, pH
             FROM infodeqb_waterqc_ph_cond WHERE YEAR(dia)=? ORDER BY dia ASC'
        );
        $stmtPC->execute([$ano]);
        $stmtTC = $pdo->prepare(
            'SELECT DATE_FORMAT(dia,"%Y-%m-%d") AS dia, water_type AS tipo,
                    TOC
             FROM infodeqb_waterqc_toc WHERE YEAR(dia)=? ORDER BY dia ASC'
        );
        $stmtTC->execute([$ano]);
    } else {
        $stmtPC = $pdo->prepare(
            'SELECT DATE_FORMAT(dia,"%Y-%m-%d") AS dia, water_type AS tipo,
                    condutivity, pH
             FROM infodeqb_waterqc_ph_cond
             WHERE YEAR(dia)=? AND MONTH(dia)=? ORDER BY dia ASC'
        );
        $stmtPC->execute([$ano, $mes]);
        $stmtTC = $pdo->prepare(
            'SELECT DATE_FORMAT(dia,"%Y-%m-%d") AS dia, water_type AS tipo,
                    TOC
             FROM infodeqb_waterqc_toc
             WHERE YEAR(dia)=? AND MONTH(dia)=? ORDER BY dia ASC'
        );
        $stmtTC->execute([$ano, $mes]);
    }
}

$phcond = $stmtPC->fetchAll(PDO::FETCH_ASSOC);
$toc    = $stmtTC->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['phcond' => $phcond, 'toc' => $toc]);
