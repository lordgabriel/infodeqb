<?php
/**
 * migrate_legacy_acessos_run.php
 * Migra acessos do formato legado (campos acessosid/acessos) para a tabela
 * relacional infodeqb_rds_registo_acessos, usando infodeqb_rds_gabinetes_map.
 *
 * PRÉ-REQUISITOS:
 *   1. Correr migrate_legacy_acessos_setup.sql (cria as tabelas de mapa e log)
 *   2. BD local actualizada com sync_dev_db.ps1
 *
 * USO: http://localhost/infodeqb/sql/migrate_legacy_acessos_run.php
 *      (ou adicionar ?run=1 para executar; sem parâmetro mostra pré-visualização)
 */

$host   = 'localhost';
$db     = 'feupptdeqb';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Ligação falhou: ' . $e->getMessage());
}

$dryRun = !isset($_GET['run']);

// Verificar que as tabelas existem
$tables = ['infodeqb_rds_gabinetes_map', 'infodeqb_rds_migr_acessos_log', 'infodeqb_rds_registo_acessos'];
foreach ($tables as $t) {
    $r = $pdo->query("SHOW TABLES LIKE '$t'")->fetch();
    if (!$r) {
        die("<b>Tabela $t não existe.</b> Correr primeiro <code>migrate_legacy_acessos_setup.sql</code>.");
    }
}

// Mapa principal: nomegab_old → deqid_new (fonte: campo acessos)
$mapByName = [];
foreach ($pdo->query("SELECT nomegab_old, deqid_new FROM infodeqb_rds_gabinetes_map WHERE nomegab_old != ''")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $mapByName[trim($row['nomegab_old'])] = trim($row['deqid_new']);
}

// Mapa secundário: deqid_old → deqid_new (fallback se acessos estiver vazio)
$map = [];
foreach ($pdo->query("SELECT deqid_old, deqid_new FROM infodeqb_rds_gabinetes_map")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $map[trim($row['deqid_old'])] = trim($row['deqid_new']);
}

// Conjunto de deqids válidos em gabinetes (para avisos)
$validDeqids = [];
foreach ($pdo->query("SELECT deqid FROM infodeqb_rds_gabinetes")->fetchAll(PDO::FETCH_COLUMN) as $d) {
    $validDeqids[$d] = true;
}

