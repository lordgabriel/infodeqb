<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
$pageTitle  = 'Departamentos';
$activePage = 'admin';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        try {
            $db->prepare("DELETE FROM infodeqb_dsd_departamento WHERE id=?")->execute([(int)$_POST['delete_id']]);
            flash('Removido.');
        } catch (Exception $e) {
            flash('Não foi possível remover (tem docentes associados?)', 'error');
        }
    } else {
        $id  = (int)($_POST['id'] ?? 0);
        $sig = trim($_POST['sigla'] ?? '');
        $des = trim($_POST['designacao'] ?? '');
        if (!$sig) { flash('Sigla obrigatória.', 'error'); }
        else {
            try {
                if ($id) {
                    $db->prepare("UPDATE infodeqb_dsd_departamento SET sigla=?,designacao=? WHERE id=?")->execute([$sig,$des,$id]);
                } else {
                    $chk = $db->prepare("SELECT id FROM infodeqb_dsd_departamento WHERE sigla=? LIMIT 1");
                    $chk->execute([$sig]);
                    if ($chk->fetch()) flash('Já existe um departamento com essa sigla.', 'error');
                    else $db->prepare("INSERT INTO infodeqb_dsd_departamento (sigla,designacao) VALUES (?,?)")->execute([$sig,$des]);
                }
                flash('Guardado.');
            } catch (Exception $e) { flash('Erro: '.$e->getMessage(),'error'); }
        }
    }
    header('Location: departamentos.php'); exit;
}
$rows = $db->query("SELECT * FROM infodeqb_dsd_departamento ORDER BY sigla")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title">🏢 Departamentos</div>
  <div class="page-sub"><?= count($rows) ?> registos</div></div>
  <a href="admin.php" class="btn btn-secondary">← Administração</a>
</div>
<div class="card">
  <div class="card-title">Adicionar / Editar</div>
  <form method="post" class="form-grid">
    <input type="hidden" name="id" id="f-id" value="">
    <div class="form-group"><label>Sigla *</label>
      <input type="text" name="sigla" id="f-sig" placeholder="ex: DEQB" required></div>
    <div class="form-group"><label>Designação</label>
      <input type="text" name="designacao" id="f-des" placeholder="ex: Dep. Eng. Química e Biológica"></div>
    <div class="form-group full" style="display:flex;gap:10px">
      <button class="btn btn-primary" type="submit">💾 Guardar</button>
      <button class="btn btn-secondary" type="button" onclick="limpar()">Limpar</button>
    </div>
  </form>
</div>
<div class="card"><div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Sigla</th><th>Designação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><strong><?= esc($r['sigla']) ?></strong></td>
    <td><?= esc($r['designacao'] ?? '') ?></td>
    <td style="text-align:right;white-space:nowrap">
      <button class="btn btn-secondary btn-xs" onclick='editar(<?= json_encode($r) ?>)'>✏️</button>
      <form method="post" style="display:inline">
        <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
        <button class="btn btn-danger btn-xs" data-confirm="Remover?">🗑️</button>
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
  document.getElementById('f-sig').value = r.sigla || '';
  document.getElementById('f-des').value = r.designacao || '';
  window.scrollTo({top:0,behavior:'smooth'});
}
function limpar() {
  document.getElementById('f-id').value = '';
  document.getElementById('f-sig').value = '';
  document.getElementById('f-des').value = '';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
