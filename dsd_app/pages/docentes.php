<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Docentes';
$activePage = 'docentes';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->prepare("UPDATE infodeqb_dsd_docente_ano SET ativo=0 WHERE docente_id=? AND ano_letivo_id=(SELECT id FROM infodeqb_dsd_ano_letivo WHERE ativo=1 ORDER BY id DESC LIMIT 1)")->execute([(int)$_POST['delete_id']]);
    flash('Docente removido.');
    header('Location: docentes.php'); exit;
}

$al = getAnoLetivoAtivo();
$docentesStmt = $db->prepare("
    SELECT d.id, d.nome, d.nome_curto,
           da.carreira_id  AS carreira_id,
           da.categoria_id AS categoria_id,
           da.deti          AS deti,
           da.h_slef        AS h_slef,
           da.ref_ecdu      AS ref_ecdu,
           da.observacoes   AS observacoes,
           da.ativo         AS ativo,
           da.id AS snap_id,
           c.designacao AS carreira_nome, cat.designacao AS categoria_nome,
           dep.sigla AS depto
    FROM infodeqb_dsd_docente d
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=d.id AND da.ano_letivo_id=?
    LEFT JOIN infodeqb_dsd_carreira c    ON da.carreira_id  = c.id
    LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id = cat.id
    LEFT JOIN infodeqb_dsd_departamento dep ON d.departamento_id = dep.id
    WHERE da.ativo = 1
    ORDER BY c.ordem, d.nome
");
$docentesStmt->execute([$al['id']]);
$docentes = $docentesStmt->fetchAll();

$carreiras = $db->query("SELECT * FROM infodeqb_dsd_carreira ORDER BY ordem")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">👩‍🏫 Docentes e Colaboradores</div>
    <div class="page-sub"><?= count($docentes) ?> registos</div>
  </div>
  <a href="docente-form.php" class="btn btn-primary">➕ Adicionar Docente</a>
</div>

<div class="card">
  <div class="filter-bar">
    <input type="text" id="search" placeholder="🔍 Pesquisar por nome, carreira, departamento…">
    <select data-filter-select="2" data-filter-table="tbl-doc">
      <option value="">Todas as carreiras</option>
      <?php foreach ($carreiras as $c): ?>
        <option value="<?= esc($c['designacao']) ?>"><?= esc($c['designacao']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir</button>
  </div>

  <div class="table-wrap">
  <table class="data-table" id="tbl-doc">
    <thead>
      <tr>
        <th>Nome</th>
        <th>Nome Curto</th>
        <th>Departamento</th>
        <th>Carreira</th>
        <th>Categoria</th>
        <th style="text-align:center">DETI</th>
        <th style="text-align:right">H SLEf</th>
        <th style="text-align:right">Ref ECDU</th>
        <th>Observações</th>
        <th style="text-align:center">Snap.</th>
        <th style="text-align:center">Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($docentes as $d): ?>
    <tr>
      <td><strong><?= esc($d['nome']) ?></strong></td>
      <td><?= $d['nome_curto'] ? esc($d['nome_curto']) : '<span style="color:var(--gray-400);font-size:11px">—</span>' ?></td>
      <td><span class="badge badge-gray"><?= esc($d['depto'] ?? '–') ?></span></td>
      <td><?= esc($d['carreira_nome'] ?? '–') ?></td>
      <td><?= esc($d['categoria_nome'] ?? '–') ?></td>
      <td style="text-align:center"><?= fmt((float)$d['deti'], 2) ?></td>
      <td class="num"><?= fmt((float)$d['h_slef'], 1) ?></td>
      <td class="num"><?= fmt((float)$d['ref_ecdu'], 1) ?></td>
      <td style="max-width:200px;font-size:12px;color:var(--gray-500)"><?= esc($d['observacoes'] ?? '') ?></td>
      <td style="text-align:center"><?= $d['snap_id'] ? '<span class="badge badge-green" title="Snapshot deste ano">✓</span>' : '<span class="badge badge-gray" title="A usar dados base">base</span>' ?></td>
      <td style="text-align:center;white-space:nowrap">
        <a href="docente-form.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-xs">✏️</a>
        <form method="post" style="display:inline">
          <input type="hidden" name="delete_id" value="<?= $d['id'] ?>">
          <button type="submit" class="btn btn-danger btn-xs"
                  data-confirm="Remover <?= esc($d['nome']) ?>?">🗑️</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script>filterTable('search','tbl-doc',[0,1,2,3]);</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>