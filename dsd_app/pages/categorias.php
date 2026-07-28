<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
$pageTitle  = 'Categorias';
$activePage = 'admin';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        try {
            $db->prepare("DELETE FROM infodeqb_dsd_categoria WHERE id=?")->execute([(int)$_POST['delete_id']]);
            flash('Removido.');
        } catch (Exception $e) {
            flash('Não foi possível remover (tem docentes associados?)', 'error');
        }
    } else {
        $id  = (int)($_POST['id'] ?? 0);
        $des = trim($_POST['designacao'] ?? '');
        $ord = (int)($_POST['ordem'] ?? 99);
        if (!$des) { flash('Designação obrigatória.', 'error'); }
        else {
            try {
                if ($id) {
                    $db->prepare("UPDATE infodeqb_dsd_categoria SET designacao=?,ordem=? WHERE id=?")->execute([$des,$ord,$id]);
                } else {
                    $chk = $db->prepare("SELECT id FROM infodeqb_dsd_categoria WHERE designacao=? LIMIT 1");
                    $chk->execute([$des]);
                    if ($chk->fetch()) flash('Já existe uma categoria com essa designação.', 'error');
                    else $db->prepare("INSERT INTO infodeqb_dsd_categoria (designacao,ordem) VALUES (?,?)")->execute([$des,$ord]);
                }
                flash('Guardado.');
            } catch (Exception $e) { flash('Erro: '.$e->getMessage(),'error'); }
        }
    }
    header('Location: categorias.php'); exit;
}
$rows = $db->query("SELECT * FROM infodeqb_dsd_categoria ORDER BY ordem, designacao")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title"><i class="fas fa-tags me-1"></i>Categorias</div>
  <div class="page-sub"><?= count($rows) ?> registos</div></div>
  <a href="admin.php" class="btn btn-secondary">← Administração</a>
</div>
<div class="card">
  <div class="card-title">Adicionar / Editar</div>
  <form method="post" class="form-grid">
    <input type="hidden" name="id" id="f-id" value="">
    <div class="form-group"><label>Designação *</label>
      <input type="text" name="designacao" id="f-des" placeholder="ex: Professor Auxiliar" required></div>
    <div class="form-group"><label>Ordem</label>
      <input type="number" name="ordem" id="f-ord" value="99" min="0"></div>
    <div class="form-group full" style="display:flex;gap:10px">
      <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Guardar</button>
      <button class="btn btn-secondary" type="button" onclick="limpar()">Limpar</button>
    </div>
  </form>
</div>
<div class="card"><div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Designação</th><th style="text-align:center">Ordem</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= esc($r['designacao']) ?></td>
    <td style="text-align:center"><?= (int)$r['ordem'] ?></td>
    <td style="text-align:right;white-space:nowrap">
      <button class="btn btn-secondary btn-xs" onclick='editar(<?= json_encode($r) ?>)'><i class="fas fa-edit"></i></button>
      <form method="post" style="display:inline">
        <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
        <button class="btn btn-danger btn-xs" data-confirm="Remover?"><i class="fas fa-trash"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<script>
function editar(r) {
  document.getElementById('f-id').value = r.id;
  document.getElementById('f-des').value = r.designacao || '';
  document.getElementById('f-ord').value = r.ordem || 99;
  window.scrollTo({top:0,behavior:'smooth'});
}
function limpar() {
  document.getElementById('f-id').value = '';
  document.getElementById('f-des').value = '';
  document.getElementById('f-ord').value = 99;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
