<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$db = getDB();
$al = getAnoLetivoAtivo();

$tipo = $_GET['tipo'] ?? '';
$ano  = (int)($_GET['ano'] ?? $al['id']);

$ts   = date('Ymd_His');
$alRow = $db->prepare("SELECT designacao FROM infodeqb_dsd_ano_letivo WHERE id=?");
$alRow->execute([$ano]);
$alDesig = str_replace('/', '-', $alRow->fetchColumn() ?: 'todos');

// ── SQL BACKUP ────────────────────────────────────────────────
if ($tipo === 'sql') {
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=dsd_backup_{$ts}.sql");
    header('Cache-Control: no-cache');

    echo "-- DSD DEQB – Backup completo\n";
    echo "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Ano letivo activo: {$alDesig}\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "SET NAMES utf8mb4;\n\n";

    $tables = [
        'infodeqb_dsd_ano_letivo', 'infodeqb_dsd_carreira', 'infodeqb_dsd_categoria',
        'infodeqb_dsd_departamento', 'infodeqb_dsd_plano_estudo', 'infodeqb_dsd_area_cientifica',
        'infodeqb_dsd_docente', 'infodeqb_dsd_docente_ano', 'infodeqb_dsd_uc', 'infodeqb_dsd_uc_area',
        'infodeqb_dsd_uc_ocorrencia', 'infodeqb_dsd_distribuicao',
    ];

    foreach ($tables as $table) {
        // Structure
        $r = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        $ddl = $r[1] ?? $r['Create Table'] ?? '';
        echo "-- ── $table ──\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $ddl . ";\n\n";

        // Data
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { echo "\n"; continue; }

        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
        $chunks = array_chunk($rows, 100);
        foreach ($chunks as $chunk) {
            $vals = array_map(function($row) use ($db) {
                return '(' . implode(', ', array_map(function($v) use ($db) {
                    return $v === null ? 'NULL' : $db->quote((string)$v);
                }, array_values($row))) . ')';
            }, $chunk);
            echo "INSERT INTO `$table` ($cols) VALUES\n";
            echo implode(",\n", $vals) . ";\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// ── CSV DISTRIBUIÇÃO (ano específico) ─────────────────────────
if ($tipo === 'csv_dist') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=infodeqb_dsd_distribuicao_{$alDesig}_{$ts}.csv");
    echo "\xEF\xBB\xBF"; // BOM UTF-8

    $rows = $db->prepare("
        SELECT pe.sigla AS Plano, uc.codigo AS Codigo, uc.designacao AS UC,
               uc.semestre AS Semestre, uc.tipo AS Tipo,
               doc.nome AS Docente, dep.sigla AS Departamento,
               car.designacao AS Carreira,
               d.regente AS Regente, d.dsd_por_docente AS DSD,
               d.semanas AS Semanas,
               d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
               d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem,
               d.turmas_OT, d.horas_OT, d.h_tese,
               ROUND((d.turmas_T*d.horas_T+d.turmas_TP*d.horas_TP+d.turmas_L*d.horas_L
                     +d.turmas_Sem*d.horas_Sem+d.turmas_OT*d.horas_OT)*d.semanas/13,2) AS H_por_semana,
               ROUND((d.turmas_T*d.horas_T+d.turmas_TP*d.horas_TP+d.turmas_L*d.horas_L
                     +d.turmas_Sem*d.horas_Sem)*d.semanas/13*o.f_slef,2) AS H_SLEf
        FROM infodeqb_dsd_distribuicao d
        JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id=o.id
        JOIN infodeqb_dsd_uc uc ON o.uc_id=uc.id
        LEFT JOIN infodeqb_dsd_plano_estudo pe ON uc.plano_id=pe.id
        JOIN infodeqb_dsd_docente doc ON d.docente_id=doc.id
        LEFT JOIN infodeqb_dsd_departamento dep ON doc.departamento_id=dep.id
        LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
        LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id=car.id
        WHERE d.ano_letivo_id=?
        ORDER BY pe.ordem, uc.designacao, car.ordem, doc.nome
    ");
    $rows->execute([$ano, $ano]);
    $data = $rows->fetchAll(PDO::FETCH_ASSOC);

    if ($data) {
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $r) fputcsv($out, $r, ';');
        fclose($out);
    }
    exit;
}

// ── CSV DOCENTES ──────────────────────────────────────────────
if ($tipo === 'csv_doc') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=infodeqb_dsd_docentes_{$alDesig}_{$ts}.csv");
    echo "\xEF\xBB\xBF";

    $rows = $db->prepare("
        SELECT doc.nome AS Nome, car.designacao AS Carreira,
               dep.sigla AS Departamento, da.deti AS DETI,
               da.h_slef AS H_SLEf, da.ref_ecdu AS Ref_ECDU,
               da.observacoes AS Observacoes
        FROM infodeqb_dsd_docente doc
        LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
        LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id=car.id
        LEFT JOIN infodeqb_dsd_departamento dep ON doc.departamento_id=dep.id
        WHERE da.ativo=1
        ORDER BY car.ordem, doc.nome
    ");
    $rows->execute([$ano]);
    $data = $rows->fetchAll(PDO::FETCH_ASSOC);

    if ($data) {
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $r) fputcsv($out, $r, ';');
        fclose($out);
    }
    exit;
}

// ── CSV UCs + OCORRÊNCIAS ─────────────────────────────────────
if ($tipo === 'csv_ucs') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=infodeqb_dsd_ucs_{$alDesig}_{$ts}.csv");
    echo "\xEF\xBB\xBF";

    $rows = $db->prepare("
        SELECT pe.sigla AS Plano_de_estudos, uc.codigo AS Codigo,
               uc.designacao AS UC, uc.ano AS Ano, uc.semestre AS Semestre,
               uc.tipo AS Tipo,
               GROUP_CONCAT(ac.sigla ORDER BY ua.principal DESC SEPARATOR ',') AS Areas,
               o.estudantes AS Estudantes, o.f_slef AS F_SLEf,
               o.outros_planos AS Outros_Planos, o.semanas AS Semanas,
               o.n_turmas_T AS Turmas_T, o.horas_T AS T,
               o.n_turmas_TP AS Turmas_TP, o.horas_TP AS TP,
               o.n_turmas_L AS Turmas_L, o.horas_L AS PL,
               o.n_turmas_Sem AS Turmas_S, o.horas_Sem AS S,
               o.n_turmas_OT AS Turmas_OT, o.horas_OT AS OT
        FROM infodeqb_dsd_uc_ocorrencia o
        JOIN infodeqb_dsd_uc uc ON o.uc_id=uc.id
        LEFT JOIN infodeqb_dsd_plano_estudo pe ON uc.plano_id=pe.id
        LEFT JOIN infodeqb_dsd_uc_area ua ON ua.uc_id=uc.id
        LEFT JOIN infodeqb_dsd_area_cientifica ac ON ac.id=ua.area_id
        WHERE o.ano_letivo_id=?
        GROUP BY o.id
        ORDER BY pe.ordem, uc.designacao
    ");
    $rows->execute([$ano]);
    $data = $rows->fetchAll(PDO::FETCH_ASSOC);

    if ($data) {
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $r) fputcsv($out, $r, ';');
        fclose($out);
    }
    exit;
}

