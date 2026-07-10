<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/supabase.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? [];
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);

header('Content-Type: application/json; charset=utf-8');

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// ── GET: histórico de um gás ──────────────────────────────────────────
if ($acao === 'historico' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $gasId  = preg_replace('/[^a-z0-9]/', '', $_GET['gas_id'] ?? '');
    $limite = max(1, min(500, (int)($_GET['limite'] ?? 50)));
    $result = supabase_request('GET', 'gas_logs', [
        'gas_id=eq.' . $gasId,
        'order=timestamp.desc',
        'limit=' . $limite,
    ]);
    if ($result['error']) { echo json_encode(['ok'=>false,'msg'=>$result['error']]); exit; }
    echo json_encode(['ok' => true, 'rows' => $result['data'] ?? []]);
    exit;
}

// ── GET: dados para gráfico consolidado (todos os gases, período) ─────
if ($acao === 'chart_data' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $de  = preg_replace('/[^0-9\-]/', '', $_GET['de']  ?? date('Y-m-d', strtotime('-30 days')));
    $ate = preg_replace('/[^0-9\-]/', '', $_GET['ate'] ?? date('Y-m-d'));
    $result = supabase_request('GET', 'gas_logs', [
        'timestamp=gte.' . $de  . 'T00:00:00',
        'timestamp=lte.' . $ate . 'T23:59:59',
        'order=timestamp.asc',
        'limit=2000',
    ]);
    if ($result['error']) { echo json_encode(['ok'=>false,'msg'=>$result['error']]); exit; }
    echo json_encode(['ok' => true, 'rows' => $result['data'] ?? []]);
    exit;
}

// ── GET: exportar CSV ────────────────────────────────────────────────
if ($acao === 'export_csv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $de  = preg_replace('/[^0-9\-]/', '', $_GET['de']  ?? date('Y-m-01'));
    $ate = preg_replace('/[^0-9\-]/', '', $_GET['ate'] ?? date('Y-m-d'));
    $result = supabase_request('GET', 'gas_logs', [
        'timestamp=gte.' . $de  . 'T00:00:00',
        'timestamp=lte.' . $ate . 'T23:59:59',
        'order=timestamp.desc',
        'limit=5000',
    ]);
    if ($result['error']) { echo json_encode(['ok'=>false,'msg'=>$result['error']]); exit; }
    $rows = $result['data'] ?? [];
    $gases = GASES_DEF;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gases_' . $de . '_' . $ate . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Gás','Símbolo','Pressão','Unidade','Nova Garrafa','Notas','Utilizador','Data/Hora'], ';');
    foreach ($rows as $r) {
        $g = $gases[$r['gas_id']] ?? ['name' => $r['gas_id'], 'symbol' => $r['gas_id'], 'unit' => 'bar'];
        fputcsv($out, [
            $g['name'], $g['symbol'],
            $r['pressure'], $g['unit'],
            $r['is_new_bottle'] ? 'Sim' : 'Não',
            $r['notes'] ?? '',
            $r['user_name'],
            date('d/m/Y H:i', strtotime($r['timestamp'])),
        ], ';');
    }
    fclose($out);
    exit;
}

// ── POST: guardar ronda ──────────────────────────────────────────────
if ($acao === 'guardar_ronda' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $leituras = $_POST['leituras'] ?? [];
    if (empty($leituras)) { echo json_encode(['ok'=>false,'msg'=>'Sem leituras.']); exit; }

    $userName = $_SESSION['DisplayName'] ?? $_SESSION['CommonName'] ?? $_iqCurrentUser;
    $tsRaw    = $_POST['data_hora'] ?? date('Y-m-d\TH:i');
    // Normalizar para ISO 8601 com segundos
    $ts = date('Y-m-d\TH:i:s', strtotime($tsRaw)) . '+00:00';

    $errors = [];
    foreach (array_values($leituras) as $idx => $l) {
        $gasId   = preg_replace('/[^a-z0-9]/', '', $l['gas_id'] ?? '');
        $pressao = isset($l['pressao']) && $l['pressao'] !== '' ? (float)str_replace(',', '.', $l['pressao']) : null;
        if (!$gasId || $pressao === null) continue;

        $row = [
            'id'           => supabase_new_id($gasId, $idx),
            'gas_id'       => $gasId,
            'pressure'     => $pressao,
            'is_new_bottle'=> isset($l['nova_garrafa']) && $l['nova_garrafa'] ? true : false,
            'notes'        => trim($l['notas'] ?? ''),
            'user_name'    => $userName,
            'timestamp'    => $ts,
        ];
        $res = supabase_request('POST', 'gas_logs', [], $row);
        if ($res['error']) $errors[] = $gasId . ': ' . $res['error'];
    }

    if (!empty($errors)) {
        echo json_encode(['ok' => false, 'msg' => implode('; ', $errors)]);
    } else {
        echo json_encode(['ok' => true, 'msg' => 'Ronda guardada com sucesso.']);
    }
    exit;
}

// ── POST: editar leitura (apenas admins) ─────────────────────────────
if ($acao === 'editar_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isGasAdmin) { echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']); exit; }
    $id      = $_POST['id'] ?? '';
    $tsRaw   = $_POST['timestamp'] ?? '';
    $ts      = $tsRaw ? date('Y-m-d\TH:i:s', strtotime($tsRaw)) . '+00:00' : null;
    $body = [
        'pressure'      => (float)str_replace(',', '.', $_POST['pressure'] ?? 0),
        'is_new_bottle' => isset($_POST['is_new_bottle']) && $_POST['is_new_bottle'] ? true : false,
        'notes'         => trim($_POST['notes'] ?? ''),
    ];
    if ($ts) $body['timestamp'] = $ts;
    $res = supabase_request('PATCH', 'gas_logs', ['id=eq.' . urlencode($id)], $body);
    echo json_encode($res['error'] ? ['ok'=>false,'msg'=>$res['error']] : ['ok'=>true,'msg'=>'Leitura actualizada.']);
    exit;
}

// ── POST: apagar leitura (apenas admins) ─────────────────────────────
if ($acao === 'apagar_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isGasAdmin) { echo json_encode(['ok'=>false,'msg'=>'Sem permissão.']); exit; }
    $id = $_POST['id'] ?? '';
    $res = supabase_request('DELETE', 'gas_logs', ['id=eq.' . urlencode($id)]);
    echo json_encode($res['code'] < 300 ? ['ok'=>true,'msg'=>'Leitura apagada.'] : ['ok'=>false,'msg'=>$res['error']]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acção desconhecida.']);
