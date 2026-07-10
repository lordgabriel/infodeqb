<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$_servdocLocalAdmins = [];
$isServdocAdmin = $isAdmin || in_array($_iqCurrentUser, $_servdocLocalAdmins);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$emailuser = $_SESSION['user'] ?? '';

// ── Dados do docente actual ───────────────────────────────────────
$stmtDoc = $pdo->prepare('SELECT * FROM infodeqb_inv_deqb WHERE email = ?');
$stmtDoc->execute([$emailuser]);
$docente = $stmtDoc->fetch(PDO::FETCH_ASSOC);

if (!$isServdocAdmin && !$docente) {
    header('Location: ' . HTTP_DIR . '/infodeqb/error.php');
    exit;
}

$idDocente = $docente ? (int)$docente['Codigo'] : null;
$nomeDoc   = $docente ? $docente['Nome']   : '';

// ── Preferências actuais ──────────────────────────────────────────
$prefActuais = array();
if ($idDocente) {
    $stmtPref = $pdo->prepare(
        'SELECT p.*, u.uc, u.ocorrencia, u.area, u.ano
         FROM infodeqb_ucs_deqb_pref p
         LEFT JOIN infodeqb_ucs_deqb u ON u.codigo = p.codigo_uc
         WHERE p.id_docente = ?
         ORDER BY p.rank DESC, u.uc ASC'
    );
    $stmtPref->execute([$idDocente]);
    $prefActuais = $stmtPref->fetchAll(PDO::FETCH_ASSOC);
}
$temPrefs = !empty($prefActuais);

$prefIndex = array();
foreach ($prefActuais as $p) $prefIndex[$p['codigo_uc']] = (int)$p['rank'];

// ── Pedido de edição em curso ─────────────────────────────────────
$pedidoEdicao = null;
if ($idDocente) {
    $stmtPed = $pdo->prepare(
        'SELECT * FROM infodeqb_servdoc_edit_request
         WHERE id_docente = ? AND editado_em IS NULL
         ORDER BY pedido_em DESC LIMIT 1'
    );
    $stmtPed->execute([$idDocente]);
    $pedidoEdicao = $stmtPed->fetch(PDO::FETCH_ASSOC);
}
$podeEditar    = $pedidoEdicao && $pedidoEdicao['aprovado'];
$pedidoPendente = $pedidoEdicao && !$pedidoEdicao['aprovado'];

// ── Todas as UCs para o formulário ───────────────────────────────
$todasUcs = $pdo->query('SELECT * FROM infodeqb_ucs_deqb ORDER BY area ASC, uc ASC')
               ->fetchAll(PDO::FETCH_ASSOC);

// ── POST handlers ─────────────────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';

// Admin edit: processar antes do bloco normal (não requer $idDocente)
if (!empty($_POST) && $isServdocAdmin && ($_POST['_acao'] ?? '') === 'admin_editar') {
    $targetId  = (int)($_POST['admin_target_id'] ?? 0);
    $targetNome = trim($_POST['admin_target_nome'] ?? '');
    $filterOut = array('_acao','admin_target_id','admin_target_nome','dataTable_length');
    $filteredArr = array_diff_key($_POST, array_flip($filterOut));
    $elementos = array();
    foreach ($filteredArr as $key => $value) {
        $v = (int)$value;
        if ($v < 1 || $v > 5) continue;
        $elementos[] = array($targetId, $key, $v, '', $targetNome);
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM infodeqb_ucs_deqb_pref WHERE id_docente = ?')->execute(array($targetId));
        $stmt = $pdo->prepare('INSERT INTO infodeqb_ucs_deqb_pref (id_docente,codigo_uc,rank,email,nome) VALUES (?,?,?,?,?)');
        foreach ($elementos as $row) $stmt->execute($row);
        // Fechar qualquer pedido de edição aberto para este docente
        $pdo->prepare('UPDATE infodeqb_servdoc_edit_request SET editado_em=NOW() WHERE id_docente=? AND editado_em IS NULL')
            ->execute(array($targetId));
        $pdo->commit();
        $_SESSION['_servdoc_flash'] = array('Preferências de ' . $targetNome . ' actualizadas.', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['_servdoc_flash'] = array('Erro ao guardar.', 'danger');
    }
    header('Location: index.php'); exit;
}

if (!empty($_POST) && $idDocente) {
    $acao = $_POST['_acao'] ?? '';

    if ($acao === 'submeter' || $acao === 'editar') {
        if ($acao === 'editar' && !$podeEditar) {
            $flashMsg = 'Edição não autorizada.'; $flashType = 'danger';
        } else {
            $filterOut = array('_acao','dataTable_length','Submeter','codigo','nome');
            $filteredArr = array_diff_key($_POST, array_flip($filterOut));
            $elementos = array();
            foreach ($filteredArr as $key => $value) {
                $v = (int)$value;
                if ($v < 1 || $v > 5) continue;
                $elementos[] = array($idDocente, $key, $v, $emailuser, $nomeDoc);
            }
            if (empty($elementos)) {
                $flashMsg = 'Seleccione pelo menos uma UC antes de submeter.';
                $flashType = 'warning';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM infodeqb_ucs_deqb_pref WHERE id_docente = ?')
                        ->execute(array($idDocente));
                    $stmt = $pdo->prepare(
                        'INSERT INTO infodeqb_ucs_deqb_pref (id_docente, codigo_uc, rank, email, nome)
                         VALUES (?,?,?,?,?)'
                    );
                    foreach ($elementos as $row) $stmt->execute($row);
                    if ($acao === 'editar' && $pedidoEdicao) {
                        $pdo->prepare(
                            'UPDATE infodeqb_servdoc_edit_request SET editado_em = NOW() WHERE id = ?'
                        )->execute(array($pedidoEdicao['id']));
                    }
                    $pdo->commit();
                    $_SESSION['_servdoc_flash'] = array('Preferências guardadas com sucesso.', 'success');
                    header('Location: index.php'); exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $flashMsg = 'Erro ao guardar preferências.'; $flashType = 'danger';
                }
            }
        }

    } elseif ($acao === 'solicitar_edicao' && $temPrefs && !$pedidoEdicao) {
        try {
            $pdo->prepare(
                'INSERT INTO infodeqb_servdoc_edit_request (id_docente, motivo) VALUES (?,?)'
            )->execute(array($idDocente, trim($_POST['motivo'] ?? '')));
            $_SESSION['_servdoc_flash'] = array('Pedido enviado. Será notificado quando a edição for aprovada.', 'success');
        } catch (Exception $e) {
            $_SESSION['_servdoc_flash'] = array('Erro ao enviar pedido.', 'danger');
        }
        header('Location: index.php'); exit;

    } elseif ($acao === 'cancelar_pedido' && $pedidoPendente) {
        $pdo->prepare('DELETE FROM infodeqb_servdoc_edit_request WHERE id = ? AND aprovado = 0')
            ->execute(array($pedidoEdicao['id']));
        $_SESSION['_servdoc_flash'] = array('Pedido de edição cancelado.', 'info');
        header('Location: index.php'); exit;

    } elseif ($acao === 'aprovar_edicao' && $isServdocAdmin) {
        $reqId = (int)($_POST['req_id'] ?? 0);
        $pdo->prepare('UPDATE infodeqb_servdoc_edit_request SET aprovado=1, aprovado_em=NOW() WHERE id=?')
            ->execute(array($reqId));
        $_SESSION['_servdoc_flash'] = array('Edição aprovada.', 'success');
        header('Location: index.php'); exit;

    } elseif ($acao === 'revogar_edicao' && $isServdocAdmin) {
        $reqId = (int)($_POST['req_id'] ?? 0);
        $pdo->prepare('DELETE FROM infodeqb_servdoc_edit_request WHERE id = ? AND editado_em IS NULL')
            ->execute(array($reqId));
        $_SESSION['_servdoc_flash'] = array('Aprovação revogada.', 'info');
        header('Location: index.php'); exit;
    }
}

