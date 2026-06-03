<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';

require_once ROOT_DIR . '/infodeqb/inc/admins.php';
if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsWater))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$flashMsg  = '';
$flashType = 'success';

// ── POST handlers ─────────────────────────────────────────────────
if (!empty($_POST)) {
    $acao = $_POST['_acao'] ?? '';
    try {
        if ($acao === 'editar_user') {
            $pdo->prepare('UPDATE infodeqb_water_users SET user=?, idresp=? WHERE userid=?')
                ->execute([
                    trim($_POST['nome_user']),
                    (int)$_POST['idresp'],
                    (int)$_POST['userid'],
                ]);
            $flashMsg = 'Utilizador actualizado.';

        } elseif ($acao === 'desativar_user') {
            $pdo->prepare('UPDATE infodeqb_water_users SET ativo=0 WHERE userid=?')
                ->execute([(int)$_POST['userid']]);
            $flashMsg = 'Utilizador desactivado — histórico preservado.';

        } elseif ($acao === 'reativar_user') {
            $pdo->prepare('UPDATE infodeqb_water_users SET ativo=1 WHERE userid=?')
                ->execute([(int)$_POST['userid']]);
            $flashMsg = 'Utilizador reactivado.';

        } elseif ($acao === 'apagar_user') {
            $uid = (int)$_POST['userid'];
            $chk = $pdo->prepare('SELECT COUNT(*) FROM infodeqb_water_ultrapure_record WHERE user=?');
            $chk->execute([$uid]);
            $nRec = (int)$chk->fetchColumn();
            if ($nRec > 0) {
                $flashMsg  = 'Não é possível remover: utilizador tem ' . $nRec . ' registo(s). Use "Desativar".';
                $flashType = 'danger';
            } else {
                $pdo->prepare('DELETE FROM infodeqb_water_users WHERE userid=?')->execute([$uid]);
                $flashMsg = 'Utilizador eliminado definitivamente.';
            }

        } elseif ($acao === 'editar_resp') {
            $pdo->prepare('UPDATE infodeqb_water_resp SET nome=? WHERE id=?')
                ->execute([trim($_POST['nome_resp']), (int)$_POST['respid']]);
            $flashMsg = 'Responsável actualizado.';

        } elseif ($acao === 'apagar_resp') {
            $pdo->prepare('DELETE FROM infodeqb_water_resp WHERE id=?')
                ->execute([(int)$_POST['respid']]);
            $flashMsg = 'Responsável removido.';
        }
    } catch (Exception $e) {
        $flashMsg  = 'Erro: ' . $e->getMessage();
        $flashType = 'danger';
    }
    $_SESSION['_water_edit_flash'] = [$flashMsg, $flashType];
    header('Location: edit.php');
    exit;
}

