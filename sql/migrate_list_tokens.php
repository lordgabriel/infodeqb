<?php
$pdo = new PDO('mysql:host=localhost;dbname=feupptdeqb;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->query("
    SELECT r.acessos
    FROM infodeqb_rds_registo r
    LEFT JOIN infodeqb_rds_registo_acessos a ON a.registo_id = r.autoid
    WHERE a.registo_id IS NULL
      AND r.status = 'Ativo'
      AND TRIM(COALESCE(r.acessos,'')) != ''
");

$tokens = [];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $raw) {
    foreach (explode(';', $raw) as $t) {
        $t = trim($t);
        if ($t !== '') $tokens[$t] = true;
    }
}
ksort($tokens);

// Verificar quais já estão no mapa (por nomegab_old)
$inMap = [];
$mapStmt = $pdo->query("SELECT nomegab_old FROM infodeqb_rds_gabinetes_map WHERE nomegab_old != ''");
foreach ($mapStmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
    $inMap[$d] = true;
}
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<title>Tokens únicos em acessosid</title>
<style>
body{font-family:monospace;font-size:13px;margin:2rem}
table{border-collapse:collapse}
th,td{border:1px solid #ddd;padding:.3rem .7rem;text-align:left}
th{background:#eee}
.ok{color:#166534}.miss{color:#991b1b;font-weight:bold}
</style>
</head>
<body>
<h2>Tokens únicos em acessos/nomes (registos ativos sem migração)</h2>
<p>Total: <?= count($tokens) ?> tokens únicos</p>
<table>
<tr><th>#</th><th>Token</th><th>No mapa?</th></tr>
<?php $i=1; foreach ($tokens as $t => $_): ?>
<tr>
    <td><?= $i++ ?></td>
    <td><?= htmlspecialchars($t) ?></td>
    <td class="<?= isset($inMap[$t]) ? 'ok' : 'miss' ?>"><?= isset($inMap[$t]) ? '✓' : '✗ FALTA' ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
