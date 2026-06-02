<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';

require_once ROOT_DIR . '/infodeqb/inc/admins.php';
$isAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsWater);

if (!$isAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── POST: inserir / editar / apagar / novo responsável / novo utilizador ──
if (!empty($_POST)) {
    $acao = $_POST['_acao'] ?? '';
    $flashMsg  = '';
    $flashType = 'success';

    try {
        if ($acao === 'inserir') {
            $pdo->prepare(
                'INSERT INTO infodeqb_water_ultrapure_record (data, resp, user, quantity) VALUES (?,?,?,?)'
            )->execute([
                $_POST['data'],
                (int)$_POST['resp'],
                (int)$_POST['userid'],
                (float)str_replace(',', '.', $_POST['qtd']),
            ]);
            $flashMsg = 'Registo inserido com sucesso.';
            // Redireciona para o ano da data inserida
            $_SESSION['_water_flash'] = [$flashMsg, 'success'];
            header('Location: index.php?ano=' . (int)substr($_POST['data'], 0, 4));
            exit;

        } elseif ($acao === 'editar') {
            $pdo->prepare(
                'UPDATE infodeqb_water_ultrapure_record SET data=?, resp=?, user=?, quantity=? WHERE autoid=?'
            )->execute([
                $_POST['editdata'],
                (int)$_POST['editresp'],
                (int)$_POST['edituser'],
                (float)str_replace(',', '.', $_POST['editqtd']),
                (int)$_POST['editid'],
            ]);
            $flashMsg = 'Registo actualizado.';

        } elseif ($acao === 'apagar') {
            $pdo->prepare('DELETE FROM infodeqb_water_ultrapure_record WHERE autoid=?')
                ->execute([(int)$_POST['deleteid']]);
            $flashMsg = 'Registo apagado.';

        } elseif ($acao === 'novo_resp') {
            $pdo->prepare('INSERT INTO infodeqb_water_resp (nome) VALUES (?)')
                ->execute([trim($_POST['nome_resp'])]);
            $flashMsg = 'Responsável adicionado.';

        } elseif ($acao === 'novo_user') {
            $pdo->prepare('INSERT INTO infodeqb_water_users (user, idresp) VALUES (?,?)')
                ->execute([trim($_POST['nome_user']), (int)$_POST['idresp_user']]);
            $flashMsg = 'Utilizador adicionado.';
        }

    } catch (Exception $e) {
        $flashMsg  = 'Erro: ' . $e->getMessage();
        $flashType = 'danger';
    }

    $_SESSION['_water_flash'] = [$flashMsg, $flashType];
    $ano = isset($_POST['data']) ? (int)substr($_POST['data'], 0, 4) : (int)($_GET['ano'] ?? date('Y'));
    header('Location: index.php?ano=' . $ano);
    exit;
}

// ── Flash de redirect ─────────────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';
if (isset($_SESSION['_water_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_water_flash'];
    unset($_SESSION['_water_flash']);
}

// ── Filtro de ano ─────────────────────────────────────────────────
$anosDisp  = $pdo->query(
    'SELECT DISTINCT YEAR(data) AS ano FROM infodeqb_water_ultrapure_record ORDER BY ano DESC'
)->fetchAll(PDO::FETCH_COLUMN);
if (empty($anosDisp)) $anosDisp = [(int)date('Y')];
$anoFiltro = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// ── Estatísticas ──────────────────────────────────────────────────
$stAll = $pdo->query(
    'SELECT COUNT(*) AS n, COALESCE(SUM(quantity),0) AS total FROM infodeqb_water_ultrapure_record'
)->fetch(PDO::FETCH_ASSOC);

$stYear = $pdo->prepare(
    'SELECT COUNT(*) AS n, COALESCE(SUM(quantity),0) AS total
     FROM infodeqb_water_ultrapure_record WHERE YEAR(data)=?'
);
$stYear->execute([$anoFiltro]);
$stYear = $stYear->fetch(PDO::FETCH_ASSOC);

$stMes = $pdo->prepare(
    'SELECT COALESCE(SUM(quantity),0) AS total
     FROM infodeqb_water_ultrapure_record WHERE YEAR(data)=? AND MONTH(data)=?'
);
$stMes->execute([(int)date('Y'), (int)date('m')]);
$stMes = (float)$stMes->fetchColumn();

// ── Registos do ano seleccionado ──────────────────────────────────
$sthRec = $pdo->prepare(
    'SELECT rec.autoid, rec.data, rec.quantity,
            r.id AS resp_id,  r.nome  AS resp_nome,
            u.userid AS user_id, u.user AS user_nome
     FROM infodeqb_water_ultrapure_record rec
     JOIN infodeqb_water_resp r  ON r.id      = rec.resp
     JOIN infodeqb_water_users u ON u.userid  = rec.user
     WHERE YEAR(rec.data) = ?
     ORDER BY rec.data DESC, rec.autoid DESC'
);
$sthRec->execute([$anoFiltro]);
$records = $sthRec->fetchAll(PDO::FETCH_ASSOC);

// ── Listas auxiliares (responsáveis e utilizadores) ───────────────
$resps = $pdo->query('SELECT id, nome FROM infodeqb_water_resp ORDER BY nome')
             ->fetchAll(PDO::FETCH_ASSOC);

$optResp = '';
foreach ($resps as $r) {
    $optResp .= '<option value="' . (int)$r['id'] . '">'
              . htmlspecialchars($r['nome']) . '</option>';
}

Database::disconnect();

$pageTitle = 'Consumos de Água Ultrapura';
include ROOT_DIR . '/infodeqb/inc/header.php';

// formata número com 2 casas decimais e separador de milhares
function fmtL($v) { return number_format((float)$v, 2, ',', ' ') . ' L'; }
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:10px">
  <h1 class="mr-auto mb-0">
    <i class="fas fa-water fa-sm mr-2 text-muted"></i>
    <?= t('WATER_CONSUMPTION') ?>
  </h1>
  <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modalNovoResp">
    <i class="fas fa-plus mr-1"></i> Responsável
  </button>
  <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#modalNovoUser">
    <i class="fas fa-plus mr-1"></i> Utilizador
  </button>
  <a href="edit.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-users-cog mr-1"></i> <?= t('WATER_MANAGE_USERS') ?>
  </a>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3"
     role="alert" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':'exclamation-circle' ?> mr-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php /* ── Badges de estatísticas ─────────────────────────────── */ ?>
<div class="d-flex flex-wrap mb-4" style="gap:12px">
  <div class="card flex-fill shadow-sm" style="min-width:150px">
    <div class="card-body py-3 text-center">
      <div class="text-muted small mb-1"><?= t('WATER_THIS_MONTH') ?></div>
      <div class="h5 mb-0 font-weight-bold text-primary"><?= fmtL($stMes) ?></div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm" style="min-width:150px">
    <div class="card-body py-3 text-center">
      <div class="text-muted small mb-1"><?= $anoFiltro ?></div>
      <div class="h5 mb-0 font-weight-bold"><?= fmtL($stYear['total']) ?></div>
      <div class="text-muted" style="font-size:.75rem"><?= number_format((int)$stYear['n']) ?> registos</div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm" style="min-width:150px">
    <div class="card-body py-3 text-center">
      <div class="text-muted small mb-1"><?= t('WATER_HISTORIC') ?></div>
      <div class="h5 mb-0 font-weight-bold text-secondary"><?= fmtL($stAll['total']) ?></div>
      <div class="text-muted" style="font-size:.75rem"><?= number_format((int)$stAll['n']) ?> registos</div>
    </div>
  </div>
</div>

<?php /* ── Formulário de inserção ──────────────────────────────── */ ?>
<div class="card mb-4 border-primary">
  <div class="card-header py-2 d-flex align-items-center"
       style="cursor:pointer" data-toggle="collapse" data-target="#formInserir">
    <i class="fas fa-plus-circle text-primary mr-2"></i>
    <strong class="mr-auto text-primary"><?= t('WATER_NEW_RECORD') ?></strong>
    <i class="fas fa-chevron-down fa-xs text-muted"></i>
  </div>
  <div id="formInserir" class="collapse">
    <div class="card-body">
      <form method="post" id="formAdd">
        <input type="hidden" name="_acao" value="inserir">
        <div class="form-row align-items-end">
          <div class="col-md-2 form-group mb-0">
            <label class="small font-weight-bold">Data <span class="text-danger">*</span></label>
            <input type="date" name="data" class="form-control form-control-sm"
                   required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-3 form-group mb-0">
            <label class="small font-weight-bold">Responsável <span class="text-danger">*</span></label>
            <select name="resp" id="addResp" class="form-control form-control-sm" required>
              <option value="">— seleccione —</option>
              <?= $optResp ?>
            </select>
          </div>
          <div class="col-md-3 form-group mb-0">
            <label class="small font-weight-bold">Utilizador <span class="text-danger">*</span></label>
            <select name="userid" id="addUser" class="form-control form-control-sm" required>
              <option value="">— seleccione primeiro o responsável —</option>
            </select>
          </div>
          <div class="col-md-2 form-group mb-0">
            <label class="small font-weight-bold">Quantidade (L) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="qtd"
                   class="form-control form-control-sm" required placeholder="ex: 10.5">
          </div>
          <div class="col-md-2 form-group mb-0">
            <button type="submit" class="btn btn-primary btn-sm btn-block">
              <i class="fas fa-save mr-1"></i> <?= t('SAVE') ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ── Tabela de registos ──────────────────────────────────── */ ?>
<div class="card mb-4 shadow-sm">
  <div class="card-header py-2 d-flex align-items-center">
    <strong class="mr-auto">Registos</strong>
    <!-- Filtro de ano -->
    <form method="get" class="d-flex align-items-center" style="gap:6px">
      <select name="ano" class="form-control form-control-sm" style="width:auto"
              onchange="this.form.submit()">
        <?php foreach ($anosDisp as $a): ?>
        <option value="<?= $a ?>" <?= $anoFiltro==$a?'selected':'' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" id="tblConsumos">
      <thead class="thead-light">
        <tr>
          <th style="width:8em">Data</th>
          <th>Responsável</th>
          <th>Utilizador</th>
          <th style="width:8em" class="text-right">Quantidade (L)</th>
          <th style="width:5em" class="text-center"><?= t('ACTIONS') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($records as $rec): ?>
        <tr>
          <td class="align-middle"><?= htmlspecialchars($rec['data']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($rec['resp_nome']) ?></td>
          <td class="align-middle"><?= htmlspecialchars($rec['user_nome']) ?></td>
          <td class="align-middle text-right"><?= number_format((float)$rec['quantity'], 2, ',', ' ') ?></td>
          <td class="align-middle text-center text-nowrap">
            <a href="#" class="btn-edit text-info mr-1"
               data-id="<?= (int)$rec['autoid'] ?>"
               data-data="<?= htmlspecialchars($rec['data']) ?>"
               data-resp="<?= (int)$rec['resp_id'] ?>"
               data-user="<?= (int)$rec['user_id'] ?>"
               data-qtd="<?= htmlspecialchars($rec['quantity']) ?>"
               title="Editar"
               data-toggle="modal" data-target="#modalEditar">
              <i class="fas fa-edit fa-xs"></i>
            </a>
            <a href="#" class="btn-delete text-danger"
               data-id="<?= (int)$rec['autoid'] ?>"
               data-info="<?= htmlspecialchars($rec['data'] . ' — ' . $rec['user_nome'] . ' — ' . $rec['quantity'] . ' L') ?>"
               title="Apagar"
               data-toggle="modal" data-target="#modalApagar">
              <i class="fas fa-trash fa-xs"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ── Modal: Editar ────────────────────────────────────────── */ ?>
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="formEditar">
        <input type="hidden" name="_acao" value="editar">
        <input type="hidden" name="editid" id="editId">
        <div class="modal-header">
          <h5 class="modal-title">Editar registo</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="small font-weight-bold">Data</label>
            <input type="date" name="editdata" id="editData" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="small font-weight-bold">Responsável</label>
            <select name="editresp" id="editResp" class="form-control" required>
              <?= $optResp ?>
            </select>
          </div>
          <div class="form-group">
            <label class="small font-weight-bold">Utilizador</label>
            <select name="edituser" id="editUser" class="form-control" required></select>
          </div>
          <div class="form-group mb-0">
            <label class="small font-weight-bold">Quantidade (L)</label>
            <input type="number" step="0.01" min="0" name="editqtd" id="editQtd"
                   class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('SAVE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ── Modal: Apagar ────────────────────────────────────────── */ ?>
<div class="modal fade" id="modalApagar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="apagar">
        <input type="hidden" name="deleteid" id="deleteId">
        <div class="modal-header">
          <h5 class="modal-title">Apagar registo</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p><?= t('CONFIRM_DELETE') ?></p>
          <p class="text-muted small" id="deleteInfo"></p>
          <p class="text-danger small mb-0"><?= t('IRREVERSIBLE') ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-danger"><?= t('DELETE') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ── Modal: Novo responsável ─────────────────────────────── */ ?>
<div class="modal fade" id="modalNovoResp" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="novo_resp">
        <div class="modal-header">
          <h5 class="modal-title">Novo responsável</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-0">
            <label class="font-weight-bold">Nome</label>
            <input type="text" name="nome_resp" class="form-control" required
                   placeholder="Nome do responsável">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('ADD') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ── Modal: Novo utilizador ──────────────────────────────── */ ?>
<div class="modal fade" id="modalNovoUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="novo_user">
        <div class="modal-header">
          <h5 class="modal-title">Novo utilizador</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="font-weight-bold">Responsável</label>
            <select name="idresp_user" class="form-control" required>
              <option value="">— seleccione —</option>
              <?= $optResp ?>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold">Nome do utilizador</label>
            <input type="text" name="nome_user" class="form-control" required
                   placeholder="Nome do utilizador">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('ADD') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {

    // ── DataTable ──────────────────────────────────────────────
    $('#tblConsumos').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search: '',
            searchPlaceholder: 'Pesquisar…',
            lengthMenu: 'Mostrar _MENU_',
            info: '_START_–_END_ de _TOTAL_',
            infoFiltered: '(de _MAX_)',
            infoEmpty: 'Sem registos',
            zeroRecords: 'Nenhum resultado',
            paginate: { previous: '‹', next: '›' },
        },
        dom: '<"d-flex align-items-center justify-content-between px-3 pt-3 pb-2"fi>t<"px-3 pb-3"p>',
        columnDefs: [
            { targets: [3], className: 'text-right' },
            { targets: [4], orderable: false, className: 'text-center' },
        ]
    });

    // ── Carregar utilizadores por responsável (inserção) ────────
    $('#addResp').on('change', function () {
        loadUsers($(this).val(), '#addUser', 0);
    });

    // ── Modal editar: preencher campos ─────────────────────────
    $(document).on('click', '.btn-edit', function () {
        var d = $(this).data();
        $('#editId').val(d.id);
        $('#editData').val(d.data);
        $('#editQtd').val(d.qtd);
        $('#editResp').val(d.resp);
        loadUsers(d.resp, '#editUser', d.user);
    });

    $('#editResp').on('change', function () {
        loadUsers($(this).val(), '#editUser', 0);
    });

    // ── Modal apagar: preencher dados ──────────────────────────
    $(document).on('click', '.btn-delete', function () {
        $('#deleteId').val($(this).data('id'));
        $('#deleteInfo').text($(this).data('info'));
    });
});

function loadUsers(respId, target, selectedUser) {
    if (!respId) {
        $(target).html('<option value="">— seleccione primeiro o responsável —</option>');
        return;
    }
    $.post('user.php', { resp: respId, user: selectedUser }, function (html) {
        $(target).html('<option value="">— seleccione —</option>' + html);
        if (selectedUser) $(target).val(selectedUser);
    });
}
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