// ── CSV CATÁLOGO UCs (sem ocorrências) ───────────────────────
if ($tipo === 'csv_catalogo') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=dsd_catalogo_ucs_{$ts}.csv");
    echo "\xEF\xBB\xBF";
    $data = $db->query("
        SELECT pe.sigla AS Plano_de_estudos, uc.codigo AS Codigo,
               uc.designacao AS UC, uc.ano AS Ano, uc.semestre AS Semestre,
               uc.tipo AS Tipo, uc.especializacao AS Especializacao,
               GROUP_CONCAT(ac.sigla ORDER BY ua.principal DESC SEPARATOR ',') AS Areas
        FROM infodeqb_dsd_uc uc
        LEFT JOIN infodeqb_dsd_plano_estudo pe ON uc.plano_id=pe.id
        LEFT JOIN infodeqb_dsd_uc_area ua ON ua.uc_id=uc.id
        LEFT JOIN infodeqb_dsd_area_cientifica ac ON ac.id=ua.area_id
        GROUP BY uc.id ORDER BY pe.ordem, uc.designacao
    ")->fetchAll(PDO::FETCH_ASSOC);
    if ($data) {
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0]), ';');
        foreach ($data as $r) fputcsv($out, $r, ';');
        fclose($out);
    }
    exit;
}

// ── Página de exportação ──────────────────────────────────────
$pageTitle  = 'Exportar Dados';
$activePage = 'admin';
require_once __DIR__ . '/../includes/header.php';

$anos = $db->query("SELECT * FROM infodeqb_dsd_ano_letivo ORDER BY id DESC")->fetchAll();
?>
<div class="page-header">
  <div>
    <div class="page-title">📤 Exportar Dados</div>
    <div class="page-sub">Backup e exportação de dados do DSD</div>
  </div>
  <a href="admin.php" class="btn btn-secondary">← Administração</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

  <!-- Backup SQL -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-title">🗄️ Backup Completo (SQL)</div>
    <p style="font-size:13px;color:var(--gray-600);margin-bottom:16px">
      Exporta toda a base de dados em formato SQL — pode ser importado directamente no phpMyAdmin para restaurar.
      Inclui estrutura e dados de todas as tabelas.
    </p>
    <a href="export.php?tipo=sql" class="btn btn-primary">⬇️ Download Backup SQL</a>
  </div>

  <!-- Catálogo (sem ano) -->
  <div class="card">
    <div class="card-title">📚 Catálogo de UCs</div>
    <p style="font-size:13px;color:var(--gray-600);margin-bottom:16px">
      Exporta o catálogo permanente de UCs — independente do ano letivo.
      Compatível com o template de importação "Catálogo de UCs".
    </p>
    <a href="export.php?tipo=csv_catalogo" class="btn btn-secondary">⬇️ Exportar Catálogo</a>
  </div>

  <!-- Por ano letivo -->
  <div class="card">
    <div class="card-title">📅 Dados por Ano Letivo</div>
    <p style="font-size:13px;color:var(--gray-600);margin-bottom:16px">
      Exporta ocorrências, docentes ou distribuição de um ano letivo específico.
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <div class="form-group" style="margin:0">
        <label>Ano letivo</label>
        <select id="sel-ano" style="min-width:130px">
          <?php foreach($anos as $a): ?>
          <option value="<?= $a['id'] ?>" <?= $a['id']==$al['id']?'selected':'' ?>>
            <?= esc($a['designacao']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      <button onclick="dl('csv_ucs')"  class="btn btn-secondary">⬇️ Ocorrências</button>
      <button onclick="dl('csv_doc')"  class="btn btn-secondary">⬇️ Docentes</button>
      <button onclick="dl('csv_dist')" class="btn btn-secondary">⬇️ Distribuição</button>
    </div>
  </div>

</div>

<script>
function dl(tipo) {
  const ano = document.getElementById('sel-ano').value;
  window.location.href = 'export.php?tipo=' + tipo + '&ano=' + ano;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
