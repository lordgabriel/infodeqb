<?php
define('SUPABASE_URL', 'https://bkhxmkjgubcwchymryar.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJraHhta2pndWJjd2NoeW1yeWFyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjUzMTMyMjIsImV4cCI6MjA4MDg4OTIyMn0.tKuYRSs5Qq5OL6F7zZRzrj66ipTv5uBR1cPaHJX-HdI');

// Definições de gases — espelho exacto de constants.ts da app móvel
// cor_bs = classe Bootstrap equivalente à cor Tailwind da app
define('GASES_DEF', [
    'g3' => ['name' => 'Azoto',             'symbol' => 'N₂',   'cor_bs' => 'dark',      'max_pressure' => 200, 'unit' => 'bar'],
    'g1' => ['name' => 'Ar Sintético',      'symbol' => 'Air',  'cor_bs' => 'primary',   'max_pressure' => 200, 'unit' => 'bar'],
    'g2' => ['name' => 'Oxigénio',          'symbol' => 'O₂',   'cor_bs' => 'info',      'max_pressure' => 200, 'unit' => 'bar'],
    'g4' => ['name' => 'Árgon',             'symbol' => 'Ar',   'cor_bs' => 'success',   'max_pressure' => 200, 'unit' => 'bar'],
    'g5' => ['name' => 'Dióxido de Carbono','symbol' => 'CO₂',  'cor_bs' => 'secondary', 'max_pressure' => 60,  'unit' => 'bar'],
    'g6' => ['name' => 'Hélio 1',           'symbol' => 'He 1', 'cor_bs' => 'warning',   'max_pressure' => 200, 'unit' => 'bar'],
    'g7' => ['name' => 'Hélio 2',           'symbol' => 'He 2', 'cor_bs' => 'orange',    'max_pressure' => 200, 'unit' => 'bar'],
    'g8' => ['name' => 'Hidrogénio',        'symbol' => 'H₂',   'cor_bs' => 'danger',    'max_pressure' => 200, 'unit' => 'bar'],
]);

/**
 * Chamada genérica à Supabase REST API.
 * $method  : GET | POST | PATCH | DELETE
 * $table   : nome da tabela
 * $filters : array de query params (PostgREST operators: 'gas_id=eq.g1', etc.)
 * $body    : array para POST/PATCH (será codificado em JSON)
 * Retorna  : ['code' => int, 'data' => array|null, 'error' => string|null]
 */
function supabase_request($method, $table, $filters = [], $body = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    if (!empty($filters)) {
        $url .= '?' . implode('&', $filters);
    }

    $headers = [
        'apikey: '       . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['code' => 0, 'data' => null, 'error' => $curlErr];
    }

    $data = json_decode($response, true);
    return [
        'code'  => $code,
        'data'  => $data,
        'error' => ($code >= 400) ? (isset($data['message']) ? $data['message'] : $response) : null,
    ];
}

/**
 * Cores Bootstrap não-nativas que precisam de inline style.
 * Bootstrap 5.3 não gera bg-orange / btn-orange como utilitárias.
 */
define('GAS_CUSTOM_COLORS', [
    'orange' => ['bg' => '#fd7e14', 'text' => '#000'],
]);

/** inline style para badge bg-{cor_bs} quando a cor não é nativa do Bootstrap */
function gas_badge_style($cor_bs) {
    $c = GAS_CUSTOM_COLORS[$cor_bs] ?? null;
    if (!$c) return '';
    return ' style="background-color:' . $c['bg'] . ';color:' . $c['text'] . '"';
}

/** inline style para btn-{cor_bs} (estado activo) quando a cor não é nativa do Bootstrap */
function gas_btn_style($cor_bs) {
    $c = GAS_CUSTOM_COLORS[$cor_bs] ?? null;
    if (!$c) return '';
    return ' style="background-color:' . $c['bg'] . ';border-color:' . $c['bg'] . ';color:' . $c['text'] . '"';
}

/** Gera um ID compatível com a app: {timestamp_ms}-{gas_id}+incremento */
function supabase_new_id($gas_id, $increment = 0) {
    return (string)(round(microtime(true) * 1000) + $increment) . '-' . $gas_id;
}