if (isset($_SESSION['_water_edit_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_water_edit_flash'];
    unset($_SESSION['_water_edit_flash']);
}

// ── Dados ─────────────────────────────────────────────────────────
$resps = $pdo->query(
    'SELECT r.id, r.nome,
            (SELECT COUNT(*) FROM infodeqb_water_users u WHERE u.idresp = r.id) AS n_users,
            (SELECT COUNT(*) FROM infodeqb_water_ultrapure_record rec WHERE rec.resp = r.id) AS n_registos
     FROM infodeqb_water_resp r
     ORDER BY r.nome'
)->fetchAll(PDO::FETCH_ASSOC);

$users = $pdo->query(
    'SELECT u.userid, u.user, u.idresp, u.ativo, r.nome AS resp_nome,
            (SELECT COUNT(*) FROM infodeqb_water_ultrapure_record rec WHERE rec.user = u.userid) AS n_registos
     FROM infodeqb_water_users u
     JOIN infodeqb_water_resp r ON r.id = u.idresp
     ORDER BY u.ativo DESC, u.user'   /* activos primeiro */
)->fetchAll(PDO::FETCH_ASSOC);

$optResp = '';
foreach ($resps as $r) {
    $optResp .= '<option value="' . (int)$r['id'] . '">'
              . htmlspecialchars($r['nome']) . '</option>';
}

Database::disconnect();

$pageTitle = 'Gerir Utilizadores — Água';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto">
    <i class="fas fa-users-cog fa-sm me-2 text-muted"></i>
    Gerir responsáveis e utilizadores
  </h1>
  <a href="index.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> <?= t('BACK') ?>
  </a>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3"
     role="alert" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<div class="row">

  <!-- ── Responsáveis ──────────────────────────────────────────── -->
  <div class="col-md-5">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <strong>Responsáveis</strong>
        <span class="badge badge-secondary ms-1"><?= count($resps) ?></span>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" id="tblResps">
          <thead class="">
            <tr>
              <th>Nome</th>
              <th class="text-center" style="width:5em">Utiliz.</th>
              <th class="text-center" style="width:5em">Registos</th>
              <th style="width:4em"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($resps as $r): ?>
            <tr>
              <td class="align-middle"><?= htmlspecialchars($r['nome']) ?></td>
              <td class="align-middle text-center"><?= (int)$r['n_users'] ?></td>
              <td class="align-middle text-center"><?= number_format((int)$r['n_registos']) ?></td>
              <td class="align-middle text-center text-nowrap">
                <a href="#" class="btn-edit-resp text-info me-1"
                   data-id="<?= (int)$r['id'] ?>"
                   data-nome="<?= htmlspecialchars($r['nome']) ?>"
                   data-bs-toggle="modal" data-bs-target="#modalEditResp"
                   title="Editar">
                  <i class="fas fa-edit fa-xs"></i>
                </a>
                <?php if ((int)$r['n_registos'] === 0 && (int)$r['n_users'] === 0): ?>
                <a href="#" class="btn-del-resp text-danger"
                   data-id="<?= (int)$r['id'] ?>"
                   data-nome="<?= htmlspecialchars($r['nome']) ?>"
                   data-bs-toggle="modal" data-bs-target="#modalDelResp"
                   title="Remover">
                  <i class="fas fa-trash fa-xs"></i>
                </a>
                <?php else: ?>
                <span class="text-muted" title="Tem registos ou utilizadores associados">
                  <i class="fas fa-lock fa-xs"></i>
                </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php
  // Agrupar utilizadores por responsável (necessário para o select de filtro)
  $usersByResp = [];
  foreach ($users as $u) $usersByResp[$u['resp_nome']][] = $u;
  ksort($usersByResp);
  ?>
  <!-- ── Utilizadores (agrupados por responsável) ─────────────── -->
  <div class="col-md-7">
    <div class="card mb-4 shadow-sm">
      <div class="card-header py-2">
        <div class="d-flex align-items-center mb-2">
          <strong class="mr-auto">Utilizadores</strong>
          <span class="badge badge-secondary me-2"><?= count($users) ?></span>
          <button type="button" class="btn btn-xs btn-outline-secondary" id="btnToggleUsers"
                  data-state="expanded">
            <i class="fas fa-compress-alt fa-xs me-1"></i>Colapsar
          </button>
        </div>
        <div class="d-flex" style="gap:6px">
          <select id="filterResp" class="form-control form-control-sm" style="flex:1">
            <option value="">— todos os responsáveis —</option>
            <?php foreach (array_keys($usersByResp ?? []) as $rn): ?>
            <option value="<?= htmlspecialchars(strtolower($rn)) ?>"><?= htmlspecialchars($rn) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="input-group input-group-sm" style="flex:1">
            <div class="input-group-prepend">
              <span class="input-group-text bg-white border-right-0 px-2">
                <i class="fas fa-search fa-xs text-muted"></i>
              </span>
            </div>
            <input type="text" id="searchUsers" class="form-control border-left-0"
                   placeholder="Utilizador…" autocomplete="off">
          </div>
        </div>
      </div>
      <div class="card-body p-0" id="tblUsers">
        <?php $ri = 0; ?>
        <?php foreach ($usersByResp as $respNome => $respUsers):
          $colId = 'resp-users-' . $ri++;
        ?>
        <!-- Cabeçalho colapsável -->
        <div class="user-group">
        <div class="d-flex align-items-center px-3 py-2"
             style="background:#f8f9fa;border-bottom:1px solid #dee2e6;cursor:pointer"
             data-bs-toggle="collapse" data-bs-target="#<?= $colId ?>"
             data-resp-nome="<?= htmlspecialchars(strtolower($respNome)) ?>">
          <small class="font-weight-bold text-secondary me-auto">
            <i class="fas fa-chevron-down fa-xs me-1 resp-chevron" style="transition:transform .15s"></i>
            <i class="fas fa-user-tie fa-xs me-1"></i><?= htmlspecialchars($respNome) ?>
          </small>
          <span class="badge badge-secondary" style="font-size:.7rem"><?= count($respUsers) ?></span>
        </div>
        <!-- Conteúdo em 3 colunas -->
        <div class="collapse show" id="<?= $colId ?>">
          <div class="px-3 py-2" style="border-bottom:1px solid #dee2e6">
            <div class="row" style="font-size:.82rem">
            <?php foreach ($respUsers as $u): ?>
              <?php
              $uid     = (int)$u['userid'];
              $nome_u  = htmlspecialchars($u['user'], ENT_QUOTES);
              $ativo   = (int)$u['ativo'];
              $nRec    = (int)$u['n_registos'];
              $dimmed  = $ativo ? '' : 'opacity:.45;';
              ?>
              <div class="col-md-4 col-sm-6 d-flex align-items-center py-1 user-item"
                   data-nome="<?= strtolower(htmlspecialchars($u['user'])) ?>">
                <span class="mr-auto text-truncate" style="max-width:120px;<?= $dimmed ?>"
                      title="<?= $nome_u ?><?= !$ativo ? ' (inactivo)' : '' ?>">
                  <?php if (!$ativo): ?>
                  <i class="fas fa-ban fa-xs text-muted me-1" title="Inactivo"></i>
                  <?php endif; ?>
                  <?= htmlspecialchars($u['user']) ?>
                </span>
                <?php if ($ativo): ?>
                  <a href="#" class="btn-edit-user text-info mx-1"
                     data-id="<?= $uid ?>" data-nome="<?= $nome_u ?>" data-resp="<?= (int)$u['idresp'] ?>"
                     data-bs-toggle="modal" data-bs-target="#modalEditUser" title="Editar">
                    <i class="fas fa-edit fa-xs"></i></a>
                  <?php if ($nRec === 0): ?>
                  <a href="#" class="btn-del-user text-danger"
                     data-id="<?= $uid ?>" data-nome="<?= $nome_u ?>"
                     data-bs-toggle="modal" data-bs-target="#modalDelUser" title="Eliminar">
                    <i class="fas fa-trash fa-xs"></i></a>
                  <?php else: ?>
                  <form method="post" class="d-inline ms-1">
                    <input type="hidden" name="_acao"  value="desativar_user">
                    <input type="hidden" name="userid" value="<?= $uid ?>">
                    <button type="submit" class="btn btn-xs btn-outline-warning"
                            title="Desativar (<?= $nRec ?> registo(s) preservados)"
                            onclick="return confirm('Desativar <?= $nome_u ?>?\nO histórico fica preservado.')">
                      <i class="fas fa-user-slash fa-xs"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                <?php else: /* inactivo */ ?>
                  <form method="post" class="d-inline mx-1">
                    <input type="hidden" name="_acao"  value="reativar_user">
                    <input type="hidden" name="userid" value="<?= $uid ?>">
                    <button type="submit" class="btn btn-xs btn-outline-success"
                            title="Reactivar utilizador">
                      <i class="fas fa-user-check fa-xs"></i>
                    </button>
                  </form>
                  <?php if ($nRec === 0): ?>
                  <a href="#" class="btn-del-user text-danger"
                     data-id="<?= $uid ?>" data-nome="<?= $nome_u ?>"
                     data-bs-toggle="modal" data-bs-target="#modalDelUser" title="Eliminar definitivamente">
                    <i class="fas fa-trash fa-xs"></i></a>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          </div>
        </div><!-- /.collapse -->
        </div><!-- /.user-group -->
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- Modal: editar responsável -->
<div class="modal fade" id="modalEditResp" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="editar_resp">
        <input type="hidden" name="respid" id="editRespId">
        <div class="modal-header">
          <h5 class="modal-title">Editar responsável</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-0">
            <label class="font-weight-bold">Nome</label>
            <input type="text" name="nome_resp" id="editRespNome"
                   class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('SAVE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: remover responsável -->
<div class="modal fade" id="modalDelResp" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="apagar_resp">
        <input type="hidden" name="respid" id="delRespId">
        <div class="modal-header">
          <h5 class="modal-title">Remover responsável</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p><?= t('REMOVE') ?> <strong id="delRespNome"></strong>?</p>
          <p class="text-danger small mb-0"><?= t('IRREVERSIBLE') ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-danger"><?= t('REMOVE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: editar utilizador -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="editar_user">
        <input type="hidden" name="userid" id="editUserId">
        <div class="modal-header">
          <h5 class="modal-title">Editar utilizador</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="font-weight-bold">Nome</label>
            <input type="text" name="nome_user" id="editUserNome"
                   class="form-control" required>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold">Responsável</label>
            <select name="idresp" id="editUserResp" class="form-control" required>
              <?= $optResp ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('SAVE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: remover utilizador -->
<div class="modal fade" id="modalDelUser" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="_acao"  value="apagar_user">
      <input type="hidden" name="userid" id="delUserId">
      <div class="modal-header">
        <h5 class="modal-title">Remover utilizador</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Remover <strong id="delUserNome"></strong>?</p>
        <p class="text-danger small mb-0"><?= t('IRREVERSIBLE') ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
        <button type="submit" class="btn btn-danger"><?= t('REMOVE') ?></button>
      </div>
    </form>
  </div></div>
</div>

<script>
$(document).ready(function () {
    $('#tblResps').DataTable({
        paging: false,
        order: [[0, 'asc']],
        language: { search: '', searchPlaceholder: 'Pesquisar…', info: '_TOTAL_ responsáveis', infoFiltered: '(de _MAX_)' },
        dom: '<"px-3 pt-3 pb-2"f>t',
        columnDefs: [{ targets: [1,2,3], orderable: false }]
    });
    // utilizadores agrupados por responsável — sem DataTable
});

$(document).on('click', '.btn-edit-resp', function () {
    $('#editRespId').val($(this).data('id'));
    $('#editRespNome').val($(this).data('nome'));
});
$(document).on('click', '.btn-del-resp', function () {
    $('#delRespId').val($(this).data('id'));
    $('#delRespNome').text($(this).data('nome'));
});
$(document).on('click', '.btn-edit-user', function () {
    $('#editUserId').val($(this).data('id'));
    $('#editUserNome').val($(this).data('nome'));
    $('#editUserResp').val($(this).data('resp'));
});
$(document).on('click', '.btn-del-user', function () {
    $('#delUserId').val($(this).data('id'));
    $('#delUserNome').text($(this).data('nome'));
});

// Chevron ao colapsar/expandir
$(document).on('hide.bs.collapse', '.collapse', function () {
    $(this).prev().find('.resp-chevron').css('transform', 'rotate(-90deg)');
}).on('show.bs.collapse', '.collapse', function () {
    $(this).prev().find('.resp-chevron').css('transform', 'rotate(0deg)');
});

// Filtros de responsável + utilizador
function applyUserFilters() {
    var resp = $('#filterResp').val();         // nome resp em lowercase ou ''
    var user = $('#searchUsers').val().toLowerCase().trim();

    $('#tblUsers .user-group').each(function () {
        var $grp     = $(this);
        var grpNome  = $grp.find('[data-resp-nome]').data('resp-nome') || '';
        var respMatch = !resp || grpNome === resp;

        if (!respMatch) { $grp.hide(); return; }

        var anyUser = false;
        $grp.find('.user-item').each(function () {
            var nome  = $(this).data('nome') || '';
            var match = !user || nome.indexOf(user) > -1;
            $(this).toggle(match);
            if (match) anyUser = true;
        });

        $grp.toggle(anyUser || !user);
        if ((resp || user) && anyUser) $grp.find('.collapse').collapse('show');
    });
}
$('#filterResp, #searchUsers').on('change keyup', applyUserFilters);

// Colapsar / Expandir tudo
$('#btnToggleUsers').on('click', function () {
    var $btn   = $(this);
    var expand = $btn.data('state') === 'expanded';
    $('#tblUsers .user-group:visible .collapse').collapse(expand ? 'hide' : 'show');
    $btn.data('state', expand ? 'collapsed' : 'expanded')
        .html(expand
            ? '<i class="fas fa-expand-alt fa-xs me-1"></i>Expandir'
            : '<i class="fas fa-compress-alt fa-xs me-1"></i>Colapsar');
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
