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
if (isset($_SESSION['_equip_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_equip_flash'];
    unset($_SESSION['_equip_flash']);
}

// ── POST ──────────────────────────────────────────────────────────
if (!empty($_POST)) {
    $acao = $_POST['_acao'] ?? '';

    // ── Upload de documento ───────────────────────────────────────
    if ($acao === 'upload_doc' && $equipId > 0) {
        $podeEditar = podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq);
        if ($podeEditar && !empty($_FILES['doc_file']['tmp_name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['pdf','doc','docx','xls','xlsx','txt'];
            $origExt     = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
            if (in_array($origExt, $allowedExts) && $_FILES['doc_file']['size'] <= 10485760) {
                $descricao = trim($_POST['doc_descricao'] ?? '');
                $lab       = $eq['Laboratorio'];
                $ins       = $pdo->prepare(
                    'INSERT INTO infodeqb_equipment_docs (equipment_id, laboratorio, filename, descricao, uploaded_by)
                     VALUES (?,?,?,?,?)'
                );
                $ins->execute([$equipId, $lab, 'tmp', $descricao ?: null, $userIdNum]);
                $docId    = (int)$pdo->lastInsertId();
                $filename = 'doc_' . $equipId . '_' . $docId . '.' . $origExt;
                $dir      = __DIR__ . '/img/' . $lab . '/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $dir . $filename)) {
                    $pdo->prepare('UPDATE infodeqb_equipment_docs SET filename=? WHERE id=?')
                        ->execute([$filename, $docId]);
                    $_SESSION['_equip_flash'] = [t('EQUIP_DOCS_UPLOADED'), 'success'];
                } else {
                    $pdo->prepare('DELETE FROM infodeqb_equipment_docs WHERE id=?')->execute([$docId]);
                    $_SESSION['_equip_flash'] = [t('EQUIP_DOCS_ERR_UPLOAD'), 'danger'];
                }
            } else {
                $_SESSION['_equip_flash'] = [
                    $_FILES['doc_file']['size'] > 10485760 ? t('EQUIP_DOCS_ERR_SIZE') : t('EQUIP_DOCS_ERR_TYPE'),
                    'warning'
                ];
            }
        }
        Database::disconnect();
        header('Location: edit_equipment.php?id=' . $equipId); exit;
    }

    // ── Remover documento ─────────────────────────────────────────
    if (strpos($acao, 'delete_doc_') === 0 && $equipId > 0) {
        $podeEditar = podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq);
        if ($podeEditar) {
            $docId = (int)substr($acao, 11);
            $sDoc  = $pdo->prepare('SELECT * FROM infodeqb_equipment_docs WHERE id=? AND equipment_id=?');
            $sDoc->execute([$docId, $equipId]);
            $doc   = $sDoc->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                $filePath = __DIR__ . '/img/' . $doc['laboratorio'] . '/' . $doc['filename'];
                if (file_exists($filePath)) unlink($filePath);
                $pdo->prepare('DELETE FROM infodeqb_equipment_docs WHERE id=?')->execute([$docId]);
                $_SESSION['_equip_flash'] = [t('EQUIP_DOCS_DELETED'), 'success'];
            }
        }
        Database::disconnect();
        header('Location: edit_equipment.php?id=' . $equipId); exit;
    }

    // ── Formulário principal ──────────────────────────────────────
    $labNovo = trim($_POST['Laboratorio'] ?? '');

    // Verificar permissão para guardar:
    // 1. Admin global → sempre
    // 2. Admin do lab destino → sim
    // 3. Acesso específico ao equipamento (só edição, não muda lab) → sim
    $canSave = $isAdmin
        || in_array($labNovo, $labsDoUser)
        || ($equipId > 0 && podeGerirEquipamento($pdo, $userIdNum, $isAdmin, $eq));

    if (!$canSave) {
        $flashMsg  = t('EQUIP_NO_PERM');
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
            $flashMsg = t('EQUIP_NAME_REQUIRED');
            $flashType = 'warning';
            $eq = array_merge($eq, $campos);
        } else {
            try {
                $pdo->beginTransaction();

                if ($equipId) {
                    $sets = implode(', ', array_map(function($k) { return "$k = ?"; }, array_keys($campos)));
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
                    $equipId ? t('SUCCESS_UPDATED') : t('SUCCESS_ADDED'),
                    'success'
                ];
                header('Location: equipment_details.php?id=' . $newId); exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $flashMsg  = t('ERROR_SAVE') . ': ' . $e->getMessage();
                $flashType = 'danger';
                $eq = array_merge($eq, $campos);
            }
        }
    }
}

