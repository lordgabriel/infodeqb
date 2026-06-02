<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Docente';
$activePage = 'docentes';
$db = getDB();

$al = getAnoLetivoAtivo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$docente = [];
if ($id) {
    $stmt = $db->prepare("
        SELECT d.id, d.nome, d.nome_curto, d.departamento_id,
               da.carreira_id  AS carreira_id,
               da.categoria_id AS categoria_id,
               da.deti          AS deti,
               da.h_slef        AS h_slef,
               da.ref_ecdu      AS ref_ecdu,
               da.observacoes   AS observacoes,
               da.ativo         AS ativo,
               da.id AS snap_id
        FROM infodeqb_dsd_docente d
        LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=d.id AND da.ano_letivo_id=?
        WHERE d.id=?
    ");
    $stmt->execute([$al['id'], $id]);
    $docente = $stmt->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = trim($_POST['nome'] ?? '');
    $nome_curto = trim($_POST['nome_curto'] ?? '');
    $carr_id  = (int)($_POST['carreira_id'] ?? 0) ?: null;
    $cat_id   = (int)($_POST['categoria_id'] ?? 0) ?: null;
    $dep_id   = (int)($_POST['departamento_id'] ?? 0) ?: null;
    $deti     = num($_POST['deti'] ?? 1);
    $h_slef   = num($_POST['h_slef'] ?? 0);
    $ref_ecdu = num($_POST['ref_ecdu'] ?? 0);
    $obs      = trim($_POST['observacoes'] ?? '');

    if (!$nome || !$nome_curto) {
        flash('O nome completo e o nome curto são obrigatórios.', 'error');
    } else {
        try {
            if ($id) {
                $db->prepare("UPDATE infodeqb_dsd_docente SET nome=?, nome_curto=?, departamento_id=? WHERE id=?")
                   ->execute([$nome, $nome_curto, $dep_id, $id]);
                // Upsert variable fields in infodeqb_dsd_docente_ano
                $db->prepare("
                    INSERT INTO infodeqb_dsd_docente_ano
                        (docente_id, ano_letivo_id, carreira_id, categoria_id, deti, h_slef, ref_ecdu, observacoes)
                    VALUES (?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        carreira_id=VALUES(carreira_id), categoria_id=VALUES(categoria_id),
                        deti=VALUES(deti), h_slef=VALUES(h_slef),
                        ref_ecdu=VALUES(ref_ecdu), observacoes=VALUES(observacoes)
                ")->execute([$id, $al['id'], $carr_id, $cat_id, $deti, $h_slef, $ref_ecdu, $obs]);
                flash("Docente atualizado: $nome");
            } else {
                $db->prepare("INSERT INTO infodeqb_dsd_docente (nome, nome_curto, departamento_id) VALUES (?,?,?)")
                   ->execute([$nome, $nome_curto, $dep_id]);
                $newId = $db->lastInsertId();
                // Create snapshot for current year
                $db->prepare("
                    INSERT INTO infodeqb_dsd_docente_ano
                        (docente_id, ano_letivo_id, carreira_id, categoria_id, deti, h_slef, ref_ecdu, observacoes, ativo)
                    VALUES (?,?,?,?,?,?,?,?,1)
                ")->execute([$newId, $al['id'], $carr_id, $cat_id, $deti, $h_slef, $ref_ecdu, $obs]);
                flash("Docente adicionado: $nome");
            }
            if (isset($_GET['back_scroll'])) {
        header('Location: distribuicao.php?scroll=' . (int)$_GET['back_scroll']);
    } else {
        header('Location: docentes.php');
    } exit;
        } catch (Exception $e) {
            flash('Erro: ' . $e->getMessage(), 'error');
        }
    }
}

$carreiras     = $db->query("SELECT * FROM infodeqb_dsd_carreira ORDER BY ordem")->fetchAll();
$categorias    = $db->query("SELECT * FROM infodeqb_dsd_categoria ORDER BY carreira_id, ordem")->fetchAll();
$departamentos = $db->query("SELECT * FROM infodeqb_dsd_departamento ORDER BY sigla")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><?= $id ? '✏️ Editar Docente' : '➕ Adicionar Docente' ?></div>
  </div>
  <a href="docentes.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="card">
<form method="post">
  <div class="form-grid">

    <div class="form-group" style="flex:2">
      <label>Nome completo *</label>
      <input type="text" name="nome" value="<?= esc($docente['nome'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label>Nome curto * <span style="font-weight:400;color:var(--gray-500)">(para gráficos)</span></label>
      <input type="text" name="nome_curto" maxlength="60"
             value="<?= esc($docente['nome_curto'] ?? '') ?>" required
             placeholder="Ex: João Silva">
    </div>

    <div class="form-group">
      <label>Carreira</label>
      <select name="carreira_id" id="sel-carreira">
        <option value="0">— Selecionar —</option>
        <?php foreach ($carreiras as $c): ?>
          <option value="<?= $c['id'] ?>"
            <?= ($docente['carreira_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
            <?= esc($c['designacao']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Categoria</label>
      <select name="categoria_id" id="sel-categoria">
        <option value="">— Selecionar —</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= $c['id'] ?>" data-carreira="<?= $c['carreira_id'] ?>"
            <?= ($docente['categoria_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
            <?= esc($c['designacao']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Departamento / Centro</label>
      <select name="departamento_id">
        <option value="">— Selecionar —</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>"
            <?= ($docente['departamento_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
            <?= esc($d['sigla']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>DETI (fator de tempo inteiro)</label>
      <input type="number" name="deti" step="0.01" min="0" max="1"
             value="<?= esc((string)($docente['deti'] ?? 1)) ?>">
      <span class="form-hint">1 = 100%, 0.5 = 50%, …</span>
    </div>

    <div class="form-group">
      <label>H SLEf (referência)</label>
      <input type="number" name="h_slef" step="0.01" min="0"
             value="<?= esc((string)($docente['h_slef'] ?? 0)) ?>">
    </div>

    <div class="form-group">
      <label>Ref ECDU</label>
      <input type="number" name="ref_ecdu" step="0.01" min="0"
             value="<?= esc((string)($docente['ref_ecdu'] ?? 0)) ?>">
    </div>

    <div class="form-group full">
      <div style="background:var(--blue-light);border-radius:6px;padding:7px 12px;font-size:12px;color:var(--blue-dark);margin-bottom:8px">
        💡 Carreira, Categoria, DETI, H SLEf, Ref ECDU e Observações são guardados por ano letivo (<?= esc($al['designacao']) ?>). Nome e Departamento são permanentes.
      </div>
      <label>Observações</label>
      <textarea name="observacoes"><?= esc($docente['observacoes'] ?? '') ?></textarea>
    </div>

  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">💾 Guardar</button>
    <a href="docentes.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<script>
const selCarr = document.getElementById('sel-carreira');
const selCat  = document.getElementById('sel-categoria');
const allOpts = Array.from(selCat.querySelectorAll('option'));

function filterCategorias() {
  const carrId = selCarr.value;
  allOpts.forEach(o => {
    o.hidden = o.value && o.dataset.carreira && o.dataset.carreira !== carrId;
  });
}
selCarr.addEventListener('change', filterCategorias);
filterCategorias();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>