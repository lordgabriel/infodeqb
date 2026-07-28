<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Catálogo de UCs';
$activePage = 'ucs';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $db->prepare("UPDATE infodeqb_dsd_uc SET ativo=0 WHERE id=?")->execute([(int)$_POST['delete_id']]);
    flash('UC removida.');
    header('Location: ucs.php'); exit;
}

$al = getAnoLetivoAtivo();
$ucsStmt = $db->prepare("
    SELECT u.*, p.sigla AS plano_sigla, pr.sigla AS plano_resp,
           GROUP_CONCAT(DISTINCT ac.sigla ORDER BY ac.sigla SEPARATOR ', ') AS areas,
           o.horas_T, o.horas_TP, o.horas_L, o.horas_OT, o.f_slef,
           o.n_turmas_T, o.n_turmas_TP, o.n_turmas_L, o.n_turmas_OT
    FROM infodeqb_dsd_uc u
    LEFT JOIN infodeqb_dsd_plano_estudo p  ON u.plano_id = p.id
    LEFT JOIN infodeqb_dsd_plano_estudo pr ON u.plano_resp_id = pr.id
    LEFT JOIN infodeqb_dsd_uc_area uac ON uac.uc_id = u.id
    LEFT JOIN infodeqb_dsd_area_cientifica ac ON ac.id = uac.area_id
    LEFT JOIN infodeqb_dsd_uc_ocorrencia o ON o.uc_id = u.id AND o.ano_letivo_id = ?
    WHERE u.ativo = 1
    GROUP BY u.id
    ORDER BY p.ordem, u.designacao
");
$ucsStmt->execute([$al['id']]);
$ucs = $ucsStmt->fetchAll();

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-book me-1"></i>Catálogo de Unidades Curriculares</div>
    <div class="page-sub"><?= count($ucs) ?> UCs &mdash; dados estáveis (não variam por ano)</div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="ocorrencias.php" class="btn btn-secondary"><i class="fas fa-calendar-alt me-1"></i>Ver Ocorrências</a>
    <a href="import-csv.php" class="btn btn-secondary"><i class="fas fa-file-upload me-1"></i>Importar CSV</a>
    <a href="uc-form.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Adicionar UC</a>
  </div>
</div>

<div class="card">
<div class="filter-bar">
  <input type="text" id="search-uc" placeholder="Pesquisar…">
  <select data-filter-select="0" data-filter-table="tbl-uc">
    <option value="">Todos os planos</option>
    <?php foreach ($planos as $p): ?>
      <option value="<?= esc($p['sigla']) ?>"><?= esc($p['sigla']) ?></option>
    <?php endforeach; ?>
  </select>
  <select data-filter-select="4" data-filter-table="tbl-uc">
    <option value="">Qualquer semestre</option>
    <option value="1S">1S</option><option value="2S">2S</option><option value="A">Anual</option>
  </select>
  <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button>
</div>

<div class="table-wrap">
<table class="data-table" id="tbl-uc">
  <thead>
    <tr>
      <th>Plano</th><th>Código</th><th>Unidade Curricular</th>
      <th>Ano</th><th>Sem.</th><th>Tipo</th>
      <th>Áreas</th>
      <th style="text-align:right">F SLEf</th>
      <th style="text-align:right">h T</th><th style="text-align:right">h TP</th>
      <th style="text-align:right">h L</th><th style="text-align:right">h OT</th>
      <th style="text-align:center">Ações</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($ucs as $u): ?>
  <tr>
    <td><span class="badge badge-blue"><?= esc($u['plano_sigla'] ?? '') ?></span></td>
    <td style="font-size:12px;color:var(--gray-500)"><?= esc($u['codigo'] ?? '') ?></td>
    <td><strong><?= esc($u['designacao']) ?></strong></td>
    <td><?= esc($u['ano'] ?? '') ?></td>
    <td style="text-align:center"><span class="badge badge-gray"><?= esc($u['semestre']) ?></span></td>
    <td><?= $u['tipo'] === 'OB' ? '<span class="badge badge-green">OB</span>' : '<span class="badge badge-orange">OPT</span>' ?></td>
    <td style="font-size:11px"><?= esc($u['areas'] ?? '') ?></td>
    <td class="num" style="color:var(--blue);font-weight:600"><?= $u['f_slef'] !== null ? fmt((float)$u['f_slef'],2) : '–' ?></td>
    <td class="num"><?= $u['horas_T'] > 0 ? fmt((float)$u['horas_T'],2) : '–' ?></td>
    <td class="num"><?= $u['horas_TP'] > 0 ? fmt((float)$u['horas_TP'],2) : '–' ?></td>
    <td class="num"><?= $u['horas_L'] > 0 ? fmt((float)$u['horas_L'],2) : '–' ?></td>
    <td class="num"><?= $u['horas_OT'] > 0 ? fmt((float)$u['horas_OT'],2) : '–' ?></td>
    <td style="text-align:center;white-space:nowrap">
      <a href="uc-form.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-xs"><i class="fas fa-edit"></i></a>
      <form method="post" style="display:inline">
        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
        <button class="btn btn-danger btn-xs" data-confirm="Remover esta UC?"><i class="fas fa-trash"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<script>filterTable('search-uc','tbl-uc',[0,1,2,3,6]);</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>