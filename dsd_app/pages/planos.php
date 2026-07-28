<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Planos de Estudo';
$activePage = 'planos';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        try {
            $db->prepare("DELETE FROM infodeqb_dsd_plano_estudo WHERE id=?")->execute([(int)$_POST['delete_id']]);
            flash('Plano removido.');
        } catch (Exception $e) {
            flash('Não foi possível remover (tem UCs associadas?): ' . $e->getMessage(), 'error');
        }
    } elseif (isset($_POST['sigla'])) {
        $id     = (int)($_POST['id'] ?? 0);
        $sigla  = trim($_POST['sigla']);
        $desig  = trim($_POST['designacao'] ?? '');
        $tipo   = $_POST['tipo_curso'] ?? '';
        $ordem  = (int)($_POST['ordem'] ?? 99);
        if (!$sigla) {
            flash('Sigla obrigatória.', 'error');
        } else {
            try {
                if ($id) {
                    $db->prepare("UPDATE infodeqb_dsd_plano_estudo SET sigla=?,designacao=?,tipo_curso=?,ordem=? WHERE id=?")
                       ->execute([$sigla, $desig, $tipo, $ordem, $id]);
                    flash('Plano atualizado.');
                } else {
                    $db->prepare("INSERT INTO infodeqb_dsd_plano_estudo (sigla,designacao,tipo_curso,ordem) VALUES (?,?,?,?)")
                       ->execute([$sigla, $desig, $tipo, $ordem]);
                    flash('Plano adicionado.');
                }
            } catch (Exception $e) {
                flash('Erro: ' . $e->getMessage(), 'error');
            }
        }
    }
    header('Location: planos.php'); exit;
}

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem, sigla")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title"><i class="fas fa-graduation-cap me-1"></i>Planos de Estudo</div>
  <div class="page-sub"><?= count($planos) ?> planos</div></div>
</div>

<div class="card">
  <div class="card-title">Adicionar / Editar</div>
  <form method="post" class="form-grid">
    <input type="hidden" name="id" id="p-id" value="">
    <div class="form-group"><label>Sigla *</label>
      <input type="text" name="sigla" id="p-sigla" required></div>
    <div class="form-group"><label>Designação</label>
      <input type="text" name="designacao" id="p-desig"></div>
    <div class="form-group"><label>Tipo</label>
      <select name="tipo_curso" id="p-tipo">
        <option value="">—</option>
        <option value="Lic">Licenciatura</option>
        <option value="M">Mestrado</option>
        <option value="D">Doutoramento</option>
      </select></div>
    <div class="form-group"><label>Ordem</label>
      <input type="number" name="ordem" id="p-ordem" value="99"></div>
    <div class="form-group full" style="display:flex;gap:10px">
      <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Guardar</button>
      <button class="btn btn-secondary" type="button" onclick="limparPlano()">Limpar</button>
    </div>
  </form>
</div>

<div class="card">
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Sigla</th><th>Designação</th><th>Tipo</th><th style="text-align:center">Ordem</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($planos as $p): ?>
  <tr>
    <td><strong><?= esc($p['sigla']) ?></strong></td>
    <td><?= esc($p['designacao'] ?? '') ?></td>
    <td><?= esc($p['tipo_curso'] ?? '') ?></td>
    <td style="text-align:center"><?= (int)$p['ordem'] ?></td>
    <td style="text-align:right">
      <button class="btn btn-secondary btn-xs"
              onclick='editarPlano(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
      <form method="post" style="display:inline">
        <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
        <button class="btn btn-danger btn-xs" data-confirm="Remover plano <?= esc($p['sigla']) ?>?"><i class="fas fa-trash"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<script>
function editarPlano(p) {
  document.getElementById('p-id').value = p.id;
  document.getElementById('p-sigla').value = p.sigla || '';
  document.getElementById('p-desig').value = p.designacao || '';
  document.getElementById('p-tipo').value  = p.tipo_curso || '';
  document.getElementById('p-ordem').value = p.ordem || 99;
  window.scrollTo({top:0, behavior:'smooth'});
}
function limparPlano() {
  document.getElementById('p-id').value = '';
  document.getElementById('p-sigla').value = '';
  document.getElementById('p-desig').value = '';
  document.getElementById('p-tipo').value  = '';
  document.getElementById('p-ordem').value = 99;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>