// Registos activos com acessos legados sem migração
$stmt = $pdo->query("
    SELECT r.autoid, r.codigo, r.acessosid, r.acessos
    FROM infodeqb_rds_registo r
    LEFT JOIN infodeqb_rds_registo_acessos a ON a.registo_id = r.autoid
    WHERE a.registo_id IS NULL
      AND r.status = 'Ativo'
      AND (TRIM(COALESCE(r.acessos,'')) != '' OR TRIM(COALESCE(r.acessosid,'')) != '')
    ORDER BY r.autoid
");
$registos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar inserts (só usados quando !$dryRun)
if (!$dryRun) {
    $insAcesso = $pdo->prepare("INSERT IGNORE INTO infodeqb_rds_registo_acessos (registo_id, lab_id) VALUES (?, ?)");
    $insLog    = $pdo->prepare("INSERT INTO infodeqb_rds_migr_acessos_log
        (registo_id, codigo, acessos_orig, mapeados, nao_mapeados, aviso, migrado_em)
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
}

// ---- Processar ----
$results = [];
foreach ($registos as $reg) {
    $id    = (int)$reg['autoid'];
    $cod   = $reg['codigo'];
    $idsRaw  = trim($reg['acessosid'] ?? '');
    $nomRaw  = trim($reg['acessos']   ?? '');

    // Tokens do campo acessos (nomes) — fonte primária
    $nameTokens = [];
    if ($nomRaw !== '') {
        foreach (explode(';', $nomRaw) as $t) {
            $t = trim($t);
            if ($t !== '') $nameTokens[] = $t;
        }
    }

    // Tokens do campo acessosid — fallback se acessos estiver vazio
    $idTokens = [];
    if ($idsRaw !== '') {
        foreach (explode(';', $idsRaw) as $t) {
            $t = trim($t);
            if ($t !== '') $idTokens[] = $t;
        }
    }

    $mapeados    = [];
    $naoMapeados = [];
    $avisos      = [];

    $source = !empty($nameTokens) ? $nameTokens : $idTokens;
    $byName = !empty($nameTokens);

    foreach ($source as $token) {
        if ($byName && isset($mapByName[$token])) {
            $newId = $mapByName[$token];
        } elseif (!$byName && isset($map[$token])) {
            $newId = $map[$token];
        } else {
            $naoMapeados[] = $token;
            continue;
        }
        $mapeados[] = $newId;
        if (!isset($validDeqids[$newId])) {
            $avisos[] = "$newId não existe em gabinetes";
        }
    }

    $mapeados = array_values(array_unique($mapeados));

    $results[$id] = [
        'id'          => $id,
        'codigo'      => $cod,
        'acessosid'   => $idsRaw,
        'acessos'     => $nomRaw,
        'mapeados'    => $mapeados,
        'naoMapeados' => $naoMapeados,
        'avisos'      => $avisos,
    ];

    if (!$dryRun) {
        foreach ($mapeados as $labId) {
            $insAcesso->execute([$id, $labId]);
        }
        $avisosStr = implode('; ', $avisos);
        $insLog->execute([
            $id,
            $cod,
            $idsRaw,
            implode('; ', $mapeados),
            implode('; ', $naoMapeados),
            $avisosStr,
        ]);
    }
}

// ---- Estatísticas ----
$total      = count($results);
$comFalha   = 0;
$comAviso   = 0;
$semAcessos = 0;
foreach ($results as $r) {
    if (!empty($r['naoMapeados'])) $comFalha++;
    if (!empty($r['avisos']))      $comAviso++;
    if (empty($r['mapeados']) && empty($r['naoMapeados'])) $semAcessos++;
}

?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<title>Migração de acessos legados</title>
<style>
body{font-family:monospace;font-size:13px;margin:2rem;background:#f8f8f8}
h1{font-size:1.2rem;margin-bottom:.5rem}
.stats{background:#fff;border:1px solid #ddd;padding:.8rem 1rem;margin-bottom:1rem;display:flex;gap:2rem}
.stats span{display:block;font-size:.85rem}
.stats b{font-size:1.1rem}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #ddd;padding:.3rem .6rem;vertical-align:top;font-size:12px}
th{background:#eee;text-align:left}
.ok{color:#166534}.fail{color:#991b1b}.warn{color:#92400e}.empty{color:#6b7280}
.dry{background:#fef9c3;border:1px solid #fde047;padding:.6rem 1rem;margin-bottom:1rem;font-size:.9rem}
.btn{display:inline-block;background:#1d4ed8;color:#fff;padding:.5rem 1.2rem;text-decoration:none;border-radius:4px;font-size:.9rem;margin-bottom:1rem}
.done{background:#bbf7d0;border:1px solid #4ade80;padding:.6rem 1rem;margin-bottom:1rem}
</style>
</head>
<body>
<h1>Migração de acessos legados → infodeqb_rds_registo_acessos</h1>

<?php if ($dryRun): ?>
<div class="dry">
    <b>Modo pré-visualização</b> — nenhuma alteração foi feita.<br>
    Confirma os resultados abaixo e clica em <em>Executar migração</em> para aplicar.
</div>
<a class="btn" href="?run=1">Executar migração</a>
<?php else: ?>
<div class="done">
    <b>Migração concluída.</b> <?= $total ?> registos processados.
    Consulta <code>infodeqb_rds_migr_acessos_log</code> para o registo completo.
</div>
<?php endif; ?>

<div class="stats">
    <div><b><?= $total ?></b><span>registos a migrar</span></div>
    <div><b class="ok"><?= $total - $comFalha - $semAcessos ?></b><span>totalmente mapeados</span></div>
    <div><b class="fail"><?= $comFalha ?></b><span>com tokens não mapeados</span></div>
    <div><b class="warn"><?= $comAviso ?></b><span>com avisos (deqid não em gabinetes)</span></div>
    <div><b class="empty"><?= $semAcessos ?></b><span>sem acessos (campos vazios?)</span></div>
</div>

<table>
<thead>
<tr>
    <th>ID</th><th>Código</th><th>acessosid</th><th>acessos (nomes)</th>
    <th>Mapeados →</th><th>Não mapeados</th><th>Avisos</th>
</tr>
</thead>
<tbody>
<?php foreach ($results as $r):
    $rowClass = !empty($r['naoMapeados']) ? 'fail' : (!empty($r['avisos']) ? 'warn' : 'ok');
?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['codigo']) ?></td>
    <td><?= htmlspecialchars($r['acessosid']) ?></td>
    <td style="color:#6b7280"><?= htmlspecialchars($r['acessos']) ?></td>
    <td class="ok"><?= htmlspecialchars(implode('; ', $r['mapeados'])) ?></td>
    <td class="fail"><?= htmlspecialchars(implode('; ', $r['naoMapeados'])) ?></td>
    <td class="warn"><?= htmlspecialchars(implode('; ', $r['avisos'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</body>
</html>
