<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'Áreas Científicas';
$activePage = 'areas';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        try {
            $db->prepare("DELETE FROM infodeqb_dsd_area_cientifica WHERE id=?")->execute([(int)$_POST['delete_id']]);
            flash('Área removida.');
        } catch (Exception $e) {
            flash('Erro: ' . $e->getMessage(), 'error');
        }
    } elseif (isset($_POST['sigla'])) {
        $id    = (int)($_POST['id'] ?? 0);
        $sigla = trim($_POST['sigla']);
        $desig = trim($_POST['designacao'] ?? '');
        if (!$sigla) { flash('Sigla obrigatória.', 'error'); }
        else {
            try {
                if ($id) {
                    $db->prepare("UPDATE infodeqb_dsd_area_cientifica SET sigla=?,designacao=? WHERE id=?")
                       ->execute([$sigla,$desig,$id]);
                    flash('Área atualizada.');
                } else {
                    $db->prepare("INSERT INTO infodeqb_dsd_area_cientifica (sigla,designacao) VALUES (?,?)")
                       ->execute([$sigla,$desig]);
                    flash('Área adicionada.');
                }
            } catch (Exception $e) { flash('Erro: ' . $e->getMessage(), 'error'); }
        }
    }
    header('Location: areas.php'); exit;
}

$areas = $db->query("SELECT * FROM infodeqb_dsd_area_cientifica ORDER BY sigla")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title"><i class="fas fa-flask me-1"></i>Áreas Científicas</div></div>
</div>

<div class="card">
<form method="post" class="form-grid">
  <input type="hidden" name="id" id="a-id" value="">
  <div class="form-group"><label>Sigla *</label>
    <input type="text" name="sigla" id="a-sigla" maxlength="10" required></div>
  <div class="form-group"><label>Designação</label>
    <input type="text" name="designacao" id="a-desig"></div>
  <div class="form-group full" style="display:flex;gap:10px">
    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Guardar</button>
    <button type="button" class="btn btn-secondary" onclick="document.querySelector('form').reset();document.getElementById('a-id').value=''">Limpar</button>
  </div>
</form>
</div>

<div class="card">
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Sigla</th><th>Designação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($areas as $a): ?>
  <tr>
    <td><strong><?= esc($a['sigla']) ?></strong></td>
    <td><?= esc($a['designacao'] ?? '') ?></td>
    <td style="text-align:right">
      <button class="btn btn-secondary btn-xs"
              onclick='document.getElementById("a-id").value=<?= $a['id'] ?>;
                       document.getElementById("a-sigla").value=<?= json_encode($a['sigla']) ?>;
                       document.getElementById("a-desig").value=<?= json_encode($a['designacao'] ?? '') ?>;
                       window.scrollTo({top:0,behavior:"smooth"})'><i class="fas fa-edit"></i></button>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