// Documentos (só para equipamentos existentes)
$docsList = [];
if ($equipId > 0) {
    $sDocs = $pdo->prepare('SELECT * FROM infodeqb_equipment_docs WHERE equipment_id=? ORDER BY id ASC');
    $sDocs->execute([$equipId]);
    $docsList = $sDocs->fetchAll(PDO::FETCH_ASSOC);
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

function docIcon($ext) {
    $map = [
        'pdf'  => 'fa-file-pdf text-danger',
        'doc'  => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'xls'  => 'fa-file-excel text-success',
        'xlsx' => 'fa-file-excel text-success',
        'txt'  => 'fa-file-alt text-muted',
    ];
    return isset($map[$ext]) ? $map[$ext] : 'fa-file text-muted';
}

$pageTitle = $equipId ? t('EQUIP_EDIT') : t('EQUIP_NEW');
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

<style>
.eq-form .card-body          { padding: .65rem .85rem !important; }
.eq-form .card-header        { padding: .35rem .85rem !important; }
.eq-form .form-group         { margin-bottom: .35rem !important; }
.eq-form label.small         { margin-bottom: .1rem; display:block; line-height:1.3; }
.eq-form .form-control,
.eq-form select.form-control { font-size: .82rem !important; padding: .25rem .45rem !important; height:auto !important; }
.eq-form textarea.form-control{ font-size:.82rem !important; padding:.25rem .45rem !important; resize:vertical; overflow:hidden; }
.eq-form .form-row           { margin-right:-.35rem; margin-left:-.35rem; }
.eq-form .form-row > [class*=col-] { padding-right:.35rem; padding-left:.35rem; }
</style>

<form method="post" enctype="multipart/form-data" class="eq-form">

  <div class="row">
    <div class="col-md-8">

      <!-- Identificação -->
      <div class="card shadow-sm mb-3">
        <div class="card-header py-2"><strong><?= t('EQUIP_IDENTIFICATION') ?></strong></div>
        <div class="card-body">
          <div class="form-row">
            <div class="col-md-4 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_LAB') ?> <span class="text-danger">*</span></label>
              <select name="Laboratorio" class="form-control" required
                      <?= (!$isAdmin && $equipId) ? 'disabled' : '' ?>>
                <option value="">— <?= t('SELECT_OPTION') ?> —</option>
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
            <div class="col-md-8 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_NAME') ?> <span class="text-danger">*</span></label>
              <input type="text" name="Equipamento" class="form-control" required
                     value="<?= htmlspecialchars($eq['Equipamento'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-5 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_BRAND') ?></label>
              <input type="text" name="Marca" class="form-control"
                     value="<?= htmlspecialchars($eq['Marca'] ?? '') ?>">
            </div>
            <div class="col-md-5 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_MODEL') ?></label>
              <input type="text" name="Modelo" class="form-control"
                     value="<?= htmlspecialchars($eq['Modelo'] ?? '') ?>">
            </div>
            <div class="col-md-2 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_QTY') ?></label>
              <input type="number" name="Quantidade" class="form-control" min="1"
                     value="<?= (int)($eq['Quantidade'] ?? 1) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-3 form-group mb-0">
              <label class="small font-weight-bold"><?= t('EQUIP_ACQ_YEAR') ?></label>
              <input type="text" name="AnoAquisicao" class="form-control" maxlength="11"
                     placeholder="ex: 2022"
                     value="<?= htmlspecialchars($eq['AnoAquisicao'] ?? '') ?>">
            </div>
            <div class="col-md-4 form-group mb-0">
              <label class="small font-weight-bold"><?= t('RESPONSIBLE') ?></label>
              <input type="text" name="Responsavel" class="form-control"
                     value="<?= htmlspecialchars($eq['Responsavel'] ?? '') ?>">
            </div>
            <div class="col-md-5 form-group mb-0">
              <label class="small font-weight-bold"><?= t('EQUIP_TECH') ?></label>
              <input type="text" name="Tecnico" class="form-control"
                     value="<?= htmlspecialchars($eq['Tecnico'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Detalhes operacionais -->
      <div class="card shadow-sm mb-3">
        <div class="card-header py-2"><strong><?= t('EQUIP_OP_DETAILS') ?></strong></div>
        <div class="card-body">
          <div class="form-row">
            <div class="col-12 form-group mb-2">
              <label class="small font-weight-bold"><?= t('DESCRIPTION') ?></label>
              <textarea name="Descricao" class="form-control eq-auto" rows="3"><?= htmlspecialchars($eq['Descricao'] ?? '') ?></textarea>
            </div>
            <div class="col-12 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_CONDITIONS') ?></label>
              <textarea name="Condicoes" class="form-control eq-auto" rows="3"><?= htmlspecialchars($eq['Condicoes'] ?? '') ?></textarea>
            </div>
            <div class="col-md-8 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_OPERATION') ?></label>
              <textarea name="Operacao" class="form-control eq-auto" rows="2"><?= htmlspecialchars($eq['Operacao'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_COST') ?></label>
              <textarea name="Custo" class="form-control eq-auto" rows="2"><?= htmlspecialchars($eq['Custo'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_SCHEDULE') ?></label>
              <textarea name="Horario" class="form-control eq-auto" rows="1"><?= htmlspecialchars($eq['Horario'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_SAMPLES') ?></label>
              <textarea name="Amostras" class="form-control eq-auto" rows="1"><?= htmlspecialchars($eq['Amostras'] ?? '') ?></textarea>
            </div>
            <div class="col-12 form-group mb-2">
              <label class="small font-weight-bold"><?= t('EQUIP_PROCEDURE') ?></label>
              <textarea name="Procedimento" class="form-control eq-auto" rows="3"><?= htmlspecialchars($eq['Procedimento'] ?? '') ?></textarea>
            </div>
            <div class="col-12 form-group mb-0">
              <label class="small font-weight-bold"><?= t('OBSERVATIONS') ?></label>
              <textarea name="Observacoes" class="form-control eq-auto" rows="2"><?= htmlspecialchars($eq['Observacoes'] ?? '') ?></textarea>
            </div>
          </div>
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
            <label class="small font-weight-bold"><?= $imgUrl ? t('EQUIP_REPLACE_IMG') : t('EQUIP_IMAGE') ?></label>
            <input type="file" name="imagem" class="form-control-file form-control-sm"
                   accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="text-muted"><?= t('EQUIP_IMG_HELP') ?></small>
          </div>
        </div>
      </div>

      <?php if ($equipId > 0): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
          <i class="fas fa-folder-open text-secondary"></i>
          <strong><?= t('EQUIP_DOCS_TITLE') ?></strong>
          <?php if ($docsList): ?>
          <span class="badge badge-secondary ml-1" style="font-size:.72rem"><?= count($docsList) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body py-2" style="font-size:.83rem">

          <?php if ($docsList): ?>
          <ul class="list-unstyled mb-2">
            <?php foreach ($docsList as $i => $doc):
              $dExt   = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
              $dIcon  = docIcon($dExt);
              $dUrl   = HTTP_DIR . '/infodeqb/equipments/img/'
                      . rawurlencode($doc['laboratorio']) . '/' . rawurlencode($doc['filename']);
              $dLabel = $doc['descricao'] ?: $doc['filename'];
            ?>
            <li class="d-flex align-items-center py-1 <?= $i < count($docsList) - 1 ? 'border-bottom' : '' ?>">
              <i class="fas <?= $dIcon ?> mr-2 flex-shrink-0" style="width:1.1em;text-align:center"></i>
              <div class="flex-grow-1 min-width-0">
                <a href="<?= htmlspecialchars($dUrl) ?>" target="_blank" rel="noopener"
                   class="d-block text-truncate" title="<?= htmlspecialchars($dLabel) ?>">
                  <?= htmlspecialchars($dLabel) ?>
                </a>
                <?php if ($doc['descricao']): ?>
                <div class="text-muted" style="font-size:.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  <?= htmlspecialchars($doc['filename']) ?>
                </div>
                <?php endif; ?>
              </div>
              <button type="submit" name="_acao"
                      value="delete_doc_<?= (int)$doc['id'] ?>"
                      class="btn btn-xs btn-outline-danger ml-1 flex-shrink-0"
                      onclick="return confirm(<?= json_encode(t('EQUIP_DOCS_CONFIRM_DEL')) ?>)">
                <i class="fas fa-times fa-xs"></i>
              </button>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <p class="text-muted mb-2"><?= t('EQUIP_DOCS_NONE') ?></p>
          <?php endif; ?>

          <div class="<?= $docsList ? 'border-top pt-2' : '' ?>">
            <div class="form-group mb-1">
              <label class="small font-weight-bold mb-0"><?= t('EQUIP_DOCS_DESC') ?></label>
              <input type="text" name="doc_descricao" class="form-control form-control-sm"
                     placeholder="<?= htmlspecialchars(t('EQUIP_DOCS_DESC_PH')) ?>"
                     maxlength="300">
            </div>
            <div class="form-group mb-1">
              <label class="small font-weight-bold mb-0"><?= t('EQUIP_DOCS_FILE') ?></label>
              <input type="file" name="doc_file" class="form-control-file form-control-sm"
                     accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">PDF, Word, Excel, TXT — máx. 10&nbsp;MB</small>
              <button type="submit" name="_acao" value="upload_doc"
                      class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-upload mr-1"></i><?= t('EQUIP_DOCS_UPLOAD') ?>
              </button>
            </div>
          </div>

        </div>
      </div>
      <?php endif; ?>
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

<script>
(function () {
  function fit(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }
  var els = document.querySelectorAll('.eq-auto');
  for (var i = 0; i < els.length; i++) {
    fit(els[i]);
    els[i].addEventListener('input', function () { fit(this); });
  }
})();
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