if (isset($_SESSION['_servdoc_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_servdoc_flash'];
    unset($_SESSION['_servdoc_flash']);
}

// ── Modo de edição (GET ?editar=1) ────────────────────────────────
$modoEdicao = (isset($_GET['editar']) && $podeEditar);

// ── Admin edit mode (GET ?admin_editar=ID) ────────────────────────
$adminEditTarget   = null; // docente record being edited by admin
$adminEditPrefIndex = array();
if ($isServdocAdmin && !empty($_GET['admin_editar'])) {
    $aeId = (int)$_GET['admin_editar'];
    $stmtAe = $pdo->prepare('SELECT * FROM infodeqb_inv_deqb WHERE Codigo = ?');
    $stmtAe->execute(array($aeId));
    $adminEditTarget = $stmtAe->fetch(PDO::FETCH_ASSOC);
    if ($adminEditTarget) {
        $stmtAeP = $pdo->prepare(
            'SELECT codigo_uc, rank FROM infodeqb_ucs_deqb_pref WHERE id_docente = ?'
        );
        $stmtAeP->execute(array($aeId));
        foreach ($stmtAeP->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $adminEditPrefIndex[$r['codigo_uc']] = (int)$r['rank'];
        }
    }
}

// ── Dados admin ───────────────────────────────────────────────────
if ($isServdocAdmin) {
    $todosDocentes = $pdo->query(
        'SELECT d.Codigo, d.Nome, d.email, d.categoria,
                COUNT(DISTINCT p.codigo_uc) AS n_prefs,
                MAX(p.update_date) AS ultima_atualizacao
         FROM infodeqb_inv_deqb d
         LEFT JOIN infodeqb_ucs_deqb_pref p ON p.id_docente = d.Codigo
         GROUP BY d.Codigo, d.Nome, d.email, d.categoria
         ORDER BY d.Nome'
    )->fetchAll(PDO::FETCH_ASSOC);

    $totalDocentes = count($todosDocentes);
    $totalResp = 0;
    foreach ($todosDocentes as $d) { if ($d['n_prefs'] > 0) $totalResp++; }

    $pedidosPendentes = $pdo->query(
        'SELECT r.*, d.Nome, d.email
         FROM infodeqb_servdoc_edit_request r
         JOIN infodeqb_inv_deqb d ON d.Codigo = r.id_docente
         WHERE r.editado_em IS NULL
         ORDER BY r.aprovado ASC, r.pedido_em DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $nPendentes  = 0;
    $nAprovados  = 0;
    foreach ($pedidosPendentes as $p) {
        if ($p['aprovado']) $nAprovados++; else $nPendentes++;
    }

    $distArea = $pdo->query(
        'SELECT u.area,
                COUNT(*) AS n_prefs,
                COUNT(DISTINCT p.id_docente) AS n_docentes,
                ROUND(AVG(p.rank),1) AS avg_rank
         FROM infodeqb_ucs_deqb_pref p
         JOIN infodeqb_ucs_deqb u ON u.codigo = p.codigo_uc
         GROUP BY u.area ORDER BY n_docentes DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $topUcs = $pdo->query(
        'SELECT u.uc, u.codigo, u.area,
                COUNT(*) AS n_prefs,
                ROUND(AVG(p.rank),1) AS avg_rank
         FROM infodeqb_ucs_deqb_pref p
         JOIN infodeqb_ucs_deqb u ON u.codigo = p.codigo_uc
         WHERE p.rank >= 4
         GROUP BY u.uc, u.codigo, u.area
         ORDER BY n_prefs DESC, avg_rank DESC
         LIMIT 15'
    )->fetchAll(PDO::FETCH_ASSOC);

    // Preferências individuais indexadas por id_docente (para modal)
    $todasPrefsRaw = $pdo->query(
        'SELECT p.id_docente, p.codigo_uc, p.rank,
                u.uc, u.area, u.ocorrencia, u.ano
         FROM infodeqb_ucs_deqb_pref p
         JOIN infodeqb_ucs_deqb u ON u.codigo = p.codigo_uc
         ORDER BY p.id_docente, p.rank DESC, u.uc ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    $todasPrefsMap = array();
    foreach ($todasPrefsRaw as $r) {
        $todasPrefsMap[$r['id_docente']][] = array(
            'codigo'     => $r['codigo_uc'],
            'uc'         => $r['uc'],
            'area'       => $r['area'],
            'ocorrencia' => $r['ocorrencia'],
            'ano'        => $r['ano'],
            'rank'       => (int)$r['rank'],
        );
    }

    // UC explorer: todas as UCs com quem as seleccionou
    $ucExplorerRaw = $pdo->query(
        'SELECT u.codigo, u.uc, u.area, u.ocorrencia, u.ano,
                COUNT(p.id_docente)       AS n_sel,
                ROUND(AVG(p.rank), 1)     AS avg_rank
         FROM infodeqb_ucs_deqb u
         LEFT JOIN infodeqb_ucs_deqb_pref p ON p.codigo_uc = u.codigo
         GROUP BY u.codigo, u.uc, u.area, u.ocorrencia, u.ano
         ORDER BY u.area ASC, u.uc ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    // Mapa UC → lista de docentes com rank (para modal)
    $ucDocentesRaw = $pdo->query(
        'SELECT p.codigo_uc, p.rank, d.Codigo, d.Nome
         FROM infodeqb_ucs_deqb_pref p
         JOIN infodeqb_inv_deqb d ON d.Codigo = p.id_docente
         ORDER BY p.codigo_uc, p.rank DESC, d.Nome ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    $ucDocentesMap = array();
    foreach ($ucDocentesRaw as $r) {
        $ucDocentesMap[$r['codigo_uc']][] = array(
            'nome'   => $r['Nome'],
            'codigo' => $r['Codigo'],
            'rank'   => (int)$r['rank'],
        );
    }
}

Database::disconnect();
$pageTitle = 'Preferência Serviço Docente';
include ROOT_DIR . '/infodeqb/inc/header.php';

// ── Helper: renderizar formulário de preferências ─────────────────
function renderForm($todasUcs, $prefIndex, $acao, $modoEdicao) {
    $label = $modoEdicao ? t('SERVDOC_UPDATE') : t('SERVDOC_SUBMIT');
    // Áreas únicas para o dropdown
    $areasUnicas = array();
    foreach ($todasUcs as $row) {
        if (!in_array($row['area'], $areasUnicas)) $areasUnicas[] = $row['area'];
    }
    sort($areasUnicas);
    ?>
<style>
.rank-btns { display:flex; gap:2px; justify-content:center; }
.rank-lbl {
    width:26px; height:26px; line-height:26px; text-align:center;
    border:1px solid #dee2e6; border-radius:4px; cursor:pointer;
    font-size:.78rem; font-weight:600; color:#6c757d; background:#fff;
    margin:0; transition:background .1s,border-color .1s,color .1s; display:block;
}
.uc-radio { position:absolute; opacity:0; width:0; height:0; }
.uc-radio:checked + .rank-lbl { background:#0d6efd; border-color:#0d6efd; color:#fff; }
.rank-lbl:hover { background:#e9ecef; border-color:#adb5bd; }
#tblUcs thead tr.filter-row th { padding:4px 6px; background:#fff; border-top:2px solid #dee2e6; }
</style>

<form method="post" id="frmPrefs">
  <input type="hidden" name="_acao" value="<?= htmlspecialchars($acao) ?>">

  <div class="card shadow-sm mb-0">
    <div class="card-header py-2 d-flex align-items-center iq-header-dark">
      <i class="fas fa-table fa-sm me-2" style="opacity:.6"></i>
      <strong><?= $modoEdicao ? t('SERVDOC_UPDATE') : 'Unidades Curriculares' ?></strong>
      <small class="ms-2" style="opacity:.6;font-size:.78rem">— indique a sua preferência (1–5) por UC</small>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-hover mb-0" id="tblUcs">
        <thead>
          <!-- Cabeçalhos ordenáveis -->
          <tr class="">
            <th>Unidade Curricular</th>
            <th style="width:11em">Área Científica</th>
            <th style="width:0;display:none">_area_raw</th>
            <th style="width:7em" class="text-center">Preferência</th>
          </tr>
          <!-- Linha de filtros -->
          <tr class="filter-row">
            <th>
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text bg-white border-right-0 pe-1">
                    <i class="fas fa-search fa-xs text-muted"></i>
                  </span>
                </div>
                <input type="text" id="filterUc" class="form-control border-left-0"
                       placeholder="Pesquisar UC…" autocomplete="off">
              </div>
            </th>
            <th colspan="2">
              <select id="filterArea" class="form-control form-control-sm">
                <option value="">Todas as áreas</option>
                <?php foreach ($areasUnicas as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
              </select>
            </th>
            <th class="text-center">
              <label class="mb-0 d-flex align-items-center justify-content-center"
                     style="gap:5px;font-size:.78rem;cursor:pointer;white-space:nowrap">
                <input type="checkbox" id="filterSel">
                <span>Só sel.</span>
              </label>
            </th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($todasUcs as $row):
          $c   = htmlspecialchars($row['codigo']);
          $sel = isset($prefIndex[$row['codigo']]) ? $prefIndex[$row['codigo']] : 0;
        ?>
          <tr>
            <td class="align-middle">
              <span style="font-size:.84rem"><?= htmlspecialchars($row['uc']) ?></span>
              <small class="text-muted d-block" style="font-size:.72rem">
                <?= $c ?> &middot; <?= htmlspecialchars($row['ocorrencia']) ?>
              </small>
            </td>
            <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($row['area']) ?></td>
            <td style="display:none"><?= htmlspecialchars($row['area']) ?></td>
            <td class="align-middle">
              <div class="rank-btns">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <input type="radio" class="uc-radio"
                       id="<?= $c ?>_<?= $i ?>"
                       name="<?= $c ?>"
                       value="<?= $i ?>"
                       <?= $sel === $i ? 'checked' : '' ?>>
                <label class="rank-lbl" for="<?= $c ?>_<?= $i ?>"><?= $i ?></label>
                <?php endfor; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex align-items-center py-2" style="gap:12px">
      <span class="badge badge-secondary px-2" id="cntBadge" style="font-size:.8rem">0 / 10</span>
      <span id="limitAlert" class="text-warning small" style="display:none">
        <i class="fas fa-exclamation-triangle me-1"></i>Máximo de 10 UCs atingido.
      </span>
      <div class="ml-auto d-flex" style="gap:8px">
        <?php if ($modoEdicao): ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><?= t('CANCEL') ?></a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-paper-plane me-1"></i><?= $label ?>
        </button>
      </div>
    </div>
  </div>
</form>

<script>
$(document).ready(function () {
    var table = $('#tblUcs').DataTable({
        paging: true,
        pageLength: 20,
        orderCellsTop: true,   // usa a 1ª linha do thead para ordenação
        order: [[0, 'asc']],
        dom: 't<"d-flex align-items-center justify-content-between px-3 py-2"ip>',
        columnDefs: [
            { targets: [2], visible: false, searchable: true },  // área raw (filtro)
            { targets: [3], orderable: false }                   // preferência
        ],
        language: {
            info: '_START_–_END_ de _TOTAL_ UCs',
            infoFiltered: '(de _MAX_)',
            paginate: { previous: '‹', next: '›' },
            zeroRecords: 'Sem resultados para os filtros actuais.'
        }
    });

    // ── Filtros ──────────────────────────────────────────────────
    $('#filterUc').on('keyup', function () {
        table.column(0).search(this.value).draw();
    });

    $('#filterArea').on('change', function () {
        var val = this.value;
        // Exact match na coluna área (col 2, raw)
        table.column(2).search(val ? ('^' + $.fn.dataTable.util.escapeRegex(val) + '$') : '', true, false).draw();
    });

    // Filtro "só selecionadas" — custom search function
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tblUcs') return true;
        if (!$('#filterSel').is(':checked')) return true;
        var tr = table.row(dataIndex).node();
        return $(tr).find('input.uc-radio:checked').length > 0;
    });
    $('#filterSel').on('change', function () { table.draw(); });

    // ── Contador e limite ────────────────────────────────────────
    function updateCount() {
        var n = table.$('input.uc-radio:checked').length;
        $('#cntBadge')
            .text(n + ' / 10')
            .toggleClass('badge-secondary', n < 10)
            .toggleClass('badge-warning',   n === 10);
        $('#limitAlert').toggle(n >= 10);
    }

    // ── Deselect + limite ────────────────────────────────────────
    table.on('draw', function () {
        table.$('input.uc-radio').each(function () {
            $(this).data('prev', this.checked);
        });
        updateCount();
    });

    $(document).on('click', '.uc-radio', function () {
        var name = this.name;
        if ($(this).data('prev') === true) {
            $(this).prop('checked', false).data('prev', false);
        } else {
            var checked = table.$('input.uc-radio:checked').length;
            if (checked > 10) {
                $(this).prop('checked', false);
            } else {
                table.$('input[name="' + name + '"]').data('prev', false);
                $(this).data('prev', true);
            }
        }
        updateCount();
        // Atualiza filtro "só selecionadas" se activo
        if ($('#filterSel').is(':checked')) table.draw();
    });

    // Serialize hidden pages before submit
    $('#frmPrefs').on('submit', function () {
        var form = this;
        table.$('input[type=radio]:checked').each(function () {
            if (!$.contains(document, form[this.name])) {
                $(form).append(
                    $('<input>').attr({ type: 'hidden', name: this.name }).val(this.value)
                );
            }
        });
        updateCount();
    });

    updateCount();
});
</script>
    <?php
}
?>

<div class="iq-page-header d-flex align-items-center">
  <h1 class="mr-auto">
    <i class="fas fa-chalkboard-teacher fa-sm me-2 text-muted"></i>
    <?= t('SERVDOC_TITLE') ?>
  </h1>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3"
     role="alert" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType==='success'?'check-circle':($flashType==='info'?'info-circle':'exclamation-circle') ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php if ($isServdocAdmin): ?>
<?php /* ═══════ VISTA ADMIN ═══════════════════════════════════════ */ ?>

<?php if ($adminEditTarget): ?>
<?php /* ── Admin a editar resposta de um docente ─────────────────── */ ?>
<div class="alert alert-warning py-2 mb-3 d-flex align-items-center" style="font-size:.84rem">
  <i class="fas fa-user-edit me-2"></i>
  <span class="mr-auto">A editar preferências de <strong><?= htmlspecialchars($adminEditTarget['Nome']) ?></strong></span>
  <a href="index.php" class="btn btn-outline-secondary btn-sm ms-3">
    <i class="fas fa-times me-1"></i>Cancelar
  </a>
</div>
<?php
// Injectar no formulário: target docente em vez do admin
$savedIdDocente    = $idDocente;
$savedNomeDoc      = $nomeDoc;
$idDocente         = (int)$adminEditTarget['Codigo'];
$nomeDoc           = $adminEditTarget['Nome'];

// Modificar renderForm para usar _acao=admin_editar e campos extra
function renderFormAdmin($todasUcs, $prefIndex, $targetId, $targetNome) {
    $areasUnicas = array();
    foreach ($todasUcs as $row) {
        if (!in_array($row['area'], $areasUnicas)) $areasUnicas[] = $row['area'];
    }
    sort($areasUnicas);
    ?>
<style>
.rank-btns{display:flex;gap:2px;justify-content:center}
.rank-lbl{width:26px;height:26px;line-height:26px;text-align:center;border:1px solid #dee2e6;border-radius:4px;cursor:pointer;font-size:.78rem;font-weight:600;color:#6c757d;background:#fff;margin:0;transition:background .1s,border-color .1s,color .1s;display:block}
.uc-radio{position:absolute;opacity:0;width:0;height:0}
.uc-radio:checked+.rank-lbl{background:#0d6efd;border-color:#0d6efd;color:#fff}
.rank-lbl:hover{background:#e9ecef;border-color:#adb5bd}
#tblUcs thead tr.filter-row th{padding:4px 6px;background:#fff;border-top:2px solid #dee2e6}
</style>
<form method="post" id="frmPrefs">
  <input type="hidden" name="_acao" value="admin_editar">
  <input type="hidden" name="admin_target_id" value="<?= (int)$targetId ?>">
  <input type="hidden" name="admin_target_nome" value="<?= htmlspecialchars($targetNome) ?>">
  <div class="card shadow-sm mb-0">
    <div class="card-body p-0">
      <table class="table table-sm table-hover mb-0" id="tblUcs">
        <thead>
          <tr class="">
            <th>Unidade Curricular</th>
            <th style="width:11em">Área Científica</th>
            <th style="width:0;display:none">_area_raw</th>
            <th style="width:7em" class="text-center">Preferência</th>
          </tr>
          <tr class="filter-row">
            <th><div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text bg-white border-right-0 pe-1"><i class="fas fa-search fa-xs text-muted"></i></span></div><input type="text" id="filterUc" class="form-control border-left-0" placeholder="Pesquisar UC…" autocomplete="off"></div></th>
            <th colspan="2"><select id="filterArea" class="form-control form-control-sm"><option value="">Todas as áreas</option><?php foreach ($areasUnicas as $a): ?><option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option><?php endforeach; ?></select></th>
            <th class="text-center"><label class="mb-0 d-flex align-items-center justify-content-center" style="gap:5px;font-size:.78rem;cursor:pointer;white-space:nowrap"><input type="checkbox" id="filterSel"><span>Só sel.</span></label></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($todasUcs as $row):
            $c   = htmlspecialchars($row['codigo']);
            $sel = isset($prefIndex[$row['codigo']]) ? $prefIndex[$row['codigo']] : 0; ?>
          <tr>
            <td class="align-middle"><span style="font-size:.84rem"><?= htmlspecialchars($row['uc']) ?></span><small class="text-muted d-block" style="font-size:.72rem"><?= $c ?> · <?= htmlspecialchars($row['ocorrencia']) ?></small></td>
            <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($row['area']) ?></td>
            <td style="display:none"><?= htmlspecialchars($row['area']) ?></td>
            <td class="align-middle"><div class="rank-btns"><?php for ($i=1;$i<=5;$i++): ?><input type="radio" class="uc-radio" id="<?= $c ?>_<?= $i ?>" name="<?= $c ?>" value="<?= $i ?>" <?= $sel===$i?'checked':'' ?>><label class="rank-lbl" for="<?= $c ?>_<?= $i ?>"><?= $i ?></label><?php endfor; ?></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer d-flex align-items-center py-2" style="gap:12px">
      <span class="badge badge-secondary px-2" id="cntBadge" style="font-size:.8rem">0 / 10</span>
      <span id="limitAlert" class="text-warning small" style="display:none"><i class="fas fa-exclamation-triangle me-1"></i>Máximo de 10 UCs.</span>
      <div class="ml-auto d-flex" style="gap:8px">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><?= t('CANCEL') ?></a>
        <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-save me-1"></i><?= t('SAVE_CHANGES') ?></button>
      </div>
    </div>
  </div>
</form>
    <?php
}
renderFormAdmin($todasUcs, $adminEditPrefIndex, $adminEditTarget['Codigo'], $adminEditTarget['Nome']);
?>
<script>
$(document).ready(function () {
    var table = $('#tblUcs').DataTable({
        paging: true, pageLength: 20, orderCellsTop: true, order: [[0,'asc']],
        dom: 't<"d-flex align-items-center justify-content-between px-3 py-2"ip>',
        columnDefs: [{ targets:[2], visible:false, searchable:true }, { targets:[3], orderable:false }],
        language: { info:'_START_–_END_ de _TOTAL_ UCs', infoFiltered:'(de _MAX_)',
                    paginate:{ previous:'‹', next:'›' }, zeroRecords:'Sem resultados' }
    });
    $('#filterUc').on('keyup', function(){ table.column(0).search(this.value).draw(); });
    $('#filterArea').on('change', function(){
        var v = this.value;
        table.column(2).search(v?('^'+$.fn.dataTable.util.escapeRegex(v)+'$'):'',true,false).draw();
    });
    $.fn.dataTable.ext.search.push(function(s,d,i){
        if(s.nTable.id!=='tblUcs') return true;
        if(!$('#filterSel').is(':checked')) return true;
        return $(table.row(i).node()).find('input.uc-radio:checked').length>0;
    });
    $('#filterSel').on('change', function(){ table.draw(); });
    function updateCount(){
        var n=table.$('input.uc-radio:checked').length;
        $('#cntBadge').text(n+' / 10').toggleClass('badge-secondary',n<10).toggleClass('badge-warning',n===10);
        $('#limitAlert').toggle(n>=10);
    }
    table.on('draw', function(){ table.$('input.uc-radio').each(function(){ $(this).data('prev',this.checked); }); updateCount(); });
    $(document).on('click','.uc-radio',function(){
        var name=this.name;
        if($(this).data('prev')===true){ $(this).prop('checked',false).data('prev',false); }
        else { var n=table.$('input.uc-radio:checked').length; if(n>10){ $(this).prop('checked',false); } else { table.$('input[name="'+name+'"]').data('prev',false); $(this).data('prev',true); } }
        updateCount();
        if($('#filterSel').is(':checked')) table.draw();
    });
    // Serialize hidden pages
    $('#frmPrefs').on('submit',function(){
        var form=this;
        table.$('input.uc-radio:checked').each(function(){
            if(!$.contains(document,form[this.name])){
                $(form).append($('<input>').attr({type:'hidden',name:this.name}).val(this.value));
            }
        });
    });
    updateCount();
});
</script>
<?php else: ?>

<?php /* ── Stats ─────────────────────────────────────────────────── */ ?>
<div class="d-flex flex-wrap mb-4" style="gap:12px">
  <div class="card flex-fill shadow-sm text-center" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><?= t('AREAS_RESPONDED') ?></div>
      <div class="h4 mb-0 font-weight-bold text-success"><?= $totalResp ?> / <?= $totalDocentes ?></div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm text-center" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1">Pendentes</div>
      <div class="h4 mb-0 font-weight-bold <?= ($totalDocentes-$totalResp)>0?'text-warning':'text-success' ?>">
        <?= $totalDocentes - $totalResp ?>
      </div>
    </div>
  </div>
  <div class="card flex-fill shadow-sm text-center" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1"><?= t('AREAS_RATE') ?></div>
      <div class="h4 mb-0 font-weight-bold">
        <?= $totalDocentes > 0 ? round($totalResp/$totalDocentes*100) : 0 ?>%
      </div>
    </div>
  </div>
  <?php if ($nPendentes > 0): ?>
  <div class="card flex-fill shadow-sm text-center border-warning" style="min-width:140px">
    <div class="card-body py-3">
      <div class="text-muted small mb-1">Pedidos de edição</div>
      <div class="h4 mb-0 font-weight-bold text-warning"><?= $nPendentes ?></div>
      <div class="text-muted" style="font-size:.72rem"><?= $nAprovados ?> já aprovados</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php /* ── Pedidos de edição ───────────────────────────────────────── */ ?>
<?php if (!empty($pedidosPendentes)): ?>
<div class="card shadow-sm mb-4 <?= $nPendentes > 0 ? 'border-warning' : '' ?>">
  <div class="card-header py-2 d-flex align-items-center">
    <i class="fas fa-edit me-2 <?= $nPendentes > 0 ? 'text-warning' : 'text-success' ?>"></i>
    <strong class="mr-auto">Pedidos de edição</strong>
    <?php if ($nPendentes > 0): ?>
    <span class="badge badge-warning"><?= $nPendentes ?> por aprovar</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
      <thead class="">
        <tr>
          <th style="width:16em">Docente</th>
          <th style="width:9em">Pedido em</th>
          <th>Motivo</th>
          <th class="text-center" style="width:7em">Estado</th>
          <th style="width:8em"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pedidosPendentes as $req): ?>
        <tr>
          <td class="align-middle">
            <?= htmlspecialchars($req['Nome']) ?>
            <small class="text-muted d-block" style="font-size:.72rem"><?= htmlspecialchars($req['email']) ?></small>
          </td>
          <td class="align-middle"><?= substr($req['pedido_em'], 0, 16) ?></td>
          <td class="align-middle" style="white-space:normal;word-break:break-word">
            <?= htmlspecialchars($req['motivo'] ?: '—') ?>
          </td>
          <td class="align-middle text-center">
            <?php if ($req['aprovado']): ?>
            <span class="badge badge-success">Aprovado</span>
            <?php else: ?>
            <span class="badge badge-warning"><?= t('SERVDOC_PENDING_U') ?></span>
            <?php endif; ?>
          </td>
          <td class="align-middle text-end">
            <?php if (!$req['aprovado']): ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="_acao" value="aprovar_edicao">
              <input type="hidden" name="req_id" value="<?= (int)$req['id'] ?>">
              <button type="submit" class="btn btn-success btn-xs">
                <i class="fas fa-check fa-xs me-1"></i><?= t('SERVDOC_APPROVE') ?>
              </button>
            </form>
            <?php endif; ?>
            <form method="post" class="d-inline ms-1">
              <input type="hidden" name="_acao" value="revogar_edicao">
              <input type="hidden" name="req_id" value="<?= (int)$req['id'] ?>">
              <button type="submit" class="btn btn-outline-secondary btn-xs"
                      title="<?= $req['aprovado'] ? 'Revogar aprovação' : 'Rejeitar pedido' ?>">
                <i class="fas fa-times fa-xs"></i>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php /* ── Dashboard: área + top UCs ────────────────────────────── */ ?>
<div class="row mb-4">
  <div class="col-md-5">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <strong>Preferências por área científica</strong>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead class="">
            <tr>
              <th>Área</th>
              <th class="text-center" style="width:5em">Docentes</th>
              <th class="text-center" style="width:5em">Prefs.</th>
              <th class="text-center" style="width:5em">Média</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($distArea as $d): ?>
            <tr>
              <td class="align-middle"><?= htmlspecialchars($d['area']) ?></td>
              <td class="align-middle text-center"><?= $d['n_docentes'] ?></td>
              <td class="align-middle text-center"><?= $d['n_prefs'] ?></td>
              <td class="align-middle text-center">
                <span class="badge badge-<?= $d['avg_rank']>=4?'success':($d['avg_rank']>=3?'warning':'secondary') ?>">
                  <?= $d['avg_rank'] ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card shadow-sm h-100">
      <div class="card-header py-2">
        <strong>UCs mais pretendidas</strong>
        <small class="text-muted ms-1">(rank ≥ 4)</small>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
          <thead class="">
            <tr>
              <th>UC</th>
              <th style="width:9em">Área</th>
              <th class="text-center" style="width:5em">Docentes</th>
              <th class="text-center" style="width:5em">Média</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($topUcs as $u): ?>
            <tr>
              <td class="align-middle">
                <?= htmlspecialchars($u['uc']) ?>
                <small class="text-muted d-block" style="font-size:.72rem"><?= htmlspecialchars($u['codigo']) ?></small>
              </td>
              <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($u['area']) ?></td>
              <td class="align-middle text-center"><?= $u['n_prefs'] ?></td>
              <td class="align-middle text-center">
                <span class="badge badge-<?= $u['avg_rank']>=4.5?'success':'warning' ?>">
                  <?= $u['avg_rank'] ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php /* ── Lista de docentes ────────────────────────────────────── */ ?>
<!-- Pills para alternar vista -->
<ul class="nav nav-pills mb-3" id="adminTabs">
  <li class="nav-item">
    <a class="nav-link active" href="#tabDocentes" data-bs-toggle="tab">
      <i class="fas fa-users fa-xs me-1"></i>Por docente
      <span class="badge badge-light ms-1"><?= $totalResp ?> / <?= $totalDocentes ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="#tabUcs" data-bs-toggle="tab">
      <i class="fas fa-book fa-xs me-1"></i>Por UC
      <span class="badge badge-light ms-1"><?= count($ucExplorerRaw) ?></span>
    </a>
  </li>
</ul>

<div class="tab-content">

<?php /* ── Tab: por docente ─────────────────────────────────────── */ ?>
<div class="tab-pane fade show active" id="tabDocentes">
<div class="card shadow-sm mb-4">
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" id="tblDocentes" style="font-size:.82rem">
      <thead class="">
        <tr>
          <th>Nome</th>
          <th style="width:9em">Categoria</th>
          <th class="text-center" style="width:5em">UCs</th>
          <th class="text-center" style="width:7em">Estado</th>
          <th class="text-center" style="width:7em">Edição</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $editStatusMap = array();
      foreach ($pedidosPendentes as $req) $editStatusMap[$req['id_docente']] = $req;
      foreach ($todosDocentes as $d):
        $editReq = isset($editStatusMap[$d['Codigo']]) ? $editStatusMap[$d['Codigo']] : null;
        $temPrefsD = $d['n_prefs'] > 0;
      ?>
        <tr class="<?= $temPrefsD ? 'row-clickable' : '' ?>"
            style="<?= $temPrefsD ? 'cursor:pointer' : '' ?>"
            <?php if ($temPrefsD): ?>
            data-id="<?= (int)$d['Codigo'] ?>"
            data-nome="<?= htmlspecialchars($d['Nome']) ?>"
            data-bs-toggle="modal" data-bs-target="#modalDocente"
            <?php endif; ?>>
          <td class="align-middle">
            <?php if ($temPrefsD): ?>
            <span class="text-primary" style="font-weight:500"><?= htmlspecialchars($d['Nome']) ?></span>
            <?php else: ?>
            <?= htmlspecialchars($d['Nome']) ?>
            <?php endif; ?>
            <small class="text-muted d-block" style="font-size:.72rem"><?= htmlspecialchars($d['email']) ?></small>
          </td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($d['categoria'] ?? '') ?></td>
          <td class="align-middle text-center"><?= $temPrefsD ? $d['n_prefs'] : '—' ?></td>
          <td class="align-middle text-center">
            <?php if ($temPrefsD): ?>
            <span class="badge badge-success"><i class="fas fa-check fa-xs me-1"></i><?= t('SERVDOC_RESPONDED') ?></span>
            <?php else: ?>
            <span class="badge badge-warning"><?= t('SERVDOC_PENDING_U') ?></span>
            <?php endif; ?>
          </td>
          <td class="align-middle text-center" onclick="event.stopPropagation()">
            <?php if ($editReq): ?>
              <?php if ($editReq['aprovado']): ?>
              <span class="badge badge-info">Aprovada</span>
              <?php else: ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="_acao" value="aprovar_edicao">
                <input type="hidden" name="req_id" value="<?= (int)$editReq['id'] ?>">
                <button type="submit" class="btn btn-warning btn-xs">
                  <i class="fas fa-unlock fa-xs me-1"></i><?= t('SERVDOC_APPROVE') ?>
                </button>
              </form>
              <?php endif; ?>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- /#tabDocentes -->

<?php /* ── Tab: por UC ──────────────────────────────────────────── */ ?>
<div class="tab-pane fade" id="tabUcs">
<div class="card shadow-sm mb-4">
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" id="tblUcAdmin" style="font-size:.82rem">
      <thead>
        <tr class="">
          <th>Unidade Curricular</th>
          <th style="width:11em">Área Científica</th>
          <th style="width:0;display:none">_area_raw</th>
          <th style="width:8em">Plano</th>
          <th style="width:0;display:none">_plano_raw</th>
          <th class="text-center" style="width:5em">Sel.</th>
          <th class="text-center" style="width:5em">Média</th>
        </tr>
        <tr class="filter-row" style="background:#fff">
          <th>
            <div class="input-group input-group-sm">
              <div class="input-group-prepend">
                <span class="input-group-text bg-white border-right-0 pe-1">
                  <i class="fas fa-search fa-xs text-muted"></i>
                </span>
              </div>
              <input type="text" id="ucFilterNome" class="form-control border-left-0"
                     placeholder="Pesquisar UC…">
            </div>
          </th>
          <th colspan="2">
            <select id="ucFilterArea" class="form-control form-control-sm">
              <option value="">Todas as áreas</option>
              <?php
              $areasUnicas2 = array();
              foreach ($ucExplorerRaw as $u) {
                  if (!in_array($u['area'], $areasUnicas2)) $areasUnicas2[] = $u['area'];
              }
              sort($areasUnicas2);
              foreach ($areasUnicas2 as $a): ?>
              <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
              <?php endforeach; ?>
            </select>
          </th>
          <th colspan="2">
            <input type="text" id="ucFilterPlano" class="form-control form-control-sm"
                   placeholder="Plano…">
          </th>
          <th>
            <select id="ucFilterSel" class="form-control form-control-sm">
              <option value="">Todas</option>
              <option value="sim">Com seleções</option>
              <option value="nao">Sem seleções</option>
            </select>
          </th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($ucExplorerRaw as $u):
        $cls = $u['n_sel'] > 0 ? 'row-clickable' : '';
        $avgCls = $u['avg_rank'] >= 4 ? 'success' : ($u['avg_rank'] >= 3 ? 'warning' : 'secondary');
      ?>
        <tr class="<?= $cls ?>"
            style="<?= $u['n_sel'] > 0 ? 'cursor:pointer' : '' ?>"
            <?php if ($u['n_sel'] > 0): ?>
            data-codigo="<?= htmlspecialchars($u['codigo']) ?>"
            data-uc="<?= htmlspecialchars($u['uc']) ?>"
            data-bs-toggle="modal" data-bs-target="#modalUc"
            <?php endif; ?>>
          <td class="align-middle">
            <?php if ($u['n_sel'] > 0): ?>
            <span class="text-primary" style="font-weight:500"><?= htmlspecialchars($u['uc']) ?></span>
            <?php else: ?>
            <?= htmlspecialchars($u['uc']) ?>
            <?php endif; ?>
            <small class="text-muted d-block" style="font-size:.72rem"><?= htmlspecialchars($u['codigo']) ?></small>
          </td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($u['area']) ?></td>
          <td style="display:none"><?= htmlspecialchars($u['area']) ?></td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($u['ocorrencia']) ?></td>
          <td style="display:none"><?= htmlspecialchars($u['ocorrencia']) ?></td>
          <td class="align-middle text-center">
            <?= $u['n_sel'] > 0 ? '<span class="badge badge-primary">' . $u['n_sel'] . '</span>' : '<span class="text-muted">—</span>' ?>
          </td>
          <td class="align-middle text-center">
            <?php if ($u['n_sel'] > 0): ?>
            <span class="badge badge-<?= $avgCls ?>"><?= $u['avg_rank'] ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- /#tabUcs -->

</div><!-- /.tab-content -->

<?php /* ── Modal: preferências de um docente ───────────────────── */ ?>
<div class="modal fade" id="modalDocente" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="modalDocenteTitulo">Preferências</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body p-0" id="modalDocenteBody" style="max-height:65vh;overflow-y:auto"></div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('CLOSE') ?></button>
        <a id="btnAdminEditar" href="#" class="btn btn-warning btn-sm">
          <i class="fas fa-edit me-1"></i>Editar resposta
        </a>
      </div>
    </div>
  </div>
</div>

<?php /* ── Modal: docentes que seleccionaram uma UC ──────────────── */ ?>
<div class="modal fade" id="modalUc" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="modalUcTitulo">Docentes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body p-0" id="modalUcBody"></div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    // ── DataTable docentes ────────────────────────────────────────
    $('#tblDocentes').DataTable({
        paging: false, order: [[0, 'asc']],
        autoWidth: false,
        language: { search: '', searchPlaceholder: 'Pesquisar docente…',
                    info: '_TOTAL_ docentes', infoFiltered: '(de _MAX_)',
                    zeroRecords: 'Sem resultados' },
        dom: '<"px-3 pt-3 pb-2"f>t',
        columnDefs: [
            { targets: [2,3,4], orderable: false },
            { targets: [0], width: '40%' },
            { targets: [1], width: '22%' },
            { targets: [2], width: '8%' },
            { targets: [3], width: '12%' },
            { targets: [4], width: '12%' }
        ]
    });

    // ── DataTable UCs (com filtros no topo) ───────────────────────
    var tblUcAdmin = $('#tblUcAdmin').DataTable({
        paging: true, pageLength: 25,
        autoWidth: false,
        orderCellsTop: true, order: [[0, 'asc']],
        dom: 't<"d-flex align-items-center justify-content-between px-3 py-2"ip>',
        columnDefs: [
            { targets: [2,4], visible: false, searchable: true },
            { targets: [5,6], orderable: false },
            { targets: [0], width: '40%' },
            { targets: [1], width: '22%' },
            { targets: [3], width: '20%' },
            { targets: [5], width: '8%' },
            { targets: [6], width: '8%' }
        ],
        language: { info: '_START_–_END_ de _TOTAL_ UCs', infoFiltered: '(de _MAX_)',
                    paginate: { previous: '‹', next: '›' },
                    zeroRecords: 'Sem resultados' }
    });

    $('#ucFilterNome').on('keyup', function () {
        tblUcAdmin.column(0).search(this.value).draw();
    });
    $('#ucFilterArea').on('change', function () {
        var v = this.value;
        tblUcAdmin.column(2).search(v ? ('^' + $.fn.dataTable.util.escapeRegex(v) + '$') : '', true, false).draw();
    });
    $('#ucFilterPlano').on('keyup', function () {
        tblUcAdmin.column(4).search(this.value).draw();
    });

    // Filtro "com/sem selecções"
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'tblUcAdmin') return true;
        var v = $('#ucFilterSel').val();
        if (!v) return true;
        var n = parseInt(data[5]) || 0;
        return v === 'sim' ? n > 0 : n === 0;
    });
    $('#ucFilterSel').on('change', function () { tblUcAdmin.draw(); });

    // ── Dados para modais ─────────────────────────────────────────
    var prefsMap    = <?= json_encode($todasPrefsMap,  JSON_UNESCAPED_UNICODE) ?>;
    var ucDocMap    = <?= json_encode($ucDocentesMap,  JSON_UNESCAPED_UNICODE) ?>;

    var rankColors = ['','danger','secondary','warning','primary','success'];
    var stars = function(r) {
        return '<span class="badge badge-' + rankColors[r] + ' px-2">'
               + '★'.repeat(r) + '☆'.repeat(5-r) + ' ' + r + '</span>';
    };

    // Modal docente
    $(document).on('click', 'tr[data-bs-target="#modalDocente"]', function () {
        var id   = $(this).data('id');
        var nome = $(this).data('nome');
        $('#modalDocenteTitulo').text(nome);
        $('#btnAdminEditar').attr('href', 'index.php?admin_editar=' + id);
        var prefs = prefsMap[id] || [];
        if (!prefs.length) {
            $('#modalDocenteBody').html('<p class="p-3 text-muted">Sem preferências submetidas.</p>');
            return;
        }
        var html = '<table class="table table-sm mb-0" style="font-size:.82rem">'
                 + '<thead class=""><tr><th>UC</th><th style="width:10em">Área</th>'
                 + '<th style="width:7em">Plano</th><th class="text-center" style="width:8em">Preferência</th></tr></thead><tbody>';
        prefs.forEach(function (p) {
            html += '<tr><td>' + p.uc + '<small class="text-muted d-block" style="font-size:.72rem">' + p.codigo + '</small></td>'
                  + '<td style="font-size:.78rem">' + p.area + '</td>'
                  + '<td style="font-size:.78rem">' + p.ocorrencia + '</td>'
                  + '<td class="text-center">' + stars(p.rank) + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#modalDocenteBody').html(html);
    });

    // Modal UC
    $(document).on('click', 'tr[data-bs-target="#modalUc"]', function () {
        var codigo = $(this).data('codigo');
        var uc     = $(this).data('uc');
        $('#modalUcTitulo').text(uc);
        var docentes = ucDocMap[codigo] || [];
        if (!docentes.length) { $('#modalUcBody').html('<p class="p-3 text-muted">Nenhum docente seleccionou esta UC.</p>'); return; }
        var html = '<table class="table table-sm mb-0" style="font-size:.83rem">'
                 + '<thead class=""><tr><th>Docente</th><th class="text-center" style="width:8em">Preferência</th></tr></thead><tbody>';
        docentes.forEach(function (d) {
            html += '<tr><td>' + d.nome + '</td><td class="text-center">' + stars(d.rank) + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#modalUcBody').html(html);
    });
});
</script>

<?php endif; // $adminEditTarget ?>

<?php else: ?>
<?php /* ═══════ VISTA UTILIZADOR ══════════════════════════════════ */ ?>

<?php /* ── Identificação ─────────────────────────────────────────── */ ?>
<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex align-items-center" style="gap:2rem;font-size:.88rem">
      <span><span class="text-muted">Código:</span> <strong><?= htmlspecialchars($idDocente) ?></strong></span>
      <span><span class="text-muted">Nome:</span> <strong><?= htmlspecialchars($nomeDoc) ?></strong></span>
      <span><span class="text-muted">Email:</span> <strong><?= htmlspecialchars($emailuser) ?></strong></span>
    </div>
  </div>
</div>

<?php if (!$temPrefs || $modoEdicao): ?>
<?php /* ── Formulário (primeira vez ou edição aprovada) ─────────── */ ?>
<?php if ($modoEdicao): ?>
<div class="alert alert-info py-2 mb-3" style="font-size:.84rem">
  <i class="fas fa-edit me-1"></i>
  Edição aprovada — pode actualizar as suas preferências. As preferências anteriores serão substituídas.
</div>
<?php else: ?>
<div class="alert alert-secondary py-2 mb-3" style="font-size:.84rem">
  <i class="fas fa-info-circle me-1"></i>
  Indique, para o <strong>máximo de 10 UCs</strong>, a sua preferência de 1 (menor) a 5 (maior).
</div>
<?php endif; ?>
<?php renderForm($todasUcs, $prefIndex, $modoEdicao ? 'editar' : 'submeter', $modoEdicao); ?>

<?php else: ?>
<?php /* ── Ver preferências submetidas ──────────────────────────── */ ?>

<?php /* Estado do pedido de edição */ ?>
<?php if ($podeEditar): ?>
<div class="alert alert-success py-2 mb-3 d-flex align-items-center" style="font-size:.84rem">
  <i class="fas fa-unlock-alt me-2"></i>
  <span class="mr-auto">Edição <strong>aprovada</strong> pelo secretariado. Pode actualizar as suas preferências.</span>
  <a href="index.php?editar=1" class="btn btn-success btn-sm ms-3">
    <i class="fas fa-edit me-1"></i><?= t('SERVDOC_EDIT_NOW') ?>
  </a>
</div>
<?php elseif ($pedidoPendente): ?>
<div class="alert alert-warning py-2 mb-3 d-flex align-items-center" style="font-size:.84rem">
  <i class="fas fa-clock me-2"></i>
  <span class="mr-auto">
    Pedido de edição enviado em <?= substr($pedidoEdicao['pedido_em'], 0, 16) ?>. Aguarda aprovação.
  </span>
  <form method="post" class="ml-3 mb-0">
    <input type="hidden" name="_acao" value="cancelar_pedido">
    <button type="submit" class="btn btn-outline-warning btn-sm">
      <i class="fas fa-times me-1"></i><?= t('SERVDOC_CANCEL_REQ') ?>
    </button>
  </form>
</div>
<?php else: ?>
<div class="d-flex align-items-center mb-3" style="gap:10px">
  <span class="badge badge-success py-1 px-2" style="font-size:.8rem">
    <i class="fas fa-check me-1"></i>Preferências submetidas
  </span>
  <button class="btn btn-outline-secondary btn-sm ms-auto"
          data-bs-toggle="modal" data-bs-target="#modalSolicitarEdicao">
    <i class="fas fa-edit me-1"></i><?= t('SERVDOC_EDIT_REQ') ?>
  </button>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
  <div class="card-header py-2">
    <strong>As minhas preferências</strong>
    <span class="badge badge-secondary ms-2"><?= count($prefActuais) ?> UCs</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" style="font-size:.83rem">
      <thead class="">
        <tr>
          <th>Unidade Curricular</th>
          <th style="width:10em">Área Científica</th>
          <th style="width:4em">Ano</th>
          <th style="width:8em" class="text-center">Preferência</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($prefActuais as $p): ?>
        <tr>
          <td class="align-middle">
            <?= htmlspecialchars($p['uc'] ?? $p['codigo_uc']) ?>
            <small class="text-muted d-block" style="font-size:.72rem"><?= htmlspecialchars($p['codigo_uc']) ?></small>
          </td>
          <td class="align-middle" style="font-size:.78rem"><?= htmlspecialchars($p['area'] ?? '') ?></td>
          <td class="align-middle text-center"><?= htmlspecialchars($p['ano'] ?? '') ?></td>
          <td class="align-middle text-center">
            <?php
            $r = (int)$p['rank'];
            $stars = str_repeat('★', $r) . str_repeat('☆', 5 - $r);
            $cls = $r >= 4 ? 'success' : ($r >= 3 ? 'warning' : 'secondary');
            ?>
            <span class="badge badge-<?= $cls ?>"><?= $stars ?> <?= $r ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ── Modal: solicitar edição ─────────────────────────────── */ ?>
<div class="modal fade" id="modalSolicitarEdicao" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="_acao" value="solicitar_edicao">
        <div class="modal-header">
          <h5 class="modal-title">Solicitar edição de preferências</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">
            O secretariado receberá uma notificação e abrirá a edição quando possível.
          </p>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Motivo <small class="text-muted">(opcional)</small></label>
            <textarea name="motivo" class="form-control" rows="2"
                      placeholder="Ex: engano na selecção, nova UC disponível…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('CANCEL') ?></button>
          <button type="submit" class="btn btn-primary"><?= t('SUBMIT') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; // $modoEdicao / $temPrefs ?>
<?php endif; // admin / utilizador ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
