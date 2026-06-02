<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$db = getDB();
$al = getAnoLetivoAtivo();

// ── List occurrences for copy panel ───────────────────────────
if (isset($_GET['list_ocors'])) {
    $exclude = (int)($_GET['exclude'] ?? 0);
    $stmt = $db->prepare("
        SELECT o.id, u.designacao AS nome, pe.sigla AS plano,
               (SELECT COUNT(*) FROM infodeqb_dsd_distribuicao d 
                WHERE d.ocorrencia_id=o.id AND d.ano_letivo_id=?) AS n_dist
        FROM infodeqb_dsd_uc_ocorrencia o
        JOIN infodeqb_dsd_uc u ON o.uc_id=u.id
        LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(o.plano_id, u.plano_id)=pe.id
        WHERE o.ano_letivo_id=? AND o.id != ?
        ORDER BY pe.ordem, u.designacao
    ");
    $stmt->execute([$al['id'], $al['id'], $exclude]);
    echo json_encode(['ocors' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── Full distribution lines for editing (all carreiras, no filter) ───
if (isset($_GET['edit_lines'])) {
    $ocorId = (int)($_GET['ocor_id'] ?? 0);
    if (!$ocorId) { echo json_encode(['rows' => []]); exit; }
    $stmt = $db->prepare("
        SELECT d.id, d.docente_id, doc.nome AS docente_nome,
               d.semanas, d.dsd_por_docente, d.regente,
               d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
               d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem,
               d.turmas_OT, d.horas_OT, d.h_tese
        FROM infodeqb_dsd_distribuicao d
        JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
        WHERE d.ocorrencia_id = ? AND d.ano_letivo_id = ?
        ORDER BY d.regente DESC, doc.nome
    ");
    $stmt->execute([$ocorId, $al['id']]);
    echo json_encode(['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── Distribution lines for a single occurrence ────────────────
$ocorId = (int)($_GET['ocor_id'] ?? 0);
if (!$ocorId) { echo json_encode(['rows'=>[],'need'=>0,'done'=>0]); exit; }

$oc = $db->prepare("SELECT * FROM infodeqb_dsd_uc_ocorrencia WHERE id=?");
$oc->execute([$ocorId]);
$ocData = $oc->fetch();
$need = $ocData ? horasNecessarias($ocData)['total'] : 0;

$rows = $db->prepare("
    SELECT doc.nome AS docente,
           d.semanas, d.dsd_por_docente AS dsd, d.regente AS reg,
           d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
           d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem, d.turmas_OT, d.horas_OT,
           (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
            + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas / 13 AS hs,
           CASE WHEN d.dsd_por_docente=1 THEN
             (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
              + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef
           ELSE 0 END AS h_slef
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id=o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id=doc.id
    WHERE d.ocorrencia_id=?
    ORDER BY d.regente DESC, doc.nome
");
$rows->execute([$ocorId]);
$data = $rows->fetchAll(PDO::FETCH_ASSOC);

$done = 0;
foreach ($data as &$r) {
    $done += (float)$r['h_slef'];
    $r['hs']     = round((float)$r['hs'], 3);
    $r['h_slef'] = round((float)$r['h_slef'], 3);
    $r['dsd']    = (bool)$r['dsd'];
    $r['reg']    = (bool)$r['reg'];
    $r['turmas'] = sprintf('%g/%g/%g/%g/%g',
        $r['turmas_T'], $r['turmas_TP'], $r['turmas_L'], $r['turmas_Sem'], $r['turmas_OT']);
    foreach (['turmas_T','horas_T','turmas_TP','horas_TP','turmas_L','horas_L',
              'turmas_Sem','horas_Sem','turmas_OT','horas_OT'] as $f) unset($r[$f]);
}

echo json_encode(['rows'=>$data, 'need'=>round($need,3), 'done'=>round($done,3)]);
