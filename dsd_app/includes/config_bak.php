<?php
// ============================================================
// Configuração da base de dados
// ============================================================
define('DB_HOST', 'webdb.fe.up.pt');
define('DB_USER', 'deqfeuppt');
define('DB_PASS', 'kiuQuooth6eiWeichaiqui9uicho3U');
define('DB_NAME', 'deqfeuppt');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'DSD – Distribuição de Serviço Docente');
define('APP_VERSION', '2.0');
define('APP_YEAR', '2026/2027');

// ── Configuração externa (config.json) ───────────────────────
function getConfig(string $key, $default = null) {
    static $cfg = null;
    if ($cfg === null) {
        $cfgFile = dirname(__DIR__) . '/config.json';
        $cfg = file_exists($cfgFile) ? (json_decode(file_get_contents($cfgFile), true) ?? []) : [];
    }
    return $cfg[$key] ?? $default;
}
define('ACCESS_PUBLIC_REPORTS', (bool)getConfig('public_reports', false));

// ── BASE_URL auto-detectado ───────────────────────────────
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = preg_replace('#/(pages|reports)$#', '', $scriptDir);
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
define('BASE_URL', rtrim($scriptDir, '/'));

date_default_timezone_set('Europe/Lisbon');

// ============================================================
// PDO
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#c00"><h2>Erro de ligação à BD</h2><p>'
                . htmlspecialchars($e->getMessage())
                . '</p><p>Verifique <code>includes/config.php</code></p></div>');
        }
    }
    return $pdo;
}

// ============================================================
// Helpers
// ============================================================
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt(float $v, int $dec = 2): string {
    return number_format($v, $dec, ',', '.');
}

function num(mixed $v): float {
    if ($v === null || $v === '') return 0.0;
    return (float)str_replace(',', '.', (string)$v);
}

function getAnoLetivoAtivo(): array {
    if (!empty($_SESSION['ano_letivo_id'])) {
        $stmt = getDB()->prepare("SELECT * FROM infodeqb_dsd_ano_letivo WHERE id=?");
        $stmt->execute([$_SESSION['ano_letivo_id']]);
        $r = $stmt->fetch();
        if ($r) return $r;
    }
    $stmt = getDB()->query("SELECT * FROM infodeqb_dsd_ano_letivo WHERE ativo=1 ORDER BY id DESC LIMIT 1");
    return $stmt->fetch() ?: ['id' => 1, 'designacao' => APP_YEAR];
}


function getDocenteAno(PDO $db, int $anoLetivoId): string {
    // Returns the SQL JOIN + COALESCE snippet for docente snapshot queries.
    // Usage: add this JOIN after JOIN infodeqb_dsd_docente doc, then use coalesced fields.
    return "LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=$anoLetivoId";
}

function coalesceDoc(string $field): string {
    // Returns COALESCE(da.field, doc.field) AS field
    return "COALESCE(da.$field, doc.$field) AS $field";
}
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ============================================================
// Cálculo de horas necessárias/atribuídas por ocorrência
// Tudo em h/semana (referencial 13 semanas)
// ============================================================

/**
 * Horas necessárias por tipologia (em h/semana).
 * A ocorrência tem sempre 13 semanas → necessárias = n_turmas × h/sem.
 */
function horasNecessarias(array $ocor): array {
    $tipos = ['T','TP','L','Sem','OT'];
    $out = ['total' => 0];
    $semanas = num($ocor['semanas'] ?? 13);
    $ref = $semanas > 0 ? $semanas / 13 : 1;
    foreach ($tipos as $t) {
        $n = num($ocor["n_turmas_$t"] ?? 0);
        $h = num($ocor["horas_$t"] ?? 0);
        $out[$t] = $n * $h * $ref;
        $out['total'] += $out[$t];
    }
    return $out;
}

/**
 * Soma horas atribuídas (em h/semana) por tipologia para uma ocorrência.
 * As distribuições têm `semanas` próprio (≤ 13) → normaliza-se dividindo por 13.
 */
function horasAtribuidas(PDO $db, int $ocorrenciaId): array {
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(turmas_T   * horas_T   * semanas / 13), 0) AS T,
            COALESCE(SUM(turmas_TP  * horas_TP  * semanas / 13), 0) AS TP,
            COALESCE(SUM(turmas_L   * horas_L   * semanas / 13), 0) AS L,
            COALESCE(SUM(turmas_Sem * horas_Sem * semanas / 13), 0) AS Sem,
            COALESCE(SUM(turmas_OT  * horas_OT  * semanas / 13), 0) AS OT
        FROM infodeqb_dsd_distribuicao
        WHERE ocorrencia_id = ?
    ");
    $stmt->execute([$ocorrenciaId]);
    $r = $stmt->fetch() ?: ['T'=>0,'TP'=>0,'L'=>0,'Sem'=>0,'OT'=>0];
    $r['total'] = (float)$r['T'] + (float)$r['TP'] + (float)$r['L'] + (float)$r['Sem'] + (float)$r['OT'];
    return array_map('floatval', $r);
}

function horasEmFalta(array $necessarias, array $atribuidas): array {
    $out = [];
    foreach (['T','TP','L','Sem','OT','total'] as $k) {
        $out[$k] = ($necessarias[$k] ?? 0) - ($atribuidas[$k] ?? 0);
    }
    return $out;
}