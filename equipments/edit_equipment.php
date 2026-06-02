<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once __DIR__ . '/inc/permissions.php';

$userIdNum = (int)preg_replace('/\D/', '', $_SESSION['Code'] ?? '');
$equipId   = (int)($_GET['id'] ?? 0);   // 0 = novo registo
$labPre    = trim($_GET['lab'] ?? ''); // lab pré-seleccionado para novo

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$labsDoUser = getLabsDoUtilizador($pdo, $userIdNum, $isAdmin);

// Validação de acesso
if ($equipId) {
    $stmtEq = $pdo->prepare('SELECT * FROM infodeqb_equipmentdeq WHERE equipment_id = ?');
    $stmtEq->execute([$equipId]);
    $eq = $stmtEq->fetch(PDO::FETCH_ASSOC);
    if (!$eq || !podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq)) {
        header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
    }
} else {
    if (empty($labsDoUser)) {
        header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
    }
    $eq = ['equipment_id'=>0,'Laboratorio'=>$labPre,'Equipamento'=>'','Marca'=>'',
           'Modelo'=>'','AnoAquisicao'=>'','Quantidade'=>1,'Descricao'=>'','Condicoes'=>'',
           'Responsavel'=>'','Tecnico'=>'','Horario'=>'','Amostras'=>'','Operacao'=>'',
           'Custo'=>'','Procedimento'=>'','Observacoes'=>''];
}

$labs = $pdo->query('SELECT lab_id, designacao FROM infodeqb_labs_ensino ORDER BY designacao')
            ->fetchAll(PDO::FETCH_ASSOC);

$flashMsg = ''; $flashType = 'success';

// ── POST ──────────────────────────────────────────────────────────
if (!empty($_POST)) {
    $labNovo = trim($_POST['Laboratorio'] ?? '');

    // Verificar permissão para guardar:
    // 1. Admin global → sempre
    // 2. Admin do lab destino → sim
    // 3. Acesso específico ao equipamento (só edição, não muda lab) → sim
    $canSave = $isAdmin
        || in_array($labNovo, $labsDoUser)
        || ($equipId > 0 && podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq));

    if (!$canSave) {
        $flashMsg  = 'Sem permissão para gerir equipamentos neste laboratório.';
        $flashType = 'danger';
    } else {
        $campos = [
            'Laboratorio'  => $labNovo,
            'Equipamento'  => trim($_POST['Equipamento']  ?? ''),
            'Marca'        => trim($_POST['Marca']        ?? ''),
            'Modelo'       => trim($_POST['Modelo']       ?? ''),
            'AnoAquisicao' => trim($_POST['AnoAquisicao'] ?? ''),
            'Quantidade'   => max(1, (int)($_POST['Quantidade'] ?? 1)),
            'Descricao'    => trim($_POST['Descricao']    ?? ''),
            'Condicoes'    => trim($_POST['Condicoes']    ?? ''),
            'Responsavel'  => trim($_POST['Responsavel']  ?? ''),
            'Tecnico'      => trim($_POST['Tecnico']      ?? ''),
            'Horario'      => trim($_POST['Horario']      ?? ''),
            'Amostras'     => trim($_POST['Amostras']     ?? ''),
            'Operacao'     => trim($_POST['Operacao']     ?? ''),
            'Custo'        => trim($_POST['Custo']        ?? ''),
            'Procedimento' => trim($_POST['Procedimento'] ?? ''),
            'Observacoes'  => trim($_POST['Observacoes']  ?? ''),
        ];

        if (empty($campos['Equipamento'])) {
            $flashMsg = 'O nome do equipamento é obrigatório.';
            $flashType = 'warning';
            $eq = array_merge($eq, $campos);
        } else {
            try {
                $pdo->beginTransaction();

                if ($equipId) {
                    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($campos)));
                    $vals = array_values($campos);
                    $vals[] = $equipId;
                    $pdo->prepare("UPDATE infodeqb_equipmentdeq SET $sets WHERE equipment_id = ?")
                        ->execute($vals);
                    $newId = $equipId;
                } else {
                    $keys = implode(', ', array_keys($campos));
                    $ph   = implode(', ', array_fill(0, count($campos), '?'));
                    $pdo->prepare("INSERT INTO infodeqb_equipmentdeq ($keys) VALUES ($ph)")
                        ->execute(array_values($campos));
                    $newId = (int)$pdo->lastInsertId();
                }

                // ── Upload de imagem ──────────────────────────────
                if (!empty($_FILES['imagem']['tmp_name']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    $fi = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $fi->file($_FILES['imagem']['tmp_name']);
                    $exts = ['image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png',
                             'image/gif'=>'gif','image/webp'=>'webp'];
                    if (isset($exts[$mime]) && $_FILES['imagem']['size'] <= 2097152) {
                        $dir = __DIR__ . '/img/' . $campos['Laboratorio'] . '/';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        // Apagar imagens antigas deste equipamento
                        foreach (glob($dir . $newId . '.*') as $old) unlink($old);
                        move_uploaded_file($_FILES['imagem']['tmp_name'],
                                           $dir . $newId . '.' . $exts[$mime]);
                    }
                }

                // ── Remover imagem ────────────────────────────────
                if (!empty($_POST['remover_imagem'])) {
                    $dir = __DIR__ . '/img/' . $campos['Laboratorio'] . '/';
                    foreach (glob($dir . $newId . '.*') as $old) unlink($old);
                }

                $pdo->commit();
                Database::disconnect();
                $_SESSION['_equip_flash'] = [
                    $equipId ? 'Equipamento actualizado.' : 'Equipamento adicionado.',
                    'success'
                ];
                header('Location: equipment_details.php?id=' . $newId); exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $flashMsg  = 'Erro ao guardar: ' . $e->getMessage();
                $flashType = 'danger';
                $eq = array_merge($eq, $campos);
            }
        }
    }
}

