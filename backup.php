<?php
/**
 * InfoDEQB — Backup de base de dados
 * Acesso restrito a administradores.
 * Gera um ficheiro .sql pronto a descarregar.
 *
 * Parâmetros GET:
 *   ?db=hr    → dump de feupptdeqb (tabelas infodeqb_*, excl. DSD)
 *   ?db=dsd   → dump de feupptdeqb (tabelas infodeqb_dsd_*)
 *   ?db=all   → dump completo de feupptdeqb
 */

session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
require ROOT_DIR . '/infodeqb/inc/admins.php';

if (!$isAdmin) {
    http_response_code(403);
    exit('Acesso negado.');
}

$which = isset($_GET['db']) ? $_GET['db'] : 'hr';
if (!in_array($which, ['hr', 'dsd', 'all'])) {
    http_response_code(400);
    exit('Parâmetro inválido.');
}

// ── Tudo está agora em feupptdeqb ────────────────────────────────
$pdo = Database::connect();

// Filtro de tabelas por modo
$tableFilter = null;
if ($which === 'hr') {
    // Todas as tabelas infodeqb_ excepto DSD
    $tableFilter = function($t) { return str_starts_with($t, 'infodeqb_') && !str_starts_with($t, 'infodeqb_dsd_'); };
} elseif ($which === 'dsd') {
    // Só tabelas DSD
    $tableFilter = function($t) { return str_starts_with($t, 'infodeqb_dsd_'); };
}
// 'all' → sem filtro (dump completo)

$targets = [[
    'label'       => 'feupptdeqb',
    'pdo'         => $pdo,
    'tableFilter' => $tableFilter,
]];

// ── Cabeçalhos HTTP ───────────────────────────────────────────────
$filename = 'infodeq_backup_' . $which . '_' . date('Ymd_His') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');
header('Pragma: no-cache');

// ── Helper: dump de uma tabela ────────────────────────────────────
function dumpTable($pdo, $table)
{
    // DROP + CREATE
    $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    echo "\n--\n-- Tabela: `$table`\n--\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $row[1] . ";\n";

    // Dados
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $count = 0;
    $cols  = null;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($cols === null) {
            $cols = '`' . implode('`, `', array_keys($r)) . '`';
        }
        $vals = array_map(function ($v) use ($pdo) {
            if ($v === null) return 'NULL';
            return $pdo->quote($v);
        }, $r);
        if ($count === 0) {
            echo "INSERT INTO `$table` ($cols) VALUES\n";
        }
        // Última linha usa ; em vez de ,
        // Acumulamos e fechamos depois
        echo '  (' . implode(', ', $vals) . ')';
        $count++;
        // Flush a cada 200 linhas para não acumular memória
        if ($count % 200 === 0) {
            echo ",\n";
            ob_flush(); flush();
        } else {
            echo ",\n";
        }
    }
    if ($count > 0) {
        // Substituir a última vírgula por ponto-e-vírgula é complicado em stream.
        // Em vez disso usamos INSERT separados por linha (padrão compatível).
        // Re-escreve usando múltiplos INSERTs — mais simples e seguro:
    }
}

// Substitui a abordagem de stream por buffer por tabela (tabelas pequenas/médias)
function dumpTableBuffered($pdo, $table)
{
    $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    echo "\n--\n-- Tabela: `$table`\n--\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $row[1] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return;
    }

    $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';

    // Agrupa em blocos de 200 linhas por INSERT
    $chunks = array_chunk($rows, 200);
    foreach ($chunks as $chunk) {
        $lines = [];
        foreach ($chunk as $r) {
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, $r);
            $lines[] = '  (' . implode(', ', $vals) . ')';
        }
        echo "INSERT INTO `$table` ($cols) VALUES\n";
        echo implode(",\n", $lines) . ";\n";
    }
    echo "\n";
    ob_flush(); flush();
}

// ── Cabeçalho do ficheiro SQL ─────────────────────────────────────
echo "-- ============================================================\n";
echo "-- InfoDEQB — Backup\n";
echo "-- Gerado em:    " . date('Y-m-d H:i:s') . "\n";
echo "-- Utilizador:   " . ($_SESSION['Code'] ?? 'desconhecido') . "\n";
echo "-- Base(s):      " . $which . "\n";
echo "-- ============================================================\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET CHARACTER SET utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";

foreach ($targets as $target) {
    $pdo    = $target['pdo'];
    $label  = $target['label'];
    $filter = $target['tableFilter'] ?? null;

    echo "\n-- ============================================================\n";
    echo "-- BASE DE DADOS: $label\n";
    echo "-- ============================================================\n";
    echo "USE `$label`;\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);

    foreach ($tables as $table) {
        if ($filter !== null && !$filter($table)) continue;
        dumpTableBuffered($pdo, $table);
    }
}

echo "\nSET FOREIGN_KEY_CHECKS = 1;\n";
echo "\n-- Fim do backup — " . date('Y-m-d H:i:s') . "\n";
exit;
