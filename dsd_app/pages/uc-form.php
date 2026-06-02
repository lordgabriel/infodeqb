<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'UC';
$activePage = 'ucs';
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$uc = [];
$areasUC = [];
$principalUC = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM infodeqb_dsd_uc WHERE id=?");
    $stmt->execute([$id]);
    $uc = $stmt->fetch() ?: [];

    $stmt = $db->prepare("SELECT area_id, principal FROM infodeqb_dsd_uc_area WHERE uc_id=?");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $r) {
        $areasUC[] = (int)$r['area_id'];
        if ($r['principal']) $principalUC = (int)$r['area_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Permite criar plano novo a partir do formulário
    $novaSiglaPlano = trim($_POST['novo_plano_sigla'] ?? '');
    $planoId = (int)($_POST['plano_id'] ?? 0) ?: null;
    $planoRespId = (int)($_POST['plano_resp_id'] ?? 0) ?: null;

    if ($novaSiglaPlano) {
        try {
            $db->prepare("INSERT IGNORE INTO infodeqb_dsd_plano_estudo (sigla, designacao, ordem) VALUES (?,?,99)")
               ->execute([$novaSiglaPlano, trim($_POST['novo_plano_desig'] ?? '')]);
            $stmt = $db->prepare("SELECT id FROM infodeqb_dsd_plano_estudo WHERE sigla=?");
            $stmt->execute([$novaSiglaPlano]);
            $r = $stmt->fetch();
            if ($r) $planoId = $planoId ?: (int)$r['id'];
        } catch (Exception $e) { /* ignora, plano pode já existir */ }
    }

    $campos = ['codigo','designacao','ano','semestre','especializacao','tipo','tipo_curso'];
    $floats = ['h_T','h_TP','h_L','h_Sem','h_OT'];

    $data = [];
    foreach ($campos as $c) $data[$c] = trim($_POST[$c] ?? '');
    $data['plano_id']      = $planoId;
    $data['plano_resp_id'] = $planoRespId;
    foreach ($floats as $c) $data[$c] = num($_POST[$c] ?? 0);

    if (!$data['designacao']) {
        flash('Designação obrigatória.', 'error');
    } else {
        try {
            if ($id) {
                $sets = implode(',', array_map(function($k) { return "$k=?"; }, array_keys($data)));
                $db->prepare("UPDATE infodeqb_dsd_uc SET $sets WHERE id=?")
                   ->execute([...array_values($data), $id]);
                $ucId = $id;
            } else {
                $cols = implode(',', array_keys($data));
                $phs  = implode(',', array_fill(0, count($data), '?'));
                $db->prepare("INSERT INTO infodeqb_dsd_uc ($cols) VALUES ($phs)")
                   ->execute(array_values($data));
                $ucId = (int)$db->lastInsertId();
            }

            // Áreas científicas
            $selAreas = array_map('intval', $_POST['areas'] ?? []);
            $principal = (int)($_POST['area_principal'] ?? 0);
            $db->prepare("DELETE FROM infodeqb_dsd_uc_area WHERE uc_id=?")->execute([$ucId]);
            if ($selAreas) {
                $ins = $db->prepare("INSERT INTO infodeqb_dsd_uc_area (uc_id,area_id,principal) VALUES (?,?,?)");
                foreach ($selAreas as $aid) {
                    $ins->execute([$ucId, $aid, $aid === $principal ? 1 : 0]);
                }
            }

            flash($id ? 'UC atualizada.' : 'UC adicionada.');
            header('Location: ucs.php'); exit;
        } catch (Exception $e) {
            flash('Erro: ' . $e->getMessage(), 'error');
        }
    }
}

$planos = $db->query("SELECT * FROM infodeqb_dsd_plano_estudo ORDER BY ordem, sigla")->fetchAll();
$areas  = $db->query("SELECT * FROM infodeqb_dsd_area_cientifica ORDER BY sigla")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title"><?= $id ? '✏️ Editar UC' : '➕ Adicionar UC' ?></div>
    <div class="page-sub">Dados estáveis da UC. Estudantes, fatores e turmas são por <strong>ocorrência</strong> (ano letivo).</div>
  </div>
  <a href="ucs.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="card">
<form method="post">
  <div class="form-grid">

    <div class="form-group">
      <label>Plano de Estudos Responsável</label>
      <select name="plano_resp_id">
        <option value="">— Selecionar —</option>
        <?php foreach ($planos as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($uc['plano_resp_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
            <?= esc($p['sigla']) ?><?= $p['designacao'] ? ' – ' . esc($p['designacao']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Plano de Estudos (principal)</label>
      <select name="plano_id" id="plano-id">
        <option value="">— Selecionar —</option>
        <?php foreach ($planos as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($uc['plano_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
            <?= esc($p['sigla']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a href="#" onclick="document.getElementById('novo-plano-box').style.display='block';return false"
         style="font-size:11px;margin-top:3px">➕ Criar novo plano</a>
    </div>

    <div id="novo-plano-box" class="form-group full" style="display:none;background:var(--blue-light);padding:10px;border-radius:6px">
      <label style="color:var(--blue-dark)">Novo Plano</label>
      <div style="display:flex;gap:10px">
        <input type="text" name="novo_plano_sigla" placeholder="Sigla (ex: M.XPT)" style="width:160px">
        <input type="text" name="novo_plano_desig" placeholder="Designação (opcional)" style="flex:1">
      </div>
      <span class="form-hint">Se preenchido, será criado e usado como plano principal desta UC.</span>
    </div>

    <div class="form-group">
      <label>Código</label>
      <input type="text" name="codigo" value="<?= esc($uc['codigo'] ?? '') ?>">
    </div>

    <div class="form-group full">
      <label>Designação *</label>
      <input type="text" name="designacao" value="<?= esc($uc['designacao'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label>Ano curricular</label>
      <input type="text" name="ano" value="<?= esc($uc['ano'] ?? '') ?>" placeholder="ex: 1º Ano">
    </div>

    <div class="form-group">
      <label>Semestre</label>
      <select name="semestre">
        <option value="1S" <?= ($uc['semestre'] ?? '') === '1S' ? 'selected' : '' ?>>1º Semestre</option>
        <option value="2S" <?= ($uc['semestre'] ?? '') === '2S' ? 'selected' : '' ?>>2º Semestre</option>
        <option value="A"  <?= ($uc['semestre'] ?? '') === 'A' ? 'selected' : '' ?>>Anual</option>
        <option value=""   <?= ($uc['semestre'] ?? '') === '' ? 'selected' : '' ?>>—</option>
      </select>
    </div>

    <div class="form-group">
      <label>Especialização</label>
      <input type="text" name="especializacao" value="<?= esc($uc['especializacao'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label>Tipo</label>
      <select name="tipo">
        <option value="OB"  <?= ($uc['tipo'] ?? 'OB') === 'OB' ? 'selected' : '' ?>>Obrigatória</option>
        <option value="OPT" <?= ($uc['tipo'] ?? '') === 'OPT' ? 'selected' : '' ?>>Optativa</option>
      </select>
    </div>

    <div class="form-group">
      <label>Tipo Curso</label>
      <select name="tipo_curso">
        <option value="">—</option>
        <option value="Lic" <?= ($uc['tipo_curso'] ?? '') === 'Lic' ? 'selected' : '' ?>>Licenciatura</option>
        <option value="M"   <?= ($uc['tipo_curso'] ?? '') === 'M'   ? 'selected' : '' ?>>Mestrado</option>
        <option value="D"   <?= ($uc['tipo_curso'] ?? '') === 'D'   ? 'selected' : '' ?>>Doutoramento</option>
      </select>
    </div>

    <div class="form-group full">
      <label>Áreas Científicas</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;border:1px solid var(--gray-300);border-radius:6px;background:var(--gray-50)">
        <?php foreach ($areas as $a): ?>
        <label style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;background:#fff;border:1px solid var(--gray-200);border-radius:20px;font-weight:400;font-size:12px;cursor:pointer">
          <input type="checkbox" name="areas[]" value="<?= $a['id'] ?>"
                 <?= in_array($a['id'], $areasUC, true) ? 'checked' : '' ?>>
          <span><?= esc($a['sigla']) ?></span>
          <input type="radio" name="area_principal" value="<?= $a['id'] ?>"
                 <?= $principalUC === $a['id'] ? 'checked' : '' ?>
                 title="Marcar como área principal"
                 style="margin-left:4px;transform:scale(0.9)">
        </label>
        <?php endforeach; ?>
        <a href="areas.php" style="align-self:center;font-size:11px">➕ Gerir áreas</a>
      </div>
      <span class="form-hint">Checkbox = pertence à área. Radio = área principal (usada em relatórios agrupados).</span>
    </div>

  </div>

  <hr style="margin:20px 0;border-color:var(--gray-200)">
  <div class="card-title">Horas por Tipo (h/semana)</div>
  <p class="form-hint" style="margin-bottom:10px">
    Valores do plano curricular. São usados como valor por defeito ao criar ocorrências anuais, podendo ser ajustados pontualmente em cada ocorrência.
  </p>
  <div class="form-grid">
    <?php foreach ([
      'h_T'  =>'T – Teóricas',
      'h_TP' =>'TP – Teórico-Práticas',
      'h_L'  =>'L – Laboratoriais',
      'h_Sem'=>'S – Seminários',
      'h_OT' =>'OT – Orient. Tutorial',
    ] as $field => $label): ?>
    <div class="form-group">
      <label><?= $label ?></label>
      <input type="number" name="<?= $field ?>" step="any" data-step="0.25" min="0"
             value="<?= esc((string)($uc[$field] ?? 0)) ?>">
    </div>
    <?php endforeach; ?>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">💾 Guardar</button>
    <a href="ucs.php" class="btn btn-secondary">Cancelar</a>
  </div>
</form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>