Database::disconnect();

// Imagem actual
$imgUrl = null;
$imgDir = __DIR__ . '/img/' . ($eq['Laboratorio'] ?? '') . '/';
foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
    if (file_exists($imgDir . ($eq['equipment_id'] ?? 0) . '.' . $ext)) {
        $imgUrl = HTTP_DIR . '/infodeqb/equipments/img/'
                . rawurlencode($eq['Laboratorio']) . '/' . $eq['equipment_id'] . '.' . $ext;
        break;
    }
}

$pageTitle = $equipId ? 'Editar Equipamento' : 'Novo Equipamento';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto">
    <i class="fas fa-<?= $equipId ? 'edit' : 'plus-circle' ?> fa-sm me-2 text-muted"></i>
    <?= $equipId ? t('EDIT') . ' — ' . htmlspecialchars($eq['Equipamento'] ?? '') : t('EQUIP_NEW') ?>
  </h1>
  <a href="<?= $equipId ? 'equipment_details.php?id=' . $equipId : 'index.php' ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-times me-1"></i><?= t('CANCEL') ?>
  </a>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> mb-3" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

  <div class="row">
    <div class="col-md-8">

      <!-- Identificação -->
      <div class="card shadow-sm mb-3">
        <div class="card-header py-2"><strong>Identificação</strong></div>
        <div class="card-body">
          <div class="form-group">
            <label class="small font-weight-bold"><?= t('EQUIP_LAB') ?> <span class="text-danger">*</span></label>
            <select name="Laboratorio" class="form-control" required
                    <?= (!$isAdmin && $equipId) ? 'disabled' : '' ?>>
              <option value="">— seleccione —</option>
              <?php foreach ($labs as $l):
                $selected = ($eq['Laboratorio'] === $l['lab_id']);
                $disabled = !$isAdmin && !in_array($l['lab_id'], $labsDoUser);
                if ($disabled) continue; ?>
              <option value="<?= htmlspecialchars($l['lab_id']) ?>"
                      <?= $selected ? 'selected' : '' ?>>
                <?= htmlspecialchars($l['designacao']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (!$isAdmin && $equipId): ?>
            <input type="hidden" name="Laboratorio" value="<?= htmlspecialchars($eq['Laboratorio']) ?>">
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="small font-weight-bold"><?= t('EQUIP_NAME') ?> <span class="text-danger">*</span></label>
            <input type="text" name="Equipamento" class="form-control" required
                   value="<?= htmlspecialchars($eq['Equipamento'] ?? '') ?>">
          </div>
          <div class="form-row">
            <div class="col-md-5 form-group">
              <label class="small font-weight-bold">Marca</label>
              <input type="text" name="Marca" class="form-control"
                     value="<?= htmlspecialchars($eq['Marca'] ?? '') ?>">
            </div>
            <div class="col-md-5 form-group">
              <label class="small font-weight-bold">Modelo</label>
              <input type="text" name="Modelo" class="form-control"
                     value="<?= htmlspecialchars($eq['Modelo'] ?? '') ?>">
            </div>
            <div class="col-md-2 form-group">
              <label class="small font-weight-bold">Qtd.</label>
              <input type="number" name="Quantidade" class="form-control" min="1"
                     value="<?= (int)($eq['Quantidade'] ?? 1) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-3 form-group">
              <label class="small font-weight-bold">Ano aquisição</label>
              <input type="text" name="AnoAquisicao" class="form-control" maxlength="11"
                     placeholder="ex: 2022"
                     value="<?= htmlspecialchars($eq['AnoAquisicao'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group">
              <label class="small font-weight-bold">Responsável</label>
              <input type="text" name="Responsavel" class="form-control"
                     value="<?= htmlspecialchars($eq['Responsavel'] ?? '') ?>">
            </div>
            <div class="col-md-5 form-group">
              <label class="small font-weight-bold">Técnico</label>
              <input type="text" name="Tecnico" class="form-control"
                     value="<?= htmlspecialchars($eq['Tecnico'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Detalhes operacionais -->
      <div class="card shadow-sm mb-3">
        <div class="card-header py-2"><strong>Detalhes operacionais</strong></div>
        <div class="card-body">
          <?php foreach ([
            'Descricao'    => ['Descrição',               2],
            'Condicoes'    => ['Condições de utilização',  3],
            'Horario'      => ['Horário',                  2],
            'Amostras'     => ['Tipo de amostras',         2],
            'Operacao'     => ['Operação',                 3],
            'Custo'        => ['Custo',                    2],
            'Procedimento' => ['Procedimento',             3],
            'Observacoes'  => ['Observações',              2],
          ] as $field => [$label, $rows]): ?>
          <div class="form-group">
            <label class="small font-weight-bold"><?= htmlspecialchars($label) ?></label>
            <textarea name="<?= $field ?>" class="form-control" rows="<?= $rows ?>"
                      style="font-size:.85rem"><?= htmlspecialchars($eq[$field] ?? '') ?></textarea>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /col-md-8 -->

    <!-- Imagem -->
    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header py-2"><strong>Imagem</strong></div>
        <div class="card-body">
          <?php if ($imgUrl): ?>
          <img src="<?= htmlspecialchars($imgUrl) ?>"
               class="img-fluid mb-2 rounded" style="max-height:180px;object-fit:contain;width:100%"
               alt="">
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="remover_imagem" id="remImg" value="1">
            <label class="form-check-label small text-danger" for="remImg"><?= t('EQUIP_REMOVE_IMG') ?></label>
          </div>
          <?php endif; ?>
          <div class="form-group mb-0">
            <label class="small font-weight-bold"><?= $imgUrl ? 'Substituir imagem' : 'Upload de imagem' ?></label>
            <input type="file" name="imagem" class="form-control-file form-control-sm"
                   accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="text-muted"><?= t('EQUIP_IMG_HELP') ?></small>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /row -->

  <div class="d-flex justify-content-end mb-4" style="gap:8px">
    <a href="<?= $equipId ? 'equipment_details.php?id='.$equipId : 'index.php' ?>"
       class="btn btn-outline-secondary"><?= t('CANCEL') ?></a>
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i><?= $equipId ? t('SAVE_CHANGES') : t('EQUIP_ADD') ?>
    </button>
  </div>

</form>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
