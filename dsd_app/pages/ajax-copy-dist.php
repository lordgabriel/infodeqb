<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'POST required']); exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$srcOcor   = (int)($payload['src_ocor'] ?? 0);
$destOcors = array_filter(array_map('intval', $payload['dest_ocors'] ?? []));
$lines     = $payload['lines'] ?? [];
$anoId     = (int)($payload['ano_letivo_id'] ?? 0);

if (!$srcOcor || !$destOcors || !$lines || !$anoId) {
    echo json_encode(['ok'=>false,'error'=>'Dados incompletos']); exit;
}

$db = getDB();
$copied = 0;

try {
    $db->beginTransaction();

    foreach ($destOcors as $destId) {
        // Delete ALL existing distribution for this ocorrencia regardless of ano
        $db->prepare("DELETE FROM infodeqb_dsd_distribuicao WHERE ocorrencia_id=?")
           ->execute([$destId]);

        // Copy n_turmas and horas from source ocorrencia to dest if dest has none defined
        $srcOcData = $db->prepare("SELECT * FROM infodeqb_dsd_uc_ocorrencia WHERE id=?");
        $srcOcData->execute([$srcOcor]);
        $srcOc = $srcOcData->fetch();

        if ($srcOc) {
            $db->prepare("
                UPDATE infodeqb_dsd_uc_ocorrencia SET
                  n_turmas_T=?, horas_T=?, n_turmas_TP=?, horas_TP=?,
                  n_turmas_L=?, horas_L=?, n_turmas_Sem=?, horas_Sem=?,
                  n_turmas_OT=?, horas_OT=?, semanas=?, estudantes=?
                WHERE id=?
            ")->execute([
                $srcOc['n_turmas_T'],  $srcOc['horas_T'],
                $srcOc['n_turmas_TP'], $srcOc['horas_TP'],
                $srcOc['n_turmas_L'],  $srcOc['horas_L'],
                $srcOc['n_turmas_Sem'],$srcOc['horas_Sem'],
                $srcOc['n_turmas_OT'], $srcOc['horas_OT'],
                $srcOc['semanas'],     $srcOc['estudantes'],
                $destId
            ]);
        }

        $semanas = $srcOc ? (float)$srcOc['semanas'] : 13;

        // Insert each line with dsd=0
        $ins = $db->prepare("
            INSERT INTO infodeqb_dsd_distribuicao
              (ano_letivo_id, ocorrencia_id, docente_id, dsd_por_docente, regente,
               semanas, turmas_T, horas_T, turmas_TP, horas_TP,
               turmas_L, horas_L, turmas_Sem, horas_Sem, turmas_OT, horas_OT, h_tese)
            VALUES (?,?,?,0,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        foreach ($lines as $l) {
            $ins->execute([
                $anoId,
                $destId,
                (int)($l['docente_id'] ?? 0),
                (int)($l['reg'] ?? 0),
                $semanas,
                num($l['tT']  ?? 0), num($l['hT']  ?? 0),
                num($l['tTP'] ?? 0), num($l['hTP'] ?? 0),
                num($l['tL']  ?? 0), num($l['hL']  ?? 0),
                num($l['tSem']?? 0), num($l['hSem']?? 0),
                num($l['tOT'] ?? 0), num($l['hOT'] ?? 0),
                num($l['htese'] ?? 0),
            ]);
        }
        $copied++;
    }

    $db->commit();
    echo json_encode(['ok'=>true, 'copied'=>$copied]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
