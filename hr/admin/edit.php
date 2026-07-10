<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');
require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

$id  = $_REQUEST['id']  ?? null;
$id1 = $_REQUEST['id1'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// Retrieve Language
$sites    = ['en' => 'en', 'pt' => 'pt'];
$language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if (!isset($sites[$language])) $language = 'en';
include "../lang/lang." . $sites[$language] . ".php";

// ── Carregar registo da BD ────────────────────────────────────────
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$q = $pdo->prepare(
    'SELECT * FROM infodeqb_rds_colaborador
     INNER JOIN infodeqb_rds_registo  ON infodeqb_rds_colaborador.codigo  = infodeqb_rds_registo.codigo
     INNER JOIN infodeqb_rds_grupo    ON infodeqb_rds_registo.grupo        = infodeqb_rds_grupo.grupoid
     LEFT  JOIN infodeqb_rds_responsaveis ON infodeqb_rds_registo.responsavel = infodeqb_rds_responsaveis.codigo
     WHERE infodeqb_rds_colaborador.codigo = ? AND infodeqb_rds_registo.autoid = ? AND infodeqb_rds_registo.deleted = 0'
);
$q->execute([$id, $id1]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) { header("Location: detail.php?id=$id"); exit; }

// Data do estado atual
$dataestado = null;
switch ($data['status']) {
    case 'Novo':     $dataestado = $data['dataregisto']; break;
    case 'Ativo':    $dataestado = $data['dataativo'];   break;
    case 'Inativo':  $dataestado = $data['datainativo']; break;
    case 'Pendente': $dataestado = $data['datacica'];    break;
}

// ── Dados de referência ──────────────────────────────────────────
$rowsResp  = $pdo->query('SELECT * FROM infodeqb_rds_responsaveis WHERE Codigo != 0 ORDER BY respespaco')->fetchAll(PDO::FETCH_ASSOC);
$rowsGrupo = $pdo->query('SELECT * FROM infodeqb_rds_grupo ORDER BY orderid')->fetchAll(PDO::FETCH_ASSOC);
$rowsCat   = $pdo->query('SELECT * FROM infodeqb_rds_categoria ORDER BY categoriaid')->fetchAll(PDO::FETCH_ASSOC);
$grupoCatMap = getGrupoCategoriasMap($pdo);
$gabRows   = $pdo->query('SELECT * FROM infodeqb_rds_gabinetes ORDER BY edificio, piso, nomegab')->fetchAll(PDO::FETCH_ASSOC);

// ── Processar POST ───────────────────────────────────────────────
$WorkrespError = null;
$erroduplicado = null;
$status        = $data['status'];  // default: mantém estado atual
$statusValidos = ['Novo','Pendente','Ativo','Inativo'];

// Registo Pendente: o SIGARRA já foi contactado — só permitir ao admin
// alterar o estado, para evitar incongruências com o pedido em curso.
$lockEdit = ($data['status'] === 'Pendente');

if (!empty($_POST) && $lockEdit) {

    // ── Registo Pendente: única alteração permitida é o estado ────────
    $novoStatus = isset($_POST['status']) ? $_POST['status'] : $data['status'];
    if (in_array($novoStatus, $statusValidos) && $novoStatus !== $data['status']) {
        $status = $novoStatus;
        $tsCol  = null;
        if ($status === 'Ativo')   $tsCol = 'dataativo';
        if ($status === 'Inativo') $tsCol = 'datainativo';

        $tsNow = date('Y-m-d H:i:s');
        if ($tsCol) {
            $pdo->prepare("UPDATE infodeqb_rds_registo SET status=?, $tsCol=?, notif_pendente=0 WHERE autoid=?")
                ->execute([$status, $tsNow, $id1]);
        } else {
            $pdo->prepare("UPDATE infodeqb_rds_registo SET status=?, notif_pendente=0 WHERE autoid=?")
                ->execute([$status, $id1]);
        }
        $data['status'] = $status;
        $lockEdit = ($status === 'Pendente');

        echo "<div class='alert alert-success' role='alert'>Estado atualizado com sucesso.</div>";
        echo "<meta http-equiv='refresh' content='2;URL=detail.php?id=" . urlencode($id) . "'>";
    }

} elseif (!empty($_POST)) {

    $codigo           = $_POST['codigo'];
    $nome             = $_POST['nome'];
    $email            = $_POST['email'];
    $emailalt         = $_POST['emailalt'];
    $telefone         = phoneFromPost();
    $unidade          = $_POST['unidade'];
    $posto            = $_POST['workplace'];
    $extensao         = $_POST['extension'];
    $datainicio       = date('Y-m-d', strtotime($_POST['datainicio']));
    $datafim          = date('Y-m-d', strtotime($_POST['datafim']));
    $responsavel      = $_POST['responsavel'];
    $outroresponsavel = $_POST['outroresponsavel'];
    $acessodeq_post   = isset($_POST['acessodeq']) ? $_POST['acessodeq'] : 0;
    $grupo            = $_POST['grupo'];
    $categoria        = $_POST['categoria'];
    $curso            = ($grupo == '2' || $grupo == '3') ? ($_POST['curso'] ?? '') : '';

    // Permitir mudança de estado directamente pelo admin
    $novoStatus = $_POST['status'] ?? $data['status'];
    if (in_array($novoStatus, $statusValidos)) {
        $status = $novoStatus;
    }

    // Construir strings de acessos
    $result         = '';
    $acessosid      = null;
    $acessosid_unique = '';
    if (isset($_POST['acessos'])) {
        $sth = $pdo->prepare('SELECT nomegab, deqid FROM infodeqb_rds_gabinetes WHERE deqid = ?');
        foreach ($_POST['acessos'] as $acessoption) {
            $sth->execute([$acessoption]);
            $sth1 = $sth->fetch(PDO::FETCH_ASSOC);
            if ($sth1) {
                $result   .= $sth1['nomegab'] . ';';
                $acessosid .= $sth1['deqid'] . ';';
            }
        }
        $acessosid_array  = array_unique(array_filter(explode(';', $acessosid)));
        $acessosid_unique = implode('; ', $acessosid_array);
    }

    // Validar responsável
    $validar = true;
    if ($responsavel != 0) {
        $outroresponsavel = 0;
    } elseif (empty($outroresponsavel)) {
        $WorkrespError = $lang['ERR_WORK_RESP'];
        $validar = false;
    }

    if ($validar) {
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->beginTransaction();

            // Guardar datafim anterior para detetar renovação em registo Ativo
            $datafimAntigo = $data['datafim'];

            $pdo->prepare(
                'UPDATE infodeqb_rds_colaborador SET nome=:nome, email=:email, emailalt=:emailalt, telefone=:telefone WHERE codigo=:codigo'
            )->execute([':nome'=>$nome,':email'=>$email,':emailalt'=>$emailalt,':telefone'=>$telefone,':codigo'=>$codigo]);

            $pdo->prepare(
                'UPDATE infodeqb_rds_registo SET codigo=:codigo, datainicio=:datainicio, datafim=:datafim,
                 responsavel=:responsavel, outroresponsavel=:outroresponsavel, status=:status,
                 acessodeq=:acessodeq, grupo=:grupo, categoria=:categoria,
                 acessos=:acessos, acessosid=:acessosid, curso=:curso,
                 local_trabalho=:posto, extensao=:extensao, unidade=:unidade
                 WHERE autoid=:id1'
            )->execute([
                ':codigo'           => $codigo,
                ':datainicio'       => $datainicio,
                ':datafim'          => $datafim,
                ':responsavel'      => $responsavel,
                ':outroresponsavel' => $outroresponsavel,
                ':status'           => $status,
                ':acessodeq'        => $acessodeq_post,
                ':grupo'            => $grupo,
                ':categoria'        => $categoria,
                ':acessos'          => $result,
                ':acessosid'        => $acessosid_unique,
                ':curso'            => $curso,
                ':id1'              => $id1,
                ':posto'            => $posto,
                ':extensao'         => $extensao,
                ':unidade'          => $unidade,
            ]);

            $pdo->commit();

            // Actualizar timestamps conforme mudança de estado
            if ($status !== $data['status']) {
                $tsNow = date('Y-m-d H:i:s');
                $tsCol = null;
                if ($status === 'Ativo')    $tsCol = 'dataativo';
                if ($status === 'Inativo')  $tsCol = 'datainativo';
                if ($status === 'Pendente') $tsCol = 'datacica';
                if ($tsCol) {
                    $pdo->prepare("UPDATE infodeqb_rds_registo SET $tsCol=? WHERE autoid=?")
                        ->execute([$tsNow, $id1]);
                }

                // P10: activating — inactivate substitui_registo if exists
                if ($status === 'Ativo' && $data['status'] !== 'Ativo') {
                    $chkSubst2 = $pdo->prepare('SELECT substitui_registo FROM infodeqb_rds_registo WHERE autoid=?');
                    $chkSubst2->execute([$id1]);
                    $substRow2 = $chkSubst2->fetch(PDO::FETCH_ASSOC);
                    if ($substRow2 && $substRow2['substitui_registo']) {
                        $pdo->prepare('UPDATE infodeqb_rds_registo SET status="Inativo", datainativo=? WHERE autoid=?')
                            ->execute([date('Y-m-d H:i:s'), (int)$substRow2['substitui_registo']]);
                    }
                }
            }

            // Actualizar tabela relacional de acessos
            if (isset($_POST['acessos'])) {
                $labsPost = array_values(array_unique(array_filter(
                    array_map('trim', (array)$_POST['acessos'])
                )));
                $gabMapAdmin = getGabMap($pdo);
                setRegistoAcessos($pdo, (int)$id1, $labsPost, $gabMapAdmin);
            }

            // Detetar alterações relevantes num registo Ativo
            if ($data['status'] === 'Ativo' && $status === 'Ativo') {
                // Normalizar labs para comparação (ordenar, sem espaços)
                $_normLabs = function ($s) {
                    $p = array_unique(array_filter(array_map('trim', preg_split('/[\s;,]+/', $s))));
                    sort($p);
                    return implode(';', $p);
                };
                $mudouDatas = ($datafim    !== $datafimAntigo)
                           || ($datainicio !== $data['datainicio']);
                $labsAntigos = array_filter(array_map('trim', preg_split('/[\s;,]+/', $data['acessosid'] ?? '')));
                $labsNovos   = isset($_POST['acessos']) ? array_values(array_unique(array_filter(array_map('trim', (array)$_POST['acessos'])))) : $labsAntigos;
                $mudouLabs   = $_normLabs(implode(';', $labsNovos)) !== $_normLabs(implode(';', $labsAntigos))
                            || ((int)$acessodeq_post !== (int)$data['acessodeq']);

                if ($mudouDatas || $mudouLabs) {
                    // Calcular labs adicionados / removidos (por deqid)
                    $labsAntigosSet = array_unique($labsAntigos);
                    $labsNovosSet   = array_unique($labsNovos);
                    $labsAdd        = array_values(array_diff($labsNovosSet, $labsAntigosSet));
                    $labsRem        = array_values(array_diff($labsAntigosSet, $labsNovosSet));

                    // Resolver gabid (código de sala) a partir de $gabRows — sem duplicados
                    $deqToGabid = array();
                    foreach ($gabRows as $_g) {
                        $deqToGabid[$_g['deqid']] = $_g['gabid'];
                    }
                    $_toGabids = function($deqids) use ($deqToGabid) {
                        $gabids = array();
                        foreach ($deqids as $lid) {
                            $gid = isset($deqToGabid[$lid]) ? $deqToGabid[$lid] : $lid;
                            if (!in_array($gid, $gabids, true)) {
                                $gabids[] = $gid;
                            }
                        }
                        return $gabids;
                    };
                    $labsAddNomes      = $_toGabids($labsAdd);
                    $labsRemNomes      = $_toGabids($labsRem);
                    $acessosNovosNomes = $_toGabids($labsNovosSet);

                    // Porta Norte: tratar Acesso DEQB como gabid regular
                    $oldAcessodeq = (int)$data['acessodeq'];
                    $newAcessodeq = (int)$acessodeq_post;
                    if ($oldAcessodeq !== $newAcessodeq) {
                        if ($newAcessodeq === 1 && !in_array('Porta Norte', $labsAddNomes, true)) {
                            array_unshift($labsAddNomes, 'Porta Norte');
                        } elseif ($newAcessodeq === 0 && !in_array('Porta Norte', $labsRemNomes, true)) {
                            array_unshift($labsRemNomes, 'Porta Norte');
                        }
                    }
                    if ($newAcessodeq === 1 && !in_array('Porta Norte', $acessosNovosNomes, true)) {
                        array_unshift($acessosNovosNomes, 'Porta Norte');
                    }

                    // Apagar pedido secretariado anterior ainda Pendente (substituir pelo novo diff)
                    $pdo->prepare(
                        "DELETE FROM infodeqb_rds_pedido
                         WHERE registo_id=? AND origem='secretariado' AND tipo='alteracao_sigarra' AND status='Pendente'"
                    )->execute([$id1]);

                    // Criar novo pedido com diff completo
                    $dadosJson = json_encode(array(
                        'datafim_novo'           => ($mudouDatas && $datafim !== $datafimAntigo) ? $datafim : null,
                        'datafim_antigo'         => ($mudouDatas && $datafim !== $datafimAntigo) ? $datafimAntigo : null,
                        'acessodeq'              => (int)$acessodeq_post,
                        'acessos'                => $labsNovosSet,
                        'acessos_nomes'          => $acessosNovosNomes,
                        'labs_adicionados'       => $labsAdd,
                        'labs_adicionados_nomes' => $labsAddNomes,
                        'labs_removidos'         => $labsRem,
                        'labs_removidos_nomes'   => $labsRemNomes,
                    ), JSON_UNESCAPED_UNICODE);
                    $dadosAnt = json_encode(array(
                        'datafim'  => $datafimAntigo,
                        'acessosid'=> $data['acessosid'] ?? '',
                        'acessodeq'=> (int)$data['acessodeq'],
                    ), JSON_UNESCAPED_UNICODE);

                    $pdo->prepare(
                        "INSERT INTO infodeqb_rds_pedido
                         (tipo,origem,codigo,registo_id,dados_json,dados_anteriores,status)
                         VALUES ('alteracao_sigarra','secretariado',?,?,?,?,'Pendente')"
                    )->execute([$id, (int)$id1, $dadosJson, $dadosAnt]);

                    $pdo->prepare("UPDATE infodeqb_rds_registo SET notif_pendente=1 WHERE autoid=?")
                        ->execute([$id1]);
                }
            } elseif ($data['status'] === 'Ativo' && $status !== 'Ativo') {
                // Registo saiu de Ativo → limpar flag e pedido pendente
                $pdo->prepare("UPDATE infodeqb_rds_registo SET notif_pendente=0 WHERE autoid=?")
                    ->execute([$id1]);
                $pdo->prepare(
                    "DELETE FROM infodeqb_rds_pedido
                     WHERE registo_id=? AND origem='secretariado' AND tipo='alteracao_sigarra' AND status='Pendente'"
                )->execute([$id1]);
            }

            echo "<div class='alert alert-success' role='alert'>Registo atualizado com sucesso.</div>";
            echo "<meta http-equiv='refresh' content='2;URL=detail.php?id=" . urlencode($id) . "'>";

        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $erroduplicado = '<div class="alert alert-danger">Código já existente.</div>';
            } else {
                echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}

// ── Valores para preencher o form (POST > BD) ─────────────────────
$fNome      = !empty($_POST['nome'])            ? $_POST['nome']            : $data['nome'];
$fEmail     = !empty($_POST['email'])           ? $_POST['email']           : $data['email'];
$fEmailalt  = !empty($_POST['emailalt'])        ? $_POST['emailalt']        : ($data['emailalt'] ?? '');
$fTelefone  = !empty($_POST['telefone_numero'])  ? phoneFromPost()           : ($data['telefone'] ?? '');
$fDatainic  = !empty($_POST['datainicio'])      ? $_POST['datainicio']      : $data['datainicio'];
$fDatafim   = !empty($_POST['datafim'])         ? $_POST['datafim']         : $data['datafim'];
$fUnidade   = !empty($_POST['unidade'])         ? $_POST['unidade']         : ($data['unidade'] ?: 'Outro');
$fWorkplace = !empty($_POST['workplace'])       ? $_POST['workplace']       : ($data['local_trabalho'] ?? '');
$fExtensao  = !empty($_POST['extension'])       ? $_POST['extension']       : ($data['extensao'] ?? '');
$fGrupo     = !empty($_POST['grupo'])           ? (int)$_POST['grupo']      : (int)$data['grupoid'];
$fCategoria = !empty($_POST['categoria'])       ? (int)$_POST['categoria']  : (int)$data['categoria'];
$fCurso     = isset($_POST['curso'])            ? $_POST['curso']           : ($data['curso'] ?? '');
$fResp      = isset($_POST['responsavel'])      ? $_POST['responsavel']     : $data['Codigo'];
$fOutroresp = !empty($_POST['outroresponsavel'])? $_POST['outroresponsavel']: ($data['outroresponsavel'] ?? '');
$fAcessodeq = isset($_POST['acessodeq'])        ? $_POST['acessodeq']       : $data['acessodeq'];

// Checkboxlist de labs
$fAcessos = !empty($_POST) && isset($_POST['acessos'])
    ? (array)$_POST['acessos']
    : getRegistoAcessos($pdo, (int)$id1);
$gab = '';
$gabCurPiso = '';
$gabCurEdificio = '';
foreach ($gabRows as $rowgab) {
    if ($rowgab['piso'] !== $gabCurPiso || $rowgab['edificio'] !== $gabCurEdificio) {
        if ($gabCurPiso !== '') $gab .= '</div>';
        $label = '';
        if ($rowgab['edificio'] !== $gabCurEdificio && stripos($rowgab['piso'], 'Edifício') === false) {
            $label .= '<span class="iq-edificio-label">' . htmlspecialchars($rowgab['edificio']) . '</span>';
        }
        $label .= '<span class="iq-checkgroup-label">' . htmlspecialchars($rowgab['piso']) . '</span>';
        $gab .= '<div class="iq-checkgroup">' . $label;
        $gabCurPiso = $rowgab['piso'];
        $gabCurEdificio = $rowgab['edificio'];
    }
    $checked = in_array($rowgab['deqid'], $fAcessos) ? ' checked' : '';
    $disAttr = $lockEdit ? ' disabled' : '';
    $gab .= '<label><input type="checkbox" name="acessos[]" value="'
          . htmlspecialchars($rowgab['deqid']) . '"' . $checked . $disAttr . '> '
          . htmlspecialchars($rowgab['nomegab']) . '</label>';
}
if ($gabCurPiso !== '') $gab .= '</div>';

$pageTitle = 'Editar Colaborador';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header">
  <h1><i class="fas fa-user-edit fa-sm me-2 text-muted"></i>Editar Colaborador</h1>
</div>

<?php if ($erroduplicado): echo $erroduplicado; endif; ?>
<?php if (!empty($_SESSION['val_info'])): ?>
<div class="alert alert-info alert-dismissible fade show mb-3" role="alert" style="font-size:.85rem">
  <i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($_SESSION['val_info']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php unset($_SESSION['val_info']); endif; ?>

<?php if ($lockEdit): ?>
<div class="alert alert-warning" role="alert">
  <i class="fas fa-exclamation-triangle me-1"></i>
  <strong>Atenção:</strong> Este registo está <strong>Pendente</strong> — o SIGARRA já foi contactado e pode estar a processar o pedido.
  Para evitar inconsistências, os dados ficam bloqueados; a única alteração permitida é o <strong>Estado</strong>.
</div>
<?php endif; ?>

<form action="edit.php?id=<?= htmlspecialchars($id) ?>&id1=<?= htmlspecialchars($id1) ?>"
      method="post" class="iq-form-2col-wrap">
  <input type="hidden" name="id1"   value="<?= htmlspecialchars($id1) ?>">
  <input type="hidden" name="id"    value="<?= htmlspecialchars($id) ?>">

  <div class="iq-form-2col">

  <!-- ══ Coluna esquerda: Dados Pessoais + Período e Afiliação ══ -->
  <div class="iq-form-col">

  <!-- ── Dados Pessoais ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Dados Pessoais</div>
    <div class="row">
      <div class="col-md-4 form-group">
        <label><?= $lang['FEUP_CODE'] ?></label>
        <input name="codigo" type="text" required maxlength="9"
               pattern="^(\d{6}|\d{9})$" class="form-control" id="code" readonly
               style="background:#f8f9fa;cursor:not-allowed;"
               value="<?= htmlspecialchars($data['codigo']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label><?= $lang['NAME'] ?></label>
      <input type="text" name="nome" class="form-control" required readonly
             style="background:#f8f9fa;cursor:not-allowed;"
             value="<?= htmlspecialchars($fNome) ?>">
    </div>
    <div class="row">
      <div class="col-md-6 form-group">
        <label><?= $lang['EMAIL'] ?></label>
        <input type="email" name="email" class="form-control" required id="email" readonly
               style="background:#f8f9fa;cursor:not-allowed;"
               value="<?= htmlspecialchars($fEmail) ?>">
      </div>
      <div class="col-md-6 form-group">
        <label><?= $lang['ALT_EMAIL'] ?></label>
        <input type="email" name="emailalt" class="form-control" <?= $lockEdit ? 'disabled' : '' ?>
               value="<?= htmlspecialchars($fEmailalt) ?>">
      </div>
    </div>
    <div class="row">
      <div class="col-md-5 form-group">
        <label><?= $lang['PHONE'] ?></label>
        <?php renderPhoneInput($fTelefone, '', false, $lockEdit); ?>
      </div>
    </div>
  </div>

  <!-- ── Período e Afiliação ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Período e Afiliação</div>
    <div class="row">
      <div class="col-md-3 form-group">
        <label><?= $lang['BEGIN_DATE'] ?></label>
        <input type="date" name="datainicio" class="form-control" required id="datainicio" <?= $lockEdit ? 'disabled' : '' ?>
               value="<?= htmlspecialchars($fDatainic) ?>">
      </div>
      <div class="col-md-3 form-group">
        <label><?= $lang['END_DATE'] ?></label>
        <input type="date" name="datafim" class="form-control" required id="datafim" <?= $lockEdit ? 'disabled' : '' ?>
               value="<?= htmlspecialchars($fDatafim) ?>">
      </div>
      <div class="col-md-4 form-group">
        <label><?= $lang['UNIT'] ?></label>
        <select name="unidade" class="form-control" id="unidade" required <?= $lockEdit ? 'disabled' : '' ?>>
          <option disabled value="">Escolha uma opção</option>
          <?php foreach (['CEFT','LEPABE','LSRE-LCM','REQUIMTE','Outro'] as $u): ?>
          <option <?= ($fUnidade === $u) ? 'selected' : '' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col-md-7 form-group">
        <label><?= $lang['WORKPLACE'] ?></label>
        <input type="text" name="workplace" class="form-control" id="workplace" <?= $lockEdit ? 'disabled' : '' ?>
               value="<?= htmlspecialchars($fWorkplace) ?>">
      </div>
      <div class="col-md-4 form-group">
        <label><?= $lang['EXTENSION'] ?></label>
        <input type="text" name="extension" class="form-control" id="extension" <?= $lockEdit ? 'disabled' : '' ?>
               value="<?= htmlspecialchars($fExtensao) ?>">
      </div>
    </div>
  </div>

  </div><!-- /.iq-form-col esquerda -->

  <!-- ══ Coluna direita: Classificação + Acessos ══ -->
  <div class="iq-form-col">

  <!-- ── Classificação ── -->
  <div class="iq-form-section">
    <div class="iq-form-section-title">Classificação</div>
    <div class="row">
      <div class="col-md-5 form-group">
        <label><?= $lang['PROGROUP'] ?></label>
        <select id="grupo" name="grupo" class="form-control" required <?= $lockEdit ? 'disabled' : '' ?>>
          <?php foreach ($rowsGrupo as $rg): ?>
          <option value="<?= (int)$rg['grupoid'] ?>" <?= ($fGrupo === (int)$rg['grupoid']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($rg['grupo_pro']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5 form-group" id="cat-col-wrap">
        <label><?= $lang['CATEGORY'] ?></label>
        <select id="category" name="categoria" class="form-control" required <?= $lockEdit ? 'disabled' : '' ?>>
          <?php foreach ($rowsCat as $rc): ?>
          <option value="<?= (int)$rc['categoriaid'] ?>" <?= ($fCategoria === (int)$rc['categoriaid']) ? 'selected' : '' ?>>
            <?= htmlspecialchars(trim($rc['categoria'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div id="curso" <?= in_array($fGrupo, [2,3,7]) ? '' : 'style="display:none"' ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?= $lang['COURSE'] ?></label>
          <input class="form-control" type="text" name="curso" <?= $lockEdit ? 'disabled' : '' ?>
                 value="<?= htmlspecialchars($fCurso) ?>">
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 form-group">
        <label><?= $lang['WORK_RESP'] ?></label>
        <select id="responsavel" name="responsavel" class="form-control" required <?= $lockEdit ? 'disabled' : '' ?>>
          <option disabled value=""><?= $lang['OPTION'] ?></option>
          <?php foreach ($rowsResp as $rr): ?>
          <option value="<?= (int)$rr['Codigo'] ?>" <?= ((string)$fResp === (string)$rr['Codigo']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($rr['respespaco']) ?>
          </option>
          <?php endforeach; ?>
          <option value="0" <?= ($fResp == '0') ? 'selected' : '' ?>><?= $lang['OTHER'] ?></option>
        </select>
      </div>
    </div>

    <div id="outroresp" <?= ($fResp != '0') ? 'style="display:none"' : '' ?>>
      <div class="row">
        <div class="col-md-6 form-group">
          <label><?= $lang['WORK_RESP2'] ?></label>
          <input class="form-control" type="text" name="outroresponsavel" <?= $lockEdit ? 'disabled' : '' ?>
                 value="<?= htmlspecialchars($fOutroresp) ?>">
          <?php if ($WorkrespError): ?>
            <div class="text-danger small mt-1"><?= $WorkrespError ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Estado — editável pelo admin -->
    <div class="row align-items-end mt-1">
      <div class="col-md-auto form-group mb-0">
        <label class="d-block"><?= $lang['STATUS'] ?></label>
        <div class="d-flex align-items-center" style="gap:8px">
          <select name="status" class="form-control form-control-sm" style="width:auto">
            <?php
            $sBadges = ['Novo'=>'badge-info','Pendente'=>'badge-warning','Ativo'=>'badge-success','Inativo'=>'badge-secondary'];
            foreach ($statusValidos as $st):
            ?>
            <option value="<?= $st ?>" <?= ($data['status'] === $st) ? 'selected' : '' ?>>
              <?= $st ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if ($dataestado): ?>
            <small class="text-muted">desde <?= htmlspecialchars(substr($dataestado,0,10)) ?></small>
          <?php endif; ?>
        </div>
        <small class="text-muted" style="font-size:.72rem">
          <i class="fas fa-exclamation-triangle text-warning me-1"></i>Mudar o estado actualiza o timestamp correspondente
        </small>
      </div>
    </div>
  </div>

  <!-- ── Acessos ── -->
  <div class="iq-form-section iq-form-section-fill">
    <div class="iq-form-section-title">Acessos</div>
    <div class="iq-form-inline mb-2">
      <label><?= $lang['DEQ_ACCESS'] ?></label>
      <select id="acessodeq" name="acessodeq" class="form-control" required <?= $lockEdit ? 'disabled' : '' ?>>
        <option value="1" <?= ($fAcessodeq == '1') ? 'selected' : '' ?>><?= $lang['OPT_YES'] ?></option>
        <option value="0" <?= ($fAcessodeq == '0') ? 'selected' : '' ?>><?= $lang['OPT_NO'] ?></option>
      </select>
    </div>
    <div class="form-group iq-fill">
      <label><?= $lang['LAB_ACCESS'] ?></label>
      <div class="iq-checkboxlist" id="acessos-list">
        <?= $gab ?>
      </div>
      <div class="labs-preview mt-2" id="labs-preview-edit"></div>
    </div>
  </div>

  </div><!-- /.iq-form-col direita -->

  </div><!-- /.iq-form-2col -->

  <!-- ── Ações ── -->
  <div class="iq-form-actions">
    <a href="javascript:history.back()" class="btn btn-secondary me-auto">
      <i class="fas fa-arrow-left me-1"></i> Voltar
    </a>
    <button type="submit" name="submit" value="" class="btn btn-primary">
      <?= $lang['SAVE'] ?>
    </button>
  </div>

</form>


<script>
// ── Mapeamento grupo → categorias (carregado da BD) ───────────────
var grupoCategorias    = <?= json_encode($grupoCatMap, JSON_UNESCAPED_UNICODE) ?>;
var gruposSemCategoria = [2, 3, 4, 6, 7];
var allCatOptions      = document.getElementById('category')
    ? document.getElementById('category').innerHTML : '';
var allGrupoOptions    = document.getElementById('grupo')
    ? document.getElementById('grupo').innerHTML : '';

// Categoria actualmente guardada (preservar mesmo que "legacy")
var catAtual = <?= (int)($fCategoria ?? 0) ?>;

function filtrarCategorias(grupoId, preservarAtual) {
    var sel  = document.getElementById('category');
    var wrap = document.getElementById('cat-col-wrap');
    if (!sel) return;

    if (gruposSemCategoria.indexOf(grupoId) !== -1) {
        if (!preservarAtual || !catAtual) {
            if (wrap) wrap.style.display = 'none';
        }
        return;
    }
    if (wrap) wrap.style.display = '';
    var permitidas = grupoCategorias[grupoId] || null;
    sel.innerHTML  = allCatOptions;
    if (!permitidas) return;
    Array.from(sel.options).forEach(function(opt) {
        if (opt.disabled) return;
        var id = parseInt(opt.value);
        if (preservarAtual && id === catAtual) return;
        if (permitidas.indexOf(id) === -1) opt.remove();
    });
}

// ── Preview de labs seleccionados ────────────────────────────────
function atualizarPreviewLabs(listId, previewId) {
    var list = document.getElementById(listId);
    var prev = document.getElementById(previewId);
    if (!list || !prev) return;
    var checks = list.querySelectorAll('input[type=checkbox]:checked');
    if (checks.length === 0) {
        prev.innerHTML = '<span style="font-size:12px;color:#999">Nenhum selecionado</span>';
    } else {
        var html = '';
        checks.forEach(function(c) {
            html += '<span class="lab-tag">' + (c.parentElement.textContent || c.value).trim() + '</span>';
        });
        prev.innerHTML = html;
    }
}

document.addEventListener('DOMContentLoaded', function () {

    var selGrupo = document.getElementById('grupo');
    if (selGrupo) {
        selGrupo.addEventListener('change', function () {
            filtrarCategorias(parseInt(this.value), false);
            var mostrarCurso = [2,3,7].indexOf(parseInt(this.value)) !== -1;
            document.getElementById('curso').style.display = mostrarCurso ? '' : 'none';
            var selDeq = document.getElementById('acessodeq');
            if (parseInt(this.value) === 3) {
                selDeq.value = '0'; selDeq.disabled = true;
            } else {
                selDeq.disabled = false;
            }
        });
        // No carregamento, preservar a categoria actual do registo
        if (selGrupo.value) filtrarCategorias(parseInt(selGrupo.value), true);
    }

    var selResp = document.querySelector('select[name=responsavel]');
    if (selResp) {
        selResp.addEventListener('change', function () {
            var isOutro = this.value === '0';
            document.getElementById('outroresp').style.display = isOutro ? '' : 'none';
            var inp = document.querySelector('input[name=outroresponsavel]');
            if (inp) {
                if (isOutro) { inp.setAttribute('required', 'required'); }
                else         { inp.removeAttribute('required'); }
            }
        });
        // Aplicar no carregamento
        (function() {
            var isOutro = selResp.value === '0';
            var inp = document.querySelector('input[name=outroresponsavel]');
            if (inp) {
                if (isOutro) inp.setAttribute('required', 'required');
                else         inp.removeAttribute('required');
            }
        })();
    }

    var inpCodigo = document.getElementById('code');
    if (inpCodigo) {
        inpCodigo.addEventListener('change', function () {
            var selG = document.getElementById('grupo');
            if (this.value.length === 9) {
                [1,4,5,6].forEach(function(v) {
                    var opt = selG.querySelector('option[value="' + v + '"]');
                    if (opt) opt.remove();
                });
            } else {
                selG.innerHTML = allGrupoOptions;
                if (selG.value) filtrarCategorias(parseInt(selG.value), true);
            }
        });
    }

    var inpInicio = document.getElementById('datainicio');
    var inpFim    = document.getElementById('datafim');
    if (inpInicio && inpFim) {
        inpInicio.addEventListener('change', function () { inpFim.min    = this.value; });
        inpFim.addEventListener('change',    function () { inpInicio.max = this.value; });
    }

    // ── Preview de labs (edit) ────────────────────────────────────
    var labList = document.getElementById('acessos-list');
    if (labList) {
        labList.addEventListener('change', function () {
            atualizarPreviewLabs('acessos-list', 'labs-preview-edit');
        });
        atualizarPreviewLabs('acessos-list', 'labs-preview-edit');
    }
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
